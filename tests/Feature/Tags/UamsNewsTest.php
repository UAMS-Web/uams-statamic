<?php

declare(strict_types=1);

use App\Services\Posts\NewsSyndicationQueryService;
use App\Tags\UamsNews;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Entries\Entry;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Coverage for the {{ uams_news }} tag (UAMS-Web/uams-statamic-archive#76). The tag is a thin adapter: it
 * normalizes the block's picker/checkbox field values, maps each format to its
 * fixed item count, and delegates to {@see NewsSyndicationQueryService} (whose
 * own behavior is pinned by NewsSyndicationQueryServiceTest, mocked here).
 *
 * What this proves lives entirely in the adapter layer: per-format counts, the
 * `full` single-article branch, the `as`/no_results/total_results contract, and
 * the Entry/checkbox value normalization the tag performs before delegating.
 *
 * @covers \App\Tags\UamsNews
 *
 * @command  php artisan test --compact tests/Feature/Tags/UamsNewsTest.php
 */

/**
 * Bind a mock query service so the tag's delegation args can be asserted.
 *
 * @return NewsSyndicationQueryService&MockInterface
 */
function uamsNewsFakeService(): NewsSyndicationQueryService
{
    /** @var NewsSyndicationQueryService&MockInterface $service */
    $service = Mockery::mock(NewsSyndicationQueryService::class);
    app()->instance(NewsSyndicationQueryService::class, $service);

    return $service;
}

/**
 * Build the tag with the given parameters and an empty context.
 *
 * @param  array<string, mixed>  $params
 */
function uamsNewsTag(array $params): UamsNews
{
    $tag = app(UamsNews::class);
    $tag->setContext([]);
    $tag->setParameters($params);

    return $tag;
}

/** A fake augmented Entry whose id() returns the given value. */
function uamsNewsEntry(string $id): Entry
{
    /** @var Entry&MockInterface $entry */
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('id')->andReturn($id);

    return $entry;
}

/**
 * Stub the list-path collaborators (resolveSiteHandles + resolveCategoryLink) so
 * a test can focus its assertions on the query() call.
 *
 * @param  NewsSyndicationQueryService&MockInterface  $service
 * @param  array<int, string>  $handles
 */
function uamsNewsStubListPath(NewsSyndicationQueryService $service, array $handles = ['news']): void
{
    $service->shouldReceive('resolveSiteHandles')->andReturn($handles);
    $service->shouldReceive('resolveCategoryLink')->andReturn(['name' => null, 'url' => null]);
}

// ---------------------------------------------------------------------------
// Per-format effective count
// ---------------------------------------------------------------------------

dataset('news_format_counts', [
    'cards is fixed at 4' => ['cards', [], 4],
    'grid is fixed at 3' => ['grid', [], 3],
    'side is fixed at 1' => ['side', [], 1],
    'headlines uses the editor count' => ['headlines', ['count' => 5], 5],
    'excerpts uses the editor count' => ['excerpts', ['count' => 7], 7],
    'headlines defaults to 3 without a count' => ['headlines', [], 3],
]);

it('passes each format its effective item count to the query', function (string $format, array $extra, int $expectedCount): void {
    $service = uamsNewsFakeService();
    uamsNewsStubListPath($service);

    // The 3rd query() argument is the effective count for the format; the 6th
    // is the format itself (so the require-image filter can be layout-aware).
    $service->shouldReceive('query')
        ->with(['news'], [], $expectedCount, 0, false, $format)
        ->once()
        ->andReturn(collect([]));

    uamsNewsTag(array_merge(['format' => $format, 'source_sites' => ['news']], $extra))->index();
})->with('news_format_counts');

it('passes the editor offset through to the query', function (): void {
    $service = uamsNewsFakeService();
    uamsNewsStubListPath($service);

    $service->shouldReceive('query')
        ->with(['news'], [], 3, 4, false, 'grid')
        ->once()
        ->andReturn(collect([]));

    uamsNewsTag(['format' => 'grid', 'source_sites' => ['news'], 'offset' => 4])->index();
});

it('threads the require_featured_image toggle through to the query', function (): void {
    $service = uamsNewsFakeService();
    uamsNewsStubListPath($service);

    // The 5th query() argument is the require-featured-image flag (UAMS-Web/uams-statamic-archive#1154).
    $service->shouldReceive('query')
        ->with(['news'], [], 3, 0, true, 'grid')
        ->once()
        ->andReturn(collect([]));

    uamsNewsTag(['format' => 'grid', 'source_sites' => ['news'], 'require_featured_image' => true])->index();
});

// ---------------------------------------------------------------------------
// full format (single article)
// ---------------------------------------------------------------------------

it('resolves a single article for the full format without a list query', function (): void {
    $service = uamsNewsFakeService();
    $article = uamsNewsEntry('art-1');

    $service->shouldReceive('findArticle')->with('art-1')->once()->andReturn($article);
    // The full format must never run a list query.
    $service->shouldNotReceive('query');
    $service->shouldNotReceive('resolveSiteHandles');

    $result = uamsNewsTag(['format' => 'full', 'article_id' => 'art-1'])->index();

    expect($result['news_item'])->toBe([$article])
        ->and($result['no_results'])->toBeFalse()
        ->and($result['total_results'])->toBe(1)
        ->and($result['category_name'])->toBeNull()
        ->and($result['category_url'])->toBeNull();
});

it('reports no results when the full-format article is missing', function (): void {
    $service = uamsNewsFakeService();
    $service->shouldReceive('findArticle')->andReturn(null);

    $result = uamsNewsTag(['format' => 'full', 'article_id' => 'gone'])->index();

    expect($result['news_item'])->toBe([])
        ->and($result['no_results'])->toBeTrue()
        ->and($result['total_results'])->toBe(0);
});

dataset('news_article_id_shapes', [
    'a raw id string' => ['plain-id', 'plain-id'],
    'a single-element array' => [['arr-id'], 'arr-id'],
]);

it('normalizes the article_id picker value to an id', function (mixed $articleId, string $expectedId): void {
    $service = uamsNewsFakeService();
    $service->shouldReceive('findArticle')->with($expectedId)->once()->andReturn(null);

    uamsNewsTag(['format' => 'full', 'article_id' => $articleId])->index();
})->with('news_article_id_shapes');

it('normalizes an Entry object article_id to its id', function (): void {
    $service = uamsNewsFakeService();
    $selected = uamsNewsEntry('entry-id');

    $service->shouldReceive('findArticle')->with('entry-id')->once()->andReturn(null);

    uamsNewsTag(['format' => 'full', 'article_id' => $selected])->index();
});

it('normalizes a single-element collection article_id to its id', function (): void {
    $service = uamsNewsFakeService();
    $selected = uamsNewsEntry('coll-id');

    $service->shouldReceive('findArticle')->with('coll-id')->once()->andReturn(null);

    uamsNewsTag(['format' => 'full', 'article_id' => collect([$selected])])->index();
});

// ---------------------------------------------------------------------------
// Category normalization (list path)
// ---------------------------------------------------------------------------

it('normalizes a bare category id', function (): void {
    $service = uamsNewsFakeService();
    uamsNewsStubListPath($service);

    $service->shouldReceive('query')->with(['news'], ['cat-cardiology'], 3, 0, false, 'grid')->once()->andReturn(collect([]));

    uamsNewsTag(['format' => 'grid', 'source_sites' => ['news'], 'category' => 'cat-cardiology'])->index();
});

it('normalizes an Entry-object category to its id', function (): void {
    $service = uamsNewsFakeService();
    uamsNewsStubListPath($service);

    $service->shouldReceive('query')->with(['news'], ['cat-cardiology'], 3, 0, false, 'grid')->once()->andReturn(collect([]));

    uamsNewsTag(['format' => 'grid', 'source_sites' => ['news'], 'category' => uamsNewsEntry('cat-cardiology')])->index();
});

it('normalizes and de-duplicates a mixed list of category ids and entries', function (): void {
    $service = uamsNewsFakeService();
    uamsNewsStubListPath($service);

    $service->shouldReceive('query')->with(['news'], ['cat-cardiology', 'cat-oncology'], 3, 0, false, 'grid')->once()->andReturn(collect([]));

    uamsNewsTag([
        'format' => 'grid',
        'source_sites' => ['news'],
        'category' => [uamsNewsEntry('cat-cardiology'), 'cat-oncology', 'cat-cardiology'],
    ])->index();
});

it('passes an empty category list when none is selected', function (): void {
    $service = uamsNewsFakeService();
    uamsNewsStubListPath($service);

    $service->shouldReceive('query')->with(['news'], [], 3, 0, false, 'grid')->once()->andReturn(collect([]));

    uamsNewsTag(['format' => 'grid', 'source_sites' => ['news']])->index();
});

// ---------------------------------------------------------------------------
// Source-site normalization (list path)
// ---------------------------------------------------------------------------

it('passes the checkbox source list and current site handle to the resolver', function (): void {
    $service = uamsNewsFakeService();

    // The current site in the test environment is `default`.
    $service->shouldReceive('resolveSiteHandles')->with(['news', 'current'], 'default')->once()->andReturn(['news', 'default']);
    $service->shouldReceive('resolveCategoryLink')->andReturn(['name' => null, 'url' => null]);
    $service->shouldReceive('query')->once()->andReturn(collect([]));

    uamsNewsTag(['format' => 'grid', 'source_sites' => ['news', 'current']])->index();
});

it('wraps a single scalar source into a one-element list', function (): void {
    $service = uamsNewsFakeService();

    $service->shouldReceive('resolveSiteHandles')->with(['news'], 'default')->once()->andReturn(['news']);
    $service->shouldReceive('resolveCategoryLink')->andReturn(['name' => null, 'url' => null]);
    $service->shouldReceive('query')->once()->andReturn(collect([]));

    uamsNewsTag(['format' => 'grid', 'source_sites' => 'news'])->index();
});

it('passes an empty source list when none is selected', function (): void {
    $service = uamsNewsFakeService();

    $service->shouldReceive('resolveSiteHandles')->with([], 'default')->once()->andReturn(['news']);
    $service->shouldReceive('resolveCategoryLink')->andReturn(['name' => null, 'url' => null]);
    $service->shouldReceive('query')->once()->andReturn(collect([]));

    uamsNewsTag(['format' => 'grid'])->index();
});

// ---------------------------------------------------------------------------
// Result contract
// ---------------------------------------------------------------------------

it('returns the entries under the default news_item alias with the full contract', function (): void {
    $service = uamsNewsFakeService();
    $service->shouldReceive('resolveSiteHandles')->andReturn(['news']);
    $service->shouldReceive('resolveCategoryLink')->andReturn(['name' => 'Cardiology', 'url' => null]);
    $service->shouldReceive('query')->andReturn(collect([uamsNewsEntry('x'), uamsNewsEntry('y')]));

    $result = uamsNewsTag(['format' => 'grid', 'source_sites' => ['news']])->index();

    expect($result)->toHaveKeys(['news_item', 'no_results', 'total_results', 'category_name', 'category_url'])
        ->and($result['news_item'])->toHaveCount(2)
        ->and($result['no_results'])->toBeFalse()
        ->and($result['total_results'])->toBe(2)
        ->and($result['category_name'])->toBe('Cardiology')
        ->and($result['category_url'])->toBeNull();
});

it('honours a custom as alias', function (): void {
    $service = uamsNewsFakeService();
    uamsNewsStubListPath($service);
    $service->shouldReceive('query')->andReturn(collect([uamsNewsEntry('x')]));

    $result = uamsNewsTag(['format' => 'grid', 'source_sites' => ['news'], 'as' => 'story'])->index();

    expect($result)->toHaveKey('story')
        ->and($result['story'])->toHaveCount(1)
        ->and($result)->not->toHaveKey('news_item');
});

it('reports no results when the query returns nothing', function (): void {
    $service = uamsNewsFakeService();
    uamsNewsStubListPath($service);
    $service->shouldReceive('query')->andReturn(collect([]));

    $result = uamsNewsTag(['format' => 'grid', 'source_sites' => ['news']])->index();

    expect($result['no_results'])->toBeTrue()
        ->and($result['total_results'])->toBe(0)
        ->and($result['news_item'])->toBe([]);
});
