<?php

declare(strict_types=1);

use App\Support\ContentRoot;
use App\Support\Redirects\DeletedEntryRedirectCapture;
use Illuminate\Support\Collection as IlluminateCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Statamic\Entries\Collection as StatamicCollection;
use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Stache;
use Statamic\Stache\Stores\Store;
use Statamic\Structures\CollectionStructure;
use Statamic\Structures\CollectionTree;
use Tests\Support\EntriesTermsAndTreesIsolatedTestCase;
use Tests\Support\NullDeletedEntryRedirectCapture;
use Tests\Support\ThrowawayRoot;

uses(EntriesTermsAndTreesIsolatedTestCase::class);

/**
 * UAMS-Web/uams-statamic-archive#1873: the contract the trees half of
 * {@see EntriesTermsAndTreesIsolatedTestCase} holds. A tree saved by a test on it lands
 * in a throwaway directory, the real committed tree is never touched, and the rest of
 * the content still resolves. Since #571 the file runs on the combined base, so its
 * entry saves land in the per-process entries copy and its Stache cache writes in the
 * per-test overlay, and the cases that guarded the trees-only base's `uri` restore now
 * assert that the shared cache is left alone instead.
 *
 * The third case is the one a reader will be tempted to call redundant. It is
 * not: the earlier cut of this ticket re-rooted the tree directory while leaving
 * the shared path index real, which made `getItem()` hand back a tree whose
 * `initialPath` was the committed file — and `ExistsAsFile::writeFile()`
 * `File::delete()`s the initial path when it differs from the recomputed one.
 * That deleted two committed trees during a run. A directory re-root ALONE reads
 * as working right up until something saves.
 *
 * @command  php artisan test --compact tests/Feature/CollectionTreeIsolationTest.php
 */
beforeEach(function (): void {
    // Deleting a pages entry records a redirect in the redirects global, which stays on the shared mirror (#558)
    app()->instance(DeletedEntryRedirectCapture::class, new NullDeletedEntryRedirectCapture);
});

/** Forward slashes, so a Windows path compares against `base_path()` at all. */
function treeIsolationSlashes(string $path): string
{
    return str_replace('\\', '/', $path);
}

/** The `collection-trees` store, narrowed off the facade's `mixed`. */
function treeIsolationStore(): Store
{
    return mustBe(Store::class, Stache::stores()->get('collection-trees'), 'the collection-trees store');
}

/** A structured collection this suite asserts against. */
function treeIsolationCollection(string $handle): StatamicCollection
{
    return mustBe(StatamicCollection::class, Collection::findByHandle($handle), "the {$handle} collection");
}

it('roots the collection-trees store outside the content submodule', function (): void {
    $directory = treeIsolationSlashes(mustBeString(treeIsolationStore()->directory(), 'the tree store directory'));

    // The segment is asserted, not just the shared parent: a bare
    // `collection-trees/` prefix matches a SIBLING run's subdirectory too, so it
    // stayed green while the base isolated per worker but not per run (UAMS-Web/uams-statamic-archive#1957).
    // This is the assertion that reds if the call site reverts.
    expect($directory)
        ->not->toContain(treeIsolationSlashes(base_path('content')).'/')
        ->toContain('framework/testing/collection-trees/'.ThrowawayRoot::segment());
});

it('still reads the rest of the real content submodule', function (): void {
    // The half that separates this base from StacheIsolatedTestCase: only trees are
    // isolated, so the collections, entries and taxonomies these tests exercise are
    // still the committed ones.
    expect(Collection::findByHandle('post_categories'))->not->toBeNull();
    expect(Collection::findByHandle('recognition_lists'))->not->toBeNull();
});

it('resolves no tree carrying a path inside the content submodule', function (): void {
    // The destructive precondition, asserted directly. A tree whose initialPath is a
    // committed file is one save away from deleting it, so the invariant is that no
    // such tree is REACHABLE while the store is re-rooted — not merely that this test
    // happens not to save one.
    $content = treeIsolationSlashes(base_path('content')).'/';
    $checked = 0;

    foreach (['post_categories', 'recognition_lists', 'clinical_expertise'] as $handle) {
        $collection = Collection::findByHandle($handle);

        if (! $collection instanceof StatamicCollection || ! $collection->hasStructure()) {
            continue;
        }

        $structure = $collection->structure();

        if (! $structure instanceof CollectionStructure) {
            continue;
        }

        $sites = mustBe(IlluminateCollection::class, $collection->sites(), "the {$handle} sites");

        foreach ($sites->all() as $site) {
            $tree = $structure->in(mustBeString($site, "a {$handle} site handle"));

            if (! $tree instanceof CollectionTree) {
                continue;
            }

            $checked++;
            $initial = $tree->initialPath();

            $insideContent = is_string($initial) && str_contains(treeIsolationSlashes($initial), $content);

            expect($insideContent)->toBeFalse(
                "{$handle} resolved a tree still pointing at the committed file: ".(is_string($initial) ? $initial : 'null')
            );
        }
    }

    // Guard against a vacuous pass: a loop that resolved no trees at all would
    // satisfy the assertion above without ever exercising it.
    expect($checked)->toBeGreaterThan(0);
});

it('writes a saved tree into the throwaway directory, leaving the committed one alone', function (): void {
    $collection = treeIsolationCollection('post_categories');

    $committed = base_path('content/trees/collections/default/post_categories.yaml');
    $committedExistedBefore = file_exists($committed);
    $committedBefore = $committedExistedBefore ? file_get_contents($committed) : null;

    // Set as statements: the fluent setters return an untyped `$this`, which loses
    // the narrowed type the assertions below depend on.
    $entry = mustBe(StatamicEntry::class, Entry::make(), 'a new entry');
    $entry->collection('post_categories');
    $entry->locale('default');
    $entry->slug('zz-tree-isolation-uhura');
    $entry->data(['title' => 'Tree isolation probe']);
    $entry->save();

    try {
        $structure = mustBe(CollectionStructure::class, $collection->structure(), 'the post_categories structure');
        $tree = mustBe(CollectionTree::class, $structure->in('default'), 'the default post_categories tree');

        $tree->tree([['entry' => $entry->id()]]);
        $tree->save();

        $path = mustBeString($tree->path(), 'the saved tree path');

        expect(treeIsolationSlashes($path))->toContain('framework/testing/collection-trees/');
        expect(file_exists($path))->toBeTrue();
    } finally {
        // Deleting a structured-collection entry saves the tree again — the implicit
        // write this whole base exists for.
        $entry->delete();
    }

    // The committed tree is byte-identical and, above all, still THERE. `writeFile()`
    // removes the initial path when the recomputed one differs, so the failure this
    // guards is a deletion rather than a modification.
    expect(file_exists($committed))->toBe($committedExistedBefore);

    if ($committedExistedBefore) {
        expect(file_get_contents($committed))->toBe($committedBefore);
    }
});

/**
 * The `pages` entry UAMS-Web/uams-statamic-archive#3219 measured: the committed health tree places it at
 * `/expertise`, after the root entry at `/`.
 */
const TREE_ISOLATION_EXPERTISE_ID = '79295ce3-cdca-4463-8dac-74d04571f104';

/**
 * The id of the entry the create-and-delete case makes, fixed so a later case can
 * look for its row.
 */
const TREE_ISOLATION_TRANSIENT_ID = 'tree-isolation-uri-transient';

/** The path the cached Stache keys hold for the health `pages` tree, empty when they have none. */
function treeIsolationHealthTreeKeyPath(): string
{
    $keys = Stache::cacheStore()->get('stache::keys/collection-trees');

    return \is_array($keys) ? mustBeString($keys['pages::health'] ?? '', 'the health pages tree key') : '';
}

/** The cached `pages` URI of the expertise entry, or null when it has no row. */
function treeIsolationCachedExpertiseUri(): mixed
{
    $cached = Stache::cacheStore()->get('stache::indexes::entries::pages::uri');

    return \is_array($cached) ? ($cached[TREE_ISOLATION_EXPERTISE_ID] ?? null) : null;
}

/**
 * The `pages` URI index as the shared Stache cache holds it, read past the per-test
 * overlay the combined base points the Stache at.
 */
function treeIsolationSharedPagesUriIndex(): mixed
{
    return Cache::store()->get('stache::indexes::entries::pages::uri');
}

/** The path the shared Stache cache's keys hold for the health `pages` tree, empty when they have none. */
function treeIsolationSharedHealthTreeKeyPath(): string
{
    $keys = Cache::store()->get('stache::keys/collection-trees');

    return \is_array($keys) ? mustBeString($keys['pages::health'] ?? '', 'the shared health pages tree key') : '';
}

it('saves an existing pages entry inside the base, leaving the shared uri index and the committed tree alone', function (): void {
    $committedTree = ContentRoot::path(Config::string('app.env')).'/trees/collections/health/pages.yaml';
    $committedTreeBefore = mustBeString(file_get_contents($committedTree), 'the committed health pages tree');

    $sharedBefore = treeIsolationSharedPagesUriIndex();

    // The shared index must hold the committed row, or the comparison below has nothing to protect
    expect($sharedBefore)->toBeArray()
        ->and(mustBeArray($sharedBefore, 'the shared pages uri index')[TREE_ISOLATION_EXPERTISE_ID] ?? null)->toBe('/expertise');

    // A blank tree is filled in entry-index order, and its root is whichever entry comes
    // first. That order differs between a snapshot and a cold rebuild (#466), so this
    // test authors the tree that makes the expertise entry the root.
    $structure = mustBe(CollectionStructure::class, treeIsolationCollection('pages')->structure(), 'the pages structure');
    $tree = mustBe(CollectionTree::class, $structure->in('health'), 'the health pages tree');
    $tree->tree([['entry' => TREE_ISOLATION_EXPERTISE_ID]]);
    $tree->save();

    $entry = mustBe(StatamicEntry::class, Entry::find(TREE_ISOLATION_EXPERTISE_ID), 'the expertise entry');

    expect($entry->save())->toBeTrue();

    // The save did write the index, with the throwaway tree's root URI, so the shared
    // index staying put is the overlay's doing rather than a save that wrote nothing.
    // UAMS-Web/uams-statamic-archive#3227 is that write reaching the shared cache.
    expect(treeIsolationCachedExpertiseUri())->toBe('/')
        ->and(treeIsolationSharedPagesUriIndex())->toBe($sharedBefore)
        ->and(file_get_contents($committedTree))->toBe($committedTreeBefore);
});

it('creates and deletes a pages entry inside the base, leaving the shared tree keys alone', function (): void {
    $sharedKeyBefore = treeIsolationSharedHealthTreeKeyPath();

    $entry = mustBe(StatamicEntry::class, Entry::make(), 'a new entry');
    $entry->id(TREE_ISOLATION_TRANSIENT_ID);
    $entry->collection('pages');
    $entry->locale('health');
    $entry->slug('zz-tree-isolation-transient');
    $entry->data(['title' => 'Tree isolation transient']);
    $entry->save();

    $entry->delete();

    // The delete saved a blank tree, so the Stache's keys now hold its throwaway path.
    // Asserted so a changed cache key cannot pass the shared-cache check vacuously
    expect(Entry::find(TREE_ISOLATION_TRANSIENT_ID))->toBeNull()
        ->and(treeIsolationHealthTreeKeyPath())->toContain('framework/testing/collection-trees/')
        ->and(treeIsolationSharedHealthTreeKeyPath())->toBe($sharedKeyBefore)
        ->and($sharedKeyBefore)->not->toContain('framework/testing/collection-trees/');
});

// A throwaway path left here hides the committed tree from every later test in the
// process: `PreventMountingToTreeRoot` then resolves the wrong root for `pages` (#329)
it('leaves no throwaway tree key for the next test', function (): void {
    expect(treeIsolationHealthTreeKeyPath())->not->toContain('framework/testing/collection-trees/');
})->depends('it creates and deletes a pages entry inside the base, leaving the shared tree keys alone');
it('leaves no uri row for an entry an earlier test created and deleted', function (): void {
    $checked = 0;
    $leaked = [];

    foreach (Collection::all() as $collection) {
        $collection = mustBe(StatamicCollection::class, $collection, 'a collection');
        $handle = mustBeString($collection->handle(), 'the collection handle');
        $cached = Stache::cacheStore()->get("stache::indexes::entries::{$handle}::uri");

        if (! \is_array($cached)) {
            continue;
        }

        $checked++;

        if (array_key_exists(TREE_ISOLATION_TRANSIENT_ID, $cached)) {
            $leaked[] = $handle;
        }
    }

    // Every structured collection's index is cached by the base, so a sweep that
    // read none has checked nothing.
    expect($checked)->toBeGreaterThan(0);

    // Asserted once over the whole sweep: which indexes are cached depends on what the
    // worker ran first, so a per-index assertion moves the band's total (#335).
    expect($leaked)->toBe([]);
})->depends('it creates and deletes a pages entry inside the base, leaving the shared tree keys alone');
