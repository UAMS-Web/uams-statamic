<?php

declare(strict_types=1);

namespace App\SearchTransformers;

use App\Support\ReplicatorSetFlattener;

/**
 * Flattens the `additional_type` schema replicator into searchable text.
 *
 * `additional_type` rows (`uamsweb_schema.additional_type`) store their external
 * vocabulary RDF class under the `uri` row handle (not `url`). Delegates the
 * row-walking to {@see ReplicatorSetFlattener}.
 */
final class AdditionalTypeTransformer
{
    /**
     * Collapse `additional_type` replicator rows into one searchable string of URIs.
     *
     * @param  mixed  $value  Raw replicator value (list of set rows)
     * @return string|null Joined `uri` values, or null when none are present
     */
    public function handle(
        mixed $value
    ): ?string {
        return ReplicatorSetFlattener::flatten(
            $value,
            ['uri']
        );
    }
}
