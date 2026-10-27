<?php

declare(strict_types=1);

use App\Jobs\ApplyScheduledPublishing;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Statamic\Events\EntrySaving;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Tests\Support\StacheIsolatedTestCase;

/**
 * The scheduled job that publishes and unpublishes entries on their `publish_at` and
 * `unpublish_at` times: people (UAMS-Web/uams-statamic-archive#2552) and pages (UAMS-Web/uams-statamic-archive#2564).
 *
 * Both collections are undated, so this job is what holds a scheduled entry back.
 * Most fixtures are people; the page cases show the same schedule applies to pages, and
 * one case shows it reaches no other collection. People fixtures are saved into an
 * undated `people` collection
 * in a throwaway Stache, with a blueprint whose schedule fields are `date` fieldtypes
 * holding the string a Control Panel save stores, so the job reads them through the
 * same `augment()` it uses in production.
 *
 * @covers \App\Jobs\ApplyScheduledPublishing
 *
 * @command  php artisan test --compact tests/Feature/Publishing/ApplyScheduledPublishingTest.php
 */
uses(StacheIsolatedTestCase::class);

/**
 * Persist a person fixture.
 *
 * @param  string  $slug  The person's slug.
 * @param  string  $status  The stored `status_and_visibility`.
 * @param  array<string, mixed>  $schedule  `publish_at` / `unpublish_at`, as stored strings.
 */
function scheduledPerson(string $slug, string $status, array $schedule = []): Statamic\Entries\Entry
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection('people');
    $entry->blueprint('person');
    $entry->slug($slug);
    $entry->data(array_merge(['title' => 'Person '.$slug, 'status_and_visibility' => $status], $schedule));
    $entry->save();

    return $entry;
}

/**
 * Read an entry back from the Stache by id.
 *
 * @param  Statamic\Entries\Entry  $entry  The fixture.
 */
function reloadScheduledEntry(Statamic\Entries\Entry $entry): Statamic\Entries\Entry
{
    /** @var Statamic\Entries\Entry $found */
    $found = Entry::find($entry->id());

    return $found;
}

beforeEach(function (): void {
    // Record (don't serialize) queued EntrySaved listeners.
    Queue::fake();

    Carbon::setTestNow(Carbon::parse('2026-06-15 17:00:00', 'UTC'));

    // Undated, as `content/collections/people.yaml` is since UAMS-Web/uams-statamic-archive#2552
    Collection::make('people')->save();

    /** @var Statamic\Fields\Blueprint $blueprint */
    $blueprint = Blueprint::makeFromFields([
        'title' => ['type' => 'text'],
        'status_and_visibility' => ['type' => 'select', 'localizable' => false],
        'publish_at' => ['type' => 'date', 'time_enabled' => true, 'localizable' => false],
        'unpublish_at' => ['type' => 'date', 'time_enabled' => true, 'localizable' => false],
    ]);
    $blueprint->setHandle('person');
    $blueprint->setNamespace('collections.people');
    $blueprint->save();

    // Undated, as `content/collections/pages.yaml` is since UAMS-Web/uams-statamic-archive#2564
    Collection::make('pages')->save();

    /** @var Statamic\Fields\Blueprint $pageBlueprint */
    $pageBlueprint = Blueprint::makeFromFields([
        'title' => ['type' => 'text'],
        'status_and_visibility' => ['type' => 'select', 'localizable' => false],
        'publish_at' => ['type' => 'date', 'time_enabled' => true, 'localizable' => false],
        'unpublish_at' => ['type' => 'date', 'time_enabled' => true, 'localizable' => false],
    ]);
    $pageBlueprint->setHandle('basic_page');
    $pageBlueprint->setNamespace('collections.pages');
    $pageBlueprint->save();
});

/**
 * Persist a page fixture in the undated `pages` collection.
 *
 * @param  string  $slug  The page's slug.
 * @param  string  $status  The stored `status_and_visibility`.
 * @param  array<string, mixed>  $schedule  `publish_at` / `unpublish_at`, as stored strings.
 */
function scheduledPage(string $slug, string $status, array $schedule = []): Statamic\Entries\Entry
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection('pages');
    $entry->blueprint('basic_page');
    $entry->slug($slug);
    $entry->published($status === 'published');
    $entry->data(array_merge(['title' => 'Page '.$slug, 'status_and_visibility' => $status], $schedule));
    $entry->save();

    return $entry;
}

afterEach(function (): void {
    Carbon::setTestNow();
});

it('keeps a person with a future publish_at unpublished until the time passes, then publishes it', function (): void {
    $person = scheduledPerson('future', 'draft', [
        'publish_at' => '2026-06-15 18:00',
    ]);

    (new ApplyScheduledPublishing)->handle();

    expect(reloadScheduledEntry($person)->published())->toBeFalse()
        ->and(reloadScheduledEntry($person)->get('publish_at'))->toBe('2026-06-15 18:00');

    Carbon::setTestNow(Carbon::parse('2026-06-15 18:00:00', 'UTC'));
    (new ApplyScheduledPublishing)->handle();

    $found = reloadScheduledEntry($person);

    expect($found->published())->toBeTrue()
        ->and($found->get('status_and_visibility'))->toBe('published')
        ->and($found->get('publish_at'))->toBeNull();
});

it('publishes a pending person whose publish_at has passed', function (): void {
    $person = scheduledPerson('pending', 'pending', [
        'publish_at' => '2026-06-15 16:59',
    ]);

    (new ApplyScheduledPublishing)->handle();

    expect(reloadScheduledEntry($person)->published())->toBeTrue()
        ->and(reloadScheduledEntry($person)->get('status_and_visibility'))->toBe('published');
});

it('unpublishes a person whose unpublish_at has passed, back to draft', function (): void {
    $person = scheduledPerson('expiring', 'published', [
        'unpublish_at' => '2026-06-15 16:00',
    ]);

    expect($person->published())->toBeTrue();

    (new ApplyScheduledPublishing)->handle();

    $found = reloadScheduledEntry($person);

    expect($found->published())->toBeFalse()
        ->and($found->get('status_and_visibility'))->toBe('draft')
        ->and($found->get('unpublish_at'))->toBeNull();
});

it('leaves a published person with a future unpublish_at live', function (): void {
    $person = scheduledPerson('staying', 'published', [
        'unpublish_at' => '2026-06-16 09:00',
    ]);

    (new ApplyScheduledPublishing)->handle();

    expect(reloadScheduledEntry($person)->published())->toBeTrue()
        ->and(reloadScheduledEntry($person)->get('unpublish_at'))->toBe('2026-06-16 09:00');
});

it('ends in the state of the later schedule when both have passed', function (string $publishAt, string $unpublishAt, bool $expected): void {
    $person = scheduledPerson('both-'.($expected ? 'live' : 'down'), 'draft', [
        'publish_at' => $publishAt,
        'unpublish_at' => $unpublishAt,
    ]);

    (new ApplyScheduledPublishing)->handle();

    $found = reloadScheduledEntry($person);

    expect($found->published())->toBe($expected)
        ->and($found->get('publish_at'))->toBeNull()
        ->and($found->get('unpublish_at'))->toBeNull();
})->with([
    'unpublish, then publish' => ['2026-06-15 16:00', '2026-06-15 15:00', true],
    'publish, then unpublish' => ['2026-06-15 15:00', '2026-06-15 16:00', false],
]);

it('clears a due publish_at on a person that is already live, without a transition', function (): void {
    $person = scheduledPerson('private', 'private', [
        'publish_at' => '2026-06-15 12:00',
    ]);

    (new ApplyScheduledPublishing)->handle();

    $found = reloadScheduledEntry($person);

    expect($found->get('status_and_visibility'))->toBe('private')
        ->and($found->published())->toBeTrue()
        ->and($found->get('publish_at'))->toBeNull();
});

it('does not republish a person unpublished by hand after its publish_at was applied', function (): void {
    $person = scheduledPerson('once', 'draft', [
        'publish_at' => '2026-06-15 16:00',
    ]);

    (new ApplyScheduledPublishing)->handle();

    $found = reloadScheduledEntry($person);
    $found->set('status_and_visibility', 'draft');
    $found->save();

    (new ApplyScheduledPublishing)->handle();

    expect(reloadScheduledEntry($person)->published())->toBeFalse();
});

it('logs each transition and a summary of the run', function (): void {
    /** @var list<array{message: string, context: array<string, mixed>}> $logged */
    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged): void {
        $logged[] = ['message' => $event->message, 'context' => $event->context];
    });

    scheduledPerson('logged-up', 'draft', ['publish_at' => '2026-06-15 16:00']);
    scheduledPerson('logged-down', 'published', ['unpublish_at' => '2026-06-15 16:00']);
    scheduledPerson('logged-later', 'draft', ['publish_at' => '2026-06-20 16:00']);
    scheduledPerson('unscheduled', 'draft');

    (new ApplyScheduledPublishing)->handle();

    $messages = array_column($logged, 'message');
    $summary = collect($logged)->firstWhere('message', 'Applied scheduled publishing.');

    expect(array_count_values($messages))->toMatchArray([
        'Applied a scheduled publish.' => 1,
        'Applied a scheduled unpublish.' => 1,
        'Applied scheduled publishing.' => 1,
    ])
        ->and($summary['context'] ?? null)->toMatchArray(['published' => 1, 'unpublished' => 1, 'scheduled' => 3]);
});

it('unpublishes when publish_at and unpublish_at fall at the same instant', function (): void {
    $person = scheduledPerson('tie', 'draft', [
        'publish_at' => '2026-06-15 16:00',
        'unpublish_at' => '2026-06-15 16:00',
    ]);

    (new ApplyScheduledPublishing)->handle();

    expect(reloadScheduledEntry($person)->published())->toBeFalse();
});

it('publishes a person with no stored status only when it is not already live', function (bool $publishedBefore): void {
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection('people');
    $entry->blueprint('person');
    $entry->slug('no-status-'.($publishedBefore ? 'live' : 'down'));
    $entry->published($publishedBefore);
    $entry->data(['title' => 'No Status', 'publish_at' => '2026-06-15 16:00']);
    $entry->saveQuietly();

    (new ApplyScheduledPublishing)->handle();

    $found = reloadScheduledEntry($entry);

    expect($found->published())->toBeTrue()
        ->and($found->get('publish_at'))->toBeNull()
        ->and($found->get('status_and_visibility'))->toBe($publishedBefore ? null : 'published');
})->with(['already live' => [true], 'not live' => [false]]);

it('reads a schedule stored as a Carbon by a programmatic save', function (): void {
    $person = scheduledPerson('carbon', 'draft', [
        'publish_at' => Carbon::parse('2026-06-15 16:30:00', 'UTC'),
    ]);

    (new ApplyScheduledPublishing)->handle();

    expect(reloadScheduledEntry($person)->published())->toBeTrue();
});

it('keeps applying the other schedules when one person cannot be saved', function (): void {
    /** @var list<array{message: string, context: array<string, mixed>}> $logged */
    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged): void {
        $logged[] = ['message' => $event->message, 'context' => $event->context];
    });

    $broken = scheduledPerson('a-broken', 'draft', ['publish_at' => '2026-06-15 16:00']);
    $fine = scheduledPerson('b-fine', 'draft', ['publish_at' => '2026-06-15 16:00']);

    Event::listen(EntrySaving::class, function (EntrySaving $event) use ($broken): void {
        $saving = $event->entry;

        if ($saving instanceof Statamic\Entries\Entry && $saving->id() === $broken->id()) {
            throw new RuntimeException('Simulated save failure');
        }
    });

    (new ApplyScheduledPublishing)->handle();

    $summary = collect($logged)->firstWhere('message', 'Applied scheduled publishing.');

    expect(reloadScheduledEntry($fine)->published())->toBeTrue()
        ->and(reloadScheduledEntry($broken)->published())->toBeFalse()
        ->and(array_column($logged, 'message'))->toContain('Could not apply a scheduled publishing change.')
        ->and($summary['context'] ?? null)->toMatchArray(['published' => 1, 'failed' => 1]);
});

it('registers the job on the schedule at the configured cadence, in the display timezone', function (): void {
    // Asserted against the real schedule, so a registration that fails to take cannot pass
    $events = collect(app(Schedule::class)->events());

    $job = mustExist(
        $events->first(fn ($e): bool => str_contains((string) $e->getSummaryForDisplay(), ApplyScheduledPublishing::class)),
        'the scheduled publishing job',
    );

    expect($job->expression)->toBe(config('scheduled_publishing.schedule.cron'))
        ->and($job->timezone)->toBe(Statamic\Statamic::displayTimezone());
});

it('publishes a page whose publish_at has passed and unpublishes one whose unpublish_at has', function (): void {
    $upcoming = scheduledPage('open-enrollment', 'draft', ['publish_at' => '2026-06-15 16:00']);
    $expiring = scheduledPage('spring-event', 'published', ['unpublish_at' => '2026-06-15 16:00']);
    $later = scheduledPage('fall-event', 'draft', ['publish_at' => '2026-06-20 16:00']);

    (new ApplyScheduledPublishing)->handle();

    $opened = reloadScheduledEntry($upcoming);
    $closed = reloadScheduledEntry($expiring);

    expect($opened->published())->toBeTrue()
        ->and($opened->get('status_and_visibility'))->toBe('published')
        ->and($opened->get('publish_at'))->toBeNull()
        ->and($closed->published())->toBeFalse()
        ->and($closed->get('status_and_visibility'))->toBe('draft')
        ->and($closed->get('unpublish_at'))->toBeNull()
        // Not yet due, so untouched
        ->and(reloadScheduledEntry($later)->published())->toBeFalse()
        ->and(reloadScheduledEntry($later)->get('publish_at'))->toBe('2026-06-20 16:00');
});

it('leaves a due schedule alone in a collection it does not schedule', function (): void {
    Collection::make('posts')->save();

    /** @var Statamic\Fields\Blueprint $postBlueprint */
    $postBlueprint = Blueprint::makeFromFields([
        'title' => ['type' => 'text'],
        'publish_at' => ['type' => 'date', 'time_enabled' => true],
    ]);
    $postBlueprint->setHandle('post');
    $postBlueprint->setNamespace('collections.posts');
    $postBlueprint->save();

    /** @var Statamic\Entries\Entry $post */
    $post = Entry::make();
    $post->collection('posts');
    $post->blueprint('post');
    $post->slug('not-scheduled-here');
    $post->published(false);
    $post->data(['title' => 'Post', 'publish_at' => '2026-06-15 16:00']);
    $post->saveQuietly();

    (new ApplyScheduledPublishing)->handle();

    $found = reloadScheduledEntry($post);

    expect(ApplyScheduledPublishing::collections())->toBe(['people', 'pages'])
        ->and($found->published())->toBeFalse()
        ->and($found->get('publish_at'))->toBe('2026-06-15 16:00');
});

/**
 * Give a scheduled origin a `health` localization with its own `published` flag.
 *
 * The origin's collection is opened to `health` first. The schedule and
 * `status_and_visibility` are `localizable: false`, so the localization stores neither.
 *
 * @param  Statamic\Entries\Entry  $origin  The scheduled origin.
 * @param  bool  $published  The localization's own `published` flag.
 */
function scheduledLocalization(Statamic\Entries\Entry $origin, bool $published): Statamic\Entries\Entry
{
    /** @var Statamic\Entries\Collection $collection */
    $collection = $origin->collection();
    $collection->sites(['default', 'health']);
    $collection->save();

    /** @var Statamic\Entries\Entry $localization */
    $localization = $origin->makeLocalization('health');
    $localization->published($published);
    $localization->saveQuietly();

    return $localization;
}

it('unpublishes each live localization when its origin\'s unpublish_at passes', function (): void {
    $origin = scheduledPage('localized-down', 'published', ['unpublish_at' => '2026-06-15 16:00']);
    $localization = scheduledLocalization($origin, true);

    (new ApplyScheduledPublishing)->handle();

    expect(reloadScheduledEntry($origin)->published())->toBeFalse()
        ->and(reloadScheduledEntry($localization)->published())->toBeFalse();
});

// Carried by the origin's save re-saving its localizations, not by the job itself; this pins the outcome
it('publishes each localization that is not yet live when its origin\'s publish_at passes', function (): void {
    $origin = scheduledPage('localized-up', 'draft', ['publish_at' => '2026-06-15 16:00']);
    $localization = scheduledLocalization($origin, false);

    (new ApplyScheduledPublishing)->handle();

    expect(reloadScheduledEntry($origin)->published())->toBeTrue()
        ->and(reloadScheduledEntry($localization)->published())->toBeTrue();
});

it('counts only the localizations a schedule moved', function (): void {
    /** @var list<array{message: string, context: array<string, mixed>}> $logged */
    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged): void {
        $logged[] = ['message' => $event->message, 'context' => $event->context];
    });

    // One live localization is taken down with its origin; one an editor had already drafted is not moved
    $origin = scheduledPage('localized-counted', 'published', ['unpublish_at' => '2026-06-15 16:00']);
    scheduledLocalization($origin, true);
    $drafted = scheduledPage('localized-drafted', 'published', ['unpublish_at' => '2026-06-15 16:00']);
    scheduledLocalization($drafted, false);

    (new ApplyScheduledPublishing)->handle();

    $summary = collect($logged)->firstWhere('message', 'Applied scheduled publishing.');
    $localized = collect($logged)->where('message', 'Applied a scheduled unpublish.')->filter(
        fn (array $line): bool => isset($line['context']['origin']),
    );

    expect($summary['context'] ?? null)->toMatchArray(['published' => 0, 'unpublished' => 3])
        ->and($localized->pluck('context.origin')->all())->toBe([$origin->id()])
        ->and($localized->pluck('context.site')->all())->toBe(['health']);
});

it('warns about a stored schedule whose blueprint has no field for it', function (): void {
    /** @var list<array{level: string, message: string, context: array<string, mixed>}> $logged */
    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged): void {
        $logged[] = ['level' => $event->level, 'message' => $event->message, 'context' => $event->context];
    });

    /** @var Statamic\Fields\Blueprint $bare */
    $bare = Blueprint::makeFromFields(['title' => ['type' => 'text']]);
    $bare->setHandle('bare_page');
    $bare->setNamespace('collections.pages');
    $bare->save();

    /** @var Statamic\Entries\Entry $page */
    $page = Entry::make();
    $page->collection('pages');
    $page->blueprint('bare_page');
    $page->slug('schedule-without-field');
    $page->published(false);
    $page->data(['title' => 'Stranded', 'publish_at' => '2026-06-15 16:00']);
    $page->saveQuietly();

    (new ApplyScheduledPublishing)->handle();

    $warning = collect($logged)->firstWhere('message', 'Ignored a stored schedule whose blueprint has no field for it.');

    expect($warning['level'] ?? null)->toBe('warning')
        ->and($warning['context'] ?? null)->toMatchArray(['id' => $page->id(), 'blueprint' => 'bare_page', 'field' => 'publish_at'])
        ->and(reloadScheduledEntry($page)->published())->toBeFalse();
});
