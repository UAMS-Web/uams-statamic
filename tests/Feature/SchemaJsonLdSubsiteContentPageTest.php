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
 * UAMS-Web/uams-statamic-archive#1414 — a `basic_page` / `landing_page` content page inside the `clinical_expertise`
 * (Medical Specialty) or `clinical_locations` (Location) virtual subsite must emit page-level
 * JSON-LD as a `WebPage` that `isPartOf` its nearest subsite hub. One shared WebPage node serves
 * both subsites, resolving the hub node through each hub's own cascade (MedicalSpecialty for an
 * Area of Expertise, a MedicalOrganization/subtype for a Location).
 *
 * Hermetic: the JSON-LD cache is disabled and entries are in-memory fixtures. The nearest-hub
 * walk is a structure-tree lookup that yields nothing for a synthetic (unsaved) page, so the
 * `schema_subsite_hub` computed resolves to null and the fixture's own data value stands in —
 * the same pattern the shipped `parent_location_name` computed test uses. `Entry::find()` is
 * partially mocked to return the hub fixture the traversal resolves.
 *
 * @command php artisan test --compact tests/Feature/SchemaJsonLdSubsiteContentPageTest.php
 */
uses(TestCase::class, MockeryPHPUnitIntegration::class);

/** The shared WebPage node wired as the webpage slot for both subsites' content pages. */
const SUBSITE_CONTENT_PAGE_WEBPAGE_NODE = 'c0da5507-13a5-4ea6-9915-1c1ec9c1a418';

beforeEach(function (): void {
    useProductionJsonLdFragmentCacheRegime();
    app()->instance('request', Request::create('http://uams-statamic.test/', 'GET'));
    Blink::flush();
});

/**
 * A hub fixture that resolves to its own schema node via its collection cascade.
 *
 * @param  array<string, mixed>  $data
 */
function hubFixture(string $collection, string $blueprint, string $id, array $data): Statamic\Entries\Entry
{
    /** @var string $title */
    $title = $data['title'] ?? $id;

    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->collection($collection);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->blueprint($blueprint);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->locale('health');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->id($id);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->slug(Str::slug($title));
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->data($data);

    return $entry;
}

/**
 * Build the WebPage node for an in-memory content page, with `Entry::find()` mocked to return
 * the given hub fixtures the isPartOf traversal resolves.
 *
 * @param  array<string, mixed>  $data
 * @param  array<string, Statamic\Entries\Entry>  $findable  id => hub entry
 * @return array<string, mixed>|null
 */
function buildContentPageWebPage(string $collection, string $blueprint, array $data, array $findable): ?array
{
    $find = Entry::partialMock();
    foreach ($findable as $id => $entry) {
        $find->shouldReceive('find')->with($id)->andReturn($entry);
    }

    /** @var Statamic\Entries\Entry $page */
    $page = Entry::make();
    /** @var Statamic\Entries\Entry $page */
    $page = $page->collection($collection);
    /** @var Statamic\Entries\Entry $page */
    $page = $page->blueprint($blueprint);
    /** @var Statamic\Entries\Entry $page */
    $page = $page->locale('health');
    /** @var Statamic\Entries\Entry $page */
    $page = $page->id($data['id'] ?? 'fixture-page');
    /** @var Statamic\Entries\Entry $page */
    $page = $page->slug($data['slug'] ?? 'fixture-page');
    /** @var Statamic\Entries\Entry $page */
    $page = $page->data($data);

    return app(SchemaOutputService::class)->buildStandaloneSchemaObjectNode(SUBSITE_CONTENT_PAGE_WEBPAGE_NODE, $page);
}

/**
 * The isPartOf node's flattened @type list.
 *
 * @param  array<string, mixed>|null  $node
 * @return list<string>
 */
function isPartOfTypes(?array $node): array
{
    $isPartOf = $node['isPartOf'] ?? null;
    if ($isPartOf === null) {
        return [];
    }

    $nodes = is_array($isPartOf) && array_is_list($isPartOf) ? $isPartOf : [$isPartOf];

    $types = [];
    foreach ($nodes as $n) {
        $type = is_array($n) ? ($n['@type'] ?? null) : null;
        foreach (is_array($type) ? $type : [$type] as $t) {
            if (is_string($t)) {
                $types[] = $t;
            }
        }
    }

    return $types;
}

/**
 * The first (or sole) node under isPartOf.
 *
 * @param  array<string, mixed>|null  $node
 * @return array<string, mixed>|null
 */
function isPartOfNode(?array $node): ?array
{
    $isPartOf = $node['isPartOf'] ?? null;
    if ($isPartOf === null) {
        return null;
    }

    $first = is_array($isPartOf) && array_is_list($isPartOf) ? ($isPartOf[0] ?? null) : $isPartOf;

    if (! is_array($first)) {
        return null;
    }

    /** @var array<string, mixed> $first */
    return $first;
}

it('emits a WebPage that isPartOf the parent Medical Specialty for a clinical_expertise content page (#1414)', function () {
    $hub = hubFixture('clinical_expertise', 'clinical_expertise', 'aoe-endo', ['title' => 'Endocrinology']);

    $node = buildContentPageWebPage(
        'clinical_expertise',
        'basic_page',
        ['id' => 'page-endo-overview', 'schema_subsite_hub' => ['aoe-endo']],
        ['aoe-endo' => $hub],
    );

    expect($node['@type'] ?? null)->toBe('WebPage');
    // MedicalSpecialty is unique to the AoE hub — the site-default subject node is a
    // MedicalOrganization, never a MedicalSpecialty — so the @type alone proves
    // isPartOf resolved the expertise hub (no wrong-slot family collision here).
    expect(isPartOfTypes($node))->toContain('MedicalSpecialty');
});

it('emits a WebPage that isPartOf the parent Location for a clinical_locations content page (#1414)', function () {
    $hub = hubFixture('clinical_locations', 'location', 'loc-clinic', ['title' => 'UAMS Endocrine Clinic']);

    $node = buildContentPageWebPage(
        'clinical_locations',
        'landing_page',
        ['id' => 'page-loc-visit', 'schema_subsite_hub' => ['loc-clinic']],
        ['loc-clinic' => $hub],
    );

    expect($node['@type'] ?? null)->toBe('WebPage');
    // The location hub's main-content node is a MedicalOrganization (its base type,
    // or a subtype); assert the family root so the test is subtype-agnostic.
    expect(isPartOfTypes($node))->toContain('MedicalOrganization');
    // Pin the hub's identity — MedicalOrganization is also the site-default subject
    // node's type, so the @type family alone would not prove isPartOf hit the hub.
    expect(isPartOfNode($node)['name'] ?? null)->toBe('UAMS Endocrine Clinic');
});

it('degrades cleanly to a WebPage with no isPartOf when the page has no resolvable subsite hub (#1414)', function () {
    $node = buildContentPageWebPage(
        'clinical_expertise',
        'basic_page',
        ['id' => 'page-orphan', 'title' => 'An orphan content page'],
        [],
    );

    expect($node['@type'] ?? null)->toBe('WebPage');
    expect($node['isPartOf'] ?? null)->toBeNull();
});

it('does not double-emit page-level about / mainEntity on the content-page WebPage node (#1414)', function () {
    // The WebPage node inherits about/mainEntity as mode:inherit (empty), so it never
    // competes with UAMS-Web/uams-statamic-archive#1065's page-level aboutness emission.
    $hub = hubFixture('clinical_expertise', 'clinical_expertise', 'aoe-endo', ['title' => 'Endocrinology']);

    $node = buildContentPageWebPage(
        'clinical_expertise',
        'basic_page',
        ['id' => 'page-endo', 'schema_subsite_hub' => ['aoe-endo']],
        ['aoe-endo' => $hub],
    );

    expect($node)->not->toHaveKey('about')
        ->and($node)->not->toHaveKey('mainEntity');
});

it('wires the content-page WebPage node as the webpage default for both subsites content-page blueprints (#1414)', function (string $file) {
    $path = __DIR__.'/../../content/collections/'.$file;
    /** @var array{inject: array{schema_templates: array{blueprint_defaults?: array<string, array<string, mixed>>}}} $parsed */
    $parsed = YAML::file($path)->parse();
    $defaults = $parsed['inject']['schema_templates']['blueprint_defaults'] ?? [];

    foreach (['basic_page', 'landing_page'] as $blueprint) {
        expect($defaults[$blueprint]['webpage_entry'] ?? null)
            ->toBe(SUBSITE_CONTENT_PAGE_WEBPAGE_NODE, "$file / $blueprint webpage_entry");
        // No separate subject node: a content page IS the WebPage.
        expect($defaults[$blueprint])->toHaveKey('main_content_entry');
        expect($defaults[$blueprint]['main_content_entry'])->toBeNull();
    }
})->with([
    'clinical_expertise.yaml',
    'clinical_locations.yaml',
]);
