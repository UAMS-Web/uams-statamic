<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Collection;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Sites\Site;
use Statamic\Stache\Query\EntryQueryBuilder;

/**
 * Returns a disambiguation suffix for entry titles so duplicates become
 * "Base (2)", "Base (3)", etc. Collection-agnostic; used by the
 * {{ disambiguate_title }} tag and any collection-specific listeners
 * (e.g., DisambiguateEntryTitle).
 */
class TitleDisambiguator
{
    /**
     * Return the suffix to append to the base title: "" or " (2)", " (3)", etc.
     * If no other entry in the same collection/site has the exact same title,
     * returns "" so the title stays as-is.
     *
     * @param  Entry  $entry  The entry (must have a collection)
     * @param  string  $baseTitle  The base title (e.g., from title_format without the tag)
     * @return string "" or " (n)"
     */
    public function suffixForEntry(Entry $entry, string $baseTitle): string
    {
        if (! $entry->collection()) {
            return '';
        }

        $baseTitle = trim($baseTitle);
        if ($baseTitle === '') {
            return '';
        }

        // Only disambiguate when another entry has the exact same title
        $othersWithExactTitle = $this->entriesWithExactTitle($entry, $baseTitle);
        if ($othersWithExactTitle->isEmpty()) {
            return '';
        }

        $othersWithSameBase = $this->entriesWithSameBaseTitle($entry, $baseTitle);
        $nextNumber = $this->nextDisambiguationNumber($baseTitle, $othersWithSameBase);

        return ' ('.$nextNumber.')';
    }

    /**
     * Return the suffix for a title when the entry is not available (e.g., new
     * entry, id null). Uses collection and site from context/request so we can
     * still check existing entries. No entry is excluded (the "current" one
     * is not saved yet).
     *
     * @param  string  $collectionHandle  Collection handle
     * @param  string|null  $siteHandle  Site handle, or null for all sites
     * @param  string  $baseTitle  The base title
     * @return string "" or " (2)", " (3)", etc.
     */
    public function suffixForCollectionAndSite(
        string $collectionHandle,
        ?string $siteHandle,
        string $baseTitle
    ): string {
        $baseTitle = trim($baseTitle);
        if ($baseTitle === '') {
            return '';
        }

        // Count existing entries with exact title (no current entry to exclude)
        $othersWithExactTitle = $this->entriesWithExactTitleInCollection(
            $collectionHandle,
            $siteHandle,
            $baseTitle,
            null
        );
        if ($othersWithExactTitle->isEmpty()) {
            return '';
        }

        $othersWithSameBase = $this->entriesWithSameBaseTitleInCollection(
            $collectionHandle,
            $siteHandle,
            $baseTitle,
            null
        );
        $nextNumber = $this->nextDisambiguationNumberFromOthers($baseTitle, $othersWithSameBase);

        return ' ('.$nextNumber.')';
    }

    /**
     * Get entries in the given collection/site whose title is exactly the
     * given title, optionally excluding an entry by id.
     *
     * @param  string  $title  The exact title to match
     * @param  string|null  $excludeEntryId  Entry id to exclude, or null
     * @return Collection<int, Entry>
     */
    public function entriesWithExactTitleInCollection(
        string $collectionHandle,
        ?string $siteHandle,
        string $title,
        ?string $excludeEntryId
    ): Collection {
        // Build a query for entries
        /** @var EntryQueryBuilder $entryQuery */
        $entryQuery = EntryFacade::query();

        // Scope to the collection AND the exact title so the Stache title index
        // narrows hydration to title matches, not the whole collection (UAMS-Web/uams-statamic-archive#862).
        /** @var EntryQueryBuilder $entryQuery */
        $entryQuery = $entryQuery->where('collection', $collectionHandle);
        /** @var EntryQueryBuilder $entryQuery */
        $entryQuery = $entryQuery->where('title', $title);
        /** @var Collection<int, Entry> $candidates */
        $candidates = $entryQuery->get();

        return $candidates->filter(function (Entry $other) use ($title, $excludeEntryId, $siteHandle): bool {
            /** @var Site|null $otherSite */
            $otherSite = $other->site();
            if ($siteHandle !== null && $otherSite?->handle() !== $siteHandle) {
                return false;
            }

            if ($excludeEntryId !== null && $other->id() === $excludeEntryId) {
                return false;
            }

            $otherTitle = $other->get('title');

            return \is_string($otherTitle) && $otherTitle === $title;
        });
    }

    /**
     * Get entries in the given collection/site whose title is the base or
     * "base (n)", optionally excluding an entry by id.
     *
     * @return Collection<int, Entry>
     */
    public function entriesWithSameBaseTitleInCollection(
        string $collectionHandle,
        ?string $siteHandle,
        string $baseTitle,
        ?string $excludeEntryId
    ): Collection {
        // Build a query for entries
        /** @var EntryQueryBuilder $entryQuery */
        $entryQuery = EntryFacade::query();

        // NOTE (UAMS-Web/uams-statamic-archive#862): base-title candidates are intentionally NOT scoped via a
        // title `like` query. Stache's sqlLikeToRegex reads a trailing `\%`/`\_`
        // as an escaped literal, so a `$baseTitle.'%'` prefix UNDER-matches a base
        // ending in `\` (it would miss real "<base> (n)" duplicates and reissue a
        // used suffix). This full scan runs only on an actual exact-title collision
        // — the exact-title gate (which IS index-scoped) returns early otherwise —
        // so the per-save hot path stays cheap.
        /** @var EntryQueryBuilder $entryQuery */
        $entryQuery = $entryQuery->where('collection', $collectionHandle);
        /** @var Collection<int, Entry> $candidates */
        $candidates = $entryQuery->get();

        return $candidates->filter(function (Entry $other) use ($baseTitle, $excludeEntryId, $siteHandle): bool {
            /** @var Site|null $otherSite */
            $otherSite = $other->site();
            if ($siteHandle !== null && $otherSite?->handle() !== $siteHandle) {
                return false;
            }

            if ($excludeEntryId !== null && $other->id() === $excludeEntryId) {
                return false;
            }

            $otherTitle = $other->get('title');
            if (! \is_string($otherTitle)) {
                return false;
            }

            if ($otherTitle === $baseTitle) {
                return true;
            }

            $pattern = '/^'.preg_quote($baseTitle, '/').' \((\d+)\)$/';

            return (bool) preg_match($pattern, $otherTitle);
        });
    }

    /**
     * Compute the next disambiguation number from existing entries only (no
     * current entry to consider). Used when the "current" entry is not yet saved.
     *
     * @param  Collection<int, Entry>  $others  Entries with same base title
     * @return int Next number, e.g. 2 or 3
     */
    public function nextDisambiguationNumberFromOthers(string $baseTitle, Collection $others): int
    {
        return $this->highestExistingNumber($baseTitle, $others) + 1;
    }

    /**
     * Get entries in the same collection/site whose title is exactly the given
     * title, excluding the given entry.
     *
     * @param  Entry  $entry  The entry we are disambiguating
     * @param  string  $title  The exact title to match
     * @return Collection<int, Entry>
     */
    public function entriesWithExactTitle(Entry $entry, string $title): Collection
    {
        /** @var \Statamic\Entries\Collection $collection */
        $collection = $entry->collection();
        $collectionHandle = $collection->handle();
        /** @var Site|null $entrySite */
        $entrySite = $entry->site();
        $siteHandle = $entrySite?->handle();
        $entryId = $entry->id();

        // Build a query for entries
        /** @var EntryQueryBuilder $entryQuery */
        $entryQuery = EntryFacade::query();

        // Scope to the collection AND the exact title so the Stache title index
        // narrows hydration to title matches, not the whole collection (UAMS-Web/uams-statamic-archive#862).
        /** @var EntryQueryBuilder $entryQuery */
        $entryQuery = $entryQuery->where('collection', $collectionHandle);
        /** @var EntryQueryBuilder $entryQuery */
        $entryQuery = $entryQuery->where('title', $title);
        /** @var Collection<int, Entry> $candidates */
        $candidates = $entryQuery->get();

        return $candidates->filter(function (Entry $other) use ($title, $entryId, $siteHandle): bool {
            /** @var Site|null $otherSite */
            $otherSite = $other->site();
            if ($siteHandle !== null && $otherSite?->handle() !== $siteHandle) {
                return false;
            }

            if ($other->id() === $entryId) {
                return false;
            }

            $otherTitle = $other->get('title');

            return \is_string($otherTitle) && $otherTitle === $title;
        });
    }

    /**
     * Get entries in the same collection/site whose title is exactly the base
     * or "base (n)", excluding the given entry.
     *
     * @param  Entry  $entry  The entry we are disambiguating
     * @param  string  $baseTitle  The base title to match
     * @return Collection<int, Entry>
     */
    public function entriesWithSameBaseTitle(Entry $entry, string $baseTitle): Collection
    {
        /** @var \Statamic\Entries\Collection $collection */
        $collection = $entry->collection();
        $collectionHandle = $collection->handle();
        /** @var Site|null $entrySite */
        $entrySite = $entry->site();
        $siteHandle = $entrySite?->handle();
        $entryId = $entry->id();

        // Build a query for entries
        /** @var EntryQueryBuilder $entryQuery */
        $entryQuery = EntryFacade::query();

        // NOTE (UAMS-Web/uams-statamic-archive#862): base-title candidates are intentionally NOT scoped via a
        // title `like` query. Stache's sqlLikeToRegex reads a trailing `\%`/`\_`
        // as an escaped literal, so a `$baseTitle.'%'` prefix UNDER-matches a base
        // ending in `\` (it would miss real "<base> (n)" duplicates and reissue a
        // used suffix). This full scan runs only on an actual exact-title collision
        // — the exact-title gate (which IS index-scoped) returns early otherwise —
        // so the per-save hot path stays cheap.
        /** @var EntryQueryBuilder $entryQuery */
        $entryQuery = $entryQuery->where('collection', $collectionHandle);
        /** @var Collection<int, Entry> $candidates */
        $candidates = $entryQuery->get();

        return $candidates->filter(function (Entry $other) use ($baseTitle, $entryId, $siteHandle): bool {
            /** @var Site|null $otherSite */
            $otherSite = $other->site();
            if ($siteHandle !== null && $otherSite?->handle() !== $siteHandle) {
                return false;
            }

            if ($other->id() === $entryId) {
                return false;
            }

            $otherTitle = $other->get('title');

            if (! \is_string($otherTitle)) {
                return false;
            }

            if ($otherTitle === $baseTitle) {
                return true;
            }

            $pattern = '/^'.preg_quote($baseTitle, '/').' \((\d+)\)$/';

            return (bool) preg_match($pattern, $otherTitle);
        });
    }

    /**
     * Compute the next disambiguation number (2, 3, ...) from existing titles.
     *
     * @param  string  $baseTitle  The base title
     * @param  Collection<int, Entry>  $others  Other entries with same base
     * @return int Next number to use, e.g. 2 or 3
     */
    public function nextDisambiguationNumber(
        string $baseTitle,
        Collection $others
    ): int {
        return $this->highestExistingNumber($baseTitle, $others) + 1;
    }

    /**
     * Highest disambiguation number already in use among same-base entries (>= 1).
     *
     * A parenthesised number at or beyond PHP_INT_MAX is treated as foreign data
     * and ignored: the disambiguator only ever issues small sequential numbers, so
     * honouring such a value would saturate the (int) cast to PHP_INT_MAX and make
     * the caller's `+ 1` overflow to a float, which the `: int` return then rejects
     * with a TypeError (uncaught in the {{ disambiguate_title }} tag path).
     *
     * @param  string  $baseTitle  The base title
     * @param  Collection<int, Entry>  $others  Entries sharing the base title
     * @return int The highest in-use number, or 1 when none carry a suffix
     */
    private function highestExistingNumber(string $baseTitle, Collection $others): int
    {
        $max = 1;
        $pattern = '/^'.preg_quote($baseTitle, '/').' \((\d+)\)$/';

        foreach ($others as $other) {
            $title = $other->get('title');
            if (! \is_string($title)) {
                continue;
            }

            if ($title === $baseTitle) {
                continue;
            }

            if (preg_match($pattern, $title, $matches)) {
                $number = (int) $matches[1];
                if ($number < PHP_INT_MAX) {
                    $max = max($max, $number);
                }
            }
        }

        return $max;
    }
}
