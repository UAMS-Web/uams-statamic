<?php

declare(strict_types=1);

namespace App\Rules\BaseRules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Base validation rule class that provides common functionality for all custom
 * validation rules.
 *
 * This class handles common validation scenarios such as:
 *   - Empty value handling
 *   - Array value detection and conversion
 *   - Type safety for validation operations
 */
abstract class BaseValidationRule implements ValidationRule
{
    /**
     * Validate the given value.
     *
     * This method automatically handles value preparation and delegates to the child
     * class for specific validation logic.
     *
     * @param  string  $attribute  The name of the field being validated
     * @param  mixed  $value  The value of the field
     * @param  Closure  $fail  The closure to call if validation fails
     */
    final public function validate(
        string $attribute,
        mixed $value,
        Closure $fail
    ): void {
        // Prepare the value for validation, ensuring it's a string.
        $value = $this->prepareValueForValidation(
            $value,
            $fail
        );

        // ---
        // If the value is null, validation should be skipped.
        // ---

        // Check if the value is null
        if ($value === null) {
            // Skip validation for null values
            return;
        }

        // Delegate to the child class for specific validation logic.
        $this->validateValue(
            $attribute,
            $value,
            $fail
        );
    }

    /**
     * Validate the prepared string value.
     *
     * Child classes must implement this method to provide specific validation logic.
     * The value parameter is guaranteed to be a non-null string.
     *
     * @param  string  $attribute  The name of the field being validated
     * @param  string  $value  The prepared string value to validate
     * @param  Closure  $fail  The closure to call if validation fails
     */
    abstract protected function validateValue(
        string $attribute,
        string $value,
        Closure $fail
    ): void;

    /**
     * Prepare the value for validation by ensuring it's a string.
     *
     * This method handles common validation scenarios:
     *   - Returns early if the value is empty (allows other rules like 'required' to
     *     handle this)
     *   - Converts arrays to strings or fails validation
     *   - Ensures the value is a string for regex operations
     *
     * @param  mixed  $value  The value to prepare
     * @param  Closure  $fail  The closure to call if validation fails
     * @param  string  $errorMessage  The error message to show if the value is an array
     * @return ?string The prepared string value, or null if validation should be skipped
     */
    protected function prepareValueForValidation(
        mixed $value,
        Closure $fail,
        string $errorMessage = 'The value must be a string, not an array.'
    ): ?string {
        // ---
        // Skip validation if the value is empty to allow other rules (e.g., 'required')
        // to handle this case, reducing unnecessary checks
        // ---

        // Check if the value is empty
        if (! $value) {
            // Return null to skip validation for empty values
            return null;
        }

        // ---
        // Ensure the value is a string before proceeding with validation. If an array is
        // passed, fail validation with a specific message.
        // ---

        // Check if the value is an array
        if (\is_array($value)) {
            // Fail validation with the error message
            $fail($errorMessage);

            // Return null after failing validation
            return null;
        }

        // Cast any remaining value to a string to preserve the original behavior.
        return (string) $value; // @phpstan-ignore cast.string
    }

    /**
     * Check if a value is valid without triggering the fail closure.
     *
     * This method allows other validation rules to check if a value would pass
     * validation without actually performing the validation (which would trigger
     * error messages).
     *
     * @param  mixed  $value  The value to check
     * @return bool True if the value would pass validation, false otherwise
     */
    public function isValid(
        mixed $value
    ): bool {
        // Track whether validation would pass.
        $isValid = true;

        // Prepare the value for validation, ensuring it's a string. Preparation can
        // itself fail (an array, for instance), and `validate()` reports that failure,
        // so record it here too rather than dropping it (UAMS-Web/uams-statamic-archive#2946).
        $preparedValue = $this->prepareValueForValidation(
            $value,
            function ($message) use (&$isValid): void {
                // Set the validation flag to false
                $isValid = false;
            }
        );

        // ---
        // If the value is null after preparation, there is nothing left to check: it was
        // either empty (valid, since emptiness is left to rules like 'required') or it
        // failed preparation (invalid, recorded above).
        // ---

        // Check if the prepared value is null
        if ($preparedValue === null) {
            // Return the preparation verdict
            return $isValid;
        }

        // Perform validation with a fail closure that sets the flag to false. The
        // attribute only names the field in messages, which this check discards.
        $this->validateValue(
            // @pest-mutate-ignore: EmptyStringToNotEmpty
            '',
            $preparedValue,
            function ($message) use (&$isValid): void {
                // Set the validation flag to false
                $isValid = false;
            }
        );

        // Return the validation result
        return $isValid;
    }
}
