<?php

declare(strict_types=1);

namespace App\Support\Imaging;

use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Encoders\PngEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageManagerInterface;

/**
 * Rasterizes SVG markup into PNG bytes via Intervention's Imagick pipeline.
 *
 * Statamic's `{{ glide }}` tag cannot be used for this: its pre-flight
 * `Statamic\Imaging\ImageValidator` rejects SVG because Intervention's driver
 * reports `supports('svg') === false`, even though the underlying Imagick build
 * decodes and rasterizes SVG correctly. The brand header raster fallback (UAMS-Web/uams-statamic-archive#50)
 * therefore generates the PNG here rather than through Glide.
 */
class SvgRasterizer
{
    public function __construct(private ImageManagerInterface $imageManager) {}

    /**
     * Build a rasterizer backed by the Imagick driver (the only driver that
     * rasterizes SVG; GD cannot, and the app forces Imagick via
     * `IMAGE_MANIPULATION_DRIVER`).
     */
    public static function imagick(): self
    {
        return new self(ImageManager::usingDriver(new ImagickDriver));
    }

    /**
     * Rasterize SVG markup to PNG bytes, scaled to the given height with the
     * aspect ratio preserved.
     *
     * @param  string  $svgContents  Raw SVG markup
     * @param  int  $height  Target height in pixels
     * @return string PNG bytes
     */
    public function toPng(string $svgContents, int $height): string
    {
        return (string) $this->imageManager
            ->decodeBinary($svgContents)
            ->scale(height: max(1, $height))
            ->encode(new PngEncoder);
    }
}
