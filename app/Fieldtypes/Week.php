<?php

declare(strict_types=1);

namespace App\Fieldtypes;

use App\Fieldtypes\Traits\Input;
use Statamic\Fields\Fieldtype;

/**
 * Statamic fieldtype for selecting a calendar week.
 */
class Week extends Fieldtype
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
    protected static $title = 'Week';

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
        return 'week';
    }

    /**
     * Fieldtype Keywords
     *
     * @var array<string> Search keywords used to find this fieldtype in the Control Panel
     */
    protected $keywords = [
        'calendar',
        'date',
        'week',
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
     * The icon for this fieldtype.
     *
     * @return string The icon name
     */
    #[\Override]
    public function icon(): string
    {
        return 'fieldtype-date';
    }

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
                'default' => 'week',
            ],
            'min' => [
                'instructions' => 'Set the minimum allowed week. Format: YYYY-Www (e.g., 2020-W01 for week 1 of 2020).',
                'validate' => ['regex:/^\d{4}-W\d{2}$/'],
            ],
            'max' => [
                'instructions' => 'Set the maximum allowed week. Format: YYYY-Www (e.g., 2030-W52 for week 52 of 2030).',
                'validate' => ['regex:/^\d{4}-W\d{2}$/'],
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
        return [];
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
            'autocomplete_query', // autocomplete is off by default
            'autocorrect',
            'calculation_type',
            'class',
            'dir',
            'draggable',
            'autocomplete', // autocomplete is off by default
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
            'list',
            'name',
            'nonce',
            'part',
            'popover',
            'prepend',
            'role',
            'slot',
            'spellcheck',
            'step',
            'style',
            'tabindex',
            'title',
            'translate',
            'virtualkeyboardpolicy',
            'writingsuggestions',
        ];
    }

    /**
     * Provide validation rules for field content validation.
     * Enforces ISO week format and min/max week constraints.
     *
     * @return array<int, string|\Closure> Validation rules (Laravel format)
     */
    #[\Override]
    public function rules(): array
    {
        // Initialize the rules array
        $rules = [];

        // Add ISO week format validation
        $rules[] = 'regex:/^\d{4}-W\d{2}$/';

        // ---
        // Add min week constraint if set
        // ---

        // Get the minimum week value from config
        /** @var string|null $min */
        $min = $this->config('min');

        // Check if min is configured
        if (! empty($min)) {
            // Add closure to enforce min week constraint
            $rules[] = function (
                $attribute,
                $value,
                $fail
            ) use ($min): void {
                /** @var string $value */
                /** @var callable $fail */
                // Check if value is less than min
                if (
                    $this->compareWeeks(
                        $value,
                        $min
                    ) < 0
                ) {
                    // Fail validation with formatted message
                    $fail(__('statamic::validation.week.after_or_equal', ['min' => $this->formatWeekForDisplay($min)]));
                }
            };
        }

        // ---
        // Add max week constraint if set
        // ---

        // Get the maximum week value from config
        /** @var string|null $max */
        $max = $this->config('max');

        // Check if max is configured
        if (! empty($max)) {
            // Add closure to enforce max week constraint
            $rules[] = function (
                $attribute,
                $value,
                $fail
            ) use ($max): void {
                /** @var string $value */
                /** @var callable $fail */
                // Check if value is greater than max
                if (
                    $this->compareWeeks(
                        $value,
                        $max
                    ) > 0
                ) {
                    // Fail validation with formatted message
                    $fail(__('statamic::validation.week.before_or_equal', ['max' => $this->formatWeekForDisplay($max)]));
                }
            };
        }

        // Return the rules
        return $rules;
    }

    /**
     * Compare two ISO week strings (YYYY-Www).
     * Returns -1 if $a < $b, 0 if equal, 1 if $a > $b.
     *
     * @param  string  $a  The first week string
     * @param  string  $b  The second week string
     * @return int -1 if $a < $b, 0 if equal, 1 if $a > $b
     */
    protected function compareWeeks(
        string $a,
        string $b
    ): int {
        // Split first week string into year and week components
        [$yearA, $weekA] = \explode(
            '-W',
            $a
        );

        // Split second week string into year and week components
        [$yearB, $weekB] = \explode(
            '-W',
            $b
        );

        // ---
        // Compare years first
        // ---

        // Check if first year is less than second year
        if ((int) $yearA < (int) $yearB) {
            return -1;
        }

        // Check if first year is greater than second year
        if ((int) $yearA > (int) $yearB) {
            return 1;
        }

        // ---
        // If years are equal, compare week numbers
        // ---

        // Check if first week is less than second week
        if ((int) $weekA < (int) $weekB) {
            return -1;
        }

        // Check if first week is greater than second week
        if ((int) $weekA > (int) $weekB) {
            return 1;
        }

        // Weeks are equal
        return 0;
    }

    /**
     * Format an ISO week string (YYYY-Www) as 'Week xx, yyyy'.
     *
     * @param  string  $week  The ISO week string to format
     * @return string The formatted week string
     */
    protected function formatWeekForDisplay(
        string $week
    ): string {
        // ---
        // Match and extract year and week number
        // ---

        // Check if week matches ISO format pattern
        if (
            \preg_match(
                '/^(\d{4})-W(\d{2})$/',
                $week,
                $matches
            )
        ) {
            // Return the formatted string as 'Week xx, yyyy'
            return sprintf('Week %s, %s', $matches[2], $matches[1]);
        }

        // Return original string if not a valid ISO week
        return $week;
    }
}
