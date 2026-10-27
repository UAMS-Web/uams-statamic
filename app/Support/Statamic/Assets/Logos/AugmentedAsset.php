<?php

declare(strict_types=1);

namespace App\Support\Statamic\Assets\Logos;

use App\Support\Statamic\Assets\Concerns\ComputesAssetCreditText;
use App\Support\Statamic\Assets\Concerns\ComputesAssetIptcDigitalSourceValues;
use App\Support\Statamic\Assets\Contracts\ProvidesComputedCreditText;
use Statamic\Assets\AugmentedAsset as StatamicAugmentedAsset;

/**
 * Augmented asset for the `logos` container with computed `title` and `alt`.
 *
 * Values derive from blueprint fields via TitleAltResolver; registered through
 * AugmentedAssetHook in ComputedPropertiesProvider.
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
     * Computed `title` from blueprint `title` via
     * App\Support\Statamic\Assets\Logos\TitleAltResolver.
     */
    protected function title(): ?string
    {
        return TitleAltResolver::title($this->logosBlueprintData());
    }

    /**
     * Computed `alt` from blueprint `title` via
     * App\Support\Statamic\Assets\Logos\TitleAltResolver.
     */
    protected function alt(): ?string
    {
        return TitleAltResolver::alt($this->logosBlueprintData());
    }

    /**
     * Collect raw blueprint values needed for title/alt resolution.
     *
     * @return array<string, mixed>
     */
    private function logosBlueprintData(): array
    {
        return [
            'uams_logo_query' => $this->getFromData('uams_logo_query'),
            'external_organization_name' => $this->getFromData('external_organization_name'),
            'base_logo' => $this->getFromData('base_logo'),
            'application_query' => $this->getFromData('application_query'),
            'application' => $this->getFromData('application'),
            'major_entity_query' => $this->getFromData('major_entity_query'),
            'major_entity' => $this->getFromData('major_entity'),
            'sub_entity_query' => $this->getFromData('sub_entity_query'),
            'sub_entity' => $this->getFromData('sub_entity'),
            'orientation' => $this->getFromData('orientation'),
            'color_scheme' => $this->getFromData('color_scheme'),
        ];
    }
}
