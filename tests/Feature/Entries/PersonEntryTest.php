<?php

declare(strict_types=1);

use App\Entries\PersonEntry;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Query\Builder;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;

/**
 * Unit coverage for the People profile-page suppression (UAMS-Web/uams-statamic-archive#43).
 *
 * `PersonEntry::route()` returns null only when `profile_page_query` is
 * explicitly false, making `Entry::uri()` short-circuit to null (a 404 for
 * `people/{slug}`) while the entry stays published and queryable. An unset
 * value (legacy entries) and an explicit true both keep the collection route.
 *
 * The override is exercised with partial mocks so the logic is covered without
 * the `content/` submodule or the Stache — `get()`, `collection()`, and
 * `locale()` are the only collaborators `route()` touches.
 *
 * A content-gated integration test additionally proves the Stache read path
 * hydrates `people` rows as `PersonEntry` (via `App\Stache\CollectionEntriesStore`),
 * without which the `route()` override would reach only `Entry::find()` and not
 * the `uri` index / routing. It self-skips without the `content/` submodule.
 *
 * @command  php artisan test --compact tests/Feature/Entries/PersonEntryTest.php
 */
uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(PersonEntry::class);

it('suppresses the route when profile_page_query is explicitly false', function (): void {
    $entry = Mockery::mock(PersonEntry::class)->makePartial();
    $entry->shouldReceive('get')
        ->with('profile_page_query')
        ->andReturn(false);

    // No profile page → no route → Statamic 404s the URL.
    /** @var PersonEntry $entry */
    expect($entry->route())->toBeNull();
});

it('keeps the collection route when profile_page_query is true', function (): void {
    $collection = Mockery::mock();
    $collection->shouldReceive('route')
        ->with('en')
        ->andReturn('people/{slug}');

    $entry = Mockery::mock(PersonEntry::class)->makePartial();
    $entry->shouldReceive('get')
        ->with('profile_page_query')
        ->andReturn(true);
    $entry->shouldReceive('collection')
        ->andReturn($collection);
    $entry->shouldReceive('locale')
        ->andReturn('en');

    /** @var PersonEntry $entry */
    expect($entry->route())->toBe('people/{slug}');
});

it('keeps the collection route when profile_page_query is unset (default routable)', function (): void {
    $collection = Mockery::mock();
    $collection->shouldReceive('route')
        ->with('en')
        ->andReturn('people/{slug}');

    $entry = Mockery::mock(PersonEntry::class)->makePartial();
    // Legacy entries saved before the field existed return null, not false.
    $entry->shouldReceive('get')
        ->with('profile_page_query')
        ->andReturn(null);
    $entry->shouldReceive('collection')
        ->andReturn($collection);
    $entry->shouldReceive('locale')
        ->andReturn('en');

    /** @var PersonEntry $entry */
    expect($entry->route())->toBe('people/{slug}');
});

it('hydrates people entries as PersonEntry through the stache', function (): void {
    // The Stache index/query path builds entries from CollectionEntriesStore, not
    // Entry::find(); without the people branch there, route suppression would be
    // invisible to the uri index and routing.
    /** @var Builder $query */
    $query = EntryFacade::query();
    $query->where('collection', 'people');
    $entry = $query->first();

    if (! $entry) {
        ContentSubmodule::reportMissing('people collection');
    }

    expect($entry)->toBeInstanceOf(PersonEntry::class);

    // The hydrated instance must carry the live route() override, so the uri
    // index / routing honor suppression — not just Entry::find(). Toggling the
    // flag in memory (no save) proves the override is active on this instance.
    /** @var PersonEntry $entry */
    $entry->set('profile_page_query', false);
    expect($entry->route())->toBeNull();

    $entry->set('profile_page_query', true);
    expect($entry->route())->not->toBeNull();
});
