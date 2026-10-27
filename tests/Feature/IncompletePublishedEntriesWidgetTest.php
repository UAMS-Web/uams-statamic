<?php

declare(strict_types=1);

use App\Widgets\IncompletePublishedEntries;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use PHPUnit\Framework\Assert;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\User;
use Statamic\Stache\Query\EntryQueryBuilder;
use Tests\Support\Doubles\IncompletePublishedEntriesWidgetTestDouble;
use Tests\Support\StacheIsolatedTestCase;

use function Pest\Laravel\actingAs;

// Isolated Stache (UAMS-Web/uams-statamic-archive#1678). This test persists real collections, blueprints, entries,
// and users. Under the plain TestCase those writes land in the SHARED `content/` and
// `resources/blueprints/` trees, and the create/delete cycle races concurrent workers'
// Stache traversals — `SplFileInfo::getMTime(): stat failed` on a file this test had
// just removed, failing unrelated tests. StacheIsolatedTestCase repoints every store
// and the blueprint directory at per-worker tokenized throwaway roots, so nothing here
// is visible to another worker.
uses(StacheIsolatedTestCase::class);

const INCOMPLETE_WIDGET_TEST_COLLECTION = 'incomplete_widget_xuser';
const INCOMPLETE_WIDGET_TEST_OTHER_COLLECTION = 'incomplete_widget_other';
const INCOMPLETE_WIDGET_TEST_SUPER_EMAIL = 'incomplete-widget-super@example.test';
const INCOMPLETE_WIDGET_TEST_RESTRICTED_EMAIL = 'incomplete-widget-restricted@example.test';

/**
 * Create a collection whose entry blueprint has a required `intro` field. The
 * collection resolves its blueprint via Blueprint::in('collections/<handle>'),
 * so a real blueprint is persisted there (the default fallback only requires
 * `title`, which would never fail validation). Skips the test when the content
 * path cannot persist the collection, mirroring the repo's content/ skips.
 */
function incompleteWidgetCreateCollection(string $handle): void
{
    $collection = CollectionFacade::make($handle);
    $collection->title($handle);
    $collection->save();

    $blueprint = Blueprint::makeFromFields([
        'intro' => [
            'type' => 'text',
            'display' => 'Intro',
            'validate' => ['required'],
        ],
    ]);
    $blueprint->setHandle($handle);
    $blueprint->setNamespace('collections.'.$handle);
    $blueprint->save();

    if (! CollectionFacade::find($handle)) {
        Assert::markTestSkipped('Requires a writable content path to create the test collection.');
    }
}

/**
 * Create and persist a published entry that fails validation (missing the
 * required `intro` field), returning its id.
 */
function incompleteWidgetCreateFailingEntry(string $collection, string $slug, string $title): string
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection($collection);
    $entry->slug($slug);
    $entry->published(true);
    $entry->data(['title' => $title]);
    $entry->save();

    $id = $entry->id();
    Assert::assertIsString($id);

    return $id;
}

/**
 * Create the fixtures for the cross-user cache-leak tests (UAMS-Web/uams-statamic-archive#860): an in-memory
 * collection blueprint with a required field, one published entry that FAILS
 * validation (missing the required field) and one that PASSES, plus a super user
 * and a permissionless user. Skips the test when the content path cannot persist
 * the collection (e.g. a read-only env), mirroring the repo's content/ skips.
 *
 * @return array{failingId: string, superEmail: string, restrictedEmail: string}
 */
function setUpIncompleteWidgetCrossUserFixtures(): array
{
    incompleteWidgetCreateCollection(INCOMPLETE_WIDGET_TEST_COLLECTION);

    // Failing: published but missing the required `intro` field.
    $failingId = incompleteWidgetCreateFailingEntry(
        INCOMPLETE_WIDGET_TEST_COLLECTION,
        'failing-xuser',
        'Failing X-User Entry'
    );

    // Passing: published with the required field present.
    /** @var Statamic\Entries\Entry $passing */
    $passing = Entry::make();
    $passing->collection(INCOMPLETE_WIDGET_TEST_COLLECTION);
    $passing->slug('passing-xuser');
    $passing->published(true);
    $passing->data(['title' => 'Passing X-User Entry', 'intro' => 'Has an intro']);
    $passing->save();

    /** @var Statamic\Auth\File\User $superUser */
    $superUser = User::make();
    $superUser->email(INCOMPLETE_WIDGET_TEST_SUPER_EMAIL);
    $superUser->makeSuper();
    $superUser->save();

    // No super, no roles -> cannot view any entry.
    /** @var Statamic\Auth\File\User $restrictedUser */
    $restrictedUser = User::make();
    $restrictedUser->email(INCOMPLETE_WIDGET_TEST_RESTRICTED_EMAIL);
    $restrictedUser->save();

    return [
        'failingId' => $failingId,
        'superEmail' => INCOMPLETE_WIDGET_TEST_SUPER_EMAIL,
        'restrictedEmail' => INCOMPLETE_WIDGET_TEST_RESTRICTED_EMAIL,
    ];
}

/**
 * Build the widget scoped to the test collection.
 */
function makeIncompleteWidgetForCrossUser(): IncompletePublishedEntries
{
    $widget = new IncompletePublishedEntries;
    $widget->setConfig([
        'collections' => [INCOMPLETE_WIDGET_TEST_COLLECTION],
        'limit' => 500,
    ]);

    return $widget;
}

/**
 * Pull the scan-coverage array out of a rendered widget view, typed (UAMS-Web/uams-statamic-archive#1905).
 *
 * Read defensively rather than cast straight off `getData()`, which is `mixed`:
 * a missing key must surface as an obviously-wrong -1 in the failure message, not
 * as a 0 that could be mistaken for a real "scanned nothing" result.
 *
 * @return array{scanned: int, total: int, truncated: bool}
 */
function incompleteWidgetScan(View $view): array
{
    $scan = $view->getData()['scan'] ?? null;
    Assert::assertIsArray($scan, 'the widget view exposes no `scan` coverage');

    $scanned = $scan['scanned'] ?? null;
    $total = $scan['total'] ?? null;
    $truncated = $scan['truncated'] ?? false;

    return [
        'scanned' => is_scalar($scanned) ? (int) $scanned : -1,
        'total' => is_scalar($total) ? (int) $total : -1,
        'truncated' => (bool) $truncated,
    ];
}

/**
 * Pluck the rendered display-row titles out of the widget view.
 *
 * @return Collection<int, string>
 */
function incompleteWidgetRenderedTitles(IncompletePublishedEntries $widget): Collection
{
    $result = $widget->html();
    Assert::assertInstanceOf(View::class, $result);

    /** @var View $result */
    $entries = $result->getData()['entries'];

    /** @var Collection<int, array{title?: string}> $entries */
    return collect($entries)
        ->map(static fn (array $row): string => (string) ($row['title'] ?? ''))
        ->values();
}

afterEach(function (): void {
    foreach ([INCOMPLETE_WIDGET_TEST_COLLECTION, INCOMPLETE_WIDGET_TEST_OTHER_COLLECTION] as $handle) {
        /** @var EntryQueryBuilder $entryQuery */
        $entryQuery = Entry::query();
        $entryQuery->where('collection', $handle);
        /** @var Collection<int, Statamic\Entries\Entry> $entries */
        $entries = $entryQuery->get();
        foreach ($entries as $entry) {
            $entry->delete();
        }

        $collection = CollectionFacade::find($handle);
        if ($collection !== null) {
            $collection->delete();
        }

        /** @var Statamic\Fields\Blueprint $blueprint */
        foreach (Blueprint::in('collections/'.$handle) as $blueprint) {
            $blueprint->delete();
        }

        // No resources/ directory sweep here: StacheIsolatedTestCase repoints the
        // blueprint directory at a per-worker throwaway root and wipes it in tearDown,
        // so nothing was ever written under resources/. Sweeping that path now would
        // reach into the REAL tree for a handle this test does not own.
    }

    foreach ([INCOMPLETE_WIDGET_TEST_SUPER_EMAIL, INCOMPLETE_WIDGET_TEST_RESTRICTED_EMAIL] as $email) {
        /** @var Statamic\Auth\File\User|null $user */
        $user = User::findByEmail($email);
        if ($user !== null) {
            $user->delete();
        }
    }

    Cache::flush();
});

/**
 * Unit tests for IncompletePublishedEntries widget.
 *
 * Windows:
 *   php artisan cache:clear && echo. > storage\logs\laravel.log && php artisan test tests/Feature/IncompletePublishedEntriesWidgetTest.php
 *   php artisan cache:clear && echo. > storage\logs\laravel.log && php artisan test tests/Feature/IncompletePublishedEntriesWidgetTest.php --stop-on-failure
 *
 * Mac:
 *   php artisan cache:clear && > storage/logs/laravel.log && php artisan test tests/Feature/IncompletePublishedEntriesWidgetTest.php
 *   php artisan cache:clear && > storage/logs/laravel.log && php artisan test tests/Feature/IncompletePublishedEntriesWidgetTest.php --stop-on-failure
 *
 * Mutation: 100% on App\Widgets\IncompletePublishedEntries (106 mutants, --covered-only).
 */
it('returns a view with the entries collection and title', function (): void {
    // Statamic's `whereStatus()` resolves a collection per matched handle and fatals with
    // `handle() on null` when the Stache holds NO collections at all (verified against an
    // empty isolated Stache). Production always has collections, so these two cases mean
    // "collections exist, nothing fails validation" — give them one so the scenario is the
    // real one rather than an empty-install edge case in vendor code.
    incompleteWidgetCreateCollection(INCOMPLETE_WIDGET_TEST_OTHER_COLLECTION);

    $widget = new IncompletePublishedEntries;
    $widget->setConfig(['limit' => 0]);
    $widget->refresh();

    $result = $widget->html();
    expect($result)->toBeInstanceOf(View::class);

    /** @var View $result */
    expect($result->name())->toBe('widgets.incomplete_published_entries');

    $data = $result->getData();
    expect($data)->toHaveKey('entries');
    expect($data)->toHaveKey('title');

    $entries = $data['entries'];
    expect($entries)->toBeInstanceOf(Collection::class);
    /** @var Collection<int|string, mixed> $entries */
    expect($entries->isEmpty())->toBeTrue();
});

it('returns no entries when the limit config is zero', function (): void {
    // Statamic's `whereStatus()` resolves a collection per matched handle and fatals with
    // `handle() on null` when the Stache holds NO collections at all (verified against an
    // empty isolated Stache). Production always has collections, so these two cases mean
    // "collections exist, nothing fails validation" — give them one so the scenario is the
    // real one rather than an empty-install edge case in vendor code.
    incompleteWidgetCreateCollection(INCOMPLETE_WIDGET_TEST_OTHER_COLLECTION);

    $widget = new IncompletePublishedEntries;
    $widget->setConfig(['limit' => 0, 'max_results' => 10]);
    $widget->refresh();

    $result = $widget->html();
    expect($result)->toBeInstanceOf(View::class);

    /** @var View $result */
    $entries = $result->getData()['entries'];
    expect($entries)->toHaveCount(0);
});

it('does not starve a high-privilege user when a low-privilege user warms the cache (#860)', function (): void {
    // Correctness side of the leak: whoever warms the shared cache must not fix
    // what others see. A permissionless user warms first (and sees nothing), then
    // a super user must still see the failing entry from the same shared cache.
    $fixtures = setUpIncompleteWidgetCrossUserFixtures();
    Cache::flush();

    $widget = makeIncompleteWidgetForCrossUser();
    $widget->refresh();

    /** @var Statamic\Contracts\Auth\User $restrictedActor */
    $restrictedActor = User::findByEmail($fixtures['restrictedEmail']);
    actingAs($restrictedActor);
    expect(incompleteWidgetRenderedTitles($widget))->toBeEmpty();

    /** @var Statamic\Contracts\Auth\User $superActor */
    $superActor = User::findByEmail($fixtures['superEmail']);
    actingAs($superActor);
    $superTitles = incompleteWidgetRenderedTitles($widget);

    expect($superTitles)->toContain('Failing X-User Entry');
    expect($superTitles)->not->toContain('Passing X-User Entry');
});

it('does not leak a high-privilege user\'s list to a low-privilege user via the cache (#860)', function (): void {
    // Information-leak side: a super user warms the shared cache (and sees the
    // failing entry); a permissionless user reading the same cache must still see
    // nothing — the per-user view filter runs on read, after the cache.
    $fixtures = setUpIncompleteWidgetCrossUserFixtures();
    Cache::flush();

    $widget = makeIncompleteWidgetForCrossUser();
    $widget->refresh();

    /** @var Statamic\Contracts\Auth\User $superActor */
    $superActor = User::findByEmail($fixtures['superEmail']);
    actingAs($superActor);
    expect(incompleteWidgetRenderedTitles($widget))->toContain('Failing X-User Entry');

    /** @var Statamic\Contracts\Auth\User $restrictedActor */
    $restrictedActor = User::findByEmail($fixtures['restrictedEmail']);
    actingAs($restrictedActor);
    expect(incompleteWidgetRenderedTitles($widget))->toBeEmpty();
});

it('shows nothing when there is no authenticated user (#860)', function (): void {
    // No actingAs() -> UserFacade::current() is null -> the read path views nothing.
    setUpIncompleteWidgetCrossUserFixtures();
    Cache::flush();

    $widget = makeIncompleteWidgetForCrossUser();
    $widget->refresh();

    expect(incompleteWidgetRenderedTitles($widget))->toBeEmpty();
});

it('skips a cached candidate id that no longer resolves to an entry (#860)', function (): void {
    // The read path loads candidates by id; a dangling id must be dropped, not crash.
    $fixtures = setUpIncompleteWidgetCrossUserFixtures();
    /** @var Statamic\Contracts\Auth\User $superActor */
    $superActor = User::findByEmail($fixtures['superEmail']);
    actingAs($superActor);

    $widget = new IncompletePublishedEntriesWidgetTestDouble;
    $widget->setConfig([
        'collections' => [INCOMPLETE_WIDGET_TEST_COLLECTION],
        'limit' => 500,
    ]);

    // Stored rows as the scheduled scan writes them (UAMS-Web/uams-statamic-archive#2506): the display fields are
    // captured at scan time, and the read path keeps only rows whose entry resolves.
    $storedRow = static fn (string $id, string $title): array => [
        'id' => $id,
        'edit_url' => '',
        'title' => $title,
        'collection_title' => '',
        'blueprint_title' => '',
    ];

    $rows = $widget->exposeVisibleFailingList([
        $storedRow($fixtures['failingId'], 'Failing X-User Entry'),
        $storedRow('nonexistent::missing-id', 'Missing Entry'),
    ]);

    expect($rows)->toHaveCount(1);
    expect(collect($rows)->map(static fn (array $row): string => $row['title']))
        ->toContain('Failing X-User Entry');
});

it('only surfaces failing entries from the configured collections (#860)', function (): void {
    // Two collections each hold a failing entry. A widget scoped to the OTHER
    // collection must surface only its entry — never the first collection's. If the
    // collection clause were dropped, the query would span every collection and the
    // first collection's entry would leak in.
    $fixtures = setUpIncompleteWidgetCrossUserFixtures();
    incompleteWidgetCreateCollection(INCOMPLETE_WIDGET_TEST_OTHER_COLLECTION);
    incompleteWidgetCreateFailingEntry(
        INCOMPLETE_WIDGET_TEST_OTHER_COLLECTION,
        'failing-other',
        'Other Failing Entry'
    );
    Cache::flush();
    /** @var Statamic\Contracts\Auth\User $superActor */
    $superActor = User::findByEmail($fixtures['superEmail']);
    actingAs($superActor);

    $widget = new IncompletePublishedEntries;
    $widget->setConfig([
        'collections' => [INCOMPLETE_WIDGET_TEST_OTHER_COLLECTION],
        'limit' => 500,
    ]);
    $widget->refresh();

    $titles = incompleteWidgetRenderedTitles($widget);
    expect($titles)->toContain('Other Failing Entry');
    expect($titles)->not->toContain('Failing X-User Entry');
});

/**
 * Coverage for the scan-coverage contract (UAMS-Web/uams-statamic-archive#1905).
 *
 * The widget capped its scan at 500 published entries by default against 732 in
 * the tree, so 232 were never audited and no part of the UI said so — "nothing
 * else is broken" and "the rest was never looked at" rendered identically. The cap
 * is now opt-in, and when it truncates the widget reports it.
 *
 * Measured on the real content before removing the default: 500 entries took
 * 31.09s and all 732 took 32.91s, so the cap bought ~6% of the scan while hiding
 * 32% of the content.
 */
it('resolves no cap at all when no limit is configured', function (): void {
    // Pins the DEFAULT directly, which no fixture-based assertion can do: any
    // fixture small enough to run fits under any plausible cap, so `scanned ===
    // total` holds whether the default is "no cap" or "500". Without this,
    // restoring `config('limit', 500)` leaves the entire suite green — a false
    // green on the single line UAMS-Web/uams-statamic-archive#1905 exists to change.
    $uncapped = new IncompletePublishedEntriesWidgetTestDouble;
    $uncapped->setConfig(['collections' => [INCOMPLETE_WIDGET_TEST_COLLECTION]]);

    expect($uncapped->exposeConfiguredScanLimit())->toBeNull();

    // ...and an explicit zero stays a real cap rather than collapsing into "absent".
    $capped = new IncompletePublishedEntriesWidgetTestDouble;
    $capped->setConfig(['collections' => [INCOMPLETE_WIDGET_TEST_COLLECTION], 'limit' => 0]);

    expect($capped->exposeConfiguredScanLimit())->toBe(0);
});

it('audits every published entry when no limit is configured', function (): void {
    incompleteWidgetCreateCollection(INCOMPLETE_WIDGET_TEST_COLLECTION);

    foreach (['alpha', 'bravo', 'charlie'] as $slug) {
        incompleteWidgetCreateFailingEntry(
            INCOMPLETE_WIDGET_TEST_COLLECTION,
            'failing-'.$slug,
            'Failing '.ucfirst($slug)
        );
    }

    Cache::flush();

    // No `limit` key at all — config/statamic/cp.php registers the widget with no
    // config, so this is the shipped configuration.
    $widget = new IncompletePublishedEntries;
    $widget->setConfig(['collections' => [INCOMPLETE_WIDGET_TEST_COLLECTION]]);
    $widget->refresh();

    $result = $widget->html();
    Assert::assertInstanceOf(View::class, $result);

    /** @var View $result */
    $scan = incompleteWidgetScan($result);

    expect($scan['truncated'])->toBeFalse()
        ->and($scan['scanned'])->toBe(3)
        ->and($scan['total'])->toBe(3);

    // Deliberately not asserting the rendered rows here. Those go through
    // visibleFailingList()'s per-user `can('view')` filter, which returns nothing
    // with no authenticated user — so a row count would measure UAMS-Web/uams-statamic-archive#860's filtering
    // rather than this ticket's scan coverage. The sibling cross-user tests own
    // that path, with real actors.
});

it('reports the truncation when a configured limit hides published entries', function (): void {
    incompleteWidgetCreateCollection(INCOMPLETE_WIDGET_TEST_COLLECTION);

    foreach (['alpha', 'bravo', 'charlie'] as $slug) {
        incompleteWidgetCreateFailingEntry(
            INCOMPLETE_WIDGET_TEST_COLLECTION,
            'failing-'.$slug,
            'Failing '.ucfirst($slug)
        );
    }

    Cache::flush();

    $widget = new IncompletePublishedEntries;
    $widget->setConfig([
        'collections' => [INCOMPLETE_WIDGET_TEST_COLLECTION],
        'limit' => 2,
    ]);
    $widget->refresh();

    $result = $widget->html();
    Assert::assertInstanceOf(View::class, $result);

    /** @var View $result */
    $scan = incompleteWidgetScan($result);

    // The point of the ticket: the shortfall is stated, with both numbers, rather
    // than the remaining entry silently never being looked at.
    expect($scan['truncated'])->toBeTrue()
        ->and($scan['scanned'])->toBe(2)
        ->and($scan['total'])->toBe(3);
});

it('renders the truncation notice, so the shortfall reaches a reader', function (): void {
    incompleteWidgetCreateCollection(INCOMPLETE_WIDGET_TEST_COLLECTION);

    foreach (['alpha', 'bravo'] as $slug) {
        incompleteWidgetCreateFailingEntry(
            INCOMPLETE_WIDGET_TEST_COLLECTION,
            'failing-'.$slug,
            'Failing '.ucfirst($slug)
        );
    }

    Cache::flush();

    $widget = new IncompletePublishedEntries;
    $widget->setConfig([
        'collections' => [INCOMPLETE_WIDGET_TEST_COLLECTION],
        'limit' => 1,
    ]);
    $widget->refresh();

    $result = $widget->html();
    Assert::assertInstanceOf(View::class, $result);

    /** @var View $result */
    // Asserted against the RENDERED html, not just the view data: the notice only
    // does its job if it reaches the page, and a template that never printed
    // `scan` would satisfy a data-only assertion.
    $html = $result->render();

    expect($html)
        ->toContain('Only 1 of 2 published entries were checked')
        ->toContain('this list is incomplete');
});

it('does not claim every entry passes when the scan was truncated', function (): void {
    // The empty-state sentence is itself a claim about entries that were never
    // looked at. With a limit that scans nothing, the old wording asserted the
    // whole tree was clean on the strength of zero entries examined.
    incompleteWidgetCreateCollection(INCOMPLETE_WIDGET_TEST_COLLECTION);
    incompleteWidgetCreateFailingEntry(
        INCOMPLETE_WIDGET_TEST_COLLECTION,
        'failing-alpha',
        'Failing Alpha'
    );

    Cache::flush();

    $widget = new IncompletePublishedEntries;
    $widget->setConfig([
        'collections' => [INCOMPLETE_WIDGET_TEST_COLLECTION],
        'limit' => 0,
    ]);
    $widget->refresh();

    $result = $widget->html();
    Assert::assertInstanceOf(View::class, $result);

    /** @var View $result */
    $html = $result->render();

    expect($html)
        ->not->toContain('All published entries pass current validation.')
        ->toContain('None of the published entries that were checked failed validation.');
});

it('keeps an explicit limit of zero meaning "scan nothing"', function (): void {
    // Absent and zero are deliberately NOT folded together. `limit: 0` has always
    // returned no rows and two sibling tests rely on it; only an ABSENT limit
    // means "audit everything". Folding them would silently turn a configured
    // no-op scan into a full one.
    incompleteWidgetCreateCollection(INCOMPLETE_WIDGET_TEST_COLLECTION);
    incompleteWidgetCreateFailingEntry(
        INCOMPLETE_WIDGET_TEST_COLLECTION,
        'failing-alpha',
        'Failing Alpha'
    );

    Cache::flush();

    $widget = new IncompletePublishedEntries;
    $widget->setConfig([
        'collections' => [INCOMPLETE_WIDGET_TEST_COLLECTION],
        'limit' => 0,
    ]);
    $widget->refresh();

    $result = $widget->html();
    Assert::assertInstanceOf(View::class, $result);

    /** @var View $result */
    $scan = incompleteWidgetScan($result);

    expect($scan['scanned'])->toBe(0)
        ->and($scan['total'])->toBe(1)
        ->and($scan['truncated'])->toBeTrue();
});

it('does not serve an uncapped audit out of a capped scan\'s cache entry', function (): void {
    // The cache key hashes the limit, so the key and the scan must resolve it the
    // same way. If the key normalized an absent limit to 0 while the scan treated
    // it as uncapped, a `limit: 0` scan of nothing would be served back as a
    // complete audit — the exact failure this ticket exists to remove.
    incompleteWidgetCreateCollection(INCOMPLETE_WIDGET_TEST_COLLECTION);
    incompleteWidgetCreateFailingEntry(
        INCOMPLETE_WIDGET_TEST_COLLECTION,
        'failing-alpha',
        'Failing Alpha'
    );

    Cache::flush();

    $capped = new IncompletePublishedEntries;
    $capped->setConfig(['collections' => [INCOMPLETE_WIDGET_TEST_COLLECTION], 'limit' => 0]);
    $capped->refresh();

    $uncapped = new IncompletePublishedEntries;
    $uncapped->setConfig(['collections' => [INCOMPLETE_WIDGET_TEST_COLLECTION]]);

    // Before its own scan, the uncapped widget must read NOTHING — not the capped
    // scan's payload, which would present a scan of zero entries as its result.
    $beforeOwnScan = $uncapped->html();
    Assert::assertInstanceOf(View::class, $beforeOwnScan);
    /** @var View $beforeOwnScan */
    expect($beforeOwnScan->getData()['computed'])->toBeFalse();

    $uncapped->refresh();
    $result = $uncapped->html();
    Assert::assertInstanceOf(View::class, $result);

    /** @var View $result */
    $scan = incompleteWidgetScan($result);

    expect($scan['truncated'])->toBeFalse()
        ->and($scan['scanned'])->toBe(1);
});
