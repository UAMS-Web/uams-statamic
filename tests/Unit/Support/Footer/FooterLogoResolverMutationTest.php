<?php

declare(strict_types=1);

use App\Support\Footer\FooterLogoResolver;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Contracts\Globals\GlobalSet as GlobalSetContract;
use Statamic\Facades\GlobalSet;
use Statamic\Globals\Variables;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(FooterLogoResolver::class);

/**
 * Narrow mutate coverage for FooterLogoResolver.
 *
 * @command  php artisan test --compact tests/Unit/Support/Footer/FooterLogoResolverMutationTest.php
 */

/**
 * Pad Pest's mutant-child timeout once per process under ``--mutate``.
 */
beforeEach(function (): void {
    static $padded = false;
    if ($padded) {
        return;
    }

    if (runningUnderMutate()) {
        usleep(20_000_000);
        $padded = true;
    }
});

function footerLogoResolverVars(
    string $handle,
    bool $ownsLogo,
    ?Variables $origin,
): Variables {
    $vars = Mockery::mock(Variables::class);
    $vars->shouldReceive('locale')->andReturn($handle);
    $vars->shouldReceive('data')->andReturn(collect(
        $ownsLogo ? ['global_footer_logo' => ['horizontal' => ['white' => 'logos::brand.svg']]] : [],
    ));
    $vars->shouldReceive('origin')->andReturn($origin);

    return $vars;
}

function footerLogoResolverGlobalSet(?Variables $currentSiteVars): void
{
    $set = Mockery::mock(GlobalSetContract::class);
    $set->shouldReceive('in')->andReturn($currentSiteVars);
    GlobalSet::shouldReceive('find')->with('uams_multisite_settings')->andReturn($set);
}

it('returns the current site when it sets its own logo', function (): void {
    footerLogoResolverGlobalSet(footerLogoResolverVars('health', true, footerLogoResolverVars('default', true, null)));

    expect((new FooterLogoResolver)->resolveOwnerHandle('health'))->toBe('health');
});

it('returns the closest ancestor that sets its own logo', function (): void {
    $grandparent = footerLogoResolverVars('medicine', true, null);
    $parent = footerLogoResolverVars('medicine_subsite', false, $grandparent);
    $child = footerLogoResolverVars('medicine_otolaryngology', false, $parent);
    footerLogoResolverGlobalSet($child);

    expect((new FooterLogoResolver)->resolveOwnerHandle('medicine_otolaryngology'))->toBe('medicine');
});

it('falls back to the top-most ancestor when no site owns a logo', function (): void {
    $grandparent = footerLogoResolverVars('default', false, null);
    $parent = footerLogoResolverVars('health', false, $grandparent);
    $child = footerLogoResolverVars('health_nicu', false, $parent);
    footerLogoResolverGlobalSet($child);

    expect((new FooterLogoResolver)->resolveOwnerHandle('health_nicu'))->toBe('default');
});

it('treats a present-but-empty logo value as not owned', function (): void {
    $vars = Mockery::mock(Variables::class);
    $vars->shouldReceive('locale')->andReturn('health');
    $vars->shouldReceive('data')->andReturn(collect(['global_footer_logo' => []]));
    $vars->shouldReceive('origin')->andReturn(footerLogoResolverVars('default', true, null));
    footerLogoResolverGlobalSet($vars);

    expect((new FooterLogoResolver)->resolveOwnerHandle('health'))->toBe('default');
});

it('returns the current site when the global set is missing', function (): void {
    GlobalSet::shouldReceive('find')->with('uams_multisite_settings')->andReturnNull();

    expect((new FooterLogoResolver)->resolveOwnerHandle('orphan_site'))->toBe('orphan_site');
});

it('treats non-collection own-data as not owned', function (): void {
    $current = Mockery::mock(Variables::class);
    $current->shouldReceive('locale')->andReturn('health_nicu');
    $current->shouldReceive('data')->andReturn(['global_footer_logo' => 'x']);
    $current->shouldReceive('origin')->andReturn(footerLogoResolverVars('health', true, null));
    footerLogoResolverGlobalSet($current);

    expect((new FooterLogoResolver)->resolveOwnerHandle('health_nicu'))->toBe('health');
});

it('yields an empty handle for a logo owner with a non-string locale', function (): void {
    $owner = Mockery::mock(Variables::class);
    $owner->shouldReceive('locale')->andReturn(null);
    $owner->shouldReceive('data')->andReturn(collect(['global_footer_logo' => ['horizontal' => ['white' => 'logos::x.svg']]]));
    $owner->shouldReceive('origin')->andReturn(null);
    footerLogoResolverGlobalSet($owner);

    expect((new FooterLogoResolver)->resolveOwnerHandle('whatever'))->toBe('');
});
