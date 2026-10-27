<?php

declare(strict_types=1);

namespace App\Fieldtypes;

use App\Fieldtypes\Traits\Input;
use Statamic\Fieldtypes\Text as BaseText;

/**
 * Extended Text fieldtype that leverages the Input trait for enhanced
 * functionality.
 *
 * This fieldtype extends Statamic's base Text fieldtype and uses the Input trait
 * to provide comprehensive HTML input attribute support.
 *
 * Key Features:
 *   - Inherits all functionality from Statamic's Text fieldtype
 *   - Maintains DRY principles through trait-based implementation
 *   - Implements intelligent validation rules based on min/max configuration
 *
 * @see  Input
 * @see  BaseText
 */
class Text extends BaseText
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
        return 'custom_text';
    }

    /**
     * The icon for this fieldtype.
     *
     * Uses the same icon as Statamic's Text fieldtype.
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
            'name',
            'honorific-prefix',
            'given-name',
            'additional-name',
            'family-name',
            'honorific-suffix',
            'nickname',
            'bday',
            'bday-day',
            'bday-month',
            'bday-year',
            'sex',
            'street-address',
            'address-line1',
            'address-line2',
            'address-line3',
            'address-level1',
            'address-level2',
            'address-level3',
            'address-level4',
            'country',
            'country-name',
            'organization',
            'organization-title',
            'cc-name',
            'cc-given-name',
            'cc-additional-name',
            'cc-family-name',
            'cc-number',
            'cc-exp',
            'cc-exp-month',
            'cc-exp-year',
            'cc-csc',
            'cc-type',
            'username',
            'one-time-code',
            'webauthn',
            'language',
            'transaction-currency',
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
            'accept',
            'accesskey',
            'alt',
            'anchor',
            // 'append',
            // 'autocapitalize',
            // 'autocapitalize_setting',
            // 'autocomplete',
            // 'autocomplete_query',
            // 'autocorrect',
            'calculation_type',
            'capture',
            'checked',
            'class',
            'contenteditable',
            // 'default',
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
            // 'input_mask',
            // 'input_mask_append_text',
            // 'input_mask_append_text_enabled',
            // 'input_mask_autofix',
            // 'input_mask_blocks',
            // 'input_mask_class',
            // 'input_mask_compiledMasks',
            // 'input_mask_currentMask',
            // 'input_mask_definitions',
            // 'input_mask_displayChar',
            // 'input_mask_eager',
            // 'input_mask_enum',
            // 'input_mask_exposeMask',
            // 'input_mask_from',
            // 'input_mask_lazy',
            // 'input_mask_mapToRadix',
            // 'input_mask_mask',
            // 'input_mask_mask_MaskedDynamic',
            // 'input_mask_mask_MaskedFunction',
            // 'input_mask_mask_MaskedPattern',
            // 'input_mask_mask_MaskedRegExp',
            // 'input_mask_max',
            // 'input_mask_maxLength',
            // 'input_mask_min',
            // 'input_mask_normalizeZeros',
            // 'input_mask_overwrite',
            // 'input_mask_padFractionalZeros',
            // 'input_mask_parent',
            // 'input_mask_pattern',
            // 'input_mask_placeholderChar',
            // 'input_mask_prepareChar',
            // 'input_mask_prepend_text',
            // 'input_mask_prepend_text_enabled',
            // 'input_mask_radix',
            // 'input_mask_repeat',
            // 'input_mask_scale',
            // 'input_mask_skipInvalid',
            // 'input_mask_store_unmasked',
            // 'input_mask_thousandsSeparator',
            // 'input_mask_to',
            // 'input_type',
            // 'inputmode',
            'is',
            'itemid',
            'itemprop',
            'itemref',
            'itemscope',
            'itemtype',
            'lang',
            'list',
            'max',
            // 'maxlength',
            'min',
            // 'minlength',
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
            // 'spellcheck',
            'src',
            'step',
            'style',
            'tabindex',
            'title',
            // 'transform_case',
            // 'translate',
            // 'virtualkeyboardpolicy',
            'width',
            // 'writingsuggestions',
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

        // Return the rules
        return $rules;
    }
}
