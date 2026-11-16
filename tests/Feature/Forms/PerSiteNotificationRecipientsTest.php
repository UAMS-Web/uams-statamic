<?php

declare(strict_types=1);

use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Site;
use Statamic\Fields\BlueprintRepository;
use Statamic\Forms\Form;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Email;
use Tests\Support\ThrowawayRoot;
use Tests\TestCase;

use function Pest\Laravel\withHeader;
use function Pest\Laravel\withServerVariables;

uses(TestCase::class);

/**
 * A site's `site_overrides` email row, written with config references, reaches
 * the app's notification job in place of the form's own list (UAMS-Web/uams-statamic-archive#3067).
 *
 * `uams-web/aether-multisite-forms` hands core the form as the submitting site
 * sees it, so core's `SendEmails` dispatches that site's email configs to
 * `SendFormNotification`. These submit over HTTP, naming the site the way a page
 * on it does (by the referrer), and read the addresses the array mailer
 * captured. The job's recipient sources, conditional logic and active toggle are
 * exercised on an override row exactly as on a base row.
 *
 * @command  php artisan test --compact tests/Feature/Forms/PerSiteNotificationRecipientsTest.php
 */

/**
 * Handle of the throwaway form each test creates.
 */
const PER_SITE_RECIPIENTS_FORM_HANDLE = 'per_site_recipients_probe';

/**
 * The throwaway forms directory for this process, so a save never writes into
 * the `resources/forms` submodule.
 */
function perSiteRecipientsFormsPath(): string
{
    return storage_path('framework/testing/per-site-recipients/'.ThrowawayRoot::segment());
}

beforeEach(function (): void {
    // Submit from a unique test-net address so this file never shares a per-address
    // form-submit rate-limit bucket with other form tests
    withServerVariables(['REMOTE_ADDR' => '198.51.100.67']);

    // Point the form repository at an empty throwaway directory
    File::deleteDirectory(perSiteRecipientsFormsPath());
    File::ensureDirectoryExists(perSiteRecipientsFormsPath());
    Config::set('statamic.forms.forms', perSiteRecipientsFormsPath());

    // Give the form the fields the recipient sources and conditions read
    app(BlueprintRepository::class)->setFallback(
        'forms.'.PER_SITE_RECIPIENTS_FORM_HANDLE,
        fn () => Blueprint::makeFromFields([
            'name' => ['type' => 'text', 'display' => 'Name'],
            'contact' => ['type' => 'text', 'display' => 'Contact'],
            'department' => ['type' => 'text', 'display' => 'Department'],
        ])
    );

    // Name a base recipient and a per-site recipient by config key
    Config::set('forms.recipients.per_site_probe_base', 'base-office@example.test');
    Config::set('forms.recipients.per_site_probe_health', 'health-office@example.test');
});

afterEach(function (): void {
    File::deleteDirectory(perSiteRecipientsFormsPath());
});

/**
 * Save the probe form: one base email row, and an override row for the
 * `health` site carrying the given email configs.
 *
 * @param  list<array<string, mixed>>  $healthEmail  The `health` row's email configs.
 */
function perSiteRecipientsMakeForm(array $healthEmail): Form
{
    $form = makeForm(PER_SITE_RECIPIENTS_FORM_HANDLE);
    $form->store(false);
    $form->email([
        [
            'name' => 'Base office',
            'to' => '{{ config:forms.recipients.per_site_probe_base }}',
            'subject' => 'Base probe',
        ],
    ]);
    $form->set('site_overrides', [
        ['_id' => 'health-row', 'site' => 'health', 'email' => $healthEmail],
    ]);
    $form->save();

    return $form;
}

/**
 * Submit the probe form from a page on the given site.
 *
 * @param  string  $site  The handle of the site the submitting page is on.
 * @param  array<string, string>  $values  The submitted values.
 */
function perSiteRecipientsSubmit(string $site, array $values = []): void
{
    $url = mustBeString(mustBe(Statamic\Sites\Site::class, Site::get($site), 'site')->absoluteUrl(), 'site url');

    withHeader('referer', rtrim($url, '/').'/a-page')
        ->post('/!/forms/'.PER_SITE_RECIPIENTS_FORM_HANDLE, ['name' => 'Pat', ...$values])
        ->assertRedirect();
}

/**
 * The lowercased To addresses of every captured notification, in send order.
 *
 * @return list<string>
 */
function perSiteRecipientsSentTo(): array
{
    $transport = mustBe(ArrayTransport::class, Mail::getSymfonyTransport(), 'transport');

    /** @var list<string> $recipients */
    $recipients = $transport->messages()
        ->map(function ($sent): string {
            $sent = mustBe(SentMessage::class, $sent, 'sent');
            $message = mustBe(Email::class, $sent->getOriginalMessage(), 'message');

            return implode(',', array_map(fn ($address): string => strtolower($address->getAddress()), $message->getTo()));
        })
        ->values()
        ->all();

    return $recipients;
}

it('emails a site with an override row at the address its config reference names, not the base list', function (): void {
    perSiteRecipientsMakeForm([
        ['name' => 'Health office', 'to' => '{{ config:forms.recipients.per_site_probe_health }}', 'subject' => 'Health probe'],
    ]);

    perSiteRecipientsSubmit('health');

    expect(perSiteRecipientsSentTo())->toBe(['health-office@example.test']);
});

it('emails a site without an override row at the base list', function (): void {
    perSiteRecipientsMakeForm([
        ['name' => 'Health office', 'to' => '{{ config:forms.recipients.per_site_probe_health }}', 'subject' => 'Health probe'],
    ]);

    perSiteRecipientsSubmit('default');

    expect(perSiteRecipientsSentTo())->toBe(['base-office@example.test']);
});

it('reads a field-sourced recipient from an override row', function (): void {
    perSiteRecipientsMakeForm([
        ['name' => 'Submitter', 'to_type' => 'field', 'to_field' => 'contact', 'subject' => 'Field probe'],
    ]);

    perSiteRecipientsSubmit('health', ['contact' => 'submitter@example.test']);

    expect(perSiteRecipientsSentTo())->toBe(['submitter@example.test']);
});

it('routes an override row by its routing rules', function (string $department, string $expected): void {
    perSiteRecipientsMakeForm([
        [
            'name' => 'Routed',
            'to_type' => 'routing',
            'to_routing' => [
                ['field' => 'department', 'operator' => 'equals', 'value' => 'cardiology', 'email' => 'cardio@example.test'],
                ['field' => 'department', 'operator' => 'equals', 'value' => 'oncology', 'email' => 'onco@example.test'],
            ],
            'subject' => 'Routing probe',
        ],
    ]);

    perSiteRecipientsSubmit('health', ['department' => $department]);

    expect(perSiteRecipientsSentTo())->toBe([$expected]);
})->with([
    'cardiology' => ['cardiology', 'cardio@example.test'],
    'oncology' => ['oncology', 'onco@example.test'],
]);

it('gates an override row on its conditional logic', function (): void {
    perSiteRecipientsMakeForm([
        [
            'name' => 'Cardiology',
            'to' => 'cardio@example.test',
            'subject' => 'Cardiology',
            'conditional_logic' => ['if' => ['department' => 'cardiology'], 'always_save' => false],
        ],
        [
            'name' => 'Oncology',
            'to' => 'onco@example.test',
            'subject' => 'Oncology',
            'conditional_logic' => ['if' => ['department' => 'oncology'], 'always_save' => false],
        ],
    ]);

    perSiteRecipientsSubmit('health', ['department' => 'cardiology']);

    expect(perSiteRecipientsSentTo())->toBe(['cardio@example.test']);
});

it('skips an override row whose active toggle is off', function (): void {
    perSiteRecipientsMakeForm([
        ['name' => 'Off', 'enabled' => false, 'to' => 'off@example.test', 'subject' => 'Off'],
        ['name' => 'On', 'enabled' => true, 'to' => '{{ config:forms.recipients.per_site_probe_health }}', 'subject' => 'On'],
    ]);

    perSiteRecipientsSubmit('health');

    expect(perSiteRecipientsSentTo())->toBe(['health-office@example.test']);
});
