<?php

declare(strict_types=1);

namespace App\Rules;

use App\Rules\BaseRules\BaseValidationRule;
use Closure;

/**
 * Custom validation rule for validating values using the GS1 modulo-10 check
 * digit algorithm.
 *
 * This rule implements the GS1 modulo-10 check digit algorithm to validate check
 * digits in various GS1 identification numbers such as Global Location Numbers
 * (GLNs), Global Trade Item Numbers (GTINs), and other GS1 standards.
 *
 * The GS1 modulo-10 check digit algorithm:
 *   - Take the first 12 digits (base number) and the 13th digit (check digit)
 *   - Multiply digits in odd positions (1st, 3rd, 5th, 7th, 9th, 11th) by 1
 *   - Multiply digits in even positions (2nd, 4th, 6th, 8th, 10th, 12th) by 3
 *   - Sum all the products
 *   - Compute the sum modulo 10 (remainder when divided by 10)
 *   - If the remainder is 0, the expected check digit is 0; otherwise, subtract
 *     the remainder from 10 to get the expected check digit
 *   - Compare the expected check digit with the actual 13th digit
 *
 * @see  https://ref.gs1.org/standards/genspecs/
 */
class Gs1Modulo10 extends BaseValidationRule
{
    /**
     * Validate that the input value passes the GS1 modulo-10 check digit algorithm.
     *
     * This method validates that the value is a 13-digit number and that the check
     * digit is correct according to the GS1 modulo-10 algorithm.
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
        // Check if the value contains only digits and is exactly 13 digits long
        if (
            ! \preg_match(
                '/^\d{13}$/D',
                $value
            )
        ) {
            // If the value is not a 13-digit number, fail validation with a specific message
            $fail(__('statamic::validation.gs1_modulo_10.format'));

            // Return early to prevent further validation
            return;
        }

        // Validate the value using the GS1 modulo-10 check digit algorithm
        if (! self::isValidGs1Modulo10($value)) {
            // ---
            // If the value fails GS1 modulo-10 check digit validation, fail validation with a
            // specific message
            // ---
            $fail(__('statamic::validation.gs1_modulo_10.check_digit_failed'));
        }
    }

    /**
     * Validate a 13-digit number using the GS1 modulo-10 check digit algorithm.
     *
     * The algorithm works as follows:
     *   1. Take the first 12 digits (base number) and the 13th digit (check digit)
     *   2. Multiply digits in odd positions (1st, 3rd, 5th, 7th, 9th, 11th) by 1
     *   3. Multiply digits in even positions (2nd, 4th, 6th, 8th, 10th, 12th) by 3
     *   4. Sum all the products
     *   5. Compute the sum modulo 10 (remainder when divided by 10)
     *   6. If the remainder is 0, the expected check digit is 0; otherwise, subtract
     *      the remainder from 10 to get the expected check digit
     *   7. Compare the expected check digit with the actual 13th digit
     *
     * @param  string  $number  The 13-digit number to validate
     * @return bool True if the number passes GS1 modulo-10 validation, false otherwise
     */
    public static function isValidGs1Modulo10(
        string $number
    ): bool {
        // ---
        // Extract the base number (first 12 digits) and check digit (13th digit)
        // ---

        // Extract the base number (first 12 digits) from the input number
        $baseNumber = \substr(
            $number,
            0,
            12
        );

        // Extract the actual check digit (13th digit) from the input number
        $actualCheckDigit = (int) $number[12];

        // ---
        // Calculate the expected check digit using the GS1 algorithm
        // ---

        // Initialize the sum accumulator to zero for calculating the weighted sum
        $sum = 0;

        // Split the base number into an array of individual digit characters
        $digits = str_split($baseNumber);

        // ---
        // Process each of the first 12 digits according to the GS1 algorithm
        // ---

        // Iterate through each of the 12 digits to calculate the weighted sum
        for ($i = 0; $i < 12; $i++) {
            // Convert the current digit character to an integer value
            $digit = (int) $digits[$i];

            // Calculate the 1-based position of the current digit for the algorithm
            $position = $i + 1;

            // ---
            // Multiply by 1 for odd positions, by 3 for even positions.
            // ---

            // Check if the current position is odd (1st, 3rd, 5th, etc.)
            if ($position % 2 === 1) {
                // Add the digit to the sum (multiply by 1 for odd positions)
                $sum += $digit;
            } else { // Otherwise
                // Multiply by 3
                $sum += $digit * 3;
            }
        }

        // ---
        // Calculate the expected check digit.
        // ---

        // Calculate the remainder when the sum is divided by 10
        $remainder = $sum % 10;

        // Calculate the expected check digit based on the remainder
        $expectedCheckDigit = $remainder === 0 ? 0 : 10 - $remainder;

        // Compare the expected check digit with the actual check digit
        return $expectedCheckDigit === $actualCheckDigit;
    }
}
