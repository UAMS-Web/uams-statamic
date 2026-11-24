<?php

declare(strict_types=1);

use App\Services\ComputedFields\DepartmentHub\DepartmentHubPeopleComputedValues;
use App\Services\Posts\NewsSpringboardService;
use App\Support\Multisite\DepartmentHubResolver;
use App\Tags\DepartmentHub;
use Illuminate\Support\Collection;
use Statamic\Entries\Entry;
use Statamic\Entries\EntryCollection;
use Statamic\Facades\Site;
use Statamic\Fields\Value;
use Statamic\Sites\Site as SiteModel;
use Tests\TestCase;

uses(TestCase::class);

covers(DepartmentHub::class);

/**
 * The `{{ department_hub }}` tag's own wiring (app/Tags/DepartmentHub.php), with its
 * three collaborators replaced in the container: the site handle it asks the resolver
 * about, the organization ids and lead count it hands the magazine, and the flags it
 * derives from what comes back. Rendering against real content is covered by
 * tests/Feature/Tags/DepartmentHubTagTest.php, whose isolated Stache makes each
 * mutant child slow enough to be scored as a timeout on a two-core Linux machine (#803).
 *
 * @command  php artisan test --compact tests/Feature/Tags/DepartmentHubWiringTest.php
 */

/**
 * Pad Pest's mutant-child timeout once per process under `--mutate`: a child boots the
 * application cold and otherwise overruns a limit sized from the warm initial run.
 */
beforeEach(function (): void {
    padMutateChildTimeout();
});

/**
 * Make `Site::current()` return a site with the given handle.
 */
function departmentHubWiringSite(string|int $handle): void
{
    Site::shouldReceive('current')->andReturn(new SiteModel($handle, ['name' => 'Hub', 'url' => '/', 'locale' => 'en_US']));
}

/**
 * An organization entry, with the given id when there is one.
 */
function departmentHubWiringOrg(?string $id): Entry
{
    $entry = new Entry;
    if ($id !== null) {
        $entry->id($id);
    }

    return $entry;
}

/**
 * The given number of post entries.
 *
 * @return Collection<int, Entry>
 */
function departmentHubWiringPosts(int $count): Collection
{
    return Collection::times($count, static fn (): Entry => new Entry);
}

/**
 * Bind stubs for the tag's three collaborators and return the arguments they received.
 *
 * @param  Collection<int, Entry>|null  $lead
 * @param  array<int, array{type: string, heading: ?string, posts: Collection<int, Entry>}>  $sections
 * @return ArrayObject<string, mixed>
 */
function departmentHubWiringStubs(?Entry $organization, ?Collection $lead = null, array $sections = [], int $leaders = 0): ArrayObject
{
    $seen = new ArrayObject;

    app()->instance(DepartmentHubResolver::class, new class($organization, $seen) extends DepartmentHubResolver
    {
        /** @param ArrayObject<string, mixed> $seen */
        public function __construct(private readonly ?Entry $stubbed, private readonly ArrayObject $seen) {}

        public function organization(string $siteHandle): ?Entry
        {
            $this->seen['site'] = $siteHandle;

            return $this->stubbed;
        }
    });

    app()->instance(NewsSpringboardService::class, new class($lead ?? new Collection, $sections, $seen) extends NewsSpringboardService
    {
        /**
         * @param  Collection<int, Entry>  $lead
         * @param  array<int, array{type: string, heading: ?string, posts: Collection<int, Entry>}>  $sections
         * @param  ArrayObject<string, mixed>  $seen
         */
        public function __construct(private readonly Collection $lead, private readonly array $sections, private readonly ArrayObject $seen) {}

        public function resolveDepartmentMagazine(array $config, array $organizationIds, string $currentSiteHandle): array
        {
            $this->seen['config'] = $config;
            $this->seen['ids'] = $organizationIds;
            $this->seen['magazine_site'] = $currentSiteHandle;

            return ['lead' => $this->lead, 'sections' => $this->sections];
        }
    });

    app()->instance(DepartmentHubPeopleComputedValues::class, new class($leaders) extends DepartmentHubPeopleComputedValues
    {
        public function __construct(private readonly int $leaders) {}

        public function resolveLeadership(Entry $organization): EntryCollection
        {
            return new EntryCollection(array_fill(0, $this->leaders, $organization));
        }
    });

    return $seen;
}

/**
 * Run the tag with the given parameters and context.
 *
 * @param  array<mixed>  $params
 * @param  array<mixed>  $context
 * @return array<string, mixed>
 */
function departmentHubWiringRun(array $params = [], array $context = []): array
{
    $tag = new DepartmentHub;
    $tag->setContext($context);
    $tag->setParameters($params);

    return $tag->index();
}

it('degrades to the base rung, without a magazine, when the site is not a hub', function () {
    departmentHubWiringSite('hub-site');
    $seen = departmentHubWiringStubs(null);

    $result = departmentHubWiringRun();

    expect($seen['site'])->toBe('hub-site')
        ->and($seen->offsetExists('config'))->toBeFalse()
        ->and($result['is_hub'])->toBeFalse()
        ->and($result['has_people'])->toBeFalse()
        ->and($result['no_results'])->toBeTrue()
        ->and($result['lead'])->toBeEmpty()
        ->and($result['sections'])->toBe([])
        ->and($result['leadership'])->toBeEmpty();
});

it('hands the magazine the organization id and the current site', function () {
    departmentHubWiringSite('hub-site');
    $organization = departmentHubWiringOrg('org-a');
    $seen = departmentHubWiringStubs($organization);

    $result = departmentHubWiringRun();

    expect($seen['site'])->toBe('hub-site')
        ->and($seen['ids'])->toBe(['org-a'])
        ->and($seen['magazine_site'])->toBe('hub-site')
        ->and($result['is_hub'])->toBeTrue()
        ->and($result['organization'])->toBe($organization);
});

it('hands the resolver and the magazine an empty handle when the site handle is not a string', function () {
    departmentHubWiringSite(7);
    $seen = departmentHubWiringStubs(departmentHubWiringOrg('org-a'));

    departmentHubWiringRun();

    expect($seen['site'])->toBe('')
        ->and($seen['magazine_site'])->toBe('');
});

it('hands the magazine no organization ids when the organization has no usable id', function (?string $id) {
    $seen = departmentHubWiringStubs(departmentHubWiringOrg($id));

    departmentHubWiringRun();

    expect($seen['ids'])->toBe([]);
})->with([
    'no id' => [null],
    'empty id' => [''],
]);

it('reads the lead count from the parameter, then the context, then the default', function (array $params, array $context, int $expected) {
    $seen = departmentHubWiringStubs(departmentHubWiringOrg('org-a'));

    departmentHubWiringRun($params, $context);

    expect($seen['config'])->toBe(['lead_count' => $expected]);
})->with([
    'parameter' => [['lead_count' => '2'], ['lead_count' => 4], 2],
    'context scalar' => [[], ['lead_count' => 3], 3],
    'context value wrapper' => [[], ['lead_count' => new Value(4)], 4],
    'numeric string truncated' => [['lead_count' => '3.7'], [], 3],
    'fraction below one' => [['lead_count' => '0.5'], [], NewsSpringboardService::DEFAULT_LEAD_COUNT],
    'one' => [['lead_count' => 1], [], 1],
    'zero' => [['lead_count' => 0], [], NewsSpringboardService::DEFAULT_LEAD_COUNT],
    'negative' => [['lead_count' => -2], [], NewsSpringboardService::DEFAULT_LEAD_COUNT],
    'non-numeric' => [['lead_count' => 'many'], [], NewsSpringboardService::DEFAULT_LEAD_COUNT],
    'absent' => [[], [], NewsSpringboardService::DEFAULT_LEAD_COUNT],
]);

it('reports people only when the leadership is not empty', function (int $leaders, bool $expected) {
    departmentHubWiringStubs(departmentHubWiringOrg('org-a'), leaders: $leaders);

    $result = departmentHubWiringRun();

    expect($result['has_people'])->toBe($expected)
        ->and($result['leadership'])->toHaveCount($leaders);
})->with([
    'no leaders' => [0, false],
    'one leader' => [1, true],
]);

it('reports no results only when the lead and every section are empty', function (int $leadSize, int $firstSection, int $secondSection, bool $expected) {
    $lead = departmentHubWiringPosts($leadSize);
    $sections = [
        ['type' => 'news', 'heading' => null, 'posts' => departmentHubWiringPosts($firstSection)],
        ['type' => 'feature', 'heading' => 'Features', 'posts' => departmentHubWiringPosts($secondSection)],
    ];
    departmentHubWiringStubs(departmentHubWiringOrg('org-a'), $lead, $sections);

    $result = departmentHubWiringRun();

    expect($result['no_results'])->toBe($expected)
        ->and($result['lead'])->toBe($lead)
        ->and($result['sections'])->toBe($sections);
})->with([
    'nothing' => [0, 0, 0, true],
    'a lead' => [1, 0, 0, false],
    'the first section has posts' => [0, 1, 0, false],
    'only a later section has posts' => [0, 0, 1, false],
    'a lead and sections with posts' => [2, 1, 1, false],
]);
