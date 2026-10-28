<?php

declare(strict_types=1);

use App\Services\ComputedFields\Registration\AugmentedAssetComputedPipelinesRegistrar;
use App\Support\Statamic\Assets\GenericAugmentedAssetWithCredit;
use App\Support\Statamic\Assets\Logos\AugmentedAsset as LogosAugmentedAsset;
use App\Support\Statamic\Assets\LogosUnrestricted\AugmentedAsset as LogosUnrestrictedAugmentedAsset;
use App\Support\Statamic\Assets\NewsReleases\AugmentedAsset as NewsReleasesAugmentedAsset;
use Illuminate\Support\Collection;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Assets\Asset as StatamicAsset;
use Statamic\Contracts\Assets\Asset as AssetContract;
use Statamic\Contracts\Assets\AssetContainer as AssetContainerContract;
use Statamic\Contracts\Data\Augmented as AugmentedContract;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * The `augmented` hooks currently registered on the base Statamic Asset class.
 *
 * @return list<Closure>
 */
function assetPipelineAugmentedHooks(): array
{
    $hooks = mustBe(Collection::class, app('statamic.hooks'), 'the statamic.hooks registry');
    $classHooks = mustBe(Collection::class, $hooks->get(StatamicAsset::class, collect()), 'the Asset hooks');

    $augmented = mustBe(Collection::class, $classHooks->get('augmented', collect()), 'the Asset augmented hooks');

    return array_values(array_map(
        fn (mixed $hook): Closure => mustBe(Closure::class, $hook, 'an augmented hook'),
        $augmented->all()
    ));
}

/**
 * Run one hook as Statamic does: bound to an asset in the given container.
 */
function runAssetPipelineHook(Closure $hook, string $containerHandle, mixed $augmented): mixed
{
    $container = Mockery::mock(AssetContainerContract::class);
    $container->shouldReceive('handle')->andReturn($containerHandle);

    $asset = Mockery::mock(AssetContract::class);
    $asset->shouldReceive('container')->andReturn($container);

    $bound = mustBe(Closure::class, $hook->bindTo($asset, $asset), 'the bound hook');

    return $bound($augmented, static fn (mixed $passable): mixed => $passable);
}

/**
 * @covers \App\Services\ComputedFields\Registration\AugmentedAssetComputedPipelinesRegistrar
 *
 * @command php artisan test --compact tests/Feature/Services/ComputedFields/Registration/AugmentedAssetComputedPipelinesRegistrarTest.php
 */
it('registers the three container pipelines in order, then the credit-text fallback', function (): void {
    $before = count(assetPipelineAugmentedHooks());

    (new AugmentedAssetComputedPipelinesRegistrar)->register();

    $added = array_slice(assetPipelineAugmentedHooks(), $before);

    expect($added)->toHaveCount(4);

    // Each container hook swaps in its own wrapper for its own container only.
    $containers = [
        ['logos_unrestricted', LogosUnrestrictedAugmentedAsset::class],
        ['news_releases', NewsReleasesAugmentedAsset::class],
        ['logos', LogosAugmentedAsset::class],
    ];

    foreach ($containers as $index => [$handle, $class]) {
        $original = Mockery::mock(AugmentedContract::class);

        expect(runAssetPipelineHook($added[$index], $handle, $original))->toBeInstanceOf($class)
            ->and(runAssetPipelineHook($added[$index], 'portraits', $original))->toBe($original);
    }

    // The last hook is the fallback: any container, any non-credit payload.
    expect(runAssetPipelineHook($added[3], 'portraits', Mockery::mock(AugmentedContract::class)))
        ->toBeInstanceOf(GenericAugmentedAssetWithCredit::class);
});
