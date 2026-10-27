<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaOutputService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Facades\Entry;
use Tests\TestCase;

/**
 * End-to-end JSON-LD coverage for the UAMS-Web/uams-statamic-archive#1067 page `about`/`mainEntity` emission (slice 1).
 *
 * A content page (`pages` collection, `basic_page`/`landing_page`) that declares it is
 * *about* a clinical condition/service via the UAMS-Web/uams-statamic-archive#1065 entries-typed aboutness fields
 * (`about_clinical_entries`, `mentions_clinical_entries`, `main_entity_of_page_clinical_entry`)
 * must emit `MedicalWebPage.about` / `mentions` / `mainEntity` resolving to that C/T's
 * `MedicalCondition` / `MedicalProcedure` node. The emission is wired on the content-page
 * `MedicalWebPage` schema_object node (`10670a0e…`) via `schema_thing_from_relationship`.
 *
 * `clinical_conditions` content is import-time and purged, so there is no real condition
 * entry to traverse to — the relationship target is provided by mocking the `Entry` facade's
 * `find()` (partial mock, so `make()` still works) to return an in-memory condition, which
 * then resolves its own `MedicalCondition` node via the `clinical_conditions` cascade.
 *
 * @command php artisan test --compact tests/Feature/SchemaJsonLdContentPageAboutnessTest.php
 */
uses(TestCase::class, MockeryPHPUnitIntegration::class);

/** The content-page `MedicalWebPage` node carrying the C/T aboutness emission (UAMS-Web/uams-statamic-archive#1067). */
const CONTENT_PAGE_MEDICALWEBPAGE_NODE = '10670a0e-c07a-4e17-8be5-000000001067';

beforeEach(function (): void {
    useProductionJsonLdFragmentCacheRegime();
    app()->instance('request', Request::create('http://uams-statamic.test/', 'GET'));
});

/**
 * An in-memory `clinical_conditions` entry (blueprint `clinical_condition`) so the aboutness
 * traversal resolves it to its `MedicalCondition` node via the collection cascade.
 */
function fixtureCondition(string $id, string $title): Statamic\Entries\Entry
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->collection('clinical_conditions');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->blueprint('clinical_condition');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->locale('health');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->id($id);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->slug(Str::slug($title));
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->data(['title' => $title]);

    return $entry;
}

/**
 * Build the content-page `MedicalWebPage` node for an in-memory page carrying the given
 * aboutness data, with `Entry::find()` mocked to return the supplied C/T fixtures.
 *
 * @param  array<string, mixed>  $data
 * @param  array<string, Statamic\Entries\Entry>  $findable  id => entry, for the traversal
 * @return array<string, mixed>|null
 */
function buildContentPageAboutnessNode(array $data, array $findable): ?array
{
    $find = Entry::partialMock();
    foreach ($findable as $id => $entry) {
        $find->shouldReceive('find')->with($id)->andReturn($entry);
    }

    /** @var Statamic\Entries\Entry $page */
    $page = Entry::make();
    /** @var Statamic\Entries\Entry $page */
    $page = $page->collection('pages');
    /** @var Statamic\Entries\Entry $page */
    $page = $page->blueprint('basic_page');
    /** @var Statamic\Entries\Entry $page */
    $page = $page->locale('health');
    /** @var Statamic\Entries\Entry $page */
    $page = $page->id($data['id'] ?? 'fixture-content-page');
    /** @var Statamic\Entries\Entry $page */
    $page = $page->slug($data['slug'] ?? 'fixture-content-page');
    /** @var Statamic\Entries\Entry $page */
    $page = $page->data($data);

    return app(SchemaOutputService::class)->buildStandaloneSchemaObjectNode(
        CONTENT_PAGE_MEDICALWEBPAGE_NODE,
        $page
    );
}

it('emits MedicalWebPage.about resolving to the declared condition node', function () {
    $condition = fixtureCondition('cond-diabetes', 'Type 2 Diabetes');

    $node = buildContentPageAboutnessNode(
        ['about_clinical_entries' => ['cond-diabetes']],
        ['cond-diabetes' => $condition],
    );

    expect($node)->toBeArray()
        ->and($node['@type'] ?? null)->toBe('MedicalWebPage');

    /** @var array<array-key, mixed> $about */
    $about = $node['about'] ?? null;
    expect($about)->not->toBeNull();

    // The about node (or one of them) resolves to the condition's MedicalCondition node.
    $aboutNodes = array_is_list($about) ? $about : [$about];
    $types = array_map(fn ($n): mixed => is_array($n) ? ($n['@type'] ?? null) : null, $aboutNodes);
    expect($types)->toContain('MedicalCondition');
});

it('emits MedicalWebPage.mainEntity resolving to the declared primary condition node', function () {
    $condition = fixtureCondition('cond-diabetes', 'Type 2 Diabetes');

    $node = buildContentPageAboutnessNode(
        ['main_entity_of_page_clinical_entry' => ['cond-diabetes']],
        ['cond-diabetes' => $condition],
    );

    /** @var array<array-key, mixed> $mainEntity */
    $mainEntity = $node['mainEntity'] ?? null;
    expect($mainEntity)->not->toBeNull();

    $nodes = array_is_list($mainEntity) ? $mainEntity : [$mainEntity];
    $types = array_map(fn ($n): mixed => is_array($n) ? ($n['@type'] ?? null) : null, $nodes);
    expect($types)->toContain('MedicalCondition');
});

it('emits MedicalWebPage.mentions resolving to the declared condition node', function () {
    $condition = fixtureCondition('cond-diabetes', 'Type 2 Diabetes');

    $node = buildContentPageAboutnessNode(
        ['mentions_clinical_entries' => ['cond-diabetes']],
        ['cond-diabetes' => $condition],
    );

    /** @var array<array-key, mixed> $mentions */
    $mentions = $node['mentions'] ?? null;
    expect($mentions)->not->toBeNull();

    $nodes = array_is_list($mentions) ? $mentions : [$mentions];
    $types = array_map(fn ($n): mixed => is_array($n) ? ($n['@type'] ?? null) : null, $nodes);
    expect($types)->toContain('MedicalCondition');
});

it('dedupes a multiply-reached C/T to one full node in the composed @graph', function () {
    // AC: all emitted nodes dedupe in the composed @graph (no duplicate when a C/T is reached
    // by more than one edge). The single-node standalone path does NOT run graph dedup — it
    // legitimately emits the same full node under both `about` and `mentions` (with the same
    // @id). This exercises the production compose path (`composeJsonLdOutputScriptTagForResolvedSlots`,
    // what `{{ schema_ld_json }}` drives), where SchemaJsonLdAtIdDeduplication collapses the
    // repeat: the condition must appear as exactly ONE full MedicalCondition node in the @graph.
    $condition = fixtureCondition('cond-diabetes', 'Type 2 Diabetes');
    $find = Entry::partialMock();
    $find->shouldReceive('find')->with('cond-diabetes')->andReturn($condition);

    /** @var Statamic\Entries\Entry $page */
    $page = Entry::make();
    /** @var Statamic\Entries\Entry $page */
    $page = $page->collection('pages');
    /** @var Statamic\Entries\Entry $page */
    $page = $page->blueprint('basic_page');
    /** @var Statamic\Entries\Entry $page */
    $page = $page->locale('health');
    /** @var Statamic\Entries\Entry $page */
    $page = $page->id('page-multiref');
    /** @var Statamic\Entries\Entry $page */
    $page = $page->slug('page-multiref');
    /** @var Statamic\Entries\Entry $page */
    $page = $page->data([
        'about_clinical_entries' => ['cond-diabetes'],
        'mentions_clinical_entries' => ['cond-diabetes'],
    ]);

    $service = app(SchemaOutputService::class);
    $compose = new ReflectionMethod($service, 'composeJsonLdOutputScriptTagForResolvedSlots');
    $compose->setAccessible(true);
    // Only the content-page MedicalWebPage node (the aboutness carrier) in the webpage slot;
    // no subject/website/main_content so the graph is just the page + the condition it reaches.
    /** @var string $scriptTag */
    $scriptTag = $compose->invoke($service, $page, null, null, CONTENT_PAGE_MEDICALWEBPAGE_NODE, null);

    $json = trim((string) preg_replace('#^<script[^>]*>|</script>$#', '', trim((string) $scriptTag)));

    // The stripped script tag must carry a valid JSON-LD payload, not a malformed one.
    expect($json)->toBeJson();

    /** @var array<string, mixed> $decoded */
    $decoded = json_decode($json, true);

    /** @var array<int, array<string, mixed>> $graph */
    $graph = $decoded['@graph'] ?? [$decoded];

    // The condition is reached by both `about` and `mentions` on the one MedicalWebPage node.
    // Collect both references; dedup must leave exactly ONE full MedicalCondition node (@type
    // present) and collapse the other to a bare {"@id": …} ref.
    $webpage = null;
    foreach ($graph as $n) {
        if (in_array('MedicalWebPage', is_array($n['@type'] ?? null) ? $n['@type'] : [$n['@type'] ?? null], true)) {
            $webpage = $n;
            break;
        }
    }
    expect($webpage)->not->toBeNull();

    $refs = [];
    foreach (['about', 'mentions'] as $prop) {
        /** @var array<array-key, mixed> $value */
        $value = $webpage[$prop] ?? null;
        foreach (array_is_list($value) ? $value : [$value] as $ref) {
            if (! is_array($ref)) {
                continue;
            }
            /** @var string $id */
            $id = $ref['@id'] ?? '';
            if (str_contains((string) $id, 'eb185cba')) {
                $refs[] = $ref;
            }
        }
    }

    $fullNodes = array_filter($refs, fn (array $r): bool => isset($r['@type']));
    $idOnlyRefs = array_filter($refs, fn (array $r): bool => ! isset($r['@type']) && isset($r['@id']));

    expect(count($refs))->toBe(2)
        ->and(count($fullNodes))->toBe(1)
        ->and(count($idOnlyRefs))->toBe(1);
});

it('emits no about node when the page declares no C/T aboutness', function () {
    $node = buildContentPageAboutnessNode(['about_clinical_entries' => []], []);

    expect($node)->toBeArray()
        ->and($node['@type'] ?? null)->toBe('MedicalWebPage');
    // about is either absent or empty — never a stray node.
    expect($node['about'] ?? null)->toBeIn([null, [], '']);
});
