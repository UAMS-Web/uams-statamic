<?php

declare(strict_types=1);

use App\Listeners\SyncEntryPublishedStatus;
use Illuminate\Support\Facades\Queue;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Contracts\Auth\User;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\User as UserFacade;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Proves `status_and_visibility` reconciles with Statamic's native `published`
 * boolean on save (UAMS-Web/uams-statamic-archive#223), considering *what changed* this save so it never
 * split-brains and never silently un/publishes content:
 *
 *   - draft/pending → not published; published/private/scheduled → published;
 *   - a collection without the status field is left untouched;
 *   - a direct `published` change (native publish toggle / Unpublish action) with
 *     an unchanged status is honored and the status is reconciled;
 *   - a legacy published entry whose status field is default-injected on an
 *     unrelated edit stays published;
 *   - a non-publisher may only *set* draft/pending — a live status they choose is
 *     downgraded to pending, but merely editing an already-live entry is left be.
 *
 * Uses {@see StacheIsolatedTestCase} so real entries persist to a throwaway Stache
 * (never production content), which also gives each save a synced original for the
 * dirty-state checks the listener relies on.
 *
 * @covers \App\Listeners\SyncEntryPublishedStatus
 *
 * @command  php artisan test --compact tests/Feature/Listeners/SyncEntryPublishedStatusTest.php
 */
uses(StacheIsolatedTestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Fake the current CP user for the publish-permission guard.
 *
 * @param  list<string>  $permissions
 */
function syncStatusActingAs(bool $super, array $permissions = []): void
{
    $user = Mockery::mock(User::class);
    $user->shouldReceive('isSuper')->andReturn($super);
    $user->shouldReceive('hasPermission')->andReturnUsing(fn (string $p): bool => in_array($p, $permissions, true));

    UserFacade::shouldReceive('current')->andReturn($user);
}

/**
 * Persist a `status_probe` entry, returning it with its original synced (so a
 * subsequent modify-and-save exercises the listener's dirty-state logic).
 *
 * @param  array<string, mixed>  $data
 */
function syncStatusSavedEntry(string $slug, array $data, ?bool $published = null): Statamic\Entries\Entry
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection('status_probe');
    $entry->slug($slug);
    $entry->data(array_merge(['title' => 'Probe'], $data));

    if ($published !== null) {
        $entry->published($published);
    }

    $entry->save();

    return $entry;
}

/**
 * Make a `status_probe` entry that has not been saved, with a `published` flag
 * that the status sync has not reconciled.
 *
 * @param  array<string, mixed>  $data
 */
function syncStatusUnsavedEntry(string $slug, array $data, bool $published): Statamic\Entries\Entry
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection('status_probe');
    $entry->slug($slug);
    $entry->data($data);
    $entry->published($published);

    return $entry;
}

beforeEach(function (): void {
    // Record (don't serialize) queued EntrySaved listeners, so a faked current
    // user stamped as the entry's author never hits `serialize()` on a queued job.
    Queue::fake();
    Collection::make('status_probe')->save();
});

it('maps status_and_visibility to the published boolean on save', function (string $status, bool $expected): void {
    $entry = syncStatusSavedEntry('e-'.$status, ['status_and_visibility' => $status], published: ! $expected);

    /** @var Statamic\Entries\Entry|null $found */
    $found = Entry::find($entry->id());

    expect($entry->published())->toBe($expected)
        ->and($found?->published())->toBe($expected);
})->with([
    'draft → not published' => ['draft', false],
    'pending → not published' => ['pending', false],
    'published → published' => ['published', true],
    'private → published' => ['private', true],
    'scheduled → published' => ['scheduled', true],
]);

it('leaves published untouched for a collection without the status field', function (): void {
    $entry = syncStatusSavedEntry('no-status', [], published: true);

    expect($entry->published())->toBeTrue();
});

it('does not force a status-less entry to published', function (): void {
    $entry = syncStatusSavedEntry('no-status-unpub', [], published: false);

    expect($entry->published())->toBeFalse();
});

it('downgrades a new live status to pending when the actor cannot publish', function (string $chosen): void {
    syncStatusActingAs(super: false, permissions: ['edit status_probe entries']); // no publish permission

    $entry = syncStatusSavedEntry('contrib-'.$chosen, ['status_and_visibility' => $chosen], published: true);

    expect($entry->get('status_and_visibility'))->toBe('pending')
        ->and($entry->published())->toBeFalse();
})->with(['published', 'private', 'scheduled']);

it('downgrades to pending when a non-publisher elevates a stored draft to a live status', function (): void {
    $entry = syncStatusSavedEntry('elevate', ['status_and_visibility' => 'draft']);

    syncStatusActingAs(super: false, permissions: ['edit status_probe entries']);
    $entry->set('status_and_visibility', 'published');
    $entry->save();

    expect($entry->get('status_and_visibility'))->toBe('pending')
        ->and($entry->published())->toBeFalse();
});

it('leaves an already-live entry untouched when a non-publisher edits it without changing the status', function (): void {
    // Published by an authorized prior save (no acting user → treated as CLI/import).
    $entry = syncStatusSavedEntry('live-edit', ['status_and_visibility' => 'published']);
    expect($entry->published())->toBeTrue();

    syncStatusActingAs(super: false, permissions: ['edit status_probe entries']);
    $entry->set('title', 'Live, edited');
    $entry->save();

    expect($entry->published())->toBeTrue()
        ->and($entry->get('status_and_visibility'))->toBe('published');
});

it('keeps a draft/pending choice untouched for a non-publisher', function (): void {
    syncStatusActingAs(super: false, permissions: ['edit status_probe entries']);

    $entry = syncStatusSavedEntry('contrib-draft', ['status_and_visibility' => 'draft']);

    expect($entry->get('status_and_visibility'))->toBe('draft')
        ->and($entry->published())->toBeFalse();
});

it('does not downgrade a live status for a user who can publish', function (): void {
    syncStatusActingAs(super: false, permissions: ['publish status_probe entries']);

    $entry = syncStatusSavedEntry('editor-pub', ['status_and_visibility' => 'published']);

    expect($entry->get('status_and_visibility'))->toBe('published')
        ->and($entry->published())->toBeTrue();
});

it('does not downgrade a live status for a super user', function (): void {
    syncStatusActingAs(super: true);

    $entry = syncStatusSavedEntry('super-pub', ['status_and_visibility' => 'private']);

    expect($entry->get('status_and_visibility'))->toBe('private')
        ->and($entry->published())->toBeTrue();
});

it('honors a native unpublish that leaves the status field unchanged and reconciles it', function (): void {
    // The native CP Unpublish action sets published=false without touching status.
    $entry = syncStatusSavedEntry('unpub', ['status_and_visibility' => 'published']);
    expect($entry->published())->toBeTrue();

    $entry->published(false);
    $entry->save();

    expect($entry->published())->toBeFalse()
        ->and($entry->get('status_and_visibility'))->toBe('draft');
});

it('honors a native publish toggle on a stored draft and reconciles it', function (): void {
    $entry = syncStatusSavedEntry('toggle', ['status_and_visibility' => 'draft']);
    expect($entry->published())->toBeFalse();

    $entry->published(true);
    $entry->save();

    expect($entry->published())->toBeTrue()
        ->and($entry->get('status_and_visibility'))->toBe('published');
});

it('keeps a legacy published entry live when the CP injects the draft default', function (): void {
    // A page authored before the status fieldset existed: published, no status value.
    $entry = syncStatusSavedEntry('legacy', [], published: true);
    expect($entry->published())->toBeTrue()
        ->and($entry->get('status_and_visibility'))->toBeNull();

    // The CP edit form injects the fieldset default on an unrelated save.
    $entry->set('status_and_visibility', 'draft');
    $entry->save();

    expect($entry->published())->toBeTrue()
        ->and($entry->get('status_and_visibility'))->toBe('published');
});

it('still lets a deliberate later change unpublish a backfilled legacy entry', function (): void {
    // Guards the legacy protection: it must not permanently pin an entry live.
    $entry = syncStatusSavedEntry('legacy-then-draft', [], published: true);
    $entry->set('status_and_visibility', 'draft'); // first edit → backfilled to 'published'
    $entry->save();
    expect($entry->get('status_and_visibility'))->toBe('published');

    // A second, deliberate change to draft is now a real transition and unpublishes.
    $entry->set('status_and_visibility', 'draft');
    $entry->save();

    expect($entry->published())->toBeFalse()
        ->and($entry->get('status_and_visibility'))->toBe('draft');
});

it('puts back a publish_at or unpublish_at change made by a user who cannot publish', function (string $field): void {
    // Stored by an authorized prior save (no acting user is treated as CLI/import)
    $entry = syncStatusSavedEntry('schedule-'.$field, ['status_and_visibility' => 'draft', $field => '2026-07-01 09:00']);

    syncStatusActingAs(super: false, permissions: ['edit status_probe entries']);
    $entry->set($field, '2026-06-01 09:00');
    $entry->save();

    expect($entry->get($field))->toBe('2026-07-01 09:00');
})->with(['publish_at', 'unpublish_at']);

it('removes a schedule a user who cannot publish adds to an unscheduled entry', function (): void {
    $entry = syncStatusSavedEntry('schedule-new', ['status_and_visibility' => 'draft']);

    syncStatusActingAs(super: false, permissions: ['edit status_probe entries']);
    $entry->set('publish_at', '2026-06-01 09:00');
    $entry->save();

    expect($entry->has('publish_at'))->toBeFalse();
});

it('keeps a schedule set by a user who can publish', function (): void {
    $entry = syncStatusSavedEntry('schedule-publisher', ['status_and_visibility' => 'draft']);

    syncStatusActingAs(super: false, permissions: ['edit status_probe entries', 'publish status_probe entries']);
    $entry->set('publish_at', '2026-06-01 09:00');
    $entry->set('unpublish_at', '2026-06-30 09:00');
    $entry->save();

    expect($entry->get('publish_at'))->toBe('2026-06-01 09:00')
        ->and($entry->get('unpublish_at'))->toBe('2026-06-30 09:00');
});

it('lets a user who cannot publish withdraw a scheduled publish, but not a scheduled unpublish', function (): void {
    $entry = syncStatusSavedEntry('schedule-withdraw', [
        'status_and_visibility' => 'draft',
        'publish_at' => '2026-07-01 09:00',
        'unpublish_at' => '2026-08-01 09:00',
    ]);

    syncStatusActingAs(super: false, permissions: ['edit status_probe entries']);
    $entry->remove('publish_at');
    $entry->remove('unpublish_at');
    $entry->save();

    expect($entry->has('publish_at'))->toBeFalse()
        ->and($entry->get('unpublish_at'))->toBe('2026-08-01 09:00');
});

it('predicts before the save the published state the save leaves (#3240)', function (Closure $prepare, bool $expected): void {
    // Stage this scenario's unsaved change on an entry
    /** @var Statamic\Entries\Entry $entry */
    $entry = $prepare();

    // Ask the listener before it runs, while `published` may still be stale
    $predicted = (new SyncEntryPublishedStatus)->willBePublished($entry);

    $entry->save();

    expect($predicted)->toBe($expected)
        ->and($entry->published())->toBe($expected);
})->with([
    'a new live status over a stale unpublished flag' => [fn () => syncStatusUnsavedEntry('p-new-live', ['title' => 'Probe', 'status_and_visibility' => 'published'], false), true],
    'a new draft status over a stale published flag' => [fn () => syncStatusUnsavedEntry('p-new-draft', ['title' => 'Probe', 'status_and_visibility' => 'draft'], true), false],
    'a collection without the status field' => [fn () => syncStatusUnsavedEntry('p-no-status', ['title' => 'Probe'], true), true],
    'a native unpublish with the status unchanged' => [fn () => syncStatusSavedEntry('p-unpub', ['status_and_visibility' => 'published'])->published(false), false],
    'a native publish over a stored draft' => [fn () => syncStatusSavedEntry('p-toggle', ['status_and_visibility' => 'draft'])->published(true), true],
    'the draft default injected onto a legacy published entry' => [fn () => syncStatusSavedEntry('p-legacy', [], published: true)->set('status_and_visibility', 'draft'), true],
    'a live status chosen by a user who cannot publish' => [function () {
        $entry = syncStatusSavedEntry('p-elevate', ['status_and_visibility' => 'draft']);
        syncStatusActingAs(super: false, permissions: ['edit status_probe entries']);

        return $entry->set('status_and_visibility', 'published');
    }, false],
]);

it('leaves a split status alone on a native toggle that already agrees with it (#3240)', function (string $status, bool $storedPublished): void {
    // Store a status and a published flag that disagree, bypassing the listener
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection('status_probe');
    $entry->slug('split-'.$status);
    $entry->data(['title' => 'Probe', 'status_and_visibility' => $status]);
    $entry->published($storedPublished);
    $entry->saveQuietly();

    // Toggle `published` to the state the status already implies
    $entry->published(! $storedPublished);
    $entry->save();

    expect($entry->get('status_and_visibility'))->toBe($status)
        ->and($entry->published())->toBe(! $storedPublished);
})->with([
    'unpublish over a stored draft' => ['draft', true],
    'publish over a stored live status' => ['published', false],
]);

it('leaves published untouched when the status field holds an empty string', function (): void {
    $entry = syncStatusSavedEntry('empty-status', ['status_and_visibility' => ''], published: true);

    $entry->set('title', 'Edited');
    $entry->save();

    expect($entry->published())->toBeTrue();
});
