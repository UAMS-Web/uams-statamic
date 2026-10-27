<?php

declare(strict_types=1);

namespace App\Services\Assets;

use Facades\Statamic\Assets\ExtractInfo;
use Statamic\Assets\Asset;
use Statamic\Support\Arr;
use Throwable;

/**
 * Maps embedded file metadata to Statamic asset blueprint fields by container handle.
 */
class AssetUploadMetadataMapper
{
    /**
     * Create the mapper with the shared PDF metadata reader.
     *
     * @param  PdfDocumentMetadataReader  $pdfDocumentMetadataReader  Reader for merged PDF Info / XMP fields.
     */
    public function __construct(
        private readonly PdfDocumentMetadataReader $pdfDocumentMetadataReader
    ) {}

    /**
     * Containers that receive image-style alt, caption, and exempt_from_alt rules.
     *
     * The logo containers (`logos`, `logos_unrestricted`) are intentionally
     * excluded: UAMS-Web/uams-statamic-archive#49 removed `alt` / `caption` / `exempt_from_alt` from their
     * blueprints (the logo `alt` is computed via TitleAltResolver / AltFormatter),
     * so writing those keys on upload would only orphan data with no backing
     * fields (UAMS-Web/uams-statamic-archive#831).
     *
     * @var list<string>
     */
    private const array IMAGE_STYLE_CONTAINERS = [
        'images',
        'portraits',
        'portraits_wide',
    ];

    /**
     * getID3 dot paths for Dublin Core description (XMP segment).
     *
     * @var list<string>
     */
    private const array DC_DESCRIPTION_ANALYSIS_PATHS = [
        'xmp.dc.description',
    ];

    /**
     * getID3 dot paths for short label / title-like text used as alt text.
     *
     * @var list<string>
     */
    private const array IMAGE_ALT_ANALYSIS_PATHS = [
        'xmp.dc.title',
        'comments.title',
        'comments.headline',
    ];

    /**
     * getID3 dot paths for longer description / caption text.
     *
     * @var list<string>
     */
    private const array IMAGE_CAPTION_ANALYSIS_PATHS = [
        'xmp.dc.description',
        'comments.caption',
        'comments.description',
    ];

    /**
     * Build blueprint field updates from embedded metadata for this container.
     *
     * @param  Asset  $asset  Statamic asset whose file and container drive mapping rules.
     * @param  array<string, mixed>|null  $preloadedPdfDetails  Merged smalot `getDetails()` for PDFs when already parsed for this upload.
     * @return array<string, mixed> Non-empty keys to merge into asset data before save.
     */
    public function resolveMetaUpdates(
        Asset $asset,
        ?array $preloadedPdfDetails
    ): array {
        // Read the container handle for routing rules
        $containerHandle = $asset->container()->handle();

        // Combine routed update slices (later spreads win on duplicate keys, same as array_merge)
        return [
            ...$this->newsReleasesExcerptUpdates(
                $asset,
                $containerHandle,
                $preloadedPdfDetails
            ),
            ...$this->audioOrFilesExcerptUpdates(
                $asset,
                $containerHandle,
                $preloadedPdfDetails
            ),
            ...$this->imageStyleMetaUpdates(
                $asset,
                $containerHandle
            ),
        ];
    }

    /**
     * PDF Subject → excerpt for `news_releases` only.
     *
     * @param  Asset  $asset  Asset whose extension and container are checked.
     * @param  string  $containerHandle  Handle of the asset container.
     * @param  array<string, mixed>|null  $preloadedPdfDetails  Merged PDF details when the upload is a PDF.
     * @return array<string, mixed> `excerpt` entry when subject exists, else empty.
     */
    private function newsReleasesExcerptUpdates(
        Asset $asset,
        string $containerHandle,
        ?array $preloadedPdfDetails
    ): array {
        // Skip when this is not the news releases container
        if ($containerHandle !== 'news_releases') {
            return [];
        }

        // Skip when the file is not a PDF
        if (strtolower($asset->extension()) !== 'pdf') {
            return [];
        }

        // Skip when PDF details were not loaded
        if ($preloadedPdfDetails === null) {
            return [];
        }

        // Read subject text from merged PDF metadata
        $subject = $this->pdfDocumentMetadataReader->subjectFromDetailsArray(
            $preloadedPdfDetails
        );

        // Skip when subject is absent
        if ($subject === null) {
            return [];
        }

        // Return the excerpt update
        return [
            'excerpt' => $subject,
        ];
    }

    /**
     * Dublin Core description → excerpt for `audio` and `files`.
     *
     * @param  Asset  $asset  Asset analyzed via PDF details or getID3.
     * @param  string  $containerHandle  Handle of the asset container.
     * @param  array<string, mixed>|null  $preloadedPdfDetails  Merged PDF details when the upload is a PDF.
     * @return array<string, mixed> `excerpt` entry when description exists, else empty.
     */
    private function audioOrFilesExcerptUpdates(
        Asset $asset,
        string $containerHandle,
        ?array $preloadedPdfDetails
    ): array {
        // Skip when this container does not use excerpt from dc:description
        // Its values are non-numeric strings, which loose and strict in_array() compare alike.
        // @pest-mutate-ignore: TrueToFalse
        if (! \in_array($containerHandle, ['audio', 'files'], true)) {
            return [];
        }

        // Initialize excerpt candidate
        $description = null;

        // Prefer dc:description from preloaded PDF details when applicable
        if (
            strtolower($asset->extension()) === 'pdf'
            && $preloadedPdfDetails !== null
        ) {
            // Read Dublin Core description from merged PDF metadata
            $description = $this->pdfDocumentMetadataReader->dcDescriptionFromDetailsArray(
                $preloadedPdfDetails
            );
        }

        // Fall back to getID3 XMP dc:description for non-PDF assets
        if ($description === null) {
            // Resolve dc:description from getID3 analysis
            $description = $this->firstStringAtAnalysisPaths(
                $asset,
                self::DC_DESCRIPTION_ANALYSIS_PATHS
            );
        }

        // Skip when no description was found
        if ($description === null) {
            return [];
        }

        // Return the excerpt update
        return [
            'excerpt' => $description,
        ];
    }

    /**
     * Alt, caption, and exempt_from_alt for configured image containers.
     *
     * @param  Asset  $asset  Asset passed to getID3 for XMP / comment fields.
     * @param  string  $containerHandle  Handle of the asset container.
     * @return array<string, mixed> Caption and/or alt-related keys, or empty.
     */
    private function imageStyleMetaUpdates(
        Asset $asset,
        string $containerHandle
    ): array {
        // Skip containers that do not use image-style meta mapping
        // Its values are non-numeric strings, which loose and strict in_array() compare alike.
        // @pest-mutate-ignore: TrueToFalse
        if (! \in_array($containerHandle, self::IMAGE_STYLE_CONTAINERS, true)) {
            return [];
        }

        // Attempt to analyze the stored file with getID3
        try {
            /** @var array<string, mixed> $analysis */
            $analysis = ExtractInfo::fromAsset($asset);
        } catch (Throwable) {
            return [];
        }

        // Resolve alt and caption strings from the same analysis payload
        $alt = $this->firstStringFromAnalysisArray(
            $analysis,
            self::IMAGE_ALT_ANALYSIS_PATHS
        );

        // Resolve caption string from the same analysis payload
        $caption = $this->firstStringFromAnalysisArray(
            $analysis,
            self::IMAGE_CAPTION_ANALYSIS_PATHS
        );

        // Initialize updates map
        $updates = [];

        // Apply caption when present
        if ($caption !== null) {
            $updates['caption'] = $caption;
        }

        // Apply alt and exempt flag when alt text is present
        if ($alt !== null) {
            $updates['alt'] = $alt;

            // Mark alt as sourced so dashboard / validation rules can treat it accordingly
            $updates['exempt_from_alt'] = true;
        }

        // Return the updates map
        return $updates;
    }

    /**
     * Return the first non-empty string found at the given dot paths (getID3 once).
     *
     * @param  Asset  $asset  Statamic asset whose binary is analyzed.
     * @param  list<string>  $paths  Ordered `Arr::get` dot paths on the getID3 payload.
     * @return string|null First non-empty normalized string, or null.
     */
    private function firstStringAtAnalysisPaths(
        Asset $asset,
        array $paths
    ): ?string {
        // Analyze the asset once for this lookup
        try {
            /** @var array<string, mixed> $analysis */
            $analysis = ExtractInfo::fromAsset($asset);
        } catch (Throwable) {
            return null;
        }

        // Return the first string from the analysis array
        return $this->firstStringFromAnalysisArray(
            $analysis,
            $paths
        );
    }

    /**
     * Scan preloaded getID3 analysis for the first non-empty string at known paths.
     *
     * @param  array<string, mixed>  $analysis  Raw getID3 analysis for one file.
     * @param  list<string>  $paths  Ordered `Arr::get` dot paths to scan.
     * @return string|null First non-empty normalized string, or null.
     */
    private function firstStringFromAnalysisArray(
        array $analysis,
        array $paths
    ): ?string {
        // Walk each path in priority order
        foreach ($paths as $path) {
            // Read the candidate at the current path
            $candidate = Arr::get($analysis, $path);

            // Normalize nested XMP / comment structures to one string
            $resolved = $this->stringifyMetadataValue($candidate);

            // Return the first usable string
            if ($resolved !== null) {
                return $resolved;
            }
        }

        // Return null when no string was found
        return null;
    }

    /**
     * Normalize getID3 metadata values to a single trimmed string.
     *
     * @param  mixed  $value  String, number, or nested array from getID3 / XMP.
     * @return string|null First usable string, or null when none.
     */
    private function stringifyMetadataValue(
        mixed $value
    ): ?string {
        // Accept scalar strings directly
        if (\is_string($value)) {
            // Trim whitespace from the candidate
            $trimmed = trim($value);

            // Reject empty strings after trim
            if ($trimmed === '') {
                return null;
            }

            // Return the trimmed string
            return $trimmed;
        }

        // Accept integer and float scalars as string equivalents
        if (
            \is_int($value)
            || \is_float($value)
        ) {
            // Return the trimmed string
            // A number cast to a string has no surrounding whitespace, so trim() is a no-op.
            // @pest-mutate-ignore: UnwrapTrim
            return trim((string) $value);
        }

        // Walk nested arrays (lang alternatives, repeated tags)
        if (\is_array($value)) {
            // Walk each nested element
            foreach ($value as $item) {
                // Recurse into each nested element
                $nested = $this->stringifyMetadataValue($item);

                // Return the first nested string
                if ($nested !== null) {
                    return $nested;
                }
            }
        }

        // Return null when no string was found
        return null;
    }
}
