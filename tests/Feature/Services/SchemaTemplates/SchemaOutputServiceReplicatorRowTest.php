<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\JsonLdCompositionProfiler;
use App\Services\SchemaTemplates\MedicalOrganizationSubtypeAtTypeResolver;
use App\Services\SchemaTemplates\PodcastEpisodeAtTypeResolver;
use App\Services\SchemaTemplates\SchemaClassificationsIdentifiersMerger;
use App\Services\SchemaTemplates\SchemaJsonLdAtIdDeduplication;
use App\Services\SchemaTemplates\SchemaJsonLdSchemaObjectEmissionCoordinator;
use App\Services\SchemaTemplates\SchemaOutputService;
use App\Services\SchemaTemplates\SchemaTemplateEmitContext;
use App\Services\SchemaTemplates\SchemaTemplateEmitContextRuntimeStack;
use App\Services\SchemaTemplates\SchemaTemplateInstanceWatermarkBag;
use App\Services\SchemaTemplates\SchemaTemplatesCascadeService;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Entries\Entry as EntryModel;
use Statamic\Facades\Parse;
use Statamic\Fields\Field;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(SchemaOutputService::class);

/**
 * Pins the branches of {@see SchemaOutputService::resolveSingleReplicatorRowToValue()}
 * that the rendering suites do not reach: which rows count as Antlers, an Antlers row
 * whose code is only whitespace, the untrusted parse and the emit context it runs
 * under, the composition profiler's parse samples in milliseconds, trimmed and empty
 * output, JSON and JSON Text output, a leading `#` reference, a terms field pair body
 * kept out of the missing-variable placeholders, `thing_` rows (their id filter, the
 * strict match against known ids, and the nodes built and tracked as emitted), and
 * the values of any other row type (a list, a keyed array, one JSON cannot encode, and
 * an empty one). The node id
 * builder, the node builder and the `Parse` facade are stubbed where a branch needs
 * it, so each branch is asserted on its own.
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
 * Resolve one replicator row and return the result with the emit arrays it changed.
 *
 * @param  array<string, mixed>  $row  The replicator row.
 * @param  array<int, string>  $graphEntryIds  Entry ids already in the graph.
 * @param  array<int, string>  $visitedIds  Entry ids being built.
 * @param  array<int, string>  $emittedEntryIds  Entry ids already emitted in full.
 * @return array{0: mixed, 1: array<int, string>, 2: array<string, bool>}
 */
function sosRowResolveTracked(SchemaOutputService $service, array $row, array $graphEntryIds = [], array $visitedIds = [], array $emittedEntryIds = [], ?SchemaTemplateInstanceWatermarkBag $bag = null): array
{
    $emittedNodeIds = [];
    $materialized = [];

    $out = (new ReflectionMethod(SchemaOutputService::class, 'resolveSingleReplicatorRowToValue'))->invokeArgs($service, [
        $row,
        null,
        $graphEntryIds,
        $visitedIds,
        &$emittedEntryIds,
        &$emittedNodeIds,
        &$materialized,
        [],
        $bag,
    ]);

    return [$out, $emittedEntryIds, $emittedNodeIds];
}

/**
 * Stub the node id builder to prefix each entry id with a fixed base.
 */
function sosRowStubNodeIds(SchemaOutputService&MockInterface $service): void
{
    $service->shouldReceive('entryIdToNodeId')
        ->andReturnUsing(fn (string $id): string => 'https://example.test/#'.$id);
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

it('records no parse sample for an antlers row whose code is only whitespace', function (): void {
    $buckets = [];
    $parses = [];

    expect(sosRowResolve(sosRowService(sosRowProfiler($buckets, $parses)), ['type' => 'antlers', 'value' => ['code' => "  \n\t "]]))->toBeNull()
        ->and($buckets)->toBe([])
        ->and($parses)->toBe([]);
});

it('treats a row as antlers only when its value holds a code string', function (array $row, string $expected): void {
    expect(sosRowResolve(sosRowService(), mustBeStringKeyed($row, 'the row')))->toBe($expected);
})->with([
    'antlers with a plain string value' => [['type' => 'antlers', 'value' => 'abc'], 'abc'],
    'a code value on another row type' => [['type' => 'text', 'value' => ['code' => 'Hi']], '{"code":"Hi"}'],
    'antlers with a code that is not a string' => [['type' => 'antlers', 'value' => ['code' => 5]], '{"code":5}'],
    'antlers with no code' => [['type' => 'antlers', 'value' => ['other' => 'Hi']], '{"other":"Hi"}'],
]);

it('parses untrusted with the emit context pushed for the parse and popped after it', function (): void {
    $bag = new SchemaTemplateInstanceWatermarkBag;
    $seenTemplate = null;
    $seenTrusted = null;
    $seenContext = null;
    $seenStack = null;
    Parse::shouldReceive('template')
        ->once()
        ->andReturnUsing(function (string $template, array $context, array $parseContext, bool $trusted) use (&$seenTemplate, &$seenTrusted, &$seenContext, &$seenStack): string {
            $emitContext = $context[SchemaOutputService::ANTLERS_SCHEMA_TEMPLATE_EMIT_CONTEXT] ?? null;
            $seenTemplate = $template;
            $seenTrusted = $trusted;
            $seenContext = $emitContext instanceof SchemaTemplateEmitContext ? $emitContext : null;
            $seenStack = SchemaTemplateEmitContextRuntimeStack::peek();

            return 'Plain text';
        });

    [$out] = sosRowResolveTracked(sosRowService(), ['type' => 'antlers', 'value' => ['code' => ' Plain text ']], bag: $bag);

    expect($out)->toBe('Plain text')
        ->and($seenTemplate)->toBe('Plain text')
        ->and($seenTrusted)->toBeFalse()
        ->and($seenContext)->toBeInstanceOf(SchemaTemplateEmitContext::class)
        ->and($seenContext?->templateInstanceWatermarkBag)->toBe($bag)
        ->and($seenStack)->toBe($seenContext)
        ->and(SchemaTemplateEmitContextRuntimeStack::peek())->toBeNull();
});

it('records the parse time in milliseconds', function (): void {
    $buckets = [];
    $parses = [];
    // The parse sleeps 20ms, so the recorded time has a floor to check. The floor is 10ms, not
    // 20ms: on Windows `usleep()` follows the system timer (see the resolver's profile test).
    // A time in seconds reads 0.02 and fails the floor; a sum of two timestamps fails the cap.
    Parse::shouldReceive('template')->andReturnUsing(function (): string {
        usleep(20_000);

        return 'Plain text';
    });

    sosRowResolve(sosRowService(sosRowProfiler($buckets, $parses)), ['type' => 'antlers', 'value' => ['code' => 'Plain text']]);

    expect($buckets[0][2])->toBeFloat()->toBeGreaterThanOrEqual(10.0)->toBeLessThan(60_000.0)
        ->and($parses[0][0])->toBe($buckets[0][2]);
});

it('trims the whitespace around antlers output', function (): void {
    expect(sosRowResolve(sosRowService(), ['type' => 'antlers', 'value' => ['code' => '{{ name }}']], [], ['name' => '  Hi  ']))->toBe('Hi');
});

it('omits antlers output that renders empty', function (): void {
    expect(sosRowResolve(sosRowService(), ['type' => 'antlers', 'value' => ['code' => '{{ name }}']], [], ['name' => '']))->toBeNull();
});

it('decodes antlers output that is a json array', function (): void {
    expect(sosRowResolve(sosRowService(), ['type' => 'antlers', 'value' => ['code' => '["a","b"]']]))->toBe(['a', 'b']);
});

it('references the entry an antlers output names with a leading hash', function (): void {
    $service = sosRowService();
    sosRowStubNodeIds($service);

    expect(sosRowResolve($service, ['type' => 'antlers', 'value' => ['code' => '#entry-5']]))
        ->toBe(['@id' => 'https://example.test/#entry-5']);
});

it('keeps antlers output that is only a hash', function (): void {
    $service = sosRowService();
    $service->shouldNotReceive('entryIdToNodeId');

    expect(sosRowResolve($service, ['type' => 'antlers', 'value' => ['code' => '#']]))->toBe('#');
});

it('references every non-empty string id of a thing row that is in the graph', function (): void {
    $service = sosRowService();
    sosRowStubNodeIds($service);
    $service->shouldNotReceive('schemaObjectEntryToNode');

    [$out] = sosRowResolveTracked($service, ['type' => 'thing_person', 'value' => ['e1', '', 5, 'e2']], ['e1', 'e2']);

    expect($out)->toBe([
        ['@id' => 'https://example.test/#e1'],
        ['@id' => 'https://example.test/#e2'],
    ]);
});

it('omits a thing row whose value is an empty string without building a node', function (): void {
    $service = sosRowService();
    $service->shouldNotReceive('schemaObjectEntryToNode');

    expect(sosRowResolve($service, ['type' => 'thing_person', 'value' => '']))->toBeNull();
});

it('matches a thing row id against the known ids strictly', function (string $list): void {
    $service = sosRowService();
    $service->shouldNotReceive('entryIdToNodeId');
    $built = [];
    $service->shouldReceive('schemaObjectEntryToNode')->andReturnUsing(function (string $id) use (&$built): ?array {
        $built[] = $id;

        return null;
    });

    // `1e1` and `10` are equal as numeric strings, so a loose match would take `1e1` as known.
    $known = ['10'];
    [$out] = sosRowResolveTracked(
        $service,
        ['type' => 'thing_person', 'value' => ['1e1']],
        $list === 'graph' ? $known : [],
        $list === 'visited' ? $known : [],
        $list === 'emitted' ? $known : [],
    );

    expect($out)->toBeNull()->and($built)->toBe(['1e1']);
})->with(['graph', 'visited', 'emitted']);

it('keeps each thing row node it builds and tracks only the full ones as emitted', function (): void {
    $service = sosRowService();
    $service->shouldReceive('schemaObjectEntryToNode')->andReturnUsing(fn (string $id): ?array => match ($id) {
        'full' => ['@id' => 'https://example.test/#full', '@type' => 'Person'],
        'ref' => ['@id' => 'https://example.test/#ref'],
        default => null,
    });

    [$out, $emittedEntryIds, $emittedNodeIds] = sosRowResolveTracked($service, ['type' => 'thing_person', 'value' => ['full', 'ref', 'none']]);

    expect($out)->toBe([
        ['@id' => 'https://example.test/#full', '@type' => 'Person'],
        ['@id' => 'https://example.test/#ref'],
    ])
        ->and($emittedEntryIds)->toBe(['full'])
        ->and($emittedNodeIds)->toHaveKey('https://example.test/#full');
});

it('tracks a built thing row id as emitted beside a numerically equal one', function (): void {
    $service = sosRowService();
    $service->shouldReceive('schemaObjectEntryToNode')
        ->andReturn(['@id' => 'https://example.test/#1e1', '@type' => 'Person']);

    // Passed as already emitted, `10` would hide `1e1` from a loose check.
    [, $emittedEntryIds] = sosRowResolveTracked($service, ['type' => 'thing_person', 'value' => ['1e1']], [], [], ['10']);

    expect($emittedEntryIds)->toBe(['10', '1e1']);
});

it('returns a list value as it is', function (): void {
    expect(sosRowResolve(sosRowService(), ['type' => 'text', 'value' => ['a', 'b']]))->toBe(['a', 'b']);
});

it('returns an empty string for a keyed value that cannot be encoded', function (): void {
    expect(sosRowResolve(sosRowService(), ['type' => 'text', 'value' => ['k' => "\xB1"]]))->toBe('');
});

it('keeps the body of a terms field pair out of the missing-variable placeholders', function (): void {
    // Seed the per-request memos the context entity's blueprint and values are read through,
    // so the entity needs only an id.
    $memo = function (string $constant): string {
        $value = (new ReflectionClassConstant(SchemaOutputService::class, $constant))->getValue();

        return \is_string($value) ? $value : throw new LogicException($constant.' is not a string');
    };
    request()->attributes->set($memo('REQUEST_ANTLERS_ENTITY_CONTEXT_CACHE'), ['ctx-1' => ['title' => 'Page']]);
    request()->attributes->set($memo('REQUEST_TERMS_LIKE_BLUEPRINT_FIELDS_GROUPED_CACHE'), ['ctx-1' => ['topics' => [Mockery::mock(Field::class)]]]);

    /** @var EntryModel&MockInterface $entity */
    $entity = Mockery::mock(EntryModel::class)->shouldIgnoreMissing();
    $entity->shouldReceive('id')->andReturn('ctx-1');

    $parsed = [];
    Parse::shouldReceive('template')->andReturnUsing(function (string $template, array $context) use (&$parsed): string {
        $parsed = $context;

        return 'Page';
    });

    $emittedEntryIds = [];
    $emittedNodeIds = [];
    $materialized = [];
    (new ReflectionMethod(SchemaOutputService::class, 'resolveSingleReplicatorRowToValue'))->invokeArgs(sosRowService(), [
        ['type' => 'antlers', 'value' => ['code' => '{{ title }}{{ topics }}{{ inner }}{{ /topics }}']],
        $entity,
        [],
        [],
        &$emittedEntryIds,
        &$emittedNodeIds,
        &$materialized,
        [],
        null,
    ]);

    expect($parsed)->toHaveKey('title')->not->toHaveKey('inner');
});
