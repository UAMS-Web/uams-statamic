<?php

declare(strict_types=1);

namespace App\Tags;

use App\Services\SchemaTemplates\SchemaObjectFragmentBuilder;
use Statamic\Fields\Value;
use Statamic\Tags\Parameters;
use Statamic\Tags\Tags;

/**
 * Antlers tag: emit a JSON-LD object fragment from `type` and `properties` only.
 *
 * `properties` must resolve to a real array or a JSON object string—not PHP array
 * syntax trapped inside a quoted HTML/Antlers attribute. A quoted value is a
 * literal string; variables such as `height` are not evaluated there, and
 * `SchemaObjectFragmentBuilder` will not treat it as key/value pairs.
 *
 * Example: assign an associative array to a variable, then bind it:
 *   {{ $height_properties = [
 *       'value' => height,
 *       'unitCode' => 'E37',
 *       'unitText' => 'pixel',
 *   ] }}
 *   {{ schema_object type="https://schema.org/QuantitativeValue" :properties="$height_properties"
 *       :enabled="height" }}
 *
 * When Antlers context includes the `schema_object` field (same handle as this tag),
 * `{{ schema_object ... }}` resolves as that variable and the remainder is parsed as
 * modifiers (e.g. `Modifier [type] not found`). Use the alias `schema_object_tag` in
 * those scopes (nested replicator rows, `{{ if schema_object }}` / `{{ else }}`, etc.).
 *
 * Optional `enabled` (default when omitted: emit): literal or bound (`:enabled="height"`).
 * When false after normalization (e.g., null, false, 0, empty string), the tag outputs
 * nothing even if `properties` is valid.
 *
 * Alternative: `:properties="some_var"` where `some_var` is a context value that
 * is already a JSON object string (valid JSON with double-quoted keys), e.g., from
 * another field or layer—not `=>` syntax inside quotes.
 *
 * Parameters:
 *   - type: Short name (e.g., `Person`) or full `https://schema.org/...` URI
 *     (e.g., `https://schema.org/Person`). Statamic `Value` instances are
 *     unwrapped (`:type="my_field"`).
 *   - properties: Bound map (`:properties="my_map"`) or JSON string as above. Keys
 *     become JSON keys; top-level `@type` in the map is ignored (use `type`).
 *     Nested maps and lists are supported.
 *   - enabled: Optional; when present and “off”, output is suppressed (empty string).
 *
 * Output: JSON object string, or empty string when disabled or nothing valid is produced
 */
class SchemaObject extends Tags
{
    /**
     * Registered Antlers handle `{{ schema_object }}`
     *
     * @var string
     */
    protected static $handle = 'schema_object';

    /**
     * Alternate handle for the same tag when context exposes a `schema_object` field
     * (entries field handle) that would shadow the primary tag name.
     *
     * @var list<string>
     */
    protected static $aliases = [
        'schema_object_tag',
    ];

    /**
     * Build JSON text for the current parameters and Antlers context.
     *
     * @return string JSON object, or empty string when no fragment is produced
     */
    public function index(): string
    {
        // Check whether the tag should emit output when `enabled` is present
        if (! $this->shouldEmitFragment()) {
            // Return an empty string to signal that the fragment should not be emitted
            return '';
        }

        // Read the type parameter
        /** @var Parameters $params */
        $params = $this->params;
        $typeRaw = $params->get('type');

        // Unwrap Statamic Value when type is field-bound
        if ($typeRaw instanceof Value) {
            $typeRaw = $typeRaw->value();
        }

        // Return early when type is absent or not a string; a blank type is left to
        // buildJson(), which returns null for it
        if (! \is_string($typeRaw)) {
            return '';
        }

        // Read the properties map (array or JSON string)
        $properties = $params->get('properties');

        // Encode the fragment via the shared builder
        $json = SchemaObjectFragmentBuilder::buildJson(
            $typeRaw,
            $properties
        );

        // Coalesce PHP absence to an empty Antlers output
        return $json ?? '';
    }

    /**
     * Whether to render JSON-LD for this tag invocation.
     *
     * When the `enabled` parameter is omitted, output is allowed. When it is set,
     * only boolean true, non-zero numbers, and non-empty strings (after trim) pass;
     * null, false, 0, 0.0, and '' block output.
     *
     * @return bool True when the fragment should be built
     */
    private function shouldEmitFragment(): bool
    {
        // ---
        // Treat omitted `enabled` as allowing output
        // ---

        // Check if the `enabled` parameter is present
        /** @var Parameters $params */
        $params = $this->params;
        if (! $params->has('enabled')) {
            // Return true to signal that the fragment should be emitted
            return true;
        }

        // Read the raw `enabled` flag from tag parameters
        $enabled = $params->get('enabled');

        // Check if the `enabled` parameter is a Statamic Value
        if ($enabled instanceof Value) {
            // Unwrap the Statamic Value and read the value
            $enabled = $enabled->value();
        }

        // Check for explicit false
        if ($enabled === false) {
            // Return false to signal that the fragment should not be emitted
            return false;
        }

        // Check for null
        if ($enabled === null) {
            // Return false to signal that the fragment should not be emitted
            return false;
        }

        // Check for empty string after trim
        if (
            \is_string($enabled)
            && trim($enabled) === ''
        ) {
            // Return false to signal that the fragment should not be emitted
            return false;
        }

        // Check for integer or float zero
        if (
            $enabled === 0
            || $enabled === 0.0
        ) {
            // Return false to signal that the fragment should not be emitted
            return false;
        }

        // Check for string zero (e.g., from a text field)
        // Allow all other values (e.g., positive dimensions, true, non-empty strings)
        // Return false to signal that the fragment should not be emitted
        return $enabled !== '0';
    }
}
