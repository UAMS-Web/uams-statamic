<?php

declare(strict_types=1);

namespace App\Support\Statamic\Assets;

use App\Support\Statamic\Assets\Concerns\ComputesAssetCreditText;
use App\Support\Statamic\Assets\Concerns\ComputesAssetIptcDigitalSourceValues;
use App\Support\Statamic\Assets\Contracts\ProvidesComputedCreditText;
use Statamic\Assets\AugmentedAsset as StatamicAugmentedAsset;

/**
 * Default augmented wrapper for assets that need shared computed fields.
 *
 * Registered last via `AugmentedAssetHook::creditTextFallbackPipelineHook` when
 * no other `ProvidesComputedCreditText` implementation is already in use.
 */
class GenericAugmentedAssetWithCredit extends StatamicAugmentedAsset implements ProvidesComputedCreditText
{
    /**
     * Computes `credit_text` from blueprint fields.
     */
    use ComputesAssetCreditText;

    /**
     * Computes IPTC Digital Source value/schema fields from blueprint groups.
     */
    use ComputesAssetIptcDigitalSourceValues;
}
