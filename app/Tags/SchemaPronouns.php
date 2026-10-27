<?php

declare(strict_types=1);

namespace App\Tags;

use Statamic\Fields\Value;
use Statamic\Tags\Parameters;
use Statamic\Tags\Tags;

/**
 * Antlers tag: JSON-encode Schema.org `PropertyValue` nodes for pronoun slots
 * (Wikidata `propertyID` per slot).
 *
 * String parameters are coerced from tag context with trim only (no
 * `strip_tags` or `sanitize`), so values are not double-encoded when the
 * template already applies `| strip_tags | sanitize` (or similar) in Antlers.
 *
 * Parameters:
 *   - name: display label (required; empty after trimming yields no output).
 *   - description: accepted for parity with templates; not emitted on each
 *     PropertyValue (only non-empty pronoun slots are encoded)
 *   - nominative: surface form for nominative case (subject form)
 *   - accusative: surface form for accusative case (object form)
 *   - dependent_possessive: surface form for dependent possessive (determiner)
 *   - independent_possessive: surface form for independent possessive pronoun
 *   - reflexive: surface form for reflexive pronoun
 *
 * When `name` is the only non-empty parameter, output is the trimmed `name`
 * string only (no JSON). Otherwise output is a JSON array whose first element
 * is the `name` string, followed by one `PropertyValue` object per non-empty
 * pronoun slot—or empty string when no slot has a value.
 *
 * Usage:
 *   {{ schema_pronouns name="he/him" description="..." nominative="he" ... }}
 */
class SchemaPronouns extends Tags
{
    /**
     * Antlers tag handle used in templates as `{{ schema_pronouns }}`
     *
     * @var string
     */
    protected static $handle = 'schema_pronouns';

    /**
     * Ordered pronoun slots: JSON-LD `name`, Wikidata `propertyID`, and `description` text.
     *
     * @var list<array{slot: string, propertyId: string, description: string}>
     */
    private const array PRONOUN_SLOT_DEFINITIONS = [
        [
            'slot' => 'nominative',
            'propertyId' => 'https://www.wikidata.org/wiki/Q131105',
            'description' => 'Nominative case (subject form)',
        ],
        [
            'slot' => 'accusative',
            'propertyId' => 'https://www.wikidata.org/wiki/Q146078',
            'description' => 'Accusative case (object form)',
        ],
        [
            'slot' => 'dependentPossessive',
            'propertyId' => 'https://www.wikidata.org/wiki/Q5051',
            'description' => 'Dependent possessive / possessive determiner',
        ],
        [
            'slot' => 'independentPossessive',
            'propertyId' => 'https://www.wikidata.org/wiki/Q1502460',
            'description' => 'Independent possessive pronoun',
        ],
        [
            'slot' => 'reflexive',
            'propertyId' => 'https://www.wikidata.org/wiki/Q953129',
            'description' => 'Reflexive pronoun',
        ],
    ];

    /**
     * Build JSON array (name string then PropertyValue maps), or plain name text.
     *
     * @return string JSON array with leading name string, trimmed name only, or empty string
     *                when `name` is empty after trimming or when no pronoun slots are filled
     *                (after name-only branch)
     */
    public function index(): string
    {
        // Coerce the required name to a trimmed string for gating output
        $nameRaw = $this->unwrapParam('name');

        // If name is empty/null/false, return empty string early
        if (
            in_array($nameRaw, ['', null, false], true)
        ) {
            return '';
        }

        // Coerce the required name to a trimmed string for gating output
        $name = $this->coerceParamString($nameRaw);

        // Check if the name is empty after trimming
        if ($name === '') {
            // Return empty string when no usable label is provided
            return '';
        }

        // Coerce optional pronoun parameters to trimmed strings
        $description = $this->unwrapAndCoerceString('description');
        $nominative = $this->unwrapAndCoerceString('nominative');
        $accusative = $this->unwrapAndCoerceString('accusative');
        $dependentPossessive = $this->unwrapAndCoerceString('dependent_possessive');
        $independentPossessive = $this->unwrapAndCoerceString('independent_possessive');
        $reflexive = $this->unwrapAndCoerceString('reflexive');

        // Check whether only name is populated (all other parameters empty)
        if (
            $description === ''
            && $nominative === ''
            && $accusative === ''
            && $dependentPossessive === ''
            && $independentPossessive === ''
            && $reflexive === ''
        ) {
            // Return the trimmed name as plain text (no JSON wrapper)
            return $name;
        }

        // Build PropertyValue entries only for non-empty pronoun slots
        $propertyValues = $this->buildPropertyValueList(
            $nominative,
            $accusative,
            $dependentPossessive,
            $independentPossessive,
            $reflexive
        );

        // Check whether every pronoun slot was empty
        if ($propertyValues === []) {
            // Return empty string so callers can omit the property
            return '';
        }

        // Prepend the display name as the first list element (JSON string)
        $payload = [
            $name,
            ...$propertyValues,
        ];

        // Attempt to encode the mixed list as JSON for JSON-LD embedding
        try {
            // Encode with stable Unicode and unescaped slashes
            return json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
        } catch (\JsonException) {
            // Return empty string when encoding fails
            return '';
        }
    }

    /**
     * Build the PropertyValue list for non-empty pronoun slots only.
     *
     * @param  string  $nominative  Trimmed nominative form, or empty to omit
     * @param  string  $accusative  Trimmed accusative form, or empty to omit
     * @param  string  $dependentPossessive  Trimmed dependent possessive form, or empty to omit
     * @param  string  $independentPossessive  Trimmed independent possessive form, or empty to omit
     * @param  string  $reflexive  Trimmed reflexive form, or empty to omit
     * @return list<array<string, string>> Ordered PropertyValue maps for each non-empty slot
     */
    private function buildPropertyValueList(
        string $nominative,
        string $accusative,
        string $dependentPossessive,
        string $independentPossessive,
        string $reflexive
    ): array {
        // Slot surface forms in the same order as PRONOUN_SLOT_DEFINITIONS
        $surfaceForms = [
            $nominative,
            $accusative,
            $dependentPossessive,
            $independentPossessive,
            $reflexive,
        ];

        // Initialize the list of PropertyValue maps
        $items = [];

        // Build one PropertyValue per non-empty slot, preserving slot order
        foreach (self::PRONOUN_SLOT_DEFINITIONS as $index => $definition) {
            $surface = $surfaceForms[$index];

            // Skip empty slots
            if ($surface === '') {
                unset($surface);

                continue;
            }

            $items[] = $this->propertyValue(
                $definition['slot'],
                $definition['propertyId'],
                $surface,
                $definition['description']
            );

            unset($surface);
        }

        unset(
            $definition,
            $index,
            $surfaceForms
        );

        // Return the ordered PropertyValue list
        return $items;
    }

    /**
     * Build one schema.org PropertyValue map.
     *
     * @param  string  $slotName  Grammatical slot id (camelCase) for `name`
     * @param  string  $propertyID  Wikidata concept URI
     * @param  string  $value  Surface pronoun string
     * @param  string  $description  Human-readable case label
     * @return array<string, string> Single PropertyValue object
     */
    private function propertyValue(
        string $slotName,
        string $propertyID,
        string $value,
        string $description
    ): array {
        // Return a PropertyValue-shaped map for JSON-LD
        return [
            '@type' => 'PropertyValue',
            'name' => $slotName,
            'propertyID' => $propertyID,
            'value' => $value,
            'description' => $description,
        ];
    }

    /**
     * Coerce an already-unwrapped tag value to a trimmed string (no strip_tags or sanitize).
     *
     * @param  mixed  $raw  Value after `unwrapParam()`; not a `Statamic\Fields\Value`
     * @return string Trimmed string, or empty when not representable as text
     */
    private function coerceParamString(
        mixed $raw
    ): string {
        // Check for null or false as empty text sources
        if (
            $raw === null
            || $raw === false
        ) {
            // Return empty string for absent values
            return '';
        }

        // Coerce booleans to a minimal string form
        if ($raw === true) {
            // Represent true as a non-empty token for rare edge cases
            $raw = '1';
        }

        // Reject arrays and objects as non-scalar text sources
        if (
            \is_array($raw)
            || \is_object($raw)
        ) {
            // Return empty string when the value is not a scalar
            return '';
        }

        // Coerce numbers to string for trimming
        $asString = \is_scalar($raw)
            ? (string) $raw
            : '';

        // Trim outer whitespace only
        return trim($asString);
    }

    /**
     * Read a tag parameter, unwrap `Value`, and coerce to a trimmed string.
     *
     * @param  string  $key  Parameter name
     * @return string Trimmed string for JSON-LD slots
     */
    private function unwrapAndCoerceString(
        string $key
    ): string {
        return $this->coerceParamString(
            $this->unwrapParam($key)
        );
    }

    /**
     * Read a tag parameter and unwrap Statamic Value instances.
     *
     * @param  string  $key  Parameter name
     * @return mixed Raw parameter value after optional Value unwrap
     */
    private function unwrapParam(
        string $key
    ): mixed {
        // Read the raw parameter value
        /** @var Parameters $params */
        $params = $this->params;
        $raw = $params->get($key);

        // Check if the parameter is wrapped as a Statamic Value
        if ($raw instanceof Value) {
            // Unwrap the Value for downstream string handling
            return $raw->value();
        }

        // Return the raw parameter when not wrapped
        return $raw;
    }
}
