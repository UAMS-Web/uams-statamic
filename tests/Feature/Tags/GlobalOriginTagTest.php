<?php

declare(strict_types=1);

use App\Support\Forms\FormRequestContext;
use App\Tags\GlobalOrigin;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Contracts\Data\Localization;
use Statamic\Contracts\Globals\GlobalSet as GlobalSetContract;
use Statamic\Contracts\Globals\Variables as GlobalVariablesContract;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * @covers \App\Tags\GlobalOrigin
 *
 * Mutation: 100% on App\Tags\GlobalOrigin (44 mutants, --covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Tags/GlobalOriginTagTest.php
 */
it('index returns empty string when handle missing', function () {
    $tag = new GlobalOrigin;
    $tag->setContext([]);
    $tag->setParameters([]);

    expect($tag->index())->toBe('');
});

it('index returns empty when global localization missing', function () {
    $set = Mockery::mock(GlobalSetContract::class);
    $set->shouldReceive('in')
        ->once()
        ->with('default')
        ->andReturn(null);

    GlobalSet::shouldReceive('find')
        ->once()
        ->with('uams_multisite_settings')
        ->andReturn($set);

    Site::shouldReceive('current')
        ->once()
        ->andReturn(
            new class
            {
                public function handle(): string
                {
                    return 'default';
                }
            }
        );

    $tag = new GlobalOrigin;
    $tag->setContext([]);
    $tag->setParameters([
        'handle' => 'uams_multisite_settings',
    ]);

    expect($tag->index())->toBe('');
});

it('index recursive returns empty array when subject is root', function () {
    $vars = Mockery::mock(GlobalVariablesContract::class);
    $vars->shouldReceive('get')
        ->with('subject')
        ->andReturn('root');

    $set = Mockery::mock(GlobalSetContract::class);
    $set->shouldReceive('in')
        ->once()
        ->with('default')
        ->andReturn($vars);

    GlobalSet::shouldReceive('find')
        ->once()
        ->with('uams_multisite_settings')
        ->andReturn($set);

    Site::shouldReceive('current')
        ->once()
        ->andReturn(
            new class
            {
                public function handle(): string
                {
                    return 'default';
                }
            }
        );

    $tag = new GlobalOrigin;
    $tag->setContext([]);
    $tag->setParameters([
        'handle' => 'uams_multisite_settings',
        'recursive' => true,
    ]);

    expect($tag->index())->toBe([]);
});

it('index recursive includes subject for each ancestor', function () {
    // The current site's origin chain leads to a root-level brand parent.
    $origin = Mockery::mock(Localization::class);
    $origin->shouldReceive('locale')
        ->once()
        ->andReturn('rootsite');

    $childVars = Mockery::mock(GlobalVariablesContract::class);
    $childVars->shouldReceive('get')
        ->with('subject')
        ->andReturn('major_entity');
    $childVars->shouldReceive('origin')
        ->andReturn($origin);

    $rootVars = Mockery::mock(GlobalVariablesContract::class);
    $rootVars->shouldReceive('get')
        ->with('subject')
        ->andReturn('root');

    $set = Mockery::mock(GlobalSetContract::class);
    $set->shouldReceive('in')
        ->with('default')
        ->andReturn($childVars);
    $set->shouldReceive('in')
        ->with('rootsite')
        ->andReturn($rootVars);

    GlobalSet::shouldReceive('find')
        ->with('uams_multisite_settings')
        ->andReturn($set);

    Site::shouldReceive('current')
        ->once()
        ->andReturn(
            new class
            {
                public function handle(): string
                {
                    return 'default';
                }
            }
        );

    Site::shouldReceive('get')
        ->with('rootsite')
        ->andReturn(
            new class
            {
                public function name(): string
                {
                    return 'University of Arkansas for Medical Sciences';
                }

                public function absoluteUrl(): string
                {
                    return 'http://uams-statamic.test';
                }
            }
        );

    $tag = new GlobalOrigin;
    $tag->setContext([]);
    $tag->setParameters([
        'handle' => 'uams_multisite_settings',
        'recursive' => true,
    ]);

    $result = $tag->index();

    expect($result)->toBeArray();
    expect($result)->toHaveCount(1);
    expect(data_get($result, '0.title'))->toBe('University of Arkansas for Medical Sciences');
    expect(data_get($result, '0.url'))->toBe('http://uams-statamic.test');
    expect(data_get($result, '0.subject'))->toBe('root');
});

it('index non recursive returns origin locale string', function () {
    $origin = Mockery::mock(Localization::class);
    $origin->shouldReceive('locale')
        ->once()
        ->andReturn('parent-site');

    $vars = Mockery::mock(GlobalVariablesContract::class);
    $vars->shouldReceive('origin')
        ->once()
        ->andReturn($origin);

    $set = Mockery::mock(GlobalSetContract::class);
    $set->shouldReceive('in')
        ->once()
        ->with('default')
        ->andReturn($vars);

    GlobalSet::shouldReceive('find')
        ->once()
        ->with('uams_multisite_settings')
        ->andReturn($set);

    Site::shouldReceive('current')
        ->once()
        ->andReturn(
            new class
            {
                public function handle(): string
                {
                    return 'default';
                }
            }
        );

    $tag = new GlobalOrigin;
    $tag->setContext([]);
    $tag->setParameters([
        'handle' => 'uams_multisite_settings',
    ]);

    expect($tag->index())->toBe('parent-site');
});

it('index non recursive returns empty string when origin missing', function () {
    // A root brand's global set has no origin, so origin() returns null.
    $vars = Mockery::mock(GlobalVariablesContract::class);
    $vars->shouldReceive('origin')
        ->once()
        ->andReturn(null);

    $set = Mockery::mock(GlobalSetContract::class);
    $set->shouldReceive('in')
        ->once()
        ->with('default')
        ->andReturn($vars);

    GlobalSet::shouldReceive('find')
        ->once()
        ->with('uams_multisite_settings')
        ->andReturn($set);

    Site::shouldReceive('current')
        ->once()
        ->andReturn(
            new class
            {
                public function handle(): string
                {
                    return 'default';
                }
            }
        );

    $tag = new GlobalOrigin;
    $tag->setContext([]);
    $tag->setParameters([
        'handle' => 'uams_multisite_settings',
    ]);

    // Must be an empty string, not null, to satisfy the string|array return type.
    expect($tag->index())->toBe('');
});

it('index prefers the captured form-request site over the live current site', function () {
    // Simulates a queued form-email render (UAMS-Web/uams-statamic-archive#199/UAMS-Web/uams-statamic-archive#830): a FormRequestContext is active
    // carrying the submitter's site ('health') while the worker's live Site::current() is
    // 'default'. The tag must resolve the global set against the CAPTURED site, so its
    // default origin reflects the submitter, not the worker. The `->in('health')`
    // expectation is the proof: a regression that read Site::current() would query
    // `->in('default')`, which this mock never allows, and the assertion would fail.
    $origin = Mockery::mock(Localization::class);
    $origin->shouldReceive('locale')
        ->once()
        ->andReturn('parent-site');

    $vars = Mockery::mock(GlobalVariablesContract::class);
    $vars->shouldReceive('origin')
        ->once()
        ->andReturn($origin);

    $set = Mockery::mock(GlobalSetContract::class);
    $set->shouldReceive('in')
        ->once()
        ->with('health')
        ->andReturn($vars);

    GlobalSet::shouldReceive('find')
        ->once()
        ->with('uams_multisite_settings')
        ->andReturn($set);

    Site::shouldReceive('current')
        ->andReturn(
            new class
            {
                public function handle(): string
                {
                    return 'default';
                }
            }
        );

    $tag = new GlobalOrigin;
    $tag->setContext([]);
    $tag->setParameters([
        'handle' => 'uams_multisite_settings',
    ]);

    // Activate the captured submitter context around the render, exactly as the queued
    // SendFormNotification job does via FormRequestContext::during().
    $captured = new FormRequestContext(ip: '', referrer: '', query: [], siteHandle: 'health');

    expect($captured->during(fn () => $tag->index()))->toBe('parent-site');
});

it('falls back to the live current site when the captured form-request site is empty', function () {
    // An active FormRequestContext whose siteHandle is empty must NOT override the live
    // site — the tag falls back to Site::current() ('default'). This guards the
    // `siteHandle !== ''` condition so it cannot be mutated away.
    $origin = Mockery::mock(Localization::class);
    $origin->shouldReceive('locale')
        ->once()
        ->andReturn('parent-site');

    $vars = Mockery::mock(GlobalVariablesContract::class);
    $vars->shouldReceive('origin')
        ->once()
        ->andReturn($origin);

    $set = Mockery::mock(GlobalSetContract::class);
    $set->shouldReceive('in')
        ->once()
        ->with('default')
        ->andReturn($vars);

    GlobalSet::shouldReceive('find')
        ->once()
        ->with('uams_multisite_settings')
        ->andReturn($set);

    Site::shouldReceive('current')
        ->andReturn(
            new class
            {
                public function handle(): string
                {
                    return 'default';
                }
            }
        );

    $tag = new GlobalOrigin;
    $tag->setContext([]);
    $tag->setParameters([
        'handle' => 'uams_multisite_settings',
    ]);

    $captured = new FormRequestContext(ip: '', referrer: '', query: [], siteHandle: '');

    expect($captured->during(fn () => $tag->index()))->toBe('parent-site');
});
