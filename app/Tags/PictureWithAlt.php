<?php

declare(strict_types=1);

namespace App\Tags;

use Closure;
use Statamic\Assets\Asset;
use Studio1902\PeakTools\Tags\Picture;

/**
 * `{{ picture_with_alt }}`: the peak-tools `picture` tag with an `alt` parameter
 * that overrides the asset's own alt text (#340).
 *
 * The vendor tag reads `alt` only from the asset, so a per-placement override
 * (the Image set's `alt_override`) never reaches the rendered `<img>`. A
 * non-empty `alt` parameter is applied to a copy of the asset for the length of
 * the `<img>` build; without one, the asset's alt renders exactly as before.
 * The original asset is never modified.
 */
class PictureWithAlt extends Picture
{
    /**
     * The tag handle used in Antlers templates.
     *
     * @var string
     */
    protected static $handle = 'picture_with_alt';

    /**
     * Build the responsive `<img>` with the alt override applied.
     *
     * @param  Asset  $asset  The resolved image asset.
     * @return string The `<img>` markup.
     */
    #[\Override]
    protected function buildImgTag(Asset $asset): string
    {
        return $this->withAltOverride($asset, fn (Asset $image): string => parent::buildImgTag($image));
    }

    /**
     * Build the SVG / GIF passthrough `<img>` with the alt override applied.
     *
     * @param  Asset  $asset  The resolved image asset.
     * @return string The `<picture>` markup.
     */
    #[\Override]
    protected function buildSimpleImgTag(Asset $asset): string
    {
        return $this->withAltOverride($asset, fn (Asset $image): string => parent::buildSimpleImgTag($image));
    }

    /**
     * Run an `<img>` builder against a copy of the asset carrying the override.
     *
     * @param  Asset  $asset  The resolved image asset.
     * @param  Closure(Asset): string  $build  The parent `<img>` builder.
     * @return string The builder's markup.
     */
    private function withAltOverride(Asset $asset, Closure $build): string
    {
        // Read the override, ignoring a missing or blank value
        $alt = $this->params->get('alt');

        // Render from the asset's own alt when no override is given
        if (! is_string($alt) || trim($alt) === '') {
            return $build($asset);
        }

        // Apply the override to a copy so the shared asset is left unchanged
        $copy = clone $asset;
        $copy->set('alt', $alt);

        return $build($copy);
    }
}
