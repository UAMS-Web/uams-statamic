<?php

declare(strict_types=1);

use Statamic\Entries\Entry;
use Statamic\Facades\Collection;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;

/**
 * Coverage for the UAMS-Web/uams-statamic-archive#1070 `people` mount: the collection mounts to the dedicated
 * `/providers` landing entry so the clinical-provider directory surfaces there,
 * WITHOUT disturbing the existing per-entry `people/{slug}` route or the flat,
 * structure-less shape of the collection.
 *
 * The mount lives in the `content/` submodule (people.yaml + the `/providers`
 * landing entry), so these assertions skip when that companion is absent.
 *
 * @command php artisan test --compact tests/Feature/PeopleProvidersMountTest.php
 */
uses(TestCase::class)->group('serial');

beforeEach(function (): void {
    $people = Collection::findByHandle('people');

    if ($people === null || $people->mount() === null) {
        ContentSubmodule::reportMissing('/providers mount');
    }
});

it('mounts people to the dedicated /providers landing entry', function (): void {
    $people = mustBe(Statamic\Entries\Collection::class, Collection::findByHandle('people'), 'people');
    $mount = mustBe(Entry::class, $people->mount(), 'mount');

    expect($mount)->not->toBeNull()
        ->and($mount->slug())->toBe('providers')
        ->and($mount->collectionHandle())->toBe('pages');
});

it('leaves the per-entry people route unchanged', function (): void {
    // The mount must not repoint individual people to `/providers/{slug}` — the
    // existing route and the non-clinical multi-site people are untouched.
    $people = mustBe(Statamic\Entries\Collection::class, Collection::findByHandle('people'), 'people');
    expect($people->route('health'))->toBe('people/{slug}');
});

it('keeps the people collection flat (no structure)', function (): void {
    // The provider subset is flat (D8): no descendant blueprints, no tree, no
    // depth-gated mount token.
    $people = mustBe(Statamic\Entries\Collection::class, Collection::findByHandle('people'), 'people');
    expect($people->hasStructure())->toBeFalse();
});

it('drives the /providers directory through the people/provider/index template', function (): void {
    // The landing entry renders the directory via `people/provider/index` → `people/provider/_listing`
    // (the eligible-provider card grid). The mount does not auto-serve a collection
    // index view — DataResponse renders the landing entry's own template — so the
    // landing must name it explicitly. Asserted at the config level so it does not
    // depend on built front-end (Vite) assets. Verified end-to-end: GET /health/providers
    // renders the directory (its empty state when no provider is eligible).
    $people = mustBe(Statamic\Entries\Collection::class, Collection::findByHandle('people'), 'people');
    $mount = mustBe(Entry::class, $people->mount(), 'mount');
    expect($mount->template())->toBe('people/provider/index');
});
