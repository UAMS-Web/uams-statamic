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
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(SchemaOutputService::class);

/**
 * Pins the branches of {@see SchemaOutputService::resolveSingleReplicatorRowToValue()}
 * that the rendering suites do not reach: an Antlers row whose code is only
 * whitespace, the composition profiler's parse samples, Antlers output that opens
 * with a quote but is not JSON Text, a `thing_` row holding one id as a string, and
 * a row of any other type whose value is empty. The node id builder is stubbed on
 * a partial mock and the profiler is a mock handed to the constructor, so each
 * branch is asserted on its own.
 *
 * @command  php artisan test --compact tests/Feature/Services/SchemaTemplates/SchemaOutputServiceReplicatorRowTest.php
 */

/**
 * The service as a partial mock, built with the given profiler.
 */
function sosRowService(?JsonLdCompositionProfiler $profiler = null): SchemaOutputService&MockInterface
{
    /** @var SchemaOutputService&MockInterface $service */
    $service = Mockery::mock(SchemaOutputService::class, [
        app(SchemaTemplatesCascadeService::class),
        app(SchemaClassificationsIdentifiersMerger::class),
        app(SchemaJsonLdAtIdDeduplication::class),
        app(SchemaJsonLdSchemaObjectEmissionCoordinator::class),
        $profiler ?? app(JsonLdCompositionProfiler::class),
        app(MedicalOrganizationSubtypeAtTypeResolver::class),
        app(PodcastEpisodeAtTypeResolver::class),
    ])
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();

    return $service;
}

/**
 * Resolve one replicator row with no context entity.
 *
 * @param  array<string, mixed>  $row  The replicator row.
 * @param  array<int, string>  $graphEntryIds  Entry ids already in the graph.
 * @param  array<string, mixed>  $antlersContext  Antlers context overrides.
 */
function sosRowResolve(SchemaOutputService $service, array $row, array $graphEntryIds = [], array $antlersContext = []): mixed
{
    $emittedEntryIds = [];
    $emittedNodeIds = [];
    $materialized = [];

    return (new ReflectionMethod(SchemaOutputService::class, 'resolveSingleReplicatorRowToValue'))->invokeArgs($service, [
        $row,
        null,
        $graphEntryIds,
        [],
        &$emittedEntryIds,
        &$emittedNodeIds,
        &$materialized,
        $antlersContext,
        null,
    ]);
}

/**
 * A profiler mock that is enabled and records each parse sample it receives.
 *
 * @param  list<array<int, mixed>>  $buckets  Bucket samples, by reference.
 * @param  list<array<int, mixed>>  $parses  Parse samples, by reference.
 */
function sosRowProfiler(array &$buckets, array &$parses): JsonLdCompositionProfiler&MockInterface
{
    /** @var JsonLdCompositionProfiler&MockInterface $profiler */
    $profiler = Mockery::mock(JsonLdCompositionProfiler::class);
    $profiler->shouldReceive('enabled')->andReturnTrue();
    $profiler->shouldReceive('recordBucketSample')->andReturnUsing(function (string $calls, string $ms, float $elapsed) use (&$buckets): void {
        $buckets[] = [$calls, $ms, $elapsed];
    });
    $profiler->shouldReceive('recordAntlersParseSample')->andReturnUsing(function (float $elapsed, ?string $entryId, ?string $propertyKey) use (&$parses): void {
        $parses[] = [$elapsed, $entryId, $propertyKey];
    });

    return $profiler;
}

it('omits an antlers row whose code is only whitespace', function (): void {
    expect(sosRowResolve(sosRowService(), ['type' => 'antlers', 'value' => ['code' => "  \n\t "]]))->toBeNull();
});

it('records the parse time against the template entry and property when profiling', function (): void {
    $buckets = [];
    $parses = [];
    $service = sosRowService(sosRowProfiler($buckets, $parses));

    $out = sosRowResolve($service, ['type' => 'antlers', 'value' => ['code' => 'Plain text']], [], [
        SchemaOutputService::ANTLERS_SCHEMA_TEMPLATE_ENTRY_ID => 'tpl-7',
        SchemaOutputService::ANTLERS_SCHEMA_PROPERTY_KEY => 'name',
    ]);

    expect($out)->toBe('Plain text');
    expect($buckets)->toHaveCount(1);
    expect($buckets[0][0])->toBe('antlers_parse_calls');
    expect($buckets[0][1])->toBe('antlers_parse_ms');
    expect($buckets[0][2])->toBeFloat()->toBeGreaterThanOrEqual(0.0);
    expect($parses)->toHaveCount(1);
    expect($parses[0][0])->toBeFloat()->toBeGreaterThanOrEqual(0.0);
    expect($parses[0][1])->toBe('tpl-7');
    expect($parses[0][2])->toBe('name');
});

it('records a parse sample with no entry or property when the context names neither', function (mixed $entryId, mixed $propertyKey): void {
    $buckets = [];
    $parses = [];
    $service = sosRowService(sosRowProfiler($buckets, $parses));

    sosRowResolve($service, ['type' => 'antlers', 'value' => ['code' => 'Plain text']], [], [
        SchemaOutputService::ANTLERS_SCHEMA_TEMPLATE_ENTRY_ID => $entryId,
        SchemaOutputService::ANTLERS_SCHEMA_PROPERTY_KEY => $propertyKey,
    ]);

    expect($parses)->toHaveCount(1);
    expect($parses[0][1])->toBeNull();
    expect($parses[0][2])->toBeNull();
})->with([
    'empty strings' => ['', ''],
    'non-strings' => [7, ['name']],
]);

it('records no parse sample when profiling is off', function (): void {
    /** @var JsonLdCompositionProfiler&MockInterface $off */
    $off = Mockery::mock(JsonLdCompositionProfiler::class);
    $off->shouldReceive('enabled')->andReturnFalse();
    $off->shouldNotReceive('recordBucketSample');
    $off->shouldNotReceive('recordAntlersParseSample');

    expect(sosRowResolve(sosRowService($off), ['type' => 'antlers', 'value' => ['code' => 'Plain text']]))->toBe('Plain text');
});

it('keeps antlers output that opens with a quote but is not json text', function (): void {
    expect(sosRowResolve(sosRowService(), ['type' => 'antlers', 'value' => ['code' => '"Kaitlin']]))->toBe('"Kaitlin');
});

it('decodes antlers output that is json text to its string', function (): void {
    expect(sosRowResolve(sosRowService(), ['type' => 'antlers', 'value' => ['code' => '"Kaitlin"']]))->toBe('Kaitlin');
});

it('references a thing row id given as a string once it is in the graph', function (): void {
    $service = sosRowService();
    $service->shouldReceive('entryIdToNodeId')
        ->andReturnUsing(fn (string $id): string => 'https://example.test/#'.$id);

    expect(sosRowResolve($service, ['type' => 'thing_person', 'value' => 'entry-5'], ['entry-5']))
        ->toBe([['@id' => 'https://example.test/#entry-5']]);
});

it('omits a row of any other type whose value is empty', function (mixed $value): void {
    expect(sosRowResolve(sosRowService(), ['type' => 'text', 'value' => $value]))->toBeNull();
})->with([
    'null' => [null],
    'empty string' => [''],
]);

it('omits a row with no value at all', function (): void {
    expect(sosRowResolve(sosRowService(), ['type' => 'text']))->toBeNull();
});
