<?php

declare(strict_types=1);

use App\Scopes\PostsInPostCategory;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Stache\Query\EntryQueryBuilder;
use Statamic\Structures\CollectionStructure;
use Statamic\Structures\Tree;
use Tests\Support\StacheIsolatedTestCase;

uses(StacheIsolatedTestCase::class);

/**
 * Hermetic behavioral coverage for the `post_categories` flat-taxonomy →
 * structured-collection migration (UAMS-Web/uams-statamic-archive#1433). Everything is built in a throwaway
 * Stache ({@see StacheIsolatedTestCase}), so these run without the content/
 * submodule and never touch committed content.
 *
 * Proves the three load-bearing properties of the migration:
 *  - the hierarchical `category/{parent_uri}/{slug}` route (top-level collapses
 *    the empty parent segment; a child nests under its parent — parent → child
 *    tree preserved, slugs stable);
 *  - a post associates to a category through the `post_categories` *entries*
 *    reference, and the reverse `posts_in_post_category` scope lists exactly
 *    those posts;
 *  - the literal `category/` prefix routes only `/category/{slug}` — bare
 *    `/category` is a 404 (a URL namespace, not an index; UAMS-Web/uams-statamic-archive#1435 adds a browse-all
 *    index there as a separate `pages` entry).
 *
 * @command  php artisan test --compact tests/Feature/PostCategoriesCollectionTest.php
 */

/** Create the structured `post_categories` collection in the throwaway Stache. */
function makePostCategoriesCollection(): void
{
    $collection = Collection::make('post_categories');
    $collection->routes("{{ depth == 1 ?= 'category' }}/{{ parent_uri }}/{{ slug }}");
    $collection->structureContents(['root' => false]);
    $collection->save();
}

/** Persist a `post_categories` entry with a known id/slug/title. */
function makeCategory(string $id, string $slug, string $title): void
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection('post_categories');
    $entry->id($id);
    $entry->slug($slug);
    $entry->data(['title' => $title]);
    $entry->save();
}

it('routes a top-level category at /category/{slug} and a nested category at /category/{parent}/{child}', function () {
    makePostCategoriesCollection();
    makeCategory('cat-parent', 'basic-sciences', 'Basic Sciences');
    makeCategory('cat-child', 'molecular-biology', 'Molecular Biology');

    /** @var Statamic\Entries\Collection $collection */
    $collection = Collection::find('post_categories');
    /** @var CollectionStructure $structure */
    $structure = $collection->structure();
    /** @var Tree $tree */
    $tree = $structure->makeTree('default', [
        ['entry' => 'cat-parent', 'children' => [
            ['entry' => 'cat-child'],
        ]],
    ]);
    $tree->save();

    // Top-level: the empty {parent_uri} segment collapses (URL::tidy), so no
    // `/category//basic-sciences` double slash.
    /** @var Statamic\Entries\Entry $parent */
    $parent = Entry::find('cat-parent');
    expect($parent->url())->toBe('/category/basic-sciences');
    // Nested: the parent slug is preserved in the child's URL — the parent → child
    // tree the flat taxonomy could not express.
    /** @var Statamic\Entries\Entry $child */
    $child = Entry::find('cat-child');
    expect($child->url())->toBe('/category/basic-sciences/molecular-biology');
});

it('lists exactly the posts filed under a category through the entries reference', function () {
    makePostCategoriesCollection();
    $posts = Collection::make('posts');
    $posts->routes('/posts/{slug}');
    $posts->save();

    makeCategory('cat-a', 'cat-a', 'Category A');
    makeCategory('cat-b', 'cat-b', 'Category B');

    /** @var Statamic\Entries\Entry $p1 */
    $p1 = Entry::make();
    $p1->collection('posts');
    $p1->id('p1');
    $p1->slug('p1');
    $p1->published(true);
    $p1->data(['title' => 'Post One', 'post_categories' => ['cat-a']]);
    $p1->save();

    /** @var Statamic\Entries\Entry $p2 */
    $p2 = Entry::make();
    $p2->collection('posts');
    $p2->id('p2');
    $p2->slug('p2');
    $p2->published(true);
    $p2->data(['title' => 'Post Two', 'post_categories' => ['cat-a', 'cat-b']]);
    $p2->save();

    /** @var Statamic\Entries\Entry $p3 */
    $p3 = Entry::make();
    $p3->collection('posts');
    $p3->id('p3');
    $p3->slug('p3');
    $p3->published(true);
    $p3->data(['title' => 'Post Three', 'post_categories' => ['cat-b']]);
    $p3->save();

    // The reverse lookup the category archive runs — posts filed under cat-a via
    // the `post_categories` entries reference — driven over the real Stache query.
    // p2 is multi-category (kept); p3 (cat-b only) is excluded.
    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->where('collection', 'posts');
    (new PostsInPostCategory)->apply($query, ['category' => 'cat-a']);

    /** @var Illuminate\Support\Collection<int, Statamic\Entries\Entry> $matched */
    $matched = $query->get();
    $ids = $matched->map(fn ($entry) => $entry->id())->sort()->values()->all();

    expect($ids)->toBe(['p1', 'p2']);
});

it('does not itself route the bare /category prefix (no collection index)', function () {
    makePostCategoriesCollection();
    makeCategory('cat-parent', 'basic-sciences', 'Basic Sciences');
    /** @var Statamic\Entries\Collection $collection */
    $collection = Collection::find('post_categories');
    /** @var CollectionStructure $structure */
    $structure = $collection->structure();
    /** @var Tree $tree */
    $tree = $structure->makeTree('default', [['entry' => 'cat-parent']]);
    $tree->save();

    // The literal `category/` prefix routes only `/category/{slug}` — no entry
    // resolves at the bare `/category` path, so Statamic 404s it (a URL namespace,
    // not an index). UAMS-Web/uams-statamic-archive#1435 adds a browse-all index there as a separate `pages`
    // entry, decoupled from this collection.
    expect(Entry::findByUri('/category', 'default'))->toBeNull();
});
