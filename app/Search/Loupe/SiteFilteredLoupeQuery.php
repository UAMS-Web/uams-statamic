<?php

declare(strict_types=1);

namespace App\Search\Loupe;

use Daun\StatamicLoupe\Loupe\Query;
use Illuminate\Support\Collection;

/**
 * A `loupe` search query that hands its site constraint to Loupe (#646).
 *
 * Statamic applies a query's `where` clauses to the hits the driver returns. This builder reads
 * the clauses on `site` and passes the sites they allow to {@see SiteFilteredLoupeIndex} as a
 * Loupe filter. Loupe applies that filter after it limits each search word's matches to
 * `max_total_hits`, so it does not recover a site's matches the cap has already cut (#511).
 * The clauses also stay on the query, so Statamic still applies them afterwards and the result
 * is the same whether or not they were handed down.
 *
 * Only a query whose clauses are all joined by `and` is narrowed: under an `or`, a site clause
 * does not bound the result, so the search then runs across every site as before.
 */
class SiteFilteredLoupeQuery extends Query
{
    /**
     * The search hits, limited to the sites the query's clauses allow.
     *
     * @param  string  $query  The search phrase.
     * @return Collection<int, array<string, mixed>>
     */
    #[\Override]
    public function getSearchResults($query): Collection
    {
        /** @var SiteFilteredLoupeIndex $index */
        $index = $this->index;

        return $index->lookupInSites($query, $this->allowedSites());
    }

    /**
     * The site handles every `site` clause allows, or null when the clauses do not bound the site.
     *
     * Each `whereIn('site', …)` and `where('site', …)` narrows the set to its values. A clause on
     * `site` of any other kind, or with a value that is not a string, leaves the site to
     * Statamic's own filter.
     *
     * @return list<string>|null
     */
    public function allowedSites(): ?array
    {
        $sites = null;

        $wheres = \is_array($this->wheres) ? $this->wheres : [];

        foreach ($wheres as $where) {
            // A clause Statamic stores in a shape this builder does not know leaves the site unbounded
            if (! \is_array($where) || ($where['boolean'] ?? 'and') !== 'and') {
                return null;
            }

            if (($where['column'] ?? null) !== SiteFilteredLoupeIndex::SITE_ATTRIBUTE) {
                continue;
            }

            $values = self::siteValues($where);

            if ($values === null) {
                continue;
            }

            $sites = $sites === null ? $values : array_values(array_intersect($sites, $values));
        }

        return $sites;
    }

    /**
     * The site handles one `site` clause allows, or null when it is not one this builder reads.
     *
     * @param  array<mixed>  $where  The clause, as Statamic's query builder stores it.
     * @return list<string>|null
     */
    private static function siteValues(array $where): ?array
    {
        $values = match ($where['type'] ?? null) {
            'In' => $where['values'] ?? null,
            'Basic' => ($where['operator'] ?? null) === '=' ? [$where['value'] ?? null] : null,
            default => null,
        };

        if ($values instanceof Collection) {
            $values = $values->all();
        }

        if (! \is_array($values)) {
            return null;
        }

        $sites = [];

        foreach ($values as $value) {
            if (! \is_string($value)) {
                return null;
            }

            $sites[] = $value;
        }

        return array_values(array_unique($sites));
    }
}
