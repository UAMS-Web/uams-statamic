<?php

declare(strict_types=1);

use App\Support\Imaging\BrandLogoRasterizer;
use App\Tags\LogoRasterFallback;
use Illuminate\Container\Container;
use Statamic\Contracts\Assets\Asset;
use Statamic\Fields\Value;

covers(LogoRasterFallback::class);

beforeEach(function (): void {
    $this->previousContainer = Container::getInstance();
    $this->container = new Container;
    Container::setInstance($this->container);
});

afterEach(function (): void {
    Mockery::close();
    Container::setInstance($this->previousContainer);
});

/**
 * Build a LogoRasterFallback tag instance configured with the given parameters.
 *
 * @param  array<string, mixed>  $params
 */
function makeLogoRasterFallbackTag(array $params = []): LogoRasterFallback
{
    $tag = new LogoRasterFallback;
    $tag->setContext([]);
    $tag->setParameters($params);

    return $tag;
}

it('defaults height to 50 and casts a null rasterizer result to an empty string', function (): void {
    $rasterizer = Mockery::mock(BrandLogoRasterizer::class);
    $rasterizer->shouldReceive('fallbackUrl')
        ->once()
        ->with(null, 50)
        ->andReturn(null);
    $this->container->instance(BrandLogoRasterizer::class, $rasterizer);

    expect(makeLogoRasterFallbackTag()->index())->toBe('');
});

it('passes a direct Asset and numeric height parameter to the rasterizer', function (): void {
    $asset = Mockery::mock(Asset::class);

    $rasterizer = Mockery::mock(BrandLogoRasterizer::class);
    $rasterizer->shouldReceive('fallbackUrl')
        ->once()
        ->with($asset, 75)
        ->andReturn('/img/logo-75.png');
    $this->container->instance(BrandLogoRasterizer::class, $rasterizer);

    expect(makeLogoRasterFallbackTag(['asset' => $asset, 'height' => '75'])->index())
        ->toBe('/img/logo-75.png');
});

it('falls back to height 50 when the height parameter is not numeric', function (): void {
    $asset = Mockery::mock(Asset::class);

    $rasterizer = Mockery::mock(BrandLogoRasterizer::class);
    $rasterizer->shouldReceive('fallbackUrl')
        ->once()
        ->with($asset, 50)
        ->andReturn('/img/logo-50.png');
    $this->container->instance(BrandLogoRasterizer::class, $rasterizer);

    expect(makeLogoRasterFallbackTag(['asset' => $asset, 'height' => 'auto'])->index())
        ->toBe('/img/logo-50.png');
});

it('unwraps a Value wrapper containing an Asset', function (): void {
    $asset = Mockery::mock(Asset::class);

    $rasterizer = Mockery::mock(BrandLogoRasterizer::class);
    $rasterizer->shouldReceive('fallbackUrl')
        ->once()
        ->with($asset, 50)
        ->andReturn('/img/from-value.png');
    $this->container->instance(BrandLogoRasterizer::class, $rasterizer);

    $tag = makeLogoRasterFallbackTag();
    $tag->params['asset'] = new Value($asset);

    expect($tag->index())->toBe('/img/from-value.png');
});

it('selects the first Asset from an iterable or wrapped iterable', function (): void {
    $first = Mockery::mock(Asset::class);
    $second = Mockery::mock(Asset::class);

    $rasterizer = Mockery::mock(BrandLogoRasterizer::class);
    $rasterizer->shouldReceive('fallbackUrl')
        ->once()
        ->with($first, 64)
        ->andReturn('/img/first.png');
    $this->container->instance(BrandLogoRasterizer::class, $rasterizer);

    $wrapped = new Value(['not-an-asset', $first, $second]);

    expect(makeLogoRasterFallbackTag(['asset' => $wrapped, 'height' => 64])->index())
        ->toBe('/img/first.png');
});

it('passes null when an iterable contains no Asset or the asset param is scalar', function (mixed $assetParam): void {
    $rasterizer = Mockery::mock(BrandLogoRasterizer::class);
    $rasterizer->shouldReceive('fallbackUrl')
        ->once()
        ->with(null, 50)
        ->andReturn('');
    $this->container->instance(BrandLogoRasterizer::class, $rasterizer);

    expect(makeLogoRasterFallbackTag(['asset' => $assetParam])->index())->toBe('');
})->with([
    'empty array' => [[]],
    'non-asset items' => [['foo', 123, null]],
    'scalar string' => ['not-an-asset'],
]);
