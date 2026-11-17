<?php

declare(strict_types=1);

// cspell:ignore cardiolgy

use App\Search\Loupe\SiteFilteredLoupeIndex;
use App\Search\Loupe\SiteFilteredLoupeQuery;
use Daun\StatamicLoupe\Loupe\Query as AddonLoupeQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Storage;
use Statamic\Assets\AssetContainer as StatamicAssetContainer;
use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Facades\Antlers;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Search;
use Statamic\Facades\Site;
use Statamic\Search\PlainResult;
use Statamic\Search\QueryBuilder;
use Tests\Support\StacheIsolatedTestCase;
use UAMSWeb\Aether\Search\Http\Controllers\SuggestionsController;

uses(StacheIsolatedTestCase::class);

/**
 * The site search on the `loupe` driver, end to end over real Loupe indexes (#646).
 *
 * Each test saves `people` entries into its own Stache and builds the indexes it searches from
 * them, so these run the driver, the site filter handed to Loupe and Statamic's `search:results`
 * tag together, with nothing stubbed.
 *
 * @command  php artisan test --compact tests/Feature/Search/LoupeSiteSearchTest.php
 */

/**
 * Save a published `people` entry on a site, without touching any search index.
 */
function loupeSearchPerson(string $site, string $slug, string $title): StatamicEntry
{
    /** @var StatamicEntry $entry */
    $entry = Entry::make();
    $entry->collection('people');
    $entry->locale($site);
    $entry->slug($slug);
    $entry->published(true);
    $entry->data(['title' => $title]);
    $entry->saveQuietly();

    return $entry;
}

/**
 * A search index on the `loupe` driver, rebuilt from this test's entries.
 */
function builtLoupeSearchIndex(string $handle): SiteFilteredLoupeIndex
{
    $index = mustBe(SiteFilteredLoupeIndex::class, Search::index($handle), 'the '.$handle.' search index');
    $index->update();

    return $index;
}

/**
 * The raw hits a search returns, without loading their entries.
 *
 * @param  QueryBuilder  $builder  The search, with any clauses already on it.
 * @param  list<string>|null  $sites  The sites to keep, or null for every site.
 * @return SupportCollection<array-key, mixed>
 */
function loupeRawHits(QueryBuilder $builder, ?array $sites = null): SupportCollection
{
    if ($sites !== null) {
        $builder->whereIn('site', $sites);
    }

    $builder->withoutData();

    return mustBe(SupportCollection::class, $builder->get(), 'the search hits');
}

/**
 * The titles a page of the shared results listing shows, as a reader on `second` sees it.
 *
 * @param  string  $searchSite  The partial's `search_site`: `second`, or `*` for every site.
 * @return list<string>
 */
function loupeListedTitles(string $index, string $query, string $searchSite): array
{
    Site::setCurrent('second');
    app()->instance('request', Request::create('/second/search', 'GET', ['q' => $query]));

    $html = (string) Antlers::parse(
        '{{ partial:aether-search::listing/results :index="index" :search_site="search_site" all_results_url="" wider_scope_url="" wider_scope_label="" }}',
        ['index' => $index, 'search_site' => $searchSite]
    );

    preg_match_all('#href="/(?:second/)?people/([a-z0-9-]+)"#', $html, $matches);

    return array_values(array_unique($matches[1]));
}

/**
 * The titles the type-ahead offers for a query on `second`'s own site.
 *
 * @return list<string>
 */
function loupeSuggestedTitles(string $query): array
{
    $response = app(SuggestionsController::class)(Request::create('/!/search/suggestions', 'GET', ['q' => $query, 'site' => 'second', 'scope' => 'site']));

    /** @var array{results?: list<array{title: string}>} $payload */
    $payload = json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);

    return array_column($payload['results'] ?? [], 'title');
}

beforeEach(function (): void {
    Site::setSites([
        'default' => ['name' => 'Starfleet Academy', 'url' => '/', 'locale' => 'en_US'],
        'second' => ['name' => 'Deep Space Nine', 'url' => '/second/', 'locale' => 'en_US'],
    ]);

    $collection = Collection::make('people');
    $collection->routes('/people/{slug}');
    $collection->sites(['default', 'second']);
    $collection->save();

    // A suggestion renders the person's portrait field, which names this container
    Storage::fake('portraits');
    $portraits = mustBe(StatamicAssetContainer::class, AssetContainer::make('portraits'), 'the portraits container');
    $portraits->disk('portraits');
    $portraits->save();
});

it('keeps up to 1000 hits per search on the default index (#511)', function (): void {
    expect(config('statamic.search.indexes.default.max_total_hits'))->toBe(1000)
        ->and(mustBe(SiteFilteredLoupeIndex::class, Search::index('default'), 'the default search index')->configuration()->getMaxTotalHits())->toBe(1000);
});

/**
 * Index `ensigns` matches for `vulcan` on the root site, then five on `second`, under a cap of 20.
 *
 * Loupe keeps the first documents in storage order up to the cap, so the root site's matches,
 * saved first, are the ones kept.
 */
function loupeCappedVulcanIndex(int $ensigns): SiteFilteredLoupeIndex
{
    config()->set('statamic.search.indexes.default.max_total_hits', 20);

    foreach (range(1, $ensigns) as $number) {
        loupeSearchPerson('default', 'ensign-'.$number, 'Ensign Vulcan');
    }

    foreach (range(1, 5) as $number) {
        loupeSearchPerson('second', 'cadet-'.$number, 'Cadet Vulcan exchange officer from the science academy');
    }

    return builtLoupeSearchIndex('default');
}

it('returns every match on a site when every match across all sites fits under the cap', function (): void {
    $index = loupeCappedVulcanIndex(15);
    $cadets = ['cadet-1', 'cadet-2', 'cadet-3', 'cadet-4', 'cadet-5'];

    expect($index->configuration()->getMaxTotalHits())->toBe(20)
        ->and(loupeRawHits($index->search('vulcan')))->toHaveCount(20)
        ->and(loupeRawHits($index->search('vulcan'), ['second']))->toHaveCount(5)
        ->and(loupeListedTitles('default', 'vulcan', 'second'))->toEqualCanonicalizing($cadets);
});

it('loses a site\'s matches that fall past the cap across all sites, even with the site handed to Loupe', function (): void {
    // Today's behavior, not the goal: Loupe limits each search word's matches before it applies
    // the site filter, so the five on `second` are cut with the rest (#511, #646)
    $index = loupeCappedVulcanIndex(25);

    $addonQuery = new AddonLoupeQuery($index);
    $addonQuery->query('vulcan');

    expect(loupeRawHits($index->search('vulcan')))->toHaveCount(20)
        ->and(loupeRawHits($index->search('vulcan'), ['second']))->toBeEmpty()
        ->and(loupeRawHits($addonQuery, ['second']))->toBeEmpty();
});

it('returns for a misspelled query the entries its correct spelling returns', function (): void {
    loupeSearchPerson('second', 'leonard-mccoy', 'Leonard McCoy, Cardiology');
    loupeSearchPerson('second', 'christine-chapel', 'Christine Chapel, Pediatric Cardiology');
    loupeSearchPerson('second', 'montgomery-scott', 'Montgomery Scott, Engineering');

    builtLoupeSearchIndex('default');

    $listed = loupeListedTitles('default', 'cardiology', 'second');
    $suggested = loupeSuggestedTitles('cardiology');

    expect($listed)->toEqualCanonicalizing(['leonard-mccoy', 'christine-chapel'])
        ->and(loupeListedTitles('default', 'cardiolgy', 'second'))->toEqualCanonicalizing($listed)
        ->and($suggested)->toEqualCanonicalizing(['Leonard McCoy, Cardiology', 'Christine Chapel, Pediatric Cardiology'])
        ->and(loupeSuggestedTitles('cardiolgy'))->toEqualCanonicalizing($suggested);
});

it('adds a saved entry to the index and removes a deleted one, without rebuilding it', function (): void {
    $kept = loupeSearchPerson('second', 'nyota-uhura', 'Nyota Uhura');
    $dropped = loupeSearchPerson('second', 'pavel-chekov', 'Pavel Chekov');

    $index = builtLoupeSearchIndex('default');

    // Take Uhura out of the index behind Statamic's back: a rebuild would put her back
    $index->delete($kept);

    $added = mustBe(StatamicEntry::class, Entry::make(), 'a new entry');
    $added->collection('people');
    $added->locale('second');
    $added->slug('hikaru-sulu');
    $added->published(true);
    $added->data(['title' => 'Hikaru Sulu']);
    $added->save();
    $dropped->delete();

    expect(loupeRawHits($index->search('sulu'))->map(fn (mixed $hit): mixed => mustBe(PlainResult::class, $hit, 'a hit')->getRawResult()['reference'] ?? null)->all())->toBe(['entry::'.mustBeString($added->id(), 'the new entry id')])
        ->and(loupeRawHits($index->search('chekov')))->toBeEmpty()
        ->and(loupeRawHits($index->search('uhura')))->toBeEmpty();
});

it('stores each document under its site handle, which is not searchable', function (): void {
    loupeSearchPerson('second', 'odo', 'Odo');

    $index = builtLoupeSearchIndex('people');

    $odo = mustBe(PlainResult::class, loupeRawHits($index->search('odo'))->first(), 'the hit for Odo');

    expect($odo->getRawResult()['site'] ?? null)->toBe('second')
        ->and(loupeRawHits($index->search('second')))->toBeEmpty()
        ->and($index->configuration()->getFilterableAttributes())->toBe([SiteFilteredLoupeIndex::SITE_ATTRIBUTE]);
});

it('hands Loupe the sites its clauses allow, and none when a clause is joined by or', function (Closure $build, ?array $sites): void {
    $query = new SiteFilteredLoupeQuery(mustBe(SiteFilteredLoupeIndex::class, Search::index('people'), 'the people index'));
    $build($query);

    expect($query->allowedSites())->toBe($sites);
})->with([
    'no site clause' => [fn (SiteFilteredLoupeQuery $query) => $query->where('status', 'published'), null],
    'whereIn' => [fn (SiteFilteredLoupeQuery $query) => $query->whereIn('site', ['second', 'default', 'second']), ['second', 'default']],
    'where equals' => [fn (SiteFilteredLoupeQuery $query) => $query->where('site', 'second'), ['second']],
    'two clauses intersect' => [function (SiteFilteredLoupeQuery $query): void {
        $query->whereIn('site', ['second', 'default']);
        $query->where('site', 'default');
    }, ['default']],
    'disjoint clauses' => [function (SiteFilteredLoupeQuery $query): void {
        $query->whereIn('site', ['second']);
        $query->where('site', 'default');
    }, []],
    'an empty whereIn' => [fn (SiteFilteredLoupeQuery $query) => $query->whereIn('site', []), []],
    'a not-equals clause' => [fn (SiteFilteredLoupeQuery $query) => $query->where('site', '!=', 'second'), null],
    'a whereNotIn clause' => [fn (SiteFilteredLoupeQuery $query) => $query->whereNotIn('site', ['second']), null],
    'a value that is not a string' => [fn (SiteFilteredLoupeQuery $query) => $query->whereIn('site', ['second', 7]), null],
    'an or clause' => [function (SiteFilteredLoupeQuery $query): void {
        $query->whereIn('site', ['second']);
        $query->orWhere('status', 'published');
    }, null],
]);

it('quotes each site handle in the filter it hands Loupe', function (): void {
    expect(SiteFilteredLoupeIndex::siteFilter(['second', "o'brien"]))->toBe("site IN ('second', 'o''brien')");
});
