<?php

declare(strict_types=1);

namespace App\Scopes;

use App\Services\Locations\LocationInheritedAddressService;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Query\Builder;
use Statamic\Query\Scopes\Scope;
use Statamic\Stache\Query\EntryQueryBuilder;

/**
 * Entry query scope narrowing the `clinical_locations` archive by a text search,
 * an Arkansas region and a location type, read from the `q`, `region` and `type`
 * query parameters (UAMS-Web/uams-statamic#1579).
 *
 * Each parameter is optional and a blank one is no constraint, so the archive with
 * no parameters lists every location, as `clinical_resource_type` does for `?type=`.
 * Given parameters combine: a location must match all of them.
 *
 * - `q` matches the location's title, case-insensitively.
 * - `region` matches the location's resolved regions: a location contained within a
 *   facility takes the facility's regions (`LocationInheritedAddressService`), so
 *   the stored `regions` field alone would miss it.
 * - `type` matches a `location_types` term slug.
 *
 * The matching runs in PHP over the collection and constrains the query to the
 * matching ids, because the resolved regions are not a stored field the query
 * builder can read. A filter that matches nothing constrains to an id no entry has,
 * so it empties the listing rather than widening it.
 *
 * Example usage on the archive listing tag:
 * ```
 * {{ collection:clinical_locations
 *      query_scope="clinical_locations_archive_filter"
 *      q="{{ get:q }}" region="{{ get:region }}" type="{{ get:type }}" }}
 * ```
 */
class ClinicalLocationsArchiveFilter extends Scope
{
    /**
     * The collection the scope reads.
     */
    private const string COLLECTION = 'clinical_locations';

    /**
     * An id no entry has, used to empty the result when nothing matches.
     */
    private const string NO_MATCH_SENTINEL = '__no_matching_clinical_location__';

    /**
     * @param  LocationInheritedAddressService  $addresses  Resolves a location's regions, inherited ones included.
     */
    public function __construct(
        private readonly LocationInheritedAddressService $addresses
    ) {}

    /**
     * Apply the search, region and type filters to the query.
     *
     * @param  Builder  $query  Entry query builder (modified in place).
     * @param  array<string, mixed>|\ArrayAccess<string, mixed>  $values  Parameters from the collection tag; reads `q`, `region` and `type`.
     */
    public function apply(
        $query,
        $values
    ): void {
        // Normalize each parameter; a blank (or non-string) value is no constraint.
        $term = \mb_strtolower($this->stringParameter($values, 'q'));
        $region = $this->slug($this->stringParameter($values, 'region'));
        $type = $this->slug($this->stringParameter($values, 'type'));
        if ($term === '' && $region === '' && $type === '') {
            return;
        }

        // Collect the ids of the locations matching every given filter.
        $ids = [];
        foreach ($this->locations() as $location) {
            $id = $location->id();
            if ((\is_string($id) || \is_int($id)) && $this->matches($location, $term, $region, $type)) {
                $ids[] = (string) $id;
            }
        }

        // Constrain to the matches, or to no entry when nothing matched.
        $query->whereIn('id', $ids === [] ? [self::NO_MATCH_SENTINEL] : $ids);
    }

    /**
     * Whether a location matches every given filter.
     *
     * @param  Entry  $location  The location to test.
     * @param  string  $term  The lower-cased search term, or ''.
     * @param  string  $region  The region slug, or ''.
     * @param  string  $type  The location type slug, or ''.
     */
    private function matches(
        Entry $location,
        string $term,
        string $region,
        string $type
    ): bool {
        // The title must contain the search term.
        $title = $location->get('title');
        if ($term !== '' && ! \str_contains(\mb_strtolower(\is_string($title) ? $title : ''), $term)) {
            return false;
        }

        // The resolved regions must include the region.
        if ($region !== '' && ! \in_array($region, $this->addresses->resolveRegionSlugs($location), true)) {
            return false;
        }

        // The location types must include the type.
        return $type === '' || \in_array($type, $this->slugs($location->get('location_types')), true);
    }

    /**
     * Every entry in the locations collection.
     *
     * @return iterable<int, Entry>
     */
    private function locations(): iterable
    {
        // One query over the collection; the collection tag applies status itself.
        /** @var EntryQueryBuilder $query */
        $query = EntryFacade::query();
        $query->where('collection', self::COLLECTION);

        /** @var iterable<int, Entry> $locations */
        $locations = $query->get();

        return $locations;
    }

    /**
     * A trimmed string parameter, or '' when it is absent or not a string.
     *
     * @param  array<string, mixed>|\ArrayAccess<string, mixed>  $values  Parameters from the collection tag (a `Statamic\Tags\Parameters` at runtime).
     * @param  string  $key  The parameter to read.
     */
    private function stringParameter(
        array|\ArrayAccess $values,
        string $key
    ): string {
        // Antlers coerces a bare `?key` to a bool, which is no value.
        $raw = $values[$key] ?? '';

        return \is_string($raw) ? \trim($raw) : '';
    }

    /**
     * A term slug without any `taxonomy::` prefix.
     *
     * @param  string  $value  A slug or a `taxonomy::slug` reference.
     */
    private function slug(string $value): string
    {
        // Keep the part after the last `::`, if there is one.
        $position = \strrpos($value, '::');

        return $position === false ? $value : \substr($value, $position + 2);
    }

    /**
     * The term slugs in a stored terms value, which is a bare slug or a list.
     *
     * @param  mixed  $value  The stored value.
     * @return list<string>
     */
    private function slugs(mixed $value): array
    {
        // Wrap a bare slug as a one-item list.
        $values = \is_array($value) ? $value : [$value];

        // Keep each non-empty string, without any taxonomy prefix.
        $slugs = [];
        foreach ($values as $item) {
            if (\is_string($item) && \trim($item) !== '') {
                $slugs[] = $this->slug(\trim($item));
            }
        }

        return $slugs;
    }
}
