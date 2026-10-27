<?php

declare(strict_types=1);

namespace App\Fieldtypes;

use App\Fieldtypes\Traits\Input;
use Statamic\Fieldtypes\Integer as BaseInteger;

/**
 * Extended Integer fieldtype that leverages the Input trait for enhanced
 * functionality.
 *
 * This fieldtype extends Statamic's base Integer fieldtype and uses the Input
 * trait to provide comprehensive HTML input attribute support, autocomplete
 * options, input masking capabilities, and configurable field behavior.
 *
 * Key Features:
 *   - Inherits all functionality from Statamic's Integer fieldtype
 *   - Provides extensive HTML input attribute configuration (min, max, step, etc.)
 *   - Supports autocomplete with context-appropriate options for numeric inputs
 *   - Includes input masking capabilities for data validation and formatting
 *   - Offers configurable field visibility and behavior controls
 *   - Maintains DRY principles through trait-based implementation
 *   - Implements intelligent validation rules based on min/max configuration
 *
 * Validation Features:
 *   - Always validates that input is an integer using Laravel's 'integer' rule
 *   - Automatically applies 'min' validation when min config is set
 *   - Automatically applies 'max' validation when max config is set
 *   - Uses 'between' validation when both min and max are configured
 *   - Provides appropriate numeric validation messages for range constraints
 *
 * @see  Input
 * @see  BaseInteger
 */
class Integer extends BaseInteger
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
     * Return the name of the Vue component for this fieldtype.
     *
     * Overrides the component() method from the Input trait.
     *
     * @return string The name of the Vue component for this fieldtype
     */
    #[\Override]
    public function component(): string
    {
        return 'custom_integer';
    }

    /**
     * The icon for this fieldtype.
     *
     * @return string The icon name
     */
    #[\Override]
    public function icon(): string
    {
        /** @var string $icon */
        $icon = parent::icon();

        return $icon;
    }

    /**
     * Get the allowed autocomplete keys for this fieldtype.
     *
     * @return array<string> The allowed autocomplete keys for this fieldtype
     */
    protected function getAllowedAutocompleteKeys(): array
    {
        return [
            'bday-day',
            'bday-month',
            'bday-year',
            'tel',
            'tel-country-code',
            'tel-national',
            'tel-area-code',
            'tel-local',
            'tel-local-prefix',
            'tel-local-suffix',
            'tel-extension',
            'postal-code',
            'cc-number',
            'cc-exp-month',
            'cc-exp-year',
            'cc-csc',
            'one-time-code',
        ];
    }

    /**
     * Allows overriding config field attributes for the integer fieldtype.
     *
     * @return array<string, array<string, mixed>> Config field attribute overrides keyed by config field handle
     */
    protected function getConfigFieldOverrides(): array
    {
        return [
            'input_type' => [
                'default' => 'number',
            ],
            // 'placeholder' => [
            //     'default' => null,
            // ],
            'default' => [
                'type' => 'integer',
            ],
            'max' => [
                'type' => 'integer',
            ],
            'min' => [
                'type' => 'integer',
            ],
            'step' => [
                'type' => 'integer',
            ],
            // 'autocomplete' => [
            //     'default' => null,
            // ],
            'inputmode' => [
                'default' => 'numeric',
            ],
            // 'append' => [
            //     'default' => null,
            // ],
            // 'prepend' => [
            //     'default' => null,
            // ],
            'calculation_type' => [
                'default' => 'math',
            ],
            'calculation_decimals' => [
                'default' => 0,
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
            'accept',
            'accesskey',
            'alt',
            'anchor',
            'antlers',
            // 'append',
            'autocapitalize',
            'autocapitalize_setting',
            // 'autocomplete',
            // 'autocomplete_query',
            'autocorrect',
            'calculation_type',
            'calculation_decimals',
            'capture',
            'checked',
            'class',
            'contenteditable',
            // 'default',
            'debounce', // Only Text fieldtype uses debounce in Vue
            'dir',
            'dirname',
            'draggable',
            'enterkeyhint',
            'exportparts',
            'focus',
            'form',
            'formaction',
            'formenctype',
            'formmethod',
            'formnovalidate',
            'formtarget',
            'height',
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
            'input_type',
            'inputmode',
            'is',
            'itemid',
            'itemprop',
            'itemref',
            'itemscope',
            'itemtype',
            'lang',
            'list',
            // 'max',
            'maxlength',
            // 'min',
            'minlength',
            'multiple',
            'name',
            'nonce',
            'part',
            'pattern',
            // 'placeholder',
            'popover',
            'popovertarget',
            'popovertargetaction',
            // 'prepend',
            'role',
            'size',
            'slot',
            'spellcheck',
            'src',
            // 'step',
            'style',
            'tabindex',
            'title',
            'transform_case',
            'translate',
            'virtualkeyboardpolicy',
            'width',
            'writingsuggestions',
        ];
    }

    /**
     * Provide validation rules for field content validation.
     *
     * @return array<string> Validation rules (Laravel format)
     */
    #[\Override]
    public function rules(): array
    {
        // Initialize the rules array
        $rules = [];

        // Always ensure the value is an integer for integer fieldtype
        $rules[] = 'integer';

        // Get the minimum value from field configuration
        /** @var scalar|null $min */
        $min = $this->config('min');

        // Check if minimum value is configured (not null or empty)
        $hasMin = isset($min) && $min !== '';

        // Get the maximum value from field configuration
        /** @var scalar|null $max */
        $max = $this->config('max');

        // Check if maximum value is configured (not null or empty)
        $hasMax = isset($max) && $max !== '';

        // Check if both min and max are set
        if ($hasMin && $hasMax) {
            // Use Laravel's between rule
            $rules[] = sprintf('between:%s,%s', $min, $max);
        } elseif ($hasMin) { // Check if only min is set
            // Use Laravel's min rule
            $rules[] = 'min:'.$min;
        } elseif ($hasMax) { // Check if only max is set
            // Use Laravel's max rule
            $rules[] = 'max:'.$max;
        }

        // Return the rules
        return $rules;
    }
}
