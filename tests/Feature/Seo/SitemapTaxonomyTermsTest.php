<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use MityDigital\Sitemapamic\Data\Source;
use MityDigital\Sitemapamic\Models\SitemapamicUrl;
use MityDigital\Sitemapamic\Support\Configuration;
use MityDigital\Sitemapamic\Support\Content\SourceResolver;
use MityDigital\Sitemapamic\Support\Content\UrlIterator;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Stache\Query\EntryQueryBuilder;
use Statamic\Structures\CollectionStructure;
use Statamic\Structures\Tree;
use Tests\Support\ContentSubmodule;
use Tests\Support\EntriesTermsAndTreesIsolatedTestCase;

// Entries, terms and trees isolated (#557): the emission test places a `post_tags` entry in its collection's
// structure tree, and a tree write needs a root no sibling worker shares.
uses(EntriesTermsAndTreesIsolatedTestCase::class);

/*
|--------------------------------------------------------------------------
| Sitemap tag and term archive URLs (UAMS-Web/uams-statamic-archive#1346, UAMS-Web/uams-statamic-archive#2532)
|--------------------------------------------------------------------------
|
| Once UAMS-Web/uams-statamic-archive#1145/UAMS-Web/uams-statamic-archive#1410 shipped the public `post_categories` / `post_tags` archives,
| the app sitemap emitted their URLs through Sitemapamic's global-taxonomies path
| (config/sitemapamic.php, `taxonomies` since Sitemapamic 4). Both have since become
| collections — `post_categories` in UAMS-Web/uams-statamic-archive#1433/UAMS-Web/uams-statamic-archive#1434, `post_tags` in UAMS-Web/uams-statamic-archive#2532 — so both
| are emitted as collection sources instead, and the taxonomies list is empty. These tests keep the original acceptance
| criteria: a tag archive URL is present, and a vocabulary without a public
| archive (now the routeless `subject_matter` collection) never is.
|
| Two layers: a hermetic config check that always runs, and a behavioral check
| over Sitemapamic's real URL output (skipped without the content submodule, which holds
| the collection config).
|
| @command php artisan test --compact tests/Feature/Seo/SitemapTaxonomyTermsTest.php
*/

/**
 * A collection with no route, so it has no public URLs and must never be sitemapped.
 * It is absent from `sitemapamic.collections` and `sitemapamic.taxonomies`, so Sitemapamic
 * resolves no source for it.
 */
const SITEMAP_ROUTELESS_COLLECTION = 'subject_matter';

/** Slug prefix for self-provisioned post_tags entries, so teardown is bulletproof. */
const SITEMAP_POST_TAGS_TEST_PREFIX = 'zz-sitemap-posttags-test-';

/**
 * Remove every self-provisioned post_tags test entry (keeps other 0-entries gates valid).
 *
 * Quiet save and delete, for the reason `TaxonomyTermArchiveTest` records (UAMS-Web/uams-statamic-archive#1634):
 * the saved/deleted events dispatch `Statamic\Search\UpdateItemIndexes`, a
 * `ShouldQueue` listener that runs inline under the suite's sync queue across every
 * local search index. Measured on UAMS-Web/uams-statamic-archive#2291 (macOS, 2026-09-16) for the taxonomy-term
 * version of this test: 121.77s with events, 2.50s without. Nothing here is
 * search-driven: the assertions read Sitemapamic's URL output, not an index.
 */
function tearDownSitemapPostTagsFixtures(): void
{
    // Content-less checkouts have no post_tags collection; the content-independent
    // tests in this file must still run there.
    if (! CollectionFacade::find('post_tags')) {
        return;
    }

    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->where('collection', 'post_tags');

    /** @var Collection<int, Statamic\Entries\Entry> $tags */
    $tags = $query->get();

    $tags
        ->filter(fn (Statamic\Entries\Entry $tag): bool => is_string($tag->slug()) && str_starts_with($tag->slug(), SITEMAP_POST_TAGS_TEST_PREFIX))
        ->each(fn (Statamic\Entries\Entry $tag) => $tag->deleteQuietly());
}

beforeEach(fn () => tearDownSitemapPostTagsFixtures());
afterEach(fn () => tearDownSitemapPostTagsFixtures());

it('emits tag archives through the collection path, and no taxonomy through the taxonomies path', function (): void {
    $configuration = app(Configuration::class);

    // Empty, read through Sitemapamic's own resolution: an absent key would fall back
    // to the package default of every taxonomy.
    expect(config('sitemapamic.taxonomies'))->toBe([])
        ->and($configuration->taxonomies())->toBe([]);

    // The tag archive is emitted as a collection's entries, with no contextual
    // taxonomy. Its `post_tags/show` view is guarded by TaxonomyTermArchiveTest,
    // and Larastan resolves it statically.
    expect($configuration->collections())->toHaveKey('post_tags')
        ->and($configuration->collections()['post_tags'])->toBe([]);

    // The routeless collection is not selected and has no public archive view.
    expect($configuration->collections())->not->toHaveKey(SITEMAP_ROUTELESS_COLLECTION)
        ->and(view()->exists(SITEMAP_ROUTELESS_COLLECTION.'/show'))->toBeFalse();
});

it('gives post_tags a collection source and the routeless collection none', function (): void {
    if (! CollectionFacade::find('post_tags')) {
        ContentSubmodule::reportMissing('post_tags collection config');
    }

    /** @var Statamic\Sites\Site $site */
    $site = Site::get('default');
    $sources = collect(app(SourceResolver::class)->resolve($site));

    // The collection's entries are a source of their own.
    expect($sources->contains(fn (Source $source): bool => $source->isCollection() && $source->collection === 'post_tags'))->toBeTrue();

    // The routeless collection resolves to no source at all, so none of its entries
    // can ever reach the sitemap. Sitemapamic skips a collection with no route even
    // when it is configured, so this holds by routability; the config side is
    // asserted by the test above.
    expect($sources->contains(fn (Source $source): bool => $source->collection === SITEMAP_ROUTELESS_COLLECTION))->toBeFalse();
});

it('emits a self-provisioned post_tags archive loc, with no routeless-collection leak', function (): void {
    if (! CollectionFacade::find('post_tags')) {
        ContentSubmodule::reportMissing('post_tags collection config');
    }

    // The shipping content submodule has zero post_tags entries, so self-provision one
    // (rather than skip) and assert Sitemapamic actually emits its archive loc.
    // Torn down by slug prefix so the sibling 0-entries/count gates stay valid.
    /** @var Statamic\Entries\Entry $tag */
    $tag = Entry::make();
    $tag->collection('post_tags');
    $tag->locale('default');
    $tag->slug(SITEMAP_POST_TAGS_TEST_PREFIX.'immunology');
    $tag->data(['title' => 'ZZ Immunology Tag']);
    $tag->saveQuietly();

    // Place it in the tree, as an import does. An entry saved without a place has
    // no URI in the index the router reads (SitemapStructureOrphansTest).
    /** @var Statamic\Entries\Collection $tagCollection */
    $tagCollection = CollectionFacade::findByHandle('post_tags');
    /** @var CollectionStructure $tagStructure */
    $tagStructure = $tagCollection->structure();
    /** @var Tree $tagTree */
    $tagTree = $tagStructure->in('default');
    $tagTree->tree([['entry' => $tag->id()]]);
    $tagTree->save();

    $locs = collect(app(UrlIterator::class)->forSource(new Source('default', collection: 'post_tags')))
        ->map(fn (SitemapamicUrl $url): string => $url->loc);

    expect($locs)->not->toBeEmpty()
        // The provisioned tag's archive loc is emitted, at the same /post-tags/ path
        // the taxonomy term page used.
        ->and($locs->contains(fn (string $loc): bool => str_contains($loc, '/post-tags/'.SITEMAP_POST_TAGS_TEST_PREFIX.'immunology')))->toBeTrue()
        // Every emitted loc is a post_tags archive URL...
        ->and($locs->every(fn (string $loc): bool => str_contains($loc, '/post-tags/')))->toBeTrue()
        // ...and no routeless collection's URLs leak into the emission.
        ->and($locs->contains(fn (string $loc): bool => str_contains($loc, '/subject-matter/')))->toBeFalse();
});
