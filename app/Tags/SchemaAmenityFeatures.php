<?php

declare(strict_types=1);

namespace App\Tags;

use App\Services\SchemaTemplates\SchemaObjectFragmentBuilder;
use Statamic\Fields\LabeledValue;
use Statamic\Fields\Value;
use Statamic\Tags\Parameters;
use Statamic\Tags\Tags;

/**
 * Antlers tag: emit a location's accessibility amenities as JSON-LD
 * `LocationFeatureSpecification` nodes for `amenityFeature`.
 *
 * Per the UAMS-Web/uams-statamic-archive#967 design, only the accessibility / wheelchair Google Business
 * Profile toggles are schema-worthy (purely-operational GBP attributes stay out
 * of JSON-LD). Each toggle is a `cast_booleans` radio with three options —
 * `true` ("Yes"), `false` ("No"), and `notapplicable` — so each answered toggle
 * becomes one node carrying a Boolean `value`; "Not Applicable" and unanswered
 * toggles emit nothing.
 *
 * Parameters (each the bound value of the matching `google_business_profile_*`
 * field; all optional):
 *   - hearing_loop, auracast, wheelchair_entrance, wheelchair_parking,
 *     wheelchair_restroom
 *
 * Output is empty unless at least one toggle is answered Yes/No.
 *
 * Example:
 *   {{ schema_amenity_features
 *       :hearing_loop="google_business_profile_has_hearing_loop"
 *       :auracast="google_business_profile_has_auracast_broadcast_audio"
 *       :wheelchair_entrance="google_business_profile_has_wheelchair_accessible_entrance"
 *       :wheelchair_parking="google_business_profile_has_wheelchair_accessible_parking"
 *       :wheelchair_restroom="google_business_profile_has_wheelchair_accessible_restroom" }}
 *
 * @property Parameters $params
 */
class SchemaAmenityFeatures extends Tags
{
    /**
     * Registered Antlers handle `{{ schema_amenity_features }}`.
     *
     * @var string
     */
    protected static $handle = 'schema_amenity_features';

    /**
     * Tag parameter name => `LocationFeatureSpecification` `name`, in emit order.
     *
     * @var array<string, string>
     */
    private const array FEATURE_NAMES = [
        'hearing_loop' => 'Assistive hearing loop',
        'auracast' => 'Auracast broadcast audio',
        'wheelchair_entrance' => 'Wheelchair accessible entrance',
        'wheelchair_parking' => 'Wheelchair accessible parking',
        'wheelchair_restroom' => 'Wheelchair accessible restroom',
    ];

    /**
     * Memoized `SchemaObjectFragmentBuilder::normalizedType()` for the feature type.
     */
    private static ?string $normalizedFeatureType = null;

    /**
     * Build the JSON-LD array of LocationFeatureSpecification nodes.
     *
     * @return string JSON array, or empty string when no toggle is answered
     */
    public function index(): string
    {
        // Resolve the LocationFeatureSpecification type URI (cached)
        $featureType = $this->cachedNormalizedFeatureType();

        // Emit nothing when the feature type is unusable
        if ($featureType === null) {
            // Return an empty string
            return '';
        }

        // Accumulate one node per answered toggle, in canonical order
        $nodes = [];

        // Walk each known accessibility toggle
        foreach (self::FEATURE_NAMES as $param => $name) {
            // Resolve the toggle to a strict boolean, or null when not answered
            $value = $this->resolveBoolean($this->params->get($param));

            // Skip toggles that are Not Applicable or unanswered
            if ($value === null) {
                // Move to the next toggle
                continue;
            }

            // Append the LocationFeatureSpecification node
            $nodes[] = [
                '@type' => $featureType,
                'name' => $name,
                'value' => $value,
            ];
        }

        // Emit nothing when no toggle resolved to Yes/No
        if ($nodes === []) {
            // Return an empty string to omit the property
            return '';
        }

        // Serialize the amenity-feature array for JSON-LD embedding
        $json = json_encode($nodes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // Return the JSON array, or an empty string when encoding fails
        return $json === false ? '' : $json;
    }

    /**
     * Resolve a `cast_booleans` radio value to a strict boolean, or null.
     *
     * Accepts a real boolean (the cast value) or the string keys `true` / `false`
     * defensively; `notapplicable`, null, and anything else yield null so the
     * toggle is omitted.
     *
     * @param  mixed  $raw  Bound toggle value
     */
    private function resolveBoolean(mixed $raw): ?bool
    {
        // Unwrap a Statamic field value
        if ($raw instanceof Value) {
            // Read the inner value for coercion
            $raw = $raw->value();
        }

        // Unwrap an augmented labeled option (the cast_booleans radio augments to
        // a LabeledValue wrapping the underlying boolean / `notapplicable` string)
        if ($raw instanceof LabeledValue) {
            // Read the option's underlying value
            $raw = $raw->value();
        }

        // A real boolean is used directly
        if (\is_bool($raw)) {
            // Return the boolean value
            return $raw;
        }

        // Map the radio string keys defensively when not cast to a boolean
        if (\is_string($raw)) {
            // Match the affirmative option key
            if ($raw === 'true') {
                // Treat the `true` key as Yes
                return true;
            }

            // Match the negative option key
            if ($raw === 'false') {
                // Treat the `false` key as No
                return false;
            }
        }

        // Not Applicable, unanswered, or any other value omits the toggle
        return null;
    }

    /**
     * Resolved HTTPS schema.org `@type` URI for `LocationFeatureSpecification`.
     *
     * @return string|null Same as `SchemaObjectFragmentBuilder::normalizedType()`
     */
    private function cachedNormalizedFeatureType(): ?string
    {
        // Resolve and memoize the normalized type on first use
        if (self::$normalizedFeatureType === null) {
            self::$normalizedFeatureType = SchemaObjectFragmentBuilder::normalizedType(
                'LocationFeatureSpecification'
            );
        }

        // Return the cached normalized type URI
        return self::$normalizedFeatureType;
    }
}
