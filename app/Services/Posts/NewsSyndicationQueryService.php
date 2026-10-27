<?php

declare(strict_types=1);

namespace App\Services\Posts;

use App\Scopes\TopLevelPostCategories;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\Site;
use Statamic\Sites\Site as SiteModel;
use Statamic\Stache\Query\EntryQueryBuilder;

/**
 * Backs the UAMS News page-builder block (UAMS-Web/uams-statamic-archive#76) by querying the local `posts`
 * collection across the selected source site(s).
 *
 * The block ports the WordPress `UAMSWP-News-Syndication-2020` plugin, but where
 * that plugin fetched remote content from `news.uams.edu/wp-json/...`, this
 * queries the native multisite `posts` collection: news.uams.edu is migrated
 * into this install (the `news` site), so syndication is a direct cross-site
 * query — no HTTP, no remote cache, no SSRF surface.
 *
 * Category filtering runs against the `post_categories` structured collection
 * (UAMS-Web/uams-statamic-archive#1433): a post references its categories as collection-entry ids, so the
 * filter matches any post overlapping the selected category (and its
 * descendants), and the archive-link CTA resolves from the category entry (UAMS-Web/uams-statamic-archive#1144).
 */
class NewsSyndicationQueryService
{
    /**
     * The default news source site handle (the migrated news.uams.edu content).
     */
    public const NEWS_SITE = 'news';

    /**
     * The source-selector token meaning "the current request's site", resolved
     * to a concrete handle at render time (placement-relative local content).
     */
    public const SOURCE_CURRENT = 'current';

    /**
     * The collection the block syndicates from.
     */
    private const string COLLECTION = 'posts';

    /**
     * The structured collection used for category filtering and the archive-link CTA.
     */
    private const string CATEGORY_COLLECTION = 'post_categories';

    /**
     * The post→`organizations` association field (UAMS-Web/uams-statamic-archive#1598) the Departmental News
     * Hub (UAMS-Web/uams-statamic-archive#1602) and its archive (UAMS-Web/uams-statamic-archive#1599) filter on. Stored as an array of
     * `organizations` entry ids, so it is matched with JSON-overlap semantics —
     * the same shape as {@see self::CATEGORY_COLLECTION} filtering.
     */
    private const string ORGANIZATION_FIELD = 'related_organizations';

    /**
     * The post blueprint handles the archive's `type` axis narrows by (UAMS-Web/uams-statamic-archive#1599).
     * An archive `type` outside this set is ignored rather than yielding an
     * empty page, so a stale query string never silently blanks the listing.
     *
     * @var array<int, string>
     */
    public const array POST_BLUEPRINTS = ['post', 'audio', 'video', 'news_release'];

    /**
     * Resolve the source-site selector — which may contain the `current` token
     * alongside concrete handles such as `news` — to a de-duplicated list of
     * site handles that actually exist.
     *
     * @param  array<int, string>  $sources  The selected source values (e.g. `['news', 'current']`).
     * @param  string  $currentSiteHandle  The current request's site handle.
     * @return array<int, string> Concrete, existing site handles to query.
     */
    public function resolveSiteHandles(
        array $sources,
        string $currentSiteHandle
    ): array {
        // Fall back to the news site when nothing was selected, matching the
        // fieldset default so an empty selection never yields an unscoped query.
        if ($sources === []) {
            $sources = [self::NEWS_SITE];
        }

        // Map the placement-relative `current` token to the actual site handle.
        $handles = array_map(
            fn (string $source): string => $source === self::SOURCE_CURRENT
                ? $currentSiteHandle
                : $source,
            $sources
        );

        // Resolve the handles of every configured site to reject stale or
        // misconfigured selections that would otherwise silently match nothing.
        /** @var Collection<array-key, SiteModel> $sites */
        $sites = Site::all();
        $existing = $sites
            ->map(fn (SiteModel $site) => $site->handle())
            ->filter(fn ($handle): bool => \is_string($handle))
            ->values()
            ->all();

        return collect($handles)
            ->filter(fn (string $handle): bool => \in_array($handle, $existing, true))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Query published `posts` entries across the given sites, newest first.
     *
     * @param  array<int, string>  $siteHandles  Concrete site handles (see {@see resolveSiteHandles()}).
     * @param  array<int, string>  $categoryIds  Selected `post_categories` entry ids, or empty for no filter.
     * @param  int  $limit  Maximum number of entries to return (a value <= 0 means no cap).
     * @param  int  $offset  Number of entries to skip (a value <= 0 means no offset).
     * @param  bool  $requireFeaturedImage  Keep only posts that would render a cover image (see {@see rendersFeaturedImage()}).
     * @param  string  $format  The block layout (e.g. `side`), so the filter mirrors that layout's cover-image render guard.
     * @param  array<int, string>  $excludeIds  Post entry ids to exclude — the news-magazine springboard threads its accumulator of already-surfaced ids here for page-wide, earlier-section-wins cross-section dedup (UAMS-Web/uams-statamic-archive#1158). Empty in the single-block path.
     * @return Collection<int, Entry> The matched entries.
     */
    public function query(
        array $siteHandles,
        array $categoryIds,
        int $limit,
        int $offset,
        bool $requireFeaturedImage = false,
        string $format = '',
        array $excludeIds = []
    ): Collection {
        // No sites to query — return nothing rather than issue an unscoped
        // whereIn (which would match every site's posts).
        if ($siteHandles === []) {
            /** @var Collection<int, Entry> $empty */
            $empty = collect();

            return $empty;
        }

        /** @var EntryQueryBuilder $query */
        $query = EntryFacade::query();
        $query->where('collection', self::COLLECTION);
        $query->whereIn('site', $siteHandles);
        $query->whereStatus('published');

        // Filter by the selected categories — and their descendants — when
        // provided. A post references its categories as `post_categories`
        // collection-entry ids, so match any post whose set overlaps the target
        // ids (UAMS-Web/uams-statamic-archive#1144). `whereJsonOverlaps` is ANY-semantics (array_intersect),
        // unlike `whereJsonContains`, which requires ALL.
        $targetIds = $this->expandWithDescendants($categoryIds);
        if ($targetIds !== []) {
            $query->whereJsonOverlaps('post_categories', $targetIds);
        }

        // Exclude posts already surfaced by an earlier springboard section, so a
        // post never repeats down a news-magazine page (UAMS-Web/uams-statamic-archive#1158). Empty for the
        // single news block, which has no cross-section state to carry.
        if ($excludeIds !== []) {
            $query->whereNotIn('id', $excludeIds);
        }

        // Newest first, mirroring the WordPress plugin's reverse-date sort.
        $query->orderBy('date', 'desc');

        // When a featured image is required (the image-led cards/grid/side
        // layouts), filter on the block's render guard *before* paging — a
        // Stache `where` on the cover-image data is not reliable, and paging in
        // the query first would let image-less posts consume the requested slots
        // and silently short the count.
        if ($requireFeaturedImage) {
            /** @var Collection<int, Entry> $all */
            $all = $query->get();

            $filtered = $all
                ->filter(fn (Entry $entry): bool => $this->rendersFeaturedImage($entry, $format))
                ->values();

            return $this->page($filtered, $limit, $offset);
        }

        // Skip the requested offset before applying any limit.
        if ($offset > 0) {
            $query->offset($offset);
        }

        // Cap the result set only when a positive count is requested.
        if ($limit > 0) {
            $query->limit($limit);
        }

        /** @var Collection<int, Entry> $results */
        $results = $query->get();

        return $results;
    }

    /**
     * Paginate the Departmental News Hub news archive (UAMS-Web/uams-statamic-archive#1599): published `posts`
     * across the given source sites, filtered along three axes — organization,
     * blueprint type, and category — ordered sticky-first then newest.
     *
     * The three axes are each optional and independent (any combination):
     * `$organizationIds` (the post→`organizations` association, UAMS-Web/uams-statamic-archive#1598),
     * `$blueprints` (the four post types), and `$categoryIds` (UAMS-Web/uams-statamic-archive#1433 categories,
     * descendant-expanded). Org-filtering is *invariant* across the hub, but an
     * empty `$organizationIds` still degrades cleanly to no org constraint — the
     * news-only / non-entity path — mirroring how an empty category or site list
     * already degrades in {@see query()}.
     *
     * Sticky ordering is a stable sort over the date-desc result, so a post's
     * position within the sticky and non-sticky groups stays newest-first (UAMS-Web/uams-statamic-archive#1555
     * reuses the existing unconsumed `sticky_query`). The matched set is
     * materialized before paging so the sticky partition is deterministic — the
     * archive is a cold page, not the hot news-block path.
     *
     * @param  array<int, string>  $siteHandles  Concrete site handles (see {@see resolveSiteHandles()}).
     * @param  array<int, string>  $organizationIds  `related_organizations` ids to match (ANY); empty applies no org filter.
     * @param  array<int, string>  $blueprints  Post blueprint handles to include; empty includes every type.
     * @param  array<int, string>  $categoryIds  Selected `post_categories` ids (descendant-expanded); empty applies no filter.
     * @param  int  $perPage  Items per page (coerced to at least 1).
     * @param  int  $page  The 1-based page number (coerced to at least 1).
     * @return LengthAwarePaginator<int, Entry> The requested page of matched entries.
     */
    public function paginateArchive(
        array $siteHandles,
        array $organizationIds,
        array $blueprints,
        array $categoryIds,
        int $perPage,
        int $page
    ): LengthAwarePaginator {
        $perPage = max(1, $perPage);
        $page = max(1, $page);

        // No sites to query — an empty page, never an unscoped whereIn.
        if ($siteHandles === []) {
            /** @var Collection<int, Entry> $empty */
            $empty = collect();

            return new LengthAwarePaginator($empty, 0, $perPage, $page);
        }

        /** @var EntryQueryBuilder $query */
        $query = EntryFacade::query();
        $query->where('collection', self::COLLECTION);
        $query->whereIn('site', $siteHandles);
        $query->whereStatus('published');

        // Type axis: restrict to the requested post blueprints when given.
        if ($blueprints !== []) {
            $query->whereIn('blueprint', $blueprints);
        }

        // Org axis: match any post associated with one of the target orgs. Same
        // JSON-overlap (ANY) semantics as the category filter; a post with no
        // association never matches an active org filter.
        if ($organizationIds !== []) {
            $query->whereJsonOverlaps(self::ORGANIZATION_FIELD, $organizationIds);
        }

        // Category axis: match the selected categories and their descendants.
        $targetIds = $this->expandWithDescendants($categoryIds);
        if ($targetIds !== []) {
            $query->whereJsonOverlaps('post_categories', $targetIds);
        }

        $query->orderBy('date', 'desc');

        /** @var Collection<int, Entry> $all */
        $all = $query->get();

        $ordered = $this->sortStickyFirst($all);

        $total = $ordered->count();
        /** @var Collection<int, Entry> $items */
        $items = $ordered->forPage($page, $perPage)->values();

        return new LengthAwarePaginator($items, $total, $perPage, $page);
    }

    /**
     * The news-magazine lead band (UAMS-Web/uams-statamic-archive#1612 / UAMS-Web/uams-statamic-archive#1602): published `posts` across the
     * given sites, ordered sticky-first then newest, capped at $limit, excluding
     * $excludeIds. A "featured" post (`sticky_query`) is guaranteed a lead slot.
     *
     * The lead is the top band of a news-magazine page; its ids seed the
     * section accumulator so a post surfaced in the lead never repeats in a
     * section below (page-wide, earlier-wins dedup — {@see NewsSpringboardService}).
     *
     * @param  array<int, string>  $siteHandles  Concrete site handles (see {@see resolveSiteHandles()}).
     * @param  array<int, string>  $categoryIds  Selected `post_categories` ids (descendant-expanded); empty applies no filter.
     * @param  int  $limit  Maximum lead posts (a value <= 0 returns all matched, sticky-first).
     * @param  array<int, string>  $excludeIds  Post ids to exclude from the lead.
     * @param  array<int, string>  $organizationIds  `related_organizations` ids to match (the department hub, UAMS-Web/uams-statamic-archive#1602); empty applies no org filter.
     * @return Collection<int, Entry> The lead posts, sticky-first then newest.
     */
    public function queryLead(
        array $siteHandles,
        array $categoryIds,
        int $limit,
        array $excludeIds = [],
        array $organizationIds = []
    ): Collection {
        if ($siteHandles === []) {
            /** @var Collection<int, Entry> $empty */
            $empty = collect();

            return $empty;
        }

        /** @var EntryQueryBuilder $query */
        $query = EntryFacade::query();
        $query->where('collection', self::COLLECTION);
        $query->whereIn('site', $siteHandles);
        $query->whereStatus('published');

        $targetIds = $this->expandWithDescendants($categoryIds);
        if ($targetIds !== []) {
            $query->whereJsonOverlaps('post_categories', $targetIds);
        }

        if ($organizationIds !== []) {
            $query->whereJsonOverlaps(self::ORGANIZATION_FIELD, $organizationIds);
        }

        if ($excludeIds !== []) {
            $query->whereNotIn('id', $excludeIds);
        }

        $query->orderBy('date', 'desc');

        /** @var Collection<int, Entry> $all */
        $all = $query->get();
        $ordered = $this->sortStickyFirst($all);

        return $limit > 0 ? $ordered->take($limit)->values() : $ordered;
    }

    /**
     * A Departmental News Hub per-type section (UAMS-Web/uams-statamic-archive#1602): published `posts` of one
     * blueprint type across the given sites, org-filtered, sticky-first then
     * newest, with the first $offset skipped and $limit taken.
     *
     * The $offset is that type's count in the hub lead — since the lead and this
     * query share the identical (sticky, date-desc) order over the same
     * org-filtered set, skipping the type's lead count yields exactly the posts of
     * that type not already shown in the lead (offset dedup — distinct from the
     * cross-category accumulator dedup of {@see NewsSpringboardService::resolveMagazine()}).
     *
     * @param  array<int, string>  $siteHandles  Concrete site handles.
     * @param  array<int, string>  $organizationIds  `related_organizations` ids to match; empty applies no org filter.
     * @param  string  $blueprint  The post blueprint handle for this section (post/audio/video/news_release).
     * @param  int  $offset  Posts of this type to skip (its count in the lead).
     * @param  int  $limit  Maximum posts to return (a value <= 0 returns all after the offset).
     * @return Collection<int, Entry> The section's posts, sticky-first then newest.
     */
    public function queryTypeSection(
        array $siteHandles,
        array $organizationIds,
        string $blueprint,
        int $offset,
        int $limit
    ): Collection {
        if ($siteHandles === []) {
            /** @var Collection<int, Entry> $empty */
            $empty = collect();

            return $empty;
        }

        /** @var EntryQueryBuilder $query */
        $query = EntryFacade::query();
        $query->where('collection', self::COLLECTION);
        $query->whereIn('site', $siteHandles);
        $query->whereStatus('published');
        $query->where('blueprint', $blueprint);

        if ($organizationIds !== []) {
            $query->whereJsonOverlaps(self::ORGANIZATION_FIELD, $organizationIds);
        }

        $query->orderBy('date', 'desc');

        /** @var Collection<int, Entry> $all */
        $all = $query->get();
        $ordered = $this->sortStickyFirst($all);

        if ($offset > 0) {
            $ordered = $ordered->slice($offset);
        }

        return $limit > 0 ? $ordered->take($limit)->values() : $ordered->values();
    }

    /**
     * Stable sticky-first ordering: sorting a date-desc set by the `sticky_query`
     * flag keeps each of the sticky and non-sticky groups newest-first, so the
     * whole list reads sticky-first then newest (UAMS-Web/uams-statamic-archive#1555 reuses the existing
     * unconsumed `sticky_query`; an absent/false flag is non-sticky).
     *
     * @param  Collection<int, Entry>  $entries  A date-desc-ordered entry set.
     * @return Collection<int, Entry> The set reordered sticky-first, then newest.
     */
    private function sortStickyFirst(Collection $entries): Collection
    {
        return $entries
            ->sortByDesc(fn (Entry $entry): int => $entry->value('sticky_query') === true ? 1 : 0)
            ->values();
    }

    /**
     * Whether a post would render a cover image in the given news-block layout.
     *
     * The block shows an image only when the editor opted the entry's featured
     * image in as a cover (`cover_image_query`) AND the layout has an asset to
     * render, so the require-featured-image filter mirrors each layout's own
     * render guard — removing posts that would otherwise render an empty image
     * slot. Every image-led layout renders the 16:9 asset, except `side`, which
     * falls back to a 4:3 asset (preferring it), so a `side` post renders when
     * *either* asset is present.
     */
    private function rendersFeaturedImage(Entry $entry, string $format = ''): bool
    {
        if ($entry->value('cover_image_query') !== true) {
            return false;
        }

        if ($format === 'side') {
            return ! empty($entry->value('featured_image_4_3'))
                || ! empty($entry->value('featured_image_16_9'));
        }

        return ! empty($entry->value('featured_image_16_9'));
    }

    /**
     * Apply offset then limit to an already-materialized entry collection.
     *
     * @param  Collection<int, Entry>  $entries
     * @return Collection<int, Entry>
     */
    private function page(Collection $entries, int $limit, int $offset): Collection
    {
        if ($offset > 0) {
            $entries = $entries->slice($offset);
        }

        if ($limit > 0) {
            $entries = $entries->take($limit);
        }

        return $entries->values();
    }

    /**
     * Find a single published `posts` entry by id, for the `full` format.
     *
     * @param  ?string  $articleId  The id of the selected entry.
     * @return ?Entry The entry when found, published, and in the posts collection; otherwise null.
     */
    public function findArticle(?string $articleId): ?Entry
    {
        // No id — nothing to resolve.
        if ($articleId === null || trim($articleId) === '') {
            return null;
        }

        /** @var ?Entry $entry */
        $entry = EntryFacade::find(trim($articleId));

        // Only surface a published posts entry — never a draft, a scheduled or
        // expired entry, or an entry from a different collection.
        if (
            ! $entry instanceof Entry
            || $entry->collectionHandle() !== self::COLLECTION
            || $entry->status() !== 'published'
        ) {
            return null;
        }

        return $entry;
    }

    /**
     * Resolve the "View the Full List" CTA target for a category filter: the
     * category entry's display title and its archive URL.
     *
     * The `post_categories` structured collection now has a hierarchical archive
     * route (UAMS-Web/uams-statamic-archive#1433), so the CTA resolves the selected category entry's title and
     * `/category/{parent_uri}/{slug}` URL. The title is display-sanitized here —
     * it flows into raw Antlers text and `aria-label` sinks in the block (the CTA
     * renders only now that the URL is non-null), so strip tags and entity-encode
     * at this one boundary rather than at each of the several template usages.
     *
     * @param  array<int, string>  $categoryIds  Selected category entry ids; only the first is used for the CTA.
     * @return array{name: ?string, url: ?string} The category's title and archive URL (either may be null).
     */
    public function resolveCategoryLink(array $categoryIds): array
    {
        // No category filter — there is no single category to link to.
        if ($categoryIds === []) {
            return ['name' => null, 'url' => null];
        }

        $entry = EntryFacade::find($categoryIds[0]);

        // Unknown id, or not a category entry — nothing to link.
        if (! $entry instanceof Entry || $entry->collectionHandle() !== self::CATEGORY_COLLECTION) {
            return ['name' => null, 'url' => null];
        }

        $title = $entry->value('title');
        $url = $entry->url();

        return [
            'name' => \is_string($title) ? $this->sanitizeLabel($title) : null,
            'url' => \is_string($url) ? $url : null,
        ];
    }

    /**
     * Expand a set of category entry ids to include every descendant category
     * (children, grandchildren, …), so filtering on a parent category also
     * matches posts filed only under a subcategory (UAMS-Web/uams-statamic-archive#1144 hierarchy).
     *
     * Each category's parent id comes from its stored `parent` value — the field
     * the importer/CP records and {@see TopLevelPostCategories} reads
     * with `whereNull('parent')` — so the map is built from one collection query.
     * A category with no `parent` is a root; unknown ids degrade to the selected
     * ids alone.
     *
     * @param  array<int, string>  $categoryIds  The selected category entry ids.
     * @return array<int, string> The selected ids plus all descendant ids, de-duplicated.
     */
    private function expandWithDescendants(array $categoryIds): array
    {
        $selected = array_values(array_filter(
            $categoryIds,
            fn (string $id): bool => trim($id) !== ''
        ));

        if ($selected === []) {
            return [];
        }

        // Build a parent id → child ids map from every category's structure parent.
        /** @var EntryQueryBuilder $query */
        $query = EntryFacade::query();
        $query->where('collection', self::CATEGORY_COLLECTION);
        /** @var Collection<int, Entry> $categories */
        $categories = $query->get();

        /** @var array<string, array<int, string>> $childrenByParent */
        $childrenByParent = [];

        foreach ($categories as $category) {
            $childId = $category->id();
            $parentId = $category->value('parent');

            if (\is_string($childId) && \is_string($parentId) && $parentId !== '') {
                $childrenByParent[$parentId][] = $childId;
            }
        }

        // Breadth-first collect the descendants of each selected category.
        $all = $selected;
        $frontier = $selected;

        while ($frontier !== []) {
            $next = [];

            foreach ($frontier as $parentId) {
                foreach ($childrenByParent[$parentId] ?? [] as $childId) {
                    if (! \in_array($childId, $all, true)) {
                        $all[] = $childId;
                        $next[] = $childId;
                    }
                }
            }

            $frontier = $next;
        }

        return array_values(array_unique($all));
    }

    /**
     * Strip tags and entity-encode a category title for safe rendering in the
     * block's raw-Antlers text and attribute sinks.
     */
    private function sanitizeLabel(string $title): string
    {
        return htmlspecialchars(strip_tags($title), ENT_QUOTES, 'UTF-8');
    }
}
