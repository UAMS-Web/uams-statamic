<?php

declare(strict_types=1);

namespace App\Scopes;

use App\Support\CpRelFieldEditContext;
use Statamic\Query\Builder;
use Statamic\Query\Scopes\Scope;

/**
 * Query scope that excludes the current entry or term from CP relationship
 * pickers, using the referrer URL.
 */
class ExcludeSelf extends Scope
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
        if (! is_array($values)) {
            return;
        }

        // Resolve the CP edit context from the prior request URL
        $context = CpRelFieldEditContext::resolve();

        // Skip scoping when no current id can be inferred from the URL.
        // resolve() normalizes missing ids to null (never ''), so the empty-
        // string arm is defensive only.
        // @pest-mutate-ignore: EmptyStringToNotEmpty
        if (
            ($context['id']) === null
            || $context['id'] === ''
        ) {
            return;
        }

        // Exclude the current record id from the relationship options
        $query->where(
            'id',
            '!=',
            $context['id']
        );
    }
}
