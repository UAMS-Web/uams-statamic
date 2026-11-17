<?php

declare(strict_types=1);

namespace App\Search\Loupe;

use Daun\StatamicLoupe\Loupe\Index;
use Illuminate\Support\Collection;
use Loupe\Loupe\Configuration;
use Loupe\Loupe\SearchParameters;
use Statamic\Contracts\Query\ContainsQueryableValues;
use Statamic\Contracts\Search\Searchable;

/**
 * A `loupe` search index that filters by site inside Loupe, before its hit cap (#646).
 *
 * `daun/statamic-loupe` asks Loupe for at most `max_total_hits` hits and leaves every
 * `where` to Statamic, which applies them to those hits afterwards. With the cap at 200 a
 * site-scoped search for a term that matches more than 200 documents across all sites then
 * shows only the share of the first 200 that belongs to the site, however many more the site
 * holds. This index stores each document's site as a Loupe filterable attribute, and
 * {@see SiteFilteredLoupeQuery} hands the query's site constraint to Loupe, so the cap counts
 * only documents of the sites asked for.
 *
 * The site is stored under {@see SITE_ATTRIBUTE} rather than as a searchable field, so a query
 * never matches a site handle. It is the value Statamic's own `site` filter reads afterwards
 * ({@see ContainsQueryableValues::getQueryableValue()}), so the two filters agree.
 *
 * Bound in place of the add-on's index in {@see \App\Providers\SearchServiceProvider}, so every
 * index on the `loupe` driver is one of these.
 */
class SiteFilteredLoupeIndex extends Index
{
    /**
     * The Loupe attribute each document's site handle is stored under.
     */
    public const string SITE_ATTRIBUTE = 'site';

    /**
     * Run the search through the query builder that passes the site filter to Loupe.
     *
     * @param  string  $query  The search phrase.
     */
    #[\Override]
    public function search($query): SiteFilteredLoupeQuery
    {
        return new SiteFilteredLoupeQuery($this)->query($query);
    }

    /**
     * The add-on's configuration, with the site attribute filterable.
     */
    #[\Override]
    public function configuration(): Configuration
    {
        if ($this->configuration instanceof Configuration) {
            return $this->configuration;
        }

        return $this->configuration = parent::configuration()->withFilterableAttributes([self::SITE_ATTRIBUTE]);
    }

    /**
     * The indexed fields of a searchable, with its site handle added.
     *
     * A searchable that reports no site handle is stored without one, so a site-filtered search
     * leaves it out, as Statamic's own `site` filter would.
     *
     * @param  Searchable  $searchable  The item being indexed.
     * @return array<string, mixed>
     */
    #[\Override]
    public function fields(Searchable $searchable): array
    {
        /** @var array<string, mixed> $fields */
        $fields = parent::fields($searchable);

        $site = $searchable instanceof ContainsQueryableValues ? $searchable->getQueryableValue('site') : null;

        if (\is_string($site)) {
            $fields[self::SITE_ATTRIBUTE] = $site;
        }

        return $fields;
    }

    /**
     * Look up a query, limited to the documents of the given sites.
     *
     * The add-on's `lookup()` builds its search parameters inline, so this repeats them with the
     * filter added. It mirrors `Daun\StatamicLoupe\Loupe\Index::lookup()` in `daun/statamic-loupe`
     * 4.2.0 (`f0b6f70f`).
     *
     * @param  string  $query  The search phrase.
     * @param  list<string>|null  $sites  The site handles to keep, or null for every site.
     * @return Collection<int, array<string, mixed>>
     */
    public function lookupInSites(string $query, ?array $sites): Collection
    {
        // No site can match an empty list, and Loupe cannot parse `IN ()`
        if ($sites === []) {
            return collect();
        }

        $parameters = SearchParameters::create()
            ->withQuery($query)
            ->withMatchingStrategy($this->matchingStrategy())
            ->withHitsPerPage($this->hitsPerPage())
            ->withShowRankingScore(true)
            ->withRankingScoreThreshold($this->config['ranking_score_threshold'])
            ->withAttributesToHighlight(
                array_unique([...$this->config['highlight_attributes'], ...array_keys($this->snippetAttributes())]),
                $this->config['highlight_tags'][0],
                $this->config['highlight_tags'][1]
            )
            ->withAttributesToCrop(
                $this->snippetAttributes(),
                $this->config['snippet_length'],
                $this->config['snippet_marker'],
                $this->config['snippet_max_fragments']
            );

        if ($sites !== null) {
            $parameters = $parameters->withFilter(self::siteFilter($sites));
        }

        $result = $this->client()->search($parameters);

        $primaryKey = $this->configuration()->getPrimaryKey();

        return collect($result->getHits())
            ->map(fn (array $hit): array => [
                ...$hit,
                'reference' => $hit[$primaryKey],
                'search_score' => floor($hit['_rankingScore'] * 100),
            ]);
    }

    /**
     * The Loupe filter that keeps documents of the given sites.
     *
     * @param  non-empty-list<string>  $sites  The site handles to keep.
     */
    public static function siteFilter(array $sites): string
    {
        return \sprintf(
            '%s IN (%s)',
            self::SITE_ATTRIBUTE,
            implode(', ', array_map(SearchParameters::escapeFilterValue(...), $sites))
        );
    }
}
