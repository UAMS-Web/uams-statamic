<?php

declare(strict_types=1);

use App\Services\Posts\PostsReadingMetricsService;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Assets\Asset as StatamicAsset;
use Statamic\Entries\Entry;
use Statamic\Facades\Asset;
use Statamic\Facades\Blink;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Term;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;
use Statamic\Fields\Value;
use Statamic\Globals\GlobalSet as GlobalSetModel;
use Statamic\Globals\Variables;
use Statamic\Sites\Site;
use Statamic\Taxonomies\LocalizedTerm;
use Statamic\Taxonomies\Term as TaxonomyTerm;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Unit tests for `PostsReadingMetricsService`.
 *
 * Covers wiring expected by computed posts fields (`word_count`, `reading_time`)
 * and other callers that resolve the service from the container, plus the
 * boilerplate-sourcing (UAMS-Web/uams-statamic-archive#861) and segment title/excerpt fallback (UAMS-Web/uams-statamic-archive#867)
 * behavior that must match the post Antlers templates.
 *
 * Blink memoizes `totalTextContent` per site + entry id, so flush between
 * examples (shared mock entry ids would otherwise return a stale aggregate).
 *
 * @command  php artisan test --compact tests/Feature/Services/Posts/PostsReadingMetricsServiceTest.php
 */
beforeEach(fn () => Blink::flush());

/**
 * Build a mocked posts Entry returning the supplied raw field values.
 *
 * The `series` field is resolved against a mocked blueprint exposing the
 * `video_series` taxonomy, matching the real single-taxonomy `terms` field.
 *
 * `$options` replaces the parts the cache key and the blueprint lookup read: `id` and
 * `site` (the site handle, which may be non-scalar), and `blueprint` (any value, null
 * included, in place of the mocked blueprint).
 *
 * @param  array<string, mixed>  $data  Raw values keyed by field handle
 * @param  array{id?: mixed, site?: mixed, blueprint?: mixed}  $options  Overrides for the entry's id, site handle and blueprint
 */
function postsMetricsEntry(array $data, array $options = []): Entry
{
    // Mock the entry site used for cache keys and term/global localization
    $site = Mockery::mock(Site::class);
    $site->shouldReceive('handle')->andReturn(array_key_exists('site', $options) ? $options['site'] : 'default');

    // Mock the `series` field config so the taxonomy namespace resolves
    $seriesField = Mockery::mock(Field::class);
    $seriesField->shouldReceive('config')->andReturn(['taxonomies' => ['video_series'], 'max_items' => 1]);

    // Mock the per-post `boilerplate` picker config (the UAMS-Web/uams-statamic-archive#1184 general-boilerplate
    // selection) so its taxonomy namespace resolves to `boilerplates`.
    $boilerplateField = Mockery::mock(Field::class);
    $boilerplateField->shouldReceive('config')->andReturn(['taxonomies' => ['boilerplates'], 'max_items' => 1]);

    // Mock the blueprint to expose the `series` and `boilerplate` term fields
    $blueprint = Mockery::mock(Blueprint::class);
    $blueprint->shouldReceive('field')->with('series')->andReturn($seriesField);
    $blueprint->shouldReceive('field')->with('boilerplate')->andReturn($boilerplateField);

    // Mock the entry, reading raw values from the supplied data map
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('site')->andReturn($site);
    $entry->shouldReceive('id')->andReturn(array_key_exists('id', $options) ? $options['id'] : 'posts-metrics-entry');
    $entry->shouldReceive('blueprint')->andReturn(array_key_exists('blueprint', $options) ? $options['blueprint'] : $blueprint);
    $entry->shouldReceive('get')->andReturnUsing(
        fn (string $key): mixed => $data[$key] ?? null
    );

    return $entry;
}

/**
 * Stub `Term::find()` to return a localized series term with the given boilerplate.
 */
function postsMetricsStubSeriesTerm(string $termId, string $boilerplate): void
{
    $localized = Mockery::mock(LocalizedTerm::class);
    $localized->shouldReceive('value')->with('boilerplate')->andReturn($boilerplate);

    $term = Mockery::mock(TaxonomyTerm::class);
    $term->shouldReceive('in')->with('default')->andReturn($localized);

    Term::shouldReceive('find')->with($termId)->andReturn($term);
}

/**
 * Stub a resolvable `boilerplates` term with the given boilerplate content, so
 * `Term::find("boilerplates::<slug>")` yields a localized term for the default site.
 */
function postsMetricsStubBoilerplateTerm(string $slug, string $content): void
{
    $localized = Mockery::mock(LocalizedTerm::class);
    $localized->shouldReceive('value')->with('boilerplate')->andReturn($content);

    $term = Mockery::mock(TaxonomyTerm::class);
    $term->shouldReceive('in')->with('default')->andReturn($localized);

    Term::shouldReceive('find')->with('boilerplates::'.$slug)->andReturn($term);
}

/**
 * Stub the site default boilerplate: the `uams_multisite_settings` global's
 * `boilerplate` field points at a `boilerplates` term (slug `about`) carrying the
 * given content. Models the UAMS-Web/uams-statamic-archive#1184 term-backed default (the general boilerplate is
 * no longer inline Bard on the global).
 */
function postsMetricsStubGlobalBoilerplate(string $boilerplate): void
{
    $vars = Mockery::mock(Variables::class);
    $vars->shouldReceive('get')->with('boilerplate')->andReturn('about');

    $global = Mockery::mock(GlobalSetModel::class);
    $global->shouldReceive('in')->with('default')->andReturn($vars);

    GlobalSet::shouldReceive('findByHandle')->with('uams_multisite_settings')->andReturn($global);

    postsMetricsStubBoilerplateTerm('about', $boilerplate);
}

/**
 * Stub `Asset::find('audio::<handle>')` to return an asset with the given fields.
 */
function postsMetricsStubAudioAsset(
    string $handle,
    ?string $title,
    ?string $excerpt = null,
    ?string $transcript = null
): void {
    $asset = Mockery::mock(StatamicAsset::class);
    $asset->shouldReceive('get')->with('title')->andReturn($title);
    $asset->shouldReceive('get')->with('excerpt')->andReturn($excerpt);
    $asset->shouldReceive('get')->with('transcript')->andReturn($transcript);

    Asset::shouldReceive('find')->with('audio::'.$handle)->andReturn($asset);
}

it('resolves from the container', function () {
    // Resolve the concrete implementation from the application container
    $service = app(PostsReadingMetricsService::class);

    // Assert Laravel returns the expected service type. Compared by exact class
    // because Larastan resolves the container binding statically, which makes an
    // instanceof over it an assertion that cannot fail.
    expect($service::class)->toBe(PostsReadingMetricsService::class);
});

it('sources series boilerplate from the series term, not the multisite global (#861)', function () {
    // Stub the series term boilerplate (3 words) and the distinct global (4 words)
    postsMetricsStubSeriesTerm('video_series::cardio-series', 'series one two');
    postsMetricsStubGlobalBoilerplate('global four five six');

    // Both toggles on with a content body (3 words) and a selected series
    $entry = postsMetricsEntry([
        'content' => 'alpha beta gamma',
        'series_boilerplate_display_query' => true,
        'boilerplate_query' => true,
        'series' => 'cardio-series',
    ]);

    // content(3) + series term(3) + global once(4) = 10; the bug double-counts
    // the global (and drops the series term) for 11.
    expect(app(PostsReadingMetricsService::class)->wordCount($entry))->toBe(10);
});

it('counts the series term boilerplate when only the series toggle is on (#861)', function () {
    // Stub the series term boilerplate; the global must never be counted here
    postsMetricsStubSeriesTerm('video_series::cardio-series', 'series one two');
    postsMetricsStubGlobalBoilerplate('global four five six');

    // Only the series toggle on
    $entry = postsMetricsEntry([
        'content' => 'alpha beta gamma',
        'series_boilerplate_display_query' => true,
        'boilerplate_query' => false,
        'series' => 'cardio-series',
    ]);

    // content(3) + series term(3) = 6 — sourced from the term, not the global(4)
    expect(app(PostsReadingMetricsService::class)->wordCount($entry))->toBe(6);
});

it('counts the multisite global only when the site toggle is on (#861)', function () {
    // Stub the global; no series selected
    postsMetricsStubGlobalBoilerplate('global four five six');

    // Only the site boilerplate toggle on
    $entry = postsMetricsEntry([
        'content' => 'alpha beta gamma',
        'series_boilerplate_display_query' => false,
        'boilerplate_query' => true,
        'series' => null,
    ]);

    // content(3) + global(4) = 7
    expect(app(PostsReadingMetricsService::class)->wordCount($entry))->toBe(7);
});

it('prefers a per-post boilerplate selection over the site default (#1184)', function () {
    // Site default term (4 words) and a distinct per-post override term (2 words)
    postsMetricsStubGlobalBoilerplate('global four five six');
    postsMetricsStubBoilerplateTerm('nwa', 'northwest arkansas');

    // The site toggle on, with a per-post boilerplate selected
    $entry = postsMetricsEntry([
        'content' => 'alpha beta gamma',
        'series_boilerplate_display_query' => false,
        'boilerplate_query' => true,
        'boilerplate' => 'nwa',
    ]);

    // content(3) + override term(2) = 5 — the site default(4) is never consulted
    expect(app(PostsReadingMetricsService::class)->wordCount($entry))->toBe(5);
});

it('falls back to the site default when a per-post boilerplate points at a deleted term (#1184)', function () {
    // Site default term (4 words); the per-post pick references a since-deleted term
    postsMetricsStubGlobalBoilerplate('global four five six');
    Term::shouldReceive('find')->with('boilerplates::ghost')->andReturn(null);

    $entry = postsMetricsEntry([
        'content' => 'alpha beta gamma',
        'series_boilerplate_display_query' => false,
        'boilerplate_query' => true,
        'boilerplate' => 'ghost',
    ]);

    // content(3) + site default(4) = 7 — the dangling pick falls through to the
    // default, mirroring the template ternary (the deleted term augments to null).
    expect(app(PostsReadingMetricsService::class)->wordCount($entry))->toBe(7);
});

it('contributes no series boilerplate when no series is selected (#861)', function () {
    // Series toggle on but no series selected — matches `series ? ... : ''`
    $entry = postsMetricsEntry([
        'content' => 'alpha beta gamma',
        'series_boilerplate_display_query' => true,
        'boilerplate_query' => false,
        'series' => null,
    ]);

    // content(3) only — the series block resolves to nothing
    expect(app(PostsReadingMetricsService::class)->wordCount($entry))->toBe(3);
});

it('resolves an already-namespaced series term id without the blueprint (#861)', function () {
    // A `taxonomy::slug` value is used as-is (no blueprint taxonomy lookup)
    postsMetricsStubSeriesTerm('video_series::cardio-series', 'series one two');

    $entry = postsMetricsEntry([
        'content' => 'alpha beta gamma',
        'series_boilerplate_display_query' => true,
        'boilerplate_query' => false,
        'series' => 'video_series::cardio-series',
    ]);

    // content(3) + series term(3) = 6
    expect(app(PostsReadingMetricsService::class)->wordCount($entry))->toBe(6);
});

it('unwraps a single-element series list (#861)', function () {
    // Defensive: a list-stored single term still resolves
    postsMetricsStubSeriesTerm('video_series::cardio-series', 'series one two');

    $entry = postsMetricsEntry([
        'content' => 'alpha beta gamma',
        'series_boilerplate_display_query' => true,
        'boilerplate_query' => false,
        'series' => ['cardio-series'],
    ]);

    // content(3) + series term(3) = 6
    expect(app(PostsReadingMetricsService::class)->wordCount($entry))->toBe(6);
});

it('treats an empty-string series as no series (#861)', function () {
    // A cleared series field contributes nothing under the series toggle
    $entry = postsMetricsEntry([
        'content' => 'alpha beta gamma',
        'series_boilerplate_display_query' => true,
        'boilerplate_query' => false,
        'series' => '',
    ]);

    expect(app(PostsReadingMetricsService::class)->wordCount($entry))->toBe(3);
});

it('contributes nothing when the series term cannot be resolved (#861)', function () {
    // A dangling series reference (term deleted) resolves to no boilerplate
    Term::shouldReceive('find')->with('video_series::ghost-series')->andReturnNull();

    $entry = postsMetricsEntry([
        'content' => 'alpha beta gamma',
        'series_boilerplate_display_query' => true,
        'boilerplate_query' => false,
        'series' => 'ghost-series',
    ]);

    expect(app(PostsReadingMetricsService::class)->wordCount($entry))->toBe(3);
});

it('does not let an empty segment title beat a filled override (#867)', function () {
    // Matches the issue: `'' ?? 'override'` must resolve to the override, not ''
    $entry = postsMetricsEntry([
        'segments' => [
            ['title' => '', 'title_override' => 'alpha beta', 'transcript' => null],
            ['title' => 'gamma', 'transcript' => null],
        ],
    ]);

    // Row 1 -> 'alpha beta'(2), Row 2 -> 'gamma'(1) = 3; the bug counts row 1 as 0 -> 1
    expect(app(PostsReadingMetricsService::class)->wordCount($entry))->toBe(3);
});

it('does not let an empty segment excerpt beat a filled override (#867)', function () {
    // Same fallback bug on the excerpt sources
    $entry = postsMetricsEntry([
        'segments' => [
            ['title' => 'one', 'excerpt' => '', 'excerpt_override' => 'delta epsilon', 'transcript' => null],
            ['title' => 'two', 'transcript' => null],
        ],
    ]);

    // titles one(1)+two(1)=2; excerpt row 1 -> 'delta epsilon'(2) = 4; the bug -> 2
    expect(app(PostsReadingMetricsService::class)->wordCount($entry))->toBe(4);
});

it('falls through an empty title_override to the asset title (#867)', function () {
    // The reachable audio case: empty override -> the asset `title` (file:title)
    postsMetricsStubAudioAsset('episode-5', 'Episode Five Recap');

    $entry = postsMetricsEntry([
        'segments' => [
            ['file' => 'episode-5', 'title_override' => '', 'excerpt_override' => '', 'transcript' => null],
            ['title' => 'closing', 'transcript' => null],
        ],
    ]);

    // Row 1 title -> 'Episode Five Recap'(3), Row 2 -> 'closing'(1) = 4; the bug -> 1
    expect(app(PostsReadingMetricsService::class)->wordCount($entry))->toBe(4);
});

it('keeps a whitespace-only override rather than diverting to the asset title (#867)', function () {
    // Antlers `??` keeps a non-empty (incl. whitespace) value, so the asset
    // title is never reached; the whitespace strips to zero words.
    postsMetricsStubAudioAsset('episode-9', 'Should Not Be Counted');

    $entry = postsMetricsEntry([
        'segments' => [
            ['file' => 'episode-9', 'title_override' => '   ', 'transcript' => null],
            ['title' => 'only', 'transcript' => null],
        ],
    ]);

    // Row 1 title -> '   ' kept -> 0 words; Row 2 -> 'only'(1) = 1
    expect(app(PostsReadingMetricsService::class)->wordCount($entry))->toBe(1);
});

it('omits segment title and excerpt for a single-segment post (#867)', function () {
    // The title/excerpt block only runs when there is more than one segment
    $entry = postsMetricsEntry([
        'segments' => [
            ['title' => 'solo title here', 'excerpt' => 'solo excerpt', 'transcript' => 'word'],
        ],
    ]);

    // Only the transcript(1) is counted; the lone segment title/excerpt are skipped
    expect(app(PostsReadingMetricsService::class)->wordCount($entry))->toBe(1);
});

/**
 * A mocked blueprint whose `series` field is configured with the given `taxonomies`
 * value, or which has no `series` field when `$field` is false.
 */
function postsMetricsSeriesBlueprint(mixed $taxonomies, bool $field = true): Blueprint
{
    $blueprint = Mockery::mock(Blueprint::class);

    if (! $field) {
        $blueprint->shouldReceive('field')->with('series')->andReturnNull();

        return $blueprint;
    }

    $seriesField = Mockery::mock(Field::class);
    $seriesField->shouldReceive('config')->andReturn($taxonomies === null ? [] : ['taxonomies' => $taxonomies]);
    $blueprint->shouldReceive('field')->with('series')->andReturn($seriesField);

    return $blueprint;
}

/**
 * Stub `Asset::find($id)` to return an asset carrying the given field values.
 *
 * @param  array<string, mixed>  $fields  Asset field values keyed by handle
 */
function postsMetricsStubAssetById(string $id, array $fields): void
{
    $asset = Mockery::mock(StatamicAsset::class);
    $asset->shouldReceive('get')->andReturnUsing(fn (string $key): mixed => $fields[$key] ?? null);

    Asset::shouldReceive('find')->with($id)->andReturn($asset);
}

it('derives reading time from the same word count, as whole minutes and as their seconds', function (int $words, int $minutes, int $seconds): void {
    $entry = postsMetricsEntry(['content' => trim(str_repeat('word ', $words))]);
    $service = app(PostsReadingMetricsService::class);

    expect($service->wordCount($entry))->toBe($words)
        ->and($service->readingTimeMinutes($entry))->toBe($minutes)
        ->and($service->readingTimeSecondsRoundedUpToWholeMinutes($entry))->toBe($seconds);
})->with([
    'no words' => [0, 0, 0],
    'one word: about 0.3 s, one minute' => [1, 1, 60],
    'exactly 200 words: one minute' => [200, 1, 60],
    '201 words: about 60.3 s, two minutes' => [201, 2, 120],
    '401 words: three minutes' => [401, 3, 180],
]);

it('reads a Bard content value as its plain text', function (): void {
    $entry = postsMetricsEntry(['content' => [
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Bard one two']]],
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'three']]],
    ]]);

    expect(app(PostsReadingMetricsService::class)->totalTextContent($entry))->toBe('Bard one two three');
});

it('joins the parts on single spaces, dropping empty ones and collapsing whitespace runs', function (): void {
    $entry = postsMetricsEntry([
        'content' => "  alpha \n\t beta  ",
        'segments' => [
            ['title' => "  <b>Bold</b>\n  title ", 'transcript' => ''],
            ['title' => 'second', 'transcript' => "  no\u{00A0}  break  "],
        ],
    ]);

    // The empty first transcript adds nothing between the parts, and a no-break space is
    // whitespace too
    expect(app(PostsReadingMetricsService::class)->totalTextContent($entry))->toBe('alpha beta Bold title second no break');
});

it('memoizes the aggregate per site and entry id for the request', function (): void {
    $service = app(PostsReadingMetricsService::class);

    expect($service->totalTextContent(postsMetricsEntry(['content' => 'first text'], ['id' => 'one'])))->toBe('first text')
        // The same site and id reuses the first aggregate, whatever this entry holds
        ->and($service->totalTextContent(postsMetricsEntry(['content' => 'other'], ['id' => 'one'])))->toBe('first text')
        // Another id, or the same id on another site, is aggregated afresh
        ->and($service->totalTextContent(postsMetricsEntry(['content' => 'second id'], ['id' => 'two'])))->toBe('second id')
        ->and($service->totalTextContent(postsMetricsEntry(['content' => 'news site'], ['id' => 'one', 'site' => 'news'])))->toBe('news site');
});

it('separates the site and the id in the memo key', function (): void {
    $service = app(PostsReadingMetricsService::class);

    // `ab` + `c` and `a` + `bc` run together would be one key
    expect($service->totalTextContent(postsMetricsEntry(['content' => 'first'], ['site' => 'ab', 'id' => 'c'])))->toBe('first')
        ->and($service->totalTextContent(postsMetricsEntry(['content' => 'second'], ['site' => 'a', 'id' => 'bc'])))->toBe('second');
});

it('keys a non-scalar site handle or entry id as empty', function (): void {
    $service = app(PostsReadingMetricsService::class);

    // A null id and an empty id share a key, as do a null handle and an empty one
    expect($service->totalTextContent(postsMetricsEntry(['content' => 'null id'], ['id' => null])))->toBe('null id')
        ->and($service->totalTextContent(postsMetricsEntry(['content' => 'empty id'], ['id' => ''])))->toBe('null id')
        ->and($service->totalTextContent(postsMetricsEntry(['content' => 'null site'], ['site' => null, 'id' => 'x'])))->toBe('null site')
        ->and($service->totalTextContent(postsMetricsEntry(['content' => 'empty site'], ['site' => '', 'id' => 'x'])))->toBe('null site');
});

it('returns an empty aggregate when the memo holds something other than a string', function (): void {
    Blink::put('posts_reading_metrics.total_text_content.default.posts-metrics-entry', 42);

    expect(app(PostsReadingMetricsService::class)->totalTextContent(postsMetricsEntry(['content' => 'ignored'])))->toBe('');
});

it('reads the toggles as Statamic booleans', function (mixed $toggle, int $expected): void {
    postsMetricsStubGlobalBoilerplate('global four five six');

    $entry = postsMetricsEntry(['content' => 'alpha beta gamma', 'boilerplate_query' => $toggle]);

    // content(3), plus the site boilerplate(4) when the toggle reads true
    expect(app(PostsReadingMetricsService::class)->wordCount($entry))->toBe($expected);
})->with([
    'true' => [true, 7],
    'int 1' => [1, 7],
    'string 1' => ['1', 7],
    'string true' => ['true', 7],
    'string yes' => ['yes', 7],
    'string on' => ['on', 7],
    'a non-empty list' => [['x'], 7],
    'int 2' => [2, 7],
    'false' => [false, 3],
    'int 0' => [0, 3],
    'string 0' => ['0', 3],
    'null' => [null, 3],
    'empty string' => ['', 3],
    'string false' => ['false', 3],
    'string no' => ['no', 3],
    'an unrecognized string' => ['maybe', 3],
    'an empty list' => [[], 3],
    'float zero' => [0.0, 3],
]);

it('skips segments that are not a non-empty list, rows that are not arrays, and disabled rows', function (mixed $segments, string $expected): void {
    $entry = postsMetricsEntry(['content' => 'body', 'segments' => $segments]);

    expect(app(PostsReadingMetricsService::class)->totalTextContent($entry))->toBe($expected);
})->with([
    'a string' => ['not a list', 'body'],
    'an empty list' => [[], 'body'],
    'keyed rows are read in order' => [['b' => ['title' => 'one', 'transcript' => 'tx1'], 'a' => ['title' => 'two', 'transcript' => 'tx2']], 'body one tx1 two tx2'],
    'a non-array row is not a segment' => [['junk', ['title' => 'solo', 'transcript' => 'only']], 'body only'],
    'a disabled row is skipped but still counted as a segment' => [[['title' => 'off', 'enabled' => false, 'transcript' => 'hidden'], ['title' => 'on', 'enabled' => true, 'transcript' => 'shown']], 'body on shown'],
    'a falsy enabled that is not false keeps the row' => [[['title' => 'kept', 'enabled' => 0, 'transcript' => 'zero'], ['title' => 'next', 'transcript' => 'tx']], 'body kept zero next tx'],
]);

it('unwraps video and audio segment groups into their child rows', function (): void {
    $entry = postsMetricsEntry(['segments' => [
        ['type' => 'new_video_segment_group',
            'youtube' => [['title' => 'yt', 'transcript' => 'y1'], 'junk'],
            'vimeo' => [['title' => 'vm', 'transcript' => 'v1']],
        ],
        // A group with nothing usable contributes no rows
        ['type' => 'new_video_segment_group', 'youtube' => 'not a list'],
        ['type' => 'new_video_segment_group', 'youtube' => [], 'vimeo' => null],
        // An unusable first source does not stop the second from being read
        ['type' => 'new_video_segment_group', 'youtube' => [], 'vimeo' => [['title' => 'v2', 'transcript' => 'w2']]],
        ['type' => 'new_video_segment_group', 'youtube' => 'not a list', 'vimeo' => [['title' => 'v3', 'transcript' => 'w3']]],
        ['type' => 'new_audio_segment_group', 'local_file' => [['title' => 'au', 'transcript' => 'a1'], 7]],
        ['type' => 'new_audio_segment_group', 'local_file' => 'not a list'],
        ['type' => 'new_audio_segment_group'],
        // Any other type is already a leaf row
        ['type' => 'text_segment', 'title' => 'leaf', 'transcript' => 'l1'],
    ]]);

    expect(app(PostsReadingMetricsService::class)->totalTextContent($entry))->toBe('yt y1 vm v1 v2 w2 v3 w3 au a1 leaf l1');
});

it('takes a segment\'s transcript from its asset when it has a file, never from the row', function (array $file, string $expected): void {
    postsMetricsStubAssetById('audio::episode', ['transcript' => 'from the asset']);
    postsMetricsStubAssetById('podcasts::episode', ['transcript' => 'qualified asset']);
    Asset::shouldReceive('find')->with('audio::missing')->andReturnNull();

    $entry = postsMetricsEntry(['segments' => [[...$file, 'transcript' => 'inline row']]]);

    expect(app(PostsReadingMetricsService::class)->totalTextContent($entry))->toBe($expected);
})->with([
    'a bare file name, in the audio container' => [['file' => 'episode'], 'from the asset'],
    'a qualified asset id' => [['file' => 'podcasts::episode'], 'qualified asset'],
    'a list of files: the first' => [['file' => ['episode', 'missing']], 'from the asset'],
    'an asset that does not resolve: nothing' => [['file' => 'missing'], ''],
    'a list whose first item is not a string: nothing' => [['file' => [5]], ''],
    // No asset is stubbed for `audio::`, so looking one up fails the test
    'a list whose first item is empty: nothing' => [['file' => ['']], ''],
    'a list with no first item: nothing' => [['file' => [1 => 'episode']], ''],
    'a file that is not a string or list: nothing' => [['file' => 12], ''],
    'no file key: the row' => [[], 'inline row'],
    'a null file: the row' => [['file' => null], 'inline row'],
    'an empty file: the row' => [['file' => ''], 'inline row'],
    'an empty list: the row' => [['file' => []], 'inline row'],
]);

it('treats a found asset that is not a Statamic asset as unresolved', function (): void {
    Asset::shouldReceive('find')->with('audio::episode')->andReturn(Mockery::mock(Statamic\Contracts\Assets\Asset::class));

    $entry = postsMetricsEntry(['segments' => [['file' => 'episode', 'transcript' => 'inline row']]]);

    expect(app(PostsReadingMetricsService::class)->totalTextContent($entry))->toBe('');
});

it('takes a segment\'s title and excerpt from the first filled source, ending with its asset', function (): void {
    postsMetricsStubAssetById('audio::episode', ['title' => 'Asset Title', 'excerpt' => 'Asset excerpt', 'transcript' => null]);

    $entry = postsMetricsEntry(['segments' => [
        ['file' => 'episode', 'title' => null, 'title_override' => null, 'excerpt' => '', 'excerpt_override' => null],
        ['title' => 'Row', 'title_override' => 'Override', 'excerpt' => 'Row excerpt', 'excerpt_override' => 'Override excerpt', 'transcript' => 'tx'],
        ['title' => '', 'title_override' => 'Second override', 'excerpt_override' => 'Override only', 'transcript' => 'ty'],
    ]]);

    expect(app(PostsReadingMetricsService::class)->totalTextContent($entry))
        ->toBe('Asset Title Asset excerpt Row Row excerpt tx Second override Override only ty');
});

it('reads a title or excerpt from a string, a number or a wrapped value, and nothing else', function (mixed $title, string $expected): void {
    $entry = postsMetricsEntry(['segments' => [
        ['title' => $title, 'title_override' => 'fallback', 'transcript' => null],
        ['title' => 'end', 'transcript' => null],
    ]]);

    expect(app(PostsReadingMetricsService::class)->totalTextContent($entry))->toBe($expected.' end');
})->with([
    'a string' => ['words here', 'words here'],
    'an integer' => [42, '42'],
    'a float' => [1.5, '1.5'],
    'a Value wrapping a string' => [new Value('wrapped text'), 'wrapped text'],
    'a Value wrapping a list' => [new Value(['a']), 'fallback'],
    'a list' => [['not', 'scalar'], 'fallback'],
    'a boolean' => [true, 'fallback'],
]);

it('reads no site boilerplate when there is no multisite global', function (): void {
    GlobalSet::shouldReceive('findByHandle')->with('uams_multisite_settings')->andReturnNull();

    $entry = postsMetricsEntry(['content' => 'alpha', 'boilerplate_query' => true]);

    expect(app(PostsReadingMetricsService::class)->totalTextContent($entry))->toBe('alpha');
});

it('reads the global\'s boilerplate selection as a bare slug, a qualified id or nothing', function (mixed $selection, string $expected): void {
    $vars = Mockery::mock(Variables::class);
    $vars->shouldReceive('get')->with('boilerplate')->andReturn($selection);

    $global = Mockery::mock(GlobalSetModel::class);
    $global->shouldReceive('in')->with('default')->andReturn($vars);
    GlobalSet::shouldReceive('findByHandle')->with('uams_multisite_settings')->andReturn($global);

    postsMetricsStubBoilerplateTerm('about', 'about text');

    $entry = postsMetricsEntry(['content' => 'alpha', 'boilerplate_query' => true]);

    expect(app(PostsReadingMetricsService::class)->totalTextContent($entry))->toBe($expected);
})->with([
    'a bare slug' => ['about', 'alpha about text'],
    'a qualified id' => ['boilerplates::about', 'alpha about text'],
    'a single-item list' => [['about'], 'alpha about text'],
    'null' => [null, 'alpha'],
    'an empty string' => ['', 'alpha'],
]);

it('reads a term that is already localized as it is, and nothing from a term with no localization', function (): void {
    $localized = Mockery::mock(LocalizedTerm::class);
    $localized->shouldReceive('value')->with('boilerplate')->andReturn('localized already');
    $localized->shouldNotReceive('in');
    Term::shouldReceive('find')->with('video_series::localized')->andReturn($localized);

    $notLocalized = Mockery::mock(TaxonomyTerm::class);
    $notLocalized->shouldReceive('in')->with('default')->andReturnNull();
    Term::shouldReceive('find')->with('video_series::not-localized')->andReturn($notLocalized);

    $service = app(PostsReadingMetricsService::class);
    $read = fn (string $series, string $id): string => $service->totalTextContent(postsMetricsEntry(
        ['content' => 'alpha', 'series_boilerplate_display_query' => true, 'series' => $series],
        ['id' => $id],
    ));

    expect($read('localized', 'one'))->toBe('alpha localized already')
        ->and($read('not-localized', 'two'))->toBe('alpha');
});

it('namespaces a bare series slug with the field\'s first configured taxonomy, or not at all', function (?Closure $blueprint, string $expected): void {
    postsMetricsStubSeriesTerm('video_series::cardio', 'series text');

    $entry = postsMetricsEntry(
        ['content' => 'alpha', 'series_boilerplate_display_query' => true, 'series' => 'cardio'],
        ['blueprint' => $blueprint instanceof Closure ? $blueprint() : null],
    );

    expect(app(PostsReadingMetricsService::class)->totalTextContent($entry))->toBe($expected);
})->with([
    'a list of taxonomies: the first' => [fn (): Blueprint => postsMetricsSeriesBlueprint(['video_series', 'audio_series']), 'alpha series text'],
    'a single taxonomy string' => [fn (): Blueprint => postsMetricsSeriesBlueprint('video_series'), 'alpha series text'],
    'a list whose first item is not a string' => [fn (): Blueprint => postsMetricsSeriesBlueprint([5, 'video_series']), 'alpha'],
    'an empty list' => [fn (): Blueprint => postsMetricsSeriesBlueprint([]), 'alpha'],
    'no taxonomies configured' => [fn (): Blueprint => postsMetricsSeriesBlueprint(null), 'alpha'],
    'no series field' => [fn (): Blueprint => postsMetricsSeriesBlueprint(null, false), 'alpha'],
    'no blueprint' => [null, 'alpha'],
]);

it('keeps a per-post boilerplate given as a qualified id', function (): void {
    postsMetricsStubGlobalBoilerplate('global four five six');
    postsMetricsStubBoilerplateTerm('nwa', 'northwest arkansas');

    $entry = postsMetricsEntry(['content' => 'alpha', 'boilerplate_query' => true, 'boilerplate' => 'boilerplates::nwa']);

    expect(app(PostsReadingMetricsService::class)->totalTextContent($entry))->toBe('alpha northwest arkansas');
});

it('keeps the valid words of a segment title or excerpt that holds an invalid byte', function (string $segmentKey): void {
    // Each invalid byte reads as a space, so it splits words and adds none (UAMS-Web/uams-statamic-archive#2984)
    $entry = postsMetricsEntry(['content' => 'ok', 'segments' => [
        [$segmentKey => "\xff\xfe", 'transcript' => null],
        [$segmentKey => "caf\xc3\xa9\xff".'au lait '."\xe2\x80", 'transcript' => null],
        ['title' => 'end', 'transcript' => null],
    ]]);
    $service = app(PostsReadingMetricsService::class);

    expect($service->totalTextContent($entry))->toBe('ok café au lait end')
        ->and($service->wordCount($entry))->toBe(5);
})->with(['title', 'excerpt']);

it('counts the valid words when the joined text holds an invalid byte', function (string $content, string $text): void {
    // Each invalid byte reads as a space, so it splits words and adds none (UAMS-Web/uams-statamic-archive#2984)
    $entry = postsMetricsEntry(['content' => $content]);
    $service = app(PostsReadingMetricsService::class);

    expect($service->totalTextContent($entry))->toBe($text)
        ->and($service->wordCount($entry))->toBe(\count(explode(' ', $text)));
})->with([
    'a trailing byte' => ["valid words \xff", 'valid words'],
    'a byte inside a word' => ['valid'."\xff".'words', 'valid words'],
    'a truncated sequence beside valid multibyte text' => ['naïve '."\xe2\x80".' café', 'naïve café'],
    'an overlong encoding and a surrogate' => ["\xc0\xaf".'one '."\xed\xa0\x80".'two', 'one two'],
]);
