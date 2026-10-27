<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\Support\ThrowawayRoot;
use Tests\TestCase;

uses(TestCase::class);

/**
 * UAMS-Web/uams-statamic-archive#898: the asset_metadata migration must be self-reversing — up() creates the
 * dedicated SQLite file, so down() must drop the table AND remove that file,
 * without ever deleting :memory: or the app's default database.
 */
const ASSET_META_MIG_FILE = 'framework/testing/asset-meta-migration-test.sqlite';
const ASSET_META_SHARED_FILE = 'framework/testing/asset-meta-shared-db-test.sqlite';

/**
 * Absolute, per-run path for an asset_metadata test sqlite file.
 *
 * Inserting a per-run segment before the extension gives every concurrent test
 * process its own file under storage/framework/testing/, so none can collide on
 * a shared path (one process's afterEach File::delete() removing a file another
 * one's test is mid-use). {@see ThrowawayRoot} supplies the segment.
 *
 * This read `Env::get('TEST_TOKEN')` with an empty fallback until UAMS-Web/uams-statamic-archive#1957, which
 * covered the paratest workers and left every SERIAL run on the plain base name
 * — the same shared-path defect the four Stache/blueprint roots had, in a file
 * the isolation lint is structurally blind to (Rule B looks for a
 * `framework/testing` literal inside the `storage_path()` call, and here it sits
 * in a const above).
 */
function assetMetaPath(string $relative): string
{
    return storage_path(
        preg_replace('/\.sqlite$/', '-'.ThrowawayRoot::segment().'.sqlite', $relative) ?? $relative
    );
}

/**
 * Structural type for the reversible asset_metadata migration under test.
 *
 * The base {@see Migration} declares neither
 * `up()` nor `down()`, so this names the pair the anonymous migration exposes.
 * It is referenced only from `@return` / `@var` annotations — never implemented
 * or instantiated at runtime.
 */
interface AssetMetadataMigration
{
    public function up(): void;

    public function down(): void;
}

/**
 * Load a fresh instance of the asset_metadata migration under test.
 *
 * @return Migration&AssetMetadataMigration
 */
function assetMetadataMigration(): object
{
    /** @var Migration&AssetMetadataMigration $migration */
    $migration = require database_path('migrations/2026_03_18_100000_create_asset_metadata_table.php');

    return $migration;
}

/**
 * Point the asset_metadata connection at a file path and reconnect.
 */
function useAssetMetadataFile(string $path): void
{
    config(['database.connections.asset_metadata.database' => $path]);
    DB::purge('asset_metadata');
}

afterEach(function (): void {
    DB::purge('asset_metadata');
    File::delete(assetMetaPath(ASSET_META_MIG_FILE));
    File::delete(assetMetaPath(ASSET_META_SHARED_FILE));
});

it('creates the assets_meta table and its sqlite file on up (#898)', function (): void {
    $path = assetMetaPath(ASSET_META_MIG_FILE);
    File::delete($path);
    useAssetMetadataFile($path);

    assetMetadataMigration()->up();

    expect(File::exists($path))->toBeTrue()
        ->and(Schema::connection('asset_metadata')->hasTable('assets_meta'))->toBeTrue();
});

it('drops the table and removes the sqlite file on down (#898)', function (): void {
    $path = assetMetaPath(ASSET_META_MIG_FILE);
    File::delete($path);
    useAssetMetadataFile($path);

    $migration = assetMetadataMigration();
    $migration->up();
    expect(File::exists($path))->toBeTrue();

    $migration->down();
    DB::purge('asset_metadata');

    // The orphan file is gone — the migration fully reverses itself.
    expect(File::exists($path))->toBeFalse();
});

it('never deletes the file when asset_metadata resolves to the default database (#898)', function (): void {
    // Guard a misconfiguration that repoints asset_metadata at the shared app DB:
    // down() must not delete the default database file.
    $shared = assetMetaPath(ASSET_META_SHARED_FILE);
    File::put($shared, '');

    /** @var string $default */
    $default = config('database.default');
    config(['database.connections.'.$default.'.database' => $shared]);
    useAssetMetadataFile($shared);

    assetMetadataMigration()->down();

    expect(File::exists($shared))->toBeTrue();
});

it('never deletes the default database via an equivalent-but-differently-spelled path (#898)', function (): void {
    // The same file named two ways (a `..` segment) is not string-equal; the guard
    // must canonicalize so down() still refuses to delete the shared default DB.
    $shared = assetMetaPath(ASSET_META_SHARED_FILE);
    File::put($shared, '');
    $alias = str_replace('/testing/', '/testing/../testing/', $shared);
    expect(realpath($alias))->toBe(realpath($shared));

    /** @var string $default */
    $default = config('database.default');
    config(['database.connections.'.$default.'.database' => $shared]);
    useAssetMetadataFile($alias);

    assetMetadataMigration()->down();

    expect(File::exists($shared))->toBeTrue();
});

it('drops the table on down for an in-memory connection without touching any file (#898)', function (): void {
    // :memory: — up creates the table, down drops it; the file-delete branch is
    // skipped. Pins both dropIfExists and the :memory: guard clause.
    useAssetMetadataFile(':memory:');

    $migration = assetMetadataMigration();
    $migration->up();
    expect(Schema::connection('asset_metadata')->hasTable('assets_meta'))->toBeTrue();

    $migration->down();
    expect(Schema::connection('asset_metadata')->hasTable('assets_meta'))->toBeFalse();
});
