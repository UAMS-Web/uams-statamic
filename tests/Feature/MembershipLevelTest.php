<?php

declare(strict_types=1);

use App\Modifiers\MembershipHeading;
use App\Services\SchemaTemplates\SchemaOutputService;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use PHPUnit\Framework\Assert;
use Statamic\Entries\Entry;
use Statamic\Facades\Antlers;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Fields\Field;
use Statamic\Fields\Value;
use Tests\TestCase;

uses(TestCase::class);

/**
 * The level a person holds in a professional organization (UAMS-Web/uams-statamic#306):
 * each `member_of` row names an organization and a level. A Member row keeps the
 * display and the plain Organization node it had before, and a higher level reads
 * "Fellow of the {organization}" and is a Role around that Organization in JSON-LD.
 *
 * **JSON-LD caches:** each test runs on the production fragment regime, see
 * `useProductionJsonLdFragmentCacheRegime()`, so no earlier render can mask this one.
 *
 * @command  php artisan test --compact tests/Feature/MembershipLevelTest.php
 */
beforeEach(function (): void {
    useProductionJsonLdFragmentCacheRegime();
});

/**
 * Entry id of the `c-lowry-barnes` person, the one entry that stores memberships.
 */
const MEMBERSHIP_LEVEL_PERSON_ID = '721e22af-79f0-4222-a9b6-51858016a855';

/**
 * Schema-object id of the template that wraps a level above Member in a Role.
 */
const MEMBERSHIP_LEVEL_ROLE_TEMPLATE_ID = 'a7d3f1c8-52e6-4b0f-9c14-6e8b2d70f3a1';

/**
 * Organization slug the fixture rows use.
 */
const MEMBERSHIP_LEVEL_ORGANIZATION = 'american-association-of-hip-and-knee-surgeons';

/**
 * Display name of that organization.
 */
const MEMBERSHIP_LEVEL_ORGANIZATION_NAME = 'American Association of Hip and Knee Surgeons';

/**
 * Load the fixture person.
 */
function membershipLevelPerson(): Entry
{
    return mustBe(Entry::class, EntryFacade::find(MEMBERSHIP_LEVEL_PERSON_ID), 'the c-lowry-barnes person');
}

/**
 * The people blueprint's `member_of` grid field.
 */
function membershipLevelField(): Field
{
    return mustBe(
        Field::class,
        mustBe(Statamic\Fields\Blueprint::class, Blueprint::find('collections.people.person'), 'the person blueprint')->field('member_of'),
        'the member_of field'
    );
}

/**
 * Render the memberships list as the profiles call it.
 *
 * @param  list<array<string, mixed>>  $rows
 */
function membershipLevelRender(array $rows): string
{
    return (string) Antlers::parse(
        '{{ partial:people/components/professional_background_list heading="Professional Memberships" :activities_list="member_of" variant="member_of" }}',
        ['member_of' => new Value($rows, 'member_of', membershipLevelField()->fieldtype())],
        true
    );
}

/**
 * Render the person's page JSON-LD and return its decoded graph, with the given
 * rows stored on the person.
 *
 * @param  list<array<string, mixed>>  $rows
 * @return array<mixed>
 */
function membershipLevelGraph(array $rows): array
{
    $person = membershipLevelPerson();
    $person->set('member_of', $rows);

    // Bind the profile URL, with the person as the route's `data` parameter so the
    // service renders this instance and its unsaved rows
    $url = $person->absoluteUrl();
    Assert::assertIsString($url);
    $request = Request::create($url, 'GET');
    $route = (new Route('GET', '{slug}', []))->bind($request);
    $route->setParameter('data', $person);
    $request->setRouteResolver(static fn (): Route => $route);
    app()->instance('request', $request);

    $scriptTag = app(SchemaOutputService::class)->outputForCurrentContext();
    $matched = preg_match('/<script[^>]*application\/ld\+json[^>]*>(.*?)<\/script>/is', $scriptTag, $matches);
    Assert::assertSame(1, $matched, 'Expected a JSON-LD script tag in the rendered output.');

    return mustBeArray(json_decode(trim($matches[1]), true, 512, JSON_THROW_ON_ERROR), 'the JSON-LD graph');
}

/**
 * Collect every array in a graph that has a given key.
 *
 * @param  array<mixed>  $graph
 * @return list<array<mixed>>
 */
function membershipLevelNodesWith(array $graph, string $key): array
{
    $nodes = [];
    $walk = static function (mixed $value) use (&$walk, &$nodes, $key): void {
        if (! is_array($value)) {
            return;
        }

        if (array_key_exists($key, $value)) {
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
 * The full nodes in a graph rendered from the membership Role template. A person's
 * other roles, such as a residency, carry `roleName` too, so `@id` picks these out.
 *
 * @param  array<mixed>  $graph
 * @return list<array<mixed>>
 */
function membershipLevelRoles(array $graph): array
{
    return array_values(array_filter(
        membershipLevelNodesWith($graph, 'roleName'),
        static fn (array $node): bool => is_string($node['@id'] ?? null) && str_contains($node['@id'], MEMBERSHIP_LEVEL_ROLE_TEMPLATE_ID),
    ));
}

/**
 * Read a `memberOf` value as a list of nodes, since one membership renders as a
 * single object and several as an array.
 *
 * @return list<array<mixed>>
 */
function membershipLevelMemberOf(mixed $memberOf): array
{
    $memberOf = mustBeArray($memberOf, 'the memberOf value');

    if (! array_is_list($memberOf)) {
        return [$memberOf];
    }

    $nodes = [];

    foreach ($memberOf as $node) {
        $nodes[] = mustBeArray($node, 'a memberOf node');
    }

    return $nodes;
}

it('builds the heading from the organization name and the level', function (mixed $value, array $params, string $expected): void {
    expect((new MembershipHeading)->index($value, array_values($params), []))->toBe($expected);
})->with([
    'a member reads as the organization alone' => ['American Orthopaedic Association', ['member'], 'American Orthopaedic Association'],
    'no level reads as the organization alone' => ['American Orthopaedic Association', [], 'American Orthopaedic Association'],
    'a null level reads as the organization alone' => ['American Orthopaedic Association', [null], 'American Orthopaedic Association'],
    'an empty level reads as the organization alone' => ['American Orthopaedic Association', [''], 'American Orthopaedic Association'],
    'a fellow' => ['American College of Surgeons', ['fellow'], 'Fellow of the American College of Surgeons'],
    'an emeritus fellow' => ['American College of Surgeons', ['emeritus_fellow'], 'Emeritus Fellow of the American College of Surgeons'],
    'a leading The is not doubled' => ['The Hip Society', ['fellow'], 'Fellow of the Hip Society'],
    'a The inside the name stays' => ['Society of The Hip', ['fellow'], 'Fellow of the Society of The Hip'],
    'a member keeps a leading The' => ['The Hip Society', ['member'], 'The Hip Society'],
    'a name wrapped in a field value' => [new Value('American College of Surgeons'), ['fellow'], 'Fellow of the American College of Surgeons'],
    'a padded name' => ['  American College of Surgeons  ', ['member'], 'American College of Surgeons'],
    'an empty name' => ['', ['fellow'], ''],
    'a blank name' => ['   ', ['fellow'], ''],
    'a missing name' => [null, ['fellow'], ''],
]);

it('stores every membership as an organization with a level', function (): void {
    $rows = 0;

    foreach (EntryFacade::whereCollection('people') as $person) {
        $stored = mustBe(Entry::class, $person, 'a person')->get('member_of');

        if ($stored === null) {
            continue;
        }

        foreach (mustBeArray($stored, 'the member_of rows') as $row) {
            $row = mustBeArray($row, 'a member_of row');

            expect($row['organization'] ?? null)->toBeString()->not->toBeEmpty()
                ->and($row['membership_level'] ?? null)->toBeIn(['member', 'fellow', 'emeritus_fellow']);

            $rows++;
        }
    }

    // A read that found no rows could not have found a plain string row
    expect($rows)->toBeGreaterThan(0);
});

it('offers the organization and the level on the grid', function (): void {
    $config = membershipLevelField()->config();
    $fields = collect(mustBeArray($config['fields'] ?? null, 'the grid fields'));
    $organization = mustBeArray(mustBeArray($fields->firstWhere('handle', 'organization'), 'the organization row')['field'] ?? null, 'the organization field');
    $level = mustBeArray(mustBeArray($fields->firstWhere('handle', 'membership_level'), 'the level row')['field'] ?? null, 'the level field');

    // Editors could add an organization before the field became a grid, and still can
    expect($config['type'] ?? null)->toBe('grid')
        ->and($fields->pluck('handle')->all())->toBe(['organization', 'membership_level'])
        ->and($organization['create'] ?? null)->toBeTrue()
        ->and($level['default'] ?? null)->toBe('member');
});

it('renders a level above Member before the organization, and a Member row as the organization', function (): void {
    $html = membershipLevelRender([
        ['id' => 'fellow', 'organization' => MEMBERSHIP_LEVEL_ORGANIZATION, 'membership_level' => 'fellow'],
        ['id' => 'member', 'organization' => 'society-for-arthritic-joint-surgery', 'membership_level' => 'member'],
    ]);

    expect($html)->toContain('Fellow of the '.MEMBERSHIP_LEVEL_ORGANIZATION_NAME)
        ->toContain('Society for Arthritic Joint Surgery')
        ->not->toContain('Member of the');
});

it('keeps a Member row a plain Organization in JSON-LD', function (): void {
    $graph = membershipLevelGraph([
        ['id' => 'member', 'organization' => MEMBERSHIP_LEVEL_ORGANIZATION, 'membership_level' => 'member'],
    ]);

    expect(membershipLevelRoles($graph))->toBe([]);

    $names = [];

    foreach (membershipLevelNodesWith($graph, 'memberOf') as $node) {
        foreach (membershipLevelMemberOf($node['memberOf']) as $organization) {
            $names[] = $organization['name'] ?? null;
        }
    }

    expect($names)->toContain(MEMBERSHIP_LEVEL_ORGANIZATION_NAME);
});

it('wraps a level above Member in a Role around the organization in JSON-LD', function (string $level, string $label): void {
    $graph = membershipLevelGraph([
        ['id' => 'role', 'organization' => MEMBERSHIP_LEVEL_ORGANIZATION, 'membership_level' => $level],
    ]);

    $roles = membershipLevelRoles($graph);

    expect($roles)->toHaveCount(1);

    $role = $roles[0];
    $organizations = membershipLevelMemberOf($role['memberOf'] ?? null);

    expect($role['@type'] ?? null)->toBe('Role')
        ->and($role['roleName'])->toBe($label)
        ->and($organizations)->toHaveCount(1)
        ->and($organizations[0]['name'] ?? null)->toBe(MEMBERSHIP_LEVEL_ORGANIZATION_NAME);
})->with([
    'fellow' => ['fellow', 'Fellow'],
    'emeritus fellow' => ['emeritus_fellow', 'Emeritus Fellow'],
]);
