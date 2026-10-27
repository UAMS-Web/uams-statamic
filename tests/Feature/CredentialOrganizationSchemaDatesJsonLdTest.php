<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaOutputService;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use PHPUnit\Framework\Assert;
use Statamic\Contracts\Globals\GlobalSet as GlobalSetContract;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\GlobalSet;
use Statamic\Globals\Variables;
use Tests\TestCase;

/**
 * Date properties on the credential and organization nodes of a person's JSON-LD
 * (UAMS-Web/uams-statamic-archive#3051).
 *
 * `format_schema_date` formats a date object since UAMS-Web/uams-statamic-archive#3040, so a `date` field that
 * reaches a schema template augmented now renders. The credential template's
 * `datePublished` and `expires` read the certification rows' `date` fields,
 * which arrive augmented; the organization template's `foundingDate` reads
 * `founding_date` through `get_global`, which returns the stored string. These
 * render `c-lowry-barnes`'s page graph from the content mirror, which carries
 * the UAMS organization node as well as the person's credentials.
 *
 * **JSON-LD caches:** each test runs on the production fragment regime, see
 * `useProductionJsonLdFragmentCacheRegime()`, so no earlier render can mask
 * this one.
 *
 * @command  php artisan test --compact tests/Feature/CredentialOrganizationSchemaDatesJsonLdTest.php
 */
uses(TestCase::class);

beforeEach(function (): void {
    useProductionJsonLdFragmentCacheRegime();
});

/**
 * Entry id of the `c-lowry-barnes` person, who stores one board certification
 * and one `md` education row.
 */
const CRED_ORG_DATES_PERSON_ID = '721e22af-79f0-4222-a9b6-51858016a855';

/**
 * Schema-object id of the credential template, which defines `datePublished`
 * and `expires`.
 */
const CRED_ORG_DATES_CREDENTIAL_TEMPLATE_ID = 'b2c9f4a1-7e8d-4b3c-9a6f-1d0e5f7a9b2c';

/**
 * Schema-object id of the certification template, a child of the credential
 * template that inherits its `datePublished`.
 */
const CRED_ORG_DATES_CERTIFICATION_TEMPLATE_ID = 'c3d0e5b2-8f9e-4c5d-ab1e-2f3a4b5c6d7e';

/**
 * Schema-object id of the degree template, a child of the credential template
 * that inherits its `datePublished`.
 */
const CRED_ORG_DATES_DEGREE_TEMPLATE_ID = 'c3d0e5b2-8f9e-4c5d-ab1e-2f3a4b5c6d7f';

/**
 * Schema-object id of the UAMS organization template, which defines `foundingDate`.
 */
const CRED_ORG_DATES_ORGANIZATION_TEMPLATE_ID = '58185f50-22a4-48e7-9575-b86a1fdffef9';

/**
 * Render a person's page JSON-LD and return every full node in it rendered from
 * the given schema template.
 *
 * @param  Entry  $person  The person whose profile page is rendered.
 * @param  string  $templateId  Schema-object id the node `@id` must contain.
 * @return list<array<mixed>> Full nodes rendered from that template.
 */
function credOrgDatesNodes(Entry $person, string $templateId): array
{
    // Bind the profile URL, with the person as the route's `data` parameter so
    // the service renders this instance, including any unsaved rows set on it
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

    // Walk the tree, collecting full nodes (more than an `@id`) from the template
    $nodes = [];
    $walk = static function (mixed $value) use (&$walk, &$nodes, $templateId): void {
        if (! is_array($value)) {
            return;
        }

        // Match the template's own nodes, by id or id plus an instance index, not
        // sub-nodes such as identifiers whose @id extends it with a name
        $atId = $value['@id'] ?? null;
        $ownNode = is_string($atId) && preg_match('/#'.preg_quote($templateId, '/').'(?:-\d+)?$/', $atId) === 1;
        if ($ownNode && count($value) > 1) {
            $nodes[] = $value;
        }

        foreach ($value as $child) {
            $walk($child);
        }
    };
    $walk($graph);

    return $nodes;
}

/**
 * Load the fixture person, failing clearly when the content mirror lacks it.
 */
function credOrgDatesPerson(): Entry
{
    return mustBe(Entry::class, EntryFacade::find(CRED_ORG_DATES_PERSON_ID), 'the c-lowry-barnes person');
}

it('emits a credential\'s stored issued and expiration dates as datePublished and expires', function (): void {
    $person = credOrgDatesPerson();

    // Store dates distinct from the fixture's, so the output can only come from them
    $person->set('certifications', [[
        'id' => 'credOrgDatesBoardRow',
        'activity' => 'american-board-of-orthopaedic-surgery-orthopaedic-surgery',
        'type' => 'board_certification',
        'enabled' => true,
        'activity_date_issued' => '2001-02-03',
        'activity_date_audit' => '2027-08-09',
        'activity_date_expires' => '2031-04-05',
    ]]);

    $nodes = credOrgDatesNodes($person, CRED_ORG_DATES_CERTIFICATION_TEMPLATE_ID);

    // The certification renders as a credential, with both dates from the row
    expect($nodes)->toHaveCount(1);
    expect($nodes[0]['@type'] ?? null)->toContain('EducationalOccupationalCredential');
    expect($nodes[0])
        ->toHaveKey('datePublished', '2001-02-03')
        ->toHaveKey('expires', '2031-04-05');
});

it('emits neither datePublished nor expires for a credential with no dates stored', function (): void {
    // The person's `md` credentials term carries no date fields at all
    $nodes = credOrgDatesNodes(credOrgDatesPerson(), CRED_ORG_DATES_CREDENTIAL_TEMPLATE_ID);

    expect($nodes)->not->toBeEmpty();

    foreach ($nodes as $node) {
        expect($node)
            ->not->toHaveKey('datePublished')
            ->not->toHaveKey('expires');
    }
});

it('emits the organization foundingDate from the stored global value', function (): void {
    // Read the stored value the template fetches through `get_global`
    $set = mustBe(GlobalSetContract::class, GlobalSet::findByHandle('uams_multisite_settings'), 'the uams_multisite_settings global set');
    $variables = mustBe(Variables::class, $set->in('default'), 'the default site\'s uams_multisite_settings values');
    $stored = $variables->get('founding_date');
    expect($stored)->toBeString()->not->toBeEmpty();

    $nodes = credOrgDatesNodes(credOrgDatesPerson(), CRED_ORG_DATES_ORGANIZATION_TEMPLATE_ID);

    expect($nodes)->not->toBeEmpty();

    foreach ($nodes as $node) {
        expect($node)->toHaveKey('foundingDate', $stored);
    }
});

it('formats a partial_date caller at the precision it stores', function (): void {
    $person = credOrgDatesPerson();

    // Give the education row a partial completion date with an unknown day
    $person->set('education', [[
        'id' => 'credOrgDatesEducationRow',
        'activity' => 'md',
        'organization' => 'university-of-arkansas-for-medical-sciences',
        'type' => 'post_secondary',
        'enabled' => true,
        'activity_end_date' => '1986-05-XX',
    ]]);

    $nodes = credOrgDatesNodes($person, CRED_ORG_DATES_DEGREE_TEMPLATE_ID);

    // The degree inherits datePublished from the credential template's partial_date value
    expect($nodes)->not->toBeEmpty();

    foreach ($nodes as $node) {
        expect($node)->toHaveKey('datePublished', '1986-05');
    }
});
