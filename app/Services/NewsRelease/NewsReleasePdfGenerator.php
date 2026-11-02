<?php

declare(strict_types=1);

namespace App\Services\NewsRelease;

use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\Facades\Pdf;
use Statamic\Assets\Asset;
use Statamic\Assets\AssetContainer;
use Statamic\Assets\UploadedReplacementFile;
use Statamic\Entries\Entry;
use Statamic\Facades\Antlers;
use Statamic\Facades\AssetContainer as AssetContainerFacade;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\Site;
use Statamic\Fields\Blueprint;
use Statamic\Sites\Site as SiteContract;
use Statamic\View\Cascade;

/**
 * Generate and store news release PDFs using Antlers and Spatie Laravel PDF.
 *
 * Renders the PDF Antlers template with a full Statamic cascade (globals such as
 * `uams_multisite_settings`, contextual `site` / `now`, plus augmented entry
 * fields) so template variables match normal front-end access. Converts HTML to
 * PDF via the default laravel-pdf driver (typically Browsershot). Persists files
 * under the `news_releases` container as `{Y-m-d}_{slug}.pdf` (date segment in
 * display_timezone, which may differ from the entry file prefix under
 * app.timezone) and sets `news_release_pdf` to container-relative paths (not
 * `Asset::id()`) for Statamic's Assets augment. Uses `saveQuietly()` so
 * `EntrySaved` listeners do not loop. Each PDF stores `news_release_entry` in
 * asset data so older files for the same entry can be removed when the path
 * changes. PDF generation uses Browsershot with `preferCSSPageSize`, zero Chrome
 * DevTools Protocol print margins, CSS `@page uams-pdf-letter` (Letter, 1in), and
 * a matching viewport width so print layout matches physical units.
 */
class NewsReleasePdfGenerator
{
    /**
     * Statamic asset container handle where generated PDFs are stored.
     */
    private const string NEWS_RELEASES_CONTAINER = 'news_releases';

    /**
     * Asset blueprint field handle linking a PDF to its `posts` entry (entries
     * fieldtype, max one). Stored value is the entry UUID string.
     *
     * Used to delete superseded files when slug or display date changes the path.
     */
    private const string NEWS_RELEASE_ENTRY_FIELD = 'news_release_entry';

    /**
     * Path under `resources/` to the full-document Antlers template for PDFs.
     */
    private const string TEMPLATE_PATH = 'views/pdf/news_release.antlers.html';

    /**
     * Assemble Antlers variables for the PDF template.
     *
     * Instantiates a dedicated Cascade for the entry's site, runs Cascade::hydrate()
     * with the entry as cascade content, then spreads Entry::toAugmentedArray() so
     * entry keys override cascade keys on collisions. Needed because passing only
     * augmented entry data omits globals that Antlers::parse() would otherwise lack.
     *
     * @param  Entry  $entry  The news release entry being exported
     * @return array<string, mixed> Template variables for Antlers::parse()
     */
    public function buildPdfAntlersContext(
        Entry $entry
    ): array {
        // Resolve the entry's site instance for the cascade
        $entrySite = $entry->site();

        // Check whether the entry site could be resolved
        if (! $entrySite instanceof SiteContract) {
            // Fall back to the default site when the handle is unknown
            $entrySite = Site::default();
        }

        // Build a fresh cascade for this site (do not mutate the app singleton)
        /** @var SiteContract $entrySite */
        $cascade = new Cascade(request(), $entrySite);

        // Hydrate globals, request context, and merged entry fields
        /** @var Cascade $contentCascade */
        $contentCascade = $cascade->withContent($entry);
        /** @var Cascade $hydratedCascade */
        $hydratedCascade = $contentCascade->hydrate();

        // Read the hydrated cascade as an array of template variables
        /** @var array<string, mixed> $cascadeData */
        $cascadeData = $hydratedCascade->toArray();

        // Read the augmented entry fields as an array of template variables
        /** @var array<string, mixed> $augmentedEntry */
        $augmentedEntry = $entry->toAugmentedArray();

        // Spread entry-augmented fields last so they win on key collisions
        return [
            ...$cascadeData,
            ...$augmentedEntry,
        ];
    }

    /**
     * Render the full HTML document used as Browsershot input for the PDF.
     *
     * Temporarily sets Site::setCurrent() to the entry's site so tags and globals
     * align, then restores the previous current site in a `finally` block.
     * Root-relative `src="/…"` attributes are rewritten to absolute URLs using
     * self::siteOrigin() for headless Chrome.
     *
     * @param  Entry  $entry  The news release entry to render
     * @return string Complete HTML5 document string
     */
    public function renderPressReleaseHtml(
        Entry $entry
    ): string {
        // Initialize the prior current site for restoration after rendering
        $previousSite = Site::current();

        // Resolve the entry's site instance for Antlers and URL building
        $entrySite = $entry->site();

        // Check whether the entry site could be resolved
        if (! $entrySite instanceof SiteContract) {
            // Fall back to the default site when the handle is unknown
            $entrySite = Site::default();
        }

        // Point Statamic's current site at the entry's site for globals/tags
        /** @var SiteContract $entrySite */
        Site::setCurrent($entrySite->handle());

        // Attempt to render the press release HTML
        try {
            // Merge cascade (globals, site, now) with augmented entry fields
            $context = $this->buildPdfAntlersContext($entry);

            // Load the PDF Antlers template from resources
            $templatePath = resource_path(self::TEMPLATE_PATH);

            // Read the template file contents
            $template = File::get($templatePath);

            // Render HTML via Antlers using the augmented context
            $html = (string) Antlers::parse($template, $context, true);

            // Rewrite root-relative asset paths to absolute URLs for PDF rendering
            $html = $this->absolutizeRootRelativeSrcAttributes(
                $html,
                $this->siteOrigin($entrySite)
            );
        } finally {
            // Restore the previous current site when one was set. Site::current() falls back
            // to the default site, so this guard always holds
            // @pest-mutate-ignore: InstanceOfToTrue
            if ($previousSite instanceof SiteContract) {
                Site::setCurrent($previousSite->handle());
            }
        }

        // Return the rendered HTML document
        return $html;
    }

    /**
     * Whether this entry is one whose PDF this generator manages: a `posts`
     * entry using the `news_release` blueprint.
     *
     * @param  Entry  $entry  The entry to test
     */
    public function appliesTo(Entry $entry): bool
    {
        if ($entry->collectionHandle() !== 'posts') {
            return false;
        }

        /** @var Blueprint|null $blueprint */
        $blueprint = $entry->blueprint();

        return $blueprint !== null && $blueprint->handle() === 'news_release';
    }

    /**
     * Delete every PDF in the `news_releases` container owned by this entry.
     *
     * Used when an entry is no longer publicly live (unpublished, draft,
     * scheduled, or expired) or is deleted, so a previously generated press
     * release cannot remain downloadable at its guessable public URL. Matches on
     * the `news_release_entry` ownership tag the sync step stamps on each asset,
     * so it catches PDFs at superseded date/slug paths too. The entry's
     * `news_release_pdf` field is intentionally left untouched: it augments to
     * empty once the underlying asset path no longer exists, and the entry may be
     * mid-deletion here, so this method never writes the entry.
     *
     * @param  Entry  $entry  The news release entry whose PDFs to remove
     */
    public function removePdfForEntry(Entry $entry): void
    {
        // Resolve the news releases asset container
        /** @var AssetContainer|null $container */
        $container = AssetContainerFacade::find(self::NEWS_RELEASES_CONTAINER);

        // Nothing to remove when the container is missing
        if ($container === null) {
            return;
        }

        // Owning entry id used to match tagged PDFs
        $entryIdValue = $entry->id();
        $entryId = is_scalar($entryIdValue) ? (string) $entryIdValue : '';

        // Delete each asset tagged for this entry
        foreach ($container->assets() as $candidate) {
            /** @var Asset $candidate */
            // Skip assets not tagged for this entry
            if ($candidate->get(self::NEWS_RELEASE_ENTRY_FIELD) !== $entryId) {
                continue;
            }

            // Attempt to remove the PDF and its `.meta` sidecar
            try {
                $candidate->deleteQuietly();
            } catch (\Throwable $deleteFailure) {
                // Log delete failures without breaking the editor save / delete
                Log::warning('NewsReleasePdfGenerator: failed deleting PDF on removal', [
                    'entry_id' => $entryId,
                    'asset_id' => $candidate->id(),
                    'message' => $deleteFailure->getMessage(),
                ]);
            }
        }
    }

    /**
     * Create or overwrite the PDF asset and attach it to the entry.
     *
     * Flow: render HTML, write a temp `.pdf` via Pdf::html() with `format('letter')`,
     * zero Chrome DevTools Protocol print margins (page margins come from
     * `@page uams-pdf-letter` in PDF SCSS). Browsershot options `preferCSSPageSize`
     * and `windowSize(624, 1200)` for correct scaling; no `->meta()` because Chromium
     * PDFs break Spatie's metadata trailer patcher. Upload or Asset::reupload() at
     * `{Y-m-d}_{slug}.pdf` in the container root (date in Statamic display timezone),
     * reload the entry via EntryFacade::find() so template/cascade work during HTML
     * render cannot skew the publish instant on the EntrySaved payload, then set
     * `news_release_pdf` as paths within the container (see
     * rawNewsReleasePdfFieldValue()) and Entry::saveQuietly(). Logs warnings and
     * returns early on temp-file or container failure; catches throwables during PDF
     * write/upload. Removes the temp file and unsets large locals in `finally`.
     *
     * @param  Entry  $entry  The news release entry to sync
     */
    public function syncPdfForEntry(
        Entry $entry
    ): void {
        // Render press release HTML for PDF conversion
        $html = $this->renderPressReleaseHtml($entry);

        // Initialize a temporary file path for the PDF bytes
        $tempPath = tempnam(sys_get_temp_dir(), 'nrpdf');

        // Check whether the temporary path was allocated. tempnam() falls back to the
        // system temporary directory, so a test cannot make it fail.
        if (
            // @pest-mutate-ignore: FalseToTrue
            $tempPath === false
        ) {
            // Log failure when no temp file is available
            Log::warning('NewsReleasePdfGenerator: could not allocate temp file for PDF', [
                'entry_id' => $entry->id(),
            ]);

            // Exit early when temp file creation fails
            return;
        }

        // Append .pdf so drivers and uploads receive a sensible extension
        $pdfTempPath = $tempPath.'.pdf';

        // Rename the temp file to use a .pdf extension
        rename($tempPath, $pdfTempPath);

        // Initialize optional references for cleanup after the upload attempt
        $container = null;

        // Initialize the uploaded file reference for cleanup bookkeeping
        $uploadedFile = null;

        // Initialize the existing asset reference for cleanup bookkeeping
        $existing = null;

        // Initialize the resulting asset reference for cleanup bookkeeping
        $asset = null;

        // Initialize the reloaded entry reference for cleanup in `finally`
        $persisted = null;

        // Attempt to generate the PDF and attach it to the entry
        try {
            // ---
            // Write PDF bytes to the temporary path via laravel-pdf Omit ->meta(): Spatie's
            // PdfMetadataWriter expects a classic PDF trailer; Chromium/Browsershot output
            // often uses xref streams, which triggers "Could not parse PDF trailer to find
            // /Size and /Root."
            // ---
            Pdf::html($html)
                ->format('letter')
                // ---
                // Chrome DevTools Protocol print margins zero: @page uams-pdf-letter supplies 1in
                // in SCSS when preferCSSPageSize is true (avoids double margins / wrong fit box).
                // ---
                ->margins(
                    0,
                    0,
                    0,
                    0,
                    'mm'
                )
                ->withBrowsershot(
                    function (Browsershot $browsershot): void {
                        // Use CSS @page (letter + margins) instead of shrink-to-fit
                        $browsershot->setOption(
                            'preferCSSPageSize',
                            true
                        );
                        // Viewport ≈ Letter content width (8.5in − 2in) in CSS px
                        $browsershot->windowSize(
                            624,
                            1200
                        );
                    }
                )
                ->save($pdfTempPath);

            // Resolve the news releases asset container (concrete class exposes makeAsset)
            /** @var AssetContainer|null $container */
            $container = AssetContainerFacade::find(self::NEWS_RELEASES_CONTAINER);

            // Check whether the container exists
            if ($container === null) {
                // Log when the configured container is missing
                Log::warning('NewsReleasePdfGenerator: asset container not found', [
                    'container' => self::NEWS_RELEASES_CONTAINER,
                    'entry_id' => $entry->id(),
                ]);

                // Exit early when the container cannot be loaded
                return;
            }

            // Build relative path from display-timezone date and slug
            $relativePath = $this->relativePathForNewsReleasePdf($entry);

            // Check whether date and slug yield a storage path
            if ($relativePath === null) {
                // Log when publish date or slug is missing for filename construction
                Log::warning('NewsReleasePdfGenerator: could not build PDF storage path', [
                    'entry_id' => $entry->id(),
                ]);

                // Exit early when the path cannot be determined
                return;
            }

            // Create an uploaded file facade for Statamic's asset pipeline
            $uploadedFile = new UploadedFile(
                $pdfTempPath,
                basename($relativePath),
                'application/pdf',
                null,
                true
            );

            // Look for an existing asset at the deterministic path
            $existing = $container->asset($relativePath);

            // Check whether an existing asset should be replaced in place
            if ($existing instanceof Asset) {
                // Replace file contents while preserving the asset id
                $existing->reupload(new UploadedReplacementFile($uploadedFile));

                // Use the existing asset instance for the entry field
                $asset = $existing;
            } else {
                // Create a new asset record and upload bytes
                $asset = $container->makeAsset($relativePath)->upload($uploadedFile);
            }

            // Record entry ownership on the asset and remove prior PDFs for this entry
            $ownerEntryIdValue = $entry->id();
            $this->persistNewsReleasePdfOwnershipAndRemoveSuperseded(
                $container,
                $asset,
                is_scalar($ownerEntryIdValue) ? (string) $ownerEntryIdValue : ''
            );

            // Reload the entry from the repository for persistence
            $persisted = EntryFacade::find($entry->id());

            // Check whether the entry could be reloaded after the main save
            if (! $persisted instanceof Entry) {
                // Log when the repository no longer has this entry id
                Log::warning('NewsReleasePdfGenerator: entry not found when attaching PDF', [
                    'entry_id' => $entry->id(),
                ]);

                // Exit early when reload fails so we do not save a stale instance
                return;
            }

            // Attach the generated asset using paths (Assets augment uses whereIn path)
            $persisted->set(
                'news_release_pdf',
                $this->rawNewsReleasePdfFieldValue($asset)
            );

            // Persist the entry without firing EntrySaved / EntrySaving
            $persisted->saveQuietly();
        } catch (\Throwable $throwable) {
            // Log PDF or upload failures without breaking the editor save
            Log::warning('NewsReleasePdfGenerator: failed to generate or store PDF', [
                'entry_id' => $entry->id(),
                'message' => $throwable->getMessage(),
            ]);
        } finally {
            // Remove the temporary PDF file when it still exists
            if (is_file($pdfTempPath)) {
                unlink($pdfTempPath);
            }

            // Unset large strings and objects after the sync attempt
            unset(
                $html,
                $container,
                $uploadedFile,
                $existing,
                $asset,
                $persisted
            );
        }
    }

    /**
     * Raw `news_release_pdf` values for the entry.
     *
     * Statamic's Assets fieldtype augment queries with `whereIn('path', $values)`.
     * Stored strings must be paths inside the `news_releases` container, not
     * `Asset::id()` (`container::path`), or augmentation returns no assets.
     *
     * @return list<string> Paths within the `news_releases` container
     */
    protected function rawNewsReleasePdfFieldValue(
        Asset $asset
    ): array {
        // Resolve the asset path, which Statamic types as mixed
        $assetPath = $asset->path();

        // Build the single-path list expected by the assets field in YAML
        return [is_scalar($assetPath) ? (string) $assetPath : ''];
    }

    /**
     * Relative path within the news releases container for the generated PDF.
     *
     * Uses config('statamic.system.display_timezone') (STATAMIC_DISPLAY_TIMEZONE) for
     * the Y-m-d segment so PDF names follow editorial calendar days. This is
     * intentional: Statamic entry markdown filenames use config('app.timezone') in
     * Entry::buildPath(), so PDF and entry prefixes can differ when display timezone
     * and app timezone disagree (e.g., UTC app + America/Chicago display).
     *
     * Format: `{Y-m-d}_{slug}.pdf` at container root.
     *
     * @param  Entry  $entry  News release entry (dated collection expected)
     * @return string|null Relative path or null when date or slug is missing
     */
    private function relativePathForNewsReleasePdf(
        Entry $entry
    ): ?string {
        // Resolve the publish instant from the entry
        /** @var CarbonInterface|null $publishInstant */
        $publishInstant = $entry->date();

        // Check whether the entry has no dated publish instant
        if ($publishInstant === null) {
            // Release the local before returning without a path
            unset($publishInstant);

            // Signal the caller to skip PDF storage when date is unavailable
            return null;
        }

        // Read Statamic display timezone for the filename date (not storage instant)
        /** @var string $displayTimezone */
        $displayTimezone = config('statamic.system.display_timezone');

        // Shift the instant into the display timezone for the calendar date segment
        $dateInDisplayTz = $publishInstant->copy()->setTimezone($displayTimezone);

        // Format the date segment as Y-m-d for the filename prefix
        $dateSegment = $dateInDisplayTz->format('Y-m-d');

        // Read the entry slug for the filename body
        /** @var string|null $slugSegment */
        $slugSegment = $entry->slug();

        // Check whether the slug is empty
        if ($slugSegment === null || $slugSegment === '') {
            // Release locals before returning without a path
            unset(
                $publishInstant,
                $displayTimezone,
                $dateInDisplayTz,
                $dateSegment,
                $slugSegment
            );

            // Signal the caller to skip PDF storage when slug is missing
            return null;
        }

        // Build container-relative path at root (no subdirectory)
        $relativePath = $dateSegment.'_'.$slugSegment.'.pdf';

        unset(
            $publishInstant,
            $displayTimezone,
            $dateInDisplayTz,
            $dateSegment,
            $slugSegment
        );

        // Return the path within the asset container
        return $relativePath;
    }

    /**
     * Store the owning entry id on the PDF asset and delete other PDFs in the
     * container that still reference the same entry.
     *
     * @param  AssetContainer  $container  The `news_releases` container
     * @param  Asset  $asset  The PDF asset that must remain after sync
     * @param  string  $entryId  UUID of the news release entry
     */
    private function persistNewsReleasePdfOwnershipAndRemoveSuperseded(
        AssetContainer $container,
        Asset $asset,
        string $entryId
    ): void {
        // Stamp the owning entry on the PDF so superseded files are discoverable
        $asset->set(self::NEWS_RELEASE_ENTRY_FIELD, $entryId);

        // Write asset meta to disk without dispatching save listeners
        $asset->saveQuietly();

        // Read the canonical id of the asset that must not be deleted
        $keepAssetId = $asset->id();

        // List all assets in the container for superseded entry matching
        $allAssets = $container->assets();

        // Check each asset for the same entry tag and delete when it is not current
        foreach ($allAssets as $candidate) {
            /** @var Asset $candidate */
            // Skip the PDF we just updated
            if ($candidate->id() === $keepAssetId) {
                continue;
            }

            // Skip assets that are not tagged for this entry
            if ($candidate->get(self::NEWS_RELEASE_ENTRY_FIELD) !== $entryId) {
                continue;
            }

            // Attempt to remove the superseded PDF and its `.meta` sidecar
            try {
                $candidate->deleteQuietly();
            } catch (\Throwable $deleteFailure) {
                // Log delete failures without blocking the editor save
                Log::warning('NewsReleasePdfGenerator: failed deleting superseded PDF asset', [
                    'entry_id' => $entryId,
                    'asset_id' => $candidate->id(),
                    'message' => $deleteFailure->getMessage(),
                ]);
            }

            // Unset local variables to free memory
            unset(
                $candidate
            );
        }

        // Unset local variables to free memory
        unset(
            $allAssets,
            $keepAssetId
        );
    }

    /**
     * Compute URL origin (scheme, host, optional port) for absolute asset URLs.
     *
     * Parses SiteContract::absoluteUrl(); when scheme or host is missing, returns
     * `config('app.url')` trimmed of trailing slashes.
     *
     * @param  SiteContract  $site  Site whose absolute URL defines the origin
     * @return string Origin without a trailing slash (e.g., `https://example.test`)
     */
    private function siteOrigin(
        SiteContract $site
    ): string {
        // Read the site's absolute URL
        $absoluteValue = $site->absoluteUrl();
        // Any text without a scheme and host takes the fallback below, so the empty
        // default is one of many equivalent values
        // @pest-mutate-ignore: EmptyStringToNotEmpty
        $absolute = is_scalar($absoluteValue) ? (string) $absoluteValue : '';

        // Parse URL components for the origin
        $parts = parse_url($absolute);

        // Check whether parsing produced a usable scheme and host
        if (
            ! \is_array($parts)
            || empty($parts['scheme'])
            || empty($parts['host'])
        ) {
            // Fall back to the configured application URL
            $appUrl = config('app.url');

            return rtrim(is_scalar($appUrl) ? (string) $appUrl : '', '/');
        }

        // Initialize the origin with scheme and host
        $origin = $parts['scheme'].'://'.$parts['host'];

        // Append the port when it is non-default and present
        if (! empty($parts['port'])) {
            $origin .= ':'.$parts['port'];
        }

        // Return the origin without a trailing slash
        return $origin;
    }

    /**
     * Rewrite `src` attributes that start with `/` to absolute URLs.
     *
     * Browsershot resolves resources against the document; root-relative URLs
     * otherwise fail. Only matches `src="/…"` (leading whitespace allowed per
     * pattern). Already-absolute URLs are unchanged.
     *
     * @param  string  $html  HTML from Antlers after rendering
     * @param  string  $origin  Origin from self::siteOrigin() (no trailing slash)
     * @return string Same HTML with root-relative `src` values absolutized
     */
    private function absolutizeRootRelativeSrcAttributes(
        string $html,
        string $origin
    ): string {
        // Replace root-relative src attributes with absolute URLs
        // preg_replace_callback() returns null only on a regex engine failure, which this
        // fixed pattern cannot reach, so the cast never changes the value
        // @pest-mutate-ignore: RemoveStringCast
        return (string) preg_replace_callback(
            '/\ssrc="(\/[^"]*)"/',
            fn (array $matches): string => sprintf(' src="%s%s"', $origin, $matches[1]),
            $html
        );
    }
}
