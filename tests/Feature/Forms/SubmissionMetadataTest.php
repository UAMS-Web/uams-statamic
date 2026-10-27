<?php

declare(strict_types=1);

use App\Listeners\AppendSubmissionMetadata;
use App\Support\Forms\FormRequestContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\URL;
use Statamic\Contracts\Forms\Submission;
use Statamic\Events\SubmissionCreating;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Site;
use Statamic\Facades\Stache;
use Statamic\Fields\BlueprintRepository;
use Statamic\Forms\Form;
use Statamic\Forms\SendEmails;
use Statamic\Sites\Site as StatamicSite;
use Statamic\Stache\Stores\Store;
use Tests\TestCase;

use function Pest\Laravel\post;
use function Pest\Laravel\withServerVariables;

/**
 * Proves the AppendSubmissionMetadata listener auto-appends the system `_*` context fields
 * to every form submission's stored YAML (UAMS-Web/uams-statamic-archive#207): which form, where, which site, and
 * spam-triage context (user agent, IP, referrer, timestamp), without clobbering editor fields.
 *
 * @covers \App\Listeners\AppendSubmissionMetadata
 *
 * @command  php artisan test --compact tests/Feature/Forms/SubmissionMetadataTest.php
 */
uses(TestCase::class);

/**
 * Handle of the throwaway form created for this test.
 */
const SUBMISSION_METADATA_FORM_HANDLE = 'submission_metadata_probe';

$submissionMetadataFormsDir = '';
$submissionMetadataSubsDir = '';

beforeEach(function () use (&$submissionMetadataFormsDir, &$submissionMetadataSubsDir): void {
    // Unique test-net IP keeps this file's submissions out of other tests' rate-limit bucket.
    withServerVariables(['REMOTE_ADDR' => '198.51.100.51']);

    // Isolate the forms directory.
    $submissionMetadataFormsDir = storage_path('framework/testing/forms-'.uniqid('', true));
    File::ensureDirectoryExists($submissionMetadataFormsDir);
    Config::set('statamic.forms.forms', $submissionMetadataFormsDir);

    // Isolate the submissions store (the booted Stache store, so a runtime config is ignored).
    $submissionMetadataSubsDir = storage_path('framework/testing/subs-'.uniqid('', true));
    File::ensureDirectoryExists($submissionMetadataSubsDir);
    Config::set('statamic.forms.submissions', $submissionMetadataSubsDir);
    /** @var Store $submissionsStore */
    $submissionsStore = Stache::store('form-submissions');
    $submissionsStore->directory($submissionMetadataSubsDir);

    app(BlueprintRepository::class)->setFallback('forms.'.SUBMISSION_METADATA_FORM_HANDLE, fn () => Blueprint::makeFromFields([
        'name' => ['type' => 'text', 'display' => 'Name'],
    ]));
});

afterEach(function () use (&$submissionMetadataFormsDir, &$submissionMetadataSubsDir): void {
    foreach ([$submissionMetadataFormsDir, $submissionMetadataSubsDir] as $dir) {
        if ($dir !== '' && File::isDirectory($dir)) {
            File::deleteDirectory($dir);
        }
    }

    $defaultSubs = storage_path('forms/'.SUBMISSION_METADATA_FORM_HANDLE);
    if (File::isDirectory($defaultSubs)) {
        File::deleteDirectory($defaultSubs);
    }
});

it('appends the system metadata fields to a stored submission', function (): void {
    $form = makeForm(SUBMISSION_METADATA_FORM_HANDLE);
    $form->title('Submission Metadata Probe');
    $form->store(true);
    $form->save();

    // Seed the submitter request context the listener reads.
    withServerVariables([
        'REMOTE_ADDR' => '203.0.113.50',
        'HTTP_REFERER' => 'https://uams.test/clinic/contact?ref=x',
    ]);

    post('/!/forms/'.SUBMISSION_METADATA_FORM_HANDLE, ['name' => 'Pat'], ['User-Agent' => 'MetaTest/2.0'])
        ->assertRedirect();

    $submission = mustBe(Submission::class, $form->submissions()->last(), 'submission');
    $data = $submission->data()->all();

    // Which form.
    expect($data['_form_handle'])->toBe(SUBMISSION_METADATA_FORM_HANDLE);
    expect($data['_form_title'])->toBe('Submission Metadata Probe');
    // Where (derived from the Referer — the page the form was on).
    expect($data['_submitted_url'])->toBe('https://uams.test/clinic/contact?ref=x');
    expect($data['_submitted_path'])->toBe('/clinic/contact');
    // Which site.
    expect($data['_site_handle'])->toBe('default');
    expect($data['_site_name'])->toBeString();
    expect($data['_site_url'])->toBeString();
    // Spam-triage context.
    expect($data['_user_agent'])->toBe('MetaTest/2.0');
    expect($data['_ip'])->toBe('203.0.113.50');
    expect($data['_referrer'])->toBe('https://uams.test/clinic/contact?ref=x');
    expect($data['_submitted_at'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/');

    // The editor field is preserved.
    expect($data['name'])->toBe('Pat');
});

it('does not overwrite a pre-existing key that collides with a metadata field', function (): void {
    $form = makeForm(SUBMISSION_METADATA_FORM_HANDLE);
    $form->store(true);
    $form->save();

    // A submission that already carries an `_ip` value before the listener runs.
    $submission = $form->makeSubmission();
    $submission->data(['name' => 'Pat', '_ip' => 'preset-sentinel']);
    $submission->save();

    $stored = mustBe(Submission::class, $form->submissions()->last(), 'stored');
    $data = $stored->data()->all();

    // The collision guard preserved the existing value, but other metadata still appended.
    expect($data['_ip'])->toBe('preset-sentinel');
    expect($data['_form_handle'])->toBe(SUBMISSION_METADATA_FORM_HANDLE);
});

it('falls back to the request URL/path when no Referer is present', function (): void {
    $form = makeForm(SUBMISSION_METADATA_FORM_HANDLE);
    $form->store(true);
    $form->save();

    // No HTTP_REFERER seeded (beforeEach sets only REMOTE_ADDR).
    post('/!/forms/'.SUBMISSION_METADATA_FORM_HANDLE, ['name' => 'Pat'])->assertRedirect();

    $submission = mustBe(Submission::class, $form->submissions()->last(), 'submission');
    $data = $submission->data()->all();

    expect($data['_submitted_url'])->toContain('/!/forms/'.SUBMISSION_METADATA_FORM_HANDLE);
    expect($data['_submitted_path'])->toBe('/!/forms/'.SUBMISSION_METADATA_FORM_HANDLE);
    expect($data['_referrer'])->toBe('');
});

it('ignores a malformed Referer, falling back to a safe URL and path', function (): void {
    $form = makeForm(SUBMISSION_METADATA_FORM_HANDLE);
    $form->store(true);
    $form->save();

    // A garbage, client-controlled Referer must not land in _submitted_url / _submitted_path.
    withServerVariables(['REMOTE_ADDR' => '198.51.100.51', 'HTTP_REFERER' => 'not a url with spaces']);
    post('/!/forms/'.SUBMISSION_METADATA_FORM_HANDLE, ['name' => 'Pat'])->assertRedirect();

    $submission = mustBe(Submission::class, $form->submissions()->last(), 'submission');
    $data = $submission->data()->all();

    expect($data['_submitted_url'])->toContain('/!/forms/'.SUBMISSION_METADATA_FORM_HANDLE);
    expect($data['_submitted_path'])->toBe('/!/forms/'.SUBMISSION_METADATA_FORM_HANDLE);
    // _referrer still records the raw header verbatim (it is literally the Referer).
    expect($data['_referrer'])->toBe('not a url with spaces');
});

/**
 * Run the listener directly against an unsaved submission, with the given request bound.
 *
 * @return array<string, mixed> The submission data after the listener ran.
 */
function submissionMetadataHandleWith(Request $request): array
{
    app()->instance('request', $request);

    $submission = makeForm(SUBMISSION_METADATA_FORM_HANDLE)->makeSubmission();
    $submission->data(['name' => 'Pat']);

    (new AppendSubmissionMetadata)->handle(new SubmissionCreating($submission));

    /** @var array<string, mixed> */
    return $submission->data()->all();
}

it('ignores an event whose submission is not a submission', function (): void {
    $event = new SubmissionCreating(new stdClass);

    (new AppendSubmissionMetadata)->handle($event);

    expect($event->submission)->toEqual(new stdClass);
});

it('records empty strings when the request has no user agent, IP or Referer', function (): void {
    $request = Request::create('https://uams.test/!/forms/'.SUBMISSION_METADATA_FORM_HANDLE, 'POST');
    $request->headers->remove('User-Agent');
    $request->server->remove('REMOTE_ADDR');

    $data = submissionMetadataHandleWith($request);

    expect($data['_user_agent'])->toBe('')
        ->and($data['_ip'])->toBe('')
        ->and($data['_referrer'])->toBe('')
        ->and($data['_submitted_url'])->toBe('https://uams.test/!/forms/'.SUBMISSION_METADATA_FORM_HANDLE);
});

it('records the root path when the Referer has no path', function (): void {
    $request = Request::create('https://uams.test/!/forms/'.SUBMISSION_METADATA_FORM_HANDLE, 'POST');
    $request->headers->set('Referer', 'https://uams.test');

    $data = submissionMetadataHandleWith($request);

    expect($data['_submitted_url'])->toBe('https://uams.test')
        ->and($data['_submitted_path'])->toBe('/');
});

it('records the site of the page the form was submitted from', function (string $siteHandle): void {
    $form = makeForm(SUBMISSION_METADATA_FORM_HANDLE);
    $form->store(true);
    $form->save();

    // Hold the email job back so the captured request context stays stashed for reading
    Bus::fake([SendEmails::class]);

    // Submit from a page on the site, which is how the add-on names the submission's site
    $site = mustBe(StatamicSite::class, Site::get($siteHandle), 'site');
    $siteUrl = mustBeString($site->absoluteUrl(), 'site url');
    withServerVariables([
        'REMOTE_ADDR' => '198.51.100.51',
        'HTTP_REFERER' => rtrim($siteUrl, '/').'/a-page',
    ]);
    post('/!/forms/'.SUBMISSION_METADATA_FORM_HANDLE, ['name' => 'Pat'])->assertRedirect();

    $submission = mustBe(Submission::class, $form->submissions()->last(), 'submission');
    $data = $submission->data()->all();

    // The context the notification job carries is stashed under the submission's id
    $id = $submission->id();
    $context = FormRequestContext::pull(is_scalar($id) ? (string) $id : '');

    // The app's site keys and the job's context name the same site as the add-on's `_site`
    expect([
        '_site' => $data['_site'] ?? null,
        '_site_handle' => $data['_site_handle'] ?? null,
        '_site_name' => $data['_site_name'] ?? null,
        '_site_url' => $data['_site_url'] ?? null,
        'context site' => $context?->siteHandle,
    ])->toBe([
        '_site' => $siteHandle,
        '_site_handle' => $siteHandle,
        '_site_name' => $site->name(),
        '_site_url' => $siteUrl,
        'context site' => $siteHandle,
    ]);
})->with(['default', 'health']);

it('names the site from the Referer when the submission carries no recorded site', function (?string $recorded): void {
    // A request from a page on the health site, with the URL generator reading it
    $healthUrl = mustBeString(mustBe(StatamicSite::class, Site::get('health'), 'site')->absoluteUrl(), 'site url');
    $request = Request::create('https://uams.test/!/forms/'.SUBMISSION_METADATA_FORM_HANDLE, 'POST');
    $request->headers->set('Referer', rtrim($healthUrl, '/').'/a-page');
    URL::setRequest($request);

    // A submission saved outside the submit route, without a usable recorded site
    app()->instance('request', $request);
    $submission = makeForm(SUBMISSION_METADATA_FORM_HANDLE)->makeSubmission();
    $submission->data(array_filter(['name' => 'Pat', '_site' => $recorded]));

    (new AppendSubmissionMetadata)->handle(new SubmissionCreating($submission));

    expect($submission->get('_site_handle'))->toBe('health');
})->with(['no recorded site' => null, 'an unknown recorded site' => 'no_such_site']);
