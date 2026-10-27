<?php

declare(strict_types=1);

use App\Widgets\IncompletePublishedEntries;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use Illuminate\View\View;
use PHPUnit\Framework\Assert;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\User;
use Statamic\Sites\Site;
use Statamic\Stache\Query\EntryQueryBuilder;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\Doubles\IncompletePublishedEntriesWidgetTestDouble;
use Tests\Support\Doubles\ScanCountingIncompletePublishedEntries;
use Tests\Support\StacheIsolatedTestCase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(StacheIsolatedTestCase::class);

/**
 * UAMS-Web/uams-statamic-archive#2506 — the Incomplete Published Entries widget never scans inside a request.
 *
 * The scan costs about 90s in a request-shaped run against a 60s request limit, so a
 * dashboard load that scanned was a 504, and one that died before writing the cache
 * could never warm it. The scan now runs only from the scheduled
 * `cp:scan-incomplete-published-entries` command; the widget reads its result, and on
 * a miss renders a state that cannot be mistaken for a clean audit.
 *
 * "No scan" is asserted by COUNTING scans through a double, never inferred from how
 * long a request took: a test fixture scans in milliseconds, so a timing assertion
 * would pass with the inline scan still in place.
 *
 * @command php artisan test --compact tests/Feature/IncompletePublishedEntriesOffRequestTest.php
 */
const OFF_REQUEST_COLLECTION = 'incomplete_widget_off_request';
const OFF_REQUEST_SUPER_EMAIL = 'incomplete-off-request-super@example.test';
const NOT_COMPUTED_MARKER = 'data-incomplete-entries-state="not-computed"';
const CONTENT_CHANGED_MARKER = 'data-incomplete-entries-state="content-changed"';

/**
 * A collection whose blueprint requires `intro`, with one published entry missing it.
 */
function offRequestSeedFailingEntry(): string
{
    $collection = CollectionFacade::make(OFF_REQUEST_COLLECTION);
    $collection->title(OFF_REQUEST_COLLECTION);
    $collection->save();

    $blueprint = Blueprint::makeFromFields([
        'intro' => ['type' => 'text', 'display' => 'Intro', 'validate' => ['required']],
    ]);
    $blueprint->setHandle(OFF_REQUEST_COLLECTION);
    $blueprint->setNamespace('collections.'.OFF_REQUEST_COLLECTION);
    $blueprint->save();

    if (! CollectionFacade::find(OFF_REQUEST_COLLECTION)) {
        Assert::markTestSkipped('Requires a writable content path to create the test collection.');
    }

    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection(OFF_REQUEST_COLLECTION);
    $entry->slug('failing-off-request');
    $entry->published(true);
    $entry->data(['title' => 'Failing Off-Request Entry']);
    $entry->save();

    $id = $entry->id();
    Assert::assertIsString($id);

    return $id;
}

function offRequestWidget(): ScanCountingIncompletePublishedEntries
{
    $widget = new ScanCountingIncompletePublishedEntries;
    $widget->setConfig(['collections' => [OFF_REQUEST_COLLECTION]]);

    return $widget;
}

/**
 * @return array<mixed>
 */
function offRequestViewData(IncompletePublishedEntries $widget): array
{
    $view = $widget->html();
    Assert::assertInstanceOf(View::class, $view);

    /** @var View $view */
    return $view->getData();
}

/**
 * @param  array<mixed>  $data
 * @return array<mixed>
 */
function offRequestPagination(array $data): array
{
    $pagination = $data['pagination'];
    Assert::assertIsArray($pagination);

    return $pagination;
}

function offRequestRendered(IncompletePublishedEntries $widget): string
{
    $view = $widget->html();
    Assert::assertInstanceOf(View::class, $view);

    /** @var View $view */
    return $view->render();
}

function offRequestSuperUser(): Statamic\Auth\File\User
{
    /** @var Statamic\Auth\File\User $user */
    $user = User::make();
    $user->email(OFF_REQUEST_SUPER_EMAIL);
    $user->makeSuper();
    $user->save();

    return $user;
}

/**
 * The Control Panel dashboard is an Inertia page: each widget's HTML arrives
 * JSON-encoded inside the `data-page` attribute, so a raw search of the response
 * bytes finds only its escaped form. Decode the page and return it as delivered.
 */
/**
 * @param  TestResponse<Response>  $response
 */
function offRequestDashboardPage(TestResponse $response): string
{
    $matched = preg_match('/data-page="([^"]*)"/', (string) $response->getContent(), $m);
    Assert::assertSame(1, $matched, 'the dashboard response carries no Inertia data-page');

    $decoded = json_decode(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5), true);
    Assert::assertIsArray($decoded, 'the dashboard data-page is not valid JSON');

    return (string) json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

beforeEach(function (): void {
    Cache::flush();
    ScanCountingIncompletePublishedEntries::$scans = 0;
});

afterEach(function (): void {
    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->where('collection', OFF_REQUEST_COLLECTION);
    /** @var Collection<int, Statamic\Entries\Entry> $entries */
    $entries = $query->get();
    foreach ($entries as $entry) {
        $entry->delete();
    }

    CollectionFacade::find(OFF_REQUEST_COLLECTION)?->delete();

    /** @var Statamic\Fields\Blueprint $blueprint */
    foreach (Blueprint::in('collections/'.OFF_REQUEST_COLLECTION) as $blueprint) {
        $blueprint->delete();
    }

    /** @var Statamic\Auth\File\User|null $user */
    $user = User::findByEmail(OFF_REQUEST_SUPER_EMAIL);
    $user?->delete();

    Cache::flush();
});

it('does not scan when rendered with no stored result', function (): void {
    offRequestSeedFailingEntry();

    $data = offRequestViewData(offRequestWidget());

    expect(ScanCountingIncompletePublishedEntries::$scans)->toBe(0)
        ->and($data['computed'])->toBeFalse();
});

it('renders a cache miss as its own state, never as a clean audit', function (): void {
    offRequestSeedFailingEntry();

    $html = offRequestRendered(offRequestWidget());

    expect($html)
        ->toContain(NOT_COMPUTED_MARKER)
        ->toContain('has not completed yet')
        ->not->toContain('All published entries pass current validation.')
        ->not->toContain('None of the published entries that were checked failed validation.');
});

it('scans once on refresh and then renders from the stored result without scanning', function (): void {
    offRequestSeedFailingEntry();
    actingAs(offRequestSuperUser());

    $widget = offRequestWidget();
    $widget->refresh();
    expect(ScanCountingIncompletePublishedEntries::$scans)->toBe(1);

    $html = offRequestRendered($widget);

    expect(ScanCountingIncompletePublishedEntries::$scans)->toBe(1)
        ->and($html)->toContain('Failing Off-Request Entry')
        ->not->toContain(NOT_COMPUTED_MARKER);
});

it('keeps the stored result through an entry save, and says content changed since', function (): void {
    offRequestSeedFailingEntry();
    actingAs(offRequestSuperUser());

    $widget = offRequestWidget();
    $widget->refresh();

    // Pin the scan to the past so the save below lands strictly after it.
    $key = $widget->cacheKey();
    /** @var array<string, mixed> $payload */
    $payload = Cache::get($key);
    $payload['computed_at'] = time() - 3600;
    Cache::forever($key, $payload);

    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    /** @var EntryQueryBuilder $query */
    $query = $query->where('collection', OFF_REQUEST_COLLECTION);
    /** @var Statamic\Entries\Entry|null $entry */
    $entry = $query->first();
    Assert::assertNotNull($entry);
    $entry->set('title', 'Failing Off-Request Entry');
    $entry->save();

    $data = offRequestViewData($widget);
    $html = offRequestRendered($widget);

    expect(ScanCountingIncompletePublishedEntries::$scans)->toBe(1)
        ->and($data['computed'])->toBeTrue()
        ->and($data['content_changed_since'])->toBeTrue()
        ->and($html)->toContain(CONTENT_CHANGED_MARKER)
        ->and($html)->toContain('Failing Off-Request Entry');
});

it('does not scan during a dashboard request with the widget enabled in config', function (): void {
    offRequestSeedFailingEntry();
    app()->bind(IncompletePublishedEntries::class, ScanCountingIncompletePublishedEntries::class);
    config(['statamic.cp.widgets' => ['incomplete_published_entries']]);
    actingAs(offRequestSuperUser());

    $url = cp_route('dashboard');
    Assert::assertIsString($url);
    $response = get($url);

    $response->assertOk();
    expect(ScanCountingIncompletePublishedEntries::$scans)->toBe(0);
    // The marker proves the widget actually rendered in this request; without it a
    // zero scan count would also result from the widget never being reached.
    expect(offRequestDashboardPage($response))->toContain(addslashes(NOT_COMPUTED_MARKER));
});

it('does not scan during a dashboard request with the widget enabled only by a saved preference', function (): void {
    offRequestSeedFailingEntry();
    app()->bind(IncompletePublishedEntries::class, ScanCountingIncompletePublishedEntries::class);

    // The config list does NOT name the widget, so it can only reach the page through
    // the preference, which Statamic reads first and which overrides the config.
    config(['statamic.cp.widgets' => ['duplicate_people']]);
    $user = offRequestSuperUser();
    $user->setPreference('widgets', ['incomplete_published_entries'])->save();
    actingAs($user);

    $url = cp_route('dashboard');
    Assert::assertIsString($url);
    $response = get($url);

    $response->assertOk();
    expect(ScanCountingIncompletePublishedEntries::$scans)->toBe(0);
    expect(offRequestDashboardPage($response))->toContain(addslashes(NOT_COMPUTED_MARKER));
});

it('stores a result the default dashboard widget reads, from the console command', function (): void {
    offRequestSeedFailingEntry();

    expect(Artisan::call('cp:scan-incomplete-published-entries'))->toBe(0);

    // The widget as the dashboard builds it from a bare string entry: no options.
    $data = offRequestViewData(new IncompletePublishedEntries);

    expect($data['computed'])->toBeTrue();
});

it('also stores a result for a widget registered with options of its own', function (): void {
    offRequestSeedFailingEntry();
    config(['statamic.cp.widgets' => [
        ['type' => 'incomplete_published_entries', 'collections' => [OFF_REQUEST_COLLECTION]],
    ]]);

    expect(Artisan::call('cp:scan-incomplete-published-entries'))->toBe(0);

    expect(offRequestViewData(offRequestWidget())['computed'])->toBeTrue();
});

it('schedules the scan hourly, without overlapping runs', function (): void {
    /** @var Collection<int, Event> $events */
    $events = collect(app(Schedule::class)->events())
        ->filter(static fn (Event $event): bool => str_contains((string) $event->command, 'cp:scan-incomplete-published-entries'));

    expect($events)->toHaveCount(1);

    /** @var Event $event */
    $event = $events->first();

    expect($event->expression)->toBe('0 * * * *')
        ->and($event->withoutOverlapping)->toBeTrue();
});

it('reads one stored result whichever site is current', function (): void {
    // The scan applies no site filter, so one scheduled run serves the whole install.
    // A per-site key would leave every site but the one scanned reading a miss.
    /** @var Collection<int, Site> $sites */
    $sites = Statamic\Facades\Site::all();
    $sites = $sites->values();
    if ($sites->count() < 2) {
        Assert::markTestSkipped('Needs at least two configured sites to switch between.');
    }

    $keys = $sites->take(2)->map(static function (Site $site): string {
        Statamic\Facades\Site::setCurrent($site->handle());

        // Confirm the switch took effect, so equal keys are not two reads of one site.
        $current = Statamic\Facades\Site::current();
        Assert::assertInstanceOf(Site::class, $current);
        Assert::assertSame($site->handle(), $current->handle());

        return offRequestWidget()->cacheKey();
    });

    expect($keys->unique())->toHaveCount(1);
});

/**
 * A stored payload written directly, so the read path can be driven with the exact
 * shapes the cache might hand back.
 *
 * @param  array<mixed>  $payload
 */
function offRequestStore(IncompletePublishedEntries $widget, array $payload): void
{
    Cache::forever($widget->cacheKey(), $payload);
}

/**
 * @return array{id: string, edit_url: string, title: string, collection_title: string, blueprint_title: string}
 */
function offRequestRow(string $id, string $title): array
{
    return ['id' => $id, 'edit_url' => '/cp/'.$id, 'title' => $title, 'collection_title' => 'C', 'blueprint_title' => 'B'];
}

it('treats a payload missing any required key as not computed', function (string $missing): void {
    $widget = offRequestWidget();
    $payload = [
        'rows' => [],
        'scan' => ['scanned' => 3, 'total' => 3, 'truncated' => false],
        'computed_at' => time(),
    ];
    unset($payload[$missing]);
    offRequestStore($widget, $payload);

    expect(offRequestViewData($widget)['computed'])->toBeFalse();
})->with(['rows', 'scan', 'computed_at']);

it('treats a payload whose parts are the wrong type as not computed', function (array $payload): void {
    $widget = offRequestWidget();
    offRequestStore($widget, $payload);

    expect(offRequestViewData($widget)['computed'])->toBeFalse();
})->with([
    'rows not a list' => [['rows' => 'x', 'scan' => [], 'computed_at' => 1]],
    'scan not an array' => [['rows' => [], 'scan' => 'x', 'computed_at' => 1]],
    'computed_at not scalar' => [['rows' => [], 'scan' => [], 'computed_at' => []]],
]);

it('reads a stored scan coverage exactly, and degrades a malformed one to zeroes', function (): void {
    $widget = offRequestWidget();

    offRequestStore($widget, ['rows' => [], 'scan' => ['scanned' => 7, 'total' => 9, 'truncated' => true], 'computed_at' => 100]);
    $data = offRequestViewData($widget);
    expect($data['scan'])->toBe(['scanned' => 7, 'total' => 9, 'truncated' => true])
        ->and($data['computed_at'])->toBe(100);

    offRequestStore($widget, ['rows' => [], 'scan' => ['scanned' => '7', 'total' => '9', 'truncated' => 1], 'computed_at' => '100']);
    $coerced = offRequestViewData($widget);
    expect($coerced['scan'])->toBe(['scanned' => 7, 'total' => 9, 'truncated' => true])
        ->and($coerced['computed_at'])->toBe(100);

    offRequestStore($widget, ['rows' => [], 'scan' => ['scanned' => [], 'total' => null], 'computed_at' => 100]);
    expect(offRequestViewData($widget)['scan'])->toBe(['scanned' => 0, 'total' => 0, 'truncated' => false]);

    offRequestStore($widget, ['rows' => [], 'scan' => ['scanned' => 3, 'total' => []], 'computed_at' => 100]);
    expect(offRequestViewData($widget)['scan'])->toBe(['scanned' => 3, 'total' => 0, 'truncated' => false]);

    offRequestStore($widget, ['rows' => [], 'scan' => ['total' => 4], 'computed_at' => 100]);
    expect(offRequestViewData($widget)['scan'])->toBe(['scanned' => 0, 'total' => 4, 'truncated' => false]);
});

it('drops stored rows without a string id, and blanks non-string display fields', function (): void {
    offRequestSeedFailingEntry();
    actingAs(offRequestSuperUser());
    $widget = offRequestWidget();
    $widget->refresh();

    /** @var array{rows: list<array<string, mixed>>} $payload */
    $payload = Cache::get($widget->cacheKey());
    $real = $payload['rows'][0];
    $payload['rows'] = [
        ['title' => 'No Id'],
        'not a row',
        ['id' => $real['id'], 'edit_url' => 5, 'title' => ['x'], 'collection_title' => null, 'blueprint_title' => 1.5],
        ['id' => $real['id']],
    ];
    offRequestStore($widget, $payload);

    /** @var Collection<int, array<string, string>> $entries */
    $entries = offRequestViewData($widget)['entries'];

    expect($entries->all())->toBe([
        ['edit_url' => '', 'title' => '', 'collection_title' => '', 'blueprint_title' => ''],
        ['edit_url' => '', 'title' => '', 'collection_title' => '', 'blueprint_title' => ''],
    ]);
});

it('stores the display row the widget shows, built at scan time', function (): void {
    offRequestSeedFailingEntry();
    actingAs(offRequestSuperUser());
    $widget = offRequestWidget();
    $widget->refresh();

    /** @var array{rows: list<array<string, string>>} $payload */
    $payload = Cache::get($widget->cacheKey());

    expect($payload['rows'])->toHaveCount(1)
        ->and($payload['rows'][0]['title'])->toBe('Failing Off-Request Entry')
        ->and($payload['rows'][0]['collection_title'])->toBe(OFF_REQUEST_COLLECTION)
        ->and($payload['rows'][0]['blueprint_title'])->not->toBe('')
        ->and($payload['rows'][0]['edit_url'])->toContain('/cp/collections/'.OFF_REQUEST_COLLECTION.'/entries/');
});

it('says content changed only when a save came strictly after the scan', function (int $offset, bool $expected): void {
    $widget = offRequestWidget();
    offRequestStore($widget, ['rows' => [], 'scan' => ['scanned' => 0, 'total' => 0, 'truncated' => false], 'computed_at' => 1000]);
    Cache::forever(IncompletePublishedEntries::CACHE_CONTENT_CHANGED_KEY, 1000 + $offset);

    expect(offRequestViewData($widget)['content_changed_since'])->toBe($expected);
})->with([
    'saved before the scan' => [-1, false],
    'saved in the same second' => [0, false],
    'saved after the scan' => [1, true],
]);

it('renders a scalar display value as its string and anything else as empty', function (mixed $value, string $expected): void {
    expect((new IncompletePublishedEntriesWidgetTestDouble)->exposeDisplayString($value))->toBe($expected);
})->with([
    'string' => ['Title', 'Title'],
    'integer' => [42, '42'],
    'array' => [['x'], ''],
    'null' => [null, ''],
]);

it('keys the stored result by the collections filter as well as the limit', function (): void {
    $a = new IncompletePublishedEntries;
    $a->setConfig(['collections' => ['one']]);
    $b = new IncompletePublishedEntries;
    $b->setConfig(['collections' => ['two']]);
    $c = new IncompletePublishedEntries;
    $c->setConfig(['collections' => ['one']]);

    expect($a->cacheKey())->not->toBe($b->cacheKey())
        ->and($a->cacheKey())->toBe($c->cacheKey());
});

it('paginates the visible rows by the configured page size', function (): void {
    $user = offRequestSuperUser();
    actingAs($user);

    // Three real entries so the per-user policy check has something to pass.
    offRequestSeedFailingEntry();
    $ids = [];
    foreach (['two', 'three'] as $slug) {
        /** @var Statamic\Entries\Entry $entry */
        $entry = Entry::make();
        $entry->collection(OFF_REQUEST_COLLECTION);
        $entry->slug('failing-'.$slug);
        $entry->published(true);
        $entry->data(['title' => 'Failing '.$slug]);
        $entry->save();
    }

    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    /** @var EntryQueryBuilder $query */
    $query = $query->where('collection', OFF_REQUEST_COLLECTION);
    $found = $query->get();
    Assert::assertInstanceOf(Collection::class, $found);
    foreach ($found as $entry) {
        /** @var Statamic\Entries\Entry $entry */
        $id = $entry->id();
        Assert::assertIsString($id);
        $ids[] = $id;
    }
    sort($ids);

    $widget = new IncompletePublishedEntries;
    $widget->setConfig(['collections' => [OFF_REQUEST_COLLECTION], 'per_page' => 2]);
    offRequestStore($widget, [
        'rows' => [offRequestRow($ids[0], 'Row A'), offRequestRow($ids[1], 'Row B'), offRequestRow($ids[2], 'Row C')],
        'scan' => ['scanned' => 3, 'total' => 3, 'truncated' => false],
        'computed_at' => time(),
    ]);

    request()->query->set('incomplete_entries_page', '2');
    $second = offRequestViewData($widget);
    request()->query->set('incomplete_entries_page', '99');
    $clamped = offRequestViewData($widget);
    request()->query->set('incomplete_entries_page', '0');
    $first = offRequestViewData($widget);
    request()->query->remove('incomplete_entries_page');
    $absent = offRequestViewData($widget);

    /** @var Collection<int, array{title: string}> $secondRows */
    $secondRows = $second['entries'];
    /** @var Collection<int, array{title: string}> $firstRows */
    $firstRows = $first['entries'];
    /** @var Collection<int, array{title: string}> $absentRows */
    $absentRows = $absent['entries'];

    expect($secondRows->pluck('title')->all())->toBe(['Row C'])
        ->and($second['pagination'])->toMatchArray([
            'total' => 3,
            'per_page' => 2,
            'current_page' => 2,
            'last_page' => 2,
            'page_query_key' => 'incomplete_entries_page',
        ])
        ->and(offRequestPagination($second)['current_page'])->toBe(2)
        ->and(offRequestPagination($second)['base_url'])->toBe(request()->url())
        ->and(offRequestPagination($second)['query'])->toBe(['incomplete_entries_page' => '2'])
        ->and(offRequestPagination($clamped)['current_page'])->toBe(2)
        ->and($firstRows->pluck('title')->all())->toBe(['Row A', 'Row B'])
        ->and($firstRows->first())->toBe(['edit_url' => '/cp/'.$ids[0], 'title' => 'Row A', 'collection_title' => 'C', 'blueprint_title' => 'B'])
        ->and(offRequestPagination($first)['current_page'])->toBe(1)
        ->and(offRequestPagination($absent)['current_page'])->toBe(1)
        ->and($absentRows->pluck('title')->all())->toBe(['Row A', 'Row B']);
});

it('falls back to 25 per page when the configured size is not positive', function (mixed $perPage): void {
    $widget = new IncompletePublishedEntries;
    $widget->setConfig(['per_page' => $perPage]);
    offRequestStore($widget, ['rows' => [], 'scan' => ['scanned' => 0, 'total' => 0, 'truncated' => false], 'computed_at' => time()]);

    expect(offRequestPagination(offRequestViewData($widget))['per_page'])->toBe(25);
})->with([0, -3, 'x', [[]]]);

it('uses 25 per page when no size is configured, and honors a size of 1', function (): void {
    $widget = new IncompletePublishedEntries;
    $widget->setConfig([]);
    offRequestStore($widget, ['rows' => [], 'scan' => ['scanned' => 0, 'total' => 0, 'truncated' => false], 'computed_at' => time()]);
    $default = offRequestPagination(offRequestViewData($widget))['per_page'];

    $widget->setConfig(['per_page' => 1]);
    offRequestStore($widget, ['rows' => [], 'scan' => ['scanned' => 0, 'total' => 0, 'truncated' => false], 'computed_at' => time()]);

    expect($default)->toBe(25)
        ->and(offRequestPagination(offRequestViewData($widget))['per_page'])->toBe(1);
});

it('rounds the page count up and returns a middle page as an integer', function (): void {
    $id = offRequestSeedFailingEntry();
    actingAs(offRequestSuperUser());

    // One real entry repeated: the per-user filter keeps every row whose id it may view.
    $rows = array_map(static fn (string $title): array => offRequestRow($id, $title), ['A', 'B', 'C', 'D']);
    $widget = offRequestWidget();
    $widget->setConfig(['collections' => [OFF_REQUEST_COLLECTION], 'per_page' => 3]);
    offRequestStore($widget, ['rows' => $rows, 'scan' => ['scanned' => 4, 'total' => 4, 'truncated' => false], 'computed_at' => time()]);
    $threePerPage = offRequestPagination(offRequestViewData($widget));

    $widget->setConfig(['collections' => [OFF_REQUEST_COLLECTION], 'per_page' => 1]);
    offRequestStore($widget, ['rows' => $rows, 'scan' => ['scanned' => 4, 'total' => 4, 'truncated' => false], 'computed_at' => time()]);
    request()->query->set('incomplete_entries_page', '2');
    $middle = offRequestViewData($widget);
    request()->query->remove('incomplete_entries_page');

    /** @var Collection<int, array{title: string}> $middleRows */
    $middleRows = $middle['entries'];

    expect($threePerPage['last_page'])->toBe(2)
        ->and(offRequestPagination($middle)['current_page'])->toBe(2)
        ->and($middleRows->pluck('title')->all())->toBe(['B']);
});

it('reads a numeric string scan limit as an integer', function (): void {
    $widget = new IncompletePublishedEntriesWidgetTestDouble;
    $widget->setConfig(['limit' => '5']);

    expect($widget->exposeConfiguredScanLimit())->toBe(5);
});

it('reports a single empty page when nothing is visible', function (): void {
    $widget = offRequestWidget();
    offRequestStore($widget, ['rows' => [], 'scan' => ['scanned' => 0, 'total' => 0, 'truncated' => false], 'computed_at' => time()]);

    expect(offRequestViewData($widget)['pagination'])->toMatchArray(['total' => 0, 'last_page' => 1, 'current_page' => 1]);
});
