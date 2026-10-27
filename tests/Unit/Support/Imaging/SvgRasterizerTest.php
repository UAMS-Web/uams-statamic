<?php

declare(strict_types=1);

use App\Support\Imaging\SvgRasterizer;

covers(SvgRasterizer::class);

/**
 * Covers the load-bearing capability behind the brand raster fallback (UAMS-Web/uams-statamic-archive#50):
 * SVG markup is rasterized to a PNG of the requested height. Gated on the
 * Imagick extension — the only driver that decodes SVG (GD cannot), and the one
 * the app configures.
 *
 * @covers \App\Support\Imaging\SvgRasterizer
 *
 * @command  php artisan test --compact tests/Unit/Support/Imaging/SvgRasterizerTest.php
 */
function rasterizerSampleSvg(): string
{
    return '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="50" viewBox="0 0 200 50">'
        .'<rect width="200" height="50" fill="#1f4e79"/>'
        .'<text x="10" y="32" fill="#fff" font-size="24">UAMS</text></svg>';
}

it('rasterizes svg markup to a png at the requested height', function (): void {
    if (! extension_loaded('imagick')) {
        $this->markTestSkipped('Imagick is required to rasterize SVG.');
    }

    $png = SvgRasterizer::imagick()->toPng(rasterizerSampleSvg(), 50);

    // PNG magic number — proves a real PNG was produced, not the SVG passed through.
    expect(bin2hex(substr($png, 0, 8)))->toBe('89504e470d0a1a0a');

    $image = new Imagick;
    $image->readImageBlob($png);
    expect($image->getImageHeight())->toBe(50)
        // 200x50 SVG scaled to height 50 keeps its 4:1 aspect ratio.
        ->and($image->getImageWidth())->toBe(200)
        ->and($image->getImageFormat())->toBe('PNG');
});

it('scales to a different height while preserving aspect ratio', function (): void {
    if (! extension_loaded('imagick')) {
        $this->markTestSkipped('Imagick is required to rasterize SVG.');
    }

    $png = SvgRasterizer::imagick()->toPng(rasterizerSampleSvg(), 25);

    $image = new Imagick;
    $image->readImageBlob($png);
    expect($image->getImageHeight())->toBe(25)
        ->and($image->getImageWidth())->toBe(100);
});

it('clamps a non-positive height to one pixel', function (): void {
    if (! extension_loaded('imagick')) {
        $this->markTestSkipped('Imagick is required to rasterize SVG.');
    }

    $png = SvgRasterizer::imagick()->toPng(rasterizerSampleSvg(), 0);

    $image = new Imagick;
    $image->readImageBlob($png);
    expect($image->getImageHeight())->toBe(1);
});
