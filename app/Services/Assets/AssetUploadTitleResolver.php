<?php

declare(strict_types=1);

namespace App\Services\Assets;

use Facades\Statamic\Assets\ExtractInfo;
use Statamic\Assets\Asset;
use Statamic\Support\Arr;
use Throwable;

/**
 * Resolves an asset `title` from embedded file metadata (PDF Info / XMP via
 * smalot/pdfparser, getID3 for other media) or from the original upload filename
 * using Statamic's deslugify behavior.
 */
class AssetUploadTitleResolver
{
    /**
     * Create the resolver with optional PDF metadata reader (for tests).
     *
     * @param  PdfDocumentMetadataReader  $pdfDocumentMetadataReader  PDF Info / XMP reader; defaults to a new instance.
     */
    public function __construct(
        private readonly PdfDocumentMetadataReader $pdfDocumentMetadataReader = new PdfDocumentMetadataReader,
    ) {}

    /**
     * getID3 paths that may hold a human-readable title before merge.
     *
     * @var list<string> Ordered dot paths for `Arr::get` on a getID3 analysis payload.
     */
    private const array TITLE_PATHS = [
        'comments.title',
        'id3v2.comments.title',
        'id3v1.comments.title',
        'quicktime.comments.title',
        'matroska.comments.title',
        'vorbiscomment.comments.title',
        'riff.comments.title',
        'asf.comments.title',
        'mpeg.comments.title',
    ];

    /**
     * Resolve a non-empty title from embedded metadata (PDF Info / XMP or getID3).
     *
     * @param  Asset  $asset  Statamic asset whose binary is read from the container disk.
     * @param  array<string, mixed>|null  $preloadedPdfDetails  Merged smalot `getDetails()` when already parsed for this upload.
     * @return string|null First embedded title string, or null when absent or unreadable.
     */
    public function resolveEmbeddedTitle(
        Asset $asset,
        ?array $preloadedPdfDetails = null
    ): ?string {
        // Read PDF Document Info / XMP title (getID3 does not populate this)
        if (strtolower($asset->extension()) === 'pdf') {
            // Resolve title from preloaded details when present, else parse once from disk
            $pdfTitle = $preloadedPdfDetails !== null
                ? $this->pdfDocumentMetadataReader->titleFromDetailsArray(
                    $preloadedPdfDetails
                )
                : $this->pdfDocumentMetadataReader->resolveTitle($asset);

            // Return PDF-derived title or continue to filename fallback upstream
            if (
                $pdfTitle !== null
                && $pdfTitle !== ''
            ) {
                return $pdfTitle;
            }

            // Return null when no title was found
            return null;
        }

        // Attempt to analyze the stored file with getID3 (same stack as Statamic)
        try {
            /** @var array<string, mixed> $analysis */
            $analysis = ExtractInfo::fromAsset($asset);
        } catch (Throwable) {
            // Treat analysis failures as no embedded title
            return null;
        }

        // Extract the first usable title string from known analysis locations
        return $this->firstTitleFromAnalysis($analysis);
    }

    /**
     * Build a title from the client's original filename (preserves casing before
     * Statamic lowercases the stored filename).
     *
     * @param  string  $originalFilename  Client original basename, often with extension
     *                                    (e.g., from `UploadedFile::getClientOriginalName()`).
     * @return string Deslugified stem suitable for the asset `title` field.
     */
    public function titleFromOriginalFilename(
        string $originalFilename
    ): string {
        // Decode URL-encoded names from the client when present
        $decoded = urldecode($originalFilename);

        // Take the filename stem (drop extension) for display text
        $stem = pathinfo($decoded, PATHINFO_FILENAME);

        // Apply Statamic deslugify semantics (hyphens and underscores to spaces)
        return $this->deslugifyStem($stem);
    }

    /**
     * Build a title from a stored asset filename stem (extension already removed).
     *
     * @param  string  $stem  Filename without extension (e.g., from `Asset::filename()`).
     * @return string Deslugified title; may be empty when `$stem` is blank.
     */
    public function titleFromAssetFilenameStem(
        string $stem
    ): string {
        // Apply Statamic deslugify semantics to the stem
        return $this->deslugifyStem($stem);
    }

    /**
     * Pick the first non-empty title string from a getID3 analysis array.
     *
     * @param  array<string, mixed>  $analysis  Raw getID3 analysis payload for one file.
     * @return string|null First non-empty title from `TITLE_PATHS`, or null.
     */
    public function firstTitleFromAnalysis(
        array $analysis
    ): ?string {
        // Walk known title locations in priority order
        foreach (self::TITLE_PATHS as $path) {
            // Read the candidate value at the current path
            $candidate = Arr::get($analysis, $path);

            // Normalize candidate to a single string when possible
            $resolved = $this->normalizeTitleCandidate($candidate);

            // Return the first usable title
            if ($resolved !== null) {
                return $resolved;
            }
        }

        // Return null when no title was found
        return null;
    }

    /**
     * Match Statamic's `deslugify` modifier: hyphens and underscores to spaces,
     * trimmed.
     *
     * @param  string  $stem  Slug-like fragment to turn into spaced words.
     * @return string Trimmed value with `-` and `_` replaced by single spaces.
     */
    private function deslugifyStem(
        string $stem
    ): string {
        // Replace slug separators with spaces like CoreModifiers::deslugify
        // preg_replace() returns null only when a pattern fails, which this fixed pattern
        // over a string cannot do, so the '' fallback is never used.
        // @pest-mutate-ignore: EmptyStringToNotEmpty
        return trim(
            preg_replace(
                '~[-_]~',
                ' ',
                $stem
            ) ?? '',
            ' '
        );
    }

    /**
     * Normalize a getID3 title field (string or list of strings) to one string.
     *
     * @param  mixed  $value  Title candidate from getID3 (`string` or `list<string>`).
     * @return string|null First non-empty trimmed string, or null when none.
     */
    private function normalizeTitleCandidate(
        mixed $value
    ): ?string {
        // Accept a single string value
        if (\is_string($value)) {
            // Trim whitespace from the string candidate
            $trimmed = trim($value);

            // Return null when the trimmed string is empty
            if ($trimmed === '') {
                return null;
            }

            // Return the non-empty trimmed title
            return $trimmed;
        }

        // Accept an array of string values (typical for comments.title)
        if (\is_array($value)) {
            // Scan array entries for the first non-empty string
            foreach ($value as $item) {
                // Skip non-string entries
                if (! \is_string($item)) {
                    continue;
                }

                // Trim whitespace from the array element
                $trimmed = trim($item);

                // Skip empty elements
                if ($trimmed === '') {
                    continue;
                }

                // Return the first usable title from the list
                return $trimmed;
            }
        }

        // No valid title in this candidate
        return null;
    }
}
