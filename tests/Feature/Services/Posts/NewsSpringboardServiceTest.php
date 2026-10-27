<?php

declare(strict_types=1);

use App\Services\Posts\NewsSpringboardService;
use App\Services\Posts\NewsSyndicationQueryService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Behavioral coverage for {@see NewsSpringboardService} — the cross-section
 * dedup core of the news-magazine springboard (UAMS-Web/uams-statamic-archive#1158).
 *
 * Builds real `posts` / `post_categories` fixtures in an isolated Stache, so the
 * accumulator threading runs against the actual {@see NewsSyndicationQueryService}
 * query (cross-site, category `whereJsonOverlaps`, `whereNotIn` exclusion, date
 * order), not a mock.
 *
 * @covers \App\Services\Posts\NewsSpringboardService
 *
 * @command  php artisan test --compact tests/Feature/Services/Posts/NewsSpringboardServiceTest.php
 */
uses(StacheIsolatedTestCase::class);

/** Create a `post_categories` entry. */
function springboardCategory(string $id): void
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->collection('post_categories');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->id($id);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->slug($id);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->published(true);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->data(['title' => $id]);
    $entry->saveQuietly();
}

/**
 * Create a published `posts` entry on the news site.
 *
 * @param  array<int, string>  $categories
 */
function springboardPost(string $id, string $date, array $categories): void
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->collection('posts');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->id($id);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->slug($id);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->locale('news');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->published(true);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->date(Carbon::parse($date));
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->data(['title' => 'Post '.$id, 'post_categories' => $categories]);
    $entry->saveQuietly();
}

/**
 * Map a resolved section's posts to their ids.
 *
 * @param  Collection<int, Statamic\Entries\Entry>  $posts
 * @return array<int, string>
 */
function springboardIds(Collection $posts): array
{
    return $posts
        ->map(function (Statamic\Entries\Entry $entry): string {
            /** @var string $id */
            $id = $entry->id();

            return (string) $id;
        })
        ->all();
}

beforeEach(function (): void {
    /** @var Statamic\Entries\Collection $postCategories */
    $postCategories = CollectionFacade::make('post_categories');
    /** @var Statamic\Entries\Collection $postCategories */
    $postCategories = $postCategories->title('Post Categories');
    /** @var Statamic\Entries\Collection $postCategories */
    $postCategories = $postCategories->sites(['default']);
    $postCategories->save();

    /** @var Statamic\Entries\Collection $posts */
    $posts = CollectionFacade::make('posts');
    /** @var Statamic\Entries\Collection $posts */
    $posts = $posts->title('Posts');
    /** @var Statamic\Entries\Collection $posts */
    $posts = $posts->sites(['default', 'news']);
    /** @var Statamic\Entries\Collection $posts */
    $posts = $posts->dated(true);
    $posts->save();

    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = Blueprint::makeFromFields([
        'title' => ['type' => 'text'],
        'post_categories' => ['type' => 'entries', 'collections' => ['post_categories']],
    ]);
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = $blueprint->setHandle('post');
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = $blueprint->setNamespace('collections.posts');
    $blueprint->save();

    springboardCategory('cat-a');
    springboardCategory('cat-b');

    // p4 is filed under BOTH categories — the cross-section dedup target.
    springboardPost('p1', '2024-05-01', ['cat-a']);
    springboardPost('p2', '2024-04-01', ['cat-a']);
    springboardPost('p3', '2024-03-01', ['cat-b']);
    springboardPost('p4', '2024-02-01', ['cat-a', 'cat-b']);
    springboardPost('p5', '2024-01-01', ['cat-b']);
});

it('excludes a post surfaced by an earlier section from a later one', function () {
    $sections = app(NewsSpringboardService::class)->resolveSections([
        ['category' => ['cat-a'], 'count' => 3],
        ['category' => ['cat-b'], 'count' => 3],
    ], 'news');

    // cat-a section takes p1, p2, p4 (newest first)…
    expect(springboardIds($sections[0]['posts']))->toBe(['p1', 'p2', 'p4']);
    // …so the cat-b section skips p4 (earlier-section-wins) and lists p3, p5 only.
    expect(springboardIds($sections[1]['posts']))->toBe(['p3', 'p5']);
});

it('caps each section at its own count', function () {
    $sections = app(NewsSpringboardService::class)->resolveSections([
        ['category' => ['cat-a'], 'count' => 1],
        ['category' => ['cat-b'], 'count' => 1],
    ], 'news');

    expect(springboardIds($sections[0]['posts']))->toBe(['p1']);
    expect(springboardIds($sections[1]['posts']))->toBe(['p3']);
});

it('defaults a section with no count to DEFAULT_COUNT', function () {
    $sections = app(NewsSpringboardService::class)->resolveSections([
        ['category' => ['cat-a']],
    ], 'news');

    // Only three cat-a posts exist, below the default cap of four.
    expect($sections[0]['posts'])->toHaveCount(3);
    expect(NewsSpringboardService::DEFAULT_COUNT)->toBe(4);
});

it('treats a section with no category as a latest-news catch-all, still deduped', function () {
    $sections = app(NewsSpringboardService::class)->resolveSections([
        ['category' => ['cat-a'], 'count' => 2],
        ['category' => [], 'count' => 10],
    ], 'news');

    // Section 1 surfaces the two newest cat-a posts…
    expect(springboardIds($sections[0]['posts']))->toBe(['p1', 'p2']);
    // …and the catch-all lists every remaining post, newest first, excluding them.
    expect(springboardIds($sections[1]['posts']))->toBe(['p3', 'p4', 'p5']);
});

it('carries the accumulator across three sections', function () {
    $sections = app(NewsSpringboardService::class)->resolveSections([
        ['category' => ['cat-a'], 'count' => 1],
        ['category' => ['cat-a'], 'count' => 1],
        ['category' => ['cat-a'], 'count' => 1],
    ], 'news');

    // Three sections on the same category each surface the next-newest post,
    // proving the accumulator threads through every section, not just the first.
    expect(springboardIds($sections[0]['posts']))->toBe(['p1']);
    expect(springboardIds($sections[1]['posts']))->toBe(['p2']);
    expect(springboardIds($sections[2]['posts']))->toBe(['p4']);
});
