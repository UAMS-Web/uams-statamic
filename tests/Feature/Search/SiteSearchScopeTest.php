<?php

declare(strict_types=1);

use Statamic\Facades\Antlers;
use Statamic\Facades\Collection;
use Statamic\Facades\Site;
use Statamic\Sites\Site as SiteObject;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;

uses(TestCase::class);

/**
 * The scope choice on `/search` (UAMS-Web/uams-statamic#413), over the real
 * `resources/sites.yaml`: a sub-site offers "This site" and "All of <root site's
 * name>", and the root site offers only the second. Which hits each scope returns is
 * covered by `SiteSearchScopeResultsTest`.
 *
 * @covers \App\Tags\SiteSearchScope
 *
 * @command  php artisan test --compact tests/Feature/Search/SiteSearchScopeTest.php
 */

/**
 * Render the search page as a reader on a site sees it.
 *
 * @param  array<string, mixed>  $get  The query string.
 */
function renderScopedSearch(string $site, array $get = []): string
{
    Site::setCurrent($site);

    return view('search/index', ['get' => $get])->render();
}

/**
 * The scope links in a rendered page, as `label => href`.
 *
 * @return array<string, string>
 */
function scopeLinks(string $html): array
{
    preg_match('#<nav class="[^"]*site-search-scopes.*?</nav>#s', $html, $nav);
    preg_match_all('#<a class="nav-link[^"]*" href="([^"]*)"[^>]*>([^<]+)</a>#', $nav[0] ?? '', $links, PREG_SET_ORDER);

    $found = [];
    foreach ($links as $link) {
        $found[$link[2]] = $link[1];
    }

    return $found;
}

/**
 * The root site's name, as `scopeLinks()` reads it out of the page.
 */
function rootSiteLabel(): string
{
    return 'All of '.mustBeString(mustBe(SiteObject::class, Site::default(), 'the root site')->name(), 'root site name');
}

it('offers only all of the root site on the root site, as the default', function (): void {
    expect(Antlers::parse('{{ site_search_scope:active }}')->__toString())->toBe('all');

    $html = renderScopedSearch('default', ['q' => 'kirk']);

    expect(scopeLinks($html))->toBe([rootSiteLabel() => '/search?q=kirk&amp;index=default'])
        ->and($html)->toMatch('#aria-current="page">'.preg_quote(rootSiteLabel(), '#').'</a>#');
});

it('offers this site by default and all of the root site on a sub-site', function (): void {
    $html = renderScopedSearch('health', ['q' => 'kirk', 'index' => 'people']);

    expect(scopeLinks($html))->toBe([
        'This site' => '/health/search?q=kirk&amp;index=people',
        rootSiteLabel() => '/health/search?q=kirk&amp;index=people&amp;scope=all',
    ])->and($html)->toMatch('#aria-current="page">This site</a>#');
});

it('keeps the chosen scope in the tabs, the form and the type-ahead links on a sub-site', function (): void {
    $html = renderScopedSearch('health', ['q' => 'kirk', 'scope' => 'all']);

    expect($html)
        ->toMatch('#aria-current="page">'.preg_quote(rootSiteLabel(), '#').'</a>#')
        ->toContain('href="/health/search?q=kirk&amp;index=people&amp;scope=all"')
        ->toContain('action="/health/search"')
        ->toContain('<input type="hidden" name="scope" value="all">')
        ->toContain('data-search-path="/health/search" data-scope="all"');
});

it('falls back to the default for a scope the site does not offer', function (array $scope): void {
    Site::setCurrent('health');

    expect((string) Antlers::parse('{{ site_search_scope:active :requested="requested" }}', ['requested' => $scope['value']]))
        ->toBe('site');

    expect(scopeLinks(renderScopedSearch('health', ['q' => 'kirk', 'scope' => $scope['value']]))['This site'])
        ->toBe('/health/search?q=kirk&amp;index=default');
})->with([
    'unknown' => [['value' => 'everywhere']],
    'empty' => [['value' => '']],
    'upper case' => [['value' => 'ALL']],
    'an array' => [['value' => ['all']]],
    'markup' => [['value' => '"><script>alert(1)</script>']],
]);

it('never reflects a hand-edited scope into the page', function (): void {
    expect(renderScopedSearch('health', ['q' => 'kirk', 'scope' => '"><script>alert(1)</script>']))
        ->not->toContain('<script>alert(1)');
});

it('ignores the scope on the root site, which has only one', function (): void {
    $html = renderScopedSearch('default', ['q' => 'kirk', 'scope' => 'site']);

    expect(scopeLinks($html))->toBe([rootSiteLabel() => '/search?q=kirk&amp;index=default'])
        ->and($html)->not->toContain('name="scope"');
});

it('leaves knowledge-base search scoped to its subtree, with no scope choice', function (): void {
    $html = renderScopedSearch('health', ['q' => 'kirk', 'index' => 'knowledge_bases', 'kb_scope' => 'kb-root', 'scope' => 'all']);

    expect($html)->not->toContain('site-search-scopes')
        ->and($html)->not->toContain('name="scope"');
});

it('serves the search page under a sub-site prefix, as that site', function (): void {
    if (! Collection::findByHandle('pages')) {
        ContentSubmodule::reportMissing('the layout render');
    }

    /** @var TestCase $test */
    $test = $this->withoutVite();

    /** @var string $html */
    $html = $test->get('/health/search?q=kirk')->assertOk()->getContent();

    expect(scopeLinks($html))->toHaveKey('This site')
        ->and($html)->toContain('href="/health/search" title="Search"')
        ->and($html)->toContain('action="/health/search"');
});

it('keeps the root search path and header link on the root site', function (): void {
    Site::setCurrent('default');

    expect((string) Antlers::parse('{{ site_search_scope:path }}'))->toBe('/search');

    Site::setCurrent('health');

    expect((string) Antlers::parse('{{ site_search_scope:path }}'))->toBe('/health/search');
});
