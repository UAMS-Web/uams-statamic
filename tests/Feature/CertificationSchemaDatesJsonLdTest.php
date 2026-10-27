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
 * Certification dates in a person's JSON-LD, rendered end to end (UAMS-Web/uams-statamic-archive#3040).
 *
 * The certification rows' `activity_date_*` fields are `date` fields, so inside
 * the person template's `{{ certifications }}` loop they reach the certification
 * schema template augmented to date objects. `format_schema_date` once returned
 * null for anything but a string, so every certification dropped `expires`,
 * `auditDate` and `validFrom`. These render `c-lowry-barnes`'s page graph from
 * the content mirror and read the Certification nodes it emits.
 *
 * **JSON-LD caches:** each test runs on the production fragment regime, see
 * `useProductionJsonLdFragmentCacheRegime()`, so no earlier render can mask
 * this one.
 *
 * @command  php artisan test --compact tests/Feature/CertificationSchemaDatesJsonLdTest.php
 */
uses(TestCase::class);

beforeEach(function (): void {
    useProductionJsonLdFragmentCacheRegime();
});

/**
 * Entry id of the `c-lowry-barnes` person, whose one stored certification row
 * is a board certification carrying all three dates.
 */
const CERT_DATES_PERSON_ID = '721e22af-79f0-4222-a9b6-51858016a855';

/**
 * Render a person's page JSON-LD and return every Certification node in it,
 * keyed by `@id`.
 *
 * @param  Entry  $person  The person whose profile page is rendered.
 * @return array<string, array<mixed>> Certification nodes by `@id`.
 */
function certDatesCertificationNodes(Entry $person): array
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

    // Walk the tree and collect every full node typed as a Certification
    $nodes = [];
    $walk = static function (mixed $value) use (&$walk, &$nodes): void {
        if (! is_array($value)) {
            return;
        }

        $type = $value['@type'] ?? null;
        $types = is_array($type) ? $type : [$type];

        if (in_array('Certification', $types, true) && is_string($value['@id'] ?? null)) {
            $nodes[$value['@id']] = $value;
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
function certDatesPerson(): Entry
{
    return mustBe(Entry::class, EntryFacade::find(CERT_DATES_PERSON_ID), 'the c-lowry-barnes person');
}

it('emits a board certification\'s stored dates', function (): void {
    $nodes = certDatesCertificationNodes(certDatesPerson());

    // The same certification renders under several properties, so check each
    expect($nodes)->not->toBeEmpty();

    foreach ($nodes as $node) {
        expect($node['name'] ?? null)->toBe('Orthopaedic Surgery');
        expect($node)
            ->toHaveKey('auditDate', '2025-01-01')
            ->toHaveKey('expires', '2034-12-31')
            ->toHaveKey('validFrom', '1994-07-15');
    }
});

it('emits neither audit nor expiration date for an assessment-based certificate', function (): void {
    $person = certDatesPerson();

    // Replace the stored rows with one certificate carrying all three dates
    $person->set('certifications', [[
        'id' => 'certDatesCertificateRow',
        'activity' => 'american-society-for-metabolic-and-bariatric-surgery-fundamentals-for-the-metabolic-and-bariatric-surgery-patient-assessment-based-certificate',
        'type' => 'other_certification',
        'enabled' => true,
        'activity_date_issued' => '2021-03-04',
        'activity_date_audit' => '2029-06-30',
        'activity_date_expires' => '2030-01-31',
    ]]);

    $nodes = certDatesCertificationNodes($person);

    // The certificate still renders, and keeps only its issued date
    expect($nodes)->not->toBeEmpty();

    foreach ($nodes as $node) {
        expect($node['name'] ?? null)->toContain('Assessment-Based Certificate');
        expect($node)
            ->not->toHaveKey('auditDate')
            ->not->toHaveKey('expires')
            ->toHaveKey('validFrom', '2021-03-04');
    }
});
