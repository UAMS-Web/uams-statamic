<?php

declare(strict_types=1);

namespace App\Rules;

use App\Rules\BaseRules\BaseValidationRule;
use App\Services\PubMed\PubMedEsummaryService;
use Closure;

/**
 * Custom validation rule for validating PubMed Identifiers (PMIDs).
 *
 * First, the input value validated to ensure it is a one- to eight-digit number
 * starting with 1-9 (e.g., '1', '123', '12345678') using a regular expression. If
 * not, the validation fails with a specific message.
 *
 * Then, the input value is validated to ensure it exists in the PubMed API using
 * the E-Utilities API, leveraging caching for performance and rate limiting. If
 * not, the validation fails with a specific message.
 *
 * @see  https://www.ncbi.nlm.nih.gov/books/NBK25497/
 */
class Pmid extends BaseValidationRule
{
    /**
     * Validate that the input value is a valid PubMed Identifier (PMID) by checking
     * format and existence via the PubMed API.
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
        // ---
        // Check if the value matches the required format of a one- to eight-digit number
        // starting with 1-9 (e.g., '1', '123', '12345678')
        // ---
        if (
            ! \preg_match(
                '/^[1-9]\d{0,7}$/D',
                $value
            )
        ) {
            // ---
            // If the value does not match the required format, fail validation with a specific
            // message
            // ---
            $fail(__('statamic::validation.pubmed.pmid.format'));

            // Return early to prevent further validation
            return;
        }

        // ---
        // Check if the value exists in the PubMed API using the E-Utilities API,
        // leveraging caching for performance and rate limiting.
        // ---

        if (! app(PubMedEsummaryService::class)->pmidExists($value)) {
            $fail(__('statamic::validation.pubmed.pmid.unverified'));
        }
    }
}
