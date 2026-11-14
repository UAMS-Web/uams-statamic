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
use Statamic\Facades\Entry;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(SchemaOutputService::class);

/**
 * Pins how {@see SchemaOutputService} builds JSON-LD `@id` values for schema
 * entries: the node id for an entry, the canonical id of a `schema_objects`
 * entry, the site root fallback, and the two callers that preview or re-enter a
 * template. Each method is called directly, through reflection, with its
 * collaborators stubbed on a partial mock so every branch is asserted on its own.
 *
 * @command  php artisan test --compact tests/Feature/Services/SchemaTemplates/SchemaOutputServiceNodeIdTest.php
 */

/**
 * The service as a partial mock, with the given methods stubbed.
 *
 * @param  array<string, Closure>  $stubs  Replacement bodies by method name.
 */
function sosNodeIdService(array $stubs = []): SchemaOutputService&MockInterface
{
    $arguments = array_map(fn (string $class): object => app($class), [
        SchemaTemplatesCascadeService::class,
        SchemaClassificationsIdentifiersMerger::class,
        SchemaJsonLdAtIdDeduplication::class,
        SchemaJsonLdSchemaObjectEmissionCoordinator::class,
        JsonLdCompositionProfiler::class,
        MedicalOrganizationSubtypeAtTypeResolver::class,
        PodcastEpisodeAtTypeResolver::class,
    ]);

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
 * Call a service method, protected or public, through reflection.
 *
 * @param  mixed  ...$args  Arguments.
 */
function sosNodeIdCall(SchemaOutputService $service, string $method, mixed ...$args): mixed
{
    return (new ReflectionMethod(SchemaOutputService::class, $method))->invoke($service, ...$args);
}

/**
 * A mocked entry in a collection, with a stored `schema` value and a site.
 */
function sosNodeIdEntry(string $id, string $collection, mixed $schema = null, ?object $site = null): EntryModel&MockInterface
{
    /** @var EntryModel&MockInterface $entry */
    $entry = Mockery::mock(EntryModel::class);
    $entry->shouldReceive('id')->andReturn($id);
    $entry->shouldReceive('collectionHandle')->andReturn($collection);
    $entry->shouldReceive('get')->with('schema')->andReturn($schema);
    $entry->shouldReceive('site')->andReturn($site);

    return $entry;
}

/**
 * A mocked context entry with the given URL.
 */
function sosNodeIdContext(mixed $url): EntryModel&MockInterface
{
    /** @var EntryModel&MockInterface $context */
    $context = Mockery::mock(EntryModel::class);
    $context->shouldReceive('url')->andReturn($url);

    return $context;
}

// ---
// entryIdToNodeId()
// ---

it('returns a bare fragment for an entry id that names no entry', function (): void {
    Entry::shouldReceive('find')->once()->with('e1')->andReturnNull();

    expect(sosNodeIdCall(sosNodeIdService(), 'entryIdToNodeId', 'e1', null, [], []))->toBe('#e1');
});

it('loads the entry by id when none is passed', function (): void {
    Entry::shouldReceive('find')->once()->with('e1')->andReturn(sosNodeIdEntry('e1', 'pages', ['properties' => []]));

    expect(sosNodeIdCall(sosNodeIdService(), 'entryIdToNodeId', 'e1', null, [], []))->toBe('#e1');
});

it('uses the canonical @id of a schema_objects entry, suffixed by a non-negative instance index', function (?int $index, string $expected): void {
    Entry::shouldReceive('find')->never();
    $service = sosNodeIdService(['getCanonicalAtIdForSchemaEntry' => fn (): string => 'https://example.test/#e1']);

    expect(sosNodeIdCall($service, 'entryIdToNodeId', 'e1', null, [], [], sosNodeIdEntry('e1', 'schema_objects'), null, $index))->toBe($expected);
})->with([
    'no index' => [null, 'https://example.test/#e1'],
    'index zero' => [0, 'https://example.test/#e1-0'],
    'index two' => [2, 'https://example.test/#e1-2'],
    'negative index' => [-1, 'https://example.test/#e1'],
]);

it('returns a bare fragment for an entry whose stored schema has no properties map', function (mixed $schema): void {
    $service = sosNodeIdService(['resolvePropertyValue' => fn (): never => throw new LogicException('not reached')]);

    expect(sosNodeIdCall($service, 'entryIdToNodeId', 'e1', sosNodeIdContext('/ctx'), [], [], sosNodeIdEntry('e1', 'pages', $schema)))->toBe('#e1');
})->with([
    'no schema' => [null],
    'scalar schema' => ['schema'],
    'no properties' => [['type' => 'Thing']],
    'scalar properties' => [['properties' => 'x']],
]);

it('bases the node id on the resolved url property, without its fragment', function (): void {
    $calls = [];
    $service = sosNodeIdService(['resolvePropertyValue' => function (mixed $value, mixed $context, array $graph, array $visited) use (&$calls): string {
        $calls[] = [$value, $graph, $visited];

        return 'https://example.test/page#section';
    }]);

    $nodeId = sosNodeIdCall($service, 'entryIdToNodeId', 'e1', sosNodeIdContext('/ctx'), ['g1'], ['v1'], sosNodeIdEntry('stored-id', 'pages', ['properties' => ['url' => 'URL-ROWS']]));

    // The fragment carries the entry's own id, and the url resolves with the entry marked visited
    expect($nodeId)->toBe('https://example.test/page#stored-id')
        ->and($calls)->toBe([['URL-ROWS', ['g1'], ['v1', 'e1']]]);
});

it('takes the properties passed in over the stored schema', function (): void {
    $service = sosNodeIdService(['resolvePropertyValue' => fn (mixed $value): mixed => $value]);

    expect(sosNodeIdCall($service, 'entryIdToNodeId', 'e1', null, [], [], sosNodeIdEntry('e1', 'pages', null), ['url' => 'https://example.test/given']))
        ->toBe('https://example.test/given#e1');
});

it('falls back to the context entity URL when the url property resolves to nothing', function (mixed $resolved, mixed $contextUrl, string $expected): void {
    $service = sosNodeIdService(['resolvePropertyValue' => fn (): mixed => $resolved]);

    expect(sosNodeIdCall($service, 'entryIdToNodeId', 'e1', sosNodeIdContext($contextUrl), [], [], sosNodeIdEntry('e1', 'pages', ['properties' => ['url' => 'rows']])))
        ->toBe($expected);
})->with([
    'empty url, context URL' => ['', '/about#team', '/about#e1'],
    'non-string url, context URL' => [['x'], '/about', '/about#e1'],
    'empty url, empty context URL' => ['', '', '#e1'],
    'empty url, non-string context URL' => ['', null, '#e1'],
]);

it('uses the context entity URL when there is no url property, and a bare fragment without a context', function (): void {
    $service = sosNodeIdService(['resolvePropertyValue' => fn (): never => throw new LogicException('not reached')]);
    $entry = sosNodeIdEntry('e1', 'pages', ['properties' => ['name' => 'N']]);

    expect(sosNodeIdCall($service, 'entryIdToNodeId', 'e1', sosNodeIdContext('https://example.test/ctx'), [], [], $entry))->toBe('https://example.test/ctx#e1')
        ->and(sosNodeIdCall($service, 'entryIdToNodeId', 'e1', null, [], [], $entry))->toBe('#e1');
});

it('builds a fragment from an empty id when the entry id is not a scalar', function (): void {
    /** @var EntryModel&MockInterface $entry */
    $entry = Mockery::mock(EntryModel::class);
    $entry->shouldReceive('id')->andReturn(['not', 'scalar']);
    $entry->shouldReceive('collectionHandle')->andReturn('pages');

    expect(sosNodeIdCall(sosNodeIdService(), 'entryIdToNodeId', 'e1', sosNodeIdContext('/ctx'), [], [], $entry, []))->toBe('/ctx#');
});

// ---
// getCanonicalAtIdForSchemaEntry() and siteRootForEntry()
// ---

it('uses the site root when a schema entry has no effective schema', function (): void {
    $service = sosNodeIdService([
        'getEffectiveSchema' => fn (): ?array => null,
        'siteRootForEntry' => fn (): string => 'https://example.test/',
    ]);

    expect($service->getCanonicalAtIdForSchemaEntry(sosNodeIdEntry('e1', 'schema_objects')))->toBe('https://example.test/#e1');
});

it('bases a schema entry @id on its resolved url property, without its fragment, or on the site root', function (mixed $resolved, string $expected): void {
    $calls = [];
    $service = sosNodeIdService([
        'getEffectiveSchema' => fn (): array => ['properties' => ['url' => 'URL-ROWS']],
        'resolvePropertyValue' => function (mixed $value, mixed $context, array $graph, array $visited) use (&$calls, $resolved): mixed {
            $calls[] = [$value, $graph, $visited];

            return $resolved;
        },
        'siteRootForEntry' => fn (): string => 'https://root.test/',
    ]);

    expect($service->getCanonicalAtIdForSchemaEntry(sosNodeIdEntry('e1', 'schema_objects')))->toBe($expected)
        ->and($calls)->toBe([['URL-ROWS', [], ['e1']]]);
})->with([
    'url with a fragment' => ['https://example.test/page#x', 'https://example.test/page#e1'],
    'url without a fragment' => ['https://example.test/page', 'https://example.test/page#e1'],
    'url that is only a fragment' => ['#x', 'https://root.test/#e1'],
    'empty url' => ['', 'https://root.test/#e1'],
    'non-string url' => [5, 'https://root.test/#e1'],
]);

it('uses the site root when a schema entry has no url property', function (): void {
    $service = sosNodeIdService([
        'getEffectiveSchema' => fn (): array => ['properties' => ['name' => 'N']],
        'resolvePropertyValue' => fn (): never => throw new LogicException('not reached'),
        'siteRootForEntry' => fn (): string => 'https://root.test/',
    ]);

    expect($service->getCanonicalAtIdForSchemaEntry(sosNodeIdEntry('e1', 'schema_objects')))->toBe('https://root.test/#e1');
});

it('reads the site root from the site absolute URL, then its URL, else the root path', function (?Closure $site, string $expected): void {
    /** @var object|null $siteObject */
    $siteObject = $site instanceof Closure ? $site() : null;
    $entry = sosNodeIdEntry('e1', 'pages', null, $siteObject);

    expect(sosNodeIdCall(sosNodeIdService(), 'siteRootForEntry', $entry))->toBe($expected);
})->with([
    'no site' => [null, '/'],
    'absolute URL' => [fn (): object => new class
    {
        public function absoluteUrl(): string
        {
            return 'https://example.test/';
        }
    }, 'https://example.test/'],
    'URL only' => [fn (): object => new class
    {
        public function url(): string
        {
            return '/sub/';
        }
    }, '/sub/'],
    'empty absolute URL' => [fn (): object => new class
    {
        public function absoluteUrl(): string
        {
            return '';
        }
    }, '/'],
    'neither' => [fn (): object => new stdClass, '/'],
]);

// ---
// canonicalAtIdForReentrantSchemaTemplateNode()
// ---

it('re-enters a template node with a bare fragment for an unknown id, the site root for a schema entry, and the shared rules otherwise', function (): void {
    $template = sosNodeIdEntry('t1', 'schema_objects');
    $page = sosNodeIdEntry('p1', 'pages', ['properties' => ['url' => 'rows']]);
    Entry::shouldReceive('find')->andReturnUsing(fn (string $id): ?EntryModel => ['t1' => $template, 'p1' => $page][$id] ?? null);
    $service = sosNodeIdService([
        'siteRootForEntry' => fn (): string => 'https://root.test/',
        'resolvePropertyValue' => fn (): string => 'https://example.test/page',
    ]);

    expect(sosNodeIdCall($service, 'canonicalAtIdForReentrantSchemaTemplateNode', 'missing'))->toBe('#missing')
        ->and(sosNodeIdCall($service, 'canonicalAtIdForReentrantSchemaTemplateNode', 't1'))->toBe('https://root.test/#t1')
        ->and(sosNodeIdCall($service, 'canonicalAtIdForReentrantSchemaTemplateNode', 'p1'))->toBe('https://example.test/page#p1');
});

// ---
// previewCanonicalAtIdForSchemaObjectTemplate()
// ---

it('previews no @id for an unknown id or an entry outside schema_objects', function (): void {
    Entry::shouldReceive('find')->andReturnUsing(fn (string $id): ?EntryModel => $id === 'p1' ? sosNodeIdEntry('p1', 'pages') : null);
    $service = sosNodeIdService(['resolveEffectiveTemplateInstanceIndexForWatermark' => fn (): never => throw new LogicException('not reached')]);

    expect($service->previewCanonicalAtIdForSchemaObjectTemplate('missing', null, []))->toBeNull()
        ->and($service->previewCanonicalAtIdForSchemaObjectTemplate('p1', null, []))->toBeNull();
});

it('previews a schema entry @id with its effective instance index, without committing the watermark', function (): void {
    Entry::shouldReceive('find')->with('t1')->andReturn(sosNodeIdEntry('t1', 'schema_objects'));
    $calls = [];
    $service = sosNodeIdService([
        'resolveEffectiveTemplateInstanceIndexForWatermark' => function (string $id, mixed $context, array $graph, array $antlers, mixed $bag, bool $commit) use (&$calls): int {
            $calls[] = [$id, $graph, $antlers, $commit];

            return 3;
        },
        'getCanonicalAtIdForSchemaEntry' => fn (): string => 'https://example.test/#t1',
    ]);

    expect($service->previewCanonicalAtIdForSchemaObjectTemplate('t1', null, ['g1'], ['k' => 'v']))->toBe('https://example.test/#t1-3')
        ->and($calls)->toBe([['t1', ['g1'], ['k' => 'v'], false]]);
});
