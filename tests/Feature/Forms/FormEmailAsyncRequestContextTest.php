<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Statamic\Contracts\Forms\Submission;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Stache;
use Statamic\Fields\BlueprintRepository;
use Statamic\Forms\Form;
use Statamic\Stache\Stores\Store;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

use function Pest\Laravel\post;
use function Pest\Laravel\withServerVariables;

/**
 * Proves the four request-context merge tags resolve to the *submitter's* values when a
 * form-notification email is rendered by a queue worker out of the submit request — the
 * async counterpart to FormEmailRequestContextTest's synchronous proof (issue UAMS-Web/uams-statamic-archive#830).
 *
 * The test switches QUEUE_CONNECTION to `database`, submits a form over HTTP with a known
 * submitter IP, Referer header, ?utm_source query parameter, and current site, then drains
 * the queue with `queue:work` so the SendFormNotification job is serialized to the database
 * and handled later — with no submitter request in scope. Because the submitter context was
 * captured at submit time (CaptureFormRequestContext), threaded onto the job, and re-activated
 * around the render (SendFormNotification::handle), the tags render the submitter's values, not
 * the worker's default/empty state.
 *
 * It also asserts the captured context never leaks into the submission record or `{{ all_fields }}`.
 *
 * @covers \App\Support\Forms\FormRequestContext
 * @covers \App\Listeners\CaptureFormRequestContext
 * @covers \UAMSWeb\FormSettings\Jobs\SendFormNotification
 * @covers \App\Tags\Ip
 * @covers \App\Tags\Referrer
 * @covers \App\Tags\GetParam
 *
 * @command  php artisan test --compact tests/Feature/Forms/FormEmailAsyncRequestContextTest.php
 */
uses(TestCase::class);

/**
 * Handle of the throwaway form created for this test.
 */
const ASYNC_REQUEST_CONTEXT_FORM_HANDLE = 'async_request_context_probe';

/**
 * Isolated forms directory so the throwaway form never touches resources/forms.
 *
 * @var string Filesystem path to the per-test forms directory.
 */
$formEmailAsyncFormsDir = '';

/**
 * Isolated views directory holding the throwaway email body template.
 *
 * @var string Filesystem path to the per-test views directory.
 */
$formEmailAsyncViewsDir = '';

/**
 * Isolated submissions directory so stored submissions never touch storage/forms.
 *
 * @var string Filesystem path to the per-test submissions directory.
 */
$formEmailAsyncSubmissionsDir = '';

beforeEach(function () use (&$formEmailAsyncFormsDir, &$formEmailAsyncViewsDir, &$formEmailAsyncSubmissionsDir): void {
    // Isolate the forms directory so the throwaway form is self-contained
    $formEmailAsyncFormsDir = storage_path('framework/testing/forms-'.uniqid('', true));
    File::ensureDirectoryExists($formEmailAsyncFormsDir);
    Config::set('statamic.forms.forms', $formEmailAsyncFormsDir);

    // Isolate the submissions directory so a stored submission never leaks into the
    // shared storage/forms tree. Submissions resolve their path from the
    // `form-submissions` Stache store (booted once), so a runtime config override is
    // ignored — repoint the store directly. The config is set too for any reader of it.
    $formEmailAsyncSubmissionsDir = storage_path('framework/testing/subs-'.uniqid('', true));
    File::ensureDirectoryExists($formEmailAsyncSubmissionsDir);
    Config::set('statamic.forms.submissions', $formEmailAsyncSubmissionsDir);
    /** @var Store $submissionsStore */
    $submissionsStore = Stache::store('form-submissions');
    $submissionsStore->directory($formEmailAsyncSubmissionsDir);

    // Isolate a views directory and register it as a namespace for the email body
    $formEmailAsyncViewsDir = storage_path('framework/testing/views-'.uniqid('', true));
    File::ensureDirectoryExists($formEmailAsyncViewsDir);
    View::addNamespace('async_probe_email', $formEmailAsyncViewsDir);

    // Render form emails through a real (non-sync) queue so the job is serialized and
    // handled by a worker out of the submit request — the condition under test
    Config::set('queue.default', 'database');

    // Create the database queue table inline (faster than running the migrator); the
    // worker reads/writes its serialized job payload here
    if (! Schema::hasTable('jobs')) {
        Schema::create('jobs', function (Illuminate\Database\Schema\Blueprint $table): void {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
    }
});

afterEach(function () use (&$formEmailAsyncFormsDir, &$formEmailAsyncViewsDir, &$formEmailAsyncSubmissionsDir): void {
    // Remove the isolated directories
    foreach ([$formEmailAsyncFormsDir, $formEmailAsyncViewsDir, $formEmailAsyncSubmissionsDir] as $dir) {
        if ($dir !== '' && File::isDirectory($dir)) {
            File::deleteDirectory($dir);
        }
    }

    // Defensively remove any submission that still landed under the default
    // storage/forms path for this test's fixed handle (belt-and-suspenders).
    $defaultSubmissionsDir = storage_path('forms/'.ASYNC_REQUEST_CONTEXT_FORM_HANDLE);
    if (File::isDirectory($defaultSubmissionsDir)) {
        File::deleteDirectory($defaultSubmissionsDir);
    }

    // Drop the database queue table so it leaves nothing behind
    Schema::dropIfExists('jobs');
});

it('resolves request-context tags rendered by a queue worker out of the submit request', function () use (&$formEmailAsyncViewsDir): void {
    // Known submitter request context to assert against — all distinct from the worker's
    // default state (REMOTE_ADDR 127.0.0.1, no Referer, no query string)
    $ip = '203.0.113.7';
    $referrer = 'https://referrer.test/async-landing';
    $utmSource = 'async-context-campaign';
    $submittedName = 'Async Submitter';

    $formHandle = ASYNC_REQUEST_CONTEXT_FORM_HANDLE;

    // Give the form an in-memory blueprint so {{ all_fields }} has fields to render
    app(BlueprintRepository::class)->setFallback('forms.'.$formHandle, fn () => Blueprint::makeFromFields([
        'name' => ['type' => 'text', 'display' => 'Name'],
    ]));

    // Write an email body that exercises the request-context tags AND {{ all_fields }},
    // so a leak of the captured context into the field table would be visible
    File::put($formEmailAsyncViewsDir.'/body.antlers.html', <<<'ANTLERS'
    IP=[{{ ip }}]
    REF=[{{ referrer }}]
    UTM=[{{ get_param name="utm_source" }}]
    {{ all_fields }}
    ANTLERS);

    // Build the form: subject exercises the tags too, so both Antlers contexts are covered
    $form = makeForm($formHandle);
    $form->store(false);
    $form->email([
        'to' => 'recipient@example.test',
        'subject' => 'IP={{ ip }} REF={{ referrer }} UTM={{ get_param name="utm_source" }}',
        'html' => 'async_probe_email::body',
    ]);
    $form->save();

    // Seed the submitter IP and Referer on the live request the capture step reads
    withServerVariables([
        'REMOTE_ADDR' => $ip,
        'HTTP_REFERER' => $referrer,
    ]);

    // Submit the form over HTTP with the utm_source query parameter present
    $response = post(
        "/!/forms/{$formHandle}?utm_source={$utmSource}",
        ['name' => $submittedName]
    );
    $response->assertRedirect();

    // The job must have been queued, not run inline: no email sent yet, one row in `jobs`
    $transport = mustBe(ArrayTransport::class, Mail::getSymfonyTransport(), 'transport');
    expect($transport->messages())->toHaveCount(0, 'On the database connection the email must not send during the submit request.');
    expect(DB::table('jobs')->count())->toBe(1, 'The send-email job should be queued to the database, not run inline.');

    // Replace the live request with a fresh, request-less one to mirror what a real
    // queue worker sees: no submitter IP/Referer/query (REMOTE_ADDR defaults to
    // 127.0.0.1). This is the condition that makes live-state reads wrong and the
    // captured context essential.
    app()->instance('request', Request::create('http://worker.localhost/'));

    // Drain the queue: deserialize and handle the job out of the submit request
    Artisan::call('queue:work', [
        '--once' => true,
        '--stop-when-empty' => true,
    ]);

    // Exactly one notification email should have been sent by the worker
    $messages = $transport->messages();
    expect($messages)->toHaveCount(1, 'The queue worker should have sent exactly one notification email.');

    $sentMessage = mustBe(SentMessage::class, $messages->first(), 'sent message');
    $message = mustBe(Email::class, $sentMessage->getOriginalMessage(), 'message');

    // The subject (parseConfig context) resolved to the submitter's captured values
    $subject = mustBeString($message->getSubject(), 'subject');
    expect($subject)->toContain("IP={$ip}");
    expect($subject)->toContain("REF={$referrer}");
    expect($subject)->toContain("UTM={$utmSource}");

    // The body (addData view context) resolved to the submitter's captured values
    $body = mustBeString($message->getHtmlBody(), 'body');
    expect($body)->toContain("IP=[{$ip}]");
    expect($body)->toContain("REF=[{$referrer}]");
    expect($body)->toContain("UTM=[{$utmSource}]");

    // The worker's own (default/empty) state must NOT have leaked in
    expect($subject)->not->toContain('127.0.0.1');
    expect($body)->not->toContain('127.0.0.1');

    // No literal Antlers markers should leak into the rendered output
    expect($subject)->not->toContain('{{');
    expect($body)->not->toContain('{{');
});

it('does not leak the captured request context into all_fields or the submission record', function () use (&$formEmailAsyncViewsDir): void {
    // Distinctive submitter context whose presence in the field table would betray a leak
    $ip = '198.51.100.99';
    $referrer = 'https://leak-check.test/source';
    $utmSource = 'leak-check-campaign';
    $submittedName = 'Leak Check Submitter';

    $formHandle = ASYNC_REQUEST_CONTEXT_FORM_HANDLE;

    // Blueprint with a single submitted field; {{ all_fields }} should render only this
    app(BlueprintRepository::class)->setFallback('forms.'.$formHandle, fn () => Blueprint::makeFromFields([
        'name' => ['type' => 'text', 'display' => 'Name'],
    ]));

    // Body renders ONLY {{ all_fields }} — no context tags — so any captured value that
    // appears must have leaked into the submission's field data
    File::put($formEmailAsyncViewsDir.'/fields-only.antlers.html', '{{ all_fields }}');

    $form = makeForm($formHandle);
    $form->store(true);
    $form->email([
        'to' => 'recipient@example.test',
        'subject' => 'Leak check submission',
        'html' => 'async_probe_email::fields-only',
    ]);
    $form->save();

    // Seed submitter context on the live request
    withServerVariables([
        'REMOTE_ADDR' => $ip,
        'HTTP_REFERER' => $referrer,
    ]);

    // Submit; the job queues to the database, then drain it out of the request
    $response = post(
        "/!/forms/{$formHandle}?utm_source={$utmSource}",
        ['name' => $submittedName]
    );
    $response->assertRedirect();

    // Replace the live request with a fresh, request-less one to mirror what a real
    // queue worker sees (no submitter IP/Referer/query), exactly as the first test does.
    // Forgetting the binding without replacing it would leave the worker render unable to
    // resolve request() and the job would fail before sending — masking the leak check.
    app()->instance('request', Request::create('http://worker.localhost/'));
    Artisan::call('queue:work', [
        '--once' => true,
        '--stop-when-empty' => true,
    ]);

    // Read the rendered all_fields body
    $transport = mustBe(ArrayTransport::class, Mail::getSymfonyTransport(), 'transport');
    $sentMessage = mustBe(SentMessage::class, $transport->messages()->first(), 'sent message');
    $message = mustBe(Email::class, $sentMessage->getOriginalMessage(), 'message');
    $body = mustBeString($message->getHtmlBody(), 'body');

    // The submitted field renders, but none of the captured context values appear
    expect($body)->toContain($submittedName);
    expect($body)->not->toContain($ip);
    expect($body)->not->toContain($referrer);
    expect($body)->not->toContain($utmSource);

    // The stored submission record carries the submitted field — and none of the keys the
    // UAMS-Web/uams-statamic-archive#830 email-render context mechanism would have leaked.
    $submission = mustBe(Submission::class, $form->submissions()->first(), 'submission');
    /** @var array<string, mixed> $data */
    $data = $submission->data()->all();
    expect($data)->toHaveKey('name');
    expect($data)->not->toHaveKey('ip');
    expect($data)->not->toHaveKey('referrer');
    expect($data)->not->toHaveKey('get_param');
    expect($data)->not->toHaveKey('global_origin');
    expect($data)->not->toHaveKey('request_context');

    // The raw stored *editor* values must not contain any captured value. The `_*` system
    // metadata that AppendSubmissionMetadata (UAMS-Web/uams-statamic-archive#207) deliberately stores — including `_ip` and
    // `_referrer` — is a separate, intended mechanism, so it is excluded here: this asserts the
    // UAMS-Web/uams-statamic-archive#830 email-render context did not leak into the submission, not that the values never
    // appear in any field.
    $editorData = collect($data)->reject(fn ($value, string $key): bool => str_starts_with($key, '_'))->all();
    $serialized = json_encode($editorData, JSON_THROW_ON_ERROR);
    expect($serialized)->not->toContain($ip);
    expect($serialized)->not->toContain($referrer);
    expect($serialized)->not->toContain($utmSource);
});
