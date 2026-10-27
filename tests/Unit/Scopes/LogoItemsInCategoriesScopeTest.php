<?php

declare(strict_types=1);

use App\Scopes\LogoItemsInCategories;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Query\Builder;

uses(MockeryPHPUnitIntegration::class);

covers(LogoItemsInCategories::class);

/**
 * @covers \App\Scopes\LogoItemsInCategories
 *
 * @command  php artisan test --compact tests/Unit/Scopes/LogoItemsInCategoriesScopeTest.php
 */
it('applies no constraint when no categories are selected', function (): void {
    $query = Mockery::mock(Builder::class);
    $query->shouldNotReceive('where');
    $query->shouldNotReceive('whereJsonContains');
    $query->shouldNotReceive('whereJsonDoesntContain');

    (new LogoItemsInCategories)->apply($query, []);
});

it('filters logos_item_categories under any logic by default', function (): void {
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('where')
        ->once()
        ->with(Mockery::type(Closure::class))
        ->andReturnSelf();

    (new LogoItemsInCategories)->apply(
        $query,
        ['categories' => ['lc-colleges']]
    );
});

it('passes all logic through to whereJsonContains on logos_item_categories', function (): void {
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereJsonContains')
        ->once()
        ->with(
            'logos_item_categories',
            'lc-colleges'
        )
        ->andReturnSelf();

    (new LogoItemsInCategories)->apply(
        $query,
        [
            'categories' => ['lc-colleges'],
            'logic' => 'all',
        ]
    );
});
