<?php

declare(strict_types=1);

use App\Jobs\SyncHealthTalkEpisodes;
use App\Services\Podcasts\HealthTalk\HealthTalkEpisodeSync;
use App\Services\Podcasts\HealthTalk\HealthTalkSpeakerMappings;
use App\Services\Podcasts\HealthTalk\HealthTalkSyncReport;
use App\Widgets\HealthTalkSync;
use Carbon\CarbonInterface;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View as IlluminateView;
use PHPUnit\Framework\Assert;
use Psr\Log\LoggerInterface;
use Statamic\Auth\File\User as FileUser;
use Statamic\Entries\Collection as StatamicCollection;
use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\User;
use Statamic\Fields\Blueprint;
use Statamic\Globals\GlobalSet as StatamicGlobalSet;
use Statamic\Globals\Variables;
use Statamic\Stache\Query\EntryQueryBuilder;
use Tests\Support\Doubles\HealthTalkFakeSource;
use Tests\Support\Doubles\HealthTalkRecordingLogger;
use Tests\Support\StacheIsolatedTestCase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

uses(StacheIsolatedTestCase::class);

/**
 * UAMS-Web/uams-statamic-archive#1803 — the UAMS Health Talk sync, run end to end against a committed feed
 * fixture and never the network.
 *
 * `tests/Fixtures/HealthTalk/` holds five real episodes taken from the feed and
 * item pages on 2026-09-25, chosen for the cases the ticket names:
 *
 * | id      | episode | speakers                                       | transcript |
 * | ------- | ------- | ---------------------------------------------- | ---------- |
 * | `69402` | 120     | Robert Hopkins, linked `robert-h-hopkins-jr`   | yes        |
 * | `69184` | 118     | Gresham Richter, linked `gresham-t-richter`    | yes        |
 * | `68170` | 112     | panel: Lexie Vaughn (linked) + Tyler Rives     | yes        |
 * | `43833` | 37      | Laura Spradley, unlinked                       | none       |
 * | `41530` | none    | Joseph Henske, unlinked (`uams016bpd.mp3`)     | yes        |
 *
 * Each test builds its feed from that fixture, so a variant -- an item added,
 * removed or edited -- is always real feed markup rather than a hand-written one.
 *
 * @command php artisan test --compact tests/Feature/Podcasts/HealthTalkEpisodeSyncTest.php
 */
const HEALTH_TALK_FIXTURES = __DIR__.'/../../Fixtures/HealthTalk';
const HEALTH_TALK_FEED_URL = 'https://support.doctorpodcasting.com/podcastfeed/287';
const HEALTH_TALK_ALL_IDS = ['69402', '69184', '68170', '43833', '41530'];

beforeEach(function (): void {
    config()->set('uams_health_talk.source.pause_ms', 0);
    config()->set('uams_health_talk.max_unpublish_per_run', 5);

    app()->instance(LoggerInterface::class, new HealthTalkRecordingLogger);

    // The real blueprints, read from this throwaway blueprint root
    foreach ([
        'collections/clinical_resources/audio.yaml',
        'globals/podcast_speaker_mappings.yaml',
    ] as $path) {
        File::ensureDirectoryExists(dirname($this->fakeBlueprintDirectory.'/'.$path));
        File::copy(resource_path('blueprints/'.$path), $this->fakeBlueprintDirectory.'/'.$path);
    }

    saveHealthTalkCollection('clinical_resources', 'health', dated: true);
    saveHealthTalkCollection('people', 'default', dated: false);

    $set = GlobalSet::make('podcast_speaker_mappings');
    Assert::assertInstanceOf(StatamicGlobalSet::class, $set);
    $set->title('Podcast Speaker Mappings');
    $set->sites(['default' => null]);
    $set->save();
    $variables = $set->makeLocalization('default');
    Assert::assertInstanceOf(Variables::class, $variables);
    $variables->data(['mappings' => []]);
    $variables->save();

    foreach ([
        'person-hopkins' => 'robert-h-hopkins-jr',
        'person-richter' => 'gresham-t-richter',
        'person-vaughn' => 'lexie-h-vaughn',
        'person-rives' => 'tyler-rives',
    ] as $id => $slug) {
        $person = newHealthTalkEntry();
        $person->collection('people');
        $person->locale('default');
        $person->id($id);
        $person->slug($slug);
        $person->data(['title' => $slug]);
        $person->saveQuietly();
    }

    // The cache is the worker's shared store, so a previous test's report or lock would leak in
    Cache::forget(HealthTalkEpisodeSync::LAST_REPORT_CACHE_KEY);
    Cache::forget(HealthTalkEpisodeSync::LAST_COMPLETED_REPORT_CACHE_KEY);
    Cache::lock(HealthTalkEpisodeSync::LOCK_KEY)->forceRelease();

    fakeHealthTalk('');
    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        if (HealthTalkFakeSource::$failure instanceof Throwable) {
            throw HealthTalkFakeSource::$failure;
        }

        if ($request->url() === HEALTH_TALK_FEED_URL) {
            return Http::response(HealthTalkFakeSource::$feed, HealthTalkFakeSource::$feedStatus);
        }

        if (preg_match('~/client/uams/item/(\d+)$~', $request->url(), $matches) === 1) {
            if (in_array($matches[1], HealthTalkFakeSource::$failingPages, true)) {
                return Http::response('error', 500);
            }

            if (isset(HealthTalkFakeSource::$pages[$matches[1]])) {
                return Http::response(HealthTalkFakeSource::$pages[$matches[1]]);
            }

            $path = HEALTH_TALK_FIXTURES.'/item-'.$matches[1].'.html';

            return is_file($path) ? Http::response((string) file_get_contents($path)) : Http::response('', 404);
        }

        return Http::response('', 404);
    });
});

/**
 * The fixture feed, keeping only the named items, each optionally edited.
 *
 * @param  list<string>  $keep
 * @param  (Closure(DOMElement, string): void)|null  $edit  Called with each kept `<item>` and its id
 */
function healthTalkFeed(array $keep = HEALTH_TALK_ALL_IDS, ?Closure $edit = null): string
{
    $document = new DOMDocument;
    $document->preserveWhiteSpace = true;
    $loaded = $document->load(HEALTH_TALK_FIXTURES.'/feed.xml', LIBXML_NONET);
    Assert::assertTrue($loaded);

    foreach (iterator_to_array($document->getElementsByTagName('item')) as $item) {
        $id = trim((string) $item->getElementsByTagName('customid')->item(0)?->textContent);

        if (! in_array($id, $keep, true)) {
            $item->parentNode?->removeChild($item);

            continue;
        }

        if ($edit instanceof Closure) {
            $edit($item, $id);
        }
    }

    $xml = $document->saveXML();
    Assert::assertIsString($xml);

    return $xml;
}

/**
 * Serve a feed body and the fixture item pages; everything else is a 404.
 *
 * @param  list<string>  $failingPages  Item ids whose page returns a 500
 * @param  array<int|string, string>  $pages  Item page bodies that replace the fixture
 */
function fakeHealthTalk(string $feed, int $feedStatus = 200, array $failingPages = [], array $pages = [], ?Throwable $failure = null): void
{
    HealthTalkFakeSource::$feed = $feed;
    HealthTalkFakeSource::$feedStatus = $feedStatus;
    HealthTalkFakeSource::$failingPages = $failingPages;
    HealthTalkFakeSource::$pages = $pages;
    HealthTalkFakeSource::$failure = $failure;
}

/**
 * Item-page requests sent so far.
 */
function healthTalkPageRequests(): int
{
    return Http::recorded(static fn (Request $request): bool => str_contains($request->url(), '/client/uams/item/'))->count();
}

/**
 * Source ids from a report map, whose numeric keys PHP stores as integers.
 *
 * @param  array<array-key, mixed>  $map
 * @return list<string>
 */
function healthTalkIds(array $map): array
{
    return array_map('strval', array_keys($map));
}

function runHealthTalkSync(): HealthTalkSyncReport
{
    return app(HealthTalkEpisodeSync::class)->run();
}

/**
 * A saved collection on one site.
 */
function saveHealthTalkCollection(string $handle, string $site, bool $dated): void
{
    $collection = Collection::make($handle);
    Assert::assertInstanceOf(StatamicCollection::class, $collection);
    $collection->sites([$site]);
    $collection->dated($dated);
    $collection->save();
}

function newHealthTalkEntry(): StatamicEntry
{
    $entry = Entry::make();
    Assert::assertInstanceOf(StatamicEntry::class, $entry);

    return $entry;
}

/**
 * A query over the episodes.
 */
function healthTalkQuery(): EntryQueryBuilder
{
    $query = Entry::query();
    Assert::assertInstanceOf(EntryQueryBuilder::class, $query);
    $query->where('collection', 'clinical_resources');

    return $query;
}

/**
 * The entry for a source id, or null.
 */
function healthTalkEntry(string $sourceId): ?StatamicEntry
{
    $query = healthTalkQuery();
    $query->where('source_item_id', $sourceId);
    $entry = $query->first();

    return $entry instanceof StatamicEntry ? $entry : null;
}

/**
 * The entry for a source id, failing the test when there is none.
 */
function healthTalkRequiredEntry(string $sourceId): StatamicEntry
{
    $entry = healthTalkEntry($sourceId);
    Assert::assertInstanceOf(StatamicEntry::class, $entry, "No entry for source item {$sourceId}.");

    return $entry;
}

function healthTalkEntryCount(): int
{
    return healthTalkCount(healthTalkQuery());
}

function healthTalkUnpublishedCount(): int
{
    $query = healthTalkQuery();
    $query->where('published', false);

    return healthTalkCount($query);
}

function healthTalkCount(EntryQueryBuilder $query): int
{
    $count = $query->count();
    Assert::assertIsInt($count);

    return $count;
}

/**
 * The segment rows on an entry.
 *
 * @return list<array<mixed>>
 */
function healthTalkSegments(StatamicEntry $entry): array
{
    $segments = $entry->get('segments');

    return is_array($segments) ? array_values(array_filter($segments, 'is_array')) : [];
}

/**
 * Replace the text of an item's first child element with this name.
 */
function setHealthTalkItemText(DOMElement $item, string $tag, string $text, ?string $namespace = null): void
{
    $element = $namespace === null
        ? $item->getElementsByTagName($tag)->item(0)
        : $item->getElementsByTagNameNS($namespace, $tag)->item(0);
    Assert::assertInstanceOf(DOMElement::class, $element);
    $element->textContent = $text;
}

/**
 * The logger the sync writes to in these tests.
 */
function healthTalkLogger(): HealthTalkRecordingLogger
{
    $logger = app(LoggerInterface::class);
    Assert::assertInstanceOf(HealthTalkRecordingLogger::class, $logger);

    return $logger;
}

/**
 * The mapping global's values.
 */
function healthTalkMappingVariables(): Variables
{
    $variables = GlobalSet::findByHandle('podcast_speaker_mappings')?->in('default');
    Assert::assertInstanceOf(Variables::class, $variables);

    return $variables;
}

/**
 * Replace the mapping rows.
 *
 * @param  array<string, string|null>  $rows  Speaker to a `people` id, or null for "no entry expected"
 */
function mapHealthTalkSpeakers(array $rows): void
{
    $variables = healthTalkMappingVariables();

    $stored = [];
    foreach ($rows as $speaker => $person) {
        $stored[] = [
            'id' => substr(md5($speaker), 0, 8),
            'type' => 'speaker_mapping',
            'enabled' => true,
            'speaker' => $speaker,
            'disposition' => $person === null ? HealthTalkSpeakerMappings::NO_ENTRY : HealthTalkSpeakerMappings::PERSON,
            'person' => $person === null ? null : [$person],
        ];
    }

    $variables->set('mappings', $stored);
    $variables->save();
}

/**
 * The stored mapping rows.
 *
 * @return list<array<mixed>>
 */
function healthTalkMappingRows(): array
{
    $rows = healthTalkMappingVariables()->get('mappings');

    return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
}

/**
 * The text of a stored ProseMirror value, flattened.
 */
function healthTalkText(mixed $nodes): string
{
    $text = '';

    foreach (is_array($nodes) ? $nodes : [] as $node) {
        if (! is_array($node)) {
            continue;
        }

        $text .= is_string($node['text'] ?? null) ? $node['text'] : '';
        $text .= healthTalkText($node['content'] ?? null).' ';
    }

    return $text;
}

/**
 * Every node type in a stored ProseMirror value.
 *
 * @return list<string>
 */
function healthTalkNodeTypes(mixed $nodes): array
{
    $types = [];

    foreach (is_array($nodes) ? $nodes : [] as $node) {
        if (is_array($node)) {
            $types[] = is_string($node['type'] ?? null) ? $node['type'] : '';
            $types = [...$types, ...healthTalkNodeTypes($node['content'] ?? null)];
        }
    }

    return $types;
}

it('imports every episode whose speakers resolve and maps the feed per the spec', function (): void {
    fakeHealthTalk(healthTalkFeed());

    $report = runHealthTalkSync();

    expect($report->completed())->toBeTrue()
        ->and($report->feedItems)->toBe(5)
        ->and($report->created)->toBe(['69402', '69184']);

    $entry = healthTalkRequiredEntry('69402');

    expect($entry->locale())->toBe('health')
        ->and($entry->blueprint() instanceof Blueprint ? $entry->blueprint()->handle() : null)->toBe('audio')
        ->and($entry->published())->toBeTrue()
        ->and($entry->get('series'))->toBe('uams-health-talk')
        ->and($entry->get('episode_number'))->toBe(120)
        ->and($entry->get('season_number'))->toBeNull()
        ->and($entry->get('episode_duration'))->toMatch('/^\d{2}:\d{2}:\d{2}$/')
        ->and($entry->get('podcast_credits'))->toBe(['guest' => ['person-hopkins']])
        ->and($entry->get('source_speaker_names'))->toBe(['Robert Hopkins, M.D.'])
        ->and($entry->date() instanceof CarbonInterface ? $entry->date()->format('Y-m-d') : null)->toBe('2026-08-27');

    // The thumbnail anchor is stripped and no image survives into the show notes
    expect(healthTalkNodeTypes($entry->get('show_notes')))->not->toContain('image')
        ->and(trim(healthTalkText($entry->get('show_notes'))))->not->toBe('')
        ->and(strlen(healthTalkText($entry->get('episode_transcript'))))->toBeGreaterThan(5000);

    $segments = healthTalkSegments($entry);
    expect($segments)->toHaveCount(1)
        ->and($segments[0]['type'] ?? null)->toBe('remote_audio')
        ->and($segments[0]['url'] ?? null)->toBe('https://support.doctorpodcasting.com/download/69402/arkansas_medical_sciences/uams120.mp3');
});

it('defers an episode with any unresolved speaker, creating nothing and a mapping row per speaker', function (): void {
    fakeHealthTalk(healthTalkFeed());

    $report = runHealthTalkSync();

    // The panel is blocked by Tyler Rives even though Lexie Vaughn resolves by slug
    expect(healthTalkIds($report->deferred))->toBe(['68170', '43833', '41530'])
        ->and(array_values($report->deferred)[0])->toBe(['Tyler Rives, M.D'])
        ->and(healthTalkEntry('68170'))->toBeNull()
        ->and(healthTalkEntry('43833'))->toBeNull()
        ->and(healthTalkEntry('41530'))->toBeNull()
        ->and(healthTalkEntryCount())->toBe(2);

    // The raw strings, unaltered, each unmapped -- and none added twice on a re-run
    $speakers = array_column(healthTalkMappingRows(), 'speaker');
    expect($speakers)->toBe(['Tyler Rives, M.D', 'Laura Spradley', 'Joseph Henske, MD'])
        ->and(array_values(array_unique(array_filter(array_column(healthTalkMappingRows(), 'disposition'), 'is_string'))))->toBe([HealthTalkSpeakerMappings::UNMAPPED]);

    runHealthTalkSync();
    expect(healthTalkMappingRows())->toHaveCount(3);
});

it('imports a deferred episode on the next run once its speakers are mapped', function (): void {
    fakeHealthTalk(healthTalkFeed());
    runHealthTalkSync();
    expect(healthTalkEntry('68170'))->toBeNull();

    mapHealthTalkSpeakers(['Tyler Rives, M.D' => 'person-rives']);
    $report = runHealthTalkSync();

    $panel = healthTalkRequiredEntry('68170');
    expect($report->created)->toBe(['68170'])
        ->and($panel->get('podcast_credits'))->toBe(['guest' => ['person-vaughn', 'person-rives']])
        ->and($panel->get('source_speaker_names'))->toBe(['Lexie Vaughn, MD', 'Tyler Rives, M.D']);
});

it('imports a speaker marked no entry expected uncredited, including the episode with no number', function (): void {
    mapHealthTalkSpeakers(['Joseph Henske, MD' => null, 'Laura Spradley' => null]);
    fakeHealthTalk(healthTalkFeed());

    $report = runHealthTalkSync();

    $bioPod = healthTalkRequiredEntry('41530');
    $noTranscript = healthTalkRequiredEntry('43833');

    expect($report->created)->toContain('41530', '43833')
        ->and($bioPod->get('episode_number'))->toBeNull()
        ->and($bioPod->get('podcast_credits'))->toBe(['guest' => []])
        ->and($bioPod->published())->toBeTrue()
        ->and($noTranscript->get('episode_transcript'))->toBeNull()
        ->and($noTranscript->get('episode_number'))->toBe(37);
});

it('creates a newly published episode on the next run and never duplicates on a re-run', function (): void {
    fakeHealthTalk(healthTalkFeed(['69184']));
    runHealthTalkSync();
    expect(healthTalkEntryCount())->toBe(1);

    fakeHealthTalk(healthTalkFeed(['69402', '69184']));
    $report = runHealthTalkSync();

    expect($report->created)->toBe(['69402'])
        ->and($report->updated)->toBe([])
        ->and(healthTalkEntryCount())->toBe(2);

    // An unchanged feed fetches no item page and writes nothing
    $pagesBefore = healthTalkPageRequests();
    $rerun = runHealthTalkSync();
    expect($rerun->created)->toBe([])
        ->and($rerun->updated)->toBe([])
        ->and(healthTalkEntryCount())->toBe(2)
        ->and(healthTalkPageRequests())->toBe($pagesBefore);
});

it('updates changed feed metadata in place without clobbering fields the feed does not supply', function (): void {
    fakeHealthTalk(healthTalkFeed(['69402']));
    runHealthTalkSync();

    $entry = healthTalkRequiredEntry('69402');
    $id = $entry->id();
    $slug = $entry->slug();

    // Editor work: another credit role, a second segment, and a field the feed never sets
    $entry->set('podcast_credits', ['guest' => ['person-hopkins'], 'host' => ['person-richter']]);
    $entry->set('segments', [...healthTalkSegments($entry), ['id' => 'editor01', 'type' => 'youtube', 'enabled' => true, 'video_id' => 'abc']]);
    $entry->set('related_resources_note', 'kept');
    $entry->save();

    fakeHealthTalk(healthTalkFeed(['69402'], function (DOMElement $item): void {
        setHealthTalkItemText($item, 'title', 'A retitled episode');
        setHealthTalkItemText($item, 'duration', '00:12:34', 'http://www.itunes.com/dtds/podcast-1.0.dtd');
    }));
    $report = runHealthTalkSync();

    $updated = healthTalkRequiredEntry('69402');
    expect($report->updated)->toBe(['69402'])
        ->and(healthTalkEntryCount())->toBe(1)
        ->and($updated->id())->toBe($id)
        ->and($updated->slug())->toBe($slug)
        ->and($updated->get('title'))->toBe('A retitled episode')
        ->and($updated->get('episode_duration'))->toBe('00:12:34')
        ->and($updated->get('podcast_credits'))->toBe(['guest' => ['person-hopkins'], 'host' => ['person-richter']])
        ->and($updated->get('related_resources_note'))->toBe('kept')
        ->and(array_column(healthTalkSegments($updated), 'type'))->toBe(['remote_audio', 'youtube']);
});

it('unpublishes a withdrawn episode, keeping it whole, and republishes it when it returns', function (): void {
    fakeHealthTalk(healthTalkFeed(['69402', '69184']));
    runHealthTalkSync();

    fakeHealthTalk(healthTalkFeed(['69184']));
    $report = runHealthTalkSync();

    $withdrawn = healthTalkRequiredEntry('69402');
    $unpublishedLogs = healthTalkLogger()->transitions('unpublished');
    expect($report->unpublished)->toBe(['69402'])
        ->and($withdrawn->published())->toBeFalse()
        ->and($withdrawn->get('podcast_credits'))->toBe(['guest' => ['person-hopkins']])
        ->and(healthTalkText($withdrawn->get('episode_transcript')))->not->toBe('')
        ->and($unpublishedLogs)->toHaveCount(1)
        ->and($unpublishedLogs[0]['context'] ?? [])->toMatchArray([
            'source_item_id' => '69402',
            'title' => $withdrawn->get('title'),
        ]);

    fakeHealthTalk(healthTalkFeed(['69402', '69184']));
    $returned = runHealthTalkSync();

    expect($returned->republished)->toBe(['69402'])
        ->and(healthTalkRequiredEntry('69402')->published())->toBeTrue()
        ->and(healthTalkEntryCount())->toBe(2);
});

it('logs every publication transition with the episode id, title and direction', function (): void {
    fakeHealthTalk(healthTalkFeed(['69402', '69184']));
    runHealthTalkSync();

    $published = healthTalkLogger()->transitions('published');
    expect(array_column(array_column($published, 'context'), 'source_item_id'))->toBe(['69402', '69184']);

    foreach ($published as $record) {
        expect($record['context']['title'])->toBeString()->not->toBe('');
    }
});

it('changes nothing and surfaces an error when the feed cannot be read', function (Closure $fake, string $reason): void {
    fakeHealthTalk(healthTalkFeed(['69402', '69184']));
    runHealthTalkSync();
    $before = Cache::get(HealthTalkEpisodeSync::LAST_COMPLETED_REPORT_CACHE_KEY);

    $fake();
    $report = runHealthTalkSync();

    expect($report->completed())->toBeFalse()
        ->and($report->aborted)->toContain($reason)
        ->and($report->unpublished)->toBe([])
        ->and(healthTalkRequiredEntry('69402')->published())->toBeTrue()
        ->and(healthTalkRequiredEntry('69184')->published())->toBeTrue()
        ->and(Cache::get(HealthTalkEpisodeSync::LAST_REPORT_CACHE_KEY))->toMatchArray(['aborted' => $report->aborted])
        ->and(Cache::get(HealthTalkEpisodeSync::LAST_COMPLETED_REPORT_CACHE_KEY))->toBe($before)
        ->and(array_column(healthTalkLogger()->records, 'level'))->toContain('error');
})->with([
    'a non-200' => [fn () => fakeHealthTalk('', 503), 'HTTP 503'],
    'a zero-item feed' => [fn () => fakeHealthTalk(healthTalkFeed([])), 'zero items'],
    'an unreadable body' => [fn () => fakeHealthTalk('<html>maintenance</html>'), 'not a readable RSS'],
    'a transport failure' => [fn () => fakeHealthTalk('', failure: new ConnectionException('timed out')), 'timed out'],
]);

it('aborts rather than unpublish more episodes than the threshold allows', function (): void {
    mapHealthTalkSpeakers(['Joseph Henske, MD' => null, 'Laura Spradley' => null]);
    fakeHealthTalk(healthTalkFeed());
    runHealthTalkSync();
    config()->set('uams_health_talk.max_unpublish_per_run', 2);

    fakeHealthTalk(healthTalkFeed(['69402']));
    $report = runHealthTalkSync();

    expect($report->completed())->toBeFalse()
        ->and($report->aborted)->toContain('would unpublish 3')
        ->and($report->unpublished)->toBe([])
        ->and(healthTalkUnpublishedCount())->toBe(0);

    // At the threshold it proceeds
    config()->set('uams_health_talk.max_unpublish_per_run', 3);
    expect(runHealthTalkSync()->unpublished)->toHaveCount(3);
});

it('keeps processing the other episodes when one item page fails', function (): void {
    fakeHealthTalk(healthTalkFeed(['69402', '69184']), failingPages: ['69402']);

    $report = runHealthTalkSync();

    expect($report->completed())->toBeTrue()
        ->and(healthTalkIds($report->pageErrors))->toBe(['69402'])
        ->and($report->created)->toBe(['69184'])
        ->and(healthTalkEntry('69402'))->toBeNull();
});

it('never publishes an entry with an unresolved speaker: a changed speaker list holds the existing credits', function (): void {
    fakeHealthTalk(healthTalkFeed(['69402']));
    runHealthTalkSync();

    // The same item, now with a new speaker nobody has mapped, and withdrawn meanwhile
    $entry = healthTalkRequiredEntry('69402');
    $entry->published(false);
    $entry->save();

    $page = (string) file_get_contents(HEALTH_TALK_FIXTURES.'/item-69402.html');
    $page = str_replace('<h6> Robert Hopkins, M.D. </h6>', '<h6> Robert Hopkins, M.D. | Jamie Newcomer </h6>', $page, $replaced);
    expect($replaced)->toBe(1);

    fakeHealthTalk(healthTalkFeed(['69402'], function (DOMElement $item): void {
        setHealthTalkItemText($item, 'title', 'Retitled');
    }), pages: ['69402' => $page]);
    $report = runHealthTalkSync();

    $updated = healthTalkRequiredEntry('69402');
    expect(healthTalkIds($report->heldCredits))->toBe(['69402'])
        ->and(array_values($report->heldCredits))->toBe([['Jamie Newcomer']])
        ->and($report->deferred)->toBe([])
        ->and($report->republished)->toBe([])
        ->and($updated->published())->toBeFalse()
        ->and($updated->get('title'))->toBe('Retitled')
        ->and($updated->get('podcast_credits'))->toBe(['guest' => ['person-hopkins']])
        ->and($updated->get('source_speaker_names'))->toBe(['Robert Hopkins, M.D.', 'Jamie Newcomer'])
        ->and(array_column(healthTalkMappingRows(), 'speaker'))->toBe(['Jamie Newcomer']);

    // Mapped later, the credit and the republish arrive without the page being fetched again
    mapHealthTalkSpeakers(['Jamie Newcomer' => 'person-richter']);
    $pagesBefore = healthTalkPageRequests();
    $mapped = runHealthTalkSync();

    $credited = healthTalkRequiredEntry('69402');
    expect($mapped->heldCredits)->toBe([])
        ->and($mapped->republished)->toBe(['69402'])
        ->and($credited->published())->toBeTrue()
        ->and($credited->get('podcast_credits'))->toBe(['guest' => ['person-hopkins', 'person-richter']])
        ->and(healthTalkPageRequests())->toBe($pagesBefore);
});

it('re-resolves an existing entry when its mapping changes, without refetching the page', function (): void {
    mapHealthTalkSpeakers(['Robert Hopkins, M.D.' => 'person-vaughn']);
    fakeHealthTalk(healthTalkFeed(['69402']));
    runHealthTalkSync();
    expect(healthTalkRequiredEntry('69402')->get('podcast_credits'))->toBe(['guest' => ['person-vaughn']]);

    mapHealthTalkSpeakers(['Robert Hopkins, M.D.' => 'person-rives']);
    $pagesBefore = healthTalkPageRequests();
    $report = runHealthTalkSync();

    expect($report->updated)->toBe(['69402'])
        ->and(healthTalkRequiredEntry('69402')->get('podcast_credits'))->toBe(['guest' => ['person-rives']])
        ->and(healthTalkPageRequests())->toBe($pagesBefore);

    // Back to the slug once the row says nobody, which credits no one
    mapHealthTalkSpeakers(['Robert Hopkins, M.D.' => null]);
    runHealthTalkSync();
    expect(healthTalkRequiredEntry('69402')->get('podcast_credits'))->toBe(['guest' => []]);
});

it('changes nothing when the speaker mapping global set is missing', function (): void {
    fakeHealthTalk(healthTalkFeed());
    $set = GlobalSet::findByHandle('podcast_speaker_mappings');
    Assert::assertInstanceOf(StatamicGlobalSet::class, $set);
    $set->delete();

    $report = runHealthTalkSync();

    expect($report->completed())->toBeFalse()
        ->and($report->aborted)->toContain('speaker mapping global set does not exist')
        ->and(healthTalkEntryCount())->toBe(0);
});

it('refuses to start while another run holds the lock', function (): void {
    fakeHealthTalk(healthTalkFeed(['69402']));
    $lock = Cache::lock(HealthTalkEpisodeSync::LOCK_KEY, 60);
    expect($lock->get())->toBeTrue();

    $report = runHealthTalkSync();
    $lock->release();

    expect($report->completed())->toBeFalse()
        ->and($report->aborted)->toContain('already running')
        ->and(healthTalkEntryCount())->toBe(0)
        ->and(Cache::get(HealthTalkEpisodeSync::LAST_REPORT_CACHE_KEY))->toBeNull();
    Http::assertNothingSent();
});

it('drops link targets outside http, https and mailto from converted show notes', function (): void {
    fakeHealthTalk(healthTalkFeed(['69402'], function (DOMElement $item): void {
        // The conversion itself already drops `javascript:` and `data:`; these it keeps, so only the scheme filter removes them
        setHealthTalkItemText($item, 'description', '<p>Hear <a href="//evil.test/x">this</a>, <a href="statamic://entry::abc">that</a>, <a href="tel:5015550100">call</a> and <a href="https://uamshealth.com/">read</a>.<img src="https://x.test/a.png"><script>alert(1)</script></p>');
    }));

    runHealthTalkSync();

    $notes = json_encode(healthTalkRequiredEntry('69402')->get('show_notes'));
    expect($notes)->toContain('https:\/\/uamshealth.com\/')
        ->toContain('this')
        ->not->toContain('evil.test')
        ->not->toContain('statamic:')
        ->not->toContain('tel:')
        ->not->toContain('x.test')
        ->not->toContain('alert');
});

it('holds converted HTML to the node types each field can edit', function (): void {
    fakeHealthTalk(healthTalkFeed(['69402'], function (DOMElement $item): void {
        setHealthTalkItemText($item, 'description', '<h1>Top</h1><table><tr><td>Cell text</td></tr></table><p>After <code>x</code></p>');
    }), pages: ['69402' => str_replace(
        '<strong>Transcription:</strong>',
        '<strong>Transcription:</strong><h2>Heading in transcript</h2><ul><li>Listed line</li></ul>',
        (string) file_get_contents(HEALTH_TALK_FIXTURES.'/item-69402.html')
    )]);

    runHealthTalkSync();
    $entry = healthTalkRequiredEntry('69402');

    // Show notes keep headings, raised to h2, and lose the table but not its text
    expect(healthTalkNodeTypes($entry->get('show_notes')))->not->toContain('table', 'tableRow', 'tableCell')
        ->toContain('heading')
        ->and(json_encode($entry->get('show_notes')))->toContain('"level":2')->toContain('Cell text')->not->toContain('"level":1');

    // The transcript's editor has paragraphs only, so everything is unwrapped into them
    expect(array_values(array_unique(healthTalkNodeTypes($entry->get('episode_transcript')))))->toEqualCanonicalizing(['paragraph', 'text', 'hardBreak'])
        ->and(healthTalkText($entry->get('episode_transcript')))->toContain('Listed line');
});

/**
 * A saved Control Panel user, super or with no permissions at all.
 */
function healthTalkUser(bool $super): FileUser
{
    $user = User::make();
    Assert::assertInstanceOf(FileUser::class, $user);
    $user->email($super ? 'health-talk-super@example.test' : 'health-talk-editor@example.test');

    if ($super) {
        $user->makeSuper();
    }

    $user->save();

    return $user;
}

/**
 * The widget as rendered for the current user.
 */
function healthTalkWidgetHtml(): string
{
    $html = (new HealthTalkSync)->html();

    return $html instanceof IlluminateView ? $html->render() : $html;
}

it('runs from the console, exiting non-zero when the run changed nothing', function (): void {
    fakeHealthTalk(healthTalkFeed(['69402']));
    expect(Artisan::call('podcasts:sync-health-talk'))->toBe(0)
        ->and(Artisan::output())->toContain('1 created');

    fakeHealthTalk('', 503);
    expect(Artisan::call('podcasts:sync-health-talk'))->toBe(1)
        ->and(Artisan::output())->toContain('HTTP 503');
});

it('is registered on the schedule, daily, from its configured cron', function (): void {
    $events = collect(app(Schedule::class)->events())
        ->filter(static fn (Event $event): bool => $event->description === SyncHealthTalkEpisodes::class);

    expect($events)->toHaveCount(1)
        ->and($events->first()?->expression)->toBe(config()->string('uams_health_talk.schedule.cron'))
        ->and(config('uams_health_talk.schedule.cron'))->toBe('30 4 * * *');
});

it('reports the deferred episodes and unmapped speakers on the dashboard widget', function (): void {
    actingAs(healthTalkUser(super: true));

    // Before any run: its own state, never "nothing deferred"
    expect(healthTalkWidgetHtml())->toContain('data-health-talk-state="not-run"')
        ->not->toContain('data-health-talk-state="deferred"');

    fakeHealthTalk(healthTalkFeed());
    runHealthTalkSync();
    $html = healthTalkWidgetHtml();

    expect($html)->toContain('3 episodes deferred on 3 unmapped speakers')
        ->toContain('Tyler Rives, M.D')
        ->toContain('Laura Spradley')
        ->toContain('Joseph Henske, MD')
        ->toContain('/cp/globals/podcast_speaker_mappings')
        ->toContain('data-health-talk-sync-now');

    // A failed run is named, and the last completed backlog still shows
    fakeHealthTalk('', 503);
    runHealthTalkSync();
    expect(healthTalkWidgetHtml())->toContain('data-health-talk-state="failed"')
        ->toContain('HTTP 503')
        ->toContain('3 episodes deferred');
});

it('keeps a vendor speaker string inert in the widget, which the dashboard compiles as a Vue template', function (): void {
    actingAs(healthTalkUser(super: true));

    // An unlinked speaker, so the string becomes a mapping row the widget lists
    $page = str_replace(
        'Joseph Henske, MD',
        'Dr X {{ $emit.constructor(1)() }}',
        (string) file_get_contents(HEALTH_TALK_FIXTURES.'/item-41530.html'),
        $replaced
    );
    expect($replaced)->toBeGreaterThan(0);
    fakeHealthTalk(healthTalkFeed(['41530']), pages: ['41530' => $page]);
    runHealthTalkSync();

    $html = healthTalkWidgetHtml();

    // `v-pre` on the root stops Vue compiling anything inside it, the speaker list included
    expect(preg_match('/^\s*<div\s+v-pre\b/', $html))->toBe(1)
        ->and($html)->toContain('Dr X {{ $emit.constructor(1)() }}');
});

it('hides the widget and refuses the sync action for a user without the permission', function (): void {
    actingAs(healthTalkUser(super: false));
    Bus::fake();

    // Only Control Panel access, so the refusal below is this action's own check
    Gate::before(static fn (mixed $user, string $ability): ?bool => $ability === 'access cp' ? true : null);

    expect(healthTalkWidgetHtml())->toBe('');

    $url = cp_route('health-talk.sync');
    Assert::assertIsString($url);
    // The Control Panel turns a failed authorize() into a redirect carrying this error
    post($url)->assertRedirect()->assertSessionHas('error', 'This action is unauthorized.');
    Bus::assertNothingDispatched();
});

it('starts the same sync job from the Control Panel action', function (): void {
    actingAs(healthTalkUser(super: true));
    Bus::fake();

    $url = cp_route('health-talk.sync');
    Assert::assertIsString($url);
    post($url)->assertRedirect();

    Bus::assertDispatchedAfterResponse(SyncHealthTalkEpisodes::class);
});
