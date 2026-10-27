<?php

declare(strict_types=1);

use App\Services\ComputedFields\Ontology\ImpliedOntologyComputedValues;
use App\Services\ComputedFields\Registration\ImpliedOntologyCollectionComputedsRegistrar;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Entries\EntryCollection;
use Statamic\Facades\Collection;
use Tests\TestCase;

covers(ImpliedOntologyCollectionComputedsRegistrar::class);

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * The computed handles the registrar owns, in registration order.
 *
 * @var list<string>
 */
const IMPLIED_ONTOLOGY_HANDLES = [
    'implied_services',
    'implied_conditions',
    'transitive_services',
    'transitive_conditions',
    'has_special_interest',
    'has_special_interest_services',
];

/**
 * @covers \App\Services\ComputedFields\Registration\ImpliedOntologyCollectionComputedsRegistrar
 *
 * @command  php artisan test --compact tests/Feature/Services/ComputedFields/Registration/ImpliedOntologyCollectionComputedsRegistrarTest.php
 */
it('registers all implied-ontology computeds exactly once on people and clinical_locations', function (): void {
    $captured = [];
    $callCount = 0;
    Collection::shouldReceive('computed')->andReturnUsing(
        function (mixed $collections, string $handle) use (&$captured, &$callCount): void {
            $callCount++;
            $captured[$handle] = $collections;
        }
    );

    app(ImpliedOntologyCollectionComputedsRegistrar::class)->register();

    // Exactly one registration per handle — a duplicate would collapse in the keyed
    // map, so pin the raw call count too.
    expect($callCount)->toBe(count(IMPLIED_ONTOLOGY_HANDLES))
        ->and(array_keys($captured))->toEqualCanonicalizing(IMPLIED_ONTOLOGY_HANDLES);

    // Each computed targets exactly the Provider and Location collections.
    foreach (IMPLIED_ONTOLOGY_HANDLES as $handle) {
        expect($captured[$handle])->toBe(['people', 'clinical_locations']);
    }
});

it('wires each computed handle to its owning resolver method', function (): void {
    // Distinct return instances catch a swapped binding (implied ↔ transitive, services ↔ conditions).
    $impliedServices = EntryCollection::make();
    $impliedConditions = EntryCollection::make();
    $transitiveServices = EntryCollection::make();
    $transitiveConditions = EntryCollection::make();

    $resolver = Mockery::mock(ImpliedOntologyComputedValues::class);
    $entry = Mockery::mock(Entry::class);
    $resolver->shouldReceive('resolveImpliedServices')->with($entry)->andReturn($impliedServices);
    $resolver->shouldReceive('resolveImpliedConditions')->with($entry)->andReturn($impliedConditions);
    $resolver->shouldReceive('resolveTransitiveServices')->with($entry)->andReturn($transitiveServices);
    $resolver->shouldReceive('resolveTransitiveConditions')->with($entry)->andReturn($transitiveConditions);
    $resolver->shouldReceive('resolveHasSpecialInterest')->with($entry)->andReturn(true);
    $resolver->shouldReceive('resolveHasSpecialInterestServices')->with($entry)->andReturn(false);

    $captured = [];
    Collection::shouldReceive('computed')->andReturnUsing(
        function (mixed $collections, string $handle, callable $callback) use (&$captured): void {
            $captured[$handle] = $callback;
        }
    );

    (new ImpliedOntologyCollectionComputedsRegistrar($resolver))->register();

    expect(($captured['implied_services'])($entry))->toBe($impliedServices)
        ->and(($captured['implied_conditions'])($entry))->toBe($impliedConditions)
        ->and(($captured['transitive_services'])($entry))->toBe($transitiveServices)
        ->and(($captured['transitive_conditions'])($entry))->toBe($transitiveConditions)
        ->and(($captured['has_special_interest'])($entry))->toBeTrue()
        ->and(($captured['has_special_interest_services'])($entry))->toBeFalse();
});
