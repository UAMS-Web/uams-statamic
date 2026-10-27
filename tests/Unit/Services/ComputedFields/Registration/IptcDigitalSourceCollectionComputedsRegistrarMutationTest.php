<?php

declare(strict_types=1);

use App\Services\ComputedFields\Registration\IptcDigitalSourceCollectionComputedsRegistrar;
use App\Services\IptcDigitalSourceValueResolver;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Facades\Collection;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(IptcDigitalSourceCollectionComputedsRegistrar::class);

/**
 * Narrow mutate coverage for IptcDigitalSourceCollectionComputedsRegistrar.
 *
 * @command  php artisan test --compact tests/Unit/Services/ComputedFields/Registration/IptcDigitalSourceCollectionComputedsRegistrarMutationTest.php
 */

/**
 * Pad Pest's mutant-child timeout once per process under ``--mutate``.
 */
beforeEach(function (): void {
    static $padded = false;
    if ($padded) {
        return;
    }

    if (runningUnderMutate()) {
        usleep(20_000_000);
        $padded = true;
    }
});

it('registers value and schema computeds for every collection and group', function (): void {
    $collections = [
        'clinical_expertise',
        'clinical_locations',
        'clinical_providers',
        'clinical_resources',
        'knowledge_bases',
        'pages',
        'people',
        'posts',
    ];
    $groups = IptcDigitalSourceValueResolver::digitalSourceTypeGroupHandles();

    $captured = [];
    Collection::shouldReceive('computed')->andReturnUsing(
        function (string $collection, string $handle, callable $callback) use (&$captured): void {
            $captured["{$collection}|{$handle}"] = $callback;
        }
    );

    (new IptcDigitalSourceCollectionComputedsRegistrar)->register();

    $expectedCount = count($collections) * count($groups) * 2;
    expect(count($captured))->toBe($expectedCount);

    foreach ($collections as $collection) {
        foreach ($groups as $group) {
            $keys = IptcDigitalSourceValueResolver::computedKeysForGroupHandle($group);
            expect($captured)->toHaveKey("{$collection}|{$keys['value']}")
                ->and($captured)->toHaveKey("{$collection}|{$keys['schema']}");
        }
    }
});

it('wires value and schema callbacks through the resolver', function (): void {
    $captured = [];
    Collection::shouldReceive('computed')->andReturnUsing(
        function (string $collection, string $handle, callable $callback) use (&$captured): void {
            if ($collection === 'pages') {
                $captured[$handle] = $callback;
            }
        }
    );

    (new IptcDigitalSourceCollectionComputedsRegistrar)->register();

    $payload = [
        'is_composite' => false,
        'origin_type' => 'algorithmic',
        'has_gen_ai_edits' => false,
        'enhancement_type' => 'none',
    ];

    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('get')
        ->with('digital_source_type')
        ->twice()
        ->andReturn($payload);

    expect(($captured['digital_source_type_value'])($entry, null))
        ->toBe('trainedAlgorithmicMedia')
        ->and(($captured['digital_source_type_value_schema'])($entry, null))
        ->toBe('https://schema.org/TrainedAlgorithmicMediaDigitalSource');
});
