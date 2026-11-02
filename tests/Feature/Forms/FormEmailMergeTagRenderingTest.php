<?php

declare(strict_types=1);

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Statamic\Facades\Blueprint;
use Statamic\Facades\GlobalSet;
use Statamic\Fields\BlueprintRepository;
use Statamic\Forms\Form;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

use function Pest\Laravel\post;
use function Pest\Laravel\withServerVariables;

/**
 * Renders every custom Gravity-Forms merge tag from issue UAMS-Web/uams-statamic-archive#199 through a real form
 * notification email and asserts each resolves under the synchronous rendering mode
 * (issue UAMS-Web/uams-statamic-archive#706).
 *
 * The email body is a throwaway Antlers view exercising all ten tags; the form is
 * submitted over HTTP so each tag renders inside the submit request (QUEUE_CONNECTION
 * =sync), where request context, submission data, and the email render context are all
 * available.
 *
 * @command  php artisan test --compact tests/Feature/Forms/FormEmailMergeTagRenderingTest.php
 *
 * Mutation: no covered mutable surface on App\Tags\Today (the today tag is a thin
 * Carbon wrapper; --covered-only reports 0 mutants). This is an integration render
 * test for the full merge-tag notification email; the request-context tags
 * (ip/referrer/get_param) are mutation-covered by FormEmailRequestContextTest and
 * the notification mailable by the form settings add-on's own suite.
 */
uses(TestCase::class);

/**
 * Handle of the throwaway form created for this test.
 */
const PEST_FORM_EMAIL_MERGE_TAG_FORM_HANDLE = 'merge_tag_probe';

/**
 * Handle of the global set the {{ global_origin }} tag resolves against.
 */
const PEST_FORM_EMAIL_MERGE_TAG_GLOBAL_HANDLE = 'merge_tag_probe_globals';

/**
 * Isolated forms directory so the throwaway form never touches resources/forms.
 *
 * @var string $pestFormEmailMergeTagFormsDir
 */
$pestFormEmailMergeTagFormsDir = '';

/**
 * Isolated views directory holding the throwaway email body template.
 *
 * @var string $pestFormEmailMergeTagViewsDir
 */
$pestFormEmailMergeTagViewsDir = '';

/**
 * The Antlers email body template exercising every merge tag under audit.
 *
 * Each tag is wrapped in `LABEL=[...]` markers so assertions can pin exact output
 * (including the empty string for origin-less global_origin and the non-quiz
 * probe form's quiz/survey scoring tags).
 *
 * @return string The Antlers template source.
 */
function pestFormEmailMergeTagEmailBodyTemplate(): string
{
    $globalHandle = PEST_FORM_EMAIL_MERGE_TAG_GLOBAL_HANDLE;
    $formHandle = PEST_FORM_EMAIL_MERGE_TAG_FORM_HANDLE;

    return <<<ANTLERS
    IP=[{{ ip }}]
    REF=[{{ referrer }}]
    UTM=[{{ get_param name="utm_source" }}]
    ORIGIN=[{{ global_origin handle="{$globalHandle}" recursive="true" }}]
    TODAY=[{{ today format="Y-m-d" }}]
    ADMIN=[{{ admin_email }}]
    FORMDATA=[{{ form_data handle="{$formHandle}" key="title" }}]
    QUIZ=[{{ quiz:grade }}]
    SURVEY=[{{ survey:score }}]
    {{ all_fields }}
    ANTLERS;
}

beforeEach(function () use (&$pestFormEmailMergeTagFormsDir, &$pestFormEmailMergeTagViewsDir): void {
    // Freeze test-now to a safe midday so the app (UTC) and Statamic display tz
    // (America/Chicago) agree on the calendar day; noon UTC is the same calendar
    // day as Chicago, so {{ today }} can never straddle the UTC date boundary.
    // Set BOTH clocks so whichever the SUT/tag uses is frozen to noon today.
    Carbon::setTestNow(Carbon::now()->startOfDay()->addHours(12));
    CarbonImmutable::setTestNow(CarbonImmutable::now()->startOfDay()->addHours(12));

    // Isolate the forms directory so the throwaway form is self-contained
    $pestFormEmailMergeTagFormsDir = storage_path('framework/testing/forms-'.uniqid('', true));
    File::ensureDirectoryExists($pestFormEmailMergeTagFormsDir);
    Config::set('statamic.forms.forms', $pestFormEmailMergeTagFormsDir);

    // Isolate a views directory and register it as a namespace for the email body
    $pestFormEmailMergeTagViewsDir = storage_path('framework/testing/views-'.uniqid('', true));
    File::ensureDirectoryExists($pestFormEmailMergeTagViewsDir);
    View::addNamespace('probe_email', $pestFormEmailMergeTagViewsDir);

    // Pin the admin email so {{ admin_email }} is deterministic
    Config::set('mail.from.address', 'admin@probe.test');
});

afterEach(function () use (&$pestFormEmailMergeTagFormsDir, &$pestFormEmailMergeTagViewsDir): void {
    // Remove the isolated directories
    foreach ([$pestFormEmailMergeTagFormsDir, $pestFormEmailMergeTagViewsDir] as $dir) {
        if ($dir !== '' && File::isDirectory($dir)) {
            File::deleteDirectory($dir);
        }
    }

    // Delete the throwaway global set so it leaves nothing behind on disk.
    // find() returns the empty GlobalSet contract; delete() lives on the concrete class.
    $globalSet = GlobalSet::find(PEST_FORM_EMAIL_MERGE_TAG_GLOBAL_HANDLE);
    if ($globalSet instanceof Statamic\Globals\GlobalSet) {
        $globalSet->delete();
    }

    // Reset the frozen clocks so other tests see the real time
    Carbon::setTestNow(null);
    CarbonImmutable::setTestNow(null);
});

it('renders every merge tag in a synchronous notification email', function () use (&$pestFormEmailMergeTagViewsDir): void {
    // Known submitter request context to assert against
    $ip = '198.51.100.42';
    $referrer = 'https://referrer.test/source';
    $utmSource = 'merge-tag-probe';
    $submittedName = 'Probe Submitter';

    // Capture today's date the same way the tag will format it (clock is frozen to noon today)
    $todayYmd = CarbonImmutable::now()->format('Y-m-d');

    $formHandle = PEST_FORM_EMAIL_MERGE_TAG_FORM_HANDLE;
    $globalHandle = PEST_FORM_EMAIL_MERGE_TAG_GLOBAL_HANDLE;

    // Give the form an in-memory blueprint so {{ all_fields }} has fields to render.
    // Resolve the singleton repository directly: the Blueprint facade's @method
    // annotation references an unimported `Closure`, which static analysis
    // misresolves to the facade namespace; the repository's typed signature does
    // not. The facade forwards to this same singleton, so behavior is identical.
    app(BlueprintRepository::class)->setFallback('forms.'.$formHandle, fn () => Blueprint::makeFromFields([
        'name' => ['type' => 'text', 'display' => 'Name'],
        'message' => ['type' => 'textarea', 'display' => 'Message'],
    ]));

    // Resolve global_origin against a real, saved (origin-less) global set so it renders empty,
    // not fatal. save() auto-creates a default-site localization with no origin, so origin() is null.
    /** @var Statamic\Globals\GlobalSet $globalSet */
    $globalSet = GlobalSet::make($globalHandle);
    $globalSet->save();

    // Write the email body view exercising all ten merge tags
    File::put($pestFormEmailMergeTagViewsDir.'/body.antlers.html', pestFormEmailMergeTagEmailBodyTemplate());

    // Build the form with a custom Antlers email body view
    $form = makeForm($formHandle);
    $form->title('Merge Tag Probe');
    $form->store(false);
    $form->email([
        'to' => 'recipient@example.test',
        'subject' => 'Merge tag probe',
        'html' => 'probe_email::body',
    ]);
    $form->save();

    // Seed the submitter IP and Referer on the request itself (the live state the
    // capture step reads via request()->ip() / request()->header('Referer'))
    withServerVariables([
        'REMOTE_ADDR' => $ip,
        'HTTP_REFERER' => $referrer,
    ]);

    // Submit the form over HTTP with field values and the utm_source query parameter
    $response = post(
        "/!/forms/{$formHandle}?utm_source={$utmSource}",
        ['name' => $submittedName, 'message' => 'Hello from the probe.']
    );
    $response->assertRedirect();

    // Read the rendered HTML body straight off the captured Symfony message (decoded)
    $transport = mustBe(ArrayTransport::class, Mail::getSymfonyTransport(), 'transport');
    $messages = $transport->messages();
    expect($messages)->toHaveCount(1, 'Exactly one notification email should have been sent.');

    $first = mustBe(SentMessage::class, $messages->first(), 'first');
    $original = mustBe(Email::class, $first->getOriginalMessage(), 'original');
    $body = mustBeString($original->getHtmlBody(), 'body');

    // Request-context tags resolve to the submitter's live values
    expect($body)->toContain("IP=[{$ip}]");
    expect($body)->toContain("REF=[{$referrer}]");
    expect($body)->toContain("UTM=[{$utmSource}]");

    // global_origin resolves against the global set without erroring or leaking (origin-less = empty)
    expect($body)->toContain('ORIGIN=[]');

    // Non-context tags resolve from their own data sources
    expect($body)->toContain("TODAY=[{$todayYmd}]");
    expect($body)->toContain('ADMIN=[admin@probe.test]');
    expect($body)->toContain('FORMDATA=[Merge Tag Probe]');

    // The quiz/survey scoring tags (UAMS-Web/uams-statamic-archive#209, UAMS-Web/uams-statamic-archive#210) resolve against the submission but
    // render empty for this non-quiz/non-survey probe form — it has no quiz or
    // survey fields to score (the tags come from the form settings add-on).
    // Their real scored output through the same confirmation/notification render
    // context is proven by FormScoringAddOnTest; here we only prove
    // they resolve in the synchronous notification pipeline without leaking Antlers.
    expect($body)->toContain('QUIZ=[]');
    expect($body)->toContain('SURVEY=[]');

    // all_fields renders an HTML table containing the submitted value
    expect($body)->toContain('<table');
    expect($body)->toContain($submittedName);

    // No literal Antlers markers should leak into the rendered body
    expect($body)->not->toContain('{{');
});
