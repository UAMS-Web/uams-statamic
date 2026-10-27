<?php

declare(strict_types=1);

namespace App\Rules;

use App\Services\PhoneUtil;
use Exception;
use Illuminate\Contracts\Validation\Rule;
use Propaganistas\LaravelPhone\PhoneNumber;

/**
 * Custom validation rule for international phone numbers.
 *
 * This rule validates that a given value is a valid international phone number
 * using the PhoneNumber class from the Laravel Phone package.
 */
class InternationalPhone implements Rule
{
    /**
     * Validates if the given value is a valid phone number.
     *
     * This method checks if the provided value forms a valid phone number using the
     * PhoneNumber class. If the initial validation fails and the value does not start
     * with a `+`, it attempts validation again by prepending a `+` to the value.
     *
     * @param  string  $attribute  The name of the attribute being validated
     * @param  mixed  $value  The value to validate as a phone number
     * @return bool True if the value is a valid phone number, false otherwise
     */
    public function passes(
        $attribute,
        mixed $value
    ): bool {
        // Check if the value is empty. Removing the return is equivalent: an empty
        // non-scalar fails the is_scalar() check, and '', '0', 0 and false become
        // '+0' or an empty string, which PhoneNumber never reports valid.
        // @pest-mutate-ignore: RemoveEarlyReturn
        if (empty($value)) {
            // Return false to indicate validation failure
            return false;
        }

        // Ensure the value is a scalar before casting it to a string
        if (! is_scalar($value)) {
            // Return false to indicate validation failure for non-scalar input
            return false;
        }

        // Clean up the input value
        $cleanedValue = PhoneUtil::cleanInput((string) $value);

        // Ensure the value starts with '+' for international format
        $cleanedValue = PhoneUtil::addPlusIfMissing($cleanedValue);

        // Attempt to parse and validate the phone number
        try {
            // Attempt to parse and validate the phone number as entered by the user
            $phone = new PhoneNumber($cleanedValue);

            // Check if the phone number is valid
            if ($phone->isValid()) {
                // Return true to indicate validation success
                return true;
            }
        }

        // Catch exceptions from phone number parsing
        catch (Exception) {
            // Return false to indicate validation failure when parsing fails
            return false;
        }

        // Return false to indicate validation failure when all attempts fail
        return false;
    }

    /**
     * Get the validation error message.
     *
     * @return string The validation error message
     */
    public function message(): string
    {
        return 'Enter a valid international phone number (e.g., `+1-555-555-5555`).';
    }
}
