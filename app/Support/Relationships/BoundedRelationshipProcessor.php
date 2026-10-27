<?php

declare(strict_types=1);

namespace App\Support\Relationships;

use App\Providers\StillatRelationshipsServiceProvider;
use Stillat\Relationships\Comparisons\ComparisonResult;
use Stillat\Relationships\EntryRelationship;
use Stillat\Relationships\Processors\RelationshipProcessor;

/**
 * A {@see RelationshipProcessor} that resets its per-operation working sets
 * before processing each relationship, so the shared singleton processor cannot
 * accumulate effected entities across a long-running bulk-save path.
 *
 * ## The problem this bounds
 *
 * The vendor processor is held as a single instance by the `RelationshipManager`
 * singleton and appends every effected entry / user / term to
 * `$effectedEntries` / `$effectedUsers` / `$effectedTerms` via `+=` inside
 * `getEffectedEntries()`, but never resets them. On the two bulk-save hot paths —
 * the WordPress importer and `php please relate:fill` — that buffer grows for the
 * whole run (unbounded memory), and because array `+=` keeps the value already
 * stored under a key, an entity cached early is served *stale* on a later
 * reciprocal touch within the same process (a snapshot clobber when that entity
 * was edited in between).
 *
 * ## Why resetting per relationship is safe
 *
 * Every relationship-processing path (`processManyToMany`, `processManyToOne`,
 * `processOneToOne`, `processOneToMany`) reads only the ids of the relationship
 * currently being processed — the ones `processRelationship()` just appended via
 * `getEffectedEntries()`. Nothing reads across relationships or across saves, and
 * the cascade of reciprocal saves is blocked from re-entering by the addon's
 * `isProcessingRelationships()` guard, so there is no nested `processRelationship`
 * whose working set could be cleared mid-flight. Clearing at the start of every
 * `processRelationship()` call therefore bounds memory to a single relationship's
 * effected set and forces a fresh fetch every time — fixing both the top-level
 * save path (`process()` → `processRelationship()`) and `relate:fill`, which calls
 * `processRelationship()` directly per item and bypasses `process()`.
 *
 * Bound over the vendor class in {@see StillatRelationshipsServiceProvider}.
 */
class BoundedRelationshipProcessor extends RelationshipProcessor
{
    /**
     * Reset the per-operation working sets, then delegate to the vendor processor.
     *
     * @param  EntryRelationship  $relationship  The relationship being processed
     * @param  ComparisonResult  $results  The add/remove comparison for this relationship
     */
    #[\Override]
    public function processRelationship(
        $relationship,
        $results
    ): void {
        $this->resetWorkingSet();

        parent::processRelationship($relationship, $results);
    }

    /**
     * Clear the per-operation working sets the parent accumulates via `+=`.
     *
     * Bounds memory on bulk-save paths and forces a fresh fetch of effected
     * entities on every relationship, so a stale snapshot cannot be re-saved.
     */
    public function resetWorkingSet(): void
    {
        // Full Entry / User / Term instances — the unbounded-memory surface.
        $this->effectedEntries = [];
        $this->effectedUsers = [];
        $this->effectedTerms = [];

        // Write-only id/value trace the one-to-many path appends to; reset for parity.
        $this->dependencies = [];
    }

    /**
     * Sizes of the per-operation working sets, for tests and bulk-run inspection.
     * A bounded processor never carries a prior save's or item's entities here.
     *
     * @return array{entries: int, users: int, terms: int, dependencies: int}
     */
    public function workingSetSizes(): array
    {
        return [
            'entries' => \count($this->effectedEntries),
            'users' => \count($this->effectedUsers),
            'terms' => \count($this->effectedTerms),
            // The vendor's `$dependencies` is untyped (mixed to static analysis); it is
            // always an array at runtime, so guard the count rather than cast.
            'dependencies' => \is_array($this->dependencies) ? \count($this->dependencies) : 0,
        ];
    }
}
