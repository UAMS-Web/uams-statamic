<?php

declare(strict_types=1);

namespace App\Fieldtypes;

use App\Fieldtypes\Traits\Input;
use Statamic\Fields\Fieldtype;

/**
 * Statamic fieldtype for entering a password value.
 */
class Password extends Fieldtype
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
    protected static $title = 'Password';

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
        return 'password';
    }

    /**
     * The icon for this fieldtype.
     *
     * @return string The icon name
     */
    #[\Override]
    public function icon(): string
    {
        return 'security-lock';
    }

    /**
     * Fieldtype Keywords
     *
     * @var array<string> Search keywords used to find this fieldtype in the Control Panel
     */
    protected $keywords = [
        'authentication',
        'password',
        'secure',
        'security',
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
            'current-password',
            'new-password',
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
                'default' => 'password',
            ],
            'autocorrect' => [
                'default' => 'off',
            ],
            'maxlength' => [
                'instructions' => 'Set the maximum number of characters for the password. Consider security implications.',
            ],
            'minlength' => [
                'instructions' => 'Set the minimum number of characters required for a secure password.',
            ],
            'pattern' => [
                'instructions' => <<<EOT
                    Set a regular expression pattern for password validation.

                    Example: `^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$` for strong password requirements.
                    EOT
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
            'append',
            'autocorrect', // autocorrect is off by default
            'calculation_type',
            'class',
            'default', // Not applicable to password inputs
            'dir',
            'draggable',
            'enterkeyhint',
            'debounce', // Only Text fieldtype uses debounce in Vue
            'exportparts',
            'focus',
            'form',
            'id',
            'inert',
            'inputmode',
            'is',
            'itemid',
            'itemprop',
            'itemref',
            'itemscope',
            'itemtype',
            'lang',
            'name',
            'nonce',
            'part',
            'popover',
            'prepend',
            'role',
            'size',
            'slot',
            'spellcheck', // spellcheck is off by default
            'style',
            'tabindex',
            'title',
            'translate',
            'virtualkeyboardpolicy',
            'writingsuggestions', // writingsuggestions is off by default
        ];
    }
}
