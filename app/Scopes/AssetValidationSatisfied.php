<?php

declare(strict_types=1);

namespace App\Scopes;

use App\Support\Statamic\Assets\AssetValidationSatisfiedResolver;
use Illuminate\Support\Arr;
use Statamic\Assets\Asset as StatamicAsset;
use Statamic\Assets\QueryBuilder;
use Statamic\Query\Scopes\Scope;

/**
 * Asset query scope that filters by dynamic blueprint validation satisfaction.
 *
 * Supported encoded handles:
 * - asset_validation_satisfied (defaults to true)
 * - asset_validation_satisfied_true
 * - asset_validation_satisfied_false
 */
class AssetValidationSatisfied extends Scope
{
    /**
     * Scope handle for registration and config.
     *
     * @var string
     */
    protected static $handle = 'asset_validation_satisfied';

    /**
     * Register dynamic aliases for base/true/false scope handles.
     *
     * @return array<int, string>
     */
    public static function aliases(): array
    {
        // Return true/false encoded aliases for this scope
        return [
            static::$handle,
            static::$handle.'_true',
            static::$handle.'_false',
        ];
    }

    /**
     * Apply the scope to the asset query.
     *
     * @param  QueryBuilder  $query  Asset query builder
     * @param  array<string, mixed>  $values  Request values (e.g. queryScopes)
     */
    public function apply(
        $query,
        $values
    ): void {
        // Resolve desired boolean state from encoded query scope handles
        $desiredState = $this->desiredStateFromScopeHandles($values);

        // Check if no encoded state was provided
        if ($desiredState === null) {
            // Unset local variables to free memory
            unset($desiredState);

            // Return early when scope aliases are not present
            return;
        }

        // Retrieve assets from the current query for dynamic validation checks
        /** @var iterable<int, mixed> $assets */
        $assets = $query->get();

        // Initialize matching path collection
        $matchingPaths = [];

        // Loop through each asset and evaluate blueprint validation satisfaction
        foreach ($assets as $asset) {
            // Check if current item is not a Statamic asset
            if (! $asset instanceof StatamicAsset) {
                // Skip non-asset items
                continue;
            }

            // Resolve validation satisfaction for the current asset
            $isValid = $this->isValidationSatisfied($asset);

            // Check if resolved state matches desired filter state
            if ($isValid === $desiredState) {
                // Add matching asset path to the constraint list
                $matchingPaths[] = $asset->path();
            }
        }

        // Check if no matching paths were found
        if ($matchingPaths === []) {
            // Apply non-matching sentinel path to force empty result set
            $query->where(
                'path',
                '=',
                '__no_asset_validation_matches__'
            );

            // Unset local variables to free memory
            unset(
                $desiredState,
                $assets,
                $matchingPaths
            );

            // Return early after applying empty-result constraint
            return;
        }

        // Apply nested OR path constraints for assets matching validation state
        $query->where(function ($nestedQuery) use ($matchingPaths): void {
            // Narrow the nested query builder to its concrete type
            /** @var QueryBuilder $nestedQuery */

            // Initialize first-constraint flag
            $isFirstConstraint = true;

            // Loop through each matching path and append OR conditions
            foreach ($matchingPaths as $path) {
                // Check if this is the first path constraint
                if ($isFirstConstraint) {
                    // Apply initial where constraint for the first path
                    $nestedQuery->where(
                        'path',
                        '=',
                        $path
                    );

                    // Update first-constraint flag after initial clause
                    $isFirstConstraint = false;
                } else {
                    // Apply OR where constraint for subsequent paths
                    $nestedQuery->orWhere(
                        'path',
                        '=',
                        $path
                    );
                }
            }

            // Unset local variables to free memory
            unset(
                $isFirstConstraint,
                $path
            );
        });

        // Unset local variables to free memory
        unset(
            $desiredState,
            $assets,
            $matchingPaths
        );
    }

    /**
     * Resolve desired true/false state from encoded query scope handles.
     *
     * @param  array<string, mixed>  $values  Incoming request values
     * @return bool|null True/false when encoded handle found, otherwise null
     */
    private function desiredStateFromScopeHandles(array $values): ?bool
    {
        // Extract query scope handles from contextual values
        $queryScopes = Arr::wrap($values['queryScopes'] ?? []);

        // Initialize base/true/false encoded alias handles
        $baseAlias = static::$handle;
        $trueAlias = static::$handle.'_true';
        $falseAlias = static::$handle.'_false';

        // Loop through query scopes and resolve first matching alias
        foreach ($queryScopes as $queryScope) {
            // Check if current query scope is not a string
            if (! \is_string($queryScope)) {
                // Skip non-string scope values
                continue;
            }

            // Check if current query scope matches the base alias
            if ($queryScope === $baseAlias) {
                // Unset local variables to free memory
                unset(
                    $queryScopes,
                    $baseAlias,
                    $trueAlias,
                    $falseAlias,
                    $queryScope
                );

                // Return resolved true state for base alias compatibility
                return true;
            }

            // Check if current query scope matches the true alias
            if ($queryScope === $trueAlias) {
                // Unset local variables to free memory
                unset(
                    $queryScopes,
                    $baseAlias,
                    $trueAlias,
                    $falseAlias,
                    $queryScope
                );

                // Return resolved true state
                return true;
            }

            // Check if current query scope matches the false alias
            if ($queryScope === $falseAlias) {
                // Unset local variables to free memory
                unset(
                    $queryScopes,
                    $baseAlias,
                    $trueAlias,
                    $falseAlias,
                    $queryScope
                );

                // Return resolved false state
                return false;
            }
        }

        // Unset local variables to free memory
        unset(
            $queryScopes,
            $baseAlias,
            $trueAlias,
            $falseAlias
        );

        // Return null when no encoded alias was provided
        return null;
    }

    /**
     * Resolve dynamic validation satisfaction for an asset.
     *
     * @param  StatamicAsset  $asset  Asset to evaluate
     * @return bool True when the asset satisfies blueprint validation
     */
    protected function isValidationSatisfied(
        StatamicAsset $asset
    ): bool {
        // Resolve validation status using the shared asset resolver
        return AssetValidationSatisfiedResolver::resolve($asset);
    }
}
