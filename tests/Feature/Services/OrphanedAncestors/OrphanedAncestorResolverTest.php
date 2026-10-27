<?php

declare(strict_types=1);

use App\Services\OrphanedAncestors\OrphanedAncestorResolver;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry;
use Statamic\Facades\Blink;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Sites\Site;
use Statamic\Structures\Page;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * @covers \App\Services\OrphanedAncestors\OrphanedAncestorResolver
 *
 * UAMS-Web/uams-statamic-archive#1429: collection-agnostic detection of a published entry orphaned by an
 * unpublished structural ancestor. `resolve()` walks the structure tree and,
 * when any ancestor is unpublished, targets the nearest LIVE ancestor (the
 * published ancestor above the highest unpublished break, so it never itself
 * redirects), else the collection mount root, else the site root. Pure Mockery
 * doubles, so no content/ submodule is required.
 *
 * Accepted equivalent mutants (not killable without contriving inputs Statamic
 * never produces): the `$nearest === null || $topmost === null` guard (both are
 * always set-together or null-together, so `||` ≡ `&&`); the `$result instanceof
 * … ? $result : null` Blink narrowing (both branches yield the same value for a
 * null result); the `in_array(…, true)` strict flag (string handles compare
 * identically loose or strict); the Blink memoization-key concatenation (any
 * reordering stays unique per entry); and the `instanceof` / `is_string`
 * defensive guards that exist only to satisfy PHPStan on Statamic's untyped
 * getters (`sites()`, `mount()`, `site()`, `locale()`).
 *
 * @command  php artisan test --compact tests/Feature/Services/OrphanedAncestors/OrphanedAncestorResolverTest.php
 */
beforeEach(fn () => Blink::flush());

/** A collection double that the resolver handles (routable + hierarchical + enabled). */
function orphanCollection(string $handle = 'test_hierarchy', ?string $route = '{parent_uri}/{slug}', bool $structured = true, ?string $mountUrl = null, string $mountStatus = 'published'): Collection
{
    $collection = Mockery::mock(Collection::class);
    $collection->shouldReceive('handle')->andReturn($handle);
    $collection->shouldReceive('hasStructure')->andReturn($structured);
    $collection->shouldReceive('route')->andReturn($route);

    if ($mountUrl !== null) {
        $mount = Mockery::mock(Entry::class);
        $mount->shouldReceive('url')->andReturn($mountUrl);
        $mount->shouldReceive('status')->andReturn($mountStatus);
        $collection->shouldReceive('mount')->andReturn($mount);
    } else {
        $collection->shouldReceive('mount')->andReturnNull();
    }

    return $collection;
}

/**
 * Build a published descendant entry sitting under an ancestor chain.
 *
 * @param  array<int, array{status: string, url?: string|null, title?: string|null}>  $ancestors  Nearest-parent-first
 * @param  string  $siteUrl  The entry site's root URL (final redirect fallback)
 */
function orphanEntry(array $ancestors, string $siteUrl = 'https://site.test', ?string $mountUrl = null, ?Collection $collection = null): Entry
{
    $site = Mockery::mock(Site::class);
    $site->shouldReceive('url')->andReturn($siteUrl);

    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('id')->andReturn('descendant');
    $entry->shouldReceive('locale')->andReturn('default');
    $entry->shouldReceive('site')->andReturn($site);
    $entry->shouldReceive('collection')->andReturn($collection ?? orphanCollection(mountUrl: $mountUrl));

    // Build ancestor pages 1..N; page[N] parents to the (excluded) structure root.
    $ancestorPages = [];
    $count = count($ancestors);

    foreach ($ancestors as $i => $anc) {
        $k = $i + 1;

        $page = Mockery::mock(Page::class);
        $page->shouldReceive('id')->andReturn('anc-'.$i);
        $page->shouldReceive('isRoot')->andReturn(false);
        $ancestorPages[$k] = $page;

        $ancestorEntry = Mockery::mock(Entry::class);
        $ancestorEntry->shouldReceive('status')->andReturn($anc['status']);
        $ancestorEntry->shouldReceive('url')->andReturn($anc['url'] ?? null);
        $ancestorEntry->shouldReceive('get')->with('title')->andReturn($anc['title'] ?? null);
        EntryFacade::shouldReceive('find')->with('anc-'.$i)->andReturn($ancestorEntry);
    }

    // The structural root sits above the topmost ancestor and is excluded.
    $rootPage = Mockery::mock(Page::class);
    $rootPage->shouldReceive('isRoot')->andReturn(true);
    $ancestorPages[$count + 1] = $rootPage;

    $entryPage = Mockery::mock(Page::class);
    $entryPage->shouldReceive('isRoot')->andReturn(false);
    $entryPage->shouldReceive('parent')->andReturn($ancestorPages[1] ?? $rootPage);
    $entry->shouldReceive('page')->andReturn($entryPage);

    foreach ($ancestorPages as $k => $page) {
        if ($k <= $count) {
            $page->shouldReceive('parent')->andReturn($ancestorPages[$k + 1]);
        }
    }

    return $entry;
}

/**
 * Assert resolve() is a clean null — no result AND no thrown Error. Catching the
 * Throwable turns a guard mutant that dereferences a null into an assertion
 * failure (a bare `->toBeNull()` would merely error out, which does not kill it).
 */
function expectResolvesToNull(Entry $entry): void
{
    $outcome = 'unset';

    try {
        $outcome = (new OrphanedAncestorResolver)->resolve($entry);
    } catch (Throwable $e) {
        $outcome = 'threw: '.$e->getMessage();
    }

    expect($outcome)->toBeNull();
}

/*
|--------------------------------------------------------------------------
| resolve() — orphan detection + redirect targets
|--------------------------------------------------------------------------
*/

it('returns null when every ancestor is published', function () {
    expectResolvesToNull(orphanEntry([
        ['status' => 'published', 'url' => '/parent'],
        ['status' => 'published', 'url' => '/grandparent'],
    ]));
});

it('redirects to the nearest live ancestor above the unpublished break', function () {
    // parent published, grandparent unpublished, great-grandparent published.
    $entry = orphanEntry([
        ['status' => 'published', 'url' => '/parent'],
        ['status' => 'draft', 'url' => '/grandparent', 'title' => 'Cardiology'],
        ['status' => 'published', 'url' => '/great-grandparent'],
    ]);

    $result = (new OrphanedAncestorResolver)->resolve($entry);

    expect($result?->redirectUrl)->toBe('/great-grandparent');
    expect($result?->unpublishedAncestorTitle)->toBe('Cardiology');
});

it('redirects a published child of an unpublished parent to the published grandparent', function () {
    $entry = orphanEntry([
        ['status' => 'draft', 'url' => '/parent', 'title' => 'Hidden Parent'],
        ['status' => 'published', 'url' => '/grandparent'],
    ]);

    $result = (new OrphanedAncestorResolver)->resolve($entry);

    expect($result?->redirectUrl)->toBe('/grandparent');
    expect($result?->unpublishedAncestorTitle)->toBe('Hidden Parent');
});

it('names the nearest unpublished ancestor but targets above the highest break', function () {
    // nearest unpublished = parent (title); highest break = the deeper draft.
    $entry = orphanEntry([
        ['status' => 'draft', 'url' => '/parent', 'title' => 'Nearest Draft'],
        ['status' => 'published', 'url' => '/gp'],
        ['status' => 'draft', 'url' => '/ggp', 'title' => 'Higher Draft'],
        ['status' => 'published', 'url' => '/gggp'],
    ]);

    $result = (new OrphanedAncestorResolver)->resolve($entry);

    expect($result?->redirectUrl)->toBe('/gggp');
    expect($result?->unpublishedAncestorTitle)->toBe('Nearest Draft');
});

it('falls back to the collection mount root when no live ancestor is above the break', function () {
    // The highest ancestor is unpublished, so there is no live ancestor above it.
    $entry = orphanEntry(
        [
            ['status' => 'published', 'url' => '/parent'],
            ['status' => 'draft', 'url' => '/top', 'title' => 'Offline Top'],
        ],
        mountUrl: '/health/expertise',
    );

    expect((new OrphanedAncestorResolver)->resolve($entry)?->redirectUrl)->toBe('/health/expertise');
});

it('skips an unpublished mount root and falls back to the site root', function () {
    // The mount landing is itself unpublished, so redirecting there would 302
    // onto a 404 — the site root is used instead.
    $entry = orphanEntry(
        [
            ['status' => 'published', 'url' => '/parent'],
            ['status' => 'draft', 'url' => '/top', 'title' => 'Offline Top'],
        ],
        siteUrl: 'https://site.test',
        collection: orphanCollection(mountUrl: '/health/expertise', mountStatus: 'draft'),
    );

    expect((new OrphanedAncestorResolver)->resolve($entry)?->redirectUrl)->toBe('https://site.test');
});

it('falls back to the site root when there is no live ancestor and no mount', function () {
    $entry = orphanEntry(
        [
            ['status' => 'draft', 'url' => '/top', 'title' => 'Offline Top'],
        ],
        siteUrl: 'https://site.test',
        mountUrl: null,
    );

    expect((new OrphanedAncestorResolver)->resolve($entry)?->redirectUrl)->toBe('https://site.test');
});

it('falls back past a live ancestor that has no url', function () {
    $entry = orphanEntry(
        [
            ['status' => 'draft', 'url' => '/top', 'title' => 'Offline Top'],
            ['status' => 'published', 'url' => null],
        ],
        mountUrl: '/mount-root',
    );

    expect((new OrphanedAncestorResolver)->resolve($entry)?->redirectUrl)->toBe('/mount-root');
});

it('treats a scheduled ancestor as unpublished', function () {
    $entry = orphanEntry([
        ['status' => 'scheduled', 'url' => '/parent', 'title' => 'Scheduled'],
        ['status' => 'published', 'url' => '/grandparent'],
    ]);

    expect((new OrphanedAncestorResolver)->resolve($entry)?->redirectUrl)->toBe('/grandparent');
});

it('returns null when the entry has no structure page', function () {
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('collection')->andReturn(orphanCollection());
    $entry->shouldReceive('locale')->andReturn('default');
    $entry->shouldReceive('id')->andReturn('rootless');
    $entry->shouldReceive('page')->andReturnNull();

    expectResolvesToNull($entry);
});

it('returns null when the entry has no ancestors below the root', function () {
    expectResolvesToNull(orphanEntry([]));
});

it('returns null and never dereferences when the entry has no collection', function () {
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('collection')->andReturnNull();
    $entry->shouldReceive('locale')->andReturn('default');

    expectResolvesToNull($entry);
});

it('resolves an unsaved (no string id) orphaned entry without memoizing', function () {
    // A not-yet-saved entry has a null id, so it takes the non-memoized path.
    $entry = orphanEntry([
        ['status' => 'draft', 'url' => '/parent', 'title' => 'Draft'],
        ['status' => 'published', 'url' => '/grandparent'],
    ]);
    /** @var MockInterface&Entry $entry */
    $entry->shouldReceive('id')->andReturnNull();

    expect((new OrphanedAncestorResolver)->resolve($entry)?->redirectUrl)->toBe('/grandparent');
});

it('skips a dangling ancestor reference that resolves to no entry', function () {
    // The nearest ancestor page resolves to no entry (a dangling structure ref);
    // the walk ascends past it to the unpublished grandparent.
    $site = Mockery::mock(Site::class);
    $site->shouldReceive('url')->andReturn('https://site.test');

    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('id')->andReturn('descendant');
    $entry->shouldReceive('locale')->andReturn('default');
    $entry->shouldReceive('site')->andReturn($site);
    $entry->shouldReceive('collection')->andReturn(orphanCollection());

    $rootPage = Mockery::mock(Page::class);
    $rootPage->shouldReceive('isRoot')->andReturn(true);

    $gpPage = Mockery::mock(Page::class);
    $gpPage->shouldReceive('id')->andReturn('gp');
    $gpPage->shouldReceive('isRoot')->andReturn(false);
    $gpPage->shouldReceive('parent')->andReturn($rootPage);

    $danglingPage = Mockery::mock(Page::class);
    $danglingPage->shouldReceive('id')->andReturn('dangling');
    $danglingPage->shouldReceive('isRoot')->andReturn(false);
    $danglingPage->shouldReceive('parent')->andReturn($gpPage);

    $entryPage = Mockery::mock(Page::class);
    $entryPage->shouldReceive('isRoot')->andReturn(false);
    $entryPage->shouldReceive('parent')->andReturn($danglingPage);
    $entry->shouldReceive('page')->andReturn($entryPage);

    EntryFacade::shouldReceive('find')->with('dangling')->andReturnNull();
    $gp = Mockery::mock(Entry::class);
    $gp->shouldReceive('status')->andReturn('draft');
    $gp->shouldReceive('url')->andReturn('/gp');
    $gp->shouldReceive('get')->with('title')->andReturn('Draft GP');
    EntryFacade::shouldReceive('find')->with('gp')->andReturn($gp);

    // Only the grandparent is an ancestor; it is unpublished with no live ancestor
    // above it, so the redirect falls through to the site root.
    expect((new OrphanedAncestorResolver)->resolve($entry)?->redirectUrl)->toBe('https://site.test');
});

it('returns a null title when the unpublished ancestor has no readable title', function () {
    $entry = orphanEntry([
        ['status' => 'draft', 'url' => '/parent'],
        ['status' => 'published', 'url' => '/grandparent'],
    ]);

    $result = (new OrphanedAncestorResolver)->resolve($entry);

    expect($result?->redirectUrl)->toBe('/grandparent');
    expect($result?->unpublishedAncestorTitle)->toBeNull();
});

it('does not resolve for an unhandled collection (not routable or hierarchical)', function () {
    // A flat collection (no structure) is never handled.
    expectResolvesToNull(orphanEntry([
        ['status' => 'draft', 'url' => '/parent', 'title' => 'Draft'],
    ], collection: orphanCollection(structured: false)));
});

it('does not resolve for an opted-out collection', function () {
    config()->set('orphaned_ancestors.disabled_collections', ['test_hierarchy']);

    expectResolvesToNull(orphanEntry([
        ['status' => 'draft', 'url' => '/parent', 'title' => 'Draft'],
    ]));
});

it('stops the walk cleanly when a mid-chain page has no parent', function () {
    $site = Mockery::mock(Site::class);
    $site->shouldReceive('url')->andReturn('https://site.test');

    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('id')->andReturn('descendant');
    $entry->shouldReceive('locale')->andReturn('default');
    $entry->shouldReceive('site')->andReturn($site);
    $entry->shouldReceive('collection')->andReturn(orphanCollection());

    // The lone ancestor page has NO parent (null), not a structure root, so the
    // walk must stop there without dereferencing the null.
    $ancPage = Mockery::mock(Page::class);
    $ancPage->shouldReceive('id')->andReturn('anc');
    $ancPage->shouldReceive('isRoot')->andReturn(false);
    $ancPage->shouldReceive('parent')->andReturnNull();

    $entryPage = Mockery::mock(Page::class);
    $entryPage->shouldReceive('isRoot')->andReturn(false);
    $entryPage->shouldReceive('parent')->andReturn($ancPage);
    $entry->shouldReceive('page')->andReturn($entryPage);

    $anc = Mockery::mock(Entry::class);
    $anc->shouldReceive('status')->andReturn('draft');
    $anc->shouldReceive('url')->andReturn('/anc');
    $anc->shouldReceive('get')->with('title')->andReturn('Draft');
    EntryFacade::shouldReceive('find')->with('anc')->andReturn($anc);

    $outcome = 'unset';
    try {
        $outcome = (new OrphanedAncestorResolver)->resolve($entry)?->redirectUrl;
    } catch (Throwable $e) {
        $outcome = 'threw: '.$e->getMessage();
    }

    expect($outcome)->toBe('https://site.test');
});

it('falls back past a live ancestor whose url is an empty string', function () {
    $entry = orphanEntry(
        [
            ['status' => 'draft', 'url' => '/top', 'title' => 'Offline Top'],
            ['status' => 'published', 'url' => ''],
        ],
        mountUrl: '/mount-root',
    );

    expect((new OrphanedAncestorResolver)->resolve($entry)?->redirectUrl)->toBe('/mount-root');
});

it('falls back to a slash when the site root url is empty', function () {
    $entry = orphanEntry(
        [
            ['status' => 'draft', 'url' => '/top', 'title' => 'Offline Top'],
        ],
        siteUrl: '',
        mountUrl: null,
    );

    expect((new OrphanedAncestorResolver)->resolve($entry)?->redirectUrl)->toBe('/');
});

it('memoizes the resolution per request', function () {
    $site = Mockery::mock(Site::class);
    $site->shouldReceive('url')->andReturn('https://site.test');

    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('id')->andReturn('memo-descendant');
    $entry->shouldReceive('locale')->andReturn('default');
    $entry->shouldReceive('site')->andReturn($site);
    $entry->shouldReceive('collection')->andReturn(orphanCollection());

    $rootPage = Mockery::mock(Page::class);
    $rootPage->shouldReceive('isRoot')->andReturn(true);

    $ancPage = Mockery::mock(Page::class);
    $ancPage->shouldReceive('id')->andReturn('memo-anc');
    $ancPage->shouldReceive('isRoot')->andReturn(false);
    $ancPage->shouldReceive('parent')->andReturn($rootPage);

    $entryPage = Mockery::mock(Page::class);
    $entryPage->shouldReceive('isRoot')->andReturn(false);
    $entryPage->shouldReceive('parent')->andReturn($ancPage);
    $entry->shouldReceive('page')->andReturn($entryPage);

    $ancestor = Mockery::mock(Entry::class);
    $ancestor->shouldReceive('status')->andReturn('draft');
    $ancestor->shouldReceive('url')->andReturn('/anc');
    $ancestor->shouldReceive('get')->with('title')->andReturn('Draft Ancestor');
    // Resolved once despite two reads.
    EntryFacade::shouldReceive('find')->with('memo-anc')->once()->andReturn($ancestor);

    $resolver = new OrphanedAncestorResolver;
    expect($resolver->resolve($entry)?->redirectUrl)->toBe('https://site.test');
    expect($resolver->resolve($entry)?->redirectUrl)->toBe('https://site.test');
});

/*
|--------------------------------------------------------------------------
| Predicate — routable + hierarchical + opt-out
|--------------------------------------------------------------------------
*/

it('treats a structured, routable collection as handled', function () {
    $collection = Mockery::mock(Collection::class);
    $collection->shouldReceive('handle')->andReturn('clinical_expertise');
    $collection->shouldReceive('hasStructure')->andReturn(true);
    $collection->shouldReceive('route')->with('health')->andReturn('{mount}/{slug}');

    expect((new OrphanedAncestorResolver)->isRoutableAndHierarchical($collection, 'health'))->toBeTrue();
});

it('treats a structured collection with no route as not routable', function () {
    $collection = Mockery::mock(Collection::class);
    $collection->shouldReceive('hasStructure')->andReturn(true);
    $collection->shouldReceive('route')->with('health')->andReturnNull();

    expect((new OrphanedAncestorResolver)->isRoutableAndHierarchical($collection, 'health'))->toBeFalse();
});

it('treats a flat collection as not hierarchical', function () {
    $collection = Mockery::mock(Collection::class);
    $collection->shouldReceive('hasStructure')->andReturn(false);

    expect((new OrphanedAncestorResolver)->isRoutableAndHierarchical($collection, 'health'))->toBeFalse();
});

it('is routable when any site produces URLs and no site is given', function () {
    $collection = Mockery::mock(Collection::class);
    $collection->shouldReceive('hasStructure')->andReturn(true);
    $collection->shouldReceive('sites')->andReturn(collect(['default', 'health']));
    $collection->shouldReceive('route')->with('default')->andReturnNull();
    $collection->shouldReceive('route')->with('health')->andReturn('{slug}');

    expect((new OrphanedAncestorResolver)->isRoutableAndHierarchical($collection))->toBeTrue();
});

it('is not routable when no site produces URLs and no site is given', function () {
    $collection = Mockery::mock(Collection::class);
    $collection->shouldReceive('hasStructure')->andReturn(true);
    $collection->shouldReceive('sites')->andReturn(collect(['default']));
    $collection->shouldReceive('route')->with('default')->andReturn('  ');

    expect((new OrphanedAncestorResolver)->isRoutableAndHierarchical($collection))->toBeFalse();
});

it('respects the opt-out config for isEnabledFor', function () {
    config()->set('orphaned_ancestors.disabled_collections', ['knowledge_bases']);

    $enabled = Mockery::mock(Collection::class);
    $enabled->shouldReceive('handle')->andReturn('clinical_expertise');

    $disabled = Mockery::mock(Collection::class);
    $disabled->shouldReceive('handle')->andReturn('knowledge_bases');

    $resolver = new OrphanedAncestorResolver;
    expect($resolver->isEnabledFor($enabled))->toBeTrue();
    expect($resolver->isEnabledFor($disabled))->toBeFalse();
});
