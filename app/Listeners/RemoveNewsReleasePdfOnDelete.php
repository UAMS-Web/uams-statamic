<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Services\NewsRelease\NewsReleasePdfGenerator;
use Statamic\Entries\Entry;
use Statamic\Events\EntryDeleted;

/**
 * Listens for {@see EntryDeleted} and removes the news release PDF for a deleted
 * `posts` `news_release` entry, so a previously published press release does not
 * remain downloadable at its public URL after the entry is gone.
 */
class RemoveNewsReleasePdfOnDelete
{
    /**
     * Remove any PDFs owned by the deleted entry, or no-op for unrelated entries.
     *
     * @param  EntryDeleted  $event  Statamic entry delete event
     */
    public function handle(EntryDeleted $event): void
    {
        // Read the entry from the event payload
        /** @var Entry $entry */
        $entry = $event->entry;

        // Resolve the generator that owns the PDF lifecycle
        $generator = app(NewsReleasePdfGenerator::class);

        // Skip entries that are not a `posts` `news_release`
        if (! $generator->appliesTo($entry)) {
            return;
        }

        // Remove every PDF this entry owns
        $generator->removePdfForEntry($entry);
    }
}
