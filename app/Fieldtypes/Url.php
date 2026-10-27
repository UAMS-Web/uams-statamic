<?php

declare(strict_types=1);

namespace App\Fieldtypes;

use App\Fieldtypes\Traits\Input;
use Statamic\Fields\Fieldtype;

/**
 * Statamic fieldtype for entering and validating a URL.
 */
class Url extends Fieldtype
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
     * @var string The display title of the fieldtype in the Control Panel.
     */
    protected static $title = 'URL';

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
        return 'url';
    }

    /**
     * The icon for this fieldtype.
     *
     * @return string The icon name
     */
    #[\Override]
    public function icon(): string
    {
        return 'web';
    }

    /**
     * Fieldtype Keywords
     *
     * @var array<string> Search keywords used to find this fieldtype in the Control Panel
     */
    protected $keywords = [
        'address',
        'communication',
        'contact',
        'link',
        'url',
        'web address',
        'web',
        'website',
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
        return [
            'url',
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
                'default' => 'url',
            ],
            'autocapitalize' => [
                'default' => 'off',
            ],
            'autocorrect' => [
                'default' => 'off',
            ],
            'inputmode' => [
                'default' => 'url',
            ],
            'spellcheck' => [
                'default' => 'off',
            ],
            'writingsuggestions' => [
                'default' => 'off',
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
            'autocapitalize', // Always 'off' for URL inputs
            'autocomplete_query', // Always 'off' for URL inputs
            'autocorrect', // Single option (url)
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
            'inputmode', // Always 'url' for URL inputs
            'is',
            'itemid',
            'itemprop',
            'itemref',
            'itemscope',
            'itemtype',
            'lang',
            'list',
            'maxlength', // Not applicable to URL inputs
            'minlength', // Not applicable to URL inputs
            'name',
            'nonce',
            'part',
            'pattern', // Not applicable to URL inputs
            'popover',
            'prepend',
            'role',
            'size',
            'slot',
            'spellcheck', // Always 'off' for URL inputs
            'style',
            'tabindex',
            'title',
            'translate',
            'virtualkeyboardpolicy',
            'writingsuggestions', // Always 'off' for URL inputs
        ];
    }

    /**
     * Provide validation rules for field content validation.
     * Applies the 'url' validation rule.
     *
     * @return array<string> Validation rules (Laravel format)
     */
    #[\Override]
    public function rules(): array
    {
        return ['url'];
    }
}
