<?php

declare(strict_types=1);

namespace App\Scopes;

use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Query\Builder;
use Statamic\Query\Scopes\Scope;
use Statamic\Stache\Query\EntryQueryBuilder;

/**
 * Entry query scope narrowing the `clinical_resources` archive by a text search,
 * a location and an area of expertise, read from the `q`, `location` and
 * `expertise` query parameters (UAMS-Web/uams-statamic#1583).
 *
 * Each parameter is optional and a blank one is no constraint, so the archive with
 * no parameters lists every resource, as `clinical_resource_type` does for `?type=`.
 * Given parameters combine with each other and with the type and provider scopes
 * piped beside this one: a resource must match all of them.
 *
 * - `q` matches the resource's title, case-insensitively.
 * - `location` is a `clinical_locations` entry slug, matched against the
 *   resource's `clinical_locations` field.
 * - `expertise` is a `clinical_expertise` entry slug, matched against the
 *   resource's `clinical_expertise` field.
 *
 * A slug that names no entry matches nothing, so a stale facet link shows the
 * filtered empty state and its reset link rather than the unfiltered archive.
 *
 * The matching runs in PHP over the collection and constrains the query to the
 * matching ids, as `clinical_locations_archive_filter` does. A filter that matches
 * nothing constrains to an id no entry has, so it empties the listing rather than
 * widening it.
 *
 * Example usage on the archive listing tag:
 * ```
 * {{ collection:clinical_resources
 *      query_scope="clinical_resources_archive_filter"
 *      q="{{ get:q }}" location="{{ get:location }}" expertise="{{ get:expertise }}" }}
 * ```
 */
class ClinicalResourcesArchiveFilter extends Scope
{
    /**
     * The collection the scope reads.
     */
    private const string COLLECTION = 'clinical_resources';

    /**
     * An id no entry has, used to empty the result when nothing matches.
     */
    private const string NO_MATCH_SENTINEL = '__no_matching_clinical_resource__';

    /**
     * Apply the search, location and expertise filters to the query.
     *
     * @param  Builder  $query  Entry query builder (modified in place).
     * @param  array<string, mixed>|\ArrayAccess<string, mixed>  $values  Parameters from the collection tag; reads `q`, `location` and `expertise`.
     */
    public function apply(
        $query,
        $values
    ): void {
        // Normalize each parameter; a blank (or non-string) value is no constraint.
        $term = \mb_strtolower($this->stringParameter($values, 'q'));
        $location = $this->stringParameter($values, 'location');
        $expertise = $this->stringParameter($values, 'expertise');
        if ($term === '' && $location === '' && $expertise === '') {
            return;
        }

        // Resolve each facet slug to the entry id a resource stores.
        $locationId = $location === '' ? '' : $this->entryId('clinical_locations', $location);
        $expertiseId = $expertise === '' ? '' : $this->entryId('clinical_expertise', $expertise);

        // Collect the ids of the resources matching every given filter.
        $ids = [];
        if ($locationId !== null && $expertiseId !== null) {
            foreach ($this->resources() as $resource) {
                $id = $resource->id();
                if ((\is_string($id) || \is_int($id)) && $this->matches($resource, $term, $locationId, $expertiseId)) {
                    $ids[] = (string) $id;
                }
            }
        }

        // Constrain to the matches, or to no entry when nothing matched.
        $query->whereIn('id', $ids === [] ? [self::NO_MATCH_SENTINEL] : $ids);
    }

    /**
     * Whether a resource matches every given filter.
     *
     * @param  Entry  $resource  The resource to test.
     * @param  string  $term  The lower-cased search term, or ''.
     * @param  string  $locationId  The location entry id, or ''.
     * @param  string  $expertiseId  The area of expertise entry id, or ''.
     */
    private function matches(
        Entry $resource,
        string $term,
        string $locationId,
        string $expertiseId
    ): bool {
        // The title must contain the search term.
        $title = $resource->get('title');
        if ($term !== '' && ! \str_contains(\mb_strtolower(\is_string($title) ? $title : ''), $term)) {
            return false;
        }

        // The related locations must include the location.
        if ($locationId !== '' && ! \in_array($locationId, $this->ids($resource->get('clinical_locations')), true)) {
            return false;
        }

        // The related areas of expertise must include the area.
        return $expertiseId === '' || \in_array($expertiseId, $this->ids($resource->get('clinical_expertise')), true);
    }

    /**
     * The id of the entry with the given slug in a collection, or null when none has it.
     *
     * @param  string  $collection  The collection handle.
     * @param  string  $slug  The entry slug.
     */
    private function entryId(
        string $collection,
        string $slug
    ): ?string {
        /** @var EntryQueryBuilder $query */
        $query = EntryFacade::query();
        $query->where('collection', $collection);
        $query->where('slug', $slug);

        $entry = $query->first();
        $id = $entry instanceof Entry ? $entry->id() : null;

        return \is_string($id) || \is_int($id) ? (string) $id : null;
    }

    /**
     * Every entry in the resources collection.
     *
     * @return iterable<int, Entry>
     */
    private function resources(): iterable
    {
        // One query over the collection; the collection tag applies status itself.
        /** @var EntryQueryBuilder $query */
        $query = EntryFacade::query();
        $query->where('collection', self::COLLECTION);

        /** @var iterable<int, Entry> $resources */
        $resources = $query->get();

        return $resources;
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
     * The entry ids in a stored entries value, which is a bare id or a list.
     *
     * @param  mixed  $value  The stored value.
     * @return list<string>
     */
    private function ids(mixed $value): array
    {
        // Wrap a bare id as a one-item list.
        $values = \is_array($value) ? $value : [$value];

        // Keep each non-empty scalar id as a string.
        $ids = [];
        foreach ($values as $item) {
            if ((\is_string($item) || \is_int($item)) && \trim((string) $item) !== '') {
                $ids[] = \trim((string) $item);
            }
        }

        return $ids;
    }
}
