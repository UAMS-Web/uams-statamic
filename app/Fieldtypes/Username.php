<?php

declare(strict_types=1);

namespace App\Fieldtypes;

use App\Fieldtypes\Traits\Input;
use Statamic\Fields\Fieldtype;

/**
 * Class Username
 *
 * A Statamic fieldtype for username input — the analog of Gravity Forms'
 * User Registration add-on `username` field (`GF_Field_Username`, which
 * extends `GF_Field_Text`).
 *
 * Renders a single-line text input with username semantics:
 *   - `autocomplete="username"` so browsers/password managers offer saved
 *     account names.
 *   - spellcheck / autocorrect / autocapitalize disabled, since usernames are
 *     not prose and must not be silently mangled on entry.
 *
 * Like Statamic's own Text fieldtype, the Control Panel editing experience is
 * plain text, so this fieldtype reuses the `custom_text` Vue component rather
 * than shipping a dedicated one — there is no UX reason for it to diverge from
 * Text in the CP.
 *
 * @see  Email
 * @see  Password
 * @see  Text
 */
class Username extends Fieldtype
{
    /**
     * Provides DRY, extensible config and preset logic for input-based fieldtypes.
     *
     *   - Centralizes config field attribute overrides (type, instructions,
     *     defaults, etc.)
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
    protected static $title = 'Username';

    /**
     * Return the name of the Vue component for this fieldtype.
     *
     * Reuses the `custom_text` component (App\Fieldtypes\Text) — a username is
     * edited as plain text in the Control Panel, so no dedicated component is
     * warranted.
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
     * @return string The icon name
     */
    #[\Override]
    public function icon(): string
    {
        return 'user-avatar';
    }

    /**
     * Fieldtype Keywords
     *
     * @var array<string> Search keywords used to find this fieldtype in the Control Panel
     */
    protected $keywords = [
        'account',
        'handle',
        'login',
        'user',
        'username',
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
            'username',
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
            'autocomplete_query' => [
                'default' => 'on',
            ],
            'autocomplete' => [
                'default' => 'username',
            ],
            'autocapitalize' => [
                'default' => 'off',
            ],
            'autocorrect' => [
                'default' => 'off',
            ],
            'spellcheck' => [
                'default' => 'off',
            ],
            'writingsuggestions' => [
                'default' => 'off',
            ],
            'maxlength' => [
                'instructions' => 'Set the maximum number of characters allowed for the username.',
            ],
            'minlength' => [
                'instructions' => 'Set the minimum number of characters required for the username.',
            ],
            'pattern' => [
                'instructions' => <<<'EOT'
                    Set a regular expression pattern for username validation.

                    Example: `^[a-zA-Z0-9_.-]{3,20}$` to allow 3–20 letters, digits, underscores, dots, and hyphens.
                    EOT,
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
            'accept',
            'accesskey',
            'alt',
            'anchor',
            'antlers',
            'append',
            'autocapitalize', // Always 'off' for username inputs
            'autocomplete', // Always 'username' for username inputs
            'autocomplete_query', // Always 'on' for username inputs
            'autocorrect', // Always 'off' for username inputs
            'calculation_type',
            'capture',
            'checked',
            'class',
            'contenteditable',
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
            'is',
            'itemid',
            'itemprop',
            'itemref',
            'itemscope',
            'itemtype',
            'lang',
            'list',
            'name',
            'nonce',
            'part',
            'popover',
            'prepend',
            'role',
            'size',
            'slot',
            'spellcheck', // Always 'off' for username inputs
            'style',
            'tabindex',
            'title',
            'translate',
            'virtualkeyboardpolicy',
            'writingsuggestions', // Always 'off' for username inputs
        ];
    }
}
