<?php

declare(strict_types=1);

namespace App\Rules;

use App\Rules\BaseRules\BaseValidationRule;
use Closure;

/**
 * Custom validation rule for validating decimal longitude values.
 *
 * This rule ensures longitude values are in decimal form, ranging from -180.0 to
 * 180.0, without cardinal direction indicators (e.g., E, W). It accepts both
 * positive and negative numbers (e.g., "-122.4194" or "122.4194") and rejects
 * invalid formats, non-numeric values, or values outside the valid range.
 */
class Longitude extends BaseValidationRule
{
    /**
     * Validate that the input value conforms to the decimal longitude format.
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

        $longitude = floatval($value);

        /**
         * Validate that the longitude is within the valid range of -180.0 to 180.0.
         * If outside this range, fail with a specific message.
         */
        if ($longitude < -180.0 || $longitude > 180.0) {
            $fail(__('statamic::validation.longitude'));
        }
    }
}
