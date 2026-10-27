<?php

declare(strict_types=1);

namespace App\Modifiers;

use App\Support\Http\OutboundUrlGuard;
use Illuminate\Http\Client\PendingRequest;
use Statamic\Modifiers\Modifier;

/**
 * Returns remote file metadata as an associative array matching Statamic asset
 * attribute names.
 *
 * Sends an HTTP HEAD request and maps response headers to: duration, height,
 * width, created_at, updated_at, size, mime_type, extension, url, title,
 * description. Extension is derived from the URL path. Title is taken from the
 * Content-Disposition filename when present. Description is taken from the
 * Content-Description header when present. Values that cannot be determined
 * (e.g. duration, height, width) are null.
 *
 * Use in Antlers for external file attachments, e.g. {{ url | remote_file_meta }}
 */
class RemoteFileMeta extends Modifier
{
    /**
     * Return keys matching Statamic asset attribute names.
     *
     * @return array<int, string>
     */
    private function metaKeys(): array
    {
        return [
            'duration',
            'height',
            'width',
            'created_at',
            'updated_at',
            'size',
            'mime_type',
            'extension',
            'url',
            'title',
            'description',
        ];
    }

    /**
     * Return metadata array for the given URL (keys match Statamic asset attrs).
     *
     * @param  mixed  $value  The URL (string) to check
     * @param  array<int, string>  $params  Any parameters (unused)
     * @param  array<string, mixed>  $context  Contextual values (unused)
     * @return array<string, mixed> Associative array with keys: duration,
     *                              height, width, created_at, updated_at,
     *                              size, mime_type, extension, url, title,
     *                              description
     */
    public function index(
        $value,
        array $params,
        array $context
    ): array {
        // Normalize value to trimmed URL string, or empty string
        $url = \is_string($value) ? trim($value) : '';

        // Check if URL is empty
        if ($url === '') {
            // Free URL before early return
            unset($url);

            // Return empty meta when no URL
            return $this->emptyMeta();
        }

        // Fetch meta from URL via HEAD request
        $meta = $this->fetchMeta($url);

        // Free URL after use
        unset($url);

        // Return the meta array
        return $meta;
    }

    /**
     * Return meta array with all keys set to null.
     *
     * @return array<string, null>
     */
    private function emptyMeta(): array
    {
        // Initialize result array
        $result = [];

        // Loop through meta keys and set each to null
        foreach ($this->metaKeys() as $key) {
            // Assign null to current key
            $result[$key] = null;
        }

        // Free loop variable after foreach
        unset($key);

        // Return the empty meta array
        return $result;
    }

    /**
     * Fetch metadata from URL via HEAD request and build meta array.
     *
     * @param  string  $url  The URL to request
     * @return array<string, mixed>
     */
    private function fetchMeta(
        string $url
    ): array {
        // Start with empty meta
        $meta = $this->emptyMeta();

        // Set URL in meta
        $meta['url'] = $url;

        // Derive extension from URL path
        $extension = $this->extensionFromUrl($url);

        // Check if extension was found
        if ($extension !== null) {
            // Set extension in meta
            $meta['extension'] = $extension;
        }

        // Reject internal/loopback/link-local/private targets before the HEAD
        // request so an editor-supplied URL cannot probe internal services (SSRF).
        // Return the meta gathered so far (url + extension, no remote headers).
        // fetch() refuses a disallowed host itself, throwing before any request (measured:
        // RuntimeException for 10.0.0.5), and the catch below returns the same meta.
        // @pest-mutate-ignore: RemoveEarlyReturn
        if (! OutboundUrlGuard::isAllowedUrl($url)) {
            return $meta;
        }

        // Attempt HEAD request; catch network or parse errors
        try {
            // Send the HEAD request through the egress guard: the connection is
            // pinned to a vetted public IP (no DNS-rebinding TOCTOU) and each
            // redirect hop is independently re-validated and re-pinned (no
            // public→internal pivot). A host that resolves to no vetted public
            // address throws, which the catch below degrades to the safe state.
            $response = OutboundUrlGuard::fetch(
                'HEAD',
                $url,
                static fn (PendingRequest $request): PendingRequest => $request
                    ->timeout(5)
                    ->connectTimeout(3),
                5
            );

            // Check if response was successful
            // $response is unset just below, so carrying on throws on the next header read and
            // the catch returns the same meta.
            // @pest-mutate-ignore: RemoveEarlyReturn
            if (! $response->successful()) {
                // Free response before early return
                unset($response);

                // Return current meta on failure
                return $meta;
            }

            // Get Content-Length header
            $contentLength = $response->header('Content-Length');

            // Check if Content-Length is present and valid
            // An empty header casts to 0, which the second condition already rejects.
            // @pest-mutate-ignore: EmptyStringToNotEmpty
            if (
                $contentLength !== ''
                && (int) $contentLength > 0
            ) {
                // Set human-readable size in meta
                $meta['size'] = $this->formatBytes((int) $contentLength);
            }

            // Get Content-Type header
            $contentType = $response->header('Content-Type');

            // Check if Content-Type is present
            if ($contentType !== '') {
                // Set MIME type in meta (without parameters)
                $meta['mime_type'] = $this->parseMimeType($contentType);
            }

            // Get Content-Disposition header
            $contentDisposition = $response->header('Content-Disposition');

            // Check if Content-Disposition is present
            // An empty header holds no filename, so the title stays null either way.
            // @pest-mutate-ignore: EmptyStringToNotEmpty
            if ($contentDisposition !== '') {
                // Parse suggested filename from header
                $title = $this->titleFromContentDisposition($contentDisposition);

                // Check if title was parsed
                if ($title !== null) {
                    // Set title in meta
                    $meta['title'] = $title;
                }

                // Free title before leaving block
                unset($title);
            }

            // Get Content-Description header
            $contentDescription = $response->header('Content-Description');

            // Trim the description. The HTTP client trims header values (measured:
            // '  padded  ' arrives as 'padded'), so this trim is a no-op.
            // @pest-mutate-ignore: UnwrapTrim
            $contentDescription = trim($contentDescription);

            // Check if Content-Description is present and non-empty
            if ($contentDescription !== '') {
                // Set trimmed description in meta
                $meta['description'] = $contentDescription;
            }

            // Get Last-Modified header
            $lastModified = $response->header('Last-Modified');

            // Check if Last-Modified is present
            // strtotime('') is false, so an empty header leaves the dates null either way.
            // @pest-mutate-ignore: EmptyStringToNotEmpty
            if ($lastModified !== '') {
                // Parse HTTP date to Unix timestamp
                $timestamp = $this->parseHttpDate($lastModified);

                // Check if timestamp parsed successfully
                if ($timestamp !== null) {
                    // Format timestamp as ISO 8601
                    $iso = date(
                        'c',
                        $timestamp
                    );

                    // Set updated_at in meta
                    $meta['updated_at'] = $iso;

                    // Set created_at in meta (same as updated_at from header)
                    $meta['created_at'] = $iso;

                    // Free timestamp and ISO string
                    unset(
                        $timestamp,
                        $iso
                    );
                } else {
                    // Free timestamp on parse failure
                    unset($timestamp);
                }
            }

            // Free response and header variables before leaving try block
            unset(
                $contentDescription,
                $contentDisposition,
                $contentLength,
                $contentType,
                $lastModified,
                $response
            );
        } catch (\Throwable $throwable) {
            // Free exception reference on catch
            unset($throwable);
        }

        // Return the meta array
        return $meta;
    }

    /**
     * Get file extension from URL path.
     *
     * @param  string  $url  The URL (e.g. https://example.com/doc.pdf)
     * @return string|null Extension (e.g. "pdf") or null if none
     */
    private function extensionFromUrl(
        string $url
    ): ?string {
        // Extract path component from URL
        $parsed = parse_url(
            $url,
            PHP_URL_PATH
        );

        // Check if path is missing (null) or the URL is malformed (false). An empty
        // path has no extension, so it returns null below.
        if (! \is_string($parsed)) {
            // Free parsed before early return
            unset($parsed);

            // Return null when no path
            return null;
        }

        // Get file extension from path
        $extension = pathinfo(
            $parsed,
            PATHINFO_EXTENSION
        );

        // Free parsed after use
        unset($parsed);

        // Check if extension is missing or empty
        if ($extension === '') {
            // Free extension before early return
            unset($extension);

            // Return null when no extension
            return null;
        }

        // Normalize extension to lowercase
        $extension = strtolower($extension);

        // Return the extension
        return $extension;
    }

    /**
     * Extract suggested filename (title) from Content-Disposition header.
     *
     * Prefers filename* (RFC 5987), then filename. Returns null if none found.
     *
     * @param  string  $contentDisposition  Raw Content-Disposition header value
     * @return string|null Decoded filename or null
     */
    private function titleFromContentDisposition(
        string $contentDisposition
    ): ?string {
        // ---
        // Prefer filename*= (RFC 5987): charset'language'percent-encoded-value
        // ---

        // Check if filename* parameter is present
        if (
            preg_match(
                '/filename\*\s*=\s*(?:[^\']+\'\')?([^\s;]+)/i',
                $contentDisposition,
                $matches
            ) === 1
        ) {
            // Percent-decode the filename* value
            $decoded = rawurldecode($matches[1]);

            // Free matches after use
            unset($matches);

            // Return decoded filename
            return $decoded;
        }

        // ---
        // Fall back to filename="..." or filename=token
        // ---

        // Check if quoted filename parameter is present
        if (
            preg_match(
                '/filename\s*=\s*"((?:[^"\\\\]|\\\\.)*)"/',
                $contentDisposition,
                $matches
            ) === 1
        ) {
            // Unescape backslashes in quoted value
            $title = stripcslashes($matches[1]);

            // Free matches after use
            unset($matches);

            // Check if title is non-empty
            if ($title !== '') {
                // Return parsed title
                return $title;
            }
        }

        // Check if unquoted filename parameter is present
        if (
            preg_match(
                '/filename\s*=\s*([^;\s]+)/i',
                $contentDisposition,
                $matches
            ) === 1
        ) {
            // Trim surrounding quotes from value
            $title = trim(
                $matches[1],
                '"'
            );

            // Free matches after use
            unset($matches);

            // Check if title is non-empty
            if ($title !== '') {
                // Return parsed title
                return $title;
            }
        }

        // Return null when no filename found
        return null;
    }

    /**
     * Parse Content-Type header to MIME type only (strip charset etc.).
     *
     * @param  string  $contentType  Raw Content-Type header value
     * @return string MIME type without parameters
     */
    private function parseMimeType(
        string $contentType
    ): string {
        // Split on first semicolon (type vs parameters)
        // Only the first part is read, and it is the same for a limit of 2 or 3.
        // @pest-mutate-ignore: IncrementInteger
        $parts = explode(
            ';',
            $contentType,
            2
        );

        // Take the type part and trim whitespace
        $mime = trim($parts[0]);

        // Free parts after use
        unset($parts);

        // Return MIME type without parameters
        return $mime;
    }

    /**
     * Parse HTTP-date (Last-Modified) to Unix timestamp.
     *
     * @param  string  $date  HTTP-date value
     * @return int|null Unix timestamp or null on failure
     */
    private function parseHttpDate(
        string $date
    ): ?int {
        // Parse HTTP-date string to Unix timestamp
        $timestamp = strtotime($date);

        // Check if parsing failed
        // Returning false from this ?int method throws a TypeError, which fetchMeta()'s
        // catch turns into the same null dates; the dates are the last thing it reads.
        // @pest-mutate-ignore: FalseToTrue, RemoveEarlyReturn
        if ($timestamp === false) {
            // Return null on parse failure
            return null;
        }

        // Return the timestamp
        return $timestamp;
    }

    /**
     * Format bytes as human-readable size (e.g. "19 KB", "1.2 MB").
     *
     * @param  int  $bytes  Size in bytes
     * @return string Formatted string
     */
    private function formatBytes(
        int $bytes
    ): string {
        // Define size units for human-readable output
        $units = [
            'B',
            'KB',
            'MB',
            'GB',
            'TB',
        ];

        // Compute unit index from log base 1024
        $i = (int) floor(log(
            $bytes,
            1024
        ));

        // Clamp index to valid unit range
        $i = min(
            $i,
            \count($units) - 1
        );

        // Index is a valid units offset for real byte counts
        /** @var int<0, 4> $i */

        // Compute numeric size in chosen unit
        $size = $bytes / (1024 ** $i);

        // Check if size is in bytes (no decimal needed)
        if ($i === 0) {
            // Free size before early return
            unset($size);

            // Return bytes with unit
            return $bytes.' '.$units[$i];
        }

        // Choose decimal places by magnitude
        $decimals = $size >= 100 ? 0 : ($size >= 10 ? 1 : 2);

        // Format number with unit
        $formatted = number_format(
            $size,
            $decimals
        ).' '.$units[$i];

        // Free locals before return
        unset(
            $decimals,
            $size,
            $units,
            $i
        );

        // Return formatted size string
        return $formatted;
    }
}
