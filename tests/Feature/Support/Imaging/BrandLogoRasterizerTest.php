<?php

declare(strict_types=1);

use App\Support\Imaging\BrandLogoRasterizer;
use App\Support\Imaging\SvgRasterizer;
use Illuminate\Support\Facades\Storage;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Contracts\Assets\Asset;
use Tests\TestCase;

// Runs in the parallel band: every case fakes the `public` disk, which under
// `--parallel` is rooted per worker at `public_test_{TEST_TOKEN}` (UAMS-Web/uams-statamic-archive#3111), so a
// concurrent worker cannot wipe this one's cached PNG (UAMS-Web/uams-statamic-archive#3205). See tests/README.md.
uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Covers the branch/cache logic of the brand raster fallback (UAMS-Web/uams-statamic-archive#50) with a
 * stubbed rasterizer, so these run without the Imagick extension and stay
 * deterministic. The rasterization itself is covered in SvgRasterizerTest.
 *
 * @command  php artisan test --compact tests/Feature/Support/Imaging/BrandLogoRasterizerTest.php
 */

/**
 * @param  string  $extension  The asset's file extension
 */
function rasterizerFakeAsset(string $extension, string $url, string $contents = ''): Asset
{
    $asset = Mockery::mock(Asset::class);
    $asset->shouldReceive('extension')->andReturn($extension);
    $asset->shouldReceive('url')->andReturn($url);
    $asset->shouldReceive('contents')->andReturn($contents);

    return $asset;
}

it('returns null when no asset is set', function (): void {
    $rasterizer = Mockery::mock(SvgRasterizer::class);
    $rasterizer->shouldNotReceive('toPng');

    $service = new BrandLogoRasterizer($rasterizer, Storage::fake('public'));

    expect($service->fallbackUrl(null))->toBeNull();
});

it('returns the asset url unchanged for an already-raster logo', function (): void {
    $rasterizer = Mockery::mock(SvgRasterizer::class);
    $rasterizer->shouldNotReceive('toPng');

    $service = new BrandLogoRasterizer($rasterizer, Storage::fake('public'));
    $asset = rasterizerFakeAsset('png', '/assets/logos/logo.png');

    expect($service->fallbackUrl($asset, 50))->toBe('/assets/logos/logo.png');
});

it('rasterizes an svg logo to a cached png and returns its url', function (): void {
    $disk = Storage::fake('public');
    $rasterizer = Mockery::mock(SvgRasterizer::class);
    $rasterizer->shouldReceive('toPng')->once()->andReturn('PNG-BYTES');

    $service = new BrandLogoRasterizer($rasterizer, $disk);
    $asset = rasterizerFakeAsset('svg', '/assets/logos/logo.svg', '<svg/>');

    $url = $service->fallbackUrl($asset, 50);

    expect($url)->toContain('img/brand-raster/')->toEndWith('.png');

    // The generated PNG is persisted on the cache disk.
    $relative = 'img/brand-raster/'.basename((string) parse_url((string) $url, PHP_URL_PATH));
    $disk->assertExists($relative);
    expect($disk->get($relative))->toBe('PNG-BYTES');
});

it('generates the raster once and reuses the cache on later calls', function (): void {
    $disk = Storage::fake('public');
    $rasterizer = Mockery::mock(SvgRasterizer::class);
    // `once()` is the assertion: a cache hit must not re-rasterize.
    $rasterizer->shouldReceive('toPng')->once()->andReturn('PNG-BYTES');

    $service = new BrandLogoRasterizer($rasterizer, $disk);
    $asset = rasterizerFakeAsset('svg', '/assets/logos/logo.svg', '<svg/>');

    $first = $service->fallbackUrl($asset, 50);
    $second = $service->fallbackUrl($asset, 50);

    expect($second)->toBe($first);
});

it('falls back to the svg url when rasterization fails', function (): void {
    $rasterizer = Mockery::mock(SvgRasterizer::class);
    $rasterizer->shouldReceive('toPng')->andThrow(new RuntimeException('no driver'));

    $service = new BrandLogoRasterizer($rasterizer, Storage::fake('public'));
    $asset = rasterizerFakeAsset('svg', '/assets/logos/logo.svg', '<svg/>');

    // Graceful degradation: the SVG URL is a safe <img> fallback.
    expect($service->fallbackUrl($asset, 50))->toBe('/assets/logos/logo.svg');
});

it('treats the extension case-insensitively', function (): void {
    $rasterizer = Mockery::mock(SvgRasterizer::class);
    $rasterizer->shouldReceive('toPng')->once()->andReturn('PNG-BYTES');

    $service = new BrandLogoRasterizer($rasterizer, Storage::fake('public'));
    $asset = rasterizerFakeAsset('SVG', '/assets/logos/logo.SVG', '<svg/>');

    expect($service->fallbackUrl($asset, 50))->toContain('img/brand-raster/');
});
