<?php

declare(strict_types=1);

namespace App\Tags;

use App\Services\SchemaTemplates\SchemaObjectFragmentBuilder;
use Statamic\Facades\GlobalSet;
use Statamic\Fields\LabeledValue;
use Statamic\Fields\Value;
use Statamic\Fields\Values;
use Statamic\Globals\Variables as GlobalVariables;
use Statamic\Tags\Parameters;
use Statamic\Tags\Tags;

/**
 * Antlers tag: emit a location's labeled phone numbers as JSON-LD
 * `ContactPoint` nodes for the `contactPoint` property.
 *
 * The general `phone`/`fax` already map to `telephone`/`faxNumber`; this tag
 * covers the appointment numbers and the free-form additional-phone repeater:
 *
 *   - Arkansas Children's appointment numbers (`ac_appointments_primary` /
 *     `ac_appointments_specialty`), gated by `ac_enabled` (the
 *     `ac_appointments_query` toggle). Each falls back to the site-wide
 *     Find-a-Doc default (`uams_fad_settings.location_ac_appointments_*`) when
 *     the location leaves its own override empty. The gate keeps the global
 *     fallback from leaking onto non-Arkansas-Children's locations.
 *   - The new/returning appointment numbers (`phone_appointment_new` /
 *     `phone_appointment_return`). When a separate returning number exists the
 *     first is named "New Patient Appointments"; alone it is just
 *     "Appointments".
 *   - The `phone_additional` repeater (each row: `text` label, `phone`,
 *     optional `description` note) → one ContactPoint per row, the label as
 *     `name` and the note as `description`.
 *
 * Appointment ContactPoints carry `contactType: "appointments"`; the additional
 * phones carry only the editor's free-form label, so no contactType is asserted.
 *
 * Output is empty when no phone resolves. A single ContactPoint is still emitted
 * as a one-element array; the engine flattens it to the bare object.
 *
 * Example:
 *   {{ schema_location_contact_points
 *      :phone_appointment_new="phone_appointment_new"
 *      :phone_appointment_return="phone_appointment_return"
 *      :ac_enabled="ac_appointments_query"
 *      :ac_primary="ac_appointments_primary"
 *      :ac_primary_fallback="uams_fad_settings:location_ac_appointments_primary"
 *      :ac_specialty="ac_appointments_specialty"
 *      :ac_specialty_fallback="uams_fad_settings:location_ac_appointments_specialty"
 *      :phone_additional="phone_additional" }}
 *
 * @property Parameters $params
 */
class SchemaLocationContactPoints extends Tags
{
    /**
     * Registered Antlers handle `{{ schema_location_contact_points }}`.
     *
     * @var string
     */
    protected static $handle = 'schema_location_contact_points';

    /**
     * Global set holding the site-wide Arkansas Children's appointment defaults.
     */
    private const string FAD_SETTINGS_GLOBAL = 'uams_fad_settings';

    /**
     * Memoized `SchemaObjectFragmentBuilder::normalizedType()` for `ContactPoint`.
     */
    private static ?string $normalizedContactPointType = null;

    /**
     * Build the JSON array of ContactPoint nodes.
     *
     * @return string JSON array, or empty string when no phone resolves
     */
    public function index(): string
    {
        // Resolve the ContactPoint type URI (cached)
        $contactPointType = $this->cachedNormalizedContactPointType();

        // Emit nothing when the type is unusable
        if ($contactPointType === null) {
            // Return an empty string
            return '';
        }

        // Accumulate one ContactPoint per resolved phone, in a stable order
        $points = [];

        // Arkansas Children's appointment numbers (gated; override falls back to the global default)
        if ($this->acGateOpen()) {
            // Primary-care appointments: location override, else the Find-a-Doc default
            $acPrimary = $this->scalarValue($this->params->get('ac_primary'))
                ?? $this->acFallback('ac_primary_fallback', 'location_ac_appointments_primary');
            if ($acPrimary !== null) {
                // Append the primary-care appointments ContactPoint
                $points[] = $this->appointmentPoint($contactPointType, 'Primary Care Appointments', $acPrimary);
            }

            // Specialty-care appointments: location override, else the Find-a-Doc default
            $acSpecialty = $this->scalarValue($this->params->get('ac_specialty'))
                ?? $this->acFallback('ac_specialty_fallback', 'location_ac_appointments_specialty');
            if ($acSpecialty !== null) {
                // Append the specialty-care appointments ContactPoint
                $points[] = $this->appointmentPoint($contactPointType, 'Specialty Care Appointments', $acSpecialty);
            }
        }

        // New / returning patient appointment numbers (gated by the separate-appointment-number toggle)
        $new = $this->appointmentGateOpen() ? $this->scalarValue($this->params->get('phone_appointment_new')) : null;
        $return = $this->appointmentGateOpen() ? $this->scalarValue($this->params->get('phone_appointment_return')) : null;

        if ($new !== null) {
            // Name the first number for new patients only when a returning number splits them
            $name = $return !== null ? 'New Patient Appointments' : 'Appointments';

            // Append the (new-patient) appointments ContactPoint
            $points[] = $this->appointmentPoint($contactPointType, $name, $new);
        }

        if ($return !== null) {
            // Append the returning-patient appointments ContactPoint
            $points[] = $this->appointmentPoint($contactPointType, 'Returning Patient Appointments', $return);
        }

        // Free-form additional phone numbers (label / phone / note repeater)
        foreach ($this->additionalRows($this->params->get('phone_additional')) as $point) {
            // Build the ContactPoint from the row
            $built = $this->additionalPoint($contactPointType, $point);

            // Append only rows that carry a phone number
            if ($built !== null) {
                // Append the additional-phone ContactPoint
                $points[] = $built;
            }
        }

        // Emit nothing when no phone resolved
        if ($points === []) {
            // Return an empty string to omit the property
            return '';
        }

        // Serialize the ContactPoint array for JSON-LD embedding
        $json = json_encode($points, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // Return the JSON array, or an empty string when encoding fails
        return $json === false ? '' : $json;
    }

    /**
     * Build an appointment ContactPoint node.
     *
     * @return array<string, string>
     */
    private function appointmentPoint(string $type, string $name, string $telephone): array
    {
        return [
            '@type' => $type,
            'contactType' => 'appointments',
            'name' => $name,
            'telephone' => $telephone,
        ];
    }

    /**
     * Build an additional-phone ContactPoint node from a resolved row.
     *
     * @param  array{label: ?string, phone: ?string, description: ?string}  $row
     * @return array<string, string>|null Null when the row carries no phone
     */
    private function additionalPoint(string $type, array $row): ?array
    {
        // A ContactPoint is meaningless without a telephone
        if ($row['phone'] === null) {
            // Skip rows with no phone number
            return null;
        }

        // Start with the type and any editor label
        $point = ['@type' => $type];

        // Add the label as the name when present
        if ($row['label'] !== null) {
            // Carry the editor's label
            $point['name'] = $row['label'];
        }

        // Add the telephone
        $point['telephone'] = $row['phone'];

        // Add the note as a description when present
        if ($row['description'] !== null) {
            // Carry the editor's note
            $point['description'] = $row['description'];
        }

        return $point;
    }

    /**
     * Normalize the additional-phone repeater into resolved rows.
     *
     * @return array<int, array{label: ?string, phone: ?string, description: ?string}>
     */
    private function additionalRows(mixed $raw): array
    {
        // Unwrap a Statamic field value
        if ($raw instanceof Value) {
            // Read the inner value for coercion
            $raw = $raw->value();
        }

        // The repeater augments to a list of rows
        if (! \is_array($raw)) {
            // Return no rows when the shape is unexpected
            return [];
        }

        // Resolve each row's label / phone / note
        $rows = [];

        // Walk each repeater row
        foreach ($raw as $row) {
            // Resolve and collect the row fields
            $rows[] = [
                'label' => $this->scalarValue($this->rowField($row, 'text')),
                'phone' => $this->scalarValue($this->rowField($row, 'phone')),
                'description' => $this->scalarValue($this->rowField($row, 'description')),
            ];
        }

        return $rows;
    }

    /**
     * Read a single field from a repeater row (a `Values` set or a plain array).
     */
    private function rowField(mixed $row, string $key): mixed
    {
        // An augmented repeater set exposes fields via array access
        if ($row instanceof Values) {
            // Read the field value
            return $row[$key] ?? null;
        }

        // A plain array row carries fields by key
        if (\is_array($row)) {
            // Read the keyed value
            return $row[$key] ?? null;
        }

        // Anything else has no readable field
        return null;
    }

    /**
     * Resolve an Arkansas Children's appointment fallback number.
     *
     * The engine's property-Antlers scope carries only the location's own
     * fields, not the globals cascade, so the site-wide Find-a-Doc default is
     * read here via the GlobalSet facade. An explicit `$paramKey` parameter (when
     * present) overrides that read — used by tests to inject a fallback without
     * standing up a global set.
     *
     * @param  string  $paramKey  Optional parameter that overrides the global read
     * @param  string  $globalKey  Handle of the field on the Find-a-Doc settings global
     */
    private function acFallback(string $paramKey, string $globalKey): ?string
    {
        // An explicitly-passed fallback parameter overrides the global read
        $param = $this->params->get($paramKey);
        if ($param !== null) {
            // Use the injected fallback
            return $this->scalarValue($param);
        }

        // Read the site-wide Find-a-Doc default
        return $this->globalPhone($globalKey);
    }

    /**
     * Read a phone number from the Find-a-Doc settings global.
     *
     * The global is rooted at the `default` site and its fields are
     * non-localizable, so `inDefaultSite()` resolves them from any site context
     * (UAMS-Web/uams-statamic-archive#1698). The value is read via the GlobalSet facade rather than the scope
     * because the engine's property-Antlers scope carries only the location's
     * own fields, not the globals cascade.
     */
    private function globalPhone(string $globalKey): ?string
    {
        // Resolve the Find-a-Doc settings global's default-origin localization
        $variables = GlobalSet::findByHandle(self::FAD_SETTINGS_GLOBAL)?->inDefaultSite();

        // Coerce the stored value to a clean string when the localization exists
        return $variables instanceof GlobalVariables
            ? $this->scalarValue($variables->get($globalKey))
            : null;
    }

    /**
     * Whether the separate-appointment-number gate is open.
     *
     * The gate is the `phone_appointment_query` toggle. When explicitly false the
     * new/returning appointment numbers are suppressed even if stale data lingers
     * (the blueprint hides but does not clear the fields). When the parameter is
     * absent, the gate defaults open so presence of a number alone drives it.
     */
    private function appointmentGateOpen(): bool
    {
        // Read the optional gate parameter
        $gate = $this->params->get('appointment_enabled');

        // Absent gate defaults open
        if ($gate === null) {
            // Treat a missing gate as open
            return true;
        }

        // Unwrap a Statamic field value
        if ($gate instanceof Value) {
            // Read the inner value for coercion
            $gate = $gate->value();
        }

        // Unwrap an augmented labeled option (the toggle augments to LabeledValue)
        if ($gate instanceof LabeledValue) {
            // Read the underlying value for coercion
            $gate = $gate->value();
        }

        // Accept native true and the common truthy string forms
        return in_array($gate, [true, 1, '1', 'true'], true);
    }

    /**
     * Whether the Arkansas Children's appointment gate is open.
     *
     * The gate is the `ac_appointments_query` toggle. When absent it defaults
     * closed: the global fallback must not leak onto non-AC locations, so AC
     * ContactPoints are emitted only when the gate is explicitly affirmed.
     */
    private function acGateOpen(): bool
    {
        // Read the gate parameter
        $gate = $this->params->get('ac_enabled');

        // Unwrap a Statamic field value
        if ($gate instanceof Value) {
            // Read the inner value for coercion
            $gate = $gate->value();
        }

        // Unwrap an augmented labeled option (the toggle augments to LabeledValue)
        if ($gate instanceof LabeledValue) {
            // Read the underlying value for coercion
            $gate = $gate->value();
        }

        // Accept native true and the common truthy string forms
        return in_array($gate, [true, 1, '1', 'true'], true);
    }

    /**
     * Coerce a bound value to a clean non-empty string, or null.
     */
    private function scalarValue(mixed $raw): ?string
    {
        // Unwrap a Statamic field value
        if ($raw instanceof Value) {
            // Read the inner value for coercion
            $raw = $raw->value();
        }

        // Unwrap an augmented labeled option
        if ($raw instanceof LabeledValue) {
            // Read the underlying value for coercion
            $raw = $raw->value();
        }

        // Reject non-scalar values
        if (! \is_scalar($raw)) {
            // Return null when there is nothing to coerce
            return null;
        }

        // Strip any markup and trim surrounding whitespace (defense in depth)
        $clean = trim(strip_tags((string) $raw));

        // Return the cleaned value when non-empty
        return $clean === '' ? null : $clean;
    }

    /**
     * Resolved HTTPS schema.org `@type` URI for `ContactPoint` (memoized).
     *
     * @return string|null Same as `SchemaObjectFragmentBuilder::normalizedType()`
     */
    private function cachedNormalizedContactPointType(): ?string
    {
        // Resolve and memoize the normalized type on first use
        if (self::$normalizedContactPointType === null) {
            self::$normalizedContactPointType = SchemaObjectFragmentBuilder::normalizedType(
                'ContactPoint'
            );
        }

        // Return the cached normalized type URI
        return self::$normalizedContactPointType;
    }
}
