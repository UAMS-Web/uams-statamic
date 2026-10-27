<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaOutputService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Facades\Blink;
use Statamic\Facades\Entry;
use Statamic\Facades\YAML;
use Tests\TestCase;

/**
 * UAMS-Web/uams-statamic-archive#1630 — a `provider_spotlight` page is *about* its one featured provider, so
 * the spotlight's `Article` schema node references that provider's `Person`
 * node under `about` and `mainEntity`, wired via `schema_thing_from_relationship`
 * over the `featured_provider` relationship.
 *
 * The wiring rides on the shared clinical-resources `Article` node
 * (`0f132206…`) with `mode: inherit_custom`, so it is naturally scoped to
 * spotlights: the other clinical-resource types (`article`, `audio`,
 * `document`, `infographic`, `video`) carry no `featured_provider` field, so
 * the block emits nothing and their `about`/`mainEntity` are unchanged — the
 * same guard shape the podcast blocks use via `is_podcast_episode`.
 *
 * The featured provider is import-time / purged content, so `Entry::find()` is
 * partially mocked to return an in-memory `people` fixture that resolves its own
 * `Person` node via the people-collection cascade.
 *
 * @command php artisan test --compact tests/Feature/SchemaJsonLdProviderSpotlightAboutTest.php
 */
uses(TestCase::class, MockeryPHPUnitIntegration::class);

/** The shared clinical-resources `Article` (main-content) node the spotlight renders through. */
const CLINICAL_RESOURCE_ARTICLE_NODE = '0f132206-3836-47dd-afe0-df744cd5baea';

beforeEach(function (): void {
    useProductionJsonLdFragmentCacheRegime();
    app()->instance('request', Request::create('http://uams-statamic.test/', 'GET'));
    Blink::flush();
});

/**
 * An in-memory `people` featured-provider fixture that resolves to its own
 * `Person` schema node via the people-collection cascade.
 */
function spotlightFeaturedProvider(string $id): Statamic\Entries\Entry
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection('people');
    $entry->blueprint('person');
    $entry->locale('health');
    $entry->id($id);
    $entry->slug(Str::slug($id));
    $entry->data([
        'title' => 'Dr. Leonard H. McCoy',
        'person_name' => [
            'first_name' => ['text' => 'Leonard', 'phonetic_text' => null],
            'no_middle_name' => false,
            'middle_name' => ['text' => 'Horatio', 'phonetic_text' => null],
            'no_nickname' => true,
            'last_name' => ['text' => 'McCoy', 'phonetic_text' => null],
            'no_generational_suffix' => true,
            'generational_suffix' => null,
            'display_format' => 'full_first_middle_initial@ns',
        ],
    ]);

    return $entry;
}

/**
 * Build the clinical-resources `Article` node for an in-memory clinical-resource
 * entry, mocking `Entry::find()` for any related fixtures the traversal resolves.
 *
 * @param  array<string, mixed>  $data
 * @param  array<string, Statamic\Entries\Entry>  $findable  id => entry
 * @return array<string, mixed>|null
 */
function buildClinicalResourceArticleNode(string $blueprint, array $data, array $findable = []): ?array
{
    $find = Entry::partialMock();
    foreach ($findable as $id => $entry) {
        $find->shouldReceive('find')->with($id)->andReturn($entry);
    }

    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection('clinical_resources');
    $entry->blueprint($blueprint);
    $entry->locale('health');
    $entry->id($data['id'] ?? 'fixture-resource');
    $entry->slug($data['slug'] ?? 'fixture-resource');
    $entry->data($data);

    return app(SchemaOutputService::class)->buildStandaloneSchemaObjectNode(CLINICAL_RESOURCE_ARTICLE_NODE, $entry);
}

/**
 * Every @type string emitted under the given node property, flattened
 * (a property value may be a single node or a list; a node's @type may be a list).
 *
 * @param  array<string, mixed>|null  $node
 * @return list<string>
 */
function schemaTypesUnder(?array $node, string $property): array
{
    /** @var array<int|string, mixed>|null $value */
    $value = $node[$property] ?? null;
    if ($value === null) {
        return [];
    }

    $nodes = array_is_list($value) ? $value : [$value];

    $types = [];
    foreach ($nodes as $n) {
        if (! is_array($n)) {
            continue;
        }
        $type = $n['@type'] ?? null;
        foreach (is_array($type) ? $type : [$type] as $t) {
            if (is_string($t)) {
                $types[] = $t;
            }
        }
    }

    return $types;
}

/**
 * Every `@id` under the given node property, whether an inline node or a compact
 * `{"@id": …}` reference (the graph emits the full node once and references it by
 * `@id` in the other slot).
 *
 * @param  array<string, mixed>|null  $node
 * @return list<string>
 */
function schemaAtIdsUnder(?array $node, string $property): array
{
    /** @var array<int|string, mixed>|null $value */
    $value = $node[$property] ?? null;
    if ($value === null) {
        return [];
    }

    $nodes = array_is_list($value) ? $value : [$value];

    $ids = [];
    foreach ($nodes as $n) {
        if (is_array($n) && is_string($n['@id'] ?? null)) {
            $ids[] = $n['@id'];
        }
    }

    return $ids;
}

it('references the featured provider Person node under the spotlight about and mainEntity (#1630)', function (): void {
    $node = buildClinicalResourceArticleNode(
        'provider_spotlight',
        ['featured_provider' => ['prov-spotlight']],
        ['prov-spotlight' => spotlightFeaturedProvider('prov-spotlight')],
    );

    // The featured provider's node @id (the graph emits it fully once and
    // references it by @id in the other slot, so resolve it robustly).
    $providerId = collect(schemaAtIdsUnder($node, 'about'))
        ->first(fn (string $id): bool => str_contains($id, 'prov-spotlight'));

    expect($providerId)->not->toBeNull();

    // Both about and mainEntity point at that provider node.
    expect(schemaAtIdsUnder($node, 'about'))->toContain($providerId);
    expect(schemaAtIdsUnder($node, 'mainEntity'))->toContain($providerId);

    // The full node is the provider's Person node (emitted inline under one slot).
    $types = [...schemaTypesUnder($node, 'about'), ...schemaTypesUnder($node, 'mainEntity')];
    expect($types)->toContain('Person');
});

it('leaves the other clinical-resource types free of a provider about/mainEntity node (#1630)', function (string $blueprint): void {
    // A non-spotlight type carries no `featured_provider`, so the block emits
    // nothing and no Person node appears under about/mainEntity.
    $node = buildClinicalResourceArticleNode($blueprint, ['id' => 'res-'.$blueprint]);

    expect(schemaTypesUnder($node, 'about'))->not->toContain('Person');
    expect(schemaTypesUnder($node, 'mainEntity'))->not->toContain('Person');
})->with(['article', 'audio', 'document', 'infographic', 'video']);

it('emits nothing under about/mainEntity when a spotlight has no featured provider (#1630)', function (): void {
    $node = buildClinicalResourceArticleNode('provider_spotlight', ['id' => 'empty-spotlight']);

    expect(schemaTypesUnder($node, 'about'))->not->toContain('Person');
    expect(schemaTypesUnder($node, 'mainEntity'))->not->toContain('Person');
});

// Pin the wiring so a future edit to plain `custom` (which would drop any
// inherited about value) or a dropped/renamed block fails the build.
it('keeps the featured_provider block on about and mainEntity as inherit_custom (#1630)', function (string $property): void {
    $path = __DIR__.'/../../content/collections/schema_objects/default/clinical-resources-collection-clinical-resource-entry-as-content.md';
    /** @var array<string, mixed> $parsed */
    $parsed = YAML::file($path)->parse();

    /** @var array<string, mixed> $schema */
    $schema = $parsed['schema'] ?? [];
    /** @var array<string, mixed> $properties */
    $properties = $schema['properties'] ?? [];
    /** @var array<string, mixed> $prop */
    $prop = $properties[$property] ?? [];

    expect($prop['mode'] ?? null)->toBe('inherit_custom');

    /** @var array<int, array<string, mixed>> $blocks */
    $blocks = $prop['value'] ?? [];
    $codes = array_map(
        function (array $block): string {
            /** @var array<string, mixed> $value */
            $value = $block['value'] ?? [];

            return \is_string($value['code'] ?? null) ? $value['code'] : '';
        },
        $blocks
    );

    expect(implode("\n", $codes))->toContain('current_field="featured_provider"');
})->with(['about', 'mainEntity']);
