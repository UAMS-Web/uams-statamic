<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaOutputService;
use Illuminate\Http\Request;
use Statamic\Facades\Entry;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;

/**
 * Schema JSON-LD coverage for clinical_resources syndication (UAMS-Web/uams-statamic-archive#1099).
 *
 * A syndicated clinical resource of any type emits its `syndication_url` as
 * schema.org `sameAs`; an NCI-syndicated article (`syndication_source == nci`)
 * additionally credits the National Cancer Institute as `sourceOrganization`
 * (a ResearchOrganization node). Both render through the shared "entry as
 * content" schema object via SchemaOutputService::buildStandaloneSchemaObjectNode()
 * against an in-memory clinical_resources entry (option A — nothing is saved).
 * The schema_object templates ship in the `content/` submodule, so this suite
 * is content-dependent and skips gracefully when content is absent.
 *
 * @command  php artisan test --compact tests/Feature/SchemaJsonLdClinicalResourceSyndicationTest.php
 */
uses(TestCase::class);

/** The shared clinical-resource "entry as content" schema object (collection `main_content_entry`). */
const SCHEMA_LD_CLINICAL_RESOURCE_CONTENT_ENTRY_ID = '0f132206-3836-47dd-afe0-df744cd5baea';

/** The shared UAMS organization node the inherited `sourceOrganization` references. */
const SCHEMA_LD_UAMS_ORGANIZATION_ENTRY_ID = '58185f50-22a4-48e7-9575-b86a1fdffef9';

beforeEach(function (): void {
    if (! Entry::find(SCHEMA_LD_CLINICAL_RESOURCE_CONTENT_ENTRY_ID)) {
        ContentSubmodule::reportMissing('schema_objects');
    }

    useProductionJsonLdFragmentCacheRegime();

    // Bind a request so @id resolution has a host to anchor on.
    app()->instance('request', Request::create('http://uams-statamic.test/', 'GET'));
});

/**
 * Build the shared clinical-resource content node for an in-memory entry
 * carrying the given data.
 *
 * @param  array<string, mixed>  $data
 * @return array<string, mixed>|null
 */
function buildClinicalResourceContentNode(array $data): ?array
{
    /** @var Statamic\Entries\Entry $entry */
    $entry = Entry::make();
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->collection('clinical_resources');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->locale('health');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->id($data['id'] ?? 'fixture-clinical-resource');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->slug($data['slug'] ?? 'fixture-clinical-resource');
    /** @var Statamic\Entries\Entry $entry */
    $entry = $entry->data($data);

    return app(SchemaOutputService::class)->buildStandaloneSchemaObjectNode(
        SCHEMA_LD_CLINICAL_RESOURCE_CONTENT_ENTRY_ID,
        $entry
    );
}

it('emits the syndication URL as sameAs for a syndicated clinical resource', function (): void {
    /** @var array<string, mixed> $node */
    $node = buildClinicalResourceContentNode([
        'title' => 'Managing Chemotherapy Side Effects',
        'syndication_query' => true,
        'syndication_url' => 'https://www.cancer.gov/about-cancer/treatment/side-effects',
    ]);

    expect($node)->not->toBeNull();
    expect($node['sameAs'])->toBe('https://www.cancer.gov/about-cancer/treatment/side-effects');
});

it('omits sameAs when the clinical resource is not syndicated', function (): void {
    $node = buildClinicalResourceContentNode([
        'title' => 'An Original UAMS Clinical Resource',
    ]);

    expect($node)->not->toBeNull();
    expect($node)->not->toHaveKey('sameAs');
});

it('omits sameAs when a stale syndication URL lingers but the toggle is off', function (): void {
    // Statamic does not clear conditionally-hidden fields, so a de-syndicated
    // resource can keep a stale syndication_url. sameAs must honor the toggle
    // (consistent with the canonical partial), not the lingering URL.
    $node = buildClinicalResourceContentNode([
        'title' => 'A De-syndicated Clinical Resource',
        'syndication_query' => false,
        'syndication_url' => 'https://www.cancer.gov/stale',
    ]);

    expect($node)->not->toBeNull();
    expect($node)->not->toHaveKey('sameAs');
});

it('credits the National Cancer Institute as sourceOrganization for an NCI-syndicated article', function (): void {
    /** @var array<string, mixed> $node */
    $node = buildClinicalResourceContentNode([
        'title' => 'Breast Cancer Treatment (PDQ)',
        'syndication_query' => true,
        'syndication_source' => 'nci',
        'syndication_url' => 'https://www.cancer.gov/types/breast/patient/breast-treatment-pdq',
    ]);

    /** @var array<string, mixed> $org */
    $org = $node['sourceOrganization'];

    expect(json_encode($org['@type'] ?? null))->toContain('ResearchOrganization');
    expect($org['name'])->toBe('National Cancer Institute');
    expect($org['url'])->toBe('https://www.cancer.gov/');
    expect($org['sameAs'])->toBe([
        'https://id.loc.gov/authorities/names/n80126779',
        'https://www.wikidata.org/wiki/Q638893',
    ]);
});

it('keeps the inherited UAMS source organization for a non-NCI syndicated resource', function (): void {
    /** @var array<string, mixed> $node */
    $node = buildClinicalResourceContentNode([
        'title' => 'A Resource Syndicated From Elsewhere',
        'syndication_query' => true,
        'syndication_source' => 'other',
        'syndication_url' => 'https://example.org/some-article',
    ]);

    // Falls to the inherited UAMS org reference (an @id ref), never NCI.
    /** @var array<string, mixed> $org */
    $org = $node['sourceOrganization'];
    expect($org['@id'] ?? '')->toContain(SCHEMA_LD_UAMS_ORGANIZATION_ENTRY_ID);
    expect($org['name'] ?? null)->not->toBe('National Cancer Institute');
});

it('emits the inherited UAMS source organization for a non-syndicated resource', function (): void {
    // The else-branch preserves the pre-#1099 inherited behavior: a plain,
    // non-syndicated clinical resource still credits the UAMS source
    // organization (an @id reference), not nothing.
    $node = buildClinicalResourceContentNode([
        'title' => 'An Original UAMS Clinical Resource',
    ]);

    /** @var array<string, mixed> $org */
    $org = $node['sourceOrganization'] ?? null;
    expect($org)->not->toBeNull();
    expect($org['@id'] ?? '')->toContain(SCHEMA_LD_UAMS_ORGANIZATION_ENTRY_ID);
    expect($org['name'] ?? null)->not->toBe('National Cancer Institute');
});
