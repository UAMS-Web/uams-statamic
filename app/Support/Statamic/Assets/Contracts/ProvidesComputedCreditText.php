<?php

declare(strict_types=1);

namespace App\Support\Statamic\Assets\Contracts;

/**
 * Marker for augmented asset classes that expose computed `credit_text`.
 *
 * Used by
 * App\Support\Statamic\Assets\AugmentedAssetHook::creditTextFallbackPipelineHook
 * so the fallback hook does not replace container-specific augmented instances
 * that already include credit resolution.
 */
interface ProvidesComputedCreditText {}
