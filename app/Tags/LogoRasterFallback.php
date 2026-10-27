<?php

declare(strict_types=1);

namespace App\Tags;

use App\Support\Imaging\BrandLogoRasterizer;
use Statamic\Contracts\Assets\Asset;
use Statamic\Fields\Value;
use Statamic\Tags\Parameters;
use Statamic\Tags\Tags;

/**
 * `{{ logo_raster_fallback :asset="..." height="50" }}`
 *
 * Returns a raster `<img>` fallback URL for a brand logo asset (UAMS-Web/uams-statamic-archive#50): an SVG is
 * rasterized to a cached PNG, an already-raster asset returns its own URL, and a
 * missing asset returns an empty string. Delegates the resolution + caching to
 * `App\Support\Imaging\BrandLogoRasterizer`.
 */
class LogoRasterFallback extends Tags
{
    /**
     * Resolve the fallback URL for the asset passed via `:asset`.
     */
    public function index(): string
    {
        // Narrow the untyped Tags::$params to its concrete type (the codebase
        // convention for this legacy property — see App\Tags\Dateline).
        /** @var Parameters $params */
        $params = $this->params;

        $asset = $this->resolveAsset($params->get('asset'));

        // Default to 50px; only a numeric param overrides it.
        $heightParam = $params->get('height', 50);
        $height = is_numeric($heightParam) ? (int) $heightParam : 50;

        return (string) app(BrandLogoRasterizer::class)->fallbackUrl($asset, $height);
    }

    /**
     * Normalize the `:asset` param to a single Asset.
     *
     * A `max_files: 1` assets field augments to a single Asset, but the value
     * may arrive wrapped in a `Value` or as an asset collection, so unwrap those
     * defensively.
     */
    private function resolveAsset(mixed $value): ?Asset
    {
        if ($value instanceof Value) {
            $value = $value->value();
        }

        if ($value instanceof Asset) {
            return $value;
        }

        if (is_iterable($value)) {
            foreach ($value as $item) {
                if ($item instanceof Asset) {
                    return $item;
                }
            }
        }

        return null;
    }
}
