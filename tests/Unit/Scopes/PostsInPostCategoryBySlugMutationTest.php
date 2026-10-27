<?php

declare(strict_types=1);

use App\Scopes\PostsInPostCategoryBySlug;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry as EntryObject;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Query\Builder;
use Statamic\Sites\Site as SiteObject;
use Tests\TestCase;

covers(PostsInPostCategoryBySlug::class);

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Narrow mutate coverage for App\Scopes\PostsInPostCategoryBySlug.
 *
 * @command  php artisan test --compact tests/Unit/Scopes/PostsInPostCategoryBySlugMutationTest.php
 */

/**
 * Pad Pest's mutant-child timeout once per process under ``--mutate``.
 */
beforeEach(function (): void {
    static $padded = false;
    if ($padded) {
        return;
    }

    if (runningUnderMutate()) {
        usleep(20_000_000);
        $padded = true;
    }
});

it('is a no-op for a missing or blank category_slug', function (array $values): void {
    /** @var array<string, mixed> $values */
    Entry::shouldReceive('query')->never();

    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereJsonContains')->never();

    (new PostsInPostCategoryBySlug)->apply(
        $query,
        $values,
    );
})->with([
    'absent key' => [[]],
    'null' => [['category_slug' => null]],
    'empty string' => [['category_slug' => '']],
    'non-string' => [['category_slug' => 12]],
]);

it('filters posts by the resolved category id on the current site', function (): void {
    $site = Mockery::mock(SiteObject::class);
    $site->shouldReceive('handle')->once()->andReturn('news');
    Site::shouldReceive('current')->once()->andReturn($site);

    $category = Mockery::mock(EntryObject::class);
    $category->shouldReceive('id')->once()->andReturn('cat-9');

    $categoryQuery = Mockery::mock();
    $categoryQuery->shouldReceive('where')->with('collection', 'post_categories')->once()->andReturnSelf();
    $categoryQuery->shouldReceive('where')->with('site', 'news')->once()->andReturnSelf();
    $categoryQuery->shouldReceive('where')->with('slug', 'cardiology')->once()->andReturnSelf();
    $categoryQuery->shouldReceive('first')->once()->andReturn($category);

    Entry::shouldReceive('query')->once()->andReturn($categoryQuery);

    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereJsonContains')
        ->once()
        ->with(
            'post_categories',
            'cat-9',
        )
        ->andReturnSelf();

    (new PostsInPostCategoryBySlug)->apply(
        $query,
        ['category_slug' => 'cardiology'],
    );
});

it('filters by null when the slug resolves to no category', function (): void {
    // Unknown slug → nullsafe id → empty listing.
    $site = Mockery::mock(SiteObject::class);
    $site->shouldReceive('handle')->once()->andReturn('default');
    Site::shouldReceive('current')->once()->andReturn($site);

    $categoryQuery = Mockery::mock();
    $categoryQuery->shouldReceive('where')->andReturnSelf();
    $categoryQuery->shouldReceive('first')->once()->andReturnNull();

    Entry::shouldReceive('query')->once()->andReturn($categoryQuery);

    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('whereJsonContains')
        ->once()
        ->with(
            'post_categories',
            null,
        )
        ->andReturnSelf();

    (new PostsInPostCategoryBySlug)->apply(
        $query,
        ['category_slug' => 'missing-slug'],
    );
});
