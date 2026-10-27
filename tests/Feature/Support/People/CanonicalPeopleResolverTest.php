<?php

declare(strict_types=1);

use App\Support\People\CanonicalPeopleResolver;
use Statamic\Entries\Entry as EntryInstance;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Behavioral coverage for {@see CanonicalPeopleResolver} — the cross-site substrate
 * that resolves the single canonical `people` entry for a slug (UAMS-Web/uams-statamic-archive#1635).
 *
 * People entries live only on the canonical `default` site; the resolver must find
 * them from ANY current-site context (the lens routes render on `health`, department
 * sites, etc.). Fixtures live in an isolated Stache; `resources/sites.yaml` still
 * supplies the full multi-site list, so `Site::hasMultiple()` is true — the condition
 * under which Statamic's site scoping bites.
 *
 * @covers \App\Support\People\CanonicalPeopleResolver
 *
 * @command php artisan test --compact tests/Feature/Support/People/CanonicalPeopleResolverTest.php
 */
uses(StacheIsolatedTestCase::class);

afterEach(function (): void {
    Site::setCurrent('default');
});

beforeEach(function (): void {
    $people = CollectionFacade::make('people');
    $people->title('People');
    $people->sites(['default', 'health']);
    $people->save();
});

/** Persist a `people` entry on the given site (default = the canonical site). */
function makeResolverPerson(string $slug, string $locale = 'default'): EntryInstance
{
    /** @var EntryInstance $entry */
    $entry = Entry::make();
    $entry->collection('people');
    $entry->locale($locale);
    $entry->slug($slug);
    $entry->published(true);
    $entry->data(['title' => 'Person '.$slug]);
    $entry->saveQuietly();

    return $entry;
}

it('resolves a canonical default-site person from a non-default current site', function (): void {
    $person = makeResolverPerson('leonard-mccoy');

    // Render context is a site the entry has no localized copy on.
    Site::setCurrent('health');

    $resolved = mustExist(app(CanonicalPeopleResolver::class)->resolve('leonard-mccoy'), 'resolved');

    expect($resolved)->not->toBeNull()
        ->and($resolved->id())->toBe($person->id())
        ->and($resolved->locale())->toBe('default');
});

it('returns the canonical-site entry when a same-slug entry exists on another site', function (): void {
    // The raw Entry::query() does NOT auto-scope to the current site (unlike the
    // `{{ collection }}` tag), so the `where('site', canonicalSite())` filter is the
    // load-bearing invariant: a same-slug entry on another site must not win.
    $default = makeResolverPerson('shared-slug', 'default');
    makeResolverPerson('shared-slug', 'health');

    Site::setCurrent('health');

    $resolved = mustExist(app(CanonicalPeopleResolver::class)->resolve('shared-slug'), 'resolved');

    expect($resolved->locale())->toBe('default')
        ->and($resolved->id())->toBe($default->id());
});

it('honors a non-default canonical site from config, pinning the where(site) wiring', function (): void {
    // With canonical_site=health, resolve() must find a health entry and NOT a
    // default-only one — this fails if the site filter is removed or hardcoded to default.
    config()->set('people_lenses.canonical_site', 'health');

    makeResolverPerson('health-only', 'health');
    makeResolverPerson('default-only', 'default');

    expect(app(CanonicalPeopleResolver::class)->resolve('health-only'))->not->toBeNull()
        ->and(app(CanonicalPeopleResolver::class)->resolve('default-only'))->toBeNull();
});

it('returns null for a slug with no canonical entry', function (): void {
    makeResolverPerson('leonard-mccoy');

    Site::setCurrent('health');

    expect(app(CanonicalPeopleResolver::class)->resolve('nobody'))->toBeNull();
});

it('returns null for an empty slug without querying', function (): void {
    expect(app(CanonicalPeopleResolver::class)->resolve(''))->toBeNull();
});

it('reads the canonical site from config, falling back to default', function (): void {
    expect(app(CanonicalPeopleResolver::class)->canonicalSite())->toBe('default');

    config()->set('people_lenses.canonical_site', '');
    expect(app(CanonicalPeopleResolver::class)->canonicalSite())->toBe('default');

    config()->set('people_lenses.canonical_site', 'health');
    expect(app(CanonicalPeopleResolver::class)->canonicalSite())->toBe('health');
});
