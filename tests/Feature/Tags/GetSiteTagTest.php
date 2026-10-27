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
 * Mutation: 100% on App\Tags\GetSite (27 mutants, --covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Tags/GetSiteTagTest.php
 */
it('returns null when the handle is missing', function () {
    $tag = new GetSite;
    $tag->setContext([]);
    $tag->setParameters([]);

    expect($tag->index())->toBeNull();
});

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
