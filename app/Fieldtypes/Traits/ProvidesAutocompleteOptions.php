<?php

declare(strict_types=1);

namespace App\Fieldtypes\Traits;

/**
 * Supplies the autocomplete option vocabulary for input-based fieldtypes.
 *
 *   - Provides the full set of HTML `autocomplete` tokens and the child-narrowed
 *     subset of allowed keys.
 *   - Child fieldtypes override `getAutocompleteOptions()` / `getAllowedAutocompleteKeys()`
 *     to constrain the tokens offered in the Control Panel.
 *
 * Extracted from the former monolithic {@see Input} trait (issue UAMS-Web/uams-statamic-archive#873).
 */
trait ProvidesAutocompleteOptions
{
    /**
     * The autocomplete options for this fieldtype.
     * Override this in child classes to provide a narrower list.
     *
     * @return array<string, string> Map of autocomplete token to display label
     */
    protected function getAutocompleteOptions(): array
    {
        // Define all available autocomplete options
        $allOptions = [
            // Personal Information
            'name' => 'Name (name)', // A person's full name
            'honorific-prefix' => 'Honorific Prefix (honorific-prefix)', // The prefix or title, such as "Dr.", "Mr.", "Miss", "Ms.", "Dr.", or "Mlle."
            'given-name' => 'Given Name (given-name)', // The given (or "first") name
            'additional-name' => 'Additional Name (additional-name)', // The middle name
            'family-name' => 'Family Name (family-name)', // The family (or "last") name
            'honorific-suffix' => 'Honorific Suffix (honorific-suffix)', // The suffix, such as "Jr.", "B.Sc.", "PhD.", "MBASW", or "IV".
            'nickname' => 'Nickname (nickname)', // A nickname or handle
            'bday' => 'Birthday (bday)', // A birth date, as a full date
            'bday-day' => 'Birthday Day (bday-day)', // The day of the month of a birth date
            'bday-month' => 'Birthday Month (bday-month)', // The month of the year of a birth date
            'bday-year' => 'Birthday Year (bday-year)', // The year of a birth date
            'sex' => 'Sex (sex)', // A gender identity (such as "Female", "Fa'afafine", "Hijra", "Male", "Nonbinary"), as freeform text without newlines
            'photo' => 'Photo (photo)', // The URL of an image representing the person, company, or contact information given in the other fields in the form

            // Contact Information
            'email' => 'Email (email)', // An email address
            'tel' => 'Telephone (tel)', // A full telephone number, including the country code
            'tel-country-code' => 'Telephone Country Code (tel-country-code)', // The country code (e.g., "1" for the United States)
            'tel-national' => 'Telephone National (tel-national)', // The entire phone number without the country code component, including a country-internal prefix. For the phone number "1-855-555-6502", this field's value would be "855-555-6502".
            'tel-area-code' => 'Telephone Area Code (tel-area-code)', // The area code, with any country-internal prefix applied if appropriate.
            'tel-local' => 'Telephone Local (tel-local)', // The phone number without the country or area code.
            'tel-local-prefix' => 'Telephone Local Prefix (tel-local-prefix)', // For the phone number "555-6502", use tel-local-prefix for "555".
            'tel-local-suffix' => 'Telephone Local Suffix (tel-local-suffix)', // For the phone number "555-6502", use tel-local-suffix for "6502".
            'tel-extension' => 'Telephone Extension (tel-extension)', // A telephone extension code within the phone number, such as a room or suite number in a hotel or an office extension in a company
            'impp' => 'IMPP (impp)', // A URL for an instant messaging protocol endpoint, such as xmpp:username@example.net

            // Address Information
            'street-address' => 'Street Address (street-address)', // A street address. This can be multiple lines of text, and should fully identify the location of the address within its second administrative level (typically a city or town), but should not include the city name, ZIP or postal code, or country name.
            'address-line1' => 'Address Line 1 (address-line1)', // Individual line of the street address. This should only be present if the street-address is not present
            'address-line2' => 'Address Line 2 (address-line2)', // Individual line of the street address. This should only be present if the street-address is not present
            'address-line3' => 'Address Line 3 (address-line3)', // Individual line of the street address. This should only be present if the street-address is not present
            'address-level1' => 'Address Level 1 (address-level1)', // The first administrative level in the address. This is typically the province in which the address is located. In the United States, this would be the state. In Switzerland, the canton. In the United Kingdom, the county.
            'address-level2' => 'Address Level 2 (address-level2)', // The second administrative level, in addresses with at least two of them. In countries with two administrative levels, this would typically be the city, town, village, or other locality in which the address is located.
            'address-level3' => 'Address Level 3 (address-level3)', // The third administrative level, in addresses with at least three administrative levels
            'address-level4' => 'Address Level 4 (address-level4)', // The finest-grained administrative level, in addresses which have four levels
            'postal-code' => 'Postal Code (postal-code)', // A postal code (in the United States, this is the ZIP code)
            'country' => 'Country (country)', // A country or territory code
            'country-name' => 'Country Name (country-name)', // A country or territory name

            // Organization Information
            'organization' => 'Organization (organization)', // A company or organization name, such as "Acme Widget Company" or "Girl Scouts of America"
            'organization-title' => 'Organization Title (organization-title)', // A job title, or the title a person has within an organization, such as "Senior Technical Writer", "President", or "Assistant Troop Leader"

            // Payment Information
            'cc-name' => 'Credit Card Name (cc-name)', // The full name as printed on or associated with a payment instrument such as a credit card
            'cc-given-name' => 'Credit Card Given Name (cc-given-name)', // A given (first) name as given on a payment instrument like a credit card
            'cc-additional-name' => 'Credit Card Additional Name (cc-additional-name)', // A family name, as given on a credit card
            'cc-family-name' => 'Credit Card Family Name (cc-family-name)', // A middle name as given on a payment instrument or credit card
            'cc-number' => 'Credit Card Number (cc-number)', // A credit card number or other number identifying a payment method, such as an account number
            'cc-exp' => 'Credit Card Expiration (cc-exp)', // A payment method expiration date, typically in the form "MM/YY" or "MM/YYYY"
            'cc-exp-month' => 'Credit Card Expiration Month (cc-exp-month)', // The month in which the payment method expires
            'cc-exp-year' => 'Credit Card Expiration Year (cc-exp-year)', // The year in which the payment method expires
            'cc-csc' => 'Credit Card Security Code (cc-csc)', // The security code for the payment instrument; on credit cards, this is the 3-digit verification number on the back of the card
            'cc-type' => 'Credit Card Type (cc-type)', // The type of payment instrument (such as "Visa" or "Master Card")

            // Security
            'current-password' => 'Current Password (current-password)', // The user's current password
            'new-password' => 'New Password (new-password)', // A new password
            'username' => 'Username (username)', // A username or account name
            'one-time-code' => 'One-Time Code (one-time-code)', // A one-time password (OTP) for verifying user identity that is used as an additional factor in a sign-in flow
            'webauthn' => 'WebAuthn (webauthn)', // Passkeys generated by the Web Authentication API, as requested by a conditional navigator.credentials.get() call (i.e., one that includes mediation: 'conditional')

            // General
            'url' => 'URL (url)', // A URL, such as a home page or company website address as appropriate given the context of the other fields in the form
            'language' => 'Language (language)', // A preferred language, given as a valid BCP 47 language tag

            // Transaction
            'transaction-amount' => 'Transaction Amount (transaction-amount)', // The amount, given in the currency specified by transaction-currency, of the transaction, for a payment form
            'transaction-currency' => 'Transaction Currency (transaction-currency)', // The currency in which the transaction is to take place
        ];

        // Define the 'null' option for autocomplete
        $autocomplete_null = ['null' => 'Not Specified'];

        // Get the allowed keys from child class, if any
        $allowedKeys = $this->getAllowedAutocompleteKeys();

        // / ---
        // If no specific keys are allowed, return all options with the 'null' option
        // ---

        // Check if no specific keys are allowed
        if (empty($allowedKeys)) {
            // Return all options with the null option prepended
            return \array_merge(
                $autocomplete_null,
                $allOptions
            );
        }

        // Filter to only include allowed keys
        $allowedKeys = array_intersect_key(
            $allOptions,
            array_flip($allowedKeys)
        );

        // Prepend 'null' option to allowed keys
        $allowedKeys = \array_merge(
            $autocomplete_null,
            $allowedKeys
        );

        // Return the allowed keys with the 'null' option
        return $allowedKeys;
    }

    /**
     * Get the allowed autocomplete keys for this fieldtype.
     *
     * Override this in child classes to restrict the available options.
     * Return an empty array to allow all options.
     *
     * @return array<string> The allowed autocomplete keys for this fieldtype
     */
    protected function getAllowedAutocompleteKeys(): array
    {
        return [];
    }
}
