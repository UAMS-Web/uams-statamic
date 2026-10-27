<?php

declare(strict_types=1);

namespace App\Modifiers;

use App\Services\IptcDigitalSourceValueResolver;
use Statamic\Modifiers\Modifier;

/**
 * Antlers modifier: schema.org DigitalSource URL from `digital_source_type` group
 * data.
 *
 * Collection computed fields such as `digital_source_type_value_schema` are
 * evaluated on the entry (and similar) root; Statamic does not expose those
 * computed values for values that only exist inside replicator rows, Bard sets,
 * or other nested field contexts. This modifier runs the same resolver as that
 * computed when you pipe the raw group payload from any Antlers scope
 * (e.g., `replicator_row:digital_source_type`).
 *
 * Pair with `digital_source_type_value` for the resolved IPTC type key string.
 *
 * Usage:
 *
 * - `{{ digital_source_type | digital_source_type_value_schema }}`
 * - `{{ replicator_row:digital_source_type | digital_source_type_value_schema }}`
 *
 * Parameters: none.
 *
 * Optional extra modifier names: set `protected static $aliases = ['foo'];` on
 * this class (Statamic `RegistersItself` registers each alias to the same
 * modifier).
 */
class DigitalSourceTypeValueSchema extends Modifier
{
    /**
     * Map mixed group payload to a schema.org DigitalSource subtype URL.
     *
     * @param  mixed  $value  Group field value (array or Statamic `Value`)
     * @param  array<int|string, mixed>  $params  Modifier parameters (unused)
     * @param  array<int|string, mixed>  $context  Modifier context (unused)
     * @return string URL such as `https://schema.org/MinorHumanEditsDigitalSource`, or empty string
     *                when the type cannot be resolved
     */
    public function index(
        $value,
        array $params,
        array $context
    ): string {
        // Delegate to the shared IPTC digital source resolver
        $out = IptcDigitalSourceValueResolver::schemaUrlFromGroupPayload($value);

        // Unset local variables to free memory
        unset(
            $context,
            $params
        );

        // Return the resolved schema URL or empty string
        return $out;
    }
}
