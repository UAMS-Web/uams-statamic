<?php

declare(strict_types=1);

namespace App\Services\OrphanedAncestors;

use Illuminate\Support\Collection as SupportCollection;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry;
use Statamic\Facades\Blink;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Sites\Site;
use Statamic\Structures\Page;

/**
 * Collection-agnostic detection of published entries orphaned by an unpublished
 * structural ancestor (UAMS-Web/uams-statamic-archive#1429). Generalizes the `knowledge_bases`-only UAMS-Web/uams-statamic-archive#217/UAMS-Web/uams-statamic-archive#1077
 * handling into one mechanism keyed on "routable + hierarchical", gating both the
 * front-end redirect and the Control Panel banner.
 *
 * A collection is handled when it is routable (has a non-empty front-end route)
 * AND hierarchical (structured), and is not opted out via
 * `config('orphaned_ancestors.disabled_collections')`. For a handled entry with
 * an unpublished ancestor, the redirect destination is the nearest LIVE ancestor
 * (the published ancestor immediately above the highest unpublished break, so it
 * never itself redirects), else a published collection mount root, else the site
 * root (the terminal best-effort fallback). The ancestor walk is memoized per
 * request per entry.
 */
class OrphanedAncestorResolver
{
    /**
     * Whether the orphaned-ancestor mechanism applies to the given collection —
     * routable + hierarchical and not opted out.
     */
    public function handles(Collection $collection, ?string $site = null): bool
    {
        return $this->isEnabledFor($collection)
            && $this->isRoutableAndHierarchical($collection, $site);
    }

    /**
     * Whether the collection is routable (produces front-end URLs) and
     * hierarchical (has a structure tree). Non-routable structured collections
     * (`facilities`, `organizations`, `schema_objects`) and flat collections are
     * excluded by construction.
     */
    public function isRoutableAndHierarchical(Collection $collection, ?string $site = null): bool
    {
        if (! $collection->hasStructure()) {
            return false;
        }

        if ($site !== null) {
            return $this->hasRoute($collection, $site);
        }

        // No site in hand (e.g. blueprint resolution): routable if any of the
        // collection's sites produces URLs.
        $sites = $collection->sites();
        $siteHandles = $sites instanceof SupportCollection ? $sites->all() : (\is_array($sites) ? $sites : []);

        foreach ($siteHandles as $collectionSite) {
            if (\is_string($collectionSite) && $this->hasRoute($collection, $collectionSite)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the collection is enabled (not opted out) for orphaned-ancestor
     * handling. The default is enabled; a collection is excluded by listing its
     * handle in `config('orphaned_ancestors.disabled_collections')`.
     */
    public function isEnabledFor(Collection $collection): bool
    {
        /** @var mixed $disabled */
        $disabled = config('orphaned_ancestors.disabled_collections', []);

        return ! \is_array($disabled) || ! \in_array($collection->handle(), $disabled, true);
    }

    /**
     * The orphaned-ancestor state for a published entry, or null when the entry
     * is not handled or has no unpublished ancestor. Memoized per request per
     * (site, entry id) so repeated augmentation never re-walks the tree.
     */
    public function resolve(Entry $entry): ?OrphanedAncestorResult
    {
        $collection = $entry->collection();
        if (! $collection instanceof Collection || ! $this->handles($collection, $this->localeOf($entry))) {
            return null;
        }

        $entryId = $entry->id();
        if (! \is_string($entryId)) {
            return $this->computeResult($entry);
        }

        $result = Blink::once(
            'orphaned_ancestor_'.$this->localeOf($entry).'_'.$entryId,
            fn (): ?OrphanedAncestorResult => $this->computeResult($entry),
        );

        return $result instanceof OrphanedAncestorResult ? $result : null;
    }

    /**
     * Walk the structure tree from the entry toward the root, applying the
     * nearest-live-ancestor redirect policy.
     */
    private function computeResult(Entry $entry): ?OrphanedAncestorResult
    {
        $page = $entry->page();
        if (! $page instanceof Page) {
            return null;
        }

        /** @var list<Entry> $ancestors Nearest-first ancestor chain (excludes the structural root). */
        $ancestors = [];
        $currentPage = $page;

        while (! $currentPage->isRoot()) {
            $parentPage = $currentPage->parent();
            if (! $parentPage instanceof Page || $parentPage->isRoot()) {
                break;
            }

            $parentEntry = EntryFacade::find($parentPage->id());
            if ($parentEntry instanceof Entry) {
                $ancestors[] = $parentEntry;
            }

            $currentPage = $parentPage;
        }

        // Find the nearest unpublished ancestor (named in the banner) and the
        // highest unpublished ancestor (the topmost break in the chain).
        $nearestUnpublished = null;
        $topmostUnpublishedIndex = null;

        foreach ($ancestors as $index => $ancestor) {
            if ($ancestor->status() !== 'published') {
                $nearestUnpublished ??= $ancestor;
                $topmostUnpublishedIndex = $index;
            }
        }

        if ($nearestUnpublished === null || $topmostUnpublishedIndex === null) {
            return null;
        }

        // The published ancestor immediately above the highest unpublished break
        // is the nearest live ancestor — everything below it is orphaned. Fall
        // back to the collection mount root, then the site root.
        $liveAncestor = $ancestors[$topmostUnpublishedIndex + 1] ?? null;

        $redirectUrl = $this->urlOf($liveAncestor)
            ?? $this->mountRootUrl($entry)
            ?? $this->siteRootUrl($entry);

        $title = $nearestUnpublished->get('title');

        return new OrphanedAncestorResult(
            $redirectUrl,
            \is_string($title) ? $title : null,
        );
    }

    /**
     * Whether the collection has a non-empty front-end route for the site.
     */
    private function hasRoute(Collection $collection, string $site): bool
    {
        $route = $collection->route($site);

        return \is_string($route) && trim($route) !== '';
    }

    /**
     * The entry's own URL, or null when it has none.
     */
    private function urlOf(?Entry $entry): ?string
    {
        if (! $entry instanceof Entry) {
            return null;
        }

        $url = $entry->url();

        return \is_string($url) && $url !== '' ? $url : null;
    }

    /**
     * The URL of the entry collection's mount root, or null when unmounted or
     * when the mount landing is not itself published — an unpublished mount would
     * 302 the visitor onto a 404, so it is skipped in favour of the site root.
     */
    private function mountRootUrl(Entry $entry): ?string
    {
        $collection = $entry->collection();
        if (! $collection instanceof Collection) {
            return null;
        }

        $mount = $collection->mount();
        if (! $mount instanceof Entry || $mount->status() !== 'published') {
            return null;
        }

        return $this->urlOf($mount);
    }

    /**
     * The entry site's root URL, the final redirect fallback.
     */
    private function siteRootUrl(Entry $entry): string
    {
        $site = $entry->site();
        $url = $site instanceof Site ? $site->url() : null;

        return \is_string($url) && $url !== '' ? $url : '/';
    }

    /**
     * The entry's site handle as a string.
     */
    private function localeOf(Entry $entry): string
    {
        $locale = $entry->locale();

        return \is_string($locale) ? $locale : '';
    }
}
