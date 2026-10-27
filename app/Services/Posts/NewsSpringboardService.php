<?php

declare(strict_types=1);

namespace App\Services\Posts;

use Illuminate\Support\Collection;
use Statamic\Entries\Entry;
use Statamic\Fields\Blueprint;

/**
 * Resolves a news-magazine springboard's stacked category sections, threading a
 * page-wide accumulator of already-surfaced post ids from each section into the
 * next section's query so a post never repeats down the page (UAMS-Web/uams-statamic-archive#1158).
 *
 * A springboard is N stacked UAMS-Web/uams-statamic-archive#76 news sections plus one capability the
 * independent page-builder blocks cannot carry: ordered, shared cross-section
 * state. This service is that single controller — it owns the accumulator and
 * threads it deterministically into {@see NewsSyndicationQueryService::query()}'s
 * `excludeIds`, while delegating the actual cross-site, category-filtered,
 * date-sorted query to the same UAMS-Web/uams-statamic-archive#76 core the news block uses.
 *
 * Dedup is page-wide and earlier-section-wins: the first section to surface a
 * post keeps it; every later section excludes it. The service is pure (no
 * Antlers), so its dedup contract is unit-tested directly.
 */
class NewsSpringboardService
{
    /**
     * Per-section item count when a section omits one.
     */
    public const int DEFAULT_COUNT = 4;

    /**
     * Lead-band post count when a magazine config omits one (UAMS-Web/uams-statamic-archive#1612 / UAMS-Web/uams-statamic-archive#1602).
     */
    public const int DEFAULT_LEAD_COUNT = 5;

    public function __construct(private readonly NewsSyndicationQueryService $news) {}

    /**
     * Resolve each springboard section to its deduped posts, in page order.
     *
     * @param  array<int, array{heading?: ?string, category?: array<int, string>, count?: int, source_sites?: array<int, string>}>  $sections  The springboard's ordered section configs.
     * @param  string  $currentSiteHandle  The rendering site (maps the `current` source token).
     * @param  array<int, string>  $preSurfacedIds  Post ids already surfaced above the sections (a magazine lead, UAMS-Web/uams-statamic-archive#1612) — seeded into the accumulator so a lead post never repeats in a section.
     * @return array<int, array{heading: ?string, posts: Collection<int, Entry>, category_name: ?string, category_url: ?string}> Each section with its deduped posts and archive-link CTA.
     */
    public function resolveSections(array $sections, string $currentSiteHandle, array $preSurfacedIds = []): array
    {
        // The page-wide accumulator: ids surfaced above (a lead) or by earlier
        // sections, excluded from every later section's query.
        $surfaced = array_values(array_filter(
            $preSurfacedIds,
            static fn (string $id): bool => $id !== ''
        ));
        $resolved = [];

        foreach ($sections as $section) {
            $categoryIds = $section['category'] ?? [];
            $count = $section['count'] ?? self::DEFAULT_COUNT;
            $sources = $section['source_sites'] ?? [];

            $siteHandles = $this->news->resolveSiteHandles($sources, $currentSiteHandle);

            $posts = $this->news->query(
                $siteHandles,
                $categoryIds,
                $count,
                0,
                false,
                '',
                $surfaced,
            );

            // Fold this section's posts into the accumulator so later sections skip them.
            foreach ($posts as $post) {
                $id = $post->id();

                if (\is_string($id) && $id !== '') {
                    $surfaced[] = $id;
                }
            }

            unset($post);

            $link = $this->news->resolveCategoryLink($categoryIds);

            $resolved[] = [
                'heading' => $section['heading'] ?? null,
                'posts' => $posts,
                'category_name' => $link['name'],
                'category_url' => $link['url'],
            ];
        }

        unset($section);

        return $resolved;
    }

    /**
     * Resolve a full news-magazine page (UAMS-Web/uams-statamic-archive#1612): a sticky-first **lead** band, then
     * the category **sections** below it, page-wide earlier-wins deduped so a post
     * in the lead never repeats in a section. This is the `axis=category`,
     * `scope=all`, `zones=none` configuration of the shared core — the `news`
     * site's own home over the whole newsroom (no org filter).
     *
     * @param  array{lead_count?: int, lead_sources?: array<int, string>, sections?: array<int, array{heading?: ?string, category?: array<int, string>, count?: int, source_sites?: array<int, string>}>}  $config  The magazine config: lead size/sources + ordered section slots.
     * @param  string  $currentSiteHandle  The rendering site (maps the `current` source token).
     * @return array{lead: Collection<int, Entry>, sections: array<int, array{heading: ?string, posts: Collection<int, Entry>, category_name: ?string, category_url: ?string}>} The lead posts and each deduped section.
     */
    public function resolveMagazine(array $config, string $currentSiteHandle): array
    {
        $leadCount = $config['lead_count'] ?? self::DEFAULT_LEAD_COUNT;
        $leadSources = $config['lead_sources'] ?? [];
        $sections = $config['sections'] ?? [];

        // The lead spans the whole newsroom (all categories), sticky-first.
        $leadSites = $this->news->resolveSiteHandles($leadSources, $currentSiteHandle);
        $lead = $this->news->queryLead($leadSites, [], $leadCount);

        // Seed the section accumulator with the lead's ids so no lead post repeats
        // in a section below.
        $leadIds = [];
        foreach ($lead as $post) {
            $id = $post->id();

            if (\is_string($id) && $id !== '') {
                $leadIds[] = $id;
            }
        }

        unset($post);

        $resolvedSections = $this->resolveSections($sections, $currentSiteHandle, $leadIds);

        return [
            'lead' => $lead,
            'sections' => $resolvedSections,
        ];
    }

    /**
     * Resolve a Departmental News Hub magazine (UAMS-Web/uams-statamic-archive#1602): a sticky-first, org-filtered
     * **lead** band across the department's newsroom, then per-blueprint **type**
     * sections below it, each offset by that type's count in the lead. This is the
     * `axis=type`, `scope={the site's org}`, `zones=department` configuration of the
     * shared core.
     *
     * Dedup is **offset**, not accumulator: a type section skips the first K posts of
     * its type (K = that type's count in the lead) and takes the next N. Because the
     * lead and each type query share the identical (sticky, date-desc) order over the
     * same org-filtered set, those first K are exactly the type's lead posts — so a
     * lead post never repeats in its type section (the correctness the UAMS-Web/uams-statamic-archive#1609 review
     * pinned as distinct from the category-magazine's cross-section accumulator dedup).
     *
     * A section with fewer than the threshold posts is dropped, so a thin department
     * shows only the sections that have content — degrading to a lead-only, or empty,
     * hub. Org-filtering is invariant: an empty `$organizationIds` (a non-entity site)
     * yields the news-only path.
     *
     * @param  array{lead_count?: int, sources?: array<int, string>, section_threshold?: int, section_types?: array<int, array{type: string, heading?: ?string, count?: int}>}  $config  Lead size/sources, the per-type section list, and the hide threshold.
     * @param  array<int, string>  $organizationIds  The hub's `organizations` ids (from the site, UAMS-Web/uams-statamic-archive#1556); empty = news-only degrade.
     * @param  string  $currentSiteHandle  The rendering site (maps the `current` source token).
     * @return array{lead: Collection<int, Entry>, sections: array<int, array{type: string, heading: ?string, posts: Collection<int, Entry>}>} The lead posts and each non-empty type section.
     */
    public function resolveDepartmentMagazine(
        array $config,
        array $organizationIds,
        string $currentSiteHandle
    ): array {
        $leadCount = $config['lead_count'] ?? self::DEFAULT_LEAD_COUNT;
        // Department-filtered central news + the site's optional local posts (UAMS-Web/uams-statamic-archive#1555).
        $sources = $config['sources'] ?? [NewsSyndicationQueryService::NEWS_SITE, NewsSyndicationQueryService::SOURCE_CURRENT];
        $threshold = max(1, $config['section_threshold'] ?? 1);
        $sectionTypes = $config['section_types'] ?? self::defaultSectionTypes();

        $sites = $this->news->resolveSiteHandles($sources, $currentSiteHandle);

        // The mixed-type, org-filtered, sticky-first lead.
        $lead = $this->news->queryLead($sites, [], $leadCount, [], $organizationIds);

        // Count the lead by blueprint type so each section can offset past its own
        // lead posts.
        $leadTypeCounts = [];
        foreach ($lead as $post) {
            $type = $this->blueprintHandle($post);

            if ($type !== null) {
                $leadTypeCounts[$type] = ($leadTypeCounts[$type] ?? 0) + 1;
            }
        }

        unset($post);

        $sections = [];
        foreach ($sectionTypes as $sectionType) {
            $type = $sectionType['type'];
            $count = $sectionType['count'] ?? self::DEFAULT_COUNT;
            $offset = $leadTypeCounts[$type] ?? 0;

            $posts = $this->news->queryTypeSection($sites, $organizationIds, $type, $offset, $count);

            // Hide a section below the threshold so a thin department shows only what has content.
            if ($posts->count() >= $threshold) {
                $sections[] = [
                    'type' => $type,
                    'heading' => $sectionType['heading'] ?? null,
                    'posts' => $posts,
                ];
            }
        }

        unset($sectionType);

        return [
            'lead' => $lead,
            'sections' => $sections,
        ];
    }

    /**
     * The default per-type section list for the department hub — the four `posts`
     * blueprints, each newest-first below the lead.
     *
     * @return array<int, array{type: string, heading: string, count: int}>
     */
    private static function defaultSectionTypes(): array
    {
        return [
            ['type' => 'post', 'heading' => 'News', 'count' => 4],
            ['type' => 'video', 'heading' => 'Videos', 'count' => 4],
            ['type' => 'audio', 'heading' => 'Podcasts', 'count' => 4],
            ['type' => 'news_release', 'heading' => 'News Releases', 'count' => 4],
        ];
    }

    /**
     * The blueprint handle of a post entry (its type), or null when unresolved.
     */
    private function blueprintHandle(Entry $post): ?string
    {
        $blueprint = $post->blueprint();

        return $blueprint instanceof Blueprint ? $blueprint->handle() : null;
    }
}
