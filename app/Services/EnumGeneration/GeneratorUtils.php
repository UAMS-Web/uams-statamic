<?php

declare(strict_types=1);

namespace App\Services\EnumGeneration;

use DateTime;
use DateTimeZone;

/**
 * Shared helpers for enum generator commands.
 *
 * Provides HTTP utilities, timestamped output path building,
 * and small string helpers used by multiple generator commands.
 */
final class GeneratorUtils
{
    /**
     * Normalize headers array to cURL-compatible header lines.
     *
     * Converts an associative array ("Header" => "Value") or a list of full
     * header lines into a normalized array of header strings for cURL.
     *
     * @param  array<int|string,string>  $headers  Headers to normalize
     * @return array<int, string> Array of header lines
     */
    private static function normalizeHeaders(
        array $headers
    ): array {
        // Initialize header lines array
        $headerLines = [];

        // Loop through headers and normalize each to a full header line
        foreach ($headers as $key => $value) {
            // ---
            // If the key is an integer, add the value as a string;
            // otherwise, add the key and value as a string
            // ---
            $headerLines[] = \is_int($key) ? (string) $value : sprintf('%s: %s', $key, $value);
        }

        // Clean up variables to free up memory
        unset(
            $headers
        );

        // Return the normalized header lines
        return $headerLines;
    }

    /**
     * Perform a simple HTTP GET and return the response body or null.
     *
     * The $headers array may be associative ("Header" => "Value") or a list
     * of full header lines. TLS peer and hostname verification are enabled
     * via cURL defaults for HTTPS.
     *
     * @param  string  $url  Remote URL to fetch
     * @param  array<int|string,string>  $headers  Optional headers
     * @param  int  $timeoutSeconds  Timeout in seconds
     * @param  string  $userAgent  User-Agent header value
     * @return ?string Response body or null on error/non-200
     */
    public static function httpGet(
        string $url,
        array $headers = ['Accept' => 'application/json'],
        int $timeoutSeconds = 60,
        string $userAgent = 'UAMS-Statamic-Enum-Generator/1.0'
    ): ?string {
        // Initialize cURL handle
        $ch = curl_init();

        // Normalize headers to cURL-compatible format
        $headerLines = self::normalizeHeaders($headers);

        // Narrow scalar option values for the cURL options array
        /** @var non-empty-string $curlUrl */
        $curlUrl = $url;
        /** @var non-empty-string $curlUserAgent */
        $curlUserAgent = $userAgent;

        // Set cURL options
        curl_setopt_array(
            $ch,
            [
                CURLOPT_URL => $curlUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => $timeoutSeconds,
                CURLOPT_USERAGENT => $curlUserAgent,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HTTPHEADER => $headerLines,
            ]
        );

        // Execute the cURL request
        $body = curl_exec($ch);

        // Get the HTTP response code
        $httpCode = curl_getinfo(
            $ch,
            CURLINFO_HTTP_CODE
        );

        // Close the cURL handle
        curl_close($ch);

        // Clean up variables to free up memory
        unset(
            $headerLines,
            $headers,
            $timeoutSeconds,
            $userAgent,
            $url,
        );

        // Check if the request failed
        if (
            $body === false
            || $httpCode !== 200
        ) {
            // Clean up variables to free up memory
            unset(
                $body,
                $httpCode,
            );

            // Return null to indicate failure
            return null;
        }

        // Clean up variables to free up memory
        unset(
            $httpCode,
        );

        // Return the response body
        /** @var string $body */
        return $body;
    }

    /**
     * Download a URL directly to a destination file path.
     *
     * The $headers array may be associative or a list of header lines.
     * Returns true only if transfer succeeds with a 200 HTTP status.
     *
     * @param  string  $url  Remote URL to fetch
     * @param  string  $destPath  Destination file path to write
     * @param  array<int|string,string>  $headers  Optional headers
     * @param  int  $timeoutSeconds  Timeout in seconds
     * @param  string  $userAgent  User-Agent header value
     * @return bool True on success
     */
    public static function downloadToFile(
        string $url,
        string $destPath,
        array $headers = ['Accept' => 'text/csv,application/csv,*/*'],
        int $timeoutSeconds = 120,
        string $userAgent = 'UAMS-Statamic-Enum-Generator/1.0'
    ): bool {
        // Open the destination file for binary writing
        $fp = fopen(
            $destPath,
            'wb'
        );

        // Check if the file pointer is false
        if ($fp === false) {
            // Clean up variables to free up memory
            unset(
                $headers,
                $timeoutSeconds,
                $userAgent,
                $url,
                $destPath,
            );

            // Return false to indicate failure
            return false;
        }

        // Initialize cURL handle
        $ch = curl_init($url);

        // Normalize headers to cURL-compatible format
        $headerLines = self::normalizeHeaders($headers);

        // Narrow the user-agent option value for the cURL options array
        /** @var non-empty-string $curlUserAgent */
        $curlUserAgent = $userAgent;

        // Set cURL options
        curl_setopt_array(
            $ch,
            [
                CURLOPT_FILE => $fp,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => $timeoutSeconds,
                CURLOPT_USERAGENT => $curlUserAgent,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HTTPHEADER => $headerLines,
            ]
        );

        // Execute the cURL request
        $ok = curl_exec($ch) !== false
            && curl_getinfo(
                $ch,
                CURLINFO_HTTP_CODE
            ) === 200;

        // Close the cURL handle
        curl_close($ch);

        // Close the file pointer
        fclose($fp);

        // Clean up variables to free up memory
        unset(
            $headerLines,
            $headers,
            $timeoutSeconds,
            $userAgent,
            $url,
            $destPath,
            $ch,
            $fp,
        );

        // Return true if the request was successful
        return $ok;
    }

    /**
     * Build an `app/Enums/{Base}_generated_{timestamp}.php` path using a
     * timezone without mutating global PHP timezone settings.
     *
     * @param  string  $enumBaseName  Base name (e.g., 'ZipCode', 'County')
     * @param  string  $tz  Timezone identifier (default America/Chicago)
     * @return string Absolute application path for output file
     */
    public static function makeTimestampedEnumPath(
        string $enumBaseName,
        string $tz = 'America/Chicago'
    ): string {
        // Create a new DateTime object with the current time and the specified timezone
        $dt = new DateTime(
            'now',
            new DateTimeZone($tz)
        );

        // Format the filename
        $filename = \sprintf(
            '%s_generated_%s.php',
            $enumBaseName,
            $dt->format(
                'Y-m-d_H-i-s'
            )
        );

        // Build the path to the generated enum file
        $path = app_path('Enums/'.$filename);

        // Clean up variables to free up memory
        unset(
            $dt,
            $filename,
            $enumBaseName,
            $tz,
        );

        // Return the path to the generated enum file
        return $path;
    }

    /**
     * Remove BOM (Byte Order Mark) and other invisible characters from a string.
     * Commonly needed when parsing CSV files from external APIs.
     *
     * @param  string  $value  The string to clean
     * @return string The cleaned string
     */
    public static function removeBom(
        string $value
    ): string {
        // Remove UTF-8 BOM
        /** @var string $value */
        $value = \preg_replace(
            '/^\xEF\xBB\xBF/',
            '',
            $value
        );

        // Remove UTF-16 BE BOM
        /** @var string $value */
        $value = \preg_replace(
            '/^\xFE\xFF/',
            '',
            $value
        );

        // Remove UTF-16 LE BOM
        /** @var string $value */
        $value = \preg_replace(
            '/^\xFF\xFE/',
            '',
            $value
        );

        // Remove UTF-32 BE BOM
        /** @var string $value */
        $value = \preg_replace(
            '/^\x00\x00\xFE\xFF/',
            '',
            $value
        );

        // Remove UTF-32 LE BOM
        /** @var string $value */
        $value = \preg_replace(
            '/^\xFF\xFE\x00\x00/',
            '',
            $value
        );

        // Return the cleaned string with all BOM characters removed
        return $value;
    }

    /**
     * Escape single quotes for embedding inside single-quoted PHP literals.
     *
     * @param  string  $value  Input string
     * @return string Escaped string
     */
    public static function escapeSingleQuotes(
        string $value
    ): string {
        // Escape single quotes
        $escaped = \str_replace(
            "'",
            "\\'",
            $value
        );

        // Clean up variables to free up memory
        unset(
            $value,
        );

        // Return the escaped string
        return $escaped;
    }

    /**
     * Convert a string into UPPER_SNAKE_CASE, removing apostrophes.
     *
     * @param  string  $value  Input string
     * @return string Upper snake string without leading/trailing underscores
     */
    public static function toUpperSnake(
        string $value
    ): string {
        // Remove apostrophes
        $noApostrophes = \str_replace(
            "'",
            '',
            $value
        );

        // Convert to uppercase snake case
        /** @var string $underscored */
        $underscored = \preg_replace(
            '/[^a-zA-Z0-9]+/',
            '_',
            $noApostrophes
        );
        $snake = \strtoupper($underscored);

        // Trim leading/trailing underscores
        $snake = \trim(
            $snake,
            '_'
        );

        // Clean up variables to free up memory
        unset(
            $value,
            $noApostrophes,
        );

        // Return the upper snake string without leading/trailing underscores
        return $snake;
    }
}
