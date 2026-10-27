<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaOutputService;
use App\Services\SchemaTemplates\SchemaTemplatesCascadeService;
use App\Tags\Schema\SchemaPropertyValidator;
use Illuminate\Http\Request;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\EntryCollection;
use Statamic\Facades\Blink;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Tests\TestCase;

/**
 * UAMS-Web/uams-statamic-archive#1067 slice 4 + UAMS-Web/uams-statamic-archive#1444 — transitive `availableService` on the routed per-subtype
 * Location node.
 *
 * UAMS-Web/uams-statamic-archive#1444 replaced the render-time `@type` relabel on the shared base node
 * (`0b9f1bee…`, declared `[MedicalOrganization, LocalBusiness]`) with real
 * per-subtype schema nodes: a `clinical_locations` entry's `main_content` slot is
 * routed by its `schema_medicalorganization_subtype` to a dedicated node whose OWN
 * declared `@type` is the subtype (`Hospital`, `MedicalClinic`, `Pharmacy`, …).
 * `availableService` (mode `custom`, over the UAMS-Web/uams-statamic-archive#1063 `transitive_services` computed
 * — each implied `clinical_services` entry → its `MedicalProcedure` node) now lives
 * ONLY on the `Hospital` / `MedicalClinic` nodes, and is gated OFF the non-service
 * subtypes (`Pharmacy`, `Dentist`, `DiagnosticLab`) and the no-subtype base node.
 *
 * So these build the node a real location page emits — the routed per-subtype node,
 * resolved through `SchemaTemplatesCascadeService` exactly as production does — and
 * assert the narrowed `@type` and (for the service subtypes) `availableService`
 * there. The per-subtype node inherits `name` / identity from the shared base tree
 * parent, so it is not missing the location identity. Conditions have no clean
 * schema.org Location predicate (a recorded UAMS-Web/uams-statamic-archive#1067 vocab residual), so only the
 * implied **services** are emitted.
 *
 * @command php artisan test --compact tests/Feature/SchemaJsonLdLocationAvailableServiceTest.php
 */
uses(TestCase::class, MockeryPHPUnitIntegration::class);

/** The shared `MedicalOrganization`/`LocalBusiness` base node — the no-subtype fallback. */
const LOCATION_NODE = '0b9f1bee-6c0b-4ba5-9ddc-850794f08c8d';

beforeEach(function (): void {
    useProductionJsonLdFragmentCacheRegime();
    app()->instance('request', Request::create('http://uams-statamic.test/', 'GET'));
    Blink::flush();
});

/**
 * Build the schema node a location's `main_content` slot resolves to (UAMS-Web/uams-statamic-archive#1444
 * subtype routing): a subtyped location builds its OWN per-subtype node, on which
 * the narrowed `@type` and (for `Hospital`/`MedicalClinic`) `availableService`
 * live; a no-subtype location falls back to the shared base node. This mirrors
 * what the production render path resolves, so the tests build the node a real
 * location page would emit.
 *
 * @return array<string, mixed>|null
 */
function buildRoutedLocationNode(Statamic\Contracts\Entries\Entry $location): ?array
{
    $resolved = app(SchemaTemplatesCascadeService::class)->resolve($location)['main_content_entry'];
    $nodeId = \is_string($resolved) && $resolved !== '' ? $resolved : LOCATION_NODE;

    return app(SchemaOutputService::class)->buildStandaloneSchemaObjectNode($nodeId, $location);
}

it('emits availableService over the implied Services on the routed MedicalClinic/Hospital node', function (string $subtype) {
    /** @var Statamic\Entries\Entry $service */
    $service = Entry::make();
    $service->collection('clinical_services');
    $service->blueprint('clinical_service');
    $service->locale('health');
    $service->id('svc-loc-implied');
    $service->slug('svc-loc-implied');
    $service->data(['title' => 'Physical Therapy']);

    // Stub the UAMS-Web/uams-statamic-archive#1063 implied-services computed on clinical_locations to the fixture
    // set (re-registration replaces by `<scope>.<field>` key); flush the cached
    // callback map so it wins. No Entry::query() mock (that would break node load).
    Collection::computed('clinical_locations', 'transitive_services', fn (): EntryCollection => EntryCollection::make([$service]));
    Blink::flush();

    $find = Entry::partialMock();
    $find->shouldReceive('find')->with('svc-loc-implied')->andReturn($service);

    /** @var Statamic\Entries\Entry $location */
    $location = Entry::make();
    $location->collection('clinical_locations');
    $location->blueprint('location');
    $location->locale('health');
    $location->id('loc-clinic');
    $location->slug('uams-clinic');
    $location->data([
        'title' => 'UAMS Clinic',
        'schema_medicalorganization_subtype' => $subtype,
        'expertise' => ['spec-endo'],
    ]);

    $node = buildRoutedLocationNode($location);

    expect($node)->toBeArray();

    // UAMS-Web/uams-statamic-archive#1444 routes the location to its per-subtype node, whose OWN declared @type
    // is the subtype and on which availableService is valid.
    $types = is_array($node['@type'] ?? null) ? $node['@type'] : [$node['@type'] ?? null];
    expect($types)->toContain($subtype);

    $available = $node['availableService'] ?? null;
    expect($available)->not->toBeNull();

    // Past the not-null gate `availableService` is the service node payload — either a
    // list of MedicalProcedure nodes or a single node array.
    /** @var array<int|string, mixed> $available */
    $nodes = array_is_list($available) ? $available : [$available];
    $serviceTypes = [];
    foreach ($nodes as $n) {
        $t = is_array($n) ? ($n['@type'] ?? null) : null;
        foreach (is_array($t) ? $t : [$t] as $one) {
            $serviceTypes[] = $one;
        }
    }

    expect($serviceTypes)->toContain('MedicalProcedure');

    // The per-subtype node inherits the base location identity (name) from the
    // shared tree parent, so it is not missing the location identity.
    expect($node)->toHaveKey('name');
})->with(['MedicalClinic', 'Hospital']);

it('narrows @type to Pharmacy and gates availableService off the non-service subtype node', function () {
    // UAMS-Web/uams-statamic-archive#1444 gates availableService off the non-supporting subtypes: a Pharmacy
    // location routes to its OWN lean node (@type Pharmacy) that carries no
    // availableService template. Even with implied services present, none emit —
    // the noted strict-domain over-emission on Pharmacy/Dentist/DiagnosticLab is
    // now closed.
    /** @var Statamic\Entries\Entry $service */
    $service = Entry::make();
    $service->collection('clinical_services');
    $service->blueprint('clinical_service');
    $service->locale('health');
    $service->id('svc-pharm');
    $service->slug('svc-pharm');
    $service->data(['title' => 'Immunization']);
    Collection::computed('clinical_locations', 'transitive_services', fn (): EntryCollection => EntryCollection::make([$service]));
    Blink::flush();
    Entry::partialMock()->shouldReceive('find')->with('svc-pharm')->andReturn($service);

    /** @var Statamic\Entries\Entry $location */
    $location = Entry::make();
    $location->collection('clinical_locations');
    $location->blueprint('location');
    $location->locale('health');
    $location->id('loc-pharmacy');
    $location->slug('uams-pharmacy');
    $location->data(['title' => 'UAMS Pharmacy', 'schema_medicalorganization_subtype' => 'Pharmacy', 'expertise' => ['spec-endo']]);

    $node = buildRoutedLocationNode($location);

    $types = is_array($node['@type'] ?? null) ? $node['@type'] : [$node['@type'] ?? null];
    expect($types)->toContain('Pharmacy');
    expect($node['availableService'] ?? null)->toBeIn([null, [], '']);
});

it('emits availableService valid for the routed node @type per SchemaPropertyValidator', function (string $subtype) {
    // AC: the routed per-subtype node passes SchemaPropertyValidator — availableService
    // is invalid on a bare MedicalOrganization/LocalBusiness, so it must be valid for
    // the node's narrowed @type (MedicalClinic/Hospital).
    /** @var Statamic\Entries\Entry $service */
    $service = Entry::make();
    $service->collection('clinical_services');
    $service->blueprint('clinical_service');
    $service->locale('health');
    $service->id('svc-valid');
    $service->slug('svc-valid');
    $service->data(['title' => 'Cardiac Rehabilitation']);
    Collection::computed('clinical_locations', 'transitive_services', fn (): EntryCollection => EntryCollection::make([$service]));
    Blink::flush();
    Entry::partialMock()->shouldReceive('find')->with('svc-valid')->andReturn($service);

    /** @var Statamic\Entries\Entry $location */
    $location = Entry::make();
    $location->collection('clinical_locations');
    $location->blueprint('location');
    $location->locale('health');
    $location->id('loc-valid');
    $location->slug('uams-valid');
    $location->data(['title' => 'UAMS Clinic', 'schema_medicalorganization_subtype' => $subtype, 'expertise' => ['spec-endo']]);

    $node = buildRoutedLocationNode($location);

    expect($node['availableService'] ?? null)->not->toBeNull();

    // availableService must be a valid property for the emitted narrowed @type.
    $types = is_array($node['@type'] ?? null) ? $node['@type'] : [$node['@type'] ?? null];
    $validForSome = false;
    foreach ($types as $type) {
        if (is_string($type) && in_array('availableService', SchemaPropertyValidator::validPropertiesForType($type), true)) {
            $validForSome = true;
        }
    }

    expect($validForSome)->toBeTrue();
})->with(['MedicalClinic', 'Hospital']);

it('emits no availableService when the routed MedicalClinic node has no implied services', function () {
    /** @var Statamic\Entries\Entry $location */
    $location = Entry::make();
    $location->collection('clinical_locations');
    $location->blueprint('location');
    $location->locale('health');
    $location->id('loc-empty');
    $location->slug('uams-empty');
    $location->data([
        'title' => 'Empty Clinic',
        'schema_medicalorganization_subtype' => 'MedicalClinic',
        'expertise' => [],
    ]);

    $node = buildRoutedLocationNode($location);

    // Pin the routed node identity: without it this negative assertion would pass
    // on a silent routing fallback to the base node (which never carries
    // availableService), never verifying the empty-services gate on the
    // MedicalClinic node that DOES carry the availableService template.
    expect($node)->toBeArray()
        ->and($node['@type'] ?? null)->toBe('MedicalClinic')
        ->and($node['availableService'] ?? null)->toBeIn([null, [], '']);
});
