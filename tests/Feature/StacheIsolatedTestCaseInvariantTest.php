<?php

declare(strict_types=1);

use Statamic\Contracts\Entries\Collection;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Stache\Query\EntryQueryBuilder;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Regression guard for UAMS-Web/uams-statamic-archive#1851. `StacheIsolatedTestCase` re-roots every Stache store
 * at a throwaway directory, but that only changes what the stores READ FROM DISK
 * — it cannot reach what Statamic already memoized into Blink during the
 * application boot. `Collection::handles()` is `Blink::once('collection-handles')`,
 * so it went on returning the 19 handles of the real `content/` submodule while
 * `findByHandle()` returned null for every one of them.
 *
 * The invariant asserted here is the one that actually matters: inside the
 * isolated base, the collection **index** and the per-handle **lookup** must agree.
 * Without it a regression surfaces only as `Call to a member function handle() on
 * null` from deep inside a vendor query builder — which is how this was mis-
 * diagnosed as a cross-worker race for two tickets running (UAMS-Web/uams-statamic-archive#1596, then UAMS-Web/uams-statamic-archive#1851).
 *
 * `content/` is the trigger: with the submodule uninitialized there is nothing to
 * memoize and the split cannot occur, so these assertions are vacuously true on a
 * bare checkout and load-bearing on a developer one. That asymmetry is why CI
 * never caught it.
 *
 * @command  php artisan test --compact tests/Feature/StacheIsolatedTestCaseInvariantTest.php
 */
uses(StacheIsolatedTestCase::class);

it('never reports a collection handle it cannot resolve', function (): void {
    $unresolvable = CollectionFacade::handles()
        ->reject(fn (string $handle): bool => CollectionFacade::findByHandle($handle) instanceof Collection)
        ->values()
        ->all();

    expect($unresolvable)->toBe([]);
});

it('starts from an empty Stache rather than the real content submodule', function (): void {
    // The base promises a throwaway directory. Real `content/` carries 19
    // collections, so anything non-empty here means the isolation leaked — and
    // a *stale memo* is exactly what would make this non-empty while the store
    // itself is empty.
    expect(CollectionFacade::handles()->all())->toBe([]);
});

it('runs a status-filtered entry query without fataling', function (): void {
    // The downstream symptom, pinned directly: `QueriesEntryStatus` resolves
    // every handle the index reports, so a split index makes this throw instead
    // of returning an empty result — which is what took all 22 tests of
    // `MarketingRedirectControllerTest` down at once.
    //
    // Deliberately creates NO fixture collection. `Collection::save()` calls
    // `Blink::forget('collection-handles')`, so a test that builds one silently
    // repairs the stale memo before querying and can no longer detect the bug —
    // verified by mutation, and the reason so many isolated suites were unaffected
    // while this one file failed 22/22.
    //
    // Constraining by `handles()` is also load-bearing: an unconstrained
    // `whereStatus()` never enters the per-collection resolve loop, so it cannot
    // reach the fatal at all.
    // `Entry::query()` is typed to the *contract*, which has no `whereIn()` — that
    // lives on the concrete Stache builder. Naming the concrete type is the
    // repo's existing pattern for this seam (see `app/Scopes/RecognitionListMembership.php`
    // and `app/Console/Commands/BackfillLocationRegions.php`). Unlike the
    // `assert()` narrowings UAMS-Web/uams-statamic-archive#1733 removed, this is a type hint for a genuinely
    // known runtime type, not a runtime check that vanishes under
    // `zend.assertions=-1`.
    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->whereIn('collection', CollectionFacade::handles()->all());
    $query->whereStatus('published');

    // `expect()` takes mixed, so the untyped `get()` result goes in as an argument
    // rather than having a method called on it — no second annotation needed.
    expect($query->get())->toHaveCount(0);
});
