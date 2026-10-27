<?php

declare(strict_types=1);

use App\Scopes\ReusableContentLandingPageOnly;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Query\Builder;

uses(MockeryPHPUnitIntegration::class);

covers(ReusableContentLandingPageOnly::class);

/**
 * Mutation: 100% on App\Scopes\ReusableContentLandingPageOnly (1 mutants, --covered-only).
 *
 * @command  php artisan test --compact tests/Unit/Scopes/ReusableContentLandingPageOnlyScopeTest.php
 */
it('applies filters for the reusable content landing page', function () {
    $scope = new ReusableContentLandingPageOnly;
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
            'reusable_content_landing_page'
        )
        ->andReturnSelf();

    $scope->apply(
        $query,
        []
    );
});
