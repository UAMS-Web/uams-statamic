<?php

declare(strict_types=1);

use App\Tags\GetSite;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Facades\Site;
use Statamic\Sites\Site as SiteModel;
use Tests\Support\Doubles\GetSiteTagHandleSource;
use Tests\Support\Doubles\GetSiteTagSiteWithName;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Feature tests for the App\Tags\GetSite Antlers tag (fetch a site variable for a
 * specific site, or the site object / [] / null fallbacks).
 *
 * Mutation: no survivor on App\Tags\GetSite, without --covered-only.
 *
 * @command  php artisan test --compact tests/Feature/Tags/GetSiteTagTest.php
 */
it('returns null without a lookup when the handle is missing or empty', function (array $parameters): void {
    Site::shouldReceive('get')->never();

    $tag = new GetSite;
    $tag->setContext([]);
    $tag->setParameters($parameters);

    expect($tag->index())->toBeNull();
})->with([
    'missing handle' => [[]],
    'empty string handle' => [['handle' => '']],
]);

it('returns null when the site is unknown and a var is requested', function () {
    Site::shouldReceive('get')
        ->once()
        ->with('missing')
        ->andReturn(null);

    $tag = new GetSite;
    $tag->setContext([]);
    $tag->setParameters([
        'handle' => 'missing',
        'var' => 'name',
    ]);

    expect($tag->index())->toBeNull();
});

it('returns an empty array when the site is unknown and no var is given', function () {
    Site::shouldReceive('get')
        ->once()
        ->with('missing')
        ->andReturn(null);

    $tag = new GetSite;
    $tag->setContext([]);
    $tag->setParameters([
        'handle' => 'missing',
    ]);

    expect($tag->index())->toBe([]);
});

it('returns the requested property when the site and var are present', function () {
    $site = new GetSiteTagSiteWithName('Example');

    Site::shouldReceive('get')
        ->once()
        ->with('default')
        ->andReturn($site);

    $tag = new GetSite;
    $tag->setContext([]);
    $tag->setParameters([
        'handle' => 'default',
        'var' => 'name',
    ]);

    expect($tag->index())->toBe('Example');
});

it('accepts a site object whose handle method resolves the key', function () {
    $handleSource = new GetSiteTagHandleSource;

    $site = Mockery::mock(SiteModel::class);

    Site::shouldReceive('get')
        ->once()
        ->with('default')
        ->andReturn($site);

    $tag = new GetSite;
    $tag->setContext([]);
    $tag->setParameters([
        'handle' => $handleSource,
    ]);

    expect($tag->index())->toBe($site);
});

it('returns the site for an object handle and an empty var', function (): void {
    $site = new GetSiteTagSiteWithName('Example');

    Site::shouldReceive('get')
        ->once()
        ->with('default')
        ->andReturn($site);

    $tag = new GetSite;
    $tag->setContext([]);
    $tag->setParameters([
        'handle' => (object) ['handle' => 'default'],
        'var' => '',
    ]);

    expect($tag->index())->toBe($site);
});

it('returns null without a lookup when an object has no usable site handle', function (object $handle): void {
    Site::shouldReceive('get')->never();

    $tag = new GetSite;
    $tag->setContext([]);
    $tag->setParameters(['handle' => $handle]);

    expect($tag->index())->toBeNull();
})->with([
    'empty handle property' => [(object) ['handle' => '']],
    'non-string handle property' => [(object) ['handle' => 42]],
    'missing handle property' => [(object) []],
]);
