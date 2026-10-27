<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaOutputService;
use App\Services\SchemaTemplates\SchemaTemplatesCascadeService;
use Statamic\Facades\Entry;

/**
 * Shared fixtures and node builders for the `clinical_locations` JSON-LD suites (UAMS-Web/uams-statamic-archive#967).
 *
 * Extracted when the former single `SchemaJsonLdLocationTest` was split into five
 * focused suites (UAMS-Web/uams-statamic-archive#1797). That class ran 1085s of a 1306s parallel band, and paratest
 * distributes by class, so one indivisible class bounded the whole band while eleven
 * workers idled.
 *
 * These are top-level functions shared by all five suites, so they live here rather
 * than being duplicated — two Pest files declaring the same top-level function is a
 * fatal redeclare, not a shadow.
 *
 * Loaded via `require_once` from `tests/Pest.php`, matching `CpSmoke.php` and the
 * other shared helper files.
 */

/** The location `MedicalOrganization` content shell (collection `main_content_entry`), the no-subtype fallback. */
const SCHEMA_LD_LOCATION_MEDICAL_ORGANIZATION_ENTRY_ID = '0b9f1bee-6c0b-4ba5-9ddc-850794f08c8d';

/** Per-subtype location schema nodes (UAMS-Web/uams-statamic-archive#1444) — routed by `schema_medicalorganization_subtype`. */
const SCHEMA_LD_LOCATION_DIAGNOSTIC_LAB_ENTRY_ID = 'cce8dd6e-6813-4b08-9215-c4eded19335e';

/** The location `MedicalWebPage` shell (collection `webpage_entry`). */
const SCHEMA_LD_LOCATION_MEDICAL_WEBPAGE_ENTRY_ID = '3524c376-d801-4097-b7b9-e64db0849512';

/** The shared UAMS `MedicalOrganization` subject node (site-default `subject_entry`). */
const UAMS_ORGANIZATION_SUBJECT_ENTRY_ID = '58185f50-22a4-48e7-9575-b86a1fdffef9';

/**
 * Build an in-memory `clinical_locations` fixture entry.
 *
 * @param  array<string, mixed>  $data
 */
function locationFixture(array $data): Statamic\Entries\Entry
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->collection('clinical_locations');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->locale('health');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->id($data['id'] ?? 'fixture-location');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->slug($data['slug'] ?? 'fixture-location');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->data($data);

    return $entry;
}

/**
 * The schema node id the location's `main_content` slot resolves to — the
 * per-subtype node (UAMS-Web/uams-statamic-archive#1444) when a `schema_medicalorganization_subtype` is set,
 * else the shared `MedicalOrganization` fallback. This mirrors what the
 * production render path resolves, so the tests build the node a real location
 * page would emit.
 */
function routedLocationNodeId(Statamic\Contracts\Entries\Entry $location): string
{
    $resolved = app(SchemaTemplatesCascadeService::class)->resolve($location)['main_content_entry'];

    return \is_string($resolved) && $resolved !== '' ? $resolved : SCHEMA_LD_LOCATION_MEDICAL_ORGANIZATION_ENTRY_ID;
}

/**
 * Build the location's routed schema node for an in-memory location entry
 * carrying the given data. A location with no subtype resolves the shared
 * `MedicalOrganization` node exactly as before; a subtyped location resolves its
 * own per-subtype node (UAMS-Web/uams-statamic-archive#1444).
 *
 * @param  array<string, mixed>  $data
 * @return array<string, mixed>|null
 */
function buildLocationNode(array $data): ?array
{
    $location = locationFixture($data);

    return app(SchemaOutputService::class)->buildStandaloneSchemaObjectNode(
        routedLocationNodeId($location),
        $location
    );
}

/**
 * Build the location `MedicalWebPage` node for an in-memory location entry.
 *
 * @param  array<string, mixed>  $data
 * @return array<string, mixed>|null
 */
function buildLocationWebpageNode(array $data): ?array
{
    /** @var Statamic\Entries\Entry $location */
    $location = Entry::make();
    /** @var Statamic\Entries\Entry $location */
    $location = $location->collection('clinical_locations');
    /** @var Statamic\Entries\Entry $location */
    $location = $location->locale('health');
    /** @var Statamic\Entries\Entry $location */
    $location = $location->id($data['id'] ?? 'fixture-location');
    /** @var Statamic\Entries\Entry $location */
    $location = $location->slug($data['slug'] ?? 'fixture-location');
    /** @var Statamic\Entries\Entry $location */
    $location = $location->data($data);

    return app(SchemaOutputService::class)->buildStandaloneSchemaObjectNode(
        SCHEMA_LD_LOCATION_MEDICAL_WEBPAGE_ENTRY_ID,
        $location
    );
}

/**
 * Render the full JSON-LD `@graph` for an in-memory location through the
 * production compose path (`composeJsonLdOutputScriptTagForResolvedSlots` —
 * what `{{ schema_ld_json }}` drives), not the single-node standalone path the
 * other cases use. This is the only path that builds the shared `subject_entry`
 * org node under the location context, so it is the one that can catch a
 * subtype bleed onto that shared node.
 *
 * @param  array<string, mixed>  $data
 * @param  array<string, string|null>  $slots  Optional slot-id overrides (subject/website/webpage/main_content)
 * @return array<int, array<string, mixed>>
 */
function composeLocationGraph(array $data, array $slots = []): array
{
    $location = locationFixture($data);

    $service = app(SchemaOutputService::class);
    $compose = new ReflectionMethod($service, 'composeJsonLdOutputScriptTagForResolvedSlots');
    $compose->setAccessible(true);

    /** @var string $scriptTag */
    $scriptTag = $compose->invoke(
        $service,
        $location,
        \array_key_exists('subject', $slots) ? $slots['subject'] : UAMS_ORGANIZATION_SUBJECT_ENTRY_ID,
        $slots['website'] ?? null,
        $slots['webpage'] ?? null,
        \array_key_exists('main_content', $slots) ? $slots['main_content'] : routedLocationNodeId($location),
    );

    expect($scriptTag)->not->toBe('');

    $json = trim((string) preg_replace('#^<script[^>]*>|</script>$#', '', trim($scriptTag)));

    // The stripped script tag must carry a valid JSON-LD payload, not a malformed one.
    expect($json)->toBeJson();

    /** @var array<string, mixed> $decoded */
    $decoded = json_decode($json, true);

    /** @var array<int, array<string, mixed>> $graph */
    $graph = $decoded['@graph'] ?? [$decoded];

    return $graph;
}

/**
 * Find the `@graph` node whose canonical `@id` carries the given entry id.
 *
 * @param  array<int, array<string, mixed>>  $graph
 * @return array<string, mixed>|null
 */
function graphNodeForEntryId(array $graph, string $entryId): ?array
{
    foreach ($graph as $node) {
        $atId = $node['@id'] ?? null;
        if (\is_string($atId) && str_contains($atId, $entryId)) {
            return $node;
        }
    }

    return null;
}
