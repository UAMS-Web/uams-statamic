<?php

declare(strict_types=1);

namespace App\SearchTransformers;

use App\Support\ReplicatorSetFlattener;

/**
 * Flattens the `same_as` schema replicator into searchable text.
 *
 * `same_as` rows (`uamsweb_schema.same_as`) store their reference webpage under
 * the `url` row handle. Delegates the row-walking to
 * {@see ReplicatorSetFlattener}.
 */
final class SameAsTransformer
{
    /**
     * Collapse `same_as` replicator rows into one searchable string of URLs.
     *
     * @param  mixed  $value  Raw replicator value (list of set rows)
     * @return string|null Joined `url` values, or null when none are present
     */
    public function handle(
        mixed $value
    ): ?string {
        return ReplicatorSetFlattener::flatten(
            $value,
            ['url']
        );
    }
}
