<?php

declare(strict_types=1);

use App\Search\SubjectSiteTree;
use App\Support\Multisite\SiteTreeCache;
use App\Support\Multisite\SiteTreeSnapshot;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Search;
use Statamic\Facades\Site;
use Statamic\Facades\URL;
use Statamic\Search\Index;
use Statamic\Search\QueryBuilder as SearchQueryBuilder;
use Statamic\Sites\Site as SiteObject;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;
use UAMSWeb\Aether\Search\Contracts\SiteTree;

// cspell:ignore anesthesiology
uses(TestCase::class);

/**
 * `/search` on a parent site searches its subtree, and a site below a tier is offered
 * "All of <nearest tier>" (#480), through the `SiteTree` seam of `uams-web/aether-search`.
 *
 * The hermetic cases prime this request's tree with a fixed snapshot over real handles from
 * `resources/sites.yaml`, so they run without the `content/` submodule:
 *
 *     default (root)
 *     ├── health (major_entity)            a tier
 *     │   └── health_nicu (sub_entity)
 *     ├── academic_affairs (major_entity)  a tier
 *     │   └── research (major_entity)      a tier, nested
 *     │       └── research_institutional_review_board (sub_entity)
 *     ├── medicine (major_entity)          a tier
 *     │   └── medicine_anesthesiology (sub_entity)
 *     └── cancer (major_entity)            no site below it, so an ordinary site
 *
 * The cases naming the real tree's figures need `content/` and self-skip without it.
 *
 * @command  php artisan test --compact tests/Feature/Search/SiteSearchTierTest.php
 */

/**
 * Prime this request's multisite tree with a fixed snapshot, so no content is read.
 */
function primeSearchTierTree(): void
{
    $snapshot = new SiteTreeSnapshot(
        [
            'default' => null,
            'health' => 'default',
            'health_nicu' => 'health',
            'academic_affairs' => 'default',
            'research' => 'academic_affairs',
            'research_institutional_review_board' => 'research',
            'medicine' => 'default',
            'medicine_anesthesiology' => 'medicine',
            'cancer' => 'default',
        ],
        [
            'default' => 'root',
            'health' => 'major_entity',
            'health_nicu' => 'sub_entity',
            'academic_affairs' => 'major_entity',
            'research' => 'major_entity',
            'research_institutional_review_board' => 'sub_entity',
            'medicine' => 'major_entity',
            'medicine_anesthesiology' => 'sub_entity',
            'cancer' => 'major_entity',
        ],
    );

    $cache = new SiteTreeCache;
    (fn () => $this->snapshot = $snapshot)->call($cache);
    app()->instance(SiteTreeCache::class, $cache);
}

/**
 * Answer every search with no hits, and record each `site` constraint a search applies.
 *
 * A facade expectation set twice in one test keeps the first, so the index is mocked once for
 * each application instance and every later call empties and returns the same record.
 *
 * @return ArrayObject<int, mixed> The constraints, appended as each search runs.
 */
function recordSearchedSites(): ArrayObject
{
    /** @var ArrayObject<int, mixed>|null $sites */
    static $sites = null;
    static $application = null;

    if ($sites instanceof ArrayObject && $application === spl_object_id(app())) {
        $sites->exchangeArray([]);

        return $sites;
    }

    $application = spl_object_id(app());
    /** @var ArrayObject<int, mixed> $sites */
    $sites = new ArrayObject;

    $index = Mockery::mock(Index::class);
    $index->shouldReceive('name')->andReturn('default');
    $index->shouldReceive('ensureExists')->andReturnSelf();
    $index->shouldReceive('extraAugmentedResultData')->andReturn([]);
    $index->shouldReceive('search')->andReturnUsing(function (string $query) use ($index, $sites): SearchQueryBuilder {
        $builder = new class($index, $sites) extends SearchQueryBuilder
        {
            /**
             * @param  ArrayObject<int, mixed>  $sites
             */
            public function __construct(Index $index, private readonly ArrayObject $sites)
            {
                parent::__construct($index);
            }

            /**
             * @return list<array{reference: string, search_score: int}>
             */
            public function getSearchResults(mixed $query): array
            {
                return [];
            }

            /**
             * @param  string  $column
             * @param  array<int, mixed>  $values
             * @param  string  $boolean
             */
            public function whereIn($column, $values, $boolean = 'and'): static
            {
                if ($column === 'site') {
                    $this->sites->append($values);
                }

                parent::whereIn($column, $values, $boolean);

                return $this;
            }
        };
        $builder->query($query);

        return $builder;
    });
    Search::shouldReceive('index')->andReturn($index);

    return $sites;
}

/**
 * Render the search page on a site, and report the page and the sites its search covered.
 *
 * @param  array<array-key, mixed>  $get  The query string.
 * @return array{html: string, sites: list<mixed>}
 */
function searchOnTierSite(string $site, array $get = []): array
{
    $sites = recordSearchedSites();
    Site::setCurrent($site);

    return ['html' => renderAetherSearchPage(['q' => 'kirk'] + $get), 'sites' => array_values($sites->getArrayCopy())];
}

/**
 * The scope options a rendered page offers, as `label => href`, in order.
 *
 * @return array<string, string>
 */
function tierScopeLinks(string $html): array
{
    preg_match('#<nav class="[^"]*site-search-scopes.*?</nav>#s', $html, $nav);
    preg_match_all('#<a class="nav-link[^"]*" href="([^"]*)"[^>]*>([^<]+)</a>#', $nav[0] ?? '', $links, PREG_SET_ORDER);

    $found = [];
    foreach ($links as $link) {
        $found[html_entity_decode($link[2])] = $link[1];
    }

    return $found;
}

/**
 * A configured site's name.
 */
function tierSiteName(string $handle): string
{
    return mustBeString(mustBe(SiteObject::class, Site::get($handle), 'the '.$handle.' site')->name(), $handle.' name');
}

/**
 * A configured site's search page path, as the page builds its links.
 */
function tierSearchPath(string $handle): string
{
    $url = mustBe(SiteObject::class, Site::get($handle), 'the '.$handle.' site')->url();

    return rtrim(URL::makeRelative(mustBeString($url, $handle.' url')), '/').'/search';
}

it('binds the subject-driven tree to the add-on\'s seam', function (): void {
    expect(get_class(app()->make(SiteTree::class)))->toBe(SubjectSiteTree::class);
});

it('searches a tier and every site below it from "This site"', function (): void {
    primeSearchTierTree();

    expect(searchOnTierSite('health')['sites'])->toBe([['health', 'health_nicu']])
        ->and(searchOnTierSite('medicine_anesthesiology')['sites'])->toBe([['medicine_anesthesiology']])
        ->and(searchOnTierSite('academic_affairs')['sites'])->toBe([['academic_affairs', 'research', 'research_institutional_review_board']]);
});

it('still searches every site from the root site', function (): void {
    primeSearchTierTree();

    $page = searchOnTierSite('default');

    expect($page['sites'])->toBe([])
        ->and(tierScopeLinks($page['html']))->toBe(['This site' => '/search?q=kirk&amp;index=default']);
});

it('offers a site below a tier "This site", "All of <nearest tier>" and "All of <root>", in that order', function (): void {
    primeSearchTierTree();

    $page = searchOnTierSite('research_institutional_review_board');
    $path = tierSearchPath('research_institutional_review_board');

    // The nearest tier is `research`, not the `academic_affairs` tier above it
    expect(tierScopeLinks($page['html']))->toBe([
        'This site' => $path.'?q=kirk&amp;index=default',
        'All of '.tierSiteName('research') => $path.'?q=kirk&amp;index=default&amp;scope=tier',
        'All of '.tierSiteName('default') => $path.'?q=kirk&amp;index=default&amp;scope=all',
    ])->and($page['sites'])->toBe([['research_institutional_review_board']]);
});

it('searches the nearest tier\'s sites at its scope, and offers the wider link from its empty state', function (): void {
    primeSearchTierTree();

    $page = searchOnTierSite('research_institutional_review_board', ['scope' => 'tier']);

    expect($page['sites'])->toBe([['research', 'research_institutional_review_board']])
        ->and($page['html'])->toMatch('#aria-current="page">All of '.preg_quote(e(tierSiteName('research')), '#').'</a>#')
        ->toContain('&amp;scope=all">search all of '.e(tierSiteName('default')).'</a>');
});

it('offers two options, unchanged, on a site whose nearest tier is the root', function (string $site): void {
    primeSearchTierTree();

    $page = searchOnTierSite($site);

    expect(array_keys(tierScopeLinks($page['html'])))->toBe(['This site', 'All of '.tierSiteName('default')])
        ->and(searchOnTierSite($site, ['scope' => 'tier'])['sites'])->toBe(searchOnTierSite($site)['sites']);
})->with(['a tier below the root' => 'medicine', 'an ordinary site below the root' => 'cancer']);

it('treats a major_entity site with no site below it as an ordinary site', function (): void {
    primeSearchTierTree();

    $page = searchOnTierSite('cancer');

    expect($page['sites'])->toBe([['cancer']])
        ->and(array_keys(tierScopeLinks($page['html'])))->toBe(['This site', 'All of '.tierSiteName('default')]);
});

it('suggests from the same sites the page searches, for each scope', function (string $site, array $get): void {
    primeSearchTierTree();

    $page = searchOnTierSite($site, $get)['sites'];

    $suggested = recordSearchedSites();
    $this->getJson('/!/search/suggestions?'.http_build_query(['q' => 'kirk', 'site' => $site] + $get))->assertOk();

    expect(array_values($suggested->getArrayCopy()))->toBe($page);
})->with([
    'a tier, this site' => ['health', []],
    'below a tier, this site' => ['research_institutional_review_board', []],
    'below a tier, the tier' => ['research_institutional_review_board', ['scope' => 'tier']],
    'below a tier, every site' => ['research_institutional_review_board', ['scope' => 'all']],
    'the root' => ['default', []],
]);

/**
 * Skip unless the multisite-settings global set is present (content submodule).
 */
function searchTierRequireGlobals(): void
{
    if (! GlobalSet::find('uams_multisite_settings')) {
        ContentSubmodule::reportMissing('uams_multisite_settings global');
    }
}

it('searches a real tier\'s whole subtree from its search page', function (string $tier, int $sites): void {
    searchTierRequireGlobals();

    expect(searchOnTierSite($tier)['sites'][0] ?? [])->toHaveCount($sites);
})->with([
    'medicine' => ['medicine', 38],
    'health' => ['health', 28],
    'inside' => ['inside', 53],
]);

it('offers a real research site "All of" research, not academic affairs', function (): void {
    searchTierRequireGlobals();

    $links = array_keys(tierScopeLinks(searchOnTierSite('research_institutional_review_board')['html']));

    expect($links)->toBe(['This site', 'All of '.tierSiteName('research'), 'All of '.tierSiteName('default')]);
});
