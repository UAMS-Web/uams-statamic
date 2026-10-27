<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Services\KnowledgeBase\KnowledgeBaseMountUriRefresher;
use Statamic\Entries\Entry;
use Statamic\Events\CollectionTreeSaved;
use Statamic\Events\EntryDeleted;
use Statamic\Events\EntrySaved;
use Statamic\Structures\CollectionTree;

/**
 * Refreshes mounted knowledge base URIs whenever the `pages` hierarchy changes.
 *
 * A `knowledge_base` root mounted under a page resolves at that page's URI, but
 * lives in a different collection, so Statamic does not re-stamp it when the
 * page is renamed, moved, or deleted. This listener reacts to `pages` entry and
 * structure changes and delegates to `KnowledgeBaseMountUriRefresher` to bring
 * the knowledge bases' `uri` index back in sync.
 */
class RefreshKnowledgeBaseMountUris
{
    /**
     * Create the listener.
     *
     * @param  KnowledgeBaseMountUriRefresher  $refresher  Service that re-stamps mounted knowledge base URIs
     */
    public function __construct(
        private readonly KnowledgeBaseMountUriRefresher $refresher
    ) {}

    /**
     * Handle a pages entry or structure change.
     *
     * @param  EntrySaved|EntryDeleted|CollectionTreeSaved  $event  The dispatched event
     */
    public function handle(EntrySaved|EntryDeleted|CollectionTreeSaved $event): void
    {
        // Ignore changes that do not concern the pages hierarchy.
        if (! $this->concernsPages($event)) {
            // Nothing to refresh for non-pages changes
            return;
        }

        // Re-stamp any mounted knowledge base whose URI drifted.
        $this->refresher->refreshAll();
    }

    /**
     * Determine whether the event concerns the `pages` collection or its tree.
     *
     * @param  EntrySaved|EntryDeleted|CollectionTreeSaved  $event  The dispatched event
     * @return bool True when a pages entry or the pages structure changed
     */
    private function concernsPages(EntrySaved|EntryDeleted|CollectionTreeSaved $event): bool
    {
        // Pages structure save: compare the tree's handle.
        if ($event instanceof CollectionTreeSaved) {
            /** @var CollectionTree|null $tree */
            $tree = $event->tree;

            // The collection tree handle matches the collection handle
            return $tree?->handle() === 'pages';
        }

        /** @var Entry|null $entry */
        $entry = $event->entry;

        // Pages entry save or delete: compare the entry's collection handle.
        return $entry?->collectionHandle() === 'pages';
    }
}
