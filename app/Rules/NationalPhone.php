<?php

declare(strict_types=1);

namespace App\Rules;

use App\Enums\Country;
use App\Services\PhoneUtil;
use Exception;
use Illuminate\Contracts\Validation\Rule;
use Propaganistas\LaravelPhone\PhoneNumber;

/**
 * Custom validation rule for national phone numbers.
 *
 * This rule validates that a given value is a valid national phone number
 * for a specified country using the PhoneNumber class from the Laravel Phone
 * package.
 */
class NationalPhone implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @param  string  $country  The country code for validation
     */
    public function __construct(protected string $country = 'US') {}

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute  The name of the attribute being validated
     * @param  mixed  $value  The value to validate as a phone number
     * @return bool True if the value is a valid phone number, false otherwise
     */
    public function passes(
        $attribute,
        mixed $value
    ): bool {
        // ---
        // Skip validation if value is empty
        // ---

        // Check if the value is empty. Removing the return is equivalent: every
        // empty value cleans to '' or '0', which PhoneNumber never reports valid.
        // @pest-mutate-ignore: RemoveEarlyReturn
        if (empty($value)) {
            // Return false to indicate validation failure
            return false;
        }

        // Clean up the input value. EmptyStringToNotEmpty is equivalent here: the
        // mutator's replacement text is not a valid number in any region the
        // phone library supports, so a non-scalar value fails either way.
        // @pest-mutate-ignore: EmptyStringToNotEmpty
        $cleanedValue = PhoneUtil::cleanInput(is_scalar($value) ? (string) $value : '');

        // Attempt to parse and validate the phone number
        try {
            // Check if the value is in E.164 format (starts with '+'). The '' guard
            // is equivalent to its mutant: an empty value read at offset 0 is not
            // '+' (the read's warning, raised as an exception, is caught below),
            // and the national parse rejects an empty value too.
            // @pest-mutate-ignore: EmptyStringToNotEmpty
            if (
                $cleanedValue !== ''
                && $cleanedValue[0] === '+'
            ) {
                // Parse and validate as E.164
                $phone = new PhoneNumber($cleanedValue);

                // Ensure the number is valid and matches the specified country
                return $phone->isValid() && $phone->getCountry() === $this->country;
            }

            // Otherwise
            // Parse and validate as a national number for the specified country
            $phone = new PhoneNumber(
                $cleanedValue,
                $this->country
            );

            // Return true if the number is valid for the country
            return $phone->isValid();
        }

        // Catch exceptions from phone number parsing
        catch (Exception) {
            // Return false to indicate validation failure when parsing fails
            return false;
        }
    }

    /**
     * Get the validation error message for a national phone number.
     *
     * Attempts to translate the country code to a full country name using the
     * App\Enums\Country enum. If the code is not found, falls back to the code
     * itself. Also provides a country-specific example number if available.
     *
     * @return string The validation error message
     */
    public function message(): string
    {
        // Default to the country code; will attempt to translate to full name below
        $countryName = $this->country;

        // Try to get the country name from the Country enum
        try {
            // Check if the country code exists in the Country enum
            if (Country::tryFrom($this->country)) {
                // Get the sentence-formatted country name from the enum
                $countryName = Country::from($this->country)->sentenceName();
            }
        }

        // Catch exceptions from enum lookup
        catch (\Throwable) {
            // Fall back to the country code
            $countryName = $this->country;
        }

        // Generate an example national phone number for the country, if possible
        $example = PhoneUtil::exampleNational($this->country);

        // Check if an example phone number is available
        if ($example) {
            // Return message with country name and example phone number
            return sprintf('Enter a valid national phone number for %s (e.g., %s).', $countryName, $example);
        }

        // Otherwise, return a generic message with the country name
        return sprintf('Enter a valid national phone number for %s.', $countryName);
    }
}
