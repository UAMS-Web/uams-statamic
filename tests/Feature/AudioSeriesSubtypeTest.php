<?php

declare(strict_types=1);

use App\Scopes\AudioSeriesBySubtype;
use App\Support\AudioSeries;
use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Facades\Blink;
use Statamic\Facades\Entry;
use Statamic\Facades\Term;
use Statamic\Fields\Value;
use Statamic\Query\Builder;
use Statamic\Taxonomies\LocalizedTerm;
use Statamic\Taxonomies\Term as TaxonomyTerm;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    Blink::flush();

    // Keep a resolved `audio_series` term's `taxonomy()` non-null so `Term::find` /
    // `Term::query` never hit `sites() on null` on the render path (UAMS-Web/uams-statamic-archive#1611); see helper.
    ensureAudioSeriesTaxonomyRegistered();
});

/*
|--------------------------------------------------------------------------
| audio_series `subtype` — query scope + the podcast-detection helper (UAMS-Web/uams-statamic-archive#1611)
|--------------------------------------------------------------------------
|
| Podcast folded into `audio_series` as a multi-select `subtype`. These cover
| the query scope that lists series by subtype and the shared helper that
| resolves "the entry's series is a podcast" behind the schema gate + co-typing resolver.
| Exercised against the real migrated content terms: `uams-health-talk`
| (podcast) and `heres-to-your-health` (radio).
*/

/**
 * The slugs an `audio_series` term query returns after the subtype scope is applied.
 *
 * @return list<string>
 */
function audioSeriesSlugsForSubtype(string $subtype): array
{
    $query = Term::query();
    $query->where('taxonomy', 'audio_series');

    (new AudioSeriesBySubtype)->apply($query, ['queryScopes' => ['audio_series_subtype_'.$subtype]]);

    $results = $query->get();

    $slugs = [];
    foreach (is_iterable($results) ? $results : [] as $term) {
        $slug = $term instanceof LocalizedTerm ? $term->slug() : null;
        if (is_string($slug)) {
            $slugs[] = $slug;
        }
    }

    return $slugs;
}

/**
 * An in-memory audio entry, narrowed off the contract-typed `Entry::make()` facade.
 *
 * @param  array<string, mixed>  $data
 */
function makeAudioEntry(string $collection, string $id, array $data): StatamicEntry
{
    $entry = Entry::make();

    if (! $entry instanceof StatamicEntry) {
        throw new RuntimeException('Entry::make() did not return a concrete entry');
    }

    // Set as statements (the fluent setters return an untyped `$this`, losing the narrowed type).
    $entry->collection($collection);
    $entry->blueprint('audio');
    $entry->locale('health');
    $entry->id($id);
    $entry->slug($id);
    $entry->data($data);

    return $entry;
}

it('returns exactly the audio_series terms whose subtype contains podcast', function () {
    $slugs = audioSeriesSlugsForSubtype('podcast');

    expect($slugs)->toContain('uams-health-talk');
    expect(in_array('heres-to-your-health', $slugs, true))->toBeFalse();
});

it('returns exactly the audio_series terms whose subtype contains radio', function () {
    $slugs = audioSeriesSlugsForSubtype('radio');

    expect($slugs)->toContain('heres-to-your-health');
    expect(in_array('uams-health-talk', $slugs, true))->toBeFalse();
});

it('registers a per-subtype scope alias', function () {
    expect(AudioSeriesBySubtype::aliases())
        ->toContain('audio_series_subtype_podcast')
        ->toContain('audio_series_subtype_radio');
});

it('constrains to the audio_series taxonomy so another taxonomy\'s subtype cannot leak', function () {
    // A same-named `subtype` value stored on a term in a DIFFERENT taxonomy must be excluded —
    // the scope's own taxonomy clause is load-bearing, not redundant with the caller's.
    $other = Term::make();

    if (! $other instanceof TaxonomyTerm) {
        throw new RuntimeException('Term::make() did not return a concrete term');
    }

    $other->taxonomy('video_series');
    $other->slug('fixture-cross-taxonomy');
    $other->blueprint('video_series');
    $other->set('subtype', ['podcast']);
    $other->save();

    try {
        // A bare term query (no pre-applied taxonomy filter) — the scope must add its own.
        $query = Term::query();
        (new AudioSeriesBySubtype)->apply($query, ['queryScopes' => ['audio_series_subtype_podcast']]);

        $slugs = [];
        foreach (is_iterable($results = $query->get()) ? $results : [] as $term) {
            if ($term instanceof LocalizedTerm) {
                $slugs[] = $term->slug();
            }
        }

        expect($slugs)->toContain('uams-health-talk');
        expect(in_array('fixture-cross-taxonomy', $slugs, true))->toBeFalse();
    } finally {
        $other->delete();
    }
});

it('leaves a non-term query untouched instead of erroring', function () {
    // apply() must no-op when handed something that is not a term index Builder.
    $threw = false;

    try {
        (new AudioSeriesBySubtype)->apply('not-a-builder', ['queryScopes' => ['audio_series_subtype_podcast']]);
    } catch (Throwable) {
        $threw = true;
    }

    expect($threw)->toBeFalse();
});

it('reads subtype option keys for an audio_series term id', function () {
    expect(AudioSeries::subtypesForTermId('audio_series::uams-health-talk'))->toBe(['podcast']);
    expect(AudioSeries::subtypesForTermId('audio_series::heres-to-your-health'))->toBe(['radio']);
    // A dangling id (deleted / never-created term) resolves to no subtypes, not an error.
    expect(AudioSeries::subtypesForTermId('audio_series::does-not-exist'))->toBe([]);
});

it('resolves the series subtype across the raw storage shapes a terms field can hold', function () {
    // A `max_items: 1` terms field stores a bare slug string (not a single-element list) —
    // the primary real shape, distinct from the list the other tests pass.
    $bareSlug = makeAudioEntry('posts', 'e-bare', ['series' => 'uams-health-talk']);
    expect(AudioSeries::entrySeriesHasSubtype($bareSlug, AudioSeries::SUBTYPE_PODCAST))->toBeTrue();

    // An augmented context can hand `get()` a `Value` wrapper; the resolver unwraps it.
    $wrapped = makeAudioEntry('posts', 'e-value', ['series' => new Value(['uams-health-talk'], 'series')]);
    expect(AudioSeries::entrySeriesHasSubtype($wrapped, AudioSeries::SUBTYPE_PODCAST))->toBeTrue();

    // A skippable leading empty value must not abort the scan of a later valid slug
    // (the loop continues past it rather than breaking).
    $leadingEmpty = makeAudioEntry('posts', 'e-empty-lead', ['series' => ['', 'uams-health-talk']]);
    expect(AudioSeries::entrySeriesHasSubtype($leadingEmpty, AudioSeries::SUBTYPE_PODCAST))->toBeTrue();
});

it('normalizes a term subtype value — drops empty and non-string options', function () {
    $term = Term::make();

    if (! $term instanceof TaxonomyTerm) {
        throw new RuntimeException('Term::make() did not return a concrete term');
    }

    $term->taxonomy('audio_series');
    $term->slug('fixture-subtype-normalize');
    $term->blueprint('audio_series');
    // A stored list carrying an empty option — exercises the empty/non-string filter,
    // which a clean `['podcast']` never would.
    $term->set('subtype', ['podcast', '']);
    $term->save();

    try {
        expect(AudioSeries::subtypesForTermId('audio_series::fixture-subtype-normalize'))->toBe(['podcast']);
    } finally {
        $term->delete();
    }
});

it('does not treat a video entry\'s shared series field as an audio series (no cross-taxonomy leak)', function () {
    // `series` is shared: on the video collections it targets `video_series`, not `audio_series`.
    // A video entry whose series slug collides with a podcast `audio_series` term must NOT be
    // classified as a podcast (UAMS-Web/uams-statamic-archive#1611 adversarial review — the field is not audio-unique).
    $video = Entry::make();

    if (! $video instanceof StatamicEntry) {
        throw new RuntimeException('Entry::make() did not return a concrete entry');
    }

    $video->collection('posts');
    $video->blueprint('video');
    $video->locale('health');
    $video->id('v-collide');
    $video->slug('v-collide');
    $video->data(['series' => ['uams-health-talk']]);

    expect(AudioSeries::entrySeriesHasSubtype($video, AudioSeries::SUBTYPE_PODCAST))->toBeFalse();
});

it('detects a podcast episode from the entry\'s series term subtype', function (string $collection) {
    $podcastEpisode = makeAudioEntry($collection, 'e1', ['series' => ['uams-health-talk']]);
    $radioRecording = makeAudioEntry($collection, 'e2', ['series' => ['heres-to-your-health']]);
    $standalone = makeAudioEntry($collection, 'e3', []);

    expect(AudioSeries::entrySeriesHasSubtype($podcastEpisode, AudioSeries::SUBTYPE_PODCAST))->toBeTrue();
    expect(AudioSeries::entrySeriesHasSubtype($radioRecording, AudioSeries::SUBTYPE_PODCAST))->toBeFalse();
    expect(AudioSeries::entrySeriesHasSubtype($standalone, AudioSeries::SUBTYPE_PODCAST))->toBeFalse();
})->with([
    'posts' => ['posts'],
    'clinical_resources' => ['clinical_resources'],
]);

// ---------------------------------------------------------------------------
// Reading the requested subtype, against a recording query
// ---------------------------------------------------------------------------

/**
 * Apply the scope to a mock term query and return the constraints it adds.
 *
 * @param  mixed  $values  Scope values.
 * @return list<array<array-key, mixed>>
 */
function audioSubtypeConstraints(mixed $values): array
{
    $recorded = [];

    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('where')->andReturnUsing(function (mixed ...$args) use (&$recorded, $query) {
        $recorded[] = ['where', ...$args];

        return $query;
    });
    $query->shouldReceive('whereJsonContains')->andReturnUsing(function (mixed ...$args) use (&$recorded, $query) {
        $recorded[] = ['whereJsonContains', ...$args];

        return $query;
    });

    (new AudioSeriesBySubtype)->apply($query, $values);

    return $recorded;
}

it('constrains to the taxonomy and the subtype an alias names', function (): void {
    expect(audioSubtypeConstraints(['queryScopes' => ['audio_series_subtype_radio']]))->toBe([
        ['where', 'taxonomy', 'audio_series'],
        ['whereJsonContains', 'subtype', 'radio'],
    ]);
});

it('reads past scope handles that are not its alias to find one that is', function (): void {
    expect(audioSubtypeConstraints(['queryScopes' => [
        42,
        'assets_by_file_type_image_jpeg',
        'audio_series_subtype_television',
        'audio_series_subtype_podcast',
    ]]))->toBe([
        ['where', 'taxonomy', 'audio_series'],
        ['whereJsonContains', 'subtype', 'podcast'],
    ]);
});

it('falls back to a bare audio_series_subtype parameter', function (): void {
    expect(audioSubtypeConstraints(['audio_series_subtype' => 'radio']))->toBe([
        ['where', 'taxonomy', 'audio_series'],
        ['whereJsonContains', 'subtype', 'radio'],
    ]);
});

it('prefers an alias to the bare parameter', function (): void {
    expect(audioSubtypeConstraints([
        'queryScopes' => ['audio_series_subtype_podcast'],
        'audio_series_subtype' => 'radio',
    ]))->toBe([
        ['where', 'taxonomy', 'audio_series'],
        ['whereJsonContains', 'subtype', 'podcast'],
    ]);
});

it('adds nothing when no known subtype is requested', function (mixed $values): void {
    expect(audioSubtypeConstraints($values))->toBe([]);
})->with([
    'no request' => [[]],
    'an unknown alias' => [['queryScopes' => ['audio_series_subtype_television']]],
    'an unknown bare value' => [['audio_series_subtype' => 'television']],
    'a bare value that is not text' => [['audio_series_subtype' => ['radio']]],
    'values that are not a list' => ['audio_series_subtype_radio'],
]);
