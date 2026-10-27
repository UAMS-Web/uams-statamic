<?php

declare(strict_types=1);

namespace App\Tags;

use App\Services\SchemaTemplates\Mergers\AhaidMerger;
use App\Services\SchemaTemplates\Mergers\CmsCertificationNumberMerger;
use App\Services\SchemaTemplates\Mergers\NpiMerger;
use App\Services\SchemaTemplates\Mergers\VaSubstationIdMerger;
use Statamic\Fields\LabeledValue;
use Statamic\Fields\Value;
use Statamic\Fields\Values;
use Statamic\Tags\Parameters;
use Statamic\Tags\Tags;

/**
 * Antlers tag: emit a location's regulatory identifiers as JSON-LD
 * `PropertyValue` nodes for the `identifier` property.
 *
 * A location's identifiers live on the location entry, not on the shared
 * schema-template entry, so the engine's identifiers-tab merger
 * (`SchemaClassificationsIdentifiersMerger`, which reads the *template* entry)
 * never sees them. This tag reads them from the location context and reuses the
 * same per-identifier merger value shapes so the emitted PropertyValues match
 * those merged elsewhere in the corpus.
 *
 * Sources (live post-#801 location handles):
 *   - npi: `National Provider Identifier` replicator (each row's `item`), gated
 *     by `npi_enabled` (the `npi_query` toggle) — when explicitly false, no NPI
 *     is emitted even if stale data lingers.
 *   - va_substation_id / ahaid / hospital_ccn_id: single identifier/text fields,
 *     emitted whenever non-empty (their blueprint gates are structural display
 *     conditions on other fields, not re-derived here).
 *
 * Parameters:
 *   - npi: bound value of the `npi` replicator field.
 *   - npi_enabled: bound value of the `npi_query` toggle (optional gate).
 *   - va_substation_id: bound value of the `va_substation_id` field.
 *   - ahaid: bound value of the `ahaid` field.
 *   - hospital_ccn_id: bound value of the `hospital_ccn_id` field.
 *
 * Output is empty when no identifier resolves. Identifiers are emitted in the
 * order npi → va_substation_id → ahaid → hospital_ccn_id; the engine flattens a
 * single-element array to the bare PropertyValue object.
 *
 * Example:
 *   {{ schema_location_identifiers :npi="npi" :npi_enabled="npi_query"
 *      :va_substation_id="va_substation_id" :ahaid="ahaid"
 *      :hospital_ccn_id="hospital_ccn_id" }}
 *
 * @property Parameters $params
 */
class SchemaLocationIdentifiers extends Tags
{
    /**
     * Registered Antlers handle `{{ schema_location_identifiers }}`.
     *
     * @var string
     */
    protected static $handle = 'schema_location_identifiers';

    /**
     * Build the JSON array of identifier PropertyValue nodes.
     *
     * @return string JSON array, or empty string when no identifier resolves
     */
    public function index(): string
    {
        // Accumulate one PropertyValue per resolved identifier value
        $identifiers = [];

        // NPI — gated by the npi_query toggle; one PropertyValue per replicator row
        if ($this->npiGateOpen()) {
            // Reuse the canonical NPI PropertyValue shape per value
            $npiMerger = app(NpiMerger::class);

            // Walk each entered NPI value
            foreach ($this->npiValues($this->params->get('npi')) as $npi) {
                // Take only the identifier PropertyValue (drop the usNPI scalar)
                $identifiers[] = $npiMerger->buildFragment($npi)['identifier'];
            }
        }

        // VA STA6A / substation ID — single field, emitted when present
        $va = $this->scalarValue($this->params->get('va_substation_id'));
        if ($va !== null) {
            // Reuse the canonical VA substation PropertyValue shape
            $identifiers[] = app(VaSubstationIdMerger::class)->buildFragment($va)['identifier'];
        }

        // AHAID — single field, emitted when present
        $ahaid = $this->scalarValue($this->params->get('ahaid'));
        if ($ahaid !== null) {
            // Reuse the canonical AHAID PropertyValue shape
            $identifiers[] = app(AhaidMerger::class)->buildFragment($ahaid)['identifier'];
        }

        // CMS Certification Number (CCN) — single text field, emitted when present
        $ccn = $this->scalarValue($this->params->get('hospital_ccn_id'));
        if ($ccn !== null) {
            // Reuse the canonical CCN PropertyValue shape
            $identifiers[] = app(CmsCertificationNumberMerger::class)->buildFragment($ccn)['identifier'];
        }

        // Emit nothing when no identifier resolved
        if ($identifiers === []) {
            // Return an empty string to omit the property
            return '';
        }

        // Serialize the identifier array for JSON-LD embedding
        $json = json_encode($identifiers, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // Return the JSON array, or an empty string when encoding fails
        return $json === false ? '' : $json;
    }

    /**
     * Whether the NPI gate is open.
     *
     * The gate is the `npi_query` toggle: when explicitly false, no NPI is
     * emitted (stale data is suppressed). When the parameter is absent, the gate
     * defaults open so presence of NPI values alone drives emission.
     */
    private function npiGateOpen(): bool
    {
        // Read the optional gate parameter
        $gate = $this->params->get('npi_enabled');

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

        // Unwrap an augmented labeled option (a cast_booleans toggle augments to LabeledValue)
        if ($gate instanceof LabeledValue) {
            // Read the underlying value for coercion
            $gate = $gate->value();
        }

        // Accept native true and the common truthy string forms
        return in_array($gate, [true, 1, '1', 'true'], true);
    }

    /**
     * Normalize the NPI replicator value into a list of NPI strings.
     *
     * @return array<int, string>
     */
    private function npiValues(mixed $raw): array
    {
        // Unwrap a Statamic field value
        if ($raw instanceof Value) {
            // Read the inner value for coercion
            $raw = $raw->value();
        }

        // The replicator augments to a list of rows
        if (! \is_array($raw)) {
            // Return no values when the shape is unexpected
            return [];
        }

        // Collect each row's identifier string
        $out = [];

        // Walk each replicator row
        foreach ($raw as $row) {
            // Resolve the row's `item` identifier string
            $item = $this->itemFromRow($row);

            // Keep non-empty values only
            if ($item !== null) {
                // Append the resolved NPI
                $out[] = $item;
            }
        }

        return $out;
    }

    /**
     * Resolve a single replicator row's `item` identifier string.
     *
     * @param  mixed  $row  A `Values` set, an `{item: …}` array, or a bare string
     */
    private function itemFromRow(mixed $row): ?string
    {
        // An augmented replicator set exposes `item` via array access
        if ($row instanceof Values) {
            // Read the `item` field value
            return $this->scalarValue($row['item'] ?? null);
        }

        // A plain array row carries the value under `item`
        if (\is_array($row)) {
            // Read the `item` key
            return $this->scalarValue($row['item'] ?? null);
        }

        // A bare string row is the identifier itself
        return $this->scalarValue($row);
    }

    /**
     * Coerce a bound value to a clean non-empty identifier string, or null.
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
}
