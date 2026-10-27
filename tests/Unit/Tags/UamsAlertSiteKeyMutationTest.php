<?php

declare(strict_types=1);

use App\Support\UamsAlert\AlertSiteKeyResolver;
use App\Tags\UamsAlertSiteKey;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Facades\Site;
use Statamic\Sites\Site as SiteObject;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(UamsAlertSiteKey::class);

/**
 * Pad Pest's mutant-child timeout once per process under ``--mutate``.
 */
beforeEach(function (): void {
    static $padded = false;
    if ($padded) {
        return;
    }

    if (runningUnderMutate()) {
        usleep(12_000_000);
        $padded = true;
    }
});

/**
 * @covers \App\Tags\UamsAlertSiteKey
 *
 * @command  php artisan test --compact tests/Unit/Tags/UamsAlertSiteKeyMutationTest.php
 */
it('resolves the alert bucket for the current site handle', function (): void {
    $site = Mockery::mock(SiteObject::class);
    $site->shouldReceive('handle')->once()->andReturn('health');
    Site::shouldReceive('current')->once()->andReturn($site);

    $resolver = Mockery::mock(AlertSiteKeyResolver::class);
    $resolver->shouldReceive('resolve')->once()->with('health')->andReturn('uamshealth');
    app()->instance(AlertSiteKeyResolver::class, $resolver);

    expect((new UamsAlertSiteKey)->index())->toBe('uamshealth');
});

it('passes an empty handle when the current site is not a Site object', function (): void {
    Site::shouldReceive('current')->once()->andReturn(null);

    $resolver = Mockery::mock(AlertSiteKeyResolver::class);
    $resolver->shouldReceive('resolve')->once()->with('')->andReturn('uams');
    app()->instance(AlertSiteKeyResolver::class, $resolver);

    expect((new UamsAlertSiteKey)->index())->toBe('uams');
});

it('casts a non-string site handle to an empty string before resolve', function (): void {
    $site = Mockery::mock(SiteObject::class);
    $site->shouldReceive('handle')->once()->andReturn(123);
    Site::shouldReceive('current')->once()->andReturn($site);

    $resolver = Mockery::mock(AlertSiteKeyResolver::class);
    $resolver->shouldReceive('resolve')->once()->with('')->andReturn('uams');
    app()->instance(AlertSiteKeyResolver::class, $resolver);

    expect((new UamsAlertSiteKey)->index())->toBe('uams');
});
