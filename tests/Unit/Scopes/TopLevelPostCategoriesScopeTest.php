<?php

declare(strict_types=1);

use App\Scopes\TopLevelPostCategories;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Query\Builder;

uses(MockeryPHPUnitIntegration::class);

covers(TopLevelPostCategories::class);

/**
 * The browse-all `/category` index (UAMS-Web/uams-statamic-archive#1435) lists structure roots only; this
 * scope is the "no parent" filter behind it.
 *
 * @command  php artisan test --compact tests/Unit/Scopes/TopLevelPostCategoriesScopeTest.php
 */
it('restricts the query to categories with no parent (structure roots)', function () {
    $scope = new TopLevelPostCategories;
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereNull')
        ->once()
        ->with('parent')
        ->andReturnSelf();

    $scope->apply(
        $query,
        []
    );
});
