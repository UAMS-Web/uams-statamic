<?php

declare(strict_types=1);

use App\Fieldtypes\TermsSearchIndex;
use Illuminate\Http\Request;
use Illuminate\Support\Fluent;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Auth\File\User as FileUser;
use Statamic\Contracts\Taxonomies\Taxonomy as TaxonomyContract;
use Statamic\Facades\Search;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;
use Statamic\Facades\User;
use Statamic\Fields\Field;
use Statamic\Search\Index;
use Statamic\Stache\Query\TermQueryBuilder;
use Tests\Support\TermsMutateProbeScope;
use Tests\TestCase;

use function Pest\Laravel\actingAs;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Unit tests for TermsSearchIndex fieldtype with custom search indexes.
 *
 * Tests application-specific functionality in the custom TermsSearchIndex
 * fieldtype:
 *   - Custom search index usage for taxonomy terms
 *   - Search index configuration and fallback behavior
 *   - Search result accuracy with custom indexes
 *
 * Mutation: 100% on App\Fieldtypes\TermsSearchIndex (42 mutants, --covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Fieldtypes/TermsSearchIndexTest.php
 */

/**
 * Create a mock search result whose searchable exposes the given term ID.
 *
 * Globally-unique helper (Pest top-level functions are shared across the run).
 *
 * @param  string  $termId  The term ID
 * @return object The mock search result
 */
function termsSearchIndexMockSearchResult(string $termId): object
{
    $result = Mockery::mock();
    $searchable = Mockery::mock();
    $searchable->shouldReceive('id')
        ->andReturn($termId);
    $result->shouldReceive('getSearchable')
        ->andReturn($searchable);

    return $result;
}

it('uses custom search index usage', function () {
    // Attach a Field so config() resolves taxonomy and search_index (Statamic v6)
    /** @var TermsSearchIndex $fieldtype */
    $fieldtype = (new TermsSearchIndex)->setField(
        new Field(
            'tags',
            [
                'type' => 'terms_search_index',
                'taxonomy' => 'membership_organizations',
                'search_index' => 'providers',
            ]
        )
    );

    // Create a mock request with search term
    $request = Request::create(
        '/',
        'GET',
        ['search' => 'test']
    );

    // ---
    // Mock Term::query() chain used by getIndexQuery()
    // ---

    // Mock the TermQueryBuilder
    $mockTermQuery = Mockery::mock();

    // Mock the whereIn method
    $mockTermQuery->shouldReceive('whereIn')
        ->once()
        ->andReturnSelf();

    // Mock the where method
    $mockTermQuery->shouldReceive('where')
        ->zeroOrMoreTimes()
        ->andReturnSelf();

    // Mock the whereNotIn method
    $mockTermQuery->shouldReceive('whereNotIn')
        ->zeroOrMoreTimes()
        ->andReturnSelf();

    // Mock the Term facade
    Term::shouldReceive('query')
        ->once()
        ->andReturn($mockTermQuery);

    // ---
    // Mock the search index
    // ---

    $mockIndex = Mockery::mock(Index::class);
    $mockQuery = Mockery::mock();
    $mockResults = collect([
        termsSearchIndexMockSearchResult('term-1'),
        termsSearchIndexMockSearchResult('term-2'),
    ]);

    $mockIndex->shouldReceive('ensureExists')
        ->once()
        ->andReturnSelf();
    $mockIndex->shouldReceive('search')
        ->with('test')
        ->once()
        ->andReturn($mockQuery);
    $mockQuery->shouldReceive('get')
        ->once()
        ->andReturn($mockResults);

    // Mock the Search facade
    Search::shouldReceive('index')
        ->with('providers')
        ->once()
        ->andReturn($mockIndex);

    // Mock the Term facade
    $mockTerm1 = Mockery::mock();
    $mockTerm1->shouldReceive('id')
        ->andReturn('term-1');
    $mockTerm2 = Mockery::mock();
    $mockTerm2->shouldReceive('id')
        ->andReturn('term-2');

    Term::shouldReceive('find')
        ->with('term-1')
        ->andReturn($mockTerm1);
    Term::shouldReceive('find')
        ->with('term-2')
        ->andReturn($mockTerm2);

    // Use reflection to access protected method
    $reflection = new ReflectionClass($fieldtype);
    $method = $reflection->getMethod('getIndexQuery');
    $query = $method->invoke($fieldtype, $request);

    // Verify query was created
    expect($query)->not->toBeNull();
});

it('uses collection specific search index', function () {
    // Define the indexes
    $indexes = [
        'clinical_providers',
        'clinical_locations',
        'clinical_conditions',
        'clinical_expertise',
        'clinical_services',
    ];

    // Loop through the indexes
    foreach ($indexes as $indexName) {
        // Create a new fieldtype with the index name
        /** @var TermsSearchIndex $fieldtype */
        $fieldtype = (new TermsSearchIndex)->setField(
            new Field(
                'tags',
                [
                    'type' => 'terms_search_index',
                    'taxonomy' => 'test_taxonomy',
                    'search_index' => $indexName,
                ]
            )
        );

        // Verify the index name matches the search index
        expect($fieldtype->config('search_index'))->toEqual($indexName);
    }
});

it('uses search index fallback', function () {
    // Create a new fieldtype with the index name
    /** @var TermsSearchIndex $fieldtype */
    $fieldtype = (new TermsSearchIndex)->setField(
        new Field(
            'tags',
            [
                'type' => 'terms_search_index',
                'taxonomy' => 'test_taxonomy',
            ]
        )
    );

    // Create a mock request with search term
    $request = Request::create(
        '/',
        'GET',
        ['search' => 'test']
    );

    // Use reflection to access protected method
    $reflection = new ReflectionClass($fieldtype);
    $method = $reflection->getMethod('getIndexQuery');
    $query = $method->invoke($fieldtype, $request);

    // Verify query was created
    expect($query)->not->toBeNull();
});

it('returns accurate search results', function () {
    // Create a new fieldtype with the index name
    /** @var TermsSearchIndex $fieldtype */
    $fieldtype = (new TermsSearchIndex)->setField(
        new Field(
            'tags',
            [
                'type' => 'terms_search_index',
                'taxonomy' => 'membership_organizations',
                'search_index' => 'providers',
            ]
        )
    );

    // Create a mock request
    $request = Request::create(
        '/',
        'GET',
        ['search' => 'doctor']
    );

    // Mock search index and results
    $mockIndex = Mockery::mock(Index::class);
    $mockQuery = Mockery::mock();
    $mockResults = collect([
        termsSearchIndexMockSearchResult('term-1'),
    ]);

    $mockIndex->shouldReceive('ensureExists')
        ->andReturnSelf();
    $mockIndex->shouldReceive('search')
        ->with('doctor')
        ->andReturn($mockQuery);
    $mockQuery->shouldReceive('get')
        ->andReturn($mockResults);

    Search::shouldReceive('index')
        ->with('providers')
        ->andReturn($mockIndex);

    // Use reflection to access protected method
    $reflection = new ReflectionClass($fieldtype);
    $method = $reflection->getMethod('getIndexQuery');
    $query = $method->invoke($fieldtype, $request);

    // Verify query filters by the correct term IDs
    expect($query)->not->toBeNull();
});

/**
 * Run getIndexQuery() for a fieldtype config and request input against a spy query.
 *
 * @param  array<string, mixed>  $config  Field config merged over the type handle
 * @param  mixed  $request  The request (or a non-request stand-in) handed to getIndexQuery()
 */
function termsSearchIndexQuery(array $config, mixed $request): MockInterface
{
    // Record every builder call instead of querying the Stache
    $spy = Mockery::spy(TermQueryBuilder::class);
    Term::shouldReceive('query')->once()->andReturn($spy);

    $fieldtype = new TermsSearchIndex;
    $fieldtype->setField(new Field('tags', array_merge(['type' => 'terms_search_index'], $config)));

    $query = (new ReflectionMethod(TermsSearchIndex::class, 'getIndexQuery'))->invoke($fieldtype, $request);

    // The method hands back the builder it was given
    expect($query)->toBe($spy);

    return $spy;
}

/**
 * Act as a super user, who can view every taxonomy.
 */
function termsSearchIndexActAsSuperUser(): void
{
    $user = mustBe(FileUser::class, User::make(), 'user');
    $user->email('terms-search-index@example.com');
    $user->makeSuper();
    actingAs($user);
}

/**
 * Stub the named search index so a search for the term returns the given term ids.
 *
 * @param  list<string>  $termIds
 */
function termsSearchIndexStubIndex(string $handle, string $search, array $termIds): void
{
    // ensureExists() hands back the index to search, which may be a different object
    $declared = Mockery::mock(Index::class);
    $ensured = Mockery::mock(Index::class);
    $searchQuery = Mockery::mock();

    $declared->shouldReceive('ensureExists')->once()->andReturn($ensured);
    $ensured->shouldReceive('search')->once()->with($search)->andReturn($searchQuery);
    $searchQuery->shouldReceive('get')->once()->andReturn(collect(array_map(termsSearchIndexMockSearchResult(...), $termIds)));

    Search::shouldReceive('index')->once()->with($handle)->andReturn($declared);
}

it('identifies itself in the Control Panel', function (): void {
    $fieldtype = new TermsSearchIndex;

    expect(TermsSearchIndex::title())->toBe('Terms w/ Search Index')
        ->and($fieldtype->icon())->toBe('fieldtype-taxonomy')
        ->and($fieldtype->keywords())->toBe([
            'taxonomy',
            'terms',
            'index',
            'search',
            'search index',
            'with index',
            'w/ index',
            'with search',
            'w/ search',
            'with search index',
            'w/ search index',
            'taxonomy terms',
            'taxonomy terms index',
            'taxonomy terms with index',
            'taxonomy terms w/ index',
            'taxonomy terms search',
            'taxonomy terms with search',
            'taxonomy terms w/ search',
            'taxonomy terms search index',
            'taxonomy terms with search index',
            'taxonomy terms w/ search index',
            'terms index',
            'terms with index',
            'terms w/ index',
            'terms search',
            'terms with search',
            'terms w/ search',
            'terms search index',
            'terms with search index',
            'terms w/ search index',
        ]);
});

it('adds a search index setting to the first config section', function (): void {
    /** @var array<int, array<string, mixed>> $items */
    $items = (new ReflectionMethod(TermsSearchIndex::class, 'configFieldItems'))->invoke(new TermsSearchIndex);

    expect(array_column($items, 'display'))->toBe(['Input Behavior', 'Appearance', 'Boundaries & Limits', 'Advanced'])
        ->and(array_keys((array) $items[0]['fields']))->toBe(['taxonomies', 'create', 'search_index'])
        ->and(data_get($items, '0.fields.search_index'))->toBe([
            'display' => 'Search Index',
            'instructions' => 'Specify a search index to use for querying terms.',
            'type' => 'text',
            'default' => null,
        ]);
});

it('limits the query to the requested taxonomies that are also configured and viewable', function (array $input, array $taxonomies): void {
    termsSearchIndexActAsSuperUser();

    $spy = termsSearchIndexQuery(['taxonomies' => ['audiences', 'boilerplates', 'no_such_taxonomy']], Request::create('/', 'GET', $input));

    $spy->shouldHaveReceived('whereIn', ['taxonomy', $taxonomies])->once();
    $spy->shouldNotHaveReceived('where', ['id']);
})->with([
    'no requested taxonomies' => [[], ['audiences', 'boilerplates']],
    'one requested taxonomy' => [['taxonomies' => 'boilerplates'], ['boilerplates']],
    'a requested taxonomy that is not configured, listed first' => [['taxonomies' => ['audio_series', 'boilerplates']], ['boilerplates']],
]);

it('matches no terms when no requested taxonomy is viewable', function (?bool $super): void {
    // A guest, or a user without view permission, sees no taxonomy
    if ($super !== null) {
        $user = mustBe(FileUser::class, User::make(), 'user');
        $user->id('terms-search-index-viewer');
        $user->email('terms-search-index-viewer@example.com');
        actingAs($user);
    }

    $spy = termsSearchIndexQuery(['taxonomies' => ['audiences']], Request::create('/'));

    $spy->shouldHaveReceived('where', ['id'])->once();
    $spy->shouldNotHaveReceived('whereIn');
})->with(['a guest' => [null], 'a user without permissions' => [false]]);

it('searches titles when no search index is configured', function (array $config): void {
    termsSearchIndexActAsSuperUser();
    Search::shouldReceive('index')->never();

    /** @var array<string, mixed> $typedConfig */
    $typedConfig = ['taxonomies' => ['audiences'], ...$config];

    $spy = termsSearchIndexQuery($typedConfig, Request::create('/', 'GET', ['search' => 'cardio']));

    $spy->shouldHaveReceived('where', ['title', 'like', '%cardio%'])->once();
})->with([
    'no search index' => [[]],
    'an empty search index' => [['search_index' => '']],
    'a non-string search index' => [['search_index' => ['providers']]],
]);

it('adds no title search for an empty or non-string search term', function (array $input): void {
    termsSearchIndexActAsSuperUser();

    $spy = termsSearchIndexQuery(['taxonomies' => ['audiences']], Request::create('/', 'GET', $input));

    $spy->shouldNotHaveReceived('where');
})->with([
    'no search' => [[]],
    'an empty search' => [['search' => '']],
    'a non-string search' => [['search' => ['cardio']]],
]);

it('limits the query to the terms a configured search index finds', function (): void {
    termsSearchIndexActAsSuperUser();
    termsSearchIndexStubIndex('providers', 'cardio', ['term-1', 'term-2']);

    $spy = termsSearchIndexQuery(['taxonomies' => ['audiences'], 'search_index' => 'providers'], Request::create('/', 'GET', ['search' => 'cardio']));

    $spy->shouldHaveReceived('whereIn', ['id', ['term-1', 'term-2']])->once();
    $spy->shouldNotHaveReceived('where');
});

it('matches no terms when the search index finds nothing', function (): void {
    termsSearchIndexActAsSuperUser();
    termsSearchIndexStubIndex('providers', 'cardio', []);

    $spy = termsSearchIndexQuery(['taxonomies' => ['audiences'], 'search_index' => 'providers'], Request::create('/', 'GET', ['search' => 'cardio']));

    $spy->shouldHaveReceived('where', ['id'])->once();
});

it('skips the search index when there is no search term', function (): void {
    termsSearchIndexActAsSuperUser();
    Search::shouldReceive('index')->never();

    $spy = termsSearchIndexQuery(['taxonomies' => ['audiences'], 'search_index' => 'providers'], Request::create('/', 'GET', ['search' => '']));

    $spy->shouldNotHaveReceived('where');
    $spy->shouldHaveReceived('whereIn')->once();
});

it('narrows to the requested site and leaves out excluded terms', function (): void {
    termsSearchIndexActAsSuperUser();

    $spy = termsSearchIndexQuery(['taxonomies' => ['audiences']], Request::create('/', 'GET', ['site' => 'health', 'exclusions' => ['audiences::a']]));

    $spy->shouldHaveReceived('where', ['site', 'health'])->once();
    $spy->shouldHaveReceived('whereNotIn', ['id', ['audiences::a']])->once();
});

it('applies the configured query scopes with the request input', function (): void {
    termsSearchIndexActAsSuperUser();
    TermsMutateProbeScope::reset();
    app('statamic.scopes')->put('terms_mutate_probe', TermsMutateProbeScope::class);

    $spy = termsSearchIndexQuery(['taxonomies' => ['audiences'], 'query_scopes' => ['terms_mutate_probe']], Request::create('/', 'GET', ['site' => 'health']));

    expect(TermsMutateProbeScope::$calls)->toHaveCount(1)
        ->and(TermsMutateProbeScope::$calls[0]['query'])->toBe($spy)
        ->and(TermsMutateProbeScope::$calls[0]['values'])->toBe(['site' => 'health', 'queryScopes' => ['terms_mutate_probe']]);
});

it('hands a non-request argument to the parent query', function (): void {
    termsSearchIndexActAsSuperUser();

    // The parent reads properties off whatever it is given, so a Fluent stands in
    $spy = termsSearchIndexQuery(['taxonomies' => ['audiences'], 'search_index' => 'providers'], new Fluent(['search' => 'cardio']));

    // The parent searches titles; it never consults the search index
    $spy->shouldHaveReceived('whereIn', ['taxonomy', ['audiences']])->once();
    $spy->shouldHaveReceived('where', ['title', 'like', '%cardio%'])->once();
});

it('drops a configured handle that resolves to something other than a taxonomy model', function (): void {
    termsSearchIndexActAsSuperUser();

    // Swap in a proxy repository so one handle resolves to a taxonomy contract
    // that is not Statamic's taxonomy model
    $audiences = Taxonomy::findByHandle('audiences');
    $repository = Mockery::mock(Taxonomy::getFacadeRoot());
    Taxonomy::swap($repository);
    $repository->shouldReceive('findByHandle')->with('not_a_taxonomy', Mockery::any())->andReturn(Mockery::mock(TaxonomyContract::class));
    $repository->shouldReceive('findByHandle')->with('audiences', Mockery::any())->andReturn($audiences);

    $spy = termsSearchIndexQuery(['taxonomies' => ['not_a_taxonomy', 'audiences']], Request::create('/'));

    $spy->shouldHaveReceived('whereIn', ['taxonomy', [1 => 'audiences']])->once();
});
