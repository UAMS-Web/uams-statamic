<?php

declare(strict_types=1);

namespace App\Rules;

use App\Rules\BaseRules\BaseDateComparisonRule;

/**
 * Custom validation rule that extends Laravel's after functionality
 * to support different data types (date, time, datetime) with specific messages.
 *
 * Usage Examples:
 *   - Date comparison: 'after:2024-01-01,date'
 *   - Time comparison: 'after:09:00,time'
 *   - DateTime comparison: 'after:2024-01-01 14:30:00,datetime'
 *   - Default (date): 'after:2024-01-01'
 *
 * Type-specific behavior:
 *   - 'date': Basic date comparison (e.g., "2024-01-01")
 *   - 'time': Time-only comparison (e.g., "14:30:00")
 *   - 'datetime': Strict datetime format validation + UTC comparison
 *     (e.g., "2024-01-01 14:30:00")
 */
class After extends BaseDateComparisonRule
{
    /**
     * Compare two values using the greater than operator.
     *
     * @param  mixed  $value  The first value to compare
     * @param  mixed  $comparison  The second value to compare
     * @return bool True if value is greater than comparison, false otherwise
     */
    protected function compareValues(
        mixed $value,
        mixed $comparison
    ): bool {
        return $value > $comparison;
    }

    /**
     * Get the message key prefix for this rule.
     *
     * @return string The message key prefix 'after'
     */
    protected function getMessageKeyPrefix(): string
    {
        return 'after';
    }
}
