<?php

declare(strict_types=1);

namespace App\Fieldtypes;

use App\Enums\CanadianProvince;
use App\Enums\Country;
use App\Enums\State;
use App\Fieldtypes\Traits\HasSubfields;
use Statamic\Fields\Fieldtype;

/**
 * Address Fieldtype
 *
 * Custom fieldtype; not a fork of a Statamic component. Provides a structured
 * address input using the HasSubfields trait, with support for American,
 * Canadian, and International presets. On save, `process()` writes a `preset`
 * key on the stored value matching the field config `preset` (or the first
 * preset option when unset) only when at least one subfield has a non-blank
 * value, so empty addresses are not persisted as `{ preset: … }` only.
 */
class Address extends Fieldtype
{
    /**
     * Use HasSubfields trait to provide structured address input fields.
     */
    use HasSubfields;

    /**
     * Indicates whether the fieldtype is selectable in form blueprints.
     *
     * @var bool Indicates whether the fieldtype is selectable in form blueprints
     */
    protected $selectableInForms = true;

    /**
     * The name of the Vue component to use for this fieldtype.
     *
     * @var string The name of the Vue component to use for this fieldtype
     */
    protected $component = 'address';

    /**
     * The icon for this fieldtype.
     *
     * @return string The icon name
     */
    #[\Override]
    public function icon(): string
    {
        return 'form-address-field';
    }

    /**
     * Normalize input and persist the blueprint `preset` on the stored value.
     *
     * @param  mixed  $data  Raw value from the publish form
     * @return array<string, mixed> Associative array; includes `preset` only
     *                              when subfields have content
     */
    #[\Override]
    public function process(
        mixed $data
    ): array {
        // Normalize the incoming value to an array for storage
        /** @var array<string, mixed> $result */
        $result = \is_array($data) ? $data : [];

        // Skip preset when there is nothing to store besides config metadata
        if (! $this->valueContainsFilledSubfield($result)) {
            unset($data);

            return [];
        }

        // Read merged field config (cached) for the preset key
        $config = $this->getCachedConfig();

        // Load the selectable preset keys
        $presetOptions = $this->getPresetOptions()['options'];

        // Resolve preset from config, falling back to the first defined option
        $preset = $config['preset'] ?? array_key_first($presetOptions);

        // Write the resolved preset onto the stored subfield map
        $result['preset'] = $preset;

        unset(
            $data,
            $config,
            $presetOptions,
            $preset,
        );

        return $result;
    }

    /**
     * Define the subfields for the Address fieldtype.
     *
     * @return list<array<string, mixed>> Subfield definition maps
     */
    protected function getSubfieldDefinitions(): array
    {
        // ---
        // Cache expensive method calls to avoid repeated array creation
        // ---

        // Static variable to cache US states and territories
        static $usStates = null;

        // Static variable to cache Canadian provinces and territories
        static $canadianProvinces = null;

        // Static variable to cache country options
        static $countryOptions = null;

        // Check if US states and territories are not already cached
        if ($usStates === null) {
            // Get US states and territories
            $usStates = $this->getUsStatesAndTerritories();
        }

        // Check if Canadian provinces and territories are not already cached
        if ($canadianProvinces === null) {
            // Get Canadian provinces and territories
            $canadianProvinces = $this->getCanadianProvincesAndTerritories();
        }

        // Check if country options are not already cached
        if ($countryOptions === null) {
            // Get country options
            $countryOptions = $this->getCountryOptions();
        }

        // Return the array of subfield definitions for the Address fieldtype
        return [
            [
                'handle' => 'street_address',
                'display' => 'Street Address',
                'type' => 'text',
                'inclusion_default' => true,
                'required_default' => true,
                'label_default' => 'Street Address',
                'placeholder_default' => '123 Main St.',
                'default_type' => 'text',
                'default_value' => '',
                'width' => 100,
                'autocomplete' => 'street-address',
            ],
            [
                'handle' => 'address_line_2',
                'display' => 'Address Line 2',
                'type' => 'text',
                'inclusion_default' => true,
                'required_default' => false,
                'label_default' => 'Address Line 2',
                'placeholder_default' => 'Apt, Suite, etc.',
                'default_type' => 'text',
                'default_value' => '',
                'width' => 100,
                'autocomplete' => 'address-line2',
            ],
            [
                'handle' => 'city',
                'display' => 'City',
                'type' => 'text',
                'inclusion_default' => true,
                'required_default' => true,
                'label_default' => 'City',
                'default_type' => 'text',
                'default_value' => '',
                'width' => 50,
                'autocomplete' => 'address-level2',
            ],
            [
                'handle' => 'state_province_region_american',
                'canonical_handle' => 'state_province_region',
                'display' => 'State, Territory or District',
                'type' => 'select',
                'inclusion_default' => true,
                'required_default' => true,
                'label_default' => 'State, Territory or District',
                'default_type' => 'select',
                'default_choices' => $usStates,
                'default_value' => '',
                'width' => 50,
                'autocomplete' => 'address-level1',
                'clearable' => true,
            ],
            [
                'handle' => 'state_province_region_canadian',
                'canonical_handle' => 'state_province_region',
                'display' => 'Province or Territory',
                'type' => 'select',
                'inclusion_default' => true,
                'required_default' => true,
                'label_default' => 'Province or Territory',
                'default_type' => 'select',
                'default_choices' => $canadianProvinces,
                'default_value' => '',
                'width' => 50,
                'autocomplete' => 'address-level1',
                'clearable' => true,
            ],
            [
                'handle' => 'state_province_region_international',
                'canonical_handle' => 'state_province_region',
                'display' => 'State, Province or Region',
                'type' => 'text',
                'inclusion_default' => true,
                'required_default' => true,
                'label_default' => 'State, Province or Region',
                'default_type' => 'text',
                'default_value' => '',
                'width' => 50,
                'autocomplete' => 'address-level1',
            ],
            [
                'handle' => 'postal_code',
                'display' => 'Postal Code',
                'type' => 'zip_code',
                'inclusion_default' => true,
                'required_default' => true,
                'label_default' => 'Postal Code',
                'default_type' => 'text',
                'default_value' => '',
                'width' => 50,
                'autocomplete' => 'postal-code',
                'placeholder_default' => 'e.g., 12345 or 12345-6789',
            ],
            [
                'handle' => 'country',
                'display' => 'Country',
                'type' => 'select',
                'inclusion_default' => true,
                'required_default' => true,
                'label_default' => 'Country',
                'default_type' => 'select',
                'default_choices' => $countryOptions,
                'default_value' => '',
                'width' => 50,
                'clearable' => true,
            ],
        ];
    }

    /**
     * Preset options for Address Type.
     *
     * @return array{display: string, options: array<string, string>} Preset options
     */
    protected function getPresetOptions(): array
    {
        return [
            'display' => 'Address Format',
            'options' => [
                'american' => 'American',
                'canadian' => 'Canadian',
                'international' => 'International',
            ],
        ];
    }

    /**
     * Only show the 'country' subfield config fields when preset is 'international'.
     *
     * @param  string  $handle  The handle of the subfield
     * @param  ?string  $preset  The preset of the subfield
     * @return ?array<string, string> The preset-based visibility condition for
     *                                the subfield
     */
    protected function getSubfieldPresetIfCondition(
        string $handle,
        ?string $preset
    ): ?array {
        // Check if the handle is 'country'
        if ($handle === 'country') {
            // Return preset-based visibility condition for the 'country' subfield
            return ['preset' => 'international'];
        }

        // Check if the handle is 'state_province_region_american'
        if ($handle === 'state_province_region_american') {
            // Return preset-based visibility condition for the 'state_province_region_american' subfield
            return ['preset' => 'american'];
        }

        // Check if the handle is 'state_province_region_canadian'
        if ($handle === 'state_province_region_canadian') {
            // Return preset-based visibility condition for the 'state_province_region_canadian' subfield
            return ['preset' => 'canadian'];
        }

        // Check if the handle is 'state_province_region_international'
        if ($handle === 'state_province_region_international') {
            // Return preset-based visibility condition for the 'state_province_region_international' subfield
            return ['preset' => 'international'];
        }

        // Return no preset-based visibility condition for other subfields
        return null;
    }

    /**
     * Get ISO 3166-1 alpha-2 country codes and names.
     *
     * @see  https://countrycode.org/
     *
     * @return array<string, string> ISO 3166-1 alpha-2 country codes and names
     */
    protected function getCountryOptions(): array
    {
        // Initialize an empty array for countries
        $countries = [];

        // Iterate over each country to build the array
        foreach (Country::cases() as $country) {
            // Add the country code and name to the array
            $countries[$country->alpha2()] = $country->country_name();
        }

        // Return the array of country codes and names
        return $countries;
    }

    /**
     * Get U.S. states, territories, and DC.
     *
     * @return array<string, string> U.S. states, territories, and District of
     *                               Columbia
     */
    protected function getUsStatesAndTerritories(): array
    {
        // Initialize an empty array for states
        $states = [];

        // Iterate over each state to build the array
        foreach (State::cases() as $state) {
            // Add the state code and name to the array
            $states[$state->postal()] = $state->fullName();
        }

        // Return the array of state codes and names
        return $states;
    }

    /**
     * Get Canadian provinces and territories.
     *
     * Mirrors `getUsStatesAndTerritories()`: postal code → official English name,
     * sourced from `App\Enums\CanadianProvince` so additions or label changes
     * land in one place. The CP State enum and CanadianProvince enum share the
     * same `postal()` / `fullName()` shape.
     *
     * @return array<string, string> Postal abbreviation keyed to province name.
     */
    protected function getCanadianProvincesAndTerritories(): array
    {
        // Initialize an empty array for provinces and territories
        $provinces = [];

        // Iterate over each province to build the array
        foreach (CanadianProvince::cases() as $province) {
            // Add the postal code and full name to the array
            $provinces[$province->postal()] = $province->fullName();
        }

        // Return the array of postal codes and province names
        return $provinces;
    }

    /**
     * Extra data merged into the form-field template scope when this fieldtype
     * is rendered via `{{ form:create }}`. Surfaces the same code-to-name maps
     * the CP `getSubfieldDefinitions()` uses for the state/province/country
     * <select>s, so `address.antlers.html` can render dropdowns instead of
     * plain text inputs.
     *
     * Statamic calls this from `RendersForms::getRenderableField` (vendor
     * `vendor/statamic/cms/src/Tags/Concerns/RendersForms.php:158`); the
     * returned keys become Antlers variables in the rendered partial.
     *
     * The maps are not gated by preset here — exposing all three keeps the
     * template simple (it picks the right one per preset), and the payload is
     * small (about 50 + 13 + 250 entries). The submitted value is the option
     * key (`'AR'`, `'ON'`, `'US'`), which `Address::process()` stores as-is.
     *
     * @return array<string, mixed> Extra data keyed for template consumption.
     */
    #[\Override]
    public function extraRenderableFieldData(): array
    {
        return [
            'state_options_american' => $this->getUsStatesAndTerritories(),
            'state_options_canadian' => $this->getCanadianProvincesAndTerritories(),
            'country_options' => $this->getCountryOptions(),
        ];
    }
}
