<?php

declare(strict_types=1);

use App\Support\Imaging\BrandLogoRasterizer;
use App\Tags\LogoRasterFallback;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Contracts\Assets\Asset;
use Statamic\Facades\Antlers;
use Statamic\Fields\Value;
use Statamic\Tags\Parameters;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Wiring coverage for the `{{ logo_raster_fallback }}` tag (UAMS-Web/uams-statamic-archive#50): it auto-
 * registers, resolves the `:asset` param to a single Asset, parses `height`,
 * and delegates to `App\Support\Imaging\BrandLogoRasterizer`. The branch/cache
 * behavior lives in BrandLogoRasterizerTest; this proves the tag plumbing.
 *
 * @covers \App\Tags\LogoRasterFallback
 *
 * @command  php artisan test --compact tests/Feature/Tags/LogoRasterFallbackTest.php
 */
it('resolves the fallback url for the asset param', function (): void {
    $asset = Mockery::mock(Asset::class);

    $service = Mockery::mock(BrandLogoRasterizer::class);
    $service->shouldReceive('fallbackUrl')
        ->once()
        ->with($asset, 50)
        ->andReturn('/img/brand-raster/abc123.png');
    app()->instance(BrandLogoRasterizer::class, $service);

    $out = (string) Antlers::parse(
        '{{ logo_raster_fallback :asset="logo" height="50" }}',
        ['logo' => $asset]
    );

    expect(trim($out))->toBe('/img/brand-raster/abc123.png');
});

it('renders empty when the asset param is missing', function (): void {
    $service = Mockery::mock(BrandLogoRasterizer::class);
    // Null asset → the service returns null → the tag emits an empty string.
    $service->shouldReceive('fallbackUrl')->once()->with(null, 50)->andReturnNull();
    app()->instance(BrandLogoRasterizer::class, $service);

    $out = (string) Antlers::parse('{{ logo_raster_fallback height="50" }}');

    expect(trim($out))->toBe('');
});

/**
 * Run the tag directly with the given parameters, against a rasterizer that
 * expects exactly one call with this asset and height.
 *
 * @param  array<string, mixed>  $parameters
 */
function logoRasterFallbackFor(array $parameters, ?Asset $expectedAsset, int $expectedHeight): string
{
    $service = Mockery::mock(BrandLogoRasterizer::class);
    $service->shouldReceive('fallbackUrl')->once()->with($expectedAsset, $expectedHeight)->andReturn('/raster.png');
    app()->instance(BrandLogoRasterizer::class, $service);

    $tag = new LogoRasterFallback;
    $tag->setContext([]);
    $tag->setParameters($parameters);

    return $tag->index();
}

it('unwraps an asset passed inside a Value', function (): void {
    // Parameters::make(), the route setParameters() takes, already unwraps a Value, so
    // the tag's own unwrap is reached only by params built without it, as here.
    $asset = Mockery::mock(Asset::class);
    $service = Mockery::mock(BrandLogoRasterizer::class);
    $service->shouldReceive('fallbackUrl')->once()->with($asset, 50)->andReturn('/raster.png');
    app()->instance(BrandLogoRasterizer::class, $service);

    $tag = new LogoRasterFallback;
    $tag->setContext([]);
    $tag->params = new Parameters(['asset' => new Value($asset)]);

    expect($tag->index())->toBe('/raster.png');
});

it('takes the first asset from a collection, skipping anything that is not one', function (): void {
    $first = Mockery::mock(Asset::class);
    $second = Mockery::mock(Asset::class);

    expect(logoRasterFallbackFor(['asset' => collect(['not an asset', $first, $second])], $first, 50))->toBe('/raster.png');
});

it('passes no asset for a collection that holds none, or for a value that is not one', function (mixed $value): void {
    expect(logoRasterFallbackFor(['asset' => $value], null, 50))->toBe('/raster.png');
})->with([
    'collection without an asset' => [fn () => collect(['a', 'b'])],
    'string' => ['logo.png'],
]);

it('uses a numeric height and falls back to 50 otherwise', function (mixed $height, int $expected): void {
    $asset = Mockery::mock(Asset::class);

    expect(logoRasterFallbackFor(['asset' => $asset, 'height' => $height], $asset, $expected))->toBe('/raster.png');
})->with([
    'numeric string' => ['80', 80],
    'integer' => [120, 120],
    'not numeric' => ['tall', 50],
]);

it('defaults the height to 50 when none is given', function (): void {
    $asset = Mockery::mock(Asset::class);

    expect(logoRasterFallbackFor(['asset' => $asset], $asset, 50))->toBe('/raster.png');
});

it('renders an empty string when the rasterizer returns nothing', function (): void {
    $service = Mockery::mock(BrandLogoRasterizer::class);
    $service->shouldReceive('fallbackUrl')->once()->andReturnNull();
    app()->instance(BrandLogoRasterizer::class, $service);

    $tag = new LogoRasterFallback;
    $tag->setContext([]);
    $tag->setParameters([]);

    expect($tag->index())->toBe('');
});
