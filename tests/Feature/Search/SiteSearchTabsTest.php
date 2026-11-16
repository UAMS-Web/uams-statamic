<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Statamic\Facades\Collection;
use Statamic\Facades\Search;
use Statamic\Facades\Site;
use Statamic\Search\Result;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;
use UAMSWeb\Aether\Search\Http\Controllers\SuggestionsController;
use UAMSWeb\Aether\Search\Support\SearchScopes;

uses(TestCase::class);

/**
 * Which type tabs `/search` shows on each site (UAMS-Web/uams-statamic#475), over the real
 * `resources/sites.yaml` and the real collection site lists. Under "This site" a tab shows
 * only when the site carries its collection; under "All sites", and on the root site, all
 * six show. The rule is the add-on's (`uams-web/aether-search` v0.3.0); what this file
 * holds is the host's `tab_collections` map, and that the sites below carry what it assumes.
 *
 * - `health` carries all five tab collections.
 * - `medicine` carries `people` and `posts`, and none of the clinical collections.
 * - `inside_team_uams` carries neither `people` nor `posts`.
 *
 * @command  php artisan test --compact tests/Feature/Search/SiteSearchTabsTest.php
 */

/** The six tab labels, in the order the page shows them. */
const ALL_SEARCH_TABS = ['All results', 'People', 'Clinical Areas of Expertise', 'Clinical Locations', 'Clinical Resources', 'News'];

/**
 * Fail loudly, rather than pass on an empty site list, when the content collections are absent.
 */
function requireTabCollections(): void
{
    foreach (['people', 'posts', 'clinical_expertise', 'clinical_locations', 'clinical_resources'] as $handle) {
        ContentSubmodule::requireCollection(Collection::find($handle), $handle);
    }
}

/**
 * Render the search page on a site, with an empty index.
 *
 * @param  array<array-key, mixed>  $get  The query string.
 */
function renderTabsSearch(string $site, array $get = []): string
{
    Site::setCurrent($site);
    stubEmptySearchIndex();

    return renderAetherSearchPage(['q' => 'kirk'] + $get);
}

/**
 * The type tabs in a rendered page, as `label => href`.
 *
 * @return array<string, string>
 */
function typeTabs(string $html): array
{
    preg_match('#<nav class="site-search-tabs mx-auto.*?</nav>#s', $html, $nav);
    preg_match_all('#<a class="nav-link[^"]*" href="([^"]*)"[^>]*>([^<]+)</a>#', $nav[0] ?? '', $links, PREG_SET_ORDER);

    $found = [];
    foreach ($links as $link) {
        $found[$link[2]] = $link[1];
    }

    return $found;
}

/**
 * The `href` of the scope link with this label.
 */
function scopeLinkHref(string $html, string $label): ?string
{
    preg_match('#<nav class="[^"]*site-search-scopes.*?</nav>#s', $html, $nav);
    preg_match('#<a class="nav-link[^"]*" href="([^"]*)"[^>]*>'.preg_quote($label, '#').'</a>#', $nav[0] ?? '', $link);

    return $link[1] ?? null;
}

beforeEach(function (): void {
    requireTabCollections();
});

it('names a tab collection for every tab but default, matching the collection its index searches', function (): void {
    /** @var array<string, string> $tabs */
    $tabs = config('aether-search.tabs');
    /** @var array<string, list<string>> $covers */
    $covers = config('aether-search.tab_collections');

    expect(array_keys($covers))->toEqualCanonicalizing(array_values(array_diff(array_keys($tabs), ['default'])));

    foreach ($covers as $index => $collections) {
        $searchables = (array) config("statamic.search.indexes.{$index}.searchables");

        expect(array_map(fn (string $handle): string => 'collection:'.$handle, $collections))
            ->toEqualCanonicalizing($searchables, "the {$index} tab's collections");
    }
});

it('configures an index for every tab', function (): void {
    expect(app(SearchScopes::class)->unconfiguredIndexes())->toBe([]);
});

it('hides the clinical tabs under This site on a site that carries none of the clinical collections', function (): void {
    expect(array_keys(typeTabs(renderTabsSearch('medicine'))))->toBe(['All results', 'People', 'News']);
});

it('shows all six tabs under This site on health, which carries every tab collection', function (): void {
    expect(array_keys(typeTabs(renderTabsSearch('health'))))->toBe(ALL_SEARCH_TABS);
});

it('drops People and News under This site on a site that carries neither', function (): void {
    expect(array_keys(typeTabs(renderTabsSearch('inside_team_uams'))))->toBe(['All results']);
});

it('shows all six tabs under All sites, whatever the site carries', function (string $site): void {
    expect(array_keys(typeTabs(renderTabsSearch($site, ['scope' => 'all']))))->toBe(ALL_SEARCH_TABS);
})->with(['medicine', 'inside_team_uams', 'health']);

it('shows all six tabs on the root site', function (): void {
    expect(array_keys(typeTabs(renderTabsSearch('default'))))->toBe(ALL_SEARCH_TABS);
});

it('lands a link to This site on All results when the site hides the current tab', function (): void {
    $html = renderTabsSearch('medicine', ['scope' => 'all', 'index' => 'clinical_locations']);

    expect(scopeLinkHref($html, 'This site'))->toBe('/medicine/search?q=kirk&amp;index=default');
});

it('keeps the current tab in a link to This site when the site shows it', function (): void {
    $html = renderTabsSearch('medicine', ['scope' => 'all', 'index' => 'people']);

    expect(scopeLinkHref($html, 'This site'))->toBe('/medicine/search?q=kirk&amp;index=people');
});

/**
 * The handle of the type tab marked active in a rendered page, or null when none is.
 */
function activeTypeTab(string $html): ?string
{
    preg_match('#<nav class="site-search-tabs mx-auto.*?</nav>#s', $html, $nav);
    preg_match('#<a class="nav-link active" href="[^"]*index=([a-z_]+)[^"]*"[^>]*aria-current="page">#', $nav[0] ?? '', $active);

    return $active[1] ?? null;
}

it('falls back to All results, marked active, on the page for a hidden or unknown tab', function (string $index): void {
    expect(activeTypeTab(renderTabsSearch('medicine', ['index' => $index])))->toBe('default');
})->with(['hidden here' => ['clinical_locations'], 'unknown' => ['enterprise']]);

it('keeps a tab active under All sites on a site that hides it under This site', function (): void {
    expect(activeTypeTab(renderTabsSearch('medicine', ['scope' => 'all', 'index' => 'clinical_locations'])))->toBe('clinical_locations');
});

it('serves a hidden or unknown tab on a sub-site as All results, with a 200', function (string $index): void {
    if (! Collection::findByHandle('pages')) {
        ContentSubmodule::reportMissing('the layout render');
    }

    // Any index but `default` throws, as an unconfigured one does, so the 200 shows the
    // page never asked for the requested index.
    Search::shouldReceive('index')->with(Mockery::not('default'))->andThrow(new InvalidArgumentException('Index not configured.'));
    stubEmptySearchIndex();

    /** @var TestCase $test */
    $test = $this->withoutVite();

    /** @var string $html */
    $html = $test->get('/medicine/search?q=kirk&index='.$index)->assertOk()->getContent();

    expect(array_keys(typeTabs($html)))->toBe(['All results', 'People', 'News'])
        ->and(activeTypeTab($html))->toBe('default');
})->with(['hidden here' => ['clinical_locations'], 'unknown' => ['enterprise']]);

it('answers the type-ahead from default for a tab the site hides, and from the tab where it shows', function (string $site, string $index, string $expected, ?string $scope = null): void {
    $recorded = new ArrayObject;
    Search::shouldReceive('index')->andReturnUsing(function (string $handle) use ($recorded): object {
        $recorded['index'] = $handle;

        $index = Mockery::mock();
        $index->shouldReceive('ensureExists')->andReturnSelf();
        $index->shouldReceive('search')->andReturn(new class
        {
            /** @var list<Result> */
            private array $hits = [];

            public function where(): static
            {
                return $this;
            }

            public function whereIn(): static
            {
                return $this;
            }

            public function limit(): static
            {
                return $this;
            }

            /** @return Illuminate\Support\Collection<int, Result> */
            public function get(): Illuminate\Support\Collection
            {
                return collect($this->hits);
            }
        });

        return $index;
    });

    $request = Request::create('/!/search/suggestions', 'GET', array_filter(['q' => 'kirk', 'site' => $site, 'index' => $index, 'scope' => $scope], fn (?string $value): bool => $value !== null));
    $response = app(SuggestionsController::class)($request);

    expect($response->getStatusCode())->toBe(200)
        ->and(mustBeArray($response->getData(true), 'the suggestions body')['index'] ?? null)->toBe($expected)
        ->and($recorded['index'] ?? null)->toBe($expected);
})->with([
    'hidden on medicine' => ['medicine', 'clinical_locations', 'default'],
    'shown on health' => ['health', 'clinical_locations', 'clinical_locations'],
    'unknown' => ['medicine', 'enterprise', 'default'],
    'hidden on medicine, but under All sites' => ['medicine', 'clinical_locations', 'clinical_locations', 'all'],
]);
