<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\JsonLdCompositionProfiler;
use App\Services\SchemaTemplates\MedicalOrganizationSubtypeAtTypeResolver;
use App\Services\SchemaTemplates\PodcastEpisodeAtTypeResolver;
use App\Services\SchemaTemplates\SchemaClassificationsIdentifiersMerger;
use App\Services\SchemaTemplates\SchemaJsonLdAtIdDeduplication;
use App\Services\SchemaTemplates\SchemaJsonLdSchemaObjectEmissionCoordinator;
use App\Services\SchemaTemplates\SchemaOutputService;
use App\Services\SchemaTemplates\SchemaTemplateInstanceWatermarkBag;
use App\Services\SchemaTemplates\SchemaTemplatesCascadeService;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Entries\Entry as EntryModel;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(SchemaOutputService::class);

/**
 * Pins the branches of {@see SchemaOutputService::buildSchemaTemplateNodeJsonForTag()}
 * that run when the `schema_template_node` tag is used outside an embedded schema
 * template (no emit context), and the two empty outputs: no usable id, and no id
 * that resolves to a node. Also pins the null-entity return of
 * `resolveGraphEntryIdsForEntity()`. The entity, graph ids and node builder are
 * stubbed on a partial mock, so each branch is asserted on its own.
 *
 * @command  php artisan test --compact tests/Feature/Services/SchemaTemplates/SchemaOutputServiceTemplateNodeTagTest.php
 */

/**
 * The service as a partial mock.
 */
function sosTagService(): SchemaOutputService&MockInterface
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

    return $service;
}

/**
 * Stub the node builder to answer from a map, recording what each call received.
 *
 * @param  array<string, array<string, mixed>|null>  $nodes  Node by template entry id.
 * @param  list<array<string, mixed>>  $calls  Each call's arguments, by reference.
 */
function sosTagNodes(SchemaOutputService&MockInterface $service, array $nodes, array &$calls): void
{
    $service->shouldReceive('schemaObjectEntryToNode')->andReturnUsing(
        function (string $id, mixed $entity, array $graphEntryIds, array $visitedIds, array &$emittedEntryIds, array &$emittedNodeIds, array &$materialized, array $antlersContext, mixed $bag) use ($nodes, &$calls): ?array {
            $calls[] = [
                'id' => $id,
                'entity' => $entity,
                'graph' => $graphEntryIds,
                'visited' => $visitedIds,
                'emittedEntryIds' => $emittedEntryIds,
                'emittedNodeIds' => $emittedNodeIds,
                'materialized' => $materialized,
                'context' => $antlersContext,
                'bag' => $bag,
            ];

            return $nodes[$id] ?? null;
        }
    );
}

it('returns an empty string when no template id is usable', function (array $ids): void {
    /** @var array<int, string> $templateIds */
    $templateIds = $ids;
    $service = sosTagService();
    $service->shouldNotReceive('schemaObjectEntryToNode');

    expect($service->buildSchemaTemplateNodeJsonForTag($templateIds, null))->toBe('');
})->with([
    'no ids' => [[]],
    'only empty ids' => [['', '']],
]);

it('builds a standalone node against the current entity and its graph ids', function (): void {
    /** @var EntryModel&MockInterface $entity */
    $entity = Mockery::mock(EntryModel::class);
    $service = sosTagService();
    $service->shouldReceive('resolveEntity')->andReturn($entity);
    $graphLookups = [];
    $service->shouldReceive('resolveGraphEntryIdsForEntity')->andReturnUsing(function (mixed $seen) use (&$graphLookups): array {
        $graphLookups[] = $seen;

        return ['slot-1'];
    });
    $calls = [];
    sosTagNodes($service, ['tpl-1' => ['@type' => 'Organization', '@id' => 'https://example.test/#tpl-1', 'name' => 'UAMS']], $calls);

    $json = $service->buildSchemaTemplateNodeJsonForTag(['tpl-1', 'tpl-1', ''], null, ['k' => 'v']);

    expect(json_decode($json, true))->toBe(['@type' => 'Organization', '@id' => 'https://example.test/#tpl-1', 'name' => 'UAMS']);
    expect($graphLookups)->toHaveCount(1);
    expect($graphLookups[0])->toBe($entity);
    expect($calls)->toHaveCount(1);
    expect($calls[0]['id'])->toBe('tpl-1');
    expect($calls[0]['entity'])->toBe($entity);
    expect($calls[0]['graph'])->toBe(['slot-1']);
    expect($calls[0]['visited'])->toBe([]);
    expect($calls[0]['emittedEntryIds'])->toBe([]);
    expect($calls[0]['emittedNodeIds'])->toBe([]);
    expect($calls[0]['materialized'])->toBe([]);
    expect($calls[0]['context'])->toBe(['k' => 'v']);
    expect($calls[0]['bag'])->toBeInstanceOf(SchemaTemplateInstanceWatermarkBag::class);
});

it('returns an empty string when no template id resolves to a node', function (): void {
    $service = sosTagService();
    $service->shouldReceive('resolveEntity')->andReturn(null);
    $service->shouldReceive('resolveGraphEntryIdsForEntity')->andReturn([]);
    $calls = [];
    sosTagNodes($service, [], $calls);

    expect($service->buildSchemaTemplateNodeJsonForTag(['tpl-1', 'tpl-2'], null))->toBe('');
    expect(array_column($calls, 'id'))->toBe(['tpl-1', 'tpl-2']);
});

it('skips a template id that resolves to no node and keeps the rest', function (): void {
    $service = sosTagService();
    $service->shouldReceive('resolveEntity')->andReturn(null);
    $service->shouldReceive('resolveGraphEntryIdsForEntity')->andReturn([]);
    $calls = [];
    sosTagNodes($service, ['tpl-2' => ['@type' => 'Person', '@id' => 'https://example.test/#tpl-2']], $calls);

    expect(json_decode($service->buildSchemaTemplateNodeJsonForTag(['tpl-1', 'tpl-2'], null), true))
        ->toBe(['@type' => 'Person', '@id' => 'https://example.test/#tpl-2']);
});

it('reads no graph ids for a missing entity', function (): void {
    $method = new ReflectionMethod(SchemaOutputService::class, 'resolveGraphEntryIdsForEntity');

    expect($method->invoke(sosTagService(), null))->toBe([]);
});

it('reads graph ids for an entity from its resolved cascade', function (): void {
    /** @var EntryContract&MockInterface $entity */
    $entity = Mockery::mock(EntryModel::class);
    /** @var SchemaTemplatesCascadeService&MockInterface $cascade */
    $cascade = Mockery::mock(SchemaTemplatesCascadeService::class);
    $cascade->shouldReceive('resolve')->once()->with($entity)->andReturn([]);

    /** @var SchemaOutputService&MockInterface $service */
    $service = Mockery::mock(SchemaOutputService::class, [
        $cascade,
        app(SchemaClassificationsIdentifiersMerger::class),
        app(SchemaJsonLdAtIdDeduplication::class),
        app(SchemaJsonLdSchemaObjectEmissionCoordinator::class),
        app(JsonLdCompositionProfiler::class),
        app(MedicalOrganizationSubtypeAtTypeResolver::class),
        app(PodcastEpisodeAtTypeResolver::class),
    ])->makePartial();

    expect((new ReflectionMethod(SchemaOutputService::class, 'resolveGraphEntryIdsForEntity'))->invoke($service, $entity))->toBe([]);
});
