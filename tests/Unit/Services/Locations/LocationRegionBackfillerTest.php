<?php

declare(strict_types=1);

use App\Services\Locations\LocationRegionBackfiller;
use App\Services\Locations\LocationRegionResolver;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;

/**
 * Unit coverage for the off-save-path region backfiller
 * (App\Services\Locations\LocationRegionBackfiller, UAMS-Web/uams-statamic-archive#1112 — extracted from the
 * old UAMS-Web/uams-statamic-archive#962 ComputeLocationRegions save listener). The resolver is mocked so this
 * stays offline and focuses on the gating (only `clinical_locations`, only when
 * `regions` is empty, only with a usable coordinate) and the persisted shape
 * (scalar for `max_items: 1`), plus that a fill goes through `saveQuietly()` so it
 * fires no further save events.
 *
 * @command  php artisan test --compact tests/Unit/Services/Locations/LocationRegionBackfillerTest.php
 */
uses(MockeryPHPUnitIntegration::class);

/**
 * Build a mock location entry with the given stored `regions` and `map` values.
 *
 * The blueprint is stubbed to report the `regions` field's `max_items` so the
 * backfiller can shape its write to match the field (scalar for `max_items: 1`).
 *
 * @param  mixed  $regions  The value `get('regions')` returns
 * @param  mixed  $map  The value `get('map')` returns
 * @param  int|null  $maxItems  The `regions` field's `max_items` config (1 = single-item scalar)
 * @return Entry&MockInterface
 */
function mockLocationEntry(?string $collectionHandle, $regions, $map, ?int $maxItems = 1): Entry
{
    $collection = null;

    if ($collectionHandle !== null) {
        $collection = Mockery::mock(Collection::class);
        $collection->shouldReceive('handle')->andReturn($collectionHandle);
    }

    $field = Mockery::mock(Field::class);
    $field->shouldReceive('get')->with('max_items')->andReturn($maxItems);

    $blueprint = Mockery::mock(Blueprint::class);
    $blueprint->shouldReceive('field')->with('regions')->andReturn($field);

    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('collection')->andReturn($collection);
    $entry->shouldReceive('blueprint')->andReturn($blueprint);
    $entry->shouldReceive('get')->with('regions')->andReturn($regions);
    $entry->shouldReceive('get')->with('map')->andReturn($map);

    return $entry;
}

/**
 * Build a clinical_locations entry (empty `regions`) whose `blueprint()` resolves
 * to the given value, so the region-shaping fallback can be exercised when the
 * blueprint — or the `regions` field within it — cannot be read.
 *
 * @param  mixed  $blueprint  The value `blueprint()` returns (a Blueprint mock, or null)
 * @param  mixed  $map  The value `get('map')` returns
 * @return Entry&MockInterface
 */
function mockEntryWithBlueprint($blueprint, $map): Entry
{
    $collection = Mockery::mock(Collection::class);
    $collection->shouldReceive('handle')->andReturn('clinical_locations');

    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('collection')->andReturn($collection);
    $entry->shouldReceive('blueprint')->andReturn($blueprint);
    $entry->shouldReceive('get')->with('regions')->andReturn(null);
    $entry->shouldReceive('get')->with('map')->andReturn($map);

    return $entry;
}

/**
 * A resolver stub that returns the given regions for any coordinate.
 *
 * @param  array<int, string>  $regions  The slugs `regionsForCoordinate` returns
 */
function stubResolver(array $regions): LocationRegionResolver
{
    $resolver = Mockery::mock(LocationRegionResolver::class);
    $resolver->shouldReceive('regionsForCoordinate')->andReturn($regions);

    return $resolver;
}

it('fills an empty region from the resolved coordinate as a scalar (max_items: 1)', function (): void {
    // The clinical_locations `regions` field pins max_items: 1, so Statamic's
    // Terms fieldtype persists a bare scalar slug; an assisted fill must match
    // that exact shape (not a one-element list) or it diverges from editor picks.
    $entry = mockLocationEntry('clinical_locations', null, ['lat' => 34.7465, 'lng' => -92.2896], maxItems: 1);
    $entry->shouldReceive('set')->once()->with('regions', 'central-arkansas');
    $entry->shouldReceive('saveQuietly')->once();

    $resolver = Mockery::mock(LocationRegionResolver::class);
    $resolver->shouldReceive('regionsForCoordinate')->once()->with(34.7465, -92.2896)
        ->andReturn(['central-arkansas']);

    expect((new LocationRegionBackfiller($resolver))->backfill($entry))->toBeTrue();
});

it('stores a list when the regions field allows multiple items', function (): void {
    // A field without the single-item cap persists a list, so the backfiller must
    // not collapse to a scalar there.
    $entry = mockLocationEntry('clinical_locations', null, ['lat' => 34.7465, 'lng' => -92.2896], maxItems: null);
    $entry->shouldReceive('set')->once()->with('regions', ['central-arkansas']);
    $entry->shouldReceive('saveQuietly')->once();

    $resolver = Mockery::mock(LocationRegionResolver::class);
    $resolver->shouldReceive('regionsForCoordinate')->once()->with(34.7465, -92.2896)
        ->andReturn(['central-arkansas']);

    expect((new LocationRegionBackfiller($resolver))->backfill($entry))->toBeTrue();
});

it('never overwrites a region the editor or importer already set', function (): void {
    $entry = mockLocationEntry('clinical_locations', ['southwest-arkansas'], ['lat' => 34.7465, 'lng' => -92.2896]);
    $entry->shouldNotReceive('set');
    $entry->shouldNotReceive('saveQuietly');

    $resolver = Mockery::mock(LocationRegionResolver::class);
    $resolver->shouldNotReceive('regionsForCoordinate');

    $backfiller = new LocationRegionBackfiller($resolver);
    expect($backfiller->shouldBackfill($entry))->toBeFalse();
    expect($backfiller->backfill($entry))->toBeFalse();
});

it('does nothing when the entry has no usable coordinate', function (): void {
    $entry = mockLocationEntry('clinical_locations', null, null);
    $entry->shouldNotReceive('set');
    $entry->shouldNotReceive('saveQuietly');

    $resolver = Mockery::mock(LocationRegionResolver::class);
    $resolver->shouldNotReceive('regionsForCoordinate');

    $backfiller = new LocationRegionBackfiller($resolver);
    expect($backfiller->shouldBackfill($entry))->toBeFalse();
    expect($backfiller->backfill($entry))->toBeFalse();
});

it('does not set regions when the coordinate resolves to none', function (): void {
    $entry = mockLocationEntry('clinical_locations', null, ['lat' => 35.1, 'lng' => -90.0]);
    $entry->shouldNotReceive('set');
    $entry->shouldNotReceive('saveQuietly');

    $resolver = Mockery::mock(LocationRegionResolver::class);
    $resolver->shouldReceive('regionsForCoordinate')->once()->with(35.1, -90.0)->andReturn([]);

    // shouldBackfill is coordinate-only (cheap gate), so it is still true here;
    // the empty resolve is what stops the fill.
    $backfiller = new LocationRegionBackfiller($resolver);
    expect($backfiller->shouldBackfill($entry))->toBeTrue();
    expect($backfiller->backfill($entry))->toBeFalse();
});

it('ignores entries from other collections', function (): void {
    $entry = mockLocationEntry('posts', null, ['lat' => 34.7465, 'lng' => -92.2896]);
    $entry->shouldNotReceive('set');
    $entry->shouldNotReceive('saveQuietly');

    $resolver = Mockery::mock(LocationRegionResolver::class);
    $resolver->shouldNotReceive('regionsForCoordinate');

    expect((new LocationRegionBackfiller($resolver))->shouldBackfill($entry))->toBeFalse();
});

it('ignores an entry with no collection', function (): void {
    $entry = mockLocationEntry(null, null, ['lat' => 34.7465, 'lng' => -92.2896]);
    $entry->shouldNotReceive('set');
    $entry->shouldNotReceive('saveQuietly');

    $resolver = Mockery::mock(LocationRegionResolver::class);
    $resolver->shouldNotReceive('regionsForCoordinate');

    expect((new LocationRegionBackfiller($resolver))->shouldBackfill($entry))->toBeFalse();
});

it('never overwrites a scalar region already stored (the editor-pick shape)', function (): void {
    // An editor pick on a max_items: 1 terms field persists a bare scalar slug,
    // not a list — that pre-existing value must still be treated as "set".
    $entry = mockLocationEntry('clinical_locations', 'central-arkansas', ['lat' => 34.7465, 'lng' => -92.2896]);
    $entry->shouldNotReceive('set');
    $entry->shouldNotReceive('saveQuietly');

    $resolver = Mockery::mock(LocationRegionResolver::class);
    $resolver->shouldNotReceive('regionsForCoordinate');

    expect((new LocationRegionBackfiller($resolver))->shouldBackfill($entry))->toBeFalse();
});

it('treats an empty-array regions as unset and fills it', function (): void {
    $entry = mockLocationEntry('clinical_locations', [], ['lat' => 34.7465, 'lng' => -92.2896], maxItems: 1);
    $entry->shouldReceive('set')->once()->with('regions', 'central-arkansas');
    $entry->shouldReceive('saveQuietly')->once();

    expect((new LocationRegionBackfiller(stubResolver(['central-arkansas'])))->backfill($entry))->toBeTrue();
});

it('treats an empty-string regions as unset and fills it', function (): void {
    $entry = mockLocationEntry('clinical_locations', '', ['lat' => 34.7465, 'lng' => -92.2896], maxItems: 1);
    $entry->shouldReceive('set')->once()->with('regions', 'central-arkansas');
    $entry->shouldReceive('saveQuietly')->once();

    expect((new LocationRegionBackfiller(stubResolver(['central-arkansas'])))->backfill($entry))->toBeTrue();
});

it('does nothing when a coordinate component is non-numeric', function (): void {
    $entry = mockLocationEntry('clinical_locations', null, ['lat' => 'not-a-number', 'lng' => -92.2896]);
    $entry->shouldNotReceive('set');
    $entry->shouldNotReceive('saveQuietly');

    $resolver = Mockery::mock(LocationRegionResolver::class);
    $resolver->shouldNotReceive('regionsForCoordinate');

    expect((new LocationRegionBackfiller($resolver))->shouldBackfill($entry))->toBeFalse();
});

it('stores the first region as a scalar when several resolve for a single-item field', function (): void {
    // shapeRegions must take the FIRST resolved slug for a max_items: 1 field,
    // never the last — guards the array_key_first contract.
    $entry = mockLocationEntry('clinical_locations', null, ['lat' => 34.7465, 'lng' => -92.2896], maxItems: 1);
    $entry->shouldReceive('set')->once()->with('regions', 'central-arkansas');
    $entry->shouldReceive('saveQuietly')->once();

    expect((new LocationRegionBackfiller(stubResolver(['central-arkansas', 'southwest-arkansas'])))->backfill($entry))
        ->toBeTrue();
});

it('falls back to a list when the entry has no resolvable blueprint', function (): void {
    // Without a blueprint, max_items is unknown, so the safe shape is the list
    // (the multi-select default) rather than guessing a scalar.
    $entry = mockEntryWithBlueprint(null, ['lat' => 34.7465, 'lng' => -92.2896]);
    $entry->shouldReceive('set')->once()->with('regions', ['central-arkansas']);
    $entry->shouldReceive('saveQuietly')->once();

    expect((new LocationRegionBackfiller(stubResolver(['central-arkansas'])))->backfill($entry))->toBeTrue();
});

it('falls back to a list when the regions field is absent from the blueprint', function (): void {
    $blueprint = Mockery::mock(Blueprint::class);
    $blueprint->shouldReceive('field')->with('regions')->andReturn(null);

    $entry = mockEntryWithBlueprint($blueprint, ['lat' => 34.7465, 'lng' => -92.2896]);
    $entry->shouldReceive('set')->once()->with('regions', ['central-arkansas']);
    $entry->shouldReceive('saveQuietly')->once();

    expect((new LocationRegionBackfiller(stubResolver(['central-arkansas'])))->backfill($entry))->toBeTrue();
});
