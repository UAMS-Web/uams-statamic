<?php

declare(strict_types=1);

use App\Models\Asset as AssetMetadataModel;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;

/**
 * `App\Models\Asset` dual-writes every save to `content/models/assets/{id}.yml`
 * for version control. Under test the metadata database is `:memory:`
 * (phpunit.xml `ASSET_METADATA_DATABASE`), so its autoincrement restarts at `1`
 * in every process — and the first asset any test creates therefore lands on the
 * real `1.yml`, replacing a stored image's alt text, caption and photographer
 * credit with a fixture's (UAMS-Web/uams-statamic-archive#1919).
 *
 * The seam that suppresses the write already existed. Its DEFAULT was the bug:
 * `Asset::$skipYamlSync` was opt-out, so a suite stayed safe only while every
 * asset-creating test remembered a toggle — five did, and the ones that did not
 * destroyed content silently. UAMS-Web/uams-statamic-archive#1878 read as having closed the class because the
 * guard it added (`SvgUploadSanitizationTest`'s "writes no asset metadata into the
 * content submodule") is scoped to the SVG upload path and never saw the rest.
 *
 * Do not take the ticket's roster of offenders at face value; it had gone stale.
 * Of the three suites UAMS-Web/uams-statamic-archive#1919 names as unguarded, one arms the flag today and two
 * touch no asset at all, while the suite that actually reproduced the corruption
 * here — `MigrationRedirectStructuralTest`, which rewrote `1.yml` with
 * `structural_assets` / `2019/brochure.pdf` — is not on the list.
 *
 * That is the gap this file exists to close. It asserts the property at the seam
 * — for ANY save, not one code path — and it asserts BOTH directions, because a
 * one-directional guard here is indistinguishable from the feature being dead:
 *
 *  1. suppression is armed by default, so a test that never heard of the flag
 *     writes nothing; and
 *  2. a save that opts back in still writes the YAML, so the version-controlled
 *     dual-write production depends on is demonstrably intact.
 *
 * The snapshot reads a directory every worker shares, and only serial tests write
 * there. `Asset::$skipYamlSync` is static, so it holds per process (#385).
 *
 * Since UAMS-Web/uams-statamic-archive#1916 that directory is the disposable `content/` mirror rather than the
 * submodule, and the path is a config key rather than a `base_path()` literal —
 * so what this file guards is the *flag*, and the structural guarantee that no
 * configured path points into `content/` belongs to `ContentMirrorIsolationTest`.
 * Both are needed: the re-root stops a forgotten flag from reaching tracked
 * content, and this stops the dual-write from silently becoming dead code.
 *
 * @command  php artisan test --compact tests/Feature/Assets/AssetMetadataYamlSyncIsolationTest.php
 */
uses(TestCase::class);

/**
 * Filename => content hash for every file in the real asset-metadata directory.
 *
 * Hashing the whole directory rather than one well-known id keeps the assertion
 * independent of which id the `:memory:` autoincrement happens to hand out, and
 * catches a stray write to any file rather than only to `1.yml`.
 *
 * @return array<string, string> Filename keyed to its md5.
 */
function assetMetadataDirectorySnapshot(string $directory): array
{
    return collect(File::files($directory))
        ->mapWithKeys(fn ($file): array => [$file->getFilename() => (string) md5_file($file->getPathname())])
        ->all();
}

/**
 * Persist a probe asset row through the real model save path.
 *
 * Writes the model directly rather than uploading through a container: the
 * dual-write hangs off Eloquent's `saved` event, so this is the narrowest input
 * that reaches it, and it needs no faked disk.
 */
function saveProbeAssetMetadata(string $container, string $path): AssetMetadataModel
{
    $probe = new AssetMetadataModel;
    $probe->setAttribute('container', $container);
    $probe->setAttribute('folder', \dirname($path));
    $probe->setAttribute('basename', basename($path));
    $probe->setAttribute('filename', pathinfo($path, PATHINFO_FILENAME));
    $probe->setAttribute('extension', pathinfo($path, PATHINFO_EXTENSION));
    $probe->setAttribute('path', $path);
    $probe->setAttribute('meta', ['data' => ['alt' => 'probe']]);
    $probe->save();

    return $probe;
}

/**
 * The `{id}.yml` path a saved probe would dual-write to, resolved through the
 * same config key the model reads.
 *
 * `getKey()` is typed `mixed`, so the id is narrowed by control flow rather than
 * annotation — a probe that reached here without an integer key means the save
 * silently did not happen, and failing loudly on that is the point: the opt-in
 * test below would otherwise assert against a path built from an empty id.
 */
function probeAssetYamlPath(AssetMetadataModel $probe): string
{
    $key = $probe->getKey();

    if (! \is_int($key)) {
        throw new RuntimeException('the probe asset was saved without an integer primary key');
    }

    return Config::string('asset_metadata.yaml_directory').'/'.$key.'.yml';
}

it('suppresses the asset metadata YAML write for a test that never touches the flag', function (): void {
    $directory = Config::string('asset_metadata.yaml_directory');

    // ---
    // Route through the UAMS-Web/uams-statamic-archive#1738 discriminator rather than skipping outright. A bare
    // skip would report as a pass whenever the directory is missing for any
    // reason — and with `content/` initialized, a missing asset metadata
    // directory is a defect, not an absent submodule. Since UAMS-Web/uams-statamic-archive#1916 the directory
    // is the `content/` mirror rather than the submodule itself, but it is a
    // copy of it: absent here still means absent at the source.
    // ---
    if (! File::isDirectory($directory)) {
        ContentSubmodule::reportMissing('the asset metadata this test snapshots for changes');
    }

    // The flag is armed centrally, by Tests\TestCase::setUp() — this test does
    // nothing to it, which is precisely the condition being asserted.
    expect(AssetMetadataModel::$skipYamlSync)->toBeTrue();

    $before = assetMetadataDirectorySnapshot($directory);

    // A probe whose id collides with a committed file is the whole failure mode,
    // so assert there is something to collide with before relying on the snapshot.
    expect($before)->not->toBeEmpty();

    saveProbeAssetMetadata('probe_container', 'probe/asset-metadata-isolation.jpg');

    // Byte-identical: no file rewritten, and none added.
    expect(assetMetadataDirectorySnapshot($directory))->toBe($before);
});

it('still writes the YAML when a save opts back in', function (): void {
    // ---
    // The counterweight to the test above. Suppression that could not be lifted
    // would be a disabled feature rather than an isolated one, and every
    // assertion in this file would still pass — so exercise the real write, just
    // against a directory that is neither the content submodule nor its mirror.
    //
    // `writeMetadataToYaml()` resolves its target from config (UAMS-Web/uams-statamic-archive#1916), so a
    // one-key override is what makes the production code path safe to run here.
    // It used to take `App::setBasePath()` — a process-wide root every other
    // `base_path()` caller also reads — which is exactly the kind of blast
    // radius a config seam removes.
    // ---
    $temporaryDirectory = storage_path('framework/testing/asset-yaml-sync-'.uniqid());

    try {
        Config::set('asset_metadata.yaml_directory', $temporaryDirectory);
        AssetMetadataModel::$skipYamlSync = false;

        $probe = saveProbeAssetMetadata('opt_in_container', 'opt-in/brochure.pdf');

        $written = probeAssetYamlPath($probe);

        expect(File::exists($written))->toBeTrue();

        $parsed = Yaml::parseFile($written);

        // Narrowed by control flow: a scalar here means the writer emitted
        // something other than the mapping every consumer of these files reads.
        if (! \is_array($parsed)) {
            throw new RuntimeException('the written asset metadata YAML did not parse to an array');
        }

        expect($parsed['container'])->toBe('opt_in_container')
            ->and($parsed['path'])->toBe('opt-in/brochure.pdf')
            ->and($parsed['basename'])->toBe('brochure.pdf');
    } finally {
        AssetMetadataModel::$skipYamlSync = true;
        File::deleteDirectory($temporaryDirectory);
    }
});

it('leaves the suppression armed after the sync command restores it', function (): void {
    // ---
    // `assets:sync-from-yaml` disables the dual-write around its own run. It used
    // to re-enable it by ASSIGNING false, which is correct in production (where
    // false is the only value it can have been) and wrong under test, where it
    // would hand the rest of the test a live dual-write against the real
    // submodule. Restoring the caller's value is what makes the command safe to
    // invoke from a test at all.
    // ---
    $temporaryDirectory = storage_path('framework/testing/asset-yaml-sync-cmd-'.uniqid());

    try {
        // The command reads (and truncates against) the configured YAML
        // directory; point it at a temp tree so neither the real submodule nor
        // its mirror is read or deleted.
        Config::set('asset_metadata.yaml_directory', $temporaryDirectory);
        File::ensureDirectoryExists($temporaryDirectory);

        // ---
        // The seeded file is load-bearing, not scenery. `handle()` returns at its
        // "No YAML files found" guard on an EMPTY directory — before it captures
        // the caller's flag and before the `try`/`finally` exists at all — so an
        // unseeded version of this test asserts nothing: it stays green against
        // the very `= false` assignment it is here to reject. Caught in review.
        // ---
        File::put($temporaryDirectory.'/1.yml', Yaml::dump([
            'id' => 1,
            'container' => 'opt_in_container',
            'folder' => 'opt-in',
            'basename' => 'brochure.pdf',
            'filename' => 'brochure',
            'extension' => 'pdf',
            'path' => 'opt-in/brochure.pdf',
            'meta' => json_encode(['data' => []]),
            'created_at' => null,
            'updated_at' => null,
        ]));

        // Pin that the body RAN. Without this the guard above can be silently
        // re-vacated by any future early return, and the test would go back to
        // passing for the wrong reason with nothing to show for it.
        pendingArtisan('assets:sync-from-yaml')
            ->expectsOutputToContain('Synced 1 asset')
            ->assertSuccessful();

        expect(AssetMetadataModel::$skipYamlSync)->toBeTrue();
    } finally {
        AssetMetadataModel::$skipYamlSync = true;
        File::deleteDirectory($temporaryDirectory);
    }
});
