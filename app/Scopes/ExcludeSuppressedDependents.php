<?php

declare(strict_types=1);

namespace App\Scopes;

use App\Listeners\RedirectSuppressedDependents;
use App\Services\PublishDependencies\PublishDependencyResolver;
use Statamic\Query\Builder;
use Statamic\Query\Scopes\Scope;

/**
 * Listing-exclusion query scope for the opt-in publish-dependency mechanism
 * (UAMS-Web/uams-statamic-archive#1593): removes every currently-suppressed dependent — an entry whose declared
 * `publish_depends_on` sources are down under its policy — from a collection
 * listing, so a down dependent neither renders on its own URL (the
 * {@see RedirectSuppressedDependents} redirect) nor appears as a
 * card in a listing.
 *
 * The scope is collection-agnostic: it excludes the global set of suppressed
 * dependent ids ({@see PublishDependencyResolver::suppressedEntryIds()}), which
 * ids being globally unique makes safe to apply to any collection's query. A
 * listing opts in by naming the scope in its collection tag's `query_scope`
 * (pipe-composed with any existing scope), mirroring how
 * {@see ExcludeUnsearchableEntries} is wired per-consumer.
 *
 * Example usage on a listing tag:
 * ```
 * {{ collection:clinical_resources
 *      query_scope="clinical_resource_type|exclude_suppressed_dependents" }}
 * ```
 */
final class ExcludeSuppressedDependents extends Scope
{
    /**
     * Scope handle referenced by a collection tag's `query_scope`.
     *
     * @var string
     */
    protected static $handle = 'exclude_suppressed_dependents';

    /**
     * Exclude the suppressed dependent ids from the query.
     *
     * @param  mixed  $query  Statamic query builder, or any value when the scope is not bound to a query
     * @param  mixed  $values  Parameters from the collection tag (unused)
     */
    public function apply(
        mixed $query,
        mixed $values
    ): void {
        // Stop when the scope is not applied to a Statamic query builder.
        if (! $query instanceof Builder) {
            return;
        }

        $ids = app(PublishDependencyResolver::class)->suppressedEntryIds();

        // Nothing suppressed — leave the query untouched (an empty whereNotIn is
        // a no-op, but skipping it avoids the needless clause).
        if ($ids === []) {
            return;
        }

        $query->whereNotIn('id', $ids);
    }
}
