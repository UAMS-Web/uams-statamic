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
use Statamic\Entries\Entry as EntryModel;
use Statamic\Facades\Entry as EntryFacade;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(SchemaOutputService::class);

/**
 * Pins the branches of {@see SchemaOutputService::getEffectiveSchema()} that the
 * rendering suites do not reach: a properties value that is not an array, a
 * revisited entry with no type, a structure parent that cannot be found or has
 * no effective schema, an entry with no type anywhere, and `inherit_from` ids that
 * are already visited, missing, or have no effective schema. Also pins the
 * `inherit_from` id normalizer and the inherited-property filter for a missing
 * or unknown recipient type. The structure parent is stubbed on a partial mock
 * and `Entry::find()` by a partial facade mock.
 *
 * @command  php artisan test --compact tests/Feature/Services/SchemaTemplates/SchemaOutputServiceEffectiveSchemaTest.php
 */

/**
 * The service as a partial mock, with each entry's structure parent given by id.
 *
 * @param  array<array-key, string|null>  $parents  Parent id by entry id (a numeric id is an int key).
 */
function sosEffService(array $parents = []): SchemaOutputService&MockInterface
{
    /** @var SchemaOutputService&MockInterface $service */
    $service = Mockery::mock(SchemaOutputService::class, [
        app(SchemaTemplatesCascadeService::class),
        app(SchemaClassificationsIdentifiersMerger::class),
        app(SchemaJsonLdAtIdDeduplication::class),
        app(SchemaJsonLdSchemaObjectEmissionCoordinator::class),
        app(JsonLdCompositionProfiler::class),
        app(MedicalOrganizationSubtypeAtTypeResolver::class),
        app(PodcastEpisodeAtTypeResolver::class),
    ])
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();

    $service->shouldReceive('getStructureParentId')
        ->andReturnUsing(function (EntryModel $entry) use ($parents): ?string {
            $id = $entry->id();

            return \is_string($id) ? ($parents[$id] ?? null) : null;
        });

    return $service;
}

/**
 * A mocked entry with a stored `schema` and `inherit_from`.
 */
function sosEffEntry(mixed $id, mixed $schema, mixed $inheritFrom = null): EntryModel&MockInterface
{
    /** @var EntryModel&MockInterface $entry */
    $entry = Mockery::mock(EntryModel::class);
    $entry->shouldReceive('id')->andReturn($id);
    $entry->shouldReceive('get')->with('schema')->andReturn($schema);
    $entry->shouldReceive('get')->with('inherit_from')->andReturn($inheritFrom);

    return $entry;
}

/**
 * Make `Entry::find()` answer from the given map, and null for any other id.
 *
 * @param  array<string, EntryModel>  $entries  Found entries by id.
 * @param  list<string>  $lookups  Every id looked up, by reference.
 */
function sosEffFind(array $entries, array &$lookups = []): void
{
    EntryFacade::partialMock()
        ->shouldReceive('find')
        ->andReturnUsing(function (string $id) use ($entries, &$lookups): ?EntryModel {
            $lookups[] = $id;

            return $entries[$id] ?? null;
        });
}

/**
 * Call `getEffectiveSchema()`.
 *
 * @param  array<int, string>  $visited  Entry ids already visited.
 */
function sosEffResolve(SchemaOutputService $service, EntryModel $entry, array $visited = []): mixed
{
    return (new ReflectionMethod(SchemaOutputService::class, 'getEffectiveSchema'))->invoke($service, $entry, $visited);
}

it('reads a properties value that is not an array as no properties', function (): void {
    $entry = sosEffEntry('e-1', ['type' => 'Thing', 'properties' => 'name']);

    expect(sosEffResolve(sosEffService(), $entry))->toBe(['type' => 'Thing', 'properties' => []]);
});

it('returns nothing for a revisited entry that has no type', function (): void {
    $entry = sosEffEntry('e-1', ['properties' => ['name' => 'X']]);

    expect(sosEffResolve(sosEffService(), $entry, ['e-1']))->toBeNull();
});

it('keeps a revisited entry with a type as it is stored', function (): void {
    $entry = sosEffEntry('e-1', ['type' => 'Thing', 'properties' => ['name' => 'X']]);

    expect(sosEffResolve(sosEffService(['e-1' => 'p-1']), $entry, ['e-1']))->toBe(['type' => 'Thing', 'properties' => ['name' => 'X']]);
});

it('keeps the entry\'s own schema when its structure parent cannot be found', function (): void {
    $lookups = [];
    sosEffFind([], $lookups);
    $entry = sosEffEntry('e-1', ['type' => 'Thing', 'properties' => ['name' => 'X']]);

    expect(sosEffResolve(sosEffService(['e-1' => 'p-missing']), $entry))->toBe(['type' => 'Thing', 'properties' => ['name' => 'X']]);
    expect($lookups)->toBe(['p-missing']);
});

it('inherits nothing from a structure parent that has no effective schema', function (): void {
    $parent = sosEffEntry('p-1', ['properties' => ['description' => 'From parent']]);
    sosEffFind(['p-1' => $parent]);
    $entry = sosEffEntry('e-1', ['type' => 'Thing', 'properties' => ['name' => 'X']]);

    expect(sosEffResolve(sosEffService(['e-1' => 'p-1']), $entry))->toBe(['type' => 'Thing', 'properties' => ['name' => 'X']]);
});

it('returns nothing when neither the entry nor its parent has a type', function (): void {
    $parent = sosEffEntry('p-1', ['properties' => ['description' => 'From parent']]);
    sosEffFind(['p-1' => $parent]);
    $entry = sosEffEntry('e-1', ['properties' => ['name' => 'X']]);

    expect(sosEffResolve(sosEffService(['e-1' => 'p-1']), $entry))->toBeNull();
});

it('returns nothing for an entry with no type and no parent', function (): void {
    expect(sosEffResolve(sosEffService(), sosEffEntry('e-1', ['properties' => ['name' => 'X']])))->toBeNull();
});

it('skips inherit_from ids that are already visited, missing, or have no effective schema', function (): void {
    $untyped = sosEffEntry('i-untyped', ['properties' => ['description' => 'Untyped']]);
    $typed = sosEffEntry('i-typed', ['type' => 'Thing', 'properties' => ['description' => 'Typed']]);
    $lookups = [];
    sosEffFind(['i-untyped' => $untyped, 'i-typed' => $typed], $lookups);
    $entry = sosEffEntry('e-1', ['type' => 'Thing', 'properties' => ['name' => 'X']], ['e-1', 'i-missing', 'i-untyped', 'i-typed']);

    expect(sosEffResolve(sosEffService(), $entry))->toBe(['type' => 'Thing', 'properties' => ['name' => 'X', 'description' => 'Typed']]);
    expect($lookups)->toBe(['i-missing', 'i-untyped', 'i-typed']);
});

it('reads an inherit_from id stored as a single string', function (): void {
    $typed = sosEffEntry('i-typed', ['type' => 'Thing', 'properties' => ['description' => 'Typed']]);
    sosEffFind(['i-typed' => $typed]);
    $entry = sosEffEntry('e-1', ['type' => 'Thing', 'properties' => []], 'i-typed');

    expect(sosEffResolve(sosEffService(), $entry))->toBe(['type' => 'Thing', 'properties' => ['description' => 'Typed']]);
});

it('normalizes inherit_from values to a list of ids', function (mixed $value, array $expected): void {
    $method = new ReflectionMethod(SchemaOutputService::class, 'normalizeInheritFromIds');

    expect($method->invoke(sosEffService(), $value))->toBe($expected);
})->with([
    'null' => [null, []],
    'empty string' => ['', []],
    'string' => ['a-1', ['a-1']],
    'list with blanks, repeats and non-strings' => [['a-1', '', 7, 'a-1', 'b-2'], ['a-1', 'b-2']],
    'integer' => [7, []],
    'boolean' => [true, []],
]);

it('keeps inherited properties unchanged when the recipient type is missing or unknown', function (mixed $type): void {
    $method = new ReflectionMethod(SchemaOutputService::class, 'filterInheritedPropertiesForRecipientType');
    $properties = ['name' => 'X', 'notASchemaProperty' => 'Y'];

    expect($method->invoke(sosEffService(), $properties, $type))->toBe($properties);
})->with([
    'no type' => [null],
    'empty list' => [[]],
    'unknown type' => ['NotARealSchemaOrgType'],
]);

it('filters inherited properties to the names a known recipient type allows', function (): void {
    $method = new ReflectionMethod(SchemaOutputService::class, 'filterInheritedPropertiesForRecipientType');

    expect($method->invoke(sosEffService(), ['name' => 'X', 'notASchemaProperty' => 'Y'], 'Thing'))->toBe(['name' => 'X']);
});

it('reads a schema with no properties as no properties', function (): void {
    expect(sosEffResolve(sosEffService(), sosEffEntry('e-1', ['type' => 'Thing'])))->toBe(['type' => 'Thing', 'properties' => []]);
});

it('matches a revisited entry by its id as a string', function (mixed $id, string $visited): void {
    $lookups = [];
    sosEffFind([], $lookups);
    $entry = sosEffEntry($id, ['type' => 'Thing', 'properties' => ['name' => 'X']], ['i-1']);

    expect(sosEffResolve(sosEffService(), $entry, [$visited]))->toBe(['type' => 'Thing', 'properties' => ['name' => 'X']]);
    expect($lookups)->toBe([]);
})->with([
    'a numeric id' => [7, '7'],
    'no id' => [null, ''],
]);

it('matches visited entry ids strictly', function (): void {
    $lookups = [];
    sosEffFind([], $lookups);
    $entry = sosEffEntry('10', ['type' => 'Thing', 'properties' => ['name' => 'X']], ['20']);

    sosEffResolve(sosEffService(['10' => 'p-1']), $entry, ['1e1', '2e1']);

    expect($lookups)->toBe(['p-1', '20']);
});

it('looks up no structure parent for an empty parent id', function (): void {
    $lookups = [];
    sosEffFind([], $lookups);
    $entry = sosEffEntry('e-1', ['type' => 'Thing', 'properties' => ['name' => 'X']]);

    expect(sosEffResolve(sosEffService(['e-1' => '']), $entry))->toBe(['type' => 'Thing', 'properties' => ['name' => 'X']]);
    expect($lookups)->toBe([]);
});

it('keeps an inherit_custom property group over the inherited value as its base', function (mixed $own, mixed $expected): void {
    $parent = sosEffEntry('p-1', ['type' => 'Thing', 'properties' => ['name' => 'From parent']]);
    sosEffFind(['p-1' => $parent]);
    $entry = sosEffEntry('e-1', ['type' => 'Thing', 'properties' => ['name' => $own]]);

    $effective = mustBeArray(sosEffResolve(sosEffService(['e-1' => 'p-1']), $entry), 'the effective schema');

    expect(mustBeArray($effective['properties'], 'the properties')['name'])->toBe($expected);
})->with([
    'a group with rows' => [['mode' => 'inherit_custom', 'value' => ['row']], ['mode' => 'inherit_custom', 'value' => ['row'], '_inherit_base' => 'From parent']],
    'a group with a string value' => [['mode' => 'inherit_custom', 'value' => 'text'], ['mode' => 'inherit_custom', 'value' => [], '_inherit_base' => 'From parent']],
    'a group with no value' => [['mode' => 'inherit_custom'], ['mode' => 'inherit_custom', 'value' => [], '_inherit_base' => 'From parent']],
    'a plain value' => ['Own', 'Own'],
    'an array without a mode' => [['a'], ['a']],
]);

it('reads whether a schema type is set', function (mixed $type, bool $expected): void {
    expect((new ReflectionMethod(SchemaOutputService::class, 'isTypeSet'))->invoke(sosEffService(), $type))->toBe($expected);
})->with([
    'a type' => ['Thing', true],
    'null' => [null, false],
    'an empty string' => ['', false],
    'a list with a type after blanks' => [[null, '', 'Thing'], true],
    'a list of blanks' => [[null, ''], false],
    'an empty list' => [[], false],
]);

it('normalizes a schema type to a list of unique non-empty names', function (mixed $type, array $expected): void {
    expect((new ReflectionMethod(SchemaOutputService::class, 'normalizeEffectiveSchemaTypeToStringList'))->invoke(sosEffService(), $type))->toBe($expected);
})->with([
    'a type' => ['Thing', ['Thing']],
    'an empty string' => ['', []],
    'a number' => [5, []],
    'a list with repeats and blanks' => [['Thing', 'Thing', '', 7, 'Person'], ['Thing', 'Person']],
    'a list of blanks' => [['', null], []],
]);
