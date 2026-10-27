<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SchemaEnumerationMember;
use Statamic\Entries\Entry as EntryModel;
use Statamic\Fields\Value;

/**
 * Resolves the IPTC Digital Source Type string from a group field's stored
 * answers (preset select and/or imported question fields).
 */
class IptcDigitalSourceValueResolver
{
    /**
     * Group handles that store IPTC Digital Source question payloads.
     *
     * @return list<string> Supported group handles
     */
    public static function digitalSourceTypeGroupHandles(): array
    {
        // Return the supported group handles in stable display order
        return [
            'digital_source_type',
            'digital_source_type_text',
            'digital_source_type_portrait',
            'digital_source_type_image',
            'digital_source_type_audio',
            'digital_source_type_video',
        ];
    }

    /**
     * Compute value and schema computed keys for a group handle.
     *
     * @param  string  $groupHandle  Group handle used in entry or asset data
     * @return array{value: string, schema: string} Computed key names for resolved
     *                                              value and schema URL
     */
    public static function computedKeysForGroupHandle(
        string $groupHandle
    ): array {
        // Check if the handle is the primary digital_source_type group
        if ($groupHandle === 'digital_source_type') {
            // Return short computed keys for the primary group
            return [
                'value' => 'digital_source_type_value',
                'schema' => 'digital_source_type_value_schema',
            ];
        }

        // Return namespaced computed keys for non-primary group handles
        return [
            'value' => $groupHandle.'_digital_source_type_value',
            'schema' => $groupHandle.'_digital_source_type_value_schema',
        ];
    }

    /**
     * Resolve the IPTC digital source value from mixed group payload input.
     *
     * @param  mixed  $groupPayload  Stored group payload from entry or asset data
     * @return string Resolved type key, or empty string when unresolved
     */
    public static function resolveFromGroupPayload(
        mixed $groupPayload
    ): string {
        // Normalize the mixed payload into a group-data array
        $data = self::normalizeGroupPayload($groupPayload);

        // Resolve the type key from normalized group data
        $resolved = self::resolve($data);

        // Return an empty string when no key can be resolved
        return $resolved ?? '';
    }

    /**
     * Resolve the schema.org DigitalSource URL from mixed group payload input.
     *
     * @param  mixed  $groupPayload  Stored group payload from entry or asset data
     * @return string Full schema URL, or empty string when unresolved
     */
    public static function schemaUrlFromGroupPayload(
        mixed $groupPayload
    ): string {
        // Resolve the type key from the incoming group payload
        $resolved = self::resolveFromGroupPayload($groupPayload);

        // Map the resolved key to a schema.org DigitalSource URL
        return self::schemaOrgDigitalSourceTypeUrl($resolved);
    }

    /**
     * Resolve the IPTC digital source type key from an entry's group field.
     *
     * @param  EntryModel  $entry  Entry exposing `get()` for blueprint data
     * @param  string  $groupHandle  Group field handle (e.g. `digital_source_type`)
     * @return string Resolved type key, or empty string when unresolved
     */
    public static function resolveFromEntryGroupField(
        EntryModel $entry,
        string $groupHandle
    ): string {
        // Read raw group data from the entry
        $groupPayload = $entry->get($groupHandle);

        // Delegate to mixed-payload resolution
        return self::resolveFromGroupPayload($groupPayload);
    }

    /**
     * Resolve the schema.org DigitalSource URL from an entry's group field.
     *
     * @param  EntryModel  $entry  Entry exposing `get()` for blueprint data
     * @param  string  $groupHandle  Group field handle (e.g. `digital_source_type`)
     * @return string Full schema URL, or empty string when unresolved
     */
    public static function schemaUrlFromEntryGroupField(
        EntryModel $entry,
        string $groupHandle
    ): string {
        // Read raw group data from the entry
        $groupPayload = $entry->get($groupHandle);

        // Delegate to mixed-payload schema URL resolution
        return self::schemaUrlFromGroupPayload($groupPayload);
    }

    /**
     * Normalize mixed group payload to a strict array shape for resolution.
     *
     * @param  mixed  $groupPayload  Stored group payload from entry or asset data
     * @return array<string, mixed> Group data array, or empty array when invalid
     */
    public static function normalizeGroupPayload(
        mixed $groupPayload
    ): array {
        // Unwrap Statamic Value wrappers from augmented or lazy field access
        if ($groupPayload instanceof Value) {
            // Resolve the plain PHP value from the Value object
            $groupPayload = $groupPayload->value();
        }

        // Check whether the payload is already an array
        if (\is_array($groupPayload)) {
            /** @var array<string, mixed> $groupPayload */
            // Return the payload unchanged when it is already an array
            return $groupPayload;
        }

        // Return an empty group payload for missing or invalid values
        return [];
    }

    /**
     * Compute the digital source type value from group field data.
     *
     * @param  array<string, mixed>  $data  Group field values (preset and imported
     *                                      question answers)
     * @return string|null IPTC digital source type key, or null when it cannot be
     *                     determined from the given data
     */
    public static function resolve(
        array $data
    ): ?string {
        // ---
        // Prefer quick preset: first segment before the first underscore
        // ---

        // Read the optional quick preset key from group data
        $preset = $data['preset'] ?? null;

        // Check whether a non-empty preset string was stored
        if (self::isNonEmptyString($preset)) {
            // Trim whitespace from the preset for reliable splitting
            $trimmed = trim($preset);

            // Split on the first underscore only to take the type prefix
            $segment = explode(
                '_',
                $trimmed,
                2
            );

            // Return the prefix segment or null when empty
            return $segment[0] !== '' ? $segment[0] : null;
        }

        // ---
        // Composite: only composite_type matters for the result
        // ---

        // Check whether the entry is marked as a composite of sources
        if (self::isTrue($data['is_composite'] ?? null)) {
            // Read composite_type when the composite flag is set
            $type = $data['composite_type'] ?? null;

            // Return the composite classification key or null when missing
            return self::isNonEmptyString($type) ? $type : null;
        }

        // Read origin_type for non-composite flows
        $origin = $data['origin_type'] ?? null;

        // Check whether primary origin is set to a non-empty string
        if (! self::isNonEmptyString($origin)) {
            // Cannot branch to origin-specific rules without an origin
            return null;
        }

        // Dispatch to origin-specific resolution (capture, digitized, algorithmic, creation)
        return match ($origin) {
            'capture' => self::resolveCapture($data),
            'digitized' => self::resolveDigitized($data),
            'algorithmic' => self::resolveAlgorithmic($data),
            'creation' => self::resolveCreation($data),
            default => null,
        };
    }

    /**
     * Build a schema.org DigitalSource subtype URL from a resolved type key.
     *
     * Uses `ucfirst` on the key, then `https://schema.org/{Name}DigitalSource`.
     *
     * @param  string|null  $value  Resolved camelCase type key, or null when none
     * @return string Full schema.org URL, or empty string when input is null or blank
     */
    public static function schemaOrgDigitalSourceTypeUrl(
        ?string $value
    ): string {
        // ---
        // Short-circuit when there is no type to map to a URL
        // ---

        // Check whether the resolved type key is null or empty
        if (
            $value === null
            || $value === ''
        ) {
            // Omit a URL when the resolved type is absent
            return '';
        }

        // Build the canonical schema enumeration member from the resolved value key
        $memberName = ucfirst($value).'DigitalSource';

        // Check whether the canonical member exists and belongs to IPTC Digital Source
        if (self::isValidIptcDigitalSourceMember($memberName)) {
            // Return the canonical URL when the member is valid
            return 'https://schema.org/'.$memberName;
        }

        // Check whether digitalCreation needs an Schema.org-compatible fallback
        // @see https://github.com/schemaorg/schemaorg/issues/3392
        if ($memberName === 'DigitalCreationDigitalSource') {
            // Prefer DigitalArtDigitalSource when DigitalCreationDigitalSource is unavailable
            $fallbackMemberName = 'DigitalArtDigitalSource';

            // Return the fallback URL when the fallback member is valid
            return self::isValidIptcDigitalSourceMember($fallbackMemberName)
                ? 'https://schema.org/'.$fallbackMemberName
                : '';
        }

        // Check whether humanEdits needs an Schema.org-compatible fallback
        // @see https://github.com/schemaorg/schemaorg/issues/3392
        if ($memberName === 'HumanEditsDigitalSource') {
            // Prefer MinorHumanEditsDigitalSource when HumanEditsDigitalSource is unavailable
            $fallbackMemberName = 'MinorHumanEditsDigitalSource';

            // Return the fallback URL when the fallback member is valid
            return self::isValidIptcDigitalSourceMember($fallbackMemberName)
                ? 'https://schema.org/'.$fallbackMemberName
                : '';
        }

        // Return an empty string when the resolved value cannot map to a valid member
        return '';
    }

    /**
     * Real-life capture origin: enhancement and gen-AI flags; computational
     * and screen-capture flags are not used for this branch.
     *
     * @param  array<string, mixed>  $data  Group field values for capture branch
     * @return string|null Resolved type key for capture origin, or null when unknown
     */
    private static function resolveCapture(
        array $data
    ): ?string {
        // ---
        // Generative AI edits on capture map to compositeWithTrainedAlgorithmicMedia
        // ---

        // Check whether the entry has generative AI edits
        if (self::isTrue($data['has_gen_ai_edits'] ?? null)) {
            // Return the compositeWithTrainedAlgorithmicMedia type key
            return 'compositeWithTrainedAlgorithmicMedia';
        }

        // Map non-generative enhancement when gen-AI edits are not used
        return self::mapEnhancementOnly($data['enhancement_type'] ?? null);
    }

    /**
     * Digitized origin: gen-AI flag, then digitized medium plus enhancement.
     *
     * @param  array<string, mixed>  $data  Group field values for digitized branch
     * @return string|null Resolved type key for digitized origin, or null when unknown
     */
    private static function resolveDigitized(
        array $data
    ): ?string {
        // ---
        // Generative AI edits on digitized content use the same composite key as capture
        // ---

        // Check whether the entry has generative AI edits
        if (self::isTrue($data['has_gen_ai_edits'] ?? null)) {
            // Return the compositeWithTrainedAlgorithmicMedia type key
            return 'compositeWithTrainedAlgorithmicMedia';
        }

        // Read digitized_medium for non-gen-AI digitized flows
        $medium = $data['digitized_medium'] ?? null;

        // ---
        // Require a known digitized medium before applying the enhancement grid
        // ---

        // Check whether the digitized medium is set to a non-empty string
        if (! self::isNonEmptyString($medium)) {
            // Cannot branch to digitized-specific rules without a medium
            return null;
        }

        // Combine medium and enhancement per the digitized determination rules
        return self::mapDigitizedMediumWithEnhancement(
            $medium,
            $data['enhancement_type'] ?? null
        );
    }

    /**
     * Algorithmic origin: only gen-AI and enhancement matter for this branch.
     *
     * @param  array<string, mixed>  $data  Group field values for algorithmic branch
     * @return string|null Resolved type key for algorithmic origin, or null when unknown
     */
    private static function resolveAlgorithmic(
        array $data
    ): ?string {
        // ---
        // Generative AI edits on algorithmic content use the composite trained key
        // ---

        // Check whether the entry has generative AI edits
        if (self::isTrue($data['has_gen_ai_edits'] ?? null)) {
            // Return the compositeWithTrainedAlgorithmicMedia type key
            return 'compositeWithTrainedAlgorithmicMedia';
        }

        // Map enhancement when the origin is algorithmic without gen-AI edits
        return self::mapEnhancementToAlgorithmicNonGenAi(
            $data['enhancement_type'] ?? null
        );
    }

    /**
     * Digital creation origin: gen-AI flag, then enhancement and data-driven
     * flag for the “none” enhancement case.
     *
     * @param  array<string, mixed>  $data  Group field values for creation branch
     * @return string|null Resolved type key for creation origin, or null when unknown
     */
    private static function resolveCreation(
        array $data
    ): ?string {
        // ---
        // Generative AI edits on digital creation use the composite trained key
        // ---

        // Check whether the entry has generative AI edits
        if (self::isTrue($data['has_gen_ai_edits'] ?? null)) {
            // Return the compositeWithTrainedAlgorithmicMedia type key
            return 'compositeWithTrainedAlgorithmicMedia';
        }

        // Read enhancement when creation origin has no gen-AI edits
        $enhancement = $data['enhancement_type'] ?? null;

        // ---
        // Branch on none vs data-driven vs standard digital creation
        // ---

        // Check whether the enhancement type is none
        if (self::stringEquals($enhancement, 'none')) {
            // Return dataDrivenMedia when the entry is data-driven, otherwise digitalCreation
            return self::isTrue($data['is_data_driven'] ?? null)
                ? 'dataDrivenMedia'
                : 'digitalCreation';
        }

        // ---
        // Human non-generative edits keep the humanEdits type key
        // ---

        // Check whether the enhancement type is humanEdits
        if (self::stringEquals($enhancement, 'humanEdits')) {
            // Return the humanEdits type key
            return 'humanEdits';
        }

        // ---
        // ---

        // Check whether the enhancement type is algorithmicallyEnhanced
        if (self::stringEquals($enhancement, 'algorithmicallyEnhanced')) {
            // Return the algorithmicallyEnhanced type key
            return 'algorithmicallyEnhanced';
        }

        // Enhancement type did not match a known option
        return null;
    }

    /**
     * Map enhancement for capture when generative AI was not used.
     *
     * @param  mixed  $enhancement  enhancement_type field value
     * @return string|null Type key for capture origin, or null when unmatched
     */
    private static function mapEnhancementOnly(
        $enhancement
    ): ?string {
        // ---
        // No post-processing maps to a plain digital capture
        // ---

        // Check whether the enhancement type is none
        if (self::stringEquals($enhancement, 'none')) {
            // Return the digitalCapture type key
            return 'digitalCapture';
        }

        // ---
        // Human-edited (non-generative) post-processing
        // ---

        // Check whether the enhancement type is humanEdits
        if (self::stringEquals($enhancement, 'humanEdits')) {
            // Return the humanEdits type key
            return 'humanEdits';
        }

        // ---
        // Algorithmic adjustments without generative AI
        // ---

        // Check whether the enhancement type is algorithmicallyEnhanced
        if (self::stringEquals($enhancement, 'algorithmicallyEnhanced')) {
            // Return the algorithmicallyEnhanced type key
            return 'algorithmicallyEnhanced';
        }

        // enhancement_type did not match a known option
        return null;
    }

    /**
     * Map enhancement when origin is algorithmic and gen-AI was not used.
     *
     * @param  mixed  $enhancement  enhancement_type field value
     * @return string|null Type key for algorithmic non-gen-AI origin, or null
     *                     when unmatched
     */
    private static function mapEnhancementToAlgorithmicNonGenAi(
        $enhancement
    ): ?string {
        // ---
        // Unmodified algorithmic output counts as trained algorithmic media
        // ---

        // Check whether the enhancement type is none
        if (self::stringEquals($enhancement, 'none')) {
            // Return the trainedAlgorithmicMedia type key
            return 'trainedAlgorithmicMedia';
        }

        // ---
        // Human-edited algorithmic output preserves the humanEdits classification
        // ---

        // Check whether the enhancement type is humanEdits
        if (self::stringEquals($enhancement, 'humanEdits')) {
            // Return the humanEdits type key
            return 'humanEdits';
        }

        // ---
        // Non-generative algorithmic enhancements stay algorithmicallyEnhanced
        // ---

        // Check whether the enhancement type is algorithmicallyEnhanced
        if (self::stringEquals($enhancement, 'algorithmicallyEnhanced')) {
            // Return the algorithmicallyEnhanced type key
            return 'algorithmicallyEnhanced';
        }

        // enhancement_type did not match a known option
        return null;
    }

    /**
     * Combine digitized medium with enhancement for the digitized branch.
     *
     * @param  string  $medium  digitized_medium field value
     * @param  mixed  $enhancement  enhancement_type field value
     * @return string|null Combined IPTC type key, or null when inputs are unknown
     */
    private static function mapDigitizedMediumWithEnhancement(
        string $medium,
        $enhancement
    ): ?string {
        // Table: digitized medium × enhancement → final type key. Keyed by the
        // `digitized_medium` option values (digital_source_type_questions.yaml:
        // negativeFilm / positiveFilm / print) — NOT the `ai_type` values, which
        // belong to the algorithmic branch. With no enhancement, a digitized
        // medium resolves to its own IPTC term (e.g. print → PrintDigitalSource).
        $map = [
            'negativeFilm' => [
                'none' => 'negativeFilm',
                'humanEdits' => 'humanEdits',
                'algorithmicallyEnhanced' => 'algorithmicallyEnhanced',
            ],
            'positiveFilm' => [
                'none' => 'positiveFilm',
                'humanEdits' => 'humanEdits',
                'algorithmicallyEnhanced' => 'algorithmicallyEnhanced',
            ],
            'print' => [
                'none' => 'print',
                'humanEdits' => 'humanEdits',
                'algorithmicallyEnhanced' => 'algorithmicallyEnhanced',
            ],
        ];

        // Check whether the medium is set to a known key
        if (! isset($map[$medium])) {
            // Unknown digitized medium has no mapping
            return null;
        }

        // Check whether the enhancement type is none
        if (self::stringEquals($enhancement, 'none')) {
            // Return the type key for the selected medium when enhancement is none
            return $map[$medium]['none'];
        }

        // Check whether the enhancement type is humanEdits
        if (self::stringEquals($enhancement, 'humanEdits')) {
            // Return the type key for the selected medium when enhancement is humanEdits
            return $map[$medium]['humanEdits'];
        }

        // Check whether the enhancement type is algorithmicallyEnhanced
        if (self::stringEquals($enhancement, 'algorithmicallyEnhanced')) {
            // Return the type key for the selected medium when enhancement is algorithmicallyEnhanced
            return $map[$medium]['algorithmicallyEnhanced'];
        }

        // enhancement_type did not match a known option
        return null;
    }

    /**
     * Whether a value is a non-empty string.
     *
     * @param  mixed  $value  Candidate scalar or value from stored entry data
     * @return bool True when the value is a string with non-whitespace content
     *
     * @phpstan-assert-if-true string $value
     */
    private static function isNonEmptyString(
        $value
    ): bool {
        // Require a real string that is not blank after trimming
        return \is_string($value) && trim($value) !== '';
    }

    /**
     * Coerce Statamic booleans and common truthy forms.
     *
     * @param  mixed  $value  Toggle field value from group data
     * @return bool True when the value represents an affirmative toggle
     */
    private static function isTrue(
        $value
    ): bool {
        // ---
        // Treat boolean true and numeric/string one as true
        // ---

        // Check whether the value is true, one, or the string "1"
        if (
            in_array($value, [true, 1, '1'], true)
        ) {
            // Return true when the value is true, one, or the string "1"
            return true;
        }

        // ---
        // Accept the word true in any casing when stored as a string
        // ---

        // Check whether the value is a string
        if (\is_string($value)) {
            // Return true when the value is a string and the lowercase version of the trimmed value is "true"
            return strtolower(trim($value)) === 'true';
        }

        // Any other shape is not considered true
        return false;
    }

    /**
     * Case-sensitive string equality for known option keys.
     *
     * @param  mixed  $value  Field value that may be a select option string
     * @param  string  $expected  Exact option key to compare
     * @return bool True when value is a string identical to expected
     */
    private static function stringEquals(
        $value,
        string $expected
    ): bool {
        // Disallow loose coercion so option keys match strictly
        return \is_string($value) && $value === $expected;
    }

    /**
     * Check whether a schema enumeration member exists and belongs to the IPTC
     * digital source enumeration.
     *
     * @param  string  $memberName  SchemaEnumerationMember case value to validate
     * @return bool True when member exists and has IPTCDigitalSourceEnumeration type
     */
    private static function isValidIptcDigitalSourceMember(
        string $memberName
    ): bool {
        // Resolve the enum case by its string value
        $member = SchemaEnumerationMember::tryFrom($memberName);

        // Check whether the enum case exists
        if ($member === null) {
            // Return false when the enum case does not exist
            return false;
        }

        // Return true when the member advertises IPTCDigitalSourceEnumeration type
        return \in_array(
            'IPTCDigitalSourceEnumeration',
            $member->type(),
            true
        );
    }
}
