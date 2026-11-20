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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Entries\Entry as EntryModel;
use Statamic\Facades\Entry;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(SchemaOutputService::class);

/**
 * Pins the page entry point of {@see SchemaOutputService}: when
 * `outputForCurrentContext()` emits nothing, how tag parameters override the
 * cascade's slots, and how the persisted output cache is used or bypassed. Also
 * pins `resolveIdentifiersTabAntlersRow()`, which reduces an identifiers-tab
 * Antlers row to the string a merger receives. Collaborators are stubbed on a
 * partial mock so each branch is asserted on its own.
 *
 * @command  php artisan test --compact tests/Feature/Services/SchemaTemplates/SchemaOutputServicePageOutputTest.php
 */

/**
 * The service as a partial mock over the given cascade and profiler, with the given methods stubbed.
 *
 * @param  array<string, Closure>  $stubs  Replacement bodies by method name.
 */
function sosPageService(?SchemaTemplatesCascadeService $cascade = null, array $stubs = [], ?JsonLdCompositionProfiler $profiler = null): SchemaOutputService&MockInterface
{
    $arguments = [
        $cascade ?? app(SchemaTemplatesCascadeService::class),
        app(SchemaClassificationsIdentifiersMerger::class),
        app(SchemaJsonLdAtIdDeduplication::class),
        app(SchemaJsonLdSchemaObjectEmissionCoordinator::class),
        $profiler ?? app(JsonLdCompositionProfiler::class),
        app(MedicalOrganizationSubtypeAtTypeResolver::class),
        app(PodcastEpisodeAtTypeResolver::class),
    ];

    /** @var SchemaOutputService&MockInterface $service */
    $service = Mockery::mock(SchemaOutputService::class, $arguments)
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();

    foreach ($stubs as $method => $body) {
        $service->shouldReceive($method)->andReturnUsing($body);
    }

    return $service;
}

/**
 * A cascade that resolves every entity to the given slots.
 *
 * @param  array<string, mixed>  $slots  Slot values over the defaults.
 */
function sosPageCascade(array $slots = []): SchemaTemplatesCascadeService&MockInterface
{
    /** @var SchemaTemplatesCascadeService&MockInterface $cascade */
    $cascade = Mockery::mock(SchemaTemplatesCascadeService::class);
    $cascade->shouldReceive('resolve')->andReturn($slots + [
        'enabled' => true,
        'subject_entry' => null,
        'website_entry' => null,
        'webpage_entry' => null,
        'main_content_entry' => null,
    ]);

    return $cascade;
}

/**
 * A real entry, with an id when one is given.
 */
function sosPageEntry(?string $id = 'ctx-1'): EntryModel
{
    $entry = mustBe(EntryModel::class, Entry::make(), 'a new entry');
    if ($id !== null) {
        $entry->id($id);
    }

    return $entry;
}

/**
 * A compose stub that records each call's slot ids and returns a numbered tag.
 *
 * @param  list<list<string|null>>  $calls  Collected slot ids, by reference.
 */
function sosPageCompose(array &$calls): Closure
{
    return function (EntryContract $entity, ?string $subject, ?string $website, ?string $webpage, ?string $main) use (&$calls): string {
        $calls[] = [$subject, $website, $webpage, $main];

        return '<script>'.\count($calls).'</script>';
    };
}

beforeEach(function (): void {
    Cache::flush();
    Config::set('schema_templates.json_ld_cache.enabled', true);
    Config::set('schema_templates.json_ld_cache.output_enabled', true);
    Config::set('schema_templates.json_ld_cache.ttl_seconds', 3600);
});

// ---
// outputForCurrentContext(): when nothing is emitted
// ---

it('emits nothing on a Control Panel route, before resolving any entity', function (): void {
    Config::set('statamic.cp.enabled', true);
    Config::set('statamic.cp.route', 'cp');
    app()->instance('request', Request::create('/cp/collections'));
    $service = sosPageService(sosPageCascade(), ['resolveEntity' => fn (): never => throw new LogicException('not reached')]);

    expect($service->outputForCurrentContext())->toBe('');
});

it('emits nothing without a context entity', function (): void {
    $cascade = Mockery::mock(SchemaTemplatesCascadeService::class);
    $cascade->shouldReceive('resolve')->never();
    $service = sosPageService($cascade, ['resolveEntity' => fn (): ?EntryContract => null]);

    expect($service->outputForCurrentContext())->toBe('');
});

it('emits nothing when the cascade is disabled for the entity', function (): void {
    $calls = [];
    $service = sosPageService(sosPageCascade(['enabled' => false, 'subject_entry' => 's1']), [
        'resolveEntity' => fn (): EntryContract => sosPageEntry(),
        'composeJsonLdOutputScriptTagForResolvedSlots' => sosPageCompose($calls),
    ]);

    expect($service->outputForCurrentContext(['subject' => 's2']))->toBe('')
        ->and($calls)->toBe([]);
});

it('emits nothing when no slot names an entry', function (array $tagParams): void {
    /** @var array{subject?: string, website?: string, webpage?: string, main_content?: string} $tagParams */
    $calls = [];
    $service = sosPageService(sosPageCascade(['subject_entry' => '', 'website_entry' => []]), [
        'resolveEntity' => fn (): EntryContract => sosPageEntry(),
        'composeJsonLdOutputScriptTagForResolvedSlots' => sosPageCompose($calls),
    ]);

    expect($service->outputForCurrentContext($tagParams))->toBe('')
        ->and($calls)->toBe([]);
})->with([
    'no tag parameters' => [[]],
    'empty tag parameters' => [['subject' => '', 'website' => '', 'webpage' => '', 'main_content' => '']],
]);

// ---
// outputForCurrentContext(): slots and the output cache
// ---

it('takes each slot from its tag parameter, else from the cascade', function (): void {
    Config::set('schema_templates.json_ld_cache.enabled', false);
    $calls = [];
    $service = sosPageService(sosPageCascade(['subject_entry' => 'c-subject', 'website_entry' => ['c-website'], 'webpage_entry' => 'c-webpage', 'main_content_entry' => 'c-main']), [
        'resolveEntity' => fn (): EntryContract => sosPageEntry(),
        'composeJsonLdOutputScriptTagForResolvedSlots' => sosPageCompose($calls),
    ]);

    expect($service->outputForCurrentContext())->toBe('<script>1</script>')
        ->and($service->outputForCurrentContext(['subject' => 't-subject', 'main_content' => 't-main']))->toBe('<script>2</script>')
        ->and($service->outputForCurrentContext(['website' => 't-website', 'webpage' => 't-webpage']))->toBe('<script>3</script>')
        ->and($calls)->toBe([
            ['c-subject', 'c-website', 'c-webpage', 'c-main'],
            ['t-subject', 'c-website', 'c-webpage', 't-main'],
            ['c-subject', 't-website', 't-webpage', 'c-main'],
        ]);
});

it('composes on every call when the output cache is off', function (string $key, mixed $value): void {
    Config::set($key, $value);
    $calls = [];
    $service = sosPageService(sosPageCascade(['subject_entry' => 's1']), [
        'resolveEntity' => fn (): EntryContract => sosPageEntry(),
        'composeJsonLdOutputScriptTagForResolvedSlots' => sosPageCompose($calls),
    ]);

    expect($service->outputForCurrentContext())->toBe('<script>1</script>')
        ->and($service->outputForCurrentContext())->toBe('<script>2</script>');
})->with([
    'master switch off' => ['schema_templates.json_ld_cache.enabled', false],
    'output switch off' => ['schema_templates.json_ld_cache.output_enabled', false],
    'zero TTL' => ['schema_templates.json_ld_cache.ttl_seconds', 0],
    'negative TTL' => ['schema_templates.json_ld_cache.ttl_seconds', -5],
    'non-numeric TTL' => ['schema_templates.json_ld_cache.ttl_seconds', 'soon'],
]);

it('caches the output for an hour when the cache is not configured', function (): void {
    Config::set('schema_templates.json_ld_cache', []);
    $this->freezeTime();
    $calls = [];
    $service = sosPageService(sosPageCascade(['subject_entry' => 's1']), [
        'resolveEntity' => fn (): EntryContract => sosPageEntry('ctx-1'),
        'composeJsonLdOutputScriptTagForResolvedSlots' => sosPageCompose($calls),
    ]);

    $first = $service->outputForCurrentContext();
    $this->travel(3599)->seconds();
    $second = $service->outputForCurrentContext();
    $this->travel(1)->seconds();
    $third = $service->outputForCurrentContext();

    expect([$first, $second, $third])->toBe(['<script>1</script>', '<script>1</script>', '<script>2</script>']);
});

it('caches the output for a one-second TTL', function (): void {
    Config::set('schema_templates.json_ld_cache.ttl_seconds', 1);
    $this->freezeTime();
    $calls = [];
    $service = sosPageService(sosPageCascade(['subject_entry' => 's1']), [
        'resolveEntity' => fn (): EntryContract => sosPageEntry('ctx-1'),
        'composeJsonLdOutputScriptTagForResolvedSlots' => sosPageCompose($calls),
    ]);

    expect($service->outputForCurrentContext())->toBe('<script>1</script>')
        ->and($service->outputForCurrentContext())->toBe('<script>1</script>');
});

it('composes uncached when the context entity has no id to key the cache on', function (): void {
    $calls = [];
    $service = sosPageService(sosPageCascade(['subject_entry' => 's1']), [
        'resolveEntity' => fn (): EntryContract => sosPageEntry(null),
        'composeJsonLdOutputScriptTagForResolvedSlots' => sosPageCompose($calls),
    ]);

    expect($service->outputForCurrentContext())->toBe('<script>1</script>')
        ->and($service->outputForCurrentContext())->toBe('<script>2</script>');
});

it('serves a repeat call for the same entity and slots from the output cache, for the configured TTL', function (): void {
    // Freeze the clock so the 119- and 121-second points are measured from the cache
    // write, not from whenever travel() runs on a loaded runner (#1172).
    $this->freezeTime();
    Config::set('schema_templates.json_ld_cache.ttl_seconds', '120');
    $calls = [];
    $service = sosPageService(sosPageCascade(['subject_entry' => 's1']), [
        'resolveEntity' => fn (): EntryContract => sosPageEntry('ctx-1'),
        'composeJsonLdOutputScriptTagForResolvedSlots' => sosPageCompose($calls),
    ]);

    $first = $service->outputForCurrentContext();
    $this->travel(119)->seconds();
    $second = $service->outputForCurrentContext();
    $this->travel(2)->seconds();
    $third = $service->outputForCurrentContext();

    expect([$first, $second, $third])->toBe(['<script>1</script>', '<script>1</script>', '<script>2</script>'])
        ->and($calls)->toBe([['s1', null, null, null], ['s1', null, null, null]]);
});

it('keys the output cache on the slots, so different tag parameters compose separately', function (): void {
    $calls = [];
    $service = sosPageService(sosPageCascade(['subject_entry' => 's1']), [
        'resolveEntity' => fn (): EntryContract => sosPageEntry('ctx-1'),
        'composeJsonLdOutputScriptTagForResolvedSlots' => sosPageCompose($calls),
    ]);

    expect($service->outputForCurrentContext())->toBe('<script>1</script>')
        ->and($service->outputForCurrentContext(['subject' => 's2']))->toBe('<script>2</script>')
        ->and($service->outputForCurrentContext())->toBe('<script>1</script>');
});

// ---
// resolveIdentifiersTabAntlersRow()
// ---

it('reads no identifier from a row that is not an Antlers row', function (mixed $type): void {
    $service = sosPageService(null, ['resolveSingleReplicatorRowToValue' => fn (): never => throw new LogicException('not reached')]);

    expect($service->resolveIdentifiersTabAntlersRow(['type' => $type, 'value' => 'x'], null))->toBeNull();
})->with([
    'another type' => ['text'],
    'no type' => [null],
]);

it('reduces a resolved Antlers row to the string a merger receives', function (mixed $resolved, ?string $expected): void {
    $service = sosPageService(null, ['resolveSingleReplicatorRowToValue' => fn (): mixed => $resolved]);

    expect($service->resolveIdentifiersTabAntlersRow(['type' => 'antlers'], null))->toBe($expected);
})->with([
    'nothing' => [null, null],
    'padded string' => ['  ABC-1  ', 'ABC-1'],
    'blank string' => ['   ', null],
    'integer' => [42, '42'],
    'float' => [1.5, '1.5'],
    'true' => [true, '1'],
    'false' => [false, ''],
    'array as JSON, slashes and Unicode unescaped' => [['url' => 'https://example.test/a', 'name' => 'Café'], '{"url":"https://example.test/a","name":"Café"}'],
    'value JSON cannot encode' => [[NAN], null],
]);

it('resolves the row against the context entity with empty graph state', function (): void {
    $context = sosPageEntry('ctx-1');
    $calls = [];
    $service = sosPageService(null, ['resolveSingleReplicatorRowToValue' => function (array $row, mixed $entity, array $graph, array $visited, array $emittedEntries, array $emittedNodes, array $materialized, array $antlers) use (&$calls): string {
        $calls[] = [$row, $entity, $graph, $visited, $emittedEntries, $emittedNodes, $materialized, $antlers];

        return 'X';
    }]);

    $row = ['type' => 'antlers', 'value' => ['code' => '{{ title }}']];

    expect($service->resolveIdentifiersTabAntlersRow($row, $context))->toBe('X')
        ->and($calls)->toBe([[$row, $context, [], [], [], [], [], []]]);
});

// ---
// composeJsonLdOutputScriptTagForResolvedSlots()
// ---

/**
 * A composition profiler that is on or off and records every bucket sample.
 *
 * @param  list<array{0: string, 1: string, 2: float}>  $samples  Collected samples, by reference.
 * @param  list<string>  $lifecycle  Collected reset and finalize calls, by reference.
 */
function sosPageProfiler(bool $enabled, array &$samples, array &$lifecycle): JsonLdCompositionProfiler&MockInterface
{
    /** @var JsonLdCompositionProfiler&MockInterface $profiler */
    $profiler = Mockery::mock(JsonLdCompositionProfiler::class);
    $profiler->shouldReceive('enabled')->andReturn($enabled);
    $profiler->shouldReceive('reset')->andReturnUsing(function () use (&$lifecycle): void {
        $lifecycle[] = 'reset';
    });
    $profiler->shouldReceive('finalize')->andReturnUsing(function (float $ms) use (&$lifecycle): void {
        $lifecycle[] = $ms >= 0.0 && $ms < 60_000.0 ? 'finalize' : 'finalize with an unusable time';
    });
    $profiler->shouldReceive('recordBucketSample')->andReturnUsing(function (string $calls, string $ms, float $elapsed) use (&$samples): void {
        $samples[] = [$calls, $ms, $elapsed];
    });

    return $profiler;
}

/**
 * Call the compose method on a service.
 */
function sosPageCall(SchemaOutputService $service, mixed ...$args): string
{
    return mustBeString(
        (new ReflectionMethod(SchemaOutputService::class, 'composeJsonLdOutputScriptTagForResolvedSlots'))->invoke($service, ...$args),
        'the composed script tag'
    );
}

it('composes nothing when no slot builds a node, and still closes the profile', function (): void {
    $samples = [];
    $lifecycle = [];
    $built = [];
    $service = sosPageService(null, [
        'schemaObjectEntryToNode' => function (string $id) use (&$built): ?array {
            $built[] = $id;

            return $id === 'w1' ? [] : null;
        },
        'inflateBareAtIdReferencesMissingFullNodesInGraph' => fn (): never => throw new LogicException('not reached'),
    ], sosPageProfiler(false, $samples, $lifecycle));

    expect(sosPageCall($service, sosPageEntry(), 's1', 'w1', null, null))->toBe('')
        ->and($built)->toBe(['s1', 'w1'])
        ->and($lifecycle)->toBe(['reset', 'finalize'])
        ->and($samples)->toBe([]);
});

it('builds each named slot in order and emits the graph as a JSON-LD script tag', function (): void {
    $samples = [];
    $lifecycle = [];
    $calls = [];
    $inflated = 0;
    $service = sosPageService(null, [
        'schemaObjectEntryToNode' => function (string $id, mixed $entity, array $graphIds, array $visited, array $emittedEntries, array $emittedNodes, array $materialized, array $antlers, mixed $bag, ?string $main) use (&$calls): array {
            $calls[] = [$id, $graphIds, $visited, $emittedEntries, $antlers, $main];

            return ['@type' => 'Thing', '@id' => 'https://example.test/#'.$id, 'name' => '</script> & Café'];
        },
        'inflateBareAtIdReferencesMissingFullNodesInGraph' => function () use (&$inflated): void {
            $inflated++;
        },
    ], sosPageProfiler(false, $samples, $lifecycle));

    $script = sosPageCall($service, sosPageEntry(), 's1', null, '', 'm1');

    // An empty slot id is built like any other but not counted in the graph ids
    expect($calls)->toBe([
        ['s1', ['s1', 'm1'], [], ['s1', 'm1'], [], 'm1'],
        ['', ['s1', 'm1'], [], ['s1', 'm1'], [], 'm1'],
        ['m1', ['s1', 'm1'], [], ['s1', 'm1'], [], 'm1'],
    ])
        ->and($inflated)->toBe(1)
        ->and($lifecycle)->toBe(['reset', 'finalize'])
        ->and($samples)->toBe([])
        ->and($script)->toBe("<script type=\"application/ld+json\">\n".'{"@context":"https://schema.org","@graph":['
            .'{"@type":"Thing","@id":"https://example.test/#s1","name":"\u003C/script\u003E & Café"},'
            .'{"@type":"Thing","@id":"https://example.test/#","name":"\u003C/script\u003E & Café"},'
            .'{"@type":"Thing","@id":"https://example.test/#m1","name":"\u003C/script\u003E & Café"}'
            ."]}\n</script>");
});

it('records a timing sample for each node build, both dedupes, the inflate and the encode when profiling is on', function (): void {
    $samples = [];
    $lifecycle = [];
    $service = sosPageService(null, [
        'schemaObjectEntryToNode' => fn (string $id): array => ['@type' => 'Thing', '@id' => '#'.$id],
        'inflateBareAtIdReferencesMissingFullNodesInGraph' => function (): void {},
    ], sosPageProfiler(true, $samples, $lifecycle));

    sosPageCall($service, sosPageEntry(), 's1', 'w1', null, null);

    expect(array_map(fn (array $sample): array => [$sample[0], $sample[1]], $samples))->toBe([
        ['schema_object_node_build_calls', 'schema_object_node_build_ms'],
        ['schema_object_node_build_calls', 'schema_object_node_build_ms'],
        ['dedupe_calls', 'dedupe_ms'],
        ['inflate_calls', 'inflate_ms'],
        ['dedupe_calls', 'dedupe_ms'],
        ['json_encode_calls', 'json_encode_ms'],
    ])
        ->and(array_filter($samples, fn (array $sample): bool => $sample[2] < 0.0 || $sample[2] >= 60_000.0))->toBe([])
        ->and($lifecycle)->toBe(['reset', 'finalize']);
});

it('records the node build sample even when the slot builds nothing', function (): void {
    $samples = [];
    $lifecycle = [];
    $service = sosPageService(null, [
        'schemaObjectEntryToNode' => fn (): ?array => null,
    ], sosPageProfiler(true, $samples, $lifecycle));

    expect(sosPageCall($service, sosPageEntry(), 's1', null, null, null))->toBe('')
        ->and(array_column($samples, 0))->toBe(['schema_object_node_build_calls'])
        ->and($lifecycle)->toBe(['reset', 'finalize']);
});

it('counts every named slot among the graph ids', function (): void {
    $samples = [];
    $lifecycle = [];
    $graphIdsSeen = [];
    $service = sosPageService(null, [
        'schemaObjectEntryToNode' => function (string $id, mixed $entity, array $graphIds) use (&$graphIdsSeen): ?array {
            $graphIdsSeen[] = $graphIds;

            return null;
        },
    ], sosPageProfiler(false, $samples, $lifecycle));

    sosPageCall($service, sosPageEntry(), 's1', 'w1', 'p1', 'm1');

    expect($graphIdsSeen)->toBe(array_fill(0, 4, ['s1', 'w1', 'p1', 'm1']));
});

it('records each built node as emitted and drops speculative full-node ids before the next slot', function (): void {
    $samples = [];
    $lifecycle = [];
    $seen = [];
    $service = sosPageService(null, [
        'schemaObjectEntryToNode' => function (string $id, mixed $entity, array $graphIds, array $visited, array &$emittedEntries, array &$emittedNodes, array &$materialized) use (&$seen): array {
            $seen[$id] = ['emitted' => array_keys($emittedNodes), 'materialized' => array_keys($materialized)];
            $materialized['https://example.test/#ghost'] = true;

            return ['@type' => 'Thing', '@id' => 'https://example.test/#'.$id];
        },
        'inflateBareAtIdReferencesMissingFullNodesInGraph' => function (): void {},
    ], sosPageProfiler(false, $samples, $lifecycle));

    sosPageCall($service, sosPageEntry(), 's1', 'w1', null, null);

    expect($seen['s1'])->toBe(['emitted' => [], 'materialized' => []])
        ->and($seen['w1']['emitted'])->toContain('https://example.test/#s1')
        ->and($seen['w1']['materialized'])->toBe(['https://example.test/#s1']);
});

it('drops a speculative full-node id a slot leaves without a node before inflating', function (): void {
    $samples = [];
    $lifecycle = [];
    $inflatedWith = null;
    $service = sosPageService(null, [
        'schemaObjectEntryToNode' => function (string $id, mixed $entity, array $graphIds, array $visited, array &$emittedEntries, array &$emittedNodes, array &$materialized): ?array {
            if ($id === 'w1') {
                $materialized['https://example.test/#ghost'] = true;

                return null;
            }

            return ['@type' => 'Thing', '@id' => 'https://example.test/#'.$id];
        },
        'inflateBareAtIdReferencesMissingFullNodesInGraph' => function (array &$graph, mixed $entity, array $graphIds, array &$emittedEntries, array &$emittedNodes, array &$materialized) use (&$inflatedWith): void {
            $inflatedWith = array_keys($materialized);
        },
    ], sosPageProfiler(false, $samples, $lifecycle));

    sosPageCall($service, sosPageEntry(), 's1', 'w1', null, null);

    expect($inflatedWith)->toBe(['https://example.test/#s1']);
});

it('records a node build sample in milliseconds', function (): void {
    $samples = [];
    $lifecycle = [];
    $service = sosPageService(null, [
        'schemaObjectEntryToNode' => function (): ?array {
            usleep(20_000);

            return null;
        },
    ], sosPageProfiler(true, $samples, $lifecycle));

    sosPageCall($service, sosPageEntry(), 's1', null, null, null);

    expect($samples)->toHaveCount(1)
        ->and($samples[0][2])->toBeGreaterThanOrEqual(10.0)->toBeLessThan(60_000.0);
});

// ---
// getSlotEntryForCurrentContext()
// ---

it('finds the schema entry of each slot for the current context', function (string $slot, string $id): void {
    $found = sosPageEntry($id);
    $service = sosPageService(sosPageCascade(['subject_entry' => 'c-subject', 'website_entry' => 'c-website', 'webpage_entry' => ['c-webpage'], 'main_content_entry' => 'c-main']), [
        'resolveEntity' => fn (): EntryContract => sosPageEntry(),
    ]);
    Entry::partialMock()->shouldReceive('find')->once()->with($id)->andReturn($found);

    expect($service->getSlotEntryForCurrentContext($slot))->toBe($found);
})->with([
    'subject' => ['subject', 'c-subject'],
    'website' => ['website', 'c-website'],
    'webpage' => ['webpage', 'c-webpage'],
    'main content' => ['main_content', 'c-main'],
]);

it('finds no slot entry on a Control Panel route or without a context entity', function (bool $cpRoute): void {
    if ($cpRoute) {
        Config::set('statamic.cp.enabled', true);
        Config::set('statamic.cp.route', 'cp');
        app()->instance('request', Request::create('/cp/collections'));
    }
    $service = sosPageService(sosPageCascade(['subject_entry' => 'c-subject']), [
        'resolveEntity' => fn (): ?EntryContract => $cpRoute ? throw new LogicException('not reached') : null,
    ]);
    Entry::partialMock()->shouldReceive('find')->never();

    expect($service->getSlotEntryForCurrentContext('subject'))->toBeNull();
})->with([
    'a Control Panel route' => [true],
    'no context entity' => [false],
]);

it('finds no slot entry for an unknown slot or a slot that names no entry', function (string $slot, mixed $stored): void {
    $service = sosPageService(sosPageCascade(['subject_entry' => $stored]), [
        'resolveEntity' => fn (): EntryContract => sosPageEntry(),
    ]);
    Entry::partialMock()->shouldReceive('find')->never();

    expect($service->getSlotEntryForCurrentContext($slot))->toBeNull();
})->with([
    'an unknown slot' => ['sidebar', 'c-subject'],
    'no entry' => ['subject', null],
    'an id that reads as empty' => ['subject', new class implements Stringable
    {
        public function __toString(): string
        {
            return '';
        }
    }],
]);

it('finds no slot entry when its id finds no entry', function (mixed $found): void {
    $service = sosPageService(sosPageCascade(['subject_entry' => 'c-subject']), [
        'resolveEntity' => fn (): EntryContract => sosPageEntry(),
    ]);
    Entry::partialMock()->shouldReceive('find')->once()->with('c-subject')->andReturn($found);

    expect($service->getSlotEntryForCurrentContext('subject'))->toBeNull();
})->with([
    'nothing' => [null],
]);
