<?php

declare(strict_types=1);

namespace App\Services\Assets;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Smalot\PdfParser\Parser;
use Statamic\Assets\Asset;
use Throwable;

/**
 * Reads PDF document metadata from the Info dictionary and XMP (via smalot/pdfparser).
 */
class PdfDocumentMetadataReader
{
    /**
     * `getDetails()` keys to try first (XMP then classic Info `Title`).
     *
     * @var list<string>
     */
    private const array DETAIL_TITLE_KEYS = [
        'dc:title',
        'Title',
        'title',
    ];

    /**
     * PDF Info / XMP keys that hold the document subject (often used as a short
     * summary in Acrobat).
     *
     * @var list<string>
     */
    private const array DETAIL_SUBJECT_KEYS = [
        'Subject',
        'subject',
    ];

    /**
     * Dublin Core description from merged PDF XMP / details.
     *
     * @var list<string>
     */
    private const array DETAIL_DC_DESCRIPTION_KEYS = [
        'dc:description',
    ];

    /**
     * Load merged PDF details once for reuse (title, subject, dc:description).
     *
     * @param  Asset  $asset  Statamic asset whose PDF bytes are read from the container disk.
     * @return array<string, mixed>|null Smalot `getDetails()` map, or null when unreadable.
     */
    public function loadDetails(
        Asset $asset
    ): ?array {
        // Check that the asset exists on its container disk
        if (! $asset->exists()) {
            return null;
        }

        // Resolve an absolute path or a temp copy for non-local disks
        $resolved = $this->resolveReadablePath($asset);

        // Check that a path or temp file was obtained
        // Carrying on hands parseFile() a null path, which throws into the catch below and
        // returns the same null; the finally has no copy to remove.
        // @pest-mutate-ignore: RemoveEarlyReturn
        if ($resolved === null) {
            return null;
        }

        // Extract absolute path and temp path from the resolved array
        [$absolutePath, $tempPath] = $resolved;

        // Attempt to parse the PDF file
        try {
            // Parse PDF structure and merged Info / XMP details
            $parser = new Parser;
            $document = $parser->parseFile($absolutePath);

            // Extract human-readable metadata map
            /** @var array<string, mixed> $details */
            $details = $document->getDetails();

            return $details;
        } catch (Throwable) {
            // Treat parse failures as absent metadata
            return null;
        } finally {
            // Remove temp copy when one was created for remote disks
            if (
                $tempPath !== null
                && is_file($tempPath)
            ) {
                unlink($tempPath);
            }
        }
    }

    /**
     * Resolve a non-empty title from PDF metadata when the file is readable.
     *
     * @param  Asset  $asset  Concrete Statamic asset (Eloquent driver extends this class).
     * @return string|null First title string, or null when absent or unreadable.
     */
    public function resolveTitle(
        Asset $asset
    ): ?string {
        // Load merged details from disk (single parse)
        $details = $this->loadDetails($asset);

        // Return null when the document could not be parsed
        if ($details === null) {
            return null;
        }

        // Pick the first usable title from known detail keys
        return $this->firstTitleFromDetails($details);
    }

    /**
     * Resolve title from a preloaded `getDetails()` map (avoids a second parse).
     *
     * @param  array<string, mixed>  $details  Merged smalot PDF metadata map.
     * @return string|null First title string, or null when absent.
     */
    public function titleFromDetailsArray(
        array $details
    ): ?string {
        return $this->firstTitleFromDetails($details);
    }

    /**
     * First non-empty subject string from merged PDF details.
     *
     * @param  array<string, mixed>  $details  Merged smalot PDF metadata map.
     * @return string|null PDF Info `Subject` (or equivalent), or null.
     */
    public function subjectFromDetailsArray(
        array $details
    ): ?string {
        return $this->firstDetailStringFromDetails(
            $details,
            self::DETAIL_SUBJECT_KEYS
        );
    }

    /**
     * First non-empty Dublin Core description from merged PDF details.
     *
     * @param  array<string, mixed>  $details  Merged smalot PDF metadata map.
     * @return string|null `dc:description` value, or null.
     */
    public function dcDescriptionFromDetailsArray(
        array $details
    ): ?string {
        return $this->firstDetailStringFromDetails(
            $details,
            self::DETAIL_DC_DESCRIPTION_KEYS
        );
    }

    /**
     * Walk preferred keys and return the first normalized string value.
     *
     * @param  array<string, mixed>  $details  Merged smalot PDF metadata map.
     * @param  list<string>  $keys  Detail keys to try in order.
     * @return string|null First non-empty normalized value, or null.
     */
    public function firstDetailStringFromDetails(
        array $details,
        array $keys
    ): ?string {
        // Try each key in priority order
        foreach ($keys as $key) {
            // Skip keys that are not present in the details map
            if (! \array_key_exists($key, $details)) {
                continue;
            }

            // Normalize smalot values to a single string
            $value = $this->normalizeDetailValue($details[$key]);

            // Return the first usable string
            if ($value !== null) {
                return $value;
            }
        }

        // Return null when no string was found
        return null;
    }

    /**
     * Pick the first non-empty title string from smalot `getDetails()` output.
     *
     * @param  array<string, mixed>  $details  Merged smalot PDF metadata map.
     * @return string|null First title candidate, or null.
     */
    public function firstTitleFromDetails(
        array $details
    ): ?string {
        // Walk preferred keys in priority order
        foreach (self::DETAIL_TITLE_KEYS as $key) {
            // Check if this detail key is present
            if (! \array_key_exists($key, $details)) {
                continue;
            }

            // Normalize smalot values (string or single-element arrays)
            $title = $this->normalizeDetailValue($details[$key]);

            // Return the first usable title
            if ($title !== null) {
                return $title;
            }
        }

        // Scan remaining keys for case-insensitive `title` suffix (e.g. variants)
        foreach ($details as $key => $value) {
            // Skip keys already tried explicitly
            // A detail key is a string or an int, and neither loosely equals one of these
            // non-numeric strings unless it is that string.
            // @pest-mutate-ignore: TrueToFalse
            if (\in_array($key, self::DETAIL_TITLE_KEYS, true)) {
                continue;
            }

            // Match keys that end with `:title` or equal `title` (any case)
            $lower = strtolower((string) $key);

            // Check if the key looks like a title field
            if (
                $lower === 'title'
                || str_ends_with($lower, ':title')
            ) {
                $title = $this->normalizeDetailValue($value);

                // Return the first usable title from fallback keys
                if ($title !== null) {
                    return $title;
                }
            }
        }

        // Return null when no title was found
        return null;
    }

    /**
     * Resolve `[absolutePath, tempPathOrNull]` for parsing.
     *
     * @param  Asset  $asset  Statamic file-backed asset with `disk()` and `path()`.
     * @return array{0: string, 1: string|null}|null
     */
    private function resolveReadablePath(
        Asset $asset
    ): ?array {
        // Read the container Flysystem disk wrapper
        /** @var Filesystem $disk */
        $disk = $asset->disk()->filesystem();

        // Relative path within the container
        $rawPath = $asset->path();
        $relativePath = is_string($rawPath) ? $rawPath : '';

        // Prefer a direct filesystem path when the disk exposes one (local)
        if ($disk instanceof FilesystemAdapter) {
            // Build the absolute path for the object
            $absolute = $disk->path($relativePath);

            // Use the live file when it is readable
            if (is_readable($absolute)) {
                return [
                    $absolute,
                    null,
                ];
            }
        }

        // Copy bytes to a temp file for remote or non-path disks
        $tempPath = tempnam(
            sys_get_temp_dir(),
            'statamic-pdf-meta-'
        );

        // Check that tempnam succeeded
        // tempnam() fails only when the system temp directory cannot be written, which no
        // test can arrange without breaking the run around it.
        // @pest-mutate-ignore: FalseToTrue
        if ($tempPath === false) {
            return null;
        }

        // Attempt to read the file contents
        try {
            // Read full file contents from storage
            $binary = $disk->get($relativePath);
        } catch (Throwable) {
            // Remove temp file on failure
            unlink($tempPath);

            // Return null when the file could not be read
            return null;
        }

        // Check that content was returned as a non-empty string
        // Carrying on writes an empty or non-string body to the copy, which the parser then
        // rejects into the same catch, and the finally removes the copy as this branch does.
        // @pest-mutate-ignore: BooleanOrToBooleanAnd, EmptyStringToNotEmpty, RemoveEarlyReturn
        if (
            ! \is_string($binary)
            || $binary === ''
        ) {
            // Remove temp file on failure
            unlink($tempPath);

            // Return null when the file contents are empty
            return null;
        }

        // Write bytes to the temp path for the parser
        file_put_contents(
            $tempPath,
            $binary
        );

        // Return the temp path and the original temp path
        return [
            $tempPath,
            $tempPath,
        ];
    }

    /**
     * Normalize smalot detail values (string or nested single-element lists).
     *
     * @param  mixed  $value  Raw detail value from smalot `getDetails()`.
     * @return string|null Single trimmed string, or null when not usable.
     */
    private function normalizeDetailValue(
        mixed $value
    ): ?string {
        // Accept a scalar string directly
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

        // Walk array values (smalot / RDF may nest lists or lang alternatives)
        if (\is_array($value)) {
            // Unwrap single-element arrays (smalot / PDF quirk)
            // The loop below reads a one-item list the same way, and an empty list yields null
            // either way, so this shortcut changes nothing but the path taken.
            // @pest-mutate-ignore: DecrementInteger, RemoveEarlyReturn
            if (\count($value) === 1) {
                // Recurse into the sole element
                return $this->normalizeDetailValue(reset($value));
            }

            // Scan multi-value lists for the first non-empty string
            foreach ($value as $item) {
                // Recurse into each list element
                $nested = $this->normalizeDetailValue($item);

                // Return the first usable nested string
                if ($nested !== null) {
                    return $nested;
                }
            }
        }

        // Return null when no string was found
        return null;
    }
}
