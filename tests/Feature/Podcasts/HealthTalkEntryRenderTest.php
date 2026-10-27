<?php

declare(strict_types=1);

use App\Services\Podcasts\HealthTalk\HealthTalkEntryFields;
use App\Services\Podcasts\HealthTalk\HealthTalkFeedParser;
use App\Services\Podcasts\HealthTalk\HealthTalkItemPageParser;
use App\Services\SchemaTemplates\SchemaOutputService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use PHPUnit\Framework\Assert;
use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Facades\Blink;
use Statamic\Facades\Entry;
use Statamic\View\View;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;

uses(TestCase::class);

/**
 * UAMS-Web/uams-statamic-archive#1803 — an ingested UAMS Health Talk episode on the real `clinical_resources/audio`
 * blueprint and templates.
 *
 * The entry is built in memory from exactly the values the sync writes, via the
 * same {@see HealthTalkEntryFields}, so nothing is saved into `content/`. Two
 * properties are asserted: the provenance fields never reach the page, and the
 * `uams-health-talk` series switches on the `PodcastEpisode` node through the
 * `subtype: [podcast]` gate (UAMS-Web/uams-statamic-archive#1611).
 *
 * @command php artisan test --compact tests/Feature/Podcasts/HealthTalkEntryRenderTest.php
 */
const HEALTH_TALK_RENDER_FIXTURES = __DIR__.'/../../Fixtures/HealthTalk';

/** The shared `clinical_resources` content node co-typed as PodcastEpisode for a podcast series. */
const HEALTH_TALK_CONTENT_NODE = '0f132206-3836-47dd-afe0-df744cd5baea';

/** Markers the provenance fields carry here, so any leak into the page is unmistakable. */
const HEALTH_TALK_SOURCE_ID_MARKER = 'provenance-source-id-7f3a';
const HEALTH_TALK_SPEAKER_MARKER = 'Provenance Speaker 9c1e, M.D';

beforeEach(function (): void {
    if (! is_file(base_path('content/taxonomies/audio_series/uams-health-talk.yaml'))) {
        ContentSubmodule::reportMissing('the uams-health-talk audio_series term');
    }

    useProductionJsonLdFragmentCacheRegime();
    app()->instance('request', Request::create('http://uams-statamic.test/', 'GET'));
    Blink::flush();
    ensureAudioSeriesTaxonomyRegistered();
});

/**
 * Episode 120 as the sync would write it, with marked provenance.
 */
function ingestedHealthTalkEntry(): StatamicEntry
{
    $items = (new HealthTalkFeedParser('https://support.doctorpodcasting.com/'))
        ->parse((string) file_get_contents(HEALTH_TALK_RENDER_FIXTURES.'/feed.xml'));
    $item = $items[0];
    $page = (new HealthTalkItemPageParser)
        ->parse((string) file_get_contents(HEALTH_TALK_RENDER_FIXTURES.'/item-'.$item->sourceId.'.html'));
    $fields = HealthTalkEntryFields::for('clinical_resources', 'audio');

    $data = [
        ...$fields->feedOwned($item, $page),
        'series' => 'uams-health-talk',
        'segments' => $fields->segments([], $item),
        'podcast_credits' => ['guest' => []],
        'source_item_id' => HEALTH_TALK_SOURCE_ID_MARKER,
        'source_speaker_names' => [HEALTH_TALK_SPEAKER_MARKER],
    ];

    $entry = Entry::make();

    if (! $entry instanceof StatamicEntry) {
        throw new RuntimeException('Entry::make() did not return a concrete entry');
    }

    $entry->collection('clinical_resources');
    $entry->blueprint('audio');
    $entry->locale('health');
    $entry->id('health-talk-render');
    $entry->slug('health-talk-render');
    Assert::assertInstanceOf(CarbonImmutable::class, $item->publishedAt);
    $entry->date($item->publishedAt->toMutable());
    $entry->data($data);

    return $entry;
}

it('renders the episode page with the remote player and without its provenance', function (): void {
    $entry = ingestedHealthTalkEntry();

    $view = new View;
    $view->template('clinical_resources/show');
    $view->layout('layout');
    $view->with($entry->toAugmentedArray());
    $view->cascadeContent($entry);
    $html = $view->render();

    expect($html)->toContain('<audio')
        ->toContain('https://support.doctorpodcasting.com/download/69402/arkansas_medical_sciences/uams120.mp3')
        ->not->toContain(HEALTH_TALK_SOURCE_ID_MARKER)
        ->not->toContain('Provenance Speaker 9c1e');
});

it('models an ingested episode as a PodcastEpisode through the subtype gate', function (): void {
    $node = app(SchemaOutputService::class)->buildStandaloneSchemaObjectNode(
        HEALTH_TALK_CONTENT_NODE,
        ingestedHealthTalkEntry()
    );

    $type = $node['@type'] ?? null;
    $types = is_array($type) ? $type : [$type];

    expect($types)->toContain('PodcastEpisode')
        ->and($node['episodeNumber'] ?? null)->toBe('120')
        ->and(json_encode($node))->not->toContain(HEALTH_TALK_SOURCE_ID_MARKER)
        ->not->toContain('Provenance Speaker 9c1e');
});
