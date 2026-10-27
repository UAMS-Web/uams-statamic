<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Support\Forms\FormRequestContext;
use App\Tags\GetParam;
use App\Tags\GlobalOrigin;
use App\Tags\Ip;
use App\Tags\Referrer;
use Statamic\Events\FormSubmitted;

/**
 * Captures the submitter's live request context when a form is submitted.
 *
 * `Statamic\Events\FormSubmitted` fires inside `FormController::submit()` while the
 * submit request is still live, immediately before `SendEmails::dispatch()` builds the
 * send-email job. This listener snapshots the request context the four request-context
 * merge tags depend on ({@see Ip}, {@see Referrer},
 * {@see GetParam}, {@see GlobalOrigin}) and stashes it keyed by the
 * submission id, so the send-email job — constructed moments later in the same request —
 * can thread it across the queue boundary to the worker that renders the email.
 *
 * Without this, those tags would render the worker's (empty/default) state instead of the
 * submitter's whenever form emails run on a real (non-`sync`) queue connection.
 *
 * It is registered once in `EventServiceProvider::$listen`, like every listener (UAMS-Web/uams-statamic-archive#3240). The handler
 * returns nothing: `FormSubmitted` halts on the first non-null listener response (treating it
 * as a silent form failure), so returning `void` keeps the submission flowing normally.
 *
 * @see docs/forms/email-rendering.md
 */
final class CaptureFormRequestContext
{
    /**
     * Snapshot and stash the submitter's request context for the send-email job.
     *
     * @param  FormSubmitted  $event  The form-submitted event, carrying the submission.
     */
    public function handle(FormSubmitted $event): void
    {
        // Capture the live request context (ip, referrer, query, current site)
        $context = FormRequestContext::capture();

        // Stash it keyed by submission id for the send-email jobs to read
        $id = $event->submission->id();
        FormRequestContext::stash(\is_scalar($id) ? (string) $id : '', $context);
    }
}
