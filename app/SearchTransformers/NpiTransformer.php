<?php

declare(strict_types=1);

namespace App\SearchTransformers;

use App\Support\ReplicatorSetFlattener;

/**
 * Flattens the `clinical_locations` `npi` replicator into searchable text.
 *
 * A location can carry more than one National Provider Identifier, so `npi` is
 * a replicator whose rows each store the identifier under the `item` row handle
 * (`uamsweb_common_identifiers.npi`). `npi` was the only replicator content
 * field in an index without a transformer — the siblings (`alternate_name`,
 * `same_as`, `additional_type`) each flatten their replicator to one specific
 * row value. Statamic's generic array flattener would otherwise index every row
 * value (including replicator scaffolding); this transformer indexes just the
 * NPI digits, for parity and a clean, deterministic searchable value. Delegates
 * the row-walking to {@see ReplicatorSetFlattener}.
 */
final class NpiTransformer
{
    /**
     * Collapse `npi` replicator rows into one searchable string of NPI values.
     *
     * @param  mixed  $value  Raw replicator value (list of set rows)
     * @return string|null Joined `item` values, or null when none are present
     */
    public function handle(
        mixed $value
    ): ?string {
        return ReplicatorSetFlattener::flatten(
            $value,
            ['item']
        );
    }
}
