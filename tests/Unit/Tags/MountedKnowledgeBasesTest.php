<?php

declare(strict_types=1);

use App\Entries\KnowledgeBaseEntry;
use App\Services\KnowledgeBase\KnowledgeBaseComputedFieldsService;
use App\Tags\MountedKnowledgeBases;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\Site;
use Statamic\Sites\Site as SiteModel;
use Statamic\Stache\Query\EntryQueryBuilder;

covers(MountedKnowledgeBases::class);

beforeEach(function (): void {
    $this->previousContainer = Container::getInstance();
    $this->previousFacadeApp = Facade::getFacadeApplication();

    $this->container = new Application;
    Container::setInstance($this->container);
    Facade::setFacadeApplication($this->container);
    Facade::clearResolvedInstances();
});

afterEach(function (): void {
    Mockery::close();
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication($this->previousFacadeApp);
    Container::setInstance($this->previousContainer);
});

/**
 * Build a MountedKnowledgeBases tag instance with the given parameters.
 *
 * @param  array<string, mixed>  $params
 */
function makeMountedKnowledgeBasesTag(array $params = []): MountedKnowledgeBases
{
    $tag = new MountedKnowledgeBases;
    $tag->setContext([]);
    $tag->setParameters($params);

    return $tag;
}

/** @param  array<string, mixed>  $params */
it('returns an empty array without querying when under is missing or empty', function (array $params): void {
    /** @var array<string, mixed> $params */
    expect(makeMountedKnowledgeBasesTag($params)->index())->toBe([]);
})->with([
    'missing under' => [[]],
    'empty under' => [['under' => '']],
    'null under' => [['under' => null]],
]);

it('queries the current site by default and returns only non-empty roots mounted under the page with re-indexed keys', function (): void {
    $currentSite = Mockery::mock(SiteModel::class);
    $currentSite->shouldReceive('handle')->once()->andReturn('main');

    $sites = Mockery::mock();
    $sites->shouldReceive('current')->once()->andReturn($currentSite);
    Site::swap($sites);

    $otherMount = Mockery::mock(Entry::class);
    $otherMount->shouldReceive('get')->once()->with(KnowledgeBaseEntry::MOUNT_FIELD)->andReturn(['other-page']);

    $emptyMounted = Mockery::mock(Entry::class);
    $emptyMounted->shouldReceive('get')->once()->with(KnowledgeBaseEntry::MOUNT_FIELD)->andReturn(['page-1']);

    $stringMounted = Mockery::mock(Entry::class);
    $stringMounted->shouldReceive('get')->once()->with(KnowledgeBaseEntry::MOUNT_FIELD)->andReturn('page-1');

    $arrayMounted = Mockery::mock(Entry::class);
    $arrayMounted->shouldReceive('get')->once()->with(KnowledgeBaseEntry::MOUNT_FIELD)->andReturn(['other', 'page-1']);

    $query = Mockery::mock(EntryQueryBuilder::class);
    $query->shouldReceive('where')->once()->with('collection', 'knowledge_bases')->andReturnSelf();
    $query->shouldReceive('where')->once()->with('blueprint', KnowledgeBaseEntry::ROOT_BLUEPRINT)->andReturnSelf();
    $query->shouldReceive('where')->once()->with('site', 'main')->andReturnSelf();
    $query->shouldReceive('get')->once()->andReturn(new Collection([
        $otherMount,
        $emptyMounted,
        $stringMounted,
        $arrayMounted,
    ]));

    $entryRepo = Mockery::mock();
    $entryRepo->shouldReceive('query')->once()->andReturn($query);
    EntryFacade::swap($entryRepo);

    $computed = Mockery::mock(KnowledgeBaseComputedFieldsService::class);
    $computed->shouldReceive('resolveHasDescendantArticles')->once()->with($emptyMounted)->andReturn(false);
    $computed->shouldReceive('resolveHasDescendantArticles')->once()->with($stringMounted)->andReturn(true);
    $computed->shouldReceive('resolveHasDescendantArticles')->once()->with($arrayMounted)->andReturn(true);
    $this->container->instance(KnowledgeBaseComputedFieldsService::class, $computed);

    $result = makeMountedKnowledgeBasesTag(['under' => 'page-1'])->index();

    expect($result)->toBe([$stringMounted, $arrayMounted])
        ->and(array_keys($result))->toBe([0, 1]);
});

it('queries the explicit in site handle when provided', function (): void {
    $currentSite = Mockery::mock(SiteModel::class);
    $currentSite->shouldReceive('handle')->once()->andReturn('main');

    $sites = Mockery::mock();
    $sites->shouldReceive('current')->once()->andReturn($currentSite);
    Site::swap($sites);

    $query = Mockery::mock(EntryQueryBuilder::class);
    $query->shouldReceive('where')->once()->with('collection', 'knowledge_bases')->andReturnSelf();
    $query->shouldReceive('where')->once()->with('blueprint', KnowledgeBaseEntry::ROOT_BLUEPRINT)->andReturnSelf();
    $query->shouldReceive('where')->once()->with('site', 'news')->andReturnSelf();
    $query->shouldReceive('get')->once()->andReturn(new Collection);

    $entryRepo = Mockery::mock();
    $entryRepo->shouldReceive('query')->once()->andReturn($query);
    EntryFacade::swap($entryRepo);

    $computed = Mockery::mock(KnowledgeBaseComputedFieldsService::class);
    $this->container->instance(KnowledgeBaseComputedFieldsService::class, $computed);

    expect(makeMountedKnowledgeBasesTag(['under' => 'page-1', 'in' => 'news'])->index())->toBe([]);
});
