<?php

declare(strict_types=1);

use MityDigital\Sitemapamic\Facades\Sitemapamic;
use MityDigital\Sitemapamic\Models\SitemapamicUrl;
use Statamic\Facades\Collection;
use Statamic\Facades\Data;
use Statamic\Facades\Entry;
use Statamic\Facades\Stache;
use Statamic\Stache\Query\EntryQueryBuilder;
use Statamic\Stache\Stores\Store;
use Statamic\Structures\CollectionStructure;
use Statamic\Structures\Tree;
use Tests\Support\CollectionTreeIsolatedTestCase;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;

uses(CollectionTreeIsolatedTestCase::class);

/**
 * End-to-end render coverage for the UAMS-Web/uams-statamic-archive#1434 category archive on the real
 * post-migration collection: the hierarchical surface (child-category list +
 * breadcrumb parent chain) and the collection-sourced post listing, rendered
 * through the full site layout.
 *
 * The in-memory tests ({@see PostCategoryArchiveTest}) prove the structure and
 * wiring but cannot exercise the archive's `:parent:is` child query or the
 * `nav:breadcrumbs` ancestry — both need disk-loaded content (the same reason
 * KnowledgeBaseDirectChildCountTest gates its `where('parent')` parity check on
 * the submodule). This creates a throwaway nested category tree on the default
 * site (the migrated terms live on science_reports / accardv, so the default
 * tree is ours to build and tear down — the ClinicalExpertiseRoutingTest
 * pattern) and renders it.
 *
 * @command  php artisan test --compact tests/Feature/PostCategoryArchiveRenderTest.php
 */
const PC_ARCHIVE_TEST_PREFIX = 'zz-pc-test-';

/** Absolute path to the default-site post_categories structure tree (test-authored). */
function postCategoryTestTreePath(): string
{
    /** @var Store $store */
    $store = Stache::store('collection-trees');

    return $store->directory().'default/post_categories.yaml';
}

/** Remove every test category/post and the test-authored default tree. */
function tearDownPostCategoryArchiveFixtures(): void
{
    foreach (['post_categories', 'posts'] as $collection) {
        /** @var EntryQueryBuilder $query */
        $query = Entry::query();
        /** @var EntryQueryBuilder $query */
        $query = $query->where('collection', $collection);
        /** @var Illuminate\Support\Collection<int, Statamic\Entries\Entry> $entries */
        $entries = $query->get();
        $entries
            ->filter(function (Statamic\Entries\Entry $e): bool {
                /** @var string|null $slug */
                $slug = $e->slug();

                return str_starts_with((string) $slug, PC_ARCHIVE_TEST_PREFIX);
            })
            ->each(fn (Statamic\Entries\Entry $e) => $e->delete());
    }

    if (file_exists(postCategoryTestTreePath())) {
        @unlink(postCategoryTestTreePath());
    }
}

beforeEach(function (): void {
    if (! Collection::findByHandle('post_categories')) {
        ContentSubmodule::reportMissing('migrated post_categories collection');
    }

    tearDownPostCategoryArchiveFixtures();
});

afterEach(fn () => tearDownPostCategoryArchiveFixtures());

it('renders the child-category list, breadcrumb parent chain, and collection-sourced posts', function (): void {
    /** @var Statamic\Entries\Entry $parent */
    $parent = Entry::make();
    /** @var Statamic\Entries\Entry $parent */
    $parent = $parent->collection('post_categories');
    /** @var Statamic\Entries\Entry $parent */
    $parent = $parent->locale('default');
    /** @var Statamic\Entries\Entry $parent */
    $parent = $parent->slug(PC_ARCHIVE_TEST_PREFIX.'parent');
    /** @var Statamic\Entries\Entry $parent */
    $parent = $parent->data(['title' => 'Test Parent Category']);
    $parent->save();

    /** @var Statamic\Entries\Entry $child */
    $child = Entry::make();
    /** @var Statamic\Entries\Entry $child */
    $child = $child->collection('post_categories');
    /** @var Statamic\Entries\Entry $child */
    $child = $child->locale('default');
    /** @var Statamic\Entries\Entry $child */
    $child = $child->slug(PC_ARCHIVE_TEST_PREFIX.'child');
    /** @var Statamic\Entries\Entry $child */
    $child = $child->data(['title' => 'Test Child Category', 'parent' => $parent->id()]);
    $child->save();

    /** @var Statamic\Entries\Collection $parentCollection */
    $parentCollection = Collection::findByHandle('post_categories');
    /** @var CollectionStructure $parentStructure */
    $parentStructure = $parentCollection->structure();
    /** @var Tree $parentTree */
    $parentTree = $parentStructure->in('default');
    /** @var Tree $parentTree */
    $parentTree = $parentTree->tree([
        ['entry' => $parent->id(), 'children' => [['entry' => $child->id()]]],
    ]);
    $parentTree->save();

    // A published post filed under the child category via the entries reference.
    /** @var Statamic\Entries\Entry $post */
    $post = Entry::make();
    /** @var Statamic\Entries\Entry $post */
    $post = $post->collection('posts');
    /** @var Statamic\Entries\Entry $post */
    $post = $post->locale('default');
    /** @var Statamic\Entries\Entry $post */
    $post = $post->slug(PC_ARCHIVE_TEST_PREFIX.'post');
    /** @var Statamic\Entries\Entry $post */
    $post = $post->published(true);
    /** @var Statamic\Entries\Entry $post */
    $post = $post->date('2026-01-02');
    /** @var Statamic\Entries\Entry $post */
    $post = $post->data(['title' => 'Test Category Post', 'post_categories' => [$child->id()]]);
    $post->save();

    // The parent archive surfaces its child category (the `:parent:is` query).
    /** @var TestCase $test */
    $test = $this->withoutVite();
    /** @var string $parentUrl */
    $parentUrl = $parent->url();
    /** @var string $parentHtml */
    $parentHtml = $test->get($parentUrl)->assertOk()->getContent();
    expect($parentHtml)->toContain('Test Child Category');

    // UAMS-Web/uams-statamic-archive#1434 reuses UAMS-Web/uams-statamic-archive#1171's descriptive term-archive title: the migrated category
    // *collection* archive names the category in BOTH the descriptive <h1> and the
    // <head> <title> (not the bare entry title), for the collection entry just as the
    // flat taxonomy did at /post-categories/{slug}. The <title> assertion is the
    // regression guard — without the layout's post_categories branch it falls to the
    // bare-entry-title else branch ("<title>Test Parent Category").
    expect($parentHtml)
        ->toContain('<title>Posts in Category Test Parent Category | ') // descriptive <head> title
        ->toContain('Posts in Category Test Parent Category');          // descriptive <h1>, end-to-end

    // The child archive lists the post through the `post_categories` entries
    // reference (not a taxonomy term).
    /** @var TestCase $test */
    $test = $this->withoutVite();
    /** @var string $childUrl */
    $childUrl = $child->url();
    /** @var string $childHtml */
    $childHtml = $test->get($childUrl)->assertOk()->getContent();
    expect($childHtml)->toContain('Test Category Post');

    // The parent chain the layout's `nav:breadcrumbs` walks: it resolves each URL
    // segment with `Data::findByUri`, so proving the parent resolves by its own URI
    // proves the crumb ancestry the structured collection now supplies (the flat
    // taxonomy had none). The breadcrumb `<nav>` itself is standard site chrome that
    // the root/default site suppresses by pre-existing design (a real
    // /welcome-to-uams/about-uams page renders none either), so it is asserted via
    // the resolution the crumb depends on rather than root-site HTML that omits it.
    /** @var string $parentUri */
    $parentUri = $parent->uri();
    /** @var Statamic\Entries\Entry|null $resolvedParent */
    $resolvedParent = Data::findByUri($parentUri, 'default');
    expect($resolvedParent?->id())->toBe($parent->id());
})->group('serial');

it('emits the migrated category archive loc via the collection defaults sitemap path', function (): void {
    /** @var Statamic\Entries\Entry $category */
    $category = Entry::make();
    /** @var Statamic\Entries\Entry $category */
    $category = $category->collection('post_categories');
    /** @var Statamic\Entries\Entry $category */
    $category = $category->locale('default');
    /** @var Statamic\Entries\Entry $category */
    $category = $category->published(true);
    /** @var Statamic\Entries\Entry $category */
    $category = $category->slug(PC_ARCHIVE_TEST_PREFIX.'sitemap-cat');
    /** @var Statamic\Entries\Entry $category */
    $category = $category->data(['title' => 'Sitemap Test Category']);
    $category->save();

    /** @var Statamic\Entries\Collection $categoryCollection */
    $categoryCollection = Collection::findByHandle('post_categories');
    /** @var CollectionStructure $categoryStructure */
    $categoryStructure = $categoryCollection->structure();
    /** @var Tree $categoryTree */
    $categoryTree = $categoryStructure->in('default');
    /** @var Tree $categoryTree */
    $categoryTree = $categoryTree->tree([
        ['entry' => $category->id()],
    ]);
    $categoryTree->save();

    // The migrated collection is emitted via the `defaults` collection path
    // (config/sitemapamic.php), at its hierarchical `/category/…` URL — not the
    // retired flat-taxonomy globals path (UAMS-Web/uams-statamic-archive#1486). The `defaults` loader is keyed by
    // collection handle and gated by the shared inclusion computed.
    /** @var callable(): Illuminate\Support\Collection<int, SitemapamicUrl> $loader */
    $loader = Sitemapamic::getLoaders()->get('post_categories');
    $locs = collect($loader())
        ->map(fn (SitemapamicUrl $url): string => $url->loc);

    expect($locs->contains(fn (string $loc): bool => str_contains($loc, '/category/'.PC_ARCHIVE_TEST_PREFIX.'sitemap-cat')))->toBeTrue();
})->group('serial');

it('strips and entity-encodes a markup-bearing category title in the document <title>', function (): void {
    // A category is a collection entry (not a taxonomy term), so the layout <title>
    // takes the non-taxonomy `{{ else }}` branch that Antlers renders raw — the
    // category title (editor/importer input) must be escaped there like the h1 is.
    /** @var Statamic\Entries\Entry $category */
    $category = Entry::make();
    /** @var Statamic\Entries\Entry $category */
    $category = $category->collection('post_categories');
    /** @var Statamic\Entries\Entry $category */
    $category = $category->locale('default');
    /** @var Statamic\Entries\Entry $category */
    $category = $category->slug(PC_ARCHIVE_TEST_PREFIX.'xss');
    /** @var Statamic\Entries\Entry $category */
    $category = $category->data(['title' => 'Onc & Heme </title><script>alert(1)</script>']);
    $category->save();

    /** @var Statamic\Entries\Collection $categoryCollection */
    $categoryCollection = Collection::findByHandle('post_categories');
    /** @var CollectionStructure $categoryStructure */
    $categoryStructure = $categoryCollection->structure();
    /** @var Tree $categoryTree */
    $categoryTree = $categoryStructure->in('default');
    /** @var Tree $categoryTree */
    $categoryTree = $categoryTree->tree([
        ['entry' => $category->id()],
    ]);
    $categoryTree->save();

    /** @var TestCase $test */
    $test = $this->withoutVite();
    /** @var string $categoryUrl */
    $categoryUrl = $category->url();
    /** @var string $html */
    $html = $test->get($categoryUrl)->assertOk()->getContent();

    // the injected script is stripped, cannot execute
    expect($html)->not->toContain('<script>alert(1)</script>');
    // no <title> break-out
    expect($html)->not->toContain('</title><script>');
    // the benign text survives, `&` entity-encoded
    expect($html)->toContain('Onc &amp; Heme');
})->group('serial');
