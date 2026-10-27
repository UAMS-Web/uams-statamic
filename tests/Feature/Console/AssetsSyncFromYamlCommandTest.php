<?php

declare(strict_types=1);

use App\Models\Asset;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Testing\PendingCommand;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

use function Pest\Laravel\artisan;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

uses(TestCase::class);

/**
 * Feature tests for the asset metadata YAML sync Artisan command.
 *
 * Exercises the truncate-then-load contract: after a run the `assets_meta`
 * cache mirrors `content/models/assets/*.yml` exactly, regardless of id
 * reassignments, deletions, or basename changes since the last sync.
 *
 * Mutation (UAMS-Web/uams-statamic-archive#864): the truncate-then-load, skip-and-continue, and partial-mirror
 * summary paths are killed (the `continue`-not-`break` behavior is pinned by the
 * later-sorting-valid-file and multi-skip tests). The one accepted survivor is
 * cosmetic — the leading `newLine()`.
 *
 * The `finally` flag restore was on that list too, as a reset with "no observable
 * behavior to assert". It is observable now, and it was a real defect rather than
 * a cosmetic one: the command ASSIGNED `false` instead of restoring what it found,
 * so invoking it from a test re-enabled the dual-write into the live `content/`
 * submodule for the rest of that test (UAMS-Web/uams-statamic-archive#1919). It is asserted in
 * `AssetMetadataYamlSyncIsolationTest`, which is where the suppression invariant
 * lives; the case is a poor fit here, since every test in this file has already
 * repointed the YAML directory away from the submodule the flag protects.
 *
 * @command  php artisan test --compact tests/Feature/Console/AssetsSyncFromYamlCommandTest.php
 */

/**
 * Absolute path to the YAML asset metadata directory the command reads.
 *
 * @var string|null
 */
$assetsSyncAssetsDir = null;

/**
 * Isolated temp tree holding the YAML source directory for one test.
 *
 * @var string|null
 */
$assetsSyncTempDir = null;

/**
 * Run the sync command and return its PendingCommand for fluent assertions.
 *
 * `Pest\Laravel\artisan()` is typed `PendingCommand|int`; with console-output
 * mocking on (the default), it always returns a PendingCommand. The returned
 * value MUST be chained on directly and never stored in a caller-held variable:
 * a PendingCommand executes the command in its destructor, so keeping a
 * reference alive defers the run past the database assertions and the cache
 * would still hold the pre-sync rows. Returning it (unstored at the call site)
 * lets it destruct synchronously at the end of the chained statement.
 *
 * @param  string  $command  The Artisan command signature to invoke
 * @param  array<string, mixed>  $parameters  Command arguments and options
 */
function assetsSyncRunCommand(string $command, array $parameters = []): PendingCommand
{
    // Return it unstored so the caller chains and it destructs in-statement.
    // pendingArtisan() resolves artisan()'s PendingCommand|int union.
    return pendingArtisan($command, $parameters);
}

/**
 * Insert a cache row directly, bypassing model events (no YAML dual-write).
 *
 * @param  int  $id  Primary key (mirrors the YAML filename)
 * @param  string  $container  Asset container handle
 * @param  string  $folder  Folder within the container
 * @param  string  $basename  File basename within the folder
 */
function assetsSyncSeedAssetRow(
    int $id,
    string $container,
    string $folder,
    string $basename
): void {
    // Insert via the query builder so saved/deleted YAML hooks never fire
    Asset::query()->insert([
        'id' => $id,
        'container' => $container,
        'folder' => $folder,
        'basename' => $basename,
        'filename' => $basename,
        'extension' => 'jpg',
        'path' => trim("{$folder}/{$basename}", '/'),
        'meta' => json_encode(['data' => []]),
    ]);
}

/**
 * Write a well-formed asset metadata YAML file matching the on-disk format.
 *
 * @param  string  $assetsDir  Absolute path to the temp-rooted assets directory
 * @param  int  $id  Primary key (used as the filename)
 * @param  string  $container  Asset container handle
 * @param  string  $folder  Folder within the container
 * @param  string  $basename  File basename within the folder
 */
function assetsSyncWriteAssetYaml(
    string $assetsDir,
    int $id,
    string $container,
    string $folder,
    string $basename
): void {
    // Build the file body matching App\Models\Asset::writeMetadataToYaml
    $data = [
        'id' => $id,
        'container' => $container,
        'folder' => $folder,
        'basename' => $basename,
        'filename' => $basename,
        'extension' => 'jpg',
        'path' => trim("{$folder}/{$basename}", '/'),
        'meta' => json_encode(['data' => []]),
        'created_at' => null,
        'updated_at' => null,
    ];

    // Persist the YAML file the command will read back
    File::put(
        "{$assetsDir}/{$id}.yml",
        Yaml::dump($data, 4, 2)
    );
}

/**
 * Set up a clean cache table and an isolated, empty YAML source directory.
 */
beforeEach(function () use (&$assetsSyncAssetsDir, &$assetsSyncTempDir): void {
    // Point the command's YAML source at an isolated temp dir so it never reads
    // or truncates against the real directory (~400 live asset YAML files).
    // A config override rather than `App::setBasePath()` (UAMS-Web/uams-statamic-archive#1916): the path is
    // configuration now, so redirecting it no longer means mutating a
    // process-wide root that every other `base_path()` caller also resolves.
    $assetsSyncTempDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'asset-sync-test-'.uniqid();
    $assetsSyncAssetsDir = $assetsSyncTempDir.DIRECTORY_SEPARATOR.'models'.DIRECTORY_SEPARATOR.'assets';
    Config::set('asset_metadata.yaml_directory', $assetsSyncAssetsDir);

    // Start every test from a clean, empty source directory
    File::ensureDirectoryExists($assetsSyncAssetsDir);

    // Start every test from an empty cache so assertions are deterministic
    Asset::query()->delete();
});

/**
 * Remove the temp tree. The config override dies with the per-test app.
 */
afterEach(function () use (&$assetsSyncTempDir): void {
    // Remove the isolated temp directory this test created
    if ($assetsSyncTempDir !== null) {
        File::deleteDirectory($assetsSyncTempDir);
    }
});

it('prunes orphan rows with no yaml file', function () use (&$assetsSyncAssetsDir): void {
    /** @var string $assetsSyncAssetsDir */

    // Seed three cache rows, one of which (id 3) has no backing YAML file
    assetsSyncSeedAssetRow(1, 'main', '', 'one.jpg');
    assetsSyncSeedAssetRow(2, 'main', '', 'two.jpg');
    assetsSyncSeedAssetRow(3, 'main', '', 'orphan.jpg');

    // Provide YAML only for ids 1 and 2; id 3 is intentionally absent
    assetsSyncWriteAssetYaml($assetsSyncAssetsDir, 1, 'main', '', 'one.jpg');
    assetsSyncWriteAssetYaml($assetsSyncAssetsDir, 2, 'main', '', 'two.jpg');

    // Run the sync and expect a clean success
    assetsSyncRunCommand('assets:sync-from-yaml')
        ->expectsOutputToContain('Synced 2 asset(s) from YAML.')
        ->assertExitCode(0);

    // The orphan row is gone and exactly one row remains per YAML file
    assertDatabaseCount('assets_meta', 2, 'asset_metadata');
    assertDatabaseMissing('assets_meta', ['id' => 3], 'asset_metadata');
    assertDatabaseHas('assets_meta', ['id' => 1, 'basename' => 'one.jpg'], 'asset_metadata');
    assertDatabaseHas('assets_meta', ['id' => 2, 'basename' => 'two.jpg'], 'asset_metadata');
});

it('handles reassigned id without unique violation', function () use (&$assetsSyncAssetsDir): void {
    /** @var string $assetsSyncAssetsDir */

    // Seed a stale row at id 3531 describing a now file-less asset
    assetsSyncSeedAssetRow(3531, 'main', '', 'old-headshot.jpg');

    // The YAML at 3531 now describes an entirely different (radiology) asset
    assetsSyncWriteAssetYaml($assetsSyncAssetsDir, 3531, 'main', 'radiology', 'scan.jpg');

    // Run the sync; the old code would throw SQLSTATE[23000] UNIQUE here
    assetsSyncRunCommand('assets:sync-from-yaml')
        ->expectsOutputToContain('Synced 1 asset(s) from YAML.')
        ->assertExitCode(0);

    // The cache holds exactly the YAML's data at the reassigned id
    assertDatabaseCount('assets_meta', 1, 'asset_metadata');
    assertDatabaseHas(
        'assets_meta',
        [
            'id' => 3531,
            'folder' => 'radiology',
            'basename' => 'scan.jpg',
        ],
        'asset_metadata'
    );
    assertDatabaseMissing(
        'assets_meta',
        ['id' => 3531, 'basename' => 'old-headshot.jpg'],
        'asset_metadata'
    );
});

it('skips malformed file and continues', function () use (&$assetsSyncAssetsDir): void {
    /** @var string $assetsSyncAssetsDir */

    // One valid file and one unparseable file (unclosed flow sequence)
    assetsSyncWriteAssetYaml($assetsSyncAssetsDir, 1, 'main', '', 'one.jpg');
    File::put("{$assetsSyncAssetsDir}/2.yml", "container: [main\n");

    // The run reports one success, names the offending file, and exits FAILURE
    assetsSyncRunCommand('assets:sync-from-yaml')
        ->expectsOutputToContain('Synced 1 asset(s) from YAML.')
        ->expectsOutputToContain('Partial sync: 1 file(s) skipped')
        ->expectsOutputToContain('INCOMPLETE')
        ->expectsOutputToContain('2.yml')
        ->expectsOutputToContain('Re-run')
        ->assertExitCode(1);

    // The well-formed file still synced despite the malformed neighbor
    assertDatabaseCount('assets_meta', 1, 'asset_metadata');
    assertDatabaseHas('assets_meta', ['id' => 1, 'basename' => 'one.jpg'], 'asset_metadata');
});

it('names a non-numeric filename and reports the partial mirror (#864)', function () use (&$assetsSyncAssetsDir): void {
    /** @var string $assetsSyncAssetsDir */

    // Two valid files plus a non-numeric filename (silently skipped before UAMS-Web/uams-statamic-archive#864)
    assetsSyncWriteAssetYaml($assetsSyncAssetsDir, 1, 'main', '', 'one.jpg');
    assetsSyncWriteAssetYaml($assetsSyncAssetsDir, 2, 'main', '', 'two.jpg');
    File::put("{$assetsSyncAssetsDir}/not-an-id.yml", "container: main\n");

    // The non-numeric file is named with its reason; the run exits FAILURE
    assetsSyncRunCommand('assets:sync-from-yaml')
        ->expectsOutputToContain('Synced 2 asset(s) from YAML.')
        ->expectsOutputToContain('Partial sync: 1 file(s) skipped')
        ->expectsOutputToContain('not-an-id.yml')
        ->expectsOutputToContain('not a numeric asset id')
        ->assertExitCode(1);

    // Valid siblings still load fully despite the bad neighbor, with all fields intact
    assertDatabaseCount('assets_meta', 2, 'asset_metadata');
    assertDatabaseHas(
        'assets_meta',
        ['id' => 1, 'container' => 'main', 'filename' => 'one.jpg', 'extension' => 'jpg', 'path' => 'one.jpg'],
        'asset_metadata'
    );
    assertDatabaseHas('assets_meta', ['id' => 2, 'basename' => 'two.jpg'], 'asset_metadata');
});

it('names a YAML file that does not parse to an array (#864)', function () use (&$assetsSyncAssetsDir): void {
    /** @var string $assetsSyncAssetsDir */

    // One valid file plus a scalar YAML document (parses to a string, not an array)
    assetsSyncWriteAssetYaml($assetsSyncAssetsDir, 1, 'main', '', 'one.jpg');
    File::put("{$assetsSyncAssetsDir}/2.yml", "just a bare string\n");

    // The non-array file is named with its reason; the run exits FAILURE
    assetsSyncRunCommand('assets:sync-from-yaml')
        ->expectsOutputToContain('Synced 1 asset(s) from YAML.')
        ->expectsOutputToContain('did not parse to an array')
        ->expectsOutputToContain('2.yml')
        ->assertExitCode(1);

    // The valid file still synced
    assertDatabaseCount('assets_meta', 1, 'asset_metadata');
    assertDatabaseHas('assets_meta', ['id' => 1, 'basename' => 'one.jpg'], 'asset_metadata');
});

it('reports a clean success when no files are skipped (#864)', function () use (&$assetsSyncAssetsDir): void {
    /** @var string $assetsSyncAssetsDir */

    // Two valid files only — no partial-mirror banner should appear
    assetsSyncWriteAssetYaml($assetsSyncAssetsDir, 1, 'main', '', 'one.jpg');
    assetsSyncWriteAssetYaml($assetsSyncAssetsDir, 2, 'main', '', 'two.jpg');

    assetsSyncRunCommand('assets:sync-from-yaml')
        ->expectsOutputToContain('Synced 2 asset(s) from YAML.')
        ->doesntExpectOutputToContain('INCOMPLETE')
        ->assertExitCode(0);

    assertDatabaseCount('assets_meta', 2, 'asset_metadata');
});

it('loads a valid file that sorts AFTER a skipped file (#864)', function () use (&$assetsSyncAssetsDir): void {
    /** @var string $assetsSyncAssetsDir */

    // glob() returns *.yml in lexical order, so the skipped non-array `1.yml`
    // is processed BEFORE the valid `2.yml`. The loop must `continue` past the
    // skip (not `break`), or the later valid sibling would be silently dropped.
    File::put("{$assetsSyncAssetsDir}/1.yml", "just a bare string\n");
    assetsSyncWriteAssetYaml($assetsSyncAssetsDir, 2, 'main', '', 'two.jpg');

    assetsSyncRunCommand('assets:sync-from-yaml')
        ->expectsOutputToContain('Synced 1 asset(s) from YAML.')
        ->expectsOutputToContain('1.yml')
        ->assertExitCode(1);

    // The valid `2.yml`, which sorts after the skipped `1.yml`, still loaded.
    assertDatabaseCount('assets_meta', 1, 'asset_metadata');
    assertDatabaseHas('assets_meta', ['id' => 2, 'basename' => 'two.jpg'], 'asset_metadata');
});

it('names every skipped file when more than one is bad (#864)', function () use (&$assetsSyncAssetsDir): void {
    /** @var string $assetsSyncAssetsDir */

    // Two non-numeric filenames are both skipped; both must be named so the
    // loop cannot stop at the first skip (`continue`, not `break`).
    File::put("{$assetsSyncAssetsDir}/aaa.yml", "container: main\n");
    File::put("{$assetsSyncAssetsDir}/bbb.yml", "container: main\n");

    assetsSyncRunCommand('assets:sync-from-yaml')
        ->expectsOutputToContain('Partial sync: 2 file(s) skipped')
        ->expectsOutputToContain('aaa.yml')
        ->expectsOutputToContain('bbb.yml')
        ->assertExitCode(1);

    // Neither bad file produced a row.
    assertDatabaseCount('assets_meta', 0, 'asset_metadata');
});

it('dry run reports count without writing', function () use (&$assetsSyncAssetsDir): void {
    /** @var string $assetsSyncAssetsDir */

    // Seed a stale row that must survive a dry run untouched
    assetsSyncSeedAssetRow(99, 'main', '', 'stale.jpg');

    // Provide two YAML files that would be synced on a real run
    assetsSyncWriteAssetYaml($assetsSyncAssetsDir, 1, 'main', '', 'one.jpg');
    assetsSyncWriteAssetYaml($assetsSyncAssetsDir, 2, 'main', '', 'two.jpg');

    // Run with --dry-run and expect only the planned count
    assetsSyncRunCommand('assets:sync-from-yaml', ['--dry-run' => true])
        ->expectsOutputToContain('Dry run: would sync 2 file(s).')
        ->assertExitCode(0);

    // The cache is unchanged: stale row stays, YAML files were not loaded
    assertDatabaseCount('assets_meta', 1, 'asset_metadata');
    assertDatabaseHas('assets_meta', ['id' => 99, 'basename' => 'stale.jpg'], 'asset_metadata');
});
