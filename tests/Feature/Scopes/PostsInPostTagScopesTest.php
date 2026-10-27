<?php

declare(strict_types=1);

use App\Scopes\PostsInPostTag;
use App\Scopes\PostsInPostTagBySlug;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Query\Scopes\Scope;
use Statamic\Stache\Query\EntryQueryBuilder;
use Tests\Support\CollectionTreeIsolatedTestCase;
use Tests\Support\ContentSubmodule;

// Tree-isolated: saving a new entry into a structured collection appends it to that
// collection's tree, which must not touch the shared `post_tags` tree.
uses(CollectionTreeIsolatedTestCase::class);

/**
 * @covers \App\Scopes\PostsInPostTag
 * @covers \App\Scopes\PostsInPostTagBySlug
 *
 * The two reverse lookups UAMS-Web/uams-statamic-archive#2532 introduced when `post_tags` became a site-scoped
 * structured collection: `posts_in_post_tag` (the tag archive's "posts with this
 * tag", keyed by entry id) and `posts_in_post_tag_by_slug` (the posts listing's
 * `?tag=<slug>` filter, resolved on the current site). Exercised against real
 * disk-loaded content, because the `Entry::query()` slug/site resolution cannot be
 * proven with a mocked builder. Fixtures are saved and deleted quietly, for the
 * reason `SitemapTaxonomyTermsTest` records: nothing here reads a search index.
 *
 * @command  php artisan test --compact tests/Feature/Scopes/PostsInPostTagScopesTest.php
 */
const POSTS_TAG_SCOPE_TEST_PREFIX = 'zz-post-tag-scope-test-';

/** Remove every test tag/post authored by this file (any site). */
function tearDownPostsTagScopeFixtures(): void
{
    foreach (['post_tags', 'posts'] as $collection) {
        /** @var EntryQueryBuilder $query */
        $query = Entry::query();
        $query->where('collection', $collection);

        /** @var Illuminate\Support\Collection<int, Statamic\Entries\Entry> $entries */
        $entries = $query->get();

        $entries
            ->filter(fn (Statamic\Entries\Entry $e): bool => is_string($e->slug()) && str_starts_with($e->slug(), POSTS_TAG_SCOPE_TEST_PREFIX))
            ->each(fn (Statamic\Entries\Entry $e) => $e->deleteQuietly());
    }
}

/**
 * The ids the given scope selects from the `posts` collection.
 *
 * @param  array<string, mixed>  $values
 * @return list<string>
 */
function postIdsForTagScope(Scope $scope, array $values): array
{
    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->where('collection', 'posts');

    $scope->apply($query, $values);

    /** @var Illuminate\Support\Collection<int, Statamic\Entries\Entry> $posts */
    $posts = $query->get();

    /** @var list<string> $ids */
    $ids = $posts->map(fn (Statamic\Entries\Entry $e): string => mustBeString($e->id(), 'post id'))->values()->all();

    return $ids;
}

/** Create and quietly save a test tag on the given site. */
function makeTestPostTag(string $slugSuffix, string $site = 'default'): Statamic\Entries\Entry
{
    /** @var Statamic\Entries\Entry $tag */
    $tag = Entry::make();
    $tag->collection('post_tags');
    $tag->locale($site);
    $tag->slug(POSTS_TAG_SCOPE_TEST_PREFIX.$slugSuffix);
    $tag->data(['title' => 'Tag '.$slugSuffix]);
    $tag->saveQuietly();

    return $tag;
}

/**
 * Create and quietly save a published default-site test post with the given tag ids.
 *
 * @param  list<string>  $tagIds
 */
function makeTestTaggedPost(string $slugSuffix, array $tagIds): Statamic\Entries\Entry
{
    /** @var Statamic\Entries\Entry $post */
    $post = Entry::make();
    $post->collection('posts');
    $post->locale('default');
    $post->published(true);
    $post->date('2026-01-02');
    $post->slug(POSTS_TAG_SCOPE_TEST_PREFIX.$slugSuffix);
    $post->data(['title' => 'Test Post '.$slugSuffix, 'post_tags' => $tagIds]);
    $post->saveQuietly();

    return $post;
}

beforeEach(function (): void {
    if (! Collection::findByHandle('post_tags') || ! Collection::findByHandle('posts')) {
        ContentSubmodule::reportMissing('post_tags + posts collections');
    }

    Site::setCurrent('default');
    tearDownPostsTagScopeFixtures();
});

afterEach(function (): void {
    tearDownPostsTagScopeFixtures();
    Site::setCurrent('default');
});

it('lists only the posts carrying the tag entry id', function (): void {
    $immunology = makeTestPostTag('immunology');
    $oncology = makeTestPostTag('oncology');

    $tagged = makeTestTaggedPost('tagged', [mustBeString($immunology->id(), 'tag id')]);
    $otherTag = makeTestTaggedPost('other-tag', [mustBeString($oncology->id(), 'tag id')]);
    $untagged = makeTestTaggedPost('untagged', []);

    $ids = postIdsForTagScope(new PostsInPostTag, ['tag' => $immunology->id()]);

    expect($ids)->toContain($tagged->id());
    expect($ids)->not->toContain($otherTag->id());
    expect($ids)->not->toContain($untagged->id());
})->group('serial');

it('lists nothing for an absent tag id, not every post', function (): void {
    $immunology = makeTestPostTag('immunology');

    $tagged = makeTestTaggedPost('tagged', [mustBeString($immunology->id(), 'tag id')]);
    $untagged = makeTestTaggedPost('untagged', []);

    $ids = postIdsForTagScope(new PostsInPostTag, []);

    expect($ids)->not->toContain($tagged->id());
    expect($ids)->not->toContain($untagged->id());
})->group('serial');

it('lists only the posts carrying the tag its slug resolves to', function (): void {
    $immunology = makeTestPostTag('immunology');
    $oncology = makeTestPostTag('oncology');

    $tagged = makeTestTaggedPost('tagged', [mustBeString($immunology->id(), 'tag id')]);
    $otherTag = makeTestTaggedPost('other-tag', [mustBeString($oncology->id(), 'tag id')]);
    $untagged = makeTestTaggedPost('untagged', []);

    $ids = postIdsForTagScope(new PostsInPostTagBySlug, ['tag_slug' => POSTS_TAG_SCOPE_TEST_PREFIX.'immunology']);

    expect($ids)->toContain($tagged->id());
    expect($ids)->not->toContain($otherTag->id());
    expect($ids)->not->toContain($untagged->id());
})->group('serial');

it('applies no constraint for a blank or absent slug, so the unfiltered listing lists every post', function (?string $blank): void {
    $immunology = makeTestPostTag('immunology');

    $tagged = makeTestTaggedPost('tagged', [mustBeString($immunology->id(), 'tag id')]);
    $untagged = makeTestTaggedPost('untagged', []);

    $values = $blank === null ? [] : ['tag_slug' => $blank];

    $ids = postIdsForTagScope(new PostsInPostTagBySlug, $values);

    expect($ids)
        ->toContain($tagged->id())
        ->toContain($untagged->id());
})->with([
    'absent key' => [null],
    'empty string' => [''],
])->group('serial');

it('lists nothing when the slug resolves to no tag', function (): void {
    $immunology = makeTestPostTag('immunology');

    $tagged = makeTestTaggedPost('tagged', [mustBeString($immunology->id(), 'tag id')]);
    $untagged = makeTestTaggedPost('untagged', []);

    $ids = postIdsForTagScope(new PostsInPostTagBySlug, ['tag_slug' => POSTS_TAG_SCOPE_TEST_PREFIX.'does-not-exist']);

    expect($ids)->not->toContain($tagged->id());
    expect($ids)->not->toContain($untagged->id());
})->group('serial');

it('resolves the slug on the current site only, ignoring a same-slug tag on another site', function (): void {
    /** @var Statamic\Entries\Collection $tagCollection */
    $tagCollection = Collection::findByHandle('post_tags');

    if (! in_array('news', mustBe(Illuminate\Support\Collection::class, $tagCollection->sites(), 'post_tags sites')->all(), true)) {
        $this->markTestSkipped('Requires a second post_tags site to prove site-scoping.');
    }

    // The only tag with this slug lives on `news`; the post referencing it lives on
    // the default site. With the current site = default, the slug must resolve to
    // no tag, so the post is excluded. Dropping the site filter would resolve the
    // slug across all sites and the post would appear.
    $elsewhere = makeTestPostTag('shared', 'news');
    $post = makeTestTaggedPost('cross-site-post', [mustBeString($elsewhere->id(), 'tag id')]);

    Site::setCurrent('default');
    $onDefault = postIdsForTagScope(new PostsInPostTagBySlug, ['tag_slug' => POSTS_TAG_SCOPE_TEST_PREFIX.'shared']);

    // Control: on the tag's own site the same slug does resolve, so the exclusion
    // above is the site filter at work rather than a lookup that never matches.
    Site::setCurrent('news');
    $onNews = postIdsForTagScope(new PostsInPostTagBySlug, ['tag_slug' => POSTS_TAG_SCOPE_TEST_PREFIX.'shared']);

    expect($onDefault)->not->toContain($post->id());
    expect($onNews)->toContain($post->id());
})->group('serial');
