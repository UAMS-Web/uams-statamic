<?php

declare(strict_types=1);

namespace App\Tags;

use App\Services\SchemaTemplates\SchemaObjectFragmentBuilder;
use Statamic\Fields\LabeledValue;
use Statamic\Fields\Value;
use Statamic\Tags\Parameters;
use Statamic\Tags\Tags;

/**
 * Antlers tag: emit a list of bare schema.org term names as a JSON array of
 * full `https://schema.org/<Term>` URIs.
 *
 * Used for enumeration-valued properties whose source field stores the bare
 * member names — e.g. the `schema_type` fieldtype backing
 * `schema_medicalspecialty`, which augments to a plain list of `MedicalSpecialty`
 * member names (`['Cardiovascular', 'Dermatology']`). schema.org enumeration
 * members are identified by their URI, so the bare names are normalized to full
 * URIs (the same normalization the engine applies to `@type`).
 *
 * Parameters:
 *   - terms: bound value of the source field (a list of term-name strings, or
 *     the augmented `{key,value,label}` / `LabeledValue` shapes).
 *
 * Output is empty when no usable term resolves. Duplicate URIs are removed while
 * preserving first-seen order. A single term still emits a one-element array so
 * the property type stays stable.
 *
 * Example:
 *   {{ schema_org_term_uris :terms="schema_medicalspecialty" }}
 *
 * @property Parameters $params
 */
class SchemaOrgTermUris extends Tags
{
    /**
     * Registered Antlers handle `{{ schema_org_term_uris }}`.
     *
     * @var string
     */
    protected static $handle = 'schema_org_term_uris';

    /**
     * Build the JSON array of normalized schema.org URIs.
     *
     * @return string JSON array, or empty string when no term resolves
     */
    public function index(): string
    {
        // Read the bound source value
        $raw = $this->params->get('terms');

        // Unwrap a Statamic field value
        if ($raw instanceof Value) {
            // Read the inner value for coercion
            $raw = $raw->value();
        }

        // Treat a non-array value as no terms
        if (! \is_array($raw)) {
            // Return an empty string
            return '';
        }

        // Accumulate normalized URIs, de-duplicated, in first-seen order
        $uris = [];

        // Walk each selected term
        foreach ($raw as $element) {
            // Resolve the bare term name for this element
            $name = $this->termNameFromElement($element);

            // Skip elements that yield no usable name
            if ($name === null) {
                // Move to the next element
                continue;
            }

            // Normalize the bare name to a full schema.org URI
            $uri = SchemaObjectFragmentBuilder::normalizedType($name);

            // Keep only resolvable, not-yet-seen URIs. Equivalent: every URI starts with
            // https://, so a loose in_array() can never read two of them as numbers.
            // @pest-mutate-ignore: TrueToFalse
            if ($uri !== null && ! \in_array($uri, $uris, true)) {
                // Append the normalized URI
                $uris[] = $uri;
            }
        }

        // Emit nothing when no URI resolved
        if ($uris === []) {
            // Return an empty string to omit the property
            return '';
        }

        // Serialize the URI array for JSON-LD embedding
        $json = json_encode($uris, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // Return the JSON array, or an empty string when encoding fails
        return $json === false ? '' : $json;
    }

    /**
     * Resolve a single element to its bare schema.org term name.
     *
     * @param  mixed  $element  A plain string, an augmented `{key,value,label}`
     *                          row, or a `LabeledValue`
     * @return string|null The term name, or null when it cannot be resolved
     */
    private function termNameFromElement(mixed $element): ?string
    {
        // Unwrap a Statamic field value
        if ($element instanceof Value) {
            // Read the inner value for coercion
            $element = $element->value();
        }

        // Read the underlying value of an augmented labeled option
        if ($element instanceof LabeledValue) {
            // Use the option's stored value as the term name
            $element = $element->value();
        }

        // A plain string is already the term name
        if (\is_string($element)) {
            // Return it untrimmed: normalizedType() trims it and returns null for a blank name
            return $element;
        }

        // The augmented multi-select shape exposes the name under `value`/`key`
        if (\is_array($element)) {
            // Prefer the stored value, then the option key
            $name = $element['value'] ?? $element['key'] ?? null;

            // Return the name when it is a string, untrimmed for the same reason
            return \is_string($name) ? $name : null;
        }

        // Anything else is not a resolvable term name
        return null;
    }
}
