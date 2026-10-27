<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Custom validation rule for identifier regex validation with custom error
 * messages.
 *
 * This rule validates identifiers against regex patterns and provides
 * specific error messages based on the identifier type.
 */
class IdentifierRegex implements ValidationRule
{
    /**
     * Create a new rule instance.
     *
     * @param  string  $pattern  The regex pattern
     * @param  string  $message  The custom error message
     */
    public function __construct(protected string $pattern, protected string $message) {}

    /**
     * Validate that the input value matches the regex pattern.
     *
     * @param  string  $attribute  The name of the field being validated
     * @param  mixed  $value  The value of the field
     * @param  Closure  $fail  The closure to call if validation fails
     */
    public function validate(
        string $attribute,
        mixed $value,
        Closure $fail
    ): void {
        // ---
        // Skip validation if the value is empty to allow other rules (e.g., 'required')
        // to handle this case, reducing unnecessary checks
        // ---

        // Check if the value is empty
        if (! $value) {
            // Return early to allow other rules to handle empty values
            return;
        }

        // ---
        // Convert array values to string for validation. Statamic sometimes passes field
        // values as arrays.
        // ---

        // Check if the value is an array
        if (is_array($value)) {
            // Coerce each element to a string so implode receives a string array
            $stringParts = array_map(
                static fn (mixed $part): string => is_scalar($part) ? (string) $part : '',
                $value
            );

            // Convert array to string by joining elements
            $value = implode(
                '',
                $stringParts
            );
        }

        // Ensure the value is a string for preg_match
        $value = is_scalar($value) ? (string) $value : '';

        // Check if the value matches the regex pattern
        if (
            ! preg_match(
                $this->pattern,
                $value
            )
        ) {
            // Call the fail closure with the custom error message
            $fail($this->message);
        }
    }
}
