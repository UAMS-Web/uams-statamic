<?php

declare(strict_types=1);

namespace App\Listeners;

use Statamic\Contracts\Forms\Submission;
use Statamic\Events\SubmissionCreating;
use Statamic\Facades\Site;
use Statamic\Sites\Site as StatamicSite;
use UAMSWeb\MultisiteForms\Forms\RecordSubmitSite;
use UAMSWeb\MultisiteForms\Sites\RequestSite;

/**
 * Auto-appends contextual `_*` metadata to every public-form submission (UAMS-Web/uams-statamic-archive#207).
 *
 * `Statamic\Events\SubmissionCreating` fires inside `Submission::save()` — while the submit
 * request is still live and before the YAML is persisted — so a listener can merge extra keys
 * into the submission's data. `Submission::fileData()` writes `data()->all()`, which carries
 * every key (not just blueprint fields), so these system fields land in the stored YAML.
 *
 * The fields record which form, where, and which site each submission came from (the site
 * of the page the form was on, as the multisite forms add-on records it under `_site`), plus
 * spam-triage context (user agent, IP, referrer, timestamp) — captured server-side so they
 * can't be omitted or spoofed per-form. Each handle is prefixed `_` to mark it system-generated
 * and distinct from editor-added fields; an editor field of the same name is never overwritten.
 *
 * Registered once in `EventServiceProvider::$listen`, like every listener (UAMS-Web/uams-statamic-archive#3240). It returns
 * `void`: `SubmissionCreating`
 * aborts the save only on a `false` return, so returning nothing lets the submission persist.
 *
 * @see docs/forms/submission-metadata.md
 */
final class AppendSubmissionMetadata
{
    /**
     * Merge the system metadata fields into the submission before it is persisted.
     *
     * @param  SubmissionCreating  $event  The creating event, carrying the submission.
     */
    public function handle(SubmissionCreating $event): void
    {
        $submission = $event->submission;

        // The event's submission is untyped; bail if it is not a real submission.
        if (! $submission instanceof Submission) {
            return;
        }

        $form = $submission->form();
        $request = request();

        // The site the form was submitted from (UAMS-Web/uams-statamic-archive#3068)
        $site = $this->submissionSite($submission);

        // The form POSTs to the `/!/forms/<handle>` endpoint, so the page the form was on
        // lives in the Referer header. Use it only when it is a well-formed URL — otherwise fall
        // back to the submit request's own URL, so a malformed, client-controlled Referer can't
        // land a garbage value in `_submitted_url` / `_submitted_path`. The path is then derived
        // from the chosen (validated) URL.
        $referer = $request->header('referer', '');
        // FILTER_VALIDATE_URL already rejects '', so the `!== ''` check cannot change the result
        // and EmptyStringToNotEmpty on it is an equivalent mutant.
        // @pest-mutate-ignore: EmptyStringToNotEmpty
        $submittedUrl = $referer !== '' && filter_var($referer, FILTER_VALIDATE_URL) !== false
            ? $referer
            : $request->fullUrl();
        $parsedPath = parse_url($submittedUrl, PHP_URL_PATH);
        // parse_url() reports a missing path as null, never as '', so the `!== ''` check
        // cannot change the result and EmptyStringToNotEmpty on it is an equivalent mutant.
        // @pest-mutate-ignore: EmptyStringToNotEmpty
        $submittedPath = is_string($parsedPath) && $parsedPath !== '' ? $parsedPath : '/';

        $metadata = [
            '_form_handle' => $form->handle(),
            '_form_title' => $form->title(),
            '_submitted_url' => $submittedUrl,
            '_submitted_path' => $submittedPath,
            '_site_handle' => $site->handle(),
            '_site_name' => $site->name(),
            '_site_url' => $site->absoluteUrl(),
            '_user_agent' => $request->userAgent() ?? '',
            '_ip' => $request->ip() ?? '',
            '_referrer' => $referer,
            '_submitted_at' => now()->toIso8601String(),
        ];

        // Append each field, but never clobber an editor-added field of the same name.
        foreach ($metadata as $key => $value) {
            if ($submission->get($key) === null) {
                $submission->set($key, $value);
            }
        }
    }

    /**
     * The site a submission was made from, as the multisite forms add-on names it.
     *
     * The form posts to `/!/forms/<handle>`, which sits under the default site's
     * prefix, so the request's own site is the default site whatever page the
     * form was on. The add-on records the submitting page's site under `_site`
     * when the submit route builds the submission; that site is read back here
     * so the two records cannot disagree. A submission without one, such as one
     * saved outside the submit route, takes the site the add-on would name for
     * this request.
     *
     * @param  Submission  $submission  The submission about to be saved.
     * @return StatamicSite The site the submission belongs to.
     */
    private function submissionSite(Submission $submission): StatamicSite
    {
        // Use the site the add-on recorded, when it names a configured site
        $recorded = $submission->get(RecordSubmitSite::KEY);
        $site = is_string($recorded) ? Site::get($recorded) : null;
        if ($site instanceof StatamicSite) {
            return $site;
        }

        // Otherwise name the site the same way the add-on does
        return app(RequestSite::class)->forSubmission();
    }
}
