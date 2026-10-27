<?php

declare(strict_types=1);

namespace App\Support\Statamic\Assets\NewsReleases;

use App\Support\Statamic\Assets\Concerns\ComputesAssetCreditText;
use App\Support\Statamic\Assets\Concerns\ComputesAssetIptcDigitalSourceValues;
use App\Support\Statamic\Assets\Concerns\ComputesNewsReleaseUsage;
use App\Support\Statamic\Assets\Contracts\ProvidesComputedCreditText;
use Statamic\Assets\AugmentedAsset as StatamicAugmentedAsset;

/**
 * Augmented asset for the `news_releases` container with computed `in_use` and
 * `used_by` (UAMS-Web/uams-statamic-archive#1376).
 *
 * Surfaces the editorial "is this PDF still attached to a live post, and which one"
 * signal from the system-managed `news_release_entry` link. Registered through
 * AugmentedAssetHook in ComputedPropertiesProvider.
 *
 * Implements {@see ProvidesComputedCreditText} and mixes in the shared credit-text
 * and IPTC computeds exactly like the logos augmented assets — NOT decoration: the
 * `creditTextFallbackPipelineHook` runs LAST in the registered pipeline and REPLACES
 * any augmented instance that is not credit-aware with `GenericAugmentedAssetWithCredit`.
 * Without the marker, this container-specific class would be clobbered and `in_use` /
 * `used_by` would silently never fire; the marker makes the fallback pass it through,
 * and mixing in the same credit/IPTC traits keeps the parity every other container gets.
 */
class AugmentedAsset extends StatamicAugmentedAsset implements ProvidesComputedCreditText
{
    /**
     * Computes `credit_text` from blueprint fields (parity with the fallback).
     */
    use ComputesAssetCreditText;

    /**
     * Computes IPTC Digital Source value/schema fields (parity with the fallback).
     */
    use ComputesAssetIptcDigitalSourceValues;

    /**
     * Computes `in_use` / `used_by` from the `news_release_entry` link.
     */
    use ComputesNewsReleaseUsage;
}
