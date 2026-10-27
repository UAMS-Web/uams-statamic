<?php

declare(strict_types=1);

namespace App\Fieldtypes;

use App\Enums\Currency as CurrencyEnum;
use App\Fieldtypes\Traits\Input;
use Statamic\Fields\Field;
use Statamic\Fields\Fieldtype;

/**
 * Statamic fieldtype for entering a monetary amount in a configured currency.
 */
class Currency extends Fieldtype
{
    /**
     * Provides DRY, extensible config and preset logic for input-based fieldtypes.
     *
     *   - Centralizes config field attribute overrides (type, instructions, defaults,
     *     etc.)
     *   - Supports preset-driven config logic
     *   - Ensures maintainable, declarative fieldtype configuration
     */
    use Input;

    /**
     * The display title of the fieldtype in the Control Panel.
     *
     * @var string The display title of the fieldtype in the Control Panel.
     */
    protected static $title = 'Currency';

    /**
     * Fieldtype Keywords
     *
     * @var array<string> Search keywords used to find this fieldtype in the Control Panel
     */
    protected $keywords = [
        'currency',
        'money',
        'price',
        'cost',
        'amount',
        'value',
        'total',
        'subtotal',
        'tax',
    ];

    /**
     * The categories this fieldtype belongs to in the Statamic fieldtype selector.
     *
     * @var array<string> Categories this fieldtype belongs to in the Statamic fieldtype selector.
     */
    protected $categories = [
        'special',
    ];

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
        return 'currency';
    }

    /**
     * The icon for this fieldtype.
     *
     * @return string The icon name
     */
    #[\Override]
    public function icon(): string
    {
        return 'money-cash-bill';
    }

    /**
     * Get the allowed autocomplete keys for this fieldtype.
     *
     * @return array<string> The allowed autocomplete keys for this fieldtype
     */
    protected function getAllowedAutocompleteKeys(): array
    {
        return [
            // 'foo',
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
                'default' => 'text',
            ],
            'default' => [
                'type' => 'float',
            ],
            'autocomplete_query' => [
                'default' => 'off',
            ],
            'inputmode' => [
                'default' => 'decimal',
            ],
            'spellcheck' => [
                'default' => 'false',
            ],
            'writingsuggestions' => [
                'default' => 'false',
            ],
            // Enable Input Mask
            'input_mask' => [
                'default' => true,
            ],
            // Input Mask Class
            'input_mask_class' => [
                'default' => 'MaskedNumber',
            ],
            // Store Unmasked Value
            'input_mask_store_unmasked' => [
                'default' => true,
                'instructions' => <<<'EOT'
                    Controls whether to store the masked (formatted) value or the unmasked (raw) value.

                    **When enabled (true):**
                    - Stores the raw, unmasked value (e.g., `1234.5`)
                    - Preserves the original data without formatting
                    - Useful for data processing, calculations, or when you need the raw value

                    **When disabled (false):**
                    - Stores the formatted, masked value (e.g., `1,234.50`)
                    - Preserves the exact display format
                    - Useful for consistent display or when formatting is part of the data
                    EOT,
            ],
            // Eager Placeholder Behavior
            'input_mask_eager' => [
                'default' => false,
            ],
            // Characters That Should Be Mapped to the Decimal Separator
            'input_mask_mapToRadix' => [
                'default' => null,
            ],
            // Maximum Bound for Validation
            'input_mask_max' => [
                'display' => 'Maximum Value',
                'default' => null,
                'instructions' => 'Set the maximum allowed value for this field.',
                'type' => 'float',
            ],
            // Minimum Bound for Validation
            'input_mask_min' => [
                'display' => 'Maximum Value',
                'default' => null,
                'instructions' => 'Set the minimum allowed value for this field.',
                'type' => 'float',
            ],
            // Normalize Zeros
            'input_mask_normalizeZeros' => [
                'default' => true, // Normalize zeros by default for consistent formatting
            ],
            // Character Overwriting
            'input_mask_overwrite' => [
                'default' => false,
            ],
            // Pad Fractional Zeros
            'input_mask_padFractionalZeros' => [
                'default' => true,
            ],
            // Parent Masked Instance
            'input_mask_parent' => [
                'default' => null,
            ],
            // Decimal Separator Character
            'input_mask_radix' => [
                'default' => '.',
            ],
            // Number of Digits After Decimal Point
            'input_mask_scale' => [
                'default' => null, // Specific value is defined in `Currency::preload()`
            ],
            // Skip Invalid Characters
            'input_mask_skipInvalid' => [
                'default' => true,
            ],
            // Character Used to Separate Thousands
            'input_mask_thousandsSeparator' => [
                'default' => ',',
            ],
            // Prepend Text to the Display Value
            'input_mask_prepend_text_enabled' => [
                'default' => null, // Specific value is defined in `Currency::preload()`
            ],
            // Prepended Text
            'input_mask_prepend_text' => [
                'default' => null, // Specific value is defined in `Currency::preload()`
            ],
            // Append Text to the Display Value
            'input_mask_append_text_enabled' => [
                'default' => null, // Specific value is defined in `Currency::preload()`
            ],
            // Appended Text
            'input_mask_append_text' => [
                'default' => null, // Specific value is defined in `Currency::preload()`
            ],
            'calculation_type' => [
                'default' => 'math',
            ],
        ];
    }

    /**
     * Get config fields that should be unset (removed) for this fieldtype.
     *
     * Remove fields that are not applicable to this fieldtype.
     *
     * @return array<string> Config fields that should be hidden (visibility: hidden) for this fieldtype
     */
    protected function getUnsetConfigFields(): array
    {
        return [
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
            'append', // Dynamically defined based on currency type
            'autocapitalize', // Not applicable to currency values
            'autocomplete', // off by default
            'autocomplete_query', // off by default
            'autocorrect', // Not applicable to currency values
            'calculation_type',
            'calculation_decimals',
            'class',
            'debounce', // Only Text fieldtype uses debounce in Vue
            'dir',
            'draggable',
            'enterkeyhint',
            'exportparts',
            'focus',
            'form',
            'id',
            'inert',
            'inputmode', // decimal by default
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
            'prepend', // Dynamically defined based on currency type
            'role',
            'size',
            'slot',
            'spellcheck', // false by default
            'step', // Dynamically defined based on currency type
            'style',
            'tabindex',
            'title',
            'transform_case',
            'translate',
            'virtualkeyboardpolicy',
            'writingsuggestions', // false by default
            'input_mask',
            'input_mask_class',
            'input_mask_mask',
            'input_mask_mask_MaskedDynamic',
            'input_mask_mask_MaskedFunction',
            'input_mask_mask_MaskedPattern',
            'input_mask_mask_MaskedRegExp',
            'input_mask_append_text',
            'input_mask_append_text_enabled',
            'input_mask_autofix',
            'input_mask_blocks',
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
            'input_mask_maxLength',
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
            'input_mask_thousandsSeparator',
            'input_mask_to',
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
        // ---
        // Build options from Currency enum
        // ---

        // Initialize the options array
        $options = [];

        // Loop through the Currency enum cases
        foreach (CurrencyEnum::cases() as $currency_enum_case) {
            // Add the currency to the options array
            $options[$currency_enum_case->value] = $currency_enum_case->name();
        }

        // Return the configuration field items
        return [
            'currency_type' => [
                'display' => __('Currency Type'),
                'type' => 'select',
                'instructions' => __('Select the type of currency to be used by the field.'),
                'options' => $options,
                'default' => 'USD',
                'validate' => ['required'],
            ],
        ];
    }

    /**
     * Preload config data for the Vue component.
     *
     * This method provides the form fields to the Vue component so it can populate
     * the field selection dropdowns. It merges preset config overrides into the
     * config array so the Vue component always receives the expanded config,
     * regardless of what is in the YAML.
     *
     * It follows the same pattern as the Input trait's preload() method.
     *
     * Sets prepend/append based on currency symbol placement.
     *
     * @return array<string, mixed> Preload data
     */
    #[\Override]
    public function preload(): array
    {
        // Process the config using the Input trait's helper method
        $config = $this->processPreloadConfig();

        // Check if currency_type is set
        if (! empty($config['currency_type'])) {
            // Process currency configuration based on the selected currency type
            try {
                // Get the currency alpha code for the selected currency_type
                $currency_alpha = is_scalar($config['currency_type']) ? (string) $config['currency_type'] : '';

                // Set field prepend with the currency alpha code
                $config['prepend'] = $currency_alpha;

                // Get the Currency enum case for the selected currency_type
                $currency_enum_case = CurrencyEnum::from($currency_alpha);

                // Get the currency symbol
                $currency_symbol = $currency_enum_case->symbol();

                // Check if the currency symbol is set
                if ($currency_symbol) {
                    // Get the currency symbol placement
                    $currency_symbol_placement = $currency_enum_case->symbol_placement();

                    // Get the currency symbol separation
                    $currency_symbol_separation = $currency_enum_case->symbol_separation();

                    // If the symbol placement is 'before', prepend the currency symbol to the display value
                    if ($currency_symbol_placement === 'before') {
                        // ---
                        // Prepend the currency symbol to the display value
                        // ---

                        // Enable prepended text
                        $config['input_mask_prepend_text_enabled'] = true;

                        // Disable appended text
                        $config['input_mask_append_text_enabled'] = false;

                        // ---
                        // Prepend the currency symbol to the display value with a separator space if
                        // relevant
                        // ---
                        $config['input_mask_prepend_text'] = $currency_symbol.($currency_symbol_separation ? ' ' : '');
                    } elseif ($currency_symbol_placement === 'after') { // Check if the symbol placement is 'after'
                        // ---
                        // Append the currency symbol to the display value
                        // ---

                        // Disable prepended text
                        $config['input_mask_prepend_text_enabled'] = false;

                        // Enable appended text
                        $config['input_mask_append_text_enabled'] = true;

                        // ---
                        // Append the currency symbol to the display value with a separator space if
                        // relevant
                        // ---
                        $config['input_mask_append_text'] = ($currency_symbol_separation ? ' ' : '').$currency_symbol;
                    } elseif ($currency_symbol_placement === 'between') { // If the symbol placement is 'between', set the currency symbol as the radix
                        // ---
                        // Set the currency symbol as the radix
                        // ---

                        // Disable prepended text
                        $config['input_mask_prepend_text_enabled'] = false;

                        // Disable appended text
                        $config['input_mask_append_text_enabled'] = false;

                        // Set the currency symbol as the radix
                        $config['input_mask_radix'] = $currency_symbol;
                    } else { // Otherwise
                        // ---
                        // Disable both prepend and append
                        // ---

                        // Disable prepended text
                        $config['input_mask_prepend_text_enabled'] = false;

                        // Disable appended text
                        $config['input_mask_append_text_enabled'] = false;
                    }

                    // Clean up variables to free up memory
                    unset(
                        $currency_symbol_placement,
                        $currency_symbol_separation
                    );
                }

                // Get the minor_unit and set scale accordingly
                $config['input_mask_scale'] = $currency_enum_case->minor_unit();

                // Check if calculation_formula is enabled and set to true
                if (
                    ! empty($config['calculation_formula'])
                    && $config['calculation_formula'] === true
                ) {
                    // Set calculation_decimals to the currency's minor unit
                    $config['calculation_decimals'] = $currency_enum_case->minor_unit();
                }
            } catch (\ValueError) {
                // Invalid currency_type, do not set prepend/append or scale
            }
        }

        // Set the handle for Vue field ID generation
        /** @var Field $field */
        $field = $this->field();
        $config['handle'] = $field->handle();

        // Return the config array for the Vue component
        return [
            'config' => $config,
            'fieldtype_handle' => $this->handle(),
        ];
    }
}
