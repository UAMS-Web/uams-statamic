<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Symfony\Component\Yaml\Yaml;

/**
 * Strip orphaned `alt` / `caption` / `exempt_from_alt` keys from logo asset metadata.
 *
 * UAMS-Web/uams-statamic-archive#49 removed those fields from the `logos` / `logos_unrestricted` blueprints (the
 * logo `alt` is computed via TitleAltResolver / AltFormatter) and UAMS-Web/uams-statamic-archive#831 stopped the
 * upload metadata mapper from writing them. This command is the scripted sweep UAMS-Web/uams-statamic-archive#1084
 * asked for: it audits the canonical YAML source (content/models/assets/*.yml) and
 * removes those keys from any asset in a logo container, leaving image / portrait
 * data (where the keys are still real fields) untouched.
 *
 * The sweep is idempotent — a clean tree changes nothing — so it is safe to re-run
 * if a future import or hand edit ever reintroduces the orphaned keys. It rewrites
 * only the YAML source of truth; run `php artisan assets:sync-from-yaml` afterwards
 * to rebuild the SQLite cache from the cleaned files.
 *
 * @command php artisan assets:prune-logo-metadata
 */
#[Description('Remove orphaned alt/caption/exempt_from_alt keys from logo asset metadata YAML')]
#[Signature('assets:prune-logo-metadata
                            {--dry-run : List files that would change without writing}')]
class PruneLogoAssetMetadata extends Command
{
    /**
     * Asset containers whose blueprints no longer define alt / caption /
     * exempt_from_alt (mirrors AssetUploadMetadataMapper's exclusion of these
     * from its image-style containers).
     *
     * @var list<string>
     */
    private const array LOGO_CONTAINERS = [
        'logos',
        'logos_unrestricted',
    ];

    /**
     * Field-data keys orphaned on logo assets after UAMS-Web/uams-statamic-archive#49 removed their blueprint fields.
     *
     * @var list<string>
     */
    private const array ORPHANED_KEYS = [
        'alt',
        'caption',
        'exempt_from_alt',
    ];

    /**
     * Execute the console command.
     *
     * @return int Command exit code: SUCCESS on a clean sweep, FAILURE if any file could not be processed
     */
    public function handle(): int
    {
        // Resolve the canonical asset metadata YAML directory
        $dir = Config::string('asset_metadata.yaml_directory');

        // ---
        // Exit early if directory does not exist
        // ---

        // Check if directory exists
        if (! is_dir($dir)) {
            // Warn the user that the asset metadata directory does not exist
            $this->warn(sprintf('Directory %s does not exist.', $dir));

            // Return success to indicate that there is nothing to sweep
            return self::SUCCESS;
        }

        // Find all .yml files in the assets metadata directory
        $files = glob($dir.'/*.yml');

        // ---
        // Exit early if no YAML files to sweep
        // ---

        // Check if no YAML files to sweep
        if (
            $files === false
            || $files === []
        ) {
            // Inform the user that no YAML files were found
            $this->info(sprintf('No asset metadata files found in %s.', $dir));

            // Return success to indicate that there is nothing to sweep
            return self::SUCCESS;
        }

        // Determine if user requested a dry run (no writes)
        $dryRun = (bool) $this->option('dry-run');

        // Count of logo assets whose YAML was (or would be) rewritten
        $changed = 0;

        // Total orphaned keys removed across all rewritten files
        $keysRemoved = 0;

        // Files that could not be processed, each with a human-readable reason
        /** @var list<array{path: string, reason: string}> $failures */
        $failures = [];

        // Iterate over each YAML file looking for orphaned logo metadata
        foreach ($files as $path) {
            // Wrap parse and rewrite so one bad file does not stop the sweep
            try {
                // Parse YAML file into array
                $data = Yaml::parseFile($path);

                // Skip files whose content is not a valid array
                if (! \is_array($data)) {
                    // Record the file whose YAML did not parse to an array
                    $failures[] = [
                        'path' => $path,
                        'reason' => 'YAML did not parse to an array',
                    ];

                    // Skip file if not a valid array
                    continue;
                }

                // Read the container handle to route the sweep
                $container = $data['container'] ?? null;

                // ---
                // Skip assets that are not in a logo container — image / portrait
                // data keeps these keys as real fields and must stay untouched
                // ---

                // Check if container is not a logo container
                if (! \in_array($container, self::LOGO_CONTAINERS, true)) {
                    // Skip non-logo assets
                    continue;
                }

                // Extract meta; stored as a JSON string in the YAML source
                $meta = $data['meta'] ?? null;

                // Decode meta from JSON string when necessary
                if (\is_string($meta)) {
                    // Decode meta from JSON string
                    $meta = json_decode(
                        $meta,
                        true
                    );
                }

                // Skip files whose meta is absent or did not decode to an array
                if (! \is_array($meta)) {
                    // Skip assets with no usable meta
                    continue;
                }

                // Read the field-data slice that holds blueprint values
                $assetData = $meta['data'] ?? null;

                // Skip assets whose data slice is missing or not an array
                if (! \is_array($assetData)) {
                    // Skip assets with no field data to prune
                    continue;
                }

                // Track how many orphaned keys this file carries
                $removedHere = 0;

                // Remove each orphaned key present in the field data
                foreach (self::ORPHANED_KEYS as $key) {
                    // Check if the orphaned key is present
                    if (\array_key_exists($key, $assetData)) {
                        // Drop the orphaned key from the field data
                        unset($assetData[$key]);

                        // Count the removed key
                        $removedHere++;
                    }
                }

                // ---
                // Skip files that already carry no orphaned keys (idempotency)
                // ---

                // Check if this file carried no orphaned keys
                if ($removedHere === 0) {
                    // Nothing to rewrite for a clean file
                    continue;
                }

                // Accumulate the sweep totals
                $changed++;
                $keysRemoved += $removedHere;

                // Report the affected file and the keys it carried
                $this->line(sprintf(
                    '  %s (%d orphaned key%s)',
                    $path,
                    $removedHere,
                    $removedHere === 1 ? '' : 's'
                ));

                // ---
                // On a dry run, report only; never write to disk
                // ---

                // Check if dry run is requested
                if ($dryRun) {
                    // Skip the write on a dry run
                    continue;
                }

                // Fold the pruned field data back into meta
                $meta['data'] = $assetData;

                // Re-encode meta as a JSON string matching the on-disk format
                $data['meta'] = json_encode($meta);

                // Write the cleaned YAML back using the same format as
                // App\Models\Asset::writeMetadataToYaml so untouched files and
                // cleaned files share one canonical serialization
                file_put_contents(
                    $path,
                    Yaml::dump(
                        $data,
                        4,
                        2,
                        Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK
                    )
                );
            } catch (\Throwable $e) {
                // Record the parse/rewrite failure with its message
                $failures[] = [
                    'path' => $path,
                    'reason' => $e->getMessage(),
                ];
            }
        }

        // ---
        // Summarize the sweep outcome
        // ---

        // Check if a dry run was requested
        if ($dryRun) {
            // Report what a real run would change
            $this->info(sprintf(
                'Dry run: would prune %d orphaned key(s) from %d logo asset file(s).',
                $keysRemoved,
                $changed
            ));
        } else {
            // Report what the real run changed
            $this->info(sprintf(
                'Pruned %d orphaned key(s) from %d logo asset file(s).',
                $keysRemoved,
                $changed
            ));

            // Remind the operator to rebuild the SQLite cache when files changed
            if ($changed > 0) {
                // Point at the sync command that mirrors YAML into the cache
                $this->comment('Run `php artisan assets:sync-from-yaml` to rebuild the cache from the cleaned files.');
            }
        }

        // A clean run with no unprocessable files succeeds
        if ($failures === []) {
            return self::SUCCESS;
        }

        // Make unprocessable files loud so the audit is never silently partial
        $this->newLine();
        $this->error(sprintf('%d file(s) could not be processed:', \count($failures)));

        // Name every offending file and its reason on separate lines
        foreach ($failures as $failure) {
            // Print the offending path
            $this->error(sprintf('  %s', $failure['path']));

            // Print the reason it was skipped
            $this->error(sprintf('      reason: %s', $failure['reason']));
        }

        // Non-zero exit so operators cannot miss the gap
        return self::FAILURE;
    }
}
