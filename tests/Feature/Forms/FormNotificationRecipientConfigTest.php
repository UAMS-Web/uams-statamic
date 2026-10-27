<?php

declare(strict_types=1);

use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Statamic\Facades\Blueprint;
use Statamic\Fields\BlueprintRepository;
use Statamic\Forms\Form;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

use function Pest\Laravel\post;
use function Pest\Laravel\withServerVariables;

/**
 * Proves form notification `to:` values resolve through
 * `config/forms.php` recipients (UAMS-Web/uams-statamic-archive#2745): a submission mails the configured
 * address, changing the config changes the recipient without editing form
 * YAML, and an unset or empty recipient fails the submit with HTTP 500 and
 * sends nothing.
 *
 * @coversNothing
 *
 * @command php artisan test --compact tests/Feature/Forms/FormNotificationRecipientConfigTest.php
 */
uses(TestCase::class);

/**
 * Handle of the throwaway form created for this suite.
 */
const RECIPIENT_CONFIG_FORM_HANDLE = 'recipient_config_probe';

/**
 * Isolated forms directory so the throwaway form never touches resources/forms.
 *
 * @var string Filesystem path to the per-test forms directory.
 */
$recipientConfigFormsDir = '';

beforeEach(function () use (&$recipientConfigFormsDir): void {
    // Submit from a unique test-net IP so this file's submissions never share the
    // per-IP form-submit rate-limit bucket with other form tests.
    withServerVariables(['REMOTE_ADDR' => '198.51.100.45']);

    $recipientConfigFormsDir = storage_path('framework/testing/forms-'.uniqid('', true));
    File::ensureDirectoryExists($recipientConfigFormsDir);
    Config::set('statamic.forms.forms', $recipientConfigFormsDir);

    app(BlueprintRepository::class)->setFallback(
        'forms.'.RECIPIENT_CONFIG_FORM_HANDLE,
        fn () => Blueprint::makeFromFields([
            'name' => ['type' => 'text', 'display' => 'Name'],
        ])
    );
});

afterEach(function () use (&$recipientConfigFormsDir): void {
    if ($recipientConfigFormsDir !== '' && File::isDirectory($recipientConfigFormsDir)) {
        File::deleteDirectory($recipientConfigFormsDir);
    }
});

/**
 * Lowercase To addresses from every captured notification email.
 *
 * @return list<string>
 */
function recipientConfigSentRecipients(): array
{
    $transport = mustBe(ArrayTransport::class, Mail::getSymfonyTransport(), 'transport');

    /** @var list<string> $recipients */
    $recipients = $transport->messages()
        ->map(function ($sent): string {
            $sent = mustBe(SentMessage::class, $sent, 'sent');
            $message = mustBe(Email::class, $sent->getOriginalMessage(), 'message');

            return strtolower($message->getTo()[0]->getAddress());
        })
        ->all();

    return $recipients;
}

/**
 * Build and save the probe form whose To reads a forms.recipients key.
 */
function recipientConfigMakeForm(): Form
{
    $form = makeForm(RECIPIENT_CONFIG_FORM_HANDLE);
    $form->store(false);
    $form->email([
        [
            'name' => 'Role mailbox',
            'to' => '{{ config:forms.recipients.institutional_relations }}',
            'subject' => 'Recipient config probe',
        ],
    ]);
    $form->save();

    return $form;
}

it('sends the notification to the configured recipient address', function (): void {
    Config::set(
        'forms.recipients.institutional_relations',
        'first-role@example.test'
    );
    recipientConfigMakeForm();

    post(
        '/!/forms/'.RECIPIENT_CONFIG_FORM_HANDLE,
        ['name' => 'Pat']
    )->assertRedirect();

    expect(recipientConfigSentRecipients())->toBe(['first-role@example.test']);
});

it('follows a change to the configured recipient without editing the form', function (): void {
    Config::set(
        'forms.recipients.institutional_relations',
        'first-role@example.test'
    );
    recipientConfigMakeForm();

    post(
        '/!/forms/'.RECIPIENT_CONFIG_FORM_HANDLE,
        ['name' => 'Pat']
    )->assertRedirect();

    expect(recipientConfigSentRecipients())->toBe(['first-role@example.test']);

    // Clear the array transport so the second submit is measured alone.
    $transport = mustBe(ArrayTransport::class, Mail::getSymfonyTransport(), 'transport');
    $transport->flush();

    Config::set(
        'forms.recipients.institutional_relations',
        'second-role@example.test'
    );

    post(
        '/!/forms/'.RECIPIENT_CONFIG_FORM_HANDLE,
        ['name' => 'Pat']
    )->assertRedirect();

    expect(recipientConfigSentRecipients())->toBe(['second-role@example.test']);
});

it('rejects the submission with HTTP 500 and sends nothing when the recipient is unset or empty', function (mixed $recipient): void {
    Config::set('forms.recipients.institutional_relations', $recipient);
    recipientConfigMakeForm();

    post(
        '/!/forms/'.RECIPIENT_CONFIG_FORM_HANDLE,
        ['name' => 'Pat']
    )->assertStatus(500);

    expect(recipientConfigSentRecipients())->toBe([]);
})->with([
    'null' => [null],
    'empty string' => [''],
]);
