<?php

declare(strict_types=1);

use App\Services\Facilities\FacilityInheritedAddressService;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Fields\Blueprint;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Unit tests for `FacilityInheritedAddressService::resolveStreetAddress`.
 *
 * @covers \App\Services\Facilities\FacilityInheritedAddressService
 *
 * Mutation: 92.11% on App\Services\Facilities\FacilityInheritedAddressService (38
 * mutants, --covered-only). The 3 survivors are equivalent: the continue-vs-break
 * after a non-resolving ancestor (ContentAncestorIds only ever yields such an id
 * last, so nothing follows it to expose the difference), and the two empty-string
 * blueprint-handle fallbacks (a '' vs a non-empty handle both fail the
 * `=== 'parking_division'` toggle gate identically).
 *
 * @command  php artisan test --compact tests/Feature/Services/Facilities/FacilityInheritedAddressServiceTest.php
 */
it('returns a building entry own street address', function () {
    $building = makeFacilityEntry('b1', 'building', null, ['streetaddress' => '4301 W. Markham St.']);

    expect((new FacilityInheritedAddressService)->resolveStreetAddress($building))
        ->toBe('4301 W. Markham St.');
});

it('returns a parking division own address when the toggle is on', function () {
    $parking = makeFacilityEntry('pk1', 'parking_division', null, [
        'streetaddress_query' => true,
        'streetaddress' => '4401 Shuffield Drive',
    ]);

    expect((new FacilityInheritedAddressService)->resolveStreetAddress($parking))
        ->toBe('4401 Shuffield Drive');
});

it('inherits the parent address for a parking division with the toggle off', function () {
    $facility = makeFacilityEntry('fac1', 'parking_facility', null, ['streetaddress' => '2422 N. Thompson St.']);
    $parkingPage = makePage('fac1');
    $parking = makeFacilityEntry('pk1', 'parking_division', $parkingPage, ['streetaddress_query' => false]);

    EntryFacade::shouldReceive('findOrFail')->with('pk1')->andReturn($parking);
    EntryFacade::shouldReceive('find')->with('fac1')->andReturn($facility);

    expect((new FacilityInheritedAddressService)->resolveStreetAddress($parking))
        ->toBe('2422 N. Thompson St.');
});

it('inherits the parent building address for a wing with no address fields', function () {
    $building = makeFacilityEntry('bldg1', 'building', null, ['streetaddress' => '4301 W. Markham St.']);
    $wingPage = makePage('bldg1');
    $wing = makeFacilityEntry('wing1', 'building_division', $wingPage, ['streetaddress' => null]);

    EntryFacade::shouldReceive('findOrFail')->with('wing1')->andReturn($wing);
    EntryFacade::shouldReceive('find')->with('bldg1')->andReturn($building);

    expect((new FacilityInheritedAddressService)->resolveStreetAddress($wing))
        ->toBe('4301 W. Markham St.');
});

it('resolves a multi-level inherited address from the nearest ancestor with one', function () {
    $top = makeFacilityEntry('top', 'building', null, ['streetaddress' => '123 Main St.']);
    $midPage = makePage('top');
    $mid = makeFacilityEntry('mid', 'building_division', $midPage, ['streetaddress' => null]);
    $wingPage = makePage('mid');
    $wing = makeFacilityEntry('wing', 'building_division', $wingPage, ['streetaddress' => null]);

    EntryFacade::shouldReceive('findOrFail')->with('wing')->andReturn($wing);
    EntryFacade::shouldReceive('find')->with('mid')->andReturn($mid);
    EntryFacade::shouldReceive('find')->with('top')->andReturn($top);

    expect((new FacilityInheritedAddressService)->resolveStreetAddress($wing))
        ->toBe('123 Main St.');
});

it('returns null when no ancestor carries a street address', function () {
    $topPage = null;
    $top = makeFacilityEntry('top', 'building_division', $topPage, ['streetaddress' => null]);
    $wingPage = makePage('top');
    $wing = makeFacilityEntry('wing', 'building_division', $wingPage, ['streetaddress' => null]);

    EntryFacade::shouldReceive('findOrFail')->with('wing')->andReturn($wing);
    EntryFacade::shouldReceive('find')->with('top')->andReturn($top);

    expect((new FacilityInheritedAddressService)->resolveStreetAddress($wing))
        ->toBeNull();
});

it('returns null without error for an addressless entry that has no ancestors', function () {
    $orphan = makeFacilityEntry('orphan', 'building_division', null, ['streetaddress' => null]);

    EntryFacade::shouldReceive('findOrFail')->with('orphan')->andReturn($orphan);

    expect((new FacilityInheritedAddressService)->resolveStreetAddress($orphan))
        ->toBeNull();
});

it('ignores a blank own street address and inherits instead', function () {
    $building = makeFacilityEntry('bldg', 'building', null, ['streetaddress' => '4301 W. Markham St.']);
    $childPage = makePage('bldg');
    // A building-complex child whose own streetaddress is whitespace-only must inherit.
    $child = makeFacilityEntry('child', 'building_complex', $childPage, ['streetaddress' => '   ']);

    EntryFacade::shouldReceive('findOrFail')->with('child')->andReturn($child);
    EntryFacade::shouldReceive('find')->with('bldg')->andReturn($building);

    expect((new FacilityInheritedAddressService)->resolveStreetAddress($child))
        ->toBe('4301 W. Markham St.');
});

it('returns null without walking when the entry has no usable id', function () {
    $blueprint = Mockery::mock(Blueprint::class);
    $blueprint->shouldReceive('handle')->andReturn('building_division');

    // An addressless entry whose id() is not a scalar cannot resolve ancestors.
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('id')->andReturn(null);
    $entry->shouldReceive('blueprint')->andReturn($blueprint);
    $entry->shouldReceive('get')->with('streetaddress')->andReturn(null);

    expect((new FacilityInheritedAddressService)->resolveStreetAddress($entry))->toBeNull();
});

it('skips an ancestor id that no longer resolves to an entry', function () {
    // The structural parent points at a now-deleted entry: ContentAncestorIds
    // still returns its id, but loading it yields null and must be skipped.
    $ghostPage = makePage('ghost');
    $wing = makeFacilityEntry('wing', 'building_division', $ghostPage, ['streetaddress' => null]);

    EntryFacade::shouldReceive('findOrFail')->with('wing')->andReturn($wing);
    EntryFacade::shouldReceive('find')->with('ghost')->andReturnNull();

    expect((new FacilityInheritedAddressService)->resolveStreetAddress($wing))->toBeNull();
});

it('treats a null blueprint as no handle and reads the own address', function () {
    // A degenerate entry with no blueprint must not error; the parking toggle gate
    // is simply skipped and the own street address is returned.
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('blueprint')->andReturnNull();
    $entry->shouldReceive('get')->with('streetaddress')->andReturn('5 Oak St.');

    expect((new FacilityInheritedAddressService)->resolveStreetAddress($entry))->toBe('5 Oak St.');
});

it('returns a building_complex own address even when an ancestor has a different one', function () {
    // Own address must win — the walk must not even be consulted.
    $ancestor = makeFacilityEntry('anc', 'building', null, ['streetaddress' => '999 Ancestor Rd.']);
    $complexPage = makePage('anc');
    $complex = makeFacilityEntry('bc', 'building_complex', $complexPage, ['streetaddress' => '100 Complex Ave.']);

    EntryFacade::shouldReceive('findOrFail')->with('bc')->andReturn($complex);
    EntryFacade::shouldReceive('find')->with('anc')->andReturn($ancestor);

    expect((new FacilityInheritedAddressService)->resolveStreetAddress($complex))
        ->toBe('100 Complex Ave.');
});

it('returns a parking_facility own address', function () {
    $parkingFacility = makeFacilityEntry('pf', 'parking_facility', null, ['streetaddress' => '200 Park Blvd.']);

    expect((new FacilityInheritedAddressService)->resolveStreetAddress($parkingFacility))
        ->toBe('200 Park Blvd.');
});

it('inherits when a parking_division toggle is on but its own address is blank', function () {
    $building = makeFacilityEntry('fac', 'building', null, ['streetaddress' => '300 Real St.']);
    $parkingPage = makePage('fac');
    $parking = makeFacilityEntry('pk', 'parking_division', $parkingPage, [
        'streetaddress_query' => true,
        'streetaddress' => '',
    ]);

    EntryFacade::shouldReceive('findOrFail')->with('pk')->andReturn($parking);
    EntryFacade::shouldReceive('find')->with('fac')->andReturn($building);

    expect((new FacilityInheritedAddressService)->resolveStreetAddress($parking))
        ->toBe('300 Real St.');
});

it('skips a toggle-off parking_division ancestor that stores an address', function () {
    $top = makeFacilityEntry('top', 'building', null, ['streetaddress' => '500 Real Ave.']);
    $midPage = makePage('top');
    // A toggle-off parking_division ancestor with a stored address must be skipped, not leaked.
    $mid = makeFacilityEntry('mid', 'parking_division', $midPage, [
        'streetaddress_query' => false,
        'streetaddress' => '999 Skip Me.',
    ]);
    $wingPage = makePage('mid');
    $wing = makeFacilityEntry('wing', 'building_division', $wingPage, ['streetaddress' => null]);

    EntryFacade::shouldReceive('findOrFail')->with('wing')->andReturn($wing);
    EntryFacade::shouldReceive('find')->with('mid')->andReturn($mid);
    EntryFacade::shouldReceive('find')->with('top')->andReturn($top);

    expect((new FacilityInheritedAddressService)->resolveStreetAddress($wing))
        ->toBe('500 Real Ave.');
});

it('ignores a non-string own street address', function (mixed $nonString) {
    $entry = makeFacilityEntry('x', 'building', null, ['streetaddress' => $nonString]);
    EntryFacade::shouldReceive('findOrFail')->with('x')->andReturn($entry);

    expect((new FacilityInheritedAddressService)->resolveStreetAddress($entry))->toBeNull();
})->with([
    'array' => fn () => ['123 Main St.'],
    'number' => fn () => 123,
]);

/*
 * resolveCity (UAMS-Web/uams-statamic-archive#1225). No facility blueprint gates a city behind a toggle, so this walk has
 * no equivalent of the parking-division `streetaddress_query` case -- which is the one way
 * the two resolvers differ and therefore the one worth pinning.
 */

it('returns a building entry own city', function () {
    $building = makeFacilityEntry('b1', 'building', null, ['city' => 'Little Rock']);

    expect((new FacilityInheritedAddressService)->resolveCity($building))->toBe('Little Rock');
});

it('inherits the parent building city for a wing, which carries none', function () {
    $building = makeFacilityEntry('bldg1', 'building', null, ['city' => 'Little Rock']);
    $wingPage = makePage('bldg1');
    $wing = makeFacilityEntry('wing1', 'building_division', $wingPage, ['city' => null]);

    EntryFacade::shouldReceive('findOrFail')->with('wing1')->andReturn($wing);
    EntryFacade::shouldReceive('find')->with('bldg1')->andReturn($building);

    expect((new FacilityInheritedAddressService)->resolveCity($wing))->toBe('Little Rock');
});

it('inherits a parking division city regardless of its street-address toggle', function () {
    // The street address is gated behind `streetaddress_query`; the city is not, because a
    // parking division carries no city field at all. Asserted with the toggle ON, which is
    // the arm that would fail if the city walk copied the address walk wholesale.
    $facility = makeFacilityEntry('fac1', 'parking_facility', null, ['city' => 'Fort Smith']);
    $parkingPage = makePage('fac1');
    $parking = makeFacilityEntry('pk1', 'parking_division', $parkingPage, [
        'streetaddress_query' => true,
        'city' => null,
    ]);

    EntryFacade::shouldReceive('findOrFail')->with('pk1')->andReturn($parking);
    EntryFacade::shouldReceive('find')->with('fac1')->andReturn($facility);

    expect((new FacilityInheritedAddressService)->resolveCity($parking))->toBe('Fort Smith');
});

it('returns null when no ancestor provides a city', function () {
    $wing = makeFacilityEntry('wing1', 'building_division', null, ['city' => null]);

    // The ancestor walk resolves the entry itself through findOrFail before climbing; with
    // no parent page there is nothing above it.
    EntryFacade::shouldReceive('findOrFail')->with('wing1')->andReturn($wing);

    expect((new FacilityInheritedAddressService)->resolveCity($wing))->toBeNull();
});

it('ignores a blank city rather than returning whitespace', function () {
    $building = makeFacilityEntry('b1', 'building', null, ['city' => '   ']);

    // Whitespace is not a provided city, so the walk proceeds and finds no ancestor.
    EntryFacade::shouldReceive('findOrFail')->with('b1')->andReturn($building);

    expect((new FacilityInheritedAddressService)->resolveCity($building))->toBeNull();
});

/**
 * Build a facility entry mock with an id, blueprint handle, structural parent
 * page, and stored field values keyed by handle.
 *
 * @param  array<string, mixed>  $gets  Stored values keyed by field handle
 */
function makeFacilityEntry(
    string $id,
    string $blueprintHandle,
    ?object $parentPage,
    array $gets
): Entry&MockInterface {
    $blueprint = Mockery::mock(Blueprint::class);
    $blueprint->shouldReceive('handle')->andReturn($blueprintHandle);

    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('id')->andReturn($id);
    $entry->shouldReceive('blueprint')->andReturn($blueprint);
    $entry->shouldReceive('parent')->andReturn($parentPage);

    foreach ($gets as $handle => $value) {
        $entry->shouldReceive('get')->with($handle)->andReturn($value);
    }

    return $entry;
}

/**
 * Build a structure page mock that answers id() with the given entry id.
 */
function makePage(string $id): MockInterface
{
    $page = Mockery::mock();
    $page->shouldReceive('id')->andReturn($id);

    return $page;
}
