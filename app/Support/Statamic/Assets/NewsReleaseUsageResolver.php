<?php

declare(strict_types=1);

namespace App\Support\Statamic\Assets;

use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;

/**
 * Resolves the usage of a `news_releases` asset from its system-managed
 * `news_release_entry` link (UAMS-Web/uams-statamic-archive#1376).
 *
 * A generated news-release PDF stores a read-only, single `entries` pointer to the
 * `posts` entry it belongs to. This turns that stored id into the two editorial
 * signals surfaced on the asset side: the linked post (if it still exists) and
 * whether it is actually live (exists and published).
 *
 * The stored value carries the usual `max_items: 1` `entries` storage-shape variance —
 * a scalar id from the CP, a single-element array from the WordPress import — so the id
 * is normalized before lookup.
 */
final class NewsReleaseUsageResolver
{
    /**
     * The `posts` entry this asset's `news_release_entry` names, if it still exists
     * (regardless of publish status), or null when the link is unset or dangling.
     *
     * @param  mixed  $newsReleaseEntryValue  The raw `news_release_entry` field value.
     */
    public static function resolve(
        mixed $newsReleaseEntryValue
    ): ?Entry {
        $id = self::normalizeEntryId($newsReleaseEntryValue);

        if ($id === null) {
            return null;
        }

        $entry = EntryFacade::find($id);

        // EntryFacade::find() is typed `?Entry`, so InstanceOfToTrue here is
        // equivalent: null still returns null, and a hit is already an Entry.
        // @pest-mutate-ignore: InstanceOfToTrue
        return $entry instanceof Entry ? $entry : null;
    }

    /**
     * Whether the asset is in use: its `news_release_entry` resolves to an existing,
     * published `posts` entry. A missing or unpublished post is not in use.
     *
     * @param  mixed  $newsReleaseEntryValue  The raw `news_release_entry` field value.
     */
    public static function isInUse(
        mixed $newsReleaseEntryValue
    ): bool {
        $entry = self::resolve($newsReleaseEntryValue);

        return $entry instanceof Entry && $entry->published();
    }

    /**
     * Normalize the `news_release_entry` value to a non-empty id string, unwrapping the
     * single-element array the WordPress import writes for a `max_items: 1` field.
     *
     * @param  mixed  $value  The raw field value (scalar id, single-element array, or null).
     */
    private static function normalizeEntryId(
        mixed $value
    ): ?string {
        if (is_array($value)) {
            $value = $value[0] ?? null;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }
}
