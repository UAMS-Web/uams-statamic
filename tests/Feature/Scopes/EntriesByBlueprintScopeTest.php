<?php

declare(strict_types=1);

use App\Scopes\EntriesByBlueprint;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Query\Builder;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * @covers \App\Scopes\EntriesByBlueprint
 *
 * Mutation: 100% on App\Scopes\EntriesByBlueprint (27 mutants, --covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Scopes/EntriesByBlueprintScopeTest.php
 */
it('skips the filter when no recognized handle', function () {
    $scope = new EntriesByBlueprint;
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereIn')->never();

    $scope->apply(
        $query,
        [
            'queryScopes' => [
                'other_scope',
            ],
        ]
    );
});

it('filters by a single blueprint', function () {
    $scope = new EntriesByBlueprint;
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereIn')
        ->once()
        ->with('blueprint', ['parking_facility'])
        ->andReturnSelf();

    $scope->apply(
        $query,
        [
            'queryScopes' => [
                'entries_by_blueprint_parking_facility',
            ],
        ]
    );
});

it('filters to the clinical_expertise blueprint (related-AoE picker)', function () {
    $scope = new EntriesByBlueprint;
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereIn')
        ->once()
        ->with('blueprint', ['clinical_expertise'])
        ->andReturnSelf();

    $scope->apply(
        $query,
        [
            'queryScopes' => [
                'entries_by_blueprint_clinical_expertise',
            ],
        ]
    );
});

it('unions multiple blueprints', function () {
    $scope = new EntriesByBlueprint;
    $query = Mockery::mock(Builder::class);
    // The location `building` picker allows Building Complex + Building.
    $query->shouldReceive('whereIn')
        ->once()
        ->with('blueprint', ['building_complex', 'building'])
        ->andReturnSelf();

    $scope->apply(
        $query,
        [
            'queryScopes' => [
                'entries_by_blueprint_building_complex',
                'entries_by_blueprint_building',
            ],
        ]
    );
});

it('ignores unknown blueprint suffixes', function () {
    $scope = new EntriesByBlueprint;
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereIn')->never();

    $scope->apply(
        $query,
        [
            'queryScopes' => [
                'entries_by_blueprint_nonexistent',
            ],
        ]
    );
});

it('derives exactly the opt-in collections blueprints, excluding content pages', function () {
    // The recognized set is derived from the `facilities` + `clinical_expertise`
    // blueprints on disk (minus the shared content pages), not a literal — so a
    // regression that let `basic_page` / `landing_page` through would fail here.
    expect(EntriesByBlueprint::recognizedBlueprints())->toEqualCanonicalizing([
        'building_complex',
        'building',
        'building_division',
        'parking_facility',
        'parking_division',
        'clinical_expertise',
    ]);
});

it('registers one alias per recognized blueprint and never a content page', function () {
    $aliases = EntriesByBlueprint::aliases();

    foreach (EntriesByBlueprint::recognizedBlueprints() as $blueprint) {
        expect($aliases)->toContain('entries_by_blueprint_'.$blueprint);
    }

    expect($aliases)->toHaveCount(\count(EntriesByBlueprint::recognizedBlueprints()))
        ->and($aliases)->not->toContain('entries_by_blueprint_basic_page')
        ->and($aliases)->not->toContain('entries_by_blueprint_landing_page');
});
