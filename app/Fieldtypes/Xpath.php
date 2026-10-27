<?php

declare(strict_types=1);

namespace App\Fieldtypes;

use App\Fieldtypes\Traits\Input;
use Statamic\Fields\Fieldtype;

/**
 * Fieldtype for XPath expressions. Uses the Input trait and validates with
 * the xpath_syntax rule when the field is saved.
 */
class Xpath extends Fieldtype
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
    protected static $title = 'XPath';

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
        return 'xpath';
    }

    /**
     * Indicates whether the fieldtype is selectable in collection blueprints.
     *
     * @var bool Indicates whether the fieldtype is selectable in collection blueprints
     */
    protected $selectable = false;

    /**
     * Indicates whether the fieldtype is selectable in form blueprints.
     *
     * @var bool Indicates whether the fieldtype is selectable in form blueprints
     */
    protected $selectableInForms = false;

    /**
     * The icon for this fieldtype.
     *
     * @return string The icon name
     */
    #[\Override]
    public function icon(): string
    {
        return 'code-inline';
    }

    /**
     * Fieldtype Keywords
     *
     * @var array<string> Search keywords used to find this fieldtype in the Control Panel
     */
    protected $keywords = [
        'code',
        'data access',
        'data',
        'expression',
        'expressions',
        'language',
        'languages',
        'queries',
        'query language',
        'query languages',
        'query',
        'xml path language',
        'xml',
        'xpath',
    ];

    /**
     * Fieldtype Categories
     *
     * @var array<string> Categories this fieldtype belongs to in the Statamic fieldtype selector
     */
    public $categories = [
        'special',
    ];

    /**
     * Get the allowed autocomplete keys for this fieldtype.
     *
     * @return array<string> The allowed autocomplete keys for this fieldtype
     */
    protected function getAllowedAutocompleteKeys(): array
    {
        return [];
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
            'autocapitalize' => [
                'default' => 'off',
            ],
            'autocorrect' => [
                'default' => 'off',
            ],
            'inputmode' => [
                'default' => 'text',
            ],
            'spellcheck' => [
                'default' => 'off',
            ],
            'writingsuggestions' => [
                'default' => 'off',
            ],
            'calculation_query' => [
                'default' => false,
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
            'autocapitalize', // Always 'off' for XPath inputs
            'autocomplete_query', // Always 'off' for XPath inputs
            'autocorrect', // Always 'off' for XPath inputs
            'calculation_formula',
            'calculation_formula_math',
            'calculation_formula_text',
            'calculation_query', // Always false for XPath inputs
            'calculation_type',
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
            'inputmode', // Always 'text' for XPath inputs
            'is',
            'itemid',
            'itemprop',
            'itemref',
            'itemscope',
            'itemtype',
            'lang',
            'list',
            'maxlength', // Not applicable to XPath inputs
            'minlength', // Not applicable to XPath inputs
            'name',
            'nonce',
            'part',
            'pattern', // Not applicable to XPath inputs
            'popover',
            'prepend',
            'role',
            'size',
            'slot',
            'spellcheck', // Always 'off' for XPath inputs
            'style',
            'tabindex',
            'title',
            'transform_case',
            'translate',
            'virtualkeyboardpolicy',
            'writingsuggestions', // Always 'off' for XPath inputs
        ];
    }

    /**
     * Provide validation rules for field content validation.
     * Applies the 'xpath_syntax' validation rule.
     *
     * @return array<string> Validation rules (Laravel format)
     */
    #[\Override]
    public function rules(): array
    {
        return ['xpath_syntax'];
    }
}
