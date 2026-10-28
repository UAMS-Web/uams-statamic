<?php

declare(strict_types=1);

use App\Services\ClinicalExpertise\ClinicalExpertiseComputedFieldsService;
use App\Services\ComputedFields\Registration\ClinicalExpertiseCollectionComputedsRegistrar;
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
 * @covers \App\Services\ComputedFields\Registration\ClinicalExpertiseCollectionComputedsRegistrar
 *
 * @command php artisan test --compact tests/Feature/Services/ComputedFields/Registration/ClinicalExpertiseCollectionComputedsRegistrarTest.php
 */
it('registers the three AoE computeds on clinical_expertise, each delegating to its service method', function (): void {
    $entry = Mockery::mock(Entry::class);
    $cta = ['heading' => 'Book a visit'];
    $nav = [['title' => 'Overview', 'url' => '/x']];

    $service = Mockery::mock(ClinicalExpertiseComputedFieldsService::class);
    $service->shouldReceive('isWithinExpertiseSubsite')->once()->with($entry)->andReturnTrue();
    $service->shouldReceive('resolveSectionNav')->once()->with($entry)->andReturn($nav);
    $service->shouldReceive('resolveEffectiveCta')->once()->with($entry)->andReturn($cta);

    $captured = [];
    Collection::shouldReceive('computed')->andReturnUsing(
        function (mixed $collection, string $handle, callable $callback) use (&$captured): void {
            $captured[$handle] = [$collection, $callback];
        }
    );

    (new ClinicalExpertiseCollectionComputedsRegistrar($service))->register();

    expect(array_keys($captured))->toBe(['is_within_aoe', 'aoe_section_nav', 'aoe_effective_cta'])
        ->and(array_column($captured, 0))->toBe(['clinical_expertise', 'clinical_expertise', 'clinical_expertise'])
        ->and(($captured['is_within_aoe'][1])($entry, null))->toBeTrue()
        ->and(($captured['aoe_section_nav'][1])($entry, null))->toBe($nav)
        ->and(($captured['aoe_effective_cta'][1])($entry, null))->toBe($cta);
});
