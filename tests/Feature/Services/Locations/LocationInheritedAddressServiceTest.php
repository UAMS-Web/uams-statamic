<?php

declare(strict_types=1);

use App\Services\Facilities\FacilityInheritedAddressService;
use App\Services\Locations\LocationInheritedAddressService;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Fields\Blueprint;
use Statamic\Fields\LabeledValue;
use Statamic\Fields\Value;
use Statamic\Structures\Page;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Unit tests for `LocationInheritedAddressService::resolveStreetAddress` (UAMS-Web/uams-statamic-archive#1269).
 *
 * @covers \App\Services\Locations\LocationInheritedAddressService
 *
 * Mocked rather than fixture-driven, matching the sibling
 * `FacilityInheritedAddressServiceTest`. The `clinical_locations` collection is empty in a
 * fresh checkout, so a fixture-driven test here would be building the whole tree anyway.
 *
 * @command  php artisan test --compact tests/Feature/Services/Locations/LocationInheritedAddressServiceTest.php
 */
/**
 * A mocked location entry stubbing every handle the service may read.
 *
 * @param  array<string, mixed>  $gets  Handle-to-value overrides on top of the defaults
 */
function makeLocationEntry(
    string $id,
    ?object $parentPage = null,
    array $gets = [],
    string $blueprintHandle = 'location'
): Entry&MockInterface {
    $blueprint = Mockery::mock(Blueprint::class);
    $blueprint->shouldReceive('handle')->andReturn($blueprintHandle);

    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('id')->andReturn($id);
    $entry->shouldReceive('blueprint')->andReturn($blueprint);
    $entry->shouldReceive('parent')->andReturn($parentPage);

    // Every handle the service may read, so an unstubbed read fails loudly rather than
    // silently returning null and passing for the wrong reason.
    $defaults = [
        'parent_address_override_query' => null,
        'parent_address_override_street_query' => null,
        'parent_address_override_facility_query' => null,
        'parent_address_override_unit_query' => null,
        'parent_address_override_map_query' => null,
        'facility_query' => null,
        'facility' => null,
        'facility_level' => null,
        'suite' => null,
        'map' => null,
        'regions' => null,
        'address' => null,
    ];

    foreach (array_merge($defaults, $gets) as $handle => $value) {
        $entry->shouldReceive('get')->with($handle)->andReturn($value);
    }

    return $entry;
}

function makeLocationParentPage(string $id): MockInterface
{
    $page = Mockery::mock(Page::class);
    $page->shouldReceive('id')->andReturn($id);

    return $page;
}

function locationAddressResolver(): LocationInheritedAddressService
{
    return new LocationInheritedAddressService(new FacilityInheritedAddressService);
}

it('returns a standalone location own street address', function () {
    $location = makeLocationEntry('loc1', null, [
        'address' => ['street_address' => '4301 W. Markham St.', 'preset' => 'us'],
    ]);

    expect(locationAddressResolver()->resolveStreetAddress($location))
        ->toBe('4301 W. Markham St.');
});

it('returns null when a standalone location stores no street address', function () {
    expect(locationAddressResolver()->resolveStreetAddress(makeLocationEntry('loc1')))->toBeNull();
});

it('ignores a blank street address rather than returning whitespace', function () {
    $location = makeLocationEntry('loc1', null, ['address' => ['street_address' => '   ']]);

    expect(locationAddressResolver()->resolveStreetAddress($location))->toBeNull();
});

it('takes the facility address when the location is contained within one', function () {
    // Route two: the location names a facility and the facilities resolver answers for it.
    $facilityBlueprint = Mockery::mock(Blueprint::class);
    $facilityBlueprint->shouldReceive('handle')->andReturn('building');

    $facility = Mockery::mock(Entry::class);
    $facility->shouldReceive('blueprint')->andReturn($facilityBlueprint);
    $facility->shouldReceive('get')->with('streetaddress')->andReturn('2422 N. Thompson St.');

    EntryFacade::shouldReceive('find')->with('fac1')->andReturn($facility);

    $location = makeLocationEntry('loc1', null, [
        'facility_query' => true,
        'facility' => ['fac1'],
        'address' => ['street_address' => 'SHOULD NOT BE USED'],
    ]);

    expect(locationAddressResolver()->resolveStreetAddress($location))
        ->toBe('2422 N. Thompson St.');
});

it('returns null when the referenced facility cannot be loaded', function () {
    EntryFacade::shouldReceive('find')->with('missing')->andReturn(null);

    $location = makeLocationEntry('loc1', null, [
        'facility_query' => true,
        'facility' => ['missing'],
    ]);

    expect(locationAddressResolver()->resolveStreetAddress($location))->toBeNull();
});

it('inherits the parent location address for a child with no override', function () {
    // The ticket's second criterion, from the data side: default behavior is unchanged.
    $parent = makeLocationEntry('parent1', null, [
        'address' => ['street_address' => '1 Children Way'],
    ]);

    EntryFacade::shouldReceive('find')->with('parent1')->andReturn($parent);

    $child = makeLocationEntry('child1', makeLocationParentPage('parent1'), [
        'address' => ['street_address' => 'IGNORED WHILE INHERITING'],
    ]);

    expect(locationAddressResolver()->resolveStreetAddress($child))
        ->toBe('1 Children Way');
});

it('uses the child own address once the street group is overridden', function () {
    // UAMS-Web/uams-statamic-archive#1269's first criterion, from the data side; since UAMS-Web/uams-statamic-archive#2608 the street group's own toggle
    // must be on with the master.
    $parent = makeLocationEntry('parent1', null, [
        'address' => ['street_address' => '1 Children Way'],
    ]);

    EntryFacade::shouldReceive('find')->with('parent1')->andReturn($parent);

    $child = makeLocationEntry('child1', makeLocationParentPage('parent1'), [
        'parent_address_override_query' => true,
        'parent_address_override_street_query' => true,
        'address' => ['street_address' => '2 Annex Road'],
    ]);

    expect(locationAddressResolver()->resolveStreetAddress($child))
        ->toBe('2 Annex Road');
});

it('resolves through two levels of parent location', function () {
    $grandparent = makeLocationEntry('gp1', null, ['address' => ['street_address' => '9 Tower St.']]);
    $parent = makeLocationEntry('p1', makeLocationParentPage('gp1'));

    EntryFacade::shouldReceive('find')->with('gp1')->andReturn($grandparent);
    EntryFacade::shouldReceive('find')->with('p1')->andReturn($parent);

    $child = makeLocationEntry('c1', makeLocationParentPage('p1'));

    expect(locationAddressResolver()->resolveStreetAddress($child))->toBe('9 Tower St.');
});

it('does not inherit from a parent that is not a location', function () {
    // A clinical_locations tree can hold basic and landing pages; neither carries an address.
    $page = makeLocationEntry('page1', null, [], 'basic_page');

    EntryFacade::shouldReceive('find')->with('page1')->andReturn($page);

    $child = makeLocationEntry('c1', makeLocationParentPage('page1'), [
        'address' => ['street_address' => '5 Own St.'],
    ]);

    // Falls through to its own address rather than walking into a page with none.
    expect(locationAddressResolver()->resolveStreetAddress($child))->toBe('5 Own St.');
});

it('stops on a structural cycle instead of recursing without end', function () {
    // Two locations naming each other as parent. Without the visited set this never returns.
    $a = makeLocationEntry('a', makeLocationParentPage('b'));
    $b = makeLocationEntry('b', makeLocationParentPage('a'));

    EntryFacade::shouldReceive('find')->with('a')->andReturn($a);
    EntryFacade::shouldReceive('find')->with('b')->andReturn($b);

    expect(locationAddressResolver()->resolveStreetAddress($a))->toBeNull();
});

it('returns null when the parent entry cannot be loaded', function () {
    EntryFacade::shouldReceive('find')->with('gone')->andReturn(null);

    $child = makeLocationEntry('c1', makeLocationParentPage('gone'), [
        'address' => ['street_address' => '7 Fallback Ave.'],
    ]);

    // No parent entity resolves, so the child is treated as standalone and uses its own.
    expect(locationAddressResolver()->resolveStreetAddress($child))->toBe('7 Fallback Ave.');
});

/*
 * resolveCity (UAMS-Web/uams-statamic-archive#1225) -- the same three routes as the street address, asserted separately
 * because a shared walk is exactly where the two could silently become one.
 */

it('returns a standalone location own city', function () {
    $location = makeLocationEntry('loc1', null, [
        'address' => ['street_address' => '4301 W. Markham St.', 'city' => 'Little Rock'],
    ]);

    expect(locationAddressResolver()->resolveCity($location))->toBe('Little Rock');
});

it('does not confuse the city with the street address', function () {
    // The two resolvers share a walk and differ only in the subfield they read. Without
    // this, wiring both to the same subfield passes every other test in this file.
    $location = makeLocationEntry('loc1', null, [
        'address' => ['street_address' => '4301 W. Markham St.', 'city' => 'Little Rock'],
    ]);

    $service = locationAddressResolver();

    expect($service->resolveStreetAddress($location))->toBe('4301 W. Markham St.')
        ->and($service->resolveCity($location))->toBe('Little Rock');
});

it('returns null when a standalone location stores no city', function () {
    $location = makeLocationEntry('loc1', null, [
        'address' => ['street_address' => '4301 W. Markham St.'],
    ]);

    expect(locationAddressResolver()->resolveCity($location))->toBeNull();
});

it('takes the facility city when the location is contained within one', function () {
    // Route two. A contained location stores no address of its own at all, which is why
    // reading `address.city` directly would leave it blank.
    $facilityBlueprint = Mockery::mock(Blueprint::class);
    $facilityBlueprint->shouldReceive('handle')->andReturn('building');

    $facility = Mockery::mock(Entry::class);
    $facility->shouldReceive('blueprint')->andReturn($facilityBlueprint);
    $facility->shouldReceive('get')->with('city')->andReturn('Fort Smith');

    EntryFacade::shouldReceive('find')->with('fac1')->andReturn($facility);

    $location = makeLocationEntry('loc1', null, [
        'facility_query' => true,
        'facility' => ['fac1'],
    ]);

    expect(locationAddressResolver()->resolveCity($location))->toBe('Fort Smith');
});

it('inherits the parent location city for a child with no override', function () {
    $parent = makeLocationEntry('parent1', null, [
        'address' => ['city' => 'Little Rock'],
    ]);

    EntryFacade::shouldReceive('find')->with('parent1')->andReturn($parent);

    $child = makeLocationEntry('child1', makeLocationParentPage('parent1'), [
        'address' => ['city' => 'IGNORED WHILE INHERITING'],
    ]);

    expect(locationAddressResolver()->resolveCity($child))->toBe('Little Rock');
});

it('uses the child own city once the street group is overridden', function () {
    // The city belongs to the street group, so the same toggles govern it -- a child that has
    // taken its street address over has taken its city with it.
    $parent = makeLocationEntry('parent1', null, ['address' => ['city' => 'Little Rock']]);

    EntryFacade::shouldReceive('find')->with('parent1')->andReturn($parent);

    $child = makeLocationEntry('child1', makeLocationParentPage('parent1'), [
        'parent_address_override_query' => true,
        'parent_address_override_street_query' => true,
        'address' => ['city' => 'Fort Smith'],
    ]);

    expect(locationAddressResolver()->resolveCity($child))->toBe('Fort Smith');
});

/*
 * resolveState and resolvePostalCode (UAMS-Web/uams-statamic-archive#2389). The state is the only component whose storage
 * handle is not fixed, so it carries most of the cases here.
 */

it('reads the state variant matching the stored preset', function () {
    // Three handles, one component. Reading a fixed handle returns null for any address
    // entered under either of the other two presets.
    $american = makeLocationEntry('loc1', null, [
        'address' => ['preset' => 'american', 'state_province_region_american' => 'AR'],
    ]);
    $canadian = makeLocationEntry('loc2', null, [
        'address' => ['preset' => 'canadian', 'state_province_region_canadian' => 'ON'],
    ]);

    expect(locationAddressResolver()->resolveState($american))->toBe('AR')
        ->and(locationAddressResolver()->resolveState($canadian))->toBe('ON');
});

it('does not read another preset variant when one is stored', function () {
    // A Canadian address whose American variant also holds a value must not yield it.
    $location = makeLocationEntry('loc1', null, [
        'address' => [
            'preset' => 'canadian',
            'state_province_region_canadian' => 'ON',
            'state_province_region_american' => 'WRONG',
        ],
    ]);

    expect(locationAddressResolver()->resolveState($location))->toBe('ON');
});

it('falls back to the American variant when no preset is stored', function () {
    // What the fieldtype itself defaults to: `process()` writes the first preset option
    // when the field config names none, and that option is `american`.
    $location = makeLocationEntry('loc1', null, [
        'address' => ['state_province_region_american' => 'AR'],
    ]);

    expect(locationAddressResolver()->resolveState($location))->toBe('AR');
});

it('takes the facility state and zip when the location is contained within one', function () {
    // The facility keeps one flat `state` and calls the postal code `zip`; the location
    // side has three state variants and calls it `postal_code`. Both differences are
    // reconciled by the resolvers rather than by the caller.
    $facilityBlueprint = Mockery::mock(Blueprint::class);
    $facilityBlueprint->shouldReceive('handle')->andReturn('building');

    $facility = Mockery::mock(Entry::class);
    $facility->shouldReceive('blueprint')->andReturn($facilityBlueprint);
    $facility->shouldReceive('get')->with('state')->andReturn('AR');
    $facility->shouldReceive('get')->with('zip')->andReturn('72205');

    EntryFacade::shouldReceive('find')->with('fac1')->andReturn($facility);

    $location = makeLocationEntry('loc1', null, [
        'facility_query' => true,
        'facility' => ['fac1'],
    ]);

    $service = locationAddressResolver();

    expect($service->resolveState($location))->toBe('AR')
        ->and($service->resolvePostalCode($location))->toBe('72205');
});

it('inherits the parent location state and postal code for a child with no override', function () {
    $parent = makeLocationEntry('parent1', null, [
        'address' => ['preset' => 'american', 'state_province_region_american' => 'AR', 'postal_code' => '72205'],
    ]);

    EntryFacade::shouldReceive('find')->with('parent1')->andReturn($parent);

    $child = makeLocationEntry('child1', makeLocationParentPage('parent1'));

    $service = locationAddressResolver();

    expect($service->resolveState($child))->toBe('AR')
        ->and($service->resolvePostalCode($child))->toBe('72205');
});

it('ignores an address value that is not a subfield map', function () {
    $location = makeLocationEntry('loc1', null, ['address' => 'a bare string']);

    expect(locationAddressResolver()->resolveStreetAddress($location))->toBeNull();
});

it('reads the canonical state handle the Address fieldtype stores', function () {
    // `HasSubfields` stores every preset's state under its `canonical_handle`,
    // `state_province_region`, so an address saved through the CP carries only that key.
    $location = makeLocationEntry('loc1', null, [
        'address' => ['preset' => 'american', 'state_province_region' => 'Arkansas'],
    ]);

    expect(locationAddressResolver()->resolveState($location))->toBe('Arkansas');
});

/*
 * Per-group overrides (UAMS-Web/uams-statamic-archive#2608). The parent below is contained within a facility on a given
 * level and suite, with its own region and map pin stored as leftovers, which a contained
 * location never reads.
 */

/**
 * A facility entry the facilities resolver can answer for, registered with the Entry facade.
 */
function locationGroupsFacility(string $id, string $street): Entry&MockInterface
{
    $facilityBlueprint = Mockery::mock(Blueprint::class);
    $facilityBlueprint->shouldReceive('handle')->andReturn('building');

    $facility = Mockery::mock(Entry::class);
    $facility->shouldReceive('id')->andReturn($id);
    $facility->shouldReceive('blueprint')->andReturn($facilityBlueprint);
    $facility->shouldReceive('get')->with('streetaddress')->andReturn($street);
    $facility->shouldReceive('get')->with('regions')->andReturn($id.'-region');

    EntryFacade::shouldReceive('find')->with($id)->andReturn($facility);

    return $facility;
}

/**
 * A top-level parent location contained within facility `fac1`, registered with the facade.
 */
function locationGroupsParent(): Entry&MockInterface
{
    locationGroupsFacility('fac1', '4301 W. Markham St.');

    $parent = makeLocationEntry('parent1', null, [
        'facility_query' => true,
        'facility' => ['fac1'],
        'facility_level' => '3',
        'suite' => 'Suite 300',
    ]);

    EntryFacade::shouldReceive('find')->with('parent1')->andReturn($parent);

    return $parent;
}

/**
 * A child of `parent1` with the master on and the named groups overridden.
 *
 * @param  list<string>  $groups  The groups whose toggle is on
 * @param  array<string, mixed>  $gets  Further stored values
 */
function locationGroupsChild(array $groups, array $gets = [], bool $master = true): Entry
{
    $toggles = ['parent_address_override_query' => $master];

    foreach ($groups as $group) {
        $toggles[LocationInheritedAddressService::GROUP_OVERRIDE[$group]] = true;
    }

    return makeLocationEntry('child1', makeLocationParentPage('parent1'), array_merge($toggles, $gets));
}

it('lets a child override only the unit and inherit street, facility and map', function () {
    locationGroupsParent();

    $child = locationGroupsChild(['unit'], [
        'facility_level' => '5',
        'suite' => 'Suite 510',
        // Stored but not owned: a leftover street must not leak through.
        'address' => ['street_address' => 'SHOULD NOT BE USED'],
    ]);

    $resolver = locationAddressResolver();

    expect($resolver->resolveUnitLocation($child)?->id())->toBe('child1')
        ->and($resolver->resolveStreetAddress($child))->toBe('4301 W. Markham St.')
        ->and($resolver->resolveFacilityId($child))->toBe('fac1')
        ->and($resolver->resolveMap($child))->toBeNull()
        // Contained through its parent, so the region is the facility's, as the street is.
        ->and($resolver->resolveRegions($child))->toBe('fac1-region');
});

it('lets a child override only the street and inherit facility, unit and map', function () {
    locationGroupsParent();

    $child = locationGroupsChild(['street'], [
        'address' => ['street_address' => '2 Annex Road', 'city' => 'Little Rock'],
        'regions' => ['central'],
        // Stored but not owned: a leftover level must not leak through.
        'facility_level' => '9',
    ]);

    $resolver = locationAddressResolver();

    expect($resolver->resolveStreetAddress($child))->toBe('2 Annex Road')
        ->and($resolver->resolveCity($child))->toBe('Little Rock')
        ->and($resolver->resolveRegions($child))->toBe(['central'])
        ->and($resolver->resolveFacilityId($child))->toBe('fac1')
        ->and($resolver->resolveUnitLocation($child)?->id())->toBe('parent1');
});

it('lets a child override only the facility, taking its street from the new facility', function () {
    locationGroupsParent();
    locationGroupsFacility('fac2', '1 Annex Blvd.');

    $child = locationGroupsChild(['facility'], [
        'facility_query' => true,
        'facility' => ['fac2'],
    ]);

    $resolver = locationAddressResolver();

    // The street follows the facility the child now names, not the parent's building.
    expect($resolver->resolveFacilityId($child))->toBe('fac2')
        ->and($resolver->resolveStreetAddress($child))->toBe('1 Annex Blvd.')
        ->and($resolver->resolveUnitLocation($child)?->id())->toBe('parent1');
});

it('lets a child override only the map pin', function () {
    locationGroupsParent();

    $child = locationGroupsChild(['map'], ['map' => ['lat' => 34.75, 'lng' => -92.32]]);

    $resolver = locationAddressResolver();

    expect($resolver->resolveMap($child))->toBe(['lat' => 34.75, 'lng' => -92.32])
        ->and($resolver->resolveStreetAddress($child))->toBe('4301 W. Markham St.')
        ->and($resolver->resolveFacilityId($child))->toBe('fac1');
});

it('inherits every group when the master is off, whatever the group toggles say', function () {
    locationGroupsParent();

    $child = locationGroupsChild(['street', 'facility', 'unit', 'map'], [
        'address' => ['street_address' => 'SHOULD NOT BE USED'],
        'facility_query' => false,
        'suite' => 'SHOULD NOT BE USED',
        'map' => ['lat' => 1, 'lng' => 1],
    ], false);

    $resolver = locationAddressResolver();

    expect($resolver->resolveStreetAddress($child))->toBe('4301 W. Markham St.')
        ->and($resolver->resolveFacilityId($child))->toBe('fac1')
        ->and($resolver->resolveUnitLocation($child)?->id())->toBe('parent1')
        ->and($resolver->resolveMap($child))->toBeNull();
});

it('resolves a child with every group overridden exactly as a top-level location', function () {
    // The migrated shape: a child that had the single UAMS-Web/uams-statamic-archive#1269 toggle on now has the master and
    // all four groups on, and must render as it did.
    locationGroupsParent();
    locationGroupsFacility('fac3', '9 Other St.');

    $stored = [
        'facility_query' => true,
        'facility' => ['fac3'],
        'suite' => 'Suite 12',
        'address' => ['street_address' => 'HIDDEN WHILE CONTAINED'],
        'map' => ['lat' => 1, 'lng' => 1],
    ];

    $child = locationGroupsChild(['street', 'facility', 'unit', 'map'], $stored);
    $topLevel = makeLocationEntry('top1', null, $stored);

    $resolver = locationAddressResolver();

    foreach ([$child, $topLevel] as $location) {
        expect($resolver->resolveStreetAddress($location))->toBe('9 Other St.')
            ->and($resolver->resolveFacilityId($location))->toBe('fac3')
            ->and($resolver->resolveMap($location))->toBeNull();
    }

    expect($resolver->resolveUnitLocation($child)?->id())->toBe('child1')
        ->and($resolver->resolveUnitLocation($topLevel)?->id())->toBe('top1');
});

it('gives a top-level standalone location no facility and no unit, whatever is stored', function () {
    $location = makeLocationEntry('top1', null, [
        'facility_query' => false,
        'facility' => ['fac1'],
        'suite' => 'Suite 1',
        'map' => ['lat' => 34.75, 'lng' => -92.32],
        'regions' => ['central'],
    ]);

    $resolver = locationAddressResolver();

    expect($resolver->resolveFacilityId($location))->toBeNull()
        ->and($resolver->resolveUnitLocation($location)?->id())->toBeNull()
        ->and($resolver->resolveMap($location))->toBe(['lat' => 34.75, 'lng' => -92.32])
        ->and($resolver->resolveRegions($location))->toBe(['central']);
});

/**
 * An unsaved, real `clinical_locations` entry on the `location` blueprint.
 *
 * @param  array<string, mixed>  $data
 */
function locationRealEntry(array $data): Entry
{
    $entry = new Entry;
    $entry->collection('clinical_locations');
    $entry->blueprint('location');
    $entry->data($data);

    return $entry;
}

it('resolves the facility level to its option label on a real location entry', function () {
    // Not mocked: the label comes from the `facility_level` select's options in the
    // blueprint, so only a real entry proves the key "1" reads as "First Floor".
    $location = locationRealEntry(['facility_query' => true, 'facility_level' => '1', 'suite' => 'Suite 110']);

    $resolver = locationAddressResolver();

    expect($resolver->resolveFacilityLevel($location))->toBe('First Floor')
        ->and($resolver->resolveSuite($location))->toBe('Suite 110');
});

it('resolves the single-story level option to no level at all', function () {
    // The `false` option says the location IS the facility, so there is no floor to name.
    $location = locationRealEntry(['facility_query' => true, 'facility_level' => 'false']);

    expect(locationAddressResolver()->resolveFacilityLevel($location))->toBeNull();
});

it('takes the level and suite from the parent for a child that does not own the unit', function () {
    locationGroupsParent()->shouldReceive('augmentedValue')->with('facility_level')
        ->andReturn(new Value(new LabeledValue('3', 'Third Floor')));

    $child = locationGroupsChild(['street'], [
        'address' => ['street_address' => '9 Other St.'],
        'facility_level' => 'STORED-LEVEL',
        'suite' => 'STORED-SUITE',
    ]);

    $resolver = locationAddressResolver();

    expect($resolver->resolveFacilityLevel($child))->toBe('Third Floor')
        ->and($resolver->resolveSuite($child))->toBe('Suite 300');
});

it('names the containing facility, and names nothing for a location that is not contained', function () {
    locationGroupsFacility('fac1', '4301 W. Markham St.')->shouldReceive('get')->with('title')->andReturn('Outpatient Center');
    $parent = makeLocationEntry('parent1', null, ['facility_query' => true, 'facility' => ['fac1']]);
    EntryFacade::shouldReceive('find')->with('parent1')->andReturn($parent);

    $resolver = locationAddressResolver();

    expect($resolver->resolveFacilityName(locationGroupsChild(['unit'])))->toBe('Outpatient Center')
        ->and($resolver->resolveFacilityName(makeLocationEntry('top1')))->toBeNull()
        ->and($resolver->resolveSuite(makeLocationEntry('top1', null, ['suite' => 'Suite 1'])))->toBeNull();
});

it('carries the second address line with the street group', function () {
    $parent = makeLocationEntry('parent1', null, [
        'address' => ['street_address' => '1 Main St.', 'address_line_2' => 'Building C'],
    ]);
    EntryFacade::shouldReceive('find')->with('parent1')->andReturn($parent);

    $inheriting = locationGroupsChild(['unit'], ['address' => ['address_line_2' => 'STORED-LINE-2']]);
    $owning = makeLocationEntry('child2', makeLocationParentPage('parent1'), [
        'parent_address_override_query' => true,
        'parent_address_override_street_query' => true,
        'address' => ['street_address' => '9 Other St.'],
    ]);

    $resolver = locationAddressResolver();

    expect($resolver->resolveAddressLine2($parent))->toBe('Building C')
        ->and($resolver->resolveAddressLine2($inheriting))->toBe('Building C')
        ->and($resolver->resolveAddressLine2($owning))->toBeNull();
});

it('lists the region slugs from either stored shape, without a taxonomy prefix', function () {
    $resolver = locationAddressResolver();

    expect($resolver->resolveRegionSlugs(makeLocationEntry('a', null, ['regions' => 'central-arkansas'])))
        ->toBe(['central-arkansas'])
        ->and($resolver->resolveRegionSlugs(makeLocationEntry('b', null, ['regions' => ['regions::northwest-arkansas', 'northwest-arkansas']])))
        ->toBe(['northwest-arkansas'])
        ->and($resolver->resolveRegionSlugs(makeLocationEntry('c')))->toBe([]);
});

it('gives a child that inherits its street the parent region slugs', function () {
    $parent = makeLocationEntry('parent1', null, ['regions' => 'central-arkansas']);
    EntryFacade::shouldReceive('find')->with('parent1')->andReturn($parent);

    expect(locationAddressResolver()->resolveRegionSlugs(locationGroupsChild(['unit'], ['regions' => 'STORED'])))
        ->toBe(['central-arkansas']);
});

it('lets a page on another blueprint under a location inherit nothing', function () {
    // `clinical_locations` also holds basic and landing pages; one nested under a location
    // is not a child location, so its title must not gain the parent's city (UAMS-Web/uams-statamic-archive#2608).
    $parent = makeLocationEntry('parent1', null, ['address' => ['city' => 'Little Rock']]);
    EntryFacade::shouldReceive('find')->with('parent1')->andReturn($parent);

    $page = makeLocationEntry('page1', makeLocationParentPage('parent1'), [], 'basic_page');

    $resolver = locationAddressResolver();

    expect($resolver->resolveCity($page))->toBeNull()
        ->and($resolver->resolveCity(makeLocationEntry('child1', makeLocationParentPage('parent1'))))->toBe('Little Rock');
});
