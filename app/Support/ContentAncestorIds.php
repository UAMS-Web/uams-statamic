<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Statamic\Contracts\Taxonomies\TermRepository;
use Statamic\Entries\Entry;
use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Facades\Entry as EntryFacade;

/**
 * Collects ancestor entry or term IDs for control-panel scopes, walking Statamic
 * parent links with cycle, self-parent, and hop-cap guards.
 */
final class ContentAncestorIds
{
    /**
     * Maximum parent hops per walk (structure max depth can be unlimited).
     *
     * Hard cap to bound work and stack safety on corrupt trees.
     */
    private const int MAX_ANCESTOR_HOPS = 500;

    /**
     * Build ordered ancestor entry IDs (immediate parent first) for a structure
     * entry.
     *
     * @param  int|string  $entryId  Statamic entry id
     * @param  int|null  $maxHopsOverride  Hop cap for tests; null uses default
     * @return list<int|string>
     */
    public static function forEntry(
        int|string $entryId,
        ?int $maxHopsOverride = null
    ): array {
        // Resolve the starting entry or fail like the legacy helper
        $resolvedEntry = EntryFacade::findOrFail($entryId);

        // Narrow to the concrete entry type so structure parent() is analyzable
        \assert($resolvedEntry instanceof StatamicEntry);

        // Initialize the walk cursor on the concrete entry
        $entry = $resolvedEntry;

        // Initialize the ordered list of ancestor ids
        $ancestorIds = [];

        // Initialize the set of ids already appended
        $seenAncestorKeys = [];

        // Resolve the hop cap (tests may pass a smaller override)
        $maxHops = $maxHopsOverride ?? self::MAX_ANCESTOR_HOPS;

        // Track how many parent links were successfully followed
        $hopsUsed = 0;

        // Walk parents until root, missing entry, abnormal stop, or hop cap
        while (true) {
            // ---
            // Stop when the hop cap is reached before another parent follow
            // ---

            // Check if the hop cap has been reached
            if ($hopsUsed >= $maxHops) {
                // Narrow the parent page to the concrete entry type or null
                /** @var Entry|null $parentPageEntry */
                $parentPageEntry = $entry->parent();

                // Resolve whether another parent still exists past the cap
                $parentPastCap = $parentPageEntry?->id();

                // Log only when the chain would continue past the cap
                if ($parentPastCap !== null && $parentPastCap !== '') {
                    // Narrow the current entry id to a scalar for interpolation
                    $currentEntryId = $entry->id();
                    \assert(is_scalar($currentEntryId) || $currentEntryId === null);

                    Log::warning(
                        'Ancestor walk stopped: hop cap reached for entry chain.',
                        [
                            'entry_id' => (string) $currentEntryId,
                            'reason' => 'hop_limit',
                            'max_hops' => $maxHops,
                        ]
                    );
                }

                // Return the ordered list of ancestor ids
                return $ancestorIds;
            }

            // Narrow the parent page to the concrete entry type or null
            /** @var Entry|null $parentPage */
            $parentPage = $entry->parent();

            // Resolve the parent page id from the structure tree
            /** @var int|string|null $parentId */
            $parentId = $parentPage?->id();

            // ---
            // Stop when there is no parent (reached root)
            // ---

            // Check if there is no parent (reached root)
            if (
                $parentId === null
                || $parentId === ''
            ) {
                // Return the ordered list of ancestor ids
                return $ancestorIds;
            }

            // Convert the parent id to a string for map keys and comparisons
            $parentKey = (string) $parentId;

            // Narrow the current entry id to a scalar for string conversion
            $currentEntryId = $entry->id();
            \assert(is_scalar($currentEntryId) || $currentEntryId === null);

            // Convert the entry id to a string for map keys and comparisons
            $currentKey = (string) $currentEntryId;

            // ---
            // Stop when the entry is its own parent (corrupt tree)
            // ---

            // Check if the entry is its own parent (corrupt tree)
            if ($parentKey === $currentKey) {
                // Log a warning when the entry is its own parent (corrupt tree)
                Log::warning(
                    'Ancestor walk stopped: entry parent points to self.',
                    [
                        'entry_id' => $currentKey,
                        'reason' => 'self_parent',
                    ]
                );

                // Return the ordered list of ancestor ids
                return $ancestorIds;
            }

            // ---
            // Stop when the parent was already collected (cycle)
            // ---

            // Check if the parent was already collected (cycle)
            if (isset($seenAncestorKeys[$parentKey])) {
                // Log a warning when the parent was already collected (cycle)
                Log::warning(
                    'Ancestor walk stopped: cycle in entry parent chain.',
                    [
                        'entry_id' => $currentKey,
                        'parent_id' => $parentKey,
                        'reason' => 'cycle',
                    ]
                );

                // Return the ordered list of ancestor ids
                return $ancestorIds;
            }

            // Record the parent id as seen
            $seenAncestorKeys[$parentKey] = true;

            // Append the parent id to the ordered list of ancestor ids
            $ancestorIds[] = $parentId;

            // Load the parent entry for the next hop (missing entry ends walk)
            $nextEntry = EntryFacade::find($parentId);

            // ---
            // Stop when the parent entry cannot be loaded
            // ---

            // Check if the parent entry cannot be loaded
            if ($nextEntry === null) {
                // Return the ordered list of ancestor ids
                return $ancestorIds;
            }

            // Narrow the parent to the concrete entry type for the next iteration
            \assert($nextEntry instanceof StatamicEntry);

            // Advance the cursor to the parent entry
            $entry = $nextEntry;

            // Increment the hop count
            $hopsUsed++;
        }
    }

    /**
     * Build ordered ancestor term references (immediate parent first) for a
     * taxonomy term id string (`handle::slug`).
     *
     * @param  string  $termReference  Statamic term reference
     * @param  int|null  $maxHopsOverride  Hop cap for tests; null uses default
     * @return list<string>
     */
    public static function forTerm(
        string $termReference,
        ?int $maxHopsOverride = null
    ): array {
        // ---
        // Resolve the term repository from the container (not the Term facade)
        // so PHPUnit can bind a mock without fighting a cached facade root
        // ---
        $termRepository = app(TermRepository::class);

        // Resolve the starting term or fail like the legacy helper
        $term = $termRepository->findOrFail($termReference);

        // Initialize the ordered list of ancestor references
        $ancestorRefs = [];

        // Initialize the set of references already appended
        $seenAncestorKeys = [];

        // Resolve the hop cap (tests may pass a smaller override)
        $maxHops = $maxHopsOverride ?? self::MAX_ANCESTOR_HOPS;

        // Track how many parent links were successfully followed
        $hopsUsed = 0;

        // Walk parents until root, missing term, abnormal stop, or hop cap
        while (true) {
            // ---
            // Stop when the hop cap is reached before another parent follow
            // ---

            // Check if the hop cap has been reached
            if ($hopsUsed >= $maxHops) {
                // Resolve the parent term from the augmented data when present
                $parentTermPeek = $term->parent ?? null;

                // Initialize the parent id past the cap
                $parentPastCap = null;

                // Resolve the canonical parent id when a parent term exists
                if (is_object($parentTermPeek)) {
                    // Resolve the canonical parent id
                    $parentPastCap = self::objectTermId($parentTermPeek);
                }

                // Check if the parent id past the cap is not null and not empty
                if ($parentPastCap !== null && $parentPastCap !== '') {
                    // Log a warning when the chain would continue past the cap
                    Log::warning(
                        'Ancestor walk stopped: hop cap reached for term chain.',
                        [
                            'term_reference' => self::objectTermId($term),
                            'reason' => 'hop_limit',
                            'max_hops' => $maxHops,
                        ]
                    );
                }

                // Return the ordered list of ancestor references
                return $ancestorRefs;
            }

            // Resolve the parent term from augmented data when present
            $parentTerm = $term->parent ?? null;

            // ---
            // Stop when there is no parent term
            // ---

            // Check if there is no parent term
            if (! is_object($parentTerm)) {
                // Return the ordered list of ancestor references
                return $ancestorRefs;
            }

            // Resolve canonical parent reference for find() and dedupe keys
            $parentId = self::objectTermId($parentTerm);

            // ---
            // Stop when parent id cannot be resolved
            // ---

            // Check if the parent id cannot be resolved
            if ($parentId === '') {
                // Return the ordered list of ancestor references
                return $ancestorRefs;
            }

            // Normalize current term reference for comparisons
            $currentKey = self::objectTermId($term);

            // ---
            // Stop when the term is its own parent (corrupt tree)
            // ---

            // Check if the term is its own parent (corrupt tree)
            if ($parentId === $currentKey) {
                // Log a warning when the term is its own parent (corrupt tree)
                Log::warning(
                    'Ancestor walk stopped: term parent points to self.',
                    [
                        'term_reference' => $currentKey,
                        'reason' => 'self_parent',
                    ]
                );

                // Return the ordered list of ancestor references
                return $ancestorRefs;
            }

            // ---
            // Stop when the parent was already collected (cycle)
            // ---

            // Check if the parent was already collected (cycle)
            if (isset($seenAncestorKeys[$parentId])) {
                // Log a warning when the parent was already collected (cycle)
                Log::warning(
                    'Ancestor walk stopped: cycle in term parent chain.',
                    [
                        'term_reference' => $currentKey,
                        'parent_id' => $parentId,
                        'reason' => 'cycle',
                    ]
                );

                // Return the ordered list of ancestor references
                return $ancestorRefs;
            }

            // Record the parent id as seen
            $seenAncestorKeys[$parentId] = true;

            // Append the parent id to the ordered list of ancestor references
            $ancestorRefs[] = $parentId;

            // Load the parent term for the next hop (missing term ends walk)
            $nextTerm = $termRepository->find($parentId);

            // ---
            // Stop when the parent term cannot be loaded
            // ---

            // Check if the parent term cannot be loaded
            if ($nextTerm === null) {
                // Return the ordered list of ancestor references
                return $ancestorRefs;
            }

            // Advance the cursor to the parent term
            $term = $nextTerm;

            // Increment the hop count
            $hopsUsed++;
        }
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
