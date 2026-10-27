<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaOutputService;
use Illuminate\Http\Request;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Facades\Blink;
use Statamic\Facades\Entry;
use Statamic\Facades\Term;
use Statamic\Facades\YAML;
use Statamic\Stache\Query\EntryQueryBuilder;
use Statamic\Taxonomies\LocalizedTerm;
use Statamic\Taxonomies\Term as TaxonomyTerm;
use Tests\TestCase;

/**
 * UAMS-Web/uams-statamic-archive#1487 / UAMS-Web/uams-statamic-archive#1611 — an audio entry (`posts/audio` or `clinical_resources/audio`) whose selected
 * `series` (`audio_series`) term declares the `podcast` subtype must model its own content node
 * as a `PodcastEpisode`: co-typed alongside the declared `Article`, linked to a `PodcastSeries`
 * node via `partOfSeries`, and carrying the `episodeNumber`. A non-podcast recording on the same
 * blueprint — no series, or a *radio*-subtype series — must NOT emit `PodcastEpisode`.
 *
 * Podcast was folded from the parallel `podcast_series` taxonomy into an `audio_series` `subtype`
 * (UAMS-Web/uams-statamic-archive#1611); the podcast gates now read a PHP-computed `is_podcast_episode` (the entry's series term's
 * subtype, resolved via `Term::find`). That resolution is independent of terms-field augmentation,
 * so it holds under these synthetic in-memory fixtures — the `uams-health-talk` (podcast) and
 * `heres-to-your-health` (radio) `audio_series` terms are real migrated content the gate resolves.
 *
 * @command php artisan test --compact tests/Feature/SchemaJsonLdPodcastEpisodeTest.php
 */
uses(TestCase::class, MockeryPHPUnitIntegration::class);

/** The `posts/audio` Article content node co-typed as PodcastEpisode when its series is a podcast. */
const POSTS_AUDIO_ARTICLE_NODE = 'b0b53c78-b13c-4a71-9388-aa68e6adeb93';

/** The shared `clinical_resources` content node co-typed as PodcastEpisode when its series is a podcast. */
const CLINICAL_RESOURCE_CONTENT_NODE = '0f132206-3836-47dd-afe0-df744cd5baea';

/** The PodcastSeries schema_object node backing `partOfSeries`. */
const PODCAST_SERIES_NODE = '33219eb0-5cd6-4131-9065-4c4a419bdd24';

/** The migrated `audio_series` term declaring `subtype: [podcast]`. */
const PODCAST_SERIES_SLUG = 'uams-health-talk';

/** The `audio_series` term declaring `subtype: [radio]` — a non-podcast audio series. */
const RADIO_SERIES_SLUG = 'heres-to-your-health';

/** The `posts/video` content node — its `series` field targets `video_series`, not `audio_series`. */
const POSTS_VIDEO_ARTICLE_NODE = '291a8cc1-8658-42ad-a821-40c48d0819ad';

beforeEach(function (): void {
    useProductionJsonLdFragmentCacheRegime();
    app()->instance('request', Request::create('http://uams-statamic.test/', 'GET'));
    Blink::flush();

    // Keep a resolved `audio_series` term's `taxonomy()` non-null on the schema render
    // path so the build cannot crash with `sites() on null` (UAMS-Web/uams-statamic-archive#1611); see the helper.
    ensureAudioSeriesTaxonomyRegistered();
});

/**
 * Build the standalone content node for an in-memory audio entry.
 *
 * @param  array<string, mixed>  $data
 * @return array<string, mixed>|null
 */
function buildAudioNode(string $nodeId, string $collection, string $blueprint, array $data): ?array
{
    return app(SchemaOutputService::class)->buildStandaloneSchemaObjectNode(
        $nodeId,
        buildAudioEntry($collection, $blueprint, $data)
    );
}

/**
 * An in-memory audio entry fixture (narrowed off the contract-typed `Entry::make()`), so a test
 * can build more than one schema_object node from the same entry context.
 *
 * @param  array<string, mixed>  $data
 */
function buildAudioEntry(string $collection, string $blueprint, array $data): StatamicEntry
{
    $entry = Entry::make();

    if (! $entry instanceof StatamicEntry) {
        throw new RuntimeException('Entry::make() did not return a concrete entry');
    }

    // Set as statements (the fluent setters return an untyped `$this`, losing the narrowed type).
    $entry->collection($collection);
    $entry->blueprint($blueprint);
    $entry->locale('health');
    $entry->id(is_string($data['id'] ?? null) ? $data['id'] : 'fixture-audio');
    $entry->slug(is_string($data['slug'] ?? null) ? $data['slug'] : 'fixture-audio');
    $entry->data($data);

    return $entry;
}

/**
 * The node's `@type` flattened to a list of strings (a node's @type may be a single string).
 *
 * @param  array<string, mixed>|null  $node
 * @return list<string>
 */
function nodeTypeList(?array $node): array
{
    $type = $node['@type'] ?? null;

    return array_values(array_filter(
        is_array($type) ? $type : [$type],
        static fn ($t): bool => is_string($t),
    ));
}

/**
 * Unwrap a possibly-listed property to its single node.
 *
 * @param  array<string, mixed>|null  $node
 * @return array<array-key, mixed>|null
 */
function singleRef(?array $node, string $property): ?array
{
    $value = $node[$property] ?? null;
    $value = is_array($value) && array_is_list($value) ? ($value[0] ?? null) : $value;

    return is_array($value) ? $value : null;
}

/**
 * Safely walk a key path into parsed-YAML data (whose nested types are `mixed`).
 *
 * @param  list<int|string>  $path
 */
function yamlDig(mixed $data, array $path): mixed
{
    foreach ($path as $key) {
        if (! is_array($data) || ! array_key_exists($key, $data)) {
            return null;
        }

        $data = $data[$key];
    }

    return $data;
}

/**
 * The Antlers code string of a schema_object property's first custom value row.
 *
 * @param  mixed  $properties  Parsed `schema.properties` (nested YAML, `mixed`)
 */
function schemaPropertyCode(mixed $properties, string $property): string
{
    $code = yamlDig($properties, [$property, 'value', 0, 'value', 'code']);

    return is_string($code) ? $code : '';
}

/**
 * The `mode` of a schema_object property (e.g. `custom`).
 *
 * @param  mixed  $properties  Parsed `schema.properties` (nested YAML, `mixed`)
 */
function schemaPropertyMode(mixed $properties, string $property): mixed
{
    return yamlDig($properties, [$property, 'mode']);
}

/**
 * Parse a schema_object template file's `schema.properties`.
 */
function schemaProperties(string $file): mixed
{
    $parsed = YAML::file(__DIR__.'/../../content/collections/schema_objects/default/'.$file)->parse();

    return yamlDig($parsed, ['schema', 'properties']);
}

it('co-types a posts/audio podcast episode as PodcastEpisode with partOfSeries and episodeNumber (#1487, #1611)', function () {
    $node = buildAudioNode(POSTS_AUDIO_ARTICLE_NODE, 'posts', 'audio', [
        'series' => [PODCAST_SERIES_SLUG],
        'episode_number' => 42,
    ]);

    // The node advertises both its declared Article type and PodcastEpisode.
    expect(nodeTypeList($node))->toContain('Article')->toContain('PodcastEpisode');

    // partOfSeries points at a PodcastSeries node backed by the migrated audio_series term.
    $series = singleRef($node, 'partOfSeries');
    expect($series)->not->toBeNull();
    expect($series['@type'] ?? null)->toBe('PodcastSeries');
    expect($series['name'] ?? null)->toBe('UAMS Health Talk');

    // episodeNumber now hydrates behaviorally: the `is_podcast_episode` gate is PHP-computed
    // (via Term::find), so it opens under a synthetic entry where terms-field augmentation does not.
    expect($node['episodeNumber'] ?? null)->toBe('42');
});

it('does not emit PodcastEpisode for a non-podcast posts/audio recording — no series (#1487, #1611)', function () {
    $node = buildAudioNode(POSTS_AUDIO_ARTICLE_NODE, 'posts', 'audio', [
        'title' => 'A standalone recording',
        // A stray episode_number with no series (reachable via import / direct YAML) must NOT
        // leak episodeNumber onto a plain Article — it shares the is_podcast_episode gate.
        'episode_number' => 99,
    ]);

    expect(nodeTypeList($node))->toContain('Article');
    expect(in_array('PodcastEpisode', nodeTypeList($node), true))->toBeFalse();
    expect($node['partOfSeries'] ?? null)->toBeNull();
    expect($node['episodeNumber'] ?? null)->toBeNull();
});

it('does not emit PodcastEpisode for a radio-subtype audio series (#1611)', function () {
    // The subtype gate is stricter than mere series-presence: a `radio`-subtype series is a
    // genuine audio series but NOT a podcast, so no podcast schema may leak onto it.
    $node = buildAudioNode(POSTS_AUDIO_ARTICLE_NODE, 'posts', 'audio', [
        'series' => [RADIO_SERIES_SLUG],
        'episode_number' => 7,
    ]);

    expect(nodeTypeList($node))->toContain('Article');
    expect(in_array('PodcastEpisode', nodeTypeList($node), true))->toBeFalse();
    expect($node['partOfSeries'] ?? null)->toBeNull();
    expect($node['episodeNumber'] ?? null)->toBeNull();
});

it('does not co-type a video entry as PodcastEpisode even on a series slug collision (#1611)', function () {
    // The shared `series` field targets `video_series` on the video blueprints; a slug that collides
    // with a podcast `audio_series` term must not leak `PodcastEpisode` onto a video content node.
    $node = buildAudioNode(POSTS_VIDEO_ARTICLE_NODE, 'posts', 'video', [
        'series' => [PODCAST_SERIES_SLUG],
        'episode_number' => 42,
    ]);

    expect(nodeTypeList($node))->toContain('Article');
    expect(in_array('PodcastEpisode', nodeTypeList($node), true))->toBeFalse();
    expect($node['partOfSeries'] ?? null)->toBeNull();
});

it('co-types a clinical_resources/audio podcast episode as PodcastEpisode (#1487, #1611)', function () {
    $node = buildAudioNode(CLINICAL_RESOURCE_CONTENT_NODE, 'clinical_resources', 'audio', [
        'series' => [PODCAST_SERIES_SLUG],
        'episode_number' => 7,
    ]);

    expect(nodeTypeList($node))->toContain('Article')->toContain('PodcastEpisode');

    $series = singleRef($node, 'partOfSeries');
    expect($series['@type'] ?? null)->toBe('PodcastSeries');
    expect($node['episodeNumber'] ?? null)->toBe('7');
});

it('does not emit PodcastEpisode for a non-podcast clinical_resources/audio recording (#1487, #1611)', function () {
    $node = buildAudioNode(CLINICAL_RESOURCE_CONTENT_NODE, 'clinical_resources', 'audio', [
        'title' => 'A standalone clinical recording',
        'episode_number' => 99,
    ]);

    expect(nodeTypeList($node))->toContain('Article');
    expect(in_array('PodcastEpisode', nodeTypeList($node), true))->toBeFalse();
    expect($node['partOfSeries'] ?? null)->toBeNull();
    expect($node['episodeNumber'] ?? null)->toBeNull();
});

it('wires the podcast episode properties on both audio content nodes (#1487, #1611)', function (string $file) {
    $properties = schemaProperties($file);

    // partOfSeries resolves the entry's `series` (audio_series) term into the PodcastSeries node,
    // gated on the podcast subtype so a radio series never emits it.
    $partOfSeries = schemaPropertyCode($properties, 'partOfSeries');
    expect(schemaPropertyMode($properties, 'partOfSeries'))->toBe('custom');
    expect($partOfSeries)->toContain('if is_podcast_episode')
        ->toContain('current_field="series"')
        ->toContain('direct_scope="audio_series"')
        ->toContain('schema_object="'.PODCAST_SERIES_NODE.'"');

    // episodeNumber emits the episode_number field, gated on is_podcast_episode like its
    // siblings so it never lands on a non-podcast Article.
    $episodeNumber = schemaPropertyCode($properties, 'episodeNumber');
    expect(schemaPropertyMode($properties, 'episodeNumber'))->toBe('custom');
    expect($episodeNumber)->toContain('episode_number')->toContain('if is_podcast_episode');

    // audio fans the podcast segments out as AudioObject(s), gated on is_podcast_episode so a
    // non-podcast recording on the same (clinical) node emits none. Pin the full load-bearing
    // param set, not just `current_field`: dropping `current_field_replicator="true"` empties the
    // fan-out (SchemaThingFromRelationshipResolver skips the replicator-row targets) while a
    // `current_field="segments"`-only assertion would stay green.
    $audio = schemaPropertyCode($properties, 'audio');
    expect(schemaPropertyMode($properties, 'audio'))->toBe('custom');
    expect($audio)->toContain('if is_podcast_episode')
        ->toContain('current_field="segments"')
        ->toContain('current_field_replicator="true"')
        ->toContain('current_field_replicator_relationship_field="file"');
})->with([
    'posts-collection-audio-blueprint-entries-content-article.md',
    'clinical-resources-collection-clinical-resource-entry-as-content.md',
]);

// ---------------------------------------------------------------------------
// UAMS-Web/uams-statamic-archive#1508 — PodcastEpisode / PodcastSeries schema enrichment: a `partOfSeason`
// PodcastSeason node, the series `about` (subject-matter), and `associatedMedia`
// alongside `audio` — all now gated on the `is_podcast_episode` subtype signal.
// ---------------------------------------------------------------------------

/** The `PodcastSeason` schema_object the episode's `partOfSeason` renders via `schema_template_node`. */
const PODCAST_SEASON_NODE = '0450176b-ed1f-422b-9ed0-2e1a00c3dbf3';

/** The `AudioObject` schema_object the segment fan-out (`audio`/`associatedMedia`/`hasPart`) resolves. */
const AUDIO_SEGMENT_NODE = 'e25beb79-29b2-465f-94f8-1ca5a4d9994a';

/** The DefinedTerm schema_object backing subject-matter `about`. */
const SUBJECT_MATTER_DEFINED_TERM_NODE = 'f15f2efa-258e-4b16-aa4e-9010bce7514c';

it('models season_number as a PodcastSeason node back-linked to the series @id (#1508, #1611)', function (string $collection, string $blueprint, string $episodeNode) {
    $entry = buildAudioEntry($collection, $blueprint, [
        'series' => [PODCAST_SERIES_SLUG],
        'episode_number' => 42,
        'season_number' => 3,
    ]);

    // The episode's own partOfSeries node @id — the PodcastSeason must reference the same one.
    $episode = app(SchemaOutputService::class)->buildStandaloneSchemaObjectNode($episodeNode, $entry);
    $seriesId = singleRef($episode, 'partOfSeries')['@id'] ?? null;
    expect($seriesId)->not->toBeNull();

    // The PodcastSeason node the partOfSeason property renders (built in the episode context).
    $season = app(SchemaOutputService::class)->buildStandaloneSchemaObjectNode(PODCAST_SEASON_NODE, $entry);

    expect($season['@type'] ?? null)->toBe('PodcastSeason');
    expect($season['seasonNumber'] ?? null)->toBe('3');
    expect(singleRef($season, 'partOfSeries')['@id'] ?? null)->toBe($seriesId);
})->with([
    ['posts', 'audio', POSTS_AUDIO_ARTICLE_NODE],
    ['clinical_resources', 'audio', CLINICAL_RESOURCE_CONTENT_NODE],
]);

it('omits seasonNumber from the PodcastSeason node when the episode has no season_number (#1508, #1611)', function () {
    $entry = buildAudioEntry('posts', 'audio', ['series' => [PODCAST_SERIES_SLUG]]);

    $season = app(SchemaOutputService::class)->buildStandaloneSchemaObjectNode(PODCAST_SEASON_NODE, $entry);

    expect($season['seasonNumber'] ?? null)->toBeNull();
});

it('emits the PodcastSeries about as DefinedTerm subject-matter nodes (#1508, #1611)', function () {
    // A saved fixture audio_series term so the schema_object property Antlers hydrates the term
    // context; `award` is a real subject_matter entry the `about` traversal
    // resolves. Subject matter is a collection since UAMS-Web/uams-statamic-archive#2822, so `about` stores its entry id.
    /** @var EntryQueryBuilder $query */
    $query = Entry::query();
    $query->where('collection', 'subject_matter');
    $query->where('slug', 'award');
    $subject = $query->first();

    if (! $subject instanceof StatamicEntry) {
        throw new RuntimeException('The award subject_matter entry is missing');
    }

    $term = Term::make();

    if (! $term instanceof TaxonomyTerm) {
        throw new RuntimeException('Term::make() did not return a concrete term');
    }

    // Set as statements via the visible `set()` (the fluent `data()` is a magic setter).
    $term->taxonomy('audio_series');
    $term->slug('fixture-audio-series');
    $term->blueprint('audio_series');
    $term->set('title', 'Fixture Series');
    $term->set('subtype', ['podcast']);
    $term->set('about', [$subject->id()]);
    $term->saveQuietly();

    try {
        $localized = $term->in('default');

        if (! $localized instanceof LocalizedTerm) {
            throw new RuntimeException('Term::in() did not return a localized term');
        }

        $node = app(SchemaOutputService::class)->buildStandaloneSchemaObjectNode(
            PODCAST_SERIES_NODE,
            $localized
        );

        $about = singleRef($node, 'about');
        expect($about['@type'] ?? null)->toBe('DefinedTerm');
        expect($about['name'] ?? null)->toBe('award');
    } finally {
        $term->deleteQuietly();
    }
});

it('fans podcast segments out as AudioObject nodes — the fan-out audio/associatedMedia share (#1508, #1611)', function () {
    // `hasPart` is the ungated twin of the podcast-gated `audio` / `associatedMedia`; it resolves
    // the same segment→AudioObject fan-out, so it proves those emit an AudioObject once the
    // is_podcast_episode gate opens (a raw segments row resolves without a stored asset).
    $entry = buildAudioEntry('posts', 'audio', [
        'segments' => [['type' => 'local_file', 'file' => 'audio/segment.mp3']],
    ]);

    $node = app(SchemaOutputService::class)->buildStandaloneSchemaObjectNode(POSTS_AUDIO_ARTICLE_NODE, $entry);

    $part = singleRef($node, 'hasPart');
    expect($part['@type'] ?? null)->toBe('AudioObject');
    expect($part['@id'] ?? null)->toContain(AUDIO_SEGMENT_NODE);

    // Same fixture, no series: the ungated hasPart fan-out fires (segments resolve here), yet the
    // podcast-gated audio / associatedMedia emit nothing — the behavioral "not for a non-podcast
    // recording" half of the gate (non-vacuous because hasPart proves the fan-out itself can fire).
    expect($node['audio'] ?? null)->toBeNull();
    expect($node['associatedMedia'] ?? null)->toBeNull();
});

it('wires associatedMedia + partOfSeason (podcast-gated) on both audio content nodes (#1508, #1611)', function (string $file) {
    $properties = schemaProperties($file);

    // associatedMedia emits the segment AudioObject fan-out beside `audio`, gated on is_podcast_episode.
    // Pin the full load-bearing param set: dropping `current_field_replicator="true"` empties the
    // fan-out at render (the resolver skips the replicator-row targets) while a `current_field`-only
    // assertion stays green.
    $associatedMedia = schemaPropertyCode($properties, 'associatedMedia');
    expect(schemaPropertyMode($properties, 'associatedMedia'))->toBe('custom');
    expect($associatedMedia)->toContain('if is_podcast_episode')
        ->toContain('current_field="segments"')
        ->toContain('current_field_replicator="true"')
        ->toContain('current_field_replicator_relationship_field="file"')
        ->toContain(AUDIO_SEGMENT_NODE);

    // partOfSeason renders the PodcastSeason node, gated on both is_podcast_episode and season_number.
    $partOfSeason = schemaPropertyCode($properties, 'partOfSeason');
    expect(schemaPropertyMode($properties, 'partOfSeason'))->toBe('custom');
    expect($partOfSeason)->toContain('if is_podcast_episode')
        ->toContain('if season_number')
        ->toContain('schema_template_node')
        ->toContain(PODCAST_SEASON_NODE);
})->with([
    'posts-collection-audio-blueprint-entries-content-article.md',
    'clinical-resources-collection-clinical-resource-entry-as-content.md',
]);

it('wires the PodcastSeries about + the PodcastSeason template node onto the series (#1508, #1611)', function () {
    $seriesProps = schemaProperties('podcast-series-taxonomy-term-podcastseries.md');

    // about resolves the series term's subject_matter into DefinedTerm nodes (creativework about pattern).
    $about = schemaPropertyCode($seriesProps, 'about');
    expect(schemaPropertyMode($seriesProps, 'about'))->toBe('custom');
    // Since UAMS-Web/uams-statamic-archive#2823 the field can hold a clinical specialization too, so the scope names both
    expect($about)->toContain('direct_scope="subject_matter|clinical_specializations"')->toContain(SUBJECT_MATTER_DEFINED_TERM_NODE);

    // The PodcastSeason template is a PodcastSeason with seasonNumber + a partOfSeries that now
    // traverses the entry's `series` (audio_series) back to the series node.
    $seasonParsed = YAML::file(__DIR__.'/../../content/collections/schema_objects/default/podcast-season-base-podcastseason.md')->parse();
    $seasonProps = yamlDig($seasonParsed, ['schema', 'properties']);
    $seasonNumber = schemaPropertyCode($seasonProps, 'seasonNumber');
    $partOfSeries = schemaPropertyCode($seasonProps, 'partOfSeries');
    expect(yamlDig($seasonParsed, ['schema', 'type']))->toBe(['PodcastSeason']);
    expect($seasonNumber)->toContain('season_number');
    expect($partOfSeries)->toContain('current_field="series"')
        ->toContain('direct_scope="audio_series"')
        ->toContain(PODCAST_SERIES_NODE);
});
