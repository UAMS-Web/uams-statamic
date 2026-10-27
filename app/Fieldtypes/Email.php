<?php

declare(strict_types=1);

namespace App\Fieldtypes;

use App\Fieldtypes\Traits\Input;
use Statamic\Fields\Fieldtype;

/**
 * Statamic fieldtype for entering and validating an email address.
 */
class Email extends Fieldtype
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
    protected static $title = 'Email';

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
        return 'email';
    }

    /**
     * The icon for this fieldtype.
     *
     * @return string The icon name
     */
    #[\Override]
    public function icon(): string
    {
        return 'mail-sign-at';
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
        'email',
        'mail',
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
            'email',
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
                'default' => 'email',
            ],
            'autocomplete_query' => [
                'default' => 'on',
            ],
            'autocomplete' => [
                'default' => 'email',
            ],
            'autocapitalize' => [
                'default' => 'off',
            ],
            'autocorrect' => [
                'default' => 'off',
            ],
            'inputmode' => [
                'default' => 'email',
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
            'append', // Not applicable to email inputs
            'autocapitalize', // Always 'off' for email inputs
            'autocomplete_query', // Always 'on' for email inputs
            'autocomplete', // Always 'email' for email inputs
            'autocorrect', // Single option (email)
            'calculation_type',
            'class',
            'contenteditable',
            'default',
            'debounce', // Only Text fieldtype uses debounce in Vue
            'dir',
            'dirname',
            'draggable',
            'enterkeyhint',
            'exportparts',
            'focus',
            'form',
            'id',
            'inert',
            'inputmode',
            'inputmode', // Always 'email' for email inputs
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
            'prepend', // Not applicable to email inputs
            'role',
            'size',
            'slot',
            'spellcheck', // Always 'off' for email inputs
            'style',
            'tabindex',
            'title',
            'translate',
            'virtualkeyboardpolicy',
            'writingsuggestions', // Always 'off' for email inputs
        ];
    }

    /**
     * Provide validation rules for field content validation.
     * Applies the 'email' validation rule.
     *
     * @return array<string> Validation rules (Laravel format)
     */
    #[\Override]
    public function rules(): array
    {
        return ['email'];
    }
}
