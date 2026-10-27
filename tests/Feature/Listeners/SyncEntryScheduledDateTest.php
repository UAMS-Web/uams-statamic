<?php

declare(strict_types=1);

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Queue;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Proves the scheduled go-live date reconciliation (UAMS-Web/uams-statamic-archive#1162): a `scheduled` entry
 * gates on the editor-chosen `date_scheduled`, not the always-`now` collection
 * `date` field, so a collection with `date_behavior: {future: private}` withholds
 * it from the public until that date and it goes live automatically once the date
 * arrives.
 *
 * Faithful to the real CP save path: `date_scheduled` is a `date` fieldtype, and
 * the CP stores it as a FORMATTED STRING (Date::process → formatAndCast), not a
 * Carbon. The listener must read it back through the field's `augment()` — reading
 * the raw `value()` and checking `instanceof CarbonInterface` would silently
 * no-op in production (the string never satisfies the guard). The blueprint is
 * defined with a real `date` field and the fixtures seed `date_scheduled` as the
 * stored string so this test exercises that exact path rather than a Carbon
 * stuffed straight into `data()`.
 *
 * Uses {@see StacheIsolatedTestCase} so a real dated collection + a real blueprint
 * persist to throwaway roots. `scheduled_probe` is the target shape for
 * `knowledge_bases`, whose `date_behavior` config ships in the content submodule
 * (`pages` is undated since UAMS-Web/uams-statamic-archive#2564).
 *
 * @covers \App\Listeners\SyncEntryPublishedStatus
 *
 * @command  php artisan test --compact tests/Feature/Listeners/SyncEntryScheduledDateTest.php
 */
uses(StacheIsolatedTestCase::class);

/**
 * The Date fieldtype's stored format for a `time_enabled` field (Date::saveFormat
 * → DEFAULT_DATETIME_FORMAT). The fixtures render `date_scheduled` with this so
 * the value is byte-for-byte what a CP save persists, and the listener's
 * `augment()` round-trips it back to a Carbon via `parseSaved`.
 */
const SCHEDULED_SAVE_FORMAT = 'Y-m-d H:i';

/**
 * Persist a `scheduled_probe` entry, seeding its collection date with `now` (the
 * fieldset's `date` default) so the reconciliation has something to override.
 * `date_scheduled`, when given, is passed as the STORED STRING — exactly what the
 * Date fieldtype's `process()` writes on a real CP save.
 *
 * @param  array<string, mixed>  $data
 */
function scheduledProbeEntry(string $slug, array $data): Statamic\Entries\Entry
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection('scheduled_probe');
    $entry->slug($slug);
    $entry->date(now());
    $entry->data(array_merge(['title' => 'Probe'], $data));

    $entry->save();

    return $entry;
}

beforeEach(function (): void {
    // Record (don't serialize) queued EntrySaved listeners.
    Queue::fake();

    // A dated collection that withholds future-dated entries and serves past ones
    // — the shape UAMS-Web/uams-statamic-archive#1162 added to knowledge_bases (companion content PR).
    /** @var Statamic\Entries\Collection $collection */
    $collection = Collection::make('scheduled_probe');
    $collection->dated(true);
    $collection->futureDateBehavior('private');
    $collection->pastDateBehavior('public');
    $collection->save();

    // A real blueprint with `date_scheduled` typed as a `date` fieldtype, so the
    // listener's `augment()` resolves the stored string back to a Carbon exactly
    // as it does in production. Without a date-typed field the raw string would
    // never augment — the faithful reproduction of the CP path.
    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = Blueprint::makeFromFields([
        'title' => ['type' => 'text'],
        'status_and_visibility' => ['type' => 'select', 'localizable' => false],
        'date_scheduled' => ['type' => 'date', 'time_enabled' => true, 'localizable' => false],
    ]);
    $blueprint->setHandle('scheduled_probe');
    $blueprint->setNamespace('collections.scheduled_probe');
    $blueprint->save();
});

it('gates a future scheduled entry on date_scheduled and withholds it until then', function (): void {
    $goLive = now()->addDays(10)->startOfMinute();

    $entry = scheduledProbeEntry('future', [
        'status_and_visibility' => 'scheduled',
        // Stored as the CP does: a formatted string, not a Carbon.
        'date_scheduled' => $goLive->format(SCHEDULED_SAVE_FORMAT),
    ]);

    // The listener augmented the stored string and synced the collection date to
    // the chosen go-live date (within the format's minute granularity) ...
    /** @var CarbonInterface $entryDate */
    $entryDate = $entry->date();
    /** @var int $entryTimestamp */
    $entryTimestamp = $entryDate->timestamp;
    /** @var int $goLiveTimestamp */
    $goLiveTimestamp = $goLive->timestamp;
    expect(abs($entryTimestamp - $goLiveTimestamp))->toBeLessThan(60)
        // ... so date_behavior withholds it from the public now ...
        ->and($entry->private())->toBeTrue()
        // ... while it stays published (scheduled → published), ready to surface.
        ->and($entry->published())->toBeTrue();

    // Persisted: a reload sees the same synced date and withheld state.
    /** @var Statamic\Entries\Entry|null $reloaded */
    $reloaded = Entry::find($entry->id());
    /** @var CarbonInterface|null $reloadedDate */
    $reloadedDate = $reloaded?->date();
    /** @var int|null $reloadedTimestamp */
    $reloadedTimestamp = $reloadedDate?->timestamp;
    expect(abs(($reloadedTimestamp ?? 0) - $goLiveTimestamp))->toBeLessThan(60)
        ->and($reloaded?->private())->toBeTrue();
});

it('serves a scheduled entry whose date_scheduled has already arrived', function (): void {
    $past = now()->subDays(3)->startOfMinute();

    $entry = scheduledProbeEntry('past', [
        'status_and_visibility' => 'scheduled',
        'date_scheduled' => $past->format(SCHEDULED_SAVE_FORMAT),
    ]);

    /** @var CarbonInterface $entryDate */
    $entryDate = $entry->date();
    /** @var int $entryTimestamp */
    $entryTimestamp = $entryDate->timestamp;
    /** @var int $pastTimestamp */
    $pastTimestamp = $past->timestamp;
    expect(abs($entryTimestamp - $pastTimestamp))->toBeLessThan(60)
        ->and($entry->private())->toBeFalse()
        ->and($entry->published())->toBeTrue();
});

it('does not move the collection date for a non-scheduled status', function (): void {
    $originalDate = now();
    $future = now()->addDays(5)->startOfMinute();

    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection('scheduled_probe');
    $entry->slug('published');
    $entry->date($originalDate);
    $entry->data([
        'title' => 'Probe',
        'status_and_visibility' => 'published',
        'date_scheduled' => $future->format(SCHEDULED_SAVE_FORMAT),
    ]);
    $entry->save();

    // A `published` entry keeps its own date — a lingering date_scheduled is ignored.
    /** @var CarbonInterface $entryDate */
    $entryDate = $entry->date();
    expect($entryDate->timestamp)->toBe($originalDate->timestamp)
        ->and($entry->private())->toBeFalse();
});

it('leaves the date untouched for a scheduled entry with no date_scheduled', function (): void {
    $keptDate = now()->addDays(2);

    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection('scheduled_probe');
    $entry->slug('no-scheduled-date');
    $entry->date($keptDate);
    $entry->data(['title' => 'Probe', 'status_and_visibility' => 'scheduled']);
    $entry->save();

    // With no date_scheduled to sync from, the chosen collection date stands
    // (never zeroed or reset to now).
    /** @var CarbonInterface $entryDate */
    $entryDate = $entry->date();
    expect($entryDate->timestamp)->toBe($keptDate->timestamp);
});

it('leaves the date alone when a native publish toggle decides a scheduled save (#3240)', function (): void {
    $keptDate = now()->subDay()->startOfMinute();
    $goLive = now()->addDays(10)->startOfMinute();

    // Store a scheduled entry whose published flag is off, bypassing the listener
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection('scheduled_probe');
    $entry->slug('toggle-scheduled');
    $entry->date($keptDate);
    $entry->data([
        'title' => 'Probe',
        'status_and_visibility' => 'scheduled',
        'date_scheduled' => $goLive->format(SCHEDULED_SAVE_FORMAT),
    ]);
    $entry->published(false);
    $entry->saveQuietly();

    // Publish through the native toggle, leaving the status unchanged
    $entry->published(true);
    $entry->save();

    /** @var CarbonInterface $entryDate */
    $entryDate = $entry->date();
    expect($entryDate->timestamp)->toBe($keptDate->timestamp)
        ->and($entry->published())->toBeTrue();
});
