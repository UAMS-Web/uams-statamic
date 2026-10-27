<?php

declare(strict_types=1);

use App\Rules\AssetMinimumDimensions;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Translation\PotentiallyTranslatedString;
use Statamic\Assets\Asset as AssetItem;
use Statamic\Facades\Asset;
use Tests\TestCase;

uses(TestCase::class);

/**
 * The AssetMinimumDimensions validation rule (UAMS-Web/uams-statamic-archive#1299): rejects a selected asset
 * whose STORED dimension meta is below the configured minimum, passes an adequate
 * one, and — crucially — SKIPS an asset the image driver could not measure
 * (width()/height() null, e.g. HEIC under GD) rather than false-rejecting it.
 *
 * The rule is exercised directly with stub assets (Mockery), so the reject / pass
 * / skip decision is covered deterministically without file I/O. End-to-end wiring
 * against a real file-backed container is proven in
 * tests/Feature/Fieldtypes/AssetsMinimumDimensionsEnforcementTest.php (serial).
 *
 * @command  php artisan test --compact tests/Feature/Rules/AssetMinimumDimensionsTest.php
 */

/**
 * A stub concrete asset with controlled dimensions and basename.
 */
function assetStub(mixed $width, mixed $height, string $basename = 'photo.jpg', bool $isSvg = false): AssetItem
{
    $asset = Mockery::mock(AssetItem::class);
    $asset->shouldReceive('width')->andReturn($width);
    $asset->shouldReceive('height')->andReturn($height);
    $asset->shouldReceive('basename')->andReturn($basename);
    $asset->shouldReceive('isSvg')->andReturn($isSvg);

    return $asset;
}

/**
 * Run the rule against a value and return the collected failure messages.
 *
 * @return list<string>
 */
function runAssetMinDimensions(AssetMinimumDimensions $rule, mixed $value): array
{
    $failures = [];

    $rule->validate('logo_image', $value, function (string $message) use (&$failures): PotentiallyTranslatedString {
        $failures[] = $message;

        return new PotentiallyTranslatedString($message, app(Translator::class));
    });

    return $failures;
}

it('rejects an asset narrower than the minimum width', function () {
    Asset::shouldReceive('find')->with('assets::narrow.jpg')->andReturn(assetStub(640, 480));

    $failures = runAssetMinDimensions(new AssetMinimumDimensions(800, 600), ['assets::narrow.jpg']);

    expect($failures)->toHaveCount(1);
    expect($failures[0])->toContain('at least 800x600')->toContain('640x480');
});

it('rejects an asset shorter than the minimum height even when wide enough', function () {
    Asset::shouldReceive('find')->with('assets::short.jpg')->andReturn(assetStub(1200, 480));

    $failures = runAssetMinDimensions(new AssetMinimumDimensions(800, 600), ['assets::short.jpg']);

    expect($failures)->toHaveCount(1);
    expect($failures[0])->toContain('1200x480');
});

it('passes an asset exactly at the minimum', function () {
    Asset::shouldReceive('find')->with('assets::exact.jpg')->andReturn(assetStub(800, 600));

    expect(runAssetMinDimensions(new AssetMinimumDimensions(800, 600), ['assets::exact.jpg']))->toBe([]);
});

it('passes an asset larger than the minimum', function () {
    Asset::shouldReceive('find')->with('assets::big.jpg')->andReturn(assetStub(1920, 1080));

    expect(runAssetMinDimensions(new AssetMinimumDimensions(800, 600), ['assets::big.jpg']))->toBe([]);
});

it('skips an unmeasurable asset (null width or height) instead of rejecting it', function (mixed $width, mixed $height) {
    Asset::shouldReceive('find')->with('assets::heic.jpg')->andReturn(assetStub($width, $height));

    // A large minimum that WOULD reject a measured asset; null dimensions must skip.
    expect(runAssetMinDimensions(new AssetMinimumDimensions(4000, 4000), ['assets::heic.jpg']))->toBe([]);
})->with([
    'both null (HEIC under GD)' => [null, null],
    'null width' => [null, 480],
    'null height' => [640, null],
    'non-numeric width' => ['wide', 480],
    'both zero (corrupt/undecodable image)' => [0, 0],
    'zero width' => [0, 480],
    'zero height' => [640, 0],
    'negative dimension' => [-1, 480],
]);

it('honours a numeric-string dimension', function () {
    // Meta can round-trip through YAML as a numeric string; it must still enforce.
    Asset::shouldReceive('find')->with('assets::stringdims.jpg')->andReturn(assetStub('640', '480'));

    expect(runAssetMinDimensions(new AssetMinimumDimensions(800, 600), ['assets::stringdims.jpg']))->toHaveCount(1);
});

it('skips an SVG (a scalable vector has no meaningful pixel minimum)', function () {
    // 16x16 would be rejected if measured, but an SVG must be skipped.
    Asset::shouldReceive('find')->with('assets::icon.svg')->andReturn(assetStub(16, 16, 'icon.svg', isSvg: true));

    expect(runAssetMinDimensions(new AssetMinimumDimensions(800, 600), ['assets::icon.svg']))->toBe([]);
});

it('skips an unresolvable reference instead of rejecting it', function () {
    Asset::shouldReceive('find')->with('assets::missing.jpg')->andReturn(null);

    expect(runAssetMinDimensions(new AssetMinimumDimensions(800, 600), ['assets::missing.jpg']))->toBe([]);
});

it('rejects when any selected asset is undersized', function () {
    Asset::shouldReceive('find')->with('assets::ok.jpg')->andReturn(assetStub(1000, 800));
    Asset::shouldReceive('find')->with('assets::bad.jpg')->andReturn(assetStub(300, 200, 'bad.jpg'));

    $failures = runAssetMinDimensions(new AssetMinimumDimensions(800, 600), ['assets::ok.jpg', 'assets::bad.jpg']);

    expect($failures)->toHaveCount(1);
    expect($failures[0])->toContain('bad.jpg');
});

it('applies only a width minimum when no height minimum is set', function () {
    Asset::shouldReceive('find')->with('assets::tallthin.jpg')->andReturn(assetStub(900, 5));

    // Height 5 is tiny but there is no height minimum, so it passes.
    expect(runAssetMinDimensions(new AssetMinimumDimensions(800, 0), ['assets::tallthin.jpg']))->toBe([]);

    Asset::shouldReceive('find')->with('assets::toonarrow.jpg')->andReturn(assetStub(700, 5000));
    $failures = runAssetMinDimensions(new AssetMinimumDimensions(800, 0), ['assets::toonarrow.jpg']);
    expect($failures)->toHaveCount(1);
    expect($failures[0])->toContain('at least 800 pixels wide')->toContain('it is 700.');
});

it('applies only a height minimum when no width minimum is set', function () {
    Asset::shouldReceive('find')->with('assets::wideshort.jpg')->andReturn(assetStub(5, 900));

    // Width 5 is tiny but there is no width minimum, so it passes.
    expect(runAssetMinDimensions(new AssetMinimumDimensions(0, 800), ['assets::wideshort.jpg']))->toBe([]);

    Asset::shouldReceive('find')->with('assets::tooshort.jpg')->andReturn(assetStub(5000, 700));
    $failures = runAssetMinDimensions(new AssetMinimumDimensions(0, 800), ['assets::tooshort.jpg']);
    expect($failures)->toHaveCount(1);
    expect($failures[0])->toContain('at least 800 pixels tall')->toContain('it is 700.');
});

it('resolves a bare-path reference through the configured containers', function () {
    // A bare path is tried as `container::path` against each configured container.
    Asset::shouldReceive('find')->with('logos::logo.png')->andReturn(assetStub(320, 240, 'logo.png'));

    $failures = runAssetMinDimensions(new AssetMinimumDimensions(800, 600, ['logos']), ['logo.png']);

    expect($failures)->toHaveCount(1);
    expect($failures[0])->toContain('logo.png');
});

it('tries each configured container in turn for a bare path', function () {
    Asset::shouldReceive('find')->with('logos::logo.png')->andReturn(null);
    Asset::shouldReceive('find')->with('logos_unrestricted::logo.png')->andReturn(assetStub(320, 240, 'logo.png'));

    $failures = runAssetMinDimensions(
        new AssetMinimumDimensions(800, 600, ['logos', 'logos_unrestricted']),
        ['logo.png'],
    );

    expect($failures)->toHaveCount(1);
});

it('skips a bare-path reference when no container resolves it', function () {
    Asset::shouldReceive('find')->with('logos::orphan.png')->andReturn(null);

    expect(runAssetMinDimensions(new AssetMinimumDimensions(800, 600, ['logos']), ['orphan.png']))->toBe([]);
});

it('accepts a single scalar reference as well as an array', function () {
    Asset::shouldReceive('find')->with('assets::solo.jpg')->andReturn(assetStub(100, 100, 'solo.jpg'));

    // max_files = 1 submits a single string, not an array.
    $failures = runAssetMinDimensions(new AssetMinimumDimensions(800, 600), 'assets::solo.jpg');

    expect($failures)->toHaveCount(1);
});

it('passes when the value is empty or contains no usable references', function (mixed $value) {
    expect(runAssetMinDimensions(new AssetMinimumDimensions(800, 600), $value))->toBe([]);
})->with([
    'null' => [null],
    'empty array' => [[]],
    'array of blanks' => [['', null]],
]);

it('goes on to check the next asset after one it skips', function (mixed $skipped) {
    // The skipped reference is listed first, so a skip that stopped the loop would pass
    Asset::shouldReceive('find')->with('assets::skipped.jpg')->andReturn($skipped);
    Asset::shouldReceive('find')->with('assets::small.jpg')->andReturn(assetStub(300, 200, 'small.jpg'));

    expect(runAssetMinDimensions(new AssetMinimumDimensions(800, 600), ['assets::skipped.jpg', 'assets::small.jpg']))
        ->toBe(['small.jpg must be at least 800x600 pixels; it is 300x200.']);
})->with([
    'unresolvable' => fn (): null => null,
    'an SVG' => fn (): AssetItem => assetStub(16, 16, 'icon.svg', isSvg: true),
    'no width' => fn (): AssetItem => assetStub(null, 480),
    'no height' => fn (): AssetItem => assetStub(640, null),
    'zero width' => fn (): AssetItem => assetStub(0, 480),
    'zero height' => fn (): AssetItem => assetStub(640, 0),
]);

it('measures a one-pixel dimension rather than skipping it', function (int $width, int $height, string $expected) {
    Asset::shouldReceive('find')->with('assets::pixel.jpg')->andReturn(assetStub($width, $height, 'pixel.jpg'));

    expect(runAssetMinDimensions(new AssetMinimumDimensions(800, 600), ['assets::pixel.jpg']))->toBe([$expected]);
})->with([
    'one pixel wide' => [1, 900, 'pixel.jpg must be at least 800x600 pixels; it is 1x900.'],
    'one pixel tall' => [900, 1, 'pixel.jpg must be at least 800x600 pixels; it is 900x1.'],
]);

it('names both minimums once each is set, however small', function (int $minWidth, int $minHeight, int $width, int $height, string $expected) {
    Asset::shouldReceive('find')->with('assets::photo.jpg')->andReturn(assetStub($width, $height));

    expect(runAssetMinDimensions(new AssetMinimumDimensions($minWidth, $minHeight), ['assets::photo.jpg']))->toBe([$expected]);
})->with([
    'a one-pixel width minimum' => [1, 600, 500, 400, 'photo.jpg must be at least 1x600 pixels; it is 500x400.'],
    'a one-pixel height minimum' => [800, 1, 500, 400, 'photo.jpg must be at least 800x1 pixels; it is 500x400.'],
]);

it('never looks up a value that is empty or not a string', function () {
    Asset::shouldReceive('find')->never();

    expect(runAssetMinDimensions(new AssetMinimumDimensions(800, 600, ['assets']), ['', 42, null]))->toBe([]);
});

it('skips a repository result that is not a concrete asset', function () {
    Asset::shouldReceive('find')->with('assets::other.jpg')->andReturn(new stdClass);

    expect(runAssetMinDimensions(new AssetMinimumDimensions(800, 600), ['assets::other.jpg']))->toBe([]);
});
