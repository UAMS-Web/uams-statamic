<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File as LaravelFile;
use Illuminate\Support\Facades\Queue;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\File;
use Statamic\Facades\Stache;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;
use Statamic\Filesystem\Manager;
use Statamic\Stache\Stores\AggregateStore;
use Statamic\Stache\Stores\BasicStore;
use Statamic\Stache\Stores\Store;
use Tests\Support\StacheIsolatedTestCase;

/**
 * A Stache file removed after it was stat'ed and before it is read is skipped, not
 * rebuilt as a new, empty item (UAMS-Web/uams-statamic-archive#2643).
 *
 * `Store::paths()` and `Store::getItemFromModifiedPath()` read each path the Stache
 * has already stat'ed through `Statamic\Facades\File`, whose `get()` returns null for
 * a missing file rather than raising. `makeItemFromFile()` then parses that null to an
 * empty array, finds no id, generates one, and for an entry writes the file back to
 * disk. So a file deleted in that window, by a parallel worker in the shared content
 * mirror or by a Control Panel delete during an index build, comes back as an empty
 * entry under a new id. The two local `statamic-stache-store-vanished-file` patches
 * skip such a file.
 *
 * The window is reached by swapping `File::get()` for a version that deletes the
 * target file and returns null, which is exactly what a delete landing between the
 * stat and the read produces. The controls keep the skip narrow: a present file that
 * also reads as null, an empty one, is still built the way Statamic builds it.
 *
 * @command  php artisan test --compact tests/Feature/Stache/StoreVanishedFileTest.php
 */
uses(StacheIsolatedTestCase::class);

const VANISHED_FILE_COLLECTION = 'vanished_file_probe';

/**
 * Save an entry in the probe collection and return its file path.
 *
 * @param  string  $slug  The entry's slug.
 */
function vanishedFileEntryPath(string $slug): string
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection(VANISHED_FILE_COLLECTION);
    $entry->slug($slug);
    $entry->data(['title' => ucfirst($slug)]);
    $entry->save();

    /** @var string $path */
    $path = $entry->path();

    return $path;
}

/**
 * Make `File::get()` delete one path and return null for it, the read a delete landing
 * after the stat produces, while every other path is read as normal.
 *
 * @param  string  $vanishing  The path that disappears on read.
 */
function vanishOnRead(string $vanishing): void
{
    /** @var Manager $real */
    $real = File::getFacadeRoot();

    File::partialMock()
        ->shouldReceive('get')
        ->andReturnUsing(function (string $path, mixed $fallback = null) use ($real, $vanishing): mixed {
            if ($path === $vanishing) {
                LaravelFile::delete($path);

                return null;
            }

            return $real->disk()->get($path, $fallback);
        });
}

/**
 * The probe collection's entries store.
 */
function vanishedFileStore(): BasicStore
{
    /** @var AggregateStore $entries */
    $entries = Stache::store('entries');

    /** @var BasicStore $store */
    $store = $entries->store(VANISHED_FILE_COLLECTION);

    return $store;
}

/**
 * The paths a store's index build returns.
 *
 * @param  Store  $store  The store.
 * @return list<string>
 */
function indexedPaths(Store $store): array
{
    /** @var Illuminate\Support\Collection<int|string, string> $paths */
    $paths = $store->paths();

    return array_values($paths->all());
}

/**
 * The index key a store records for a path.
 *
 * @param  Store  $store  The store.
 * @param  string  $path  The file path.
 */
function keyForPath(Store $store, string $path): string
{
    /** @var Illuminate\Support\Collection<string, string> $paths */
    $paths = $store->paths();

    $key = $paths->flip()->get($path);

    return is_string($key) ? $key : '';
}

/**
 * Call a store's protected `getItemFromModifiedPath()`.
 *
 * @param  Store  $store  The store.
 * @param  string  $path  The path read.
 */
function readModifiedPath(Store $store, string $path): mixed
{
    $method = new ReflectionMethod($store, 'getItemFromModifiedPath');

    return $method->invoke($store, $path);
}

beforeEach(function (): void {
    // Record (don't serialize) queued EntrySaved listeners.
    Queue::fake();

    Collection::make(VANISHED_FILE_COLLECTION)->save();
});

it('skips a file deleted between the change scan and its read, and does not write it back', function (): void {
    $path = vanishedFileEntryPath('gone');

    vanishOnRead($path);

    expect(readModifiedPath(vanishedFileStore(), $path))->toBeNull()
        ->and(LaravelFile::exists($path))->toBeFalse();
});

it('leaves a vanished file out of the index build, and keeps every file that is still there', function (): void {
    $gone = vanishedFileEntryPath('gone');
    $kept = vanishedFileEntryPath('kept');

    // Build the index from disk rather than from the cache the saves just warmed
    $store = vanishedFileStore();
    $store->clear();

    vanishOnRead($gone);

    expect(indexedPaths($store))->toContain($kept)
        ->not->toContain($gone)
        ->and(LaravelFile::exists($gone))->toBeFalse();
});

it('still builds a present file that reads as empty, rather than skipping it', function (string $method): void {
    $path = vanishedFileEntryPath('empty');
    LaravelFile::put($path, '');

    $store = vanishedFileStore();

    if ($method === 'getItemFromModifiedPath') {
        expect(readModifiedPath($store, $path))->not->toBeNull();

        return;
    }

    $store->clear();

    expect(indexedPaths($store))->toContain($path);
})->with(['getItemFromModifiedPath', 'paths']);

it('returns no terms for a taxonomy term file deleted before its read, rather than fatal on the skipped item', function (): void {
    /** @var Statamic\Taxonomies\Taxonomy $taxonomy */
    $taxonomy = Taxonomy::make('vanished_terms_probe');
    $taxonomy->save();

    /** @var Statamic\Taxonomies\Term $term */
    $term = Term::make();
    $term->taxonomy('vanished_terms_probe');
    $term->slug('gone');
    $term->set('title', 'Gone');
    $term->save();

    /** @var string $path */
    $path = $term->path();

    vanishOnRead($path);

    /** @var AggregateStore $terms */
    $terms = Stache::store('terms');

    /** @var Store $store */
    $store = $terms->store('vanished_terms_probe');

    // `TaxonomyTermsStore` calls `localizations()` on its parent's result, which is null for a vanished file
    expect(readModifiedPath($store, $path))->toBe([])
        ->and(LaravelFile::exists($path))->toBeFalse();
});

/**
 * Save a term in the probe taxonomy and return its file path.
 *
 * @param  string  $slug  The term's slug.
 */
function vanishedFileTermPath(string $slug): string
{
    /** @var Statamic\Taxonomies\Term $term */
    $term = Term::make();
    $term->taxonomy('vanished_terms_probe');
    $term->slug($slug);
    $term->set('title', ucfirst($slug));
    $term->save();

    /** @var string $path */
    $path = $term->path();

    return $path;
}

/**
 * The probe taxonomy's terms store.
 */
function vanishedTermsStore(): BasicStore
{
    /** @var AggregateStore $terms */
    $terms = Stache::store('terms');

    /** @var BasicStore $store */
    $store = $terms->store('vanished_terms_probe');

    return $store;
}

it('leaves a vanished term file out of the terms index build, and keeps every term still there', function (): void {
    /** @var Statamic\Taxonomies\Taxonomy $taxonomy */
    $taxonomy = Taxonomy::make('vanished_terms_probe');
    $taxonomy->save();

    $gone = vanishedFileTermPath('gone');
    $kept = vanishedFileTermPath('kept');

    $store = vanishedTermsStore();
    $store->clear();

    vanishOnRead($gone);

    expect(indexedPaths($store))->toContain($kept)
        ->not->toContain($gone);
});

it('returns null from getItem for a file deleted after its path was recorded, and does not write it back', function (string $kind): void {
    if ($kind === 'term') {
        /** @var Statamic\Taxonomies\Taxonomy $taxonomy */
        $taxonomy = Taxonomy::make('vanished_terms_probe');
        $taxonomy->save();

        $path = vanishedFileTermPath('gone');
        $store = vanishedTermsStore();
        $key = 'default::gone';
    } else {
        $path = vanishedFileEntryPath('gone');
        $store = vanishedFileStore();

        $key = keyForPath($store, $path);
    }

    // Keep the recorded path, drop the cached item, so getItem() reads the file
    $store->paths();
    $store->forgetItem($key);

    vanishOnRead($path);

    expect($store->getItem($key))->toBeNull()
        ->and(LaravelFile::exists($path))->toBeFalse();
})->with(['term', 'entry']);

it('still builds a present term that reads as empty, rather than dropping it', function (string $method): void {
    /** @var Statamic\Taxonomies\Taxonomy $taxonomy */
    $taxonomy = Taxonomy::make('vanished_terms_probe');
    $taxonomy->save();

    $path = vanishedFileTermPath('empty');
    LaravelFile::put($path, '');

    $store = vanishedTermsStore();
    $store->clear();

    if ($method === 'paths') {
        expect(indexedPaths($store))->toContain($path);

        return;
    }

    $store->paths();
    $store->forgetItem('default::empty');

    expect($store->getItem('default::empty'))->not->toBeNull();
})->with(['paths', 'getItem']);

it('still builds a present entry that reads as empty from getItem, rather than dropping it', function (): void {
    $path = vanishedFileEntryPath('empty');
    LaravelFile::put($path, '');

    $store = vanishedFileStore();

    $key = keyForPath($store, $path);
    $store->forgetItem($key);

    expect($store->getItem($key))->not->toBeNull();
});

it('reuses a candidate path whose file vanished before the unique-path probe read it', function (): void {
    // CollectionEntriesStore::writeItemToDisk() reads each candidate path and takes the
    // first that reads as null. A file deleted before that read is genuinely free, so the
    // new entry is written there rather than beside it; the store needs no guard here.
    $taken = vanishedFileEntryPath('shared');

    vanishOnRead($taken);

    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection(VANISHED_FILE_COLLECTION);
    $entry->slug('shared');
    $entry->data(['title' => 'Second']);
    $entry->save();

    expect($entry->path())->toBe($taken)
        ->and(LaravelFile::exists($taken))->toBeTrue();
});
