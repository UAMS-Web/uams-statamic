<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaJsonLdAtIdDeduplication;
use App\Services\SchemaTemplates\SchemaJsonLdSchemaObjectEmissionCoordinator;
use App\Services\SchemaTemplates\SchemaOutputService;
use App\Services\SchemaTemplates\SchemaTemplateInstanceWatermarkBag;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry as EntryModel;

uses(MockeryPHPUnitIntegration::class);

/**
 * Unit tests for SchemaJsonLdSchemaObjectEmissionCoordinator (compact @id
 * fast-path and reference-object construction for schema_objects emission).
 *
 * Mutation: 100% on App\Services\SchemaTemplates\SchemaJsonLdSchemaObjectEmissionCoordinator (14 mutants, --covered-only).
 *
 * @command  php artisan test tests/Unit/Services/SchemaTemplates/SchemaJsonLdSchemaObjectEmissionCoordinatorTest.php --compact
 */
it('returns null when the canonical at-id is not preregistered', function () {
    $dedupe = new SchemaJsonLdAtIdDeduplication;
    $coordinator = new SchemaJsonLdSchemaObjectEmissionCoordinator($dedupe);

    $output = Mockery::mock(SchemaOutputService::class);
    $output->shouldReceive('previewCanonicalAtIdForSchemaObjectTemplate')
        ->once()
        ->with(
            'entry-1',
            null,
            [],
            [],
            null
        )
        ->andReturn('https://example.test#entry-1');

    $emittedNodeIds = [];
    $materializedFullNodeAtIds = [];

    $ref = $coordinator->tryCompactReferenceIfSchemaObjectAlreadyFullyEmitted(
        $output,
        'entry-1',
        $emittedNodeIds,
        $materializedFullNodeAtIds,
        null,
        [],
        []
    );

    expect($ref)->toBeNull();
});

it('returns the at-id reference when the canonical at-id is preregistered', function () {
    $dedupe = new SchemaJsonLdAtIdDeduplication;
    $coordinator = new SchemaJsonLdSchemaObjectEmissionCoordinator($dedupe);

    $output = Mockery::mock(SchemaOutputService::class);
    $output->shouldReceive('previewCanonicalAtIdForSchemaObjectTemplate')
        ->once()
        ->andReturn('https://example.test#entry-1');

    $emittedNodeIds = ['https://example.test#entry-1' => true];
    $materializedFullNodeAtIds = ['https://example.test#entry-1' => true];

    $ref = $coordinator->tryCompactReferenceIfSchemaObjectAlreadyFullyEmitted(
        $output,
        'entry-1',
        $emittedNodeIds,
        $materializedFullNodeAtIds,
        null,
        [],
        []
    );

    expect($ref)->toBe(['@id' => 'https://example.test#entry-1']);
});

it('builds the at-id reference object via the schema output service', function () {
    $dedupe = new SchemaJsonLdAtIdDeduplication;
    $coordinator = new SchemaJsonLdSchemaObjectEmissionCoordinator($dedupe);

    $entry = Mockery::mock(EntryModel::class);

    $output = Mockery::mock(SchemaOutputService::class);
    $output->shouldReceive('getCanonicalAtIdForSchemaEntry')
        ->once()
        ->with($entry)
        ->andReturn('https://example.test#abc');

    $ref = $coordinator->atIdReferenceObjectForSchemaObjectEntry(
        $entry,
        $output
    );

    expect($ref)->toBe(['@id' => 'https://example.test#abc']);
});

it('returns null when the preview at-id is missing or empty', function (?string $previewAtId) {
    $coordinator = new SchemaJsonLdSchemaObjectEmissionCoordinator(new SchemaJsonLdAtIdDeduplication);

    $output = Mockery::mock(SchemaOutputService::class);
    $output->shouldReceive('previewCanonicalAtIdForSchemaObjectTemplate')
        ->once()
        ->andReturn($previewAtId);

    // Both maps carry the '' key, so neither map lookup ends the call. A missing
    // @id stops at the coordinator's own guard, and an empty one at the dedupe
    // helper's.
    $emittedNodeIds = [(string) $previewAtId => true];
    $materializedFullNodeAtIds = [(string) $previewAtId => true];

    $ref = $coordinator->tryCompactReferenceIfSchemaObjectAlreadyFullyEmitted(
        $output,
        'entry-1',
        $emittedNodeIds,
        $materializedFullNodeAtIds,
        null,
        [],
        []
    );

    expect($ref)->toBeNull();
})->with([
    'no preview at-id' => [null],
    'an empty preview at-id' => [''],
]);

it('returns null when the at-id was emitted but its full node did not survive dedupe', function () {
    $coordinator = new SchemaJsonLdSchemaObjectEmissionCoordinator(new SchemaJsonLdAtIdDeduplication);

    $output = Mockery::mock(SchemaOutputService::class);
    $output->shouldReceive('previewCanonicalAtIdForSchemaObjectTemplate')
        ->once()
        ->andReturn('https://example.test#entry-1');

    $emittedNodeIds = ['https://example.test#entry-1' => true];
    $materializedFullNodeAtIds = ['https://example.test#other' => true];

    $ref = $coordinator->tryCompactReferenceIfSchemaObjectAlreadyFullyEmitted(
        $output,
        'entry-1',
        $emittedNodeIds,
        $materializedFullNodeAtIds,
        null,
        [],
        []
    );

    expect($ref)->toBeNull();
});

it('returns null when the full node survived dedupe but was not recorded as emitted', function () {
    $coordinator = new SchemaJsonLdSchemaObjectEmissionCoordinator(new SchemaJsonLdAtIdDeduplication);

    $output = Mockery::mock(SchemaOutputService::class);
    $output->shouldReceive('previewCanonicalAtIdForSchemaObjectTemplate')
        ->once()
        ->andReturn('https://example.test#entry-1');

    $emittedNodeIds = [];
    $materializedFullNodeAtIds = ['https://example.test#entry-1' => true];

    $ref = $coordinator->tryCompactReferenceIfSchemaObjectAlreadyFullyEmitted(
        $output,
        'entry-1',
        $emittedNodeIds,
        $materializedFullNodeAtIds,
        null,
        [],
        []
    );

    expect($ref)->toBeNull();
});

it('passes every preview argument through to the schema output service', function () {
    $coordinator = new SchemaJsonLdSchemaObjectEmissionCoordinator(new SchemaJsonLdAtIdDeduplication);
    $context = Mockery::mock(EntryModel::class);
    $bag = new SchemaTemplateInstanceWatermarkBag;

    $output = Mockery::mock(SchemaOutputService::class);
    $output->shouldReceive('previewCanonicalAtIdForSchemaObjectTemplate')
        ->once()
        ->withArgs(fn (...$args): bool => $args === ['entry-9', $context, ['slot-a'], ['instance' => 2], $bag])
        ->andReturn('https://example.test#entry-9');

    $emittedNodeIds = ['https://example.test#entry-9' => true];
    $materializedFullNodeAtIds = ['https://example.test#entry-9' => true];

    $ref = $coordinator->tryCompactReferenceIfSchemaObjectAlreadyFullyEmitted(
        $output,
        'entry-9',
        $emittedNodeIds,
        $materializedFullNodeAtIds,
        $context,
        ['slot-a'],
        ['instance' => 2],
        $bag
    );

    expect($ref)->toBe(['@id' => 'https://example.test#entry-9']);
});

it('records a full node as emitted for its at-id', function () {
    $coordinator = new SchemaJsonLdSchemaObjectEmissionCoordinator(new SchemaJsonLdAtIdDeduplication);
    $emittedNodeIds = ['https://example.test#kept' => true];

    $coordinator->recordFullSchemaObjectNode($emittedNodeIds, ['@id' => 'https://example.test#new', '@type' => 'Thing']);

    expect($emittedNodeIds)->toBe([
        'https://example.test#kept' => true,
        'https://example.test#new' => true,
    ]);
});

it('does not record a missing node', function () {
    $coordinator = new SchemaJsonLdSchemaObjectEmissionCoordinator(new SchemaJsonLdAtIdDeduplication);
    $emittedNodeIds = [];

    $coordinator->recordFullSchemaObjectNode($emittedNodeIds, null);

    expect($emittedNodeIds)->toBe([]);
});

it('does not record a reference-only node with no type', function () {
    $coordinator = new SchemaJsonLdSchemaObjectEmissionCoordinator(new SchemaJsonLdAtIdDeduplication);
    $emittedNodeIds = [];

    $coordinator->recordFullSchemaObjectNode($emittedNodeIds, ['@id' => 'https://example.test#ref']);

    expect($emittedNodeIds)->toBe([]);
});

it('returns null for a schema object entry whose canonical at-id is empty', function () {
    $coordinator = new SchemaJsonLdSchemaObjectEmissionCoordinator(new SchemaJsonLdAtIdDeduplication);
    $entry = Mockery::mock(EntryModel::class);

    $output = Mockery::mock(SchemaOutputService::class);
    $output->shouldReceive('getCanonicalAtIdForSchemaEntry')
        ->once()
        ->with($entry)
        ->andReturn('');

    expect($coordinator->atIdReferenceObjectForSchemaObjectEntry($entry, $output))->toBeNull();
});
