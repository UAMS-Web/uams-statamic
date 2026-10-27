<?php

declare(strict_types=1);

namespace App\Tags;

use App\Enums\CanadianProvince;
use App\Enums\State;
use App\Services\SchemaTemplates\SchemaObjectFragmentBuilder;
use Statamic\Tags\Parameters;
use Statamic\Tags\Tags;

/**
 * Antlers tag: emit a JSON-LD `Place` fragment with a nested `PostalAddress`.
 *
 * Parameters (all optional; omitted or empty values are not included in JSON):
 *   - address: bound value from the `Address` fieldtype (`App\Fieldtypes\Address`);
 *     flat associative array (e.g. `preset`, `street_address`, `city`,
 *     `postal_code`, `country`, `state_province_region` or preset-specific
 *     region keys) or Statamic list of `key` / `value` pairs. Supplies defaults
 *     for the scalar parameters below when those parameters are not passed on
 *     the tag. When `preset` is `american`, `addressRegion` is emitted as a
 *     `State` object and `addressCountry` as `US`. When `preset` is `canadian`,
 *     `addressRegion` is a `State` object and `addressCountry` as `CA`.
 *   - street_address, post_office_box_number, extended_address, locality,
 *     postal_code: strings
 *   - region, country: string or bound array (e.g., Thing with `@type` and
 *     `name`); lists are normalized like other schema fragments
 *
 * Explicit scalar parameters override values taken from `address` when the
 * parameter is present on the tag (including empty string).
 *
 * Output is empty when the address would contain only `@type` (no address
 * fields).
 *
 * Example:
 *   {{ schema_place street_address="4301 W. Markham St." locality="Little Rock"
 *       region="AR" country="US" postal_code="72205" }}
 *
 * Example with Address field:
 *   {{ schema_place :address="founding_location" }}
 *
 * @property Parameters $params
 */
class SchemaPlace extends Tags
{
    /**
     * Registered Antlers handle `{{ schema_place }}`
     *
     * @var string
     */
    protected static $handle = 'schema_place';

    /**
     * Map of tag parameter names to PostalAddress JSON-LD property names.
     *
     * @var array<string, string>
     */
    private const array ADDRESS_PARAM_TO_KEY = [
        'street_address' => 'streetAddress',
        'post_office_box_number' => 'postOfficeBoxNumber',
        'extended_address' => 'extendedAddress',
        'locality' => 'addressLocality',
        'region' => 'addressRegion',
        'country' => 'addressCountry',
        'postal_code' => 'postalCode',
    ];

    /**
     * `@type` of the nested address. A fixed URI: `SchemaObjectFragmentBuilder::
     * normalizedType('PostalAddress')` returns exactly this and is null only for a
     * blank name.
     */
    private const string POSTAL_ADDRESS_TYPE = 'https://schema.org/PostalAddress';

    /**
     * `@type` of the emitted Place, fixed for the same reason.
     */
    private const string PLACE_TYPE = 'https://schema.org/Place';

    /**
     * Lowercased US state/territory full name => canonical display name from `State`.
     *
     * @var array<string, string>|null
     */
    private static ?array $stateFullNameLowercaseToCanonical = null;

    /**
     * Lowercased Canadian subdivision full name => canonical name from `CanadianProvince`.
     *
     * @var array<string, string>|null
     */
    private static ?array $canadianProvinceFullNameLowercaseToCanonical = null;

    /**
     * Build JSON text for the current parameters and Antlers context.
     *
     * @return string JSON object, or empty string when no fragment is produced
     */
    public function index(): string
    {
        // Skip work when no address field binding and no address-related tag params.
        // A shortcut only: without it the address below holds just its `@type` and
        // the count check returns the same empty string.
        // @pest-mutate-ignore: RemoveEarlyReturn
        if (! $this->hasAnyAddressRelatedTagInput()) {
            return '';
        }

        // Start the nested address with its `@type`
        $address = [
            '@type' => self::POSTAL_ADDRESS_TYPE,
        ];

        // Build default tag-level params from the Address fieldtype when present
        $fromAddressField = $this->tagParamsFromAddressFieldValue();

        // Merge each parameter into the address when it normalizes
        foreach (self::ADDRESS_PARAM_TO_KEY as $param => $jsonKey) {
            // Prefer an explicit tag parameter when that parameter was passed
            $raw = $this->params->has($param)
                ? $this->params->get($param)
                : ($fromAddressField[$param] ?? null);

            // Normalize the value for JSON-LD (strings, nested Things, lists)
            $normalized = SchemaObjectFragmentBuilder::normalizeJsonLdValuePublic(
                $raw
            );

            // Check if the normalized value should be included
            if ($normalized !== null) {
                // Add the property to the address object
                $address[$jsonKey] = $normalized;
            }
        }

        // Check if the address has no fields besides `@type`
        if (\count($address) <= 1) {
            // Return an empty string when there is nothing to emit
            return '';
        }

        // Build the top-level Place payload
        $payload = [
            '@type' => self::PLACE_TYPE,
            'address' => $address,
        ];

        // Serialize the fragment for JSON-LD embedding
        $json = json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        // Check if encoding failed
        if ($json === false) {
            // Return an empty string when JSON encoding fails
            return '';
        }

        // Return the JSON string
        return $json;
    }

    /**
     * Whether the tag received an `address` binding or any scalar address parameter.
     *
     * @return bool True when `address` or any `ADDRESS_PARAM_TO_KEY` handle is present
     */
    private function hasAnyAddressRelatedTagInput(): bool
    {
        // Check for Address fieldtype binding
        if ($this->params->has('address')) {
            return true;
        }

        return array_any(array_keys(self::ADDRESS_PARAM_TO_KEY), fn (string $param) => $this->params->has($param));
    }

    /**
     * Map an Address fieldtype stored value to schema_place parameter keys.
     *
     * @return array<string, mixed> Keys are tag param names (e.g. street_address)
     */
    private function tagParamsFromAddressFieldValue(): array
    {
        // Check if the address field parameter was not passed. A shortcut only: a
        // missing `address` reads as null and coerces to the same empty map.
        // @pest-mutate-ignore: RemoveEarlyReturn
        if (! $this->params->has('address')) {
            // Return an empty map when there is no Address field binding
            return [];
        }

        // Read the raw Address field value from the tag
        $raw = $this->params->get('address');

        // Coerce the value to a flat associative map of subfield keys
        $row = $this->coerceAddressFieldValueToAssoc($raw);

        // Build tag-level defaults from the Address subfield map
        return $this->addressFieldAssocToTagParams($row);
    }

    /**
     * Normalize Address fieldtype storage to an associative array by subfield key.
     *
     * @param  mixed  $value  Augmented or raw field value
     * @return array<string, mixed>
     */
    private function coerceAddressFieldValueToAssoc(mixed $value): array
    {
        // Statamic\Tags\Parameters::make() already unwraps a field Value, so the
        // value arrives here as its stored array (or a scalar)

        // Check for a non-array value
        if (! \is_array($value)) {
            // Return an empty map when the value is not array-like
            return [];
        }

        // Detect Statamic list-of-pairs shape from HasSubfields storage
        if (
            isset($value[0])
            && \is_array($value[0])
            && \array_key_exists(
                'key',
                $value[0]
            )
        ) {
            // Build a flat key => value map from pair entries
            $assoc = [];

            // Walk each stored pair
            foreach ($value as $item) {
                // Check for a valid key entry
                if (\is_array($item) && \is_string($item['key'] ?? null)) {
                    // Assign the pair value by storage key
                    $assoc[$item['key']] = $item['value'] ?? null;
                }
            }

            // Return the reconstructed associative array
            return $assoc;
        }

        // Return a copy of an already-associative Address value
        /** @var array<string, mixed> $value */
        return $value;
    }

    /**
     * Convert Address subfield keys to `schema_place` scalar parameter keys.
     *
     * @param  array<string, mixed>  $row  Flat map (canonical or subfield handles)
     * @return array<string, mixed>
     */
    private function addressFieldAssocToTagParams(array $row): array
    {
        // Start with an empty parameter map
        $out = [];

        // Read preset when the Address field stored it (see Address::process)
        $preset = $this->normalizedAddressPreset($row);

        // Resolve region raw from canonical or preset-specific subfield keys
        $regionRaw = $row['state_province_region']
            ?? $row['state_province_region_american']
            ?? $row['state_province_region_canadian']
            ?? $row['state_province_region_international']
            ?? null;

        // Copy street line when present
        if (\array_key_exists('street_address', $row)) {
            // Map street_address subfield to the tag param
            $out['street_address'] = $row['street_address'];
        }

        // Map address line 2 to extended_address for PostalAddress
        if (\array_key_exists('address_line_2', $row)) {
            // Map address_line_2 subfield to extended_address
            $out['extended_address'] = $row['address_line_2'];
        }

        // Copy city to locality
        if (\array_key_exists('city', $row)) {
            // Map city subfield to locality
            $out['locality'] = $row['city'];
        }

        // Copy postal code when present
        if (\array_key_exists('postal_code', $row)) {
            // Map postal_code subfield to the tag param
            $out['postal_code'] = $row['postal_code'];
        }

        // Check if the address uses the American preset
        if ($preset === 'american') {
            // Build State JSON-LD from the US region subfield when possible
            $usName = $this->resolvedUsStateNameForSchema($regionRaw);

            // Check if a display name was resolved for the region
            if ($usName !== null) {
                // Use a schema.org State object for addressRegion
                $out['region'] = $this->schemaStateObjectWithName($usName);
            }

            // Fix country to US for American-format addresses
            $out['country'] = 'US';
        } elseif ($preset === 'canadian') {
            // Build State JSON-LD from the Canadian region subfield when possible
            $caName = $this->resolvedCanadianSubdivisionNameForSchema($regionRaw);

            // Check if a display name was resolved for the region
            if ($caName !== null) {
                // Use a schema.org State object for addressRegion
                $out['region'] = $this->schemaStateObjectWithName($caName);
            }

            // Fix country to CA for Canadian-format addresses
            $out['country'] = 'CA';
        } else {
            // Copy country when present (international preset and legacy rows)
            if (\array_key_exists('country', $row)) {
                // Map country subfield to the tag param
                $out['country'] = $row['country'];
            }

            // Check if a region value was resolved for non-preset-specific output
            if ($regionRaw !== null) {
                // Expose region as the stored scalar (code, label, or text)
                $out['region'] = $regionRaw;
            }
        }

        // Return the tag parameter defaults derived from the Address field
        return $out;
    }

    /**
     * Normalize the Address `preset` subfield to a lowercase token when valid.
     *
     * @param  array<string, mixed>  $row
     */
    private function normalizedAddressPreset(array $row): ?string
    {
        // Check if preset is missing or not a string
        if (
            ! isset($row['preset'])
            || ! \is_string($row['preset'])
        ) {
            // Return null when preset is absent or not a string token
            return null;
        }

        // Return the normalized preset token. A blank one becomes '', which matches
        // neither preset, so it reads as no preset.
        return strtolower(trim($row['preset']));
    }

    /**
     * Resolve a US region subfield value to a full state/territory name for
     * schema.org `State` using `State::tryFrom()`, `State::fullName()`, and
     * `State::cases()`.
     *
     * @return ?string Null when the value is empty or not a string
     */
    private function resolvedUsStateNameForSchema(mixed $regionRaw): ?string
    {
        // Normalize the raw region to a trimmed string or null
        $trimmed = $this->trimmedStringOrNull($regionRaw);

        // Check if the region string is unusable
        if ($trimmed === null) {
            // Return null when there is nothing to resolve
            return null;
        }

        // Attempt to match a USPS-style postal abbreviation via backed value
        $state = State::tryFrom(strtoupper($trimmed));

        // Check if a matching enum case was found
        if ($state !== null) {
            // Return the official full name from State metadata
            return $state->fullName();
        }

        // Attempt to match a stored full name (label) from the select (O(1) map)
        $canonicalStateName = $this->stateFullNameLowercaseToCanonicalName()[strtolower($trimmed)] ?? null;

        // Check if the map resolved a canonical state name
        if ($canonicalStateName !== null) {
            return $canonicalStateName;
        }

        // Fall back to the trimmed user-entered string
        return $trimmed;
    }

    /**
     * Lowercased US state `fullName()` => canonical display name (lazy, once per process).
     *
     * @return array<string, string>
     */
    private function stateFullNameLowercaseToCanonicalName(): array
    {
        // A cache only: rebuilding the map gives the same map.
        // @pest-mutate-ignore: RemoveEarlyReturn
        if (self::$stateFullNameLowercaseToCanonical !== null) {
            return self::$stateFullNameLowercaseToCanonical;
        }

        $map = [];

        foreach (State::cases() as $case) {
            $name = $case->fullName();
            $map[strtolower($name)] = $name;
        }

        self::$stateFullNameLowercaseToCanonical = $map;

        unset($case);

        return self::$stateFullNameLowercaseToCanonical;
    }

    /**
     * Resolve a Canadian region subfield value to a province/territory name
     * using `CanadianProvince::tryFrom()`, `CanadianProvince::fullName()`, and
     * `CanadianProvince::cases()`.
     *
     * @return ?string Null when the value is empty or not a string
     */
    private function resolvedCanadianSubdivisionNameForSchema(mixed $regionRaw): ?string
    {
        // Normalize the raw region to a trimmed string or null
        $trimmed = $this->trimmedStringOrNull($regionRaw);

        // Check if the region string is unusable
        if ($trimmed === null) {
            // Return null when there is nothing to resolve
            return null;
        }

        // Attempt to match a Canada Post abbreviation via backed enum value
        $province = CanadianProvince::tryFrom(strtoupper($trimmed));

        // Check if a matching enum case was found
        if ($province !== null) {
            // Return the official full name from CanadianProvince metadata
            return $province->fullName();
        }

        // Attempt to match a stored full name (label) from the select (O(1) map)
        $canonicalProvinceName = $this->canadianProvinceFullNameLowercaseToCanonicalName()[strtolower($trimmed)] ?? null;

        // Check if the map resolved a canonical subdivision name
        if ($canonicalProvinceName !== null) {
            return $canonicalProvinceName;
        }

        // Fall back to the trimmed user-entered string
        return $trimmed;
    }

    /**
     * Lowercased Canadian subdivision `fullName()` => canonical name (lazy, once per process).
     *
     * @return array<string, string>
     */
    private function canadianProvinceFullNameLowercaseToCanonicalName(): array
    {
        // A cache only: rebuilding the map gives the same map.
        // @pest-mutate-ignore: RemoveEarlyReturn
        if (self::$canadianProvinceFullNameLowercaseToCanonical !== null) {
            return self::$canadianProvinceFullNameLowercaseToCanonical;
        }

        $map = [];

        foreach (CanadianProvince::cases() as $case) {
            $name = $case->fullName();
            $map[strtolower($name)] = $name;
        }

        self::$canadianProvinceFullNameLowercaseToCanonical = $map;

        unset($case);

        return self::$canadianProvinceFullNameLowercaseToCanonical;
    }

    /**
     * Coerce a mixed region value to a non-empty trimmed string, or null.
     */
    private function trimmedStringOrNull(mixed $value): ?string
    {
        // Check for a missing or non-string region value
        if (! \is_string($value)) {
            // Return null when the region is not string-like
            return null;
        }

        // Trim surrounding whitespace from the region string
        $trimmed = trim($value);

        // Check if nothing usable remains
        if ($trimmed === '') {
            // Return null when the region string is empty
            return null;
        }

        // Return the trimmed string for enum resolution
        return $trimmed;
    }

    /**
     * Build a JSON-LD map for schema.org `State` with a `name` property.
     *
     * @return array<string, string> `@type` plus `name` (URI normalized later)
     */
    private function schemaStateObjectWithName(string $name): array
    {
        // Return a short @type token normalized in the shared JSON-LD pass
        return [
            '@type' => 'State',
            'name' => $name,
        ];
    }
}
