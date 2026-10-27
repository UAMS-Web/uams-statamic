<?php

declare(strict_types=1);

namespace App\Support\People;

/**
 * Resolves the program accreditor block emitted on a residency/fellowship
 * `EducationalOccupationalCredential`'s `about` (`EducationalOccupationalProgram`)
 * `accreditedBy` property.
 *
 * Accreditation is derived from the taxonomy a training set's specialty term
 * belongs to (the prefix of its canonical `taxonomy::slug` id), so no authoring
 * field is required. The taxonomy-to-accreditor map and the accreditor
 * `Organization` blocks live in config (`schema_templates.training_accreditors`)
 * so they can be updated centrally without code changes.
 *
 * @see config/schema_templates.php
 * @see https://github.com/UAMS-Web/uams-statamic-archive/issues/637
 */
final class TrainingAccreditorRegistry
{
    /**
     * Config key prefix for the training accreditor map and organization blocks.
     */
    private const string CONFIG_PREFIX = 'schema_templates.training_accreditors';

    /**
     * Resolve the accreditor block for a specialty term canonical id.
     *
     * @param  string  $specialtyTermId  Canonical `taxonomy::slug` id (e.g. `specialties_acgme::orthopaedic-surgery`)
     * @return array<string, mixed>|null Accreditor block, or null when non-accredited or unresolvable
     */
    public static function forSpecialtyTermId(
        string $specialtyTermId
    ): ?array {
        // Derive the taxonomy handle from the canonical id prefix
        $taxonomyHandle = self::taxonomyHandleFromTermId($specialtyTermId);

        // Bail when no taxonomy handle could be parsed
        if ($taxonomyHandle === null) {
            return null;
        }

        // Resolve the accreditor key mapped to this specialty taxonomy
        $accreditorKey = config(self::CONFIG_PREFIX.'.taxonomy_map.'.$taxonomyHandle);

        // Bail when the taxonomy is non-accredited or unmapped
        if (
            ! \is_string($accreditorKey)
            || $accreditorKey === ''
        ) {
            return null;
        }

        // Resolve the accreditor organization block for the mapped key
        $organization = config(self::CONFIG_PREFIX.'.organizations.'.$accreditorKey);

        // Bail unless the block is a non-empty array
        if (! \is_array($organization) || $organization === []) {
            return null;
        }

        // Return the block keyed by its string config keys
        /** @var array<string, mixed> $organization */
        return $organization;
    }

    /**
     * Resolve the accreditor block for a specialty term as a JSON object string.
     *
     * @param  string  $specialtyTermId  Canonical `taxonomy::slug` id
     * @return string|null JSON object string, or null when non-accredited or unresolvable
     */
    public static function jsonForSpecialtyTermId(
        string $specialtyTermId
    ): ?string {
        // Resolve the accreditor block for the specialty term
        $block = self::forSpecialtyTermId($specialtyTermId);

        // Emit nothing when the program is non-accredited
        if ($block === null) {
            return null;
        }

        // Encode the block without escaping slashes so URLs stay readable
        $json = json_encode(
            $block,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        // Emit nothing when encoding fails
        if ($json === false) {
            return null;
        }

        return $json;
    }

    /**
     * Extract the taxonomy handle from a canonical `taxonomy::slug` term id.
     *
     * @param  string  $termId  Canonical term id or bare value
     * @return string|null Taxonomy handle, or null when the id carries no `::` prefix
     */
    private static function taxonomyHandleFromTermId(
        string $termId
    ): ?string {
        // Locate the canonical `::` separator between taxonomy handle and slug
        $separatorPosition = strpos(
            $termId,
            '::'
        );

        // Reject bare values without a taxonomy prefix
        if ($separatorPosition === false) {
            return null;
        }

        // Read the taxonomy handle that precedes the separator
        $taxonomyHandle = substr(
            $termId,
            0,
            $separatorPosition
        );

        // Treat an empty prefix as unresolvable
        return $taxonomyHandle === ''
            ? null
            : $taxonomyHandle;
    }
}
