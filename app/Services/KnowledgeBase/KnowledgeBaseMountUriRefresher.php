<?php

declare(strict_types=1);

namespace App\Services\KnowledgeBase;

use App\Entries\KnowledgeBaseEntry;
use Statamic\Entries\Entry;
use Statamic\Facades\Blink;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\Stache;
use Statamic\Stache\Indexes\Index;
use Statamic\Stache\Query\EntryQueryBuilder;
use Statamic\Stache\Stores\AggregateStore;
use Statamic\Stache\Stores\Store;
use Statamic\Structures\Page;

/**
 * Keeps the persisted `uri` index of mounted knowledge bases in step with their
 * mount-parent pages.
 *
 * A mounted `knowledge_base` root resolves its URI live as
 * `{mount_parent_uri}/{slug}` (see `KnowledgeBaseEntry::routeData()`), but it is
 * not a structural descendant of its mount-parent page. When that page is
 * renamed, moved, or deleted, Statamic re-stamps the page's own structural
 * descendants and leaves the knowledge base's `uri` index untouched, so
 * `Data::findByUri()` (used by routing and breadcrumbs) would go stale even
 * though `Entry::uri()` recomputes correctly. This service re-stamps the
 * affected knowledge base subtrees so both agree again — automatically, with no
 * manual re-stamping of any stored URI.
 */
class KnowledgeBaseMountUriRefresher
{
    /**
     * Guards against re-entrant refreshes within a single request.
     */
    private bool $refreshing = false;

    /**
     * Re-stamp the `uri` index of every mounted knowledge base whose computed
     * URI no longer matches the index.
     *
     * Called when a `pages` entry or the pages structure changes. Knowledge
     * bases without a mount parent are skipped (their URIs are unaffected), and
     * subtrees already in sync are left alone so bulk page operations stay cheap.
     */
    public function refreshAll(): void
    {
        // Avoid re-entrancy when index updates trigger further listeners.
        if ($this->refreshing) {
            // Bail out of the nested call
            return;
        }

        // Mark a refresh as in progress for the duration of this pass.
        $this->refreshing = true;

        try {
            // Drop per-request URI caches so URIs recompute against the changed pages.
            $this->flushUriCaches();

            // Re-stamp each mounted knowledge base root whose URI drifted.
            $this->mountedRoots()->each(fn (Entry $root) => $this->restampIfStale($root));
        } finally {
            // Always clear the guard, even if a root failed to re-stamp.
            $this->refreshing = false;
        }
    }

    /**
     * Re-stamp a single knowledge base subtree unconditionally.
     *
     * Used when the root's own mount reference changes on save, where the index
     * is known to be stale.
     *
     * @param  Entry  $root  The `knowledge_base` root entry whose subtree to re-stamp
     */
    public function restampSubtree(Entry $root): void
    {
        // Collect the root and all descendant entry ids from the structure.
        $ids = $this->subtreeIds($root);

        // Check whether the subtree resolved to any pages.
        if ($ids->isEmpty()) {
            // Nothing to re-stamp
            return;
        }

        // Drop per-request URI caches so they recompute against the current mount parent.
        $this->flushUriCaches();

        // Rebuild the persisted uri index for the whole subtree.
        Collection::findByHandle('knowledge_bases')?->updateEntryUris($ids->all());
    }

    /**
     * Re-stamp a knowledge base subtree only when its root's live URI differs
     * from the value currently stored in the `uri` index.
     *
     * @param  Entry  $root  The `knowledge_base` root entry to check
     */
    private function restampIfStale(Entry $root): void
    {
        // Collect the root and all descendant entry ids from the structure.
        $ids = $this->subtreeIds($root);

        // Check whether the subtree resolved to any pages.
        if ($ids->isEmpty()) {
            // Nothing to re-stamp
            return;
        }

        // Compare the freshly computed root URI against the indexed value.
        // (`refreshAll()` has already flushed the per-request URI caches.)
        /** @var string $rootId */
        $rootId = $root->id();
        if ($this->indexedUri($rootId) === $root->uri()) {
            // The index is already current; skip the rewrite
            return;
        }

        // The index drifted: rebuild it for the whole subtree.
        Collection::findByHandle('knowledge_bases')?->updateEntryUris($ids->all());
    }

    /**
     * Query the `knowledge_base` root entries that declare a mount parent.
     *
     * @return \Illuminate\Support\Collection<int, Entry> The mounted root entries
     */
    private function mountedRoots(): \Illuminate\Support\Collection
    {
        // Build a query for knowledge base root entries.
        /** @var EntryQueryBuilder $query */
        $query = EntryFacade::query();

        // Restrict to knowledge_base roots.
        /** @var EntryQueryBuilder $query */
        $query = $query->where('collection', 'knowledge_bases');
        /** @var EntryQueryBuilder $query */
        $query = $query->where('blueprint', KnowledgeBaseEntry::ROOT_BLUEPRINT);

        // Materialize the results.
        /** @var \Illuminate\Support\Collection<int, Entry> $roots */
        $roots = $query->get();

        // Keep only roots that actually declare a mount parent.
        return $roots
            ->filter(fn (Entry $root): bool => ! empty($root->get(KnowledgeBaseEntry::MOUNT_FIELD)));
    }

    /**
     * Collect the entry ids of a knowledge base root and all its descendants.
     *
     * @param  Entry  $root  The `knowledge_base` root entry
     * @return \Illuminate\Support\Collection<int, string> The subtree entry ids
     */
    private function subtreeIds(Entry $root): \Illuminate\Support\Collection
    {
        // Resolve the structure page for the root entry.
        /** @var ?Page $page */
        $page = $root->page();

        // Check whether the root participates in the structure.
        if (! $page) {
            // Return an empty id collection when there is no structure page
            /** @var \Illuminate\Support\Collection<int, string> $empty */
            $empty = collect();

            return $empty;
        }

        // Flatten the subtree into a collection of structure pages.
        /** @var \Illuminate\Support\Collection<int, Page> $pages */
        $pages = $page->flattenedPages();

        // Pluck the entry ids, dropping any that are missing.
        /** @var \Illuminate\Support\Collection<int, string> $ids */
        $ids = $pages->pluck('id')->filter()->values();

        return $ids;
    }

    /**
     * Flush the per-request URI caches so URIs recompute against current pages.
     *
     * A mounted knowledge base's URI depends on its mount-parent page (and that
     * page's ancestors), not just on the knowledge base entries themselves, so
     * clearing only the subtree's cache would leave a stale mount-parent URI in
     * place. These blink stores are per-request scratch caches that rebuild on
     * demand, so flushing them wholesale is safe.
     */
    private function flushUriCaches(): void
    {
        // Drop the entry-level URI cache for all entries this request.
        /** @var \Spatie\Blink\Blink $entryUris */
        $entryUris = Blink::store('entry-uris');
        $entryUris->flush();

        // Drop the structure-level URI cache for all pages this request.
        /** @var \Spatie\Blink\Blink $structureUris */
        $structureUris = Blink::store('structure-uris');
        $structureUris->flush();
    }

    /**
     * Read the URI currently stored in the knowledge bases `uri` index.
     *
     * @param  string  $id  The entry id
     * @return ?string The indexed URI, or null when absent
     */
    private function indexedUri(string $id): ?string
    {
        // Resolve the knowledge bases uri index and read the stored value.
        /** @var AggregateStore $entries */
        $entries = Stache::store('entries');

        /** @var Store $store */
        $store = $entries->store('knowledge_bases');

        /** @var Index $index */
        $index = $store->index('uri');

        /** @var ?string $uri */
        $uri = $index->get($id);

        return $uri;
    }
}
