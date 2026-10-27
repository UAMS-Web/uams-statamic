<?php

declare(strict_types=1);

namespace App\Fieldtypes;

use App\Fieldtypes\Traits\Input;
use Statamic\Fieldtypes\Floatval as BaseFloatval;

/**
 * Extended Floatval fieldtype that leverages the Input trait for enhanced
 * functionality.
 *
 * This fieldtype extends Statamic's base Floatval fieldtype and uses the Input
 * trait to provide comprehensive HTML input attribute support, autocomplete
 * options, input masking capabilities, and configurable field behavior.
 *
 * Key Features:
 *   - Inherits all functionality from Statamic's Floatval fieldtype
 *   - Provides extensive HTML input attribute configuration (min, max, step, etc.)
 *   - Supports autocomplete with context-appropriate options for decimal inputs
 *   - Includes input masking capabilities for data validation and formatting
 *   - Offers configurable field visibility and behavior controls
 *   - Maintains DRY principles through trait-based implementation
 *   - Implements intelligent validation rules based on min/max configuration
 *
 * Validation Features:
 *   - Always validates that input is numeric using Laravel's 'numeric' rule
 *   - Automatically applies 'min' validation when min config is set
 *   - Automatically applies 'max' validation when max config is set
 *   - Uses 'between' validation when both min and max are configured
 *   - Provides appropriate numeric validation messages for range constraints
 *
 * @see  Input
 * @see  BaseFloatval
 */
class Floatval extends BaseFloatval
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
     * Indicates whether the fieldtype is selectable in form blueprints.
     *
     * @var bool Indicates whether the fieldtype is selectable in form blueprints
     */
    protected $selectableInForms = true;

    /**
     * The display title of the fieldtype in the Control Panel.
     *
     * Set to "Decimal Number" to provide a more user-friendly name than the parent
     * "Float" fieldtype, while maintaining the same functionality.
     *
     * @var string The display title of the fieldtype in the Control Panel.
     */
    protected static $title = 'Decimal Number';

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
        return 'custom_floatval';
    }

    /**
     * The icon for this fieldtype.
     *
     * @return string The icon name
     */
    #[\Override]
    public function icon(): string
    {
        // Retrieve the parent icon value, which may be implicitly mixed
        $icon = parent::icon();

        // Narrow to a scalar before casting, matching PHP's implicit coercion
        // The parent icon is always a string, so the cast and the fallback are equivalent
        // @pest-mutate-ignore: RemoveStringCast, EmptyStringToNotEmpty
        return \is_scalar($icon) ? (string) $icon : '';
    }

    /**
     * Extend the keywords for the fieldtype picker.
     *
     * Inherits the parent Floatval fieldtype's keywords and adds "float"
     * to ensure this fieldtype appears when searching for "float" in the
     * blueprint editor.
     *
     * @return array<string> Search keywords used to find this fieldtype in the Control Panel
     */
    #[\Override]
    public function keywords(): array
    {
        // Define additional keywords to improve fieldtype discoverability
        $keywords = [
            'float',
            'number',
            'numeric',
            'decimal',
            'floatval',
        ];

        // Merge with parent keywords to maintain inheritance while adding new terms
        /** @var array<string> $parentKeywords */
        $parentKeywords = parent::keywords();

        return \array_merge(
            $parentKeywords,
            $keywords
        );
    }

    /**
     * Get the allowed autocomplete keys for this fieldtype.
     *
     * @return array<string> The allowed autocomplete keys for this fieldtype
     */
    protected function getAllowedAutocompleteKeys(): array
    {
        return [
            'transaction-amount',
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
                'default' => 'number',
            ],
            // 'placeholder' => [
            //     'default' => null,
            // ],
            'default' => [
                'type' => 'float',
            ],
            'max' => [
                'type' => 'float',
            ],
            'min' => [
                'type' => 'float',
            ],
            'step' => [
                'type' => 'float',
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

        // Always ensure the value is a numeric value for float fieldtype
        $rules[] = 'numeric';

        // Get the minimum value from field configuration
        $min = $this->config('min');

        // Check if a minimum value is configured
        $hasMin = isset($min) && $min !== '';

        // Get the maximum value from field configuration
        $max = $this->config('max');

        // Check if a maximum value is configured
        $hasMax = isset($max) && $max !== '';

        // Narrow the configured bounds to strings for rule interpolation
        // sprintf() and concatenation cast a scalar bound to the same string, so RemoveStringCast is equivalent
        // @pest-mutate-ignore: RemoveStringCast
        $minString = \is_scalar($min) ? (string) $min : '';

        // sprintf() and concatenation cast a scalar bound to the same string, so RemoveStringCast is equivalent
        // @pest-mutate-ignore: RemoveStringCast
        $maxString = \is_scalar($max) ? (string) $max : '';

        // Check if both min and max are set
        if ($hasMin && $hasMax) {
            // Use Laravel's between rule
            $rules[] = sprintf('between:%s,%s', $minString, $maxString);
        } elseif ($hasMin) { // Check if only min is set
            // Use Laravel's min rule
            $rules[] = 'min:'.$minString;
        } elseif ($hasMax) { // Check if only max is set
            // Use Laravel's max rule
            $rules[] = 'max:'.$maxString;
        }

        // Return the rules
        return $rules;
    }
}
