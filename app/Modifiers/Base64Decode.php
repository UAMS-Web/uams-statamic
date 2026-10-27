<?php

declare(strict_types=1);

namespace App\Modifiers;

use App\Services\Encoding\UrlSafeBase64StringCodec;
use InvalidArgumentException;
use Statamic\Modifiers\Modifier;

/**
 * Antlers modifier: decode URL-safe Base64 without padding.
 *
 * Uses {@see UrlSafeBase64StringCodec::decode}. Invalid tokens yield an empty
 * string so templates do not throw.
 *
 * Antlers usage:
 *
 * - `{{ $token | base64_decode }}`
 */
class Base64Decode extends Modifier
{
    /**
     * Decode URL-safe Base64 into the original string.
     *
     * @param  mixed  $value  Encoded token string or wrapped Statamic\Fields\Value
     * @param  array<int|string, mixed>  $params  Unused
     * @param  array<int|string, mixed>  $context  Unused
     * @return string Decoded string, or empty when input is invalid
     */
    public function index(
        mixed $value,
        array $params,
        array $context
    ): string {
        // Coerce Antlers input to the encoded token string
        $encoded = UrlSafeBase64StringCodec::plainStringFromAntlersValue($value);

        // Attempt strict decode
        try {
            $plain = UrlSafeBase64StringCodec::decode($encoded);
        } catch (InvalidArgumentException) {
            // Unset local variables to free memory
            unset(
                $encoded
            );

            // Return empty string for malformed tokens
            return '';
        }

        // Unset local variables to free memory
        unset(
            $encoded
        );

        // Return the decoded plain string
        return $plain;
    }
}
