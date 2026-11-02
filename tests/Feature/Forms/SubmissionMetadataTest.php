<?php

declare(strict_types=1);

use App\Support\Forms\MultisiteSubmissionSite;
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
use Statamic\Forms\SendEmails;
use Statamic\Sites\Site as StatamicSite;
use Statamic\Stache\Stores\Store;
use Tests\TestCase;
use UAMSWeb\Aether\Forms\Settings\Listeners\AppendSubmissionMetadata;
use UAMSWeb\Aether\Forms\Settings\Support\FormRequestContext;

use function Pest\Laravel\post;
use function Pest\Laravel\withServerVariables;

/**
 * Proves a submission's site is the page's site the multisite forms add-on records (#265).
 *
 * The form settings add-on saves the `_*` metadata with each submission and captures
 * the request context for queued emails (UAMS-Web/uams-statamic-archive#207), and asks
 * its `SubmissionSite` contract which site to record. Its default names the current
 * site, which is always the default site here, since a form posts under the default
 * site's prefix. The site binds {@see MultisiteSubmissionSite}, which reads the `_site`
 * the multisite forms add-on records, so these two cases stay in the site with that
 * adapter. The metadata listener's other cases moved to the add-on with it.
 *
 * @covers \App\Support\Forms\MultisiteSubmissionSite
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

    // The site keys and the job's context name the same site as the multisite add-on's `_site`
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
