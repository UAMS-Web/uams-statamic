<?php

declare(strict_types=1);

use App\Services\ComputedFields\Registration\SubsiteContentPageHubComputedsRegistrar;
use App\Services\SchemaTemplates\SubsiteHubResolver;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Facades\Collection;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

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

/**
 * @covers \App\Services\ComputedFields\Registration\SubsiteContentPageHubComputedsRegistrar
 *
 * @command php artisan test --compact tests/Feature/Services/ComputedFields/Registration/SubsiteContentPageHubComputedsRegistrarTest.php
 */
it('registers schema_subsite_hub on both subsite collections, resolved from the entry', function (): void {
    $expertise = Mockery::mock(Entry::class);
    $location = Mockery::mock(Entry::class);

    $resolver = Mockery::mock(SubsiteHubResolver::class);
    $resolver->shouldReceive('resolveIdList')->once()->with($expertise)->andReturn(['hub-expertise']);
    $resolver->shouldReceive('resolveIdList')->once()->with($location)->andReturnNull();

    $captured = [];
    Collection::shouldReceive('computed')->andReturnUsing(
        function (mixed $collection, string $handle, callable $callback) use (&$captured): void {
            $captured[] = [$collection, $handle, $callback];
        }
    );

    (new SubsiteContentPageHubComputedsRegistrar($resolver))->register();

    expect(array_column($captured, 0))->toBe(['clinical_expertise', 'clinical_locations'])
        ->and(array_column($captured, 1))->toBe(['schema_subsite_hub', 'schema_subsite_hub'])
        ->and(($captured[0][2])($expertise, 'stored'))->toBe(['hub-expertise'])
        ->and(($captured[1][2])($location, 'stored'))->toBeNull();
});
