<?php

declare(strict_types=1);

use App\Scopes\RecognitionListMembership;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Query\Builder;

uses(MockeryPHPUnitIntegration::class);

/**
 * @covers \App\Scopes\RecognitionListMembership
 *
 * The recognition-list filter behind the UAMS-Web/uams-statamic-archive#1176 provider-block control. Membership
 * is a top-level `recognition_lists` entries relationship (UAMS-Web/uams-statamic-archive#1546), so the scope is
 * a `whereJsonOverlaps` — but only when a list/category is selected. This locks in
 * the pass-through: a block with the filter left blank must NOT constrain the
 * listing (an empty needle would otherwise match nothing).
 *
 * The list→descendant expansion and the real overlap are exercised against real
 * content in RecognitionListFilterTest (they query the recognition_lists structure).
 *
 * @command  php artisan test --compact tests/Unit/Scopes/RecognitionListMembershipScopeTest.php
 */

/**
 * Apply the recognition-list scope and return the builder apply() hands back, so
 * the caller can assert the pass-through return contract on the blank-filter path
 * (apply() returns the builder unchanged when no list is selected).
 *
 * @param  array<string, mixed>  $values
 */
function recMembershipApplyScope(RecognitionListMembership $scope, Builder $query, array $values): Builder
{
    return $scope->apply($query, $values);
}

it('leaves the query unfiltered when the recognition filter is a blank string', function () {
    $query = Mockery::mock(Builder::class);
    // A blank filter must never add a `whereJsonOverlaps` — the block renders as-is.
    $query->shouldReceive('whereJsonOverlaps')->never();

    $result = recMembershipApplyScope(new RecognitionListMembership, $query, ['recognition_lists' => '']);

    expect($result)->toBe($query);
});

it('leaves the query unfiltered when the recognition parameter is absent', function () {
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereJsonOverlaps')->never();

    $result = recMembershipApplyScope(new RecognitionListMembership, $query, []);

    expect($result)->toBe($query);
});

it('leaves the query unfiltered when the recognition filter is neither a list nor text', function (mixed $filter) {
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereJsonOverlaps')->never();

    $result = recMembershipApplyScope(new RecognitionListMembership, $query, ['recognition_lists' => $filter]);

    expect($result)->toBe($query);
})->with([
    'a number' => [42],
    'an object' => [new stdClass],
    'true' => [true],
]);
