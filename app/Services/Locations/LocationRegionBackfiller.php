<?php

declare(strict_types=1);

namespace App\Services\Locations;

use App\Jobs\BackfillLocationRegion;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;

/**
 * Fills a location's Arkansas `regions` from its coordinate, off the synchronous
 * save path (UAMS-Web/uams-statamic-archive#1112).
 *
 * This is the shared core the assisted-region model (UAMS-Web/uams-statamic-archive#962) used to run inline in
 * an `EntrySaving` listener. It is now driven by the queued
 * {@see BackfillLocationRegion} job (dispatched on `EntrySaved`) and the
 * `content:backfill-location-regions` command, so a bulk import of distinct
 * coordinates no longer serialize-stalls the save path on the Census outbound
 * call ({@see CensusCountyGeocoder}).
 *
 * The behavior contract is unchanged from the old listener: it only ever *fills*
 * an empty value (an editor pick or imported region is never overwritten), it
 * only acts on `clinical_locations`, and it writes the region in the exact shape
 * the `regions` field persists (a bare scalar slug for the blueprint's
 * `max_items: 1`) so an assisted fill is byte-identical to an editor pick.
 */
final class LocationRegionBackfiller
{
    /**
     * The collection whose entries derive regions from coordinates.
     */
    private const string LOCATIONS_COLLECTION = 'clinical_locations';

    /**
     * The blueprint handle of the terms field that receives the derived region.
     */
    private const string REGIONS_FIELD = 'regions';

    public function __construct(private readonly LocationRegionResolver $resolver) {}

    /**
     * Whether the entry is a candidate for a region backfill — a cheap gate that
     * makes no outbound call, so it is safe to run on every `EntrySaved` and per
     * entry in the backfill command.
     *
     * True only for a `clinical_locations` entry that has a usable coordinate and
     * no region yet.
     */
    public function shouldBackfill(Entry $entry): bool
    {
        return $this->isLocation($entry)
            && ! $this->hasRegions($entry)
            && $this->coordinate($entry) !== null;
    }

    /**
     * Resolve the entry's region from its coordinate and persist it, when the
     * entry still qualifies. Returns whether a region was written.
     *
     * The gate is re-checked here because the entry's state may have changed
     * between the dispatch (on `EntrySaved`) and this deferred run — another save
     * could have filled `regions` in the interim. The write uses `saveQuietly()`
     * so it fires no further save events (no re-dispatch loop), and the
     * empty-only gate makes the fill idempotent regardless.
     */
    public function backfill(Entry $entry): bool
    {
        if (! $this->shouldBackfill($entry)) {
            return false;
        }

        $coordinate = $this->coordinate($entry);

        // Guaranteed non-null by shouldBackfill(); the narrowing keeps the static
        // analyzer honest without changing behavior.
        if ($coordinate === null) {
            return false;
        }

        $regions = $this->resolver->regionsForCoordinate($coordinate['lat'], $coordinate['lng']);

        if ($regions === []) {
            return false;
        }

        $entry->set(self::REGIONS_FIELD, $this->shapeRegions($entry, $regions));
        $entry->saveQuietly();

        return true;
    }

    /**
     * Whether the entry belongs to the locations collection.
     */
    private function isLocation(Entry $entry): bool
    {
        /** @var ?Collection $collection */
        $collection = $entry->collection();

        return $collection !== null && $collection->handle() === self::LOCATIONS_COLLECTION;
    }

    /**
     * Whether the entry already carries a region (manual or imported).
     */
    private function hasRegions(Entry $entry): bool
    {
        $regions = $entry->get(self::REGIONS_FIELD);

        if (\is_array($regions)) {
            return $regions !== [];
        }

        return $regions !== null && $regions !== '';
    }

    /**
     * The entry's stored coordinate, or null when absent / incomplete.
     *
     * @return array{lat: float, lng: float}|null The coordinate, or null
     */
    private function coordinate(Entry $entry): ?array
    {
        $map = $entry->get('map');

        if (! \is_array($map) || ! is_numeric($map['lat'] ?? null) || ! is_numeric($map['lng'] ?? null)) {
            return null;
        }

        return ['lat' => (float) $map['lat'], 'lng' => (float) $map['lng']];
    }

    /**
     * Shape the derived region slug(s) to match how the `regions` terms field
     * itself persists, so an assisted fill is byte-identical to an editor pick.
     *
     * A single-item, single-taxonomy terms field (`max_items: 1`, as the
     * clinical_locations blueprint pins it) stores a bare scalar slug — not a
     * one-element list — via Statamic's Terms fieldtype `process()`. Writing the
     * raw list here would diverge from the editor / importer shape and break raw
     * `get('regions')` consumers (e.g. the provider `regions` computed field).
     *
     * @param  non-empty-array<int, string>  $regions  The resolved region slug(s)
     * @return string|array<int, string> The scalar slug for a single-item field, else the list
     */
    private function shapeRegions(Entry $entry, array $regions): string|array
    {
        $blueprint = $entry->blueprint();

        if (! $blueprint instanceof Blueprint) {
            return $regions;
        }

        $field = $blueprint->field(self::REGIONS_FIELD);

        if ($field instanceof Field && $field->get('max_items') === 1) {
            return $regions[array_key_first($regions)];
        }

        return $regions;
    }
}
