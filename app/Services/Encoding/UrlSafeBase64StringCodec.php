<?php

declare(strict_types=1);

namespace App\Services\Encoding;

use InvalidArgumentException;
use Statamic\Fields\Value;

/**
 * Encode and decode UTF-8 strings using URL-safe Base64 without padding.
 *
 * Used by Antlers modifiers `base64_encode` and `base64_decode` and callable
 * from any PHP context.
 */
final class UrlSafeBase64StringCodec
{
    /**
     * Encode a plain string to URL-safe Base64 without `=` padding.
     *
     * @param  string  $plain  Raw string (e.g. a taxonomy path)
     * @return string Encoded token using `-` and `_` instead of `+` and `/`
     */
    public static function encode(
        string $plain
    ): string {
        // Build standard Base64 then make alphabet URL-safe and strip padding
        $base64 = base64_encode($plain);

        // Replace reserved URL characters and drop padding equals signs
        $encoded = rtrim(
            strtr(
                $base64,
                '+/',
                '-_'
            ),
            '='
        );

        // Unset local variables to free memory
        unset(
            $base64
        );

        // Return the URL-safe token
        return $encoded;
    }

    /**
     * Decode a URL-safe Base64 string (no padding) back to the original string.
     *
     * @param  string  $encoded  Token from `encode`
     * @return string Original plain string
     *
     * @throws InvalidArgumentException When the payload is not valid Base64
     */
    public static function decode(
        string $encoded
    ): string {
        // Treat empty token as empty plain text
        if ($encoded === '') {
            return '';
        }

        // Restore standard Base64 alphabet
        $normalized = strtr(
            $encoded,
            '-_',
            '+/'
        );

        // Restore padding length required by `base64_decode`
        $remainder = \strlen($normalized) % 4;

        // Check if padding is required
        if ($remainder !== 0) {
            // Append the correct number of padding characters
            $normalized .= str_repeat(
                '=',
                4 - $remainder
            );
        }

        // Decode strictly so malformed input fails
        $binary = base64_decode(
            $normalized,
            true
        );

        // Check if decoding failed
        if ($binary === false) {
            // Unset local variables before throwing
            unset(
                $binary,
                $normalized,
                $remainder
            );

            // Signal invalid encoded input
            throw new InvalidArgumentException(
                'Invalid URL-safe Base64 string.'
            );
        }

        // Copy decoded bytes for return
        $result = $binary;

        // Unset local variables to free memory
        unset(
            $binary,
            $normalized,
            $remainder
        );

        // Return the decoded plain string
        return $result;
    }

    /**
     * Coerce an Antlers modifier value to a string for encode/decode.
     *
     * Unwraps a single {@see Value}; scalars become strings; other types yield
     * an empty string.
     *
     * @param  mixed  $value  Raw Antlers modifier value
     * @return string Plain string to encode or decode
     */
    public static function plainStringFromAntlersValue(
        mixed $value
    ): string {
        // Track the working value after optional unwrap
        $candidate = $value;

        // Unwrap a Statamic field Value when present
        if ($candidate instanceof Value) {
            // Read the inner stored value
            $unwrapped = $candidate->value();

            // Unset local variables no longer needed
            unset(
                $candidate
            );

            // Continue with the unwrapped value
            $candidate = $unwrapped;

            // Unset local variables no longer needed
            unset(
                $unwrapped
            );
        }

        // Check for null after unwrap
        if ($candidate === null) {
            // Unset local variables to free memory
            unset(
                $candidate
            );

            // Return empty string for null
            return '';
        }

        // Check if the candidate is already a string
        if (\is_string($candidate)) {
            // Copy for return
            $out = $candidate;

            // Unset local variables to free memory
            unset(
                $candidate
            );

            // Return the string unchanged
            return $out;
        }

        // Check if the candidate is scalar (bool, int, float)
        if (is_scalar($candidate)) {
            // Cast scalar to string
            $out = (string) $candidate;

            // Unset local variables to free memory
            unset(
                $candidate
            );

            // Return the string form
            return $out;
        }

        // Unset local variables to free memory
        unset(
            $candidate
        );

        // Return empty string for unsupported types (e.g. arrays)
        return '';
    }
}
