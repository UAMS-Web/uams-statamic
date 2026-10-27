<?php

declare(strict_types=1);

use App\Services\ComputedFields\Registration\ContentUsageComputedsRegistrar;
use App\Services\ComputedFields\Reverse\ContentUsageComputedValues;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Entries\EntryCollection;
use Statamic\Facades\Collection;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * The computed handles the content-usage registrar owns, in registration order.
 *
 * @var list<string>
 */
const CONTENT_USAGE_HANDLES = [
    'used_by',
    'in_use',
];

/**
 * The transcludable source collections the usage computeds are registered on.
 *
 * @var list<string>
 */
const CONTENT_USAGE_COLLECTIONS = [
    'reusable_content',
    'tables',
    'calculators',
];

/**
 * @covers \App\Services\ComputedFields\Registration\ContentUsageComputedsRegistrar
 *
 * @command php artisan test --compact tests/Feature/Services/ComputedFields/Registration/ContentUsageComputedsRegistrarTest.php
 */
it('registers both usage computeds exactly once on all three source collections', function (): void {
    $captured = [];
    $callCount = 0;
    Collection::shouldReceive('computed')->andReturnUsing(
        function (mixed $collection, string $handle) use (&$captured, &$callCount): void {
            $callCount++;
            $captured[$handle] = $collection;
        }
    );

    app(ContentUsageComputedsRegistrar::class)->register();

    expect($callCount)->toBe(count(CONTENT_USAGE_HANDLES))
        ->and(array_keys($captured))->toEqualCanonicalizing(CONTENT_USAGE_HANDLES);

    foreach (CONTENT_USAGE_HANDLES as $handle) {
        expect($captured[$handle])->toBe(CONTENT_USAGE_COLLECTIONS);
    }
});

it('wires used_by and in_use to their owning resolver methods', function (): void {
    $used = EntryCollection::make([Mockery::mock(Entry::class)]);

    $values = Mockery::mock(ContentUsageComputedValues::class);
    $entry = Mockery::mock(Entry::class);
    $values->shouldReceive('resolveUsedBy')->with($entry)->andReturn($used);
    $values->shouldReceive('isInUse')->with($entry)->andReturnTrue();

    $captured = [];
    Collection::shouldReceive('computed')->andReturnUsing(
        function (mixed $collection, string $handle, callable $callback) use (&$captured): void {
            $captured[$handle] = $callback;
        }
    );

    (new ContentUsageComputedsRegistrar($values))->register();

    expect(($captured['used_by'])($entry))->toBe($used)
        ->and(($captured['in_use'])($entry))->toBeTrue();
});
