<?php

declare(strict_types=1);

use App\Scopes\ReusableContentBasicPageOnly;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Query\Builder;

uses(MockeryPHPUnitIntegration::class);

covers(ReusableContentBasicPageOnly::class);

/**
 * Mutation: 100% on App\Scopes\ReusableContentBasicPageOnly (1 mutants, --covered-only).
 *
 * @command  php artisan test --compact tests/Unit/Scopes/ReusableContentBasicPageOnlyScopeTest.php
 */
it('apply filters reusable content basic page', function () {
    $scope = new ReusableContentBasicPageOnly;
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('where')
        ->once()
        ->with(
            'collection',
            'reusable_content'
        )
        ->andReturnSelf();
    $query->shouldReceive('where')
        ->once()
        ->with(
            'blueprint',
            'reusable_content_basic_page'
        )
        ->andReturnSelf();

    $scope->apply(
        $query,
        []
    );
});
