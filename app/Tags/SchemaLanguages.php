<?php

declare(strict_types=1);

namespace App\Tags;

use App\Services\SchemaTemplates\SchemaObjectFragmentBuilder;
use Statamic\Fields\LabeledValue;
use Statamic\Fields\Value;
use Statamic\Tags\Parameters;
use Statamic\Tags\Tags;

/**
 * Antlers tag: emit a multi-select of BCP-47 languages as JSON-LD `Language`
 * nodes for `knowsLanguage`.
 *
 * The `language` fieldtype (a BCP-47 `select`, `multiple`) augments to a list of
 * `{key, value, label}` rows — `value` is the BCP-47 code (e.g. `es`), `label`
 * the display name (e.g. `Spanish, Castilian`). Each becomes a `Language` node:
 * `name` from the label, `alternateName` from the code. A plain list of code
 * strings is also accepted (code becomes both the lookup and the `alternateName`,
 * with no `name`).
 *
 * Parameters:
 *   - languages: bound value of the language field.
 *
 * Output is empty when no language resolves. A single language still emits a
 * one-element array so the property type stays stable.
 *
 * Example:
 *   {{ schema_languages :languages="interpreter_language" }}
 *
 * @property Parameters $params
 */
class SchemaLanguages extends Tags
{
    /**
     * Registered Antlers handle `{{ schema_languages }}`.
     *
     * @var string
     */
    protected static $handle = 'schema_languages';

    /**
     * Memoized `SchemaObjectFragmentBuilder::normalizedType()` for `Language`.
     */
    private static ?string $normalizedLanguageType = null;

    /**
     * Build the JSON array of Language nodes.
     *
     * @return string JSON array, or empty string when no language resolves
     */
    public function index(): string
    {
        // Read the bound source value
        $raw = $this->params->get('languages');

        // Unwrap a Statamic field value
        if ($raw instanceof Value) {
            // Read the inner value for coercion
            $raw = $raw->value();
        }

        // Treat a non-array value as no languages
        if (! \is_array($raw)) {
            // Return an empty string
            return '';
        }

        // Resolve the Language type URI (cached)
        $languageType = $this->cachedNormalizedLanguageType();

        // Emit nothing when the Language type is unusable
        if ($languageType === null) {
            // Return an empty string
            return '';
        }

        // Accumulate one Language node per resolved code, in first-seen order
        $nodes = [];

        // Track seen codes so duplicates are not emitted twice
        $seen = [];

        // Walk each selected language
        foreach ($raw as $element) {
            // Resolve the BCP-47 code and display label for this element
            [$code, $label] = $this->codeAndLabelFromElement($element);
            // Skip elements that yield no usable code
            if ($code === null) {
                // Move to the next element
                continue;
            }

            if (\in_array($code, $seen, true)) {
                // Move to the next element
                continue;
            }

            // Record the code as seen
            $seen[] = $code;

            // Start the Language node with its @type and code alternateName
            $node = [
                '@type' => $languageType,
                'alternateName' => $code,
            ];

            // Add the display name when a distinct label is available
            if ($label !== null && $label !== $code) {
                // Order name before alternateName for readability
                $node = [
                    '@type' => $languageType,
                    'name' => $label,
                    'alternateName' => $code,
                ];
            }

            // Append the completed Language node
            $nodes[] = $node;
        }

        // Emit nothing when no language resolved
        if ($nodes === []) {
            // Return an empty string to omit the property
            return '';
        }

        // Serialize the Language array for JSON-LD embedding
        $json = json_encode($nodes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // Return the JSON array, or an empty string when encoding fails
        return $json === false ? '' : $json;
    }

    /**
     * Resolve a single element to its [code, label] pair.
     *
     * @param  mixed  $element  A plain code string, an augmented `{key,value,label}`
     *                          row, or a `LabeledValue`
     * @return array{0: string|null, 1: string|null} The BCP-47 code and label
     */
    private function codeAndLabelFromElement(mixed $element): array
    {
        // Unwrap a Statamic field value
        if ($element instanceof Value) {
            // Read the inner value for coercion
            $element = $element->value();
        }

        // Read code and label from an augmented labeled option
        if ($element instanceof LabeledValue) {
            // Resolve the code and label from the labeled value
            $code = $element->value();
            $label = $element->label();

            return [
                \is_string($code) && trim($code) !== '' ? trim($code) : null,
                \is_string($label) && trim($label) !== '' ? trim($label) : null,
            ];
        }

        // A plain string is the code with no label
        if (\is_string($element)) {
            // Trim the code string
            $trimmed = trim($element);

            // Return the code (no label) when non-empty
            return [$trimmed === '' ? null : $trimmed, null];
        }

        // The augmented multi-select shape exposes code under value/key, label under label
        if (\is_array($element)) {
            // Prefer the stored value, then the option key, for the code
            $code = $element['value'] ?? $element['key'] ?? null;

            // Read the display label when present
            $label = $element['label'] ?? null;

            return [
                \is_string($code) && trim($code) !== '' ? trim($code) : null,
                \is_string($label) && trim($label) !== '' ? trim($label) : null,
            ];
        }

        // Anything else yields no language
        return [null, null];
    }

    /**
     * Resolved HTTPS schema.org `@type` URI for `Language` (memoized).
     *
     * @return string|null Same as `SchemaObjectFragmentBuilder::normalizedType()`
     */
    private function cachedNormalizedLanguageType(): ?string
    {
        // Resolve and memoize the normalized type on first use
        if (self::$normalizedLanguageType === null) {
            self::$normalizedLanguageType = SchemaObjectFragmentBuilder::normalizedType(
                'Language'
            );
        }

        // Return the cached normalized type URI
        return self::$normalizedLanguageType;
    }
}
