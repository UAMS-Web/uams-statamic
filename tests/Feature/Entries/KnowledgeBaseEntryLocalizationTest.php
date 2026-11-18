<?php

declare(strict_types=1);

use App\Entries\KnowledgeBaseEntry;
use Illuminate\Support\Collection;
use Statamic\Entries\Entry;
use Statamic\Facades\Blink;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\Site;
use Statamic\Stache\Query\EntryQueryBuilder;
use Statamic\Structures\CollectionStructure;
use Statamic\Structures\Tree;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Localizing a `knowledge_base` root localizes its subtree into the target site in
 * the same save, and places each localization under the localization of its parent
 * in the target site's stored tree (#1041).
 *
 * Each case builds an isolated `knowledge_bases` collection on `default` and
 * `health` (structure `root: false`, as in `content/collections/knowledge_bases.yaml`)
 * with `kb-root` -> `kb-topic` -> `kb-article` on `default`, and localizes the root
 * the way the Control Panel's `LocalizeEntryController` does: `makeLocalization()`
 * then `save()`. `Blink::flush()` between steps stands in for a new request, since
 * the tree reads the defect turned on are memoized per request.
 *
 * @command  php artisan test --compact tests/Feature/Entries/KnowledgeBaseEntryLocalizationTest.php
 */
uses(StacheIsolatedTestCase::class);

beforeEach(function (): void {
    Site::setSites([
        'default' => ['name' => 'Default', 'url' => '/', 'locale' => 'en_US'],
        'health' => ['name' => 'Health', 'url' => '/health/', 'locale' => 'en_US'],
    ]);

    $collection = CollectionFacade::make('knowledge_bases');
    $collection->title('Knowledge Bases');
    $collection->sites(['default', 'health']);
    $collection->routes('{parent_uri}/{slug}');
    $collection->structureContents(['root' => false]);
    $collection->save();

    foreach (['knowledge_base', 'topic', 'article', 'note'] as $handle) {
        /** @var Statamic\Fields\Blueprint $blueprint */
        $blueprint = Blueprint::make($handle);
        /** @var Statamic\Fields\Blueprint $blueprint */
        $blueprint = $blueprint->setNamespace('collections.knowledge_bases');
        /** @var Statamic\Fields\Blueprint $blueprint */
        $blueprint = $blueprint->setContents([
            'tabs' => ['main' => ['sections' => [['fields' => [
                ['handle' => 'title', 'field' => ['type' => 'text']],
            ]]]]],
        ]);
        $blueprint->save();
    }

    foreach (['kb-root' => 'knowledge_base', 'kb-topic' => 'topic', 'kb-article' => 'article'] as $id => $blueprint) {
        kbLocalizationFixtureEntry($id, $blueprint);
    }

    $tree = kbLocalizationTree('default');
    $tree->tree([
        ['entry' => 'kb-root', 'children' => [
            ['entry' => 'kb-topic', 'children' => [
                ['entry' => 'kb-article'],
            ]],
        ]],
    ]);
    $tree->save();

    Blink::flush();
});

/**
 * Save a `default`-site entry of the isolated collection, without events.
 */
function kbLocalizationFixtureEntry(string $id, string $blueprint): Entry
{
    /** @var Entry $entry */
    $entry = EntryFacade::make();
    $entry->collection('knowledge_bases');
    $entry->id($id);
    $entry->locale('default');
    $entry->blueprint($blueprint);
    $entry->slug($id);
    $entry->set('title', $id);
    $entry->saveQuietly();

    return $entry;
}

/**
 * The isolated collection's tree in a site.
 */
function kbLocalizationTree(string $site): Tree
{
    $collection = CollectionFacade::findByHandle('knowledge_bases');
    $structure = $collection?->structure();

    if (! $structure instanceof CollectionStructure) {
        throw new RuntimeException('The knowledge_bases fixture has no structure.');
    }

    $tree = $structure->in($site);

    if (! $tree instanceof Tree) {
        throw new RuntimeException("The knowledge_bases fixture has no {$site} tree.");
    }

    return $tree;
}

/**
 * A site's tree as stored, read fresh from the Stache.
 *
 * @return array<mixed>
 */
function kbStoredTree(string $site): array
{
    Blink::flush();

    $fileData = kbLocalizationTree($site)->fileData();

    return is_array($fileData) && is_array($fileData['tree'] ?? null) ? $fileData['tree'] : [];
}

/**
 * The ids of an entry's localizations in a site.
 *
 * @return list<mixed>
 */
function kbLocalizationIds(string $originId, string $site): array
{
    Blink::flush();

    $query = EntryFacade::query();

    if (! $query instanceof EntryQueryBuilder) {
        throw new RuntimeException('Entry::query() is not the Stache entry query builder.');
    }

    $query->where('collection', 'knowledge_bases');
    $query->where('origin', $originId);
    $query->where('locale', $site);

    $results = $query->get();

    if (! $results instanceof Collection) {
        throw new RuntimeException('The entry query did not return a collection.');
    }

    $ids = [];

    foreach ($results as $entry) {
        if ($entry instanceof Entry) {
            $ids[] = $entry->id();
        }
    }

    return $ids;
}

/**
 * Find a `default`-site entry of the fixture, failing loudly when it is missing.
 */
function kbFixtureEntry(string $id): KnowledgeBaseEntry
{
    Blink::flush();

    $entry = EntryFacade::find($id);

    if (! $entry instanceof KnowledgeBaseEntry) {
        throw new RuntimeException("Fixture entry [{$id}] is not a KnowledgeBaseEntry.");
    }

    return $entry;
}

/**
 * Localize the fixture root into `health` as the Control Panel does, and return the
 * localization.
 */
function localizeKbRootToHealth(): Entry
{
    $localized = kbFixtureEntry('kb-root')->makeLocalization('health');
    $localized->save();

    return $localized;
}

it('builds the localization of a knowledge base entry as a KnowledgeBaseEntry', function (): void {
    expect(kbFixtureEntry('kb-root')->makeLocalization('health'))->toBeInstanceOf(KnowledgeBaseEntry::class);
});

it('localizes the whole subtree in the save that localizes the root', function (): void {
    $root = localizeKbRootToHealth();

    expect(kbLocalizationIds('kb-root', 'health'))->toBe([$root->id()])
        ->and(kbLocalizationIds('kb-topic', 'health'))->toHaveCount(1)
        ->and(kbLocalizationIds('kb-article', 'health'))->toHaveCount(1);
});

it('places each localization under the localization of its parent in the stored tree', function (): void {
    $root = localizeKbRootToHealth();
    [$topic] = kbLocalizationIds('kb-topic', 'health');
    [$article] = kbLocalizationIds('kb-article', 'health');

    expect(kbStoredTree('health'))->toBe([
        ['entry' => $root->id(), 'children' => [
            ['entry' => $topic, 'children' => [
                ['entry' => $article],
            ]],
        ]],
    ]);
});

it('creates no duplicate localization or tree node when the localized root is saved again', function (): void {
    $root = localizeKbRootToHealth();

    Blink::flush();
    $reloaded = EntryFacade::find($root->id());
    expect($reloaded)->toBeInstanceOf(KnowledgeBaseEntry::class);
    /** @var KnowledgeBaseEntry $reloaded */
    $reloaded->save();

    [$topic] = kbLocalizationIds('kb-topic', 'health');
    [$article] = kbLocalizationIds('kb-article', 'health');

    expect(kbStoredTree('health'))->toBe([
        ['entry' => $root->id(), 'children' => [
            ['entry' => $topic, 'children' => [
                ['entry' => $article],
            ]],
        ]],
    ])
        ->and(kbLocalizationIds('kb-root', 'health'))->toHaveCount(1)
        ->and(kbLocalizationIds('kb-topic', 'health'))->toHaveCount(1)
        ->and(kbLocalizationIds('kb-article', 'health'))->toHaveCount(1);
});

it('still refuses to localize a topic or an article directly', function (string $id): void {
    expect(fn (): Entry => kbFixtureEntry($id)->makeLocalization('health'))
        ->toThrow(Exception::class, 'Only Knowledge Base entries can be localized.');
})->with(['kb-topic', 'kb-article']);

it('places a localization of another blueprint under the localization of its parent', function (): void {
    $root = localizeKbRootToHealth();
    [$topic] = kbLocalizationIds('kb-topic', 'health');
    [$article] = kbLocalizationIds('kb-article', 'health');

    kbLocalizationFixtureEntry('kb-note', 'note');
    $tree = kbLocalizationTree('default');
    $tree->tree([
        ['entry' => 'kb-root', 'children' => [
            ['entry' => 'kb-topic', 'children' => [
                ['entry' => 'kb-article'],
                ['entry' => 'kb-note'],
            ]],
        ]],
    ]);
    $tree->save();

    $note = kbFixtureEntry('kb-note')->makeLocalization('health');
    $note->save();

    expect(kbStoredTree('health'))->toBe([
        ['entry' => $root->id(), 'children' => [
            ['entry' => $topic, 'children' => [
                ['entry' => $article],
                ['entry' => $note->id()],
            ]],
        ]],
    ]);
});
