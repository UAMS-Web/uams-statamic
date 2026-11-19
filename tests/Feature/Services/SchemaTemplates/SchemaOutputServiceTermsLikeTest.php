<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\JsonLdCompositionProfiler;
use App\Services\SchemaTemplates\MedicalOrganizationSubtypeAtTypeResolver;
use App\Services\SchemaTemplates\PodcastEpisodeAtTypeResolver;
use App\Services\SchemaTemplates\SchemaClassificationsIdentifiersMerger;
use App\Services\SchemaTemplates\SchemaJsonLdAtIdDeduplication;
use App\Services\SchemaTemplates\SchemaJsonLdSchemaObjectEmissionCoordinator;
use App\Services\SchemaTemplates\SchemaOutputService;
use App\Services\SchemaTemplates\SchemaTemplatesCascadeService;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Contracts\Taxonomies\Term;
use Statamic\Fields\Field;
use Statamic\Fields\Value;
use Statamic\Taxonomies\LocalizedTerm;
use Statamic\Taxonomies\Term as TaxonomyTerm;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(SchemaOutputService::class);

/**
 * Pins how {@see SchemaOutputService} treats terms fields in the merged Antlers
 * context: which of several blueprint fields sharing a handle is chosen for a
 * stored value, and which stored values still need a blueprint-aware augment.
 *
 * @command  php artisan test --compact tests/Feature/Services/SchemaTemplates/SchemaOutputServiceTermsLikeTest.php
 */

/**
 * The service, built from the container's collaborators.
 */
function sosTermsLikeService(): SchemaOutputService
{
    return new SchemaOutputService(
        app(SchemaTemplatesCascadeService::class),
        app(SchemaClassificationsIdentifiersMerger::class),
        app(SchemaJsonLdAtIdDeduplication::class),
        app(SchemaJsonLdSchemaObjectEmissionCoordinator::class),
        app(JsonLdCompositionProfiler::class),
        app(MedicalOrganizationSubtypeAtTypeResolver::class),
        app(PodcastEpisodeAtTypeResolver::class),
    );
}

/**
 * Call a non-public method of the service.
 */
function sosTermsLikeCall(string $method, mixed ...$args): mixed
{
    return (new ReflectionMethod(SchemaOutputService::class, $method))->invoke(sosTermsLikeService(), ...$args);
}

/**
 * A blueprint terms field double: `single` caps at one item, `multi` does not.
 */
function sosTermsLikeField(string $kind): Field&MockInterface
{
    /** @var Field&MockInterface $field */
    $field = Mockery::mock(Field::class);
    $field->shouldReceive('handle')->andReturn('tags');
    $field->shouldReceive('get')->with('max_items')->andReturn($kind === 'single' ? 1 : null);

    return $field;
}

/**
 * A stored value built from its dataset name, so a dataset row needs no double.
 */
function sosTermsLikeStoredValue(mixed $present): mixed
{
    return match ($present) {
        'TERM' => Mockery::mock(Term::class),
        'TAXONOMY_TERM' => Mockery::mock(TaxonomyTerm::class),
        'LOCALIZED_TERM' => Mockery::mock(LocalizedTerm::class),
        default => $present,
    };
}

it('selects the blueprint field the stored value shape calls for', function (array $kinds, mixed $present, int $expectedIndex): void {
    $candidates = array_map(sosTermsLikeField(...), $kinds);

    $selected = sosTermsLikeCall('selectTermsLikeBlueprintFieldForMergedValue', $candidates, sosTermsLikeStoredValue($present));

    expect($selected)->toBe($candidates[$expectedIndex]);
})->with([
    'a slug, single second' => [['multi', 'single'], 'alpha', 1],
    'a slug, single first' => [['single', 'multi'], 'alpha', 0],
    'a slug, no single field' => [['multi', 'multi'], 'alpha', 0],
    'a slug in a Value' => [['multi', 'single'], new Value('alpha'), 1],
    'a whitespace string' => [['multi', 'single'], '  ', 0],
    'an empty string' => [['multi', 'single'], '', 0],
    'a slug list, multi second' => [['single', 'multi'], ['alpha', 'beta'], 1],
    'a slug list, no multi field' => [['single', 'single'], ['alpha'], 0],
    'a slug list in a Value' => [['single', 'multi'], new Value(['alpha']), 1],
    'an empty list' => [['single', 'multi'], [], 0],
    'a keyed array' => [['single', 'multi'], ['a' => 'alpha'], 0],
    'null' => [['single', 'multi'], null, 0],
    'a term, single second' => [['multi', 'single'], 'TERM', 1],
    'a term, no single field' => [['multi', 'multi'], 'TERM', 0],
    'a collection, multi second' => [['single', 'multi'], collect(['alpha']), 1],
    'a collection, no multi field' => [['single', 'single'], collect(['alpha']), 0],
    'an empty collection' => [['single', 'multi'], collect(), 0],
    'an integer, multi first' => [['multi', 'single'], 5, 0],
    'an integer, single first' => [['single', 'multi'], 5, 0],
]);

it('selects the only blueprint field whatever the stored value', function (string $kind, mixed $present): void {
    $field = sosTermsLikeField($kind);

    expect(sosTermsLikeCall('selectTermsLikeBlueprintFieldForMergedValue', [$field], sosTermsLikeStoredValue($present)))->toBe($field);
})->with([
    'single' => ['single'],
    'multi' => ['multi'],
])->with([
    'a slug' => ['alpha'],
    'a slug list' => [['alpha']],
    'a term' => ['TERM'],
    'a collection' => [collect(['alpha'])],
    'an integer' => [5],
]);

it('decides which stored values need a blueprint-aware augment', function (mixed $present, bool $expected): void {
    expect(sosTermsLikeCall('mergedContextTermsLikeValueNeedsBlueprintAwareAugment', sosTermsLikeStoredValue($present)))->toBe($expected);
})->with([
    'a slug' => ['alpha', true],
    'a slug in a Value' => [new Value('alpha'), true],
    'a whitespace string' => ['  ', false],
    'an empty string' => ['', false],
    'a slug list' => [['alpha', 'beta'], true],
    'a slug list in a Value' => [new Value(['alpha']), true],
    'a list with an empty slug' => [['alpha', ''], false],
    'a list with a non-string' => [['alpha', 7], false],
    'an empty list' => [[], false],
    'a keyed array' => [['a' => 'alpha'], false],
    'null' => [null, false],
    'an integer' => [5, false],
    'an empty collection' => [collect(), false],
    'a collection of slugs' => [collect(['alpha']), true],
]);

it('needs no augment for a collection of term models', function (string $termClass): void {
    $collection = collect([sosTermsLikeStoredValue($termClass), sosTermsLikeStoredValue($termClass)]);

    expect(sosTermsLikeCall('mergedContextTermsLikeValueNeedsBlueprintAwareAugment', $collection))->toBeFalse();
})->with([
    'taxonomy terms' => ['TAXONOMY_TERM'],
    'localized terms' => ['LOCALIZED_TERM'],
]);

it('needs an augment for a collection that mixes term models and slugs', function (): void {
    $collection = collect([sosTermsLikeStoredValue('TAXONOMY_TERM'), 'alpha']);

    expect(sosTermsLikeCall('mergedContextTermsLikeValueNeedsBlueprintAwareAugment', $collection))->toBeTrue();
});
