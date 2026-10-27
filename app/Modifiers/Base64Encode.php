<?php

declare(strict_types=1);

namespace App\Modifiers;

use App\Services\Encoding\UrlSafeBase64StringCodec;
use Statamic\Modifiers\Modifier;

/**
 * Antlers modifier: URL-safe Base64 encode without padding.
 *
 * Uses {@see UrlSafeBase64StringCodec::encode}; reversible with `base64_decode`.
 *
 * Antlers usage:
 *
 * - `{{ $path | base64_encode }}`
 */
class Base64Encode extends Modifier
{
    /**
     * Encode the value as URL-safe Base64 without padding.
     *
     * @param  mixed  $value  String or Statamic\Fields\Value wrapping a string
     * @param  array<int|string, mixed>  $params  Unused
     * @param  array<int|string, mixed>  $context  Unused
     * @return string Encoded token
     */
    public function index(
        mixed $value,
        array $params,
        array $context
    ): string {
        // Coerce Antlers input to a plain string
        $plain = UrlSafeBase64StringCodec::plainStringFromAntlersValue($value);

        // Encode using the shared codec
        $encoded = UrlSafeBase64StringCodec::encode($plain);

        // Unset local variables to free memory
        unset(
            $plain
        );

        // Return the encoded string
        return $encoded;
    }
}
