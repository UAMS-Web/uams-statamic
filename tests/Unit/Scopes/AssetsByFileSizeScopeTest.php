<?php

declare(strict_types=1);

use App\Scopes\AssetsByFileSize;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Assets\QueryBuilder;

uses(MockeryPHPUnitIntegration::class);

/**
 * @covers \App\Scopes\AssetsByFileSize
 *
 * @command  php artisan test --compact tests/Unit/Scopes/AssetsByFileSizeScopeTest.php
 */
it('apply skips when no encoded handles', function () {
    $scope = new AssetsByFileSize;
    /** @var QueryBuilder&MockInterface $query */
    $query = Mockery::mock(QueryBuilder::class);
    $query->shouldReceive('where')->never();

    $scope->apply(
        $query,
        [
            'queryScopes' => [],
        ]
    );
});

it('apply skips when the base handle carries no bound', function () {
    $scope = new AssetsByFileSize;
    /** @var QueryBuilder&MockInterface $query */
    $query = Mockery::mock(QueryBuilder::class);
    $query->shouldReceive('where')->never();

    $scope->apply(
        $query,
        [
            'queryScopes' => [
                'assets_by_file_size',
            ],
        ]
    );
});

it('apply adds only a lower bound for a min handle', function () {
    $scope = new AssetsByFileSize;
    /** @var QueryBuilder&MockInterface $query */
    $query = Mockery::mock(QueryBuilder::class);
    $query->shouldReceive('where')
        ->once()
        ->with('size', '>=', 1_000_000)
        ->andReturnSelf();

    $scope->apply(
        $query,
        [
            'queryScopes' => [
                'assets_by_file_size_min_1000000',
            ],
        ]
    );
});

it('apply adds only an upper bound for a max handle', function () {
    $scope = new AssetsByFileSize;
    /** @var QueryBuilder&MockInterface $query */
    $query = Mockery::mock(QueryBuilder::class);
    $query->shouldReceive('where')
        ->once()
        ->with('size', '<=', 10_000_000)
        ->andReturnSelf();

    $scope->apply(
        $query,
        [
            'queryScopes' => [
                'assets_by_file_size_max_10000000',
            ],
        ]
    );
});

it('apply adds both bounds for a min and max handle', function () {
    $scope = new AssetsByFileSize;
    /** @var QueryBuilder&MockInterface $query */
    $query = Mockery::mock(QueryBuilder::class);
    $query->shouldReceive('where')->once()->with('size', '>=', 1_000_000)->andReturnSelf();
    $query->shouldReceive('where')->once()->with('size', '<=', 10_000_000)->andReturnSelf();

    $scope->apply(
        $query,
        [
            'queryScopes' => [
                'assets_by_file_size_min_1000000',
                'assets_by_file_size_max_10000000',
            ],
        ]
    );
});

it('apply keeps the tightest bound when a bound is encoded twice', function () {
    $scope = new AssetsByFileSize;
    /** @var QueryBuilder&MockInterface $query */
    $query = Mockery::mock(QueryBuilder::class);
    // Largest min and smallest max win, so two fields' scopes never widen each other.
    $query->shouldReceive('where')->once()->with('size', '>=', 2_000_000)->andReturnSelf();
    $query->shouldReceive('where')->once()->with('size', '<=', 5_000_000)->andReturnSelf();

    $scope->apply(
        $query,
        [
            'queryScopes' => [
                'assets_by_file_size_min_1000000',
                'assets_by_file_size_min_2000000',
                'assets_by_file_size_max_10000000',
                'assets_by_file_size_max_5000000',
            ],
        ]
    );
});

it('apply ignores a non-digit byte fragment', function () {
    $scope = new AssetsByFileSize;
    /** @var QueryBuilder&MockInterface $query */
    $query = Mockery::mock(QueryBuilder::class);
    $query->shouldReceive('where')->never();

    $scope->apply(
        $query,
        [
            'queryScopes' => [
                'assets_by_file_size_min_1.5',
                'assets_by_file_size_max_abc',
                'assets_by_file_size_min_',
            ],
        ]
    );
});

it('apply ignores an overflowing byte fragment instead of saturating to PHP_INT_MAX', function () {
    $scope = new AssetsByFileSize;
    /** @var QueryBuilder&MockInterface $query */
    $query = Mockery::mock(QueryBuilder::class);
    // A digit run longer than PHP_INT_MAX must be rejected (no bound), not cast to
    // a clamped PHP_INT_MAX which would apply a wrong filter.
    $query->shouldReceive('where')->never();

    $scope->apply(
        $query,
        [
            'queryScopes' => [
                'assets_by_file_size_min_999999999999999999999999999',
            ],
        ]
    );
});

it('apply ignores handles for a different scope prefix', function () {
    $scope = new AssetsByFileSize;
    /** @var QueryBuilder&MockInterface $query */
    $query = Mockery::mock(QueryBuilder::class);
    $query->shouldReceive('where')->never();

    $scope->apply(
        $query,
        [
            'queryScopes' => [
                'assets_by_file_type_image_jpeg',
            ],
        ]
    );
});
