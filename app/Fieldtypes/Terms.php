<?php

declare(strict_types=1);

namespace App\Fieldtypes;

use Illuminate\Support\Arr;
use Statamic\Facades\Scope;
use Statamic\Fieldtypes\Terms as BaseTerms;
use Statamic\Query\Builder;
use Statamic\Stache\Query\TermQueryBuilder;

/**
 * App-level taxonomy-terms fieldtype, identical to Statamic terms except the
 * relationship index passes configured query scope handles to `Scope::apply`.
 * Merges field `query_scopes` into the `queryScopes` param and prefers
 * `queryScopes` on the request when the stack provides them. Aligns with
 * `App\Fieldtypes\Entries` and GitHub issue statamic/cms#14204.
 *
 * Does not call `Relationship::applyIndexQueryScopes` on the typed terms path:
 * that parent re-reads field `query_scopes` and would overwrite the request-
 * preferred handle list before `Scope::apply`.
 */
class Terms extends BaseTerms
{
    /**
     * Add merged scope handles to params before the relationship runs each scope.
     * Signature must stay untyped to match `Relationship::applyIndexQueryScopes`
     * (the Statamic base does not declare param types; adding them here is a fatal in
     * PHP). Types are applied on `applyIndexQueryScopesForTerms` instead.
     *
     * @param  mixed  $query  See `applyIndexQueryScopesForTerms` (term index query).
     * @param  mixed  $params  See `applyIndexQueryScopesForTerms` (request params for each `Scope::apply`
     *                         call).
     */
    #[\Override]
    protected function applyIndexQueryScopes(
        $query,
        $params
    ): void {
        // Check the relationship passed a term index query and a param map
        if (
            ! $query instanceof Builder
            || ! is_array($params)
        ) {
            // ---
            // Delegate unchanged when the stack does not match the terms index
            // path (keeps the same base behavior as if this override were not
            // present)
            // ---
            parent::applyIndexQueryScopes(
                $query,
                $params
            );

            return;
        }

        /** @var array<string, mixed> $params */
        $this->applyIndexQueryScopesForTerms(
            $query,
            $params
        );
    }

    /**
     * Merge request/config scope handles and run each resolved scope.
     * Declared with full param types; the override only forwards when the
     * runtime values match.
     *
     * @param  Builder|TermQueryBuilder  $query  Index query for taxonomy terms.
     * @param  array<string, mixed>  $params  Request-style params for each `Scope::apply` call.
     */
    private function applyIndexQueryScopesForTerms(
        Builder|TermQueryBuilder $query,
        array $params
    ): void {
        // Prefer request `queryScopes` over field `query_scopes` (#14204).
        $handles = $this->mergedIndexQueryScopeHandles($params);

        // Build params so each scope can read the active handle under `queryScopes`
        $params = [
            ...$params,
            'queryScopes' => $handles,
        ];

        // Run each resolved scope with the merged params (do not call parent:
        // Relationship overwrites `queryScopes` from field config alone).
        collect($handles)
            ->map(fn ($handle) => Scope::find($handle))
            ->filter()
            // @phpstan-ignore method.nonObject (Scope::find() is declared mixed, but it only returns a registered Statamic\Query\Scopes\Scope instance or null, and filter() drops the null)
            ->each(fn ($scope) => $scope->apply($query, $params));
    }

    /**
     * Resolve the scope handle list passed into `Scope::apply` for the index
     * picker.
     *
     * @param  array<string, mixed>  $params  Request-style params, sometimes including a `queryScopes` key.
     * @return array<int, string> Merged query scope handles for the index relationship picker.
     */
    protected function mergedIndexQueryScopeHandles(
        array $params
    ): array {
        // Read `queryScopes` the relationship or request may have attached
        /** @var array<int, string> $fromRequest */
        $fromRequest = Arr::wrap(
            $params['queryScopes'] ?? []
        );

        // Check whether the request path already provided non-empty scope handles
        if ($fromRequest !== []) {
            $result = $fromRequest;
            unset($fromRequest);

            // Return the request copy; do not replace with field `query_scopes`
            return $result;
        }

        // Read scope handles from the field when the request list is empty
        /** @var array<int, string> $result */
        $result = Arr::wrap(
            $this->config('query_scopes')
        );
        unset($fromRequest);

        // Return the config-driven list for each `Scope::apply` invocation
        return $result;
    }
}
