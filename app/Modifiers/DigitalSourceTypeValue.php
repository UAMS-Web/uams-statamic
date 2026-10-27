<?php

declare(strict_types=1);

namespace App\Modifiers;

use App\Services\IptcDigitalSourceValueResolver;
use Statamic\Modifiers\Modifier;

/**
 * Antlers modifier: IPTC digital source type key from `digital_source_type` group data.
 *
 * Collection computed fields such as `digital_source_type_value` are evaluated on
 * the entry (and similar) root; Statamic does not expose those computeds for values
 * that only exist inside replicator rows, Bard sets, or other nested field contexts.
 * This modifier runs the same resolver as that computed when you pipe the raw group
 * payload from any Antlers scope (e.g.
 * `replicator_row:digital_source_type`).
 *
 * Pair with `digital_source_type_value_schema` for the matching schema.org URL.
 *
 * Usage:
 *
 * - `{{ digital_source_type | digital_source_type_value }}`
 * - `{{ replicator_row:digital_source_type | digital_source_type_value }}`
 *
 * Parameters: none.
 *
 * Optional extra modifier names: set `protected static $aliases = ['foo'];` on
 * this class (Statamic `RegistersItself` registers each alias to the same
 * modifier).
 */
class DigitalSourceTypeValue extends Modifier
{
    /**
     * Map mixed group payload to the resolved camelCase IPTC type key.
     *
     * @param  mixed  $value  Group field value (array or Statamic `Value`)
     * @param  array<int|string, mixed>  $params  Modifier parameters (unused)
     * @param  array<int|string, mixed>  $context  Modifier context (unused)
     * @return string Resolved key (e.g. `humanEdits`), or empty string when unresolved
     */
    public function index(
        $value,
        array $params,
        array $context
    ): string {
        // Delegate to the shared IPTC digital source resolver
        $out = IptcDigitalSourceValueResolver::resolveFromGroupPayload($value);

        // Unset local variables to free memory
        unset(
            $context,
            $params
        );

        // Return the resolved type key or empty string
        return $out;
    }
}
