<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaOutputService;
use Illuminate\Http\Request;
use Statamic\Facades\Entry;
use Tests\TestCase;

/**
 * Guards the invariant that made the Antlers parse-template memo undeletable-as-written
 * and, once measured, deletable outright (UAMS-Web/uams-statamic-archive#1800).
 *
 * That memo keyed `Parse::template()` results partly on a context-entity signature. The
 * signature is what made every key unique, so the memo hit 3 times in 22 261 calls
 * (0.013%) and was removed. The tempting "fix" — relaxing the key to template + property
 * so it finally hits — is a **correctness bug**, not an optimization: the same template
 * rendered against a different entity must produce that entity's values.
 *
 * This test renders one shared template against two distinct entities **within a single
 * request**, which is the exact scope a `RequestAttributeMemo` bag lives in. Under a
 * template-keyed memo the second render would return the first entity's output and the
 * name assertions would fail. It therefore fails loudly if such a memo is reintroduced.
 *
 * Deliberately does not reuse the `SchemaJsonLdLocation*` helpers: those are top-level
 * Pest functions, and UAMS-Web/uams-statamic-archive#1797 relocates them, so redeclaring or importing either shape
 * would break on one side of that merge.
 *
 * @command  php artisan test --compact tests/Feature/SchemaAntlersParseIsolationTest.php
 */
uses(TestCase::class);

/** The shared `MedicalOrganization` content shell every subtype-less location resolves. */
const SCHEMA_PARSE_ISOLATION_MEDICAL_ORGANIZATION_NODE = '0b9f1bee-6c0b-4ba5-9ddc-850794f08c8d';

beforeEach(function (): void {
    // A cached JSON-LD payload would mask a memo bug behind a different cache — and
    // here that has to include the cross-request fragment cache, because the shared
    // org node this renders twice is exactly the fragment-cacheable shape. So this
    // suite deliberately keeps the master switch off and does NOT adopt
    // useProductionJsonLdFragmentCacheRegime() the way the SchemaJsonLd* suites do
    // (UAMS-Web/uams-statamic-archive#1842): a fragment hit on the second render would satisfy the guard without
    // proving the memo is gone. Anything measured from this suite is therefore a
    // fragments-off regime, which production does not run.
    config()->set('schema_templates.json_ld_cache.enabled', false);
    config()->set('schema_templates.json_ld_cache.ttl_seconds', 0);

    // One request for the whole test: a request-scoped memo bag survives across both
    // renders below, which is precisely the contamination window being guarded.
    app()->instance('request', Request::create('http://uams-statamic.test/', 'GET'));
});

/**
 * Build an in-memory `clinical_locations` entry. Never saved, so no content is written.
 */
function schemaParseIsolationLocation(string $id, string $title): Statamic\Entries\Entry
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->collection('clinical_locations');
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

it('renders one shared template against two entities without cross-contamination', function (): void {
    $service = app(SchemaOutputService::class);

    $alpha = schemaParseIsolationLocation('parse-isolation-alpha', 'Alpha Diagnostics Center');
    $beta = schemaParseIsolationLocation('parse-isolation-beta', 'Beta Diagnostics Center');

    // Same template entry, same request, different context entities.
    /** @var array<string, mixed>|null $alphaNode */
    $alphaNode = $service->buildStandaloneSchemaObjectNode(
        SCHEMA_PARSE_ISOLATION_MEDICAL_ORGANIZATION_NODE,
        $alpha
    );
    /** @var array<string, mixed>|null $betaNode */
    $betaNode = $service->buildStandaloneSchemaObjectNode(
        SCHEMA_PARSE_ISOLATION_MEDICAL_ORGANIZATION_NODE,
        $beta
    );

    // A null node means the build did no work — the UAMS-Web/uams-statamic-archive#1797 measurement trap. Assert first
    // so a silently-empty render can never masquerade as a pass.
    expect($alphaNode)->not->toBeNull();
    expect($betaNode)->not->toBeNull();

    /** @var array<string, mixed> $alphaNode */
    /** @var array<string, mixed> $betaNode */

    // The load-bearing assertions: each node carries its OWN entity's name. Under a memo
    // keyed on template + property alone, the second render returns the first's output
    // and this is where it fails.
    expect($alphaNode['name'])->toBe('Alpha Diagnostics Center');
    expect($betaNode['name'])->toBe('Beta Diagnostics Center');

    // And they are genuinely distinct nodes, not the same payload twice.
    expect($betaNode['name'])->not->toBe($alphaNode['name']);
});

it('renders the same entity twice identically, so removing the memo changed nothing', function (): void {
    $service = app(SchemaOutputService::class);
    $location = schemaParseIsolationLocation('parse-isolation-repeat', 'Repeat Diagnostics Center');

    $first = $service->buildStandaloneSchemaObjectNode(
        SCHEMA_PARSE_ISOLATION_MEDICAL_ORGANIZATION_NODE,
        $location
    );
    $second = $service->buildStandaloneSchemaObjectNode(
        SCHEMA_PARSE_ISOLATION_MEDICAL_ORGANIZATION_NODE,
        $location
    );

    expect($first)->not->toBeNull();
    expect($second)->not->toBeNull();

    // Rendering is a pure function of (template, entity): the uncached second render must
    // equal the first. This is the property that made deleting the memo safe.
    expect($second)->toBe($first);
});
