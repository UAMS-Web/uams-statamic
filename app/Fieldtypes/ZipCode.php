<?php

declare(strict_types=1);

namespace App\Fieldtypes;

use App\Fieldtypes\Traits\Input;
use App\Rules\ZipCode as ZipCodeRule;
use Statamic\Fields\Fieldtype;

/**
 * Class ZipCode
 *
 * A Statamic fieldtype for ZIP code input.
 *
 * Features:
 *   - Provides a configurable ZIP code input field for the Control Panel.
 *   - Supplies ZIP code-specific instructions and sensible defaults for
 *     input configuration.
 *   - Restricts autocomplete options to postal code-related values.
 *   - Hides irrelevant config fields for a cleaner UI.
 *   - Uses custom validation rule for U.S. ZIP code format validation.
 */
class ZipCode extends Fieldtype
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
    protected static $title = 'ZIP Code';

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
        return 'zip_code';
    }

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
     * Fieldtype Keywords
     *
     * @var array<string> Search keywords used to find this fieldtype in the Control Panel
     */
    protected $keywords = [
        'address',
        'code',
        'codes',
        'location',
        'postal',
        'zip',
        'zipcode',
        'zipcodes',
    ];

    /**
     * Fieldtype Categories
     *
     * @var array<string> Categories this fieldtype belongs to in the Statamic fieldtype selector
     */
    public $categories = [
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
            'postal-code', // Postal code (e.g., "12345" or "12345-6789")
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
            'inputmode' => [
                'default' => 'numeric',
            ],
            'spellcheck' => [
                'default' => 'false',
            ],
            'placeholder' => [
                'default' => 'e.g., 12345 or 12345-6789',
            ],
            'input_mask' => [
                'default' => true,
            ],
            'input_mask_mask_MaskedPattern' => [
                'default' => '00000[-0000]',
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
            'debounce', // Only Text fieldtype uses debounce in Vue
            'enterkeyhint',
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
            'inputmode', // Always 'numeric' for ZIP code inputs
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
            'placeholder',
            'popover',
            'prepend',
            'role',
            'size',
            'slot',
            'spellcheck', // Always 'false' for ZIP code inputs
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
     * Provide validation rules for field content validation.
     * Uses custom rule for ZIP code validation.
     *
     * @return array<int, ZipCodeRule> Validation rules (Laravel format)
     */
    #[\Override]
    public function rules(): array
    {
        return [new ZipCodeRule];
    }

    /**
     * Normalize the value before saving to storage.
     * Trims whitespace and ensures consistent formatting.
     *
     * @param  mixed  $value  The value to normalize
     * @param  array<string, mixed>  $config  The field config
     * @return string The normalized value
     */
    public function normalizeValue(
        mixed $value,
        array $config = []
    ): string {
        // Clean up the input value by trimming whitespace
        $stringValue = \is_scalar($value) ? (string) $value : '';
        $cleanedValue = \trim($stringValue);

        // Clean up variables to free up memory
        unset(
            $value,
            $config
        );

        // Return the cleaned value
        return $cleanedValue;
    }
}
