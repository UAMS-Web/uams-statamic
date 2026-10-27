<?php

declare(strict_types=1);

namespace App\Listeners;

use Statamic\Entries\Entry;
use Statamic\Events\EntryDeleting;
use Statamic\Facades\Collection;

/**
 * Prevents deletion of entries that are mounted as collections.
 *
 * This listener is sourced from studio1902/statamic-peak:
 * - https://statamic.com/starter-kits/studio1902/peak
 * - https://peak.1902.studio/
 */
class PreventDeletingMounts
{
    /**
     * Handle the entry deleting event.
     *
     * @param  EntryDeleting  $event  The entry deleting event
     *
     * @throws \Exception If the entry is mounted as a collection
     */
    public function handle(
        EntryDeleting $event
    ): void {
        if (Collection::findByMount($event->entry)) {
            /** @var Entry $entry */
            $entry = $event->entry;

            $title = $entry->get('title');
            // trans() writes an int, float, bool or string placeholder out the same with or without
            // the cast (measured for 42, 1.5, true, false, 'x'); the cast is for the static types.
            // @pest-mutate-ignore: RemoveStringCast
            $title = is_scalar($title) ? (string) $title : '';

            // The string ships in lang/vendor/statamic, so it resolves only under the
            // `statamic::` namespace; the bare key renders as the key itself.
            throw new \Exception(trans(
                'statamic::strings.collection_mounted',
                ['title' => $title]
            ));
        }
    }
}
