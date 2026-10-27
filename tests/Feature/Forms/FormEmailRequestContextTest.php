<?php

declare(strict_types=1);

use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Statamic\Forms\Form;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

use function Pest\Laravel\post;
use function Pest\Laravel\withServerVariables;

/**
 * End-to-end proof that request-context merge tags resolve in a form-notification
 * email under the project's synchronous rendering mode (issue UAMS-Web/uams-statamic-archive#706).
 *
 * Submits a form over HTTP with a known submitter IP, Referer header, and
 * ?utm_source query parameter, then asserts the rendered notification subject
 * contains all three values. Because QUEUE_CONNECTION=sync (phpunit.xml), the
 * Statamic\Forms\SendEmail job runs inline in the submit request. The submitter's
 * context is captured from the live request at submit time (CaptureFormRequestContext)
 * and the four tags prefer that captured context during the render (issue UAMS-Web/uams-statamic-archive#830), so the
 * subject resolves to the submitter's values. The async counterpart — driving the same
 * render through a non-sync queue — lives in FormEmailAsyncRequestContextTest.
 *
 * @covers \App\Tags\Ip
 * @covers \App\Tags\Referrer
 * @covers \App\Tags\GetParam
 *
 * Mutation: 100% on the App\Tags request-context tags Ip, Referrer, and GetParam
 * (14 mutants combined, --covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Forms/FormEmailRequestContextTest.php
 */
uses(TestCase::class);

/**
 * Handle of the throwaway form created for this test.
 */
const REQUEST_CONTEXT_FORM_HANDLE = 'request_context_probe';

/**
 * Isolated forms directory so the throwaway form never touches resources/forms.
 *
 * @var string Filesystem path to the per-test forms directory.
 */
$formEmailRequestContextDir = '';

beforeEach(function () use (&$formEmailRequestContextDir): void {
    // Isolate the forms directory so the throwaway form is self-contained
    $formEmailRequestContextDir = storage_path('framework/testing/forms-'.uniqid('', true));
    File::ensureDirectoryExists($formEmailRequestContextDir);
    Config::set('statamic.forms.forms', $formEmailRequestContextDir);
});

afterEach(function () use (&$formEmailRequestContextDir): void {
    // Remove the isolated forms directory
    if ($formEmailRequestContextDir !== '' && File::isDirectory($formEmailRequestContextDir)) {
        File::deleteDirectory($formEmailRequestContextDir);
    }
});

it('resolves request-context tags in a synchronous notification email', function (): void {
    // Known submitter request context to assert against
    $ip = '203.0.113.7';
    $referrer = 'https://referrer.test/landing-page';
    $utmSource = 'plan-test-campaign';

    // Build a form whose notification subject exercises the three request-context tags
    $form = makeForm(REQUEST_CONTEXT_FORM_HANDLE);
    $form->store(false);
    $form->email([
        'to' => 'recipient@example.test',
        'subject' => 'IP={{ ip }} REF={{ referrer }} UTM={{ get_param name="utm_source" }}',
    ]);
    $form->save();

    // Seed the submitter IP and Referer on the request itself (the live state the
    // capture step reads via request()->ip() / request()->header('Referer'))
    withServerVariables([
        'REMOTE_ADDR' => $ip,
        'HTTP_REFERER' => $referrer,
    ]);

    // Submit the form over HTTP with the utm_source query parameter present
    $response = post(
        '/!/forms/'.REQUEST_CONTEXT_FORM_HANDLE."?utm_source={$utmSource}",
        ['name' => 'Pat Submitter']
    );

    // The submission should succeed (redirect back, not a validation/error response)
    $response->assertRedirect();

    // Read the rendered subject straight off the captured Symfony message (decoded)
    $transport = mustBe(ArrayTransport::class, Mail::getSymfonyTransport(), 'transport');
    $messages = $transport->messages();
    expect($messages)->toHaveCount(1, 'Exactly one notification email should have been sent.');

    $sentMessage = mustBe(SentMessage::class, $messages->first(), 'sent message');
    $message = mustBe(Email::class, $sentMessage->getOriginalMessage(), 'message');
    $subject = $message->getSubject();

    // Each request-context tag must have resolved to the submitter's live value
    expect($subject)->toContain("IP={$ip}");
    expect($subject)->toContain("REF={$referrer}");
    expect($subject)->toContain("UTM={$utmSource}");

    // No literal Antlers markers should leak into the rendered subject
    expect($subject)->not->toContain('{{');
});
