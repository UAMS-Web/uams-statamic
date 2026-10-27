<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaOutputService;
use Illuminate\Http\Request;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\EntryCollection;
use Statamic\Facades\Blink;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Tests\TestCase;

/**
 * UAMS-Web/uams-statamic-archive#1067 slice 3 — transitive `knowsAbout` on the Provider (`people`) `[Person]` node.
 *
 * Per the recorded decision (see docs/DECISIONS.md, 2026-07-09), the provider D9
 * emission is wired on the routable `people` `[Person]` node (`430ada55…`), NOT the
 * non-routable `clinical_providers` `IndividualPhysician` node — `people` natively
 * carries the UAMS-Web/uams-statamic-archive#1063 `implied_services`/`implied_conditions` transitive computeds and
 * is the identity #47 folds `clinical_providers` into.
 *
 * The node emits `knowsAbout` over both implied sets: each implied `clinical_services`
 * entry resolves to its `MedicalProcedure` node and each `clinical_conditions` entry to
 * its `MedicalCondition` node. `knowsAbout` is valid on `Person`.
 *
 * The `implied_*` computeds run a `whereJsonOverlaps` query (mocked here to return the
 * fixture C/T entries, per the UAMS-Web/uams-statamic-archive#1063 pattern); `Entry::find()` is mocked so the resolver
 * can resolve each implied entry to its node (C/T content is import-purged).
 *
 * @command php artisan test --compact tests/Feature/SchemaJsonLdProviderKnowsAboutTest.php
 */
uses(TestCase::class, MockeryPHPUnitIntegration::class);

const PEOPLE_PERSON_NODE = '430ada55-4f2e-4dab-907f-4d51fbbe26bc';

beforeEach(function (): void {
    useProductionJsonLdFragmentCacheRegime();
    app()->instance('request', Request::create('http://uams-statamic.test/', 'GET'));
    Blink::flush();
});

/** An in-memory C/T entry for a node-resolution + implied-set target. */
function knowsAboutCtFixture(string $collection, string $blueprint, string $id, string $title): Statamic\Entries\Entry
{
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
    $entry = $entry->slug($id);
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->data(['title' => $title]);

    return $entry;
}

it('emits knowsAbout over the transitive implied Services and Conditions, resolving to MedicalProcedure and MedicalCondition nodes', function () {
    $service = knowsAboutCtFixture('clinical_services', 'clinical_service', 'svc-implied', 'Insulin Pump Therapy');
    $condition = knowsAboutCtFixture('clinical_conditions', 'clinical_condition', 'cond-implied', 'Type 2 Diabetes');

    // Stub the UAMS-Web/uams-statamic-archive#1063 implied computeds to return the fixture C/T sets directly —
    // re-registering by `<scope>.<field>` key replaces the boot registration
    // (StoresScopedComputedFieldCallbacks), so we bypass the `whereJsonOverlaps`
    // query without mocking Entry::query() (which would also break the node-entry
    // load). Blink::flush() drops the cached callback map so the new closures win.
    Collection::computed('people', 'transitive_services', fn (): EntryCollection => EntryCollection::make([$service]));
    Collection::computed('people', 'transitive_conditions', fn (): EntryCollection => EntryCollection::make([$condition]));
    Blink::flush();

    // Resolve each implied entry to its node (C/T content is import-purged).
    $find = Entry::partialMock();
    $find->shouldReceive('find')->with('svc-implied')->andReturn($service);
    $find->shouldReceive('find')->with('cond-implied')->andReturn($condition);

    // A provider people entry anchored to one specialty (drives the overlap).
    /** @var Statamic\Entries\Entry $person */
    $person = Entry::make();
    /** @var Statamic\Entries\Entry $person */
    $person = $person->collection('people');
    /** @var Statamic\Entries\Entry $person */
    $person = $person->blueprint('person');
    /** @var Statamic\Entries\Entry $person */
    $person = $person->locale('health');
    /** @var Statamic\Entries\Entry $person */
    $person = $person->id('person-provider');
    /** @var Statamic\Entries\Entry $person */
    $person = $person->slug('dr-example');
    /** @var Statamic\Entries\Entry $person */
    $person = $person->data(['title' => 'Dr. Example', 'expertise' => ['spec-endo']]);

    $node = app(SchemaOutputService::class)->buildStandaloneSchemaObjectNode(PEOPLE_PERSON_NODE, $person);

    expect($node)->toBeArray();

    /** @var array<mixed> $knowsAbout */
    $knowsAbout = $node['knowsAbout'] ?? null;
    expect($knowsAbout)->not->toBeNull();

    $nodes = array_is_list($knowsAbout) ? $knowsAbout : [$knowsAbout];
    $types = [];
    foreach ($nodes as $n) {
        $t = is_array($n) ? ($n['@type'] ?? null) : null;
        foreach (is_array($t) ? $t : [$t] as $one) {
            $types[] = $one;
        }
    }

    expect($types)->toContain('MedicalProcedure')
        ->and($types)->toContain('MedicalCondition');
});

it('emits no knowsAbout when the provider has no specialties (empty transitive set)', function () {
    // No `expertise` → the implied computeds short-circuit to empty (no query).
    /** @var Statamic\Entries\Entry $person */
    $person = Entry::make();
    /** @var Statamic\Entries\Entry $person */
    $person = $person->collection('people');
    /** @var Statamic\Entries\Entry $person */
    $person = $person->blueprint('person');
    /** @var Statamic\Entries\Entry $person */
    $person = $person->locale('health');
    /** @var Statamic\Entries\Entry $person */
    $person = $person->id('person-no-specialty');
    /** @var Statamic\Entries\Entry $person */
    $person = $person->slug('dr-empty');
    /** @var Statamic\Entries\Entry $person */
    $person = $person->data(['title' => 'Dr. Empty', 'expertise' => []]);

    $node = app(SchemaOutputService::class)->buildStandaloneSchemaObjectNode(PEOPLE_PERSON_NODE, $person);

    expect($node)->toBeArray()
        ->and($node['knowsAbout'] ?? null)->toBeIn([null, [], '']);
});

/**
 * The `termCode` of every DefinedTerm in a built node's `knowsAbout`.
 *
 * @param  array<mixed>  $node
 * @return list<mixed>
 */
function knowsAboutTermCodes(array $node): array
{
    $knowsAbout = $node['knowsAbout'] ?? [];
    $items = is_array($knowsAbout) && array_is_list($knowsAbout) ? $knowsAbout : [$knowsAbout];
    $codes = [];

    foreach ($items as $item) {
        if (is_array($item) && ($item['@type'] ?? null) === 'DefinedTerm' && isset($item['termCode'])) {
            $codes[] = $item['termCode'];
        }
    }

    return $codes;
}

/**
 * An unsaved provider on `health` holding the given clinical specialization ids.
 *
 * @param  list<string>  $specializationIds
 */
function knowsAboutSpecializationProvider(string $slug, array $specializationIds): Statamic\Entries\Entry
{
    /** @var Statamic\Entries\Entry $person */
    $person = Entry::make();
    /** @var Statamic\Entries\Entry $person */
    $person = $person->collection('people');
    /** @var Statamic\Entries\Entry $person */
    $person = $person->blueprint('person');
    /** @var Statamic\Entries\Entry $person */
    $person = $person->locale('health');
    /** @var Statamic\Entries\Entry $person */
    $person = $person->id("person-{$slug}");
    /** @var Statamic\Entries\Entry $person */
    $person = $person->slug($slug);
    /** @var Statamic\Entries\Entry $person */
    $person = $person->data(['title' => 'Dr. Example', 'expertise' => [], 'clinical_specializations' => $specializationIds]);

    return $person;
}

/**
 * UAMS-Web/uams-statamic-archive#2684: the content people node's custom `knowsAbout` gains the base node's
 * clinical-specialization loop, so production provider pages emit a
 * specialization as a DefinedTerm carrying its Health Care Provider Taxonomy code.
 */
it('emits a code-set clinical specialization in the production people node knowsAbout', function () {
    // The content Thoracic Surgeon entry: 208G00000X, not an extension.
    $person = knowsAboutSpecializationProvider('dr-thoracic', ['cc9e8d05-1d2a-4be0-94e7-09e158060723']);

    $node = app(SchemaOutputService::class)->buildStandaloneSchemaObjectNode(PEOPLE_PERSON_NODE, $person);

    expect($node)->toBeArray()
        ->and(knowsAboutTermCodes(mustBeArray($node, 'the people node')))->toBe(['208G00000X']);
});

it('emits no specialization in the production people node knowsAbout for a provider without one', function () {
    $person = knowsAboutSpecializationProvider('dr-no-specialization', []);

    $node = app(SchemaOutputService::class)->buildStandaloneSchemaObjectNode(PEOPLE_PERSON_NODE, $person);

    expect($node)->toBeArray()
        ->and(knowsAboutTermCodes(mustBeArray($node, 'the people node')))->toBe([]);
});
