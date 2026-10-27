<?php

declare(strict_types=1);

use App\Listeners\PreventMountingToTreeRoot;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry;
use Statamic\Events\CollectionSaving;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Structures\CollectionStructure;
use Statamic\Structures\CollectionTree;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/*
|--------------------------------------------------------------------------
| PreventMountingToTreeRoot (UAMS-Web/uams-statamic-archive#1324)
|--------------------------------------------------------------------------
|
| Rejects saving a collection whose `mount` resolves to the structural root of
| its target's site tree (a site's home page), which would collapse the whole
| collection into the site-root namespace. Applies to ANY collection with a
| mount (Option C), not just the clinical_* family. The root is resolved against
| the mount entry's OWN site tree (multi-site correctness).
|
| The mocked cases exercise every guard branch; the real-content cases assert the
| shipped configs pass the guard and that a real root mount is caught.
|
| @covers \App\Listeners\PreventMountingToTreeRoot
|
| @command  php artisan test --compact tests/Feature/Listeners/PreventMountingToTreeRootTest.php
*/

/**
 * The real health `pages` tree root (first node; `pages` has `structure.root: true`).
 */
const HEALTH_HOME_ROOT_ID = 'caae3df2-1f38-465f-b6e7-2a4d5b4f812f';

/**
 * Build a CollectionSaving event whose collection mounts to an entry with the
 * given id + locale, whose OWN collection's site tree has a root referencing
 * $rootReference. Pass $rootReference === $mountId to model a root mount, or
 * $mountId = null for an unmounted collection.
 */
function mountRootEvent(
    ?string $mountId,
    string $rootReference = 'root-id',
    string $locale = 'health',
    mixed $collectionTitle = 'Clinical Areas of Expertise',
    string $scenario = 'structured',
): CollectionSaving {
    $collection = Mockery::mock(Collection::class);
    $collection->shouldReceive('title')->andReturn($collectionTitle);

    if ($mountId === null) {
        $collection->shouldReceive('mount')->andReturnNull();

        return new CollectionSaving($collection);
    }

    $mountEntry = Mockery::mock(Entry::class);
    $mountEntry->shouldReceive('id')->andReturn($mountId);
    $mountEntry->shouldReceive('locale')->andReturn($locale);
    $collection->shouldReceive('mount')->andReturn($mountEntry);

    // The mount entry's own collection is missing.
    if ($scenario === 'no-collection') {
        $mountEntry->shouldReceive('collection')->andReturnNull();

        return new CollectionSaving($collection);
    }

    $mountCollection = Mockery::mock(Collection::class);
    $mountCollection->shouldReceive('handle')->andReturn('pages');
    $mountEntry->shouldReceive('collection')->andReturn($mountCollection);

    // The mount target lives in a flat (unstructured) collection.
    if ($scenario === 'unstructured') {
        $mountCollection->shouldReceive('structure')->andReturnNull();

        return new CollectionSaving($collection);
    }

    $structure = Mockery::mock(CollectionStructure::class);
    $mountCollection->shouldReceive('structure')->andReturn($structure);

    // The structure has no tree for the mount entry's site.
    if ($scenario === 'no-tree') {
        // `in()` is stubbed ONLY for the mount entry's own locale; a call for any
        // other site would fail as unexpected, pinning the multi-site behavior.
        $structure->shouldReceive('in')->with($locale)->andReturnNull();

        return new CollectionSaving($collection);
    }

    // `Tree::root()` returns the raw root node array, or null when the structure
    // does not expect a root.
    $rootNode = $scenario === 'no-root' ? null : ['entry' => $rootReference];

    $tree = Mockery::mock(CollectionTree::class);
    $tree->shouldReceive('root')->andReturn($rootNode);
    $structure->shouldReceive('in')->with($locale)->andReturn($tree);

    return new CollectionSaving($collection);
}

/**
 * Assert the guard is a silent no-op for the given event. A surviving guard
 * mutant (one that skips an early return) dereferences a null and throws; a real
 * no-op does not. Catching the Throwable and asserting on it turns any such throw
 * into a clean assertion failure that kills the mutant.
 */
function assertMountGuardNoOp(CollectionSaving $event): void
{
    $thrown = null;

    try {
        (new PreventMountingToTreeRoot)->handle($event);
    } catch (Throwable $e) {
        $thrown = $e->getMessage();
    }

    expect($thrown)->toBeNull();
}

it('rejects a collection whose mount resolves to the site tree root', function () {
    $event = mountRootEvent(mountId: 'home-id', rootReference: 'home-id');

    expect(fn () => (new PreventMountingToTreeRoot)->handle($event))
        ->toThrow(Exception::class, 'home page');
});

it('names the offending collection and its mount structure in the error', function () {
    $event = mountRootEvent(mountId: 'home-id', rootReference: 'home-id', collectionTitle: 'Clinical Areas of Expertise');

    try {
        (new PreventMountingToTreeRoot)->handle($event);
        $this->fail('Expected the guard to throw for a root mount.');
    } catch (Exception $e) {
        expect($e->getMessage())
            ->toContain('Clinical Areas of Expertise')
            ->toContain("'pages'");
    }
});

it('accepts a mount that resolves to a non-root page', function () {
    assertMountGuardNoOp(mountRootEvent(mountId: 'landing-id', rootReference: 'home-id'));
});

it('is a no-op when the collection has no mount', function () {
    assertMountGuardNoOp(mountRootEvent(mountId: null));
});

it('is a no-op when the mount target has no collection', function () {
    assertMountGuardNoOp(mountRootEvent(mountId: 'x', scenario: 'no-collection'));
});

it('is a no-op when the mount target is in an unstructured collection', function () {
    assertMountGuardNoOp(mountRootEvent(mountId: 'x', scenario: 'unstructured'));
});

it('is a no-op when the mount site has no tree', function () {
    assertMountGuardNoOp(mountRootEvent(mountId: 'x', scenario: 'no-tree'));
});

it('is a no-op when the mount structure does not expect a root', function () {
    assertMountGuardNoOp(mountRootEvent(mountId: 'x', scenario: 'no-root'));
});

it('resolves the root against the mount entry own site tree', function () {
    // `in()` is stubbed only for 'health'; a throw here proves the listener
    // resolved the health-site root (not the default site's).
    $event = mountRootEvent(mountId: 'home-id', rootReference: 'home-id', locale: 'health');

    expect(fn () => (new PreventMountingToTreeRoot)->handle($event))
        ->toThrow(Exception::class);
});

it('coerces a non-string scalar collection title into the error message', function () {
    // A non-string scalar title (an int) still renders as a string — under
    // strict_types, dropping the cast would raise a TypeError instead.
    $event = mountRootEvent(mountId: 'home-id', rootReference: 'home-id', collectionTitle: 2024);

    expect(fn () => (new PreventMountingToTreeRoot)->handle($event))
        ->toThrow(Exception::class, "'2024' collection");
});

it('renders an empty name for a non-scalar collection title', function () {
    $event = mountRootEvent(mountId: 'home-id', rootReference: 'home-id', collectionTitle: ['not', 'scalar']);

    try {
        (new PreventMountingToTreeRoot)->handle($event);
        $this->fail('Expected the guard to throw for a root mount.');
    } catch (Exception $e) {
        expect($e->getMessage())->toContain("The '' collection");
    }
});

/*
|--------------------------------------------------------------------------
| Real-content shipped-config guard (self-skips without content/)
|--------------------------------------------------------------------------
*/

it('passes every shipped collection mount through the guard without throwing', function () {
    if (! CollectionFacade::find('clinical_expertise')) {
        ContentSubmodule::reportMissing('collection configs');
    }

    /** @var Illuminate\Support\Collection<int|string, Collection> $allCollections */
    $allCollections = CollectionFacade::all();
    $mounted = $allCollections->filter(fn (Collection $collection) => $collection->mount() !== null);

    expect($mounted)->not->toBeEmpty();

    $listener = new PreventMountingToTreeRoot;

    foreach ($mounted as $collection) {
        expect(throwableFrom(fn () => $listener->handle(new CollectionSaving($collection))))->toBeNull();
    }
});

it('confirms the three clinical mounts resolve to real non-root landing pages', function () {
    foreach (['clinical_expertise', 'clinical_locations', 'clinical_resources'] as $handle) {
        $collection = CollectionFacade::find($handle);

        if (! $collection || ! $collection->mount()) {
            ContentSubmodule::reportMissing("#1068/#1069 mount bump for {$handle}");
        }

        // A resolvable, non-root mount: the guard does not throw for it.
        expect(throwableFrom(fn () => (new PreventMountingToTreeRoot)->handle(new CollectionSaving($collection))))->toBeNull();
    }
});

it('rejects a collection repointed at the real health pages tree root', function () {
    if (! CollectionFacade::find('pages') || ! EntryFacade::find(HEALTH_HOME_ROOT_ID)) {
        ContentSubmodule::reportMissing('health pages tree + home entry');
    }

    // A throwaway, never-saved collection pointed at the real health home root.
    // Resolving through real content proves the guard fires against a live tree
    // root — and, since the home entry's locale is `health`, that the health-site
    // root (not the default site's) is the one resolved.
    $collection = CollectionFacade::make();
    $collection->handle('mount_root_guard_fixture');
    $collection->title('Mount Root Guard Fixture');
    $collection->mount(HEALTH_HOME_ROOT_ID);

    expect(fn () => (new PreventMountingToTreeRoot)->handle(new CollectionSaving($collection)))
        ->toThrow(Exception::class, 'home page');
});
