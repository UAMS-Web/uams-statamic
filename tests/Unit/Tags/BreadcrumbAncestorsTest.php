<?php

declare(strict_types=1);

use App\Tags\BreadcrumbAncestors;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\Site;
use Statamic\Sites\Site as SiteObject;
use Statamic\Tags\Context;
use Statamic\Tags\Parameters;

/**
 * Pure unit tests for the {{ breadcrumb_ancestors }} tag.
 *
 * Covers ancestor chain resolution, missing intermediates, site mount prefix stripping,
 * scalar/non-scalar parameter and accessor narrowing, and context/request URI fallbacks
 * without booting Laravel.
 *
 * @command  vendor/bin/pest tests/Unit/Tags/BreadcrumbAncestorsTest.php
 */
uses(MockeryPHPUnitIntegration::class);

covers(BreadcrumbAncestors::class);

/** @var Container|null $previousContainer */
$previousContainer = null;

beforeEach(function () use (&$previousContainer): void {
    $previousContainer = Container::getInstance();
    $container = new Container;
    $container->instance('request', Request::create('/'));
    Container::setInstance($container);
});

afterEach(function () use (&$previousContainer): void {
    Facade::clearResolvedInstances();
    if ($previousContainer instanceof Container) {
        Container::setInstance($previousContainer);
    }
});

/**
 * Create a BreadcrumbAncestors tag with explicit context and parameters.
 *
 * @param  array<string, mixed>  $contextData
 * @param  array<string, mixed>  $params
 */
function makeBreadcrumbTag(array $contextData = [], array $params = []): BreadcrumbAncestors
{
    $tag = new BreadcrumbAncestors;
    $tag->setContext(new Context($contextData));
    $tag->setParameters(new Parameters($params));

    return $tag;
}

/**
 * Swap the Site facade with mocks for current() and get().
 */
function swapBreadcrumbSites(mixed $currentHandle, mixed $lookedUpSite = 'same', mixed $currentUrl = 'http://example.test'): void
{
    $current = Mockery::mock(SiteObject::class);
    $current->shouldReceive('handle')->andReturn($currentHandle);
    $current->shouldReceive('url')->andReturn($currentUrl);

    $sites = Mockery::mock();
    $sites->shouldReceive('current')->andReturn($current);
    $sites->shouldReceive('get')->andReturn($lookedUpSite === 'same' ? $current : $lookedUpSite);

    Site::swap($sites);
}

/**
 * Build a mock Statamic entry exposing url() and value().
 */
function makeBreadcrumbEntry(mixed $url, mixed $title, mixed $navAltName = null): StatamicEntry&MockInterface
{
    $entry = Mockery::mock(StatamicEntry::class);
    $entry->shouldReceive('url')->andReturn($url);
    $entry->shouldReceive('value')->with('nav_alt_name')->andReturn($navAltName);
    $entry->shouldReceive('value')->with('title')->andReturn($title);

    return $entry;
}

it('returns false when the URI has no segments or only a single segment', function (string $uri): void {
    swapBreadcrumbSites('default');
    EntryFacade::swap(Mockery::mock());

    expect(makeBreadcrumbTag(params: ['url' => $uri])->index())->toBeFalse();
})->with([
    'empty' => [''],
    'root slash' => ['/'],
    'multiple slashes' => ['///'],
    'single segment' => ['/hotdog'],
]);

it('resolves ordered ancestors, skips missing intermediates, and filters empty segments', function (): void {
    swapBreadcrumbSites('default');

    $entries = Mockery::mock();
    $entries->shouldReceive('findByUri')->once()->with('/a', 'default')->andReturnNull();
    $entries->shouldReceive('findByUri')->once()->with('/a/b', 'default')->andReturn(makeBreadcrumbEntry('/a/b', 'B'));
    $entries->shouldReceive('findByUri')->once()->with('/a/b/c', 'default')->andReturn(makeBreadcrumbEntry('/a/b/c', 'C', 'Alt C'));
    EntryFacade::swap($entries);

    expect(makeBreadcrumbTag(params: ['url' => '/a//b/c/missing/'])->index())->toBe([
        ['url' => '/a/b', 'title' => 'B'],
        ['url' => '/a/b/c', 'title' => 'Alt C'],
    ]);
});

it('returns false when no ancestor paths resolve to entries', function (): void {
    swapBreadcrumbSites('default');

    $entries = Mockery::mock();
    $entries->shouldReceive('findByUri')->with('/a', 'default')->andReturnNull();
    $entries->shouldReceive('findByUri')->with('/a/b', 'default')->andReturnNull();
    EntryFacade::swap($entries);

    expect(makeBreadcrumbTag(params: ['url' => '/a/b/missing'])->index())->toBeFalse();
});

it('coerces scalar entry URLs and labels and defaults non-scalar values to empty strings', function (): void {
    swapBreadcrumbSites('default');

    $entries = Mockery::mock();
    $entries->shouldReceive('findByUri')->once()->with('/scalar', 'default')
        ->andReturn(makeBreadcrumbEntry(123, 456));
    $entries->shouldReceive('findByUri')->once()->with('/scalar/nonscalar', 'default')
        ->andReturn(makeBreadcrumbEntry(['bad'], ['bad'], ''));
    EntryFacade::swap($entries);

    expect(makeBreadcrumbTag(params: ['url' => '/scalar/nonscalar/missing'])->index())->toBe([
        ['url' => '123', 'title' => '456'],
        ['url' => '', 'title' => ''],
    ]);
});

it('uses explicit scalar in param and coerces non-scalar in/url params to empty strings', function (): void {
    $site = Mockery::mock(SiteObject::class);
    $site->shouldReceive('handle')->andReturn('default');
    $site->shouldReceive('url')->andReturn('http://example.test');

    $sites = Mockery::mock();
    $sites->shouldReceive('current')->andReturn($site);
    $sites->shouldReceive('get')->once()->with('42')->andReturn($site);
    Site::swap($sites);

    $entries = Mockery::mock();
    $entries->shouldReceive('findByUri')->once()->with('/a', '42')->andReturn(makeBreadcrumbEntry('/a', 'A'));
    EntryFacade::swap($entries);

    expect(makeBreadcrumbTag(params: ['in' => 42, 'url' => '/a/missing'])->index())->toBe([
        ['url' => '/a', 'title' => 'A'],
    ]);

    $sites2 = Mockery::mock();
    $sites2->shouldReceive('current')->andReturn($site);
    $sites2->shouldReceive('get')->once()->with('')->andReturnNull();
    Site::swap($sites2);

    $entries2 = Mockery::mock();
    $entries2->shouldReceive('findByUri')->once()->with('/a', '')->andReturn(makeBreadcrumbEntry('/a', 'A'));
    EntryFacade::swap($entries2);

    expect(makeBreadcrumbTag(params: ['in' => ['bad'], 'url' => '/a/missing'])->index())->toBe([
        ['url' => '/a', 'title' => 'A'],
    ]);

    swapBreadcrumbSites('default');
    EntryFacade::swap(Mockery::mock());

    expect(makeBreadcrumbTag(params: ['url' => ['bad']])->index())->toBeFalse()
        ->and(makeBreadcrumbTag(params: ['url' => 123])->index())->toBeFalse();
});

it('strips a path-mounted site prefix and leaves non-matching, root, or missing sites unchanged', function (): void {
    // Path-mounted site with trailing slash in URL
    swapBreadcrumbSites('child', 'same', 'http://example.test/medicine/anesthesiology/');
    $entries = Mockery::mock();
    $entries->shouldReceive('findByUri')->once()->with('/test', 'child')
        ->andReturn(makeBreadcrumbEntry('/medicine/anesthesiology/test', 'Test'));
    EntryFacade::swap($entries);

    expect(makeBreadcrumbTag(params: ['url' => '/medicine/anesthesiology/test/missing'])->index())->toBe([
        ['url' => '/medicine/anesthesiology/test', 'title' => 'Test'],
    ]);

    // URI that does not start with the mount prefix
    swapBreadcrumbSites('child', 'same', 'http://example.test/medicine/anesthesiology');
    $entries2 = Mockery::mock();
    $entries2->shouldReceive('findByUri')->once()->with('/other', 'child')
        ->andReturn(makeBreadcrumbEntry('/other', 'Other'));
    EntryFacade::swap($entries2);

    expect(makeBreadcrumbTag(params: ['url' => '/other/missing'])->index())->toBe([
        ['url' => '/other', 'title' => 'Other'],
    ]);

    // Site URL is non-scalar (object) or integer
    swapBreadcrumbSites('child', 'same', new stdClass);
    $entries3 = Mockery::mock();
    $entries3->shouldReceive('findByUri')->once()->with('/a', 'child')
        ->andReturn(makeBreadcrumbEntry('/a', 'A'));
    EntryFacade::swap($entries3);

    expect(makeBreadcrumbTag(params: ['url' => '/a/missing'])->index())->toBe([
        ['url' => '/a', 'title' => 'A'],
    ]);

    swapBreadcrumbSites('child', 'same', 123);
    $entries4 = Mockery::mock();
    $entries4->shouldReceive('findByUri')->once()->with('/a', 'child')
        ->andReturn(makeBreadcrumbEntry('/a', 'A'));
    EntryFacade::swap($entries4);

    expect(makeBreadcrumbTag(params: ['url' => '/a/missing'])->index())->toBe([
        ['url' => '/a', 'title' => 'A'],
    ]);

    // Malformed site URL where parse_url returns false
    swapBreadcrumbSites('child', 'same', 'http://:80');
    $entries5 = Mockery::mock();
    $entries5->shouldReceive('findByUri')->once()->with('/a', 'child')
        ->andReturn(makeBreadcrumbEntry('/a', 'A'));
    EntryFacade::swap($entries5);

    expect(makeBreadcrumbTag(params: ['url' => '/a/missing'])->index())->toBe([
        ['url' => '/a', 'title' => 'A'],
    ]);
});

it('falls back to context current_uri and then request path when url param is omitted', function (): void {
    swapBreadcrumbSites('default');

    // Context current_uri string
    $entries = Mockery::mock();
    $entries->shouldReceive('findByUri')->once()->with('/ctx', 'default')
        ->andReturn(makeBreadcrumbEntry('/ctx', 'Ctx'));
    EntryFacade::swap($entries);

    expect(makeBreadcrumbTag(contextData: ['current_uri' => '/ctx/missing'])->index())->toBe([
        ['url' => '/ctx', 'title' => 'Ctx'],
    ]);

    // Context current_uri scalar int and non-scalar array
    EntryFacade::swap(Mockery::mock());
    expect(makeBreadcrumbTag(contextData: ['current_uri' => 123])->index())->toBeFalse()
        ->and(makeBreadcrumbTag(contextData: ['current_uri' => ['not-scalar']])->index())->toBeFalse();

    // Request path fallback when current_uri is absent (root-mounted site)
    Container::getInstance()->instance('request', Request::create('/req-a/req-b/missing'));

    $entries2 = Mockery::mock();
    $entries2->shouldReceive('findByUri')->once()->with('/req-a', 'default')
        ->andReturn(makeBreadcrumbEntry('/req-a', 'Req A'));
    $entries2->shouldReceive('findByUri')->once()->with('/req-a/req-b', 'default')
        ->andReturn(makeBreadcrumbEntry('/req-a/req-b', 'Req B'));
    EntryFacade::swap($entries2);

    expect(makeBreadcrumbTag()->index())->toBe([
        ['url' => '/req-a', 'title' => 'Req A'],
        ['url' => '/req-a/req-b', 'title' => 'Req B'],
    ]);

    // Request path fallback on a path-mounted site requires the leading slash before prefix matching
    swapBreadcrumbSites('child', 'same', 'http://example.test/medicine/anesthesiology');
    Container::getInstance()->instance('request', Request::create('/medicine/anesthesiology/test/missing'));

    $entries3 = Mockery::mock();
    $entries3->shouldReceive('findByUri')->once()->with('/test', 'child')
        ->andReturn(makeBreadcrumbEntry('/medicine/anesthesiology/test', 'Test'));
    EntryFacade::swap($entries3);

    expect(makeBreadcrumbTag()->index())->toBe([
        ['url' => '/medicine/anesthesiology/test', 'title' => 'Test'],
    ]);
});
