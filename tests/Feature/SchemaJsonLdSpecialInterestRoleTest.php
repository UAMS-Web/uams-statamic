<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaOutputService;
use App\Services\SchemaTemplates\SchemaTemplatesCascadeService;
use Illuminate\Http\Request;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\EntryCollection;
use Statamic\Facades\Blink;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Tests\TestCase;

/**
 * UAMS-Web/uams-statamic-archive#1067 slice 5 — Special Interest as a `Role`-wrapped node.
 *
 * The provider `special_interest_*` fields (UAMS-Web/uams-statamic-archive#1064) emit as a `Role` node (roleName
 * "Special Interest") feeding Physician `knowsAbout`, and the location's as a `Role`
 * feeding `availableService` — distinguishable in the `@graph` from the plain transitive
 * edges. Per the recorded decision, the plain legs read the overlap-**minus**-override
 * `transitive_*` computeds, so a special-interest item is emitted **only** inside the
 * `Role` (never also in the bulk list).
 *
 * UAMS-Web/uams-statamic-archive#1444 moved the location `availableService` (Role included) off the shared base node
 * onto the per-subtype `Hospital`/`MedicalClinic` nodes, so the location legs build the
 * node the location routes to via `SchemaTemplatesCascadeService` — the node a real page
 * emits — rather than the base node directly.
 *
 * The Role node is emitted via `{{ schema_template_node }}` against the subject entity and
 * reads its raw `special_interest_*` fields; `Entry::find()` is mocked to resolve each
 * special-interest C/T to its node (content is import-purged).
 *
 * @command php artisan test --compact tests/Feature/SchemaJsonLdSpecialInterestRoleTest.php
 */
uses(TestCase::class, MockeryPHPUnitIntegration::class);

const SI_PEOPLE_NODE = '430ada55-4f2e-4dab-907f-4d51fbbe26bc';
const SI_LOCATION_NODE = '0b9f1bee-6c0b-4ba5-9ddc-850794f08c8d';

beforeEach(function (): void {
    useProductionJsonLdFragmentCacheRegime();
    app()->instance('request', Request::create('http://uams-statamic.test/', 'GET'));
    Blink::flush();
});

function siCtFixture(string $collection, string $blueprint, string $id, string $title): Statamic\Entries\Entry
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    $entry->collection($collection);
    $entry->blueprint($blueprint);
    $entry->locale('health');
    $entry->id($id);
    $entry->slug($id);
    $entry->data(['title' => $title]);

    return $entry;
}

/**
 * Flatten a knowsAbout/availableService value to a list of node arrays.
 *
 * @return array<int, mixed>
 */
function siNodeList(mixed $value): array
{
    if ($value === null) {
        return [];
    }

    /** @var array<int|string, mixed> $value */
    return array_is_list($value) ? $value : [$value];
}

/**
 * The first Role node in a property value, or null.
 *
 * @return array<array-key, mixed>|null
 */
function siFindRole(mixed $value): ?array
{
    foreach (siNodeList($value) as $node) {
        if (! is_array($node)) {
            continue;
        }
        $type = $node['@type'] ?? null;
        $types = is_array($type) ? $type : [$type];
        if (in_array('Role', $types, true)) {
            return $node;
        }
    }

    return null;
}

/** @return list<mixed> the @type values found inside a property value */
function siTypesIn(mixed $value): array
{
    $types = [];
    foreach (siNodeList($value) as $node) {
        $t = is_array($node) ? ($node['@type'] ?? null) : null;
        foreach (is_array($t) ? $t : [$t] as $one) {
            $types[] = $one;
        }
    }

    return $types;
}

/**
 * Build the schema node a location routes to (UAMS-Web/uams-statamic-archive#1444 subtype routing) — the node on
 * which the location `availableService` (Role included) lives — for the location legs.
 *
 * @return array<string, mixed>|null
 */
function siLocationRoutedNode(Statamic\Contracts\Entries\Entry $location): ?array
{
    $resolved = app(SchemaTemplatesCascadeService::class)->resolve($location)['main_content_entry'];
    $nodeId = \is_string($resolved) && $resolved !== '' ? $resolved : SI_LOCATION_NODE;

    return app(SchemaOutputService::class)->buildStandaloneSchemaObjectNode($nodeId, $location);
}

it('emits a Role-wrapped Special Interest node on the provider knowsAbout', function () {
    $siService = siCtFixture('clinical_services', 'clinical_service', 'si-svc', 'Bariatric Surgery');
    $siCondition = siCtFixture('clinical_conditions', 'clinical_condition', 'si-cond', 'Obesity');

    // Plain transitive legs empty — isolate the Role. Special interest drives it.
    Collection::computed('people', 'transitive_services', fn (): EntryCollection => EntryCollection::make());
    Collection::computed('people', 'transitive_conditions', fn (): EntryCollection => EntryCollection::make());
    Blink::flush();

    $find = Entry::partialMock();
    $find->shouldReceive('find')->with('si-svc')->andReturn($siService);
    $find->shouldReceive('find')->with('si-cond')->andReturn($siCondition);

    /** @var Statamic\Entries\Entry $person */
    $person = Entry::make();
    $person->collection('people');
    $person->blueprint('person');
    $person->locale('health');
    $person->id('person-si');
    $person->slug('dr-si');
    $person->data([
        'title' => 'Dr. Special Interest',
        'special_interest_clinical_services' => ['si-svc'],
        'special_interest_clinical_conditions' => ['si-cond'],
    ]);

    $node = app(SchemaOutputService::class)->buildStandaloneSchemaObjectNode(SI_PEOPLE_NODE, $person);

    expect($node)->toBeArray();

    $role = siFindRole($node['knowsAbout'] ?? null);
    expect($role)->not->toBeNull()
        ->and($role['roleName'] ?? null)->toBe('Special Interest');

    // The Role's own knowsAbout carries the special-interest service + condition nodes.
    $innerTypes = siTypesIn($role['knowsAbout'] ?? null);
    expect($innerTypes)->toContain('MedicalProcedure')
        ->and($innerTypes)->toContain('MedicalCondition');
});

it('emits no Special Interest Role on the provider when no special interest is authored', function () {
    Collection::computed('people', 'transitive_services', fn (): EntryCollection => EntryCollection::make());
    Collection::computed('people', 'transitive_conditions', fn (): EntryCollection => EntryCollection::make());
    Blink::flush();

    /** @var Statamic\Entries\Entry $person */
    $person = Entry::make();
    $person->collection('people');
    $person->blueprint('person');
    $person->locale('health');
    $person->id('person-no-si');
    $person->slug('dr-no-si');
    $person->data(['title' => 'Dr. No Special Interest']);

    $node = app(SchemaOutputService::class)->buildStandaloneSchemaObjectNode(SI_PEOPLE_NODE, $person);

    expect($node)->toBeArray();
    expect(siFindRole($node['knowsAbout'] ?? null))->toBeNull();
});

it('emits a Role-wrapped Special Interest node on the location availableService', function () {
    $siService = siCtFixture('clinical_services', 'clinical_service', 'si-loc-svc', 'Sleep Study');

    Collection::computed('clinical_locations', 'transitive_services', fn (): EntryCollection => EntryCollection::make());
    Blink::flush();

    $find = Entry::partialMock();
    $find->shouldReceive('find')->with('si-loc-svc')->andReturn($siService);

    /** @var Statamic\Entries\Entry $location */
    $location = Entry::make();
    $location->collection('clinical_locations');
    $location->blueprint('location');
    $location->locale('health');
    $location->id('loc-si');
    $location->slug('uams-si');
    $location->data([
        'title' => 'UAMS Special Interest Clinic',
        'schema_medicalorganization_subtype' => 'MedicalClinic',
        'special_interest_clinical_services' => ['si-loc-svc'],
    ]);

    $node = siLocationRoutedNode($location);

    $role = siFindRole($node['availableService'] ?? null);
    expect($role)->not->toBeNull()
        ->and($role['roleName'] ?? null)->toBe('Special Interest')
        ->and(siTypesIn($role['availableService'] ?? null))->toContain('MedicalProcedure');
});

it('emits no location Role for a condition-only special interest (services-only gate)', function () {
    // The location Role feeds availableService from services alone; a location whose only
    // special interest is a CONDITION must not emit a bare, targetless Role. The gate is
    // has_special_interest_services (services-only), not the combined has_special_interest
    // (which fires on the condition and would leak an empty Role — UAMS-Web/uams-statamic-archive#1067 slice 5 review).
    Collection::computed('clinical_locations', 'transitive_services', fn (): EntryCollection => EntryCollection::make());
    Blink::flush();

    /** @var Statamic\Entries\Entry $location */
    $location = Entry::make();
    $location->collection('clinical_locations');
    $location->blueprint('location');
    $location->locale('health');
    $location->id('loc-si-cond-only');
    $location->slug('uams-si-cond');
    $location->data([
        'title' => 'UAMS Condition-Only Clinic',
        'schema_medicalorganization_subtype' => 'MedicalClinic',
        'special_interest_clinical_conditions' => ['si-cond-only'],
        'special_interest_clinical_services' => [],
    ]);

    $node = siLocationRoutedNode($location);

    // Pin the routed node identity so the services-only gate is exercised on the
    // MedicalClinic node that actually carries the Role template — not a silent
    // base-node fallback (which has no availableService, and would pass vacuously).
    expect($node['@type'] ?? null)->toBe('MedicalClinic');
    expect(siFindRole($node['availableService'] ?? null))->toBeNull();
});
