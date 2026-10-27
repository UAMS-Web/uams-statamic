<?php

declare(strict_types=1);

use App\Tags\SchemaTemplateNode;
use Illuminate\Support\Collection;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Contracts\Query\Builder as StatamicQueryBuilder;
use Statamic\Entries\Entry as EntryModel;
use Statamic\Fields\Value;
use Statamic\Tags\Parameters;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Unit tests for SchemaTemplateNode tag id collection.
 *
 * Mutation: 100% on App\Tags\SchemaTemplateNode (137 mutants, --covered-only).
 *
 * @command  php artisan test tests/Feature/Tags/SchemaTemplateNodeTest.php --compact
 */

/**
 * Example schema_objects entry id (fixture shape).
 *
 * @var string
 */
const SCHEMA_TEMPLATE_NODE_UUID_A = '58185f50-22a4-48e7-9575-b86a1fdffef9';

/**
 * Second example id for multi-id cases.
 *
 * @var string
 */
const SCHEMA_TEMPLATE_NODE_UUID_B = '68185f50-22a4-48e7-9575-b86a1fdffef0';

/**
 * Invoke private collectTemplateEntryIds with mocked tag parameters.
 *
 * @param  array<string, mixed>  $params  Parameter map for the tag
 * @return array<int, string>
 */
function schemaTemplateNodeCollectTemplateEntryIds(array $params): array
{
    // Build a mock Parameters object that resolves keys from the map
    $parameters = Mockery::mock(Parameters::class);

    // Return stored values for get() like Antlers would supply
    $parameters->shouldReceive('get')
        ->andReturnUsing(
            fn (string $key, mixed $default = null): mixed => $params[$key] ?? $default
        );

    // Instantiate the tag and inject parameters via reflection
    $tag = new SchemaTemplateNode;
    $reflection = new ReflectionClass($tag);
    $paramsProperty = $reflection->getProperty('params');
    $paramsProperty->setValue($tag, $parameters);

    // Call the private collector
    $method = $reflection->getMethod('collectTemplateEntryIds');
    $method->setAccessible(true);

    /** @var array<int, string> $ids */
    $ids = $method->invoke($tag);

    return $ids;
}

it('unwraps value for id', function () {
    $result = schemaTemplateNodeCollectTemplateEntryIds([
        'id' => new Value(SCHEMA_TEMPLATE_NODE_UUID_A),
    ]);

    expect($result)->toBe([SCHEMA_TEMPLATE_NODE_UUID_A]);
});

it('unwraps value for pipe string and dedupes', function () {
    $pipe = SCHEMA_TEMPLATE_NODE_UUID_B.'|'.SCHEMA_TEMPLATE_NODE_UUID_A.'|'.SCHEMA_TEMPLATE_NODE_UUID_B;

    $result = schemaTemplateNodeCollectTemplateEntryIds([
        'id' => new Value($pipe),
    ]);

    expect($result)->toBe([
        SCHEMA_TEMPLATE_NODE_UUID_B,
        SCHEMA_TEMPLATE_NODE_UUID_A,
    ]);
});

it('accepts array id with nested values', function () {
    $result = schemaTemplateNodeCollectTemplateEntryIds([
        'id' => [
            new Value(SCHEMA_TEMPLATE_NODE_UUID_A),
            SCHEMA_TEMPLATE_NODE_UUID_B,
            SCHEMA_TEMPLATE_NODE_UUID_A,
        ],
    ]);

    expect($result)->toBe([
        SCHEMA_TEMPLATE_NODE_UUID_A,
        SCHEMA_TEMPLATE_NODE_UUID_B,
    ]);
});

it('accepts collection of uuid strings', function () {
    $result = schemaTemplateNodeCollectTemplateEntryIds([
        'id' => new Collection([SCHEMA_TEMPLATE_NODE_UUID_A, SCHEMA_TEMPLATE_NODE_UUID_B]),
    ]);

    expect($result)->toBe([
        SCHEMA_TEMPLATE_NODE_UUID_A,
        SCHEMA_TEMPLATE_NODE_UUID_B,
    ]);
});

it('accepts collection of entries', function () {
    $entryA = Mockery::mock(EntryModel::class);
    $entryA->shouldReceive('id')->andReturn(SCHEMA_TEMPLATE_NODE_UUID_A);

    $entryB = Mockery::mock(EntryModel::class);
    $entryB->shouldReceive('id')->andReturn(SCHEMA_TEMPLATE_NODE_UUID_B);

    $result = schemaTemplateNodeCollectTemplateEntryIds([
        'id' => new Collection([$entryA, $entryB]),
    ]);

    expect($result)->toBe([
        SCHEMA_TEMPLATE_NODE_UUID_A,
        SCHEMA_TEMPLATE_NODE_UUID_B,
    ]);
});

it('resolves statamic query builder', function () {
    $entryA = Mockery::mock(EntryModel::class);
    $entryA->shouldReceive('id')->andReturn(SCHEMA_TEMPLATE_NODE_UUID_A);

    $entryB = Mockery::mock(EntryModel::class);
    $entryB->shouldReceive('id')->andReturn(SCHEMA_TEMPLATE_NODE_UUID_B);

    $query = Mockery::mock(StatamicQueryBuilder::class);
    $query->shouldReceive('get')
        ->once()
        ->withNoArgs()
        ->andReturn(collect([$entryA, $entryB]));

    $result = schemaTemplateNodeCollectTemplateEntryIds([
        'id' => $query,
    ]);

    expect($result)->toBe([
        SCHEMA_TEMPLATE_NODE_UUID_A,
        SCHEMA_TEMPLATE_NODE_UUID_B,
    ]);
});

it('accepts single entry for id', function () {
    $entry = Mockery::mock(EntryModel::class);
    $entry->shouldReceive('id')->andReturn(SCHEMA_TEMPLATE_NODE_UUID_A);

    $result = schemaTemplateNodeCollectTemplateEntryIds([
        'id' => $entry,
    ]);

    expect($result)->toBe([SCHEMA_TEMPLATE_NODE_UUID_A]);
});

it('collects type override type names from strings, arrays, collections, and value', function () {
    $tag = new SchemaTemplateNode;
    $reflection = new ReflectionClass($tag);
    $method = $reflection->getMethod('collectTypeOverrideTypeNames');
    $method->setAccessible(true);

    expect($method->invoke($tag, 'Patient|Person'))->toBe(['Patient', 'Person']);

    expect($method->invoke($tag, new Value('Patient|Patient')))->toBe(['Patient']);

    expect($method->invoke($tag, ['A', new Value('B')]))->toBe(['A', 'B']);

    expect($method->invoke($tag, new Collection(['X', 'Y'])))->toBe(['X', 'Y']);

    expect($method->invoke($tag, null))->toBe([]);
    expect($method->invoke($tag, ''))->toBe([]);
});
