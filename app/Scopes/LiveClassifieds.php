<?php

declare(strict_types=1);

namespace App\Scopes;

use App\Support\Classifieds\ClassifiedExpiry;
use Statamic\Query\Builder;
use Statamic\Query\Scopes\Scope;

/**
 * Query scope restricting a `classifieds` query to listings that have not yet
 * expired, keeping each through its `expiration_date` (UAMS-Web/uams-statamic-archive#2096).
 *
 * Backs the classifieds board index. Publication is handled separately by the
 * `collection` tag, which already excludes unpublished entries; this scope removes
 * published listings whose date has passed, until the scheduled job UAMS-Web/uams-statamic-archive#2520 builds
 * unpublishes them. When a listing expires is defined once,
 * in `App\Support\Classifieds\ClassifiedExpiry`, which a listing's page reads too.
 *
 * A listing with no `expiration_date` is treated as live. The blueprint requires the
 * field, so that case is an entry saved outside the Control Panel, and hiding it
 * silently would lose a listing nobody decided to remove.
 */
class LiveClassifieds extends Scope
{
    /**
     * @param  ClassifiedExpiry  $expiry  The shared definition of an expired listing.
     */
    public function __construct(private readonly ClassifiedExpiry $expiry)
    {
        //
    }

    /**
     * Constrain the query to listings whose expiration date is today or later.
     *
     * @param  Builder  $query  Entry query builder for the `classifieds` collection
     * @param  array<string, mixed>  $values  Antlers tag parameters (unused; the scope is single-purpose)
     */
    public function apply(
        $query,
        $values
    ): void {
        // Resolve today's calendar date in the audience's timezone
        $today = $this->expiry->today();

        // Compare calendar dates with `whereDate`. A plain `where` against a bare
        // `Y-m-d` was measured to keep a listing dated today under `>` and to match
        // nothing under `=`, so it cannot express "on or after today".
        $query->where(function (Builder $query) use ($today): void {
            $query->whereNull('expiration_date');
            $query->orWhereDate('expiration_date', '>=', $today);
        });
    }
}
