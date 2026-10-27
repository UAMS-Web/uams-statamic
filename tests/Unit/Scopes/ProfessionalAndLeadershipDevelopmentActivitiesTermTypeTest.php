<?php

declare(strict_types=1);

use App\Scopes\ProfessionalAndLeadershipDevelopmentActivitiesTermType as ActivitiesScope;
use Illuminate\Database\Eloquent\Builder;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Stache\Query\TermQueryBuilder;

uses(MockeryPHPUnitIntegration::class);

/**
 * Unit tests for `ProfessionalAndLeadershipDevelopmentActivitiesTermType` query
 * scope aliases and `apply` behavior.
 *
 * Mutation: 100% on App\Scopes\ProfessionalAndLeadershipDevelopmentActivitiesTermType (36 mutants, --covered-only).
 *
 * @command  php artisan test --compact tests/Unit/Scopes/ProfessionalAndLeadershipDevelopmentActivitiesTermTypeTest.php
 */
it('exposes only the leadership-development handle in aliases', function () {
    $aliases = ActivitiesScope::aliases();

    expect($aliases)->toBe([
        ActivitiesScope::ALIAS_LEADERSHIP_DEVELOPMENT_ACTIVITY_TERM,
    ]);
});

it('uses professional_development_activity_term as its handle', function () {
    expect(ActivitiesScope::handle())->toBe('professional_development_activity_term');
});

it('maps both handles to type values via expectationFromScopeAlias', function () {
    expect(ActivitiesScope::expectationFromScopeAlias('professional_development_activity_term'))->toBe([
        'type' => ActivitiesScope::TYPE_VALUE_PROFESSIONAL_DEVELOPMENT,
    ]);

    expect(ActivitiesScope::expectationFromScopeAlias(
        ActivitiesScope::ALIAS_LEADERSHIP_DEVELOPMENT_ACTIVITY_TERM
    ))->toBe([
        'type' => ActivitiesScope::TYPE_VALUE_LEADERSHIP_DEVELOPMENT,
    ]);

    expect(ActivitiesScope::expectationFromScopeAlias('unrelated_scope'))->toBeNull();
});

it('adds taxonomy and type filters for the professional handle', function () {
    $scope = new ActivitiesScope;

    /** @var TermQueryBuilder&MockInterface $query */
    $query = Mockery::mock(TermQueryBuilder::class);
    $query->shouldReceive('whereIn')
        ->once()
        ->with(
            'taxonomies',
            [
                ActivitiesScope::TAXONOMY,
            ]
        )
        ->andReturnSelf();
    $query->shouldReceive('where')
        ->once()
        ->with(
            ActivitiesScope::TYPE_FIELD,
            ActivitiesScope::TYPE_VALUE_PROFESSIONAL_DEVELOPMENT
        )
        ->andReturnSelf();

    $scope->apply(
        $query,
        [
            'queryScopes' => [
                'professional_development_activity_term',
            ],
        ]
    );
});

it('adds taxonomy and type filters for the leadership alias', function () {
    $scope = new ActivitiesScope;

    /** @var TermQueryBuilder&MockInterface $query */
    $query = Mockery::mock(TermQueryBuilder::class);
    $query->shouldReceive('whereIn')
        ->once()
        ->with(
            'taxonomies',
            [
                ActivitiesScope::TAXONOMY,
            ]
        )
        ->andReturnSelf();
    $query->shouldReceive('where')
        ->once()
        ->with(
            ActivitiesScope::TYPE_FIELD,
            ActivitiesScope::TYPE_VALUE_LEADERSHIP_DEVELOPMENT
        )
        ->andReturnSelf();

    $scope->apply(
        $query,
        [
            'queryScopes' => [
                ActivitiesScope::ALIAS_LEADERSHIP_DEVELOPMENT_ACTIVITY_TERM,
            ],
        ]
    );
});

it('uses the first matching handle when both are present', function () {
    $scope = new ActivitiesScope;

    /** @var TermQueryBuilder&MockInterface $query */
    $query = Mockery::mock(TermQueryBuilder::class);
    $query->shouldReceive('whereIn')
        ->once()
        ->andReturnSelf();
    $query->shouldReceive('where')
        ->once()
        ->with(
            ActivitiesScope::TYPE_FIELD,
            ActivitiesScope::TYPE_VALUE_PROFESSIONAL_DEVELOPMENT
        )
        ->andReturnSelf();

    $scope->apply(
        $query,
        [
            'queryScopes' => [
                'professional_development_activity_term',
                ActivitiesScope::ALIAS_LEADERSHIP_DEVELOPMENT_ACTIVITY_TERM,
            ],
        ]
    );
});

it('prefers the first scope when leadership is listed first', function () {
    $scope = new ActivitiesScope;

    /** @var TermQueryBuilder&MockInterface $query */
    $query = Mockery::mock(TermQueryBuilder::class);
    $query->shouldReceive('whereIn')
        ->once()
        ->andReturnSelf();
    $query->shouldReceive('where')
        ->once()
        ->with(
            ActivitiesScope::TYPE_FIELD,
            ActivitiesScope::TYPE_VALUE_LEADERSHIP_DEVELOPMENT
        )
        ->andReturnSelf();

    $scope->apply(
        $query,
        [
            'queryScopes' => [
                ActivitiesScope::ALIAS_LEADERSHIP_DEVELOPMENT_ACTIVITY_TERM,
                'professional_development_activity_term',
            ],
        ]
    );
});

it('does not alter the query when no known scope handle is present', function () {
    $scope = new ActivitiesScope;

    /** @var TermQueryBuilder&MockInterface $query */
    $query = Mockery::mock(TermQueryBuilder::class);
    $query->shouldReceive('whereIn')->never();
    $query->shouldReceive('where')->never();

    $scope->apply(
        $query,
        [
            'queryScopes' => [
                'unrelated_scope',
            ],
        ]
    );
});

it('does not alter a non-statamic builder', function () {
    $scope = new ActivitiesScope;

    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereIn')->never();
    $query->shouldReceive('where')->never();

    $scope->apply(
        $query,
        [
            'queryScopes' => [
                'professional_development_activity_term',
            ],
        ]
    );
});
