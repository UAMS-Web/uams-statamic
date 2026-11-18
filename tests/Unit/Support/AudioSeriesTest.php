<?php

declare(strict_types=1);

use App\Support\AudioSeries;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Facades\Term;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;
use Statamic\Taxonomies\LocalizedTerm;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * The guards in {@see AudioSeries} that `AudioSeriesSubtypeTest`'s real content
 * terms never reach: an entry with no blueprint or no `series` field, a `series`
 * field whose taxonomies or a term whose subtypes would match only loosely, a
 * stored subtype that is not a list, and the raw `series` values the term-id
 * reader skips.
 *
 * Mockery doubles and a faked `Term` facade, so no content/ submodule.
 *
 * @command  php artisan test --compact tests/Unit/Support/AudioSeriesTest.php
 */

/**
 * An entry double whose blueprint is the given one, storing `$series` in its
 * `series` field.
 */
function audioSeriesEntry(?Blueprint $blueprint, mixed $series = null): Entry
{
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('blueprint')->andReturn($blueprint);
    $entry->shouldReceive('get')->with('series')->andReturn($series);

    return $entry;
}

/**
 * A blueprint double whose `series` field targets the given taxonomies, or that
 * has no `series` field when `$taxonomies` is null.
 */
function audioSeriesBlueprint(mixed $taxonomies): Blueprint
{
    $blueprint = Mockery::mock(Blueprint::class);

    if ($taxonomies === null) {
        $blueprint->shouldReceive('field')->with('series')->andReturn(null);

        return $blueprint;
    }

    $field = Mockery::mock(Field::class);
    $field->shouldReceive('get')->with('taxonomies')->andReturn($taxonomies);
    $blueprint->shouldReceive('field')->with('series')->andReturn($field);

    return $blueprint;
}

/**
 * Resolves `Term::find($id)` to a term double storing `$subtype` in its `subtype`
 * field, and every other id to nothing.
 *
 * @param  array<string, mixed>  $subtypesById
 */
function audioSeriesTerms(array $subtypesById): void
{
    Term::shouldReceive('find')->andReturnUsing(static function (string $id) use ($subtypesById): ?LocalizedTerm {
        if (! array_key_exists($id, $subtypesById)) {
            return null;
        }

        $term = Mockery::mock(LocalizedTerm::class);
        $term->shouldReceive('get')->with('subtype')->andReturn($subtypesById[$id]);

        return $term;
    });
}

it('finds no subtype on an entry with no blueprint or no series field', function (?Blueprint $blueprint): void {
    Term::shouldReceive('find')->never();

    expect(AudioSeries::entrySeriesHasSubtype(audioSeriesEntry($blueprint, 'show'), 'podcast'))->toBeFalse();
})->with([
    'no blueprint' => fn (): ?Blueprint => null,
    'no series field' => fn (): Blueprint => audioSeriesBlueprint(null),
]);

it('treats the series field as audio only when it names the audio_series taxonomy exactly', function (mixed $taxonomies, bool $expected): void {
    audioSeriesTerms(['audio_series::show' => ['podcast']]);

    expect(AudioSeries::entrySeriesHasSubtype(audioSeriesEntry(audioSeriesBlueprint($taxonomies), 'show'), 'podcast'))->toBe($expected);
})->with([
    'audio_series' => [['audio_series'], true],
    'another taxonomy' => [['video_series'], false],
    'a value equal to it only loosely' => [[true], false],
    'not a list' => ['audio_series', false],
]);

it('matches a subtype only when the term declares it exactly', function (array $subtypes, bool $expected): void {
    audioSeriesTerms(['audio_series::show' => $subtypes]);

    expect(AudioSeries::entrySeriesHasSubtype(audioSeriesEntry(audioSeriesBlueprint(['audio_series']), 'show'), '10'))->toBe($expected);
})->with([
    'the same key' => [['10'], true],
    'a key equal to it only loosely' => [['1e1'], false],
]);

it('reads past a stored series value that is not a slug to a later one that is', function (): void {
    audioSeriesTerms(['audio_series::show' => ['podcast']]);

    expect(AudioSeries::entrySeriesHasSubtype(audioSeriesEntry(audioSeriesBlueprint(['audio_series']), [5, 'show']), 'podcast'))->toBeTrue();
});

it('reads no subtypes from a term whose stored subtype is not a list', function (): void {
    audioSeriesTerms(['audio_series::show' => 'podcast']);

    expect(AudioSeries::subtypesForTermId('audio_series::show'))->toBe([]);
});

it('namespaces each non-empty stored slug and keeps an already-namespaced id', function (): void {
    $ids = (new ReflectionMethod(AudioSeries::class, 'seriesTermIds'))->invoke(null, audioSeriesEntry(null, ['', 'show', 'audio_series::other', null]));

    expect($ids)->toBe(['audio_series::show', 'audio_series::other']);
});
