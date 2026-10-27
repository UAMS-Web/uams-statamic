<?php

declare(strict_types=1);

use App\Scopes\ExcludeNonSearchableCollections;
use App\Scopes\ExcludeUnsearchableEntries;
use App\SearchTransformers\AlternateNameTransformer;
use Illuminate\Support\Facades\File;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Scope;
use Statamic\Facades\Search;
use Statamic\Search\Index;
use Statamic\Search\Searchables;
use Statamic\Stache\Query\EntryQueryBuilder;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Coverage for the standalone `/search` site-search page (UAMS-Web/uams-statamic-archive#815 / UAMS-Web/uams-statamic-archive#56): a search
 * form (query input + a type <select> that filters by search index) over the
 * `local` driver, rendering each hit through its content type's card partial and
 * reflecting the submitted query back to the reader (escaped).
 *
 * The template wiring, config scoping, and reflected-`q` escaping are asserted
 * content-free (app-repo files, config, and a mocked query builder); the render
 * and index-exclusion tests are gated on the content/ submodule. The hostile-query
 * test renders the view directly with an injected query, so it needs no index build.
 *
 * @command  php artisan test --compact tests/Feature/SiteSearchTest.php
 */
it('wires the /search form to the launch index handles and escapes the reflected query', function (): void {
    $page = File::get(resource_path('views/search/index.antlers.html'));

    expect($page)
        // Form submits back to /search (GET).
        ->toContain('action="/search"')
        ->toContain('name="q"')
        ->toContain('name="index"')
        // The typed tabs (UAMS-Web/uams-statamic-archive#1524) offer the six launch scopes as links.
        ->toContain('index=default"')
        ->toContain('index=people"')
        ->toContain('index=clinical_expertise"')
        ->toContain('index=clinical_locations"')
        ->toContain('index=clinical_resources"')
        ->toContain('index=posts"')
        ->toContain('All results')
        ->toContain('Clinical Areas of Expertise')
        ->toContain('News')
        // The `index` param is validated against the curated launch scopes; a blank
        // or tampered value falls back to the unified default (no unconfigured-index 500).
        ->toContain("get:index == 'people'")
        ->toContain("\$search_index = 'default'")
        // Reflected query is escaped in both sinks — raw-by-default Antlers makes it an XSS sink.
        // The query is coerced to an array-guarded scalar (`$q`) first, then sanitized,
        // so a tampered `?q[]=x` can't crash `sanitize`/`urlencode` with a TypeError.
        ->toContain('$raw_q | is_array')
        ->toContain('$q | sanitize')
        // The page delegates to the shared listing partial.
        ->toContain('partial:search/results')
        // The heading + results are wrapped in the standard page-builder block shell,
        // on a gray band, with the query echoed through the block's own heading partial.
        ->toContain('partial:page_builder/uamsweb_block')
        ->toContain('background_color="bg-gray"')
        ->toContain('partial:page_builder/components/block_header');

    // The field carries no leading search glyph — a prepended icon reads as a button (per
    // design). Guard on `fa-search`, the class the removed glyph actually used, so
    // re-adding a leading icon trips this. `->not` resolves on a fresh `expect()`.
    expect($page)->not->toContain('fa-search');
});

it('scopes the unified default index to collections only, minus the non-searchable ones', function (): void {
    /** @var array{searchables: array<int, string>, query_scope: string} $default */
    $default = config('statamic.search.indexes.default');

    // Collections (taxonomy terms and assets are deliberately excluded, having been
    // previously swept in by `content`) plus the synthetic Area-of-Expertise routed-subpage
    // documents (UAMS-Web/uams-statamic-archive#1527), which carry their own `searchable` gate.
    expect($default['searchables'])->toBe(['collection:*', 'clinical_expertise_section:*'])
        // The infrastructure / non-reader-facing collections are stripped at index-build
        // time by the query scope rather than filtered per query.
        ->and($default['query_scope'])->toBe('exclude_non_searchable_collections');
});

it('resolves the default index query_scope handle to the exclusion scope class', function (): void {
    // Ties the config's `query_scope` string to the concrete scope; renaming the
    // handle on either side breaks index-build silently otherwise.
    expect(Scope::find('exclude_non_searchable_collections'))
        ->toBeInstanceOf(ExcludeNonSearchableCollections::class);
});

it('lists exactly the non-reader-facing collections it excludes from the default index', function (): void {
    // Hard-coded expectation (not the class constant) so a mutation to the list is
    // actually caught rather than comparing the constant against itself. The scope
    // queries the reader-facing collections *positively* (the universe minus this
    // list); its real behavior — including the wildcard-index non-collapse when a
    // flagged AoE is present — is covered by ExcludeUnsearchableEntriesScopeTest.
    $expected = [
        'calculators',
        'clinical_conditions',
        // `clinical_specializations` became a structured collection in UAMS-Web/uams-statamic-archive#2596: NUCC
        // classification names are not reader-facing search hits.
        'clinical_specializations',
        'clinical_services',
        'facilities',
        // `faq_categories` became a structured collection in UAMS-Web/uams-statamic-archive#2535: category names
        // are not reader-facing search hits.
        'faq_categories',
        // `geographic_locations` became a structured collection in UAMS-Web/uams-statamic-archive#2597: place
        // names are not reader-facing search hits.
        'geographic_locations',
        // `knowledge_base_tags` became a structured collection in UAMS-Web/uams-statamic-archive#2534: tag names
        // are not reader-facing search hits.
        'knowledge_base_tags',
        'logo_list_items',
        // `logos_item_categories` and `people_groups` became structured collections
        // in UAMS-Web/uams-statamic-archive#1863. As flat taxonomies they were excluded by the index's
        // "collections only" rule; as collections they need naming here, or the
        // conversion would have quietly turned category and group names into
        // reader-facing search hits.
        'logos_item_categories',
        // `logos_item_labels` followed in UAMS-Web/uams-statamic-archive#2533: label names are not reader-facing
        // search hits either.
        'logos_item_labels',
        'organizations',
        // `patient_types` became a structured collection in UAMS-Web/uams-statamic-archive#2598: patient-type names
        // are filter options, not reader-facing search hits.
        'patient_types',
        'people_groups',
        'post_categories',
        // `post_tags` followed as a structured collection in UAMS-Web/uams-statamic-archive#2532, for the same
        // reason: tag names are not reader-facing search hits.
        'post_tags',
        // `redirect_domains` holds redirect rules for domains that are not Statamic
        // sites (UAMS-Web/uams-statamic-archive#2678), which are infrastructure, not reader-facing search hits.
        'redirect_domains',
        'reusable_content',
        'schema_objects',
        // `subject_matter` became a structured collection in UAMS-Web/uams-statamic-archive#2822: subjects are a
        // controlled vocabulary for tagging, not reader-facing search hits.
        'subject_matter',
        'tables',
    ];

    expect(ExcludeNonSearchableCollections::EXCLUDED_COLLECTIONS)->toBe($expected);
});

it('sources the logos_item_labels index from the collection that replaced the taxonomy (#2533)', function (): void {
    // A `taxonomy:logos_item_labels` source would name a taxonomy that no longer exists.
    expect(config('statamic.search.indexes.logos_item_labels.searchables'))->toBe('collection:logos_item_labels');
});

it('sources the patient_types index from the collection that replaced the taxonomy (#2598)', function (): void {
    // A `taxonomy:patient_types` source would name a taxonomy that no longer exists.
    expect(config('statamic.search.indexes.patient_types.searchables'))->toBe('collection:patient_types');
});

it('sources the post_tags index from the collection that replaced the taxonomy (#2532)', function (): void {
    // A `taxonomy:post_tags` source would name a taxonomy that no longer exists.
    expect(config('statamic.search.indexes.post_tags.searchables'))->toBe('collection:post_tags');
});

it('gates the default and clinical_expertise indexes on the per-entry searchable toggle', function (): void {
    // The unified default index applies the per-entry gate via its collections scope…
    expect(Scope::find('exclude_non_searchable_collections'))
        ->toBeInstanceOf(ExcludeNonSearchableCollections::class);

    // …and the per-type Areas-of-Expertise index applies the same gate directly, so a
    // `searchable: false` overview is dropped from both "All results" and the AoE filter.
    expect(config('statamic.search.indexes.clinical_expertise.query_scope'))
        ->toBe('exclude_unsearchable_entries')
        ->and(Scope::find('exclude_unsearchable_entries'))
        ->toBeInstanceOf(ExcludeUnsearchableEntries::class);
});

it('indexes the clinical_expertise alternate_name field so an AoE is findable by it (#1060)', function (): void {
    // Guards the already-consistent `alternate_name` handle against future drift: the
    // per-type Areas-of-Expertise index must keep matching on `alternate_name` (via the
    // transformer) alongside `title`, so an AoE surfaces by an alternate name in search.
    /** @var array{fields: array<int, string>, transformers: array<string, string>} $index */
    $index = config('statamic.search.indexes.clinical_expertise');

    expect($index['fields'])->toContain('title')
        ->toContain('alternate_name')
        ->and($index['transformers']['alternate_name'] ?? null)
        ->toBe(AlternateNameTransformer::class);
});

it('keeps taxonomies, assets, and the excluded collections out of the built default index', function (): void {
    if (! Collection::findByHandle('pages') || ! Collection::findByHandle('schema_objects')) {
        ContentSubmodule::reportMissing('a real search index build');
    }

    // Drive the real provider path end to end — `searchables()` applies the index's
    // `query_scope` (the exclusion) and the provider's own `whereStatus('published')`
    // gate — without writing an index file (so no shared-storage leak across workers).
    /** @var Index $searchIndex */
    $searchIndex = Search::index('default');
    /** @var Searchables $searchables */
    $searchables = $searchIndex->searchables();
    /** @var Illuminate\Support\Collection<int, string> $references */
    $references = $searchables->all();

    // Only entry documents and the synthetic AoE-section documents (UAMS-Web/uams-statamic-archive#1527) are searchable —
    // no taxonomy terms or assets leak in.
    expect($references->every(fn ($ref): bool => str_starts_with((string) $ref, 'entry::')
        || str_starts_with((string) $ref, 'clinical_expertise_section::')))->toBeTrue();

    // The exclusion is only meaningful if an excluded collection actually has publishable
    // entries that would otherwise be indexed — assert that, then assert none leak in.
    // `Entry::query()` is the contract `QueryBuilder`; narrow each hop to the
    // concrete `EntryQueryBuilder` (whose builder methods return mixed).
    /** @var EntryQueryBuilder $schemaObjectQuery */
    $schemaObjectQuery = Entry::query();
    /** @var EntryQueryBuilder $schemaObjectQuery */
    $schemaObjectQuery = $schemaObjectQuery->where('collection', 'schema_objects');
    /** @var EntryQueryBuilder $schemaObjectQuery */
    $schemaObjectQuery = $schemaObjectQuery->whereStatus('published');
    /** @var Illuminate\Support\Collection<int, Statamic\Entries\Entry> $schemaObjectEntries */
    $schemaObjectEntries = $schemaObjectQuery->get();
    $schemaObjectRefs = $schemaObjectEntries->map(function (Statamic\Entries\Entry $entry): string {
        /** @var string $id */
        $id = $entry->id();

        return 'entry::'.$id;
    });
    expect($schemaObjectRefs)->not->toBeEmpty()
        ->and($references->intersect($schemaObjectRefs)->all())->toBe([]);

    // A reader-facing collection is still present.
    /** @var EntryQueryBuilder $pageQuery */
    $pageQuery = Entry::query();
    /** @var EntryQueryBuilder $pageQuery */
    $pageQuery = $pageQuery->where('collection', 'pages');
    /** @var EntryQueryBuilder $pageQuery */
    $pageQuery = $pageQuery->whereStatus('published');
    /** @var Statamic\Entries\Entry $page */
    $page = $pageQuery->first();
    /** @var string $pageId */
    $pageId = $page->id();
    expect($references)->toContain('entry::'.$pageId);
});

it('dispatches each hit to a per-type card and paginates via the shared component', function (): void {
    $listing = File::get(resource_path('views/search/_results.antlers.html'));

    expect($listing)
        ->toContain('search:results')
        ->toContain(':index="index"')
        // The listing is the block's `module-body`.
        ->toContain('search-results module-body')
        // Shared pagination component.
        ->toContain('partial:components/pagination');

    // People render the compact card with a portrait (UAMS-Web/uams-statamic-archive#1510); the other wrappers pass
    // through to the existing native card partials.
    expect(File::get(resource_path('views/search/cards/_people.antlers.html')))
        ->toContain('partial:search/cards/compact :portrait_url="portrait:url"')
        ->not->toContain('partial:page_builder/components/fad_provider_card');
    expect(File::get(resource_path('views/search/cards/_posts.antlers.html')))
        ->toContain('partial:posts/card');
    expect(File::get(resource_path('views/search/cards/_clinical_locations.antlers.html')))
        ->toContain('partial:clinical_locations/card');
    expect(File::get(resource_path('views/search/cards/_clinical_resources.antlers.html')))
        ->toContain('partial:clinical_resources/card');
    // Areas of Expertise get their own card (UAMS-Web/uams-statamic-archive#1510), through the shared compact card.
    expect(File::get(resource_path('views/search/cards/_clinical_expertise.antlers.html')))
        ->toContain('partial:search/cards/compact');
    // The generic fallback renders the compact card, which escapes editor/importer
    // content (Antlers output is raw by default).
    expect(File::get(resource_path('views/search/cards/_default.antlers.html')))
        ->toContain('partial:search/cards/compact');
    expect(File::get(resource_path('views/search/cards/_compact.antlers.html')))
        ->toContain('title | strip_tags | entities')
        ->toContain('excerpt | strip_tags');
    // Each hit renders through the shared result row, which dispatches it.
    expect($listing)->toContain('partial:search/result');
    // Per-type dispatch with a generic fallback, in the row.
    expect(File::get(resource_path('views/search/_result.antlers.html')))
        ->toContain('search/cards/{collection}')
        ->toContain('search/cards/default')
        ->toContain("collection == 'clinical_expertise'");
});

it('renders the shared pagination only when there is more than one page', function (): void {
    $pagination = File::get(resource_path('views/components/_pagination.antlers.html'));

    expect($pagination)
        ->toContain('paginate:total_pages > 1')
        ->toContain('class="pagination"')
        ->toContain('page-link');
});

it('renders the search form at /search when no query is present', function (): void {
    if (! Collection::findByHandle('pages')) {
        ContentSubmodule::reportMissing('the layout render');
    }

    // Pest binds `$this` to the test case at runtime, but PHPStan infers it as a
    // `TestCall`, so narrow the disabled-Vite test case before driving the request.
    /** @var TestCase $test */
    $test = $this->withoutVite();
    /** @var string $html */
    $html = $test->get('/search')->assertOk()->getContent();

    // Form and type options render; the pre-query prompt shows instead of a list.
    expect($html)
        ->toContain('action="/search"')
        ->toContain('All results')
        ->toContain('Clinical Areas of Expertise')
        ->toContain('Enter a search term')
        // The `published => true` route prop keeps the routeless view out of the
        // draft state the shared `layout/article` would otherwise apply.
        ->toContain('status-publish')
        // The `title_options => 'normal'` route prop renders the page-header `h1`.
        ->toContain('<h1>Search</h1>');
    expect($html)->not->toContain('status-draft');
});

it('fails closed on an array-valued query instead of crashing with a 500', function (): void {
    // A tampered `?q[]=x` arrives as an array; piping it through `sanitize` /
    // `urlencode` throws an uncaught TypeError (HTTP 500). The page must coerce it to
    // '' and render the pre-query prompt rather than 500ing.
    $html = (string) view('search/index', ['get' => ['q' => ['x']]])->render();

    expect($html)
        ->toContain('Enter a search term');
    expect($html)->not->toContain('Search results for');
});

it('escapes a hostile query rather than reflecting it as live markup', function (): void {
    // A full HTTP `?q=` request does not populate the Antlers `get` cascade under
    // the test harness, so render the page view with a hostile query injected into
    // `get` directly. This targets the template's own `| sanitize` on the reflected
    // query (the input value and the results heading) rather than relying on the
    // cascade's implicit sanitization — so it goes red if `| sanitize` is dropped.
    // The request query is empty, so `search:results` short-circuits to no-results
    // and no index build is needed.
    $payload = '<script>alert(1)</script>';

    $html = (string) view('search/index', ['get' => ['q' => $payload]])->render();

    // The raw payload must never appear; it is reflected HTML-escaped instead.
    expect($html)
        ->not->toContain($payload)
        ->toContain('&lt;script&gt;');
});

/**
 * Render the /search view with `$get` injected into the Antlers `get` cascade (a full
 * HTTP request does not populate it under the harness; see the hostile-query test).
 *
 * @param  array<string, mixed>  $get
 */
function renderSiteSearch(array $get): string
{
    return (string) view('search/index', ['get' => $get])->render();
}

/**
 * The typed-tab bar (UAMS-Web/uams-statamic-archive#1524) only, so an assertion about a tab cannot be satisfied by
 * the same string appearing elsewhere on the page.
 */
function siteSearchTabs(string $html): string
{
    preg_match('~<nav class="site-search-tabs.*?</nav>~s', $html, $matches);
    $tabs = $matches[0] ?? '';

    expect($tabs)->not->toBe('');

    return $tabs;
}

it('renders one link per launch scope, each carrying the encoded query (#1524)', function (): void {
    $tabs = siteSearchTabs(renderSiteSearch(['q' => 'heart care', 'index' => 'people']));

    expect(substr_count($tabs, 'class="nav-link'))->toBe(6)
        ->and($tabs)
        ->toContain('href="/search?q=heart+care&amp;index=default"')
        ->toContain('href="/search?q=heart+care&amp;index=people"')
        ->toContain('href="/search?q=heart+care&amp;index=clinical_expertise"')
        ->toContain('href="/search?q=heart+care&amp;index=clinical_locations"')
        ->toContain('href="/search?q=heart+care&amp;index=clinical_resources"')
        ->toContain('href="/search?q=heart+care&amp;index=posts"')
        // Clinical Providers is deferred to UAMS-Web/uams-statamic-archive#919, so it is not a tab.
        ->not->toContain('Clinical Providers');
});

it('marks exactly the resolved scope as the current tab (#1524)', function (?string $requested, string $active, string $label): void {
    $html = renderSiteSearch(array_filter(['q' => 'heart', 'index' => $requested]));
    $tabs = siteSearchTabs($html);

    expect(substr_count($tabs, 'aria-current="page"'))->toBe(1)
        ->and(substr_count($tabs, ' active"'))->toBe(1)
        ->and($tabs)->toContain('index='.$active.'" aria-current="page">'.$label.'</a>')
        // The form carries the same scope, so a refined query stays on this tab.
        ->and($html)->toContain('<input type="hidden" name="index" value="'.$active.'">');
})->with([
    'no index' => [null, 'default', 'All results'],
    'people' => ['people', 'people', 'People'],
    'news' => ['posts', 'posts', 'News'],
    'tampered index falls back' => ['users', 'default', 'All results'],
    'unscoped knowledge base falls back' => ['knowledge_bases', 'default', 'All results'],
]);

it('omits the query from the tab links before anything is searched (#1524)', function (): void {
    $tabs = siteSearchTabs(renderSiteSearch([]));

    expect($tabs)
        ->toContain('href="/search?index=people"')
        ->not->toContain('q=');
});

it('url-encodes a hostile query in the tab links rather than breaking out of the href (#1524)', function (): void {
    $tabs = siteSearchTabs(renderSiteSearch(['q' => '"><b>x</b>']));

    expect($tabs)
        ->toContain('href="/search?q=%22%3E%3Cb%3Ex%3C%2Fb%3E&amp;index=people"')
        ->not->toContain('"><b>');
});

it('hides the typed tabs in knowledge-base mode, which is already scoped (#1524)', function (): void {
    $html = renderSiteSearch(['q' => 'heart', 'index' => 'knowledge_bases', 'kb_scope' => 'root-id']);

    expect($html)
        ->not->toContain('site-search-tabs')
        ->toContain('<input type="hidden" name="index" value="knowledge_bases">');
});

it('dispatches the query to the index the selected tab names (#1524)', function (?string $requested, string $expected): void {
    // `search:results` reads the query from the request, not the `get` cascade.
    request()->query->set('q', 'heart');

    // Stop at the dispatch: the index handle the tag asks for is the whole assertion,
    // so no index build is needed and nothing after it runs.
    Search::shouldReceive('index')
        ->once()
        ->with($expected)
        ->andThrow(new RuntimeException('dispatched to '.$expected));

    expect(fn (): string => renderSiteSearch(array_filter(['q' => 'heart', 'index' => $requested])))
        ->toThrow('dispatched to '.$expected);
})->with([
    'no index' => [null, 'default'],
    'people' => ['people', 'people'],
    'clinical_expertise' => ['clinical_expertise', 'clinical_expertise'],
    'clinical_locations' => ['clinical_locations', 'clinical_locations'],
    'clinical_resources' => ['clinical_resources', 'clinical_resources'],
    'posts' => ['posts', 'posts'],
    'tampered index falls back' => ['users', 'default'],
]);

/**
 * Render one search-result row (UAMS-Web/uams-statamic-archive#1510) for a hit carrying `$data`.
 *
 * @param  array<string, mixed>  $data
 */
function renderSearchResultRow(array $data): string
{
    return (string) view('search/_result', $data)->render();
}

it('renders an Area of Expertise hit through its own card under a type label (#1510)', function (): void {
    $html = renderSearchResultRow([
        'collection' => 'clinical_expertise',
        'title' => 'Heart <b>Care</b>',
        'url' => '/heart-care',
        'excerpt' => 'Diagnosis and treatment of the heart.',
        'parent_expertise_name' => 'Cardiovascular',
        'parent_expertise_url' => '/cardiovascular',
    ]);

    expect($html)
        ->toContain('<p class="search-result-type text-uppercase text-muted small mb-1">Area of Expertise</p>')
        ->toContain('class="card search-card h-100"')
        ->toContain('<a href="/heart-care">Heart Care</a>')
        // A child Area names its parent, and the whole-card link is dropped so the
        // parent link stays clickable.
        ->toContain('Part of <a href="/cardiovascular">Cardiovascular</a>')
        ->toContain('Diagnosis and treatment of the heart.');
    expect($html)
        ->not->toContain('stretched-link')
        ->not->toContain('<b>Care</b>');
});

it('renders a page through the generic card with a whole-card link and a trimmed excerpt (#1510)', function (): void {
    $html = renderSearchResultRow([
        'collection' => 'pages',
        'title' => 'Vision',
        'url' => '/about/vision',
        'excerpt' => '<p>'.str_repeat('word ', 80).'</p>',
    ]);

    expect($html)
        ->toContain('>Page</p>')
        ->toContain('<a href="/about/vision" class="stretched-link">Vision</a>')
        // Tags stripped and cut on a word boundary, with an ellipsis.
        ->toContain('word word&hellip;');
    expect($html)
        ->not->toContain('<p>word')
        ->not->toContain('Part of');
});

it('labels each dispatched type, and leaves an unmapped type unlabeled on the generic card (#1510)', function (string $key, string $value, ?string $label): void {
    // `collection` for an entry, `result_type` for a synthetic section hit.
    $html = renderSearchResultRow([$key => $value, 'title' => 'T', 'url' => '/t']);

    if ($label === null) {
        expect($html)
            ->not->toContain('search-result-type')
            ->toContain('class="card search-card h-100"');

        return;
    }

    expect($html)->toContain('small mb-1">'.$label.'</p>');
})->with([
    'knowledge base' => ['collection', 'knowledge_bases', 'Knowledge Base'],
    'page' => ['collection', 'pages', 'Page'],
    'area of expertise section' => ['result_type', 'clinical_expertise_section', 'Area of Expertise'],
    'unmapped collection' => ['collection', 'calculators', null],
]);

it('escapes an Area-of-Expertise section title in the compact card (#1527, #1510)', function (): void {
    $html = renderSearchResultRow([
        'result_type' => 'clinical_expertise_section',
        'title' => 'Cancer <script>x</script> — Locations',
        'url' => '/cancer-care/locations',
    ]);

    expect($html)
        ->toContain('<a href="/cancer-care/locations" class="stretched-link">')
        ->not->toContain('<script>');
});

it('links a scoped empty result to the same query across all results (#1510)', function (): void {
    // The request query is empty, so `search:results` short-circuits to no results
    // and the empty state renders without an index build. The query is given as the
    // cascade delivers it in production: HTML-escaped (Statamic\View\Cascade).
    $html = renderSiteSearch(['q' => e('heart & soul'), 'index' => 'people']);

    expect($html)->toContain('or <a href="/search?q=heart+%26+soul&amp;index=default">search all results</a>.');
});

it('decodes the cascade-escaped query before encoding it into every link (#1510)', function (string $query, string $encoded): void {
    // `renderSiteSearch()` hands `get` over raw, while the live cascade HTML-escapes
    // it first; `e()` reproduces that, so encoding the escaped form directly would
    // turn `O'Brien` into `O%26%23039%3BBrien` here as it did on the live page.
    $html = renderSiteSearch(['q' => e($query), 'index' => 'people']);
    $kb = renderSiteSearch(['q' => e($query), 'index' => 'knowledge_bases', 'kb_scope' => 'abc']);

    expect(siteSearchTabs($html))->toContain('href="/search?q='.$encoded.'&amp;index=default"');
    expect($html)->toContain('<a href="/search?q='.$encoded.'&amp;index=default">search all results</a>');
    expect($kb)->toContain('<a href="/search?q='.$encoded.'">Search the whole site</a>');
    expect($html)->not->toContain('%26%23039%3B')->not->toContain('%26amp%3B');
})->with([
    'an apostrophe' => ["O'Brien", 'O%27Brien'],
    'an ampersand' => ['heart & soul', 'heart+%26+soul'],
    'a quote and angle brackets' => ['"><b>x</b>', '%22%3E%3Cb%3Ex%3C%2Fb%3E'],
]);

it('offers no all-results link when the search was already unscoped (#1510)', function (): void {
    $html = renderSiteSearch(['q' => 'heart', 'index' => 'default']);

    expect($html)
        ->toContain('No results found. Try a different search term.')
        ->not->toContain('search all results');
});

it('labels the search field visibly and keeps the button from wrapping (#1510, #815)', function (): void {
    $html = renderSiteSearch([]);

    expect($html)
        ->toContain('<label for="site-search-q" class="site-search-label font-weight-bold">Search UAMS</label>')
        ->toContain('btn btn-primary btn-lg btn-block text-nowrap')
        ->toContain('You can search people, areas of expertise');
});

it('names the knowledge base, not the site, in knowledge-base mode (#1510)', function (): void {
    $html = renderSiteSearch(['index' => 'knowledge_bases', 'kb_scope' => 'abc']);

    expect($html)
        ->toContain('font-weight-bold">Search this knowledge base</label>')
        ->toContain('Enter a search term above to get started.')
        ->not->toContain('You can search people');
});

it('renders a person as a compact card with their name, clinical title and a small portrait (#1510)', function (): void {
    $html = renderSearchResultRow([
        'collection' => 'people',
        'title' => 'Christina L. Clark, <i>MBA</i>',
        'url' => '/people/christina-l-clark',
        'clinical_title' => 'Director & <b>Lead</b>',
        'portrait' => ['url' => '/assets/portraits/clark.jpg'],
    ]);

    expect($html)
        ->toContain('small mb-1">Person</p>')
        // The name comes from the entry title, which carries the credentials; the
        // directory card read a computed display name that search hits leave empty.
        ->toContain('<a href="/people/christina-l-clark" class="stretched-link">Christina L. Clark, MBA</a>')
        ->toContain('<p class="card-subtitle text-muted mb-1">Director &amp; Lead</p>')
        ->toContain('search-card-portrait');
    // Not the directory card, whose full-height portrait slot filled the row.
    expect($html)
        ->not->toContain('<span class="name">')
        ->not->toContain('<i>MBA</i>');
});

it('gives a person without a portrait no image rather than a placeholder (#1510)', function (): void {
    $html = renderSearchResultRow([
        'collection' => 'people',
        'title' => 'David Wise',
        'url' => '/people/david-wise',
    ]);

    expect($html)
        ->toContain('class="stretched-link">David Wise</a>')
        ->not->toContain('search-card-thumb')
        ->not->toContain('No Image Available')
        ->not->toContain('card-subtitle');
});
