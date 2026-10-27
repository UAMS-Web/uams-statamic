<?php

declare(strict_types=1);

namespace App\SearchTransformers;

use App\Support\ReplicatorSetFlattener;

/**
 * Flattens the `alternate_name` schema replicator into searchable text.
 *
 * `alternate_name` rows (`uamsweb_schema.alternate_name`, also re-exported by
 * `uamsweb_brand_organization.alternate_name`) store each acronym or alternate
 * spelling under the `text` row handle. Delegates the row-walking to
 * {@see ReplicatorSetFlattener}.
 */
final class AlternateNameTransformer
{
    /**
     * Collapse `alternate_name` replicator rows into one searchable string.
     *
     * @param  mixed  $value  Raw replicator value (list of set rows)
     * @return string|null Joined `text` values, or null when none are present
     */
    public function handle(
        mixed $value
    ): ?string {
        return ReplicatorSetFlattener::flatten(
            $value,
            ['text']
        );
    }
}
