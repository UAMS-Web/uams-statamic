<?php

declare(strict_types=1);

namespace App\Support\Imaging;

use Illuminate\Contracts\Filesystem\Filesystem;
use Statamic\Contracts\Assets\Asset;
use Throwable;

/**
 * Resolves the `<img>` raster fallback URL for a brand logo asset (UAMS-Web/uams-statamic-archive#50).
 *
 * The header brand `<picture>` serves SVG to capable browsers via `<source>`
 * elements; the `<img>` fallback needs a raster. Rather than require editors to
 * upload a second raster version of every logo, an SVG logo is rasterized once
 * to a cached PNG and that cached URL is returned. Assets that are already
 * raster, that have unreadable contents, and any rasterization failure degrade
 * to the asset's own URL.
 */
class BrandLogoRasterizer
{
    /**
     * Web-root-relative directory for the generated PNG cache.
     */
    private const string CACHE_DIRECTORY = 'img/brand-raster';

    public function __construct(
        private SvgRasterizer $rasterizer,
        private Filesystem $disk,
    ) {}

    /**
     * Resolve the raster fallback URL for a brand logo asset.
     *
     * @param  Asset|null  $asset  The logo asset (a `max_files: 1` field augments to a single Asset)
     * @param  int  $height  Target PNG height in pixels
     * @return string|null The fallback image URL, or null when no asset is set
     */
    public function fallbackUrl(?Asset $asset, int $height = 50): ?string
    {
        if (! $asset instanceof Asset) {
            return null;
        }

        $contents = $asset->contents();

        // Only an SVG with readable string contents can be rasterized. Anything
        // else — an already-raster asset, or unreadable contents — falls through
        // to the asset's own URL, the correct `<img>` fallback in that case.
        if (strtolower($asset->extension()) === 'svg' && is_string($contents)) {
            try {
                return $this->rasterUrl($contents, $height);
            } catch (Throwable) {
                // Rasterization unavailable (no SVG-capable driver, malformed
                // source, …) — fall through to the SVG URL, a safe fallback
                // since virtually all browsers render inline SVG.
            }
        }

        return $asset->url();
    }

    /**
     * Return the cached PNG URL for the SVG markup, generating it on a cache miss.
     *
     * @param  string  $contents  Raw SVG markup
     * @param  int  $height  Target PNG height in pixels
     */
    private function rasterUrl(string $contents, int $height): string
    {
        // Content-address the cache: an edited logo yields new markup and a new
        // hash (so it regenerates), and each requested height stays distinct.
        $path = self::CACHE_DIRECTORY.'/'.md5($contents.'|'.$height).'.png';

        // Generate once and persist; later requests reuse the cached PNG.
        if (! $this->disk->exists($path)) {
            $this->disk->put($path, $this->rasterizer->toPng($contents, $height));
        }

        // The cache disk is rooted at the web root, so the URL is the path itself.
        return '/'.$path;
    }
}
