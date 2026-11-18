<?php

declare(strict_types=1);

use App\Entries\KnowledgeBaseEntry;
use App\Services\KnowledgeBase\KnowledgeBaseMountUriRefresher;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\LegacyMockInterface;
use Spatie\Blink\Blink as SpatieBlink;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Events\EntryCreated;
use Statamic\Events\EntryCreating;
use Statamic\Events\EntrySaved;
use Statamic\Events\EntrySaving;
use Statamic\Facades\Blink;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\Stache;
use Statamic\Facades\Term;
use Statamic\Fields\Blueprint as StatamicBlueprint;
use Statamic\Stache\Indexes\Index;
use Statamic\Stache\Query\EntryQueryBuilder;
use Statamic\Stache\Query\TermQueryBuilder;
use Statamic\Stache\Stores\CollectionEntriesStore;
use Statamic\Stache\Stores\EntriesStore;
use Statamic\Structures\CollectionStructure;
use Statamic\Structures\Tree;
use Statamic\Taxonomies\LocalizedTerm;
use Statamic\Taxonomies\Taxonomy;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Real saves and localizations of `App\Entries\KnowledgeBaseEntry` against an isolated
 * `knowledge_bases` fixture: a structured collection on the `default` and `health` sites
 * with the `knowledge_base`, `topic` and `article` blueprints, plus a `glossary`
 * blueprint that none of the localization rules name.
 *
 * Each Control Panel save is its own request, and Statamic memoizes per request what a
 * tree read returns, so {@see kbNewRequest()} marks the boundary between saves. The
 * placement tests read the tree as stored ({@see kbRawTree()}), because a validated read
 * adds every entry the tree is missing at the top level and so cannot show where one was
 * put. The localization cascade's own placement is covered by
 * `KnowledgeBaseEntryLocalizationTest.php`; this file covers the rest of the class.
 *
 * @command  php artisan test --compact tests/Feature/Entries/KnowledgeBaseEntrySaveTest.php
 */
uses(StacheIsolatedTestCase::class, MockeryPHPUnitIntegration::class);

beforeEach(function (): void {
    (new ReflectionProperty(KnowledgeBaseEntry::class, 'localizedEntries'))->setValue(null, []);
    kbSaveFixture();
});

/**
 * Build the structured `knowledge_bases` collection and its blueprints.
 *
 * @param  array<string, mixed>  $overrides  Collection setters to apply, by method name
 */
function kbSaveFixture(array $overrides = []): Collection
{
    $collection = mustBe(Collection::class, CollectionFacade::make('knowledge_bases'), 'the knowledge_bases collection');
    $collection->title('Knowledge Bases');
    $collection->routes('{parent_uri}/{slug}');
    $collection->sites(['default', 'health']);
    $collection->structureContents(['root' => false]);

    foreach ($overrides as $method => $value) {
        $collection->{$method}($value);
    }

    $collection->save();

    foreach (['knowledge_base', 'topic', 'article', 'glossary'] as $handle) {
        $blueprint = mustBe(StatamicBlueprint::class, Blueprint::make($handle), "the {$handle} blueprint");
        $blueprint->setNamespace('collections.knowledge_bases');
        $blueprint->setContents([
            'tabs' => ['main' => ['sections' => [['fields' => [
                ['handle' => 'title', 'field' => ['type' => 'text']],
                ['handle' => KnowledgeBaseEntry::MOUNT_FIELD, 'field' => ['type' => 'entries', 'max_items' => 1]],
            ]]]]],
        ]);
        $blueprint->save();
    }

    return $collection;
}

/**
 * End the current request: forget every Blink memo, as a new request starts with none.
 */
function kbNewRequest(): void
{
    Blink::flush();

    foreach (['entry-uris', 'structure-uris', 'structure-entries', 'collection-mounts'] as $store) {
        kbBlinkStore($store)->flush();
    }
}

/**
 * A named Blink store.
 */
function kbBlinkStore(string $name): SpatieBlink
{
    return mustBe(SpatieBlink::class, Blink::store($name), "the {$name} Blink store");
}

/**
 * A spy on the log, for asserting what a save logged.
 */
function kbLogSpy(): LegacyMockInterface
{
    return mustBe(LegacyMockInterface::class, Log::spy(), 'the log spy');
}

/**
 * A collection the test has saved.
 */
function kbCollection(string $handle = 'knowledge_bases'): Collection
{
    return mustBe(Collection::class, CollectionFacade::findByHandle($handle), "the {$handle} collection");
}

/**
 * Make (unsaved) a plain entry in another collection.
 */
function kbPlainEntry(string $id, string $collection, string $slug): StatamicEntry
{
    $entry = mustBe(StatamicEntry::class, EntryFacade::make(), 'a new entry');
    $entry->id($id);
    $entry->collection($collection);
    $entry->slug($slug);

    return $entry;
}

/**
 * The `uri` index of a collection's entries, freshly loaded.
 */
function kbUriIndex(string $collection): Index
{
    $index = mustBe(Index::class, kbEntriesStore($collection)->index('uri'), "the {$collection} uri index");
    $index->load();

    return $index;
}

/**
 * The Stache store holding a collection's entries.
 */
function kbEntriesStore(string $collection): CollectionEntriesStore
{
    $entries = mustBe(EntriesStore::class, Stache::store('entries'), 'the entries store');

    return mustBe(CollectionEntriesStore::class, $entries->store($collection), "the {$collection} entries store");
}

/**
 * Make (unsaved) a `knowledge_bases` entry.
 */
function kbSaveEntry(string $id, string $blueprint, string $slug, string $site = 'default'): KnowledgeBaseEntry
{
    $entry = new KnowledgeBaseEntry;
    $entry->id($id);
    $entry->collection('knowledge_bases');
    $entry->blueprint($blueprint);
    $entry->locale($site);
    $entry->slug($slug);
    $entry->data(['title' => ucfirst($slug)]);

    return $entry;
}

/**
 * Find a `knowledge_bases` entry that the test has saved.
 */
function kbFind(string $id): KnowledgeBaseEntry
{
    return mustBe(KnowledgeBaseEntry::class, EntryFacade::find($id), "entry {$id}");
}

/**
 * Find any entry the test has saved, whatever its class.
 */
function kbAnyEntry(string $id): StatamicEntry
{
    return mustBe(StatamicEntry::class, EntryFacade::find($id), "entry {$id}");
}

/**
 * Make a localization of an entry with a fixed id, so tests can name it.
 */
function kbLocalize(StatamicEntry $entry, string $id, bool $cascade = false): StatamicEntry
{
    $localization = mustBe(StatamicEntry::class, $entry instanceof KnowledgeBaseEntry
        ? $entry->makeLocalization('health', $cascade)
        : $entry->makeLocalization('health'), "the localization {$id}");
    $localization->id($id);

    return $localization;
}

/**
 * The id of an entry's origin, or null when it has none.
 */
function kbOriginId(StatamicEntry $entry): mixed
{
    $origin = $entry->origin();

    return $origin instanceof StatamicEntry ? $origin->id() : null;
}

/**
 * The collection's tree for a site, read fresh.
 */
function kbTree(string $site = 'default'): Tree
{
    $structure = mustBe(CollectionStructure::class, kbCollection()->structure(), 'the knowledge_bases structure');

    return mustBe(Tree::class, $structure->in($site), "the {$site} tree");
}

/**
 * The tree for a site as stored, before `validateTree()` adds the entries it is missing.
 *
 * @return array<array-key, mixed>
 */
function kbRawTree(string $site = 'default'): array
{
    kbNewRequest();

    return mustBeArray((new ReflectionProperty(Tree::class, 'tree'))->getValue(kbTree($site)), "the stored {$site} tree");
}

/**
 * Store the default tree, then save the given entries quietly, each a separate request.
 *
 * @param  list<KnowledgeBaseEntry>  $entries
 * @param  array<int, array<string, mixed>>  $tree
 */
function kbSeed(array $entries, array $tree): void
{
    // The tree goes first, so each save finds its entry already placed and leaves it there
    kbStoreTree($tree);

    foreach ($entries as $entry) {
        $entry->saveQuietly();
        kbNewRequest();
    }
}

/**
 * Store a site's tree, then end the request.
 *
 * @param  array<int, array<string, mixed>>  $tree
 */
function kbStoreTree(array $tree, string $site = 'default'): void
{
    $stored = kbTree($site);
    $stored->tree($tree);
    $stored->save();
    kbNewRequest();
}

/**
 * The default-site hierarchy the localization tests start from: root, topic, article.
 */
function kbSeedHierarchy(): KnowledgeBaseEntry
{
    kbSeed(
        [kbSaveEntry('kb-root', 'knowledge_base', 'kb'), kbSaveEntry('kb-topic', 'topic', 'topic'), kbSaveEntry('kb-article', 'article', 'article')],
        [['entry' => 'kb-root', 'children' => [['entry' => 'kb-topic', 'children' => [['entry' => 'kb-article']]]]]],
    );

    return kbFind('kb-root');
}

/**
 * Map a stored tree to the origin id of each entry, so a localized hierarchy reads like its source.
 *
 * @param  array<array-key, mixed>  $tree
 * @return array<array-key, array<string, mixed>>
 */
function kbTreeByOrigin(array $tree): array
{
    return array_map(function (mixed $node): array {
        $node = mustBeStringKeyed($node, 'a tree node');
        $mapped = ['origin' => kbOriginId(kbAnyEntry(mustBeString($node['entry'], 'a tree node entry')))];
        if (isset($node['children'])) {
            $mapped['children'] = kbTreeByOrigin(mustBeArray($node['children'], 'a tree node children'));
        }

        return $mapped;
    }, $tree);
}

/**
 * The ids of the `knowledge_bases` entries on a site whose origin is the given id.
 *
 * @return list<string>
 */
function kbLocalizationsOf(string $originId, string $site = 'health'): array
{
    kbNewRequest();

    $query = mustBe(EntryQueryBuilder::class, EntryFacade::query(), 'an entry query');
    $query->where('collection', 'knowledge_bases');
    $query->where('locale', $site);
    $query->where('origin', $originId);

    $entries = mustBe(Illuminate\Support\Collection::class, $query->get(), 'the queried entries');

    return array_values(array_map(
        fn (mixed $entry): string => mustBeString(mustBe(StatamicEntry::class, $entry, 'a queried entry')->id(), 'an entry id'),
        $entries->all(),
    ));
}

/**
 * The slugs of the `kb_tags` terms, as the term query sees them now.
 *
 * @return list<mixed>
 */
function kbTermSlugs(): array
{
    $terms = mustBe(TermQueryBuilder::class, Term::query(), 'a term query');
    $terms->where('taxonomy', 'kb_tags');

    return array_values(array_map(
        fn (mixed $term): mixed => mustBe(LocalizedTerm::class, $term, 'a term')->slug(),
        mustBe(Illuminate\Support\Collection::class, $terms->get(), 'the queried terms')->all(),
    ));
}

describe('structure placement on save', function (): void {
    it('appends a new entry under the parent in its data, against a tree read before the entry existed', function (): void {
        kbSeed([kbSaveEntry('kb-root', 'knowledge_base', 'kb')], [['entry' => 'kb-root']]);

        // The request's first tree read happens before the topic exists
        kbTree()->tree();
        $topic = kbSaveEntry('kb-topic', 'topic', 'topic');
        $topic->set('parent', 'kb-root');

        expect($topic->save())->toBeTrue()
            ->and(kbRawTree())->toBe([['entry' => 'kb-root', 'children' => [['entry' => 'kb-topic']]]]);
    });

    it('appends a new entry with no parent at the top level, against a tree read before it existed', function (): void {
        kbSeed([kbSaveEntry('kb-root', 'knowledge_base', 'kb')], [['entry' => 'kb-root']]);

        kbTree()->tree();
        kbSaveEntry('kb-second', 'knowledge_base', 'second')->save();

        expect(kbRawTree())->toBe([['entry' => 'kb-root'], ['entry' => 'kb-second']]);
    });

    it('keeps an entry with no parent in its data under its current structure parent', function (): void {
        kbSeed(
            [kbSaveEntry('kb-root', 'knowledge_base', 'kb'), kbSaveEntry('kb-topic', 'topic', 'topic')],
            [['entry' => 'kb-root', 'children' => [['entry' => 'kb-topic']]]],
        );

        $topic = kbFind('kb-topic');
        $topic->set('title', 'Renamed');

        expect($topic->save())->toBeTrue()
            ->and(kbRawTree())->toBe([['entry' => 'kb-root', 'children' => [['entry' => 'kb-topic']]]]);
    });

    it('appends a new entry under its parent whatever the request read first', function (): void {
        kbSeed([kbSaveEntry('kb-root', 'knowledge_base', 'kb')], [['entry' => 'kb-root']]);

        $topic = kbSaveEntry('kb-topic', 'topic', 'topic');
        $topic->set('parent', 'kb-root');
        $topic->save();

        expect(kbRawTree())->toBe([['entry' => 'kb-root', 'children' => [['entry' => 'kb-topic']]]]);
    });

    it('skips an after-save callback that is not callable', function (): void {
        $entry = kbSaveEntry('kb-root', 'knowledge_base', 'kb');
        $calls = [];
        (new ReflectionProperty(StatamicEntry::class, 'afterSaveCallbacks'))
            ->setValue($entry, ['not a function', function ($saved) use (&$calls): void {
                $calls[] = mustBe(StatamicEntry::class, $saved, 'the saved entry')->id();
            }]);

        expect($entry->save())->toBeTrue()
            ->and($calls)->toBe(['kb-root']);
    });

    it('adds nothing to the tree of an orderable collection', function (): void {
        kbSaveFixture(['structureContents' => ['root' => false, 'max_depth' => 1]]);
        kbNewRequest();

        $addToStructure = new ReflectionMethod(KnowledgeBaseEntry::class, 'addToStructure');

        expect($addToStructure->invoke(kbSaveEntry('kb-root', 'knowledge_base', 'kb'), 'default'))->toBeNull();
    });

    it('adds nothing to the tree of a collection with no structure', function (): void {
        kbSaveFixture(['structure' => null]);
        kbNewRequest();

        $addToStructure = new ReflectionMethod(KnowledgeBaseEntry::class, 'addToStructure');
        $entry = kbSaveEntry('kb-root', 'knowledge_base', 'kb');

        expect($addToStructure->invoke($entry, 'default'))->toBeNull()
            ->and($entry->save())->toBeTrue();
    });
});

describe('localization on save', function (): void {
    /**
     * Seed a root with a topic and a glossary child, the glossary with a child of its own.
     * The tree validator names no rule for `glossary`, so a localization of one may stay
     * unplaced in the target tree without the validator rejecting the next tree save.
     */
    function kbSeedWithGlossary(): KnowledgeBaseEntry
    {
        kbSeed(
            [
                kbSaveEntry('kb-root', 'knowledge_base', 'kb'),
                kbSaveEntry('kb-topic', 'topic', 'topic'),
                kbSaveEntry('kb-glossary', 'glossary', 'glossary'),
                kbSaveEntry('kb-glossary-child', 'glossary', 'glossary-child'),
            ],
            [['entry' => 'kb-root', 'children' => [
                ['entry' => 'kb-topic'],
                ['entry' => 'kb-glossary', 'children' => [['entry' => 'kb-glossary-child']]],
            ]]],
        );

        return kbFind('kb-root');
    }

    it('does not localize the subtree twice when the localized root is saved again', function (): void {
        kbLocalize(kbSeedHierarchy(), 'kb-root-health')->save();
        kbNewRequest();

        kbFind('kb-root-health')->save();

        expect(kbLocalizationsOf('kb-topic'))->toHaveCount(1)
            ->and(kbLocalizationsOf('kb-article'))->toHaveCount(1);
    });

    it('saves a localized topic again, which only a new direct localization is refused', function (): void {
        kbLocalize(kbSeedHierarchy(), 'kb-root-health')->save();
        kbNewRequest();

        expect(kbFind(kbLocalizationsOf('kb-topic')[0])->save())->toBeTrue();
    });

    it('does not cascade from a second localization of a root already localized in the target site', function (): void {
        kbLocalize(kbSeedHierarchy(), 'kb-root-health')->save();
        kbNewRequest();

        kbLocalize(kbFind('kb-root'), 'kb-root-health-2')->save();

        $placed = json_encode(kbRawTree('health'));
        expect(kbLocalizationsOf('kb-root'))->toHaveCount(2)
            ->and(kbLocalizationsOf('kb-topic'))->toHaveCount(1)
            ->and($placed)->not->toContain('kb-root-health-2');
    });

    it('skips a child already localized in the target site, with its subtree', function (): void {
        kbSeedWithGlossary();
        kbLocalize(kbFind('kb-glossary'), 'kb-glossary-health', true)->saveQuietly();
        kbNewRequest();

        kbLocalize(kbFind('kb-root'), 'kb-root-health')->save();

        expect(kbLocalizationsOf('kb-topic'))->toHaveCount(1)
            ->and(kbLocalizationsOf('kb-glossary'))->toBe(['kb-glossary-health'])
            ->and(kbLocalizationsOf('kb-glossary-child'))->toBe([]);
    });

    it('logs and skips a child page whose entry cannot be found', function (): void {
        kbSeedWithGlossary();
        $ghost = kbFind('kb-glossary-child');
        $root = kbFind('kb-root');

        // Memoize the tree with the child in it, then remove the entry behind it
        kbTree()->tree();
        kbEntriesStore('knowledge_bases')->delete($ghost);
        $log = kbLogSpy();

        kbLocalize($root, 'kb-root-health')->save();

        $log->shouldHaveReceived('warning')->with(
            'KnowledgeBaseEntry::localizeDescendantsFromStructure: Child entry not found in structure',
            ['child_id' => 'kb-glossary-child', 'parent_id' => kbLocalizationsOf('kb-glossary')[0]],
        )->once();
        expect(kbLocalizationsOf('kb-topic'))->toHaveCount(1);
    });

    it('saves the localized root of an orderable collection, which has no tree to add it to', function (): void {
        kbSaveFixture(['structureContents' => ['root' => false, 'max_depth' => 1]]);
        kbNewRequest();
        kbSeed([kbSaveEntry('kb-root', 'knowledge_base', 'kb')], [['entry' => 'kb-root']]);

        expect(kbLocalize(kbFind('kb-root'), 'kb-root-health')->save())->toBeTrue()
            ->and(kbLocalizationsOf('kb-root'))->toBe(['kb-root-health']);
    });

    it('rejects saving a new topic or article localization made directly, and logs it', function (string $origin, string $blueprint): void {
        kbSeedHierarchy();
        $log = kbLogSpy();

        $localization = kbSaveEntry('kb-local', $blueprint, 'local', 'health');
        $localization->origin($origin);

        expect(fn () => $localization->save())->toThrow(
            Exception::class,
            sprintf("Only Knowledge Base entries can be localized. '%s' entries are not allowed to be localized directly.", $blueprint),
        );
        $log->shouldHaveReceived('error')->with(
            'KnowledgeBaseEntry::save: Throwing exception due to invalid blueprint',
            ['entry_id' => 'kb-local', 'blueprint' => $blueprint],
        )->once();
    })->with([['kb-topic', 'topic'], ['kb-article', 'article']]);

    it('saves a glossary localization, which no rule names', function (): void {
        kbSeed([kbSaveEntry('kb-origin', 'glossary', 'origin')], [['entry' => 'kb-origin']]);

        $localization = kbSaveEntry('kb-local', 'glossary', 'origin', 'health');
        $localization->origin('kb-origin');

        expect($localization->save())->toBeTrue()
            ->and(kbLocalizationsOf('kb-origin'))->toBe(['kb-local']);
    });
});

describe('makeLocalization', function (): void {
    it('copies the collection, origin, site, published state and slug', function (): void {
        $root = kbSeedHierarchy();
        $root->published(false);

        $localization = $root->makeLocalization('health');

        expect($localization->collectionHandle())->toBe('knowledge_bases')
            ->and(kbOriginId($localization))->toBe('kb-root')
            ->and($localization->locale())->toBe('health')
            ->and($localization->published())->toBeFalse()
            ->and($localization->slug())->toBe('kb');
    });

    it('rejects a direct topic or article localization and logs it', function (string $blueprint): void {
        $entry = kbSaveEntry('kb-'.$blueprint, $blueprint, $blueprint);
        $log = kbLogSpy();

        expect(fn () => $entry->makeLocalization('health'))->toThrow(
            Exception::class,
            sprintf("Only Knowledge Base entries can be localized. '%s' entries are not allowed to be localized directly.", $blueprint),
        );
        $log->shouldHaveReceived('error')->with(
            'KnowledgeBaseEntry::makeLocalization: Throwing exception due to invalid blueprint',
            ['entry_id' => 'kb-'.$blueprint, 'blueprint' => $blueprint],
        )->once();
    })->with(['topic', 'article']);

    it('allows a topic localization made for a descendant cascade', function (): void {
        kbSeedHierarchy();

        $localization = kbFind('kb-topic')->makeLocalization('health', true);

        expect($localization->locale())->toBe('health')
            ->and(kbOriginId($localization))->toBe('kb-topic');
    });

    it('places a glossary localization at the top when its parent has no localization in the target tree', function (): void {
        kbSeed([kbSaveEntry('kb-root', 'knowledge_base', 'kb'), kbSaveEntry('kb-glossary', 'glossary', 'glossary')], [
            ['entry' => 'kb-root', 'children' => [['entry' => 'kb-glossary']]],
        ]);

        kbLocalize(kbFind('kb-glossary'), 'kb-glossary-health')->save();

        expect(kbRawTree('health'))->toBe([['entry' => 'kb-glossary-health']]);
    });

    it('places nothing in a site the collection has no tree in', function (): void {
        kbSeed([kbSaveEntry('kb-glossary', 'glossary', 'glossary')], [['entry' => 'kb-glossary']]);
        $glossary = kbFind('kb-glossary');

        $append = (new ReflectionMethod(KnowledgeBaseEntry::class, 'addToStructure'))->invoke($glossary, 'health_ar_impact');
        expect($append)->toBeInstanceOf(Closure::class);
        mustBe(Closure::class, $append, 'the structure append')($glossary);

        expect(kbRawTree())->toBe([['entry' => 'kb-glossary']]);
    });

    it('places a top-level glossary localization at the top of the target tree', function (): void {
        kbSeed([kbSaveEntry('kb-glossary', 'glossary', 'glossary')], [['entry' => 'kb-glossary']]);

        kbLocalize(kbFind('kb-glossary'), 'kb-glossary-health')->save();

        expect(kbRawTree('health'))->toBe([['entry' => 'kb-glossary-health']]);
    });

    it('registers no callback for a knowledge base root or a cascade localization', function (): void {
        kbSeedHierarchy();
        $callbacks = new ReflectionProperty(StatamicEntry::class, 'afterSaveCallbacks');

        expect($callbacks->getValue(kbFind('kb-root')->makeLocalization('health')))->toBe([])
            ->and($callbacks->getValue(kbSaveEntry('kb-glossary', 'glossary', 'glossary')->makeLocalization('health', true)))->toBe([]);
    });
});

describe('events and side effects of save', function (): void {
    it('cancels a new entry when EntryCreating returns false', function (): void {
        Event::listen(EntryCreating::class, fn () => false);
        $log = kbLogSpy();

        expect(kbSaveEntry('kb-root', 'knowledge_base', 'kb')->save())->toBeFalse()
            ->and(EntryFacade::find('kb-root'))->toBeNull();
        $log->shouldHaveReceived('notice')->with('KnowledgeBaseEntry::save: EntryCreating event returned false', ['entry_id' => 'kb-root'])->once();
    });

    it('cancels the save when EntrySaving returns false', function (): void {
        Event::listen(EntrySaving::class, fn () => false);
        $log = kbLogSpy();

        expect(kbSaveEntry('kb-root', 'knowledge_base', 'kb')->save())->toBeFalse()
            ->and(EntryFacade::find('kb-root'))->toBeNull();
        $log->shouldHaveReceived('notice')->with('KnowledgeBaseEntry::save: EntrySaving event returned false', ['entry_id' => 'kb-root'])->once();
    });

    it('does not ask EntryCreating about an entry that already exists', function (): void {
        kbSeed([kbSaveEntry('kb-root', 'knowledge_base', 'kb')], [['entry' => 'kb-root']]);
        Event::listen(EntryCreating::class, fn () => false);

        expect(kbFind('kb-root')->save())->toBeTrue();
    });

    it('dispatches EntryCreated for a new entry and EntrySaved for every save', function (): void {
        Event::fake([EntryCreated::class, EntrySaved::class]);

        kbSaveEntry('kb-root', 'knowledge_base', 'kb')->save();
        Event::assertDispatchedTimes(EntryCreated::class, 1);
        Event::assertDispatchedTimes(EntrySaved::class, 1);
        kbNewRequest();

        kbFind('kb-root')->save();
        Event::assertDispatchedTimes(EntryCreated::class, 1);
        Event::assertDispatchedTimes(EntrySaved::class, 2);
    });

    it('dispatches no events on a quiet save, and the next save is not quiet', function (): void {
        Event::fake([EntryCreating::class, EntrySaving::class, EntryCreated::class, EntrySaved::class]);

        $entry = kbSaveEntry('kb-root', 'knowledge_base', 'kb');
        expect($entry->saveQuietly())->toBeTrue();
        Event::assertNotDispatched(EntryCreating::class);
        Event::assertNotDispatched(EntrySaving::class);
        Event::assertNotDispatched(EntryCreated::class);
        Event::assertNotDispatched(EntrySaved::class);

        $entry->save();
        Event::assertDispatched(EntrySaving::class);
        Event::assertDispatched(EntrySaved::class);
    });

    it('sets an auto-generated title over a typed one', function (): void {
        kbSaveFixture(['titleFormats' => 'Generated title']);
        kbNewRequest();

        $entry = kbSaveEntry('kb-root', 'knowledge_base', 'kb');
        $entry->set('title', 'Typed');
        $entry->save();

        expect($entry->get('title'))->toBe('Generated title');
    });

    it('leaves the title of a localization whose generated title matches its origin', function (): void {
        kbSaveFixture(['titleFormats' => 'Generated title']);
        kbNewRequest();
        kbSeed([kbSaveEntry('kb-root', 'knowledge_base', 'kb')], [['entry' => 'kb-root']]);

        $localization = kbSaveEntry('kb-local', 'knowledge_base', 'kb', 'health');
        $localization->origin('kb-root');
        $localization->data([]);
        $localization->saveQuietly();

        expect(mustBe(Illuminate\Support\Collection::class, $localization->data(), 'the localization data')->has('title'))->toBeFalse();
    });

    it('clears the per-entry caches, the origin cache and the collection mounts', function (): void {
        kbSeed([kbSaveEntry('kb-root', 'knowledge_base', 'kb')], [['entry' => 'kb-root']]);
        kbLocalize(kbFind('kb-root'), 'kb-local')->saveQuietly();
        kbNewRequest();

        $local = kbFind('kb-local');
        foreach (['entry-uris', 'structure-uris', 'structure-entries'] as $store) {
            kbBlinkStore($store)->put('kb-local', 'stale');
        }
        kbBlinkStore('collection-mounts')->put('anything', 'stale');
        $originKey = mustBeString((new ReflectionMethod(StatamicEntry::class, 'getOriginBlinkKey'))->invoke($local), 'the origin Blink key');
        $stale = kbSaveEntry('kb-stale', 'knowledge_base', 'stale');
        Blink::put($originKey, $stale);

        $local->saveQuietly();

        foreach (['entry-uris', 'structure-uris', 'structure-entries'] as $store) {
            expect(kbBlinkStore($store)->has('kb-local'))->toBeFalse();
        }
        // `origin()` reloads the key later in the save, so only the stale value must be gone
        expect(kbBlinkStore('collection-mounts')->has('anything'))->toBeFalse()
            ->and(Blink::get($originKey))->not->toBe($stale);
    });

    it('clears the descendant cache of the origin it was localized from', function (): void {
        // Glossary entries run no localization cascade, which would clear caches of its own
        kbSeed([kbSaveEntry('kb-glossary', 'glossary', 'glossary'), kbSaveEntry('kb-other', 'glossary', 'other')], [['entry' => 'kb-glossary'], ['entry' => 'kb-other']]);
        kbLocalize(kbFind('kb-glossary'), 'kb-local')->saveQuietly();
        kbNewRequest();
        $stale = collect();
        Blink::put('entry-descendants-kb-glossary', $stale);
        Blink::put('entry-descendants-kb-other', $stale);

        kbFind('kb-local')->saveQuietly();

        // A later read in the save may rebuild the key, so only the stale value must be gone
        expect(Blink::get('entry-descendants-kb-glossary'))->not->toBe($stale)
            ->and(Blink::get('entry-descendants-kb-other'))->toBe($stale);
    });

    it('saves the direct descendants, quietly on a quiet save', function (): void {
        kbSeed([kbSaveEntry('kb-root', 'knowledge_base', 'kb')], [['entry' => 'kb-root']]);
        kbLocalize(kbFind('kb-root'), 'kb-local')->saveQuietly();
        kbNewRequest();

        Event::fake([EntrySaved::class]);
        kbFind('kb-root')->save();
        Event::assertDispatched(EntrySaved::class, fn (EntrySaved $event): bool => mustBe(StatamicEntry::class, $event->entry, 'the saved entry')->id() === 'kb-local');

        kbNewRequest();
        Event::fake([EntrySaved::class]);
        kbFind('kb-root')->saveQuietly();
        Event::assertNotDispatched(EntrySaved::class);
    });

    it('does not save the descendants of an entry the cascade just localized', function (): void {
        kbSeed([kbSaveEntry('kb-root', 'knowledge_base', 'kb')], [['entry' => 'kb-root']]);
        kbLocalize(kbFind('kb-root'), 'kb-local')->saveQuietly();
        kbNewRequest();

        (new ReflectionProperty(KnowledgeBaseEntry::class, 'localizedEntries'))->setValue(null, ['kb-root' => true]);
        Event::fake([EntrySaved::class]);
        kbFind('kb-root')->save();

        Event::assertDispatched(EntrySaved::class, fn (EntrySaved $event): bool => mustBe(StatamicEntry::class, $event->entry, 'the saved entry')->id() === 'kb-root');
        Event::assertNotDispatched(EntrySaved::class, fn (EntrySaved $event): bool => mustBe(StatamicEntry::class, $event->entry, 'the saved entry')->id() === 'kb-local');
    });

    it('stores the slug normalized', function (): void {
        $entry = kbSaveEntry('kb-root', 'knowledge_base', 'kb');
        $entry->slug('Hello World');
        $entry->save();

        expect((new ReflectionProperty(StatamicEntry::class, 'slug'))->getValue($entry))->toBe('hello-world');
    });

    it('falls back to the current structure parent when the parent in its data does not exist', function (): void {
        kbSeed([kbSaveEntry('kb-root', 'knowledge_base', 'kb')], [['entry' => 'kb-root']]);

        kbTree()->tree();
        $second = kbSaveEntry('kb-second', 'knowledge_base', 'second');
        $second->set('parent', 'kb-missing');

        expect($second->save())->toBeTrue()
            ->and(kbRawTree())->toBe([['entry' => 'kb-root'], ['entry' => 'kb-second']]);
    });

    it('syncs the taxonomy terms the entry names', function (): void {
        $taxonomy = mustBe(Taxonomy::class, Statamic\Facades\Taxonomy::make('kb_tags'), 'the kb_tags taxonomy');
        $taxonomy->sites(['default', 'health']);
        $taxonomy->save();
        kbSaveFixture(['taxonomies' => ['kb_tags']]);
        kbNewRequest();

        // Load the term associations first, so the save has to update them rather than a later rebuild
        expect(kbTermSlugs())->toBe([]);

        $entry = kbSaveEntry('kb-root', 'knowledge_base', 'kb');
        $entry->set('kb_tags', ['alpha']);
        $entry->saveQuietly();

        expect(kbTermSlugs())->toBe(['alpha']);
    });

    it('pops itself off the save initiator stack', function (): void {
        $entry = kbSaveEntry('kb-root', 'knowledge_base', 'kb');
        $entry->save();

        expect(Blink::get('entry-event-initiator-kb-root'))->toEqual(collect());
    });

    it('is clean after a save', function (): void {
        $entry = kbSaveEntry('kb-root', 'knowledge_base', 'kb');
        $entry->save();
        $entry->set('title', 'Changed');
        $entry->save();

        expect($entry->isDirty())->toBeFalse();
    });
});

describe('URIs on save', function (): void {
    it('re-stamps the child page URIs when a parent slug changes', function (): void {
        kbSeed(
            [kbSaveEntry('kb-root', 'knowledge_base', 'kb'), kbSaveEntry('kb-topic', 'topic', 'topic')],
            [['entry' => 'kb-root', 'children' => [['entry' => 'kb-topic']]]],
        );
        expect(kbUriIndex('knowledge_bases')->get('kb-topic'))->toBe('/kb/topic');

        $root = kbFind('kb-root');
        $root->slug('renamed');
        $root->save();

        expect(kbUriIndex('knowledge_bases')->get('kb-topic'))->toBe('/renamed/topic');
    });

    it('updates the entries of a collection mounted on the entry when its slug changes', function (): void {
        kbSeed([kbSaveEntry('kb-root', 'knowledge_base', 'kb')], [['entry' => 'kb-root']]);
        $mounted = mustBe(Collection::class, CollectionFacade::make('kb_mounted'), 'the kb_mounted collection');
        $mounted->routes('{mount}/{slug}');
        $mounted->mount('kb-root');
        $mounted->save();
        kbPlainEntry('mounted-one', 'kb_mounted', 'one')->saveQuietly();
        kbNewRequest();
        expect(kbUriIndex('kb_mounted')->get('mounted-one'))->toBe('/kb/one');

        $root = kbFind('kb-root');
        $root->slug('moved');
        $root->save();

        expect(kbUriIndex('kb_mounted')->get('mounted-one'))->toBe('/moved/one');
    });

    it('skips the child URI update when the collection is orderable, has no route, or the entry has no page or children', function (): void {
        $update = new ReflectionMethod(KnowledgeBaseEntry::class, 'updateChildPageUris');
        kbSeed(
            [kbSaveEntry('kb-root', 'knowledge_base', 'kb'), kbSaveEntry('kb-topic', 'topic', 'topic')],
            [['entry' => 'kb-root', 'children' => [['entry' => 'kb-topic']]]],
        );
        // Every case but the last has a page with children, so only its own condition stops the update
        $parentPage = kbFind('kb-root')->page();
        $leafPage = kbFind('kb-topic')->page();

        foreach ([[true, '{slug}', $parentPage], [false, null, $parentPage], [false, '{slug}', null], [false, '{slug}', $leafPage]] as [$orderable, $route, $entryPage]) {
            $collection = Mockery::mock(Collection::class);
            $collection->shouldReceive('orderable')->andReturn($orderable);
            $collection->shouldNotReceive('updateEntryUris');

            $entry = Mockery::mock(KnowledgeBaseEntry::class)->makePartial();
            $entry->shouldReceive('collection')->andReturn($collection);
            $entry->shouldReceive('route')->andReturn($route);
            $entry->shouldReceive('page')->andReturn($entryPage);

            $update->invoke($entry);
        }
    });

    it('re-stamps the mounted subtree when a root mount reference changes, and only then', function (): void {
        kbSeed([kbSaveEntry('kb-root', 'knowledge_base', 'kb'), kbSaveEntry('kb-topic', 'topic', 'topic')], [
            ['entry' => 'kb-root', 'children' => [['entry' => 'kb-topic']]],
        ]);
        $refresher = Mockery::mock(KnowledgeBaseMountUriRefresher::class);
        app()->instance(KnowledgeBaseMountUriRefresher::class, $refresher);
        $refresher->shouldReceive('restampSubtree')->once()->with(Mockery::on(fn (mixed $root): bool => $root instanceof KnowledgeBaseEntry && $root->id() === 'kb-root'));

        $root = kbFind('kb-root');
        $root->set(KnowledgeBaseEntry::MOUNT_FIELD, ['page-1']);
        $root->save();

        // An unchanged mount on the root, and a changed one on a topic: neither re-stamps
        $root->set('title', 'Again');
        $root->save();
        $topic = kbFind('kb-topic');
        $topic->set(KnowledgeBaseEntry::MOUNT_FIELD, ['page-1']);
        $topic->save();
    });
});

describe('the mount prefix in route data', function (): void {
    /**
     * A routed `kb_pages` collection with a `host` page and a structured `home` page at `/`.
     */
    function kbMountPages(): void
    {
        $pages = mustBe(Collection::class, CollectionFacade::make('kb_pages'), 'the kb_pages collection');
        $pages->routes('{parent_uri}/{slug}');
        $pages->structureContents(['root' => true]);
        $pages->save();
        kbPlainEntry('page-home', 'kb_pages', 'home')->saveQuietly();
        kbPlainEntry('page-host', 'kb_pages', 'host')->saveQuietly();
        kbNewRequest();

        $structure = mustBe(CollectionStructure::class, kbCollection('kb_pages')->structure(), 'the kb_pages structure');
        $tree = mustBe(Tree::class, $structure->in('default'), 'the kb_pages tree');
        $tree->tree([['entry' => 'page-home'], ['entry' => 'page-host']]);
        $tree->save();
        kbNewRequest();
    }

    it('is the mount parent URI for a mounted root', function (mixed $mount): void {
        kbMountPages();

        $root = kbSaveEntry('kb-root', 'knowledge_base', 'kb');
        $root->set(KnowledgeBaseEntry::MOUNT_FIELD, $mount);

        expect($root->routeData()['mount_prefix'])->toBe('/host');
    })->with([
        'an entries-field list' => [['page-host']],
        'a single id' => ['page-host'],
    ]);

    it('is empty without a usable mount, or for a topic', function (string $blueprint, mixed $mount): void {
        kbMountPages();

        $entry = kbSaveEntry('kb-'.$blueprint, $blueprint, 'kb');
        $entry->set(KnowledgeBaseEntry::MOUNT_FIELD, $mount);

        expect($entry->routeData()['mount_prefix'])->toBe('');
    })->with([
        'no mount' => ['knowledge_base', null],
        'an empty list' => ['knowledge_base', []],
        'a missing entry' => ['knowledge_base', ['nope']],
        'a mount at the site root' => ['knowledge_base', ['page-home']],
        'a topic with a mount' => ['topic', ['page-host']],
    ]);
});
