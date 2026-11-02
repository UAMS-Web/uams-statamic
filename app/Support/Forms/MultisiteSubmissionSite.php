<?php

declare(strict_types=1);

namespace App\Support\Forms;

use Statamic\Contracts\Forms\Submission;
use Statamic\Facades\Site;
use Statamic\Sites\Site as StatamicSite;
use UAMSWeb\Aether\Forms\Multisite\Forms\RecordSubmitSite;
use UAMSWeb\Aether\Forms\Multisite\Sites\RequestSite;
use UAMSWeb\Aether\Forms\Settings\Contracts\SubmissionSite;

/**
 * Names a submission's site the way the multisite forms add-on does (#265).
 *
 * The form settings add-on records the site with each submission's metadata and
 * with the request context its queued emails carry, and asks this contract for
 * it. Its default answers with the current site, which is always the default
 * site here: a form posts to `/!/forms/<handle>`, under the default site's
 * prefix, whatever page the form was on.
 *
 * The multisite forms add-on records the submitting page's site under `_site`
 * when the submit route builds the submission, so that site is read back here
 * and the two records cannot disagree. A submission without one, such as one
 * saved outside the submit route, and the request context captured before a
 * submission exists, take the site the multisite add-on names for the request.
 */
final class MultisiteSubmissionSite implements SubmissionSite
{
    /**
     * The site a submission was made from.
     *
     * @param  Submission|null  $submission  The submission being saved, or null while the
     *                                       submit request is captured before one exists.
     * @return StatamicSite The site the submission belongs to.
     */
    #[\Override]
    public function forSubmission(?Submission $submission = null): StatamicSite
    {
        // Use the site the multisite add-on recorded, when it names a configured site
        $recorded = $submission?->get(RecordSubmitSite::KEY);
        $site = \is_string($recorded) ? Site::get($recorded) : null;
        if ($site instanceof StatamicSite) {
            return $site;
        }

        // Otherwise name the site the same way the multisite add-on does
        return app(RequestSite::class)->forSubmission();
    }
}
