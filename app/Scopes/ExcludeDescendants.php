<?php

declare(strict_types=1);

namespace App\Scopes;

use App\Support\ContentDescendantIds;
use App\Support\CpRelFieldEditContext;
use Statamic\Query\Builder;
use Statamic\Query\Scopes\Scope;

/**
 * Query scope that excludes the descendant entry or term IDs of the record being
 * edited from CP relationship pickers, so a self-referential picker cannot select
 * one of its own descendants (which would create a parent/child cycle).
 *
 * Composes with `ExcludeSelf`: this scope excludes descendants only, not the
 * record itself.
 */
class ExcludeDescendants extends Scope
{
    /**
     * Apply the scope.
     *
     * @param  mixed  $query  Statamic query builder, or any value when the scope is not bound to a query
     * @param  mixed  $values  Scope values from the field blueprint (array when valid)
     */
    public function apply(
        mixed $query,
        mixed $values
    ): void {
        // Stop when the scope is not applied to a Statamic query builder
        if (! $query instanceof Builder) {
            return;
        }

        // Stop when scope values are not the expected array shape
        if (! \is_array($values)) {
            return;
        }

        // Resolve the CP context entry or term from the referring URL
        $entryInfo = CpRelFieldEditContext::resolve();

        // Collect descendant ids for the resolved type. resolve() returns a type
        // only together with a validated id, so a URL that yielded no entry or
        // term context (e.g. creating a new record) stops here.
        if ($entryInfo['type'] === 'Entry') {
            $descendants = ContentDescendantIds::forEntry($entryInfo['id']);
        } elseif ($entryInfo['type'] === 'Term') {
            $descendants = ContentDescendantIds::forTerm($entryInfo['id']);
        } else {
            return;
        }

        // Exclude descendant ids from the scoped query when any exist
        if ($descendants !== []) {
            $query->whereNotIn(
                'id',
                $descendants
            );
        }
    }
}
