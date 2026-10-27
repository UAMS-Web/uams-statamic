<?php

declare(strict_types=1);

namespace App\Services\SchemaTemplates;

use Statamic\Fields\Value;

/**
 * Builds JSON-LD object fragments from a `type` and associative `properties` map.
 */
final class SchemaObjectFragmentBuilder
{
    /**
     * Normalize a schema.org `@type` string to an HTTPS vocabulary URI.
     *
     * Accepts a short type (`Person`) or a full `https://schema.org/...` URI.
     *
     * @param  string  $type  Raw `type` tag parameter
     * @return string|null HTTPS `https://schema.org/...` URI, or null when unusable
     */
    public static function normalizedType(
        string $type
    ): ?string {
        // Trim surrounding whitespace from the type token
        $trimmed = trim($type);

        // Check if the trimmed type is empty
        if ($trimmed === '') {
            // Return null to signal unusable type
            return null;
        }

        // Check for an explicit HTTPS schema.org URI
        if (str_starts_with($trimmed, 'https://schema.org/')) {
            // Return the full URI as-is
            return $trimmed;
        }

        // Check for HTTP schema.org and upgrade to HTTPS
        if (str_starts_with($trimmed, 'http://schema.org/')) {
            // Upgrade HTTP to HTTPS by appending the schema.org prefix
            return 'https://schema.org/'.substr(
                $trimmed,
                \strlen('http://schema.org/')
            );
        }

        // Treat remaining values as local type names (e.g., Person, Organization)
        return 'https://schema.org/'.$trimmed;
    }

    /**
     * Coerce the `properties` tag parameter to an array (associative map).
     *
     * Accepts a PHP/Statamic associative array or a JSON object string.
     *
     * @param  mixed  $properties  Bound array or JSON string
     * @return array<string, mixed>
     */
    public static function coercePropertiesToArray(mixed $properties): array
    {
        // Check for null or empty input
        if ($properties === null) {
            return [];
        }

        // Decode a JSON object string when provided
        if (\is_string($properties)) {
            // Trim whitespace from the JSON string
            $trimmed = trim($properties);

            // Check if the trimmed string is empty
            if ($trimmed === '') {
                // Return an empty array to signal unusable properties
                return [];
            }

            // Attempt to decode JSON into an associative array
            /** @var array<string, mixed>|mixed $decoded */
            $decoded = json_decode(
                $trimmed,
                true
            );

            // Check if decoding produced an array
            if (! \is_array($decoded)) {
                // Return an empty array to signal unusable properties
                return [];
            }

            /** @var array<string, mixed> $decoded */
            return $decoded;
        }

        // Accept only array maps at the top level
        if (! \is_array($properties)) {
            // Return an empty array to signal unusable properties
            return [];
        }

        // Return the properties as-is
        /** @var array<string, mixed> $properties */
        return $properties;
    }

    /**
     * Turn top-level `properties` into JSON-LD-ready keys (omits `@type`; nested maps keep `@type`).
     *
     * @param  mixed  $properties  Same inputs as `coercePropertiesToArray`
     * @return array<string, mixed>
     */
    public static function normalizedPropertiesForFragment(
        mixed $properties
    ): array {
        // Coerce input to a plain array
        $raw = self::coercePropertiesToArray($properties);

        // Build normalized entries, reserving `@type` for the tag's `type` param
        $out = [];

        // Walk each top-level property
        foreach ($raw as $key => $value) {
            // Normalize the property name
            $keyString = trim($key);
            // Skip empty or reserved top-level `@type`
            if ($keyString === '') {
                // Skip empty or reserved top-level `@type`
                continue;
            }

            if ($keyString === '@type') {
                // Skip empty or reserved top-level `@type`
                continue;
            }

            // Normalize the value for JSON-LD
            $normalized = self::normalizeJsonLdValue($value);

            // Skip properties that normalize to null
            if ($normalized === null) {
                // Skip properties that normalize to null
                continue;
            }

            // Add the normalized property to the output
            $out[$keyString] = $normalized;
        }

        // Return the normalized properties
        return $out;
    }

    /**
     * JSON-encode a fragment: `@type` plus normalized `properties`.
     *
     * `properties` may be an associative array (e.g. `:properties="my_map"` in Antlers)
     * or a JSON object string. Top-level `@type` inside `properties` is ignored; use the
     * `type` parameter for `@type`. Requires at least one property after `@type` once
     * normalized.
     *
     * @param  string  $type  Raw `type` tag parameter
     * @param  mixed  $properties  Associative array or JSON object string
     * @return string|null Encoded JSON object, or null when unusable
     */
    public static function buildJson(
        string $type,
        mixed $properties = null
    ): ?string {
        // Resolve vocabulary URI for @type
        $atType = self::normalizedType($type);

        // Check if the type did not resolve
        if ($atType === null) {
            // Return null to signal unusable type
            return null;
        }

        // Start the payload with `@type`
        $payload = [
            '@type' => $atType,
        ];

        // Merge normalized properties into the payload
        $extra = self::normalizedPropertiesForFragment($properties);

        // ---
        // Fold each property into the payload
        // ---

        // Loop through each property
        foreach ($extra as $propKey => $propValue) {
            // Add the property to the payload
            $payload[$propKey] = $propValue;
        }

        // Require at least one property besides @type
        if (\count($payload) <= 1) {
            // Return null to signal unusable payload
            return null;
        }

        // Serialize the fragment for JSON-LD embedding
        $json = json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        // Check if encoding failed
        if ($json === false) {
            // Return null to signal unusable JSON
            return null;
        }

        // Return the JSON
        return $json;
    }

    /**
     * Normalize a property value for JSON-LD (public entry for tags and builders).
     *
     * @param  mixed  $value  Property value from Antlers or JSON
     * @return mixed Normalized value, or null to omit
     */
    public static function normalizeJsonLdValuePublic(mixed $value): mixed
    {
        // Delegate to the shared normalizer for scalars, lists, and nested maps
        return self::normalizeJsonLdValue($value);
    }

    /**
     * Normalize a single value for JSON (scalars, lists, nested associative maps).
     *
     * @param  mixed  $value  Property value from Antlers or JSON
     * @return mixed Normalized value, or null to omit
     */
    private static function normalizeJsonLdValue(
        mixed $value
    ): mixed {
        // Check if the value is a Statamic Value
        if ($value instanceof Value) {
            // Unwrap the Statamic Value and normalize the value
            return self::normalizeJsonLdValue($value->value());
        }

        // Check if the value is explicitly null
        if ($value === null) {
            // Return null to signal omitted value
            return null;
        }

        // Check if the value is a boolean
        if (\is_bool($value)) {
            // Return the boolean value as-is
            return $value;
        }

        // Check if the value is an integer or float
        if (
            \is_int($value)
            || \is_float($value)
        ) {
            // Return the integer or float value as-is
            return $value;
        }

        // Check if the value is a string
        if (\is_string($value)) {
            // Strip HTML tags and collapse surrounding whitespace
            $text = trim(strip_tags($value));

            // Check if nothing usable remains
            if ($text === '') {
                // Return null to signal omitted value
                return null;
            }

            // Return the string value
            return $text;
        }

        // Refuse unexpected object types
        if (! \is_array($value)) {
            // Return null to signal omitted value
            return null;
        }

        // Normalize empty lists as omitted
        if ($value === []) {
            // Return null to signal omitted value
            return null;
        }

        // Normalize JSON lists
        if (array_is_list($value)) {
            // Initialize an empty list
            $list = [];

            // Loop through each item in the list
            foreach ($value as $item) {
                // Normalize the list element
                $normalizedItem = self::normalizeJsonLdValue($item);

                // Check if the normalized item is not null (i.e., not omitted)
                if ($normalizedItem !== null) {
                    // Add the normalized item to the list
                    $list[] = $normalizedItem;
                }
            }

            // Check if the list is empty (i.e., all items were omitted)
            if ($list === []) {
                // Return null to signal omitted list
                return null;
            }

            // Return the normalized list
            return $list;
        }

        // Build nested associative objects (e.g. nested Thing); allow `@type` here
        $nested = [];

        // Loop through each nested key
        foreach ($value as $nestedKey => $nestedValue) {
            // Check if the nested key is a string
            if (! \is_string($nestedKey)) {
                // Skip non-string keys (i.e., not a property name)
                continue;
            }

            // Normalize the nested key
            $nestedKeyString = trim($nestedKey);

            // Check if the nested key is empty
            if ($nestedKeyString === '') {
                // Skip empty nested keys
                continue;
            }

            // ---
            // Expand vocabulary strings on nested `@type` keys
            // ---

            // Check if the nested key is `@type`
            if ($nestedKeyString === '@type') {
                // Unwrap Statamic values before reading a string type
                $typeSource = $nestedValue instanceof Value
                    ? $nestedValue->value()
                    : $nestedValue;

                // Check for a string token to normalize
                if (\is_string($typeSource)) {
                    // Normalize the nested type
                    $nestedType = self::normalizedType($typeSource);

                    // Check if the nested type resolved
                    if ($nestedType !== null) {
                        // Add the normalized type to the nested object
                        $nested['@type'] = $nestedType;
                    }
                }

                // Skip to the next nested key
                continue;
            }

            // Normalize the nested value
            $nestedNormalized = self::normalizeJsonLdValue($nestedValue);

            // Check if the nested value is not null (i.e., not omitted)
            if ($nestedNormalized !== null) {
                // Add the normalized nested value to the nested object
                $nested[$nestedKeyString] = $nestedNormalized;
            }
        }

        // Check if the nested object is empty (i.e., all values were omitted)
        if ($nested === []) {
            // Return null to signal omitted nested object
            return null;
        }

        // Return the normalized nested object
        return $nested;
    }
}
