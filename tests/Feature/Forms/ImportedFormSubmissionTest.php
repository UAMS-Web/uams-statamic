<?php

declare(strict_types=1);

use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Statamic\Facades\Antlers;
use Statamic\Facades\Form as FormFacade;
use Statamic\Facades\Site;
use Statamic\Facades\Stache;
use Statamic\Forms\Form;
use Statamic\Sites\Site as SiteObject;
use Statamic\Stache\Stores\Store;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Email;
use Tests\Support\FormsSubmodule;
use Tests\TestCase;

use function Pest\Laravel\from;
use function Pest\Laravel\withServerVariables;

/**
 * Proves a form imported from WordPress renders and sends its notification with the
 * `uams-web/statamic-form-settings` add-on installed and no importer change (#263).
 *
 * The form is the stored `mail_processing_center_bulk_mail_request` in the private
 * `resources/forms` submodule, added by the 2025 import of the Mail Processing Center
 * site: its title carries the site-name prefix the importer writes. It is read
 * as stored and never saved, so the test exercises the settings keys the importer
 * wrote: the email row with its recipient role and `{{ all_fields }}` message, and the
 * site assignment.
 *
 * The form's stored Bard confirmation message is not asserted. `v0.1.0` of the add-on
 * never shows a Bard confirmation message; UAMS-Web/statamic-form-settings#22 fixes it
 * after that tag (UAMS-Web/statamic-form-settings#13), so assert it once the site
 * requires a release that carries the fix.
 *
 * @command  php artisan test --compact tests/Feature/Forms/ImportedFormSubmissionTest.php
 */
uses(TestCase::class);

/**
 * Handle of the stored, imported form under test.
 */
const IMPORTED_FORM_SUBMISSION_HANDLE = 'mail_processing_center_bulk_mail_request';

/**
 * Address the test gives the form's recipient role.
 */
const IMPORTED_FORM_SUBMISSION_RECIPIENT = 'mailcenter@example.test';

/**
 * Per-test submissions directory, so a submission never lands in storage/forms.
 *
 * @var string Filesystem path to the per-test submissions directory.
 */
$importedFormSubsDir = '';

beforeEach(function () use (&$importedFormSubsDir): void {
    FormsSubmodule::skipUnlessInitialized();

    // Give each test its own form-submit rate-limit bucket
    withServerVariables(['REMOTE_ADDR' => '198.51.'.random_int(0, 255).'.'.random_int(1, 254)]);

    // Point stored submissions at a throwaway directory
    $importedFormSubsDir = storage_path('framework/testing/subs-'.uniqid('', true));
    File::ensureDirectoryExists($importedFormSubsDir);
    Config::set('statamic.forms.submissions', $importedFormSubsDir);
    /** @var Store $submissionsStore */
    $submissionsStore = Stache::store('form-submissions');
    $submissionsStore->directory($importedFormSubsDir);

    // Give the recipient role the form names a test address
    Config::set('forms.recipients.mail_processing_center', IMPORTED_FORM_SUBMISSION_RECIPIENT);
});

afterEach(function () use (&$importedFormSubsDir): void {
    // Remove the throwaway submissions directory
    if ($importedFormSubsDir !== '' && File::isDirectory($importedFormSubsDir)) {
        File::deleteDirectory($importedFormSubsDir);
    }
});

/**
 * The stored, imported form under test.
 */
function importedFormSubmissionForm(): Form
{
    return mustBe(Form::class, FormFacade::find(IMPORTED_FORM_SUBMISSION_HANDLE), 'imported form');
}

/**
 * A submission that fills every field the imported blueprint requires.
 *
 * @return array<string, mixed>
 */
function importedFormSubmissionPayload(): array
{
    return [
        'request_date' => '2026-10-23',
        'first_name' => 'Pat',
        'last_name' => 'Rivera',
        'email_address' => 'pat.rivera@example.test',
        'phone' => '501-555-0100',
        'mail_slot' => '512',
        'department' => 'Facilities',
        'fund' => '1000',
        'cost_center' => 'CC-2040',
        'job_name_description' => 'Open house postcards',
        'job_requirements' => ['Folds', 'Addressing'],
        'date_required' => '2026-11-06',
        'approximate_count' => 400,
    ];
}

it('renders the imported form with each of its fields on the site it is assigned to', function (): void {
    // Render the form tag and the site's field partials, as a page on its site would
    Site::setCurrent('mail_processing_center');
    $html = (string) Antlers::parse(
        '{{ form:create in="'.IMPORTED_FORM_SUBMISSION_HANDLE.'" }}{{ partial:vendor/statamic/forms/fields }}{{ /form:create }}',
        [],
        true
    );

    // The form posts to its own submit route
    expect($html)->toContain('action="'.url('/!/forms/'.IMPORTED_FORM_SUBMISSION_HANDLE).'"');

    // Each imported field draws an input under its stored handle
    foreach (array_keys(importedFormSubmissionPayload()) as $handle) {
        expect($html)->toMatch('/name="'.preg_quote($handle, '/').'(\[\])?"/');
    }
});

it('sends the imported notification to the address its recipient role names', function (): void {
    $form = importedFormSubmissionForm();

    // Submit the form over HTTP from a page on its site, so the add-on's job and middleware
    // run as on a live submission and the site gate admits it
    $siteUrl = mustBeString(mustBe(SiteObject::class, Site::get('mail_processing_center'), 'site')->absoluteUrl(), 'site URL');
    from($siteUrl)->post('/!/forms/'.IMPORTED_FORM_SUBMISSION_HANDLE, importedFormSubmissionPayload())
        ->assertRedirect()
        ->assertSessionHasNoErrors()
        ->assertSessionHas('form.'.IMPORTED_FORM_SUBMISSION_HANDLE.'.submission_created', true);

    // Exactly one notification is sent, to the address the recipient role resolves to
    $transport = mustBe(ArrayTransport::class, Mail::getSymfonyTransport(), 'transport');
    expect($transport->messages())->toHaveCount(1);
    $email = mustBe(Email::class, mustBe(SentMessage::class, $transport->messages()->first(), 'sent')->getOriginalMessage(), 'message');
    expect(array_map(static fn ($address): string => $address->getAddress(), $email->getTo()))->toBe([IMPORTED_FORM_SUBMISSION_RECIPIENT]);

    // The subject is the stored row's, and the body is its all fields table
    $row = mustBeArray(mustBeArray($form->email(), 'email rows')[0] ?? null, 'email row');
    expect($email->getSubject())->toBe($row['subject']);
    $html = mustBeString($email->getHtmlBody(), 'html');
    expect(substr_count($html, '<table'))->toBe(1);
    expect($html)->toContain('Open house postcards')
        ->and($html)->toContain('CC-2040')
        ->and($html)->toContain('pat.rivera@example.test');
});
