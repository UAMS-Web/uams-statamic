<?php

declare(strict_types=1);

use App\Listeners\AppendPublishDependencyBanner;
use App\Services\PublishDependencies\PublishDependencyResolver;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry;
use Statamic\Events\EntryBlueprintFound;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Fields\Blueprint;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * @covers \App\Listeners\AppendPublishDependencyBanner
 *
 * UAMS-Web/uams-statamic-archive#1593: on EntryBlueprintFound, a read-only `publish_dependency_banner` field is
 * prepended to every blueprint that declares the tether field
 * (`publish_depends_on`) and belongs to an enabled collection — and to nothing
 * else. Mocked blueprint + collection + resolver — no content/ submodule required.
 *
 * Accepted equivalent mutants, annotated in the listener: in `collectionFor()`,
 * the `instanceof Blueprint` guard always holds because `handle()` returns first
 * for anything else, and the namespace path's `instanceof Collection` guard
 * returns `findByHandle()`'s `?Collection` result unchanged either way.
 *
 * @command  php artisan test --compact tests/Feature/Listeners/AppendPublishDependencyBannerTest.php
 */

/** A resolver double reporting whether the collection is enabled. */
function dependencyBannerResolver(bool $enabled, bool $expectCall = true): PublishDependencyResolver
{
    $resolver = Mockery::mock(PublishDependencyResolver::class);

    if ($expectCall) {
        $resolver->shouldReceive('isEnabledFor')->andReturn($enabled);
    } else {
        $resolver->shouldNotReceive('isEnabledFor');
    }

    return $resolver;
}

/**
 * A blueprint double: whether the banner field is already present, whether the
 * tether field is declared, and asserting the prepend of the banner field.
 */
function dependencyBannerBlueprint(bool $hasBanner, bool $hasTether, bool $expectPrepend, ?string $namespace = null): Blueprint
{
    $blueprint = Mockery::mock(Blueprint::class);
    $blueprint->shouldReceive('hasField')->with('publish_dependency_banner')->andReturn($hasBanner);
    $blueprint->shouldReceive('hasField')->with('publish_depends_on')->andReturn($hasTether);

    if ($namespace !== null) {
        $blueprint->shouldReceive('namespace')->andReturn($namespace);
    }

    if ($expectPrepend) {
        $blueprint->shouldReceive('ensureFieldPrepended')->once()->with(
            'publish_dependency_banner',
            Mockery::on(fn ($config): bool => $config === [
                'type' => 'publish_dependency_banner',
                'display' => 'Publish Dependency Status',
                'localizable' => false,
                'listable' => false,
            ]),
        );
    } else {
        $blueprint->shouldNotReceive('ensureFieldPrepended');
    }

    return $blueprint;
}

it('prepends the banner to a tether-declaring blueprint of an enabled collection', function (): void {
    $collection = Mockery::mock(Collection::class);
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('collection')->andReturn($collection);

    $blueprint = dependencyBannerBlueprint(hasBanner: false, hasTether: true, expectPrepend: true);

    (new AppendPublishDependencyBanner(dependencyBannerResolver(enabled: true)))
        ->handle(new EntryBlueprintFound($blueprint, $entry));
});

it('does nothing when the banner field is already present', function (): void {
    $blueprint = dependencyBannerBlueprint(hasBanner: true, hasTether: true, expectPrepend: false);

    (new AppendPublishDependencyBanner(dependencyBannerResolver(enabled: true, expectCall: false)))
        ->handle(new EntryBlueprintFound($blueprint, Mockery::mock(Entry::class)));
});

it('does nothing for a blueprint that did not declare the tether field', function (): void {
    $blueprint = dependencyBannerBlueprint(hasBanner: false, hasTether: false, expectPrepend: false);

    (new AppendPublishDependencyBanner(dependencyBannerResolver(enabled: true, expectCall: false)))
        ->handle(new EntryBlueprintFound($blueprint, Mockery::mock(Entry::class)));
});

it('does nothing when the collection is opted out', function (): void {
    $collection = Mockery::mock(Collection::class);
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('collection')->andReturn($collection);

    $blueprint = dependencyBannerBlueprint(hasBanner: false, hasTether: true, expectPrepend: false);

    (new AppendPublishDependencyBanner(dependencyBannerResolver(enabled: false)))
        ->handle(new EntryBlueprintFound($blueprint, $entry));
});

it('resolves the collection from the blueprint namespace when no entry is present', function (): void {
    $collection = Mockery::mock(Collection::class);
    CollectionFacade::shouldReceive('findByHandle')->with('clinical_resources')->andReturn($collection);

    $blueprint = dependencyBannerBlueprint(hasBanner: false, hasTether: true, expectPrepend: true, namespace: 'collections.clinical_resources');

    (new AppendPublishDependencyBanner(dependencyBannerResolver(enabled: true)))
        ->handle(new EntryBlueprintFound($blueprint));
});

it('does nothing and never dereferences a non-blueprint event', function (): void {
    $threw = null;

    try {
        (new AppendPublishDependencyBanner(dependencyBannerResolver(enabled: true, expectCall: false)))
            ->handle(new EntryBlueprintFound(null, Mockery::mock(Entry::class)));
    } catch (Throwable $e) {
        $threw = $e->getMessage();
    }

    expect($threw)->toBeNull();
});

it('does nothing when the entry reports something other than a collection', function (): void {
    // `Entry::collection()` is untyped, so its value is checked before it is
    // returned as a `?Collection`, and the resolver is never asked about it.
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('collection')->andReturn('clinical_resources');

    $blueprint = dependencyBannerBlueprint(hasBanner: false, hasTether: true, expectPrepend: false);

    (new AppendPublishDependencyBanner(dependencyBannerResolver(enabled: true, expectCall: false)))
        ->handle(new EntryBlueprintFound($blueprint, $entry));
});

it('does nothing, and looks nothing up, when the blueprint namespace is not a collection', function (): void {
    // A lookup of the namespace's tail would also find nothing, so only the
    // missing call shows the guard held.
    CollectionFacade::shouldReceive('findByHandle')->never();

    $blueprint = dependencyBannerBlueprint(hasBanner: false, hasTether: true, expectPrepend: false, namespace: 'globals.settings');

    (new AppendPublishDependencyBanner(dependencyBannerResolver(enabled: true, expectCall: false)))
        ->handle(new EntryBlueprintFound($blueprint));
});
