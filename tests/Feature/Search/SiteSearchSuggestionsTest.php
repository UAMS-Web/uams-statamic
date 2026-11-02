<?php

declare(strict_types=1);

use App\Http\Controllers\SiteSearchSuggestionsController;
use App\Search\AreaOfExpertiseSectionDocument;
use Illuminate\Http\Request;
use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Search;
use Statamic\Facades\Site;
use Statamic\Facades\User;
use Statamic\Search\Result;
use Tests\Support\StacheIsolatedTestCase;

uses(StacheIsolatedTestCase::class);

/**
 * The type-ahead endpoint behind the `/search` input (UAMS-Web/uams-statamic-archive#1506).
 *
 * The search driver is mocked at `Search::index()`, as SiteSearchTest does, so no index
 * is built. What is under test is the endpoint's own contract: which index and
 * constraints it asks for, how many results, which inputs it refuses to query, and
 * how each hit is shaped. The hits are real `Result`s over real entries in this
 * test's isolated Stache, so URLs and titles come from Statamic rather than from
 * fixtures.
 *
 * @command  php artisan test --compact tests/Feature/Search/SiteSearchSuggestionsTest.php
 */

/**
 * A search builder stand-in recording each constraint applied to it, under a flat key
 * (`where.status`, `whereIn.site`, `limit`), and returning the given hits.
 *
 * @param  list<Result>  $hits  What `get()` returns.
 * @param  ArrayObject<string, mixed>  $recorded  Receives each constraint.
 */
function suggestionsBuilder(array $hits, ArrayObject $recorded): object
{
    return new class($hits, $recorded)
    {
        /**
         * @param  list<Result>  $hits
         * @param  ArrayObject<string, mixed>  $recorded
         */
        public function __construct(private readonly array $hits, private readonly ArrayObject $recorded) {}

        public function where(string $column, mixed $value): static
        {
            $this->recorded['where.'.$column] = $value;

            return $this;
        }

        /**
         * @param  list<string>  $values
         */
        public function whereIn(string $column, array $values): static
        {
            $this->recorded['whereIn.'.$column] = $values;

            return $this;
        }

        public function limit(int $limit): static
        {
            $this->recorded['limit'] = $limit;

            return $this;
        }

        /**
         * @return Illuminate\Support\Collection<int, Result>
         */
        public function get(): Illuminate\Support\Collection
        {
            return collect($this->hits);
        }
    };
}

/**
 * Point `Search::index('default')` at a builder returning `$hits`, and return what the
 * endpoint asks of it: the index, the query and each constraint.
 *
 * @param  list<Result>  $hits  The hits to return.
 * @return ArrayObject<string, mixed>
 */
function mockSuggestionsIndex(array $hits): ArrayObject
{
    /** @var ArrayObject<string, mixed> $recorded */
    $recorded = new ArrayObject;
    $builder = suggestionsBuilder($hits, $recorded);

    $index = Mockery::mock();
    $index->shouldReceive('ensureExists')->andReturnSelf();
    $index->shouldReceive('search')->andReturnUsing(function (string $query) use ($builder, $recorded): object {
        $recorded['query'] = $query;

        return $builder;
    });

    Search::shouldReceive('index')->andReturnUsing(function (string $handle) use ($index, $recorded): object {
        $recorded['index'] = $handle;

        return $index;
    });

    return $recorded;
}

/**
 * Call the endpoint with `?q=` (and `?index=` when given) and return its decoded body.
 *
 * @return array<string, mixed>
 */
function suggestionsFor(mixed $query, mixed $index = null): array
{
    $request = Request::create('/!/search/suggestions', 'GET', array_filter(['q' => $query, 'index' => $index], fn (mixed $value): bool => $value !== null));

    $response = app(SiteSearchSuggestionsController::class)($request);

    /** @var array<string, mixed> $body */
    $body = mustBeArray($response->getData(true), 'the suggestions body');

    return $body;
}

/**
 * Call the endpoint at `?q=kirk` with these parameters, as a page on a `people` collection
 * across a root site and one sub-site would, and return its decoded body.
 *
 * @param  array<array-key, mixed>  $parameters  Query parameters, such as `site` and `scope`.
 * @return array<string, mixed>
 */
function multisiteSuggestionsFor(array $parameters): array
{
    $request = Request::create('/!/search/suggestions', 'GET', ['q' => 'kirk'] + $parameters);

    $response = app(SiteSearchSuggestionsController::class)($request);

    /** @var array<string, mixed> $body */
    $body = mustBeArray($response->getData(true), 'the suggestions body');

    return $body;
}

/**
 * Give the test a root site and one sub-site, both carrying `people`.
 */
function useTwoSuggestionSites(): void
{
    Site::setSites([
        'default' => ['name' => 'Starfleet Academy', 'url' => '/', 'locale' => 'en_US'],
        'second' => ['name' => 'Second', 'url' => '/second/', 'locale' => 'en_US'],
    ]);

    $collection = Collection::make('people');
    $collection->routes('/people/{slug}');
    $collection->sites(['default', 'second']);
    $collection->save();
}

/**
 * Save a published `people` entry on a site.
 *
 * @param  string|null  $title  Null for a localization that inherits it.
 * @param  string|null  $visibility  A `status_and_visibility` value, such as `private`.
 * @param  StatamicEntry|null  $origin  The entry this one localizes.
 */
function siteSuggestionEntry(string $site, string $slug, ?string $title, ?string $visibility = null, ?StatamicEntry $origin = null): StatamicEntry
{
    /** @var StatamicEntry $entry */
    $entry = Entry::make();
    $entry->collection('people');
    $entry->locale($site);
    $entry->slug($slug);
    $entry->published(true);
    $entry->data(array_filter(['title' => $title, 'status_and_visibility' => $visibility]));

    if ($origin instanceof StatamicEntry) {
        $entry->origin($origin);
    }

    $entry->saveQuietly();

    return $entry;
}

/**
 * Save a published entry in a routed collection and wrap it as a search hit.
 *
 * @param  string|null  $visibility  A `status_and_visibility` value, such as `private`.
 */
function suggestionHit(string $collection, string $slug, string $title, ?string $visibility = null): Result
{
    return new Result(savedSuggestionEntry($collection, $slug, $title, $visibility), 'entry');
}

/**
 * Save a published entry in a routed collection.
 *
 * @param  string|null  $visibility  A `status_and_visibility` value, such as `private`.
 */
function savedSuggestionEntry(string $collection, string $slug, string $title, ?string $visibility = null): StatamicEntry
{
    /** @var StatamicEntry $entry */
    $entry = Entry::make();
    $entry->collection($collection);
    $entry->slug($slug);
    $entry->published(true);
    $entry->data(array_filter(['title' => $title, 'status_and_visibility' => $visibility]));
    $entry->saveQuietly();

    return $entry;
}

beforeEach(function (): void {
    foreach (['people' => '/people/{slug}', 'pages' => '/{slug}', 'clinical_expertise' => '/{slug}', 'organizations' => null] as $handle => $route) {
        $collection = Collection::make($handle);

        if ($route !== null) {
            $collection->routes($route);
        }

        $collection->save();
    }
});

it('asks the unified default index for published items, limited to the flyout size', function (): void {
    $recorded = mockSuggestionsIndex([]);

    suggestionsFor('  cardiology ');

    expect($recorded['index'])->toBe('default')
        ->and($recorded['query'])->toBe('cardiology')
        ->and($recorded['where.status'])->toBe('published')
        // A request naming no site is the root site's, and its one scope is every site
        ->and($recorded)->not->toHaveKey('whereIn.site')
        // Twice the flyout size, so hits dropped after the query do not leave it short
        ->and($recorded['limit'])->toBe(16)
        ->and(SiteSearchSuggestionsController::LIMIT)->toBe(8);
});

it('shapes each hit as title, url and type, labeling it as the results page does', function (): void {
    mockSuggestionsIndex([
        suggestionHit('people', 'leonard-mccoy', 'Leonard McCoy'),
        new Result(new AreaOfExpertiseSectionDocument(mustBeString(savedSuggestionEntry('clinical_expertise', 'cardiology', 'Cardiology')->id(), 'id'), 'providers', 'Cardiology — Providers', '/cardiology/providers', 'default'), 'clinical_expertise_section'),
        suggestionHit('pages', 'visiting-hours', 'Visiting Hours'),
    ]);

    expect(suggestionsFor('ca')['results'])->toBe([
        ['title' => 'Leonard McCoy', 'url' => '/people/leonard-mccoy', 'type' => 'Person'],
        ['title' => 'Cardiology — Providers', 'url' => '/cardiology/providers', 'type' => 'Area of Expertise'],
        ['title' => 'Visiting Hours', 'url' => '/visiting-hours', 'type' => 'Page'],
    ]);
});

it('drops a hit with no public URL rather than offering a link to nowhere', function (): void {
    mockSuggestionsIndex([
        suggestionHit('organizations', 'starfleet-medical', 'Starfleet Medical'),
        suggestionHit('pages', 'sickbay', 'Sickbay'),
    ]);

    expect(array_column(mustBeArray(suggestionsFor('st')['results'], 'results'), 'title'))->toBe(['Sickbay']);
});

it('queries nothing for a short, overlong or non-string query', function (mixed $query): void {
    Search::shouldReceive('index')->never();

    expect(suggestionsFor($query)['results'])->toBe([]);
})->with([
    'empty' => [''],
    'one character' => ['c'],
    'whitespace around one character' => ['  c  '],
    'over the maximum' => [str_repeat('a', SiteSearchSuggestionsController::MAX_QUERY_LENGTH + 1)],
    'an array' => [['cardiology']],
]);

it('queries the index of the page tab, and falls back to default for anything else', function (mixed $requested, string $expected): void {
    $recorded = mockSuggestionsIndex([]);

    $body = suggestionsFor('cardiology', $requested);

    expect($recorded['index'])->toBe($expected)
        ->and($body['index'])->toBe($expected);
})->with([
    'none' => [null, 'default'],
    'people' => ['people', 'people'],
    'posts' => ['posts', 'posts'],
    'knowledge bases, which the type-ahead is not offered on' => ['knowledge_bases', 'default'],
    'an unconfigured handle' => ['users', 'default'],
    'an array' => [['people'], 'default'],
]);

it('answers a query that is not valid UTF-8 with nothing, rather than failing to encode it', function (): void {
    $recorded = mockSuggestionsIndex([]);

    expect(suggestionsFor("\xFF\xFF"))->toBe(['query' => '', 'index' => 'default', 'results' => []])
        ->and($recorded)->not->toHaveKey('query');
});

it('searches only the sub-site at This site', function (array $parameters): void {
    useTwoSuggestionSites();
    $recorded = mockSuggestionsIndex([]);

    multisiteSuggestionsFor($parameters);

    expect($recorded['whereIn.site'] ?? null)->toBe(['second']);
})->with([
    'no scope' => [['site' => 'second']],
    'the site scope by name' => [['site' => 'second', 'scope' => 'site']],
    'an unknown scope' => [['site' => 'second', 'scope' => 'bridge']],
    'an array scope' => [['site' => 'second', 'scope' => ['all']]],
]);

it('searches every site for all UAMS websites, and this site when no engine is configured', function (mixed $engine, bool $everySite): void {
    config(['services.google_search.engine_id' => $engine]);
    useTwoSuggestionSites();
    $recorded = mockSuggestionsIndex([]);

    multisiteSuggestionsFor(['site' => 'second', 'scope' => 'google']);

    expect(isset($recorded['whereIn.site']))->toBe(! $everySite);
})->with([
    'configured' => ['engine:one', true],
    'not configured' => [null, false],
]);

it('searches every site where the page would', function (array $parameters): void {
    useTwoSuggestionSites();
    $recorded = mockSuggestionsIndex([]);

    multisiteSuggestionsFor($parameters);

    expect($recorded)->not->toHaveKey('whereIn.site')
        ->and($recorded['where.status'] ?? null)->toBe('published');
})->with([
    'the wide scope on a sub-site' => [['site' => 'second', 'scope' => 'all']],
    'the wide scope on the root site' => [['site' => 'default', 'scope' => 'all']],
    'the root site, whose one scope is every site' => [['site' => 'default']],
    'the root site asking for less than it offers' => [['site' => 'default', 'scope' => 'site']],
    'no site at all, which is the root site' => [[]],
    'a site that does not exist' => [['site' => 'bridge']],
    'the every-site wildcard, which names no site' => [['site' => '*']],
    'an array of sites' => [['site' => ['second', 'default']]],
]);

it('links each hit at the wide scope to its own site', function (): void {
    useTwoSuggestionSites();
    mockSuggestionsIndex([
        new Result(siteSuggestionEntry('default', 'james-kirk', 'James Kirk'), 'entry'),
        new Result(siteSuggestionEntry('second', 'hikaru-sulu', 'Hikaru Sulu'), 'entry'),
    ]);

    expect(multisiteSuggestionsFor(['site' => 'second', 'scope' => 'all'])['results'])->toBe([
        ['title' => 'James Kirk', 'url' => '/people/james-kirk', 'type' => 'Person'],
        ['title' => 'Hikaru Sulu', 'url' => '/second/people/hikaru-sulu', 'type' => 'Person'],
    ]);
});

it('hides a private entry at either scope, and a localization that inherits it', function (array $parameters): void {
    useTwoSuggestionSites();
    $private = siteSuggestionEntry('default', 'janice-rand', 'Janice Rand', 'private');

    mockSuggestionsIndex([
        new Result($private, 'entry'),
        new Result(siteSuggestionEntry('second', 'janice-rand', null, null, $private), 'entry'),
        new Result(siteSuggestionEntry('second', 'leonard-mccoy', 'Leonard McCoy'), 'entry'),
    ]);

    expect(multisiteSuggestionsFor($parameters)['results'])->toBe([
        ['title' => 'Leonard McCoy', 'url' => '/second/people/leonard-mccoy', 'type' => 'Person'],
    ]);
})->with([
    'this site' => [['site' => 'second']],
    'every site' => [['site' => 'second', 'scope' => 'all']],
]);

it('drops a private entry for a guest, as its own URL would 404', function (): void {
    mockSuggestionsIndex([
        suggestionHit('people', 'janice-rand', 'Janice Rand', 'private'),
        suggestionHit('people', 'leonard-mccoy', 'Leonard McCoy', 'published'),
    ]);

    expect(array_column(mustBeArray(suggestionsFor('an')['results'], 'results'), 'title'))->toBe(['Leonard McCoy']);
});

it('offers a private entry to a super user, who may open it', function (): void {
    // Saved before the search facade is mocked, since a user save updates the indexes
    /** @var Statamic\Auth\File\User $admin */
    $admin = User::make();
    $admin->email('kirk@example.test');
    $admin->makeSuper();
    $admin->save();
    $this->actingAs($admin);

    mockSuggestionsIndex([suggestionHit('people', 'janice-rand', 'Janice Rand', 'private')]);

    expect(array_column(mustBeArray(suggestionsFor('ja')['results'], 'results'), 'title'))->toBe(['Janice Rand']);
});

it('drops the section subpages of a private Area of Expertise', function (): void {
    $private = savedSuggestionEntry('clinical_expertise', 'classified-medicine', 'Classified Medicine', 'private');
    $public = savedSuggestionEntry('clinical_expertise', 'cardiology', 'Cardiology');

    mockSuggestionsIndex([
        new Result(new AreaOfExpertiseSectionDocument(mustBeString($private->id(), 'id'), 'providers', 'Classified Medicine — Providers', '/classified-medicine/providers', 'default'), 'clinical_expertise_section'),
        new Result(new AreaOfExpertiseSectionDocument(mustBeString($public->id(), 'id'), 'providers', 'Cardiology — Providers', '/cardiology/providers', 'default'), 'clinical_expertise_section'),
    ]);

    expect(array_column(mustBeArray(suggestionsFor('me')['results'], 'results'), 'title'))->toBe(['Cardiology — Providers']);
});

it('fills the flyout past dropped hits, and stops at its size', function (): void {
    $hits = [suggestionHit('organizations', 'no-url', 'No URL')];

    foreach (range(1, 10) as $number) {
        $hits[] = suggestionHit('pages', 'page-'.$number, 'Page '.$number);
    }

    mockSuggestionsIndex($hits);

    $titles = array_column(mustBeArray(suggestionsFor('pa')['results'], 'results'), 'title');

    expect($titles)->toHaveCount(SiteSearchSuggestionsController::LIMIT)
        ->and($titles[0])->toBe('Page 1');
});

it('labels each collection exactly as the results page row does', function (string $collection, string $label): void {
    // The two lists are written in two languages; this keeps them from drifting.
    $row = (string) view('search/_result', ['collection' => $collection, 'title' => 'T', 'url' => '/t'])->render();

    expect($row)->toContain('<p class="search-result-type text-uppercase text-muted small mb-1">'.$label.'</p>');
})->with(array_map(
    fn (string $collection, string $label): array => [$collection, $label],
    array_keys(SiteSearchSuggestionsController::TYPE_LABELS),
    array_values(SiteSearchSuggestionsController::TYPE_LABELS),
));

it('serves the endpoint as JSON behind its rate limiter', function (): void {
    $response = $this->get('/!/search/suggestions?q=c');

    $response->assertOk()
        ->assertExactJson(['query' => 'c', 'index' => 'default', 'results' => []]);

    $route = mustExist(app('router')->getRoutes()->getByName('site-search.suggestions'), 'the suggestions route');

    expect($route->gatherMiddleware())->toContain('throttle:site-search-suggestions');
});

/**
 * Render the /search view for a given `get`, as SiteSearchTest does.
 *
 * @param  array<string, string>  $get  The query string.
 */
function renderSearchPageForTypeahead(array $get): string
{
    return (string) view('search/index', ['get' => $get])->render();
}

it('wires the search input as an ARIA combobox bound to the type-ahead component', function (): void {
    $html = renderSearchPageForTypeahead(['q' => 'heart']);

    expect($html)->toContain('x-data="siteSearchTypeahead"')
        ->toContain('role="combobox"')
        ->toContain('aria-autocomplete="list"')
        ->toContain('aria-controls="site-search-suggestions"')
        ->toContain('id="site-search-suggestions"')
        ->toContain('role="listbox"')
        ->toContain('role="status"')
        // Still a plain GET form field named q, so it submits to /search without JavaScript
        ->toContain('<form action="/search" method="get"')
        ->toContain('name="q"')
        ->toContain('value="heart"');
});

it('tells the type-ahead which site the page belongs to', function (): void {
    expect(renderSearchPageForTypeahead(['q' => 'kirk']))->toContain('data-site="default"');
});

it('leaves knowledge-base search as a plain field, since it is scoped to one subtree', function (): void {
    $html = renderSearchPageForTypeahead(['q' => 'heart', 'index' => 'knowledge_bases', 'kb_scope' => 'kb-root-id']);

    expect($html)->not->toContain('siteSearchTypeahead')
        ->not->toContain('role="combobox"')
        ->toContain('id="site-search-q"');
});

it('binds only names, never inline expressions the CSP build would refuse', function (): void {
    $html = renderSearchPageForTypeahead(['q' => 'heart']);

    preg_match('~<div class="site-search-typeahead.*?</ul>~s', $html, $matches);
    $widget = $matches[0] ?? '';

    expect($widget)->not->toBe('');

    preg_match_all('~(?:x-[a-z]+|:[a-z-]+|@[a-z.]+)="([^"]*)"~', $widget, $bindings);

    // The scan must have found the widget's bindings, or the loop below checks nothing
    expect(count($bindings[1]))->toBeGreaterThanOrEqual(15);

    foreach ($bindings[1] as $expression) {
        // A property, a dotted property, a method name, a call with a bare argument,
        // or the x-for header. No operators, literals or object syntax.
        expect($expression)->toMatch('~^(?:[A-Za-z_][\w.]*(?:\([A-Za-z_]\w*\))?|\(option, index\) in options)$~');
    }
});
