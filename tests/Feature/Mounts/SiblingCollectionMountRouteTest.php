<?php

declare(strict_types=1);

use Statamic\Entries\Entry;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Fields\Blueprint;
use Statamic\Structures\CollectionStructure;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;

uses(TestCase::class);

/**
 * The sibling-collection mounts (UAMS-Web/uams-statamic-archive#1068 / UAMS-Web/uams-statamic-archive#1069): `clinical_locations` and
 * `clinical_resources` are each mounted wholesale to their own dedicated `pages`
 * landing entry (`/locations`, `/resources`), so their entries become publicly
 * addressable under that base path. `clinical_locations` uses the depth-gated mount
 * route; `clinical_resources` is flat (`{mount}/{slug}`).
 *
 * These assert the wired mount config + the resolved URL shape of a real saved
 * entry (created then deleted). They require the `content/` submodule (which
 * carries the mount config + landing entries) and self-skip without it.
 *
 * @command  php artisan test --compact tests/Feature/Mounts/SiblingCollectionMountRouteTest.php
 */
beforeEach(function () {
    if (! Collection::find('clinical_resources') || ! Collection::find('clinical_locations')) {
        ContentSubmodule::reportMissing('sibling-collection configs');
    }
    if (! Collection::find('clinical_resources')->mount()) {
        ContentSubmodule::reportMissing('#1068/#1069 mount pointer bump');
    }
});

it('mounts clinical_resources flat at its landing entry ({mount}/{slug})', function () {
    /** @var Statamic\Entries\Collection $collection */
    $collection = Collection::find('clinical_resources');

    // Config: flat mount route, no depth-gate.
    expect($collection->route('health'))->toBe('{mount}/{slug}');
    expect($collection->mount())->not->toBeNull();

    // A real entry resolves under the landing entry's URI.
    /** @var Illuminate\Support\Collection<int, Blueprint> $entryBlueprints */
    $entryBlueprints = $collection->entryBlueprints();
    /** @var Blueprint $firstBlueprint */
    $firstBlueprint = $entryBlueprints->first();
    $blueprint = $firstBlueprint->handle();
    $slug = 'mount-test-resource-'.uniqid();
    /** @var Entry $entry */
    $entry = EntryFacade::make();
    /** @var Entry $entry */
    $entry = $entry->collection('clinical_resources');
    /** @var Entry $entry */
    $entry = $entry->blueprint($blueprint);
    /** @var Entry $entry */
    $entry = $entry->locale('health');
    /** @var Entry $entry */
    $entry = $entry->slug($slug);
    /** @var Entry $entry */
    $entry = $entry->data(['title' => 'Mount Test Resource']);
    $entry->saveQuietly();

    try {
        expect($entry->url())->toEndWith('/resources/'.$slug);
    } finally {
        $entry->delete();
    }
});

it('mounts clinical_locations with the depth-gated route at its landing entry', function () {
    /** @var Statamic\Entries\Collection $collection */
    $collection = Collection::find('clinical_locations');

    // The depth-gated mount route (mount appears exactly once at every depth) —
    // the same form proven for the AoE mount (UAMS-Web/uams-statamic-archive#1054/UAMS-Web/uams-statamic-archive#1058); the actual URL
    // resolution at nesting depth needs a structure-tree position and is covered
    // by that shared mechanism + the wave browser-smoke.
    expect($collection->route('health'))->toBe('{{ depth == 1 ?= mount }}/{{ parent_uri }}/{{ slug }}');

    // The mount targets the dedicated /locations landing entry.
    /** @var Entry $mount */
    $mount = $collection->mount();
    expect($mount)->not->toBeNull();
    expect($mount->uri())->toBe('/locations');

    // The coarse collection-wide max_depth is gone (per-blueprint depth is now the
    // ValidateClinicalLocationsTree listener's job).
    /** @var CollectionStructure|null $structure */
    $structure = $collection->structure();
    expect($structure?->maxDepth())->toBeNull();
});
