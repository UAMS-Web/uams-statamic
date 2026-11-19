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
 * in `uams_multisite_settings` localization origins); those cases self-skip without
 * it. The defensive and tree cases further down are hermetic (Mockery over the
 * global set and the site list), so they run everywhere.
 *
 * Mutation: measured under App\Support\Multisite\ChildSitesResolver without
 * --covered-only (#1087).
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

/**
 * Mock a hermetic multisite tree: each site's multisite-settings localization
 * and the locale of its origin.
 *
 * A `null` origin is a root localization (it has no origin); `false` is a site
 * with no localization at all.
 *
 * @param  array<string, string|false|null>  $origins  Site handle => origin locale
 */
function childSitesMockTree(array $origins): void
{
    $set = Mockery::mock(Statamic\Contracts\Globals\GlobalSet::class);
    $sites = [];

    foreach ($origins as $handle => $originLocale) {
        $site = Mockery::mock(SiteObject::class);
        $site->shouldReceive('handle')->andReturn($handle);
        $sites[] = $site;

        if ($originLocale === false) {
            $set->shouldReceive('in')->with($handle)->andReturnNull();

            continue;
        }

        $origin = null;
        if (\is_string($originLocale)) {
            $origin = Mockery::mock(Variables::class);
            $origin->shouldReceive('locale')->andReturn($originLocale);
        }

        $variables = Mockery::mock(Variables::class);
        $variables->shouldReceive('origin')->andReturn($origin);
        $set->shouldReceive('in')->with($handle)->andReturn($variables);
    }

    Site::shouldReceive('all')->andReturn(collect($sites));
    GlobalSet::shouldReceive('find')->with('uams_multisite_settings')->andReturn($set);
}

it('collects every direct child in site order, past roots and other parents', function (): void {
    // A root (no origin) and a child of another parent each come before a real
    // child, so neither may end the walk or be collected.
    childSitesMockTree([
        'the_root' => null,
        'first_child' => 'the_parent',
        'cousin' => 'another_parent',
        'no_localization' => false,
        'second_child' => 'the_parent',
    ]);

    expect((new ChildSitesResolver)->forSite('the_parent')->all())->toBe(['first_child', 'second_child'])
        ->and((new ChildSitesResolver)->forSite('another_parent')->all())->toBe(['cousin'])
        ->and((new ChildSitesResolver)->forSite('the_root')->all())->toBe([]);
});

it('skips a site whose handle is not a string, and keeps walking', function (): void {
    $nullHandle = Mockery::mock(SiteObject::class);
    $nullHandle->shouldReceive('handle')->andReturnNull();

    $child = Mockery::mock(SiteObject::class);
    $child->shouldReceive('handle')->andReturn('the_child');

    Site::shouldReceive('all')->andReturn(collect([$nullHandle, $child]));

    $origin = Mockery::mock(Variables::class);
    $origin->shouldReceive('locale')->andReturn('the_parent');
    $variables = Mockery::mock(Variables::class);
    $variables->shouldReceive('origin')->andReturn($origin);

    // Only the string handle is ever looked up.
    $set = Mockery::mock(Statamic\Contracts\Globals\GlobalSet::class);
    $set->shouldReceive('in')->once()->with('the_child')->andReturn($variables);
    GlobalSet::shouldReceive('find')->with('uams_multisite_settings')->andReturn($set);

    expect((new ChildSitesResolver)->forSite('the_parent')->all())->toBe(['the_child']);
});

it('returns empty, without a warning, when the site list is not iterable', function (): void {
    $set = Mockery::mock(Statamic\Contracts\Globals\GlobalSet::class);
    $set->shouldNotReceive('in');
    GlobalSet::shouldReceive('find')->with('uams_multisite_settings')->andReturn($set);
    Site::shouldReceive('all')->andReturnNull();

    expect((new ChildSitesResolver)->forSite('the_parent')->all())->toBe([]);
});

it('looks nothing up when the global set is missing, even with sites configured', function (): void {
    GlobalSet::shouldReceive('find')->with('uams_multisite_settings')->andReturnNull();
    childSitesSiteList(['the_child']);

    expect((new ChildSitesResolver)->forSite('the_parent')->all())->toBe([]);
});

/**
 * Mock `Site::all()` as sites with the given handles.
 *
 * @param  list<string>  $handles  Site handles, in order
 */
function childSitesSiteList(array $handles): void
{
    Site::shouldReceive('all')->andReturn(collect(array_map(function (string $handle): SiteObject {
        $site = Mockery::mock(SiteObject::class);
        $site->shouldReceive('handle')->andReturn($handle);

        return $site;
    }, $handles)));
}
