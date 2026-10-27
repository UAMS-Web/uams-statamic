<?php

declare(strict_types=1);

use App\Scopes\ValidArticleParent;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Query\Builder;

uses(MockeryPHPUnitIntegration::class);

/**
 * @covers \App\Scopes\ValidArticleParent
 *
 * Mutation: 100% on App\Scopes\ValidArticleParent (1 mutants, --covered-only).
 *
 * @command  php artisan test --compact tests/Unit/Scopes/ValidArticleParentScopeTest.php
 */
it('restricts the query to the topic blueprint when applied', function () {
    $scope = new ValidArticleParent;
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('where')
        ->once()
        ->with(
            'blueprint',
            'topic'
        )
        ->andReturnSelf();

    $scope->apply(
        $query,
        []
    );
});
