<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Queue\Events\JobQueued;
use Illuminate\Support\Facades\Log;
use UAMSWeb\FormSettings\Jobs\SendFormNotification;

/**
 * Warns when a form-notification email is queued asynchronously *without* the submitter's
 * captured request context.
 *
 * Form notifications render their Antlers at job-handle time. Several custom merge
 * tags ({{ ip }}, {{ referrer }}, {{ get_param }}, {{ global_origin }}) read live
 * request state ($_SERVER, request(), Site::current()), which only resolves to the
 * submitter when the job runs in the submitter's own request — i.e. on the `sync` queue.
 *
 * Issue UAMS-Web/uams-statamic-archive#830 closed that gap for the normal flow: {@see SendFormNotification}
 * claims the context captured by {@see CaptureFormRequestContext} in its constructor (in the
 * live submit request) and re-activates it around the render, so on an async worker the tags
 * still resolve to the submitter's values. A job that carries that captured context is safe to
 * queue and needs no warning.
 *
 * The remaining risk is a send-email job queued async with **no** captured context — e.g. one
 * dispatched outside the form-submit flow, or a regression in the capture step. There the tags
 * fall back to the worker's empty/default state and render wrong. This guard fires only in that
 * case, inspecting the queued job's captured-context payload rather than blanket-warning on every
 * async send (which UAMS-Web/uams-statamic-archive#830 made safe).
 *
 * Laravel raises JobQueued only when a job is pushed to a non-sync connection
 * (Statamic\Queue\SyncQueue::push() never raises it). This listener is registered once in
 * `EventServiceProvider::$listen`, like every listener (UAMS-Web/uams-statamic-archive#3240).
 *
 * @see docs/forms/email-rendering.md
 */
class WarnWhenFormEmailQueued
{
    /**
     * Log a warning when the form send-email job is queued async with no captured context.
     *
     * @param  JobQueued  $event  The queued-job event raised for non-sync connections.
     */
    public function handle(JobQueued $event): void
    {
        // Resolve the configured form send-email job class (default Statamic\Forms\SendEmail)
        $sendEmailJob = config('statamic.forms.send_email_job');

        // Ignore anything that is not the form send-email job
        if (! \is_string($sendEmailJob) || ! $event->job instanceof $sendEmailJob) {
            return;
        }

        // UAMS-Web/uams-statamic-archive#830: a job that captured the submitter's request context renders the four
        // request-context tags correctly even on an async worker, so it needs no warning.
        // Only an async send-email job *without* captured context will render them wrong.
        $hasCapturedContext = property_exists($event->job, 'requestContext')
            && $event->job->requestContext !== null;

        if ($hasCapturedContext) {
            return;
        }

        // Warn operators that request-context merge tags will not resolve without captured context
        Log::warning(
            'Form-notification email queued on a non-sync connection without captured submitter '
            .'request context; request-context merge tags ({{ ip }}, {{ referrer }}, {{ get_param }}, '
            .'{{ global_origin }}) will render empty or incorrect because the worker has no submitter '
            .'request. Normal form submissions capture this context automatically (UAMS-Web/uams-statamic-archive#830); this usually '
            .'means the job was dispatched outside the form-submit flow. See docs/forms/email-rendering.md.',
            [
                'connection' => $event->connectionName,
                'queue' => $event->queue,
                'job' => $sendEmailJob,
            ]
        );
    }
}
