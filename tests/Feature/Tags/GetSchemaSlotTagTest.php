<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaOutputService;
use App\Tags\GetSchemaSlot;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Entries\Entry;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * @covers \App\Tags\GetSchemaSlot
 *
 * Mutation: 100% on App\Tags\GetSchemaSlot (37 mutants, --covered-only; every line covered).
 *
 * @command  php artisan test --compact tests/Feature/Tags/GetSchemaSlotTagTest.php
 */
it('returns null when slot missing', function () {
    /** @var SchemaOutputService&MockInterface $service */
    $service = Mockery::mock(SchemaOutputService::class);
    $service->shouldReceive('getSlotEntryForCurrentContext')->never();
    app()->instance(SchemaOutputService::class, $service);

    $tag = new GetSchemaSlot;
    $tag->setContext([]);
    $tag->setParameters([]);

    expect($tag->index())->toBeNull();
});

it('returns hash id when entry resolved for subject slot', function () {
    /** @var Entry&MockInterface $entry */
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('collectionHandle')->andReturn('pages');
    $entry->shouldReceive('id')->andReturn('uuid-1');

    /** @var SchemaOutputService&MockInterface $service */
    $service = Mockery::mock(SchemaOutputService::class);
    $service->shouldReceive('getSlotEntryForCurrentContext')
        ->once()
        ->with('subject')
        ->andReturn($entry);
    app()->instance(SchemaOutputService::class, $service);

    $tag = new GetSchemaSlot;
    $tag->setContext([]);
    $tag->setParameters([]);

    expect($tag->subject())->toBe('#uuid-1');
});

/**
 * An entry double in the given collection with the given id.
 */
function schemaSlotEntry(string $collection, mixed $id): Entry
{
    /** @var Entry&MockInterface $entry */
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('collectionHandle')->andReturn($collection);
    $entry->shouldReceive('id')->andReturn($id);

    return $entry;
}

/**
 * Bind a SchemaOutputService double that resolves `$slot` to `$entry`.
 *
 * @return SchemaOutputService&MockInterface
 */
function bindSchemaSlotService(string $slot, mixed $entry): SchemaOutputService
{
    /** @var SchemaOutputService&MockInterface $service */
    $service = Mockery::mock(SchemaOutputService::class);
    $service->shouldReceive('getSlotEntryForCurrentContext')->once()->with($slot)->andReturn($entry);
    app()->instance(SchemaOutputService::class, $service);

    return $service;
}

/**
 * A get_schema_slot tag with the given parameters.
 *
 * @param  array<mixed>  $parameters
 */
function schemaSlotTag(array $parameters = []): GetSchemaSlot
{
    $tag = new GetSchemaSlot;
    $tag->setContext([]);
    $tag->setParameters($parameters);

    return $tag;
}

it('returns null for an empty or non-string slot without asking the service', function (mixed $slot) {
    /** @var SchemaOutputService&MockInterface $service */
    $service = Mockery::mock(SchemaOutputService::class);
    $service->shouldReceive('getSlotEntryForCurrentContext')->never();
    app()->instance(SchemaOutputService::class, $service);

    expect(schemaSlotTag(['slot' => $slot])->index())->toBeNull();
})->with([
    'empty' => [''],
    'integer' => [5],
    'array' => [['subject']],
]);

it('resolves the slot named by the slot parameter', function () {
    bindSchemaSlotService('about', schemaSlotEntry('pages', 'uuid-2'));

    expect(schemaSlotTag(['slot' => 'about'])->index())->toBe('#uuid-2');
});

it('resolves each named slot method', function (string $method, string $slot) {
    bindSchemaSlotService($slot, schemaSlotEntry('pages', 'uuid-3'));

    expect(schemaSlotTag()->{$method}())->toBe('#uuid-3');
})->with([
    'website' => ['website', 'website'],
    'webpage' => ['webpage', 'webpage'],
    'main content' => ['mainContent', 'main_content'],
]);

it('returns null when the slot resolves to no entry', function () {
    bindSchemaSlotService('subject', null);

    expect(schemaSlotTag()->subject())->toBeNull();
});

it('returns null for an entry id that is empty or not a string', function (mixed $id) {
    bindSchemaSlotService('subject', schemaSlotEntry('pages', $id));

    expect(schemaSlotTag()->subject())->toBeNull();
})->with([
    'empty' => [''],
    'integer' => [7],
    'null' => [null],
]);

it('returns the canonical full @id for a schema_objects entry when full_id is set', function (string $method, string $slot, array $parameters) {
    $entry = schemaSlotEntry('schema_objects', 'uuid-4');
    $service = bindSchemaSlotService($slot, $entry);
    $service->shouldReceive('getCanonicalAtIdForSchemaEntry')->once()->with($entry)->andReturn('/#uuid-4');

    expect(schemaSlotTag($parameters)->{$method}())->toBe('/#uuid-4');
})->with([
    'index' => ['index', 'about', ['slot' => 'about', 'full_id' => 'true']],
    'subject' => ['subject', 'subject', ['full_id' => true]],
    'website' => ['website', 'website', ['full_id' => true]],
    'webpage' => ['webpage', 'webpage', ['full_id' => true]],
    'main content' => ['mainContent', 'main_content', ['full_id' => true]],
]);

it('returns null when the canonical full @id is empty', function () {
    $entry = schemaSlotEntry('schema_objects', 'uuid-5');
    $service = bindSchemaSlotService('subject', $entry);
    $service->shouldReceive('getCanonicalAtIdForSchemaEntry')->once()->andReturn('');

    expect(schemaSlotTag(['full_id' => true])->subject())->toBeNull();
});

it('returns the short reference when full_id is unset or the entry is not a schema object', function (string $collection, array $parameters) {
    $service = bindSchemaSlotService('subject', schemaSlotEntry($collection, 'uuid-6'));
    $service->shouldReceive('getCanonicalAtIdForSchemaEntry')->never();

    expect(schemaSlotTag($parameters)->subject())->toBe('#uuid-6');
})->with([
    'schema object, no full_id' => ['schema_objects', []],
    'schema object, full_id false' => ['schema_objects', ['full_id' => false]],
    'other collection, full_id set' => ['pages', ['full_id' => true]],
]);
