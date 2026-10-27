<?php

declare(strict_types=1);

namespace App\Tags;

use App\Services\Posts\NewsSyndicationQueryService;
use App\Support\Multisite\DepartmentHubResolver;
use App\Support\Statamic\AugmentedEntryIds;
use App\Support\Statamic\Tags\TagParam;
use Statamic\Facades\Site;
use Statamic\Sites\Site as SiteModel;
use Statamic\Tags\Parameters;
use Statamic\Tags\Tags;

/**
 * Provides the {{ news_archive }} tag backing the Departmental News Hub's news
 * archive view (UAMS-Web/uams-statamic-archive#1599).
 *
 * The archive is one parameterized paginated listing that every hub "More" / CTA
 * link resolves to: the site's org-associated published `posts`, filterable along
 * three axes — organization (invariant, the site's `department_organization`),
 * blueprint type (`post`/`audio`/`video`/`news_release`), and source (newsroom /
 * local / both) — ordered sticky-first then newest, paginated. It delegates the
 * query to {@see NewsSyndicationQueryService::paginateArchive()}.
 *
 * Each axis is read from the query string first (the "More"/filter links and the
 * pagination controls are URLs — `?type=video&source=newsroom&page=2`), with an
 * explicit tag parameter allowed to force a value (a template pinning the org, or
 * the tests). The organization defaults to the current site's designated
 * `department_organization` (UAMS-Web/uams-statamic-archive#1556); a non-hub site resolves none, so the archive
 * degrades to an unscoped newsroom listing rather than an empty page.
 *
 * Usage:
 *   {{ news_archive as="posts" }}
 *     {{ if no_results }} … {{ else }}
 *       {{ posts }} … {{ /posts }}
 *       {{ if paginate:total_pages > 1 }} … {{ paginate:prev_page }} … {{ /if }}
 *     {{ /if }}
 *   {{ /news_archive }}
 */
class NewsArchive extends Tags
{
    /**
     * Default posts per page (WordPress's "Blog pages show at most N" default).
     */
    private const int DEFAULT_PER_PAGE = 10;

    /**
     * The {{ news_archive }} tag.
     *
     * @return array<string, mixed>
     */
    public function index(): array
    {
        // Normalize the tag parameter bag to a plain array once (mirrors UamsNews),
        // so the axis reads are array-access rather than repeated bag calls.
        /** @var Parameters $paramsBag */
        $paramsBag = $this->params;
        $params = TagParam::all($paramsBag);

        $service = app(NewsSyndicationQueryService::class);
        $resolver = app(DepartmentHubResolver::class);

        /** @var SiteModel $current */
        $current = Site::current();
        $currentHandle = \is_string($current->handle()) ? $current->handle() : '';

        // Source axis → concrete site handles (news / current / both), validated.
        // Normalize to the known set so `active_source` can only ever be one of
        // three safe literals — the query string is attacker-controllable and the
        // view renders it raw (Antlers is unescaped by default).
        $source = $this->axis($params, 'source', 'both');
        if (! \in_array($source, ['newsroom', 'local', 'both'], true)) {
            $source = 'both';
        }

        $siteHandles = $this->resolveSites($source, $currentHandle, $service);

        // Organization axis (invariant): an explicit `:organization` param wins,
        // else the current site's designated department_organization (UAMS-Web/uams-statamic-archive#1556).
        $organizationIds = AugmentedEntryIds::fromValue($params['organization'] ?? null);
        if ($organizationIds === []) {
            $siteOrg = $resolver->organizationId($currentHandle);

            if ($siteOrg !== null) {
                $organizationIds = [$siteOrg];
            }
        }

        // Type axis: a recognized post blueprint handle, else no type filter.
        $type = $this->axis($params, 'type', '');
        $blueprints = \in_array($type, NewsSyndicationQueryService::POST_BLUEPRINTS, true) ? [$type] : [];

        // Category axis (optional): a forced `:category` entries id param.
        $categoryIds = AugmentedEntryIds::fromValue($params['category'] ?? null);

        $perPage = $this->perPage($params);
        $page = $this->currentPage($params);

        $paginator = $service->paginateArchive(
            $siteHandles,
            $organizationIds,
            $blueprints,
            $categoryIds,
            $perPage,
            $page,
        );

        // Build page URLs off the current request, carrying the active filters
        // (?type / ?source) across pages so pagination preserves the filter.
        $paginator->setPath(request()->url())->withQueryString();

        $as = $this->stringParam($params, 'as') ?: 'posts';

        return [
            $as => $paginator->items(),
            'no_results' => $paginator->total() === 0,
            'total_results' => $paginator->total(),
            // The active filter state, for a filter notice / active-tab styling.
            // `active_type` is exposed only when it is a recognized blueprint (so
            // an unknown/hostile `?type=` never reaches the raw-Antlers view);
            // `active_source` is already normalized to a safe literal above.
            'active_type' => $blueprints !== [] ? $type : null,
            'active_source' => $source,
            'paginate' => [
                'current_page' => $paginator->currentPage(),
                'total_pages' => $paginator->lastPage(),
                'prev_page' => $paginator->previousPageUrl(),
                'next_page' => $paginator->nextPageUrl(),
            ],
        ];
    }

    /**
     * Resolve a filter axis: an explicit, non-empty tag parameter wins, else the
     * same-named query-string value, else the default. Trims and lower-cases so
     * `?Source=Newsroom` and `:source="newsroom"` resolve identically.
     *
     * @param  array<string, mixed>  $params  The normalized tag parameters.
     */
    private function axis(array $params, string $key, string $default): string
    {
        $param = $params[$key] ?? null;

        if (\is_scalar($param) && trim((string) $param) !== '') {
            return strtolower(trim((string) $param));
        }

        $query = request()->query($key);

        if (\is_string($query) && trim($query) !== '') {
            return strtolower(trim($query));
        }

        return $default;
    }

    /**
     * Map the source axis to the concrete, de-duplicated, existing site handles
     * to query: newsroom (the `news` site), local (the current site), or both.
     *
     * @return array<int, string>
     */
    private function resolveSites(
        string $source,
        string $currentHandle,
        NewsSyndicationQueryService $service
    ): array {
        $tokens = match ($source) {
            'newsroom' => [NewsSyndicationQueryService::NEWS_SITE],
            'local' => [NewsSyndicationQueryService::SOURCE_CURRENT],
            default => [NewsSyndicationQueryService::NEWS_SITE, NewsSyndicationQueryService::SOURCE_CURRENT],
        };

        return $service->resolveSiteHandles($tokens, $currentHandle);
    }

    /**
     * Resolve the effective per-page count: a positive `:per_page` param wins,
     * else the default.
     *
     * @param  array<string, mixed>  $params  The normalized tag parameters.
     */
    private function perPage(array $params): int
    {
        $param = $params['per_page'] ?? null;

        return \is_numeric($param) && (int) $param > 0 ? (int) $param : self::DEFAULT_PER_PAGE;
    }

    /**
     * Resolve the 1-based current page: a `:page` param wins, else the `?page=`
     * query string, else 1.
     *
     * @param  array<string, mixed>  $params  The normalized tag parameters.
     */
    private function currentPage(array $params): int
    {
        $param = $params['page'] ?? null;

        if (\is_numeric($param)) {
            return max(1, (int) $param);
        }

        $query = request()->query('page');

        return \is_numeric($query) ? max(1, (int) $query) : 1;
    }

    /**
     * Read a scalar tag parameter as a trimmed string ('' when absent/non-scalar).
     *
     * @param  array<string, mixed>  $params  The normalized tag parameters.
     */
    private function stringParam(array $params, string $key): string
    {
        $value = $params[$key] ?? null;

        return \is_scalar($value) ? trim((string) $value) : '';
    }
}
