<?php

declare(strict_types=1);

namespace App\Rules;

use App\Rules\BaseRules\BaseDateComparisonRule;

/**
 * Custom validation rule that extends Laravel's date_equals functionality
 * to support different data types (date, time, datetime) with specific messages.
 *
 * Usage Examples:
 *   - Date comparison: 'date_equals:2024-01-01,date'
 *   - Time comparison: 'date_equals:09:00,time'
 *   - DateTime comparison: 'date_equals:2024-01-01 14:30:00,datetime'
 *   - Default (date): 'date_equals:2024-01-01'
 *
 * Type-specific behavior:
 *   - 'date': Basic date comparison (e.g., "2024-01-01")
 *   - 'time': Time-only comparison (e.g., "14:30:00")
 *   - 'datetime': Strict datetime format validation + UTC comparison
 *     (e.g., "2024-01-01 14:30:00")
 */
class DateEquals extends BaseDateComparisonRule
{
    /**
     * Compare two values using the equality operator.
     *
     * @param  mixed  $value  The first value to compare
     * @param  mixed  $comparison  The second value to compare
     * @return bool True if value equals comparison, false otherwise
     */
    protected function compareValues(
        mixed $value,
        mixed $comparison
    ): bool {
        return $value === $comparison;
    }

    /**
     * Get the message key prefix for this rule.
     *
     * @return string The message key prefix 'date_equals'
     */
    protected function getMessageKeyPrefix(): string
    {
        return 'date_equals';
    }
}
