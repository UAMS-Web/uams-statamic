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
use Statamic\Fieldtypes\Terms as TermsFieldtype;
use Statamic\Taxonomies\LocalizedTerm;
use Statamic\Taxonomies\Term as TaxonomyTerm;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(SchemaOutputService::class);

/**
 * Pins how {@see SchemaOutputService} treats terms fields in the merged Antlers
 * context: which of several blueprint fields sharing a handle is chosen for a
 * stored value, which stored values still need a blueprint-aware augment, which
 * fields of a blueprint field (nested replicator sets, grids and groups included)
 * are terms fields, how a terms augment is keyed, and when a raw slug hydration
 * is reused from the request memo.
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
    $candidates = array_map(fn (mixed $kind): Field => sosTermsLikeField(mustBeString($kind, 'the field kind')), $kinds);

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

/**
 * Collect the terms fields under one blueprint field, grouped by handle.
 *
 * @param  array<string, list<Field>>  $grouped
 * @return array<string, list<Field>>
 */
function sosTermsLikeCollect(Field $field, array $grouped = []): array
{
    $method = new ReflectionMethod(SchemaOutputService::class, 'collectTermsLikeFieldHandlesFromField');
    $method->invokeArgs(sosTermsLikeService(), [$field, &$grouped]);

    return $grouped;
}

/**
 * A blueprint field config list holding one terms field named `topics`.
 *
 * @return list<array<string, mixed>>
 */
function sosTermsLikeTopicsFields(): array
{
    return [['handle' => 'topics', 'field' => ['type' => 'terms', 'taxonomies' => ['tags']]]];
}

it('collects a top-level terms field under its handle', function (): void {
    $field = new Field('tags', ['type' => 'terms', 'taxonomies' => ['tags']]);

    expect(sosTermsLikeCollect($field))->toBe(['tags' => [$field]]);
});

it('appends a terms field to the fields already collected under its handle', function (): void {
    $earlier = new Field('tags', ['type' => 'terms', 'taxonomies' => ['tags']]);
    $field = new Field('tags', ['type' => 'terms', 'taxonomies' => ['tags'], 'max_items' => 1]);

    expect(sosTermsLikeCollect($field, ['tags' => [$earlier]]))->toBe(['tags' => [$earlier, $field]]);
});

it('collects no terms field from a field of another type', function (): void {
    expect(sosTermsLikeCollect(new Field('title', ['type' => 'text'])))->toBe([]);
});

it('keys a terms field by its scalar handle as a string, and by an empty string otherwise', function (mixed $handle, string $expectedKey): void {
    /** @var Field&MockInterface $field */
    $field = Mockery::mock(Field::class);
    $field->shouldReceive('fieldtype')->andReturn(app(TermsFieldtype::class));
    $field->shouldReceive('handle')->andReturn($handle);

    expect(sosTermsLikeCollect($field))->toBe([$expectedKey => [$field]]);
})->with([
    'a float' => [1.5, '1.5'],
    'null' => [null, ''],
    'an array' => [['tags'], ''],
]);

it('collects the terms fields of every replicator set, indexed as unsaved rows', function (): void {
    $replicator = new Field('rows', ['type' => 'replicator', 'sets' => [
        'main' => ['sets' => [
            'first' => ['fields' => sosTermsLikeTopicsFields()],
            'second' => ['fields' => sosTermsLikeTopicsFields()],
            'plain' => ['fields' => [['handle' => 'title', 'field' => ['type' => 'text']]]],
        ]],
    ]]);

    $grouped = sosTermsLikeCollect($replicator);

    expect(array_keys($grouped))->toBe(['topics']);
    expect($grouped['topics'])->toHaveCount(2);
    foreach ($grouped['topics'] as $child) {
        expect($child->handle())->toBe('topics');
        expect($child->parentField())->toBe($replicator);
        expect($child->parentIndex())->toBe(-1);
    }
});

it('collects the terms fields of a grid, indexed as an unsaved row', function (): void {
    $grid = new Field('table', ['type' => 'grid', 'fields' => sosTermsLikeTopicsFields()]);

    $grouped = sosTermsLikeCollect($grid);

    expect(array_keys($grouped))->toBe(['topics']);
    expect($grouped['topics'])->toHaveCount(1);
    expect($grouped['topics'][0]->parentIndex())->toBe(-1);
});

it('collects the terms fields of a group', function (): void {
    $group = new Field('details', ['type' => 'group', 'fields' => sosTermsLikeTopicsFields()]);

    $grouped = sosTermsLikeCollect($group);

    expect(array_keys($grouped))->toBe(['topics']);
    expect($grouped['topics'])->toHaveCount(1);
    expect($grouped['topics'][0]->handle())->toBe('topics');
});

it('keys a terms augment parent with an id by its class and id', function (): void {
    /** @var LocalizedTerm&MockInterface $parent */
    $parent = Mockery::mock(LocalizedTerm::class);
    $parent->shouldReceive('id')->andReturn('term-1');

    expect(sosTermsLikeCall('memoKeyForTermsAugmentParent', $parent))->toBe($parent::class.'::term-1');
});

it('keys a terms augment on the parent, field handle, field config and stored value', function (mixed $handle, string $handleInKey, mixed $present, string $payloadSig): void {
    /** @var LocalizedTerm&MockInterface $parent */
    $parent = Mockery::mock(LocalizedTerm::class);
    $parent->shouldReceive('id')->andReturn('term-1');
    $config = ['type' => 'terms', 'taxonomies' => ['tags'], 'max_items' => 1];
    /** @var Field&MockInterface $field */
    $field = Mockery::mock(Field::class);
    $field->shouldReceive('handle')->andReturn($handle);
    $field->shouldReceive('config')->andReturn($config);

    $expected = hash('xxh128', $parent::class.'::term-1'."\0".$handleInKey."\0".hash('xxh128', serialize($config))."\0".$payloadSig);

    expect(sosTermsLikeCall('termsLikeAugmentMemoItemKey', $field, $parent, $present))->toBe($expected);
})->with([
    'a slug' => ['tags', 'tags', 'alpha', 's:alpha'],
    'a slug in a Value' => ['tags', 'tags', new Value('alpha'), 's:alpha'],
    'a slug list, sorted' => ['tags', 'tags', ['beta', 'alpha'], "l:alpha\0beta"],
    'a null handle' => [null, '', 'alpha', 's:alpha'],
    'an array handle' => [['tags'], '', 'alpha', 's:alpha'],
]);

/**
 * Seed one row of the raw term slug hydration memo.
 */
function sosTermsLikeSeedSlugMemo(string $key, mixed $row): void
{
    $memo = request()->attributes->get('_app_schema_output_raw_term_slug_hydration_memo', []);
    $memo = is_array($memo) ? $memo : [];
    $memo[$key] = $row;
    request()->attributes->set('_app_schema_output_raw_term_slug_hydration_memo', $memo);
}

it('reuses a memoized hydration keyed on the stored value', function (mixed $present, string $key): void {
    sosTermsLikeSeedSlugMemo($key, ['in' => $present, 'out' => 'memoized']);

    expect(sosTermsLikeCall('memoizedHydrateRawTermSlugValueToAugmentedValue', $present))->toBe('memoized');
})->with([
    'a slug' => ['alpha', 's'.'alpha'],
    'a slug list' => [['alpha', 'beta'], 'l'.hash('xxh128', "alpha\0beta")],
]);

it('reuses a memoized hydration keyed on the identity of an object', function (): void {
    $present = new stdClass;
    sosTermsLikeSeedSlugMemo('o'.spl_object_id($present), ['in' => $present, 'out' => 'memoized']);

    expect(sosTermsLikeCall('memoizedHydrateRawTermSlugValueToAugmentedValue', $present))->toBe('memoized');
});

it('hydrates without the memo a value no memo key fits', function (mixed $present, string $key): void {
    sosTermsLikeSeedSlugMemo($key, ['in' => $present, 'out' => 'memoized']);

    expect(sosTermsLikeCall('memoizedHydrateRawTermSlugValueToAugmentedValue', $present))->toBe($present);
})->with([
    'an empty string' => ['', 's'],
    'an empty list' => [[], 'l'.hash('xxh128', '')],
    'a keyed array' => [['a' => 'alpha'], 'l'.hash('xxh128', 'alpha')],
    'a list with an empty slug' => [['alpha', ''], 'l'.hash('xxh128', 'alpha')],
    'a list with a non-string before a slug' => [['alpha', 7, 'beta'], 'l'.hash('xxh128', 'beta')],
    'an integer' => [5, 's5'],
]);

it('hydrates without the memo when the memo row does not match the stored value', function (mixed $row): void {
    $present = new stdClass;
    sosTermsLikeSeedSlugMemo('o'.spl_object_id($present), $row);

    expect(sosTermsLikeCall('memoizedHydrateRawTermSlugValueToAugmentedValue', $present))->toBe($present);
})->with([
    'not an array' => ['memoized'],
    'no input' => [['out' => 'memoized']],
    'no output' => [['in' => 'x']],
    'another object' => fn (): array => [['in' => new stdClass, 'out' => 'memoized']],
]);

it('stores the input and the hydrated output in the memo row', function (): void {
    $present = new stdClass;

    expect(sosTermsLikeCall('memoizedHydrateRawTermSlugValueToAugmentedValue', $present))->toBe($present);

    $memo = request()->attributes->get('_app_schema_output_raw_term_slug_hydration_memo');
    expect($memo)->toBe(['o'.spl_object_id($present) => ['in' => $present, 'out' => $present]]);
});
