<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Collection;
use Statamic\Contracts\Taxonomies\TermRepository;
use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Structures\Page;
use Statamic\Taxonomies\LocalizedTerm;

/**
 * Collects descendant entry or term IDs for control-panel scopes.
 *
 * Entries walk the native collection structure (a tree, acyclic by
 * construction); terms have no structure, so their descendants are resolved by
 * a reverse-map breadth-first walk over each term's raw stored `parent` value
 * with a seen-set guard against pre-existing cycles in the stored data.
 */
final class ContentDescendantIds
{
    /**
     * Build descendant entry IDs for a structure entry (children, grandchildren,
     * …), excluding the entry itself.
     *
     * Returns an empty list when the entry cannot be loaded or its collection is
     * not a structured tree (no descendants are resolvable).
     *
     * @param  int|string  $entryId  Statamic entry id
     * @return list<int|string>
     */
    public static function forEntry(
        int|string $entryId
    ): array {
        // Load the starting entry (a deleted id yields no descendants)
        $entry = EntryFacade::find($entryId);

        // Stop when the entry cannot be loaded
        if ($entry === null) {
            return [];
        }

        // Narrow to the concrete entry type so structure page() is analyzable
        \assert($entry instanceof StatamicEntry);

        // Resolve the entry's structure page (null when the collection has no tree)
        $page = $entry->page();

        // Stop when the entry is not part of a structured collection
        if (! $page instanceof Page) {
            return [];
        }

        // Initialize the ordered list of descendant ids
        $descendantIds = [];

        // Narrow the untyped structure return to a collection of pages
        /** @var Collection<int, Page> $descendantPages */
        $descendantPages = $page->flattenedPages();

        // Flatten the page's subtree (children and below, excluding the page itself)
        foreach ($descendantPages as $descendantPage) {
            // Read the descendant page id from the structure tree
            $descendantId = $descendantPage->id();
            // Skip descendant ids that are not usable entry id scalars
            if (! is_int($descendantId) && ! is_string($descendantId)) {
                continue;
            }

            if ($descendantId === '') {
                continue;
            }

            // Append the descendant id to the ordered list
            $descendantIds[] = $descendantId;
        }

        // Return the ordered list of descendant ids
        return $descendantIds;
    }

    /**
     * Build descendant term references for a taxonomy term id string
     * (`handle::slug`), excluding the term itself.
     *
     * Walks a parent → children reverse map built from every term in the same
     * taxonomy, breadth-first from the starting term, with a seen-set guard so a
     * pre-existing cycle in the stored `parent` data terminates the walk.
     *
     * @param  string  $termReference  Statamic term reference (`handle::slug`)
     * @return list<string>
     */
    public static function forTerm(
        string $termReference
    ): array {
        // Resolve the term repository from the container (not the Term facade)
        // so PHPUnit can bind a mock without fighting a cached facade root
        $termRepository = app(TermRepository::class);

        // Stop when the starting term does not exist in the repository
        if ($termRepository->find($termReference) === null) {
            return [];
        }

        // Derive the taxonomy handle from the canonical `handle::slug` reference
        $taxonomyHandle = explode(
            '::',
            $termReference
        )[0];

        // Stop when the reference carries no taxonomy handle
        if ($taxonomyHandle === '') {
            return [];
        }

        // Build the parent reference → child references reverse map
        $childrenByParent = [];

        // Narrow the untyped repository return to the augmentable term type
        /** @var iterable<LocalizedTerm> $taxonomyTerms */
        $taxonomyTerms = $termRepository->whereTaxonomy($taxonomyHandle);

        // Inspect every term in the taxonomy to record its parent link
        foreach ($taxonomyTerms as $term) {
            // Read the RAW stored parent, never the augmented `parent` field: the
            // latter routes through Terms::augment() → a Stache Term sub-query PER
            // term, solely to read one id (UAMS-Web/uams-statamic-archive#1367). A single-taxonomy parent field
            // stores the bare slug, so normalize it to a canonical `handle::slug`.
            $parentReference = self::parentReferenceFromRaw(
                $term->get('parent'),
                $taxonomyHandle
            );

            // Skip terms with no resolvable parent (taxonomy roots)
            if ($parentReference === '') {
                continue;
            }

            // Resolve the canonical child reference for the map value
            $childReference = self::objectTermId($term);

            // Skip children whose reference cannot be resolved
            if ($childReference === '') {
                continue;
            }

            // Record the child under its parent reference
            $childrenByParent[$parentReference][] = $childReference;
        }

        // Initialize the ordered list of descendant references
        $descendantRefs = [];

        // Seed the seen set with the starting term so it is never re-emitted
        $seenKeys = [$termReference => true];

        // Initialize the breadth-first queue with the starting term
        $queue = [$termReference];

        // Walk the reverse map until every reachable descendant is collected
        while ($queue !== []) {
            // Take the next reference to expand
            $currentReference = array_shift($queue);

            // Visit each child recorded under the current reference
            foreach ($childrenByParent[$currentReference] ?? [] as $childReference) {
                // Skip references already seen (dedupe and cycle guard)
                if (isset($seenKeys[$childReference])) {
                    continue;
                }

                // Record the child reference as seen
                $seenKeys[$childReference] = true;

                // Append the child reference to the ordered list of descendants
                $descendantRefs[] = $childReference;

                // Queue the child reference to expand its own descendants
                $queue[] = $childReference;
            }
        }

        // Return the ordered list of descendant references
        return $descendantRefs;
    }

    /**
     * Normalize a raw stored `parent` value to a canonical `handle::slug` reference.
     *
     * A single-taxonomy `max_items: 1` parent field stores the bare slug (optionally
     * wrapped in a one-element array); an already-qualified `handle::slug` reference is
     * left as-is. Returns '' when no usable parent slug is present (a taxonomy root).
     *
     * @param  mixed  $rawParent  The raw `get('parent')` value
     * @param  string  $taxonomyHandle  Taxonomy handle to qualify a bare slug
     */
    private static function parentReferenceFromRaw(
        mixed $rawParent,
        string $taxonomyHandle
    ): string {
        // A max_items:1 terms field may persist a single-element array; unwrap it.
        if (is_array($rawParent)) {
            $rawParent = $rawParent[0] ?? null;
        }

        // Only a non-empty string slug or reference is usable.
        if (! is_string($rawParent) || $rawParent === '') {
            return '';
        }

        // Qualify a bare slug with the taxonomy handle; keep an existing reference.
        return str_contains($rawParent, '::')
            ? $rawParent
            : $taxonomyHandle.'::'.$rawParent;
    }

    /**
     * Read `id()` from a term or parent object without assuming a rich contract.
     *
     * Uses `is_callable` so Mockery parent doubles (magic `__call` for `id()`)
     * still resolve, unlike `method_exists` alone.
     *
     * @param  object  $object  Term-like value (repository contract or stub)
     */
    private static function objectTermId(object $object): string
    {
        // Check if the runtime object can answer `id()` (real types or mocks)
        if (! is_callable(
            [
                $object,
                'id',
            ]
        )) {
            // Return an empty string when the object cannot answer `id()`
            return '';
        }

        // Dispatch id() without static analysis on unknown contract methods
        $resolvedId = \call_user_func(
            [
                $object,
                'id',
            ]
        );

        // Narrow the dispatched id to a scalar before string conversion
        \assert(is_scalar($resolvedId) || $resolvedId === null);

        // Return the canonical id as a string
        return (string) $resolvedId;
    }
}
