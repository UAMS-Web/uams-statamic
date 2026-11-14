<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\JsonLdCompositionProfiler;
use App\Services\SchemaTemplates\SchemaJsonLdAtIdDeduplication;
use App\Services\SchemaTemplates\SchemaJsonLdFragmentCache;
use App\Services\SchemaTemplates\SchemaJsonLdOutputCache;
use App\Services\SchemaTemplates\SchemaOutputService;
use App\Services\SchemaTemplates\SchemaTemplateEmitContext;
use App\Services\SchemaTemplates\SchemaTemplateEmitContextRuntimeStack;
use App\Services\SchemaTemplates\SchemaTemplateInstanceWatermarkBag;
use App\Services\SchemaTemplates\SchemaTemplatesCascadeService;
use App\Services\SchemaTemplates\SchemaThingFromRelationshipResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Entries\Entry as EntryModel;
use Statamic\Facades\Data;
use Statamic\Facades\GlobalSet;
use Statamic\Globals\Variables;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(SchemaThingFromRelationshipResolver::class);

/**
 * Pins {@see SchemaThingFromRelationshipResolver::resolve()} end to end against
 * mocked collaborators: mode selection, template id choice, source-context
 * merging, the compact-reference and fragment-cache fast paths, the emit maps a
 * graph build shares, dedupe by @id, and the composition profile.
 *
 * @command  php artisan test --compact tests/Feature/Services/SchemaTemplates/SchemaThingFromRelationshipResolverResolveTest.php
 */

/**
 * A mocked entry with an id and a canonical absolute URL.
 */
function thingResolveEntry(string $id, ?string $url = null): EntryModel&MockInterface
{
    $entry = Mockery::mock(EntryModel::class);
    $entry->shouldReceive('id')->andReturn($id);
    $entry->shouldReceive('absoluteUrl')->andReturn($url ?? 'https://example.test/'.$id);
    $entry->shouldReceive('url')->andReturnNull();
    $entry->shouldReceive('slug')->andReturn($id);
    $entry->shouldReceive('collectionHandle')->andReturn('people');
    $entry->shouldReceive('site')->andReturnNull();

    return $entry;
}

/**
 * A schema output service whose standalone builds return a node per template and target.
 *
 * @param  array<string, mixed>  $overrides  Node fields merged into every build.
 */
function thingResolveOutput(array $overrides = []): SchemaOutputService&MockInterface
{
    $output = Mockery::mock(SchemaOutputService::class);
    $output->shouldReceive('buildFlattenedSchemaAntlersContextFromDataObject')->andReturnUsing(
        function (EntryModel $entry): array {
            /** @var string $id */
            $id = $entry->id();

            return ['title' => 'T-'.$id];
        }
    );
    $output->shouldReceive('buildStandaloneSchemaObjectNode')->andReturnUsing(
        fn (string $template, mixed $entity, array $context): array => $overrides + ['@type' => 'Person', 'template' => $template, 'title' => $context['title'] ?? null]
    )->byDefault();

    return $output;
}

/**
 * A resolver over the given output service and an optional cascade.
 */
function thingResolveResolver(SchemaOutputService $output, ?SchemaTemplatesCascadeService $cascade = null): SchemaThingFromRelationshipResolver
{
    if ($cascade === null) {
        $cascade = Mockery::mock(SchemaTemplatesCascadeService::class);
        $cascade->shouldReceive('resolve')->andReturn([
            'enabled' => true,
            'main_content_entry' => 'cascade-tpl',
            'subject_entry' => null,
            'webpage_entry' => null,
            'website_entry' => null,
        ]);
    }

    return new SchemaThingFromRelationshipResolver(
        $output,
        $cascade,
        new SchemaJsonLdAtIdDeduplication,
        new SchemaJsonLdFragmentCache(new SchemaJsonLdOutputCache)
    );
}

/**
 * Decode a resolve() result.
 *
 * @return list<array<string, mixed>>
 */
function thingResolveDecode(string $json): array
{
    /** @var list<array<string, mixed>> $decoded */
    $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

    return $decoded;
}

/**
 * A fresh emit context with the given pass maps.
 *
 * @param  array<string, bool>  $emittedNodeIds  Full nodes already emitted.
 * @param  array<string, bool>  $materialized  Full nodes that survived merging.
 */
function thingResolveEmitContext(array $emittedNodeIds = [], array $materialized = []): SchemaTemplateEmitContext
{
    $emittedEntryIds = [];

    return new SchemaTemplateEmitContext($emittedEntryIds, $emittedNodeIds, $materialized, ['g1'], null, new SchemaTemplateInstanceWatermarkBag);
}

beforeEach(function (): void {
    // No route or request URL names a context entity
    Data::shouldReceive('findByRequestUrl')->andReturnNull()->byDefault();
    Cache::flush();
    Config::set('schema_templates.json_ld_cache.enabled', true);
    Config::set('schema_templates.json_ld_cache.fragments_enabled', true);
    Config::set('schema_templates.json_ld_compose_profile.enabled', false);
    request()->attributes->remove(JsonLdCompositionProfiler::REQUEST_JSON_LD_COMPOSITION_PROFILE);
});

it('builds one node per direct target with its canonical @id, and dedupes repeats', function (): void {
    $a = thingResolveEntry('a');
    $b = thingResolveEntry('b');

    $nodes = thingResolveDecode(thingResolveResolver(thingResolveOutput())->resolve(
        ['about' => [$a, $b, $a]],
        ['current_field' => 'about', 'schema_object' => 'tpl']
    ));

    expect($nodes)->toBe([
        ['@type' => 'Person', 'template' => 'tpl', 'title' => 'T-a', '@id' => 'https://example.test/a'],
        ['@type' => 'Person', 'template' => 'tpl', 'title' => 'T-b', '@id' => 'https://example.test/b'],
    ]);
});

it('treats an unknown or non-string mode as direct, and normalizes a named mode', function (mixed $mode, int $count): void {
    $nodes = thingResolveDecode(thingResolveResolver(thingResolveOutput())->resolve(
        ['about' => [thingResolveEntry('a')]],
        ['mode' => $mode, 'current_field' => 'about', 'schema_object' => 'tpl']
    ));

    // Secondary, global and tertiary modes need their own parameters, so they collect nothing here
    expect($nodes)->toHaveCount($count);
})->with([
    'unknown' => ['sideways', 1],
    'not a string' => [5, 1],
    'padded direct' => [' DIRECT ', 1],
    'secondary' => [' Secondary ', 0],
    'global' => ['global', 0],
    'tertiary' => ['TERTIARY', 0],
]);

it('takes the template from the parameter, or from the cascade without a usable one', function (mixed $param, string $expected): void {
    $nodes = thingResolveDecode(thingResolveResolver(thingResolveOutput())->resolve(
        ['about' => [thingResolveEntry('a')]],
        ['current_field' => 'about', 'schema_object' => $param]
    ));

    expect($nodes[0]['template'])->toBe($expected);
})->with([
    'named' => ['tpl', 'tpl'],
    'empty' => ['', 'cascade-tpl'],
    'not a string' => [5, 'cascade-tpl'],
    'missing' => [null, 'cascade-tpl'],
]);

it('skips a target whose cascade names no template', function (mixed $template): void {
    $cascade = Mockery::mock(SchemaTemplatesCascadeService::class);
    $cascade->shouldReceive('resolve')->andReturn([
        'enabled' => true,
        'main_content_entry' => $template,
        'subject_entry' => null,
        'webpage_entry' => null,
        'website_entry' => null,
    ]);
    $output = thingResolveOutput();
    $output->shouldReceive('buildStandaloneSchemaObjectNode')->never();

    expect(thingResolveResolver($output, $cascade)->resolve(['about' => [thingResolveEntry('a')]], ['current_field' => 'about']))->toBe('[]');
})->with([
    'none' => [null],
    'empty' => [''],
]);

it('skips targets whose template builds no node', function (mixed $node): void {
    $output = thingResolveOutput();
    $output->shouldReceive('buildStandaloneSchemaObjectNode')->andReturn($node);

    expect(thingResolveResolver($output)->resolve(['about' => [thingResolveEntry('a')]], ['current_field' => 'about', 'schema_object' => 'tpl']))->toBe('[]');
})->with([
    'null' => [null],
    'empty' => [[]],
]);

it('moves on to the next target after skipping one', function (string $skip): void {
    $cascade = Mockery::mock(SchemaTemplatesCascadeService::class);
    $cascade->shouldReceive('resolve')->andReturnUsing(fn (EntryModel $entry): array => [
        'enabled' => true,
        'main_content_entry' => $skip === 'no template' && $entry->id() === 'a' ? null : 'tpl',
        'subject_entry' => null,
        'webpage_entry' => null,
        'website_entry' => null,
    ]);
    $output = thingResolveOutput();
    $output->shouldReceive('buildStandaloneSchemaObjectNode')->andReturnUsing(
        fn (string $template, EntryModel $entry): ?array => $entry->id() === 'a' ? ['null node' => null, 'empty node' => [], 'no template' => ['@type' => 'Person']][$skip] : ['@type' => 'Person']
    );

    $json = thingResolveResolver($output, $cascade)->resolve(['about' => [thingResolveEntry('a'), thingResolveEntry('b')]], ['current_field' => 'about']);

    expect(thingResolveDecode($json))->toBe([['@type' => 'Person', '@id' => 'https://example.test/b']]);
})->with([
    'no template' => ['no template'],
    'null node' => ['null node'],
    'empty node' => ['empty node'],
]);

it('emits every node without an @id, and keeps the template @id for a target without one', function (): void {
    $output = Mockery::mock(SchemaOutputService::class);
    $output->shouldReceive('buildStandaloneSchemaObjectNode')->andReturn(['@type' => 'Thing', 'name' => 'x']);

    // URL strings resolve to no entity, but replicator rows keep them as targets
    $rows = [['type' => 'r', 'value' => ['title' => 'one']], ['type' => 'r', 'value' => ['title' => 'two']]];

    $nodes = thingResolveDecode(thingResolveResolver($output)->resolve(
        ['segments' => $rows],
        ['current_field' => 'segments', 'current_field_replicator' => true, 'schema_object' => 'tpl']
    ));

    expect($nodes)->toBe([['@type' => 'Thing', 'name' => 'x'], ['@type' => 'Thing', 'name' => 'x']]);
});

it('merges the source entry into each target context unless told not to', function (mixed $flag, bool $merged): void {
    $source = Mockery::mock(EntryModel::class);
    $source->shouldReceive('id')->andReturn('src');
    $source->shouldReceive('site')->andReturnNull();
    Data::shouldReceive('find')->with('src')->andReturn($source);

    $seen = [];
    $output = thingResolveOutput();
    $output->shouldReceive('buildStandaloneSchemaObjectNode')->andReturnUsing(function (string $t, mixed $e, array $context) use (&$seen): array {
        $seen[] = $context;

        return ['@type' => 'Person'];
    });

    $params = ['current_field' => 'about', 'schema_object' => 'tpl'];
    if ($flag !== 'absent') {
        $params['merge_source_context'] = $flag;
    }

    thingResolveResolver($output)->resolve(['id' => 'src', 'about' => [thingResolveEntry('a')]], $params);

    expect(isset($seen[0]['source_entry']))->toBe($merged)
        ->and($seen[0]['title'])->toBe('T-a');
})->with([
    'absent' => ['absent', true],
    'true' => ['true', true],
    'false' => ['false', false],
    'zero' => [0, false],
]);

it('filters direct targets by term data only in direct mode', function (): void {
    $params = [
        'current_field' => 'about',
        'schema_object' => 'tpl',
        'direct_term_data_equals_field' => 'type',
        'direct_term_data_equals_value' => 'Credential',
    ];

    // An entry is never a matching term, so the direct filter drops it
    expect(thingResolveResolver(thingResolveOutput())->resolve(['about' => [thingResolveEntry('a')]], $params))->toBe('[]');
});

it('resolves the context entity only after a non-direct mode collects targets', function (): void {
    Data::shouldReceive('find')->never();
    Data::shouldReceive('findByRequestUrl')->never();
    GlobalSet::shouldReceive('findByHandle')->andReturnNull();

    expect(thingResolveResolver(thingResolveOutput())->resolve(['id' => 'ctx'], ['mode' => 'global', 'global_handle' => 'settings', 'global_field' => 'founder']))->toBe('[]');
});

it('resolves the context entity for a global target and builds against the target', function (): void {
    $person = thingResolveEntry('founder');
    $variables = Mockery::mock(Variables::class);
    $variables->shouldReceive('get')->andReturnUsing(fn (string $h): mixed => $h === 'founder' ? [$person] : null);
    $set = Mockery::mock(Statamic\Globals\GlobalSet::class);
    $set->shouldReceive('inCurrentSite')->andReturn($variables);
    GlobalSet::shouldReceive('findByHandle')->with('settings')->andReturn($set);
    $page = thingResolveEntry('page');
    Data::shouldReceive('find')->once()->with('page')->andReturn($page);

    $seen = [];
    $output = thingResolveOutput();
    $output->shouldReceive('buildStandaloneSchemaObjectNode')->andReturnUsing(function (string $t, mixed $entity, array $context) use (&$seen): array {
        $seen[] = [$entity, $context['source_entry'] ?? null];

        return ['@type' => 'Person'];
    });

    $nodes = thingResolveDecode(thingResolveResolver($output)->resolve(['id' => 'page'], ['mode' => 'global', 'global_handle' => 'settings', 'global_field' => 'founder', 'schema_object' => 'tpl']));

    expect($nodes)->toBe([['@type' => 'Person', '@id' => 'https://example.test/founder']])
        ->and($seen)->toBe([[$person, $page]]);
});

// ---
// Fragment cache
// ---

/**
 * Resolve one global-sourced target with source merging off, so the build is page-invariant.
 *
 * @param  array<string, mixed>  $params  Extra parameters.
 * @param  array<string, mixed>  $context  Extra Antlers context.
 */
function thingResolvePageInvariant(SchemaThingFromRelationshipResolver $resolver, EntryModel $target, array $params = [], array $context = []): string
{
    return $resolver->resolve($context + ['about' => [$target]], $params + [
        'current_field' => 'about',
        'schema_object' => 'tpl',
        'merge_source_context' => 'false',
        'global_handle' => 'settings',
    ]);
}

it('stores a page-invariant build in the fragment cache and serves the next compose from it', function (): void {
    $target = thingResolveEntry('founder');
    $output = thingResolveOutput();
    $output->shouldReceive('buildStandaloneSchemaObjectNode')->once()->andReturn(['@type' => 'Person', 'name' => 'Built']);
    $resolver = thingResolveResolver($output);

    $first = thingResolvePageInvariant($resolver, $target);
    $second = thingResolvePageInvariant($resolver, $target);

    expect($second)->toBe($first)
        ->and(thingResolveDecode($second))->toBe([['@type' => 'Person', 'name' => 'Built', '@id' => 'https://example.test/founder']]);
});

it('does not cache a build that is not page-invariant', function (Closure $vary): void {
    /** @var array{0: array<string, mixed>, 1: array<string, mixed>, 2: bool} $variation */
    $variation = $vary();
    [$params, $context, $fragments] = $variation;
    Config::set('schema_templates.json_ld_cache.fragments_enabled', $fragments);
    $target = thingResolveEntry('founder');
    $output = thingResolveOutput();
    $output->shouldReceive('buildStandaloneSchemaObjectNode')->twice()->andReturn(['@type' => 'Person']);
    $resolver = thingResolveResolver($output);

    $context = array_map(fn (mixed $v): mixed => $v === 'TARGET' ? $target : $v, $context);
    thingResolvePageInvariant($resolver, $target, $params, $context);
    thingResolvePageInvariant($resolver, $target, $params, $context);
})->with([
    'source context merged' => [fn (): array => [['merge_source_context' => true], [], true]],
    'no global handle' => [fn (): array => [['global_handle' => ' '], [], true]],
    'non-string global handle' => [fn (): array => [['global_handle' => 5], [], true]],
    'fragments disabled' => [fn (): array => [[], [], false]],
    'target is the page entity' => [fn (): array => [[], ['entry' => 'TARGET'], true]],
]);

it('does not cache row-level builds that carry their own template context', function (): void {
    $output = thingResolveOutput();
    $output->shouldReceive('buildStandaloneSchemaObjectNode')->times(4)->andReturn(['@type' => 'Person']);
    $resolver = thingResolveResolver($output);
    $person = thingResolveEntry('p');
    $rows = [['type' => 'r', 'value' => ['who' => [$person]]]];
    $params = [
        'current_field' => 'rows',
        'current_field_replicator' => true,
        'current_field_replicator_relationship_field' => 'who',
        'schema_object' => 'tpl',
        'merge_source_context' => 'false',
        'mode' => 'direct',
        'global_handle' => 'settings',
    ];

    $resolver->resolve(['rows' => $rows], $params);
    $resolver->resolve(['rows' => $rows], $params);
    $resolver->resolve(['rows' => $rows], $params + ['current_field_replicator' => true]);
    $resolver->resolve(['rows' => $rows], $params);
});

it('does not cache a node without a type', function (): void {
    $target = thingResolveEntry('founder');
    $output = thingResolveOutput();
    $output->shouldReceive('buildStandaloneSchemaObjectNode')->twice()->andReturn(['name' => 'untyped']);
    $resolver = thingResolveResolver($output);

    thingResolvePageInvariant($resolver, $target);
    thingResolvePageInvariant($resolver, $target);
});

it('caches a global-mode build without a global handle parameter', function (): void {
    $person = thingResolveEntry('founder');
    $variables = Mockery::mock(Variables::class);
    $variables->shouldReceive('get')->andReturn([$person]);
    $set = Mockery::mock(Statamic\Globals\GlobalSet::class);
    $set->shouldReceive('inCurrentSite')->andReturn($variables);
    GlobalSet::shouldReceive('findByHandle')->andReturn($set);
    $output = thingResolveOutput();
    $output->shouldReceive('buildStandaloneSchemaObjectNode')->once()->andReturn(['@type' => 'Person']);
    $resolver = thingResolveResolver($output);
    $params = ['mode' => 'global', 'global_handle' => 'settings', 'global_field' => 'founder', 'schema_object' => 'tpl', 'merge_source_context' => 'false'];

    $resolver->resolve([], $params);
    $resolver->resolve([], $params);
});

// ---
// Emit context shared with a graph build
// ---

it('compacts a target to a reference when its full node already survived in the graph', function (): void {
    $target = thingResolveEntry('a');
    $output = thingResolveOutput();
    $output->shouldReceive('buildStandaloneSchemaObjectNodeSharingEmittedNodeIds')->never();
    $emit = thingResolveEmitContext(['https://example.test/a' => true], ['https://example.test/a' => true]);

    $json = thingResolveResolver($output)->resolve(['about' => [$target]], ['current_field' => 'about', 'schema_object' => 'tpl', 'emit_context' => $emit]);

    expect(thingResolveDecode($json))->toBe([['@id' => 'https://example.test/a']]);
});

it('builds in full when the graph emitted the @id but no full body survived', function (): void {
    $target = thingResolveEntry('a');
    $output = thingResolveOutput();
    $output->shouldReceive('buildStandaloneSchemaObjectNodeSharingEmittedNodeIds')->once()->andReturn(['@type' => 'Person', 'name' => 'Full']);
    $emit = thingResolveEmitContext(['https://example.test/a' => true]);

    $json = thingResolveResolver($output)->resolve(['about' => [$target]], ['current_field' => 'about', 'schema_object' => 'tpl', 'emit_context' => $emit]);

    expect(thingResolveDecode($json))->toBe([['@type' => 'Person', 'name' => 'Full', '@id' => 'https://example.test/a']])
        ->and($emit->emittedNodeIds)->toHaveKey('https://example.test/a')
        ->and($emit->materializedFullNodeAtIds)->toBe(['https://example.test/a' => true]);
});

it('builds each canonical @id in full once per call and compacts the repeats', function (): void {
    $a = thingResolveEntry('a');
    $alsoA = thingResolveEntry('a2', 'https://example.test/a');
    $output = thingResolveOutput();
    $output->shouldReceive('buildStandaloneSchemaObjectNodeSharingEmittedNodeIds')->once()->andReturn(['@type' => 'Person']);

    $json = thingResolveResolver($output)->resolve(['about' => [$a, $alsoA]], ['current_field' => 'about', 'schema_object' => 'tpl', 'emit_context' => thingResolveEmitContext()]);

    // The compact repeat shares the @id, so the dedupe keeps only the full node
    expect(thingResolveDecode($json))->toBe([['@type' => 'Person', '@id' => 'https://example.test/a']]);
});

it('compacts a repeat built in this call even when the graph no longer holds its full body', function (): void {
    $emit = thingResolveEmitContext();
    $cascade = Mockery::mock(SchemaTemplatesCascadeService::class);
    // The graph drops its record of surviving full bodies before each target
    $cascade->shouldReceive('resolve')->andReturnUsing(function () use ($emit): array {
        $emit->materializedFullNodeAtIds = [];

        return ['enabled' => true, 'main_content_entry' => 'tpl', 'subject_entry' => null, 'webpage_entry' => null, 'website_entry' => null];
    });
    $output = thingResolveOutput();
    $output->shouldReceive('buildStandaloneSchemaObjectNodeSharingEmittedNodeIds')->once()->andReturn(['@type' => 'Person']);
    $alsoA = thingResolveEntry('a2', 'https://example.test/a');

    $json = thingResolveResolver($output, $cascade)->resolve(['about' => [thingResolveEntry('a'), $alsoA]], ['current_field' => 'about', 'emit_context' => $emit]);

    expect(thingResolveDecode($json))->toBe([['@type' => 'Person', '@id' => 'https://example.test/a']]);
});

it('records nothing in the graph maps for a node without a type or an @id', function (array $node): void {
    $output = thingResolveOutput();
    $output->shouldReceive('buildStandaloneSchemaObjectNodeSharingEmittedNodeIds')->andReturn($node);
    $emit = thingResolveEmitContext();
    $rows = [['type' => 'r', 'value' => ['title' => 'one']]];

    thingResolveResolver($output)->resolve(['rows' => $rows], ['current_field' => 'rows', 'current_field_replicator' => true, 'schema_object' => 'tpl', 'emit_context' => $emit]);

    expect($emit->materializedFullNodeAtIds)->toBe([])
        ->and($emit->emittedNodeIds)->toBe([]);
})->with([
    'no type' => [['@id' => 'https://example.test/x']],
    'no @id' => [['@type' => 'Thing']],
    'empty @id' => [['@type' => 'Thing', '@id' => '']],
    'non-string @id' => [['@type' => 'Thing', '@id' => 5]],
]);

it('uses the emit context on the runtime stack when none is passed', function (): void {
    $target = thingResolveEntry('a');
    $output = thingResolveOutput();
    $output->shouldReceive('buildStandaloneSchemaObjectNodeSharingEmittedNodeIds')->never();
    $emit = thingResolveEmitContext(['https://example.test/a' => true], ['https://example.test/a' => true]);

    SchemaTemplateEmitContextRuntimeStack::push($emit);
    try {
        $json = thingResolveResolver($output)->resolve(['about' => [$target]], ['current_field' => 'about', 'schema_object' => 'tpl', 'emit_context' => 'not a context']);
    } finally {
        SchemaTemplateEmitContextRuntimeStack::pop();
    }

    expect(thingResolveDecode($json))->toBe([['@id' => 'https://example.test/a']]);
});

it('serves a page-invariant graph build from the fragment cache and replays its emit effects', function (): void {
    $target = thingResolveEntry('founder');
    $output = thingResolveOutput();
    $output->shouldReceive('buildStandaloneSchemaObjectNodeSharingEmittedNodeIds')->once()->andReturn(['@type' => 'Person', 'name' => 'Built']);
    $output->shouldReceive('replayEmitSideEffectsFromCachedNodeFragment')->once()->with(['@type' => 'Person', 'name' => 'Built'], Mockery::any(), Mockery::any(), Mockery::any());
    $resolver = thingResolveResolver($output);

    thingResolvePageInvariant($resolver, $target, ['emit_context' => thingResolveEmitContext()]);
    $json = thingResolvePageInvariant($resolver, $target, ['emit_context' => thingResolveEmitContext()]);

    expect(thingResolveDecode($json))->toBe([['@type' => 'Person', 'name' => 'Built', '@id' => 'https://example.test/founder']]);
});

it('does not cache an untyped graph build', function (): void {
    $target = thingResolveEntry('founder');
    $output = thingResolveOutput();
    $output->shouldReceive('buildStandaloneSchemaObjectNodeSharingEmittedNodeIds')->twice()->andReturn(['name' => 'untyped']);
    $resolver = thingResolveResolver($output);

    thingResolvePageInvariant($resolver, $target, ['emit_context' => thingResolveEmitContext()]);
    thingResolvePageInvariant($resolver, $target, ['emit_context' => thingResolveEmitContext()]);
});

// ---
// Composition profile
// ---

it('records the composition profile when it is enabled', function (): void {
    Config::set('schema_templates.json_ld_compose_profile.enabled', true);
    $output = thingResolveOutput();
    // The one graph build sleeps 20ms, so the resolve timing has a floor to check. The floor is
    // 10ms, not 20ms: on Windows `usleep()` follows the system timer, and `microtime()` read
    // 4 of 3000 20ms sleeps as under 20ms (lowest 19.699ms) and none under 10ms (PHP 8.4.27,
    // Windows 11). A recorded time in seconds, not milliseconds, reads 0.02 and still fails (#704).
    $output->shouldReceive('buildStandaloneSchemaObjectNodeSharingEmittedNodeIds')->andReturnUsing(function (): array {
        usleep(20_000);

        return ['@type' => 'Person'];
    });
    $resolver = thingResolveResolver($output);
    $emit = thingResolveEmitContext(['https://example.test/b' => true], ['https://example.test/b' => true]);

    $resolver->resolve(['about' => [thingResolveEntry('a'), thingResolveEntry('b')]], ['current_field' => 'about', 'schema_object' => 'tpl', 'emit_context' => $emit]);
    $resolver->resolve(['about' => [thingResolveEntry('c')]], ['current_field' => 'about', 'schema_object' => 'tpl']);

    /** @var array<string, mixed> $bag */
    $bag = request()->attributes->get(JsonLdCompositionProfiler::REQUEST_JSON_LD_COMPOSITION_PROFILE);

    expect($bag['relationship_collect_direct_calls'])->toBe(2)
        ->and($bag['relationship_targets_direct'])->toBe(3)
        ->and($bag['relationship_resolve_calls'])->toBe(2)
        ->and($bag['relationship_resolve_ms'])->toBeFloat()->toBeGreaterThanOrEqual(10.0)->toBeLessThan(60_000.0)
        ->and($bag['relationship_full_build_calls'])->toBe(2)
        ->and($bag['relationship_compact_ref_hits'])->toBe(1)
        ->and($bag['relationship_full_build_top'])->toHaveCount(2);
});

it('records collector samples for each mode, and fragment cache hits', function (): void {
    Config::set('schema_templates.json_ld_compose_profile.enabled', true);
    GlobalSet::shouldReceive('findByHandle')->andReturnNull();
    $output = thingResolveOutput();
    $output->shouldReceive('buildStandaloneSchemaObjectNode')->andReturn(['@type' => 'Person']);
    $output->shouldReceive('buildStandaloneSchemaObjectNodeSharingEmittedNodeIds')->andReturn(['@type' => 'Person']);
    $output->shouldReceive('replayEmitSideEffectsFromCachedNodeFragment');
    $resolver = thingResolveResolver($output);
    $target = thingResolveEntry('founder');

    foreach (['secondary', 'tertiary', 'global'] as $mode) {
        $resolver->resolve([], ['mode' => $mode]);
    }
    thingResolvePageInvariant($resolver, $target);
    thingResolvePageInvariant($resolver, $target);
    thingResolvePageInvariant($resolver, $target, ['emit_context' => thingResolveEmitContext()]);

    /** @var array<string, mixed> $bag */
    $bag = request()->attributes->get(JsonLdCompositionProfiler::REQUEST_JSON_LD_COMPOSITION_PROFILE);

    expect($bag['relationship_collect_secondary_calls'])->toBe(1)
        ->and($bag['relationship_collect_tertiary_calls'])->toBe(1)
        ->and($bag['relationship_collect_global_calls'])->toBe(1)
        // Elapsed times, not timestamps: each collector is timed from its own start
        ->and($bag['relationship_collect_secondary_ms'])->toBeFloat()->toBeGreaterThanOrEqual(0.0)->toBeLessThan(60_000.0)
        ->and($bag['relationship_collect_tertiary_ms'])->toBeFloat()->toBeGreaterThanOrEqual(0.0)->toBeLessThan(60_000.0)
        ->and($bag['relationship_fragment_cache_hits'])->toBe(2)
        ->and($bag['relationship_full_build_calls'])->toBe(1)
        // A call that collects nothing returns before the resolve sample
        ->and($bag['relationship_resolve_calls'])->toBe(3);
});

it('records no profile when it is disabled', function (): void {
    thingResolveResolver(thingResolveOutput())->resolve(['about' => [thingResolveEntry('a')]], ['current_field' => 'about', 'schema_object' => 'tpl']);

    expect(request()->attributes->get(JsonLdCompositionProfiler::REQUEST_JSON_LD_COMPOSITION_PROFILE))->toBeNull();
});

it('skips a keyword target that is neither a term nor an entry, and reads the next one', function (): void {
    $subject = thingResolveEntry('heart');
    $subject->shouldReceive('data')->andReturn(collect(['title' => 'Heart', 'alternate_name' => [['text' => 'Cardiac']]]));

    $json = thingResolveResolver(thingResolveOutput())->resolveSubjectMatterKeywords(
        ['about' => [(new Variables)->globalSet('settings'), $subject]],
        ['current_field' => 'about']
    );

    expect($json)->toBe('["Heart","Cardiac"]');
});

it('lazily resolves the fragment cache from the container when none is injected', function (): void {
    $cascade = Mockery::mock(SchemaTemplatesCascadeService::class);
    $cascade->shouldIgnoreMissing();
    $resolver = new SchemaThingFromRelationshipResolver(thingResolveOutput(), $cascade, new SchemaJsonLdAtIdDeduplication);
    $fragmentCache = (new ReflectionMethod(SchemaThingFromRelationshipResolver::class, 'fragmentCache'));

    expect($fragmentCache->invoke($resolver))->toBeInstanceOf(SchemaJsonLdFragmentCache::class)
        ->and($fragmentCache->invoke($resolver))->toBe($fragmentCache->invoke($resolver));
});
