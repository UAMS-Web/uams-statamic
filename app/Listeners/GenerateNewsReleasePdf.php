<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Services\NewsRelease\NewsReleasePdfGenerator;
use Illuminate\Support\Facades\Log;
use Statamic\Entries\Entry;
use Statamic\Events\EntrySaved;

/**
 * Listens for {@see EntrySaved} and syncs a news release PDF when appropriate.
 *
 * Runs only for entries in the `posts` collection whose blueprint handle is
 * `news_release`. Delegates to {@see NewsReleasePdfGenerator::syncPdfForEntry()}
 * so the PDF is stored in the `news_releases` container and the
 * `news_release_pdf` field is updated. Uses a static re-entrancy guard so
 * `saveQuietly()` inside the generator does not trigger nested listener runs.
 * Logs listener-level failures without rethrowing.
 */
class GenerateNewsReleasePdf
{
    /**
     * When true, {@see self::handle()} returns immediately to avoid nested PDF
     * generation while {@see NewsReleasePdfGenerator::syncPdfForEntry()} runs.
     */
    private static bool $generating = false;

    /**
     * Sync or remove the PDF for a qualifying news release entry, or no-op.
     *
     * Skips when already generating or when the entry is not a `posts`
     * `news_release`. The PDF is generated only when the entry is publicly live
     * (`status() === 'published'`); for any other status — draft, scheduled
     * (future-dated), or expired — any existing PDF is removed instead, so an
     * embargoed or unpublished release is never downloadable at its guessable
     * public URL.
     *
     * @param  EntrySaved  $event  Statamic entry save event
     */
    public function handle(EntrySaved $event): void
    {
        // Skip nested invocations while a PDF sync is already running
        if (self::$generating) {
            return;
        }

        // Read the entry from the event payload
        /** @var Entry $entry */
        $entry = $event->entry;

        // Resolve the generator that owns the PDF lifecycle
        $generator = app(NewsReleasePdfGenerator::class);

        // Skip entries that are not a `posts` `news_release`
        if (! $generator->appliesTo($entry)) {
            return;
        }

        // Set the generating flag to prevent nested runs
        self::$generating = true;

        // Generate when publicly live; otherwise remove any existing PDF
        try {
            if ($entry->status() === 'published') {
                // Entry is publicly live: (re)generate and attach its PDF
                $generator->syncPdfForEntry($entry);
            } else {
                // Draft / scheduled / expired: ensure no public PDF lingers
                $generator->removePdfForEntry($entry);
            }
        } catch (\Throwable $throwable) {
            // Log failures without breaking the control panel save response
            Log::warning('GenerateNewsReleasePdf: listener failed', [
                'entry_id' => $entry->id(),
                'message' => $throwable->getMessage(),
            ]);
        } finally {
            // Reset the generating flag to allow nested runs
            self::$generating = false;
        }
    }
}
