<?php

declare(strict_types=1);

use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Statamic\Facades\Blueprint;
use Statamic\Fields\BlueprintRepository;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

use function Pest\Laravel\post;

/**
 * Proves a `{{ all_fields }}` tag in an email row's `message` renders through the site's tag
 * (UAMS-Web/uams-statamic-archive#3149, decided on UAMS-Web/uams-statamic-archive#3094).
 *
 * The form settings add-on sends the row's `message` as the notification body and owns the rest
 * of that behavior and its tests. The `all_fields` tag stays in the site, so these two cases stay
 * here (#3072): the table is sent once with no second field list, and a quoted parameter in the
 * stored Bard text reaches the tag. A form is submitted over HTTP and the captured Symfony
 * message is read back.
 *
 * @command  php artisan test --compact tests/Feature/Forms/FormEmailAllFieldsBodyTest.php
 */
uses(TestCase::class);

/**
 * Handle of the throwaway form created for each test.
 */
const FORM_EMAIL_ALL_FIELDS_BODY_HANDLE = 'all_fields_body_probe';

/**
 * Isolated forms directory so the throwaway form never touches resources/forms.
 *
 * @var string Filesystem path to the per-test forms directory.
 */
$formEmailAllFieldsBodyDir = '';

beforeEach(function () use (&$formEmailAllFieldsBodyDir): void {
    // Lift the form-submission throttle, so repeated submissions cannot trip it
    $this->withoutMiddleware(ThrottleRequests::class);

    // Isolate the forms directory so the throwaway form is self-contained
    $formEmailAllFieldsBodyDir = storage_path('framework/testing/forms-'.uniqid('', true));
    File::ensureDirectoryExists($formEmailAllFieldsBodyDir);
    Config::set('statamic.forms.forms', $formEmailAllFieldsBodyDir);

    // A text field, a textarea, an option field and a replicator cover the display branches
    app(BlueprintRepository::class)->setFallback('forms.'.FORM_EMAIL_ALL_FIELDS_BODY_HANDLE, fn () => Blueprint::makeFromFields([
        'name' => ['type' => 'text', 'display' => 'Name'],
        'comments' => ['type' => 'textarea', 'display' => 'Comments'],
        'color' => ['type' => 'select', 'display' => 'Favorite Color', 'options' => ['red' => 'Bright Red', 'blue' => 'Deep Blue']],
        'guests' => [
            'type' => 'replicator',
            'display' => 'Guests',
            'sets' => ['main' => ['sets' => ['guest' => [
                'display' => 'Guest',
                'fields' => [['handle' => 'guest_name', 'field' => ['type' => 'text', 'display' => 'Guest Name']]],
            ]]]],
        ],
    ]));
});

afterEach(function () use (&$formEmailAllFieldsBodyDir): void {
    // Remove the isolated forms directory
    if ($formEmailAllFieldsBodyDir !== '' && File::isDirectory($formEmailAllFieldsBodyDir)) {
        File::deleteDirectory($formEmailAllFieldsBodyDir);
    }
});

/**
 * Build a Bard document holding one paragraph of text per argument.
 *
 * @param  string  ...$paragraphs  The text of each paragraph.
 * @return list<array<string, mixed>>
 */
function formEmailAllFieldsBodyBard(string ...$paragraphs): array
{
    return array_map(
        static fn (string $text): array => ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]],
        array_values($paragraphs)
    );
}

/**
 * Store the form with one email row, submit it, and return the one captured message.
 *
 * @param  array<string, mixed>  $row  The email row's config, merged over a minimal row.
 * @param  array<string, mixed>  $payload  The submitted values.
 * @return Email The captured notification message.
 */
function formEmailAllFieldsBodySend(array $row, array $payload): Email
{
    // Store the form with the one email row under test
    $form = makeForm(FORM_EMAIL_ALL_FIELDS_BODY_HANDLE);
    $form->title('All Fields Body Probe');
    $form->store(false);
    $form->email([array_merge(['to' => 'recipient@example.test', 'subject' => 'Message probe'], $row)]);
    $form->save();

    // Submit the form over HTTP
    post('/!/forms/'.FORM_EMAIL_ALL_FIELDS_BODY_HANDLE, $payload)->assertRedirect();

    // Read back the single captured message
    $transport = mustBe(ArrayTransport::class, Mail::getSymfonyTransport(), 'transport');
    expect($transport->messages())->toHaveCount(1);
    $sent = mustBe(SentMessage::class, $transport->messages()->first(), 'sent');

    return mustBe(Email::class, $sent->getOriginalMessage(), 'message');
}

it('sends the all fields table once and no second field list', function (): void {
    $email = formEmailAllFieldsBodySend(
        ['message' => formEmailAllFieldsBodyBard('{{ all_fields }}')],
        [
            'name' => 'Ada Lovelace',
            'comments' => "Line one\nLine two",
            'color' => 'red',
            'guests' => [
                ['type' => 'guest', 'enabled' => true, 'guest_name' => 'Grace Hopper'],
                ['type' => 'guest', 'enabled' => true, 'guest_name' => 'Alan Turing'],
            ],
        ]
    );

    $html = mustBeString($email->getHtmlBody(), 'html');

    // Exactly one table, and none of the field list's bold label lines
    expect(substr_count($html, '<table'))->toBe(1);
    expect($html)->not->toContain('<b>Name:</b>');

    // Each field's label and display value, as the field list shows them
    expect($html)->toContain('Ada Lovelace');
    expect($html)->toMatch('/Line one<br>\s*Line two/');
    expect($html)->toContain('Bright Red');
    expect($html)->not->toContain('>red<');

    // Replicator rows render as labelled set blocks, not JSON
    expect(substr_count($html, '<b>Guest</b><br>'))->toBe(2);
    expect($html)->toContain('Guest Name: Grace Hopper<br>');
    expect($html)->toContain('Guest Name: Alan Turing<br>');
    expect($html)->not->toContain('&quot;guest_name&quot;');

    // The text part lists each field on its own line
    $text = (string) $email->getTextBody();
    expect($text)->toContain('Name: Ada Lovelace');
    expect($text)->toContain('Favorite Color: Bright Red');
    expect($text)->toContain('Guest Name: Grace Hopper');
});

it('passes all fields parameters through the stored Bard text', function (): void {
    $email = formEmailAllFieldsBodySend(
        ['message' => formEmailAllFieldsBodyBard('{{ all_fields show_empty="true" }}')],
        ['name' => 'Ada Lovelace']
    );

    // The quoted parameter reaches the tag, so the empty comments field is listed too
    expect(mustBeString($email->getHtmlBody(), 'html'))->toContain('Comments');
});
