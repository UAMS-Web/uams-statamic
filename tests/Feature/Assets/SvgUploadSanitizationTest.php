<?php

declare(strict_types=1);

use App\Support\Imaging\SvgHazardScanner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Statamic\Assets\AssetContainer as StatamicAssetContainer;
use Statamic\Facades\AssetContainer;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;

/**
 * `statamic.assets.svg_sanitization_on_upload` is the control that makes SVG a
 * supported format here rather than a liability (UAMS-Web/uams-statamic-archive#1739). It routes every
 * uploaded `.svg` through `rhukster/dom-sanitizer` in
 * `Statamic\Assets\Uploader::write()`.
 *
 * Before this test it was an unwatched config flag: nothing failed if it were
 * flipped off, or if an upstream change stopped honoring it. This asserts the
 * behavior end-to-end through the real upload path, so the flag is covered
 * rather than assumed.
 *
 * Runs in the parallel band. It fakes the `logos` disk, which under `--parallel`
 * is rooted per worker (UAMS-Web/uams-statamic-archive#3111), and persists no container; the asset metadata
 * directory it snapshots is written only by serial tests or by tests that point
 * it at their own directory (UAMS-Web/uams-statamic-archive#3205).
 *
 * @command  php artisan test --compact tests/Feature/Assets/SvgUploadSanitizationTest.php
 */
uses(TestCase::class);

/**
 * SVG markup carrying a script element, an event handler, and a javascript URL.
 */
function svgUploadSanitizationHostileMarkup(): string
{
    return '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" '
        .'width="100" height="100" viewBox="0 0 100 100">'
        .'<script>alert(1)</script>'
        .'<rect width="100" height="100" fill="#1f4e79" onload="alert(2)"/>'
        .'<a xlink:href="javascript:alert(3)"><circle cx="50" cy="50" r="10"/></a>'
        .'</svg>';
}

/**
 * Upload markup to the `logos` container and return what was actually stored.
 *
 * @param  string  $markup  The SVG markup to upload
 * @param  string  $filename  The upload filename
 * @return string The stored file's contents
 */
function svgUploadSanitizationUploadAndRead(
    string $markup,
    string $filename
): string {
    // Resolve the container whose disk this test has faked
    $container = AssetContainer::find('logos');

    // Fail loudly rather than vacuously passing when the container is missing
    if (! $container instanceof StatamicAssetContainer) {
        throw new RuntimeException('the logos asset container was not found');
    }

    // Write the markup to a real temp file so UploadedFile has bytes to move
    $source = tempnam(sys_get_temp_dir(), 'svg').'.svg';
    file_put_contents($source, $markup);

    // Push it through Statamic's real upload path
    $asset = $container->makeAsset($filename)->upload(
        new UploadedFile($source, $filename, 'image/svg+xml', null, true)
    );

    // Narrow the stored path before reading it back
    $path = $asset->path();

    // Fail loudly when the upload did not produce a readable path
    if (! is_string($path)) {
        throw new RuntimeException("upload of [{$filename}] produced no stored path");
    }

    // Read back exactly what landed on the faked disk
    return (string) Storage::disk('logos')->get($path);
}

beforeEach(function (): void {
    // Fake the logos disk so nothing reaches the public/assets submodule
    Storage::fake('logos');

    // ---
    // Faking the disk is not enough. `App\Models\Asset` dual-writes every save
    // to `content/models/assets/<id>.yml`, and the metadata database is
    // `:memory:` under test — so the autoincrement restarts at 1 and an upload
    // here OVERWRITES the real `1.yml` in the content submodule. Observed
    // exactly that: a stored image's metadata replaced by this test's fixture.
    //
    // This file used to arm `Asset::$skipYamlSync` itself. It no longer has to:
    // UAMS-Web/uams-statamic-archive#1919 inverted the default so `Tests\TestCase::setUp()` arms it for every
    // test, which is what closed the class this file only closed for its own
    // upload path. The guard at the bottom is unchanged and now pins the CENTRAL
    // mechanism — drop it and this file goes red.
    // ---
});

it('strips script-bearing constructs from an uploaded svg', function (): void {
    // Confirm the fixture is genuinely hostile before asserting it gets cleaned
    expect((new SvgHazardScanner)->scan(svgUploadSanitizationHostileMarkup()))
        ->toHaveCount(3);

    // Upload the hostile markup through the real container path
    $stored = svgUploadSanitizationUploadAndRead(
        svgUploadSanitizationHostileMarkup(),
        'hostile.svg'
    );

    // Assert the stored file carries none of the three constructs
    expect((new SvgHazardScanner)->scan($stored))->toBe([]);
});

it('preserves the drawable content it sanitizes around', function (): void {
    // Upload the hostile markup through the real container path
    $stored = svgUploadSanitizationUploadAndRead(
        svgUploadSanitizationHostileMarkup(),
        'preserved.svg'
    );

    // ---
    // Sanitization must remove the script, not the artwork — a sanitizer that
    // emptied the file would pass the assertion above while breaking every logo.
    // ---
    expect($stored)->toContain('<rect')
        ->and($stored)->toContain('<circle')
        ->and($stored)->toContain('#1f4e79');
});

it('writes no asset metadata into the content submodule', function (): void {
    // ---
    // A guard on the guard. This test uploads through the real path, and
    // `App\Models\Asset` dual-writes every save to `content/models/assets/`.
    // Without suppression that overwrote a real stored asset's metadata — a
    // silent edit to tracked content, invisible until someone noticed a dirty
    // submodule. This pins the property so the suppression cannot be dropped
    // without a test going red.
    //
    // Since UAMS-Web/uams-statamic-archive#1919 the suppression is `Tests\TestCase::setUp()`'s, not this
    // file's, so what this now guards is the CENTRAL default. It stays scoped to
    // the SVG upload path on purpose — that narrowness is exactly why it kept
    // passing while three other suites went on corrupting `1.yml`, and
    // `AssetMetadataYamlSyncIsolationTest` is the general-case guard added to
    // cover what this one structurally cannot see.
    // ---
    // The directory the dual-write would actually land in. Since UAMS-Web/uams-statamic-archive#1916 that is
    // the disposable `content/` mirror rather than the submodule, so a write
    // here is no longer a tracked-content edit — but it is still the write this
    // test exists to catch, and reading the configured path keeps the assertion
    // pointed at wherever the model writes rather than at a hard-coded guess.
    $directory = Config::string('asset_metadata.yaml_directory');

    // ---
    // Route through the UAMS-Web/uams-statamic-archive#1738 discriminator rather than skipping outright. A
    // bare skip here would report as a pass whenever the directory is missing
    // for any reason — and with `content/` initialized, a missing asset
    // metadata directory is a defect, not an absent submodule. `reportMissing()`
    // skips only in the genuinely-absent case and fails in every other.
    // ---
    if (! File::isDirectory($directory)) {
        ContentSubmodule::reportMissing('the asset metadata this test snapshots for changes');
    }

    // Snapshot the metadata directory before the upload
    $before = collect(File::files($directory))
        ->mapWithKeys(fn ($file): array => [$file->getFilename() => md5_file($file->getPathname())])
        ->all();

    // Push an upload through the real container path
    svgUploadSanitizationUploadAndRead(
        svgUploadSanitizationHostileMarkup(),
        'leak-probe.svg'
    );

    // Snapshot it again afterwards
    $after = collect(File::files($directory))
        ->mapWithKeys(fn ($file): array => [$file->getFilename() => md5_file($file->getPathname())])
        ->all();

    expect($after)->toBe($before, 'the upload changed asset metadata in the configured YAML directory');
});

it('honors the sanitization config flag', function (): void {
    // ---
    // Pin the flag itself. If it is ever flipped off, this fails with a message
    // naming the config key rather than surfacing later as a stored XSS.
    // ---
    expect(config('statamic.assets.svg_sanitization_on_upload'))->toBeTrue();
});
