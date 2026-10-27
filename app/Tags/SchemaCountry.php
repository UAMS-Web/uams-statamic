<?php

declare(strict_types=1);

namespace App\Tags;

use App\Enums\Country;
use App\Services\SchemaTemplates\SchemaJsonLdAtIdDeduplication;
use App\Services\SchemaTemplates\SchemaJsonLdFragmentAtId;
use App\Services\SchemaTemplates\SchemaOutputService;
use App\Services\SchemaTemplates\SchemaTemplateEmitContext;
use App\Services\SchemaTemplates\SchemaTemplateEmitContextRuntimeStack;
use Statamic\Fields\Value;
use Statamic\Tags\Context;
use Statamic\Tags\Parameters;
use Statamic\Tags\Tags;

/**
 * Antlers tag: emit JSON-LD `Country` object(s) with ISO 3166-1 identifiers.
 *
 * Parameters:
 *   - code: ISO 3166-1 alpha-2, alpha-3, or numeric code (per `code_type`), as a
 *     string, or a list of such codes (e.g. YAML list bound with `:code`)
 *   - code_type: `alpha2` (default), `alpha3`, or `numeric`
 *
 * When `code` is a string, output is one JSON `Country` object.
 *
 * When `code` is an array, output is a JSON array of `Country` objects.
 *
 * Unknown codes are skipped.
 *
 * Country labels and codes come from `App\Enums\Country` (`country_name()` for the
 * exonym, `alpha2()` / `alpha3()` / `numeric()`, `requiresThe()` for “the …”).
 * JSON-LD uses the schema.org `name` key for that label. When `useThe` is true,
 * `alternateName` is set to `the ` plus the same label; otherwise
 * `alternateName` is omitted.
 *
 * Each `identifier` entry uses `PropertyValue` with Wikidata property IRIs for
 * P297 (alpha-2), P298 (alpha-3), and P299 (numeric). The `value` for a given
 * code system uses the tag `code` when `code_type` matches that system; otherwise
 * the enum supplies the canonical value for that system.
 *
 * `@id` uses `SchemaJsonLdFragmentAtId` (same stem and URL-safe Base64 fragment
 * scheme as taxonomy terms in `SchemaThingFromRelationshipResolver`). When
 * `SchemaOutputService::ANTLERS_SCHEMA_TEMPLATE_EMIT_CONTEXT` is present, the tag
 * shares `SchemaTemplateEmitContext` with the graph: it emits `{"@id":…}` only if
 * that id already has a full node in the pass map. The first full `Country` JSON
 * decoded from Antlers is registered on that map by
 * `SchemaJsonLdAtIdDeduplication::dedupeWithSharedEmitMapAndDecodeFirstFullRetention`
 * in `SchemaOutputService` (not inside this tag), so pre-tag registration does
 * not collapse the first inline body before decode.
 *
 * `SchemaObjectFragmentBuilder` is not used here; it normalizes `@type` URIs for
 * other fragments, while this tag keeps short `Country` / `PropertyValue` names
 * like `SchemaLanguage`.
 *
 * Example:
 *   {{ schema_country code="US" }}
 *   {{ schema_country :code="nationality" }}
 */
class SchemaCountry extends Tags
{
    /**
     * Registered Antlers handle `{{ schema_country }}`
     *
     * @var string Statamic tag handle for this class
     */
    protected static $handle = 'schema_country';

    /**
     * Wikidata P297 IRI: ISO 3166-1 alpha-2 country code
     */
    private const string WIKIDATA_ISO_3166_1_ALPHA_2 = 'https://www.wikidata.org/wiki/Property:P297';

    /**
     * Wikidata P298 IRI: ISO 3166-1 alpha-3 country code
     */
    private const string WIKIDATA_ISO_3166_1_ALPHA_3 = 'https://www.wikidata.org/wiki/Property:P298';

    /**
     * Wikidata P299 IRI: ISO 3166-1 numeric country code
     */
    private const string WIKIDATA_ISO_3166_1_NUMERIC = 'https://www.wikidata.org/wiki/Property:P299';

    /**
     * Lazy map: uppercased ISO 3166-1 alpha-3 code => enum case (built once per process).
     *
     * @var array<string, Country>|null
     */
    private static ?array $countryByUpperAlpha3 = null;

    /**
     * Lazy map: canonical numeric key (string of int, leading zeros stripped) => enum case.
     *
     * @var array<int, Country>|null
     */
    private static ?array $countryByNumericIntKey = null;

    /**
     * Build JSON-LD for the tag parameters.
     *
     * @return string JSON `Country` object, JSON array of `Country` objects, `[]` when `code` is an
     *                empty list, or empty string when a single string code cannot be resolved
     */
    public function index(): string
    {
        // Read the code parameter from the tag
        /** @var Parameters $params */
        $params = $this->params;
        $codeRaw = $params->get('code');

        // If code is empty/null/false, return empty string early
        if (
            in_array($codeRaw, ['', null, false], true)
        ) {
            return '';
        }

        // Unwrap Statamic Value when the code is field-bound
        if ($codeRaw instanceof Value) {
            $codeRaw = $codeRaw->value();
        }

        // Read the code_type parameter from the tag
        $codeTypeRaw = $params->get('code_type', 'alpha2');

        // Unwrap Statamic Value when code_type is field-bound
        if ($codeTypeRaw instanceof Value) {
            $codeTypeRaw = $codeTypeRaw->value();
        }

        // Normalize code_type to alpha2, alpha3, or numeric
        $codeType = $this->normalizeCodeType($codeTypeRaw);

        // Branch on list input vs scalar string input
        if (\is_array($codeRaw)) {
            // Normalize the list to trimmed non-empty string codes
            $codes = $this->normalizeCodeStringList($codeRaw);

            // Return an empty JSON array when the list has no usable codes
            if ($codes === []) {
                return $this->encodeJson([]);
            }

            // Resolve emit context and deduplication service once per tag (not per list item)
            $emitContext = $this->resolveEmitContextFromAntlersScope();
            $atIdDeduplication = app(SchemaJsonLdAtIdDeduplication::class);

            // Build one Country map per resolved code (skip unknown codes)
            $items = [];

            // Walk each code in list order
            foreach ($codes as $codeStr) {
                // Resolve the Country enum case from this code and code_type
                $country = $this->resolveCountry($codeStr, $codeType);

                // Skip entries that do not match a known country
                if (! $country instanceof Country) {
                    continue;
                }

                // Append the Country fragment (compact @id when emit map says so)
                $items[] = $this->resolveCountryPayload(
                    $country,
                    $codeStr,
                    $codeType,
                    $emitContext,
                    $atIdDeduplication
                );
            }

            unset(
                $atIdDeduplication,
                $emitContext
            );

            // Serialize the list of Country objects to JSON
            return $this->encodeJson($items);
        }

        // Coerce scalar code to a trimmed string
        $codeStr = \is_string($codeRaw) ? trim($codeRaw) : '';

        // Return empty output when the code is missing
        if ($codeStr === '') {
            return '';
        }

        // Resolve the Country enum case from the code and type
        $country = $this->resolveCountry($codeStr, $codeType);

        // Return empty output when the country is unknown
        if (! $country instanceof Country) {
            return '';
        }

        // Resolve emit context and deduplication service once for this invocation
        $emitContext = $this->resolveEmitContextFromAntlersScope();
        $atIdDeduplication = app(SchemaJsonLdAtIdDeduplication::class);

        // Build the single Country JSON-LD map (or compact @id when appropriate)
        $payload = $this->resolveCountryPayload(
            $country,
            $codeStr,
            $codeType,
            $emitContext,
            $atIdDeduplication
        );

        unset(
            $atIdDeduplication,
            $emitContext
        );

        // Serialize the single Country object to JSON
        return $this->encodeJson($payload);
    }

    /**
     * Read shared emit maps from Antlers when this tag runs inside schema property
     * templates.
     *
     * @return SchemaTemplateEmitContext|null Context from `SchemaOutputService`, or null
     */
    private function resolveEmitContextFromAntlersScope(): ?SchemaTemplateEmitContext
    {
        $fromStack = SchemaTemplateEmitContextRuntimeStack::peek();

        if ($fromStack instanceof SchemaTemplateEmitContext) {
            return $fromStack;
        }

        // Read the emit context object from the live Antlers context
        /** @var Context $context */
        $context = $this->context;
        $raw = $context->get(
            SchemaOutputService::ANTLERS_SCHEMA_TEMPLATE_EMIT_CONTEXT
        );

        // Accept only the expected context type
        if ($raw instanceof SchemaTemplateEmitContext) {
            return $raw;
        }

        unset($raw);

        return null;
    }

    /**
     * Build a full `Country` map or a compact `{"@id":…}` reference from the shared
     * emit map (decode-time registration lives in `SchemaOutputService`).
     *
     * @param  Country  $country  Resolved enum case
     * @param  string  $codeStr  Original code string for this slot
     * @param  string  $codeType  Normalized `alpha2`, `alpha3`, or `numeric`
     * @param  SchemaTemplateEmitContext|null  $emitContext  Shared graph emit map from Antlers, or null
     * @param  SchemaJsonLdAtIdDeduplication  $atIdDeduplication  Service for compact `@id` references
     * @return array<string, mixed>|array<string, string> Full Country map or compact reference map
     */
    private function resolveCountryPayload(
        Country $country,
        string $codeStr,
        string $codeType,
        ?SchemaTemplateEmitContext $emitContext,
        SchemaJsonLdAtIdDeduplication $atIdDeduplication
    ): array {
        // Compute the stable canonical @id for dedupe and registration
        $atId = $this->canonicalAtIdForCountry($country);

        // Prefer a compact reference when this @id already has a full node
        if ($emitContext instanceof SchemaTemplateEmitContext) {
            $compactRef = $atIdDeduplication
                ->compactToReferenceIfFullNodeAlreadyEmitted(
                    $emitContext->emittedNodeIds,
                    $atId
                );

            // Return only the @id reference when the graph pass map says to
            if ($compactRef !== null) {
                unset(
                    $atId,
                    $emitContext
                );

                return $compactRef;
            }
        }

        // Build the full inline Country JSON-LD map (reuse computed @id)
        $map = $this->buildCountryMap($country, $codeStr, $codeType, $atId);

        unset(
            $atId,
            $emitContext
        );

        return $map;
    }

    /**
     * Build the `Country` property map for JSON-LD output.
     *
     * @param  Country  $country  Resolved enum case
     * @param  string  $codeStr  Original code string for this slot
     * @param  string  $codeType  Normalized `alpha2`, `alpha3`, or `numeric`
     * @param  string|null  $atId  Canonical `@id` when already computed, or null
     * @return array<string, mixed> Map with `@id`, `@type`, `name`, optional `alternateName`, and `identifier`
     */
    private function buildCountryMap(
        Country $country,
        string $codeStr,
        string $codeType,
        ?string $atId = null
    ): array {
        // Read canonical metadata from the resolved enum case
        $name = $country->country_name();
        $alpha2 = $country->alpha2();
        $alpha3 = $country->alpha3();
        $numeric = $country->numeric();

        // Resolve the canonical JSON-LD @id when the caller did not supply it
        if ($atId === null) {
            $atId = $this->canonicalAtIdForCountry($country);
        }

        // Start the Country map with @id, @type, and name
        $map = [
            '@id' => $atId,
            '@type' => 'Country',
            'name' => $name,
        ];

        // Add alternateName when the country name takes a definite article
        if ($country->requiresThe()) {
            $map['alternateName'] = 'the '.$name;
        }

        // Choose alpha-2 identifier value from the tag code or enum handle
        $alpha2Value = $codeType === 'alpha2'
            ? strtoupper($codeStr)
            : $alpha2;

        // Choose alpha-3 identifier value from the tag code or enum
        $alpha3Value = $codeType === 'alpha3'
            ? strtoupper($codeStr)
            : $alpha3;

        // Choose numeric identifier value from the tag code or enum
        $numericValue = $codeType === 'numeric'
            ? $codeStr
            : $numeric;

        // Attach structured ISO identifiers as PropertyValue nodes
        $map['identifier'] = [
            $this->propertyValueIdentifier(self::WIKIDATA_ISO_3166_1_ALPHA_2, 'ISO 3166-1 alpha-2 code', $alpha2Value),
            $this->propertyValueIdentifier(self::WIKIDATA_ISO_3166_1_ALPHA_3, 'ISO 3166-1 alpha-3 code', $alpha3Value),
            $this->propertyValueIdentifier(self::WIKIDATA_ISO_3166_1_NUMERIC, 'ISO 3166-1 numeric code', $numericValue),
        ];

        return $map;
    }

    /**
     * Build the JSON-LD `@id` for a country (same scheme as taxonomy term
     * fragments in `SchemaThingFromRelationshipResolver`).
     *
     * @param  Country  $country  Resolved enum case (alpha-2 is the case value)
     * @return string Absolute URI: application base URL, `#`, URL-safe Base64 of
     *                `/country/{lowercased country/{alpha-2}}`
     */
    private function canonicalAtIdForCountry(
        Country $country
    ): string {
        // Lowercase the `country/{alpha-2}` path segment for the fragment source
        $countryPathSegment = strtolower(
            'country/'.$country->alpha2()
        );

        // Prefix with `/` so the encoded path matches term URL path shape
        $path = '/'.$countryPathSegment;

        // Build the same absolute @id shape as taxonomy term fragments
        return SchemaJsonLdFragmentAtId::atIdFromRootRelativePath($path);
    }

    /**
     * Encode a value to JSON with project flags, or empty string on failure.
     *
     * @param  array<string, mixed>|list<array<string, mixed>>  $value  One `Country` map or a list of maps from `buildCountryMap`
     * @return string UTF-8 JSON with unescaped slashes, or empty string when encoding throws
     */
    private function encodeJson(
        array $value
    ): string {
        // Attempt to encode the value to JSON for Antlers output
        try {
            return json_encode(
                $value,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );
        } catch (\JsonException) {
            return '';
        }
    }

    /**
     * Normalize a list parameter to trimmed non-empty string codes.
     *
     * @param  array<int|string, mixed>  $list  Raw list from the tag
     * @return list<string> Ordered trimmed codes from string entries only
     */
    private function normalizeCodeStringList(
        array $list
    ): array {
        // Initialize the output list
        $out = [];

        // Walk each list entry in order
        foreach ($list as $item) {
            // Unwrap Statamic Value when the list item is field-bound
            if ($item instanceof Value) {
                $item = $item->value();
            }

            // Skip non-string list entries
            if (! \is_string($item)) {
                continue;
            }

            // Trim whitespace from the segment
            $segment = trim($item);

            // Skip empty segments
            if ($segment === '') {
                continue;
            }

            // Keep the segment in list order
            $out[] = $segment;
        }

        return $out;
    }

    /**
     * Build one PropertyValue map for an ISO identifier entry.
     *
     * @param  string  $propertyId  Wikidata property IRI
     * @param  string  $name  Human-readable property name
     * @param  string  $value  Identifier value text
     * @return array<string, string> PropertyValue map with `@type`, `propertyID`, `name`, and `value`
     */
    private function propertyValueIdentifier(
        string $propertyId,
        string $name,
        string $value
    ): array {
        return [
            '@type' => 'PropertyValue',
            'propertyID' => $propertyId,
            'name' => $name,
            'value' => $value,
        ];
    }

    /**
     * Normalize `code_type` to alpha2, alpha3, or numeric.
     *
     * @param  mixed  $raw  Tag parameter value
     * @return string One of `alpha2`, `alpha3`, `numeric`
     */
    private function normalizeCodeType(
        mixed $raw
    ): string {
        // Coerce non-string input to an empty token
        $token = \is_string($raw) ? strtolower(trim($raw)) : '';

        // Treat empty, null, or unknown tokens as alpha2 (default)
        if (
            in_array($token, ['', 'alpha2', 'alpha-2'], true)
        ) {
            return 'alpha2';
        }

        // Map alpha-3 aliases to alpha3
        if (
            $token === 'alpha3'
            || $token === 'alpha-3'
        ) {
            return 'alpha3';
        }

        // Map numeric aliases
        if ($token === 'numeric') {
            return 'numeric';
        }

        // Default unknown values to alpha2 for predictable resolution
        return 'alpha2';
    }

    /**
     * Resolve a `Country` case from a code string and code type.
     *
     * @param  string  $code  Trimmed code from the tag
     * @param  string  $codeType  `alpha2`, `alpha3`, or `numeric`
     * @return Country|null The matching case, or null when not found
     */
    private function resolveCountry(
        string $code,
        string $codeType
    ): ?Country {
        // Resolve by ISO 3166-1 alpha-2 when requested
        if ($codeType === 'alpha2') {
            // Uppercase the alpha-2 code for enum matching
            $alpha2 = strtoupper($code);

            // Attempt direct enum resolution from the alpha-2 value
            return Country::tryFrom($alpha2);
        }

        // Resolve by ISO 3166-1 alpha-3 when requested
        if ($codeType === 'alpha3') {
            // Uppercase the alpha-3 code for map lookup
            $needle = strtoupper($code);

            // Look up the enum case from the lazily built alpha-3 map
            return $this->countryCasesByUpperAlpha3()[$needle] ?? null;
        }

        // Resolve by ISO 3166-1 numeric when requested
        if ($codeType === 'numeric') {
            // Reject non-digit codes so only canonical numeric input resolves
            if (! ctype_digit($code)) {
                return null;
            }

            // Compare using the same canonical int string key as stored in the map
            $key = (string) (int) $code;

            // Look up the enum case from the lazily built numeric map
            return $this->countryCasesByNumericIntKey()[$key] ?? null;
        }

        // Fall back to no match for unexpected type tokens
        return null;
    }

    /**
     * Uppercased alpha-3 code => enum case (lazy, one build per request lifecycle).
     *
     * @return array<string, Country>
     */
    private function countryCasesByUpperAlpha3(): array
    {
        // Return cached map when already built
        if (self::$countryByUpperAlpha3 !== null) {
            return self::$countryByUpperAlpha3;
        }

        // Build the map from all enum cases
        $map = [];

        foreach (Country::cases() as $case) {
            $map[strtoupper($case->alpha3())] = $case;
        }

        self::$countryByUpperAlpha3 = $map;

        unset($case);

        return self::$countryByUpperAlpha3;
    }

    /**
     * Canonical numeric string key (same as `(string)(int)$digits` for digit inputs) => enum case.
     *
     * PHP coerces the numeric-string keys to ints, so the map is keyed by int.
     *
     * @return array<int, Country>
     */
    private function countryCasesByNumericIntKey(): array
    {
        // Return cached map when already built
        if (self::$countryByNumericIntKey !== null) {
            return self::$countryByNumericIntKey;
        }

        // Build the map from all enum cases with digit-only numeric codes
        $map = [];

        foreach (Country::cases() as $case) {
            $n = $case->numeric();

            // Skip cases whose stored numeric code is not digit-only
            if (! ctype_digit($n)) {
                unset($n);

                continue;
            }

            // Use the same canonical key as numeric lookups
            $key = (string) (int) $n;

            // Keep the first case when keys collide (same as prior linear scan)
            if (! \array_key_exists($key, $map)) {
                $map[$key] = $case;
            }

            unset(
                $key,
                $n
            );
        }

        self::$countryByNumericIntKey = $map;

        unset($case);

        return self::$countryByNumericIntKey;
    }
}
