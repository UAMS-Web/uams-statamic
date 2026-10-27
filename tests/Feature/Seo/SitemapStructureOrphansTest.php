<?php

declare(strict_types=1);

use App\Services\ComputedFields\Registration\SitemapInclusionComputedsRegistrar as Inclusion;
use Illuminate\Support\Collection;
use MityDigital\Sitemapamic\Facades\Sitemapamic;
use MityDigital\Sitemapamic\Models\SitemapamicUrl;
use Statamic\Facades\Blink;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\Stache;
use Statamic\Facades\YAML;
use Statamic\Stache\Query\EntryQueryBuilder;
use Statamic\Stache\Stores\AggregateStore;
use Statamic\Stache\Stores\Store;
use Statamic\Structures\CollectionStructure;
use Statamic\Structures\Tree;
use Tests\Support\CollectionTreeIsolatedTestCase;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;

// Tree-isolated: each test writes the collection's structure tree, and a tree
// write needs a root no sibling worker shares.
uses(CollectionTreeIsolatedTestCase::class);

/*
|--------------------------------------------------------------------------
| Structure-orphaned entries stay out of the sitemap (UAMS-Web/uams-statamic-archive#2542)
|--------------------------------------------------------------------------
|
| `post_categories` and `post_tags` are structured collections. An entry saved
| without a place in the collection's tree gets a null URI in the Stache `uri`
| index, because that index is computed before the entry is in the `site` index
| that `CollectionStructure::validateTree()` reads. The router matches requests
| against that index, so the entry's URL 404s. `$entry->uri()` recomputes instead
| and re-roots the entry, so Sitemapamic would advertise `/category/{slug}` or
| `/post-tags/{slug}` for a page that does not serve. A Stache rebuild re-roots
| it in the index too, after which it serves and belongs in the sitemap.
|
| The inclusion computed reads the index, so the sitemap follows the router in
| both states. These tests pin both, beside a tree-placed control.
|
| @command php artisan test --compact tests/Feature/Seo/SitemapStructureOrphansTest.php
*/

/** Slug prefix for self-provisioned entries, so teardown is bulletproof. */
const SITEMAP_ORPHAN_TEST_PREFIX = 'zz-sitemap-orphan-test-';

/**
 * The structured collections with no sitemap inclusion computed, keyed by handle,
 * with the path a root-level entry routes at.
 *
 * @var array<string, string>
 */
const SITEMAP_ORPHAN_COLLECTIONS = [
    'post_categories' => '/category/',
    'post_tags' => '/post-tags/',
];

/**
 * Remove every self-provisioned test entry from both collections.
 *
 * Quiet delete, for the reason `SitemapTaxonomyTermsTest` records: the deleted
 * event runs every search index inline, and nothing here reads an index.
 */
function tearDownSitemapOrphanFixtures(): void
{
    foreach (array_keys(SITEMAP_ORPHAN_COLLECTIONS) as $handle) {
        // Content-less checkouts have neither collection.
        if (! CollectionFacade::find($handle)) {
            continue;
        }

        /** @var EntryQueryBuilder $query */
        $query = Entry::query();
        $query->where('collection', $handle);

        /** @var Collection<int, Statamic\Entries\Entry> $entries */
        $entries = $query->get();

        $entries
            ->filter(fn (Statamic\Entries\Entry $entry): bool => is_string($entry->slug()) && str_starts_with($entry->slug(), SITEMAP_ORPHAN_TEST_PREFIX))
            ->each(fn (Statamic\Entries\Entry $entry) => $entry->deleteQuietly());
    }
}

/**
 * Save a published entry in the given collection on the default site.
 *
 * @param  string  $handle  The collection handle.
 * @param  string  $suffix  The slug suffix after the file's prefix.
 */
function makeSitemapOrphanEntry(
    string $handle,
    string $suffix
): Statamic\Entries\Entry {
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection($handle);
    $entry->locale('default');
    $entry->slug(SITEMAP_ORPHAN_TEST_PREFIX.$suffix);
    $entry->data(['title' => 'ZZ '.$suffix]);
    $entry->saveQuietly();

    return $entry;
}

/**
 * The URL paths the collection's sitemap loader emits.
 *
 * @param  string  $handle  The collection handle.
 * @return array<int, mixed>
 */
function sitemapOrphanPaths(string $handle): array
{
    /** @var callable(): iterable<int, SitemapamicUrl> $loader */
    $loader = Sitemapamic::getLoaders()->get($handle);

    $paths = collect($loader())
        ->map(fn (SitemapamicUrl $url): mixed => parse_url($url->loc, PHP_URL_PATH))
        ->values();

    // Nothing may land at the site root, which is where a URL-less entry would go.
    expect($paths->contains(fn (mixed $path): bool => in_array($path, [null, '', '/'], true)))->toBeFalse();

    return $paths->all();
}

beforeEach(fn () => tearDownSitemapOrphanFixtures());
afterEach(fn () => tearDownSitemapOrphanFixtures());

it('treats an entry with no indexed URI as unroutable', function (): void {
    expect(Inclusion::shouldIncludeRoutedEntry(null))->toBeFalse()
        ->and(Inclusion::shouldIncludeRoutedEntry(''))->toBeFalse()
        ->and(Inclusion::shouldIncludeRoutedEntry('/post-tags/immunology'))->toBeTrue();
});

it('keeps an unplaced entry out of the sitemap until the router serves it', function (string $handle, string $routePrefix): void {
    if (! CollectionFacade::find($handle)) {
        ContentSubmodule::reportMissing("{$handle} collection config");
    }

    $placed = makeSitemapOrphanEntry($handle, 'placed');
    $orphan = makeSitemapOrphanEntry($handle, 'orphan');

    // Write a tree that lists only the control, as an import that skips tree
    // placement leaves it.
    /** @var Statamic\Entries\Collection $collection */
    $collection = CollectionFacade::findByHandle($handle);
    /** @var CollectionStructure $structure */
    $structure = $collection->structure();
    /** @var Tree $tree */
    $tree = $structure->in('default');
    $tree->tree([['entry' => $placed->id()]]);
    $tree->save();

    // The file on disk really omits the orphan.
    /** @var array{tree?: list<array{entry?: string}>} $file */
    $file = YAML::file($tree->path())->parse();

    expect(collect($file['tree'] ?? [])->pluck('entry')->all())->toBe([$placed->id()]);

    $orphanPath = $routePrefix.SITEMAP_ORPHAN_TEST_PREFIX.'orphan';
    $placedPath = $routePrefix.SITEMAP_ORPHAN_TEST_PREFIX.'placed';

    /** @var TestCase $test */
    $test = $this;

    // Before a rebuild the router cannot resolve the orphan, though its own URI
    // re-roots it: this is the state that would put a 404 in the sitemap.
    Blink::flush();

    $reread = Entry::find($orphan->id());

    expect($reread)->toBeInstanceOf(Statamic\Entries\Entry::class);

    if ($reread instanceof Statamic\Entries\Entry) {
        expect($reread->uri())->toBe($orphanPath);
    }

    $test->get($orphanPath)->assertNotFound();
    $test->get($placedPath)->assertOk();

    expect(sitemapOrphanPaths($handle))->toContain($placedPath)
        ->not->toContain($orphanPath);

    // Rebuild this collection's entry indexes, as a Stache warm does. The orphan
    // is re-rooted in the index too, so it serves and is emitted.
    $entries = Stache::store('entries');
    $collectionEntries = $entries instanceof AggregateStore ? $entries->store($handle) : null;

    expect($collectionEntries)->toBeInstanceOf(Store::class);

    if ($collectionEntries instanceof Store) {
        $collectionEntries->clear();
    }

    Blink::flush();

    $test->get($orphanPath)->assertOk();

    expect(sitemapOrphanPaths($handle))->toContain($placedPath, $orphanPath);
})->with(array_map(
    fn (string $handle, string $routePrefix): array => [$handle, $routePrefix],
    array_keys(SITEMAP_ORPHAN_COLLECTIONS),
    SITEMAP_ORPHAN_COLLECTIONS,
))->group('serial');
