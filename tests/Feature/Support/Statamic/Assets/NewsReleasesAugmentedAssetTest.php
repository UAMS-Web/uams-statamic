<?php

declare(strict_types=1);

use App\Support\Statamic\Assets\AugmentedAssetHook;
use App\Support\Statamic\Assets\Concerns\ComputesNewsReleaseUsage;
use App\Support\Statamic\Assets\Contracts\ProvidesComputedCreditText;
use App\Support\Statamic\Assets\GenericAugmentedAssetWithCredit;
use App\Support\Statamic\Assets\NewsReleases\AugmentedAsset;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Contracts\Assets\Asset as AssetContract;
use Statamic\Contracts\Assets\AssetContainer as AssetContainerContract;
use Statamic\Contracts\Data\Augmented as AugmentedContract;
use Statamic\Entries\Entry as ConcreteEntry;
use Statamic\Facades\Entry;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

covers(ComputesNewsReleaseUsage::class);

/*
|--------------------------------------------------------------------------
| NewsReleases augmented asset (UAMS-Web/uams-statamic-archive#1376)
|--------------------------------------------------------------------------
|
| Proves the augmentation wiring: the pipeline hook swaps in the news_releases
| AugmentedAsset for that container, and Statamic dispatches `in_use` / `used_by`
| to the camelCase `inUse` / `usedBy` methods (a mis-cased method would silently
| resolve to null). The resolution logic itself is covered in
| NewsReleaseUsageResolverTest.
|
| @covers \App\Support\Statamic\Assets\NewsReleases\AugmentedAsset
| @covers \App\Support\Statamic\Assets\Concerns\ComputesNewsReleaseUsage
|
| @command php artisan test --compact tests/Feature/Support/Statamic/Assets/NewsReleasesAugmentedAssetTest.php
*/

/** A mock asset whose `news_release_entry` field returns $entryValue. */
function newsReleaseAsset(mixed $entryValue): AssetContract
{
    $asset = Mockery::mock(AssetContract::class);
    // getFromData() reads the field via value()/get(); support either path.
    $asset->shouldReceive('value')->with('news_release_entry')->andReturn($entryValue);
    $asset->shouldReceive('get')->with('news_release_entry')->andReturn($entryValue);
    $asset->shouldReceive('hasSupplement')->andReturn(false);

    return $asset;
}

it('registers the news_releases container to the news-releases augmented asset', function (): void {
    $container = Mockery::mock(AssetContainerContract::class);
    $container->shouldReceive('handle')->andReturn('news_releases');

    $asset = Mockery::mock(AssetContract::class);
    $asset->shouldReceive('container')->andReturn($container);

    $hook = AugmentedAssetHook::pipelineHook('news_releases', AugmentedAsset::class);
    $bound = $hook->bindTo($asset, $asset);

    $result = $bound(Mockery::mock(AugmentedContract::class), fn (mixed $passable): mixed => $passable);

    expect($result)->toBeInstanceOf(AugmentedAsset::class);
});

it('survives the credit-text fallback hook that runs later in the registered pipeline', function (): void {
    // The registrar registers the news_releases hook BEFORE the credit-text fallback,
    // and the fallback REPLACES any augmented instance that is not credit-aware. If the
    // news-releases class did not implement ProvidesComputedCreditText, the fallback
    // would clobber it with GenericAugmentedAssetWithCredit and in_use / used_by would
    // silently never fire. This runs the two hooks in registration order (as Statamic's
    // Pipeline does) and asserts the container-specific class survives.
    $container = Mockery::mock(AssetContainerContract::class);
    $container->shouldReceive('handle')->andReturn('news_releases');

    $asset = Mockery::mock(AssetContract::class);
    $asset->shouldReceive('container')->andReturn($container);

    $newsHook = AugmentedAssetHook::pipelineHook('news_releases', AugmentedAsset::class)->bindTo($asset, $asset);
    $fallbackHook = AugmentedAssetHook::creditTextFallbackPipelineHook(GenericAugmentedAssetWithCredit::class)->bindTo($asset, $asset);

    // Pipeline order: news_releases first, then the fallback (its $next is the identity).
    $result = $newsHook(
        Mockery::mock(AugmentedContract::class),
        fn (mixed $passable): mixed => $fallbackHook($passable, fn (mixed $x): mixed => $x)
    );

    expect($result)->toBeInstanceOf(AugmentedAsset::class)
        ->and($result)->toBeInstanceOf(ProvidesComputedCreditText::class)
        ->and($result)->not->toBeInstanceOf(GenericAugmentedAssetWithCredit::class);
});

it('dispatches in_use and used_by to the resolver for a published post', function (): void {
    $post = Mockery::mock(ConcreteEntry::class);
    $post->shouldReceive('published')->andReturn(true);
    Entry::shouldReceive('find')->with('post-1')->andReturn($post);

    $augmented = new AugmentedAsset(newsReleaseAsset('post-1'));

    expect($augmented->get('in_use')->value())->toBeTrue()
        ->and($augmented->get('used_by')->value())->toBe($post);
});

it('dispatches in_use false while still surfacing the post via used_by when unpublished', function (): void {
    $post = Mockery::mock(ConcreteEntry::class);
    $post->shouldReceive('published')->andReturn(false);
    Entry::shouldReceive('find')->with('post-2')->andReturn($post);

    $augmented = new AugmentedAsset(newsReleaseAsset('post-2'));

    expect($augmented->get('in_use')->value())->toBeFalse()
        ->and($augmented->get('used_by')->value())->toBe($post);
});

it('dispatches in_use false and used_by null when the link is unset', function (): void {
    Entry::shouldReceive('find')->never();

    $augmented = new AugmentedAsset(newsReleaseAsset(null));

    expect($augmented->get('in_use')->value())->toBeFalse()
        ->and($augmented->get('used_by')->value())->toBeNull();
});
