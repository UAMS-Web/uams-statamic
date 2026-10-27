<?php

declare(strict_types=1);

use App\Services\Facilities\FacilityInheritedParkingService;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Fields\Blueprint;
use Statamic\Structures\Page;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Unit tests for `FacilityInheritedParkingService`.
 *
 * @covers \App\Services\Facilities\FacilityInheritedParkingService
 *
 * Mutation: 93.26% on App\Services\Facilities\FacilityInheritedParkingService (89
 * mutants, --covered-only). The 6 survivors are equivalent: the `isset`-keyed
 * seen-set whose `=> true` value is never read, the two `find()`-result instanceof
 * guards (EntryFacade::find only ever returns an Entry or null, both handled
 * identically), the `(string)` cast on already-string entry ids, and the two
 * blueprint-handle empty-string fallbacks (a '' vs a non-empty handle both fall to
 * the `match` default).
 *
 * @command  php artisan test --compact tests/Feature/Services/Facilities/FacilityInheritedParkingServiceTest.php
 */
it('resolves a contained location entire parking chain from its facility entity', function () {
    $entity = makeParkingEntry('pe', 'parking_facility', [
        'parking_instructions' => 'Park in deck B',
        'directions_from_parking' => 'Enter via the south doors',
        'parking_free_query' => true,
        'parking_validated_query' => false,
    ]);
    $building = makeParkingEntry('b', 'building', [
        'parking_query' => true,
        'parking_facility' => 'pe',
    ]);
    $location = makeParkingEntry('loc', 'location', [
        'facility_query' => true,
        'parking_facility_inherit_query' => true,
        'facility' => 'b',
        'parking_instructions_inherit_query' => true,
        'directions_from_parking_inherit_query' => true,
    ]);

    EntryFacade::shouldReceive('find')->with('b')->andReturn($building);
    EntryFacade::shouldReceive('find')->with('pe')->andReturn($entity);

    $service = new FacilityInheritedParkingService;

    expect($service->resolveParkingFacility($location)?->id())->toBe('pe')
        ->and($service->resolveParkingInstructions($location))->toBe('Park in deck B')
        ->and($service->resolveDirectionsFromParking($location))->toBe('Enter via the south doors')
        ->and($service->resolveParkingFree($location))->toBeTrue()
        ->and($service->resolveParkingValidated($location))->toBeFalse();
});

it('returns a contained location own instructions when it overrides them', function () {
    $location = makeParkingEntry('loc', 'location', [
        'parking_instructions_inherit_query' => false,
        'parking_instructions' => 'Use the valet entrance on 7th St.',
    ]);

    expect((new FacilityInheritedParkingService)->resolveParkingInstructions($location))
        ->toBe('Use the valet entrance on 7th St.');
});

it('resolves a standalone location directly-picked parking facility and its instructions', function () {
    $entity = makeParkingEntry('pe', 'parking_division', ['parking_instructions' => 'Surface lot C']);
    $location = makeParkingEntry('loc', 'location', [
        'facility_query' => false,
        'parking_query' => true,
        'parking_facility' => 'pe',
        'parking_instructions_inherit_query' => true,
    ]);

    EntryFacade::shouldReceive('find')->with('pe')->andReturn($entity);

    $service = new FacilityInheritedParkingService;

    expect($service->resolveParkingFacility($location)?->id())->toBe('pe')
        ->and($service->resolveParkingInstructions($location))->toBe('Surface lot C');
});

it('resolves a building_division parking from its parent building', function () {
    $entity = makeParkingEntry('pe', 'parking_facility', []);
    $building = makeParkingEntry('b', 'building', [
        'parking_query' => true,
        'parking_facility' => 'pe',
    ]);
    $buildingPage = makeParkingPage('b');
    $wing = makeParkingEntry('w', 'building_division', [
        'parking_facility_inherit_query' => true,
    ], $buildingPage);

    EntryFacade::shouldReceive('find')->with('b')->andReturn($building);
    EntryFacade::shouldReceive('find')->with('pe')->andReturn($entity);

    expect((new FacilityInheritedParkingService)->resolveParkingFacility($wing)?->id())->toBe('pe');
});

it('returns a building_division own parking facility when it overrides inheritance', function () {
    $entity = makeParkingEntry('pe2', 'parking_division', []);
    $wing = makeParkingEntry('w', 'building_division', [
        'parking_facility_inherit_query' => false,
        'parking_facility' => 'pe2',
    ]);

    EntryFacade::shouldReceive('find')->with('pe2')->andReturn($entity);

    expect((new FacilityInheritedParkingService)->resolveParkingFacility($wing)?->id())->toBe('pe2');
});

it('resolves multi-level location to wing to building to parking entity', function () {
    $entity = makeParkingEntry('pe', 'parking_facility', []);
    $building = makeParkingEntry('b', 'building', [
        'parking_query' => true,
        'parking_facility' => 'pe',
    ]);
    $buildingPage = makeParkingPage('b');
    $wing = makeParkingEntry('w', 'building_division', [
        'parking_facility_inherit_query' => true,
    ], $buildingPage);
    $location = makeParkingEntry('loc', 'location', [
        'facility_query' => true,
        'parking_facility_inherit_query' => true,
        'facility' => 'w',
    ]);

    EntryFacade::shouldReceive('find')->with('w')->andReturn($wing);
    EntryFacade::shouldReceive('find')->with('b')->andReturn($building);
    EntryFacade::shouldReceive('find')->with('pe')->andReturn($entity);

    expect((new FacilityInheritedParkingService)->resolveParkingFacility($location)?->id())->toBe('pe');
});

it('terminates on a reference cycle in the parking chain', function () {
    $a = makeParkingEntry('a', 'location', [
        'facility_query' => true,
        'parking_facility_inherit_query' => true,
        'facility' => 'b',
    ]);
    $b = makeParkingEntry('b', 'location', [
        'facility_query' => true,
        'parking_facility_inherit_query' => true,
        'facility' => 'a',
    ]);

    EntryFacade::shouldReceive('find')->with('a')->andReturn($a);
    EntryFacade::shouldReceive('find')->with('b')->andReturn($b);

    expect((new FacilityInheritedParkingService)->resolveParkingFacility($a))->toBeNull();
});

it('returns null parking values when no parking is resolvable', function () {
    $location = makeParkingEntry('loc', 'location', [
        'facility_query' => false,
        'parking_query' => false,
        'parking_instructions_inherit_query' => true,
        'directions_from_parking_inherit_query' => true,
    ]);

    $service = new FacilityInheritedParkingService;

    expect($service->resolveParkingFacility($location))->toBeNull()
        ->and($service->resolveParkingInstructions($location))->toBeNull()
        ->and($service->resolveDirectionsFromParking($location))->toBeNull()
        ->and($service->resolveParkingFree($location))->toBeNull()
        ->and($service->resolveParkingValidated($location))->toBeNull();
});

it('returns null parking facility for a building with parking turned off', function () {
    $building = makeParkingEntry('b', 'building', ['parking_query' => false]);

    expect((new FacilityInheritedParkingService)->resolveParkingFacility($building))->toBeNull();
});

it('returns null free flag when the resolved entity value is not boolean', function () {
    $entity = makeParkingEntry('pe', 'parking_facility', ['parking_free_query' => 'yes']);
    $location = makeParkingEntry('loc', 'location', [
        'facility_query' => false,
        'parking_query' => true,
        'parking_facility' => 'pe',
    ]);

    EntryFacade::shouldReceive('find')->with('pe')->andReturn($entity);

    expect((new FacilityInheritedParkingService)->resolveParkingFree($location))->toBeNull();
});

it('returns null parking facility for an entry with an unkeyable id', function () {
    // A non-scalar id cannot be tracked for cycles, so the walk stops immediately.
    $blueprint = Mockery::mock(Blueprint::class);
    $blueprint->shouldReceive('handle')->andReturn('building');
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('id')->andReturn([]);
    $entry->shouldReceive('blueprint')->andReturn($blueprint);
    $entry->shouldReceive('parent')->andReturn(null);

    expect((new FacilityInheritedParkingService)->resolveParkingFacility($entry))->toBeNull();
});

it('returns null parking facility for an entry with no blueprint', function () {
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('id')->andReturn('x');
    $entry->shouldReceive('blueprint')->andReturnNull();
    $entry->shouldReceive('parent')->andReturn(null);

    expect((new FacilityInheritedParkingService)->resolveParkingFacility($entry))->toBeNull();
});

it('returns null when an inheriting wing has no structural parent', function () {
    $wing = makeParkingEntry('w', 'building_division', ['parking_facility_inherit_query' => true], null);

    expect((new FacilityInheritedParkingService)->resolveParkingFacility($wing))->toBeNull();
});

it('returns null when an inheriting wing parent page has no id', function () {
    $blankPage = makeParkingPage('');
    $wing = makeParkingEntry('w', 'building_division', ['parking_facility_inherit_query' => true], $blankPage);

    // An empty parent id must stop before any lookup is attempted.
    EntryFacade::shouldReceive('find')->never();

    expect((new FacilityInheritedParkingService)->resolveParkingFacility($wing))->toBeNull();
});

it('returns null when a contained location facility reference is unresolvable', function () {
    $location = makeParkingEntry('loc', 'location', [
        'facility_query' => true,
        'parking_facility_inherit_query' => true,
        'facility' => 'gone',
    ]);

    EntryFacade::shouldReceive('find')->with('gone')->andReturnNull();

    expect((new FacilityInheritedParkingService)->resolveParkingFacility($location))->toBeNull();
});

it('returns null for a building with an empty parking facility reference', function () {
    $building = makeParkingEntry('b', 'building', [
        'parking_query' => true,
        'parking_facility' => '',
    ]);

    // An empty reference must stop before any lookup is attempted.
    EntryFacade::shouldReceive('find')->never();

    expect((new FacilityInheritedParkingService)->resolveParkingFacility($building))->toBeNull();
});

it('resolves a nested child location parking from its parent location', function () {
    // clinical_locations is a structured collection; a child location stores no
    // parking of its own and must inherit the whole chain from its parent location.
    $entity = makeParkingEntry('pe', 'parking_facility', ['parking_instructions' => 'Parent lot']);
    $parent = makeParkingEntry('ploc', 'location', [
        'facility_query' => false,
        'parking_query' => true,
        'parking_facility' => 'pe',
        'parking_instructions_inherit_query' => true,
    ]);
    $parentPage = makeParkingPage('ploc');
    $child = makeParkingEntry('cloc', 'location', [
        'facility_query' => null,
        'parking_query' => null,
        // A nested child stores no parking fields of its own.
        'parking_instructions_inherit_query' => null,
    ], $parentPage);

    EntryFacade::shouldReceive('find')->with('ploc')->andReturn($parent);
    EntryFacade::shouldReceive('find')->with('pe')->andReturn($entity);

    $service = new FacilityInheritedParkingService;

    expect($service->resolveParkingFacility($child)?->id())->toBe('pe')
        ->and($service->resolveParkingInstructions($child))->toBe('Parent lot');
});

it('returns null for a wing whose structural parent is a building_complex', function () {
    // building_complex carries no parking fields, so a wing parented to one inherits nothing.
    $complex = makeParkingEntry('cx', 'building_complex', []);
    $complexPage = makeParkingPage('cx');
    $wing = makeParkingEntry('w', 'building_division', ['parking_facility_inherit_query' => true], $complexPage);

    EntryFacade::shouldReceive('find')->with('cx')->andReturn($complex);

    expect((new FacilityInheritedParkingService)->resolveParkingFacility($wing))->toBeNull();
});

it('returns a contained location own directions when it overrides them', function () {
    $location = makeParkingEntry('loc', 'location', [
        'directions_from_parking_inherit_query' => false,
        'directions_from_parking' => 'Use the east stairwell',
    ]);

    expect((new FacilityInheritedParkingService)->resolveDirectionsFromParking($location))
        ->toBe('Use the east stairwell');
});

it('inherits instructions from the entity when the toggle is null, not overriding', function () {
    // A default/untouched toggle persists as null; null must inherit, not override.
    $entity = makeParkingEntry('pe', 'parking_facility', ['parking_instructions' => 'Inherited deck']);
    $location = makeParkingEntry('loc', 'location', [
        'facility_query' => false,
        'parking_query' => true,
        'parking_facility' => 'pe',
        'parking_instructions_inherit_query' => null,
    ]);

    EntryFacade::shouldReceive('find')->with('pe')->andReturn($entity);

    expect((new FacilityInheritedParkingService)->resolveParkingInstructions($location))
        ->toBe('Inherited deck');
});

it('returns a contained location own parking facility when it overrides inheritance', function () {
    $entity = makeParkingEntry('peOwn', 'parking_division', []);
    $location = makeParkingEntry('loc', 'location', [
        'facility_query' => true,
        'parking_facility_inherit_query' => false,
        'parking_facility' => 'peOwn',
    ]);

    EntryFacade::shouldReceive('find')->with('peOwn')->andReturn($entity);

    expect((new FacilityInheritedParkingService)->resolveParkingFacility($location)?->id())->toBe('peOwn');
});

it('resolves a contained location whose facility is an overriding wing to the wing own entity', function () {
    $entity = makeParkingEntry('peW', 'parking_division', []);
    $wing = makeParkingEntry('w', 'building_division', [
        'parking_facility_inherit_query' => false,
        'parking_facility' => 'peW',
    ]);
    $location = makeParkingEntry('loc', 'location', [
        'facility_query' => true,
        'parking_facility_inherit_query' => true,
        'facility' => 'w',
    ]);

    EntryFacade::shouldReceive('find')->with('w')->andReturn($wing);
    EntryFacade::shouldReceive('find')->with('peW')->andReturn($entity);

    expect((new FacilityInheritedParkingService)->resolveParkingFacility($location)?->id())->toBe('peW');
});

it('resolves a wing nested under another wing up to the building parking', function () {
    $entity = makeParkingEntry('pe', 'parking_facility', []);
    $building = makeParkingEntry('b', 'building', [
        'parking_query' => true,
        'parking_facility' => 'pe',
    ]);
    $buildingPage = makeParkingPage('b');
    $parentWing = makeParkingEntry('pw', 'building_division', ['parking_facility_inherit_query' => true], $buildingPage);
    $parentWingPage = makeParkingPage('pw');
    $childWing = makeParkingEntry('cw', 'building_division', ['parking_facility_inherit_query' => true], $parentWingPage);

    EntryFacade::shouldReceive('find')->with('pw')->andReturn($parentWing);
    EntryFacade::shouldReceive('find')->with('b')->andReturn($building);
    EntryFacade::shouldReceive('find')->with('pe')->andReturn($entity);

    expect((new FacilityInheritedParkingService)->resolveParkingFacility($childWing)?->id())->toBe('pe');
});

it('resolves the parking facility name from the effective facility title (#3172)', function () {
    $entity = makeParkingEntry('pe', 'parking_facility', ['title' => '3805 McCain Park']);
    $location = makeParkingEntry('loc', 'location', [
        'facility_query' => false,
        'parking_query' => true,
        'parking_facility' => 'pe',
    ]);

    EntryFacade::shouldReceive('find')->with('pe')->andReturn($entity);

    expect((new FacilityInheritedParkingService)->resolveParkingFacilityName($location))
        ->toBe('3805 McCain Park');
});

it('returns a null parking facility name when the facility title is blank or not a string (#3172)', function (mixed $title) {
    $entity = makeParkingEntry('pe', 'parking_facility', ['title' => $title]);
    $location = makeParkingEntry('loc', 'location', [
        'facility_query' => false,
        'parking_query' => true,
        'parking_facility' => 'pe',
    ]);

    EntryFacade::shouldReceive('find')->with('pe')->andReturn($entity);

    expect((new FacilityInheritedParkingService)->resolveParkingFacilityName($location))->toBeNull();
})->with([
    'empty' => [''],
    'whitespace' => ['   '],
    'null' => [null],
    'array' => [['3805 McCain Park']],
]);

it('returns a null parking facility name when no parking facility resolves (#3172)', function () {
    $location = makeParkingEntry('loc', 'location', [
        'facility_query' => false,
        'parking_query' => false,
    ]);

    expect((new FacilityInheritedParkingService)->resolveParkingFacilityName($location))->toBeNull();
});

/**
 * Build a parking-consumer or parking-entity entry mock.
 *
 * @param  array<string, mixed>  $gets  Stored values keyed by field handle
 */
function makeParkingEntry(
    string $id,
    string $blueprintHandle,
    array $gets,
    ?object $parentPage = null
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
function makeParkingPage(string $id): MockInterface
{
    $page = Mockery::mock(Page::class);
    $page->shouldReceive('id')->andReturn($id);

    return $page;
}
