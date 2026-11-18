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
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Contracts\Query\Builder as QueryBuilderContract;
use Statamic\Contracts\Taxonomies\Term;
use Statamic\Entries\Entry as EntryModel;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;
use Statamic\Fields\Fields;
use Statamic\Fields\Value;
use Statamic\Taxonomies\LocalizedTerm;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(SchemaOutputService::class);

/**
 * Pins how {@see SchemaOutputService} hydrates `terms` and `terms_search_index`
 * fields in a schema template's Antlers context:
 * `hydrateTermsLikeFieldValuesForSchemaAntlersContext()` decides which stored
 * values to replace, `replaceTermsLikeFieldContextWithAugmentedTerms()` turns the
 * field's augmented value into a loopable term collection, and
 * `hydrateTermsLikeFieldsIfSupportedForSchemaAntlersContext()` skips an entity it
 * cannot augment. Also pins the two early nulls of
 * `getSlotEntryForCurrentContext()`. The entity, its blueprint and its augmented
 * values are mocks, so each branch is asserted on its own.
 *
 * @command  php artisan test --compact tests/Feature/Services/SchemaTemplates/SchemaOutputServiceTermsHydrationTest.php
 */

/**
 * An entry mock whose blueprint holds the given fields and whose augmented
 * values come from the given map.
 *
 * @param  array<string, string>  $fields  Fieldtype by handle.
 * @param  array<string, mixed>  $augmented  Augmented value by handle.
 * @param  list<string>  $augmentedLookups  Each handle augmented, by reference.
 */
function sosTermsEntry(array $fields, array $augmented = [], array &$augmentedLookups = []): EntryModel&MockInterface
{
    $fieldMocks = [];
    foreach ($fields as $handle => $type) {
        /** @var Field&MockInterface $field */
        $field = Mockery::mock(Field::class);
        $field->shouldReceive('handle')->andReturn($handle);
        $field->shouldReceive('type')->andReturn($type);
        $fieldMocks[$handle] = $field;
    }

    /** @var Fields&MockInterface $fieldsMock */
    $fieldsMock = Mockery::mock(Fields::class);
    $fieldsMock->shouldReceive('all')->andReturn(collect($fieldMocks));

    /** @var Blueprint&MockInterface $blueprint */
    $blueprint = Mockery::mock(Blueprint::class);
    $blueprint->shouldReceive('fields')->andReturn($fieldsMock);

    /** @var EntryModel&MockInterface $entry */
    $entry = Mockery::mock(EntryModel::class);
    $entry->shouldReceive('blueprint')->andReturn($blueprint);
    $entry->shouldReceive('augmentedValue')->andReturnUsing(function (string $handle) use ($augmented, &$augmentedLookups): mixed {
        $augmentedLookups[] = $handle;

        return $augmented[$handle] ?? null;
    });

    return $entry;
}

/**
 * Hydrate a context through `hydrateTermsLikeFieldValuesForSchemaAntlersContext()`.
 *
 * @param  array<string, mixed>  $context  The Antlers context.
 * @return array<string, mixed>
 */
function sosTermsHydrate(EntryModel $entry, array $context): array
{
    return mustBeStringKeyed(
        (new ReflectionMethod(SchemaOutputService::class, 'hydrateTermsLikeFieldValuesForSchemaAntlersContext'))
            ->invoke(app(SchemaOutputService::class), $entry, $context),
        'the hydrated context'
    );
}

/**
 * A term query builder mock whose `get()` returns the given terms.
 *
 * @template TTerm
 *
 * @param  Collection<int, TTerm>  $terms  The terms `get()` returns.
 */
function sosTermsQuery(Collection $terms): QueryBuilderContract&MockInterface
{
    /** @var QueryBuilderContract&MockInterface $query */
    $query = Mockery::mock(QueryBuilderContract::class);
    $query->shouldReceive('get')->andReturn($terms);

    return $query;
}

it('leaves a terms field the context does not hold', function (): void {
    $lookups = [];
    $entry = sosTermsEntry(['tags' => 'terms'], [], $lookups);

    expect(sosTermsHydrate($entry, ['title' => 'Post']))->toBe(['title' => 'Post']);
    expect($lookups)->toBe([]);
});

it('replaces a stored slug list with the terms its augmented query returns', function (string $type): void {
    /** @var Term&MockInterface $term */
    $term = Mockery::mock(Term::class);
    $terms = collect([$term]);
    $lookups = [];
    $entry = sosTermsEntry(['tags' => $type], ['tags' => new Value(sosTermsQuery($terms))], $lookups);

    $context = sosTermsHydrate($entry, ['tags' => ['alpha', 'beta']]);

    expect($context['tags'])->toBe($terms);
    expect($lookups)->toBe(['tags']);
})->with(['terms', 'terms_search_index']);

it('wraps the one term a single stored slug augments to', function (): void {
    /** @var Term&MockInterface $term */
    $term = Mockery::mock(Term::class);
    $entry = sosTermsEntry(['pronouns' => 'terms'], ['pronouns' => new Value($term)]);

    $context = sosTermsHydrate($entry, ['pronouns' => 'she-her']);

    expect($context['pronouns'])->toBeInstanceOf(Collection::class);
    expect(mustBe(Collection::class, $context['pronouns'], 'the hydrated pronouns')->all())->toBe([$term]);
});

it('gives an empty collection when the augmented value is null', function (): void {
    $entry = sosTermsEntry(['pronouns' => 'terms'], ['pronouns' => new Value(null)]);

    $context = sosTermsHydrate($entry, ['pronouns' => 'she-her']);

    expect(mustBe(Collection::class, $context['pronouns'], 'the hydrated pronouns')->all())->toBe([]);
});

it('keeps an augmented collection as the field value', function (): void {
    $terms = collect(['already-loaded']);
    $entry = sosTermsEntry(['tags' => 'terms'], ['tags' => new Value($terms)]);

    expect(sosTermsHydrate($entry, ['tags' => ['alpha']])['tags'])->toBe($terms);
});

it('keeps the stored value when augmentation gives no usable shape', function (mixed $augmented): void {
    $entry = sosTermsEntry(['tags' => 'terms'], ['tags' => $augmented]);

    expect(sosTermsHydrate($entry, ['tags' => ['alpha']])['tags'])->toBe(['alpha']);
})->with([
    'no Value wrapper' => ['alpha'],
    'a Value holding a string' => [new Value('alpha')],
    'a Value holding an array' => [new Value(['alpha'])],
]);

it('does not re-hydrate a list whose terms are already augmentable', function (): void {
    /** @var LocalizedTerm&MockInterface $loaded */
    $loaded = Mockery::mock(LocalizedTerm::class);
    $lookups = [];
    $entry = sosTermsEntry(['tags' => 'terms'], [], $lookups);

    expect(sosTermsHydrate($entry, ['tags' => [$loaded]])['tags'])->toBe([$loaded]);
    expect($lookups)->toBe([]);
});

it('does not hydrate an empty, blank or non-list stored value', function (mixed $stored): void {
    $lookups = [];
    $entry = sosTermsEntry(['tags' => 'terms'], [], $lookups);

    expect(sosTermsHydrate($entry, ['tags' => $stored])['tags'])->toBe($stored);
    expect($lookups)->toBe([]);
})->with([
    'empty list' => [[]],
    'blank string' => ['   '],
    'map' => [['a' => 'alpha']],
    'integer' => [7],
]);

it('skips terms hydration for an entity it cannot augment', function (): void {
    /** @var Term&MockInterface $entity */
    $entity = Mockery::mock(Term::class);
    $method = new ReflectionMethod(SchemaOutputService::class, 'hydrateTermsLikeFieldsIfSupportedForSchemaAntlersContext');

    expect($method->invoke(app(SchemaOutputService::class), $entity, ['tags' => ['alpha']]))->toBe(['tags' => ['alpha']]);
});

it('hydrates terms for an entry it can augment', function (): void {
    /** @var Term&MockInterface $term */
    $term = Mockery::mock(Term::class);
    $entry = sosTermsEntry(['tags' => 'terms'], ['tags' => new Value(sosTermsQuery(collect([$term])))]);
    $method = new ReflectionMethod(SchemaOutputService::class, 'hydrateTermsLikeFieldsIfSupportedForSchemaAntlersContext');

    $context = mustBeArray($method->invoke(app(SchemaOutputService::class), $entry, ['tags' => ['alpha']]), 'the hydrated context');

    expect(mustBe(Collection::class, $context['tags'], 'the hydrated tags')->all())->toBe([$term]);
});

/**
 * The service as a partial mock with the given cascade service.
 */
function sosTermsSlotService(SchemaTemplatesCascadeService $cascade): SchemaOutputService&MockInterface
{
    /** @var SchemaOutputService&MockInterface $service */
    $service = Mockery::mock(SchemaOutputService::class, [
        $cascade,
        app(SchemaClassificationsIdentifiersMerger::class),
        app(SchemaJsonLdAtIdDeduplication::class),
        app(SchemaJsonLdSchemaObjectEmissionCoordinator::class),
        app(JsonLdCompositionProfiler::class),
        app(MedicalOrganizationSubtypeAtTypeResolver::class),
        app(PodcastEpisodeAtTypeResolver::class),
    ])
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();

    return $service;
}

it('has no slot entry on a control panel route', function (): void {
    config(['statamic.cp.enabled' => true, 'statamic.cp.route' => 'cp']);
    app()->instance('request', Request::create('/cp/collections'));

    /** @var SchemaTemplatesCascadeService&MockInterface $cascade */
    $cascade = Mockery::mock(SchemaTemplatesCascadeService::class);
    $cascade->shouldNotReceive('resolve');
    $service = sosTermsSlotService($cascade);
    $service->shouldNotReceive('resolveEntity');

    expect($service->getSlotEntryForCurrentContext('webpage'))->toBeNull();
});

it('has no slot entry for a slot name it does not know', function (): void {
    app()->instance('request', Request::create('/about'));

    /** @var EntryModel&MockInterface $entity */
    $entity = Mockery::mock(EntryModel::class);
    /** @var SchemaTemplatesCascadeService&MockInterface $cascade */
    $cascade = Mockery::mock(SchemaTemplatesCascadeService::class);
    $cascade->shouldReceive('resolve')->once()->with($entity)->andReturn(['webpage_entry' => 'w-1']);
    $service = sosTermsSlotService($cascade);
    $service->shouldReceive('resolveEntity')->andReturn($entity);

    expect($service->getSlotEntryForCurrentContext('footer'))->toBeNull();
});
