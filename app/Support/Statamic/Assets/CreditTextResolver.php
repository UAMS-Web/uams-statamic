<?php

declare(strict_types=1);

namespace App\Support\Statamic\Assets;

/**
 * Builds display credit text from asset `creator` and `creator_organization`.
 */
final class CreditTextResolver
{
    /**
     * Join creator and organization with ` / ` when both are non-empty after trim;
     * otherwise return the single non-empty value, or null when neither applies.
     *
     * @param  mixed  $creator  Blueprint `creator` value; only strings contribute
     * @param  mixed  $creatorOrganization  Blueprint `creator_organization` value
     * @return string|null Trimmed combined or single value, or null
     */
    public static function resolve(
        mixed $creator,
        mixed $creatorOrganization
    ): ?string {
        // Normalize creator to a present string or null
        $creatorPart = self::normalizePresentString($creator);

        // Normalize organization to a present string or null
        $organizationPart = self::normalizePresentString($creatorOrganization);

        // Check if both parts are absent. Removing this return still yields null
        // via the creator-null branch that returns $organizationPart.
        // @pest-mutate-ignore: RemoveEarlyReturn
        if (
            $creatorPart === null
            && $organizationPart === null
        ) {
            // Return null to signal absence of credit text
            return null;
        }

        // Check if only the organization part is present
        if ($creatorPart === null) {
            // Return the organization part to signal only organization credit
            return $organizationPart;
        }

        // Check if only the creator part is present
        if ($organizationPart === null) {
            // Return the creator part to signal only creator credit
            return $creatorPart;
        }

        // Join both non-empty parts with the required separator
        return sprintf('%s / %s', $creatorPart, $organizationPart);
    }

    /**
     * Treat only non-empty trimmed strings as present; other types are ignored.
     *
     * @param  mixed  $value  Raw field value from asset data
     * @return string|null Non-empty trimmed string, or null
     */
    private static function normalizePresentString(
        mixed $value
    ): ?string {
        // Check if the value is not a string
        if (! \is_string($value)) {
            // Return null to signal absence of credit text
            return null;
        }

        // Trim whitespace and treat empty as absent
        $trimmed = trim($value);

        // Check if the trimmed string is empty
        if ($trimmed === '') {
            // Return null to signal absence of credit text
            return null;
        }

        // Return the trimmed string
        return $trimmed;
    }
}
