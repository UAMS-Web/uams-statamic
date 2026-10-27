<?php

declare(strict_types=1);

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberType;
use libphonenumber\PhoneNumberUtil;
use Propaganistas\LaravelPhone\PhoneNumber;

/**
 * Utility class for phone number operations.
 *
 * This class provides methods for cleaning and formatting phone numbers.
 */
class PhoneUtil
{
    /**
     * Ensure a phone number string starts with '+', if not already present.
     *
     * @param  string  $value  The phone number string
     * @return string The phone number with '+' prefix if missing
     */
    public static function addPlusIfMissing(
        string $value
    ): string {
        // Check if the value is not empty and does not start with '+'
        if (
            $value !== ''
            && $value[0] !== '+'
        ) {
            // Prepend a '+' to the value
            return '+'.$value;
        }

        // Otherwise, return the value unchanged
        return $value;
    }

    /**
     * Clean up the input value by trimming whitespace and casting to string.
     *
     * @param  string  $value  The input value to clean
     * @return string The cleaned string value
     */
    public static function cleanInput(
        string $value
    ): string {
        // Trim whitespace and cast to string
        return \trim($value);
    }

    /**
     * Format a phone number for display.
     *
     * @param  string  $e164  The E.164 formatted phone number
     * @param  string  $format  The desired format ('national' or 'international')
     * @return string The formatted phone number
     */
    public static function formatForDisplay(
        string $e164,
        string $format = 'national'
    ): string {
        // Attempt to format the phone number using the phone number library
        try {
            // Parse the E.164 phone number
            $phone = new PhoneNumber($e164);

            // Get the country code for the phone number
            $country = $phone->getCountry();
            // ---
            // Determine the desired display format
            // ---
            // Check if the desired format is international
            // The fall-through below returns the international format too, so this early return
            // changes only the path taken.
            // @pest-mutate-ignore: RemoveEarlyReturn
            if ($format === 'international') {
                // Return the international format (e.g., +1 555-555-5555)
                return $phone->formatInternational();
            }

            if ($format === 'national') {
                // Check if the desired format is national
                // Format the phone number as a national number
                $national = $phone->formatNational();
                // Check if the phone number is in the United States
                if ($country === 'US') {
                    // U.S.: Convert (555) 555-5555 to 555-555-5555 (AP Style)
                    $nationalApStyle = \preg_replace(
                        '/^\((\d{3})\)\s*(\d{3}-\d{4})$/',
                        '$1-$2',
                        $national
                    );

                    // Return the AP Style U.S. national format,
                    // falling back to the unmodified national format if
                    // the replacement could not be applied
                    return $nationalApStyle ?? $national;
                }

                // Otherwise (non-US)
                // Return the national format using the library's default format
                return $national;
            }

            // Otherwise
            // Default to international format
            return $phone->formatInternational();
        }

        // Handle any exceptions that occur during phone number parsing
        catch (Exception) {
            // Return the original value if parsing fails
            return $e164;
        }
    }

    /**
     * Generate and format an example phone number for a country.
     *
     * @param  string  $country  The country code
     * @param  string  $formatMethod  The format method to call ('formatNational' or 'formatInternational')
     * @param  string  $logMethodName  The method name for error logging
     * @return ?string The formatted example phone number or null if unavailable
     */
    private static function generateExamplePhoneNumber(
        string $country,
        string $formatMethod,
        string $logMethodName
    ): ?string {
        // Attempt to generate an example phone number for the country
        try {
            // Get the PhoneNumberUtil instance
            $util = PhoneNumberUtil::getInstance();

            // Generate an example number for the given country and type
            $example = $util->getExampleNumberForType(
                $country,
                PhoneNumberType::FIXED_LINE_OR_MOBILE
            );

            // Check if an example number was generated
            if ($example instanceof \libphonenumber\PhoneNumber) {
                // Convert the example object to E.164 string
                $e164 = $util->format(
                    $example,
                    PhoneNumberFormat::E164
                );

                // Create a PhoneNumber instance
                $phone = new PhoneNumber($e164);

                // Format the phone number using the specified method
                /** @var string $formatted */
                $formatted = $phone->$formatMethod();

                // Clean up variables to free up memory
                unset(
                    $phone,
                    $e164,
                    $example,
                    $util
                );

                // Return the formatted example
                return $formatted;
            }

            // Clean up variables to free up memory
            unset(
                $example,
                $util
            );
        }

        // Handle any exceptions that occur during example generation
        catch (Exception $exception) {
            // Log any exceptions that occur
            Log::error(
                sprintf('PhoneUtil::%s exception', $logMethodName),
                [
                    'exception' => $exception->getMessage(),
                ]
            );

            // Clean up variables to free up memory
            unset($exception);
        }

        // Clean up variables to free up memory
        unset(
            $country,
            $formatMethod,
            $logMethodName
        );

        // Return null if no example could be generated
        return null;
    }

    /**
     * Get an example national phone number for a country.
     *
     * @param  string  $country  The country code (default: 'US')
     * @return ?string The example national phone number or null if unavailable
     */
    public static function exampleNational(
        string $country = 'US'
    ): ?string {
        // Generate and format an example phone number in national format
        return self::generateExamplePhoneNumber(
            $country,
            'formatNational',
            'exampleNational'
        );
    }

    /**
     * Get an example international phone number for a country.
     *
     * @param  string  $country  The country code (default: 'US')
     * @return ?string The example international phone number or null if unavailable
     */
    public static function exampleInternational(
        string $country = 'US'
    ): ?string {
        // Generate and format an example phone number in international format
        return self::generateExamplePhoneNumber(
            $country,
            'formatInternational',
            'exampleInternational'
        );
    }
}
