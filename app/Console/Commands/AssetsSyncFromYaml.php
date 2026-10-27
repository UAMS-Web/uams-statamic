<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Asset;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Symfony\Component\Yaml\Yaml;

/**
 * Sync asset metadata from YAML files into the asset_metadata SQLite database.
 *
 * Run after deploy (e.g., after git pull) so content/models/assets/*.yml is the
 * source of truth and the local SQLite cache is repopulated.
 *
 * @command php artisan assets:sync-from-yaml
 */
#[Description('Sync asset metadata from content/models/assets/*.yml into the database')]
#[Signature('assets:sync-from-yaml
                            {--dry-run : List files that would be synced without writing}')]
class AssetsSyncFromYaml extends Command
{
    /**
     * Execute the console command.
     *
     * @return int Command exit code: SUCCESS if no errors, FAILURE if any file skipped/failed
     */
    public function handle(): int
    {
        // Resolve path to asset metadata YAML directory
        $dir = Config::string('asset_metadata.yaml_directory');

        // ---
        // Exit early if directory does not exist
        // ---

        // Check if directory exists
        if (! is_dir($dir)) {
            // Warn the user that the asset metadata directory does not exist
            $this->warn(sprintf('Directory %s does not exist.', $dir));

            // Return success to indicate that no files to sync
            return self::SUCCESS;
        }

        // Find all .yml files in the assets metadata directory
        $files = glob($dir.'/*.yml');

        // ---
        // Exit early if no YAML files to sync
        // ---

        // Check if no YAML files to sync
        if (
            $files === false
            || $files === []
        ) {
            // Inform the user that no YAML files were found
            $this->info(sprintf('No YAML files found in %s.', $dir));

            // Return success to indicate that no files to sync
            return self::SUCCESS;
        }

        // Determine if user requested dry run (no DB writes)
        $dryRun = (bool) $this->option('dry-run');

        // ---
        // For dry run, report count and exit without syncing
        // ---

        // Check if dry run is requested
        if ($dryRun) {
            // Inform the user how many files would be synced without writing
            $this->info('Dry run: would sync '.\count($files).' file(s).');

            // Return success to indicate that no files were synced
            return self::SUCCESS;
        }

        // Count of assets successfully synced
        $synced = 0;

        // Files that could not be synced, each with a human-readable reason
        /** @var list<array{path: string, reason: string}> $failures */
        $failures = [];

        // Remember the caller's setting so the finally below RESTORES it rather
        // than asserting a value. In production this is always false and the two
        // are identical; under test it is armed by `Tests\TestCase` to keep probe
        // assets out of content/models/assets/ (UAMS-Web/uams-statamic-archive#1919), and hard-setting false
        // here would silently re-enable the dual-write for the rest of any test
        // that runs this command.
        $previousSkipYamlSync = Asset::$skipYamlSync;

        // Wrap sync loop so we always restore the YAML write setting in finally
        try {
            // Disable model YAML write so we do not rewrite files during sync
            Asset::$skipYamlSync = true;

            // ---
            // Truncate-then-load on the asset_metadata connection. Wrapping the
            // delete + all inserts in one transaction makes the cache mirror the
            // YAML source exactly: reassigned ids, renamed basenames, and deleted
            // files all resolve as clean inserts against an empty table, so a
            // renumbered or pruned source can never raise a UNIQUE violation or
            // leave orphan rows. Atomicity also means concurrent readers never
            // observe a partially-populated (or empty) cache.
            // ---
            (new Asset)->getConnection()->transaction(
                function () use ($files, &$synced, &$failures): void {
                    // Clear every existing row so each YAML file becomes a fresh
                    // insert with no prior state to collide with. Mass delete does
                    // not fire model events, so no YAML files are removed from disk.
                    Asset::query()->delete();

                    // Iterate over each YAML file to load into the empty table
                    foreach ($files as $path) {
                        // Get filename without extension (e.g., 1.yml -> 1)
                        $basename = basename(
                            $path,
                            '.yml'
                        );

                        // ---
                        // Treat non-numeric filenames as errors: the filename is the
                        // asset id, so a non-numeric name is not a valid source file
                        // ---

                        // Check if filename is not numeric
                        if (! ctype_digit($basename)) {
                            // Record the unusable file without aborting the run
                            $failures[] = [
                                'path' => $path,
                                'reason' => 'filename is not a numeric asset id',
                            ];

                            // Skip file if not numeric
                            continue;
                        }

                        // Use filename as asset metadata primary key
                        $id = (int) $basename;

                        // Wrap parse and insert so one bad file does not stop the loop.
                        // Parse failures and non-array content never touch the DB, and a
                        // per-statement constraint failure rolls back only that insert
                        // (SQLite default), so the surrounding transaction stays healthy.
                        try {
                            // Parse YAML file into array
                            $data = Yaml::parseFile($path);

                            // ---
                            // Skip file if content is not a valid array
                            // ---

                            // Check if content is not a valid array
                            if (! \is_array($data)) {
                                // Record the file whose YAML did not parse to an array
                                $failures[] = [
                                    'path' => $path,
                                    'reason' => 'YAML did not parse to an array',
                                ];

                                // Skip file if not a valid array
                                continue;
                            }

                            // Extract meta; may be JSON string in YAML
                            $meta = $data['meta'] ?? null;

                            // ---
                            // Decode meta from JSON string when necessary
                            // ---

                            // Check if meta is a string
                            if (\is_string($meta)) {
                                // Decode meta from JSON string
                                $meta = json_decode(
                                    $meta,
                                    true
                                );
                            }

                            // Check if meta is not a valid array
                            if (! \is_array($meta)) {
                                // Normalize meta to an empty array when YAML omits or decodes to null
                                $meta = [];
                            }

                            // ---
                            // meta.last_modified is filled by App\Models\Asset::saving (see
                            // ensureMetaLastModified) on create; keep meta a valid array here
                            // ---

                            // Insert the YAML file as a plain create against the empty table
                            Asset::query()->create([
                                'id' => $id,
                                'container' => $data['container'] ?? '',
                                'folder' => $data['folder'] ?? '',
                                'basename' => $data['basename'] ?? '',
                                'filename' => $data['filename'] ?? '',
                                'extension' => $data['extension'] ?? '',
                                'path' => $data['path'] ?? '',
                                'meta' => $meta,
                                'created_at' => $data['created_at'] ?? null,
                                'updated_at' => $data['updated_at'] ?? null,
                            ]);

                            // Increment synced count
                            $synced++;
                        } catch (\Throwable $e) {
                            // Record the parse/insert failure with its message
                            $failures[] = [
                                'path' => $path,
                                'reason' => $e->getMessage(),
                            ];
                        }
                    }
                }
            );
        } finally {
            // Restore the caller's setting so normal asset saves resume writing
            Asset::$skipYamlSync = $previousSkipYamlSync;
        }

        // Inform the user how many assets were synced
        $this->info(sprintf('Synced %d asset(s) from YAML.', $synced));

        // A clean run with no skipped files mirrors the YAML source exactly
        if ($failures === []) {
            return self::SUCCESS;
        }

        // Make the partial mirror loud: state plainly that the cache is incomplete
        $this->newLine();
        $this->error(sprintf('Partial sync: %d file(s) skipped.', \count($failures)));
        $this->error('The asset_metadata mirror is INCOMPLETE until the file(s) below are fixed:');

        // Name every offending file and its reason on separate lines
        foreach ($failures as $failure) {
            $this->error(sprintf('  %s', $failure['path']));
            $this->error(sprintf('      reason: %s', $failure['reason']));
        }

        // Tell the operator exactly how to make the mirror whole again
        $this->error('Re-run `php artisan assets:sync-from-yaml` after fixing the file(s) above.');

        // Non-zero exit so deploy pipelines and operators cannot miss the gap
        return self::FAILURE;
    }
}
