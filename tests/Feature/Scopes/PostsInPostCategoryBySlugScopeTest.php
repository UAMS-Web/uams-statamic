<?php

declare(strict_types=1);

use App\Scopes\PostsInPostCategoryBySlug;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Stache\Query\EntryQueryBuilder;
use Tests\Support\CollectionTreeIsolatedTestCase;
use Tests\Support\ContentSubmodule;

uses(CollectionTreeIsolatedTestCase::class);

/**
 * @covers \App\Scopes\PostsInPostCategoryBySlug
 *
 * The slug-resolving reverse lookup behind the posts listing's optional
 * `?category=<slug>` filter (UAMS-Web/uams-statamic-archive#1483): resolve the query-string slug to its
 * `post_categories` collection entry on the current site, then constrain the
 * `posts` query to that entry id. Exercised against real disk-loaded content (the
 * `Entry::query()` slug/site resolution cannot be proven with a mocked builder),
 * so it self-provisions a throwaway category/post set on the default site (the
 * PostCategoryArchiveRenderTest pattern) and tears it down by slug prefix.
 *
 * @command  php artisan test --compact tests/Feature/Scopes/PostsInPostCategoryBySlugScopeTest.php
 */
const POSTS_CAT_SLUG_TEST_PREFIX = 'zz-pcslug-test-';

/** Remove every test category/post authored by this file (any site). */
function tearDownPostsCategorySlugFixtures(): void
{
    foreach (['post_categories', 'posts'] as $collection) {
        /** @var EntryQueryBuilder $query */
        $query = Entry::query();
        $query->where('collection', $collection);

        /** @var Illuminate\Support\Collection<int, Statamic\Entries\Entry> $entries */
        $entries = $query->get();

        $entries
            ->filter(function (Statamic\Entries\Entry $e): bool {
                /** @var string|null $slug */
                $slug = $e->slug();

                return str_starts_with((string) $slug, POSTS_CAT_SLUG_TEST_PREFIX);
            })
            ->each(fn (Statamic\Entries\Entry $e) => $e->delete());
    }
}

/**
 * The ids the scope selects from the `posts` collection for the given slug.
 *
 * @param  array<string, mixed>  $values
 * @return list<string>
 */
function postIdsForCategorySlug(array $values): array
{
    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->where('collection', 'posts');

    (new PostsInPostCategoryBySlug)->apply($query, $values);

    /** @var Illuminate\Support\Collection<int, Statamic\Entries\Entry> $posts */
    $posts = $query->get();

    /** @var list<string> $ids */
    $ids = $posts->map(function (Statamic\Entries\Entry $e): string {
        /** @var string $id */
        $id = $e->id();

        return $id;
    })->all();

    return $ids;
}

beforeEach(function (): void {
    if (! Collection::findByHandle('post_categories') || ! Collection::findByHandle('posts')) {
        ContentSubmodule::reportMissing('migrated post_categories + posts collections');
    }

    Site::setCurrent('default');
    tearDownPostsCategorySlugFixtures();
});

afterEach(function (): void {
    tearDownPostsCategorySlugFixtures();
    Site::setCurrent('default');
});

it('lists only the posts filed under the category its slug resolves to', function (): void {
    /** @var Statamic\Entries\Entry $cardiology */
    $cardiology = Entry::make();
    /** @var Statamic\Entries\Entry $cardiology */
    $cardiology = $cardiology->collection('post_categories');
    /** @var Statamic\Entries\Entry $cardiology */
    $cardiology = $cardiology->locale('default');
    /** @var Statamic\Entries\Entry $cardiology */
    $cardiology = $cardiology->slug(POSTS_CAT_SLUG_TEST_PREFIX.'cardiology');
    /** @var Statamic\Entries\Entry $cardiology */
    $cardiology = $cardiology->data(['title' => 'Cardiology']);
    $cardiology->save();

    /** @var Statamic\Entries\Entry $oncology */
    $oncology = Entry::make();
    /** @var Statamic\Entries\Entry $oncology */
    $oncology = $oncology->collection('post_categories');
    /** @var Statamic\Entries\Entry $oncology */
    $oncology = $oncology->locale('default');
    /** @var Statamic\Entries\Entry $oncology */
    $oncology = $oncology->slug(POSTS_CAT_SLUG_TEST_PREFIX.'oncology');
    /** @var Statamic\Entries\Entry $oncology */
    $oncology = $oncology->data(['title' => 'Oncology']);
    $oncology->save();

    /** @var string $cardiologyId */
    $cardiologyId = $cardiology->id();
    /** @var string $oncologyId */
    $oncologyId = $oncology->id();

    $inCardiology = makeTestPost('heart-study', [$cardiologyId]);
    $inOncology = makeTestPost('tumor-study', [$oncologyId]);
    $uncategorized = makeTestPost('general-news', []);

    $ids = postIdsForCategorySlug(['category_slug' => POSTS_CAT_SLUG_TEST_PREFIX.'cardiology']);

    expect($ids)->toContain($inCardiology->id());
    expect($ids)->not->toContain($inOncology->id());
    expect($ids)->not->toContain($uncategorized->id());
})->group('serial');

it('applies no constraint for a blank or absent slug, so the unfiltered listing lists every post', function (?string $blank): void {
    /** @var Statamic\Entries\Entry $cardiology */
    $cardiology = Entry::make();
    /** @var Statamic\Entries\Entry $cardiology */
    $cardiology = $cardiology->collection('post_categories');
    /** @var Statamic\Entries\Entry $cardiology */
    $cardiology = $cardiology->locale('default');
    /** @var Statamic\Entries\Entry $cardiology */
    $cardiology = $cardiology->slug(POSTS_CAT_SLUG_TEST_PREFIX.'cardiology');
    /** @var Statamic\Entries\Entry $cardiology */
    $cardiology = $cardiology->data(['title' => 'Cardiology']);
    $cardiology->save();

    /** @var string $cardiologyId */
    $cardiologyId = $cardiology->id();

    $inCardiology = makeTestPost('heart-study', [$cardiologyId]);
    $uncategorized = makeTestPost('general-news', []);

    // Both a blank slug and an absent key must leave the query unconstrained.
    $values = $blank === null ? [] : ['category_slug' => $blank];

    $ids = postIdsForCategorySlug($values);

    expect($ids)
        ->toContain($inCardiology->id())
        ->toContain($uncategorized->id());
})->with([
    'absent key' => [null],
    'empty string' => [''],
])->group('serial');

it('lists nothing when the slug resolves to no category (including uncategorized posts)', function (): void {
    /** @var Statamic\Entries\Entry $cardiology */
    $cardiology = Entry::make();
    /** @var Statamic\Entries\Entry $cardiology */
    $cardiology = $cardiology->collection('post_categories');
    /** @var Statamic\Entries\Entry $cardiology */
    $cardiology = $cardiology->locale('default');
    /** @var Statamic\Entries\Entry $cardiology */
    $cardiology = $cardiology->slug(POSTS_CAT_SLUG_TEST_PREFIX.'cardiology');
    /** @var Statamic\Entries\Entry $cardiology */
    $cardiology = $cardiology->data(['title' => 'Cardiology']);
    $cardiology->save();

    /** @var string $cardiologyId */
    $cardiologyId = $cardiology->id();

    $inCardiology = makeTestPost('heart-study', [$cardiologyId]);
    $uncategorized = makeTestPost('general-news', []);

    // A slug that matches no category resolves to a null id; the null needle must
    // intersect no post — not even a post with an empty/absent category array.
    $ids = postIdsForCategorySlug(['category_slug' => POSTS_CAT_SLUG_TEST_PREFIX.'does-not-exist']);

    expect($ids)->not->toContain($inCardiology->id());
    expect($ids)->not->toContain($uncategorized->id());
})->group('serial');

it('resolves the slug on the current site only, ignoring a same-slug category on another site', function (): void {
    // beforeEach already skips unless post_categories resolves, so it is non-null here.
    /** @var Statamic\Entries\Collection $categoryCollection */
    $categoryCollection = Collection::findByHandle('post_categories');
    /** @var Illuminate\Support\Collection<int, string> $categorySites */
    $categorySites = $categoryCollection->sites();

    if (! in_array('science_reports', $categorySites->all(), true)) {
        $this->markTestSkipped('Requires a second post_categories site to prove site-scoping.');
    }

    // The only "shared" category lives on a non-default site; the post referencing
    // it lives on the default site. With the current site = default, the slug must
    // resolve to NO category (site-scoped), so the post is excluded. Dropping the
    // site filter would resolve the slug across all sites → the post would appear.
    /** @var Statamic\Entries\Entry $otherSiteCategory */
    $otherSiteCategory = Entry::make();
    /** @var Statamic\Entries\Entry $otherSiteCategory */
    $otherSiteCategory = $otherSiteCategory->collection('post_categories');
    /** @var Statamic\Entries\Entry $otherSiteCategory */
    $otherSiteCategory = $otherSiteCategory->locale('science_reports');
    /** @var Statamic\Entries\Entry $otherSiteCategory */
    $otherSiteCategory = $otherSiteCategory->slug(POSTS_CAT_SLUG_TEST_PREFIX.'shared');
    /** @var Statamic\Entries\Entry $otherSiteCategory */
    $otherSiteCategory = $otherSiteCategory->data(['title' => 'Shared Elsewhere']);
    $otherSiteCategory->save();

    /** @var string $otherSiteCategoryId */
    $otherSiteCategoryId = $otherSiteCategory->id();

    $post = makeTestPost('cross-site-post', [$otherSiteCategoryId]);

    Site::setCurrent('default');

    $ids = postIdsForCategorySlug(['category_slug' => POSTS_CAT_SLUG_TEST_PREFIX.'shared']);

    expect($ids)->not->toContain($post->id());
})->group('serial');

/**
 * Create + save a published default-site test post filed under the given category ids.
 *
 * @param  list<string>  $categoryIds
 */
function makeTestPost(string $slugSuffix, array $categoryIds): Statamic\Entries\Entry
{
    /** @var Statamic\Entries\Entry $post */
    $post = Entry::make();
    /** @var Statamic\Entries\Entry $post */
    $post = $post->collection('posts');
    /** @var Statamic\Entries\Entry $post */
    $post = $post->locale('default');
    /** @var Statamic\Entries\Entry $post */
    $post = $post->published(true);
    /** @var Statamic\Entries\Entry $post */
    $post = $post->date('2026-01-02');
    /** @var Statamic\Entries\Entry $post */
    $post = $post->slug(POSTS_CAT_SLUG_TEST_PREFIX.$slugSuffix);
    /** @var Statamic\Entries\Entry $post */
    $post = $post->data(['title' => 'Test Post '.$slugSuffix, 'post_categories' => $categoryIds]);
    $post->save();

    return $post;
}
