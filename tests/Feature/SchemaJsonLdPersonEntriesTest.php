<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaJsonLdOutputCache;
use App\Services\SchemaTemplates\SchemaOutputService;
use Illuminate\Http\Request;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Assert;
use Statamic\Facades\Asset;
use Tests\Support\SchemaFixtureStacheTestCase;

/**
 * Person schema JSON-LD output assertions over a hermetic, synthetic fixture graph.
 *
 * Rewrites the former real-entry `SchemaJsonLdRealPersonEntriesTest` (consolidated
 * in UAMS-Web/uams-statamic-archive#729 from `SchemaJsonLdPeopleLeslieTaylorTest` +
 * `SchemaJsonLdPeopleCLowryBarnesOccupationLocationTest`). Those asserted against
 * live `content/collections/people` entries and their ~250-entry `schema_objects`
 * parent chain, so they needed the `content/` submodule and pinned real UAMS staff
 * data. This host instead renders {@see SchemaFixtureStacheTestCase}'s committed
 * Star-Trek fixture graph, so it runs with `content/` absent (UAMS-Web/uams-statamic-archive#1313).
 *
 * The assertions are rewritten from specific rendered values to the **structural
 * invariants** the originals existed to protect — the cross-entry Antlers
 * scope-leakage regressions surface as graph-shape properties rather than snapshots:
 *
 *  1. Every canonical `@id` has at most one full node (no duplicated bodies).
 *  2. Every compact `{"@id":…}` reference resolves to a full node in the tree.
 *  3. Each co-rendered person's job-title `Occupation` gets a distinct `@id` — the
 *     replicator row index is 0 for each, so the allocation must disambiguate across
 *     people (the original "distinct `hasOccupation.@id`" media-contact guard).
 *  4. A credential on one person never leaks onto another co-rendered person (the
 *     original "no `M.A.` leaks to Kaitlin" guard).
 *  5. A job-title `Occupation` emits `occupationLocation` from the person's
 *     entry-level `work_location` when the per-role `role_work_location` is empty
 *     (the original C. Lowry Barnes fallback).
 *
 * The graph is composed for James Kirk's profile page. Kirk is the page person
 * (media contact, `M.A.` credential, `work_location`); Spock is the site's
 * `accountable_person` and NOT a media contact, so his full Person node renders with
 * its own occupation (giving the two distinct co-rendered occupations invariant 3
 * needs); McCoy is a second media contact.
 *
 * **JSON-LD caches:** `beforeEach` puts the test on the shared production fragment
 * regime — see {@see useProductionJsonLdFragmentCacheRegime()}. That switches off the
 * page output cache {@see SchemaOutputService::outputForCurrentContext()} wraps around
 * composition, leaves the cross-request fragment cache on as production runs it, and
 * rotates the {@see SchemaJsonLdOutputCache} bust generation per test so no earlier
 * test's cached payload or fragment can mask a fresh render. Do not replace that call
 * with a bare `json_ld_cache.enabled` override: that is the master switch, and using it
 * takes the fragment cache down too (UAMS-Web/uams-statamic-archive#1842).
 *
 * @command  php artisan test --compact tests/Feature/SchemaJsonLdPersonEntriesTest.php
 */
uses(SchemaFixtureStacheTestCase::class, MockeryPHPUnitIntegration::class);

beforeEach(function (): void {
    useProductionJsonLdFragmentCacheRegime();
});

/**
 * `schema_objects` entry emitting the job-title `Occupation` (fragment-suffixed `@id`).
 */
const SCHEMA_LD_JOB_TITLE_OCCUPATION_TEMPLATE_UUID = 'd4f58c4f-ee5c-4536-af1c-6e3dc3f2da4f';

/**
 * Canonical `@id` of the person page entity (James Kirk's profile).
 */
const SCHEMA_LD_KIRK_PERSON_URL = 'http://uams-statamic.test/people/james-kirk';

const SCHEMA_LD_SPOCK_PERSON_URL = 'http://uams-statamic.test/people/spock';

const SCHEMA_LD_MCCOY_PERSON_URL = 'http://uams-statamic.test/people/leonard-mccoy';

/**
 * Render the JSON-LD `@graph` for a fixture profile URL and return it decoded.
 *
 * @return array<string, mixed> The decoded top-level JSON-LD document (with `@graph`).
 */
function schemaLdRenderPersonGraph(string $url): array
{
    // Bind a request URL so the service resolves the entity via findByRequestUrl
    // without rendering the full front-end view.
    $request = Request::create($url, 'GET');
    app()->instance('request', $request);

    // Stub asset lookups so schema rendering does not require the Eloquent asset
    // metadata tables in the test environment.
    Asset::shouldReceive('find')->andReturn(null);

    // Render and extract the first JSON-LD script block.
    $scriptTag = app(SchemaOutputService::class)->outputForCurrentContext();

    $matched = preg_match(
        '/<script[^>]*type=[\'"]application\/ld\+json[\'"][^>]*>(.*?)<\/script>/is',
        $scriptTag,
        $matches
    );

    Assert::assertSame(1, $matched, 'Expected a JSON-LD script tag in the rendered output.');
    Assert::assertArrayHasKey(1, $matches);

    /** @var array<string, mixed> $decoded */
    $decoded = json_decode(trim($matches[1]), true, 512, JSON_THROW_ON_ERROR);

    return $decoded;
}

/**
 * Walk the decoded tree and tally, per `@id`, how often it appears as a full node
 * (any key besides `@id`) versus a compact `{"@id":…}` reference.
 *
 * @param  mixed  $value  Current branch.
 * @param  array<string, int>  $fullCounts  Full-node counts keyed by `@id`.
 * @param  array<string, int>  $compactCounts  Compact-reference counts keyed by `@id`.
 */
function schemaLdAccumulateNodeShapeCounts(mixed $value, array &$fullCounts, array &$compactCounts): void
{
    if (! is_array($value)) {
        return;
    }

    $atId = $value['@id'] ?? null;

    if (is_string($atId) && $atId !== '') {
        $hasOtherKey = false;

        foreach (array_keys($value) as $key) {
            if ($key !== '@id') {
                $hasOtherKey = true;

                break;
            }
        }

        if ($hasOtherKey) {
            $fullCounts[$atId] = ($fullCounts[$atId] ?? 0) + 1;
        } else {
            $compactCounts[$atId] = ($compactCounts[$atId] ?? 0) + 1;
        }
    }

    foreach ($value as $child) {
        schemaLdAccumulateNodeShapeCounts($child, $fullCounts, $compactCounts);
    }
}

/**
 * Walk the decoded tree and collect every job-title `Occupation` object (one whose
 * `@id` carries the job-title template uuid), keyed with its owning node `@id`.
 *
 * @param  mixed  $value  Current branch.
 * @param  list<array{owner: string, occupation: array<string, mixed>}>  $out  Collected occupations.
 */
function schemaLdCollectJobTitleOccupations(mixed $value, array &$out): void
{
    if (! is_array($value)) {
        return;
    }

    if (array_key_exists('hasOccupation', $value)) {
        $ownerAtId = is_string($value['@id'] ?? null) ? $value['@id'] : '';
        $hasOccupation = $value['hasOccupation'];
        $occupations = is_array($hasOccupation) && array_is_list($hasOccupation)
            ? $hasOccupation
            : [$hasOccupation];

        foreach ($occupations as $occupation) {
            if (! is_array($occupation)) {
                continue;
            }

            $occupationAtId = $occupation['@id'] ?? null;

            if (is_string($occupationAtId) && str_contains($occupationAtId, SCHEMA_LD_JOB_TITLE_OCCUPATION_TEMPLATE_UUID)) {
                /** @var array<string, mixed> $occupation */
                $out[] = ['owner' => $ownerAtId, 'occupation' => $occupation];
            }
        }
    }

    foreach ($value as $child) {
        schemaLdCollectJobTitleOccupations($child, $out);
    }
}

/**
 * Whether a `hasCredential` object is the synthetic `M.A.` (Master of Arts) term.
 * Asserts stable identifying fields; the full shape may gain properties over time.
 *
 * @param  array<string, mixed>  $payload  A single `hasCredential` object.
 */
function schemaLdIsMasterOfArtsCredential(array $payload): bool
{
    return ($payload['@type'] ?? null) === 'EducationalOccupationalCredential'
        && ($payload['name'] ?? null) === 'Master of Arts';
}

/**
 * Walk the decoded tree and collect every `hasCredential` payload keyed with its
 * owning node `@id` (flattening list-valued `hasCredential`).
 *
 * @param  mixed  $value  Current branch.
 * @param  list<array{owner: string, credential: array<string, mixed>}>  $out  Collected credentials.
 */
function schemaLdCollectCredentials(mixed $value, array &$out): void
{
    if (! is_array($value)) {
        return;
    }

    if (array_key_exists('hasCredential', $value)) {
        $ownerAtId = is_string($value['@id'] ?? null) ? $value['@id'] : '';
        $hasCredential = $value['hasCredential'];
        $credentials = is_array($hasCredential) && array_is_list($hasCredential)
            ? $hasCredential
            : [$hasCredential];

        foreach ($credentials as $credential) {
            if (is_array($credential)) {
                /** @var array<string, mixed> $credential */
                $out[] = ['owner' => $ownerAtId, 'credential' => $credential];
            }
        }
    }

    foreach ($value as $child) {
        schemaLdCollectCredentials($child, $out);
    }
}

/**
 * Whether a full node for the given `@id` exists anywhere in the decoded tree.
 *
 * @param  mixed  $value  Current branch.
 */
function schemaLdHasFullNode(mixed $value, string $atId): bool
{
    if (! is_array($value)) {
        return false;
    }

    if (($value['@id'] ?? null) === $atId) {
        foreach (array_keys($value) as $key) {
            if ($key !== '@id') {
                return true;
            }
        }
    }

    foreach ($value as $child) {
        if (schemaLdHasFullNode($child, $atId)) {
            return true;
        }
    }

    return false;
}

it('renders a hermetic person JSON-LD graph preserving the cross-entry invariants', function (): void {
    $graph = schemaLdRenderPersonGraph(SCHEMA_LD_KIRK_PERSON_URL);

    expect($graph)->toHaveKey('@graph');
    expect($graph['@graph'])->toBeArray();

    // Tally node shapes by @id across the whole tree.
    $fullCounts = [];
    $compactCounts = [];
    schemaLdAccumulateNodeShapeCounts($graph, $fullCounts, $compactCounts);

    // Invariant 1 — every canonical @id has at most one full node (no duplicated bodies).
    foreach ($fullCounts as $atId => $count) {
        expect($count)->toBeLessThanOrEqual(
            1,
            sprintf('Expected at most one full JSON-LD node for @id %s; found %d.', $atId, $count)
        );
    }

    // Invariant 2 — every compact reference resolves to a full node somewhere in the tree.
    foreach (array_keys($compactCounts) as $atId) {
        expect(schemaLdHasFullNode($graph, $atId))->toBeTrue(
            sprintf('Compact reference to @id %s has no full node in the graph.', $atId)
        );
    }

    // The three fixture people are co-rendered (invariant 3 needs multiple people present).
    foreach ([SCHEMA_LD_KIRK_PERSON_URL, SCHEMA_LD_SPOCK_PERSON_URL, SCHEMA_LD_MCCOY_PERSON_URL] as $personUrl) {
        expect(schemaLdHasFullNode($graph, $personUrl))->toBeTrue(
            sprintf('Expected a full Person node for %s.', $personUrl)
        );
    }

    // Invariant 3 — each co-rendered person's job-title Occupation gets a distinct @id.
    $occupations = [];
    schemaLdCollectJobTitleOccupations($graph, $occupations);

    expect(count($occupations))->toBeGreaterThanOrEqual(
        2,
        'Expected at least two co-rendered job-title Occupation nodes (Kirk + Spock).'
    );

    $occupationAtIds = array_map(
        static fn (array $row): string => is_string($row['occupation']['@id'] ?? null) ? $row['occupation']['@id'] : '',
        $occupations
    );

    expect(count(array_unique($occupationAtIds)))->toBe(
        count($occupationAtIds),
        'Each co-rendered person must have a distinct job-title Occupation @id. Got: '
        .json_encode($occupationAtIds, JSON_THROW_ON_ERROR)
    );

    $occupationOwners = array_unique(array_map(
        static fn (array $row): string => $row['owner'],
        $occupations
    ));
    expect(count($occupationOwners))->toBeGreaterThanOrEqual(
        2,
        'The distinct occupations must belong to at least two different owner nodes.'
    );

    // Invariant 5 — each job-title Occupation emits occupationLocation from work_location.
    // Kirk (and Spock) set entry-level `work_location` and omit per-role
    // `role_work_location`, so the occupation template falls back to the person's
    // general locations.
    foreach ($occupations as $row) {
        expect($row['occupation'])->toHaveKey(
            'occupationLocation',
            message: sprintf(
                'Job-title Occupation %s (owner %s) must emit occupationLocation from the entry work_location fallback.',
                is_string($row['occupation']['@id'] ?? null) ? $row['occupation']['@id'] : '?',
                $row['owner']
            )
        );
    }

    // Invariant 4 — the Master of Arts credential appears exactly once in the whole
    // graph. A credential bleeding from Kirk's Antlers scope into a co-rendered person
    // (the original "no `M.A.` leaks to Kaitlin" scope-leak regression) would
    // materialize a second `hasCredential`, so the site-wide count is the primary guard.
    $credentials = [];
    schemaLdCollectCredentials($graph, $credentials);

    $masterOfArts = array_values(array_filter(
        $credentials,
        static fn (array $row): bool => schemaLdIsMasterOfArtsCredential($row['credential'])
    ));

    expect(count($masterOfArts))->toBe(
        1,
        'Expected exactly one Master of Arts credential site-wide; found '.count($masterOfArts).'.'
    );

    // The single M.A. renders on the page person's rich schema node — the node that
    // also carries a job-title `Occupation` (Kirk's page-person node renders under the
    // person-template fragment `@id`, not the `/people/james-kirk` entry url) — and
    // never on Spock's or McCoy's `Person` node.
    $masterOfArtsOwner = $masterOfArts[0]['owner'];
    $occupationOwners = array_map(
        static fn (array $row): string => $row['owner'],
        $occupations
    );

    expect(in_array($masterOfArtsOwner, $occupationOwners, true))->toBeTrue(
        'The M.A. must render on a person node that also carries a job-title Occupation '
        .'(the page person), not a bare or leaked node. Owner: '.$masterOfArtsOwner
    );

    expect(in_array($masterOfArtsOwner, [SCHEMA_LD_SPOCK_PERSON_URL, SCHEMA_LD_MCCOY_PERSON_URL], true))->toBeFalse(
        'The M.A. must not render on Spock\'s or McCoy\'s Person node. Owner: '.$masterOfArtsOwner
    );
});
