<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\BardContentSanitizer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File as LaravelFile;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Stache\Query\EntryQueryBuilder;

/**
 * Artisan command to sanitize Bard content in existing entries.
 *
 * This command scans all entries for Bard fields containing YAML document
 * separators and automatically escapes them to prevent parsing errors.
 *
 * ## Problem Context
 *
 * When Bard fields contain YAML document separators (`...` or `---`) as text
 * content, Statamic's YAML parser can interpret them as document terminators
 * rather than literal string values, causing parsing errors. This command
 * provides a way to clean up existing problematic content in YAML files.
 *
 * ## How It Works
 *
 * The command processes YAML files directly rather than working with parsed entry
 * data. It uses regex patterns to find unquoted YAML separators in text fields
 * and replaces them with quoted versions to prevent parsing errors.
 *
 * ## Upstream Monitoring
 *
 * This is a workaround for a bug in the interaction between Statamic's YAML
 * parsing and Tiptap's Bard editor. Developers should monitor:
 *   - Statamic CMS updates for YAML parsing improvements
 *   - Tiptap editor updates for Bard field handling
 *   - Symfony YAML component updates for parser behavior changes
 *
 * This command may become unnecessary or require modification if upstream
 * libraries implement proper handling of YAML separators in content fields.
 *
 * @since  1.0.0
 */
#[Description('Sanitize Bard content to prevent YAML parsing issues')]
#[Signature('bard:sanitize
                            {--collection= : Only process entries from a specific collection}
                            {--dry-run : Show what would be changed without making changes}')]
class SanitizeBardContent extends Command
{
    /**
     * Create a new command instance.
     *
     * @param  BardContentSanitizer  $sanitizer  The sanitizer service for dependency injection
     */
    public function __construct(
        private readonly BardContentSanitizer $sanitizer
    ) {
        // Initialize parent command class
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * Main entry point for the command. Processes entries from all collections
     * or a specific collection, scanning for YAML separator issues and applying
     * sanitization as needed.
     *
     * @return int Exit code (0 for success, 1 for error)
     */
    public function handle(): int
    {
        // Get collection option from command
        $collection = $this->option('collection');

        // Get dry-run option from command
        $dryRun = $this->option('dry-run');

        // Display initial status message
        $this->info('Scanning for Bard content that needs sanitization...');

        // Get all collections or specific collection based on option
        $collections = $collection
            ? [Collection::find($collection)]
            : Collection::all();

        // ---
        // Validate that the specified collection exists
        // ---

        // Check if collection was specified but not found
        if (
            $collection
            && ! $collections[0]
        ) {
            // Display error message for invalid collection
            $this->error(sprintf("Collection '%s' not found.", $collection));

            // Return error exit code
            return 1;
        }

        // Initialize counter for total entries processed
        $totalEntries = 0;

        // Initialize counter for entries with issues
        $entriesWithIssues = 0;

        // Initialize counter for entries updated
        $entriesUpdated = 0;

        // Process each collection
        foreach ($collections as $collection) {
            /** @var \Statamic\Entries\Collection $collectionItem */
            $collectionItem = $collection;

            // Display current collection being processed
            $collectionTitleValue = $collectionItem->title();
            $collectionTitle = is_scalar($collectionTitleValue) ? (string) $collectionTitleValue : '';
            $this->line('Processing collection: '.$collectionTitle);

            // ---
            // Get all entries from the current collection
            // ---

            // Build a query for entries
            /** @var EntryQueryBuilder $entryQuery */
            $entryQuery = Entry::query();

            // Add the collection filter to the query to get entries
            /** @var EntryQueryBuilder $filteredQuery */
            $filteredQuery = $entryQuery->where(
                'collection',
                $collectionItem->handle()
            );

            /** @var \Illuminate\Support\Collection<int, \Statamic\Entries\Entry> $entries */
            $entries = $filteredQuery->get();

            // Process each entry in the collection
            foreach ($entries as $entry) {
                /** @var \Statamic\Entries\Entry $entry */

                // Increment total entries counter
                $totalEntries++;

                // Get a display name for the entry (title or slug)
                $entryTitleValue = $entry->value('title') ?: $entry->slug();
                $entryTitle = is_scalar($entryTitleValue) ? (string) $entryTitleValue : '';

                // Get the raw file path where the entry is stored
                $rawFilePath = $entry->path();
                $filePath = is_string($rawFilePath) ? $rawFilePath : '';

                // Skip entries with missing or inaccessible files
                if (
                    ! $filePath
                    || ! LaravelFile::exists($filePath)
                ) {
                    // Display warning for missing entry file
                    $this->warn(sprintf("  - Entry '%s' file not found: %s", $entryTitle, $filePath));

                    // Skip to next entry
                    continue;
                }

                // Read the raw file content for analysis
                $fileContent = LaravelFile::get($filePath);

                // Check if the file needs sanitization (YAML separators or unquoted
                // nbsp text)
                $needsSanitization = $this->sanitizer->containsYamlSeparatorsInRawFile(
                    $fileContent
                )
                || $this->sanitizer->containsUnquotedNbspTextInRawFile($fileContent);

                if ($needsSanitization) {
                    // Increment counter for entries with issues
                    $entriesWithIssues++;

                    // Display message about entry needing sanitization
                    $this->line(sprintf("  - Entry '%s' has Bard content needing sanitization", $entryTitle));

                    // ---
                    // Apply sanitization if not in dry-run mode
                    // ---

                    // Check if not in dry-run mode
                    if (! $dryRun) {
                        // Sanitize file content (YAML separators and nbsp text)
                        $sanitizedContent = $this->sanitizer->sanitizeRawFileContent($fileContent);

                        // Write sanitized content back to file
                        LaravelFile::put(
                            $filePath,
                            $sanitizedContent
                        );

                        // Increment counter for updated entries
                        $entriesUpdated++;
                    }
                }
            }
        }

        // Display summary report
        $this->newLine();
        $this->info('Summary:');
        $this->line('  - Total entries processed: '.$totalEntries);
        $this->line('  - Entries with Bard content issues: '.$entriesWithIssues);

        // ---
        // Display appropriate message based on dry-run mode
        // ---

        // Check if in dry-run mode
        if ($dryRun) {
            // Display dry-run warning message
            $this->warn('  - This was a dry run. No changes were made.');
            $this->line('  - Run without --dry-run to apply changes.');
        } else { // Otherwise
            // Display count of updated entries
            $this->info('  - Entries updated: '.$entriesUpdated);
        }

        // Return success exit code
        return 0;
    }
}
