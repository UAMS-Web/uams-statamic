<?php

declare(strict_types=1);

namespace App\Services\ComputedFields\Reverse;

use Statamic\Entries\Entry;
use Statamic\Entries\EntryCollection;
use Statamic\Facades\Blink;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Stache\Query\EntryQueryBuilder;

/**
 * Resolves the reverse of a directional `entries` pointer: every entry in a set
 * of source collections whose named field references a given target entry id.
 *
 * This is the query engine shared by the collection-level reverse-reference
 * computeds (UAMS-Web/uams-statamic-archive#1372 facilities, UAMS-Web/uams-statamic-archive#1373 organizations, UAMS-Web/uams-statamic-archive#1374 people) surfaced by the
 * relationship audit behind UAMS-Web/uams-statamic-archive#1360. Those pointers are directional many-to-one/many
 * links whose reverse is a query, not authored data, so the reverse is derived on
 * read rather than stored as a second edge.
 *
 * Two matching modes:
 *
 * - {@see resolve()} — a **top-level** `entries` field pointer (e.g. a facility's
 *   `parking_facility`, an org member's `service_line`, a provider entry's `person`).
 * - {@see resolveNested()} — a pointer **nested inside a group/structure field**,
 *   matched by deep-collecting ids from the whole field value (e.g. a person's
 *   authored/credited content, where each `creator` group role sub-field —
 *   `writer`, `editor`, `host`, … — is itself an `entries` pointer to `people`).
 *
 * ## Why membership is matched in PHP, not with a Stache `where`/JSON predicate
 *
 * A Statamic `entries` field stores its value inconsistently across content: a
 * `max_items: 1` field is a scalar string (`parking_facility: <id>`) when authored
 * through the CP but a single-element array (`parking_facility:\n  - <id>`) when
 * written by the WordPress import — both shapes occur for the same handle. Neither
 * Stache query predicate covers BOTH shapes: on the flat-file Stache
 * `whereJsonContains($field, $id)` matches an **array**-stored value but silently
 * MISSES a **scalar**-stored id (it returns nothing for the CP-authored scalar),
 * while `where($field, $id)` matches the scalar but THROWS on an array value — its
 * in-memory equality filter calls `strtolower()` on the array. So for a handle that
 * occurs as both a scalar and an array, `whereJsonContains` drops the scalars and
 * `where` crashes on the arrays; there is no single predicate that is safe for the
 * mixed storage these `max_items: 1` reverse pointers actually carry.
 *
 * Instead this loads the candidate collections' published entries — already resident
 * in the warm Stache, so no extra I/O — and matches membership in PHP against each
 * pointing entry's field value normalized to a list of ids. That is correct for every
 * storage shape (scalar, single-element array, multi-element array, and — for the
 * nested mode — ids nested at any depth inside a group value) and independent of index
 * state. Each result set is {@see Blink}-memoized per (collections, field, id, mode,
 * published) for the request, so an entry that renders several reverse computeds
 * resolves each reverse once, not once per render.
 */
class ReverseEntryReferenceResolver
{
    /**
     * Every entry in $sourceCollections whose top-level `entries` field $field
     * references $targetId, restricted to published entries unless $publishedOnly
     * is false.
     *
     * @param  string|list<string>  $sourceCollections  The collection(s) whose entries may point at the target.
     * @param  string  $field  The `entries` field handle on the pointing entries.
     * @param  mixed  $targetId  The referenced (target) entry id (accepts `Entry::id()`); a null/empty/non-string id has no reverse.
     * @param  bool  $publishedOnly  Restrict the reverse to published pointing entries.
     * @return EntryCollection<int, Entry>
     */
    public function resolve(
        string|array $sourceCollections,
        string $field,
        mixed $targetId,
        bool $publishedOnly = true
    ): EntryCollection {
        return $this->resolveMatching(
            $sourceCollections,
            $field,
            fn (Entry $entry): array => $this->referencedIds($entry, $field),
            $targetId,
            $publishedOnly
        );
    }

    /**
     * Every entry in $sourceCollections whose field(s) $fields reference $targetId
     * anywhere within their (possibly nested) values — for a pointer nested inside a
     * group/structure field rather than stored as a single top-level `entries` field.
     * Ids are deep-collected from the whole field value(s), so this matches a target
     * named by any sub-field of a group (e.g. any `creator` role) or embedded anywhere
     * in a page-builder Bard/replicator (e.g. a transcluded `reusable_content` entry).
     * Passing several fields unions their nested ids (a page can carry more than one
     * page-builder field: `content`, `body`, `page_builder`).
     *
     * Only sound when every id nested in $fields is a reference of interest — true for
     * a group whose sub-fields are all `entries` pointers (the `creator` group), and
     * for page-builder fields matched against a target whose id appears there only via
     * transclusion (`reusable_content` / `tables` / `calculators`).
     *
     * @param  string|list<string>  $sourceCollections  The collection(s) whose entries may point at the target.
     * @param  string|list<string>  $fields  The group/structure/page-builder field handle(s) whose nested value holds the pointers.
     * @param  mixed  $targetId  The referenced (target) entry id (accepts `Entry::id()`); a null/empty/non-string id has no reverse.
     * @param  bool  $publishedOnly  Restrict the reverse to published pointing entries.
     * @return EntryCollection<int, Entry>
     */
    public function resolveNested(
        string|array $sourceCollections,
        string|array $fields,
        mixed $targetId,
        bool $publishedOnly = true
    ): EntryCollection {
        $fieldList = is_string($fields) ? [$fields] : $fields;

        // Swapping the concatenation gives `<fields>nested:`, which is still distinct
        // from every shallow key: every caller passes blueprint field handles, and a
        // blueprint handle cannot contain `:`.
        // @pest-mutate-ignore: ConcatSwitchSides
        return $this->resolveMatching(
            // A distinct key namespace so a shallow and a nested resolve of the same
            // (collections, field, id) never share a memoized result.
            $sourceCollections,
            'nested:'.implode(',', $fieldList),
            fn (Entry $entry): array => $this->nestedReferencedIds($entry, $fieldList),
            $targetId,
            $publishedOnly
        );
    }

    /**
     * Shared engine: guard the target id, load the candidate collections' entries,
     * and keep those whose ids (via $extractIds) include $targetId — memoized per
     * distinct reverse for the request.
     *
     * @param  string|list<string>  $sourceCollections
     * @param  string  $keyDiscriminator  Stable cache-key fragment identifying the matcher (the field, namespaced by mode).
     * @param  callable(Entry): list<string>  $extractIds  The ids $entry references under this matcher.
     * @return EntryCollection<int, Entry>
     */
    private function resolveMatching(
        string|array $sourceCollections,
        string $keyDiscriminator,
        callable $extractIds,
        mixed $targetId,
        bool $publishedOnly
    ): EntryCollection {
        // An unsaved or id-less target cannot be referenced by anything yet. Letting
        // '' through instead is unobservable: every extractor drops empty ids, so the
        // query would match nothing.
        // @pest-mutate-ignore: EmptyStringToNotEmpty
        if (! is_string($targetId) || $targetId === '') {
            return EntryCollection::make();
        }

        // Normalize the collection argument to a list for the query and cache key.
        $collections = is_string($sourceCollections) ? [$sourceCollections] : $sourceCollections;

        // Memoize the resolved set per distinct reverse for the request. The parts are
        // joined with `|` because handles contain `_`: joined with `_`, collection
        // `a` with field `b_c` and collection `a_b` with field `c` shared one key.
        // The status is a word rather than 1/0 so no integer mutant can merge the two
        // statuses; negating the ternary only swaps the words, which stay distinct.
        // @pest-mutate-ignore: TernaryNegated
        $cacheKey = sprintf(
            'reverse_entry_reference|%s|%s|%s|%s',
            implode('.', $collections),
            $keyDiscriminator,
            $targetId,
            $publishedOnly ? 'published' : 'any'
        );

        /** @var EntryCollection<int, Entry> $result */
        $result = Blink::once(
            $cacheKey,
            fn (): EntryCollection => $this->query($collections, $extractIds, $targetId, $publishedOnly)
        );

        return $result;
    }

    /**
     * Load the candidate collections' entries and keep those whose extracted ids
     * include $targetId, matching membership in PHP against the (possibly nested)
     * field value.
     *
     * @param  list<string>  $collections
     * @param  callable(Entry): list<string>  $extractIds
     * @return EntryCollection<int, Entry>
     */
    private function query(
        array $collections,
        callable $extractIds,
        string $targetId,
        bool $publishedOnly
    ): EntryCollection {
        // Drop handles with no live collection before querying. `whereStatus()`
        // below asks Statamic to apply per-collection status logic, which
        // dereferences each queried collection (`$collection->handle()`) and
        // fatals ("Call to a member function handle() on null") on a handle that
        // resolves to null. Every real source collection exists in production, but
        // a configured source can be absent in a minimal/isolated Stache (e.g. the
        // people-merge tests provision only `people`) — and a collection that does
        // not exist holds no reverse references, so skipping it is also correct.
        // The list is only read by value (`whereIn`, `=== []`), so the gaps
        // array_filter() leaves are harmless.
        // @pest-mutate-ignore: UnwrapArrayValues
        $collections = array_values(array_filter(
            $collections,
            fn (string $handle): bool => CollectionFacade::findByHandle($handle) !== null
        ));

        if ($collections === []) {
            return EntryCollection::make();
        }

        /** @var EntryQueryBuilder $query */
        $query = EntryFacade::query();
        $query->whereIn('collection', $collections);

        if ($publishedOnly) {
            $query->whereStatus('published');
        }

        /** @var EntryCollection<int, Entry> $candidates */
        $candidates = $query->get();

        // An entry query only yields Entry instances, so this is never false at run
        // time. PHPStan types the query result's items as mixed and rejects an
        // Entry-typed closure, so the instanceof is what narrows them.
        // @pest-mutate-ignore: InstanceOfToTrue
        /** @var EntryCollection<int, Entry> $matches */
        $matches = $candidates
            ->filter(fn (mixed $entry): bool => $entry instanceof Entry
                && in_array($targetId, $extractIds($entry), true))
            ->values();

        return $matches;
    }

    /**
     * The referenced entry ids stored in a top-level $field on $entry, normalized to
     * a list of id strings regardless of whether the field stores a scalar or array.
     *
     * @return list<string>
     */
    private function referencedIds(
        Entry $entry,
        string $field
    ): array {
        $value = $entry->get($field);

        // Without this return, null becomes [null], which the filter below drops.
        // @pest-mutate-ignore: RemoveEarlyReturn
        if ($value === null) {
            return [];
        }

        // A `max_items: 1` field stores a scalar; a multi field stores an array.
        $ids = is_array($value) ? $value : [$value];

        // Keep only non-empty id strings (references are stored as string ids). The
        // only consumer is a strict in_array() of a non-empty string target, which
        // can never match an empty or non-string id or depend on key order.
        // @pest-mutate-ignore: UnwrapArrayFilter, UnwrapArrayValues, BooleanAndToBooleanOr, EmptyStringToNotEmpty
        return array_values(array_filter(
            $ids,
            fn (mixed $id): bool => is_string($id) && $id !== ''
        ));
    }

    /**
     * The union of every non-empty id string nested anywhere within each of $fields
     * on $entry — for matching a pointer nested inside one or more group/structure or
     * page-builder fields.
     *
     * @param  list<string>  $fields
     * @return list<string>
     */
    private function nestedReferencedIds(
        Entry $entry,
        array $fields
    ): array {
        $ids = [];

        foreach ($fields as $field) {
            foreach ($this->collectReferencedIds($entry->get($field)) as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * Every non-empty id string reachable anywhere within $value, recursing into
     * nested arrays — for matching a pointer nested inside a group/structure field
     * (e.g. the `creator` group's per-role `entries` sub-fields).
     *
     * @return list<string>
     */
    private function collectReferencedIds(mixed $value): array
    {
        if (is_string($value)) {
            // The target is never '' (resolveMatching() returns first), so keeping an
            // empty id cannot change a match.
            // @pest-mutate-ignore: EmptyStringToNotEmpty
            return $value !== '' ? [$value] : [];
        }

        if (! is_array($value)) {
            return [];
        }

        $ids = [];

        foreach ($value as $item) {
            foreach ($this->collectReferencedIds($item) as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }
}
