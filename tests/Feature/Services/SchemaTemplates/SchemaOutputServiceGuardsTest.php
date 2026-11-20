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
use App\Services\SchemaTemplates\SchemaTemplateNodeBuildStack;
use App\Services\SchemaTemplates\SchemaTemplatesCascadeService;
use App\Support\RequestAttributeMemo;
use Illuminate\Support\Collection;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Contracts\Data\Augmentable;
use Statamic\Contracts\Taxonomies\Term;
use Statamic\Entries\Entry as EntryModel;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Fields\Field;
use Statamic\Fields\Value;
use Statamic\Taxonomies\LocalizedTerm;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(SchemaOutputService::class);

/**
 * Pins the guard branches of {@see SchemaOutputService} that the rendering suites
 * do not reach: early returns for empty, missing, skipped or unparseable input in
 * the type override, property value, `@id` suffix, template instance index,
 * terms-like field, overlay slug and memo helpers, and in
 * `schemaObjectEntryToNode()` and `buildStandaloneSchemaObjectNodeSharingEmittedNodeIds()`.
 * Collaborators are stubbed on a partial mock and `Entry::find()` by a partial
 * facade mock, so each guard is asserted on its own.
 *
 * @command  php artisan test --compact tests/Feature/Services/SchemaTemplates/SchemaOutputServiceGuardsTest.php
 */

/**
 * The service as a partial mock.
 */
function sosGuardService(): SchemaOutputService&MockInterface
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
 * Call a non-public method of the service.
 */
function sosGuardCall(SchemaOutputService $service, string $method, mixed ...$args): mixed
{
    return (new ReflectionMethod(SchemaOutputService::class, $method))->invoke($service, ...$args);
}

/**
 * Call `resolvePropertyValue()` with no context entity.
 */
function sosGuardResolveProperty(SchemaOutputService $service, mixed $value): mixed
{
    $emittedEntryIds = [];
    $emittedNodeIds = [];
    $materialized = [];

    return (new ReflectionMethod(SchemaOutputService::class, 'resolvePropertyValue'))->invokeArgs($service, [
        $value,
        null,
        [],
        [],
        &$emittedEntryIds,
        &$emittedNodeIds,
        &$materialized,
        [],
        null,
    ]);
}

/**
 * Call `schemaObjectEntryToNode()` with no context entity.
 *
 * @param  array<int, string>  $visitedIds  Entry ids being built.
 */
function sosGuardEntryToNode(SchemaOutputService $service, string $entryId, array $visitedIds = []): mixed
{
    $emittedEntryIds = [];
    $emittedNodeIds = [];
    $materialized = [];

    return (new ReflectionMethod(SchemaOutputService::class, 'schemaObjectEntryToNode'))->invokeArgs($service, [
        $entryId,
        null,
        [],
        $visitedIds,
        &$emittedEntryIds,
        &$emittedNodeIds,
        &$materialized,
        [],
        null,
        null,
    ]);
}

/**
 * A blueprint field mock with a handle and a `max_items` setting.
 */
function sosGuardField(string $handle, mixed $maxItems): Field&MockInterface
{
    /** @var Field&MockInterface $field */
    $field = Mockery::mock(Field::class);
    $field->shouldReceive('handle')->andReturn($handle);
    $field->shouldReceive('get')->with('max_items')->andReturn($maxItems);

    return $field;
}

it('normalizes a type override list to unique trimmed names, unwrapping values', function (): void {
    expect(sosGuardCall(sosGuardService(), 'normalizeTypeOverrideContextList', [new Value(' Person '), 'Person', 7, 1.5, '', '  ', true, null, ['Thing']]))
        ->toBe(['Person', '7', '1.5']);
});

it('finds a type override compatible only through a known type\'s ancestors', function (array $override, array $entry, bool $expected): void {
    expect(sosGuardCall(sosGuardService(), 'typeOverrideListIsCompatibleWithEntryTypes', $override, $entry))->toBe($expected);
})->with([
    'the same name' => [['Thing'], ['Thing'], true],
    'an unknown override' => [['NotARealSchemaOrgType'], ['Thing'], false],
    'a subtype of the entry type' => [['Person'], ['Thing'], true],
    'an unrelated known type' => [['Person'], ['Place'], false],
    'an unknown override equal to a later entry type' => [['NotARealSchemaOrgType'], ['Thing', 'NotARealSchemaOrgType'], true],
]);

it('unwraps a type override given only as a value', function (): void {
    expect(sosGuardCall(sosGuardService(), 'normalizeTypeOverrideContextList', [new Value(' Person ')]))->toBe(['Person']);
});

it('reads no structure parent for an entry whose page is missing', function (): void {
    /** @var EntryModel&MockInterface $entry */
    $entry = Mockery::mock(EntryModel::class);
    $entry->shouldReceive('hasStructure')->andReturnTrue();
    $entry->shouldReceive('page')->andReturnNull();

    expect(sosGuardCall(sosGuardService(), 'getStructureParentId', $entry))->toBeNull();
});

it('decides whether a property value is set', function (mixed $value, bool $expected): void {
    expect(sosGuardCall(sosGuardService(), 'isValueSetAndNonEmpty', $value))->toBe($expected);
})->with([
    'an inherit_custom group' => [['mode' => 'inherit_custom', 'value' => []], true],
    'a disabled group' => [['mode' => 'disable'], true],
    'a custom group with rows' => [['mode' => 'custom', 'value' => [['type' => 'text']]], true],
    'a custom group with no rows' => [['mode' => 'custom', 'value' => []], false],
    'an inherit group' => [['mode' => 'inherit'], false],
    'a non-group list' => [['alpha'], true],
    'an empty array' => [[], false],
    'a scalar' => [0, true],
]);

it('decides whether a group\'s custom rows are set', function (mixed $value, bool $expected): void {
    expect(sosGuardCall(sosGuardService(), 'propertyValueRowsNonEmpty', $value))->toBe($expected);
})->with([
    'null' => [null, false],
    'an empty string' => ['', false],
    'an empty list' => [[], false],
    'rows' => [[['type' => 'text']], true],
    'a legacy string' => ['legacy', true],
]);

it('replays nothing from a scalar cached fragment', function (): void {
    $emittedEntryIds = ['a'];
    $emittedNodeIds = ['#a' => true];
    $materialized = ['#a' => true];

    sosGuardService()->replayEmitSideEffectsFromCachedNodeFragment('not a node', $emittedEntryIds, $emittedNodeIds, $materialized);

    expect($emittedEntryIds)->toBe(['a']);
    expect($emittedNodeIds)->toBe(['#a' => true]);
    expect($materialized)->toBe(['#a' => true]);
});

it('builds no node for an entry already being built, missing, or without a schema or type', function (): void {
    $lookups = [];
    $untyped = Mockery::mock(EntryModel::class);
    $blankTyped = Mockery::mock(EntryModel::class);
    EntryFacade::partialMock()->shouldReceive('find')->andReturnUsing(function (string $id) use (&$lookups, $untyped, $blankTyped): ?EntryModel {
        $lookups[] = $id;

        return ['untyped' => $untyped, 'blank-typed' => $blankTyped][$id] ?? null;
    });
    $service = sosGuardService();
    $service->shouldReceive('getEffectiveSchema')->andReturnUsing(
        fn (EntryModel $entry): ?array => $entry === $blankTyped ? ['type' => ['', null], 'properties' => []] : null
    );

    expect(sosGuardEntryToNode($service, 'visiting', ['visiting']))->toBeNull();
    expect(sosGuardEntryToNode($service, 'missing'))->toBeNull();
    expect(sosGuardEntryToNode($service, 'untyped'))->toBeNull();
    expect(sosGuardEntryToNode($service, 'blank-typed'))->toBeNull();
    expect($lookups)->toBe(['missing', 'untyped', 'blank-typed']);
});

it('builds a standalone node with no graph ids when there is no context entity', function (): void {
    $service = sosGuardService();
    $seen = [];
    $service->shouldReceive('schemaObjectEntryToNode')->andReturnUsing(function (string $id, mixed $entity, array $graphEntryIds) use (&$seen): array {
        $seen = ['id' => $id, 'entity' => $entity, 'graph' => $graphEntryIds];

        return ['@type' => 'Thing', '@id' => '#'.$id];
    });
    $emittedNodeIds = [];
    $materialized = [];

    $node = $service->buildStandaloneSchemaObjectNodeSharingEmittedNodeIds('tpl-1', null, [], $emittedNodeIds, $materialized);

    expect($node)->toBe(['@type' => 'Thing', '@id' => '#tpl-1']);
    expect($seen)->toBe(['id' => 'tpl-1', 'entity' => null, 'graph' => []]);
});

it('suffixes a canonical @id with a template instance index', function (string $atId, string $expected): void {
    expect(sosGuardCall(sosGuardService(), 'appendTemplateInstanceIndexToCanonicalAtId', $atId, 2))->toBe($expected);
})->with([
    'no fragment' => ['https://example.test/org', 'https://example.test/org-2'],
    'an empty fragment' => ['https://example.test/#', 'https://example.test/#2'],
    'a fragment' => ['https://example.test/#org', 'https://example.test/#org-2'],
    'the last fragment of several' => ['https://example.test/#a#org', 'https://example.test/#a#org-2'],
    'a one-character id' => ['x', 'x-2'],
]);

it('scopes the instance watermark by the entity id', function (mixed $entity, string $expected): void {
    expect(sosGuardCall(sosGuardService(), 'scopeEntityIdForTemplateInstanceWatermark', $entity))->toBe($expected);
})->with([
    'no entity' => [null, ''],
    'an entity with no id accessor' => fn (): array => [new class implements Term {}, ''],
    'an integer id' => fn (): array => [new class implements Term
    {
        public function id(): int
        {
            return 5;
        }
    }, '5'],
    'a non-scalar id' => fn (): array => [new class implements Term
    {
        /** @return list<string> */
        public function id(): array
        {
            return ['5'];
        }
    }, ''],
]);

it('reads the effective template instance index without a watermark', function (array $context, ?int $expected): void {
    $method = new ReflectionMethod(SchemaOutputService::class, 'resolveEffectiveTemplateInstanceIndexForWatermark');

    expect($method->invoke(sosGuardService(), 'tpl-1', null, [], $context, null, false))->toBe($expected);
})->with([
    'no index' => [[], null],
    'an unparseable index' => [[SchemaOutputService::ANTLERS_SCHEMA_TEMPLATE_INSTANCE_INDEX => 'two'], null],
    'a negative index' => [[SchemaOutputService::ANTLERS_SCHEMA_TEMPLATE_INSTANCE_INDEX => -1], null],
    'a numeric string' => [[SchemaOutputService::ANTLERS_SCHEMA_TEMPLATE_INSTANCE_INDEX => '3'], 3],
    'an integer' => [[SchemaOutputService::ANTLERS_SCHEMA_TEMPLATE_INSTANCE_INDEX => 0], 0],
]);

it('allocates through the watermark bag when one is given', function (): void {
    $method = new ReflectionMethod(SchemaOutputService::class, 'resolveEffectiveTemplateInstanceIndexForWatermark');
    $bag = new SchemaTemplateInstanceWatermarkBag;

    expect($method->invoke(sosGuardService(), 'tpl-1', null, [], [SchemaOutputService::ANTLERS_SCHEMA_TEMPLATE_INSTANCE_INDEX => 3], $bag, false))
        ->toBeInt();
});

it('resolves an empty property value to nothing', function (mixed $value): void {
    expect(sosGuardResolveProperty(sosGuardService(), $value))->toBeNull();
})->with([
    'null' => [null],
    'an empty string' => [''],
    'an empty row list' => [[]],
    'a disabled group' => [['mode' => 'disable', 'value' => [['type' => 'text', 'value' => 'X']]]],
    'an object' => [new stdClass],
]);

it('keeps the inherited base of an inherit_custom group with no rows of its own', function (): void {
    $value = ['mode' => 'inherit_custom', 'value' => 'not rows', '_inherit_base' => 'Inherited'];

    expect(sosGuardResolveProperty(sosGuardService(), $value))->toBe('Inherited');
});

it('skips disabled rows and rows that resolve to an empty string', function (): void {
    $service = sosGuardService();
    $service->shouldReceive('resolveSingleReplicatorRowToValue')->andReturnUsing(
        fn (array $row): mixed => $row['value'] ?? null
    );

    $out = sosGuardResolveProperty($service, [
        ['type' => 'text', 'value' => 'Hidden', 'enabled' => false],
        ['type' => 'text', 'value' => ''],
        ['type' => 'text', 'value' => 'Shown'],
    ]);

    expect($out)->toBe('Shown');
});

it('merges two resolved property values', function (mixed $a, mixed $b, mixed $expected): void {
    expect(sosGuardCall(sosGuardService(), 'mergeAdditiveResolvedPropertyValues', $a, $b))->toBe($expected);
})->with([
    'both null' => [null, null, null],
    'both empty strings' => ['', '', null],
    'null and an empty string' => [null, '', null],
    'only the second' => ['', 'B', 'B'],
    'only the first' => ['A', null, 'A'],
    'two empty lists' => [[], [], null],
]);

it('has no pair-strip pattern for a handle that is not an identifier', function (string $handle): void {
    expect(sosGuardCall(sosGuardService(), 'pairStripRegexPatternForTermsLikeHandle', $handle))->toBe('');
})->with([
    'a dash' => ['bad-handle'],
    'a leading digit' => ['9tags'],
    'a space' => ['tag s'],
]);

it('strips only pair bodies for usable terms handles', function (): void {
    $template = '{{ tags }}{{ schema_object }}{{ /tags }}{{ bad-handle }}x{{ /bad-handle }}{{ title }}';

    expect(sosGuardCall(sosGuardService(), 'stripTermsLikeFieldPairBodiesForFillMissingAnalysis', $template, ['', 'bad-handle', 'tags']))
        ->toBe('{{ bad-handle }}x{{ /bad-handle }}{{ title }}');
});

it('skips an empty handle when hydrating grouped terms fields', function (): void {
    $context = ['' => ['alpha'], 'title' => 'Post'];

    expect(sosGuardCall(sosGuardService(), 'hydrateMergedContextTermsLikeFieldsIfRawSlugs', null, $context, ['' => [sosGuardField('', null)]]))
        ->toBe($context);
});

it('skips empty, reserved and absent overlay keys when hydrating overlay slugs', function (): void {
    $context = [
        '' => 'alpha',
        SchemaOutputService::ANTLERS_SCHEMA_TEMPLATE_INSTANCE_INDEX => 'alpha',
        SchemaOutputService::ANTLERS_SCHEMA_PRIMARY_RELATED_TAXONOMY_TERM => 'alpha',
    ];
    $overlay = $context + ['absent' => 'alpha'];
    $template = '{{ schema_primary_related_taxonomy_term }}';

    expect(sosGuardCall(sosGuardService(), 'hydrateAntlersOverlayTermSlugValuesIfRaw', $context, $overlay, $template))->toBe($context);
});

it('selects no terms field from no candidates', function (): void {
    expect(sosGuardCall(sosGuardService(), 'selectTermsLikeBlueprintFieldForMergedValue', [], ['alpha']))->toBeNull();
});

it('selects the multi-select field for a stored slug list', function (): void {
    $single = sosGuardField('tags', 1);
    $multi = sosGuardField('tags', null);

    expect(sosGuardCall(sosGuardService(), 'selectTermsLikeBlueprintFieldForMergedValue', [$single, $multi], ['alpha', 'beta']))->toBe($multi);
});

it('keys a terms augment parent with no readable id by its pinned identity', function (mixed $id): void {
    /** @var LocalizedTerm&MockInterface $parent */
    $parent = Mockery::mock(LocalizedTerm::class);
    $parent->shouldReceive('id')->andReturn($id);
    $service = sosGuardService();

    $key = sosGuardCall($service, 'memoKeyForTermsAugmentParent', $parent);

    expect($key)->toBeString()->toStartWith($parent::class.'::');
    expect($key)->not->toBe($parent::class.'::');
    expect(sosGuardCall($service, 'memoKeyForTermsAugmentParent', $parent))->toBe($key);
})->with([
    'null' => [null],
    'an empty string' => [''],
]);

it('keys a terms augment parent whose id accessor throws by its pinned identity', function (): void {
    /** @var LocalizedTerm&MockInterface $parent */
    $parent = Mockery::mock(LocalizedTerm::class);
    $parent->shouldReceive('id')->andThrow(new RuntimeException('No id on this double.'));

    $key = sosGuardCall(sosGuardService(), 'memoKeyForTermsAugmentParent', $parent);

    expect($key)->toBeString()->toStartWith($parent::class.'::');
    expect($key)->not->toBe($parent::class.'::');
});

it('omits a property that resolves to an empty string from the built node', function (): void {
    $entry = Mockery::mock(EntryModel::class);
    $entry->shouldReceive('get')->andReturnNull();
    $entry->shouldReceive('id')->andReturn('tpl-1');
    EntryFacade::partialMock()->shouldReceive('find')->with('tpl-1')->andReturn($entry);
    $service = sosGuardService();
    $service->shouldReceive('getEffectiveSchema')->andReturn(['type' => 'Thing', 'properties' => ['name' => 'raw', 'description' => 'raw']]);
    $service->shouldReceive('entryIdToNodeId')->andReturn('https://example.test/#tpl-1');
    $service->shouldReceive('resolvePropertyValue')->andReturnUsing(
        fn (mixed $value, mixed $entity, array $graph, array $visited, array &$a, array &$b, array &$c, array $context): string => $context[SchemaOutputService::ANTLERS_SCHEMA_PROPERTY_KEY] === 'name' ? '' : 'Kept'
    );

    $node = mustBeArray(sosGuardEntryToNode($service, 'tpl-1'), 'the built node');

    expect($node)->not->toHaveKey('name');
    expect($node['description'] ?? null)->toBe('Kept');
});

it('collects no terms-like fields from an augmentable with no blueprint', function (): void {
    /** @var Augmentable&MockInterface $entity */
    $entity = Mockery::mock(Augmentable::class);

    expect(sosGuardCall(sosGuardService(), 'collectTermsLikeBlueprintFieldsGroupedByHandleUncached', $entity))->toBe([]);
});

it('hydrates a slug list holding a non-slug item without the memo', function (): void {
    $present = ['alpha', 7];
    RequestAttributeMemo::remember('_app_schema_output_raw_term_slug_hydration_memo', 'l'.hash('xxh128', 'alpha'), static fn (): array => ['in' => ['alpha'], 'out' => 'memoized']);

    expect(sosGuardCall(sosGuardService(), 'memoizedHydrateRawTermSlugValueToAugmentedValue', $present))->not->toBe('memoized');
});

it('hydrates directly when the memo row was stored for a different input', function (): void {
    RequestAttributeMemo::remember('_app_schema_output_raw_term_slug_hydration_memo', 's'.'alpha', static fn (): array => ['in' => 'beta', 'out' => 'memoized']);

    expect(sosGuardCall(sosGuardService(), 'memoizedHydrateRawTermSlugValueToAugmentedValue', 'alpha'))->not->toBe('memoized');
});

it('reuses the memo row stored for the same input', function (): void {
    RequestAttributeMemo::remember('_app_schema_output_raw_term_slug_hydration_memo', 's'.'alpha', static fn (): array => ['in' => 'alpha', 'out' => 'memoized']);

    expect(sosGuardCall(sosGuardService(), 'memoizedHydrateRawTermSlugValueToAugmentedValue', 'alpha'))->toBe('memoized');
});

/**
 * The service with each replicator row resolving to its own `out` value.
 */
function sosGuardRowsService(): SchemaOutputService&MockInterface
{
    $service = sosGuardService();
    $service->shouldReceive('resolveSingleReplicatorRowToValue')->andReturnUsing(fn (array $row): mixed => $row['out'] ?? null);

    return $service;
}

it('collects the values of the enabled rows, flattening lists and dropping empty values', function (array $rows, mixed $expected): void {
    expect(sosGuardResolveProperty(sosGuardRowsService(), $rows))->toBe($expected);
})->with([
    'a null row before a value' => [[['out' => null], ['out' => 'A']], 'A'],
    'an empty row before a value' => [[['out' => ''], ['out' => 'A']], 'A'],
    'a disabled row before a value' => [[['out' => 'X', 'enabled' => false], ['out' => 'A']], 'A'],
    'a list with empty items' => [[['out' => ['x', null, '', 'y']]], ['x', 'y']],
    'two values' => [[['out' => 'A'], ['out' => 'B']], ['A', 'B']],
    'a keyed value' => [[['out' => ['k' => 'v']]], ['k' => 'v']],
    'only empty rows' => [[['out' => null], ['out' => '']], null],
    'no rows' => [[], null],
]);

it('resolves a property group by its mode', function (array $group, mixed $expected): void {
    expect(sosGuardResolveProperty(sosGuardRowsService(), $group))->toBe($expected);
})->with([
    'custom rows' => [['mode' => 'custom', 'value' => [['out' => 'A']]], 'A'],
    'custom with no value' => [['mode' => 'custom'], null],
    'disabled' => [['mode' => 'disable', 'value' => [['out' => 'A']]], null],
    'inherit_custom with no base and no rows' => [['mode' => 'inherit_custom'], null],
    'inherit_custom with a base only' => [['mode' => 'inherit_custom', '_inherit_base' => 'Base'], 'Base'],
    'inherit_custom with rows only' => [['mode' => 'inherit_custom', 'value' => [['out' => 'A']]], 'A'],
    'inherit_custom with rows that are not a list' => [['mode' => 'inherit_custom', '_inherit_base' => 'Base', 'value' => 'text'], 'Base'],
]);

/**
 * The service building `tpl-1` from the given type and properties, recording every
 * property resolution and node id computed.
 *
 * @param  array<string, mixed>  $properties
 * @param  list<array<string, mixed>>  $resolutions  Each property resolution, by reference.
 * @param  list<array<int, string>>  $nodeIdVisits  The visited ids each node id saw, by reference.
 */
function sosGuardNodeService(mixed $type, array $properties, array &$resolutions = [], array &$nodeIdVisits = []): SchemaOutputService&MockInterface
{
    $entry = Mockery::mock(EntryModel::class);
    $entry->shouldReceive('get')->andReturnNull();
    $entry->shouldReceive('id')->andReturn('tpl-1');
    EntryFacade::partialMock()->shouldReceive('find')->andReturn($entry);
    $service = sosGuardService();
    $service->shouldReceive('getEffectiveSchema')->andReturn(['type' => $type, 'properties' => $properties]);
    $service->shouldReceive('entryIdToNodeId')->andReturnUsing(function (string $id, mixed $entity, array $graph, array $visited) use (&$nodeIdVisits): string {
        $nodeIdVisits[] = $visited;

        return 'https://example.test/#'.$id;
    });
    $service->shouldReceive('resolvePropertyValue')->andReturnUsing(
        function (mixed $value, mixed $entity, array $graph, array $visited, array &$a, array &$b, array &$c, array $context) use (&$resolutions): mixed {
            $resolutions[] = ['value' => $value, 'visited' => $visited, 'context' => $context, 'building' => SchemaTemplateNodeBuildStack::contains('tpl-1')];

            return $value;
        }
    );

    return $service;
}

/**
 * Build `tpl-1` as a node.
 *
 * @param  array<int, string>  $visited
 * @param  array<string, mixed>  $antlers
 */
function sosGuardBuildNode(SchemaOutputService $service, mixed $context = null, array $visited = [], array $antlers = [], ?SchemaTemplateInstanceWatermarkBag $bag = null, ?string $ownBusinessId = null, string $entryId = 'tpl-1'): mixed
{
    $emittedEntryIds = [];
    $emittedNodeIds = [];
    $materialized = [];

    return (new ReflectionMethod(SchemaOutputService::class, 'schemaObjectEntryToNode'))->invokeArgs($service, [
        $entryId, $context, [], $visited, &$emittedEntryIds, &$emittedNodeIds, &$materialized, $antlers, $bag, $ownBusinessId,
    ]);
}

it('builds no node for a blank scalar type', function (mixed $type): void {
    expect(sosGuardBuildNode(sosGuardNodeService($type, [])))->toBeNull();
})->with([
    'an empty string' => [''],
    'null' => [null],
]);

it('reads the node @type from the declared types', function (mixed $type, mixed $expected): void {
    $node = mustBeArray(sosGuardBuildNode(sosGuardNodeService($type, [])), 'the built node');

    expect($node['@type'])->toBe($expected);
})->with([
    'one type after a blank' => [['', 'Thing'], 'Thing'],
    'a numeric type' => [5, '5'],
    'a non-scalar type' => [[['Thing']], ''],
    'two types' => [['Thing', 'Person'], ['Thing', 'Person']],
]);

it('builds an entry whose id only loosely matches a visited one', function (): void {
    expect(sosGuardBuildNode(sosGuardNodeService('Thing', []), null, ['1e1'], [], null, null, '10'))->toBeArray();
});

it('applies a compatible type override, and keeps the declared type for another', function (array $override, mixed $expected): void {
    $node = mustBeArray(sosGuardBuildNode(sosGuardNodeService('Thing', []), null, [], [SchemaOutputService::ANTLERS_SCHEMA_TEMPLATE_TYPE_OVERRIDE => $override]), 'the built node');

    expect($node['@type'])->toBe($expected);
})->with([
    'a subtype' => [['Person'], 'Person'],
    'an unknown type' => [['NotASchemaType'], 'Thing'],
    'no override' => [[], 'Thing'],
]);

it('types the context entity\'s own business node by its subtype, else as a podcast episode', function (?array $subtype, ?array $podcast, mixed $expected): void {
    $service = sosGuardNodeService('MedicalOrganization', []);
    $service->shouldReceive('resolveContextEntitySubtypeAtType')->once()->andReturn($subtype);
    $service->shouldReceive('resolvePodcastEpisodeAtType')->times($subtype === null ? 1 : 0)->andReturn($podcast);

    $node = mustBeArray(sosGuardBuildNode($service, null, [], [], null, 'tpl-1'), 'the built node');

    expect($node['@type'])->toBe($expected);
})->with([
    'a subtype' => [['MedicalClinic'], null, 'MedicalClinic'],
    'a podcast episode' => [null, ['Article', 'PodcastEpisode'], ['Article', 'PodcastEpisode']],
    'neither' => [null, null, 'MedicalOrganization'],
]);

it('keeps the declared type for a node that is not the context entity\'s own business node', function (?string $ownBusinessId): void {
    $service = sosGuardNodeService('MedicalOrganization', []);
    $service->shouldNotReceive('resolveContextEntitySubtypeAtType');
    $service->shouldNotReceive('resolvePodcastEpisodeAtType');

    $node = mustBeArray(sosGuardBuildNode($service, null, [], [], null, $ownBusinessId), 'the built node');

    expect($node['@type'])->toBe('MedicalOrganization');
})->with([
    'no own business node' => [null],
    'another entry' => ['tpl-2'],
]);

it('resolves each property with the entry added to the visited ids, while the entry is building', function (): void {
    $resolutions = [];
    $nodeIdVisits = [];
    $service = sosGuardNodeService('Thing', ['empty' => null, 'name' => 'N'], $resolutions, $nodeIdVisits);

    $node = mustBeArray(sosGuardBuildNode($service, null, ['parent-1']), 'the built node');

    expect($node)->toBe(['@id' => 'https://example.test/#tpl-1', '@type' => 'Thing', 'name' => 'N'])
        ->and($nodeIdVisits)->toBe([['parent-1', 'tpl-1']])
        ->and(array_column($resolutions, 'visited'))->toBe([['parent-1', 'tpl-1'], ['parent-1', 'tpl-1']])
        ->and(array_column($resolutions, 'building'))->toBe([true, true])
        ->and(SchemaTemplateNodeBuildStack::contains('tpl-1'))->toBeFalse();
});

it('commits the template instance index it issues', function (): void {
    $bag = new SchemaTemplateInstanceWatermarkBag;

    sosGuardBuildNode(sosGuardNodeService('Thing', []), null, [], [SchemaOutputService::ANTLERS_SCHEMA_TEMPLATE_INSTANCE_INDEX => 0], $bag);

    expect(array_values($bag->lastIssuedByKey))->toBe([0]);
});

it('exposes an entry context entity as the property source entry, unless one is given', function (Closure $context, array $antlers, mixed $expected): void {
    $resolutions = [];
    $entity = $context();
    sosGuardBuildNode(sosGuardNodeService('Thing', ['name' => 'N'], $resolutions), $entity, [], mustBeStringKeyed($antlers, 'the Antlers context'));

    $propertyContext = mustBeArray($resolutions[0]['context'], 'the property context');
    expect($propertyContext['source_entry'] ?? null)->toBe($expected === 'ENTITY' ? $entity : $expected);
})->with([
    'an entry' => [fn (): EntryModel => Mockery::mock(EntryModel::class), [], 'ENTITY'],
    'an entry, with a source entry given' => [fn (): EntryModel => Mockery::mock(EntryModel::class), ['source_entry' => 'given'], 'given'],
    'a term' => [fn (): Term => Mockery::mock(Term::class), [], null],
    'no context entity' => [fn (): null => null, [], null],
]);

/**
 * The service as a partial mock over the given cascade, recording each node build.
 *
 * @param  list<array<string, mixed>>  $builds  Each node build's arguments, by reference.
 */
function sosGuardStandaloneService(SchemaTemplatesCascadeService $cascade, array &$builds): SchemaOutputService&MockInterface
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
    $service->shouldReceive('schemaObjectEntryToNode')->andReturnUsing(
        function (string $id, mixed $entity, array $graph, array $visited, array &$a, array &$b, array &$c, array $antlers, mixed $bag, ?string $own) use (&$builds): array {
            $builds[] = ['graph' => $graph, 'bag' => $bag, 'own' => $own];

            return ['@id' => '#'.$id, '@type' => 'Thing'];
        }
    );

    return $service;
}

it('builds a standalone node without resolving a cascade for no context entity', function (): void {
    /** @var SchemaTemplatesCascadeService&MockInterface $cascade */
    $cascade = Mockery::mock(SchemaTemplatesCascadeService::class);
    $cascade->shouldNotReceive('resolve');
    $builds = [];

    expect(sosGuardStandaloneService($cascade, $builds)->buildStandaloneSchemaObjectNode('tpl-1'))->toBe(['@id' => '#tpl-1', '@type' => 'Thing'])
        ->and($builds[0]['graph'])->toBe([])
        ->and($builds[0]['own'])->toBeNull();
});

it('builds a standalone node against the context entity\'s cascade slots and own business node', function (): void {
    $entity = Mockery::mock(EntryModel::class);
    /** @var SchemaTemplatesCascadeService&MockInterface $cascade */
    $cascade = Mockery::mock(SchemaTemplatesCascadeService::class);
    $cascade->shouldReceive('resolve')->once()->with($entity)->andReturn(['subject_entry' => 's1', 'main_content_entry' => 'm1']);
    $builds = [];

    sosGuardStandaloneService($cascade, $builds)->buildStandaloneSchemaObjectNode('tpl-1', $entity);

    expect($builds[0]['graph'])->toBe(['s1', 'm1'])
        ->and($builds[0]['own'])->toBe('m1');
});

it('shares the given watermark bag and graph ids with a standalone node build', function (): void {
    /** @var SchemaTemplatesCascadeService&MockInterface $cascade */
    $cascade = Mockery::mock(SchemaTemplatesCascadeService::class);
    $cascade->shouldNotReceive('resolve');
    $builds = [];
    $bag = new SchemaTemplateInstanceWatermarkBag;
    $emitted = [];
    $materialized = [];

    sosGuardStandaloneService($cascade, $builds)->buildStandaloneSchemaObjectNodeSharingEmittedNodeIds('tpl-1', Mockery::mock(EntryModel::class), [], $emitted, $materialized, ['g1'], $bag);

    expect($builds[0]['bag'])->toBe($bag)
        ->and($builds[0]['graph'])->toBe(['g1'])
        ->and($builds[0]['own'])->toBeNull();
});

it('reads no graph ids, and resolves no cascade, for a missing entity', function (): void {
    /** @var SchemaTemplatesCascadeService&MockInterface $cascade */
    $cascade = Mockery::mock(SchemaTemplatesCascadeService::class);
    $cascade->shouldNotReceive('resolve');
    $builds = [];

    expect(sosGuardCall(sosGuardStandaloneService($cascade, $builds), 'resolveGraphEntryIdsForEntity', null))->toBe([]);
});

it('anchors template instance allocation on the first non-empty graph slot, else the context entity', function (array $graph, string $expected): void {
    $entity = Mockery::mock(EntryModel::class);
    $entity->shouldReceive('id')->andReturn('ctx-1');

    expect(sosGuardCall(sosGuardService(), 'templateInstanceWatermarkAllocationAnchor', $graph, $entity))->toBe($expected);
})->with([
    'a blank first slot' => [['', 's1'], 's1'],
    'only blank slots' => [[''], 'ctx-1'],
]);

it('merges two empty additive property values to nothing', function (mixed $a, mixed $b): void {
    expect(sosGuardCall(sosGuardService(), 'mergeAdditiveResolvedPropertyValues', $a, $b))->toBeNull();
})->with([
    'null and empty' => [null, ''],
    'empty and null' => ['', null],
]);

it('parses the template instance index from the Antlers context', function (mixed $raw, ?int $expected): void {
    expect(sosGuardCall(sosGuardService(), 'parseTemplateInstanceIndexFromAntlersContext', [SchemaOutputService::ANTLERS_SCHEMA_TEMPLATE_INSTANCE_INDEX => $raw]))->toBe($expected);
})->with([
    'an integer' => [3, 3],
    'a numeric string' => ['3', 3],
    'an empty string' => ['', null],
    'text' => ['three', null],
    'a negative integer' => [-1, null],
    'true' => [true, null],
    'a float' => [1.5, null],
]);

it('reads the site root as the root path for an entry with no site', function (): void {
    $entry = Mockery::mock(EntryModel::class);
    $entry->shouldReceive('site')->andReturnNull();

    expect(sosGuardCall(sosGuardService(), 'siteRootForEntry', $entry))->toBe('/');
});

it('keys an entity context on its id, else on its pinned identity', function (mixed $id, bool $byId): void {
    $entity = Mockery::mock(EntryModel::class);
    $entity->shouldReceive('id')->andReturn($id);

    $key = sosGuardCall(sosGuardService(), 'antlersEntityContextCacheKey', $entity);

    expect($key)->toBe($byId ? $id : 'o'.spl_object_id($entity));
})->with([
    'an id' => ['entry-1', true],
    'an empty id' => ['', false],
    'a numeric id' => [5, false],
]);

it('exposes an entry context entity as the scoped entry alias', function (Closure $make, bool $aliased): void {
    $entity = $make();
    $context = mustBeArray(sosGuardCall(sosGuardService(), 'applyScopedEntryAliasToSchemaAntlersContext', ['title' => 'T'], $entity), 'the context');

    expect($context)->toBe($aliased ? ['title' => 'T', 'entry' => $entity] : ['title' => 'T']);
})->with([
    'an entry' => [fn (): EntryModel => Mockery::mock(EntryModel::class), true],
    'a term' => [fn (): Term => Mockery::mock(Term::class), false],
    'nothing' => [fn (): null => null, false],
]);

it('flags whether an entry context belongs to a podcast series', function (): void {
    $entry = Mockery::mock(EntryModel::class);
    $entry->shouldReceive('blueprint')->andReturnNull();
    $entry->shouldReceive('get')->andReturnNull();

    expect(sosGuardCall(sosGuardService(), 'injectAudioSeriesPodcastSignal', ['title' => 'T'], $entry))->toBe(['title' => 'T', SchemaOutputService::ANTLERS_IS_PODCAST_EPISODE => false])
        ->and(sosGuardCall(sosGuardService(), 'injectAudioSeriesPodcastSignal', ['title' => 'T'], Mockery::mock(Term::class)))->toBe(['title' => 'T']);
});

it('materializes no context for an entity with no field accessor', function (): void {
    expect(sosGuardCall(sosGuardService(), 'materializeAntlersContextFromEntity', new class implements Term {}))->toBe([]);
});

it('materializes the stored values of an entity that cannot augment', function (): void {
    $entity = new class implements Term
    {
        /**
         * @return Collection<string, string>
         */
        public function values(): Collection
        {
            return collect(['name' => 'Stored']);
        }
    };

    expect(sosGuardCall(sosGuardService(), 'materializeAntlersContextFromEntity', $entity))->toBe(['name' => 'Stored']);
});
