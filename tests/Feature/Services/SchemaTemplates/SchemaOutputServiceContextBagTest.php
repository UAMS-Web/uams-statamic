<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaOutputService;
use App\Support\RequestAttributeMemo;
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
 * Pins three guards around the variable bag {@see SchemaOutputService} hands a
 * schema template's Antlers: `hydrateReferencedEntriesFieldsForSchemaAntlersContext()`
 * keeps an `entries` field's query builder when its query returns something
 * other than a collection, and skips an entity with no blueprint;
 * `buildAntlersContextFromEntity()` answers an empty bag when the request memo
 * holds something other than an array for the entity; and
 * `mergedContextTermsLikeValueNeedsBlueprintAwareAugment()` asks for augmentation
 * when a collection still holds an item that is not a term. The entity and its
 * blueprint are mocks.
 *
 * @command  php artisan test --compact tests/Feature/Services/SchemaTemplates/SchemaOutputServiceContextBagTest.php
 */

/**
 * An entry mock with an id and a blueprint holding the given fields, or no blueprint.
 *
 * @param  array<string, string>|null  $fields  Fieldtype by handle, or null for no blueprint.
 */
function sosBagEntry(string $id, ?array $fields): EntryModel&MockInterface
{
    /** @var EntryModel&MockInterface $entry */
    $entry = Mockery::mock(EntryModel::class);
    $entry->shouldReceive('id')->andReturn($id);

    if ($fields === null) {
        $entry->shouldReceive('blueprint')->andReturn(null);

        return $entry;
    }

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
    $entry->shouldReceive('blueprint')->andReturn($blueprint);

    return $entry;
}

/**
 * Hydrate the entries fields a template references.
 *
 * @param  array<string, mixed>  $context  The Antlers context.
 * @return array<string, mixed>
 */
function sosBagHydrateEntries(EntryModel $entity, array $context, string $template): array
{
    return mustBeStringKeyed(
        (new ReflectionMethod(SchemaOutputService::class, 'hydrateReferencedEntriesFieldsForSchemaAntlersContext'))
            ->invoke(app(SchemaOutputService::class), $entity, $context, $template),
        'the hydrated context'
    );
}

/**
 * A query builder mock whose `get()` returns the given value.
 */
function sosBagQuery(mixed $result): QueryBuilderContract&MockInterface
{
    /** @var QueryBuilderContract&MockInterface $query */
    $query = Mockery::mock(QueryBuilderContract::class);
    $query->shouldReceive('get')->andReturn($result);

    return $query;
}

it('loads the entries a referenced entries field queries', function (): void {
    $entries = collect(['entry-a']);
    $context = sosBagHydrateEntries(sosBagEntry('ctx-1', ['related' => 'entries']), ['related' => sosBagQuery($entries)], '{{ related }}{{ title }}{{ /related }}');

    expect($context['related'])->toBe($entries);
});

it('keeps the builder when the entries query does not return a collection', function (): void {
    $query = sosBagQuery(['entry-a']);
    $context = sosBagHydrateEntries(sosBagEntry('ctx-2', ['related' => 'entries']), ['related' => $query], '{{ related }}{{ title }}{{ /related }}');

    expect($context['related'])->toBe($query);
});

it('leaves the context alone for an entity with no blueprint', function (): void {
    $query = sosBagQuery(collect(['entry-a']));

    expect(sosBagHydrateEntries(sosBagEntry('ctx-3', null), ['related' => $query], '{{ related }}'))->toBe(['related' => $query]);
});

it('leaves the context alone for an entity with no blueprint accessor', function (): void {
    $query = sosBagQuery(collect(['entry-a']));
    $entity = new class implements Term
    {
        public function id(): string
        {
            return 'ctx-5';
        }
    };

    $context = (new ReflectionMethod(SchemaOutputService::class, 'hydrateReferencedEntriesFieldsForSchemaAntlersContext'))
        ->invoke(app(SchemaOutputService::class), $entity, ['related' => $query], '{{ related }}');

    expect($context)->toBe(['related' => $query]);
});

it('gives an empty bag when the request memo holds no array for the entity', function (): void {
    RequestAttributeMemo::remember('_app_schema_output_antlers_entity_context', 'ctx-4', static fn (): string => 'not an array');
    $method = new ReflectionMethod(SchemaOutputService::class, 'buildAntlersContextFromEntity');

    expect($method->invoke(app(SchemaOutputService::class), sosBagEntry('ctx-4', [])))->toBe([]);
});

it('asks for augmentation when a collection still holds an item that is not a term', function (mixed $present): void {
    $method = new ReflectionMethod(SchemaOutputService::class, 'mergedContextTermsLikeValueNeedsBlueprintAwareAugment');

    expect($method->invoke(app(SchemaOutputService::class), $present))->toBeTrue();
})->with([
    'a slug' => [collect(['alpha'])],
    'a slug after a term' => fn (): Collection => collect([Mockery::mock(LocalizedTerm::class), 'alpha']),
    'a Value holding a slug collection' => fn (): Value => new Value(collect(['alpha'])),
]);

it('asks for no augmentation when a collection holds only terms or nothing', function (mixed $present): void {
    $method = new ReflectionMethod(SchemaOutputService::class, 'mergedContextTermsLikeValueNeedsBlueprintAwareAugment');

    expect($method->invoke(app(SchemaOutputService::class), $present))->toBeFalse();
})->with([
    'empty' => [collect()],
    'terms only' => fn (): Collection => collect([Mockery::mock(LocalizedTerm::class)]),
]);
