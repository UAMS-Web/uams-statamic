<?php

declare(strict_types=1);

namespace App\Listeners;

use Statamic\Entries\Entry;
use Statamic\Events\EntrySaved;
use Statamic\Events\EntrySaving;
use Statamic\Facades\Blink;

/**
 * Stores the entry currently being saved in Blink so the {{ disambiguate_title }}
 * tag can resolve it when context has no id (e.g., new entries). Clears after save.
 */
class SetCurrentEntryForTitleFormat
{
    private const string BLINK_KEY = 'disambiguate_title_current_entry';

    /**
     * Handle entry saving/saved: store entry before save, clear after save.
     */
    public function handle(EntrySaving|EntrySaved $event): void
    {
        // EntrySaving stores; EntrySaved (the only other arm of the union) clears.
        if ($event instanceof EntrySaving) {
            Blink::put(self::BLINK_KEY, $event->entry);

            return;
        }

        Blink::forget(self::BLINK_KEY);
    }

    /**
     * Return the entry currently being saved, if any (for use by the tag).
     */
    public static function currentEntry(): ?Entry
    {
        $entry = Blink::get(self::BLINK_KEY);

        // @phpstan-ignore instanceof.alwaysFalse (Blink is an untyped key-value store; handle() puts the Entry being saved, so this guards the value's runtime type)
        return $entry instanceof Entry ? $entry : null;
    }
}
