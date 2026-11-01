<?php

declare(strict_types=1);

use App\Scopes\HidePrivateSearchResults;
use App\Search\AreaOfExpertiseSectionDocument;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\File;
use Statamic\Auth\File\Role as FileRole;
use Statamic\Auth\File\User as FileUser;
use Statamic\Contracts\Auth\RoleRepository as RoleRepositoryContract;
use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Facades\Antlers;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Role;
use Statamic\Facades\Scope;
use Statamic\Facades\Search;
use Statamic\Facades\User;
use Statamic\Query\ItemQueryBuilder;
use Statamic\Search\Index;
use Statamic\Search\QueryBuilder as SearchQueryBuilder;
use Statamic\Search\Result;
use Tests\Support\Doubles\RecordingCallerSearchScope;
use Tests\Support\StacheIsolatedTestCase;

use function Pest\Laravel\actingAs;

uses(StacheIsolatedTestCase::class);

/**
 * The `/search` results page hides the `private` entries a reader may not open
 * (UAMS-Web/uams-statamic-archive#2634), by the rule `ProtectEntry` applies to their URLs and the type-ahead applies
 * to its suggestions.
 *
 * The scope is exercised against Statamic's in-memory query builder, whose conditions
 * filter before pagination as the search tag's builder does, over real `Result`s for
 * entries saved in this test's isolated Stache. One case renders the shared results
 * listing through a stubbed index, so the page's own wiring is covered too.
 *
 * @command  php artisan test --compact tests/Feature/Search/HidePrivateSearchResultsTest.php
 */

/**
 * Save a published entry in a routed collection.
 *
 * @param  string|null  $visibility  A `status_and_visibility` value, such as `private`.
 */
function privateSearchEntry(string $collection, string $slug, string $title, ?string $visibility = null): StatamicEntry
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

/**
 * The section subpage document of an Area of Expertise, as a search hit.
 */
function privateSearchSection(StatamicEntry $areaOfExpertise, string $title): Result
{
    return new Result(
        new AreaOfExpertiseSectionDocument(mustBeString($areaOfExpertise->id(), 'id'), 'providers', $title, '/'.mustBeString($areaOfExpertise->slug(), 'slug').'/providers', 'default'),
        AreaOfExpertiseSectionDocument::REFERENCE_PREFIX
    );
}

/**
 * Four hits: a private and a published person, and a section subpage of a private and
 * of a public Area of Expertise.
 *
 * @return list<Result>
 */
function privateSearchHits(): array
{
    $classified = privateSearchEntry('clinical_expertise', 'classified-medicine', 'Classified Medicine', 'private');
    $cardiology = privateSearchEntry('clinical_expertise', 'cardiology', 'Cardiology');

    return [
        new Result(privateSearchEntry('people', 'janice-rand', 'Janice Rand', 'private'), 'entry'),
        new Result(privateSearchEntry('people', 'leonard-mccoy', 'Leonard McCoy', 'published'), 'entry'),
        privateSearchSection($classified, 'Classified Medicine Providers'),
        privateSearchSection($cardiology, 'Cardiology Providers'),
    ];
}

/**
 * The titles the scope leaves in a query over these hits.
 *
 * @param  list<Result>  $hits
 * @return list<string>
 */
function titlesAfterPrivateScope(array $hits): array
{
    $query = new ItemQueryBuilder;
    $query->withItems(collect($hits));
    app(HidePrivateSearchResults::class)->apply($query, []);

    $titles = [];
    foreach (mustBe(Illuminate\Support\Collection::class, $query->get(), 'results')->all() as $result) {
        $searchable = mustBe(Result::class, $result, 'a result')->getSearchable();

        $titles[] = mustBeString(
            $searchable instanceof AreaOfExpertiseSectionDocument
                ? $searchable->augmentedArrayData()['title']
                : ($searchable instanceof StatamicEntry ? $searchable->value('title') : null),
            'title'
        );
    }

    return $titles;
}

/**
 * Sign in a user whose only content permission is to publish one collection.
 */
function actAsPublisherOf(string $collection): void
{
    $role = mustBe(FileRole::class, Role::make('publisher-'.$collection), 'a role');
    $role->title('Publisher');
    $role->permissions(['access cp', 'access default site', 'publish '.$collection.' entries']);
    $role->save();

    $user = mustBe(FileUser::class, User::make(), 'a user');
    $user->email($collection.'-publisher@example.test');
    $user->assignRole($role->handle());
    $user->save();

    actingAs($user);
}

beforeEach(function (): void {
    // Keep the roles these tests save out of the tracked resources/users/roles.yaml
    $rolesDirectory = storage_path('framework/testing/private-search-roles/'.uniqid('', true));
    File::ensureDirectoryExists($rolesDirectory);
    app(RoleRepositoryContract::class)->path($rolesDirectory.'/roles.yaml');

    foreach (['people' => '/people/{slug}', 'clinical_expertise' => '/{slug}'] as $handle => $route) {
        $collection = Collection::make($handle);
        $collection->routes($route);
        $collection->save();
    }
});

it('is registered under the handle the results listing names', function (): void {
    expect(Scope::find('hide_private_search_results'))->toBeInstanceOf(HidePrivateSearchResults::class);
});

it('hides a private entry, and the section subpages of a private Area of Expertise, from a guest', function (): void {
    expect(titlesAfterPrivateScope(privateSearchHits()))->toBe(['Leonard McCoy', 'Cardiology Providers']);
});

it('counts only the listed hits, so the pagination totals match the page', function (): void {
    $query = new ItemQueryBuilder;
    $query->withItems(collect(privateSearchHits()));
    app(HidePrivateSearchResults::class)->apply($query, []);

    $page = mustBe(LengthAwarePaginator::class, $query->paginate(1), 'a page');

    expect($page->total())->toBe(2)
        ->and($page->lastPage())->toBe(2);
});

it('shows a private entry to a reader who may publish its collection, and only that collection', function (): void {
    $hits = privateSearchHits();
    actAsPublisherOf('people');

    expect(titlesAfterPrivateScope($hits))->toBe(['Janice Rand', 'Leonard McCoy', 'Cardiology Providers']);
});

it('shows every private hit to a super user', function (): void {
    $hits = privateSearchHits();

    /** @var FileUser $admin */
    $admin = User::make();
    $admin->email('kirk@example.test');
    $admin->makeSuper();
    $admin->save();
    actingAs($admin);

    expect(titlesAfterPrivateScope($hits))->toBe(['Janice Rand', 'Leonard McCoy', 'Classified Medicine Providers', 'Cardiology Providers']);
});

it('leaves the query untouched when no entry is private', function (): void {
    $query = new ItemQueryBuilder;
    $query->withItems(collect([new Result(privateSearchEntry('people', 'leonard-mccoy', 'Leonard McCoy'), 'entry')]));
    app(HidePrivateSearchResults::class)->apply($query, []);

    expect($query->count())->toBe(1);
});

it('keeps a private entry out of the rendered results listing, alongside a caller scope', function (?string $callerScope): void {
    $private = privateSearchEntry('people', 'janice-rand', 'Janice Rand', 'private');
    $public = privateSearchEntry('people', 'leonard-mccoy', 'Leonard McCoy', 'published');

    // A stubbed index whose search returns both entries by reference, so the tag's
    // own pipeline (status, scopes, pagination, cards) runs over real entries
    $index = Mockery::mock(Index::class);
    $index->shouldReceive('name')->andReturn('default');
    $index->shouldReceive('ensureExists')->andReturnSelf();
    $index->shouldReceive('extraAugmentedResultData')->andReturn([]);
    $ids = [mustBeString($private->id(), 'id'), mustBeString($public->id(), 'id')];
    $index->shouldReceive('search')->andReturnUsing(function (string $query) use ($index, $ids): SearchQueryBuilder {
        $builder = new class($index, $ids) extends SearchQueryBuilder
        {
            /**
             * @param  list<string>  $ids
             */
            public function __construct(Index $index, private readonly array $ids)
            {
                parent::__construct($index);
            }

            /**
             * @return list<array{reference: string, search_score: int}>
             */
            public function getSearchResults(mixed $query): array
            {
                return array_map(static fn (string $id): array => ['reference' => 'entry::'.$id, 'search_score' => 1], $this->ids);
            }
        };
        $builder->query($query);

        return $builder;
    });
    Search::shouldReceive('index')->andReturn($index);

    app()->instance('request', Request::create('/search', 'GET', ['q' => 'rand']));
    app('statamic.scopes')->put('recording_caller_search_scope', RecordingCallerSearchScope::class);
    RecordingCallerSearchScope::$applied = false;

    $html = (string) Antlers::parse(
        '{{ partial:search/results index="default" :query_scope="caller_scope" }}',
        ['caller_scope' => $callerScope]
    );

    expect($html)->toContain('Leonard McCoy')
        ->and($html)->not->toContain('Janice Rand')
        ->and(RecordingCallerSearchScope::$applied)->toBe($callerScope !== null);
})->with([
    'no caller scope' => [null],
    'a caller scope' => ['recording_caller_search_scope'],
]);
