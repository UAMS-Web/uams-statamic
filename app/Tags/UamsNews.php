<?php

declare(strict_types=1);

namespace App\Tags;

use App\Services\Posts\NewsSyndicationQueryService;
use App\Support\Statamic\AugmentedEntryIds;
use App\Support\Statamic\Tags\TagParam;
use Illuminate\Support\Collection;
use Statamic\Entries\Entry;
use Statamic\Facades\Site;
use Statamic\Fields\LabeledValue;
use Statamic\Sites\Site as SiteModel;
use Statamic\Tags\Parameters;
use Statamic\Tags\Tags;

/**
 * Provides the {{ uams_news }} tag backing the UAMS News page-builder block (UAMS-Web/uams-statamic-archive#76).
 *
 * Delegates to {@see NewsSyndicationQueryService} to query the local `posts`
 * collection across the block's selected source site(s) — `news`, the current
 * site, or both — filtered by category and paged by count/offset. The `full`
 * format resolves a single entry by article id instead.
 *
 * Returns entries under the caller's `as` alias plus `no_results`/`total_results`
 * flags (mirroring {@see RssFeed}) and the `category_name`/`category_url` values
 * for the optional "View the Full List" CTA.
 *
 * Usage:
 *   {{ uams_news :format="format" :source_sites="source_sites" :category="category"
 *      :count="count" :offset="offset" :article_id="article_id" as="news_item" }}
 *     {{ if no_results }} ... {{ else }} {{ news_item }} ... {{ /news_item }} {{ /if }}
 *   {{ /uams_news }}
 */
class UamsNews extends Tags
{
    /**
     * The {{ uams_news }} tag.
     *
     * @return array<string, mixed>
     */
    public function index(): array
    {
        /** @var Parameters $paramsBag */
        $paramsBag = $this->params;
        $params = TagParam::all($paramsBag);

        $service = app(NewsSyndicationQueryService::class);

        $format = $this->stringParam($params, 'format');
        $as = $this->stringParam($params, 'as');
        $as = $as !== '' ? $as : 'news_item';

        // The `full` format renders one specific article selected via the
        // `entries` picker; every other format renders a cross-site,
        // category-filtered, paged list.
        if ($format === 'full') {
            $article = $service->findArticle($this->resolveArticleId($params['article_id'] ?? null));

            return $this->result(
                $as,
                $article instanceof Entry ? [$article] : [],
                categoryName: null,
                categoryUrl: null,
            );
        }

        // Resolve the source-site selector (news / current / both) to concrete
        // handles, mapping the placement-relative `current` token to this site.
        $sources = $this->listParam($params, 'source_sites');

        /** @var SiteModel $currentSite */
        $currentSite = Site::current();
        $currentHandle = $currentSite->handle();
        $siteHandles = $service->resolveSiteHandles(
            $sources,
            \is_string($currentHandle) ? $currentHandle : ''
        );

        // Resolve the category selection (post_categories entry reference(s)) to
        // a list of category entry ids.
        $categoryIds = $this->resolveCategoryIds($params['category'] ?? null);

        $entries = $service->query(
            $siteHandles,
            $categoryIds,
            $this->effectiveCount($format, $params),
            $this->intParam($params, 'offset'),
            $this->boolParam($params, 'require_featured_image'),
            $format,
        )->all();

        // Resolve the category-archive CTA target (name + URL) from the selected
        // category entry (UAMS-Web/uams-statamic-archive#1144 — the /category/{parent_uri}/{slug} archive route
        // now exists).
        $categoryLink = $service->resolveCategoryLink($categoryIds);

        return $this->result(
            $as,
            $entries,
            categoryName: $categoryLink['name'],
            categoryUrl: $categoryLink['url'],
        );
    }

    /**
     * Build the tag's return payload.
     *
     * @param  string  $as  The alias the template iterates.
     * @param  array<int, Entry>  $items  The resolved entries.
     * @param  ?string  $categoryName  The category title for the CTA, or null.
     * @param  ?string  $categoryUrl  The category archive URL for the CTA, or null.
     * @return array<string, mixed>
     */
    private function result(
        string $as,
        array $items,
        ?string $categoryName,
        ?string $categoryUrl
    ): array {
        return [
            $as => $items,
            'no_results' => $items === [],
            'total_results' => \count($items),
            'category_name' => $categoryName,
            'category_url' => $categoryUrl,
        ];
    }

    /**
     * Read a scalar parameter as a trimmed string ('' when absent/non-scalar).
     *
     * @param  array<string, mixed>  $params
     */
    private function stringParam(array $params, string $key): string
    {
        $value = $params[$key] ?? null;

        return \is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * Read a parameter as a non-negative integer (0 when absent/non-numeric).
     *
     * @param  array<string, mixed>  $params
     */
    private function intParam(array $params, string $key): int
    {
        $value = $params[$key] ?? null;

        return \is_numeric($value) ? max(0, (int) $value) : 0;
    }

    /**
     * Read a parameter as a boolean (false when absent).
     *
     * A `true_false` block field augments to a real boolean, but a raw `'true'`
     * / `'1'` string is tolerated too, so normalize via {@see filter_var()}.
     *
     * @param  array<string, mixed>  $params
     */
    private function boolParam(array $params, string $key): bool
    {
        return filter_var($params[$key] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Read a parameter as a list of non-empty string values.
     *
     * The `source_sites` checkboxes field does NOT arrive as a bare array of
     * handles: Antlers augments a `checkboxes` value to a list of
     * `['key' => …, 'value' => …, 'label' => …]` rows (and a single select to a
     * {@see LabeledValue}). A raw (un-augmented) array of scalars
     * or a single scalar is also tolerated, so each item is unwrapped to its
     * stored handle via {@see optionValue()}.
     *
     * @param  array<string, mixed>  $params
     * @return array<int, string>
     */
    private function listParam(array $params, string $key): array
    {
        $value = $params[$key] ?? null;

        if ($value instanceof Collection) {
            $value = $value->all();
        }

        if (\is_string($value)) {
            $value = $value !== '' ? [$value] : [];
        }

        if (! \is_array($value)) {
            return [];
        }

        return collect($value)
            ->map(fn ($item): ?string => $this->optionValue($item))
            ->filter(fn (?string $item): bool => $item !== null && $item !== '')
            ->values()
            ->all();
    }

    /**
     * Unwrap one checkboxes/select selection to its stored scalar handle.
     *
     * Handles the augmented `checkboxes` row shape
     * (`['key' => …, 'value' => …, 'label' => …]`), an augmented single-select
     * {@see LabeledValue} (exposing `value()`), and a raw scalar.
     */
    private function optionValue(mixed $item): ?string
    {
        if (\is_array($item)) {
            $value = $item['value'] ?? $item['key'] ?? null;

            return \is_scalar($value) ? (string) $value : null;
        }

        if (\is_object($item) && method_exists($item, 'value')) {
            $value = $item->value();

            return \is_scalar($value) ? (string) $value : null;
        }

        return \is_scalar($item) ? (string) $item : null;
    }

    /**
     * Resolve the effective item count for a format.
     *
     * `cards`, `grid`, and `side` carry fixed counts (4 / 3 / 1) that the editor
     * never sets; `headlines`/`excerpts` use the editor's `count` (defaulting to
     * 3). `full` is handled separately (a single selected article).
     *
     * @param  array<string, mixed>  $params
     */
    private function effectiveCount(string $format, array $params): int
    {
        return match ($format) {
            'cards' => 4,
            'grid' => 3,
            'side' => 1,
            default => $this->intParam($params, 'count') ?: 3,
        };
    }

    /**
     * Normalize the `article_id` selection (an `entries` picker value) to a
     * single entry id, or null when empty.
     */
    private function resolveArticleId(mixed $value): ?string
    {
        return AugmentedEntryIds::firstId($value);
    }

    /**
     * Normalize the `category` selection (a `post_categories` entries picker
     * value) to a de-duplicated list of category entry ids for
     * {@see NewsSyndicationQueryService::query()}.
     *
     * @return array<int, string>
     */
    private function resolveCategoryIds(mixed $value): array
    {
        return AugmentedEntryIds::fromValue($value);
    }
}
