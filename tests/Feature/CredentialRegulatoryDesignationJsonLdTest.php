<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaOutputService;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use PHPUnit\Framework\Assert;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Tests\TestCase;

/**
 * The regulatory-designation answer in a credential's JSON-LD (UAMS-Web/uams-statamic#343).
 *
 * The credential schema template reads `clinical_statutory_credential_query`, which the
 * credentials blueprint now asks as the regulatory-designation question, and adds the
 * Wikidata item for "statutory credential" to `additionalType` only when it is true.
 * These render `c-lowry-barnes`'s page graph from the content mirror with one education
 * row at a time: `md`, a regulatory designation, and `ms`, a clinical credential that is
 * not one. Both are degrees, so both render through the same templates. The person's
 * own credentials render `md` nodes too, so each test picks out its credential's nodes
 * by the CTDL type they carry.
 *
 * **JSON-LD caches:** each test runs on the production fragment regime, see
 * `useProductionJsonLdFragmentCacheRegime()`, so no earlier render can mask this one.
 *
 * @command  php artisan test --compact tests/Feature/CredentialRegulatoryDesignationJsonLdTest.php
 */
uses(TestCase::class);

beforeEach(function (): void {
    useProductionJsonLdFragmentCacheRegime();
});

/**
 * Entry id of the `c-lowry-barnes` person.
 */
const CRED_REGULATORY_PERSON_ID = '721e22af-79f0-4222-a9b6-51858016a855';

/**
 * Schema-object id of the credential template, which defines `additionalType`.
 */
const CRED_REGULATORY_CREDENTIAL_TEMPLATE_ID = 'b2c9f4a1-7e8d-4b3c-9a6f-1d0e5f7a9b2c';

/**
 * Schema-object id of the degree template, a child of the credential template.
 */
const CRED_REGULATORY_DEGREE_TEMPLATE_ID = 'c3d0e5b2-8f9e-4c5d-ab1e-2f3a4b5c6d7f';

/**
 * The Wikidata item for "statutory credential" that a regulatory designation emits.
 */
const CRED_REGULATORY_WIKIDATA = 'https://www.wikidata.org/wiki/Q139581896';

/**
 * Render the person's page with one education row for the given credential, and return
 * the `additionalType` values of every full node from the credential or degree template.
 *
 * @param  string  $credential  The `credentials` term slug the row names.
 * @return list<list<string>> One list of `additionalType` values per rendered node.
 */
function credRegulatoryAdditionalTypes(string $credential): array
{
    $person = mustBe(Entry::class, EntryFacade::find(CRED_REGULATORY_PERSON_ID), 'the c-lowry-barnes person');

    // One row, so every credential node in the graph comes from this credential
    $person->set('education', [[
        'id' => 'credRegulatoryEducationRow',
        'activity' => $credential,
        'organization' => 'university-of-arkansas-for-medical-sciences',
        'type' => 'post_secondary',
        'enabled' => true,
    ]]);
    $person->set('certifications', []);

    // Bind the profile URL, with the person as the route's `data` parameter
    $url = $person->absoluteUrl();
    Assert::assertIsString($url);
    $request = Request::create($url, 'GET');
    $route = (new Route('GET', '{slug}', []))->bind($request);
    $route->setParameter('data', $person);
    $request->setRouteResolver(static fn (): Route => $route);
    app()->instance('request', $request);

    // Render and decode the first JSON-LD script block
    $scriptTag = app(SchemaOutputService::class)->outputForCurrentContext();
    $matched = preg_match('/<script[^>]*application\/ld\+json[^>]*>(.*?)<\/script>/is', $scriptTag, $matches);
    Assert::assertSame(1, $matched, 'Expected a JSON-LD script tag in the rendered output.');

    /** @var array<string, mixed> $graph */
    $graph = json_decode(trim($matches[1]), true, 512, JSON_THROW_ON_ERROR);

    // Collect the additionalType of each full node from either template
    $pattern = '/#(?:'.preg_quote(CRED_REGULATORY_CREDENTIAL_TEMPLATE_ID, '/').'|'.preg_quote(CRED_REGULATORY_DEGREE_TEMPLATE_ID, '/').')(?:-\d+)?$/';
    $found = [];
    $walk = static function (mixed $value) use (&$walk, &$found, $pattern): void {
        if (! is_array($value)) {
            return;
        }

        $atId = $value['@id'] ?? null;
        if (is_string($atId) && preg_match($pattern, $atId) === 1 && count($value) > 1) {
            $types = $value['additionalType'] ?? [];
            $found[] = array_values(array_filter(is_array($types) ? $types : [$types], is_string(...)));
        }

        foreach ($value as $child) {
            $walk($child);
        }
    };
    $walk($graph);

    return $found;
}

/**
 * The `additionalType` lists of the rendered credential nodes that carry the given CTDL
 * type, which identifies the education row's credential among the person's others.
 *
 * @param  string  $credential  The `credentials` term slug the row names.
 * @param  string  $ctdlUri  The CTDL term URI that credential renders in `additionalType`.
 * @return list<list<string>> One list of `additionalType` values per matching node.
 */
function credRegulatoryNodesOfType(string $credential, string $ctdlUri): array
{
    return array_values(array_filter(
        credRegulatoryAdditionalTypes($credential),
        static fn (array $types): bool => in_array($ctdlUri, $types, true),
    ));
}

it('marks a regulatory designation as a statutory credential', function (): void {
    $nodes = credRegulatoryNodesOfType('md', 'https://purl.org/ctdl/terms/ProfessionalDoctorate');

    expect($nodes)->not->toBeEmpty();

    foreach ($nodes as $types) {
        expect($types)->toContain(CRED_REGULATORY_WIKIDATA);
    }
});

it('does not mark a clinical credential that is not a regulatory designation', function (): void {
    // Picked out by its CTDL type, so a node from the person's other credentials
    // cannot satisfy or fail this, and an empty list cannot pass it
    $nodes = credRegulatoryNodesOfType('ms', 'https://purl.org/ctdl/terms/MasterOfScienceDegree');

    expect($nodes)->not->toBeEmpty();

    foreach ($nodes as $types) {
        expect($types)->not->toContain(CRED_REGULATORY_WIKIDATA);
    }
});
