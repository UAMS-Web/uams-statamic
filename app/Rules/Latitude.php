<?php

declare(strict_types=1);

namespace App\Rules;

use App\Rules\BaseRules\BaseValidationRule;
use Closure;

/**
 * Custom validation rule for validating decimal latitude values.
 *
 * This rule ensures latitude values are in decimal form, ranging from `-90.0` to
 * `90.0`, without cardinal direction indicators (e.g., `N`, `S`). It accepts both
 * positive and negative numbers (e.g., `37.7749` or `-37.7749`) and rejects
 * invalid formats, non-numeric values, or values outside the valid range.
 */
class Latitude extends BaseValidationRule
{
    /**
     * Validate that the input value conforms to the decimal latitude format.
     *
     * @param  string  $attribute  The name of the field being validated
     * @param  string  $value  The prepared string value to validate
     * @param  Closure  $fail  The closure to call if validation fails
     */
    protected function validateValue(
        string $attribute,
        string $value,
        Closure $fail
    ): void {
        /**
         * Check if the value is a valid numeric string or number and convert it to float.
         * If not numeric, fail with a specific message.
         */
        if (! \is_numeric($value)) {
            $fail(__('statamic::validation.decimal_valid'));

            return;
        }

        $latitude = floatval($value);

        /**
         * Validate that the latitude is within the valid range of `-90.0` to `90.0`.
         * If outside this range, fail with a specific message.
         */
        if ($latitude < -90.0 || $latitude > 90.0) {
            $fail(__('statamic::validation.latitude'));
        }
    }
}
