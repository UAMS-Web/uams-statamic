<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\BardContentSanitizer;
use Illuminate\Support\Facades\Event as IlluminateEventFacade;
use Illuminate\Support\Facades\File as LaravelFileFacade;
use Illuminate\Support\ServiceProvider;
use Statamic\Entries\Entry;
use Statamic\Events\EntrySaved;

/**
 * Service provider for Bard content sanitization.
 *
 * This provider registers the Bard content sanitizer service and integrates
 * it with Statamic's entry saving process to automatically handle YAML document
 * separator issues.
 *
 * ## Problem Context
 *
 * When Bard fields contain YAML document separators (`...` or `---`) as text
 * content, Statamic's YAML parser can interpret them as document terminators
 * rather than literal string values, causing parsing errors. This service
 * provider automatically sanitizes content after entries are saved to prevent
 * these issues.
 *
 * ## How It Works
 *
 * The provider hooks into Statamic's `EntrySaved` event to automatically process
 * YAML files after they are written to disk. It scans the raw file content for
 * problematic patterns and applies sanitization by quoting YAML separators.
 *
 * ## Upstream Monitoring
 *
 * This is a workaround for a bug in the interaction between Statamic's YAML
 * parsing and Tiptap's Bard editor. Developers should monitor:
 *   - Statamic CMS updates for YAML parsing improvements
 *   - Tiptap editor updates for Bard field handling
 *   - Symfony YAML component updates for parser behavior changes
 *
 * This provider may become unnecessary or require modification if upstream
 * libraries implement proper handling of YAML separators in content fields.
 */
class BardContentSanitizerServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * Registers the BardContentSanitizer service as a singleton in the
     * application container for dependency injection.
     */
    #[\Override]
    public function register(): void
    {
        // Register the sanitizer service as a singleton for consistent behavior
        $this->app->singleton(
            BardContentSanitizer::class,
            fn (): BardContentSanitizer => new BardContentSanitizer
        );
    }

    /**
     * Bootstrap any application services.
     *
     * Registers event listeners to automatically sanitize Bard content after
     * entries are saved. This ensures that YAML document separators are properly
     * escaped in the saved files.
     */
    public function boot(): void
    {
        // Listen for entry saved events to sanitize the file directly
        IlluminateEventFacade::listen(
            EntrySaved::class,
            function (EntrySaved $event): void {
                // Resolve the sanitizer service from the container
                $sanitizer = $this->app->make(BardContentSanitizer::class);

                // Get the entry that was just saved
                /** @var Entry $entry */
                $entry = $event->entry;

                // Get the raw file path where the entry is stored
                $filePath = $entry->path();

                // ---
                // Only process if the file exists and is accessible
                // ---

                // Check if the file path is valid and the file exists
                if (
                    is_string($filePath)
                    && $filePath !== ''
                    && LaravelFileFacade::exists($filePath)
                ) {
                    // Read the raw file content for analysis
                    $fileContent = LaravelFileFacade::get($filePath);

                    // Check if the file needs sanitization (YAML separators or unquoted
                    // nbsp text)
                    $needsSanitization = $sanitizer->containsYamlSeparatorsInRawFile(
                        $fileContent
                    )
                    || $sanitizer->containsUnquotedNbspTextInRawFile($fileContent);

                    if ($needsSanitization) {
                        // Apply sanitization to the file content
                        $sanitizedContent = $sanitizer->sanitizeRawFileContent($fileContent);

                        // Write the sanitized content back to the file
                        LaravelFileFacade::put(
                            $filePath,
                            $sanitizedContent
                        );
                    }
                }
            }
        );
    }
}
