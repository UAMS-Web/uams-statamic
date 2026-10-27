<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Services\TitleDisambiguator;
use Illuminate\Support\Facades\Log;
use Statamic\Entries\Entry;
use Statamic\Events\EntrySaved;

/**
 * When an entry is saved, ensure its title is unique within the collection.
 * If another entry already has the same title, append (2), (3), etc.
 *
 * Runs after save as a fallback when the collection title_format does not
 * include the {{ disambiguate_title }} tag, or when the tag did not run.
 * Prefer adding {{ disambiguate_title }} to the collection title_format so
 * titles are correct at generation time.
 *
 * Wrapped in try/catch so a failure here never breaks the save response (e.g., CP
 * "Something went wrong" / TypeError reading 'title' when the API returns an error shape).
 */
class DisambiguateEntryTitle
{
    /**
     * Guard to avoid re-entry when we save the entry again after disambiguating.
     */
    private static bool $disambiguating = false;

    /**
     * Handle the EntrySaved event: if the entry's title collides with another
     * in the same collection/site, set title to "Base (n)" and save quietly.
     *
     * @param  EntrySaved  $event  The entry saved event
     */
    public function handle(EntrySaved $event): void
    {
        /** @var Entry $entry */
        $entry = $event->entry;

        try {
            $this->disambiguateIfNeeded($entry);
        } catch (\Throwable $throwable) {
            Log::warning('DisambiguateEntryTitle: failed to disambiguate title', [
                'entry_id' => $entry->id(),
                'message' => $throwable->getMessage(),
            ]);
        }
    }

    /**
     * Run disambiguation when another entry in the same collection/site has
     * the same title.
     *
     * @param  Entry  $entry  The saved entry
     */
    private function disambiguateIfNeeded(Entry $entry): void
    {
        if (! $entry->collection()) {
            return;
        }

        if (self::$disambiguating) {
            return;
        }

        $title = $entry->get('title');
        if (! \is_string($title) || $title === '') {
            return;
        }

        $disambiguator = app(TitleDisambiguator::class);
        $suffix = $disambiguator->suffixForEntry($entry, $title);
        if ($suffix === '') {
            return;
        }

        self::$disambiguating = true;
        try {
            $entry->set('title', $title.$suffix);
            $entry->saveQuietly();
        } finally {
            self::$disambiguating = false;
        }
    }
}
