<?php

declare(strict_types=1);

namespace App\Fieldtypes;

use App\Fieldtypes\Traits\Input;
use Statamic\Fields\Fieldtype;

/**
 * Statamic fieldtype for selecting a calendar month.
 */
class Month extends Fieldtype
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
    protected static $title = 'Month';

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
        return 'month';
    }

    /**
     * Fieldtype Keywords
     *
     * @var array<string> Keywords used to find this fieldtype in the Control Panel selector
     */
    protected $keywords = [
        'calendar',
        'date',
        'month',
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
                'default' => 'month',
            ],
            'min' => [
                'instructions' => 'Set the minimum allowed month. Format: YYYY-MM (e.g., 2020-01 for January 2020).',
                'validate' => ['regex:/^\d{4}-\d{2}$/'],
            ],
            'max' => [
                'instructions' => 'Set the maximum allowed month. Format: YYYY-MM (e.g., 2030-12 for December 2030).',
                'validate' => ['regex:/^\d{4}-\d{2}$/'],
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
            'autocapitalize',
            'autocomplete', // autocomplete is off by default
            'autocomplete_query', // autocomplete is off by default
            'autocorrect',
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
     * Enforces YYYY-MM format and min/max month constraints.
     *
     * @return array<int, string|\Closure> Validation rules (Laravel format)
     */
    #[\Override]
    public function rules(): array
    {
        // Initialize the rules array
        $rules = [];

        // Add YYYY-MM format validation
        $rules[] = 'regex:/^\d{4}-\d{2}$/';

        // Get the minimum month configuration value
        $min = $this->config('min');

        // Check if minimum month is configured
        if (! empty($min)) {
            // Narrow the configured min month to a YYYY-MM string
            // Either fallback has no separator and skips the comparison, so EmptyStringToNotEmpty is equivalent
            // @pest-mutate-ignore: EmptyStringToNotEmpty
            $min = \is_scalar($min) ? (string) $min : '';

            // Add a closure to ensure the value is not before the configured min month
            $rules[] = function (
                string $attribute,
                mixed $value,
                \Closure $fail
            ) use ($min): void {
                // Narrow the value under validation to a string
                // Either fallback has no separator and skips the comparison, so EmptyStringToNotEmpty is equivalent
                // @pest-mutate-ignore: EmptyStringToNotEmpty
                $value = \is_scalar($value) ? (string) $value : '';

                // Check if the value is before the configured min month
                if (
                    $this->compareMonths(
                        $value,
                        $min
                    ) < 0
                ) {
                    // Fail value is before the configured min month
                    $fail(__('statamic::validation.month.after_or_equal', ['min' => $this->formatMonthForDisplay($min)]));
                }
            };
        }

        // Get the maximum month configuration value
        $max = $this->config('max');

        // Check if maximum month is configured
        if (! empty($max)) {
            // Narrow the configured max month to a YYYY-MM string
            // Either fallback has no separator and skips the comparison, so EmptyStringToNotEmpty is equivalent
            // @pest-mutate-ignore: EmptyStringToNotEmpty
            $max = \is_scalar($max) ? (string) $max : '';

            // Add a closure to ensure the value is not after the configured max month
            $rules[] = function (
                string $attribute,
                mixed $value,
                \Closure $fail
            ) use ($max): void {
                // Narrow the value under validation to a string
                // Either fallback has no separator and skips the comparison, so EmptyStringToNotEmpty is equivalent
                // @pest-mutate-ignore: EmptyStringToNotEmpty
                $value = \is_scalar($value) ? (string) $value : '';

                // Check if the value is after the configured max month
                if (
                    $this->compareMonths(
                        $value,
                        $max
                    ) > 0
                ) {
                    // Fail value is after the configured max month
                    $fail(__('statamic::validation.month.before_or_equal', ['max' => $this->formatMonthForDisplay($max)]));
                }
            };
        }

        // Return the rules
        return $rules;
    }

    /**
     * Compare two YYYY-MM strings.
     * Returns -1 if $a < $b, 0 if equal, 1 if $a > $b. Returns 0 when either has
     * no `-` separator, which would otherwise raise an undefined-index error; a
     * malformed value then fails only the format rule.
     *
     * @param  string  $a  First month string in YYYY-MM format
     * @param  string  $b  Second month string in YYYY-MM format
     * @return int Returns -1 if $a < $b, 0 if equal or not comparable, 1 if $a > $b
     */
    protected function compareMonths(
        string $a,
        string $b
    ): int {
        // A string with no year-month separator cannot be split into the two parts
        // compared below; treat it as equal so no bound fails on it
        if (
            ! \str_contains($a, '-')
            || ! \str_contains($b, '-')
        ) {
            return 0;
        }

        // ---
        // Split the month strings into year and month components
        // ---

        // Split the first month string into year and month components
        [$yearA, $monthA] = \explode(
            '-',
            $a
        );

        // Split the second month string into year and month components
        [$yearB, $monthB] = \explode(
            '-',
            $b
        );

        // ---
        // Compare years first
        // ---

        // Return -1 if first year is less than second year
        // Callers read only the sign, so returning -2 instead of -1 is equivalent
        // @pest-mutate-ignore: DecrementInteger
        if ((int) $yearA < (int) $yearB) {
            return -1;
        }

        // Return 1 if first year is greater than second year
        // Callers read only the sign, so returning 2 instead of 1 is equivalent
        // @pest-mutate-ignore: IncrementInteger
        if ((int) $yearA > (int) $yearB) {
            return 1;
        }

        // ---
        // If years are equal, compare month numbers
        // ---

        // Return -1 if first month is less than second month
        // Callers read only the sign, so returning -2 instead of -1 is equivalent
        // @pest-mutate-ignore: DecrementInteger
        if ((int) $monthA < (int) $monthB) {
            return -1;
        }

        // Return 1 if first month is greater than second month
        // Callers read only the sign, so returning 2 instead of 1 is equivalent
        // @pest-mutate-ignore: IncrementInteger
        if ((int) $monthA > (int) $monthB) {
            return 1;
        }

        // Months are equal
        return 0;
    }

    /**
     * Format a YYYY-MM string as 'Month yyyy'.
     *
     * @param  string  $month  Month string in YYYY-MM format
     * @return string Formatted string as 'Month yyyy' or original string if invalid
     */
    protected function formatMonthForDisplay(
        string $month
    ): string {
        // ---
        // Match and extract year and month number
        // ---

        // Check if the month string matches the YYYY-MM format
        if (
            \preg_match(
                '/^(\d{4})-(\d{2})$/',
                $month,
                $matches
            )
        ) {
            // Convert the matched month number to an integer
            $monthNum = (int) $matches[2];

            // Extract the year from the matches
            $year = $matches[1];

            // Get the full month name from the first day of the matched month. A month
            // number of 0 or above 12 rolls into an earlier or later year; only the
            // month name is used.
            $monthName = (new \DateTimeImmutable)
                ->setDate(
                    (int) $year,
                    $monthNum,
                    // Day; the second of the month names the same month
                    // @pest-mutate-ignore: IncrementInteger
                    1
                )
                ->format('F');

            // Return the formatted string as 'Month yyyy'
            return sprintf('%s %s', $monthName, $year);
        }

        // Return original string if not a valid YYYY-MM
        return $month;
    }
}
