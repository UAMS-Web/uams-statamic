<?php

declare(strict_types=1);

use Spatie\Blink\Blink as BlinkStore;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry as EntryInstance;
use Statamic\Facades\Blink;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Structures\CollectionStructure;
use Statamic\Support\Blink as BlinkStores;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Regression guard for UAMS-Web/uams-statamic-archive#1845. `StacheIsolatedTestCase` re-roots every Stache store
 * at a throwaway directory, which changes only what those stores READ FROM DISK —
 * it cannot reach what Statamic already memoized into Blink during the application
 * boot in `parent::setUp()`.
 *
 * UAMS-Web/uams-statamic-archive#1851 pinned one consequence of that and UAMS-Web/uams-statamic-archive#1867 closed it with a `Blink::flush()`.
 * Its guard ({@see StacheIsolatedTestCaseInvariantTest}) asserts the *index* half —
 * `Collection::handles()` agreeing with `findByHandle()`. This file guards the half
 * UAMS-Web/uams-statamic-archive#1845 reported, which fails with a different null, a different trace, and no
 * involvement of `handles()` at all: the structure/tree resolution path.
 *
 * `Statamic\Structures\CollectionStructure::in($site)` hands back that site's tree,
 * or null when the collection does not live in that site:
 *
 *     $tree = app(CollectionTreeRepository::class)->find($this->collection()->handle(), $site);
 *     if (! $tree && $this->existsIn($site)) { $tree = $this->makeTree($site); }
 *
 * `$this->collection()` is `Blink::once('collection-structure-collection-<handle>')`
 * and `existsIn()` asks *that* collection which sites it lives on — so a memo left
 * standing from the real `content/` submodule answers for a collection the test
 * never built. Real `clinical_expertise` is `sites: [health]` while the fixture is
 * on `default`, so `existsIn('default')` is false, `in()` returns null, and
 * `Statamic\Entries\Entry::page()` dies on `->find($id)`: the
 * `Call to a member function find() on null` UAMS-Web/uams-statamic-archive#1845 reported.
 *
 * Worth stating plainly, because the ticket recorded it the other way round:
 * `structure()` is **not** the null on that line. `structure()` resolves fine;
 * `structure()->in()` is what returns null.
 *
 * Only the first two tests here are guards — they go red if the base stops
 * clearing Blink. The last two are characterization: they construct the poisoned
 * state deliberately, so they pass either way, and exist to pin the mechanism the
 * guards protect against.
 *
 * @command  php artisan test --compact tests/Feature/StacheIsolatedStructureResolutionTest.php
 */
uses(StacheIsolatedTestCase::class);

/**
 * The named Blink stores the base clears because they mirror the re-rooted Stache.
 *
 * Mirrors `StacheIsolatedTestCase::STACHE_DERIVED_BLINK_STORES`. The `blueprints.*`
 * stores are deliberately absent from both — the base re-roots the blueprint
 * directory to protect against a clobbering WRITE, not to hide real blueprints
 * from reads, and `EntriesByBlueprintDynamicDerivationTest` reads them on purpose.
 *
 * @var array<int, string>
 */
const STACHE_DERIVED_BLINK_STORES = [
    'entry-uris',
    'structure-uris',
    'structure-entries',
    'collection-mounts',
];

/**
 * The names of the stores currently held by the `Statamic\Support\Blink` wrapper.
 *
 * Reads the private store map because a count alone cannot carry the assertion:
 * `Blink::store($name)` CREATES what it is asked for
 * (`$this->stores[$name] ??= new SpatieBlink`), so a count of 0 reads identically
 * for "the base flushed a populated store" and "this store never existed". Presence
 * in the map is what disambiguates — see the guard below.
 *
 * Narrows with `instanceof` and `is_array()` rather than annotations, so a change
 * to the facade binding or the property's shape fails loudly instead of being
 * asserted away.
 *
 * @return array<int, string>
 */
function blinkStoreNames(): array
{
    $root = Blink::getFacadeRoot();

    if (! $root instanceof BlinkStores) {
        return ['<the Blink facade no longer resolves to Statamic\Support\Blink>'];
    }

    $stores = (new ReflectionProperty(BlinkStores::class, 'stores'))->getValue($root);

    if (! is_array($stores)) {
        return ['<Statamic\Support\Blink::$stores is no longer an array>'];
    }

    return array_map(strval(...), array_keys($stores));
}

/**
 * Build the structured `clinical_expertise` fixture UAMS-Web/uams-statamic-archive#1845's test builds: a routed
 * collection with a structure, left on the default site, plus the blueprint its
 * entries need.
 */
function isolatedExpertiseFixture(): void
{
    /** @var Collection $expertise */
    $expertise = CollectionFacade::make('clinical_expertise');
    /** @var Collection $expertise */
    $expertise = $expertise->title('Areas of Expertise');
    /** @var Collection $expertise */
    $expertise = $expertise->routes('{slug}');
    /** @var Collection $expertise */
    $expertise = $expertise->structureContents(['root' => false]);
    $expertise->save();

    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = Blueprint::make('clinical_expertise');
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = $blueprint->setNamespace('collections.clinical_expertise');
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = $blueprint->setContents([
        'tabs' => ['main' => ['sections' => [['fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text']],
        ]]]]],
    ]);
    $blueprint->save();
}

it('inherits no default-store memo computed before the stores were re-rooted', function (): void {
    // The boot in `parent::setUp()` leaves 900+ keys standing against the real
    // `content/`. Asserting emptiness rather than naming the handful of keys UAMS-Web/uams-statamic-archive#1845
    // and UAMS-Web/uams-statamic-archive#1851 happened to trip over is deliberate: any memo of a real collection
    // can answer for one the test never built, and the next one to bite will not be
    // a key this file knew to name.
    //
    // `array_keys` so a failure prints the offending key names rather than a wall
    // of serialized collections and entries.
    expect(array_keys(Blink::all()))->toBe([]);
});

it('inherits nothing in the Stache-derived named stores either', function (): void {
    // `Blink::flush()` reaches only the `default` store — `Statamic\Support\Blink`
    // is a map of Spatie stores and its `__call` forwards through `store()`. These
    // four kept 1,002 values of real-`content/` state behind that flush:
    // `structure-entries` 728, `entry-uris` 153, `structure-uris` 120,
    // `collection-mounts` 1 (UAMS-Web/uams-statamic-archive#1845).
    //
    // Read the map BEFORE touching any store, since asking for one creates it.
    $names = blinkStoreNames();

    foreach (STACHE_DERIVED_BLINK_STORES as $name) {
        /** @var BlinkStore $store */
        $store = Blink::store($name);

        // Present AND empty is the exact signature of the flush having run, and
        // nothing else produces it: if the base stopped clearing these, a store the
        // boot populated is present but non-empty, and one it never touched is
        // absent from the map altogether. Asserting emptiness alone would pass in
        // that second case, which is the false green this pair closes.
        expect($names)->toContain($name)
            ->and($store->all())->toBe([]);
    }
});

it('resolves a structured fixture collection into a tree for the entry it saves', function (): void {
    isolatedExpertiseFixture();

    /** @var EntryInstance $entry */
    $entry = Entry::make();
    /** @var EntryInstance $entry */
    $entry = $entry->collection('clinical_expertise');
    /** @var EntryInstance $entry */
    $entry = $entry->blueprint('clinical_expertise');
    /** @var EntryInstance $entry */
    $entry = $entry->slug('cancer-care');
    /** @var EntryInstance $entry */
    $entry = $entry->published(true);
    /** @var EntryInstance $entry */
    $entry = $entry->data(['title' => 'Cancer Care']);

    // This save is where UAMS-Web/uams-statamic-archive#1845 died: `Entry::save()` reaches `page()`, which walks
    // `structure()->in($this->locale())->find($id)`. A memo answering for the
    // health-only real collection makes the middle call null, so the save fatals
    // instead of returning.
    $entry->saveQuietly();

    expect($entry->page())->not->toBeNull();
});

it('cannot resolve a tree once the structure answers for a collection on other sites', function (): void {
    // Plant what a boot against real `content/` leaves behind:
    // `CollectionStructure::collection()` memoized against the REAL
    // `clinical_expertise`, which is `sites: [health]`.
    //
    // Planted rather than inherited because the clearing this file guards has
    // already run by the time a test body starts. Pinning the hazard here is what
    // stops those guards from being narrowed into a tautology later — it shows
    // there is a live failure on the other side of them.
    //
    // Planted AFTER the fixture save. A boot-time memo comes first in a real run,
    // but since statamic/cms 6.35.0 `Collection::save()` forgets
    // `collection-structure-collection-{$id}`, so a memo planted before the save is
    // cleared by it. Planting afterwards still pins what the guards protect
    // against: a memo standing after the save answers for the collection. In a
    // real run that now happens only to a collection not re-saved after boot.
    isolatedExpertiseFixture();

    /** @var Collection $real */
    $real = CollectionFacade::make('clinical_expertise');
    /** @var Collection $real */
    $real = $real->title('Clinical Areas of Expertise');
    /** @var Collection $real */
    $real = $real->sites(['health']);
    /** @var Collection $real */
    $real = $real->structureContents(['root' => false]);
    Blink::put('collection-structure-collection-clinical_expertise', $real);

    /** @var Collection $fixture */
    $fixture = CollectionFacade::findByHandle('clinical_expertise');
    /** @var CollectionStructure $structure */
    $structure = $fixture->structure();

    // The fixture's own structure resolves — only the site lookup behind `in()` is
    // poisoned, which is why UAMS-Web/uams-statamic-archive#1845 surfaced as a null deep inside a vendor call
    // rather than as a collection that could not be found.
    //
    // `existsIn()` is asserted directly because it IS the predicate that decides
    // this: it reads `$this->collection()->sites()`, and `$this->collection()` is
    // the planted memo rather than the fixture. The sibling test above saves an
    // entry into the same fixture without fataling, which is what establishes that
    // the fixture really is resolvable on `default`.
    expect($structure)->not->toBeNull()
        ->and($structure->existsIn('default'))->toBeFalse()
        ->and($structure->in('default'))->toBeNull();
});
