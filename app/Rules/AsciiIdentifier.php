<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates that a value is an ASCII identifier.
 *
 * Pattern: `[a-zA-Z_][a-zA-Z0-9_]*` — starts with an ASCII letter or underscore,
 * followed by zero or more ASCII letters, digits, or underscores.
 *
 * This is the lexical rule for identifiers in C, Java, and the ASCII subset of
 * most other languages. It is intentionally *tighter* than the strict
 * JavaScript / PHP / Python identifier rules, which additionally allow `$`
 * (JavaScript), high-bit bytes (PHP), or Unicode `ID_Start` / `ID_Continue`
 * characters (Python 3, modern JavaScript).
 *
 * Use this rule wherever a value must be safely usable as a variable name across
 * multiple consumers — for instance, a Statamic field handle whose value is
 * interpolated into a JavaScript expression (where `$` would have special meaning
 * in `String.replace` replacement strings) and a CSS class (where Unicode would
 * require escaping).
 */
class AsciiIdentifier implements ValidationRule
{
    /**
     * Validate that the input value matches the ASCII identifier pattern.
     *
     * Empty values pass so the `required` rule remains the single source of
     * truth for emptiness; pair with `required` when the field is mandatory.
     *
     * @param  string  $attribute  The name of the field being validated
     * @param  mixed  $value  The value of the field
     * @param  Closure  $fail  Closure to call with the error message on failure
     */
    public function validate(
        string $attribute,
        mixed $value,
        Closure $fail
    ): void {
        // Defer empty-value handling to the `required` rule
        if (! $value) {
            return;
        }

        // Check if the value is a string or a number
        if (
            ! is_string($value)
            && ! is_numeric($value)
        ) {
            // Fail validation with the error message
            $fail(__('statamic::validation.ascii_identifier.invalid_type'));

            // Return early to skip validation
            return;
        }

        // Check if the value matches the ASCII identifier pattern
        if (
            ! preg_match(
                '/^[a-zA-Z_]\w*$/D',
                (string) $value
            )
        ) {
            // Fail validation with the error message
            $fail(__('statamic::validation.ascii_identifier.invalid_format'));
        }
    }
}
