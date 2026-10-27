<?php

declare(strict_types=1);

use App\Support\Statamic\Taxonomies\RecognitionListTaxonomyComputedValues;
use PHPUnit\Framework\Assert;
use Statamic\Facades\Term;
use Statamic\Fields\Value;
use Statamic\Taxonomies\AugmentedTerm;
use Statamic\Taxonomies\LocalizedTerm;
use Statamic\Taxonomies\Term as TaxonomyTerm;
use Tests\TestCase;

/**
 * Covers the surviving `recognition_lists_series` `full_name` computed after the
 * flat `recognition_lists` taxonomy (and its `award_name` computed) was retired in
 * favor of a structured `recognition_lists` collection (UAMS-Web/uams-statamic-archive#1546).
 */
uses(TestCase::class);

/**
 * Normalize a found term to a `LocalizedTerm` in the default site.
 *
 * Mirrors the source test's `$term instanceof LocalizedTerm ? $term : $term->in('default')`
 * branch while narrowing for PHPStan-max.
 *
 * @param  mixed  $term  Result of `Term::find()`
 */
function recognitionListLocalizeDefaultTerm(mixed $term): LocalizedTerm
{
    // Reuse an already-localized term instance as-is.
    if ($term instanceof LocalizedTerm) {
        return $term;
    }

    // Localize a base taxonomy term for the default site.
    Assert::assertInstanceOf(TaxonomyTerm::class, $term);

    /** @var LocalizedTerm $localized */
    $localized = $term->in('default');

    return $localized;
}

it('normalizes bare slug to term id', function (): void {
    expect(
        RecognitionListTaxonomyComputedValues::normalizeTermId(
            'arkansas-times-best-of-arkansas',
            'recognition_lists_series'
        )
    )->toBe('recognition_lists_series::arkansas-times-best-of-arkansas');
});

it('preserves prefixed term id', function (): void {
    expect(
        RecognitionListTaxonomyComputedValues::normalizeTermId(
            'recognition_lists_series::arkansas-times-best-of-arkansas',
            'recognition_lists_series'
        )
    )->toBe('recognition_lists_series::arkansas-times-best-of-arkansas');
});

it('normalizes single element slug array', function (): void {
    expect(
        RecognitionListTaxonomyComputedValues::normalizeTermId(
            ['arkansas-times'],
            'recognition_lists_publications'
        )
    )->toBe('recognition_lists_publications::arkansas-times');
});

it('resolves full name from stache terms', function (): void {
    $series = Term::find('recognition_lists_series::arkansas-times-best-of-arkansas');

    expect($series)->not->toBeNull();

    $seriesLocalized = recognitionListLocalizeDefaultTerm($series);

    expect(
        RecognitionListTaxonomyComputedValues::fullNameForSeriesLocalizedTerm($seriesLocalized)
    )->toBe('Arkansas Times Best of Arkansas');
});

it('exposes computed keys on augmented term instances', function (): void {
    $series = Term::find('recognition_lists_series::arkansas-times-best-of-arkansas');

    expect($series)->not->toBeNull();

    $seriesLocalized = recognitionListLocalizeDefaultTerm($series);

    $augmented = $seriesLocalized->augmented();

    Assert::assertInstanceOf(AugmentedTerm::class, $augmented);

    /** @var array<int, mixed> $seriesKeys */
    $seriesKeys = $augmented->keys();

    expect($seriesKeys)->toContain('full_name');

    $seriesFullName = $seriesLocalized->augmentedValue('full_name');

    Assert::assertInstanceOf(Value::class, $seriesFullName);

    expect($seriesFullName->value())->toBe('Arkansas Times Best of Arkansas');
});

it('normalizes a missing, empty or non-string value to no id', function (mixed $value): void {
    expect(RecognitionListTaxonomyComputedValues::normalizeTermId($value, 'recognition_lists_series'))->toBeNull();
})->with([
    'null' => [null],
    'empty string' => [''],
    'empty list' => [[]],
    'list of null' => [[null]],
    'list of empty string' => [['']],
    'integer' => [5],
    'list of integer' => [[5]],
]);

it('takes only the first element of a list', function (): void {
    expect(RecognitionListTaxonomyComputedValues::normalizeTermId(['first', 'second'], 'recognition_lists_series'))
        ->toBe('recognition_lists_series::first');
});

it('finds no term for an empty id, without looking it up', function (): void {
    Term::shouldReceive('find')->never();

    expect(RecognitionListTaxonomyComputedValues::findLocalizedTerm(null, 'default'))->toBeNull()
        ->and(RecognitionListTaxonomyComputedValues::findLocalizedTerm('', 'default'))->toBeNull();
});

it('finds no term for an unknown id', function (): void {
    expect(RecognitionListTaxonomyComputedValues::findLocalizedTerm('recognition_lists_series::no-such-series', 'default'))->toBeNull();
});

it('returns the found term itself when it is already in the requested site', function (): void {
    $localized = Mockery::mock(LocalizedTerm::class);
    $localized->shouldReceive('locale')->andReturn('default');
    $localized->shouldReceive('in')->never();
    Term::shouldReceive('find')->with('recognition_lists_series::here')->andReturn($localized);

    expect(RecognitionListTaxonomyComputedValues::findLocalizedTerm('recognition_lists_series::here', 'default'))->toBe($localized);
});

it('localizes a term found in another site, or a base term, for the requested site', function (): void {
    $other = RecognitionListTaxonomyComputedValues::findLocalizedTerm('recognition_lists_series::arkansas-times-best-of-arkansas', 'health');

    expect($other?->locale())->toBe('health');

    $localized = Mockery::mock(LocalizedTerm::class);
    $base = Mockery::mock(TaxonomyTerm::class);
    $base->shouldReceive('in')->once()->with('default')->andReturn($localized);
    Term::shouldReceive('find')->with('recognition_lists_series::base')->andReturn($base);

    expect(RecognitionListTaxonomyComputedValues::findLocalizedTerm('recognition_lists_series::base', 'default'))->toBe($localized);
});

/**
 * A series term double with the given publication value and name, in the default site.
 */
function recognitionListSeries(mixed $publication, mixed $name): LocalizedTerm
{
    $series = Mockery::mock(LocalizedTerm::class);
    $series->shouldReceive('value')->with('publication')->andReturn($publication);
    $series->shouldReceive('value')->with('name')->andReturn($name);
    $series->shouldReceive('locale')->andReturn('default');

    return $series;
}

it('falls back to the series name alone when the publication is missing or unnamed', function (mixed $publication): void {
    expect(RecognitionListTaxonomyComputedValues::fullNameForSeriesLocalizedTerm(recognitionListSeries($publication, '  Best of Arkansas  ')))
        ->toBe('Best of Arkansas');
})->with([
    'no publication' => [null],
    'unknown publication' => ['no-such-publication'],
]);

it('returns the publication name alone when the series name is not text', function (): void {
    expect(RecognitionListTaxonomyComputedValues::fullNameForSeriesLocalizedTerm(recognitionListSeries('arkansas-times', ['not', 'text'])))
        ->toBe('Arkansas Times');
});

it('returns an empty string when neither name resolves', function (): void {
    expect(RecognitionListTaxonomyComputedValues::fullNameForSeriesLocalizedTerm(recognitionListSeries(null, '   ')))->toBe('');
});
