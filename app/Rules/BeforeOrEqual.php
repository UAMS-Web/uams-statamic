<?php

declare(strict_types=1);

namespace App\Rules;

use App\Rules\BaseRules\BaseDateComparisonRule;

/**
 * Custom validation rule that extends Laravel's before_or_equal functionality
 * to support different data types (date, time, datetime) with specific messages.
 *
 * Usage Examples:
 *   - Date comparison: 'before_or_equal:2024-01-01,date'
 *   - Time comparison: 'before_or_equal:09:00,time'
 *   - DateTime comparison: 'before_or_equal:2024-01-01 14:30:00,datetime'
 *   - Default (date): 'before_or_equal:2024-01-01'
 *
 * Type-specific behavior:
 *   - 'date': Basic date comparison (e.g., "2024-01-01")
 *   - 'time': Time-only comparison (e.g., "14:30:00")
 *   - 'datetime': Strict datetime format validation + UTC comparison
 *     (e.g., "2024-01-01 14:30:00")
 */
class BeforeOrEqual extends BaseDateComparisonRule
{
    /**
     * Compare two values using the less than or equal operator.
     *
     * @param  mixed  $value  The first value to compare
     * @param  mixed  $comparison  The second value to compare
     * @return bool True if value is less than or equal to comparison, false otherwise
     */
    protected function compareValues(
        mixed $value,
        mixed $comparison
    ): bool {
        return $value <= $comparison;
    }

    /**
     * Get the message key prefix for this rule.
     *
     * @return string The message key prefix 'before_or_equal'
     */
    protected function getMessageKeyPrefix(): string
    {
        return 'before_or_equal';
    }
}
