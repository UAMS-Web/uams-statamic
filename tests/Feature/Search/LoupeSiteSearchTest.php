<?php

declare(strict_types=1);

use App\Search\Loupe\SiteFilteredLoupeIndex;
use App\Search\Loupe\SiteFilteredLoupeQuery;
use Daun\StatamicLoupe\Loupe\Query as AddonLoupeQuery;
use Illuminate\Http\Request;
use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Facades\Antlers;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Search;
use Statamic\Facades\Site;
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

    /** @var array{suggestions?: list<array{title: string}>} $payload */
    $payload = json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);

    return array_column($payload['suggestions'] ?? [], 'title');
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
});

it('returns every match on a site when more than 200 documents across all sites match', function (): void {
    // 210 exact matches on the root site outrank the five on `second`, so the first 200 hits
    // across all sites hold none of `second`'s
    foreach (range(1, 210) as $number) {
        loupeSearchPerson('default', 'ensign-'.$number, 'Ensign Vulcan');
    }

    foreach (range(1, 5) as $number) {
        loupeSearchPerson('second', 'cadet-'.$number, 'Cadet Vulcan exchange officer from the science academy');
    }

    $index = builtLoupeSearchIndex('default');

    // Control: the add-on's own query caps first and filters after, and finds none of them
    $capped = new AddonLoupeQuery($index)->query('vulcan')->whereIn('site', ['second'])->withoutData()->get();

    expect(config('statamic.search.indexes.default.max_total_hits'))->toBe(200)
        ->and($capped)->toBeEmpty()
        ->and($index->search('vulcan')->withoutData()->get())->toHaveCount(200)
        ->and($index->search('vulcan')->whereIn('site', ['second'])->withoutData()->get())->toHaveCount(5)
        ->and(loupeListedTitles('default', 'vulcan', 'second'))->toEqualCanonicalizing(['cadet-1', 'cadet-2', 'cadet-3', 'cadet-4', 'cadet-5']);

    // The `people` tab, on its own index, keeps the same five
    builtLoupeSearchIndex('people');

    expect(loupeListedTitles('people', 'vulcan', 'second'))->toEqualCanonicalizing(['cadet-1', 'cadet-2', 'cadet-3', 'cadet-4', 'cadet-5']);
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

    $added = Entry::make()->collection('people')->locale('second')->slug('hikaru-sulu')->published(true)->data(['title' => 'Hikaru Sulu']);
    $added->save();
    $dropped->delete();

    expect($index->search('sulu')->withoutData()->get()->pluck('reference')->all())->toBe(['entry::'.$added->id()])
        ->and($index->search('chekov')->withoutData()->get())->toBeEmpty()
        ->and($index->search('uhura')->withoutData()->get())->toBeEmpty();
});

it('stores each document under its site handle, which is not searchable', function (): void {
    loupeSearchPerson('second', 'odo', 'Odo');

    $index = builtLoupeSearchIndex('people');

    expect($index->search('odo')->withoutData()->get()->first()?->getRawResult()['site'] ?? null)->toBe('second')
        ->and($index->search('second')->withoutData()->get())->toBeEmpty()
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
    'two clauses intersect' => [fn (SiteFilteredLoupeQuery $query) => $query->whereIn('site', ['second', 'default'])->where('site', 'default'), ['default']],
    'disjoint clauses' => [fn (SiteFilteredLoupeQuery $query) => $query->whereIn('site', ['second'])->where('site', 'default'), []],
    'an empty whereIn' => [fn (SiteFilteredLoupeQuery $query) => $query->whereIn('site', []), []],
    'a not-equals clause' => [fn (SiteFilteredLoupeQuery $query) => $query->where('site', '!=', 'second'), null],
    'a whereNotIn clause' => [fn (SiteFilteredLoupeQuery $query) => $query->whereNotIn('site', ['second']), null],
    'a value that is not a string' => [fn (SiteFilteredLoupeQuery $query) => $query->whereIn('site', ['second', 7]), null],
    'an or clause' => [fn (SiteFilteredLoupeQuery $query) => $query->whereIn('site', ['second'])->orWhere('status', 'published'), null],
]);

it('quotes each site handle in the filter it hands Loupe', function (): void {
    expect(SiteFilteredLoupeIndex::siteFilter(['second', "o'brien"]))->toBe("site IN ('second', 'o''brien')");
});
