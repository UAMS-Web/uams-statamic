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
 * Mutation: 100% on App\Scopes\ProfessionalAndLeadershipDevelopmentActivitiesTermType (34 mutants),
 * measured on macOS 26.5 (arm64) on 2026-11-26 with PHP 8.4.28 and Herd's Xdebug 3.5.3, without --covered-only, over this
 * file and ProfessionalAndLeadershipDevelopmentActivitiesTermRuleTest (UAMS-Web/uams-statamic#842).
 * No mutant is annotated. The check that skipped an empty handle was removed as dead code:
 * an empty handle matches neither known handle, which the dataset test pins.
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

it('passes over handles that are not text, or are empty, to the first known one', function (array $queryScopes, string $type): void {
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
            $type
        )
        ->andReturnSelf();

    $scope->apply(
        $query,
        [
            'queryScopes' => $queryScopes,
        ]
    );
})->with([
    'a number, then the professional handle' => [[7, 'professional_development_activity_term'], ActivitiesScope::TYPE_VALUE_PROFESSIONAL_DEVELOPMENT],
    'an empty handle, then the leadership alias' => [['', ActivitiesScope::ALIAS_LEADERSHIP_DEVELOPMENT_ACTIVITY_TERM], ActivitiesScope::TYPE_VALUE_LEADERSHIP_DEVELOPMENT],
]);
