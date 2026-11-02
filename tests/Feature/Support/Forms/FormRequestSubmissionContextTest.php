<?php

declare(strict_types=1);

use App\Support\Forms\FormRequestContext;
use App\Support\Forms\FormRequestSubmissionContext;
use Statamic\Contracts\Forms\Submission;
use Statamic\Facades\Site;
use Statamic\Sites\Site as StatamicSite;
use Tests\TestCase;
use UAMSWeb\Aether\Forms\Settings\Contracts\SubmissionContext;
use UAMSWeb\Aether\Forms\Settings\Jobs\SendFormNotification;

/**
 * Proves the site's submission context hands the form settings add-on's notification
 * job the request context captured at submit time, and re-activates it for the send
 * (UAMS-Web/uams-statamic-archive#830, #263).
 *
 * @command  php artisan test --compact tests/Feature/Support/Forms/FormRequestSubmissionContextTest.php
 */
uses(TestCase::class);

covers(FormRequestSubmissionContext::class);

/**
 * A submission double that reports the given id.
 *
 * @param  mixed  $id  The id the submission reports.
 */
function formRequestSubmissionContextSubmission(mixed $id): Submission
{
    $submission = Mockery::mock(Submission::class);
    $submission->shouldReceive('id')->andReturn($id);

    return $submission;
}

it('is what the container builds for the add-on contract', function (): void {
    expect(app(SubmissionContext::class)::class)->toBe(FormRequestSubmissionContext::class);
});

it('captures the context stashed for the submission, for every notification', function (): void {
    $context = new FormRequestContext('203.0.113.9', 'https://example.test/apply', ['ref' => 'news'], 'default');
    FormRequestContext::stash('submission-1', $context);

    $expected = [
        'ip' => '203.0.113.9',
        'referrer' => 'https://example.test/apply',
        'query' => ['ref' => 'news'],
        'site' => 'default',
    ];

    // Statamic builds one job per notification, and each one captures the context
    expect((new FormRequestSubmissionContext)->capture(formRequestSubmissionContextSubmission('submission-1')))->toBe($expected)
        ->and((new FormRequestSubmissionContext)->capture(formRequestSubmissionContextSubmission('submission-1')))->toBe($expected);

    FormRequestContext::pull('submission-1');
});

it('hands the context to each notification job of a submission', function (): void {
    // Make an unsaved form whose submission the add-on's notification job is built for
    $form = makeForm('form_request_submission_context_probe');
    $submission = $form->makeSubmission();
    $submission->data(['name' => 'Pat']);
    $id = $submission->id();
    FormRequestContext::stash(is_scalar($id) ? (string) $id : '', new FormRequestContext('203.0.113.8', '', [], 'default'));

    // Build two jobs as Statamic's SendEmails does, one per notification
    $site = mustBe(StatamicSite::class, Site::default(), 'site');
    $first = new SendFormNotification($submission, $site, ['to' => 'a@example.test']);
    $second = new SendFormNotification($submission, $site, ['to' => 'b@example.test']);

    expect($first->requestContext['ip'] ?? null)->toBe('203.0.113.8')
        ->and($second->requestContext['ip'] ?? null)->toBe('203.0.113.8');

    FormRequestContext::pull(is_scalar($id) ? (string) $id : '');
});

it('captures nothing for a submission with no stashed context', function (mixed $id): void {
    FormRequestContext::stash('someone-else', new FormRequestContext('203.0.113.1', '', [], 'default'));

    expect((new FormRequestSubmissionContext)->capture(formRequestSubmissionContextSubmission($id)))->toBeNull();

    // Clear the unrelated stash so it cannot leak into a later test
    FormRequestContext::pull('someone-else');
})->with([
    'unknown id' => ['submission-2'],
    'null id' => [null],
]);

it('captures by a numeric id converted to a string', function (): void {
    FormRequestContext::stash('42', new FormRequestContext('203.0.113.4', '', [], 'default'));

    $captured = (new FormRequestSubmissionContext)->capture(formRequestSubmissionContextSubmission(42));

    expect($captured)->toBeArray()->toHaveKey('ip', '203.0.113.4');
});

it('makes the captured context active only while the send runs', function (): void {
    $seen = null;

    $result = (new FormRequestSubmissionContext)->during(
        ['ip' => '203.0.113.7', 'referrer' => 'https://example.test/', 'query' => [], 'site' => 'default'],
        function () use (&$seen): string {
            $seen = FormRequestContext::active();

            return 'sent';
        }
    );

    // The send's return value passes through
    expect($result)->toBe('sent');

    // The send saw the captured context, and it is no longer active afterwards
    expect($seen)->toBeInstanceOf(FormRequestContext::class)
        ->and($seen?->ip)->toBe('203.0.113.7')
        ->and(FormRequestContext::active())->toBeNull();
});
