<?php

declare(strict_types=1);

namespace App\Rules;

use App\Rules\BaseRules\BaseValidationRule;
use Closure;

/**
 * Custom validation rule for validating values using the Luhn algorithm.
 *
 * This rule implements the Luhn algorithm (also known as the "mod 10" algorithm)
 * to validate check digits in various identification numbers. It supports
 * optional prepend and append parameters to handle cases where the validation
 * value needs to be combined with additional strings before validation.
 *
 * Examples:
 *   - NPI validation: prepend '80840' to the NPI value before validation
 *   - Credit card validation: validate the full card number directly
 *   - ISBN validation: validate with or without check digit
 */
class Luhn extends BaseValidationRule
{
    /**
     * Each digit's contribution when doubled: twice the digit, less 9 when that is
     * two digits (for example 7 doubles to 14, which contributes 5).
     *
     * @var list<int>
     */
    private const array DOUBLED_DIGITS = [0, 2, 4, 6, 8, 1, 3, 5, 7, 9];

    /**
     * Create a new Luhn validation rule instance.
     *
     * @param  ?string  $prepend  Optional string to prepend to the value before validation
     * @param  ?string  $append  Optional string to append to the value before validation
     */
    public function __construct(private readonly ?string $prepend = null, private readonly ?string $append = null) {}

    /**
     * Validate that the input value passes the Luhn algorithm check.
     *
     * This method combines the value with optional prepend/append strings,
     * then validates the resulting string using the Luhn algorithm.
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
        // Build the complete string for validation by combining prepend, value, and append
        $validationString = $this->buildValidationString($value);

        // Check if the validation string contains only digits.
        if (
            ! \preg_match(
                '/^\d+$/D',
                $validationString
            )
        ) {
            // ---
            // If the validation string does not contain only digits, fail validation with a
            // specific message
            // ---
            $fail(__('statamic::validation.luhn.format'));

            // Return early to prevent further validation
            return;
        }

        // Check if the validation string fails Luhn algorithm validation
        if (! self::isValidLuhn($validationString)) {
            // ---
            // If the validation string fails Luhn algorithm validation, fail validation with a
            // specific message
            // ---
            $fail(__('statamic::validation.luhn.check_failed'));
        }
    }

    /**
     * Build the complete validation string by combining prepend, value, and append.
     *
     * @param  string  $value  The original value to validate
     * @return string The complete string for Luhn validation
     */
    private function buildValidationString(
        string $value
    ): string {
        // Initialize an empty array to store the parts of the validation string
        $parts = [];

        // Check if prepend string is provided
        if ($this->prepend !== null) {
            // Add prepend string to parts array
            $parts[] = $this->prepend;
        }

        // Add the original value.
        $parts[] = $value;

        // Check if append string is provided
        if ($this->append !== null) {
            // Add append string to parts array
            $parts[] = $this->append;
        }

        // Combine all parts into a single string
        return \implode(
            '',
            $parts
        );
    }

    /**
     * Validate a string using the Luhn algorithm.
     *
     * The Luhn algorithm works as follows:
     *   1. Starting from the rightmost digit, double every second digit
     *   2. If doubling results in a number greater than 9, subtract 9
     *   3. Sum all the digits
     *   4. If the sum is divisible by 10, the number is valid
     *
     * @param  string  $number  The numeric string to validate
     * @return bool True if the number passes Luhn validation, false otherwise
     */
    public static function isValidLuhn(
        string $number
    ): bool {
        // ---
        // Convert the string to an array of digits for processing
        // ---

        // Split the number into an array of digit characters
        $digits = str_split($number);

        // Initialize sum to zero for accumulating digit values
        $sum = 0;

        // ---
        // Process each digit from right to left
        // ---

        // Start at the rightmost digit, which is not doubled
        $double = false;

        // Iterate through digits from rightmost to leftmost
        for ($i = \count($digits) - 1; $i >= 0; $i--) {
            // Cast the digit character to an integer. Equivalent without the cast:
            // both callers allow digits only, which PHP reads as integers anyway.
            // @pest-mutate-ignore: RemoveIntegerCast
            $digit = (int) $digits[$i];

            // Add the digit, or its doubled contribution for every second digit.
            // Equivalent subtracting: a negated sum is divisible by 10 exactly when
            // the sum is.
            // @pest-mutate-ignore: PlusEqualToMinusEqual
            $sum += $double
                ? self::DOUBLED_DIGITS[$digit]
                : $digit;

            // Alternate doubling for the next digit to the left
            $double = ! $double;
        }

        // The number is valid if the sum is divisible by 10
        return $sum % 10 === 0;
    }
}
