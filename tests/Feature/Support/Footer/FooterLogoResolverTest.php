<?php

declare(strict_types=1);

use App\Support\Footer\FooterLogoResolver;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Facades\GlobalSet;
use Statamic\Globals\Variables;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Branch coverage for the footer-logo owner resolution (UAMS-Web/uams-statamic-archive#46) using a fully
 * mocked origin chain, so it is deterministic and needs no content. The real
 * multisite hierarchy is exercised by FooterLogoResolverContentTest.
 *
 * @covers \App\Support\Footer\FooterLogoResolver
 *
 * @command  php artisan test --compact tests/Feature/Support/Footer/FooterLogoResolverTest.php
 */

/**
 * Build a mocked `uams_multisite_settings` localization.
 *
 * @param  string  $handle  The site handle (locale)
 * @param  bool  $ownsLogo  Whether the site sets its own `global_footer_logo`
 * @param  Variables|null  $origin  The parent localization, or null at the root
 */
function fakeFooterVars(string $handle, bool $ownsLogo, ?Variables $origin): Variables
{
    $vars = Mockery::mock(Variables::class);
    $vars->shouldReceive('locale')->andReturn($handle);
    $vars->shouldReceive('data')->andReturn(collect(
        $ownsLogo ? ['global_footer_logo' => ['horizontal' => ['white' => 'logos::brand.svg']]] : []
    ));
    $vars->shouldReceive('origin')->andReturn($origin);

    return $vars;
}

/**
 * Swap `GlobalSet::find('uams_multisite_settings')` for a set returning the
 * given current-site localization (the chain is reachable via origin()).
 */
function fakeFooterGlobalSet(?Variables $currentSiteVars): void
{
    // The set must satisfy GlobalRepository::find()'s `?GlobalSet` return type.
    $set = Mockery::mock(Statamic\Contracts\Globals\GlobalSet::class);
    $set->shouldReceive('in')->andReturn($currentSiteVars);
    GlobalSet::shouldReceive('find')->with('uams_multisite_settings')->andReturn($set);
}

it('returns the current site when it sets its own logo', function (): void {
    fakeFooterGlobalSet(fakeFooterVars('health', true, fakeFooterVars('default', true, null)));

    expect((new FooterLogoResolver)->resolveOwnerHandle('health'))->toBe('health');
});

it('returns the closest ancestor that sets its own logo', function (): void {
    // child (no) → parent (no) → grandparent (yes)
    $grandparent = fakeFooterVars('medicine', true, null);
    $parent = fakeFooterVars('medicine_subsite', false, $grandparent);
    $child = fakeFooterVars('medicine_otolaryngology', false, $parent);
    fakeFooterGlobalSet($child);

    expect((new FooterLogoResolver)->resolveOwnerHandle('medicine_otolaryngology'))->toBe('medicine');
});

it('prefers the nearer ancestor when several own logos', function (): void {
    // child (no) → parent (yes) → grandparent (yes): the nearer parent wins.
    $grandparent = fakeFooterVars('default', true, null);
    $parent = fakeFooterVars('health', true, $grandparent);
    $child = fakeFooterVars('health_nicu', false, $parent);
    fakeFooterGlobalSet($child);

    expect((new FooterLogoResolver)->resolveOwnerHandle('health_nicu'))->toBe('health');
});

it('falls back to the top-most ancestor when no site owns a logo', function (): void {
    $grandparent = fakeFooterVars('default', false, null);
    $parent = fakeFooterVars('health', false, $grandparent);
    $child = fakeFooterVars('health_nicu', false, $parent);
    fakeFooterGlobalSet($child);

    expect((new FooterLogoResolver)->resolveOwnerHandle('health_nicu'))->toBe('default');
});

it('treats a present-but-empty logo value as not owned', function (): void {
    $vars = Mockery::mock(Variables::class);
    $vars->shouldReceive('locale')->andReturn('health');
    // The key exists but holds an empty array (cleared asset group).
    $vars->shouldReceive('data')->andReturn(collect(['global_footer_logo' => []]));
    $vars->shouldReceive('origin')->andReturn(fakeFooterVars('default', true, null));
    fakeFooterGlobalSet($vars);

    expect((new FooterLogoResolver)->resolveOwnerHandle('health'))->toBe('default');
});

it('returns the current site when the global set is missing', function (): void {
    GlobalSet::shouldReceive('find')->with('uams_multisite_settings')->andReturnNull();

    expect((new FooterLogoResolver)->resolveOwnerHandle('orphan_site'))->toBe('orphan_site');
});

it('treats non-collection own-data as not owned (defensive narrowing)', function (): void {
    // Variables::data() is documented to return a Collection; if a contract
    // violation hands back anything else, the site must read as "no own logo"
    // rather than error, so resolution continues up the chain.
    $current = Mockery::mock(Variables::class);
    $current->shouldReceive('locale')->andReturn('health_nicu');
    $current->shouldReceive('data')->andReturn(['global_footer_logo' => 'x']); // not a Collection
    $current->shouldReceive('origin')->andReturn(fakeFooterVars('health', true, null));
    fakeFooterGlobalSet($current);

    expect((new FooterLogoResolver)->resolveOwnerHandle('health_nicu'))->toBe('health');
});

it('yields an empty handle for a logo owner with a non-string locale (defensive)', function (): void {
    // A malformed localization with no string locale resolves to '' rather than
    // leaking a non-string handle.
    $owner = Mockery::mock(Variables::class);
    $owner->shouldReceive('locale')->andReturn(null);
    $owner->shouldReceive('data')->andReturn(collect(['global_footer_logo' => ['horizontal' => ['white' => 'logos::x.svg']]]));
    $owner->shouldReceive('origin')->andReturn(null);
    fakeFooterGlobalSet($owner);

    expect((new FooterLogoResolver)->resolveOwnerHandle('whatever'))->toBe('');
});
