<?php

declare(strict_types=1);

namespace App\Rules\BaseRules;

use Illuminate\Contracts\Validation\Rule;

/**
 * Base class for date/time comparison validation rules.
 *
 * Provides shared functionality for date, time, and datetime comparisons
 * with support for different comparison operators (>, >=, <, <=, ===).
 */
abstract class BaseDateComparisonRule implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @param  string  $date  The comparison date/time value
     * @param  string  $type  The data type (date, time, datetime)
     */
    public function __construct(protected string $date, protected string $type = 'date') {}

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute  The attribute name
     * @param  mixed  $value  The value being validated
     * @return bool True if validation passes, false otherwise
     */
    public function passes(
        $attribute,
        mixed $value
    ): bool {
        // Ensure the value is a string before date/time parsing
        if (! \is_string($value)) {
            // Return false if the value is not a parseable string
            return false;
        }

        // ---
        // Convert both values to timestamps for comparison
        // ---

        // Convert user input to timestamp
        $valueTimestamp = \strtotime($value);

        // Convert comparison date to timestamp
        $dateTimestamp = \strtotime($this->date);

        // Check if both timestamps are valid
        if (
            $valueTimestamp === false // User input is not a valid date/time
            || $dateTimestamp === false // Comparison date is not a valid date/time
        ) {
            // Return false if either timestamp conversion failed
            return false;
        }

        // ---
        // For time-only comparisons, compare just the time portion
        // ---

        // Check if validation type is time-only
        if ($this->type === 'time') {
            // Extract time portion from user input timestamp
            $valueTime = \date(
                'H:i:s',
                $valueTimestamp
            );

            // Extract time portion from comparison date timestamp
            $dateTime = \date(
                'H:i:s',
                $dateTimestamp
            );

            // Compare time strings using the comparison method
            return $this->compareValues(
                $valueTime,
                $dateTime
            );
        }

        // ---
        // For datetime comparisons, ensure both values are valid datetime strings
        // ---

        // Check if validation type is datetime
        if ($this->type === 'datetime') {
            // Validate datetime format (YYYY-MM-DD HH:MM:SS). The `D` modifier
            // (PCRE_DOLLAR_ENDONLY) makes `$` match only the absolute end, so a
            // trailing newline is rejected (consistent with the UAMS-Web/uams-statamic-archive#1017 sweep).
            $datetimePattern = '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D';

            // Check if both values match the required datetime format
            if (
                ! \preg_match(
                    $datetimePattern,
                    $value
                )
                || ! \preg_match(
                    $datetimePattern,
                    $this->date
                )
            ) {
                // Return false if format validation failed
                return false;
            }

            // ---
            // Convert to UTC for consistent comparison
            // ---

            // Convert value to UTC timestamp for consistent comparison. strtotime()
            // reads the timezone token wherever it sits, so ' UTC' before the
            // datetime parses to the same timestamp as after it.
            // @pest-mutate-ignore: ConcatSwitchSides
            $valueTimestamp = \strtotime($value.' UTC');

            // Convert comparison date to UTC timestamp for consistent comparison.
            // As above, swapping the sides of the concatenation is equivalent.
            // @pest-mutate-ignore: ConcatSwitchSides
            $dateTimestamp = \strtotime($this->date.' UTC');

            // ---
            // Validate that UTC conversion was successful
            // ---

            // Check if UTC timestamp conversion was successful for both values.
            // Unreachable in practice: the strict-format check and the first strtotime()
            // above already rejected anything that cannot parse, and appending ' UTC'
            // has not been found to change whether such a string parses (400,000 sampled
            // strings across four timezones, none). So these mutants cannot be told
            // apart; this is a sampled bound, not a proof (#255). Each value has its
            // own guard so each annotation reaches exactly one mutant.
            if (
                // @pest-mutate-ignore: FalseToTrue
                $valueTimestamp === false
            ) {
                // Return false if the value's UTC conversion failed
                // @pest-mutate-ignore: FalseToTrue
                return false;
            }

            // Check if the comparison date's UTC conversion was successful
            if (
                // @pest-mutate-ignore: FalseToTrue
                $dateTimestamp === false
            ) {
                // Return false if the comparison date's UTC conversion failed
                // @pest-mutate-ignore: FalseToTrue
                return false;
            }
        }

        // ---
        // For date and datetime comparisons, use full timestamp comparison
        // ---
        return $this->compareValues(
            $valueTimestamp,
            $dateTimestamp
        );
    }

    /**
     * Get the validation error message.
     *
     * The type-specific lines (`date`, `time`, `datetime`) live under Statamic's
     * namespace, as `statamic::validation.<prefix>.<type>` in the app's
     * `lang/vendor/statamic/en/validation.php`, the way the app's other rules read
     * theirs. Looking them up as `validation.<prefix>.<type>` always missed, so every
     * type fell back to Laravel's generic date line (UAMS-Web/uams-statamic-archive#2945). A type with no line of
     * its own still falls back to `validation.<prefix>`.
     *
     * @return string The validation error message
     */
    public function message(): string
    {
        // Use the language file message based on type, with fallback to generic message
        $messageKey = sprintf('statamic::validation.%s.%s', $this->getMessageKeyPrefix(), $this->type);

        // ---
        // Check if the specific type message exists, otherwise use the generic fallback
        // ---

        // Check if translation key exists
        if (trans($messageKey) === $messageKey) {
            // Use generic validation message if type-specific message not found
            $messageKey = 'validation.'.$this->getMessageKeyPrefix();
        }

        // Return translated message with date parameter
        return trans(
            $messageKey,
            [
                'date' => $this->date,
            ]
        );
    }

    /**
     * Compare two values using the rule's comparison operator.
     *
     * @param  mixed  $value  The first value to compare
     * @param  mixed  $comparison  The second value to compare
     * @return bool True if the comparison passes, false otherwise
     */
    abstract protected function compareValues(
        mixed $value,
        mixed $comparison
    ): bool;

    /**
     * Get the message key prefix for this rule.
     *
     * @return string The message key prefix (e.g., 'after', 'before', 'date_equals')
     */
    abstract protected function getMessageKeyPrefix(): string;
}
