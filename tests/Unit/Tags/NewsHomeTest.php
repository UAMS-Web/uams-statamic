<?php

declare(strict_types=1);

use App\Services\Posts\NewsSpringboardService;
use App\Tags\NewsHome;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use Statamic\Entries\Entry;
use Statamic\Facades\Site;
use Statamic\Fields\Value;
use Statamic\Sites\Site as SiteModel;

covers(NewsHome::class);

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
 * Bind a mocked current site handle to the Site facade.
 */
function bindNewsHomeSite(mixed $handle = 'news'): void
{
    $site = Mockery::mock(SiteModel::class);
    $site->shouldReceive('handle')->once()->andReturn($handle);

    $sites = Mockery::mock();
    $sites->shouldReceive('current')->once()->andReturn($site);
    Site::swap($sites);
}

/**
 * Build a NewsHome tag instance with the given context and parameters.
 *
 * @param  array<string, mixed>  $context
 * @param  array<string, mixed>  $params
 */
function makeNewsHomeTag(array $context = [], array $params = []): NewsHome
{
    $tag = new NewsHome;
    $tag->setContext($context);
    $tag->setParameters($params);

    return $tag;
}

it('prefers explicit tag params over context values and reports no_results false when lead has posts', function (): void {
    bindNewsHomeSite('news');

    $leadEntry = Mockery::mock(Entry::class);
    $lead = new Collection([$leadEntry]);

    $service = Mockery::mock(NewsSpringboardService::class);
    $service->shouldReceive('resolveMagazine')
        ->once()
        ->withArgs(fn (array $config, string $handle): bool => $handle === 'news'
            && $config['lead_count'] === 4
            && $config['lead_sources'] === []
            && data_get($config, 'sections.0.heading') === 'From param')
        ->andReturn([
            'lead' => $lead,
            'sections' => [],
        ]);
    $this->container->instance(NewsSpringboardService::class, $service);

    $result = makeNewsHomeTag(
        context: [
            'lead_count' => new Value(2),
            'sections' => new Value([['section_heading' => 'From context', 'category' => ['cat-b'], 'count' => 1]]),
        ],
        params: [
            'lead_count' => '4',
            'sections' => [['section_heading' => 'From param', 'category' => ['cat-a'], 'count' => 1]],
        ],
    )->index();

    expect($result['lead'])->toBe($lead)
        ->and($result['sections'])->toBe([])
        ->and($result['no_results'])->toBeFalse();
});

it('unwraps Value context fields and reports no_results false when lead is empty but a section has posts', function (): void {
    bindNewsHomeSite('news');

    $post = Mockery::mock(Entry::class);
    $resolvedSections = [
        ['heading' => 'Empty', 'posts' => new Collection, 'category_name' => null, 'category_url' => null],
        ['heading' => 'Active', 'posts' => new Collection([$post]), 'category_name' => 'Health', 'category_url' => '/health'],
    ];

    $service = Mockery::mock(NewsSpringboardService::class);
    $service->shouldReceive('resolveMagazine')
        ->once()
        ->withArgs(fn (array $config, string $handle): bool => $handle === 'news'
            && $config['lead_count'] === 3
            && $config['lead_sources'] === []
            && data_get($config, 'sections.0.heading') === 'From context')
        ->andReturn([
            'lead' => new Collection,
            'sections' => $resolvedSections,
        ]);
    $this->container->instance(NewsSpringboardService::class, $service);

    $result = makeNewsHomeTag(
        context: [
            'lead_count' => new Value('3'),
            'sections' => new Value([['section_heading' => 'From context', 'category' => ['cat-a'], 'count' => 2]]),
        ],
    )->index();

    expect($result['sections'])->toBe($resolvedSections)
        ->and($result['no_results'])->toBeFalse();
});

it('falls back to DEFAULT_LEAD_COUNT for non-positive or non-numeric lead_count and reports no_results true when all sections are empty', function (mixed $leadCount): void {
    bindNewsHomeSite('news');

    $resolvedSections = [
        ['heading' => 'Empty', 'posts' => new Collection, 'category_name' => null, 'category_url' => null],
    ];

    $service = Mockery::mock(NewsSpringboardService::class);
    $service->shouldReceive('resolveMagazine')
        ->once()
        ->withArgs(fn (array $config, string $handle): bool => $handle === 'news'
            && $config['lead_count'] === NewsSpringboardService::DEFAULT_LEAD_COUNT
            && $config['lead_sources'] === []
            && $config['sections'] === [])
        ->andReturn([
            'lead' => new Collection,
            'sections' => $resolvedSections,
        ]);
    $this->container->instance(NewsSpringboardService::class, $service);

    $result = makeNewsHomeTag(context: ['lead_count' => $leadCount])->index();

    expect($result['no_results'])->toBeTrue();
})->with([
    'zero' => [0],
    'negative' => [-2],
    'non-numeric' => ['abc'],
    'null' => [null],
]);

it('accepts a lead_count of 1', function (): void {
    bindNewsHomeSite('news');

    $service = Mockery::mock(NewsSpringboardService::class);
    $service->shouldReceive('resolveMagazine')
        ->once()
        ->withArgs(fn (array $config, string $handle): bool => $handle === 'news' && $config['lead_count'] === 1)
        ->andReturn([
            'lead' => new Collection,
            'sections' => [],
        ]);
    $this->container->instance(NewsSpringboardService::class, $service);

    expect(makeNewsHomeTag(params: ['lead_count' => '1'])->index()['no_results'])->toBeTrue();
});
