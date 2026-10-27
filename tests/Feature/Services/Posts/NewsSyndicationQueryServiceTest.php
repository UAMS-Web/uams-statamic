<?php

declare(strict_types=1);

use App\Services\Posts\NewsSyndicationQueryService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Structures\CollectionStructure;
use Statamic\Structures\CollectionTree;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Behavioral coverage for {@see NewsSyndicationQueryService}, the query core of
 * the UAMS News page-builder block (UAMS-Web/uams-statamic-archive#76).
 *
 * The content snapshot ships zero `posts` entries, so every case builds its own
 * fixtures in an isolated Stache (real collections/entries, throwaway disk). This
 * exercises the actual cross-site `whereIn('site')`, published-status,
 * `whereJsonOverlaps` category filter (post_categories is a structured collection
 * since UAMS-Web/uams-statamic-archive#1433, referenced by entry id), date ordering, and offset/limit paths —
 * not a mock of them.
 *
 * @covers \App\Services\Posts\NewsSyndicationQueryService
 *
 * @command  php artisan test --compact tests/Feature/Services/Posts/NewsSyndicationQueryServiceTest.php
 */
uses(StacheIsolatedTestCase::class);

/** Resolve the service under test from the container. */
function newsSvc(): NewsSyndicationQueryService
{
    return app(NewsSyndicationQueryService::class);
}

/**
 * Create a `post_categories` collection entry with the given id/slug/title, and
 * (for a subcategory) its parent id recorded in data — the shape the importer/CP
 * writes (mirrors PostCategoryArchiveTest).
 */
function newsSvcCategory(string $id, string $slug, string $title, ?string $parent = null): void
{
    $data = ['title' => $title];
    if ($parent !== null) {
        $data['parent'] = $parent;
    }

    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->collection('post_categories');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->id($id);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->slug($slug);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->published(true);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->data($data);
    $entry->save();
}

/**
 * Persist a fixture post (bypassing listeners) with a date, status, category
 * entry-id associations, and site — the four dimensions the service
 * filters/orders on.
 *
 * @param  array<int, string>  $categories  `post_categories` entry ids to associate.
 */
function newsSvcPost(
    string $id,
    string $site,
    string $date,
    bool $published,
    array $categories = [],
    string $collection = 'posts'
): EntryContract {
    $data = ['title' => 'Post '.$id];
    if ($categories !== []) {
        $data['post_categories'] = $categories;
    }

    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->collection($collection);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->id($id);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->slug($id);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->locale($site);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->published($published);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->data($data);

    // Only dated collections accept a date (setting one on `pages` throws).
    if (CollectionFacade::findByHandle($collection)?->dated()) {
        $entry->date(Carbon::parse($date));
    }

    $entry->saveQuietly();

    return $entry;
}

/**
 * The standard fixture set used by the query/findArticle cases (categories by
 * entry id):
 *   A  news    2024-01-01  published  [cat-cardiology]
 *   B  news    2024-03-01  published  [cat-oncology]
 *   C  news    2024-02-01  DRAFT      [cat-cardiology]
 *   D  default 2024-04-01  published  [cat-cardiology]
 */
function newsSvcSeed(): void
{
    newsSvcPost('a', 'news', '2024-01-01', true, ['cat-cardiology']);
    newsSvcPost('b', 'news', '2024-03-01', true, ['cat-oncology']);
    newsSvcPost('c', 'news', '2024-02-01', false, ['cat-cardiology']);
    newsSvcPost('d', 'default', '2024-04-01', true, ['cat-cardiology']);
}

/**
 * Map a result collection to entry ids for order-sensitive assertions.
 *
 * @param  Collection<int, Statamic\Entries\Entry>  $results
 * @return array<int, string>
 */
function newsSvcIds(Collection $results): array
{
    return $results->map(function (Statamic\Entries\Entry $entry): string {
        /** @var string $id */
        $id = $entry->id();

        return (string) $id;
    })->all();
}

beforeEach(function (): void {
    // The `post_categories` structured collection (UAMS-Web/uams-statamic-archive#1433) — routed so entries
    // have archive URLs, structured so a parent surfaces descendants.
    /** @var Statamic\Entries\Collection $postCategories */
    $postCategories = CollectionFacade::make('post_categories');
    /** @var Statamic\Entries\Collection $postCategories */
    $postCategories = $postCategories->title('Post Categories');
    /** @var Statamic\Entries\Collection $postCategories */
    $postCategories = $postCategories->sites(['default']);
    /** @var Statamic\Entries\Collection $postCategories */
    $postCategories = $postCategories->routes("{{ depth == 1 ?= 'category' }}/{{ parent_uri }}/{{ slug }}");
    /** @var Statamic\Entries\Collection $postCategories */
    $postCategories = $postCategories->structureContents(['root' => false]);
    $postCategories->save();

    // The `posts` collection routed on the default + news sites, dated. Its
    // category field is an entries reference to the post_categories collection.
    /** @var Statamic\Entries\Collection $posts */
    $posts = CollectionFacade::make('posts');
    /** @var Statamic\Entries\Collection $posts */
    $posts = $posts->title('Posts');
    /** @var Statamic\Entries\Collection $posts */
    $posts = $posts->sites(['default', 'news']);
    /** @var Statamic\Entries\Collection $posts */
    $posts = $posts->dated(true);
    $posts->save();

    /** @var Statamic\Fields\Blueprint $postBlueprint */
    $postBlueprint = Blueprint::makeFromFields([
        'title' => ['type' => 'text'],
        'post_categories' => [
            'type' => 'entries',
            'collections' => ['post_categories'],
            'mode' => 'select',
        ],
        // The cover-image controls the require-featured-image filter reads (UAMS-Web/uams-statamic-archive#1154).
        // The `side` layout falls back to the 4:3 asset, so the filter is layout-aware.
        'cover_image_query' => ['type' => 'toggle'],
        'featured_image_16_9' => ['type' => 'text'],
        'featured_image_4_3' => ['type' => 'text'],
    ]);
    /** @var Statamic\Fields\Blueprint $postBlueprint */
    $postBlueprint = $postBlueprint->setHandle('post');
    /** @var Statamic\Fields\Blueprint $postBlueprint */
    $postBlueprint = $postBlueprint->setNamespace('collections.posts');
    $postBlueprint->save();

    // A second collection so findArticle can prove it rejects a non-posts entry.
    /** @var Statamic\Entries\Collection $pages */
    $pages = CollectionFacade::make('pages');
    /** @var Statamic\Entries\Collection $pages */
    $pages = $pages->title('Pages');
    /** @var Statamic\Entries\Collection $pages */
    $pages = $pages->sites(['default']);
    $pages->save();

    // Categories: Cardiology > Interventional > Structural Heart (three levels,
    // for grandchild-descent coverage) and Oncology.
    newsSvcCategory('cat-cardiology', 'cardiology', 'Cardiology');
    newsSvcCategory('cat-interventional', 'interventional', 'Interventional Cardiology', 'cat-cardiology');
    newsSvcCategory('cat-structural', 'structural-heart', 'Structural Heart', 'cat-interventional');
    newsSvcCategory('cat-oncology', 'oncology', 'Oncology');

    /** @var Statamic\Entries\Collection $postCategoriesCollection */
    $postCategoriesCollection = CollectionFacade::find('post_categories');
    /** @var CollectionStructure $structure */
    $structure = $postCategoriesCollection->structure();
    /** @var CollectionTree $tree */
    $tree = $structure->makeTree('default', [
        ['entry' => 'cat-cardiology', 'children' => [
            ['entry' => 'cat-interventional', 'children' => [
                ['entry' => 'cat-structural'],
            ]],
        ]],
        ['entry' => 'cat-oncology'],
    ]);
    $tree->save();
});

// ---------------------------------------------------------------------------
// resolveSiteHandles
// ---------------------------------------------------------------------------

it('defaults an empty source selection to the news site', function (): void {
    expect(newsSvc()->resolveSiteHandles([], 'default'))->toBe(['news']);
});

it('maps the current token to the current site handle', function (): void {
    expect(newsSvc()->resolveSiteHandles(['current'], 'default'))->toBe(['default']);
});

it('keeps both news and the current site when both are selected', function (): void {
    expect(newsSvc()->resolveSiteHandles(['news', 'current'], 'default'))->toBe(['news', 'default']);
});

it('drops a selected site that does not exist', function (): void {
    expect(newsSvc()->resolveSiteHandles(['news', 'does_not_exist'], 'default'))->toBe(['news']);
});

it('de-duplicates repeated site selections', function (): void {
    expect(newsSvc()->resolveSiteHandles(['news', 'news'], 'default'))->toBe(['news']);
});

it('drops the current token when the current handle is empty', function (): void {
    // A blank current handle must not resolve to an existing site, so it is filtered.
    expect(newsSvc()->resolveSiteHandles(['current'], ''))->toBe([]);
});

it('returns no handles when every selection is invalid, rather than falling back', function (): void {
    // A non-empty-but-invalid selection stays empty (the query then returns nothing)
    // instead of silently defaulting to news, which would surprise the editor.
    expect(newsSvc()->resolveSiteHandles(['bogus'], 'default'))->toBe([]);
});

// ---------------------------------------------------------------------------
// query
// ---------------------------------------------------------------------------

it('returns only published posts for the selected site, newest first', function (): void {
    newsSvcSeed();

    // News site only: B (Mar) then A (Jan). Draft C and default-site D excluded.
    expect(newsSvcIds(newsSvc()->query(['news'], [], 0, 0)))->toBe(['b', 'a']);
});

it('merges posts across selected sites, ordered by date descending', function (): void {
    newsSvcSeed();

    // news + default merged: D (Apr), B (Mar), A (Jan). Draft C still excluded.
    expect(newsSvcIds(newsSvc()->query(['news', 'default'], [], 0, 0)))->toBe(['d', 'b', 'a']);
});

it('filters by a single category', function (): void {
    newsSvcSeed();

    // News + cardiology: only A (B is oncology, C is a draft).
    expect(newsSvcIds(newsSvc()->query(['news'], ['cat-cardiology'], 0, 0)))->toBe(['a']);
});

it('filters by category across multiple sites', function (): void {
    newsSvcSeed();

    // news + default, cardiology: D (Apr) then A (Jan).
    expect(newsSvcIds(newsSvc()->query(['news', 'default'], ['cat-cardiology'], 0, 0)))->toBe(['d', 'a']);
});

it('includes posts filed under a descendant category when filtering the parent', function (): void {
    newsSvcSeed();

    // E is filed under the child category (Interventional) only. Filtering the
    // parent (Cardiology) must still surface it via descendant expansion.
    newsSvcPost('e', 'news', '2024-05-01', true, ['cat-interventional']);

    // News + cardiology: E (May, child) then A (Jan, parent). B oncology, C draft excluded.
    expect(newsSvcIds(newsSvc()->query(['news'], ['cat-cardiology'], 0, 0)))->toBe(['e', 'a']);
});

it('includes posts filed under a grandchild category when filtering the root', function (): void {
    newsSvcSeed();

    // G is filed under the grandchild (Structural Heart) only. Filtering the root
    // (Cardiology) must reach it through two levels of descent — a single-level
    // expansion would drop it.
    newsSvcPost('g', 'news', '2024-06-01', true, ['cat-structural']);

    // News + cardiology subtree: G (Jun, grandchild) then A (Jan, root).
    expect(newsSvcIds(newsSvc()->query(['news'], ['cat-cardiology'], 0, 0)))->toBe(['g', 'a']);
});

it('terminates on a category parent cycle without looping or duplicating', function (): void {
    // Defensive: malformed data where two categories point at each other. The
    // visited-set guard must terminate with a finite, de-duplicated target set.
    newsSvcCategory('cat-loop-a', 'loop-a', 'Loop A', 'cat-loop-b');
    newsSvcCategory('cat-loop-b', 'loop-b', 'Loop B', 'cat-loop-a');
    newsSvcPost('la', 'news', '2024-01-01', true, ['cat-loop-b']);

    // Filtering A reaches B (its cyclic child) once and stops — no hang, and the
    // post filed under B surfaces exactly once.
    expect(newsSvcIds(newsSvc()->query(['news'], ['cat-loop-a'], 0, 0)))->toBe(['la']);
});

it('matches a post in any of several selected categories (OR semantics)', function (): void {
    newsSvcSeed();

    // cardiology OR oncology on news: B (Mar, oncology) then A (Jan, cardiology).
    expect(newsSvcIds(newsSvc()->query(['news'], ['cat-cardiology', 'cat-oncology'], 0, 0)))->toBe(['b', 'a']);
});

it('caps the result set to the requested limit', function (): void {
    newsSvcSeed();

    // Two newest across both sites: D, B.
    expect(newsSvcIds(newsSvc()->query(['news', 'default'], [], 2, 0)))->toBe(['d', 'b']);
});

it('skips the requested offset before applying the limit', function (): void {
    newsSvcSeed();

    // Offset 1 then two: skip D, take B, A.
    expect(newsSvcIds(newsSvc()->query(['news', 'default'], [], 2, 1)))->toBe(['b', 'a']);
});

it('returns nothing for an empty site list rather than an unscoped query', function (): void {
    newsSvcSeed();

    // No sites → empty, never every site's posts.
    expect(newsSvc()->query([], [], 0, 0)->all())->toBe([]);
});

// ---------------------------------------------------------------------------
// findArticle
// ---------------------------------------------------------------------------

it('finds a published posts entry by id', function (): void {
    newsSvcSeed();

    /** @var Statamic\Entries\Entry $entry */
    $entry = newsSvc()->findArticle('a');

    /** @var string $entryId */
    $entryId = $entry->id();

    expect($entry)->not->toBeNull()
        ->and((string) $entryId)->toBe('a');
});

it('does not return a draft entry from findArticle', function (): void {
    newsSvcSeed();

    // C is published=false.
    expect(newsSvc()->findArticle('c'))->toBeNull();
});

it('does not return an entry from a different collection', function (): void {
    newsSvcPost('page-1', 'default', '2024-01-01', true, [], 'pages');

    expect(newsSvc()->findArticle('page-1'))->toBeNull();
});

it('returns null from findArticle for an unknown id', function (): void {
    expect(newsSvc()->findArticle('nope'))->toBeNull();
});

it('returns null from findArticle for a null or blank id', function (): void {
    expect(newsSvc()->findArticle(null))->toBeNull()
        ->and(newsSvc()->findArticle(''))->toBeNull()
        ->and(newsSvc()->findArticle('   '))->toBeNull();
});

// ---------------------------------------------------------------------------
// resolveCategoryLink
// ---------------------------------------------------------------------------

it('returns a null name and url when no category is given', function (): void {
    expect(newsSvc()->resolveCategoryLink([]))->toBe(['name' => null, 'url' => null]);
});

it('resolves the category display title and archive url from the entry', function (): void {
    $link = newsSvc()->resolveCategoryLink(['cat-cardiology']);

    /** @var Statamic\Entries\Entry|null $cardiology */
    $cardiology = Entry::find('cat-cardiology');

    expect($link['name'])->toBe('Cardiology')
        ->and($link['url'])->toBe($cardiology?->url())
        // The archive route now exists (UAMS-Web/uams-statamic-archive#1433), so the CTA has a real target.
        ->and($link['url'])->not->toBeNull();
});

it('returns a null name and url for an unknown category id', function (): void {
    expect(newsSvc()->resolveCategoryLink(['made-up']))->toBe(['name' => null, 'url' => null]);
});

it('returns null when the first id is not a post_categories entry', function (): void {
    // A stray posts id must not resolve as a category link.
    newsSvcSeed();

    expect(newsSvc()->resolveCategoryLink(['a']))->toBe(['name' => null, 'url' => null]);
});

it('uses the first category for the link when several are given', function (): void {
    $link = newsSvc()->resolveCategoryLink(['cat-cardiology', 'cat-oncology']);

    /** @var Statamic\Entries\Entry|null $cardiology */
    $cardiology = Entry::find('cat-cardiology');

    expect($link['name'])->toBe('Cardiology')
        ->and($link['url'])->toBe($cardiology?->url());
});

it('strips tags and entity-encodes a category title for the CTA name (XSS-safe)', function (): void {
    // The CTA name renders raw in block text + aria-labels, so a markup-bearing
    // category title must not break out — strip tags then entity-encode.
    newsSvcCategory('cat-evil', 'evil', 'Heart & <b>Soul</b><img src=x onerror=alert(1)>');

    $link = newsSvc()->resolveCategoryLink(['cat-evil']);

    expect($link['name'])->toBe('Heart &amp; Soul')
        ->and($link['name'])->not->toContain('<')
        ->and($link['name'])->not->toContain('onerror');
});

// ---------------------------------------------------------------------------
// require-featured-image filter (UAMS-Web/uams-statamic-archive#1154)
// ---------------------------------------------------------------------------

/**
 * Persist a published news post carrying the cover-image controls the
 * require-featured-image filter reads. `$hasImage` sets the 16:9 asset every
 * image-led layout renders; `$has4x3` sets the 4:3 asset only the `side` layout
 * falls back to.
 */
function newsSvcCoverPost(string $id, string $date, bool $coverQuery, bool $hasImage, bool $has4x3 = false): void
{
    $data = ['title' => 'Post '.$id, 'cover_image_query' => $coverQuery];
    if ($hasImage) {
        $data['featured_image_16_9'] = $id.'-featured.jpg';
    }
    if ($has4x3) {
        $data['featured_image_4_3'] = $id.'-4x3.jpg';
    }

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
    $entry = $entry->data($data);
    $entry->saveQuietly();
}

it('keeps only posts that would render a cover image when required', function (): void {
    newsSvcCoverPost('with', '2024-04-01', coverQuery: true, hasImage: true);
    newsSvcCoverPost('no-image', '2024-03-01', coverQuery: true, hasImage: false);
    newsSvcCoverPost('cover-off', '2024-02-01', coverQuery: false, hasImage: true);

    $results = newsSvc()->query(['news'], [], 0, 0, requireFeaturedImage: true);

    expect(newsSvcIds($results))->toBe(['with']);
});

it('returns every post regardless of cover image when not required', function (): void {
    newsSvcCoverPost('with', '2024-04-01', coverQuery: true, hasImage: true);
    newsSvcCoverPost('no-image', '2024-03-01', coverQuery: true, hasImage: false);

    $results = newsSvc()->query(['news'], [], 0, 0, requireFeaturedImage: false);

    expect(newsSvcIds($results))->toBe(['with', 'no-image']);
});

it('applies count and offset after the require-featured-image filter, never shorting the count', function (): void {
    // Two image-less posts sit between image-bearing ones by date; the filter
    // must remove them BEFORE paging so a count of 2 returns 2 image-bearing posts.
    newsSvcCoverPost('img-1', '2024-05-01', coverQuery: true, hasImage: true);
    newsSvcCoverPost('gap-1', '2024-04-01', coverQuery: true, hasImage: false);
    newsSvcCoverPost('gap-2', '2024-03-01', coverQuery: true, hasImage: false);
    newsSvcCoverPost('img-2', '2024-02-01', coverQuery: true, hasImage: true);

    $results = newsSvc()->query(['news'], [], 2, 0, requireFeaturedImage: true);

    expect(newsSvcIds($results))->toBe(['img-1', 'img-2']);
});

it('keeps a side-layout post that has only a 4:3 cover, mirroring the side render guard', function (): void {
    // The `side` layout falls back to the 4:3 asset (a state reachable via
    // migrated content, where the 16:9 `required` validation never ran), so a
    // post with a 4:3 but no 16:9 still renders an image and must survive.
    newsSvcCoverPost('four-three-only', '2024-04-01', coverQuery: true, hasImage: false, has4x3: true);

    $results = newsSvc()->query(['news'], [], 0, 0, requireFeaturedImage: true, format: 'side');

    expect(newsSvcIds($results))->toBe(['four-three-only']);
});

it('drops a 4:3-only post for the image-led layouts that render the 16:9 only', function (): void {
    // cards/grid render the 16:9 asset only, so a 4:3-only post would render an
    // empty slot — the layout-aware filter must still remove it (only `side` keeps it).
    newsSvcCoverPost('four-three-only', '2024-04-01', coverQuery: true, hasImage: false, has4x3: true);
    newsSvcCoverPost('sixteen-nine', '2024-03-01', coverQuery: true, hasImage: true);

    $results = newsSvc()->query(['news'], [], 0, 0, requireFeaturedImage: true, format: 'cards');

    expect(newsSvcIds($results))->toBe(['sixteen-nine']);
});

it('excludes the given ids from the results', function (): void {
    newsSvcSeed();

    // Published news posts newest-first are [b, a] (c is a draft, d is on the
    // default site); the springboard's accumulator (UAMS-Web/uams-statamic-archive#1158) excludes b, leaving a.
    $results = newsSvc()->query(['news'], [], 0, 0, excludeIds: ['b']);

    expect(newsSvcIds($results))->toBe(['a']);
});

it('applies no exclusion for an empty exclude list', function (): void {
    newsSvcSeed();

    // The single-block path passes no exclusions, so both published news posts remain.
    $results = newsSvc()->query(['news'], [], 0, 0, excludeIds: []);

    expect(newsSvcIds($results))->toBe(['b', 'a']);
});
