<?php

declare(strict_types=1);

use App\Support\Classifieds\ClassifiedCategories;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Facades\Term;
use Statamic\Taxonomies\LocalizedTerm;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * The classified categories a listing can be filed under, read from the `classified_categories`
 * taxonomy in title order (#255 coverage).
 *
 * The term query is mocked, so each test controls exactly which terms the taxonomy holds and
 * can assert the query it asks for.
 *
 * @command  php artisan test --compact tests/Feature/Support/Classifieds/ClassifiedCategoriesTest.php
 */

/**
 * Make the term query return the given terms, after checking it asks for this taxonomy in title order.
 *
 * @param  list<array{0: mixed, 1: mixed}>  $terms  Each term's slug and title.
 */
function classifiedCategoriesQueryReturns(array $terms): void
{
    $termDoubles = array_map(function (array $term): LocalizedTerm {
        $double = Mockery::mock(LocalizedTerm::class);
        $double->shouldReceive('slug')->andReturn($term[0]);
        $double->shouldReceive('title')->andReturn($term[1]);

        return $double;
    }, $terms);

    $query = Mockery::mock();
    $query->shouldReceive('where')->once()->with('taxonomy', 'classified_categories')->andReturnSelf();
    $query->shouldReceive('orderBy')->once()->with('title')->andReturnSelf();
    $query->shouldReceive('get')->once()->andReturn(collect($termDoubles));

    Term::shouldReceive('query')->once()->andReturn($query);
}

it('describes each category by slug and title, in the order the query returns them', function (): void {
    classifiedCategoriesQueryReturns([['furniture', 'Furniture'], ['housing', 'Housing']]);

    expect((new ClassifiedCategories)->all())->toBe([
        ['slug' => 'furniture', 'title' => 'Furniture'],
        ['slug' => 'housing', 'title' => 'Housing'],
    ]);
});

it('skips a term with no usable slug', function (mixed $slug): void {
    classifiedCategoriesQueryReturns([['furniture', 'Furniture'], [$slug, 'Broken'], ['housing', 'Housing']]);

    expect((new ClassifiedCategories)->all())->toBe([
        ['slug' => 'furniture', 'title' => 'Furniture'],
        ['slug' => 'housing', 'title' => 'Housing'],
    ]);
})->with([
    'no slug' => [null],
    'an empty slug' => [''],
]);

it('titles a category with no text title by its slug', function (): void {
    classifiedCategoriesQueryReturns([['furniture', null]]);

    expect((new ClassifiedCategories)->all())->toBe([['slug' => 'furniture', 'title' => 'furniture']]);
});

it('keeps one category per slug, the last term for that slug winning, in first-seen position', function (): void {
    classifiedCategoriesQueryReturns([['furniture', 'Furniture'], ['housing', 'Housing'], ['furniture', 'Furnishings']]);

    expect((new ClassifiedCategories)->all())->toBe([
        ['slug' => 'furniture', 'title' => 'Furnishings'],
        ['slug' => 'housing', 'title' => 'Housing'],
    ]);
});

it('lists the slugs alone', function (): void {
    classifiedCategoriesQueryReturns([['furniture', 'Furniture'], ['', 'Broken'], ['housing', 'Housing']]);

    expect((new ClassifiedCategories)->slugs())->toBe(['furniture', 'housing']);
});

it('has no categories when the taxonomy has no terms', function (): void {
    classifiedCategoriesQueryReturns([]);

    expect((new ClassifiedCategories)->all())->toBe([]);
});
