<?php

declare(strict_types=1);

namespace App\Support\Statamic\Assets\Logos;

use App\Support\Statamic\Assets\LogosUnrestricted\AltFormatter;

/**
 * Blueprint-driven `title` and `alt` strings for the `logos` asset container.
 *
 * Branching follows `uams_logo_query` (UAMS logo vs external organization).
 * Orientation and color scheme display labels default to the live `logos`
 * asset blueprint (see LogosBlueprintOptionLabels).
 */
final class TitleAltResolver
{
    /**
     * Computed display title for CP and templates.
     *
     * @param  array<string, mixed>  $data  Blueprint field values keyed by handle
     * @param  array<string, array<string, string>>|null  $selectOptionLabelMaps  Per-field value => label; null loads from the `logos` blueprint
     * @return string|null Joined title segments for UAMS logos, or external org name
     */
    public static function title(
        array $data,
        ?array $selectOptionLabelMaps = null
    ): ?string {
        // Check whether this asset is configured as a UAMS logo
        if (self::isTruthy($data['uams_logo_query'] ?? null)) {
            // Resolve orientation/color labels from the blueprint when not injected
            $selectOptionLabelMaps ??= LogosBlueprintOptionLabels::maps();

            // Build the multi-part display title for the UAMS branch
            return self::titleForUamsLogo($data, $selectOptionLabelMaps);
        }

        // Use the external organization name as the title
        return self::stringOrNull($data['external_organization_name'] ?? null);
    }

    /**
     * Computed alt text for accessibility.
     *
     * @param  array<string, mixed>  $data  Blueprint field values keyed by handle
     * @return string|null UAMS alt string, `AltFormatter` output for external orgs, or null
     */
    public static function alt(
        array $data
    ): ?string {
        // Check whether this asset is configured as a UAMS logo
        if (self::isTruthy($data['uams_logo_query'] ?? null)) {
            // Build alt without orientation/color (blueprint rules omit them)
            return self::altForUamsLogo($data);
        }

        // Mirror unrestricted logos: prefix external org name with Logo of
        return AltFormatter::fromTitle($data['external_organization_name'] ?? null);
    }

    /**
     * Build the UAMS-logo display title from base text, conditional segments,
     * and blueprint option labels for orientation and color scheme.
     *
     * @param  array<string, mixed>  $data  Blueprint field values keyed by handle
     * @param  array<string, array<string, string>>  $selectOptionLabelMaps  `orientation` and `color_scheme` value => label maps
     * @return string|null Segments joined with em dashes, or null when every part is empty
     */
    private static function titleForUamsLogo(
        array $data,
        array $selectOptionLabelMaps
    ): ?string {
        // Read label maps for radio-stored values
        $orientationMap = $selectOptionLabelMaps['orientation'] ?? [];
        $colorMap = $selectOptionLabelMaps['color_scheme'] ?? [];

        // Collect non-empty fragments in blueprint order
        $parts = array_filter(
            [
                self::baseLogoPhrase($data['base_logo'] ?? null),
                self::applicationTitlePart($data),
                self::majorEntityTitlePart($data),
                self::subEntityTitlePart($data),
                self::mapLabel($orientationMap, $data['orientation'] ?? null),
                self::mapLabel($colorMap, $data['color_scheme'] ?? null),
            ],
            static fn (?string $p): bool => $p !== null && $p !== ''
        );

        // ---
        // Avoid returning an empty string when no segment resolved
        // ---

        // Check if no parts are present
        if ($parts === []) {
            // Return null to signal no title should be displayed
            return null;
        }

        // Join remaining parts with space-em dash-space
        return implode(' — ', $parts);
    }

    /**
     * Build UAMS-logo alt: fixed `Logo of ` prefix plus base and conditional
     * application or entity fragments (with punctuation per product rules).
     *
     * @param  array<string, mixed>  $data  Blueprint field values keyed by handle
     * @return string Alt text (may be only `Logo of ` when no tail segments)
     */
    private static function altForUamsLogo(
        array $data
    ): string {
        // Resolve master vs clinical display phrase
        $base = self::baseLogoPhrase($data['base_logo'] ?? null);

        // Detect special-application vs major/sub-entity flow
        $applicationQuery = self::isTruthy($data['application_query'] ?? null);

        // Start the tail after Logo of with the base phrase or empty string
        $suffix = $base;

        // Check if the special application branch is enabled
        if ($applicationQuery) {
            // Append comma-prefixed application text when present
            $app = self::trimmedString($data['application'] ?? null);

            // Check if the application text is present
            if ($app !== null) {
                // Append the application text to the suffix
                $suffix .= ', '.$app;
            }
        } else {
            // ---
            // Append spaced major entity when that branch is enabled
            // ---

            // Check if the major entity branch is enabled
            if (self::isTruthy($data['major_entity_query'] ?? null)) {
                // Get the major entity text
                $major = self::trimmedString($data['major_entity'] ?? null);

                // Check if the major entity text is present
                if ($major !== null) {
                    // Append the major entity text to the suffix
                    $suffix .= ' '.$major;
                }
            }

            // ---
            // Append spaced sub-entity when that branch is enabled
            // ---

            // Check if the sub-entity branch is enabled
            if (self::isTruthy($data['sub_entity_query'] ?? null)) {
                // Get the sub-entity text
                $sub = self::trimmedString($data['sub_entity'] ?? null);

                // Check if the sub-entity text is present
                if ($sub !== null) {
                    // Append the sub-entity text to the suffix
                    $suffix .= ' '.$sub;
                }
            }
        }

        // Always use the Logo of prefix for the UAMS alt pattern
        return 'Logo of '.$suffix;
    }

    /**
     * Title segment for the special-application branch (`application_query` true).
     *
     * @param  array<string, mixed>  $data  Blueprint field values keyed by handle
     * @return string|null Trimmed application text, or null when branch off or empty
     */
    private static function applicationTitlePart(
        array $data
    ): ?string {
        // ---
        // Skip when not using the special application questionnaire branch
        // ---

        // Check if the special application branch is enabled
        if (! self::isTruthy($data['application_query'] ?? null)) {
            // Return null to signal no title should be displayed
            return null;
        }

        // Return trimmed free-text application when set
        return self::trimmedString($data['application'] ?? null);
    }

    /**
     * Title segment for major entity when not in the special-application branch.
     *
     * @param  array<string, mixed>  $data  Blueprint field values keyed by handle
     * @return string|null Trimmed major entity text, or null when excluded or empty
     */
    private static function majorEntityTitlePart(
        array $data
    ): ?string {
        // ---
        // Major entity applies only when special application is not selected
        // ---

        // Check if the special application branch is enabled
        if (self::isTruthy($data['application_query'] ?? null)) {
            // Return null to signal no title should be displayed
            return null;
        }

        // ---
        // Require the major-entity toggle before reading text
        // ---

        // Check if the major entity branch is enabled
        if (! self::isTruthy($data['major_entity_query'] ?? null)) {
            // Return null to signal no title should be displayed
            return null;
        }

        // Return trimmed major entity label
        return self::trimmedString($data['major_entity'] ?? null);
    }

    /**
     * Title segment for sub-entity when not in the special-application branch.
     *
     * @param  array<string, mixed>  $data  Blueprint field values keyed by handle
     * @return string|null Trimmed sub-entity text, or null when excluded or empty
     */
    private static function subEntityTitlePart(
        array $data
    ): ?string {
        // ---
        // Sub-entity applies only when special application is not selected
        // ---

        // Check if the special application branch is enabled
        if (self::isTruthy($data['application_query'] ?? null)) {
            // Return null to signal no title should be displayed
            return null;
        }

        // ---
        // Require the sub-entity toggle before reading text
        // ---

        // Check if the sub-entity branch is enabled
        if (! self::isTruthy($data['sub_entity_query'] ?? null)) {
            // Return null to signal no title should be displayed
            return null;
        }

        // Return trimmed sub-entity label
        return self::trimmedString($data['sub_entity'] ?? null);
    }

    /**
     * Map `base_logo` radio value to the short phrase used in title and alt.
     *
     * @param  mixed  $baseLogo  Stored `base_logo` key (e.g. master)
     * @return string|null Display phrase, or null when unknown or unset
     */
    private static function baseLogoPhrase(
        mixed $baseLogo
    ): ?string {
        // ---
        // Master brand string
        // ---

        // Check if the base logo is the master brand
        if ($baseLogo === 'master') {
            // Return the UAMS brand string
            return 'UAMS';
        }

        // ---
        // Clinical master brand string
        // ---

        // Check if the base logo is the clinical master brand
        if ($baseLogo === 'master-clinical') {
            // Return the UAMS Health brand string
            return 'UAMS Health';
        }

        // No phrase for other or missing values
        return null;
    }

    /**
     * Resolve a stored option key to its blueprint label using a value => label map.
     *
     * @param  array<string, string>  $map  Stored value => display label
     * @param  mixed  $key  Stored field value (orientation or color_scheme key)
     * @return string|null Label when the key exists in the map, otherwise null
     */
    private static function mapLabel(
        array $map,
        mixed $key
    ): ?string {
        // ---
        // Ignore non-string or blank keys
        // ---

        // Check if the key is not a string or is blank
        if (
            ! \is_string($key)
            || $key === ''
        ) {
            // Return null to signal no label should be displayed
            return null;
        }

        // Look up label, missing keys yield null
        return $map[$key] ?? null;
    }

    /**
     * Return a trimmed non-empty string, or null for non-strings or whitespace-only.
     *
     * @param  mixed  $value  Raw blueprint scalar
     * @return string|null Trimmed string, or null when unusable
     */
    private static function trimmedString(
        mixed $value
    ): ?string {
        // ---
        // Reject non-string inputs
        // ---

        // Check if the value is not a string
        if (! \is_string($value)) {
            // Return null to signal no string should be displayed
            return null;
        }

        // Trim surrounding whitespace from the string
        $t = trim($value);

        // Treat all-whitespace as absent
        return $t === '' ? null : $t;
    }

    /**
     * @param  mixed  $value  Raw blueprint scalar
     * @return string|null Same semantics as `trimmedString()`
     */
    private static function stringOrNull(
        mixed $value
    ): ?string {
        // Delegate to shared trimming rules
        return self::trimmedString($value);
    }

    /**
     * Whether a blueprint toggle value counts as true (bool, int, or string forms).
     *
     * @param  mixed  $value  Raw `true_false` or similar field value
     * @return bool True for explicit affirmative values only
     */
    private static function isTruthy(
        mixed $value
    ): bool {
        // ---
        // Accept boolean true and integer 1
        // ---

        // Check if the value is true or 1
        if (
            $value === true
            || $value === 1
        ) {
            // Return true
            return true;
        }

        // ---
        // Accept common string encodings from forms or imports
        // ---
        // Check if the value is 1, true, or yes
        // Everything else is non-truthy for branching
        // Return true
        return in_array($value, ['1', 'true', 'yes'], true);
    }
}
