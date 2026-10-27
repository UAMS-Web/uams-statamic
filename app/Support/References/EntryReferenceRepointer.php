<?php

declare(strict_types=1);

namespace App\Support\References;

use Illuminate\Support\Collection;
use Statamic\Entries\Entry as EntryItem;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;
use Statamic\Facades\Term;
use Statamic\Globals\Variables;
use Statamic\Taxonomies\LocalizedTerm;

/**
 * Re-points inbound references from one entry id to another across all content.
 *
 * When a `people` entry is merged away (UAMS-Web/uams-statamic-archive#838), everything that pointed at the
 * retired entry must be rewritten to the survivor so no link dangles. Statamic
 * core ships reference updaters for assets and terms, but NOT for entries — the
 * `entries` fieldtype does not use the `UpdatesReferences` trait — so this fills
 * that gap.
 *
 * The rewrite is value-shape based rather than blueprint-driven: it walks the
 * raw stored data of every entry / global / term and rewrites two reference
 * shapes wherever they appear, however deeply nested (inside groups, grids,
 * replicator/Bard sets, or Bard prosemirror marks):
 *
 *   1. A string that is exactly an old entry id — how the `entries` fieldtype
 *      stores a relationship (a single id, or one element of an id array).
 *   2. A `statamic://entry::<old-id>` token inside any string — how Bard,
 *      Markdown, and Link fields store inline entry links. The token is matched
 *      with a trailing boundary so one id can never partial-match a longer id.
 *
 * Entry ids in this application are non-guessable unique tokens (UUID-like), so
 * an exact whole-string match against a known old id is a reference and never a
 * coincidental literal in a free-text field.
 *
 * Out of scope (documented, not silently dropped): legacy numeric CSV include
 * lists (e.g. a Bard "people" set's `id_include: "7,11,15"`) hold WordPress ids,
 * not Statamic entry ids, so they are not entry references and are not rewritten.
 */
class EntryReferenceRepointer
{
    /**
     * The inline-entry-link token prefix stored by Bard / Markdown / Link.
     */
    private const string ENTRY_LINK_PREFIX = 'statamic://entry::';

    /**
     * Rewrite references across all content and persist every changed item.
     *
     * @param  array<string, string>  $idMap  Old (retired) entry id => new (survivor) entry id.
     * @return ReferenceRepointSummary Counts of items changed and occurrences rewritten.
     */
    public function repoint(array $idMap): ReferenceRepointSummary
    {
        return $this->scan($idMap, true);
    }

    /**
     * Count references without writing anything (drives the merge preview).
     *
     * @param  array<string, string>  $idMap  Old (retired) entry id => new (survivor) entry id.
     * @return ReferenceRepointSummary Counts of items that would change and occurrences found.
     */
    public function preview(array $idMap): ReferenceRepointSummary
    {
        return $this->scan($idMap, false);
    }

    /**
     * Walk every entry, global set localization, and term, rewriting references.
     *
     * @param  array<string, string>  $idMap  Old entry id => new entry id.
     * @param  bool  $apply  When true, persist changed items; when false, only count.
     */
    private function scan(array $idMap, bool $apply): ReferenceRepointSummary
    {
        // Drop no-op mappings (an id pointing at itself can never need rewriting)
        $idMap = array_filter($idMap, static fn (string $new, string $old): bool => $new !== $old, ARRAY_FILTER_USE_BOTH);

        // Nothing to do when there are no real remappings
        if ($idMap === []) {
            return new ReferenceRepointSummary(0, 0, 0, 0);
        }

        return new ReferenceRepointSummary(
            $this->scanEntries($idMap, $apply),
            $this->scanGlobals($idMap, $apply),
            $this->scanTerms($idMap, $apply),
            $this->occurrences,
        );
    }

    /**
     * Running total of individual reference occurrences rewritten in this scan.
     */
    private int $occurrences = 0;

    /**
     * Rewrite references held by entries (excluding the retired entries themselves).
     *
     * @param  array<string, string>  $idMap  Old entry id => new entry id.
     * @param  bool  $apply  Whether to persist changes.
     * @return int Number of entries changed.
     */
    private function scanEntries(array $idMap, bool $apply): int
    {
        // The retired entries are about to be deleted; never waste a write on them
        $retired = array_keys($idMap);

        $changed = 0;

        /** @var Collection<int, EntryItem> $all */
        $all = Entry::all();

        foreach ($all as $entry) {
            // Skip the entries being merged away
            $entryId = $entry->id();
            if (\is_string($entryId) && \in_array($entryId, $retired, true)) {
                continue;
            }

            [$newData, $count] = $this->rewriteValue($this->dataArray($entry), $idMap);

            // Move on when this entry held no references to the retired ids
            if ($count === 0) {
                continue;
            }

            $this->occurrences += $count;
            $changed++;

            // Persist quietly so merge re-pointing never fires entry-saved listeners
            if ($apply) {
                $entry->data($newData);
                $entry->saveQuietly();
            }
        }

        return $changed;
    }

    /**
     * Rewrite references held by global set localizations.
     *
     * @param  array<string, string>  $idMap  Old entry id => new entry id.
     * @param  bool  $apply  Whether to persist changes.
     * @return int Number of global localizations changed.
     */
    private function scanGlobals(array $idMap, bool $apply): int
    {
        $changed = 0;

        $siteHandles = $this->siteHandles();

        /** @var Collection<int, \Statamic\Globals\GlobalSet> $sets */
        $sets = GlobalSet::all();

        foreach ($sets as $set) {
            foreach ($siteHandles as $siteHandle) {
                $variables = $set->in($siteHandle);

                // A set may not be localized into every site
                if (! $variables instanceof Variables) {
                    continue;
                }

                [$newData, $count] = $this->rewriteValue($this->dataArray($variables), $idMap);

                // Skip localizations that held no references
                if ($count === 0) {
                    continue;
                }

                $this->occurrences += $count;
                $changed++;

                if ($apply) {
                    $variables->data($newData);
                    $variables->save();
                }
            }
        }

        return $changed;
    }

    /**
     * Rewrite references held by taxonomy terms.
     *
     * @param  array<string, string>  $idMap  Old entry id => new entry id.
     * @param  bool  $apply  Whether to persist changes.
     * @return int Number of terms changed.
     */
    private function scanTerms(array $idMap, bool $apply): int
    {
        $changed = 0;

        /** @var Collection<int, LocalizedTerm> $terms */
        $terms = Term::query()->get();

        foreach ($terms as $term) {
            [$newData, $count] = $this->rewriteValue($this->dataArray($term), $idMap);

            // Skip terms that held no references
            if ($count === 0) {
                continue;
            }

            $this->occurrences += $count;
            $changed++;

            if ($apply) {
                $term->data($newData);
                $term->saveQuietly();
            }
        }

        return $changed;
    }

    /**
     * Recursively rewrite both reference shapes within an arbitrary value.
     *
     * @param  mixed  $value  Any stored data value (scalar, array, or nested tree).
     * @param  array<string, string>  $idMap  Old entry id => new entry id.
     * @return array{0: mixed, 1: int} The rewritten value and the count of occurrences rewritten.
     */
    private function rewriteValue(mixed $value, array $idMap): array
    {
        // A relationship id stored as a plain string, or an inline entry link
        if (\is_string($value)) {
            return $this->rewriteString($value, $idMap);
        }

        // Recurse into arrays (groups, grids, replicator/Bard sets, prosemirror)
        if (\is_array($value)) {
            return $this->rewriteArray($value, $idMap);
        }

        // Numbers, booleans, and null can never hold an entry reference
        return [$value, 0];
    }

    /**
     * Rewrite a single string value: exact id match, then inline entry links.
     *
     * @param  array<string, string>  $idMap  Old entry id => new entry id.
     * @return array{0: string, 1: int} The rewritten string and occurrence count.
     */
    private function rewriteString(string $value, array $idMap): array
    {
        // Whole-string id (the `entries` fieldtype storage shape) — one reference
        if (isset($idMap[$value])) {
            return [$idMap[$value], 1];
        }

        // Cheap bail when the string holds no inline entry link at all
        if (! str_contains($value, self::ENTRY_LINK_PREFIX)) {
            return [$value, 0];
        }

        $count = 0;

        // Rewrite each `statamic://entry::<old-id>` token, bounded so a shorter id
        // can never partial-match the front of a longer id
        foreach ($idMap as $oldId => $newId) {
            $pattern = '/'.preg_quote(self::ENTRY_LINK_PREFIX.$oldId, '/').'(?![A-Za-z0-9_-])/';

            $value = (string) preg_replace(
                $pattern,
                self::ENTRY_LINK_PREFIX.$newId,
                $value,
                -1,
                $replacements
            );

            $count += $replacements;
        }

        return [$value, $count];
    }

    /**
     * Recurse into an array, rewriting each element and de-duplicating id lists.
     *
     * @param  array<mixed>  $value  The array to rewrite.
     * @param  array<string, string>  $idMap  Old entry id => new entry id.
     * @return array{0: array<mixed>, 1: int} The rewritten array and occurrence count.
     */
    private function rewriteArray(array $value, array $idMap): array
    {
        $count = 0;

        foreach ($value as $key => $item) {
            [$newItem, $itemCount] = $this->rewriteValue($item, $idMap);

            $value[$key] = $newItem;
            $count += $itemCount;
        }

        // When a multi-value relationship referenced both the survivor and a
        // retired entry, the rewrite collapses them onto the same id; drop the
        // resulting duplicate so the survivor is not listed twice. ONLY the
        // re-pointed (survivor) id is de-duplicated — every other value, including
        // legitimately repeated strings (e.g. repeated select options or links),
        // is preserved in its original order. Prosemirror node lists and keyed
        // structures are left untouched.
        if ($count > 0 && array_is_list($value) && $this->isListOfStrings($value)) {
            $value = $this->collapseRepeatedTargets($value, $idMap);
        }

        return [$value, $count];
    }

    /**
     * Read an item's stored data as an array (the `data()` getter is untyped).
     *
     * @return array<string, mixed>
     */
    private function dataArray(EntryItem|Variables|LocalizedTerm $item): array
    {
        /** @var Collection<string, mixed> $data */
        $data = $item->data();

        return $data->all();
    }

    /**
     * Every configured site handle.
     *
     * @return list<string>
     */
    private function siteHandles(): array
    {
        /** @var Collection<int, \Statamic\Sites\Site> $sites */
        $sites = Site::all();

        $handles = [];

        foreach ($sites as $site) {
            $handle = $site->handle();

            if (\is_string($handle)) {
                $handles[] = $handle;
            }
        }

        return $handles;
    }

    /**
     * Collapse repeated occurrences of a re-pointed (survivor) id that the
     * rewrite introduced — when a list referenced both a retired id and the
     * survivor, they now coincide. Only survivor ids are de-duplicated; every
     * other value (including legitimately repeated strings) is kept verbatim, in
     * its original order.
     *
     * @param  list<string>  $value
     * @param  array<string, string>  $idMap
     * @return list<string>
     */
    private function collapseRepeatedTargets(array $value, array $idMap): array
    {
        $targets = array_values($idMap);

        $seenTargets = [];
        $result = [];

        foreach ($value as $item) {
            // Only the survivor ids are subject to de-duplication
            if (\in_array($item, $targets, true)) {
                // Skip a survivor id already kept — the duplicate the merge created
                if (\in_array($item, $seenTargets, true)) {
                    continue;
                }

                $seenTargets[] = $item;
            }

            $result[] = $item;
        }

        return $result;
    }

    /**
     * Whether every element of a list is a string (i.e. an id array, not nodes).
     *
     * @param  array<mixed>  $value  A list array (sequential integer keys).
     *
     * @phpstan-assert-if-true list<string> $value
     */
    private function isListOfStrings(array $value): bool
    {
        foreach ($value as $item) {
            if (! \is_string($item)) {
                return false;
            }
        }

        return $value !== [];
    }
}
