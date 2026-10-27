<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Support\Redirects\DeletedEntryRedirectCapture;
use Statamic\Entries\Entry;
use Statamic\Events\EntryDeleted;

/**
 * Listens for Statamic's `EntryDeleted` event and records a disabled redirect for the
 * deleted entry's public URI, so a URL that was live a moment ago has somewhere to go once
 * an editor supplies a destination.
 *
 * The row is written with `enabled: false`, which `RedirectRuleMatcher` skips — so an
 * incomplete capture never serves a redirect to nowhere. Completing it is a deliberate
 * Control Panel act rather than a default.
 *
 * @see DeletedEntryRedirectCapture
 */
class CaptureRedirectOnEntryDelete
{
    /**
     * Take the shared capture service, which holds the URI read before the delete.
     *
     * @param  DeletedEntryRedirectCapture  $capture  The shared capture service.
     */
    public function __construct(private readonly DeletedEntryRedirectCapture $capture) {}

    /**
     * Record a disabled redirect for the deleted entry, or no-op when it had no public URL.
     *
     * @param  EntryDeleted  $event  Statamic entry delete event
     */
    public function handle(EntryDeleted $event): void
    {
        // Read the entry from the event payload
        /** @var Entry $entry */
        $entry = $event->entry;

        // Record the entry's URI, skipping anything with no public URL
        $this->capture->captureFor($entry);
    }
}
