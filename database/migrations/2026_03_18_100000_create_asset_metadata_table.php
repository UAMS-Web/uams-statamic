<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Migration that creates the assets_meta table on the asset_metadata SQLite
 * connection for local asset metadata (YAML dual-write).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the assets_meta table on the dedicated asset_metadata SQLite
     * connection used for local asset metadata (YAML dual-write).
     */
    public function up(): void
    {
        // ---
        // For file-based SQLite, ensure the database file exists (Laravel does
        // not create it automatically). Skip when using :memory: (e.g. tests).
        // ---

        // Resolve configured database path for the asset_metadata connection
        $dbPath = config('database.connections.asset_metadata.database');

        // Check if storage uses a file rather than in-memory SQLite
        if ($dbPath !== ':memory:') {
            // Ensure parent directory exists for the SQLite file
            File::ensureDirectoryExists(
                dirname($dbPath)
            );

            // Create empty file if missing so the connection can open it
            if (! file_exists($dbPath)) {
                touch($dbPath);
            }
        }

        // Create assets_meta table on the asset_metadata connection
        Schema::connection('asset_metadata')->create(
            'assets_meta',
            function (Blueprint $table) {
                // ---
                // Columns match Statamic Eloquent driver asset schema
                // ---

                // Add id column
                $table->id();

                // Add container column
                $table->string('container')->index();

                // Add folder column
                $table->string('folder')->index();

                // Add basename column
                $table->string('basename')->index();

                // Add filename column
                $table->string('filename')->index();

                // Add extension column
                $table->char('extension', 10)->index();

                // Add path column
                $table->string('path')->index();

                // Add meta column
                $table->json('meta')->nullable();

                // Add timestamps
                $table->timestamps();

                // ---
                // One metadata row per container + folder + basename
                // ---

                // Add unique index on container, folder, and basename
                $table->unique(
                    [
                        'container',
                        'folder',
                        'basename',
                    ]
                );
            }
        );
    }

    /**
     * Reverse the migrations.
     *
     * Drops the table and removes the dedicated SQLite file that up() created, so
     * a rollback leaves no orphan. The file is removed only when it is the
     * connection's own file — never :memory:, and never the app's default
     * database (guards a misconfiguration that repoints asset_metadata at the
     * shared DB, which must not be deleted).
     */
    public function down(): void
    {
        // Drop assets_meta table from the asset_metadata connection
        Schema::connection('asset_metadata')->dropIfExists(
            'assets_meta'
        );

        // Resolve the configured SQLite file for the asset_metadata connection
        $dbPath = config('database.connections.asset_metadata.database');

        // Resolve the app's default database file to guard against deleting it
        $defaultConnection = config('database.default');
        $defaultPath = config('database.connections.'.$defaultConnection.'.database');

        // Canonicalize both paths before comparing: two different spellings of the
        // same file (relative vs absolute, a `..` segment, or a symlink) are NOT
        // string-equal, so a raw `!==` would let down() delete the shared default
        // database. realpath() resolves them to one canonical path; fall back to the
        // raw value only when the path does not resolve (e.g. it does not exist).
        $resolvedDbPath = \is_string($dbPath) ? (realpath($dbPath) ?: $dbPath) : $dbPath;
        $resolvedDefaultPath = \is_string($defaultPath) ? (realpath($defaultPath) ?: $defaultPath) : $defaultPath;

        // Remove the dedicated SQLite file only when it is safe to do so
        if (
            \is_string($dbPath)
            && $dbPath !== ':memory:'
            && $resolvedDbPath !== $resolvedDefaultPath
            && File::exists($dbPath)
        ) {
            // Close the asset_metadata connection before deleting its file. The
            // dropIfExists() above opened a PDO handle to the SQLite file, and on
            // Windows an open handle blocks File::delete (POSIX unlinks an open
            // file regardless); without this the rollback leaves the file behind.
            DB::purge('asset_metadata');

            File::delete($dbPath);
        }
    }
};
