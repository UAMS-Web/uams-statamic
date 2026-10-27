<?php

declare(strict_types=1);

use App\Services\ComputedFields\Registration\PeopleReverseReferenceComputedsRegistrar;
use App\Services\ComputedFields\Reverse\PeopleReverseReferenceComputedValues;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Entries\EntryCollection;
use Statamic\Facades\Collection;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(PeopleReverseReferenceComputedsRegistrar::class);

/**
 * The computed handles the people registrar owns, in registration order.
 *
 * @var list<string>
 */
const PEOPLE_REVERSE_HANDLES = [
    'credited_content',
    'clinical_provider_entry',
];

/**
 * @covers \App\Services\ComputedFields\Registration\PeopleReverseReferenceComputedsRegistrar
 *
 * @command php artisan test --compact tests/Feature/Services/ComputedFields/Registration/PeopleReverseReferenceComputedsRegistrarTest.php
 */
it('registers both reverse computeds exactly once on the people collection', function (): void {
    $captured = [];
    $callCount = 0;
    Collection::shouldReceive('computed')->andReturnUsing(
        function (mixed $collection, string $handle) use (&$captured, &$callCount): void {
            $callCount++;
            $captured[$handle] = $collection;
        }
    );

    app(PeopleReverseReferenceComputedsRegistrar::class)->register();

    expect($callCount)->toBe(count(PEOPLE_REVERSE_HANDLES))
        ->and(array_keys($captured))->toEqualCanonicalizing(PEOPLE_REVERSE_HANDLES);

    foreach (PEOPLE_REVERSE_HANDLES as $handle) {
        expect($captured[$handle])->toBe('people');
    }
});

it('wires each computed handle to its owning resolver method', function (): void {
    // Distinct return instances catch a swapped binding (credited ↔ provider).
    $credited = EntryCollection::make();
    $provider = EntryCollection::make();

    $values = Mockery::mock(PeopleReverseReferenceComputedValues::class);
    $entry = Mockery::mock(Entry::class);
    $values->shouldReceive('resolveCreditedContent')->with($entry)->andReturn($credited);
    $values->shouldReceive('resolveClinicalProviderEntry')->with($entry)->andReturn($provider);

    $captured = [];
    Collection::shouldReceive('computed')->andReturnUsing(
        function (mixed $collection, string $handle, callable $callback) use (&$captured): void {
            $captured[$handle] = $callback;
        }
    );

    (new PeopleReverseReferenceComputedsRegistrar($values))->register();

    expect(($captured['credited_content'])($entry))->toBe($credited)
        ->and(($captured['clinical_provider_entry'])($entry))->toBe($provider);
});
