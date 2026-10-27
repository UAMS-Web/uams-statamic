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

covers(ContentUsageComputedsRegistrar::class);

/**
 * Pad Pest's mutant-child timeout once per process under ``--mutate``.
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
 * @covers \App\Services\ComputedFields\Registration\ContentUsageComputedsRegistrar
 *
 * @command  php artisan test --compact tests/Unit/Services/ComputedFields/Registration/ContentUsageComputedsRegistrarMutationTest.php
 */
it('registers both usage computeds on the three source collections', function (): void {
    $captured = [];
    $callCount = 0;
    Collection::shouldReceive('computed')->andReturnUsing(
        function (mixed $collections, string $handle) use (&$captured, &$callCount): void {
            $callCount++;
            $captured[$handle] = $collections;
        }
    );

    app(ContentUsageComputedsRegistrar::class)->register();

    expect($callCount)->toBe(2)
        ->and(array_keys($captured))->toEqualCanonicalizing(['used_by', 'in_use']);

    foreach (array_keys($captured) as $handle) {
        expect($captured[$handle])->toBe([
            'reusable_content',
            'tables',
            'calculators',
        ]);
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
        function (mixed $collections, string $handle, callable $callback) use (&$captured): void {
            $captured[$handle] = $callback;
        }
    );

    (new ContentUsageComputedsRegistrar($values))->register();

    expect(($captured['used_by'])($entry))->toBe($used)
        ->and(($captured['in_use'])($entry))->toBeTrue();
});
