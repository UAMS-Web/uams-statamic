<?php

declare(strict_types=1);

use App\Statamic\Eloquent\Assets\Asset as AppEloquentStatamicAsset;
use App\Support\Statamic\Assets\AugmentedAssetHook;
use App\Support\Statamic\Assets\GenericAugmentedAssetWithCredit;
use App\Support\Statamic\Assets\LogosUnrestricted\AugmentedAsset as LogosUnrestrictedAugmentedAsset;
use Illuminate\Support\Collection;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Assets\Asset as StatamicAsset;
use Statamic\Contracts\Assets\Asset as AssetContract;
use Statamic\Contracts\Assets\AssetContainer as AssetContainerContract;
use Statamic\Contracts\Data\Augmented as AugmentedContract;
use Statamic\Eloquent\Assets\Asset as EloquentStatamicAsset;
use Tests\TestCase;

covers(AugmentedAssetHook::class);

uses(TestCase::class, MockeryPHPUnitIntegration::class);

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
 * Tests AugmentedAssetHook pipeline closures (Statamic binds them to
 * the asset, which affects `self` and method visibility).
 *
 * Mutation: 100% on App\Support\Statamic\Assets\AugmentedAssetHook (8 mutants, --covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Support/Statamic/Assets/AugmentedAssetHookTest.php
 */
it('returns the original augmented value when the container mismatches', function () {
    $container = Mockery::mock(AssetContainerContract::class);
    $container->shouldReceive('handle')->andReturn('portraits');

    $asset = Mockery::mock(AssetContract::class);
    $asset->shouldReceive('container')->andReturn($container);

    $hook = AugmentedAssetHook::pipelineHook(
        'logos_unrestricted',
        LogosUnrestrictedAugmentedAsset::class
    );

    $bound = $hook->bindTo($asset, $asset);

    $original = Mockery::mock(AugmentedContract::class);

    $nextWasCalled = false;
    $next = static function (mixed $passable) use (&$nextWasCalled): mixed {
        $nextWasCalled = true;

        return $passable;
    };

    expect($bound($original, $next))->toBe($original);
    expect($nextWasCalled)->toBeTrue();
});

it('returns the custom augmented value when the container matches', function () {
    $container = Mockery::mock(AssetContainerContract::class);
    $container->shouldReceive('handle')->andReturn('logos_unrestricted');

    $asset = Mockery::mock(AssetContract::class);
    $asset->shouldReceive('container')->andReturn($container);

    $hook = AugmentedAssetHook::pipelineHook(
        'logos_unrestricted',
        LogosUnrestrictedAugmentedAsset::class
    );

    $bound = $hook->bindTo($asset, $asset);

    $original = Mockery::mock(AugmentedContract::class);

    $nextWasCalled = false;
    $next = static function (mixed $passable) use (&$nextWasCalled): mixed {
        $nextWasCalled = true;

        return $passable;
    };

    $result = $bound($original, $next);

    expect($result)->toBeInstanceOf(LogosUnrestrictedAugmentedAsset::class);
    expect($nextWasCalled)->toBeTrue();
});

it('wraps a non-credit augmented payload with the fallback credit hook', function () {
    $container = Mockery::mock(AssetContainerContract::class);
    $container->shouldReceive('handle')->andReturn('audio');

    $asset = Mockery::mock(AssetContract::class);
    $asset->shouldReceive('container')->andReturn($container);

    $hook = AugmentedAssetHook::creditTextFallbackPipelineHook(
        GenericAugmentedAssetWithCredit::class
    );

    $bound = $hook->bindTo($asset, $asset);

    $original = Mockery::mock(AugmentedContract::class);

    $nextWasCalled = false;
    $next = static function (mixed $passable) use (&$nextWasCalled): mixed {
        $nextWasCalled = true;

        return $passable;
    };

    $result = $bound($original, $next);

    expect($result)->toBeInstanceOf(GenericAugmentedAssetWithCredit::class);
    expect($nextWasCalled)->toBeTrue();
});

it('leaves an already credit-aware augmented payload alone in the fallback hook', function () {
    // InstanceOfToFalse on ProvidesComputedCreditText would wrap again.
    $asset = Mockery::mock(AssetContract::class);
    $already = new LogosUnrestrictedAugmentedAsset($asset);

    $hook = AugmentedAssetHook::creditTextFallbackPipelineHook(
        GenericAugmentedAssetWithCredit::class
    );
    $bound = $hook->bindTo($asset, $asset);

    $seen = null;
    $next = static function (mixed $passable) use (&$seen): mixed {
        $seen = $passable;

        return $passable;
    };

    expect($bound($already, $next))->toBe($already)
        ->and($seen)->toBe($already);
});

it('registers the augmented hook on every Asset class the app uses', function () {
    // RemoveMethodCall on any of the three ::hook lines drops that concrete class.
    $marker = static function (mixed $augmented, Closure $next): mixed {
        return $next($augmented);
    };

    $countFor = static function (string $class): int {
        $hooks = mustBe(Collection::class, app('statamic.hooks'), 'the statamic.hooks registry');
        $classHooks = mustBe(Collection::class, $hooks->get($class, collect()), "the {$class} hooks");

        return mustBe(Collection::class, $classHooks->get('augmented', collect()), "the {$class} augmented hooks")->count();
    };

    $before = [
        StatamicAsset::class => $countFor(StatamicAsset::class),
        EloquentStatamicAsset::class => $countFor(EloquentStatamicAsset::class),
        AppEloquentStatamicAsset::class => $countFor(AppEloquentStatamicAsset::class),
    ];

    AugmentedAssetHook::registerPipelineHook($marker);

    expect($countFor(StatamicAsset::class))->toBe($before[StatamicAsset::class] + 1)
        ->and($countFor(EloquentStatamicAsset::class))->toBe($before[EloquentStatamicAsset::class] + 1)
        ->and($countFor(AppEloquentStatamicAsset::class))->toBe($before[AppEloquentStatamicAsset::class] + 1);
});
