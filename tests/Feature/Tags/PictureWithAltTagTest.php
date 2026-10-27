<?php

declare(strict_types=1);

use App\Tags\PictureWithAlt;
use Illuminate\Support\Facades\Storage;
use Statamic\Assets\Asset;
use Statamic\Assets\AssetContainer as StatamicAssetContainer;
use Statamic\Facades\Antlers;
use Statamic\Facades\AssetContainer;
use Tests\TestCase;

// Serial because it persists a file-backed asset container into the shared
// content mirror, the asset-container leak class (#1120), as
// SeoMetaSocialImageTest does.
uses(TestCase::class)->group('serial');

covers(PictureWithAlt::class);

/**
 * The Image Bard set renders its `alt_override` as the image's alt text (#3390).
 *
 * The peak-tools `picture` tag reads `alt` only from the asset, so the set
 * renders through `{{ picture_with_alt }}`, which applies a non-empty `alt`
 * parameter. Both builders are covered: the responsive `<img>` (JPEG) and the
 * SVG / GIF passthrough. Without an override the asset's own alt still renders,
 * and the asset itself is never modified.
 *
 * @command  php artisan test --compact tests/Feature/Tags/PictureWithAltTagTest.php
 */
const PICTURE_ALT_DISK = 'picture_alt_probe';

const PICTURE_ALT_CONTAINER = 'picture_alt_probe';

beforeEach(function (): void {
    Storage::fake(PICTURE_ALT_DISK);

    /** @var StatamicAssetContainer $container */
    $container = AssetContainer::make(PICTURE_ALT_CONTAINER);
    /** @var StatamicAssetContainer $container */
    $container = $container->disk(PICTURE_ALT_DISK);
    /** @var StatamicAssetContainer $container */
    $container = $container->title('Picture Alt Probe');
    $container->save();

    // Write a small JPEG for the responsive path
    $image = imagecreatetruecolor(40, 30);
    ob_start();
    imagejpeg($image);
    $bytes = (string) ob_get_clean();
    imagedestroy($image);
    Storage::disk(PICTURE_ALT_DISK)->put('photo.jpg', $bytes);

    // Write a small SVG for the passthrough path
    Storage::disk(PICTURE_ALT_DISK)->put(
        'mark.svg',
        '<svg xmlns="http://www.w3.org/2000/svg" width="40" height="30"></svg>',
    );

    /** @var StatamicAssetContainer $found */
    $found = AssetContainer::find(PICTURE_ALT_CONTAINER);
    foreach (['photo.jpg', 'mark.svg'] as $path) {
        $asset = $found->makeAsset($path);
        $asset->set('alt', 'Asset alt text');
        $asset->save();
    }
});

afterEach(function (): void {
    $container = AssetContainer::find(PICTURE_ALT_CONTAINER);

    if ($container instanceof StatamicAssetContainer) {
        $container->delete();
    }
});

/**
 * The fixture asset at the given path.
 *
 * Named for this file: Pest declares helpers in the GLOBAL namespace.
 */
function pictureAltFixtureAsset(string $path): Asset
{
    /** @var StatamicAssetContainer $container */
    $container = AssetContainer::find(PICTURE_ALT_CONTAINER);
    $asset = $container->asset($path);

    expect($asset)->toBeInstanceOf(Asset::class);

    /** @var Asset $asset */
    return $asset;
}

/**
 * Render the tag for the fixture asset with the given parameters.
 *
 * @param  array<string, mixed>  $params  Tag parameters besides `image`.
 */
function pictureAltRender(Asset $asset, array $params = []): string
{
    $tag = new PictureWithAlt;
    $tag->setContext([]);
    $tag->setParameters(['image' => $asset, 'lazy' => true] + $params);

    return $tag->index();
}

it('renders the override as the alt text', function (string $path): void {
    $html = pictureAltRender(pictureAltFixtureAsset($path), ['alt' => 'Placement alt text']);

    expect($html)->toContain('alt="Placement alt text."')
        ->and($html)->not->toContain('Asset alt text')
        ->and($html)->not->toContain('aria-hidden');
})->with(['responsive image' => 'photo.jpg', 'passthrough image' => 'mark.svg']);

it('renders the asset alt text when no override is given', function (string $path, ?string $alt): void {
    $html = pictureAltRender(pictureAltFixtureAsset($path), $alt === null ? [] : ['alt' => $alt]);

    expect($html)->toContain('alt="Asset alt text."');
})->with(['photo.jpg', 'mark.svg'])->with(['absent' => null, 'empty' => '', 'blank' => '   ']);

it('trims the override', function (): void {
    $html = pictureAltRender(pictureAltFixtureAsset('mark.svg'), ['alt' => '  Placement alt text  ']);

    expect($html)->toContain('alt="Placement alt text."');
});

it('leaves the asset alt text unchanged', function (): void {
    $asset = pictureAltFixtureAsset('photo.jpg');

    pictureAltRender($asset, ['alt' => 'Placement alt text']);

    expect($asset->get('alt'))->toBe('Asset alt text')
        ->and(pictureAltFixtureAsset('photo.jpg')->get('alt'))->toBe('Asset alt text');
});

it('renders the Image set override as the alt text, falling back to the asset alt', function (?string $override, string $expected): void {
    // Render trusted: the user-content sandbox blocks tags outright (#2040)
    $html = (string) Antlers::parse('{{ partial:sets/image }}', [
        'image' => pictureAltFixtureAsset('photo.jpg'),
        'alt_override' => $override,
        'crop' => '',
        'align' => 'aligncenter',
    ], true);

    expect($html)->toContain('<picture>')
        ->and($html)->toContain($expected);
})->with([
    'override' => ['Placement alt text', 'alt="Placement alt text."'],
    'no override' => [null, 'alt="Asset alt text."'],
]);
