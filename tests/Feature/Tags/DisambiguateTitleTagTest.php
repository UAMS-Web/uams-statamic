<?php

declare(strict_types=1);

use App\Listeners\SetCurrentEntryForTitleFormat;
use App\Services\TitleDisambiguator;
use App\Tags\DisambiguateTitle;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Collection as StatamicCollection;
use Statamic\Entries\Entry;
use Statamic\Events\EntrySaving;
use Statamic\Facades\Blink;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\Site;
use Statamic\Fields\Value;
use Statamic\Sites\Site as StatamicSite;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Feature tests for the {{ disambiguate_title }} paired tag.
 *
 * Mutation: on App\Tags\DisambiguateTitle (--covered-only); see the class for annotated
 * equivalents. The entry branch at the top of resolveCollectionHandle() is not covered:
 * index() returns through suffixForEntry() whenever the entry has a collection, so
 * resolveCollectionHandle() never receives one.
 *
 * @command  php artisan test --compact tests/Feature/Tags/DisambiguateTitleTagTest.php
 */
it('returns empty when parsed base title empty', function () {
    $tag = new DisambiguateTitle;
    $tag->setContent('   ');
    $tag->setContext([]);
    $tag->setParameters([]);

    expect($tag->index())->toBe('');
});

it('appends suffix for resolved entry', function () {
    $collection = Mockery::mock(StatamicCollection::class);
    $collection->shouldReceive('handle')->andReturn('news');

    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('collection')->andReturn($collection);

    EntryFacade::shouldReceive('find')
        ->with('entry-1')
        ->andReturn($entry);

    $disambiguator = Mockery::mock(TitleDisambiguator::class);
    $disambiguator->shouldReceive('suffixForEntry')
        ->once()
        ->andReturn(' (2)');
    app()->instance(TitleDisambiguator::class, $disambiguator);

    $tag = new DisambiguateTitle;
    $tag->setContent('Headline');
    $tag->setContext([
        'id' => 'entry-1',
    ]);
    $tag->setParameters([]);

    expect($tag->index())->toBe('Headline (2)');
});

/**
 * Run the tag with the given content (a pair when non-empty) and context.
 *
 * @param  array<string, mixed>  $context
 */
function disambiguateTitle(string $content, array $context = []): string
{
    $tag = new DisambiguateTitle;
    $tag->setContent($content);
    $tag->setContext($context);
    $tag->setParameters([]);

    return $tag->index();
}

/**
 * Bind a disambiguator that expects one collection-and-site lookup and returns `$suffix`.
 */
function expectCollectionSuffix(string $collection, ?string $site, string $base, string $suffix = ' (3)'): void
{
    $disambiguator = Mockery::mock(TitleDisambiguator::class);
    $disambiguator->shouldReceive('suffixForEntry')->never();
    $disambiguator->shouldReceive('suffixForCollectionAndSite')->once()->with($collection, $site, $base)->andReturn($suffix);
    app()->instance(TitleDisambiguator::class, $disambiguator);
}

/**
 * Bind a disambiguator that must not be asked for any suffix.
 */
function expectNoSuffixLookup(): void
{
    $disambiguator = Mockery::mock(TitleDisambiguator::class);
    $disambiguator->shouldReceive('suffixForEntry')->never();
    $disambiguator->shouldReceive('suffixForCollectionAndSite')->never();
    app()->instance(TitleDisambiguator::class, $disambiguator);
}

/**
 * Make `$request` the current request, matched to a route with the given parameters.
 *
 * @param  array<mixed>  $parameters  Route parameter name => value
 * @param  array<mixed>  $input  Query input
 */
function disambiguateTitleRequest(string $uri, array $parameters = [], array $input = []): void
{
    $request = Request::create($uri, 'GET', $input);
    $route = new Route('GET', ltrim($uri, '/'), fn () => null);
    $route->bind($request);
    foreach ($parameters as $name => $value) {
        $route->setParameter((string) $name, \is_string($value) ? $value : null);
    }
    $request->setRouteResolver(fn () => $route);
    app()->instance('request', $request);
}

/**
 * An entry double with the given collection handle (or no collection) and site handle (or no site).
 */
function disambiguateTitleEntry(mixed $collectionHandle, mixed $siteHandle = null): Entry
{
    $entry = Mockery::mock(Entry::class);

    if ($collectionHandle === false) {
        $entry->shouldReceive('collection')->andReturnNull();
    } else {
        $collection = Mockery::mock(StatamicCollection::class);
        $collection->shouldReceive('handle')->andReturn($collectionHandle);
        $entry->shouldReceive('collection')->andReturn($collection);
    }

    if ($siteHandle === null) {
        $entry->shouldReceive('site')->andReturnNull();
    } else {
        $site = Mockery::mock(StatamicSite::class);
        $site->shouldReceive('handle')->andReturn($siteHandle);
        $entry->shouldReceive('site')->andReturn($site);
    }

    return $entry;
}

it('uses the context title as the base when used as a single tag', function (mixed $title, string $expected) {
    // A collection is in reach, so only the single-tag return keeps the suffix away.
    disambiguateTitleRequest('/');
    expectNoSuffixLookup();

    expect(disambiguateTitle('', ['title' => $title, 'collection' => 'news']))->toBe($expected);
})->with([
    'string' => ['  Plain title  ', 'Plain title'],
    // Built directly: in a multi-value row Pest passes a closure to a `mixed` parameter
    // through uncalled.
    'value' => [new Value('Wrapped title'), 'Wrapped title'],
    'not a string' => [42, ''],
    'missing' => [null, ''],
]);

it('returns an empty string when the pair content parses to nothing', function () {
    disambiguateTitleRequest('/');
    expectNoSuffixLookup();

    expect(disambiguateTitle('{{ missing_variable }}', ['collection' => 'news']))->toBe('');
});

it('parses the pair content against the context', function () {
    disambiguateTitleRequest('/');
    expectCollectionSuffix('news', 'default', 'Hello World', '');

    expect(disambiguateTitle('  {{ greeting }} World  ', ['greeting' => 'Hello', 'collection' => 'news']))->toBe('Hello World');
});

it('resolves the entry from a Value-wrapped id', function () {
    $entry = disambiguateTitleEntry('news');
    // withArgs() is strict: with('entry-2') would also match the Value, whose string
    // form is the same.
    EntryFacade::shouldReceive('find')->withArgs(fn (mixed $id): bool => $id === 'entry-2')->andReturn($entry);
    $disambiguator = Mockery::mock(TitleDisambiguator::class);
    $disambiguator->shouldReceive('suffixForEntry')->once()->with($entry, 'Headline')->andReturn(' (4)');
    app()->instance(TitleDisambiguator::class, $disambiguator);

    expect(disambiguateTitle('Headline', ['id' => new Value('entry-2')]))->toBe('Headline (4)');
});

it('resolves the entry being saved when the context has no id', function (mixed $id) {
    $entry = disambiguateTitleEntry('news');
    (new SetCurrentEntryForTitleFormat)->handle(new EntrySaving($entry));
    $disambiguator = Mockery::mock(TitleDisambiguator::class);
    $disambiguator->shouldReceive('suffixForEntry')->once()->with($entry, 'Headline')->andReturn(' (5)');
    app()->instance(TitleDisambiguator::class, $disambiguator);

    try {
        expect(disambiguateTitle('Headline', ['id' => $id]))->toBe('Headline (5)');
    } finally {
        Blink::forget('disambiguate_title_current_entry');
    }
})->with([
    'no id' => [null],
    'empty id' => [''],
]);

it('falls back to the context collection when the id finds no entry', function () {
    disambiguateTitleRequest('/');
    EntryFacade::shouldReceive('find')->with('gone')->andReturnNull();
    expectCollectionSuffix('news', 'default', 'Headline');

    expect(disambiguateTitle('Headline', ['id' => 'gone', 'collection' => new Value('news')]))->toBe('Headline (3)');
});

it('uses the collection and site of an entry that has a collection handle but is looked up by scope', function () {
    // An entry whose collection() is falsy is not handed to suffixForEntry; the
    // collection then comes from the context, and the site from the entry.
    disambiguateTitleRequest('/');
    $entry = disambiguateTitleEntry(false, 'health');
    EntryFacade::shouldReceive('find')->with('orphan')->andReturn($entry);
    expectCollectionSuffix('pages', 'health', 'Headline');

    expect(disambiguateTitle('Headline', ['id' => 'orphan', 'collection' => 'pages']))->toBe('Headline (3)');
});

it('reads the collection from the route, the request input, then the CP url', function (string $uri, array $parameters, array $input, string $expected) {
    disambiguateTitleRequest($uri, $parameters, $input);
    expectCollectionSuffix($expected, 'default', 'Headline');

    expect(disambiguateTitle('Headline'))->toBe('Headline (3)');
})->with([
    'route parameter' => ['/cp/x', ['collection' => 'events'], [], 'events'],
    'request input' => ['/cp/x', [], ['collection' => 'people'], 'people'],
    'cp create url' => ['/cp/collections/locations/entries/create', [], [], 'locations'],
    'cp url ending at the handle' => ['/cp/collections/locations', [], [], 'locations'],
]);

it('returns the base title when no collection can be found', function (string $uri) {
    disambiguateTitleRequest($uri);
    expectNoSuffixLookup();

    expect(disambiguateTitle('Headline', ['collection' => '']))->toBe('Headline');
})->with([
    'no collections segment' => ['/cp/entries'],
    'collections is the last segment' => ['/cp/collections'],
]);

it('ignores an empty route collection and an empty context collection', function () {
    disambiguateTitleRequest('/cp/collections/blog/entries/create', ['collection' => '']);
    expectCollectionSuffix('blog', 'default', 'Headline');

    expect(disambiguateTitle('Headline', ['collection' => '']))->toBe('Headline (3)');
});

it('reads the site from the route parameter before the current site', function () {
    disambiguateTitleRequest('/cp/x', ['site' => 'health', 'collection' => 'news']);
    expectCollectionSuffix('news', 'health', 'Headline');

    expect(disambiguateTitle('Headline'))->toBe('Headline (3)');
});

it('ignores an empty route site', function () {
    disambiguateTitleRequest('/cp/x', ['site' => '', 'collection' => 'news']);
    expectCollectionSuffix('news', 'default', 'Headline');

    expect(disambiguateTitle('Headline'))->toBe('Headline (3)');
});

it('passes no site when there is no current site', function () {
    disambiguateTitleRequest('/cp/x', ['collection' => 'news']);
    Site::partialMock()->shouldReceive('current')->andReturnNull();
    expectCollectionSuffix('news', null, 'Headline');

    expect(disambiguateTitle('Headline'))->toBe('Headline (3)');
});

it('passes no site for a site handle that is not a string', function () {
    disambiguateTitleRequest('/cp/x', ['collection' => 'news']);
    $current = Mockery::mock(StatamicSite::class);
    $current->shouldReceive('handle')->andReturnNull();
    Site::partialMock()->shouldReceive('current')->andReturn($current);
    expectCollectionSuffix('news', null, 'Headline');

    expect(disambiguateTitle('Headline'))->toBe('Headline (3)');
});

it('passes no site when the entry site handle is not a string', function () {
    disambiguateTitleRequest('/');
    $entry = disambiguateTitleEntry(false, 7);
    EntryFacade::shouldReceive('find')->with('odd')->andReturn($entry);
    expectCollectionSuffix('pages', null, 'Headline');

    expect(disambiguateTitle('Headline', ['id' => 'odd', 'collection' => 'pages']))->toBe('Headline (3)');
});
