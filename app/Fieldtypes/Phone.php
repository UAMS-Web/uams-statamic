<?php

declare(strict_types=1);

namespace App\Fieldtypes;

use App\Enums\Country;
use App\Fieldtypes\Traits\Input;
use App\Rules\InternationalPhone;
use App\Rules\NationalPhone;
use App\Rules\SmsShortCode;
use App\Services\PhoneUtil;
use Exception;
use Illuminate\Support\Facades\Log;
use Propaganistas\LaravelPhone\PhoneNumber;
use Statamic\Fields\Fieldtype;

/**
 * Class Phone
 *
 * A Statamic fieldtype for phone number input.
 *
 * Features:
 *   - Provides a configurable phone number input field for the Control Panel.
 *   - Supplies phone-specific presets, instructions, and sensible defaults for
 *     input configuration.
 *   - Restricts autocomplete options to phone-related values.
 *   - Hides irrelevant config fields (e.g., autocapitalize, autocorrect) for a
 *     cleaner UI.
 *   - Supports preset-driven configuration for international, national, and SMS
 *     short code formats.
 *
 * Integration Plan: laravel-phone
 *   - Integrate propaganistas/laravel-phone for robust backend phone validation
 *     and normalization.
 *   - Hard-code validation to accept any valid international phone number (no UI
 *     for country/type/strictness yet).
 *   - Normalize and store phone numbers in E.164 format (hard-coded).
 *   - Use trait extension points for validation, normalization, and display
 *     formatting.
 *   - Document that allowed countries, types, validation strictness, and output
 *     format could be made configurable in the future.
 *
 * @todo  Add documentation noting current hard-coded behavior and future extensibility.
 */
class Phone extends Fieldtype
{
    /**
     * Provides DRY, extensible config and preset logic for input-based fieldtypes.
     *
     *   - Centralizes config field attribute overrides (type, instructions,
     *     defaults, etc.)
     *   - Supports preset-driven config logic
     *   - Ensures maintainable, declarative fieldtype configuration
     */
    use Input;

    /**
     * Indicates whether the fieldtype is selectable in form blueprints.
     *
     * @var bool Indicates whether the fieldtype is selectable in form blueprints
     */
    protected $selectableInForms = true;

    /**
     * The display title of the fieldtype in the Control Panel.
     *
     * @var string The display title of the fieldtype in the Control Panel.
     */
    protected static $title = 'Phone';

    /**
     * The icon for this fieldtype.
     *
     * @return string The icon name
     */
    #[\Override]
    public function icon(): string
    {
        return 'phone-telephone-call';
    }

    /**
     * Fieldtype Keywords
     *
     * @var array<string> Search keywords used to find this fieldtype in the Control Panel
     */
    protected $keywords = [
        'call',
        'contact',
        'dial',
        'number',
        'phone',
        'tel',
        'telephone',
    ];

    /**
     * Fieldtype Categories
     *
     * @var array<string> Categories this fieldtype belongs to in the Statamic fieldtype selector
     */
    public $categories = [
        'number',
        'text',
        'special',
    ];

    /**
     * Get the allowed autocomplete keys for this fieldtype.
     *
     * @return array<string> The allowed autocomplete keys for this fieldtype
     */
    protected function getAllowedAutocompleteKeys(): array
    {
        return [
            'tel', // A full telephone number, including the country code (e.g., "1-855-555-6502")
            'tel-national', // The entire phone number without the country code component, including a country-internal prefix (e.g., "855-555-6502")
        ];
    }

    /**
     * Allows overriding config field attributes for this fieldtype.
     *
     * @return array<string, mixed> Config field overrides
     */
    protected function getConfigFieldOverrides(): array
    {
        return [
            'input_type' => [
                'default' => 'tel',
            ],
            'inputmode' => [
                'default' => 'tel',
            ],
            'spellcheck' => [
                'default' => 'false',
            ],
            'calculation_type' => [
                'default' => 'text',
            ],
        ];
    }

    /**
     * Get config fields that should be hidden (visibility: hidden) for this
     * fieldtype.
     *
     * Hide fields that have sensible defaults for this fieldtype.
     *
     * @return array<string> Config fields that should be hidden (visibility: hidden) for this fieldtype
     */
    protected function getHiddenConfigFields(): array
    {
        return [
            'accesskey',
            'anchor',
            'antlers',
            'append',
            'autocapitalize',
            'autocomplete_query',
            'autocorrect',
            'calculation_type',
            'class',
            'dir',
            'draggable',
            'enterkeyhint',
            'debounce', // Only Text fieldtype uses debounce in Vue
            'exportparts',
            'focus',
            'form',
            'id',
            'inert',
            'input_mask',
            'input_mask_append_text',
            'input_mask_append_text_enabled',
            'input_mask_autofix',
            'input_mask_blocks',
            'input_mask_class',
            'input_mask_compiledMasks',
            'input_mask_currentMask',
            'input_mask_definitions',
            'input_mask_displayChar',
            'input_mask_eager',
            'input_mask_enum',
            'input_mask_exposeMask',
            'input_mask_from',
            'input_mask_lazy',
            'input_mask_mapToRadix',
            'input_mask_mask',
            'input_mask_mask_MaskedDynamic',
            'input_mask_mask_MaskedFunction',
            'input_mask_mask_MaskedPattern',
            'input_mask_mask_MaskedRegExp',
            'input_mask_max',
            'input_mask_maxLength',
            'input_mask_min',
            'input_mask_normalizeZeros',
            'input_mask_overwrite',
            'input_mask_padFractionalZeros',
            'input_mask_parent',
            'input_mask_pattern',
            'input_mask_placeholderChar',
            'input_mask_prepare_case',
            'input_mask_prepend_text',
            'input_mask_prepend_text_enabled',
            'input_mask_radix',
            'input_mask_repeat',
            'input_mask_scale',
            'input_mask_skipInvalid',
            'input_mask_store_unmasked',
            'input_mask_thousandsSeparator',
            'input_mask_to',
            'inputmode', // Always 'tel' for phone inputs
            'is',
            'itemid',
            'itemprop',
            'itemref',
            'itemscope',
            'itemtype',
            'lang',
            'list',
            'maxlength',
            'minlength',
            'name',
            'nonce',
            'part',
            'pattern',
            'popover',
            'prepend',
            'role',
            'size',
            'slot',
            'spellcheck', // Always 'false' for phone inputs
            'style',
            'tabindex',
            'title',
            'transform_case',
            'translate',
            'virtualkeyboardpolicy',
            'writingsuggestions',
        ];
    }

    /**
     * Get additional config fields to be added after the preset field.
     *
     * These fields can be conditionally displayed based on preset values.
     *
     * @return array<string, mixed> Additional config fields
     */
    protected function getAdditionalConfigFields(): array
    {
        // Use the Country enum to build the options array
        $options = [];

        // Iterate through all available countries to build options
        foreach (Country::cases() as $country) {
            // Map country alpha2 code to country name for select options
            $options[$country->alpha2()] = $country->country_name();
        }

        // Build the country select field configuration
        $result = [
            'country' => [
                'display' => 'Country',
                'instructions' => 'Select the country for phone number validation and formatting.',
                'type' => 'select',
                'options' => $options,
                'default' => 'US',
                'if' => [
                    'preset' => 'tel-national',
                ],
            ],
        ];

        // Clean up variables to free up memory
        unset($options);

        // Return the result
        return $result;
    }

    /**
     * Unified preset definition for phone number formats.
     *
     * @return array<string, mixed> Structured array with keys:
     *                              - display: string
     *                              - instructions?: string
     *                              - default: string
     *                              - options: array<string, array{ label: string, config: array<string, mixed> }>
     */
    protected function getPresets(): array
    {
        // Get example national phone number for placeholder text
        $exampleNational = PhoneUtil::exampleNational() ?: '(555) 555-5555';

        // Get example international phone number for placeholder text
        $exampleInternational = PhoneUtil::exampleInternational() ?: '+1-555-555-5555';

        // Build the preset configuration array with all phone format options
        $result = [
            'display' => 'Phone Format',
            'instructions' => 'Choose the phone number format for this field.',
            'default' => 'tel-national',
            'options' => [
                'tel' => [
                    'label' => 'International (with country code)',
                    'config' => [
                        'autocomplete_query' => 'on',
                        'autocomplete' => 'tel',
                        'pattern' => '^\\+?\\d{1,3}[- ]?\\(?\\d{3}\\)?[- ]?\\d{3}[- ]?\\d{4}$',
                        'placeholder' => 'e.g., '.$exampleInternational,
                        'parse_as_phone' => true, // Parse international phone numbers as phone numbers
                    ],
                ],
                'tel-national' => [
                    'label' => 'National (no country code)',
                    'config' => [
                        'autocomplete_query' => 'on',
                        'autocomplete' => 'tel-national',
                        'pattern' => '^\\(?\\d{3}\\)?[- ]?\\d{3}[- ]?\\d{4}$',
                        'placeholder' => 'e.g., '.$exampleNational, // Will be overridden in preload() if needed
                        'parse_as_phone' => true, // Parse national phone numbers as phone numbers
                    ],
                ],
                'sms-short-code' => [
                    'label' => 'U.S. SMS Short Code',
                    'config' => [
                        'autocomplete_query' => 'off',
                        'placeholder' => 'e.g., 01234 or 234567',
                        'maxlength' => '6',
                        'minlength' => '5',
                        'parse_as_phone' => false, // Don't parse SMS short codes as phone numbers
                    ],
                ],
            ],
        ];

        // Clean up variables to free up memory
        unset(
            $exampleNational,
            $exampleInternational
        );

        // Return the complete preset configuration
        return $result;
    }

    /**
     * Modify the preload config to set the placeholder for tel-national
     * dynamically based on country config.
     *
     * @param  array<string, mixed>  $config  The preload config
     * @return array<string, mixed> The modified preload config
     */
    protected function modifyPreloadConfig(
        array $config
    ): array {
        // Check if the preset is set to tel-national format
        if (($config['preset'] ?? 'tel-national') === 'tel-national') {
            // Get the country code from config or default to US
            $country = is_string($config['country'] ?? null) ? $config['country'] : 'US';

            // Get example national phone number for the specified country
            $exampleNational = PhoneUtil::exampleNational($country) ?: '855-555-6502';

            // Set the placeholder text with the example phone number
            $config['placeholder'] = 'e.g., '.$exampleNational;

            // Clean up variables to free up memory
            unset(
                $country,
                $exampleNational
            );
        }

        // Return the modified config with updated placeholder
        return $config;
    }

    /**
     * Provide validation rules for field content validation.
     * Uses custom rules for international and national phone validation.
     *
     * @return list<InternationalPhone|NationalPhone|SmsShortCode> Validation rules (Laravel format)
     */
    #[\Override]
    public function rules(): array
    {
        // Initialize the rules array
        $rules = [];

        // Determine which preset is selected (international or national)
        $preset = $this->config(
            'preset',
            'tel-national'
        );

        // Check if the preset is set to international phone format
        if ($preset === 'tel') {
            // Use custom international phone rule
            $rules[] = new InternationalPhone;
        } elseif ($preset === 'tel-national') { // Check if the preset is set to national phone format
            // Get the country from the config
            /** @var string $country */
            $country = $this->config(
                'country',
                'US'
            );

            // Use custom national phone rule with country
            $rules[] = new NationalPhone(
                $country
            );

            // Clean up variables to free up memory
            unset($country);
        } elseif ($preset === 'sms-short-code') { // Check if the preset is set to SMS short code format
            $rules[] = new SmsShortCode;
        } else { // Otherwise
            // Log unexpected preset
            Log::warning(
                'Phone rules() - Unexpected preset',
                [
                    'preset' => $preset,
                ]
            );
        }

        // Clean up variables to free up memory
        unset($preset);

        // Return the rules
        return $rules;
    }

    /**
     * Normalize the value before saving to storage.
     * Converts to E.164 format using laravel-phone.
     *
     * @param  mixed  $value  The value to normalize
     * @param  array<string, mixed>  $config  The field config
     * @return mixed The normalized value
     */
    public function normalizeValue(
        mixed $value,
        array $config = []
    ): mixed {
        // Check if the input value is empty or only whitespace
        if (in_array(\trim(is_scalar($value) ? (string) $value : ''), ['', '0'], true)) {
            // Return an empty string immediately
            return '';
        }

        // Always normalize config with defaults
        $config = $this->normalizeConfigWithDefaults($config);

        // Determine which preset is selected (international or national)
        $preset = $config['preset'] ?? 'tel-national';

        // Check if the preset is set to international phone format
        if ($preset === 'tel') {
            // For international numbers, parse and format as E.164
            try {
                // Clean up the input value
                $cleanedValue = PhoneUtil::cleanInput(is_scalar($value) ? (string) $value : '');

                // Ensure the value starts with '+' for international format
                $cleanedValue = PhoneUtil::addPlusIfMissing($cleanedValue);

                // Parse and format as E.164
                $normalized = new PhoneNumber($cleanedValue)->formatE164();

                // Clean up variables to free up memory
                unset($cleanedValue);

                // Return the normalized value
                return $normalized;
            } catch (Exception $e) {
                // ---
                // Handle any exceptions during international phone number parsing
                // ---

                // Log the exception for debugging
                Log::error(
                    'Phone normalizeValue() exception (international)',
                    [
                        'input_value' => $value,
                        'exception' => $e->getMessage(),
                    ]
                );

                // Clean up variables to free up memory
                unset($e);

                // If parsing fails, return the original value
                return $value;
            }
        } elseif ($preset === 'tel-national') { // Check if the preset is set to national phone format
            // For national numbers, use the selected country for parsing
            $country = is_string($config['country'] ?? null) ? $config['country'] : 'US';

            // Parse and format as E.164 for display (international format)
            try {
                // Clean up the input value
                $cleanedValue = PhoneUtil::cleanInput(is_scalar($value) ? (string) $value : '');

                // Parse and format as E.164
                $normalized = new PhoneNumber(
                    $cleanedValue,
                    $country
                )->formatE164();

                // Clean up variables to free up memory
                unset(
                    $cleanedValue,
                    $country
                );

                // Return the normalized value
                return $normalized;
            } catch (Exception $e) {
                // ---
                // Handle any exceptions during national phone number parsing
                // ---

                // Log the exception for debugging
                Log::error(
                    'Phone normalizeValue() exception (national)',
                    [
                        'input_value' => $value,
                        'country' => $country,
                        'exception' => $e->getMessage(),
                    ]
                );

                // Clean up variables to free up memory
                unset(
                    $e,
                    $country
                );

                // If parsing fails, return the original value
                return $value;
            }
        } elseif ($preset === 'sms-short-code') { // Check if the preset is set to SMS short code format
            // ---
            // For SMS short codes, just clean the input (cast the input to a string and trim
            // whitespace) and return as-is. Short codes don't need E.164 formatting.
            // ---
            $cleanedValue = PhoneUtil::cleanInput(is_scalar($value) ? (string) $value : '');

            // Return the cleaned SMS short code value
            return $cleanedValue;
        }

        // Log a warning if the preset is unrecognized
        Log::warning(
            'Phone normalizeValue() unrecognized preset',
            [
                'input_value' => $value,
                'preset' => $preset,
            ]
        );

        // Clean up variables to free up memory
        unset($preset);

        // If preset is unrecognized, return the original value
        return $value;
    }

    /**
     * Format the value for display in the Control Panel or frontend.
     *
     * This method formats the phone number for display based on the preset:
     *   - If preset is 'tel', displays in international format
     *     (e.g., +1 555-555-5555)
     *   - If preset is 'tel-national', displays in national format
     *     (e.g., 555-555-5555 for US, or local national format for other countries)
     *   - If preset is 'sms-short-code', displays as-is (e.g., 01234, 234567)
     *   - Otherwise, defaults to national format
     *
     * Uses PhoneUtil::formatForDisplay() for consistent formatting and custom U.S.
     * style.
     *
     * @param  mixed  $value  The value to format
     * @param  array<string, mixed>  $config  The field config
     * @return mixed The formatted value
     */
    public function formatValueForDisplay(
        mixed $value,
        array $config = []
    ): mixed {
        // Normalize config with defaults to ensure all required keys are present
        $config = $this->normalizeConfigWithDefaults($config);

        // Determine which preset is selected
        $preset = $config['preset'] ?? null;

        // ---
        // Handle SMS short codes - return as-is since they don't need formatting
        // ---

        // Check if the preset is set to SMS short code format
        if ($preset === 'sms-short-code') {
            // Clean up variables to free up memory
            unset(
                $config,
                $preset
            );

            // Return the original value
            return $value;
        }

        // Otherwise
        // ---
        // Handle international and national phone numbers
        // ---
        // Choose the display format: 'international' for 'tel', 'national' otherwise
        $format = $preset === 'tel' ? 'international' : 'national';
        // Format the phone number for display using PhoneUtil
        $result = PhoneUtil::formatForDisplay(
            is_scalar($value) ? (string) $value : '',
            $format
        );
        // Clean up variables to free up memory
        unset(
            $config,
            $preset,
            $format
        );

        // Return the formatted value
        return $result;
    }

    /**
     * Return the name of the Vue component for this fieldtype.
     *
     * Overrides the component() method from the Input trait.
     *
     * @return string The name of the Vue component for this fieldtype
     */
    #[\Override]
    public function component(): string
    {
        return 'phone_input';
    }
}
