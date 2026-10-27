<?php

declare(strict_types=1);

use App\Support\Multisite\ChildSitesResolver;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;
use Statamic\Globals\Variables;
use Statamic\Sites\Site as SiteObject;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(ChildSitesResolver::class);

/**
 * Coverage for the immediate-child-sites resolution (UAMS-Web/uams-statamic-archive#219). Runs against the
 * real multisite tree in the `content/` submodule (the parent/child edges live
 * in `uams_multisite_settings` localization origins); self-skips without it.
 *
 * Mutation: tracked under App\Support\Multisite\ChildSitesResolver
 * (--covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Support/Multisite/ChildSitesResolverTest.php
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

/**
 * Skip unless the multisite-settings global set is present (content submodule).
 */
function childSitesRequireGlobals(): void
{
    if (! GlobalSet::find('uams_multisite_settings')) {
        ContentSubmodule::reportMissing('uams_multisite_settings global');
    }
}

it('returns the immediate children of a major-entity site', function (): void {
    childSitesRequireGlobals();

    $children = (new ChildSitesResolver)->forSite('health')->all();

    expect($children)->toContain('health_ar_impact');
    // Not itself, and not its own parent.
    expect($children)->not->toContain('health');
    expect($children)->not->toContain('default');
});

it('returns the root-level children for the default site', function (): void {
    childSitesRequireGlobals();

    $children = (new ChildSitesResolver)->forSite('default')->all();

    expect($children)
        ->toContain('health')
        ->toContain('academic_affairs');
    expect($children)->not->toBeEmpty();
});

it('does not include grandchildren', function (): void {
    childSitesRequireGlobals();

    // health_ar_impact is a child of health, so it is NOT a direct child of default.
    expect((new ChildSitesResolver)->forSite('default')->all())
        ->not->toContain('health_ar_impact');
});

it('returns no children for a leaf site', function (): void {
    childSitesRequireGlobals();

    expect((new ChildSitesResolver)->forSite('medicine_otolaryngology')->all())->toBe([]);
});

it('returns no children for an unknown site', function (): void {
    childSitesRequireGlobals();

    expect((new ChildSitesResolver)->forSite('definitely_not_a_site_xyz')->all())->toBe([]);
});

it('returns empty when the global set is missing', function (): void {
    GlobalSet::shouldReceive('find')->with('uams_multisite_settings')->andReturnNull();

    expect((new ChildSitesResolver)->forSite('health')->all())->toBe([]);
});

it('skips malformed sites and non-localization origins (defensive)', function (): void {
    // Guards against a contract violation: a non-Site entry, a blank handle, or
    // a site whose multisite-settings localization is missing must all be
    // skipped rather than error, while a real child is still collected.
    $notASite = new stdClass;

    $blankHandle = Mockery::mock(SiteObject::class);
    $blankHandle->shouldReceive('handle')->andReturn('');

    $missingLocalization = Mockery::mock(SiteObject::class);
    $missingLocalization->shouldReceive('handle')->andReturn('orphan_site');

    $child = Mockery::mock(SiteObject::class);
    $child->shouldReceive('handle')->andReturn('the_child');

    Site::shouldReceive('all')->andReturn(collect([$notASite, $blankHandle, $missingLocalization, $child]));

    $childOrigin = Mockery::mock(Variables::class);
    $childOrigin->shouldReceive('locale')->andReturn('the_parent');
    $childVariables = Mockery::mock(Variables::class);
    $childVariables->shouldReceive('origin')->andReturn($childOrigin);

    $set = Mockery::mock(Statamic\Contracts\Globals\GlobalSet::class);
    $set->shouldReceive('in')->with('orphan_site')->andReturnNull();
    $set->shouldReceive('in')->with('the_child')->andReturn($childVariables);
    GlobalSet::shouldReceive('find')->with('uams_multisite_settings')->andReturn($set);

    expect((new ChildSitesResolver)->forSite('the_parent')->all())->toBe(['the_child']);
});
