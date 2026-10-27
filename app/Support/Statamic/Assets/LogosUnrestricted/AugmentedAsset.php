<?php

declare(strict_types=1);

namespace App\Support\Statamic\Assets\LogosUnrestricted;

use App\Support\Statamic\Assets\Concerns\ComputesAssetCreditText;
use App\Support\Statamic\Assets\Concerns\ComputesAssetIptcDigitalSourceValues;
use App\Support\Statamic\Assets\Contracts\ProvidesComputedCreditText;
use Statamic\Assets\AugmentedAsset as StatamicAugmentedAsset;

/**
 * Augmented asset for the `logos_unrestricted` container.
 *
 * Registered via App\Support\Statamic\Assets\AugmentedAssetHook in
 * App\Providers\ComputedPropertiesProvider so the Control Panel and templates
 * resolve computed `alt`. Formatting rules live in
 * App\Support\Statamic\Assets\LogosUnrestricted\AltFormatter.
 */
class AugmentedAsset extends StatamicAugmentedAsset implements ProvidesComputedCreditText
{
    /**
     * Computes `credit_text` from blueprint fields.
     */
    use ComputesAssetCreditText;

    /**
     * Computes IPTC Digital Source value/schema fields from blueprint groups.
     */
    use ComputesAssetIptcDigitalSourceValues;

    /**
     * Computed `alt` from blueprint `title` via
     * App\Support\Statamic\Assets\LogosUnrestricted\AltFormatter.
     *
     * @return string|null Formatted alt, or null when title is unusable
     */
    protected function alt(): ?string
    {
        return AltFormatter::fromTitle($this->getFromData('title'));
    }
}
