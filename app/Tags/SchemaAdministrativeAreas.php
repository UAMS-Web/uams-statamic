<?php

declare(strict_types=1);

namespace App\Tags;

use App\Services\SchemaTemplates\SchemaObjectFragmentBuilder;
use Illuminate\Support\Collection;
use Statamic\Fields\LabeledValue;
use Statamic\Fields\Value;
use Statamic\Tags\Parameters;
use Statamic\Tags\Tags;

/**
 * Antlers tag: emit taxonomy terms as JSON-LD `AdministrativeArea` nodes for
 * `areaServed`.
 *
 * Backs the location's `regions` field (an Arkansas-region `terms` taxonomy
 * field). The field augments to a single `Term` (when `max_items: 1`) or a list
 * of terms; each term's title becomes an `AdministrativeArea` `name`. (UAMS-Web/uams-statamic-archive#962 will
 * later derive the served area from coordinates; this is the manual-taxonomy
 * interim.)
 *
 * Parameters:
 *   - areas: bound value of the terms field (a single term, a list of terms, or
 *     the augmented `{title,...}` / `LabeledValue` / string shapes).
 *
 * Names are passed through `strip_tags` for defense in depth. Output is empty
 * when no term resolves; a single term still emits a one-element array so the
 * property type stays stable. Duplicate names are removed in first-seen order.
 *
 * Example:
 *   {{ schema_administrative_areas :areas="regions" }}
 *
 * @property Parameters $params
 */
class SchemaAdministrativeAreas extends Tags
{
    /**
     * Registered Antlers handle `{{ schema_administrative_areas }}`.
     *
     * @var string
     */
    protected static $handle = 'schema_administrative_areas';

    /**
     * Memoized `SchemaObjectFragmentBuilder::normalizedType()` for `AdministrativeArea`.
     */
    private static ?string $normalizedAreaType = null;

    /**
     * Build the JSON array of AdministrativeArea nodes.
     *
     * @return string JSON array, or empty string when no term resolves
     */
    public function index(): string
    {
        // Read the bound source value
        $raw = $this->params->get('areas');

        // Unwrap a Statamic field value
        if ($raw instanceof Value) {
            // Read the inner value for coercion
            $raw = $raw->value();
        }

        // Normalize to a list: a single term becomes a one-element list
        $elements = $this->toList($raw);

        // Nothing to emit when the list is empty. Equivalent without the return: an empty
        // list builds no nodes, which returns the same '' below.
        // @pest-mutate-ignore: RemoveEarlyReturn
        if ($elements === []) {
            // Return an empty string
            return '';
        }

        // Resolve the AdministrativeArea type URI (cached)
        $areaType = $this->cachedNormalizedAreaType();

        // Emit nothing when the type is unusable
        if ($areaType === null) {
            // Return an empty string
            return '';
        }

        // Accumulate one node per resolved name, de-duplicated, in first-seen order
        $nodes = [];

        // Track seen names so duplicates are not emitted twice
        $seen = [];

        // Walk each selected term
        foreach ($elements as $element) {
            // Resolve the area display name for this element
            $name = $this->nameFromElement($element);
            // Skip elements with no usable name or an already-seen name
            if ($name === null) {
                // Move to the next element
                continue;
            }

            if (\in_array($name, $seen, true)) {
                // Move to the next element
                continue;
            }

            // Record the name as seen
            $seen[] = $name;

            // Append the AdministrativeArea node
            $nodes[] = [
                '@type' => $areaType,
                'name' => $name,
            ];
        }

        // Emit nothing when no name resolved
        if ($nodes === []) {
            // Return an empty string to omit the property
            return '';
        }

        // Serialize the area array for JSON-LD embedding
        $json = json_encode($nodes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // Return the JSON array, or an empty string when encoding fails
        return $json === false ? '' : $json;
    }

    /**
     * Normalize a bound value into a list of term-like elements.
     *
     * A `terms` field with `max_items: 1` augments to a single `Term`; with more
     * it augments to a list. Both are reduced to a flat list here. A single
     * `LabeledValue`, and a single augmented row (an array with a `title`,
     * `label` or `value` key), are each one element rather than a list (UAMS-Web/uams-statamic-archive#2988).
     *
     * @return array<int, mixed>
     */
    private function toList(mixed $raw): array
    {
        // The schema engine passes a terms field as a Laravel collection
        if ($raw instanceof Collection) {
            // Return the collection's items as a plain list. Equivalent without
            // array_values(): index() walks the list with foreach and ignores its keys.
            // @pest-mutate-ignore: UnwrapArrayValues
            return array_values($raw->all());
        }

        // A single term object (has a title) is wrapped into a one-element list
        if (\is_object($raw) && method_exists($raw, 'title')) {
            // Return the single term as a list
            return [$raw];
        }

        // A single augmented option is wrapped into a one-element list
        if ($raw instanceof LabeledValue) {
            // Return the single option as a list
            return [$raw];
        }

        // A single augmented row carries a name key, which a list of rows does
        // not, so it is one element rather than a list of its fields
        if (
            \is_array($raw)
            && (\array_key_exists('title', $raw) || \array_key_exists('label', $raw) || \array_key_exists('value', $raw))
        ) {
            // Return the single row as a list
            return [$raw];
        }

        // A list passes through as its values
        if (\is_array($raw)) {
            // Return the array values (equivalent without array_values(), as above)
            // @pest-mutate-ignore: UnwrapArrayValues
            return array_values($raw);
        }

        // A single non-empty scalar name is wrapped into a one-element list. Equivalent
        // blank check: cleanName() turns a blank name into null, so no node is built.
        // @pest-mutate-ignore: EmptyStringToNotEmpty, UnwrapTrim
        if (\is_string($raw) && trim($raw) !== '') {
            // Return the single name as a list
            return [$raw];
        }

        // Anything else (null, empty string, other scalar) yields no terms
        return [];
    }

    /**
     * Resolve a single element to its sanitized display name.
     *
     * @param  mixed  $element  A `Term`, an augmented `{title,...}` row, a
     *                          `LabeledValue`, or a plain string
     * @return string|null The display name, or null when it cannot be resolved
     */
    private function nameFromElement(mixed $element): ?string
    {
        // Unwrap a Statamic field value
        if ($element instanceof Value) {
            // Read the inner value for coercion
            $element = $element->value();
        }

        // Read the underlying value of an augmented labeled option
        if ($element instanceof LabeledValue) {
            // Prefer the label, then the stored value
            return $this->cleanName($element->label() ?? $element->value());
        }

        // Read a taxonomy term's title (LocalizedTerm / Term both expose title())
        if (\is_object($element) && method_exists($element, 'title')) {
            // Use the term's title as the area name
            return $this->cleanName($element->title());
        }

        // A plain string is used directly as the name
        if (\is_string($element)) {
            // Clean the string name
            return $this->cleanName($element);
        }

        // An augmented row exposes the name under `title`, then `label`/`value`
        if (\is_array($element)) {
            // Read the first available name-like key
            $name = $element['title'] ?? $element['label'] ?? $element['value'] ?? null;

            // Clean a string name when present
            return \is_string($name) ? $this->cleanName($name) : null;
        }

        // Anything else yields no name
        return null;
    }

    /**
     * Strip tags and trim a candidate name, returning null when empty.
     */
    private function cleanName(mixed $name): ?string
    {
        // Reject non-string candidates
        if (! \is_string($name)) {
            // Return null when the name is not a string
            return null;
        }

        // Strip any markup and trim surrounding whitespace
        $clean = trim(strip_tags($name));

        // Return the cleaned name when non-empty
        return $clean === '' ? null : $clean;
    }

    /**
     * Resolved HTTPS schema.org `@type` URI for `AdministrativeArea` (memoized).
     *
     * @return string|null Same as `SchemaObjectFragmentBuilder::normalizedType()`
     */
    private function cachedNormalizedAreaType(): ?string
    {
        // Resolve and memoize the normalized type on first use
        if (self::$normalizedAreaType === null) {
            self::$normalizedAreaType = SchemaObjectFragmentBuilder::normalizedType(
                'AdministrativeArea'
            );
        }

        // Return the cached normalized type URI
        return self::$normalizedAreaType;
    }
}
