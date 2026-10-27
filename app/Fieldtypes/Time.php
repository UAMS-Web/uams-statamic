<?php

declare(strict_types=1);

namespace App\Fieldtypes;

use App\Fieldtypes\Traits\Input;
use Statamic\Fieldtypes\Time as BaseTime;

/**
 * Extended Time fieldtype that leverages the Input trait for enhanced
 * functionality.
 *
 * This fieldtype extends Statamic's base Time fieldtype and uses the Input trait
 * to provide comprehensive HTML input attribute support, autocomplete options,
 * input masking capabilities, and configurable field behavior.
 *
 * Key Features:
 *   - Inherits all functionality from Statamic's Time fieldtype
 *   - Supports autocomplete with context-appropriate options for time inputs
 *   - Includes input masking capabilities for data validation and formatting
 *   - Offers configurable field visibility and behavior controls
 *   - Maintains DRY principles through trait-based implementation
 *
 * @see  Input
 * @see  BaseTime
 */
class Time extends BaseTime
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
     * Return the name of the Vue component for this fieldtype.
     * Overrides the component() method from the Input trait.
     */
    #[\Override]
    public function component(): string
    {
        return 'time';
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
                'default' => 'time',
            ],
            'default' => [
                'type' => 'time',
            ],
            'autocomplete_query' => [
                'default' => false,
            ],
            'inputmode' => [
                'default' => 'numeric',
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
            'append',
            'autocapitalize',
            'autocapitalize_setting',
            'autocomplete',
            'autocomplete_query',
            'autocorrect',
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
            'max', // Not supported in Statamic v6 Time fieldtype component
            'maxlength',
            'min', // Not supported in Statamic v6 Time fieldtype component
            'minlength',
            'multiple',
            'name',
            'nonce',
            'part',
            'pattern',
            'placeholder',
            'popover',
            'popovertarget',
            'popovertargetaction',
            'prepend',
            'role',
            'size',
            'slot',
            'spellcheck',
            'src',
            'step', // Not supported in Statamic v6 Time fieldtype component
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
        // Return validation rules with time format constraint
        return [
            'date_format:H:i',
        ];
    }
}
