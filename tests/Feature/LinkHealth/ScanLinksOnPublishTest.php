<?php

declare(strict_types=1);

use App\Jobs\ScanEntryLinks;
use App\Jobs\ScanLinkHealth;
use App\Listeners\ScanLinksOnPublish;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Statamic\Events\EntrySaved;
use Tests\TestCase;

uses(TestCase::class);

/*
 * UAMS-Web/uams-statamic-archive#1772 — scanning links on publish.
 *
 * These drive the listener directly with a fabricated EntrySaved rather than saving a real
 * entry, so what is under test is the listener's decision to dispatch and not Statamic's
 * save pipeline.
 */

beforeEach(function (): void {
    Cache::flush();
    config(['link_health.on_publish.enabled' => true]);
    config(['link_health.on_publish.debounce_seconds' => 300]);
});

/** A stand-in entry with only what the listener reads. */
function fakeEntry(string $id, bool $published = true, string $site = 'default', string $collection = 'pages'): object
{
    return new class($id, $published, $site, $collection)
    {
        public function __construct(
            private string $id,
            private bool $published,
            private string $site,
            private string $collection,
        ) {}

        public function id(): string
        {
            return $this->id;
        }

        /**
         * `EntrySaved`'s constructor builds an InitiatorStack, which calls
         * `$entry->root()->id()` — so the fake needs a root even though the listener
         * never asks for one. Discovered by the event throwing, not by reading it.
         */
        public function root(): self
        {
            return $this;
        }

        public function published(): bool
        {
            return $this->published;
        }

        public function collectionHandle(): string
        {
            return $this->collection;
        }

        /**
         * The site handle. The listener reads `locale()` rather than
         * `site()->handle()` because Statamic derives the latter from the former
         * (`Entry::site()` is `Site::get($this->locale())`), so the flat call is the
         * same value without a nested object that can be null.
         */
        public function locale(): string
        {
            return $this->site;
        }
    };
}

/**
 * A stand-in whose accessors are untyped, matching Statamic's own signatures: `id()`,
 * `locale()` and `collectionHandle()` declare no return type, so the listener genuinely
 * can be handed a non-string, and the guard and casts it carries exist for that.
 */
function looseEntry(mixed $id, mixed $site = 'default', mixed $collection = 'pages'): object
{
    return new class($id, $site, $collection)
    {
        public function __construct(private mixed $id, private mixed $site, private mixed $collection) {}

        public function id(): mixed
        {
            return $this->id;
        }

        public function root(): self
        {
            return $this;
        }

        public function published(): bool
        {
            return true;
        }

        public function collectionHandle(): mixed
        {
            return $this->collection;
        }

        public function locale(): mixed
        {
            return $this->site;
        }
    };
}

function publishSaved(object $entry): void
{
    $event = new EntrySaved($entry);
    (new ScanLinksOnPublish)->handle($event);
}

it('is registered as a listener for EntrySaved', function (): void {
    // The tests below drive the listener directly, which says nothing about whether
    // anything ever calls it. Without this, the whole feature could be wired to no
    // event at all and every other test here would still pass.
    //
    // Read from getRawListeners() rather than getListeners(): the latter returns
    // listeners already wrapped in closures, so the class name is not recoverable and
    // a check against it reports absent for a listener that is present.
    $raw = Event::getRawListeners()[EntrySaved::class] ?? [];

    expect($raw)->toContain(ScanLinksOnPublish::class);
});

it('dispatches a scan when a published entry is saved', function (): void {
    Queue::fake();

    publishSaved(fakeEntry('entry-1'));

    Queue::assertPushed(ScanEntryLinks::class);
});

it('does not dispatch for an unpublished entry', function (): void {
    // AC6 — a draft's links are not yet anyone's problem, and scanning them spends
    // outbound requests on content that may never ship.
    Queue::fake();

    publishSaved(fakeEntry('entry-2', published: false));

    Queue::assertNothingPushed();
});

it('coalesces repeated saves of one entry into a single scan', function (): void {
    // AC5 — save-read-save is the normal editing rhythm, and without the window that is
    // one scan per button press.
    Queue::fake();

    publishSaved(fakeEntry('entry-3'));
    publishSaved(fakeEntry('entry-3'));
    publishSaved(fakeEntry('entry-3'));

    Queue::assertPushed(ScanEntryLinks::class, 1);
});

it('debounces per entry rather than globally', function (): void {
    // The window is claimed per id, so editing one entry must not silence another's scan.
    // A global flag would pass the test above and fail this one.
    Queue::fake();

    publishSaved(fakeEntry('entry-4'));
    publishSaved(fakeEntry('entry-5'));

    Queue::assertPushed(ScanEntryLinks::class, 2);
});

it('scopes the dispatched scan to the entry own site and collection', function (): void {
    // AC2 — the merge must not disturb other entries' findings, which starts with the
    // scan not being estate-wide.
    Queue::fake();

    publishSaved(fakeEntry('entry-6', site: 'news', collection: 'posts'));

    Queue::assertPushed(function (ScanEntryLinks $job): bool {
        return $job->entryId === 'entry-6'
            && $job->site === 'news'
            && $job->collection === 'posts';
    });
});

it('does not fail the save when dispatching throws', function (): void {
    // AC4 — a queue outage is a link-checking problem. Surfacing it as a save error would
    // make an editor think their content was lost.
    //
    // The dispatcher itself throws, so the catch is what keeps the save alive. Faking the bus
    // here would swallow the dispatch before anything could fail, and the test would pass
    // with the catch deleted.
    Bus::shouldReceive('dispatch')->once()->andThrow(new RuntimeException('queue is down'));
    Log::shouldReceive('warning')
        ->once()
        ->with('Could not dispatch on-publish link scan.', ['exception' => 'queue is down']);

    publishSaved(fakeEntry('entry-7'));
});

it('does nothing when the on-publish scan is disabled', function (): void {
    Queue::fake();
    config(['link_health.on_publish.enabled' => false]);

    publishSaved(fakeEntry('entry-8'));

    Queue::assertNothingPushed();
});

it('dispatches every time when the debounce window is zero', function (): void {
    // A zero window turns debouncing off rather than collapsing to a window so short it
    // is untestable. Without this, nothing distinguishes `<= 0` from `< 0`, and an
    // off-by-one there would silently claim a window for every save.
    Queue::fake();
    config(['link_health.on_publish.debounce_seconds' => 0]);

    publishSaved(fakeEntry('entry-7'));
    publishSaved(fakeEntry('entry-7'));

    Queue::assertPushed(ScanEntryLinks::class, 2);
});

it('still debounces at the smallest window that is not zero', function (): void {
    // Pairs with the zero-window test above to pin the boundary from both sides. With only
    // that one, `<= 0` and `<= 1` are indistinguishable -- and under `<= 1` a one-second
    // window would silently stop debouncing at all.
    //
    // The clock is pinned to the start of a second (#3328). The cache stores expiry in whole
    // seconds, so a one-second window ends at the next second boundary on the wall clock;
    // two saves on a real clock that straddle one push twice, which a loaded machine made
    // happen often enough to fail a parallel run.
    Queue::fake();
    config(['link_health.on_publish.debounce_seconds' => 1]);
    $this->travelTo(Carbon::parse('2026-10-26 12:00:00.000'));

    publishSaved(fakeEntry('entry-8'));
    publishSaved(fakeEntry('entry-8'));

    Queue::assertPushed(ScanEntryLinks::class, 1);
});

it('holds a one-second window until the next second and no longer', function (): void {
    // Pins the window's far edge, which the test above cannot see: a window that never
    // expired would pass it too. One millisecond before the next second the entry is still
    // debounced; at the second, the window has passed and the next save scans again.
    Queue::fake();
    config(['link_health.on_publish.debounce_seconds' => 1]);
    $this->travelTo(Carbon::parse('2026-10-26 12:00:00.000'));

    // Claim the window, then save again just inside it
    publishSaved(fakeEntry('entry-9'));
    $this->travelTo(Carbon::parse('2026-10-26 12:00:00.999'));
    publishSaved(fakeEntry('entry-9'));

    Queue::assertPushed(ScanEntryLinks::class, 1);

    // Save once the window has passed
    $this->travelTo(Carbon::parse('2026-10-26 12:00:01.000'));
    publishSaved(fakeEntry('entry-9'));

    Queue::assertPushed(ScanEntryLinks::class, 2);
});

it('ignores an entry that cannot name itself', function (): void {
    // Statamic declares no return type on id(), so a non-scalar is reachable. Dispatching
    // one would push a malformed job whose failure surfaces in a worker, where no editor
    // sees it -- so the listener returns instead.
    //
    // A STRINGABLE object rather than an array, and the difference decides whether this
    // test reaches the listener at all: EntrySaved's own constructor builds an
    // InitiatorStack that concatenates `$entry->root()->id()` into a string, so an array
    // raises there, before any listener runs. An object with __toString passes that and
    // is still not a scalar, which is exactly the case the guard is written for.
    Queue::fake();

    $unnameable = new class
    {
        public function __toString(): string
        {
            return 'stringable-but-not-scalar';
        }
    };

    publishSaved(looseEntry($unnameable));

    Queue::assertNotPushed(ScanEntryLinks::class);
});

it('carries a non-string scalar through as a string', function (): void {
    // ScanEntryLinks declares string properties under strict_types, so an un-cast scalar
    // is a TypeError swallowed by the listener's catch -- a scan that silently never runs.
    Queue::fake();

    publishSaved(looseEntry(7, 42, 99));

    Queue::assertPushed(function (ScanEntryLinks $job): bool {
        return $job->entryId === '7'
            && $job->site === '42'
            && $job->collection === '99';
    });
});

it('registers the recurring scan as a dispatched job at the configured cadence', function (): void {
    // AC1 — asserted against the real schedule rather than by reading routes/console.php,
    // so a registration that silently fails to take cannot pass.
    $events = collect(app(Schedule::class)->events());

    $scan = mustExist(
        $events->first(fn ($e): bool => str_contains((string) $e->getSummaryForDisplay(), ScanLinkHealth::class)),
        'the scheduled link-health scan',
    );

    expect($scan->expression)->toBe(config('link_health.schedule.cron'));
});
