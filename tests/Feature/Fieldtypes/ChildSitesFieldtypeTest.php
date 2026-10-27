<?php

declare(strict_types=1);

use App\Fieldtypes\ChildSites;
use App\Support\Multisite\ChildSitesResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Auth\File\User as FileUser;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Site;
use Statamic\Facades\User;
use Statamic\Fields\Field;
use Statamic\Sites\Site as SiteObject;
use Tests\TestCase;

use function Pest\Laravel\actingAs;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Wrap raw site-option rows as the loosely-typed option collection the fieldtype's
 * `filterToChildSites()` accepts (an invariant `Collection<int, mixed>`).
 *
 * @param  array<int, mixed>  $items
 * @return Collection<int, mixed>
 */
function childSiteOptionItems(array $items): Collection
{
    return collect($items);
}

/**
 * Coverage for the `child_sites` fieldtype (UAMS-Web/uams-statamic-archive#219): it narrows the site picker to
 * the editing site's direct children. The children come from
 * ChildSitesResolver (tested separately); this checks the filter that restricts
 * the option list, without the CP's authenticated relationship-index request.
 *
 * @covers \App\Fieldtypes\ChildSites
 *
 * @command  php artisan test --compact tests/Feature/Fieldtypes/ChildSitesFieldtypeTest.php
 */
it('keeps only the option items whose handle is a direct child site', function (): void {
    $resolver = Mockery::mock(ChildSitesResolver::class);
    $resolver->shouldReceive('forSite')->andReturn(collect(['health_ar_impact', 'health_coronavirus']));
    app()->instance(ChildSitesResolver::class, $resolver);

    $filtered = (new ChildSites)->filterToChildSites(childSiteOptionItems([
        ['id' => 'health_ar_impact', 'title' => 'AR-IMPACT'],
        ['id' => 'default', 'title' => 'UAMS'],
        ['id' => 'health_coronavirus', 'title' => 'Coronavirus'],
    ]));

    expect($filtered->pluck('id')->all())->toBe(['health_ar_impact', 'health_coronavirus']);
});

it('drops malformed option items', function (): void {
    $resolver = Mockery::mock(ChildSitesResolver::class);
    $resolver->shouldReceive('forSite')->andReturn(collect(['health']));
    app()->instance(ChildSitesResolver::class, $resolver);

    $filtered = (new ChildSites)->filterToChildSites(childSiteOptionItems([
        ['id' => 'health', 'title' => 'Health'],
        'not-an-array',
        ['title' => 'no id key'],
        ['id' => 123, 'title' => 'non-string id'],
    ]));

    expect($filtered->pluck('id')->all())->toBe(['health']);
});

it('returns nothing when the editing site has no children', function (): void {
    $resolver = Mockery::mock(ChildSitesResolver::class);
    $resolver->shouldReceive('forSite')->andReturn(collect());
    app()->instance(ChildSitesResolver::class, $resolver);

    $filtered = (new ChildSites)->filterToChildSites(childSiteOptionItems([
        ['id' => 'health', 'title' => 'Health'],
    ]));

    expect($filtered->all())->toBe([]);
});

it('registers the child_site_link blueprint with the child_sites field', function (): void {
    $blueprint = mustBe(Statamic\Fields\Blueprint::class, Blueprint::find('collections.pages.child_site_link'), 'blueprint');
    $childSiteField = mustBe(Field::class, $blueprint->field('child_site'), 'child site field');

    expect($blueprint)->not->toBeNull()
        ->and($blueprint->hasField('child_site'))->toBeTrue()
        ->and($childSiteField->type())->toBe('child_sites');
});

it('asks the resolver for the children of the selected Control Panel site', function (): void {
    // The editing site is the one selected in the Control Panel, not the default
    Site::setSelected('health');

    $resolver = Mockery::mock(ChildSitesResolver::class);
    $resolver->shouldReceive('forSite')->once()->with('health')->andReturn(collect(['health_ar_impact']));
    app()->instance(ChildSitesResolver::class, $resolver);

    $filtered = (new ChildSites)->filterToChildSites(childSiteOptionItems([
        ['id' => 'health_ar_impact', 'title' => 'AR-IMPACT'],
    ]));

    expect($filtered->pluck('id')->all())->toBe(['health_ar_impact']);
});

it('narrows the index items to the editing site\'s children', function (): void {
    // A super user can view every site, so the parent lists them all before the filter
    $user = mustBe(FileUser::class, User::make(), 'user');
    $user->email('child-sites-index@example.com');
    $user->makeSuper();
    actingAs($user);

    $resolver = Mockery::mock(ChildSitesResolver::class);
    $resolver->shouldReceive('forSite')->once()->with('default')->andReturn(collect(['health', 'health_coronavirus']));
    app()->instance(ChildSitesResolver::class, $resolver);

    $items = (new ChildSites)->getIndexItems(new Request);

    // The parent sorts by site name, and the filter keeps that order rather than the resolver's
    expect($items->pluck('id')->all())->toBe(['health_coronavirus', 'health']);
});

it('asks the resolver for the children of an empty handle when no site is selected', function (): void {
    // Without a selected site the editing handle falls back to an empty string
    Site::shouldReceive('selected')->once()->andReturn(null);

    $resolver = Mockery::mock(ChildSitesResolver::class);
    $resolver->shouldReceive('forSite')->once()->with('')->andReturn(collect());
    app()->instance(ChildSitesResolver::class, $resolver);

    expect((new ChildSites)->filterToChildSites(childSiteOptionItems([['id' => 'health']]))->all())->toBe([]);
});

it('asks the resolver for the children of an empty handle when the selected site has no string handle', function (): void {
    $site = Mockery::mock(SiteObject::class);
    $site->shouldReceive('handle')->andReturn(null);
    Site::shouldReceive('selected')->once()->andReturn($site);

    $resolver = Mockery::mock(ChildSitesResolver::class);
    $resolver->shouldReceive('forSite')->once()->with('')->andReturn(collect());
    app()->instance(ChildSitesResolver::class, $resolver);

    expect((new ChildSites)->filterToChildSites(childSiteOptionItems([['id' => 'health']]))->all())->toBe([]);
});
