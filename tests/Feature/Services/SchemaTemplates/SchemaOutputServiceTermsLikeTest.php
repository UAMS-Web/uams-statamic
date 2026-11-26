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
use Illuminate\Support\Collection;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Contracts\Data\Augmentable;
use Statamic\Contracts\Query\Builder as QueryBuilderContract;
use Statamic\Contracts\Taxonomies\Term;
use Statamic\Entries\Entry as EntryModel;
use Statamic\Facades\Term as TermFacade;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;
use Statamic\Fields\Value;
use Statamic\Fieldtypes\Terms as TermsFieldtype;
use Statamic\Taxonomies\LocalizedTerm;
use Statamic\Taxonomies\Term as TaxonomyTerm;
use Tests\Support\Doubles\SchemaTermsAugmentProbeFieldtype;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(SchemaOutputService::class);

/**
 * Pins how {@see SchemaOutputService} treats terms fields in the merged Antlers
 * context: which of several blueprint fields sharing a handle is chosen for a
 * stored value, which stored values still need a blueprint-aware augment, which
 * fields of a blueprint field (nested replicator sets, grids and groups included)
 * are terms fields, how a terms augment is keyed and run, when a raw slug
 * hydration is reused from the request memo, and how the primary related term
 * is found in the overlay.
 *
 * @command  php artisan test --compact tests/Feature/Services/SchemaTemplates/SchemaOutputServiceTermsLikeTest.php
 */

/**
 * The service, built from the container's collaborators and the given profiler.
 */
function sosTermsLikeService(?JsonLdCompositionProfiler $profiler = null): SchemaOutputService
{
    return new SchemaOutputService(
        app(SchemaTemplatesCascadeService::class),
        app(SchemaClassificationsIdentifiersMerger::class),
        app(SchemaJsonLdAtIdDeduplication::class),
        app(SchemaJsonLdSchemaObjectEmissionCoordinator::class),
        $profiler ?? app(JsonLdCompositionProfiler::class),
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
 *
 * @param  list<string>  $taxonomies  The taxonomies the field is configured with
 */
function sosTermsLikeField(string $kind, array $taxonomies = []): Field&MockInterface
{
    /** @var Field&MockInterface $field */
    $field = Mockery::mock(Field::class);
    $field->shouldReceive('handle')->andReturn('tags');
    $field->shouldReceive('get')->with('max_items')->andReturn($kind === 'single' ? 1 : null);
    $field->shouldReceive('get')->with('taxonomies')->andReturn($taxonomies);
    $field->shouldReceive('config')->andReturn(['type' => 'terms', 'max_items' => $kind === 'single' ? 1 : null]);

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

it('selects the same-handle field whose taxonomy holds the stored term', function (mixed $present, int $expectedIndex): void {
    // A degree row and a certification row both store their term under `activity`
    $candidates = [
        sosTermsLikeField('single', ['credentials']),
        sosTermsLikeField('single', ['certifications']),
    ];

    TermFacade::shouldReceive('find')->with('credentials::acls')->andReturnNull();
    TermFacade::shouldReceive('find')->with('certifications::acls')->andReturn(Mockery::mock(LocalizedTerm::class));
    TermFacade::shouldReceive('find')->with('credentials::md')->andReturn(Mockery::mock(LocalizedTerm::class));
    TermFacade::shouldReceive('find')->with('certifications::md')->andReturnNull();
    TermFacade::shouldReceive('find')->with('credentials::gone')->andReturnNull();
    TermFacade::shouldReceive('find')->with('certifications::gone')->andReturnNull();

    $selected = sosTermsLikeCall('selectTermsLikeBlueprintFieldForMergedValue', $candidates, $present);

    expect($selected)->toBe($candidates[$expectedIndex]);
})->with([
    'a certification slug' => ['acls', 1],
    'a certification slug in a Value' => [new Value('acls'), 1],
    'a certification slug list' => [['acls'], 1],
    'a padded certification slug' => [' acls ', 1],
    'a qualified certification id' => ['certifications::acls', 1],
    'a qualified id naming the other taxonomy' => ['credentials::acls', 0],
    'a degree slug' => ['md', 0],
    'a slug no taxonomy holds' => ['gone', 0],
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
    'a list with an empty slug' => [['alpha', ''], 'l'.hash('xxh128', "alpha\0")],
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

/**
 * Hydrate the overlay slugs of a merged context, with `alpha` memoized as hydrated.
 *
 * @param  array<string, mixed>  $context
 * @param  array<string, mixed>  $overlay
 */
function sosTermsLikeHydrateOverlay(array $context, array $overlay, string $template): mixed
{
    sosTermsLikeSeedSlugMemo('s'.'alpha', ['in' => 'alpha', 'out' => 'HYDRATED']);

    return sosTermsLikeCall('hydrateAntlersOverlayTermSlugValuesIfRaw', $context, $overlay, $template);
}

it('leaves an overlay key it skips and hydrates the key after it', function (string $skippedKey, bool $inContext): void {
    $context = ['tags' => 'ctx'] + ($inContext ? [$skippedKey => 'ctx'] : []);
    $overlay = [$skippedKey => 'alpha', 'tags' => 'alpha'];

    $expected = ['tags' => 'HYDRATED'] + ($inContext ? [$skippedKey => 'ctx'] : []);

    expect(sosTermsLikeHydrateOverlay($context, $overlay, '{{ schema_primary_related_taxonomy_term }}'))->toEqual($expected);
})->with([
    'an empty key' => ['', true],
    'the template instance index' => [SchemaOutputService::ANTLERS_SCHEMA_TEMPLATE_INSTANCE_INDEX, true],
    'the type override' => [SchemaOutputService::ANTLERS_SCHEMA_TEMPLATE_TYPE_OVERRIDE, true],
    'the emit context' => [SchemaOutputService::ANTLERS_SCHEMA_TEMPLATE_EMIT_CONTEXT, true],
    'the template entry id' => [SchemaOutputService::ANTLERS_SCHEMA_TEMPLATE_ENTRY_ID, true],
    'the property key' => [SchemaOutputService::ANTLERS_SCHEMA_PROPERTY_KEY, true],
    'the primary related term' => [SchemaOutputService::ANTLERS_SCHEMA_PRIMARY_RELATED_TAXONOMY_TERM, true],
    'a key absent from the context' => ['absent', false],
]);

it('leaves an overlay value that needs no augment and hydrates the key after it', function (): void {
    $result = sosTermsLikeHydrateOverlay(['count' => 'ctx', 'tags' => 'ctx'], ['count' => 5, 'tags' => 'alpha'], '{{ count }}{{ tags }}');

    expect($result)->toBe(['count' => 'ctx', 'tags' => 'HYDRATED']);
});

it('hydrates only the overlay keys the template reads', function (): void {
    $result = sosTermsLikeHydrateOverlay(['other' => 'ctx', 'tags' => 'ctx', 'topics' => 'ctx'], ['other' => 'alpha', 'tags' => 'alpha', 'topics' => 'alpha'], '{{ tags }}{{ if topics }}x{{ /if }}');

    expect($result)->toBe(['other' => 'ctx', 'tags' => 'HYDRATED', 'topics' => 'HYDRATED']);
});

it('hydrates every overlay key when the template reads the primary related term', function (): void {
    $result = sosTermsLikeHydrateOverlay(['other' => 'ctx'], ['other' => 'alpha'], '{{ schema_primary_related_taxonomy_term }}');

    expect($result)->toBe(['other' => 'HYDRATED']);
});

it('returns the merged context unchanged for an empty overlay', function (): void {
    expect(sosTermsLikeHydrateOverlay(['tags' => 'alpha'], [], '{{ tags }}'))->toBe(['tags' => 'alpha']);
});

/**
 * A terms augment parent double with a readable id.
 */
function sosTermsLikeParent(string $class, string $id): EntryModel|LocalizedTerm
{
    /** @var (EntryModel&MockInterface)|(LocalizedTerm&MockInterface) $parent */
    $parent = Mockery::mock($class);
    $parent->shouldReceive('id')->andReturn($id);

    return $parent;
}

/**
 * Seed the terms augment memo for one parent, field and stored value.
 */
function sosTermsLikeSeedAugmentMemo(Field $field, EntryModel|LocalizedTerm $parent, mixed $present, mixed $augmented): void
{
    $key = mustBeString(sosTermsLikeCall('termsLikeAugmentMemoItemKey', $field, $parent, $present), 'the augment memo key');
    $memo = request()->attributes->get('_app_schema_output_terms_like_augment_memo', []);
    $memo = is_array($memo) ? $memo : [];
    $memo[$key] = $augmented;
    request()->attributes->set('_app_schema_output_terms_like_augment_memo', $memo);
}

it('augments a stored slug through its blueprint field for an entry or localized term', function (string $parentClass): void {
    $field = sosTermsLikeField('multi');
    $parent = sosTermsLikeParent($parentClass, 'parent-1');
    $term = sosTermsLikeStoredValue('TAXONOMY_TERM');
    sosTermsLikeSeedAugmentMemo($field, $parent, 'alpha', $term);
    sosTermsLikeSeedSlugMemo('s'.'alpha', ['in' => 'alpha', 'out' => 'HYDRATED']);

    $result = sosTermsLikeCall('hydrateMergedContextTermsLikeFieldHandleValue', $field, $parent, 'alpha');

    expect($result)->toBeInstanceOf(Collection::class);
    expect(mustBe(Collection::class, $result, 'the augmented terms')->all())->toBe([$term]);
})->with([
    'an entry' => [EntryModel::class],
    'a localized term' => [LocalizedTerm::class],
]);

it('hydrates a stored value as raw slugs without a blueprint field, an entry or localized term, or a slug', function (bool $withField, ?string $parentClass, mixed $present, mixed $expected): void {
    $field = $withField ? sosTermsLikeField('multi') : null;
    $parent = match ($parentClass) {
        null => null,
        'TAXONOMY_TERM' => mustBe(TaxonomyTerm::class, sosTermsLikeStoredValue('TAXONOMY_TERM'), 'the parent term'),
        default => sosTermsLikeParent($parentClass, 'parent-1'),
    };
    if ($field instanceof Field && ($parent instanceof EntryModel || $parent instanceof LocalizedTerm)) {
        sosTermsLikeSeedAugmentMemo($field, $parent, $present, 'AUGMENTED');
    }
    sosTermsLikeSeedSlugMemo('s'.'alpha', ['in' => 'alpha', 'out' => 'HYDRATED']);

    expect(sosTermsLikeCall('hydrateMergedContextTermsLikeFieldHandleValue', $field, $parent, $present))->toBe($expected);
})->with([
    'no blueprint field' => [false, LocalizedTerm::class, 'alpha', 'HYDRATED'],
    'no parent' => [true, null, 'alpha', 'HYDRATED'],
    'a taxonomy term parent' => [true, 'TAXONOMY_TERM', 'alpha', 'HYDRATED'],
    'a value that needs no augment' => [true, LocalizedTerm::class, 5, 5],
]);

it('hydrates each grouped terms field present in the merged context', function (): void {
    $field = sosTermsLikeField('multi');
    sosTermsLikeSeedSlugMemo('s'.'alpha', ['in' => 'alpha', 'out' => 'HYDRATED']);
    $grouped = ['' => [$field], 'absent' => [$field], 'tags' => [$field]];

    $result = sosTermsLikeCall('hydrateMergedContextTermsLikeFieldsIfRawSlugs', null, ['' => 'alpha', 'tags' => 'alpha'], $grouped);

    expect($result)->toBe(['' => 'alpha', 'tags' => 'HYDRATED']);
});

it('returns the merged context unchanged with no grouped terms fields', function (): void {
    expect(sosTermsLikeCall('hydrateMergedContextTermsLikeFieldsIfRawSlugs', null, ['tags' => 'alpha'], []))->toBe(['tags' => 'alpha']);
});

it('shapes an augmented terms value as a loopable collection', function (mixed $augmented, Closure $expect): void {
    $expect(sosTermsLikeCall('normalizeTermsLikeAugmentedResolutionToAntlersLoopableCollection', sosTermsLikeStoredValue($augmented)));
})->with([
    'nothing' => [null, fn (mixed $result) => expect($result)->toEqual(collect())],
    'a taxonomy term' => ['TAXONOMY_TERM', fn (mixed $result) => expect(mustBe(Collection::class, $result, 'the terms')->all())->toHaveCount(1)->each->toBeInstanceOf(TaxonomyTerm::class)],
    'a localized term' => ['LOCALIZED_TERM', fn (mixed $result) => expect(mustBe(Collection::class, $result, 'the terms')->all())->toHaveCount(1)->each->toBeInstanceOf(LocalizedTerm::class)],
    'a collection' => [collect(['x']), fn (mixed $result) => expect($result)->toEqual(collect(['x']))],
    'a slug' => ['alpha', fn (mixed $result) => expect($result)->toBe('alpha')],
]);

it('reads an augmented query builder into its results', function (): void {
    /** @var QueryBuilderContract&MockInterface $builder */
    $builder = Mockery::mock(QueryBuilderContract::class);
    $builder->shouldReceive('get')->once()->andReturn(collect(['x']));

    expect(sosTermsLikeCall('normalizeTermsLikeAugmentedResolutionToAntlersLoopableCollection', $builder))->toEqual(collect(['x']));
});

/**
 * A localized term double whose `term()` answers the given value.
 */
function sosTermsLikeLocalized(mixed $term): LocalizedTerm&MockInterface
{
    /** @var LocalizedTerm&MockInterface $localized */
    $localized = Mockery::mock(LocalizedTerm::class);
    $localized->shouldReceive('term')->andReturn($term);

    return $localized;
}

it('unwraps the first taxonomy term of a terms value', function (Closure $make, bool $found): void {
    $term = mustBe(TaxonomyTerm::class, sosTermsLikeStoredValue('TAXONOMY_TERM'), 'the term');

    expect(sosTermsLikeCall('unwrapFirstTaxonomyTermFromSchemaContextValue', $make($term)))->toBe($found ? $term : null);
})->with([
    'a term' => [fn (TaxonomyTerm $term): TaxonomyTerm => $term, true],
    'a term in a Value' => [fn (TaxonomyTerm $term): Value => new Value($term), true],
    'a localized term' => [fn (TaxonomyTerm $term): LocalizedTerm => sosTermsLikeLocalized($term), true],
    'a localized term with no term' => [fn (TaxonomyTerm $term): LocalizedTerm => sosTermsLikeLocalized(null), false],
    'a localized term with another term object' => [fn (TaxonomyTerm $term): LocalizedTerm => sosTermsLikeLocalized(new stdClass), false],
    'a collection led by a term' => [fn (TaxonomyTerm $term): Collection => collect([$term, 'x']), true],
    'a collection led by a term in a Value' => [fn (TaxonomyTerm $term): Collection => collect([new Value($term)]), true],
    'a collection led by a localized term' => [fn (TaxonomyTerm $term): Collection => collect([sosTermsLikeLocalized($term)]), true],
    'a collection led by a localized term with another term object' => [fn (TaxonomyTerm $term): Collection => collect([sosTermsLikeLocalized(new stdClass)]), false],
    'a collection led by a slug' => [fn (TaxonomyTerm $term): Collection => collect(['x', $term]), false],
    'an empty collection' => [fn (TaxonomyTerm $term): Collection => collect(), false],
    'a slug' => [fn (TaxonomyTerm $term): string => 'x', false],
]);

it('resolves the primary related term from the first overlay key, in sorted order, holding one', function (string $skippedKey, bool $inContext): void {
    $term = mustBe(TaxonomyTerm::class, sosTermsLikeStoredValue('TAXONOMY_TERM'), 'the term');
    $skippedTerm = sosTermsLikeStoredValue('TAXONOMY_TERM');
    $context = ['tags' => $term] + ($inContext ? [$skippedKey => $skippedTerm] : []);
    $overlay = ['tags' => 'x', $skippedKey => 'x'];

    expect(sosTermsLikeCall('resolveSchemaPrimaryRelatedTaxonomyTermFromAntlersOverlay', $context, $overlay))->toBe($term);
})->with([
    'an empty key' => ['', true],
    'a reserved key' => [SchemaOutputService::ANTLERS_SCHEMA_PROPERTY_KEY, true],
    'a key absent from the context' => ['absent', false],
    'a key holding no term' => ['alpha', false],
]);

it('resolves no primary related term from an empty overlay', function (): void {
    expect(sosTermsLikeCall('resolveSchemaPrimaryRelatedTaxonomyTermFromAntlersOverlay', ['tags' => sosTermsLikeStoredValue('TAXONOMY_TERM')], []))->toBeNull();
});

it('injects the default-locale value of the primary related term as it is when it is not a plain localized term', function (Closure $make): void {
    $presented = $make();
    /** @var TaxonomyTerm&MockInterface $term */
    $term = Mockery::mock(TaxonomyTerm::class);
    $term->shouldReceive('inDefaultLocale')->andReturn($presented);

    $context = sosTermsLikeCall('injectSchemaPrimaryRelatedTaxonomyTermFromAntlersOverlay', ['tags' => $term], ['tags' => 'x']);

    expect(mustBeStringKeyed($context, 'the context')[SchemaOutputService::ANTLERS_SCHEMA_PRIMARY_RELATED_TAXONOMY_TERM])->toBe($presented);
})->with([
    'a localized term double' => [fn (): LocalizedTerm => sosTermsLikeLocalized(null)],
    'a non-object' => [fn (): string => 'presented'],
]);

it('leaves the context unchanged when no overlay key holds a term', function (array $overlay): void {
    expect(sosTermsLikeCall('injectSchemaPrimaryRelatedTaxonomyTermFromAntlersOverlay', ['tags' => 'x'], $overlay))->toBe(['tags' => 'x']);
})->with([
    'an empty overlay' => [[]],
    'an overlay key holding a slug' => [['tags' => 'x']],
]);

/**
 * A blueprint field double with the given handle and fieldtype handle.
 */
function sosTermsLikeTypedField(mixed $handle, mixed $type): Field&MockInterface
{
    /** @var Field&MockInterface $field */
    $field = Mockery::mock(Field::class);
    $field->shouldReceive('handle')->andReturn($handle);
    $field->shouldReceive('type')->andReturn($type);

    return $field;
}

/**
 * A query builder double whose results are the given items.
 *
 * @param  list<mixed>  $items
 */
function sosTermsLikeBuilder(array $items): QueryBuilderContract&MockInterface
{
    /** @var QueryBuilderContract&MockInterface $builder */
    $builder = Mockery::mock(QueryBuilderContract::class);
    $builder->shouldReceive('get')->andReturn(collect($items));

    return $builder;
}

/**
 * Hydrate the terms fields of a merged context for an entity whose blueprint holds the given fields.
 *
 * @param  list<Field>  $fields
 * @param  array<string, mixed>  $context
 * @return array<string, mixed>
 */
function sosTermsLikeHydrateFields(array $fields, array $context): array
{
    /** @var Blueprint&MockInterface $blueprint */
    $blueprint = Mockery::mock(Blueprint::class);
    $blueprint->shouldReceive('fields->all')->andReturn(collect($fields));
    /** @var LocalizedTerm&MockInterface $entity */
    $entity = Mockery::mock(LocalizedTerm::class);
    $entity->shouldReceive('blueprint')->andReturn($blueprint);

    return mustBeStringKeyed(sosTermsLikeCall('hydrateTermsLikeFieldValuesForSchemaAntlersContext', $entity, $context), 'the hydrated context');
}

it('reads a terms field query builder after a field it skips', function (Closure $skipped, array $context): void {
    $result = sosTermsLikeHydrateFields(
        [mustBe(Field::class, $skipped(), 'the skipped field'), sosTermsLikeTypedField('tags', 'terms_search_index')],
        mustBeStringKeyed($context, 'the context') + ['tags' => sosTermsLikeBuilder(['tag'])]
    );

    expect($result['tags'])->toEqual(collect(['tag']));
})->with([
    'a terms field absent from the context' => [fn (): Field => sosTermsLikeTypedField('absent', 'terms'), []],
    'a field of another type' => [fn (): Field => sosTermsLikeTypedField('title', 'text'), ['title' => sosTermsLikeBuilder(['x'])]],
    'a terms field already holding term models' => [fn (): Field => sosTermsLikeTypedField('topics', 'terms'), ['topics' => [Mockery::mock(Augmentable::class)]]],
    'a terms field read from a query builder' => [fn (): Field => sosTermsLikeTypedField('topics', 'terms'), ['topics' => sosTermsLikeBuilder(['topic'])]],
]);

it('reads the context of a terms field under its scalar handle as a string, and under an empty string otherwise', function (mixed $handle, string $key): void {
    $result = sosTermsLikeHydrateFields([sosTermsLikeTypedField($handle, 'terms')], [$key => sosTermsLikeBuilder(['tag'])]);

    expect($result[$key])->toEqual(collect(['tag']));
})->with([
    'a float' => [1.5, '1.5'],
    'null' => [null, ''],
]);

it('matches the terms fieldtype handles strictly', function (): void {
    $builder = sosTermsLikeBuilder(['tag']);

    expect(sosTermsLikeHydrateFields([sosTermsLikeTypedField('tags', true)], ['tags' => $builder]))->toBe(['tags' => $builder]);
});

/**
 * A blueprint field of the probe fieldtype, nested in a replicator at the given index.
 *
 * @return array{Field, Field}
 */
function sosTermsLikeProbeField(int $index): array
{
    SchemaTermsAugmentProbeFieldtype::register();
    $parentField = new Field('rows', ['type' => 'replicator']);
    $field = new Field('tags', ['type' => SchemaTermsAugmentProbeFieldtype::handle()]);
    $field->setParentField($parentField, $index);

    return [$field, $parentField];
}

it('augments a stored value through a copy of the blueprint field on the parent', function (mixed $present): void {
    [$field, $parentField] = sosTermsLikeProbeField(3);
    $parent = sosTermsLikeParent(LocalizedTerm::class, 'term-1');

    $augmented = sosTermsLikeCall('hydrateMergedContextTermsLikeValueViaTermsBlueprintFieldAugmentUncached', $field, $parent, $present);

    expect($augmented)->toBe([
        'value' => 'alpha',
        'parent' => $parent,
        'parentField' => $parentField,
        'parentIndex' => 3,
        'fieldValue' => 'alpha',
    ]);
    expect($field->value())->toBeNull();
})->with([
    'a slug' => ['alpha'],
    'a slug in a Value' => [new Value('alpha')],
]);

it('augments a stored value once per request and records the uncached time in milliseconds', function (): void {
    [$field] = sosTermsLikeProbeField(0);
    $parent = sosTermsLikeParent(LocalizedTerm::class, 'term-1');
    $samples = [];
    /** @var JsonLdCompositionProfiler&MockInterface $profiler */
    $profiler = Mockery::mock(JsonLdCompositionProfiler::class);
    $profiler->shouldReceive('recordAugmentUncachedSample')->andReturnUsing(function (float $ms) use (&$samples): void {
        $samples[] = $ms;
    });
    $service = sosTermsLikeService($profiler);
    $method = new ReflectionMethod(SchemaOutputService::class, 'hydrateMergedContextTermsLikeValueViaTermsBlueprintFieldAugment');
    SchemaTermsAugmentProbeFieldtype::$sleepMicroseconds = 20_000;

    try {
        $first = $method->invoke($service, $field, $parent, 'alpha');
        $second = $method->invoke($service, $field, $parent, 'alpha');
    } finally {
        SchemaTermsAugmentProbeFieldtype::$sleepMicroseconds = 0;
    }

    expect($second)->toBe($first);
    expect(mustBeStringKeyed($first, 'the augmented value')['value'])->toBe('alpha');
    expect($samples)->toHaveCount(1);
    expect($samples[0])->toBeGreaterThanOrEqual(10.0)->toBeLessThan(60000.0);
});

it('keeps the grouped terms fields a template names, or all of them when it names none', function (string $template, array $expectedHandles): void {
    $grouped = ['tags' => [sosTermsLikeField('multi')], 'topics' => [sosTermsLikeField('multi')]];

    expect(array_keys(mustBeArray(sosTermsLikeCall('filterGroupedTermsLikeBlueprintFieldsByAntlersTemplateReferences', $grouped, $template), 'the filtered fields')))->toBe($expectedHandles);
})->with([
    'one named' => ['{{ tags }}{{ title }}', ['tags']],
    'none named' => ['{{ title }}', ['tags', 'topics']],
    'no identifiers' => ['plain text', ['tags', 'topics']],
]);

it('keeps no grouped terms fields when there are none', function (): void {
    expect(sosTermsLikeCall('filterGroupedTermsLikeBlueprintFieldsByAntlersTemplateReferences', [], '{{ tags }}'))->toBe([]);
});

it('collects the terms fields of an entity blueprint, grouped by handle', function (): void {
    $tags = new Field('tags', ['type' => 'terms', 'taxonomies' => ['tags']]);
    /** @var Blueprint&MockInterface $blueprint */
    $blueprint = Mockery::mock(Blueprint::class);
    $blueprint->shouldReceive('fields->all')->andReturn(collect([$tags, new Field('title', ['type' => 'text'])]));
    /** @var EntryModel&MockInterface $entity */
    $entity = Mockery::mock(EntryModel::class);
    $entity->shouldReceive('blueprint')->andReturn($blueprint);

    expect(sosTermsLikeCall('collectTermsLikeBlueprintFieldsGroupedByHandleUncached', $entity))->toBe(['tags' => [$tags]]);
});

it('replaces a terms field with the loopable terms its augmented value holds', function (Closure $augmented, Closure $expect): void {
    /** @var LocalizedTerm&MockInterface $entity */
    $entity = Mockery::mock(LocalizedTerm::class);
    $entity->shouldReceive('augmentedValue')->with('tags')->andReturn($augmented());
    $context = ['tags' => 'raw'];

    $method = new ReflectionMethod(SchemaOutputService::class, 'replaceTermsLikeFieldContextWithAugmentedTerms');
    $method->invokeArgs(sosTermsLikeService(), [$entity, 'tags', &$context]);

    $expect($context['tags']);
})->with([
    'a query builder' => [fn (): Value => new Value(sosTermsLikeBuilder(['tag'])), fn (mixed $tags) => expect($tags)->toEqual(collect(['tag']))],
    'one term' => [fn (): Value => new Value(sosTermsLikeStoredValue('TAXONOMY_TERM')), fn (mixed $tags) => expect(mustBe(Collection::class, $tags, 'the terms')->all())->toHaveCount(1)->each->toBeInstanceOf(TaxonomyTerm::class)],
    'nothing' => [fn (): Value => new Value(null), fn (mixed $tags) => expect($tags)->toEqual(collect())],
    'a collection' => [fn (): Value => new Value(collect(['tag'])), fn (mixed $tags) => expect($tags)->toEqual(collect(['tag']))],
    'another value' => [fn (): Value => new Value('slug'), fn (mixed $tags) => expect($tags)->toBe('raw')],
    'no Value' => [fn (): string => 'not a Value', fn (mixed $tags) => expect($tags)->toBe('raw')],
]);

it('scans an empty template when a terms field pair pattern fails', function (): void {
    // A limit of 100 lets the handle check pass and fails only the replace over the
    // long body
    $limit = ini_get('pcre.backtrack_limit');
    ini_set('pcre.backtrack_limit', '100');

    try {
        $stripped = sosTermsLikeCall('stripTermsLikeFieldPairBodiesForFillMissingAnalysis', '{{ tags }}'.str_repeat('x', 2000).'{{ /tags }}', ['tags']);
    } finally {
        ini_set('pcre.backtrack_limit', $limit === false ? '1000000' : $limit);
    }

    expect($stripped)->toBe('');
});
