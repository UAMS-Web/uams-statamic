<?php

declare(strict_types=1);

use App\Services\Posts\NewsSyndicationQueryService;
use App\Support\Multisite\DepartmentHubResolver;
use App\Tags\NewsArchive;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Facade;
use Statamic\Facades\Site;
use Statamic\Sites\Site as SiteModel;

covers(NewsArchive::class);

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
 * Configure the container's current Site facade and HTTP request.
 */
function bindNewsArchiveContext(Container $container, mixed $siteHandle, string $url = 'https://example.test/news'): void
{
    $site = Mockery::mock(SiteModel::class);
    $site->shouldReceive('handle')->atLeast()->once()->andReturn($siteHandle);

    $sites = Mockery::mock();
    $sites->shouldReceive('current')->atLeast()->once()->andReturn($site);
    Site::swap($sites);

    $request = Request::create($url);
    $container->instance('request', $request);
    Paginator::queryStringResolver(static fn (): array => $request->query());
}

/**
 * Build a NewsArchive tag instance configured with the given parameters.
 *
 * @param  array<string, mixed>  $params
 */
function makeNewsArchiveTag(array $params = []): NewsArchive
{
    $tag = new NewsArchive;
    $tag->setContext([]);
    $tag->setParameters($params);

    return $tag;
}

it('resolves defaults from the current site organization and preserves query strings on pagination links', function (): void {
    bindNewsArchiveContext($this->container, 'college', 'https://example.test/news?type=Video&source=Local&page=2');

    $resolver = Mockery::mock(DepartmentHubResolver::class);
    $resolver->shouldReceive('organizationId')->once()->with('college')->andReturn('org-college');
    $this->container->instance(DepartmentHubResolver::class, $resolver);

    $paginator = new LengthAwarePaginator(['item-1', 'item-2'], 25, 10, 2);

    $service = Mockery::mock(NewsSyndicationQueryService::class);
    $service->shouldReceive('resolveSiteHandles')
        ->once()
        ->with([NewsSyndicationQueryService::SOURCE_CURRENT], 'college')
        ->andReturn(['college']);
    $service->shouldReceive('paginateArchive')
        ->once()
        ->with(['college'], ['org-college'], ['video'], [], 10, 2)
        ->andReturn($paginator);
    $this->container->instance(NewsSyndicationQueryService::class, $service);

    $result = makeNewsArchiveTag()->index();

    expect($result['posts'])->toBe(['item-1', 'item-2'])
        ->and($result['no_results'])->toBeFalse()
        ->and($result['total_results'])->toBe(25)
        ->and($result['active_type'])->toBe('video')
        ->and($result['active_source'])->toBe('local')
        ->and(data_get($result, 'paginate.current_page'))->toBe(2)
        ->and(data_get($result, 'paginate.total_pages'))->toBe(3)
        ->and(data_get($result, 'paginate.prev_page'))->toContain('https://example.test/news')
        ->and(data_get($result, 'paginate.prev_page'))->toContain('type=Video')
        ->and(data_get($result, 'paginate.prev_page'))->toContain('source=Local')
        ->and(data_get($result, 'paginate.prev_page'))->toContain('page=1')
        ->and(data_get($result, 'paginate.next_page'))->toContain('page=3');
});

it('prefers explicit tag parameters over query string values and trims custom as alias', function (): void {
    bindNewsArchiveContext($this->container, 'college', 'https://example.test/news?type=audio&source=local&page=5');

    $resolver = Mockery::mock(DepartmentHubResolver::class);
    $resolver->shouldNotReceive('organizationId');

    $this->container->instance(DepartmentHubResolver::class, $resolver);

    $paginator = new LengthAwarePaginator([], 0, 5, 3);

    $service = Mockery::mock(NewsSyndicationQueryService::class);
    $service->shouldReceive('resolveSiteHandles')
        ->once()
        ->with([NewsSyndicationQueryService::NEWS_SITE], 'college')
        ->andReturn(['news']);
    $service->shouldReceive('paginateArchive')
        ->once()
        ->with(['news'], ['org-explicit'], ['post'], ['cat-1'], 5, 3)
        ->andReturn($paginator);
    $this->container->instance(NewsSyndicationQueryService::class, $service);

    $result = makeNewsArchiveTag([
        'source' => '  Newsroom  ',
        'organization' => 'org-explicit',
        'type' => '  Post  ',
        'category' => 'cat-1',
        'per_page' => '5',
        'page' => '3',
        'as' => '  articles  ',
    ])->index();

    expect($result)->toHaveKey('articles')
        ->and($result)->not->toHaveKey('posts')
        ->and($result['articles'])->toBe([])
        ->and($result['no_results'])->toBeTrue()
        ->and($result['total_results'])->toBe(0)
        ->and($result['active_type'])->toBe('post')
        ->and($result['active_source'])->toBe('newsroom');
});

it('normalizes unknown source and type, handles non-string site handle, and falls back when resolver returns null', function (): void {
    bindNewsArchiveContext($this->container, 123, 'https://example.test/news?source=hostile&type=bogus&page=abc');

    $resolver = Mockery::mock(DepartmentHubResolver::class);
    $resolver->shouldReceive('organizationId')->once()->with('')->andReturn(null);
    $this->container->instance(DepartmentHubResolver::class, $resolver);

    $paginator = new LengthAwarePaginator([], 0, 10, 1);

    $service = Mockery::mock(NewsSyndicationQueryService::class);
    $service->shouldReceive('resolveSiteHandles')
        ->once()
        ->with([NewsSyndicationQueryService::NEWS_SITE, NewsSyndicationQueryService::SOURCE_CURRENT], '')
        ->andReturn(['news']);
    $service->shouldReceive('paginateArchive')
        ->once()
        ->with(['news'], [], [], [], 10, 1)
        ->andReturn($paginator);
    $this->container->instance(NewsSyndicationQueryService::class, $service);

    $result = makeNewsArchiveTag([
        'as' => ['non-scalar'],
        'per_page' => '0',
    ])->index();

    expect($result)->toHaveKey('posts')
        ->and($result['active_type'])->toBeNull()
        ->and($result['active_source'])->toBe('both');
});

it('falls back from blank or non-scalar axis params to query string and clamps non-positive pages to 1', function (): void {
    bindNewsArchiveContext($this->container, 'main', 'https://example.test/news?source=%20local%20&type=%20%20&page=0');

    $resolver = Mockery::mock(DepartmentHubResolver::class);
    $resolver->shouldReceive('organizationId')->once()->with('main')->andReturn(null);
    $this->container->instance(DepartmentHubResolver::class, $resolver);

    $paginator = new LengthAwarePaginator([], 0, 10, 1);

    $service = Mockery::mock(NewsSyndicationQueryService::class);
    $service->shouldReceive('resolveSiteHandles')
        ->once()
        ->with([NewsSyndicationQueryService::SOURCE_CURRENT], 'main')
        ->andReturn(['main']);
    $service->shouldReceive('paginateArchive')
        ->once()
        ->with(['main'], [], [], [], 10, 1)
        ->andReturn($paginator);
    $this->container->instance(NewsSyndicationQueryService::class, $service);

    $result = makeNewsArchiveTag([
        'source' => '   ',
        'type' => ['not-scalar'],
        'as' => '   ',
        'per_page' => 'invalid',
    ])->index();

    expect($result)->toHaveKey('posts')
        ->and($result['active_source'])->toBe('local')
        ->and($result['active_type'])->toBeNull();
});

it('clamps non-positive explicit page param to 1 and ignores non-string query axes', function (): void {
    bindNewsArchiveContext($this->container, 'main', 'https://example.test/news?source[]=bad&type[]=bad');

    $resolver = Mockery::mock(DepartmentHubResolver::class);
    $resolver->shouldReceive('organizationId')->once()->with('main')->andReturn(null);
    $this->container->instance(DepartmentHubResolver::class, $resolver);

    $paginator = new LengthAwarePaginator([], 0, 10, 1);

    $service = Mockery::mock(NewsSyndicationQueryService::class);
    $service->shouldReceive('resolveSiteHandles')
        ->once()
        ->with([NewsSyndicationQueryService::NEWS_SITE, NewsSyndicationQueryService::SOURCE_CURRENT], 'main')
        ->andReturn(['news', 'main']);
    $service->shouldReceive('paginateArchive')
        ->once()
        ->with(['news', 'main'], [], [], [], 10, 1)
        ->andReturn($paginator);
    $this->container->instance(NewsSyndicationQueryService::class, $service);

    $result = makeNewsArchiveTag([
        'page' => '0',
        'per_page' => '-5',
    ])->index();

    expect($result['active_source'])->toBe('both')
        ->and($result['active_type'])->toBeNull();
});

it('casts non-string scalar params and handles fractional or boundary per_page values', function (): void {
    bindNewsArchiveContext($this->container, 'main', 'https://example.test/news');

    $resolver = Mockery::mock(DepartmentHubResolver::class);
    $resolver->shouldReceive('organizationId')->twice()->with('main')->andReturn(null);
    $this->container->instance(DepartmentHubResolver::class, $resolver);

    $paginator = new LengthAwarePaginator([], 0, 10, 1);

    $service = Mockery::mock(NewsSyndicationQueryService::class);
    $service->shouldReceive('resolveSiteHandles')
        ->twice()
        ->with([NewsSyndicationQueryService::NEWS_SITE, NewsSyndicationQueryService::SOURCE_CURRENT], 'main')
        ->andReturn(['news', 'main']);
    $service->shouldReceive('paginateArchive')
        ->once()
        ->with(['news', 'main'], [], [], [], 10, 1)
        ->andReturn($paginator);
    $service->shouldReceive('paginateArchive')
        ->once()
        ->with(['news', 'main'], [], [], [], 1, 1)
        ->andReturn($paginator);
    $this->container->instance(NewsSyndicationQueryService::class, $service);

    $fractional = makeNewsArchiveTag([
        'source' => 123,
        'per_page' => '0.5',
        'as' => 42,
    ])->index();

    expect($fractional)->toHaveKey('42')
        ->and($fractional['active_source'])->toBe('both');

    $onePerPage = makeNewsArchiveTag([
        'per_page' => '1',
    ])->index();

    expect($onePerPage)->toHaveKey('posts');
});
