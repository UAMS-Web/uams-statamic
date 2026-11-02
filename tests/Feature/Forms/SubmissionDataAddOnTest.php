<?php

declare(strict_types=1);

use Illuminate\Queue\Events\JobQueued;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Statamic\Contracts\Forms\Submission;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Site;
use Statamic\Facades\Stache;
use Statamic\Fields\BlueprintRepository;
use Statamic\Forms\Form;
use Statamic\Forms\SendEmail;
use Statamic\Forms\SendEmails;
use Statamic\Sites\Site as StatamicSite;
use Statamic\Stache\Stores\Store;
use Tests\TestCase;
use UAMSWeb\Aether\Forms\Settings\Support\FormRequestContext;

use function Pest\Laravel\post;
use function Pest\Laravel\withServerVariables;

/**
 * Proves the site's submission data works through the form settings add-on (#265).
 *
 * The listeners that save `_*` metadata with each submission, capture the submitter's
 * request context for queued emails, and warn when a form email is queued without that
 * context moved into `uams-web/aether-form-settings`, behind its `submission_data`
 * switch, which `config/aether-form-settings.php` turns on. These run each behavior
 * through the installed add-on and the site's own configuration, so a release that
 * drops a listener, or a configuration that leaves the switch off, fails here. The
 * listeners' own cases are tested in the add-on.
 *
 * @command  php artisan test --compact tests/Feature/Forms/SubmissionDataAddOnTest.php
 */
uses(TestCase::class);

/**
 * Handle of the throwaway form created for this test.
 */
const SUBMISSION_DATA_ADDON_FORM_HANDLE = 'submission_data_addon_probe';

/**
 * The environment variable the add-on's switch reads.
 */
const SUBMISSION_DATA_ADDON_SWITCH = 'FORM_SETTINGS_SUBMISSION_DATA';

$submissionDataAddOnDirs = [];

/**
 * Isolate the forms and submissions directories, and give the form a blueprint.
 *
 * Run again after the application is refreshed, since a refresh discards the config.
 *
 * @param  array<int, string>  $dirs  The directories made, to remove after the test
 */
function submissionDataAddOnIsolate(array &$dirs): void
{
    // Unique test-net IP keeps this file's submissions out of other tests' rate-limit bucket
    withServerVariables(['REMOTE_ADDR' => '198.51.100.52']);

    // Isolate the forms directory
    $formsDir = storage_path('framework/testing/forms-'.uniqid('', true));
    File::ensureDirectoryExists($formsDir);
    Config::set('statamic.forms.forms', $formsDir);

    // Isolate the submissions store (the booted Stache store, so a runtime config is ignored)
    $subsDir = storage_path('framework/testing/subs-'.uniqid('', true));
    File::ensureDirectoryExists($subsDir);
    Config::set('statamic.forms.submissions', $subsDir);
    /** @var Store $submissionsStore */
    $submissionsStore = Stache::store('form-submissions');
    $submissionsStore->directory($subsDir);

    array_push($dirs, $formsDir, $subsDir);

    app(BlueprintRepository::class)->setFallback('forms.'.SUBMISSION_DATA_ADDON_FORM_HANDLE, fn () => Blueprint::makeFromFields([
        'name' => ['type' => 'text', 'display' => 'Name'],
    ]));
}

/**
 * Make and save the throwaway form, storing its submissions.
 */
function submissionDataAddOnForm(): Form
{
    $form = makeForm(SUBMISSION_DATA_ADDON_FORM_HANDLE);
    $form->title('Submission Data Probe');
    $form->store(true);
    $form->save();

    return $form;
}

beforeEach(function () use (&$submissionDataAddOnDirs): void {
    submissionDataAddOnIsolate($submissionDataAddOnDirs);
});

afterEach(function () use (&$submissionDataAddOnDirs): void {
    foreach ($submissionDataAddOnDirs as $dir) {
        if (File::isDirectory($dir)) {
            File::deleteDirectory($dir);
        }
    }

    $submissionDataAddOnDirs = [];
});

it('saves the submission metadata and the request context through the add-on', function (): void {
    $form = submissionDataAddOnForm();

    // Hold the email job back so the captured request context stays stashed for reading
    Bus::fake([SendEmails::class]);

    // Submit from a page on the health site
    $site = mustBe(StatamicSite::class, Site::get('health'), 'site');
    $siteUrl = mustBeString($site->absoluteUrl(), 'site url');
    withServerVariables([
        'REMOTE_ADDR' => '203.0.113.52',
        'HTTP_REFERER' => rtrim($siteUrl, '/').'/a-page?ref=x',
    ]);
    post('/!/forms/'.SUBMISSION_DATA_ADDON_FORM_HANDLE, ['name' => 'Uhura'], ['User-Agent' => 'SubmissionData/1.0'])
        ->assertRedirect();

    $submission = mustBe(Submission::class, $form->submissions()->last(), 'submission');
    $data = $submission->data()->all();

    // The context the notification job carries is stashed under the submission's id
    $id = $submission->id();
    $context = FormRequestContext::pull(is_scalar($id) ? (string) $id : '');

    // Every metadata key the site stored before the move, with the site named by the site's adapter
    expect(array_intersect_key($data, array_flip(['_form_handle', '_form_title', '_submitted_path', '_site_handle', '_user_agent', '_ip', '_referrer'])))
        ->toBe([
            '_form_handle' => SUBMISSION_DATA_ADDON_FORM_HANDLE,
            '_form_title' => 'Submission Data Probe',
            '_submitted_path' => parse_url(rtrim($siteUrl, '/').'/a-page', PHP_URL_PATH),
            '_site_handle' => 'health',
            '_user_agent' => 'SubmissionData/1.0',
            '_ip' => '203.0.113.52',
            '_referrer' => rtrim($siteUrl, '/').'/a-page?ref=x',
        ])
        ->and(array_keys($data))->toContain('_submitted_url', '_site_name', '_site_url', '_submitted_at')
        ->and($data['name'])->toBe('Uhura');

    // The captured request context names the same submitter and site
    expect($context?->toArray())->toBe([
        'ip' => '203.0.113.52',
        'referrer' => rtrim($siteUrl, '/').'/a-page?ref=x',
        'query' => [],
        'site' => 'health',
    ]);
});

it('logs a warning when a form notification is queued without request context', function (): void {
    $log = Log::spy();

    // The configured send-email job, built outside the submit flow, so it carries no context
    $submission = submissionDataAddOnForm()->makeSubmission();
    /** @var class-string<SendEmail> $jobClass */
    $jobClass = config('statamic.forms.send_email_job');
    $job = new $jobClass($submission, mustBe(StatamicSite::class, Site::default(), 'site'), []);

    // Raise the event Laravel fires when that job reaches a real queue
    event(new JobQueued(connectionName: 'database', queue: null, id: 'job-id', job: $job, payload: '', delay: null));

    $log->shouldHaveReceived('warning')
        ->once()
        ->withArgs(fn ($message): bool => \is_string($message) && str_contains($message, 'request-context merge tags'));
});

it('saves a submission with none of the added metadata and no error when the switch is off', function () use (&$submissionDataAddOnDirs): void {
    $previous = getenv(SUBMISSION_DATA_ADDON_SWITCH);

    // Turn the switch off and boot the application again, since the add-on registers its listeners at boot
    putenv(SUBMISSION_DATA_ADDON_SWITCH.'=false');
    $_ENV[SUBMISSION_DATA_ADDON_SWITCH] = $_SERVER[SUBMISSION_DATA_ADDON_SWITCH] = 'false';

    try {
        $this->refreshApplication();
        submissionDataAddOnIsolate($submissionDataAddOnDirs);

        expect(config('aether-form-settings.features.submission_data'))->toBeFalse();

        $form = submissionDataAddOnForm();
        post('/!/forms/'.SUBMISSION_DATA_ADDON_FORM_HANDLE, ['name' => 'Sulu'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $submission = mustBe(Submission::class, $form->submissions()->last(), 'submission');
        $added = array_filter(array_keys($submission->data()->all()), fn (int|string $key): bool => \in_array($key, ['_form_handle', '_ip', '_site_handle', '_submitted_at'], true));

        expect($submission->get('name'))->toBe('Sulu')
            ->and($added)->toBe([]);
    } finally {
        // Restore the environment the rest of the process boots with
        if ($previous === false) {
            putenv(SUBMISSION_DATA_ADDON_SWITCH);
            unset($_ENV[SUBMISSION_DATA_ADDON_SWITCH], $_SERVER[SUBMISSION_DATA_ADDON_SWITCH]);
        } else {
            putenv(SUBMISSION_DATA_ADDON_SWITCH.'='.$previous);
            $_ENV[SUBMISSION_DATA_ADDON_SWITCH] = $_SERVER[SUBMISSION_DATA_ADDON_SWITCH] = $previous;
        }
    }
});
