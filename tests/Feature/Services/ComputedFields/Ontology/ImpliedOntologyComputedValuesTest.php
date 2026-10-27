<?php

declare(strict_types=1);

use App\Services\ComputedFields\Ontology\ImpliedOntologyComputedValues;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Entries\EntryCollection;
use Statamic\Facades\Blink;
use Statamic\Facades\Entry as EntryFacade;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/*
|--------------------------------------------------------------------------
| ImpliedOntologyComputedValues (UAMS-Web/uams-statamic-archive#1063, D9 transitive inference)
|--------------------------------------------------------------------------
|
| Verifies the resolver builds the right published overlap query (proving the
| FLAT match — it passes exactly the entry's own `expertise` ids, never expanded
| to descendants), unions the explicit special-interest override additively
| (deduped by id, reading the typed field matching the collection being resolved),
| short-circuits an entry with no specialties/override to an empty set, and
| Blink-memoizes per entry per request. The overlap semantics themselves are
| Statamic's `whereJsonOverlaps`, covered by the scope tests.
|
| @covers \App\Services\ComputedFields\Ontology\ImpliedOntologyComputedValues
|
| @command  php artisan test --compact tests/Feature/Services/ComputedFields/Ontology/ImpliedOntologyComputedValuesTest.php
*/

beforeEach(function () {
    Blink::flush();
});

/**
 * A Provider/Location entry mock exposing its own specialty + override id arrays.
 *
 * @param  array<int, string>  $expertise
 * @param  array<int, string>  $special
 */
function impliedSubjectEntry(?string $id, array $expertise, array $special = []): Entry
{
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('id')->andReturn($id);
    $entry->shouldReceive('get')->with('expertise', [])->andReturn($expertise);

    // The override is authored as two typed fields; split the combined fixture by id
    // prefix (svc* → services, else → conditions) so each field returns its own ids.
    $services = array_values(array_filter($special, fn ($v): bool => str_starts_with((string) $v, 'svc')));
    $conditions = array_values(array_filter($special, fn ($v): bool => ! str_starts_with((string) $v, 'svc')));
    $entry->shouldReceive('get')->with('special_interest_clinical_conditions', [])->andReturn($conditions);
    $entry->shouldReceive('get')->with('special_interest_clinical_services', [])->andReturn($services);

    return $entry;
}

/** A resolved related entry mock exposing only its id (for dedupe/order assertions). */
function impliedRelatedEntry(string $id): Entry
{
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('id')->andReturn($id);

    return $entry;
}

/**
 * The ids of the resolved implied set, in order.
 *
 * @return array<array-key, mixed>
 */
function impliedIds(EntryCollection $set): array
{
    return $set->map(function ($entry): mixed {
        /** @var Entry $entry */
        return $entry->id();
    })->all();
}

it('returns an empty set and issues no query when the entry has no specialties or override', function () {
    // No anchor specialties and no override → short-circuit, never touching the
    // query (the opposite of the overlap scope's unfiltered whole-collection return).
    EntryFacade::shouldReceive('query')->never();

    $entry = impliedSubjectEntry('subject-empty', [], []);
    $service = app(ImpliedOntologyComputedValues::class);

    expect(impliedIds($service->resolveImpliedServices($entry)))->toBe([])
        ->and(impliedIds($service->resolveImpliedConditions($entry)))->toBe([]);
});

it('builds a published expertise-overlap query against clinical_services (flat — the entry own ids only)', function () {
    $results = EntryCollection::make([impliedRelatedEntry('svc-a'), impliedRelatedEntry('svc-b')]);

    $query = Mockery::mock();
    $query->shouldReceive('where')->once()->with('collection', 'clinical_services')->andReturnSelf();
    // Exactly the entry's own two specialty ids — never expanded to descendants (flat, Fork H).
    // The target column is the C/T handle `clinical_expertise` (not the subject handle `expertise`).
    $query->shouldReceive('whereJsonOverlaps')->once()->with('clinical_expertise', ['spec-1', 'spec-2'])->andReturnSelf();
    $query->shouldReceive('whereStatus')->once()->with('published')->andReturnSelf();
    $query->shouldReceive('get')->once()->andReturn($results);
    EntryFacade::shouldReceive('query')->once()->andReturn($query);

    $entry = impliedSubjectEntry('subject-transitive', ['spec-1', 'spec-2'], []);
    $implied = app(ImpliedOntologyComputedValues::class)->resolveImpliedServices($entry);

    expect(impliedIds($implied))->toBe(['svc-a', 'svc-b']);
});

it('targets clinical_conditions for the implied-conditions set', function () {
    $query = Mockery::mock();
    $query->shouldReceive('where')->once()->with('collection', 'clinical_conditions')->andReturnSelf();
    $query->shouldReceive('whereJsonOverlaps')->with('clinical_expertise', ['spec-1'])->andReturnSelf();
    $query->shouldReceive('whereStatus')->with('published')->andReturnSelf();
    $query->shouldReceive('get')->andReturn(EntryCollection::make([impliedRelatedEntry('cond-a')]));
    EntryFacade::shouldReceive('query')->once()->andReturn($query);

    $entry = impliedSubjectEntry('subject-conditions', ['spec-1'], []);
    $implied = app(ImpliedOntologyComputedValues::class)->resolveImpliedConditions($entry);

    expect(impliedIds($implied))->toBe(['cond-a']);
});

it('unions the explicit special-interest override additively, deduped by id (no double-count)', function () {
    // Transitive default and the explicit override share `svc-b`; it must appear once.
    $transitive = EntryCollection::make([impliedRelatedEntry('svc-a'), impliedRelatedEntry('svc-b')]);
    $override = EntryCollection::make([impliedRelatedEntry('svc-b'), impliedRelatedEntry('svc-c')]);

    $transitiveQuery = Mockery::mock();
    $transitiveQuery->shouldReceive('where')->with('collection', 'clinical_services')->andReturnSelf();
    $transitiveQuery->shouldReceive('whereJsonOverlaps')->with('clinical_expertise', ['spec-1'])->andReturnSelf();
    $transitiveQuery->shouldReceive('whereStatus')->with('published')->andReturnSelf();
    $transitiveQuery->shouldReceive('get')->andReturn($transitive);

    $overrideQuery = Mockery::mock();
    $overrideQuery->shouldReceive('where')->once()->with('collection', 'clinical_services')->andReturnSelf();
    // The override is resolved by id (the explicitly-authored special-interest picks), published only.
    $overrideQuery->shouldReceive('whereIn')->once()->with('id', ['svc-b', 'svc-c'])->andReturnSelf();
    $overrideQuery->shouldReceive('whereStatus')->once()->with('published')->andReturnSelf();
    $overrideQuery->shouldReceive('get')->andReturn($override);

    EntryFacade::shouldReceive('query')->twice()->andReturn($transitiveQuery, $overrideQuery);

    $entry = impliedSubjectEntry('subject-override', ['spec-1'], ['svc-b', 'svc-c']);
    $implied = app(ImpliedOntologyComputedValues::class)->resolveImpliedServices($entry);

    // svc-a (transitive), svc-b (in both — once), svc-c (override): additive, deduped.
    expect(impliedIds($implied))->toBe(['svc-a', 'svc-b', 'svc-c']);
});

it('unions the explicit CONDITIONS override into the implied-conditions set (reads the conditions typed field)', function () {
    // The mirror of the services union test, on the CONDITIONS side: proves
    // explicitOverride reads special_interest_clinical_conditions (not the services field)
    // for the clinical_conditions collection. Without this, a field-selection regression on
    // the conditions branch would ship green (only the services read is otherwise pinned).
    $transitive = EntryCollection::make([impliedRelatedEntry('cond-a')]);
    $override = EntryCollection::make([impliedRelatedEntry('cond-c')]);

    $transitiveQuery = Mockery::mock();
    $transitiveQuery->shouldReceive('where')->with('collection', 'clinical_conditions')->andReturnSelf();
    $transitiveQuery->shouldReceive('whereJsonOverlaps')->with('clinical_expertise', ['spec-1'])->andReturnSelf();
    $transitiveQuery->shouldReceive('whereStatus')->with('published')->andReturnSelf();
    $transitiveQuery->shouldReceive('get')->andReturn($transitive);

    $overrideQuery = Mockery::mock();
    $overrideQuery->shouldReceive('where')->once()->with('collection', 'clinical_conditions')->andReturnSelf();
    // The override id is resolved from the conditions typed field — NOT the (empty) services field.
    $overrideQuery->shouldReceive('whereIn')->once()->with('id', ['cond-c'])->andReturnSelf();
    $overrideQuery->shouldReceive('whereStatus')->once()->with('published')->andReturnSelf();
    $overrideQuery->shouldReceive('get')->andReturn($override);

    EntryFacade::shouldReceive('query')->twice()->andReturn($transitiveQuery, $overrideQuery);

    // cond-c (cond* prefix → the conditions typed field via the fixture split).
    $entry = impliedSubjectEntry('subject-cond-override', ['spec-1'], ['cond-c']);
    $implied = app(ImpliedOntologyComputedValues::class)->resolveImpliedConditions($entry);

    expect(impliedIds($implied))->toBe(['cond-a', 'cond-c']);
});

it('memoizes the implied set per entry per request (one query for repeated reads)', function () {
    $query = Mockery::mock();
    $query->shouldReceive('where')->andReturnSelf();
    $query->shouldReceive('whereJsonOverlaps')->andReturnSelf();
    $query->shouldReceive('whereStatus')->andReturnSelf();
    // A single get() across two reads asserts the Blink memoization.
    $query->shouldReceive('get')->once()->andReturn(EntryCollection::make([impliedRelatedEntry('svc-a')]));
    EntryFacade::shouldReceive('query')->once()->andReturn($query);

    $entry = impliedSubjectEntry('subject-memo', ['spec-1'], []);
    $service = app(ImpliedOntologyComputedValues::class);

    $first = $service->resolveImpliedServices($entry);
    $second = $service->resolveImpliedServices($entry);

    // Same set both reads; the query's ->once() expectation proves it ran only once.
    expect(impliedIds($first))->toBe(['svc-a'])
        ->and(impliedIds($second))->toBe(['svc-a']);
});

it('recomputes (does not memoize) when the entry id is not a string (unsaved entry)', function () {
    $query = Mockery::mock();
    $query->shouldReceive('where')->andReturnSelf();
    $query->shouldReceive('whereJsonOverlaps')->andReturnSelf();
    $query->shouldReceive('whereStatus')->andReturnSelf();
    // No stable Blink key → each read queries afresh (the ->twice() expectation proves it).
    $query->shouldReceive('get')->twice()->andReturn(EntryCollection::make([impliedRelatedEntry('svc-a')]));
    EntryFacade::shouldReceive('query')->twice()->andReturn($query, $query);

    $entry = impliedSubjectEntry(null, ['spec-1'], []);
    $service = app(ImpliedOntologyComputedValues::class);

    expect(impliedIds($service->resolveImpliedServices($entry)))->toBe(['svc-a'])
        ->and(impliedIds($service->resolveImpliedServices($entry)))->toBe(['svc-a']);
});

// ---------------------------------------------------------------------------
// transitive_services / transitive_conditions — overlap minus override (UAMS-Web/uams-statamic-archive#1067 slice 5)
// ---------------------------------------------------------------------------
//
// The plain-transitive legs for JSON-LD: the specialty overlap with the explicit
// special-interest override REMOVED, so a C/T that is both overlap-implied and a
// hand-picked special interest is emitted only in its Role-wrapped node, never twice.

it('removes the explicit special-interest override from the transitive-only services set', function () {
    // overlap {svc-a, svc-b}; override {svc-b, svc-c} → transitive-only {svc-a}
    // (svc-b dropped as it is also an override; svc-c never in the overlap).
    $overlap = EntryCollection::make([impliedRelatedEntry('svc-a'), impliedRelatedEntry('svc-b')]);
    $override = EntryCollection::make([impliedRelatedEntry('svc-b'), impliedRelatedEntry('svc-c')]);

    // The override is resolved first (to index its ids), then the overlap.
    $overrideQuery = Mockery::mock();
    $overrideQuery->shouldReceive('where')->once()->with('collection', 'clinical_services')->andReturnSelf();
    $overrideQuery->shouldReceive('whereIn')->once()->with('id', ['svc-b', 'svc-c'])->andReturnSelf();
    $overrideQuery->shouldReceive('whereStatus')->once()->with('published')->andReturnSelf();
    $overrideQuery->shouldReceive('get')->andReturn($override);

    $overlapQuery = Mockery::mock();
    $overlapQuery->shouldReceive('where')->once()->with('collection', 'clinical_services')->andReturnSelf();
    $overlapQuery->shouldReceive('whereJsonOverlaps')->once()->with('clinical_expertise', ['spec-1'])->andReturnSelf();
    $overlapQuery->shouldReceive('whereStatus')->once()->with('published')->andReturnSelf();
    $overlapQuery->shouldReceive('get')->andReturn($overlap);

    EntryFacade::shouldReceive('query')->twice()->andReturn($overrideQuery, $overlapQuery);

    $entry = impliedSubjectEntry('subject-transitive-excl', ['spec-1'], ['svc-b', 'svc-c']);
    $set = app(ImpliedOntologyComputedValues::class)->resolveTransitiveServices($entry);

    expect(impliedIds($set))->toBe(['svc-a']);
});

it('returns the full overlap for the transitive-only conditions set when no override is authored', function () {
    // No override authored → explicitOverride short-circuits (no query); transitive-only
    // equals the raw overlap.
    $overlapQuery = Mockery::mock();
    $overlapQuery->shouldReceive('where')->once()->with('collection', 'clinical_conditions')->andReturnSelf();
    $overlapQuery->shouldReceive('whereJsonOverlaps')->once()->with('clinical_expertise', ['spec-1'])->andReturnSelf();
    $overlapQuery->shouldReceive('whereStatus')->once()->with('published')->andReturnSelf();
    $overlapQuery->shouldReceive('get')->andReturn(EntryCollection::make([impliedRelatedEntry('cond-a'), impliedRelatedEntry('cond-b')]));

    EntryFacade::shouldReceive('query')->once()->andReturn($overlapQuery);

    $entry = impliedSubjectEntry('subject-transitive-no-override', ['spec-1'], []);
    $set = app(ImpliedOntologyComputedValues::class)->resolveTransitiveConditions($entry);

    expect(impliedIds($set))->toBe(['cond-a', 'cond-b']);
});

it('reports has_special_interest true when either override field is authored, false otherwise', function () {
    // No query is issued — the flag reads the raw override fields directly.
    EntryFacade::shouldReceive('query')->never();

    $withService = impliedSubjectEntry('subj-si-svc', ['spec-1'], ['svc-a']);
    $withCondition = impliedSubjectEntry('subj-si-cond', ['spec-1'], ['cond-a']);
    $without = impliedSubjectEntry('subj-si-none', ['spec-1'], []);

    $service = app(ImpliedOntologyComputedValues::class);

    expect($service->resolveHasSpecialInterest($withService))->toBeTrue()
        ->and($service->resolveHasSpecialInterest($withCondition))->toBeTrue()
        ->and($service->resolveHasSpecialInterest($without))->toBeFalse();
});

it('reports has_special_interest_services true only for a service override, not a condition-only one', function () {
    // The location Role gate — a condition-only special interest must read false here so no
    // bare, targetless location Role emits (UAMS-Web/uams-statamic-archive#1067 slice 5 review).
    EntryFacade::shouldReceive('query')->never();

    $withService = impliedSubjectEntry('subj-svc-only', ['spec-1'], ['svc-a']);
    $conditionOnly = impliedSubjectEntry('subj-cond-only', ['spec-1'], ['cond-a']);
    $without = impliedSubjectEntry('subj-none', ['spec-1'], []);

    $service = app(ImpliedOntologyComputedValues::class);

    expect($service->resolveHasSpecialInterestServices($withService))->toBeTrue()
        ->and($service->resolveHasSpecialInterestServices($conditionOnly))->toBeFalse()
        ->and($service->resolveHasSpecialInterestServices($without))->toBeFalse();
});

// ---------------------------------------------------------------------------
// Handle contract (UAMS-Web/uams-statamic-archive#1063 adversarial-review blocker)
// ---------------------------------------------------------------------------
//
// The overlap bridges an ASYMMETRY: the Provider/Location subjects store their
// specialty edge under handle `expertise`, but the Condition/Service targets
// store the same `clinical_expertise` entry ids under handle `clinical_expertise`.
// The review caught the resolver querying the target with the SUBJECT handle, so
// the implied set was unconditionally empty. The mock tests above cannot catch
// that (they assert the string the resolver passes, not that it matches the
// stored key); these tie the resolver's handle constants to the real blueprints.

/** The handle of the single field referencing the shared `uamsweb_common_fad.expertise` fieldset. */
function impliedSpecialtyEdgeHandle(string $blueprintPath): ?string
{
    /** @var array{tabs?: array<array-key, array{sections?: array<array-key, array{fields?: array<array-key, array{field?: mixed, handle?: string}>}>}>} $parsed */
    $parsed = Yaml::parseFile(base_path($blueprintPath));
    $tabs = $parsed['tabs'] ?? [];

    foreach ($tabs as $tab) {
        foreach ($tab['sections'] ?? [] as $section) {
            foreach ($section['fields'] ?? [] as $field) {
                if (($field['field'] ?? null) === 'uamsweb_common_fad.expertise') {
                    return $field['handle'] ?? null;
                }
            }
        }
    }

    return null;
}

/** A private constant on the resolver, read reflectively. */
function impliedResolverConstant(string $name): mixed
{
    return (new ReflectionClass(ImpliedOntologyComputedValues::class))->getConstant($name);
}

it('reads the subject specialty edge under the handle the Provider/Location blueprints store it', function (string $path) {
    expect(impliedSpecialtyEdgeHandle($path))->toBe(impliedResolverConstant('SUBJECT_SPECIALTY_FIELD'));
})->with([
    'people' => ['resources/blueprints/collections/people/person.yaml'],
    'clinical_locations' => ['resources/blueprints/collections/clinical_locations/location.yaml'],
]);

it('queries the target specialty edge under the handle the Condition/Service blueprints store it', function (string $path) {
    expect(impliedSpecialtyEdgeHandle($path))->toBe(impliedResolverConstant('TARGET_SPECIALTY_FIELD'));
})->with([
    'clinical_services' => ['resources/blueprints/collections/clinical_services/clinical_service.yaml'],
    'clinical_conditions' => ['resources/blueprints/collections/clinical_conditions/clinical_condition.yaml'],
]);

it('bridges a genuine asymmetry: the subject and target specialty handles differ', function () {
    // Equal handles would make the split pointless and re-admit the original bug
    // (one shared constant used for both the subject read and the target query).
    expect(impliedResolverConstant('SUBJECT_SPECIALTY_FIELD'))
        ->not->toBe(impliedResolverConstant('TARGET_SPECIALTY_FIELD'));
});

// ---------------------------------------------------------------------------
// candidateOverrideIds — the special-interest scope candidate set (UAMS-Web/uams-statamic-archive#1064 B)
// ---------------------------------------------------------------------------
//
// The in-scope candidates for the special-interest pickers: the transitive overlap
// across BOTH C/T collections, WITHOUT the override union (a selection is never
// validated against itself). The ValidateSpecialInterestsScope save guard reads
// this to reject an out-of-scope selection on any save path.

it('returns the union of the specialty-overlap ids across both C/T collections as scope candidates (publish-independent)', function () {
    // Reuses the same transitive-overlap primitive: the FLAT `expertise` ids matched
    // against the target `clinical_expertise` column, per collection — but WITHOUT the
    // published filter (publishStatus is not part of association; a draft in-specialty
    // C/T is still a valid candidate). Asserted by the ABSENCE of any whereStatus call.
    $servicesQuery = Mockery::mock();
    $servicesQuery->shouldReceive('where')->once()->with('collection', 'clinical_services')->andReturnSelf();
    $servicesQuery->shouldReceive('whereJsonOverlaps')->once()->with('clinical_expertise', ['spec-1'])->andReturnSelf();
    $servicesQuery->shouldReceive('whereStatus')->never();
    $servicesQuery->shouldReceive('get')->once()->andReturn(EntryCollection::make([impliedRelatedEntry('svc-a'), impliedRelatedEntry('svc-b')]));

    $conditionsQuery = Mockery::mock();
    $conditionsQuery->shouldReceive('where')->once()->with('collection', 'clinical_conditions')->andReturnSelf();
    $conditionsQuery->shouldReceive('whereJsonOverlaps')->once()->with('clinical_expertise', ['spec-1'])->andReturnSelf();
    $conditionsQuery->shouldReceive('whereStatus')->never();
    $conditionsQuery->shouldReceive('get')->once()->andReturn(EntryCollection::make([impliedRelatedEntry('cond-a')]));

    EntryFacade::shouldReceive('query')->twice()->andReturn($servicesQuery, $conditionsQuery);

    $entry = impliedSubjectEntry('subject-candidates', ['spec-1'], []);

    // Services first, then conditions — a plain list of ids (no override union).
    expect(app(ImpliedOntologyComputedValues::class)->candidateOverrideIds($entry))
        ->toBe(['svc-a', 'svc-b', 'cond-a']);
});

it('yields an empty candidate set (and issues no query) when the entry has no specialties', function () {
    // An entity with no specialties can hold no special interests: every selection
    // is out of scope. The overlap short-circuits before touching the query.
    EntryFacade::shouldReceive('query')->never();

    $entry = impliedSubjectEntry('subject-no-specialty', [], []);

    expect(app(ImpliedOntologyComputedValues::class)->candidateOverrideIds($entry))->toBe([]);
});
