<?php

declare(strict_types=1);

use App\Services\ComputedFields\Registration\OrganizationTitleComputedsRegistrar;
use App\Services\Organizations\OrganizationPublicLabelService;
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
 * @covers \App\Services\ComputedFields\Registration\OrganizationTitleComputedsRegistrar
 *
 * @command php artisan test --compact tests/Feature/Services/ComputedFields/Registration/OrganizationTitleComputedsRegistrarTest.php
 */
it('registers the resolved public label and the composed title on organizations', function (): void {
    $values = [
        'inherit_public_label_query' => false,
        'public_label' => 'Bridge',
        'title' => 'USS Enterprise',
        'workday_supervisory_organization_name' => 'Starfleet',
        'workday_supervisory_organization_id' => 'NCC-1701',
    ];

    $organization = Mockery::mock(Entry::class);
    $organization->shouldReceive('value')->andReturnUsing(fn (string $handle): mixed => $values[$handle] ?? null);

    $service = app(OrganizationPublicLabelService::class);

    $captured = [];
    Collection::shouldReceive('computed')->andReturnUsing(
        function (mixed $collection, string $handle, callable $callback) use (&$captured): void {
            $captured[$handle] = [$collection, $callback];
        }
    );

    (new OrganizationTitleComputedsRegistrar($service))->register();

    expect(array_keys($captured))->toBe(['resolved_public_label', 'title'])
        ->and(array_column($captured, 0))->toBe(['organizations', 'organizations'])
        ->and(($captured['resolved_public_label'][1])($organization, 'stored'))->toBe('Bridge')
        ->and($service->resolvePublicLabel($organization))->toBe('Bridge')
        ->and(($captured['title'][1])($organization, 'stored'))->toBe('Starfleet (NCC-1701) — Bridge')
        ->and($service->composeTitle($organization))->toBe('Starfleet (NCC-1701) — Bridge');
});
