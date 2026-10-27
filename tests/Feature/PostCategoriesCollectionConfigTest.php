<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Stache;
use Statamic\Facades\YAML;
use Statamic\Fields\Field;
use Statamic\Stache\Stores\Store;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Config/shape coverage for the UAMS-Web/uams-statamic-archive#1433 `post_categories` migration.
 *
 * The field-repoint assertions read the app-repo blueprints directly (always
 * present). The shipped-collection assertions read the `content/` submodule's
 * collection config and skip when it is absent (no submodule, or the app PR's
 * gitlink not yet bumped to the content companion) — mirroring the content-gated
 * assertions in ClinicalExpertiseRoutingTest / TaxonomyTermArchiveTest.
 *
 * @command  php artisan test --compact tests/Feature/PostCategoriesCollectionConfigTest.php
 */
it('repoints the posts post_categories field to a post_categories collection reference', function () {
    // The shared fieldset feeds post/audio/news_release; the effective post
    // blueprint resolves the import to the entries fieldtype.
    /** @var Statamic\Fields\Blueprint $postBlueprint */
    $postBlueprint = Blueprint::in('collections/posts')->get('post');
    /** @var Field $postCategory */
    $postCategory = $postBlueprint->field('post_categories');

    /** @var string $type */
    $type = $postCategory->type();
    expect($type)->toBe('entries');

    /** @var list<string> $collections */
    $collections = $postCategory->config()['collections'];
    expect($collections)->toBe(['post_categories']);

    expect($postCategory->config())->not->toHaveKey('taxonomies');
});

it('repoints the inline post_categories field on the video blueprint too', function () {
    // video.yaml defines the field inline (not via the shared fieldset), so it
    // needs its own repoint.
    /** @var Statamic\Fields\Blueprint $videoBlueprint */
    $videoBlueprint = Blueprint::in('collections/posts')->get('video');
    /** @var Field $videoCategory */
    $videoCategory = $videoBlueprint->field('post_categories');

    /** @var string $type */
    $type = $videoCategory->type();
    expect($type)->toBe('entries');

    /** @var list<string> $collections */
    $collections = $videoCategory->config()['collections'];
    expect($collections)->toBe(['post_categories']);
});

it('keeps the post_tags field on its own collection, apart from categories (bounds the change)', function () {
    // UAMS-Web/uams-statamic-archive#1433 migrated only categories and left tags a flat taxonomy. UAMS-Web/uams-statamic-archive#2532 later
    // converted tags too, into their own collection; this pins that the two
    // references stay separate rather than tags folding into post_categories.
    /** @var Statamic\Fields\Blueprint $postBlueprint */
    $postBlueprint = Blueprint::in('collections/posts')->get('post');
    /** @var Field $postTags */
    $postTags = $postBlueprint->field('post_tags');

    /** @var string $type */
    $type = $postTags->type();
    expect($type)->toBe('entries');

    /** @var list<string> $collections */
    $collections = $postTags->config()['collections'];
    expect($collections)->toBe(['post_tags']);
});

it('defines the post_categories structured collection with the hierarchical category route', function () {
    /** @var Store $store */
    $store = Stache::store('collections');
    /** @var string $directory */
    $directory = $store->directory();
    $path = $directory.'post_categories.yaml';

    if (! File::exists($path)) {
        ContentSubmodule::reportMissing('migrated post_categories collection (post-gitlink-bump)');
    }

    $config = YAML::parse(File::get($path));

    // Hierarchical URL via a literal `category` prefix + the structure's parent
    // chain — not a mount. The prefix is depth-gated (`depth == 1 ?= 'category'`)
    // so it is emitted once at the top level only; deeper entries inherit it
    // through `{parent_uri}` (which already carries it). A flat
    // `category/{parent_uri}/{slug}` would double the prefix on nested categories
    // (`/category/category/parent/child`) — the clinical_expertise precedent the
    // decision cites resolves this exact case the same way.
    expect($config['route'])->toBe("{{ depth == 1 ?= 'category' }}/{{ parent_uri }}/{{ slug }}");
    expect($config['structure'])->toBe(['root' => false]);
    expect($config)->not->toHaveKey('mount');
    expect($config['template'])->toBe('post_categories/show');
    // Categories are not dated (unlike posts) — no date behavior.
    expect($config)->not->toHaveKey('date');
    // Spans exactly the 33 sites the former taxonomy did.
    expect($config['sites'])->toHaveCount(33);
});

it('retires the flat post_categories taxonomy (hard replace on the shared handle)', function () {
    /** @var Store $collectionsStore */
    $collectionsStore = Stache::store('collections');
    /** @var string $collectionsDirectory */
    $collectionsDirectory = $collectionsStore->directory();
    $collectionPath = $collectionsDirectory.'post_categories.yaml';

    if (! File::exists($collectionPath)) {
        ContentSubmodule::reportMissing('migrated post_categories collection (post-gitlink-bump)');
    }

    // Once the collection exists, nothing may still reference the taxonomy model.
    /** @var Store $taxonomiesStore */
    $taxonomiesStore = Stache::store('taxonomies');
    /** @var string $taxonomiesDirectory */
    $taxonomiesDirectory = $taxonomiesStore->directory();
    $taxonomyPath = $taxonomiesDirectory.'post_categories.yaml';

    expect(File::exists($taxonomyPath))->toBeFalse();
});

it('sitemaps the post_categories collection, not the retired taxonomy path', function () {
    // The migrated `/category/…` archives are emitted via the collection `defaults`
    // path; leaving `post_categories` under `globals.taxonomies` would fatal
    // `/sitemap.xml` (Sitemapamic dereferences the now-null `Taxonomy::find(...)`).
    expect(config('sitemapamic.defaults.post_categories.include'))->toBeTrue();
    /** @var array<string, mixed> $globalTaxonomies */
    $globalTaxonomies = config('sitemapamic.globals.taxonomies', []);
    expect(array_keys($globalTaxonomies))->not->toContain('post_categories');
});
