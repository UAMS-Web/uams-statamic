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

covers(SpecialInterestInverseComputedsRegistrar::class);

/**
 * Pad Pest's mutant-child timeout once per process when this file is the
 * ``--mutate`` suite (TestCase boot alone is ~9s; without padding, covering
 * re-runs tip over into timeout kills).
 */
beforeEach(function (): void {
    static $padded = false;
    if ($padded) {
        return;
    }

    if (runningUnderMutate()) {
        usleep(12_000_000);
        $padded = true;
    }
});

/**
 * @covers \App\Services\ComputedFields\Registration\SpecialInterestInverseComputedsRegistrar
 *
 * @command  php artisan test --compact tests/Unit/Services/ComputedFields/Registration/SpecialInterestInverseComputedsRegistrarMutationTest.php
 */
it('registers both inverse computeds on clinical_conditions and clinical_services', function (): void {
    $captured = [];
    $callCount = 0;
    Collection::shouldReceive('computed')->andReturnUsing(
        function (mixed $collections, string $handle) use (&$captured, &$callCount): void {
            $callCount++;
            $captured[$handle] = $collections;
        }
    );

    app(SpecialInterestInverseComputedsRegistrar::class)->register();

    expect($callCount)->toBe(2)
        ->and(array_keys($captured))->toEqualCanonicalizing([
            'special_interest_providers',
            'special_interest_locations',
        ]);

    foreach (array_keys($captured) as $handle) {
        expect($captured[$handle])->toBe(['clinical_conditions', 'clinical_services']);
    }
});

it('wires each computed handle to its owning resolver method', function (): void {
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
