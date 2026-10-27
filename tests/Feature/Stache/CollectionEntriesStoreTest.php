<?php

declare(strict_types=1);

use App\Entries\KnowledgeBaseEntry;
use App\Entries\PersonEntry;
use App\Stache\CollectionEntriesStore;
use Carbon\CarbonInterface;
use Statamic\Entries\Entry;
use Statamic\Facades\Blink;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\Stache;
use Statamic\Fields\Blueprint;
use Statamic\Stache\Stores\AggregateStore;
use Tests\Support\StacheIsolatedTestCase;

uses(StacheIsolatedTestCase::class);

/**
 * `App\Stache\CollectionEntriesStore` re-wraps the entries it builds from content files:
 * `knowledge_bases` rows become `KnowledgeBaseEntry` and `people` rows `PersonEntry`, with
 * the stock entry's state copied across (#2834 coverage batch 12).
 *
 * Each test builds an entry straight from YAML through the store, as the Stache does when it
 * indexes a file, on a throwaway Stache so the collections can be dated or not.
 *
 * @command  php artisan test --compact tests/Feature/Stache/CollectionEntriesStoreTest.php
 */

/**
 * Save a collection on the default site, dated or not.
 */
function collectionEntriesStoreCollection(string $handle, bool $dated = false): void
{
    $collection = Collection::make($handle);
    $collection->sites(['default']);
    $collection->dated($dated);
    $collection->save();
}

/**
 * Build an entry from YAML the way the Stache does when it reads a content file.
 *
 * @param  string  $file  The file name under the collection's default-site folder
 */
function collectionEntriesStoreMake(string $handle, string $file, string $yaml): Entry
{
    $entries = mustBe(AggregateStore::class, Stache::store('entries'), 'the entries store');
    $store = mustBe(CollectionEntriesStore::class, $entries->store($handle), "the {$handle} store");
    $path = rtrim(mustBeString($entries->directory(), 'the entries directory'), '/').'/'.$handle.'/default/'.$file;

    return mustBe(Entry::class, $store->makeItemFromFile($path, "---\n{$yaml}\n---\n"), "the {$handle} entry");
}

it('re-wraps a knowledge base or person row, copying its state', function (string $handle, string $class): void {
    collectionEntriesStoreCollection($handle);

    $entry = collectionEntriesStoreMake($handle, 'kept-slug.md', "id: row-1\ntitle: Kept\nblueprint: wrapped\npublished: false\nextra: value");

    expect($entry::class)->toBe($class)
        ->and($entry->id())->toBe('row-1')
        ->and($entry->collectionHandle())->toBe($handle)
        ->and($entry->locale())->toBe('default')
        ->and($entry->published())->toBeFalse()
        ->and($entry->slug())->toBe('kept-slug')
        ->and($entry->get('title'))->toBe('Kept')
        ->and($entry->get('extra'))->toBe('value')
        ->and($entry->initialPath())->toEndWith("/{$handle}/default/kept-slug.md")
        ->and($entry->hasOrigin())->toBeFalse();
})->with([
    'a knowledge base' => ['knowledge_bases', KnowledgeBaseEntry::class],
    'a person' => ['people', PersonEntry::class],
]);

it('replaces the stock knowledge base entry in the structure-entries blink with the re-wrapped one', function (): void {
    collectionEntriesStoreCollection('knowledge_bases');

    $entry = collectionEntriesStoreMake('knowledge_bases', 'blinked.md', 'id: kb-blinked');

    expect(mustBe(Spatie\Blink\Blink::class, Blink::store('structure-entries'), 'the structure-entries blink')->get('kb-blinked'))->toBe($entry);
});

it('copies the publish date when the collection is dated, and not when it is not', function (string $handle, bool $dated): void {
    collectionEntriesStoreCollection($handle, $dated);

    $entry = collectionEntriesStoreMake($handle, $dated ? '2024-05-06.dated-row.md' : 'dated-row.md', 'id: dated-row');

    expect($entry->hasDate())->toBe($dated);

    if ($dated) {
        expect(mustBe(CarbonInterface::class, $entry->date(), 'the copied date')->format('Y-m-d'))->toBe('2024-05-06');
    }
})->with([
    'a dated knowledge base' => ['knowledge_bases', true],
    'an undated knowledge base' => ['knowledge_bases', false],
    'a dated person' => ['people', true],
    'an undated person' => ['people', false],
]);

it('keeps the origin of a localization', function (string $handle): void {
    collectionEntriesStoreCollection($handle);

    // The origin must exist for the entry to resolve it
    $origin = mustBe(Entry::class, EntryFacade::make(), 'the origin entry');
    $origin->collection($handle);
    $origin->locale('default');
    $origin->id('the-origin');
    $origin->slug('the-origin');
    $origin->saveQuietly();

    $entry = collectionEntriesStoreMake($handle, 'localized.md', "id: localized\norigin: the-origin");

    expect($entry->hasOrigin())->toBeTrue()
        ->and(mustBe(Entry::class, $entry->origin(), 'the resolved origin')->id())->toBe('the-origin');
})->with(['knowledge_bases', 'people']);

it('returns any other collection\'s entry as the stock entry', function (): void {
    collectionEntriesStoreCollection('pages_probe');

    $entry = collectionEntriesStoreMake('pages_probe', 'plain.md', "id: plain\ntitle: Plain");

    expect($entry::class)->toBe(Entry::class)
        ->and($entry->id())->toBe('plain')
        ->and($entry->get('title'))->toBe('Plain');
});

it('keeps the site the file sits under', function (string $handle): void {
    $collection = Collection::make($handle);
    $collection->sites(['default', 'news']);
    $collection->save();

    $path = 'news';
    $entries = mustBe(AggregateStore::class, Stache::store('entries'), 'the entries store');
    $store = mustBe(CollectionEntriesStore::class, $entries->store($handle), "the {$handle} store");
    $file = rtrim(mustBeString($entries->directory(), 'the entries directory'), '/')."/{$handle}/{$path}/on-news.md";
    $entry = mustBe(Entry::class, $store->makeItemFromFile($file, "---\nid: on-news\n---\n"), 'the news entry');

    expect($entry->locale())->toBe('news');
})->with(['knowledge_bases', 'people']);

it('keeps a localization\'s own blueprint rather than its origin\'s', function (string $handle): void {
    collectionEntriesStoreCollection($handle);

    foreach (['origin_blueprint', 'own_blueprint'] as $blueprintHandle) {
        $blueprint = mustBe(Blueprint::class, Statamic\Facades\Blueprint::make($blueprintHandle), 'the blueprint');
        $blueprint->setNamespace("collections.{$handle}");
        $blueprint->setContents(['tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text']],
        ]]]]]]);
        $blueprint->save();
    }

    $origin = mustBe(Entry::class, EntryFacade::make(), 'the origin entry');
    $origin->collection($handle);
    $origin->locale('default');
    $origin->id('blueprint-origin');
    $origin->slug('blueprint-origin');
    $origin->blueprint('origin_blueprint');
    $origin->saveQuietly();

    $entry = collectionEntriesStoreMake($handle, 'own-blueprint.md', "id: own-blueprint\norigin: blueprint-origin\nblueprint: own_blueprint");

    // Without its own blueprint copied across, the localization would take its origin's
    expect(mustBe(Blueprint::class, $entry->blueprint(), 'the blueprint')->handle())->toBe('own_blueprint');
})->with(['knowledge_bases', 'people']);
