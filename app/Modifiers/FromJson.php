<?php

declare(strict_types=1);

namespace App\Modifiers;

use Statamic\Fields\Value;
use Statamic\Modifiers\Modifier;

/**
 * Antlers modifier: decode a JSON string to PHP values (`json_decode` inverse of
 * core `to_json`).
 *
 * Usage:
 *
 * - `{{ $json | from_json }}` — associative arrays (same as `json_decode(..., true)`)
 * - `{{ $json | from_json:false }}` or `| from_json(0)` — `stdClass` for objects
 *
 * Invalid JSON or an empty string after trim returns `null`. `Statamic\Fields\Value`
 * inputs are unwrapped.
 */
class FromJson extends Modifier
{
    /**
     * Decode JSON text to arrays (default) or objects.
     *
     * @param  mixed  $value  JSON string or `Value` wrapping a string
     * @param  array<int|string, mixed>  $params  Optional first flag: falsy or `objects` for `json_decode` without associative
     * @param  array<int|string, mixed>  $context  Unused Statamic modifier context
     * @return mixed Decoded value, or `null` when input is empty or JSON is invalid
     */
    public function index(
        mixed $value,
        array $params,
        array $context
    ): mixed {
        // Unwrap Statamic Value wrappers when present
        if ($value instanceof Value) {
            $value = $value->value();
        }

        // Reject non-string values after unwrap
        if (! \is_string($value)) {
            return null;
        }

        // Trim surrounding whitespace from the JSON payload
        $trimmed = trim($value);

        // Return null when nothing remains to decode
        if ($trimmed === '') {
            return null;
        }

        // Resolve associative flag from the first parameter (default: true)
        $associative = $this->resolveAssociativeDecodeFlag($params);

        // Decode JSON without throwing; check json_last_error after
        $decoded = json_decode(
            $trimmed,
            $associative
        );

        // Return null when the payload is not valid JSON
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $decoded;
    }

    /**
     * Whether `json_decode` should return associative arrays for objects.
     *
     * @param  array<int|string, mixed>  $params  Modifier arguments
     */
    private function resolveAssociativeDecodeFlag(
        array $params
    ): bool {
        // Read the first positional parameter when present
        $flag = $params[0] ?? null;

        // Unwrap a single-element list form some Antlers call sites
        if (
            \is_array($flag)
            && \count($flag) === 1
        ) {
            $flag = reset($flag);
        }

        // Treat explicit object-mode tokens as non-associative
        return ! in_array($flag, [false, 0, '0', 'false'], true) && (! \is_string($flag) || strtolower(trim($flag)) !== 'objects');
    }
}
