<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaOutputService;
use Illuminate\Support\Collection;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Contracts\Query\Builder as QueryBuilderContract;
use Statamic\Contracts\Taxonomies\Term;
use Statamic\Fields\Value;
use Statamic\Taxonomies\LocalizedTerm;
use Statamic\Taxonomies\Term as TaxonomyTerm;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(SchemaOutputService::class);

/**
 * Pins three term helpers of {@see SchemaOutputService}:
 * `stableSignatureForTermsAugmentPresentPayload()`, the memo signature of a
 * stored terms value; `normalizeTermsLikeAugmentedResolutionToAntlersLoopableCollection()`,
 * which turns an augmented terms value into something Antlers can loop; and
 * `resolveSchemaPrimaryRelatedTaxonomyTermFromAntlersOverlay()` with
 * `unwrapFirstTaxonomyTermFromSchemaContextValue()`, which pick the first taxonomy
 * term among the caller's overlay keys. The terms are mocks.
 *
 * @command  php artisan test --compact tests/Feature/Services/SchemaTemplates/SchemaOutputServiceTermHelpersTest.php
 */

/**
 * Call a non-public method of the service.
 */
function sosTermHelperCall(string $method, mixed ...$args): mixed
{
    return (new ReflectionMethod(SchemaOutputService::class, $method))->invoke(app(SchemaOutputService::class), ...$args);
}

it('signs a stored terms value by its shape', function (mixed $raw, string $expected): void {
    expect(sosTermHelperCall('stableSignatureForTermsAugmentPresentPayload', $raw))->toBe($expected);
})->with([
    'null' => [null, 'null'],
    'a slug' => ['alpha', 's:alpha'],
    'an empty string' => ['', 's:'],
    'a slug list, sorted, without blanks or non-strings' => [['beta', '', 7, 'alpha'], "l:alpha\0beta"],
]);

it('signs any other stored value by a hash of its serialized form', function (mixed $raw): void {
    expect(sosTermHelperCall('stableSignatureForTermsAugmentPresentPayload', $raw))
        ->toBe('x:'.hash('xxh128', serialize($raw)));
})->with([
    'an empty list' => [[]],
    'a map' => [['a' => 'alpha']],
    'an integer' => [7],
    'a boolean' => [false],
]);

it('signs the same slugs in any order alike', function (): void {
    expect(sosTermHelperCall('stableSignatureForTermsAugmentPresentPayload', ['beta', 'alpha']))
        ->toBe(sosTermHelperCall('stableSignatureForTermsAugmentPresentPayload', ['alpha', 'beta']));
});

it('loops the terms an augmented query returns', function (): void {
    $terms = collect(['term-a']);
    /** @var QueryBuilderContract&MockInterface $query */
    $query = Mockery::mock(QueryBuilderContract::class);
    $query->shouldReceive('get')->once()->andReturn($terms);

    expect(sosTermHelperCall('normalizeTermsLikeAugmentedResolutionToAntlersLoopableCollection', $query))->toBe($terms);
});

it('loops nothing for a null augmented value', function (): void {
    $out = sosTermHelperCall('normalizeTermsLikeAugmentedResolutionToAntlersLoopableCollection', null);

    expect(mustBe(Collection::class, $out, 'the normalized value')->all())->toBe([]);
});

it('wraps a single augmented term in a collection', function (string $class): void {
    /** @var Term&MockInterface $term */
    $term = Mockery::mock($class);
    $out = sosTermHelperCall('normalizeTermsLikeAugmentedResolutionToAntlersLoopableCollection', $term);

    expect(mustBe(Collection::class, $out, 'the normalized value')->all())->toBe([$term]);
})->with([
    'a localized term' => [LocalizedTerm::class],
    'a term contract' => [Term::class],
]);

it('passes any other augmented value through', function (mixed $value): void {
    expect(sosTermHelperCall('normalizeTermsLikeAugmentedResolutionToAntlersLoopableCollection', $value))->toBe($value);
})->with([
    'a string' => ['alpha'],
    'an array' => [['alpha']],
]);

it('picks no primary term without an overlay', function (): void {
    /** @var TaxonomyTerm&MockInterface $term */
    $term = Mockery::mock(TaxonomyTerm::class);

    expect(sosTermHelperCall('resolveSchemaPrimaryRelatedTaxonomyTermFromAntlersOverlay', ['topic' => $term], []))->toBeNull();
});

it('skips empty, reserved, absent and non-term overlay keys', function (): void {
    /** @var TaxonomyTerm&MockInterface $term */
    $term = Mockery::mock(TaxonomyTerm::class);
    $context = [
        '' => $term,
        SchemaOutputService::ANTLERS_SCHEMA_TEMPLATE_INSTANCE_INDEX => $term,
        SchemaOutputService::ANTLERS_SCHEMA_TEMPLATE_TYPE_OVERRIDE => $term,
        SchemaOutputService::ANTLERS_SCHEMA_TEMPLATE_EMIT_CONTEXT => $term,
        SchemaOutputService::ANTLERS_SCHEMA_TEMPLATE_ENTRY_ID => $term,
        SchemaOutputService::ANTLERS_SCHEMA_PROPERTY_KEY => $term,
        SchemaOutputService::ANTLERS_SCHEMA_PRIMARY_RELATED_TAXONOMY_TERM => $term,
        'plain' => 'alpha',
    ];
    $overlay = array_fill_keys(array_keys($context), 'x') + ['absent' => 'x'];

    expect(sosTermHelperCall('resolveSchemaPrimaryRelatedTaxonomyTermFromAntlersOverlay', $context, $overlay))->toBeNull();
});

it('picks the term of the first overlay key in lexical order', function (): void {
    /** @var TaxonomyTerm&MockInterface $first */
    $first = Mockery::mock(TaxonomyTerm::class);
    /** @var TaxonomyTerm&MockInterface $second */
    $second = Mockery::mock(TaxonomyTerm::class);

    $picked = sosTermHelperCall(
        'resolveSchemaPrimaryRelatedTaxonomyTermFromAntlersOverlay',
        ['zeta' => $second, 'alpha' => 'no term here', 'beta' => $first],
        ['zeta' => 'x', 'alpha' => 'x', 'beta' => 'x']
    );

    expect($picked)->toBe($first);
});

it('unwraps the first term from a collection whose first item is a value', function (): void {
    /** @var TaxonomyTerm&MockInterface $term */
    $term = Mockery::mock(TaxonomyTerm::class);

    expect(sosTermHelperCall('unwrapFirstTaxonomyTermFromSchemaContextValue', collect([new Value($term)])))->toBe($term);
});
