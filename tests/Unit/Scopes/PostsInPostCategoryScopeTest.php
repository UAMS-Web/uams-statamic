<?php

declare(strict_types=1);

use App\Scopes\PostsInPostCategory;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Query\Builder;

uses(MockeryPHPUnitIntegration::class);

covers(PostsInPostCategory::class);

/**
 * @covers \App\Scopes\PostsInPostCategory
 *
 * The reverse lookup behind the UAMS-Web/uams-statamic-archive#1433 `post_categories` structured-collection
 * archive: "the posts filed under this category". A post references its
 * categories through the `post_categories` *entries* field, so the scope is a
 * `whereJsonContains` over that array.
 *
 * @command  php artisan test --compact tests/Unit/Scopes/PostsInPostCategoryScopeTest.php
 */
it('constrains the query to posts whose post_categories field contains the category id', function () {
    $scope = new PostsInPostCategory;
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereJsonContains')
        ->once()
        ->with(
            'post_categories',
            'cat-basic-sciences'
        )
        ->andReturnSelf();

    $scope->apply(
        $query,
        ['category' => 'cat-basic-sciences']
    );
});

it('matches nothing when no category id is supplied, so an id-less context never lists every post', function () {
    $scope = new PostsInPostCategory;
    $query = Mockery::mock(Builder::class);
    // A null needle intersects no post's category array, so the query stays
    // constrained (empty) rather than unfiltered.
    $query->shouldReceive('whereJsonContains')
        ->once()
        ->with(
            'post_categories',
            null
        )
        ->andReturnSelf();

    $scope->apply(
        $query,
        []
    );
});
