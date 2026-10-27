<?php

declare(strict_types=1);

namespace App\Rules;

use App\Rules\BaseRules\BaseValidationRule;
use Closure;
use Illuminate\Support\Facades\Http;

/**
 * Custom validation rule for validating National Provider Identifiers (NPIs)
 * against the National Plan and Provider Enumeration System (NPPES) API and
 * format requirements.
 *
 * This rule ensures NPIs are 10-digit numbers and exist in the NPPES registry.
 *
 * First, the input value is validated to ensure it is exactly 10 digits using a
 * regular expression. If not, the validation fails with a specific message.
 *
 * Then, the input value is validated to ensure it passes the Luhn algorithm using
 * the Luhn class. If not, the validation fails with a specific message.
 *
 * Finally, the input value is validated to ensure it exists in the NPPES registry
 * using the NPPES API. If not, the validation fails with a specific message.
 *
 * @see  https://www.cms.gov/Regulations-and-Guidance/Administrative-Simplification/NationalProvIdentStand/Downloads/NPIcheckdigit.pdf
 */
class Npi extends BaseValidationRule
{
    /**
     * Validate the NPI against the NPPES API and format requirements.
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
        // Check if the NPI matches the required format of exactly 10 digits using a
        // regular expression
        // ---
        if (
            ! \preg_match(
                '/^\d{10}$/D',
                $value
            )
        ) {
            // ---
            // If the NPI does not match the required format, fail validation with a specific
            // message
            // ---
            $fail(__('statamic::validation.npi.format'));

            // Return early to prevent further validation
            return;
        }

        // ---
        // Validate the NPI using the Luhn algorithm with the '80840' prefix.
        // NPIs are validated by prepending '80840' to the NPI value and checking
        // if the resulting string passes the Luhn algorithm.
        // ---

        // Create Luhn validation string with prefix
        $luhnString = '80840'.$value;

        // Check if Luhn validation fails
        if (! Luhn::isValidLuhn($luhnString)) {
            // ---
            // If the NPI does not pass the Luhn algorithm, fail validation with a specific
            // message
            // ---
            $fail(__('statamic::validation.npi.invalid'));

            // Return early to prevent further validation
            return;
        }

        // ---
        // Query the NPPES (National Plan and Provider Enumeration System) API to verify
        // if the NPI exists. The API endpoint is accessed via HTTP GET with version 2.1
        // and the NPI value as parameters.
        // ---
        $response = Http::get(
            'https://npiregistry.cms.hhs.gov/api/',
            [
                'version' => '2.1',
                'number' => $value,
            ]
        );

        // Check if the API request failed (e.g., network issues, server error)
        if ($response->failed()) {
            // If the API request failed, fail validation with a specific message
            $fail(__('statamic::validation.npi.api_error'));

            // Return early to prevent further validation
            return;
        }

        // ---
        // Parse the JSON response from the NPPES API to check if the NPI exists. The
        // 'result_count' field indicates the number of matching records; 0 means the NPI
        // is not valid or doesn't exist.
        // ---
        /** @var array<string, mixed> $data */
        $data = $response->json();

        // Check if the NPI exists in the NPPES registry
        if ($data['result_count'] === 0) {
            // If no results are found, fail validation with a specific message
            $fail(__('statamic::validation.npi.not_found'));
        }
    }
}
