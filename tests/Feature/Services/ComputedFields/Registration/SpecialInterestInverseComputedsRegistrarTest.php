<?php

declare(strict_types=1);

use App\Services\ComputedFields\Ontology\SpecialInterestInverseComputedValues;
use App\Services\ComputedFields\Registration\SpecialInterestInverseComputedsRegistrar;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Entries\EntryCollection;
use Statamic\Facades\Collection;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * The computed handles the registrar owns, in registration order.
 *
 * @var list<string>
 */
const SPECIAL_INTEREST_INVERSE_HANDLES = [
    'special_interest_providers',
    'special_interest_locations',
];

/**
 * @covers \App\Services\ComputedFields\Registration\SpecialInterestInverseComputedsRegistrar
 *
 * @command php artisan test --compact tests/Feature/Services/ComputedFields/Registration/SpecialInterestInverseComputedsRegistrarTest.php
 */
it('registers both inverse computeds exactly once on clinical_conditions and clinical_services', function (): void {
    $captured = [];
    $callCount = 0;
    Collection::shouldReceive('computed')->andReturnUsing(
        function (mixed $collections, string $handle) use (&$captured, &$callCount): void {
            $callCount++;
            $captured[$handle] = $collections;
        }
    );

    app(SpecialInterestInverseComputedsRegistrar::class)->register();

    // Exactly one registration per handle — a duplicate would collapse in the keyed
    // map, so pin the raw call count too.
    expect($callCount)->toBe(count(SPECIAL_INTEREST_INVERSE_HANDLES))
        ->and(array_keys($captured))->toEqualCanonicalizing(SPECIAL_INTEREST_INVERSE_HANDLES);

    // Each computed targets exactly the Condition and Service collections.
    foreach (SPECIAL_INTEREST_INVERSE_HANDLES as $handle) {
        expect($captured[$handle])->toBe(['clinical_conditions', 'clinical_services']);
    }
});

it('wires each computed handle to its owning resolver method', function (): void {
    // Distinct return instances catch a swapped binding (providers ↔ locations).
    $providers = EntryCollection::make();
    $locations = EntryCollection::make();

    $resolver = Mockery::mock(SpecialInterestInverseComputedValues::class);
    $entry = Mockery::mock(Entry::class);
    $resolver->shouldReceive('resolveSpecialInterestProviders')->with($entry)->andReturn($providers);
    $resolver->shouldReceive('resolveSpecialInterestLocations')->with($entry)->andReturn($locations);

    $captured = [];
    Collection::shouldReceive('computed')->andReturnUsing(
        function (mixed $collections, string $handle, callable $callback) use (&$captured): void {
            $captured[$handle] = $callback;
        }
    );

    (new SpecialInterestInverseComputedsRegistrar($resolver))->register();

    expect(($captured['special_interest_providers'])($entry))->toBe($providers)
        ->and(($captured['special_interest_locations'])($entry))->toBe($locations);
});
