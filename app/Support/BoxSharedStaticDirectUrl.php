<?php

declare(strict_types=1);

namespace App\Support;

use UAMSWeb\Aether\Enums\MimeType;

/**
 * Parses Box.com "direct link" URLs used with shared static file paths.
 *
 * Expects HTTPS URLs on `app.box.com` whose path is under `/shared/static/`
 * with a file extension that maps to a known MIME type via `MimeType`.
 */
final class BoxSharedStaticDirectUrl
{
    /**
     * Expected host for Box shared static direct links.
     *
     * @var string Box application host name
     */
    public const string HOST = 'app.box.com';

    /**
     * Required directory prefix for the URL path (POSIX-style, no trailing slash).
     *
     * @var string Path directory segment for shared static files
     */
    public const string PATH_DIR = '/shared/static';

    /**
     * Extensions whose primary MIME registry entry is not under the `video`
     * category but should still be treated as video for Box direct links.
     *
     * @var list<string> Lowercase file extensions without a leading dot
     */
    private const array VIDEO_EXTENSIONS_FORCE_CATEGORY = [
        'mp4',
    ];

    /**
     * Parse a Box shared static direct URL into extension, MIME type, and category.
     *
     * @param  string  $url  Candidate URL string
     * @return ?array{
     *     extension: string,
     *     mime_type: string,
     *     category: string
     * } Parsed parts, or null when the URL is not a valid Box direct link
     */
    public static function parse(
        string $url
    ): ?array {
        // Normalize leading and trailing whitespace on the candidate URL
        $trimmed = \trim($url);

        // Reject values that are not syntactically valid URLs, the empty string included
        if (
            ! \filter_var(
                $trimmed,
                \FILTER_VALIDATE_URL
            )
        ) {
            return null;
        }

        // Read the URL scheme and require HTTPS only
        $scheme = \parse_url(
            $trimmed,
            \PHP_URL_SCHEME
        );

        // Check if the scheme is missing or not HTTPS
        if (
            ! \is_string($scheme)
            || \strtolower($scheme) !== 'https'
        ) {
            return null;
        }

        // Read the URL host and require the Box application host
        $host = \parse_url(
            $trimmed,
            \PHP_URL_HOST
        );

        // Check if the host is missing or not the expected Box host
        if (
            ! \is_string($host)
            || \strtolower($host) !== self::HOST
        ) {
            return null;
        }

        // Read the URL path for directory and extension checks
        $path = \parse_url(
            $trimmed,
            \PHP_URL_PATH
        );

        // Check if the path is missing; a path with no file name fails the
        // extension or directory check below
        if (! \is_string($path)) {
            return null;
        }

        // Split the path into its segments to read the extension
        $pathInfo = \pathinfo($path);

        // Reject a path whose last segment has no extension
        if (! isset($pathInfo['extension'])) {
            return null;
        }

        // Read the file extension in lowercase for MIME lookup consistency. An
        // empty one (a trailing dot) maps to no MIME type and is rejected below.
        $extension = \strtolower($pathInfo['extension']);

        // Check if the directory matches the required Box shared static prefix
        if (\dirname($path) !== self::PATH_DIR) {
            return null;
        }

        // Resolve the MIME enum case from the file extension
        $mimeCase = MimeType::findByExtension($extension);

        // Reject extensions that do not map to a known MIME type entry
        if (! $mimeCase instanceof MimeType) {
            return null;
        }

        // Read the MIME category from the enum case
        $category = $mimeCase->category();

        // Normalize extensions that are video containers but tagged otherwise in MIME DB
        if (
            \in_array(
                $extension,
                self::VIDEO_EXTENSIONS_FORCE_CATEGORY,
                // The needle and every element are strings, so a loose match is the same.
                // @pest-mutate-ignore: TrueToFalse
                true
            )
        ) {
            $category = 'video';
        }

        // Return structured parse data for validators and the BoxMime modifier
        return [
            'extension' => $extension,
            'mime_type' => $mimeCase->mimeType(),
            'category' => $category,
        ];
    }

    /**
     * Return a MIME type string suitable for HTML `<source type="…">`, or empty.
     *
     * Maps `application/mp4` (from the MIME registry) to `video/mp4` so browsers
     * accept the type hint when playing Box-hosted MP4 files.
     *
     * @param  string  $url  Candidate URL string
     * @return string MIME type when parseable, otherwise an empty string
     */
    public static function mimeTypeString(
        string $url
    ): string {
        // Parse the URL into structured parts when possible
        $parsed = self::parse($url);

        // Return an empty string when parsing fails
        if ($parsed === null) {
            return '';
        }

        // Prefer video/mp4 for .mp4 so `<source type>` matches common playback checks
        if ($parsed['extension'] === 'mp4') {
            return 'video/mp4';
        }

        // Return the enum MIME string for other extensions (e.g. audio/mp3, video/webm)
        return $parsed['mime_type'];
    }
}
