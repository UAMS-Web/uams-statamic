<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Testing\PendingCommand;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

use function Pest\Laravel\artisan;

uses(TestCase::class);

/**
 * Feature tests for the orphaned-logo-metadata sweep Artisan command (UAMS-Web/uams-statamic-archive#1084).
 *
 * The command rewrites the canonical YAML source (content/models/assets/*.yml),
 * so these tests repoint the app base path at an isolated temp dir — the real
 * content/ submodule is never read or written. They pin the load-bearing
 * behavior: orphaned alt/caption/exempt_from_alt keys are stripped from logo
 * containers, image/portrait data is left untouched, unrelated keys survive, the
 * sweep is idempotent, and --dry-run never writes.
 *
 * @command  php artisan test --compact tests/Feature/Console/PruneLogoAssetMetadataCommandTest.php
 */

/**
 * Absolute path to the temp-rooted asset metadata directory.
 *
 * @var string|null
 */
$pruneAssetsDir = null;

/**
 * Isolated temp tree holding the YAML source directory for one test.
 *
 * @var string|null
 */
$pruneTempDir = null;

/**
 * Run the sweep command and return its PendingCommand for fluent assertions.
 *
 * The returned value MUST be chained on directly: a PendingCommand executes in
 * its destructor, so storing it would defer the run past the disk assertions.
 *
 * @param  array<string, mixed>  $parameters  Command options
 */
function pruneRunCommand(array $parameters = []): PendingCommand
{
    // Return it unstored so the caller chains and it destructs in-statement.
    // pendingArtisan() resolves artisan()'s PendingCommand|int union.
    return pendingArtisan('assets:prune-logo-metadata', $parameters);
}

/**
 * Write an asset metadata YAML file with the given container and field data.
 *
 * Matches the on-disk format App\Models\Asset::writeMetadataToYaml produces:
 * `meta` is a JSON string carrying a `data` slice plus file-level keys.
 *
 * @param  string  $assetsDir  Absolute path to the temp-rooted assets directory
 * @param  int  $id  Primary key (used as the filename)
 * @param  string  $container  Asset container handle
 * @param  array<string, mixed>  $assetData  The meta.data field-value slice
 */
function pruneWriteAssetYaml(
    string $assetsDir,
    int $id,
    string $container,
    array $assetData
): void {
    // Build the meta payload with the field data plus a realistic file key
    $meta = [
        'data' => $assetData,
        'size' => 1234,
        'last_modified' => 1738850299,
        'mime_type' => 'image/svg+xml',
    ];

    // Build the file body matching the canonical YAML structure
    $data = [
        'id' => $id,
        'container' => $container,
        'folder' => 'uams/master-logo',
        'basename' => "logo-{$id}.svg",
        'filename' => "logo-{$id}",
        'extension' => 'svg',
        'path' => "uams/master-logo/logo-{$id}.svg",
        'meta' => json_encode($meta),
        'created_at' => '2025-02-06T13:58:19+00:00',
        'updated_at' => '2025-02-06T13:58:19+00:00',
    ];

    // Persist the YAML file the command will read back
    File::put(
        "{$assetsDir}/{$id}.yml",
        Yaml::dump($data, 4, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK)
    );
}

/**
 * Read a YAML file back and return its decoded meta.data slice.
 *
 * @param  string  $assetsDir  Absolute path to the temp-rooted assets directory
 * @param  int  $id  Asset id / filename
 * @return array<string, mixed> The decoded meta.data, or an empty array
 */
function pruneReadAssetData(
    string $assetsDir,
    int $id
): array {
    // Parse the on-disk YAML file
    /** @var array<string, mixed> $data */
    $data = Yaml::parseFile("{$assetsDir}/{$id}.yml");

    // Decode the JSON meta string into an array
    /** @var string $metaJson */
    $metaJson = $data['meta'];
    /** @var array{data?: array<string, mixed>} $meta */
    $meta = json_decode($metaJson, true);

    // Return the data slice, normalized to an array
    $dataSlice = $meta['data'] ?? null;

    return \is_array($dataSlice) ? $dataSlice : [];
}

/**
 * Repoint the app base path at an isolated, empty YAML source directory.
 */
beforeEach(function () use (&$pruneAssetsDir, &$pruneTempDir): void {
    // Point the command's YAML source at a temp dir so it never rewrites the
    // real one. A config override rather than `App::setBasePath()` (UAMS-Web/uams-statamic-archive#1916): the
    // path is configuration now, so redirecting it no longer means mutating a
    // process-wide root that every other `base_path()` caller also resolves.
    $pruneTempDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'prune-logo-test-'.uniqid();
    $pruneAssetsDir = $pruneTempDir.DIRECTORY_SEPARATOR.'models'.DIRECTORY_SEPARATOR.'assets';
    Config::set('asset_metadata.yaml_directory', $pruneAssetsDir);

    // Start every test from a clean, empty source directory
    File::ensureDirectoryExists($pruneAssetsDir);
});

/**
 * Remove the temp tree. The config override dies with the per-test app.
 */
afterEach(function () use (&$pruneTempDir): void {
    // Remove the isolated temp directory this test created
    if ($pruneTempDir !== null) {
        File::deleteDirectory($pruneTempDir);
    }
});

it('strips orphaned alt/caption/exempt_from_alt from a logos asset', function () use (&$pruneAssetsDir): void {
    /** @var string $pruneAssetsDir */

    // A logo asset carrying both real config keys and the orphaned keys
    pruneWriteAssetYaml($pruneAssetsDir, 219, 'logos', [
        'orientation' => 'horizontal',
        'color_scheme' => 'black',
        'alt' => 'UAMS logo',
        'caption' => 'A caption',
        'exempt_from_alt' => true,
    ]);

    // Run the sweep and expect one file with three keys pruned
    pruneRunCommand()
        ->expectsOutputToContain('Pruned 3 orphaned key(s) from 1 logo asset file(s).')
        ->assertExitCode(0);

    // The orphaned keys are gone; the real config keys survive
    expect(pruneReadAssetData($pruneAssetsDir, 219))
        ->toBe([
            'orientation' => 'horizontal',
            'color_scheme' => 'black',
        ]);
});

it('strips orphaned keys from a logos_unrestricted asset too', function () use (&$pruneAssetsDir): void {
    /** @var string $pruneAssetsDir */

    // A logos_unrestricted asset with one orphaned key
    pruneWriteAssetYaml($pruneAssetsDir, 1, 'logos_unrestricted', [
        'title' => 'fpo_200.jpg',
        'alt' => 'orphaned',
    ]);

    pruneRunCommand()
        ->expectsOutputToContain('Pruned 1 orphaned key(s) from 1 logo asset file(s).')
        ->assertExitCode(0);

    // The orphaned alt is gone; the real title survives
    expect(pruneReadAssetData($pruneAssetsDir, 1))
        ->toBe(['title' => 'fpo_200.jpg']);
});

it('leaves image and portrait asset data untouched', function () use (&$pruneAssetsDir): void {
    /** @var string $pruneAssetsDir */

    // Content-image and portrait assets keep alt/caption/exempt_from_alt as real fields
    pruneWriteAssetYaml($pruneAssetsDir, 10, 'images', [
        'alt' => 'A real alt',
        'caption' => 'A real caption',
        'exempt_from_alt' => false,
    ]);
    pruneWriteAssetYaml($pruneAssetsDir, 200, 'portraits', [
        'alt' => 'A portrait alt',
    ]);

    // Capture the exact bytes so we can prove the files are not rewritten at all
    $imagesBefore = File::get("{$pruneAssetsDir}/10.yml");
    $portraitsBefore = File::get("{$pruneAssetsDir}/200.yml");

    pruneRunCommand()
        ->expectsOutputToContain('Pruned 0 orphaned key(s) from 0 logo asset file(s).')
        ->assertExitCode(0);

    // The image / portrait data is byte-for-byte unchanged
    expect(File::get("{$pruneAssetsDir}/10.yml"))->toBe($imagesBefore)
        ->and(File::get("{$pruneAssetsDir}/200.yml"))->toBe($portraitsBefore);

    // And the keys remain present as real fields
    expect(pruneReadAssetData($pruneAssetsDir, 10))
        ->toBe(['alt' => 'A real alt', 'caption' => 'A real caption', 'exempt_from_alt' => false]);
});

it('is idempotent: a clean logo file is not rewritten', function () use (&$pruneAssetsDir): void {
    /** @var string $pruneAssetsDir */

    // A logo asset that already carries no orphaned keys
    pruneWriteAssetYaml($pruneAssetsDir, 220, 'logos', [
        'orientation' => 'vertical',
        'color_scheme' => 'dark',
    ]);

    // Capture the exact bytes to prove a clean file is left alone
    $before = File::get("{$pruneAssetsDir}/220.yml");

    pruneRunCommand()
        ->expectsOutputToContain('Pruned 0 orphaned key(s) from 0 logo asset file(s).')
        ->assertExitCode(0);

    // The clean file is byte-for-byte unchanged
    expect(File::get("{$pruneAssetsDir}/220.yml"))->toBe($before);
});

it('dry run reports the count without writing', function () use (&$pruneAssetsDir): void {
    /** @var string $pruneAssetsDir */

    // A logo asset with an orphaned caption that a real run would strip
    pruneWriteAssetYaml($pruneAssetsDir, 235, 'logos', [
        'orientation' => 'horizontal',
        'caption' => 'orphaned caption',
    ]);

    // Capture the exact bytes to prove a dry run never writes
    $before = File::get("{$pruneAssetsDir}/235.yml");

    pruneRunCommand(['--dry-run' => true])
        ->expectsOutputToContain('Dry run: would prune 1 orphaned key(s) from 1 logo asset file(s).')
        ->assertExitCode(0);

    // The file is untouched and still carries the orphaned key
    expect(File::get("{$pruneAssetsDir}/235.yml"))->toBe($before)
        ->and(pruneReadAssetData($pruneAssetsDir, 235))->toHaveKey('caption');
});

it('preserves unrelated meta keys and top-level fields when pruning', function () use (&$pruneAssetsDir): void {
    /** @var string $pruneAssetsDir */

    // A logo asset with orphaned + real keys
    pruneWriteAssetYaml($pruneAssetsDir, 247, 'logos', [
        'orientation' => 'horizontal',
        'uams_logo_query' => true,
        'exempt_from_alt' => true,
    ]);

    pruneRunCommand()->assertExitCode(0);

    // Re-parse the rewritten file in full
    /** @var array<string, mixed> $data */
    $data = Yaml::parseFile("{$pruneAssetsDir}/247.yml");
    /** @var string $metaJson */
    $metaJson = $data['meta'];
    /** @var array<string, mixed> $meta */
    $meta = json_decode($metaJson, true);

    // Top-level file keys are preserved
    expect($data['container'])->toBe('logos')
        ->and($data['basename'])->toBe('logo-247.svg')
        ->and($data['created_at'])->toBe('2025-02-06T13:58:19+00:00');

    // Non-data meta keys survive the rewrite
    expect($meta['size'])->toBe(1234)
        ->and($meta['mime_type'])->toBe('image/svg+xml');

    // The orphaned key is gone; the real data keys survive
    expect($meta['data'])->toBe([
        'orientation' => 'horizontal',
        'uams_logo_query' => true,
    ]);
});

it('reports a clean sweep when no logo files carry orphaned keys', function () use (&$pruneAssetsDir): void {
    /** @var string $pruneAssetsDir */

    // A mix of clean logo data and legitimate image data — nothing to prune
    pruneWriteAssetYaml($pruneAssetsDir, 219, 'logos', ['orientation' => 'horizontal']);
    pruneWriteAssetYaml($pruneAssetsDir, 10, 'images', ['alt' => 'real']);

    pruneRunCommand()
        ->expectsOutputToContain('Pruned 0 orphaned key(s) from 0 logo asset file(s).')
        ->doesntExpectOutputToContain('Run `php artisan assets:sync-from-yaml`')
        ->assertExitCode(0);
});

it('prints the cache-rebuild reminder only after a real change', function () use (&$pruneAssetsDir): void {
    /** @var string $pruneAssetsDir */

    // A single dirty logo file: the post-change reminder must fire even for one file
    pruneWriteAssetYaml($pruneAssetsDir, 219, 'logos', ['orientation' => 'horizontal', 'alt' => 'x']);

    pruneRunCommand()
        ->expectsOutputToContain('Run `php artisan assets:sync-from-yaml`')
        ->assertExitCode(0);
});

it('names a malformed file and exits FAILURE without stopping the sweep', function () use (&$pruneAssetsDir): void {
    /** @var string $pruneAssetsDir */

    // A valid dirty logo file plus an unparseable neighbor (unclosed flow sequence)
    pruneWriteAssetYaml($pruneAssetsDir, 219, 'logos', ['orientation' => 'horizontal', 'caption' => 'x']);
    File::put("{$pruneAssetsDir}/bad.yml", "container: [logos\n");

    // The malformed file is named with its reason and the run exits FAILURE,
    // but the valid logo file is still pruned (the sweep continues past the error)
    pruneRunCommand()
        ->expectsOutputToContain('Pruned 1 orphaned key(s) from 1 logo asset file(s).')
        ->expectsOutputToContain('could not be processed')
        ->expectsOutputToContain('bad.yml')
        ->assertExitCode(1);

    // The valid logo file was cleaned despite the malformed neighbor
    expect(pruneReadAssetData($pruneAssetsDir, 219))->toBe(['orientation' => 'horizontal']);
});

it('names a file that does not parse to an array and exits FAILURE', function () use (&$pruneAssetsDir): void {
    /** @var string $pruneAssetsDir */

    // A scalar YAML document parses to a string, not an array
    File::put("{$pruneAssetsDir}/scalar.yml", "just a bare string\n");

    pruneRunCommand()
        ->expectsOutputToContain('did not parse to an array')
        ->expectsOutputToContain('scalar.yml')
        ->assertExitCode(1);
});

it('reports nothing to sweep when the directory has no files', function (): void {
    // The temp source directory exists but is empty
    pruneRunCommand()
        ->expectsOutputToContain('No asset metadata files found')
        // The empty-dir branch returns early — it must NOT also print the sweep summary
        ->doesntExpectOutputToContain('Pruned 0 orphaned')
        ->assertExitCode(0);
});

it('processes every file even when earlier files are skipped or fail', function () use (&$pruneAssetsDir): void {
    /** @var string $pruneAssetsDir */

    // Files are swept in glob (alphabetical) order. Place a skip/fail of each kind
    // BEFORE the dirty logo so a sweep that `break`s on any of them would never reach
    // (and never prune) the logo — pinning that the sweep continues past every skip.
    pruneWriteAssetYaml($pruneAssetsDir, 10, 'images', ['alt' => 'real']);          // non-logo → skipped
    pruneWriteAssetYaml($pruneAssetsDir, 20, 'logos', ['orientation' => 'clean']);  // clean logo → skipped
    File::put("{$pruneAssetsDir}/30.yml", "just a bare string\n");                  // non-array → failure, continue
    pruneWriteAssetYaml($pruneAssetsDir, 40, 'logos', [                             // dirty logo → must still be pruned
        'orientation' => 'horizontal',
        'alt' => 'x',
        'caption' => 'y',
        'exempt_from_alt' => true,
    ]);

    pruneRunCommand()
        // Only the dirty logo is pruned, and it is reached despite three earlier skips/failures
        ->expectsOutputToContain('Pruned 3 orphaned key(s) from 1 logo asset file(s).')
        // The non-array file is named as a failure and the run exits FAILURE
        ->expectsOutputToContain('30.yml')
        ->expectsOutputToContain('did not parse to an array')
        ->assertExitCode(1);

    // The dirty logo WAS pruned (the sweep did not halt at any earlier file)
    expect(pruneReadAssetData($pruneAssetsDir, 40))->toBe(['orientation' => 'horizontal'])
        // The non-logo image and the clean logo are untouched
        ->and(pruneReadAssetData($pruneAssetsDir, 10))->toBe(['alt' => 'real'])
        ->and(pruneReadAssetData($pruneAssetsDir, 20))->toBe(['orientation' => 'clean']);
});

it('counts every dirty file on a dry run', function () use (&$pruneAssetsDir): void {
    /** @var string $pruneAssetsDir */

    // Two dirty logo files: a dry run must report BOTH (a sweep that stopped after the
    // first would report "from 1"), and must write neither.
    pruneWriteAssetYaml($pruneAssetsDir, 10, 'logos', ['orientation' => 'horizontal', 'caption' => 'one']);
    pruneWriteAssetYaml($pruneAssetsDir, 20, 'logos', ['orientation' => 'vertical', 'alt' => 'two']);

    $before10 = File::get("{$pruneAssetsDir}/10.yml");
    $before20 = File::get("{$pruneAssetsDir}/20.yml");

    pruneRunCommand(['--dry-run' => true])
        ->expectsOutputToContain('Dry run: would prune 2 orphaned key(s) from 2 logo asset file(s).')
        ->assertExitCode(0);

    // Neither file was written, and both still carry their orphaned keys
    expect(File::get("{$pruneAssetsDir}/10.yml"))->toBe($before10)
        ->and(File::get("{$pruneAssetsDir}/20.yml"))->toBe($before20)
        ->and(pruneReadAssetData($pruneAssetsDir, 10))->toHaveKey('caption')
        ->and(pruneReadAssetData($pruneAssetsDir, 20))->toHaveKey('alt');
});
