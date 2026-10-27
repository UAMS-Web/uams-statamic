<?php

declare(strict_types=1);

namespace App\Support\Statamic\Assets;

use App\Statamic\Eloquent\Assets\Asset as AppEloquentStatamicAsset;
use App\Support\Statamic\Assets\Contracts\ProvidesComputedCreditText;
use Closure;
use Statamic\Assets\Asset as StatamicAsset;
use Statamic\Assets\AugmentedAsset as StatamicAugmentedAsset;
use Statamic\Contracts\Assets\Asset as AssetContract;
use Statamic\Eloquent\Assets\Asset as EloquentStatamicAsset;

/**
 * Helpers for Statamic asset `augmented` pipeline hooks by container handle.
 *
 * Statamic binds hook closures with `bindTo($this, $this)` on the asset, so
 * instantiations inside the closure must use an explicit `$augmentedClass`
 * string (not `self`).
 */
final class AugmentedAssetHook
{
    /**
     * Build a closure for Statamic's `augmented` hook: when the asset's
     * container handle matches, replace the default augmented instance with
     * `$augmentedClass` (constructor receives the asset).
     *
     * @param  string  $containerHandle  Asset container handle to match
     * @param  class-string<StatamicAugmentedAsset>  $augmentedClass  Concrete subclass of Statamic's AugmentedAsset
     * @return Closure Bound hook; Statamic binds it to the asset. Passes `$augmented` through when
     *                 the container handle differs, otherwise replaces it with `$augmentedClass` for
     *                 this asset
     */
    public static function pipelineHook(
        string $containerHandle,
        string $augmentedClass
    ): Closure {
        // Build the Statamic `augmented` pipeline hook for a single container
        return function (
            mixed $augmented,
            Closure $next
        ) use ($containerHandle, $augmentedClass): mixed {
            // Resolve `$this` as the asset. Statamic binds this hook closure to the asset.
            /** @var AssetContract $asset */
            // @phpstan-ignore variable.undefined (Statamic binds this hook closure to the asset via bindTo, so $this is the asset at runtime; a closure declared in a static method has no $this statically)
            $asset = $this;

            // Check whether the asset belongs to the hook's target container
            if ($asset->container()->handle() !== $containerHandle) {
                // Continue the pipeline with the unchanged augmented instance
                return $next($augmented);
            }

            // ---
            // Replace the pipeline payload with a container-specific augmented
            // instance. Call `makeAugmentedAsset` on this class so the augmented
            // class name resolves in `AugmentedAssetHook` scope; inside a bound
            // closure, `self` would refer to the asset class, not this helper
            // class. `makeAugmentedAsset` stays public because the bound closure
            // cannot call private methods on `AugmentedAssetHook`.
            // ---
            $augmented = AugmentedAssetHook::makeAugmentedAsset($augmentedClass, $asset);

            // Continue the pipeline with the container-specific augmented instance
            return $next($augmented);
        };
    }

    /**
     * Pipeline hook that swaps Statamic's default augmented instance for one that
     * exposes computed `credit_text`, without replacing classes that already
     * implement `ProvidesComputedCreditText` (e.g., logos containers).
     *
     * @param  class-string<StatamicAugmentedAsset&ProvidesComputedCreditText>  $genericAugmentedClass  Class to instantiate when no credit-aware instance is found
     * @return Closure Bound hook; Statamic binds it to the asset. Returns `$augmented` when it
     *                 already implements `ProvidesComputedCreditText`, otherwise replaces it with
     *                 `$genericAugmentedClass` for this asset
     */
    public static function creditTextFallbackPipelineHook(
        string $genericAugmentedClass
    ): Closure {
        // Build the fallback `augmented` hook that adds computed `credit_text`
        return function (
            mixed $augmented,
            Closure $next
        ) use ($genericAugmentedClass): mixed {
            // Resolve `$this` as the asset. Statamic binds this hook closure to the asset.
            /** @var AssetContract $asset */
            // @phpstan-ignore variable.undefined (Statamic binds this hook closure to the asset via bindTo, so $this is the asset at runtime; a closure declared in a static method has no $this statically)
            $asset = $this;

            // Check whether an earlier pipeline step already wrapped with credit support
            if ($augmented instanceof ProvidesComputedCreditText) {
                // Continue the pipeline with the existing credit-aware instance
                return $next($augmented);
            }

            // Substitute the default augmented wrapper for the generic credit implementation
            $augmented = AugmentedAssetHook::makeAugmentedAsset($genericAugmentedClass, $asset);

            // Continue the pipeline with the fallback credit-aware instance
            return $next($augmented);
        };
    }

    /**
     * Instantiate `$augmentedClass` with `$asset` so static analysis sees a
     * real constructor (the `Augmented` contract does not declare one).
     *
     * @internal  Must be public: hook closures are bound with the asset's class
     * scope, so private methods on this class are not callable from inside the
     * hook.
     *
     * @param  class-string<StatamicAugmentedAsset>  $augmentedClass  Augmented implementation to instantiate
     * @param  AssetContract  $asset  Statamic asset passed to the augmented constructor
     * @return StatamicAugmentedAsset New augmented wrapper for `$asset`
     */
    public static function makeAugmentedAsset(
        string $augmentedClass,
        AssetContract $asset
    ): StatamicAugmentedAsset {
        // Instantiate the augmented wrapper so static analysis sees a real constructor
        return new $augmentedClass($asset);
    }

    /**
     * Register the same augmented hook on each `Asset` class the app uses
     * (Statamic keys hooks by `static::class`, so each concrete class must
     * register).
     *
     * @param  Closure  $hook  Pipeline hook closure, e.g., from `pipelineHook()`,
     *                         `creditTextFallbackPipelineHook()`, or any compatible `augmented` hook
     */
    public static function registerPipelineHook(
        Closure $hook
    ): void {
        // Register the hook on the base Statamic Asset class
        StatamicAsset::hook('augmented', $hook);

        // Register the hook on the Eloquent Statamic Asset class
        EloquentStatamicAsset::hook('augmented', $hook);

        // Register the hook on the App Eloquent Statamic Asset class
        AppEloquentStatamicAsset::hook('augmented', $hook);
    }
}
