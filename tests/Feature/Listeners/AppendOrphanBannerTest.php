<?php

declare(strict_types=1);

use App\Listeners\AppendOrphanBanner;
use App\Services\OrphanedAncestors\OrphanedAncestorResolver;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry;
use Statamic\Events\EntryBlueprintFound;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Fields\Blueprint;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * @covers \App\Listeners\AppendOrphanBanner
 *
 * UAMS-Web/uams-statamic-archive#1429: the universal CP banner injection. On EntryBlueprintFound, a read-only
 * `orphan_banner` field is prepended to the blueprints of handled (routable +
 * hierarchical, not opted-out) collections, and to nothing else. Mocked
 * blueprint + collection + resolver — no content/ submodule required.
 *
 * Accepted equivalent mutants: the defensive `instanceof` / boolean guards inside
 * `collectionFor()` are masked by `handle()`'s own downstream
 * `! $collection instanceof Collection` check (a non-Collection there is caught
 * either way), so mutating them changes no observable outcome.
 *
 * @command  php artisan test --compact tests/Feature/Listeners/AppendOrphanBannerTest.php
 */

/** A resolver double reporting whether it handles the given collection. */
function bannerResolver(bool $handles, bool $expectCall = true): OrphanedAncestorResolver
{
    $resolver = Mockery::mock(OrphanedAncestorResolver::class);

    if ($expectCall) {
        $resolver->shouldReceive('handles')->andReturn($handles);
    } else {
        $resolver->shouldNotReceive('handles');
    }

    return $resolver;
}

/** A blueprint double: whether the field is present, and asserting the prepend. */
function bannerBlueprint(bool $hasField, bool $expectPrepend, ?string $namespace = null): Blueprint
{
    $blueprint = Mockery::mock(Blueprint::class);
    $blueprint->shouldReceive('hasField')->with('orphan_banner')->andReturn($hasField);

    if ($namespace !== null) {
        $blueprint->shouldReceive('namespace')->andReturn($namespace);
    }

    if ($expectPrepend) {
        // Assert the exact injected field config so a dropped key or a flipped
        // localizable/listable flag is caught.
        $blueprint->shouldReceive('ensureFieldPrepended')->once()->with(
            'orphan_banner',
            Mockery::on(fn ($config): bool => $config === [
                'type' => 'orphan_banner',
                'display' => 'Orphaned Redirect Status',
                'localizable' => false,
                'listable' => false,
            ]),
        );
    } else {
        $blueprint->shouldNotReceive('ensureFieldPrepended');
    }

    return $blueprint;
}

it('prepends the orphan_banner field to a handled collection blueprint (entry context)', function () {
    $collection = Mockery::mock(Collection::class);
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('collection')->andReturn($collection);

    $blueprint = bannerBlueprint(hasField: false, expectPrepend: true);

    (new AppendOrphanBanner(bannerResolver(handles: true)))
        ->handle(new EntryBlueprintFound($blueprint, $entry));
});

it('does nothing when the banner field is already present', function () {
    $blueprint = bannerBlueprint(hasField: true, expectPrepend: false);

    (new AppendOrphanBanner(bannerResolver(handles: true, expectCall: false)))
        ->handle(new EntryBlueprintFound($blueprint, Mockery::mock(Entry::class)));
});

it('does nothing for an unhandled collection', function () {
    $collection = Mockery::mock(Collection::class);
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('collection')->andReturn($collection);

    $blueprint = bannerBlueprint(hasField: false, expectPrepend: false);

    (new AppendOrphanBanner(bannerResolver(handles: false)))
        ->handle(new EntryBlueprintFound($blueprint, $entry));
});

it('resolves the collection from the blueprint namespace when no entry is present', function () {
    $collection = Mockery::mock(Collection::class);
    CollectionFacade::shouldReceive('findByHandle')->with('pages')->andReturn($collection);

    $blueprint = bannerBlueprint(hasField: false, expectPrepend: true, namespace: 'collections.pages');

    (new AppendOrphanBanner(bannerResolver(handles: true)))
        ->handle(new EntryBlueprintFound($blueprint));
});

it('does nothing when the blueprint namespace is not a collection', function () {
    $blueprint = bannerBlueprint(hasField: false, expectPrepend: false, namespace: 'globals.settings');

    (new AppendOrphanBanner(bannerResolver(handles: true, expectCall: false)))
        ->handle(new EntryBlueprintFound($blueprint));
});

it('does nothing and never dereferences a non-blueprint event', function () {
    // The blueprint slot is untyped on the event; a non-Blueprint value must be
    // a clean no-op, not a null dereference.
    $threw = null;

    try {
        (new AppendOrphanBanner(bannerResolver(handles: true, expectCall: false)))
            ->handle(new EntryBlueprintFound(null, Mockery::mock(Entry::class)));
    } catch (Throwable $e) {
        $threw = $e->getMessage();
    }

    expect($threw)->toBeNull();
});
