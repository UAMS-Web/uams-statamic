<?php

declare(strict_types=1);

use App\SearchTransformers\TermsToTitleTransformer;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Facades\Term as TermFacade;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;
use Statamic\Taxonomies\Term;
use Tests\TestCase;

/**
 * Unit tests for TermsToTitleTransformer search transformer.
 *
 * Mutation: ~90% on App\SearchTransformers\TermsToTitleTransformer
 * (--covered-only). The surviving mutants are equivalent: the instanceof
 * mutation on the `! $searchable instanceof Term || ! $blueprint` guard is
 * caught by the second operand; `get('taxonomies', [])` always returns an
 * array so the `! is_array || === []` short-circuit and its early return fall
 * through to the same final `return $value`; and the `(string)` cast before a
 * `sprintf('%s…')` interpolation / the non-scalar-value narrowing are no-ops
 * for the coerced output.
 */
uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Build a searchable Term whose blueprint reports the given field type +
 * taxonomies, for exercising the slug -> title resolution path.
 *
 * @param  list<mixed>  $taxonomies
 */
function termsToTitleSearchable(string $fieldHandle, string $fieldType, array $taxonomies): Term
{
    $field = Mockery::mock(Field::class);
    $field->shouldReceive('type')->andReturn($fieldType);
    $field->shouldReceive('get')->with('taxonomies', [])->andReturn($taxonomies);

    $blueprint = Mockery::mock(Blueprint::class);
    $blueprint->shouldReceive('field')->with($fieldHandle)->andReturn($field);

    $searchable = Mockery::mock(Term::class);
    $searchable->shouldReceive('blueprint')->andReturn($blueprint);

    return $searchable;
}

it('returns null when the field value is empty', function () {
    $transformer = new TermsToTitleTransformer;

    expect(
        $transformer->handle(
            '',
            'field',
            new stdClass
        )
    )->toBeNull();
});

it('returns the original value when the searchable is not a term', function () {
    $transformer = new TermsToTitleTransformer;

    expect(
        $transformer->handle(
            'slug',
            'field',
            new stdClass
        )
    )->toBe('slug');
});

it('returns the original value when the term has no blueprint', function () {
    $searchable = Mockery::mock(Term::class);
    $searchable->shouldReceive('blueprint')->once()->andReturn(null);

    $transformer = new TermsToTitleTransformer;

    expect(
        $transformer->handle(
            'slug',
            'field',
            $searchable
        )
    )->toBe('slug');
});

it('returns the original value when the field is neither terms nor terms_search_index', function () {
    $searchable = termsToTitleSearchable('label', 'text', ['drugs']);

    expect((new TermsToTitleTransformer)->handle('aspirin', 'label', $searchable))->toBe('aspirin');
});

it('resolves a core terms field slug to the term title', function () {
    $searchable = termsToTitleSearchable('organization', 'terms', ['certification_organizations']);

    $resolved = Mockery::mock(Term::class);
    $resolved->shouldReceive('title')->andReturn('American Board of Radiology');
    TermFacade::shouldReceive('find')
        ->with('certification_organizations::abr')
        ->andReturn($resolved);

    expect((new TermsToTitleTransformer)->handle('abr', 'organization', $searchable))
        ->toBe('American Board of Radiology');
});

it('resolves a terms_search_index field slug to the term title', function () {
    // The regression: `terms_search_index` was previously skipped by the
    // `terms`-only guard, so the raw slug was indexed instead of the title.
    $searchable = termsToTitleSearchable('series', 'terms_search_index', ['recognition_lists_series']);

    $resolved = Mockery::mock(Term::class);
    $resolved->shouldReceive('title')->andReturn('Best Doctors in America');
    TermFacade::shouldReceive('find')
        ->with('recognition_lists_series::best-doctors')
        ->andReturn($resolved);

    expect((new TermsToTitleTransformer)->handle('best-doctors', 'series', $searchable))
        ->toBe('Best Doctors in America');
});

it('resolves a fully-qualified multi-taxonomy value directly to its title', function () {
    // A multi-taxonomy field (`data_source` spans two taxonomies) stores a
    // FULLY-QUALIFIED `taxonomy::slug` id, not a bare slug — as on the
    // recognition_lists collection's list entries. Resolve it directly;
    // prepending a taxonomy handle would double-prefix and fail.
    $searchable = termsToTitleSearchable(
        'data_source',
        'terms_search_index',
        ['recognition_lists_data_sources', 'recognition_lists_publications'],
    );

    $resolved = Mockery::mock(Term::class);
    $resolved->shouldReceive('title')->andReturn('Best Doctors Inc.');
    TermFacade::shouldReceive('find')
        ->with('recognition_lists_data_sources::best-doctors-inc')
        ->andReturn($resolved);

    expect((new TermsToTitleTransformer)->handle('recognition_lists_data_sources::best-doctors-inc', 'data_source', $searchable))
        ->toBe('Best Doctors Inc.');
});

it('resolves a fully-qualified value from either taxonomy of a multi-taxonomy field', function () {
    $searchable = termsToTitleSearchable(
        'data_source',
        'terms_search_index',
        ['recognition_lists_data_sources', 'recognition_lists_publications'],
    );

    $resolved = Mockery::mock(Term::class);
    $resolved->shouldReceive('title')->andReturn('AY Magazine');
    TermFacade::shouldReceive('find')
        ->with('recognition_lists_publications::ay-magazine')
        ->andReturn($resolved);

    expect((new TermsToTitleTransformer)->handle('recognition_lists_publications::ay-magazine', 'data_source', $searchable))
        ->toBe('AY Magazine');
});

it('casts a non-string title to a string on the qualified-id path', function () {
    $searchable = termsToTitleSearchable('data_source', 'terms_search_index', ['recognition_lists_data_sources']);

    $resolved = Mockery::mock(Term::class);
    $resolved->shouldReceive('title')->andReturn(2021);
    TermFacade::shouldReceive('find')
        ->with('recognition_lists_data_sources::2021-list')
        ->andReturn($resolved);

    expect((new TermsToTitleTransformer)->handle('recognition_lists_data_sources::2021-list', 'data_source', $searchable))
        ->toBe('2021');
});

it('returns the raw qualified id when no such term exists', function () {
    $searchable = termsToTitleSearchable('data_source', 'terms_search_index', ['recognition_lists_data_sources']);

    TermFacade::shouldReceive('find')
        ->with('recognition_lists_data_sources::missing')
        ->andReturn(null);

    expect((new TermsToTitleTransformer)->handle('recognition_lists_data_sources::missing', 'data_source', $searchable))
        ->toBe('recognition_lists_data_sources::missing');
});

it('falls back to the raw slug when no configured taxonomy has the term', function () {
    $searchable = termsToTitleSearchable('series', 'terms_search_index', ['recognition_lists_series']);

    TermFacade::shouldReceive('find')
        ->with('recognition_lists_series::missing')
        ->andReturn(null);

    expect((new TermsToTitleTransformer)->handle('missing', 'series', $searchable))->toBe('missing');
});

it('returns the raw slug when no taxonomies are configured', function () {
    $searchable = termsToTitleSearchable('series', 'terms_search_index', []);

    expect((new TermsToTitleTransformer)->handle('slug', 'series', $searchable))->toBe('slug');
});

it('returns the raw value when the blueprint has no such field', function () {
    $blueprint = Mockery::mock(Blueprint::class);
    $blueprint->shouldReceive('field')->with('missing')->andReturn(null);

    $searchable = Mockery::mock(Term::class);
    $searchable->shouldReceive('blueprint')->andReturn($blueprint);

    expect((new TermsToTitleTransformer)->handle('slug', 'missing', $searchable))->toBe('slug');
});

it('casts a non-string slug to a string in the fallback', function () {
    // Defensive: the stored value is normally a string slug, but the transformer
    // narrows `mixed`. A non-string scalar must round-trip as a string.
    $searchable = termsToTitleSearchable('series', 'terms_search_index', ['recognition_lists_series']);

    TermFacade::shouldReceive('find')
        ->with('recognition_lists_series::123')
        ->andReturn(null);

    expect((new TermsToTitleTransformer)->handle(123, 'series', $searchable))->toBe('123');
});

it('casts a non-string term title to a string', function () {
    $searchable = termsToTitleSearchable('series', 'terms_search_index', ['recognition_lists_series']);

    $resolved = Mockery::mock(Term::class);
    $resolved->shouldReceive('title')->andReturn(2024);
    TermFacade::shouldReceive('find')
        ->with('recognition_lists_series::2024-list')
        ->andReturn($resolved);

    expect((new TermsToTitleTransformer)->handle('2024-list', 'series', $searchable))->toBe('2024');
});

it('skips non-scalar and unmatched taxonomies and resolves via a later one', function () {
    // A non-scalar taxonomy handle is skipped; an integer handle is cast and
    // tried; the string taxonomy that actually holds the term wins.
    $searchable = termsToTitleSearchable(
        'data_source',
        'terms_search_index',
        [['nested'], 42, 'recognition_lists_publications'],
    );

    TermFacade::shouldReceive('find')
        ->with('42::us-news')
        ->andReturn(null);

    $resolved = Mockery::mock(Term::class);
    $resolved->shouldReceive('title')->andReturn('U.S. News');
    TermFacade::shouldReceive('find')
        ->with('recognition_lists_publications::us-news')
        ->andReturn($resolved);

    expect((new TermsToTitleTransformer)->handle('us-news', 'data_source', $searchable))->toBe('U.S. News');
});
