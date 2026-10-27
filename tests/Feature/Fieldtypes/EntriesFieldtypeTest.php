<?php

declare(strict_types=1);

use App\Fieldtypes\Entries;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Auth\File\User as FileUser;
use Statamic\Contracts\Auth\User as UserContract;
use Statamic\Contracts\Entries\Entry;
use Statamic\Entries\Collection;
use Statamic\Exceptions\CollectionNotFoundException;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\User;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;
use Tests\Support\TermsMutateProbeScope;
use Tests\TestCase;

use function Pest\Laravel\actingAs;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Tests for App\Fieldtypes\Entries: the blueprint-restriction config field, the
 * create-button options it builds per collection and blueprint, and the query
 * scopes it hands to the index query.
 *
 * @command  php artisan test --compact tests/Feature/Fieldtypes/EntriesFieldtypeTest.php
 */

/**
 * Build an Entries fieldtype bound to a Field with the given config.
 *
 * @param  array<string, mixed>  $config  Field config merged over the type handle
 */
function entriesFieldtype(array $config = []): Entries
{
    // Bind a Field so config() resolves collections, blueprints and query scopes
    $fieldtype = new Entries;
    $fieldtype->setField(new Field('related', array_merge(['type' => 'entries'], $config)));

    return $fieldtype;
}

/**
 * Make an unsaved blueprint with the given handle, title and hidden flag.
 */
function entriesBlueprint(string $handle, string $title, bool $hidden = false): Blueprint
{
    $blueprint = new Blueprint;
    $blueprint->setHandle($handle);
    $blueprint->setContents(['title' => $title]);
    $blueprint->setHidden($hidden);

    return $blueprint;
}

/**
 * Register a mock collection that the Collection facade returns for its handle.
 *
 * @param  list<Blueprint>  $blueprints  The collection's entry blueprints
 */
function entriesCollection(string $handle, string $title, array $blueprints): Collection
{
    $collection = Mockery::mock(Collection::class);
    $collection->shouldReceive('handle')->andReturn($handle);
    $collection->shouldReceive('title')->andReturn($title);
    $collection->shouldReceive('entryBlueprints')->andReturn(collect($blueprints));
    $collection->shouldReceive('createEntryUrl')->with('default')->andReturn('/cp/collections/'.$handle.'/entries/create/default');

    entriesCollectionRepository()->shouldReceive('findByHandle')->with($handle)->andReturn($collection);

    return $collection;
}

/**
 * Swap the collection repository for a proxy that passes unstubbed calls to the real one.
 */
function entriesCollectionRepository(): MockInterface
{
    // Reuse the proxy once swapped in, so several collections can be stubbed
    $repository = CollectionFacade::getFacadeRoot();

    if ($repository instanceof MockInterface) {
        return $repository;
    }

    $proxy = Mockery::mock($repository);
    CollectionFacade::swap($proxy);

    return $proxy;
}

/**
 * Read the create-button options an Entries fieldtype builds.
 *
 * @return array<int, array<string, string>>
 */
function entriesCreatables(Entries $fieldtype): array
{
    /** @var array<int, array<string, string>> $creatables */
    $creatables = (new ReflectionMethod(Entries::class, 'getCreatables'))->invoke($fieldtype);

    return $creatables;
}

/**
 * Act as a super user, who may create entries in every collection.
 */
function entriesActAsSuperUser(): void
{
    $user = mustBe(FileUser::class, User::make(), 'user');
    $user->email('entries-fieldtype@example.com');
    $user->makeSuper();
    actingAs($user);
}

it('uses the parent entries icon', function (): void {
    expect((new Entries)->icon())->toBe('fieldtype-entries');
});

it('adds a blueprint restriction field to the Appearance section', function (): void {
    /** @var array<int, array<string, mixed>> $items */
    $items = (new ReflectionMethod(Entries::class, 'configFieldItems'))->invoke(new Entries);

    expect(array_column($items, 'display'))->toBe(['Input Behavior', 'Appearance', 'Boundaries & Limits', 'Advanced'])
        ->and(array_keys((array) $items[1]['fields']))->toBe(['mode', 'create', 'blueprints'])
        ->and(data_get($items, '1.fields.blueprints'))->toBe([
            'display' => 'Blueprint Restrictions',
            'instructions' => 'Enter basename of blueprint file (e.g., `foo` out of `path/to/foo.yaml`) to restrict entry creation to specific blueprints. Only the selected blueprints will be available when creating new entries.',
            'type' => 'taggable',
            'if' => [
                'create' => true,
            ],
        ]);
});

it('offers only a custom create URL when one is set', function (): void {
    // A subclass that supplies its own create URL skips the collection walk
    $fieldtype = new class extends Entries
    {
        protected function getCreateItemUrl(): string
        {
            return '/cp/custom/create';
        }
    };

    expect(entriesCreatables($fieldtype))->toBe([['url' => '/cp/custom/create']]);
});

it('titles each visible blueprint of a single collection by the blueprint alone', function (): void {
    entriesActAsSuperUser();
    entriesCollection('pages', 'Pages', [
        entriesBlueprint('page', 'Page'),
        entriesBlueprint('landing', 'Landing Page'),
        entriesBlueprint('legacy', 'Legacy', hidden: true),
    ]);

    expect(entriesCreatables(entriesFieldtype(['collections' => ['pages']])))->toBe([
        ['title' => 'Page', 'url' => '/cp/collections/pages/entries/create/default?blueprint=page'],
        ['title' => 'Landing Page', 'url' => '/cp/collections/pages/entries/create/default?blueprint=landing'],
    ]);
});

it('titles a single-blueprint collection by the collection, and a multi-blueprint one by both, when several collections are configured', function (): void {
    entriesActAsSuperUser();
    entriesCollection('news', 'News', [entriesBlueprint('article', 'Article')]);
    entriesCollection('pages', 'Pages', [
        entriesBlueprint('page', 'Page'),
        entriesBlueprint('landing', 'Landing Page'),
    ]);

    expect(entriesCreatables(entriesFieldtype(['collections' => ['news', 'pages']])))->toBe([
        ['title' => 'News', 'url' => '/cp/collections/news/entries/create/default?blueprint=article'],
        ['title' => 'Pages: Page', 'url' => '/cp/collections/pages/entries/create/default?blueprint=page'],
        ['title' => 'Pages: Landing Page', 'url' => '/cp/collections/pages/entries/create/default?blueprint=landing'],
    ]);
});

it('counts only visible blueprints when choosing a title', function (): void {
    entriesActAsSuperUser();
    entriesCollection('news', 'News', [
        entriesBlueprint('article', 'Article'),
        entriesBlueprint('archived', 'Archived', hidden: true),
    ]);
    entriesCollection('pages', 'Pages', [entriesBlueprint('page', 'Page')]);

    // News has one visible blueprint, so it is titled by the collection alone
    expect(array_column(entriesCreatables(entriesFieldtype(['collections' => ['news', 'pages']])), 'title'))->toBe(['News', 'Pages']);
});

it('restricts the options to the configured blueprints', function (): void {
    entriesActAsSuperUser();
    entriesCollection('pages', 'Pages', [
        entriesBlueprint('page', 'Page'),
        entriesBlueprint('landing', 'Landing Page'),
        entriesBlueprint('event', 'Event'),
    ]);

    $creatables = entriesCreatables(entriesFieldtype(['collections' => ['pages'], 'blueprints' => ['event', 'page']]));

    // The collection's order is kept, not the configured order
    expect(array_column($creatables, 'title'))->toBe(['Page', 'Event']);
});

it('skips a collection the current user may not create entries in', function (): void {
    // A user with no permissions can create in no collection
    $user = mustBe(FileUser::class, User::make(), 'user');
    $user->id('entries-fieldtype-viewer');
    $user->email('entries-fieldtype-viewer@example.com');
    actingAs($user);
    entriesCollection('pages', 'Pages', [entriesBlueprint('page', 'Page')]);

    expect(entriesCreatables(entriesFieldtype(['collections' => ['pages']])))->toBe([]);
});

it('casts a Stringable title and falls back to empty text for a missing title or URL', function (): void {
    entriesActAsSuperUser();

    // A blueprint with no title and a collection titled by a Stringable, with no create URL
    $untitled = Mockery::mock(Blueprint::class);
    $untitled->shouldReceive('handle')->andReturn('untitled');
    $untitled->shouldReceive('title')->andReturn(null);
    $untitled->shouldReceive('hidden')->andReturn(false);

    $news = Mockery::mock(Collection::class);
    $news->shouldReceive('handle')->andReturn('news');
    $news->shouldReceive('title')->andReturn(str('News'));
    $news->shouldReceive('entryBlueprints')->andReturn(collect([$untitled, entriesBlueprint('article', 'Article')]));
    $news->shouldReceive('createEntryUrl')->with('default')->andReturn(null);
    entriesCollectionRepository()->shouldReceive('findByHandle')->with('news')->andReturn($news);

    $pages = Mockery::mock(Collection::class);
    $pages->shouldReceive('handle')->andReturn('pages');
    $pages->shouldReceive('title')->andReturn(null);
    $pages->shouldReceive('entryBlueprints')->andReturn(collect([entriesBlueprint('page', 'Page')]));
    $pages->shouldReceive('createEntryUrl')->with('default')->andReturn(str('/cp/pages/create'));
    entriesCollectionRepository()->shouldReceive('findByHandle')->with('pages')->andReturn($pages);

    expect(entriesCreatables(entriesFieldtype(['collections' => ['news', 'pages']])))->toBe([
        ['title' => 'News: ', 'url' => '?blueprint=untitled'],
        ['title' => 'News: Article', 'url' => '?blueprint=article'],
        ['title' => '', 'url' => '/cp/pages/create?blueprint=page'],
    ]);
});

it('titles the only blueprint of a single collection by the blueprint, not the collection', function (): void {
    entriesActAsSuperUser();
    entriesCollection('news', 'News', [entriesBlueprint('article', 'Article')]);

    expect(array_column(entriesCreatables(entriesFieldtype(['collections' => ['news']])), 'title'))->toBe(['Article']);
});

it('casts a Stringable title that is returned on its own', function (): void {
    entriesActAsSuperUser();

    // One collection with one blueprint is titled by the collection alone, and a
    // single collection's blueprint by the blueprint alone
    $stringableBlueprint = Mockery::mock(Blueprint::class);
    $stringableBlueprint->shouldReceive('handle')->andReturn('article');
    $stringableBlueprint->shouldReceive('title')->andReturn(str('Article'));
    $stringableBlueprint->shouldReceive('hidden')->andReturn(false);

    $news = Mockery::mock(Collection::class);
    $news->shouldReceive('handle')->andReturn('news');
    $news->shouldReceive('title')->andReturn(str('News'));
    $news->shouldReceive('entryBlueprints')->andReturn(collect([entriesBlueprint('article', 'Article')]));
    $news->shouldReceive('createEntryUrl')->with('default')->andReturn('/news');
    entriesCollectionRepository()->shouldReceive('findByHandle')->with('news')->andReturn($news);

    $pages = Mockery::mock(Collection::class);
    $pages->shouldReceive('handle')->andReturn('pages');
    $pages->shouldReceive('title')->andReturn('Pages');
    $pages->shouldReceive('entryBlueprints')->andReturn(collect([$stringableBlueprint]));
    $pages->shouldReceive('createEntryUrl')->with('default')->andReturn('/pages');
    entriesCollectionRepository()->shouldReceive('findByHandle')->with('pages')->andReturn($pages);

    expect(entriesCreatables(entriesFieldtype(['collections' => ['news', 'pages']])))->toBe([
        ['title' => 'News', 'url' => '/news?blueprint=article'],
        ['title' => 'Pages', 'url' => '/pages?blueprint=article'],
    ])->and(entriesCreatables(entriesFieldtype(['collections' => ['pages']])))->toBe([
        ['title' => 'Article', 'url' => '/pages?blueprint=article'],
    ]);
});

it('treats a non-text title or URL as empty text', function (): void {
    entriesActAsSuperUser();

    $listTitled = Mockery::mock(Blueprint::class);
    $listTitled->shouldReceive('handle')->andReturn('article');
    $listTitled->shouldReceive('title')->andReturn(['Article']);
    $listTitled->shouldReceive('hidden')->andReturn(false);

    $news = Mockery::mock(Collection::class);
    $news->shouldReceive('handle')->andReturn('news');
    $news->shouldReceive('title')->andReturn(['News']);
    $news->shouldReceive('entryBlueprints')->andReturn(collect([$listTitled, entriesBlueprint('brief', 'Brief')]));
    $news->shouldReceive('createEntryUrl')->with('default')->andReturn(['/news']);
    entriesCollectionRepository()->shouldReceive('findByHandle')->with('news')->andReturn($news);
    entriesCollection('pages', 'Pages', [entriesBlueprint('page', 'Page')]);

    expect(entriesCreatables(entriesFieldtype(['collections' => ['news', 'pages']])))->toBe([
        ['title' => ': ', 'url' => '?blueprint=article'],
        ['title' => ': Brief', 'url' => '?blueprint=brief'],
        ['title' => 'Pages', 'url' => '/cp/collections/pages/entries/create/default?blueprint=page'],
    ]);
});

it('asks whether the user may create entries, not whether they may configure the collection', function (): void {
    $collection = entriesCollection('pages', 'Pages', [entriesBlueprint('page', 'Page')]);

    // The ability is checked against the entry class and the collection together
    $user = Mockery::mock(UserContract::class);
    $user->shouldReceive('can')->once()->with('create', [Entry::class, $collection])->andReturn(true);
    User::partialMock()->shouldReceive('current')->andReturn($user);

    expect(entriesCreatables(entriesFieldtype(['collections' => ['pages']])))->toBe([
        ['title' => 'Page', 'url' => '/cp/collections/pages/entries/create/default?blueprint=page'],
    ]);
});

it('fails on a configured collection that does not exist', function (): void {
    entriesActAsSuperUser();
    entriesCollectionRepository()->shouldReceive('findByHandle')->with('missing')->andReturn(null);

    expect(fn () => entriesCreatables(entriesFieldtype(['collections' => ['missing']])))
        ->toThrow(CollectionNotFoundException::class, 'Collection [missing] not found');
});

it('applies the configured query scopes with their handles in the params', function (): void {
    TermsMutateProbeScope::reset();
    app('statamic.scopes')->put('terms_mutate_probe', TermsMutateProbeScope::class);
    $query = new stdClass;

    (new ReflectionMethod(Entries::class, 'applyIndexQueryScopes'))
        ->invoke(entriesFieldtype(['query_scopes' => 'terms_mutate_probe']), $query, ['site' => 'health']);

    expect(TermsMutateProbeScope::$calls)->toHaveCount(1)
        ->and(TermsMutateProbeScope::$calls[0]['query'])->toBe($query)
        ->and(TermsMutateProbeScope::$calls[0]['values'])->toBe(['site' => 'health', 'queryScopes' => ['terms_mutate_probe']]);
});
