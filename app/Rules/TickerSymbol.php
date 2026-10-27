<?php

declare(strict_types=1);

namespace App\Rules;

use App\Rules\BaseRules\BaseValidationRule;
use Closure;

/**
 * Custom validation rule for validating stock ticker symbols.
 *
 * This rule ensures ticker symbols conform to a format commonly used on NASDAQ
 * and NYSE exchanges, consisting of one to five uppercase letters, optionally
 * followed by a period and a specific suffix (e.g., `A`, `AAPL`, `XYZ.PK`). The
 * pattern is based on NASDAQ and NYSE ticker symbol conventions. It rejects
 * invalid formats, lowercase letters, or symbols that don't match the allowed
 * structure, including unrecognized suffixes.
 *
 * The regular expression pattern is /^[A-Z]{1,5}(?:\.(?:[A-Z]|PK?|SC?|NM?))?$/
 *
 * This pattern ensures:
 *   - One to five uppercase letters ([A-Z]{1,5}),
 *   - Optionally followed by a period and a suffix (?:\.(?:[A-Z]|PK?|SC?|NM?)),
 *   - The suffix is either a single uppercase letter (e.g., `N`) or a recognized
 *     code (i.e., `PK`, `SC`, `NM`),
 *   - No extra characters, spaces or special symbols (except the optional dot)
 *     are allowed.
 *
 * If the pattern doesn't match, the validation fails with a specific message.
 */
class TickerSymbol extends BaseValidationRule
{
    /**
     * Validate that the input value conforms to the ticker symbol format for NASDAQ
     * and NYSE exchanges using a regular expression.
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
        // Check if the value matches the ticker symbol format for NASDAQ and NYSE
        if (
            ! \preg_match(
                '/^[A-Z]{1,5}(?:\.(?:[A-Z]|PK?|SC?|NM?))?$/D',
                $value
            )
        ) {
            // If the pattern doesn't match, fail validation with a specific message
            $fail(__('statamic::validation.ticker_symbol'));
        }
    }
}
