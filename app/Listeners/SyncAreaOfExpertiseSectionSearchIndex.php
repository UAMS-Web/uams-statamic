<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Search\AreaOfExpertiseSectionDocument;
use App\Search\AreaOfExpertiseSectionProvider;
use App\Services\ClinicalExpertise\ClinicalExpertiseSectionResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry;
use Statamic\Events\EntryDeleted;
use Statamic\Events\EntrySaved;
use Statamic\Facades\Search;
use Statamic\Search\Index;
use Statamic\Search\UpdateItemIndexes;

/**
 * Keeps the synthetic Area-of-Expertise section documents (UAMS-Web/uams-statamic-archive#1527) fresh when their
 * source AoE entry changes, because the `local` driver's own reindex
 * ({@see UpdateItemIndexes}) only tracks the saved Entry — never the
 * derived {@see AreaOfExpertiseSectionDocument}s that {@see AreaOfExpertiseSectionProvider}
 * synthesizes from it.
 *
 * On every AoE save it removes all of that entry's section documents and re-inserts the
 * currently valid ones, so adding/removing a section's source items, toggling `searchable`,
 * or (un)publishing the AoE is reflected in search; on delete it removes them. The full
 * delete-then-reinsert is idempotent and bounded (five sections), and the actual index write
 * happens off the request via the driver's queued insert job.
 */
final class SyncAreaOfExpertiseSectionSearchIndex implements ShouldQueue
{
    /**
     * The search indexes this provider is registered into (config/statamic/search.php). Extend
     * this if the `clinical_expertise_section:*` searchable is added to another index.
     *
     * @var list<string>
     */
    private const array INDEXES = ['default'];

    /**
     * @param  AreaOfExpertiseSectionProvider  $provider  Computes an AoE's valid section references.
     */
    public function __construct(
        private readonly AreaOfExpertiseSectionProvider $provider
    ) {}

    /**
     * Only queue this listener for an Area-of-Expertise save/delete. `EntrySaved`/`EntryDeleted`
     * fire for every collection site-wide, so gating here (Laravel consults `shouldQueue()`
     * before enqueuing) keeps a non-AoE save — the vast majority, including bulk imports — from
     * pushing a no-op `CallQueuedListener` job, while a qualifying AoE save still reindexes
     * off-request.
     */
    public function shouldQueue(EntrySaved|EntryDeleted $event): bool
    {
        return $this->isAreaOfExpertise($event->entry);
    }

    /**
     * Resync (save) or purge (delete) an edited AoE's section documents across the indexes.
     */
    public function handle(EntrySaved|EntryDeleted $event): void
    {
        $entry = $event->entry;
        if (! $entry instanceof Entry || ! $this->isAreaOfExpertise($entry)) {
            return;
        }

        $id = $entry->id();
        if (! \is_string($id)) {
            return;
        }

        $removing = $event instanceof EntryDeleted;

        foreach (self::INDEXES as $indexHandle) {
            /** @var Index $index */
            $index = Search::index($indexHandle);

            // Only maintain an already-built index incrementally; if it has not been built yet,
            // the next query's ensureExists() rebuilds it in full from the provider.
            if (! $index->exists()) {
                continue;
            }

            // Remove every possible section document for this AoE (idempotent) so an emptied
            // section, a toggled-off `searchable`, an unpublish, or a delete drops its stale docs.
            foreach (ClinicalExpertiseSectionResolver::tokens() as $token) {
                $index->delete(new AreaOfExpertiseSectionDocument($id, $token, '', '', ''));
            }

            // A save re-inserts the currently valid sections; a delete only removes.
            if ($removing) {
                continue;
            }

            foreach ($this->provider->sectionReferencesFor($entry) as $reference) {
                $index->insert($reference);
            }
        }
    }

    /**
     * Whether an event's entry is an Area of Expertise — the cheap collection-handle gate shared
     * by {@see self::shouldQueue()} and {@see self::handle()}. The collection facade is loosely
     * typed, so it is narrowed before the handle comparison.
     */
    private function isAreaOfExpertise(mixed $entry): bool
    {
        if (! $entry instanceof Entry) {
            return false;
        }

        /** @var Collection|null $collection */
        $collection = $entry->collection();

        return $collection !== null && $collection->handle() === AreaOfExpertiseSectionProvider::COLLECTION;
    }
}
