<?php

declare(strict_types=1);

use Illuminate\Queue\Events\JobQueued;
use Illuminate\Support\Facades\Log;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Facades\Form as FormFacade;
use Statamic\Facades\Site;
use Statamic\Forms\SendEmail;
use Tests\TestCase;
use UAMSWeb\Aether\Forms\Settings\Jobs\SendFormNotification;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Build a real form send-email job instance for the default site.
 *
 * @return SendEmail The configured form send-email job.
 */
function warnFormEmailMakeSendEmailJob(): SendEmail
{
    // Make a throwaway submission to satisfy the job constructor.
    $submission = FormFacade::make('guard_probe')->makeSubmission();

    // Build the *configured* send-email job so this test tracks
    // config('statamic.forms.send_email_job') instead of hard-coding a class;
    // the guard matches on the configured class, which UAMS-Web/uams-statamic-archive#213 repointed to a
    // SendEmail subclass (UAMSWeb\Aether\Forms\Settings\Jobs\SendFormNotification).
    /** @var class-string<SendEmail> $jobClass */
    $jobClass = config('statamic.forms.send_email_job');

    /** @var Statamic\Sites\Site $site */
    $site = Site::default();

    return new $jobClass($submission, $site, []);
}

/**
 * Build a JobQueued event for the given job on the given connection.
 *
 * @param  object  $job  The queued job instance.
 * @param  string  $connection  The queue connection name.
 * @return JobQueued The constructed queue event.
 */
function warnFormEmailJobQueuedEvent(object $job, string $connection): JobQueued
{
    return new JobQueued(
        connectionName: $connection,
        queue: null,
        id: 'test-job-id',
        job: $job,
        payload: '',
        delay: null
    );
}

/**
 * Verifies the WarnWhenFormEmailQueued guard logs a warning only when the form
 * send-email job is pushed onto an asynchronous queue *without* captured request
 * context (issues UAMS-Web/uams-statamic-archive#706, UAMS-Web/uams-statamic-archive#830).
 *
 * Since UAMS-Web/uams-statamic-archive#830, a send-email job dispatched by a normal form submission carries the
 * submitter's captured request context (populated in its constructor before JobQueued
 * fires), so its request-context merge tags resolve correctly on an async worker — that
 * case is safe and must NOT warn. The guard fires only for an async send-email job with
 * no captured context, where the tags would render empty/wrong. It is auto-discovered on
 * Laravel's JobQueued event, which fires only for non-sync connections.
 *
 * @covers \App\Listeners\WarnWhenFormEmailQueued
 *
 * @command  php artisan test --compact tests/Feature/Listeners/WarnWhenFormEmailQueuedTest.php
 */
it('warns when the form send-email job is queued async without captured context', function () {
    // Capture log calls without suppressing other behavior.
    $log = Log::spy();

    // A throwaway job has no stashed context, so its constructor leaves requestContext
    // null — the genuine "tags will render wrong on a worker" case the guard catches.
    $job = warnFormEmailMakeSendEmailJob();
    expect($job)->toBeInstanceOf(SendFormNotification::class);
    /** @var SendFormNotification $job */
    expect($job->requestContext)->toBeNull();

    // Raise the event Laravel fires when the form send-email job hits a real queue.
    event(warnFormEmailJobQueuedEvent($job, 'database'));

    // The guard should have logged a single, descriptive warning.
    $log->shouldHaveReceived('warning')
        ->once()
        ->withArgs(fn ($message) => \is_string($message) && str_contains($message, 'request-context merge tags'));
});

it('does not warn when the queued send-email job carries captured request context', function () {
    // Capture log calls without suppressing other behavior.
    $log = Log::spy();

    // Simulate the normal UAMS-Web/uams-statamic-archive#830 async path: the job captured the submitter's context.
    $job = warnFormEmailMakeSendEmailJob();
    expect($job)->toBeInstanceOf(SendFormNotification::class);
    /** @var SendFormNotification $job */
    $job->requestContext = ['ip' => '203.0.113.7', 'referrer' => '', 'query' => [], 'site' => 'default'];

    // Raise the queue event for the context-carrying job.
    event(warnFormEmailJobQueuedEvent($job, 'database'));

    // A job that carries captured context renders correctly async, so the guard stays silent.
    $log->shouldNotHaveReceived('warning');
});

it('does not warn for unrelated queued jobs', function () {
    // Capture log calls without suppressing other behavior.
    $log = Log::spy();

    // Raise JobQueued for a job that is not the form send-email job.
    event(warnFormEmailJobQueuedEvent(new stdClass, 'database'));

    // The guard must stay silent for unrelated jobs.
    $log->shouldNotHaveReceived('warning');
});

it('logs the full warning with the connection, queue and job', function () {
    $log = Log::spy();

    $job = warnFormEmailMakeSendEmailJob();

    event(new JobQueued(
        connectionName: 'database',
        queue: 'emails',
        id: 'test-job-id',
        job: $job,
        payload: '',
        delay: null
    ));

    $log->shouldHaveReceived('warning')
        ->once()
        ->with(
            'Form-notification email queued on a non-sync connection without captured submitter '
            .'request context; request-context merge tags ({{ ip }}, {{ referrer }}, {{ get_param }}, '
            .'{{ global_origin }}) will render empty or incorrect because the worker has no submitter '
            .'request. Normal form submissions capture this context automatically (UAMS-Web/uams-statamic-archive#830); this usually '
            .'means the job was dispatched outside the form-submit flow. See docs/forms/email-rendering.md.',
            [
                'connection' => 'database',
                'queue' => 'emails',
                'job' => config('statamic.forms.send_email_job'),
            ]
        );
});
