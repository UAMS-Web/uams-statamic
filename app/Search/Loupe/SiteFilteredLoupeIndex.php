<?php

declare(strict_types=1);

namespace App\Search\Loupe;

use App\Providers\SearchServiceProvider;
use Daun\StatamicLoupe\Loupe\Index;
use Illuminate\Support\Collection;
use Loupe\Loupe\Configuration;
use Loupe\Loupe\SearchParameters;
use Statamic\Contracts\Query\ContainsQueryableValues;
use Statamic\Contracts\Search\Searchable;

/**
 * A `loupe` search index that stores each document's site and hands Loupe the site filter (#646).
 *
 * This index stores each document's site as a Loupe filterable attribute, and
 * {@see SiteFilteredLoupeQuery} hands the query's site constraint to Loupe as a filter. The
 * stored site is also what Statamic's own `site` filter reads on a hit returned without its
 * entry ({@see ContainsQueryableValues::getQueryableValue()}), so the two filters agree.
 *
 * Handing the filter to Loupe does not get past `max_total_hits`. Loupe limits each search
 * word's matching documents to the cap before it applies the filter, so a site-scoped search
 * for a word that matches more documents across all sites than the cap shows only those of the
 * site's matches that survived the cap (#511).
 *
 * The site is stored under {@see SITE_ATTRIBUTE} rather than as a searchable field, so a query
 * never matches a site handle.
 *
 * Bound in place of the add-on's index in {@see SearchServiceProvider}, so every
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
        $builder = new SiteFilteredLoupeQuery($this);
        $builder->query($query);

        return $builder;
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

        $highlightTags = $this->configStrings('highlight_tags');

        if (\count($highlightTags) !== 2) {
            throw $this->invalidSetting('highlight_tags', 'an opening and a closing tag');
        }

        $parameters = SearchParameters::create()
            ->withQuery($query)
            ->withMatchingStrategy($this->matchingStrategy())
            ->withHitsPerPage($this->hitsPerPage())
            ->withShowRankingScore(true)
            ->withRankingScoreThreshold((float) $this->configNumber('ranking_score_threshold'))
            ->withAttributesToHighlight(
                array_values(array_unique([...$this->configStrings('highlight_attributes'), ...array_keys($this->snippetAttributes())])),
                $highlightTags[0],
                $highlightTags[1]
            )
            ->withAttributesToCrop(
                $this->snippetAttributes(),
                (int) $this->configNumber('snippet_length'),
                $this->configString('snippet_marker'),
                (int) $this->configNumber('snippet_max_fragments')
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
                'search_score' => floor((is_numeric($hit['_rankingScore'] ?? null) ? (float) $hit['_rankingScore'] : 0.0) * 100),
            ]);
    }

    /**
     * One setting of this index, after the add-on's defaults are merged in.
     *
     * @param  string  $key  The config key.
     */
    private function setting(string $key): mixed
    {
        $config = $this->config();

        return \is_array($config) ? ($config[$key] ?? null) : null;
    }

    /**
     * The error for a setting that does not hold what the search needs, naming the index.
     *
     * @param  string  $key  The config key.
     * @param  string  $expected  What the setting must hold.
     */
    private function invalidSetting(string $key, string $expected): \UnexpectedValueException
    {
        $name = $this->name();

        return new \UnexpectedValueException(\sprintf(
            'Search index [%s] setting [%s] must hold %s.',
            \is_string($name) ? $name : '?',
            $key,
            $expected
        ));
    }

    /**
     * A numeric setting of this index.
     *
     * @param  string  $key  The config key.
     */
    private function configNumber(string $key): int|float
    {
        $value = $this->setting($key);

        if (! is_numeric($value)) {
            throw $this->invalidSetting($key, 'a number');
        }

        return $value + 0;
    }

    /**
     * A string setting of this index.
     *
     * @param  string  $key  The config key.
     */
    private function configString(string $key): string
    {
        $value = $this->setting($key);

        if (! \is_string($value)) {
            throw $this->invalidSetting($key, 'a string');
        }

        return $value;
    }

    /**
     * A list-of-strings setting of this index; absent reads as empty.
     *
     * @param  string  $key  The config key.
     * @return list<string>
     */
    private function configStrings(string $key): array
    {
        $value = $this->setting($key) ?? [];

        if (! \is_array($value) || array_filter($value, \is_string(...)) !== $value) {
            throw $this->invalidSetting($key, 'a list of strings');
        }

        return array_values($value);
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
