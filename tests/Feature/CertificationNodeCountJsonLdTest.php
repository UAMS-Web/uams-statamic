<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaOutputService;
use App\Services\SchemaTemplates\SchemaTemplateInstanceWatermarkBag;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use PHPUnit\Framework\Assert;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Tests\TestCase;

/**
 * A person's JSON-LD emits one full Certification node per stored certification
 * row (UAMS-Web/uams-statamic-archive#3049).
 *
 * The person template emits each row under both `hasCertification` and
 * `hasCredential`, and the person renders twice in the graph (as the Person
 * node and as the WebSite's `accountablePerson`), so one row is emitted four
 * times. Each repeat must reuse the row's `@id`, so the first emit is the full
 * node and the rest compact to `{"@id":…}` references to it.
 *
 * @command  php artisan test --compact tests/Feature/CertificationNodeCountJsonLdTest.php
 */
uses(TestCase::class);

beforeEach(function (): void {
    useProductionJsonLdFragmentCacheRegime();
});

/**
 * Entry id of the `c-lowry-barnes` person, who stores one board certification.
 */
const CERT_COUNT_PERSON_ID = '721e22af-79f0-4222-a9b6-51858016a855';

/**
 * Schema-object template id of the Certification node the person template emits.
 */
const CERT_COUNT_TEMPLATE_ID = 'c3d0e5b2-8f9e-4c5d-ab1e-2f3a4b5c6d7e';

/**
 * Render a person's page JSON-LD, returning its full Certification nodes and
 * every `@id` its `hasCertification` and `hasCredential` values point at.
 *
 * @param  Entry  $person  The person whose profile page is rendered.
 * @return array{nodes: array<string, array<mixed>>, refs: list<string>} Full
 *                                                                       nodes by `@id`, and the `@id` of every credential value.
 */
function certCountRender(Entry $person): array
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

    // Walk the tree, collecting full Certification nodes and credential values
    $nodes = [];
    $refs = [];
    $walk = static function (mixed $value) use (&$walk, &$nodes, &$refs): void {
        if (! is_array($value)) {
            return;
        }

        $type = $value['@type'] ?? null;
        $types = is_array($type) ? $type : [$type];

        if (in_array('Certification', $types, true) && is_string($value['@id'] ?? null)) {
            $nodes[$value['@id']] = $value;
        }

        // Record the @id of every value under a credential property
        foreach (['hasCertification', 'hasCredential'] as $property) {
            $values = $value[$property] ?? [];
            $values = is_array($values) && array_is_list($values) ? $values : [$values];

            foreach ($values as $item) {
                if (is_array($item) && is_string($item['@id'] ?? null)) {
                    $refs[] = $item['@id'];
                }
            }
        }

        foreach ($value as $child) {
            $walk($child);
        }
    };
    $walk($graph);

    return ['nodes' => $nodes, 'refs' => $refs];
}

/**
 * Load the fixture person, failing clearly when the content mirror lacks it.
 */
function certCountPerson(): Entry
{
    return mustBe(Entry::class, EntryFacade::find(CERT_COUNT_PERSON_ID), 'the c-lowry-barnes person');
}

it('emits one Certification node for a person holding one row', function (): void {
    $render = certCountRender(certCountPerson());

    // One stored row is one full node, carrying the row's fields and dates
    expect($render['nodes'])->toHaveCount(1);
    $node = array_values($render['nodes'])[0];
    expect($node['name'] ?? null)->toBe('Orthopaedic Surgery');
    expect($node)
        ->toHaveKey('validFrom', '1994-07-15')
        ->toHaveKey('expires', '2034-12-31')
        ->toHaveKey('auditDate', '2025-01-01');

    // Every repeat emit points at that node rather than at a missing one
    $certificationRefs = array_values(array_filter(
        $render['refs'],
        static fn (string $atId): bool => str_contains($atId, CERT_COUNT_TEMPLATE_ID),
    ));
    expect(count($certificationRefs))->toBeGreaterThan(1);
    expect(array_unique($certificationRefs))->toBe([array_key_first($render['nodes'])]);
});

it('emits one Certification node per row for a person holding two rows', function (): void {
    $person = certCountPerson();

    // Store the board certification beside a distinct assessment-based certificate
    $person->set('certifications', [
        [
            'id' => 'certCountBoardRow',
            'activity' => 'american-board-of-orthopaedic-surgery-orthopaedic-surgery',
            'type' => 'board_certification',
            'enabled' => true,
            'activity_date_issued' => '1994-07-15',
            'activity_date_audit' => '2025-01-01',
            'activity_date_expires' => '2034-12-31',
        ],
        [
            'id' => 'certCountCertificateRow',
            'activity' => 'american-society-for-metabolic-and-bariatric-surgery-fundamentals-for-the-metabolic-and-bariatric-surgery-patient-assessment-based-certificate',
            'type' => 'other_certification',
            'enabled' => true,
            'activity_date_issued' => '2021-03-04',
            'activity_date_audit' => '2029-06-30',
            'activity_date_expires' => '2030-01-31',
        ],
    ]);

    $render = certCountRender($person);

    // Two distinct rows stay two nodes, each keeping its own name and dates
    expect($render['nodes'])->toHaveCount(2);
    $byName = [];
    foreach ($render['nodes'] as $node) {
        $name = $node['name'] ?? null;
        Assert::assertIsString($name);
        $byName[$name] = $node;
    }
    expect($byName)->toHaveKey('Orthopaedic Surgery');
    expect($byName['Orthopaedic Surgery'])
        ->toHaveKey('expires', '2034-12-31')
        ->toHaveKey('auditDate', '2025-01-01');

    $certificate = array_values(array_filter(
        $byName,
        static fn (string $name): bool => str_contains($name, 'Assessment-Based Certificate'),
        ARRAY_FILTER_USE_KEY,
    ));
    expect($certificate)->toHaveCount(1);
    expect($certificate[0])
        ->toHaveKey('validFrom', '2021-03-04')
        ->not->toHaveKey('expires')
        ->not->toHaveKey('auditDate');

    // Every credential reference resolves to one of the two emitted nodes
    $certificationRefs = array_values(array_unique(array_filter(
        $render['refs'],
        static fn (string $atId): bool => str_contains($atId, CERT_COUNT_TEMPLATE_ID),
    )));
    sort($certificationRefs);
    $nodeIds = array_keys($render['nodes']);
    sort($nodeIds);
    expect($certificationRefs)->toBe($nodeIds);
});

/**
 * Call the service's protected instance-index allocator for one request.
 *
 * @param  SchemaTemplateInstanceWatermarkBag  $bag  Allocator shared by the calls.
 * @param  int  $requested  Requested `schema_template_instance_index`.
 * @param  array<mixed>  $rowScope  Row overlay, without the index key.
 * @param  bool  $commit  Whether to commit (a full node) or peek (a preview).
 * @return int|null Effective instance index.
 */
function certCountAllocate(
    SchemaTemplateInstanceWatermarkBag $bag,
    int $requested,
    array $rowScope,
    bool $commit = true,
): ?int {
    $context = $rowScope + [SchemaOutputService::ANTLERS_SCHEMA_TEMPLATE_INSTANCE_INDEX => $requested];
    $result = (new ReflectionMethod(SchemaOutputService::class, 'resolveEffectiveTemplateInstanceIndexForWatermark'))
        ->invoke(app(SchemaOutputService::class), CERT_COUNT_TEMPLATE_ID, certCountPerson(), [], $context, $bag, $commit);
    Assert::assertTrue($result === null || is_int($result));

    return $result;
}

it('reuses a row\'s instance index and bumps a distinct row', function (): void {
    $bag = new SchemaTemplateInstanceWatermarkBag;

    // A row's first emit takes its requested index; a repeat reuses it
    expect(certCountAllocate($bag, 0, ['id' => 'rowA']))->toBe(0);
    expect(certCountAllocate($bag, 0, ['id' => 'rowA']))->toBe(0);
    expect(certCountAllocate($bag, 0, ['id' => 'rowA'], false))->toBe(0);

    // A different row at the same requested index still takes a new suffix
    expect(certCountAllocate($bag, 0, ['id' => 'rowB']))->toBe(1);
    expect(certCountAllocate($bag, 0, ['id' => 'rowB']))->toBe(1);
});

it('does not remember a row index on a peek', function (): void {
    $bag = new SchemaTemplateInstanceWatermarkBag;

    // A peek previews the next index without recording it for the row
    expect(certCountAllocate($bag, 0, ['id' => 'rowA'], false))->toBe(0);
    expect(certCountAllocate($bag, 0, ['id' => 'rowB']))->toBe(0);
    expect(certCountAllocate($bag, 0, ['id' => 'rowA']))->toBe(1);
});

it('gives a fresh suffix to each emit of a row without its own id', function (array $rowScope): void {
    $bag = new SchemaTemplateInstanceWatermarkBag;

    // Without an id of the row's own, repeats are indistinguishable from new rows
    expect(certCountAllocate($bag, 0, $rowScope))->toBe(0);
    expect(certCountAllocate($bag, 0, $rowScope))->toBe(1);
})->with([
    'no id' => [[]],
    'empty id' => [['id' => '']],
    'non-string id' => [['id' => 7]],
    'id cascaded from the person' => [['id' => CERT_COUNT_PERSON_ID]],
]);
