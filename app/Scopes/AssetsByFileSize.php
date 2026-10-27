<?php

declare(strict_types=1);

namespace App\Scopes;

use Illuminate\Support\Arr;
use Statamic\Assets\QueryBuilder;
use Statamic\Query\Scopes\Scope;

/**
 * Asset query scope that filters assets by file size.
 *
 * Resolves min/max size bounds (in bytes) from encoded query scope handles, such
 * as:
 * - assets_by_file_size_min_1000000          (>= 1 MB)
 * - assets_by_file_size_max_10000000         (<= 10 MB)
 * - assets_by_file_size_min_1000000 + ..._max_10000000   (1–10 MB)
 *
 * Mirrors {@see AssetsByFileType}: the data travels in the encoded handle names
 * (the only transport the asset browser carries today), and the base
 * `assets_by_file_size` handle's apply() decodes them. The encoded handles are
 * deliberately NOT registered as aliases — sizes are arbitrary, so enumerating
 * them is impossible; an unregistered handle in `query_scopes` is harmlessly
 * skipped by Statamic's scope resolver (ScopeRepository::find() returns null),
 * while this base scope still reads it as data.
 *
 * Used with the Assets fieldtype so the asset browser can show only assets within
 * a field's `min_file_size` / `max_file_size` bounds without query-scope
 * parameter transport.
 *
 * Statamic v6's asset browser applies a field's configured `query_scopes` to the
 * index query — `Statamic\Http\Controllers\CP\Assets\BrowserController::applyQueryScopes()`
 * resolves each handle via `Scope::find()` and calls `apply($query, $params)` — so
 * once `Assets::preload()` puts the base handle in `config.query_scopes`, this
 * scope filters the browser (it reads the encoded `_min_/_max_` handles from the
 * forwarded `$params['queryScopes']`). Server-side size validation remains the
 * authoritative enforcement regardless (the original UAMS-Web/uams-statamic-archive#487 concern that scope
 * params never reached the browser no longer applies in v6).
 */
class AssetsByFileSize extends Scope
{
    /**
     * Scope handle for registration and config.
     *
     * @var string
     */
    protected static $handle = 'assets_by_file_size';

    /**
     * Apply the scope to the asset query.
     *
     * Applies size-based filtering derived from encoded query scope handles in
     * request values. No-op when no encoded bound is present.
     *
     * @param  QueryBuilder  $query  Asset query builder (has where() from parent)
     * @param  array<string, mixed>  $values  Request values (e.g. queryScopes)
     */
    public function apply(
        $query,
        $values
    ): void {
        // Decode the min/max byte bounds from encoded query scope handles.
        [$minBytes, $maxBytes] = $this->sizeBoundsFromScopeHandles($values);

        // Apply the lower bound when a minimum size was encoded.
        if ($minBytes !== null) {
            $query->where(
                'size',
                '>=',
                $minBytes
            );
        }

        // Apply the upper bound when a maximum size was encoded.
        if ($maxBytes !== null) {
            $query->where(
                'size',
                '<=',
                $maxBytes
            );
        }

        // Clean up variables to free up memory.
        unset(
            $minBytes,
            $maxBytes
        );
    }

    /**
     * Decode min/max byte bounds from encoded query scope handles.
     *
     * Supports handles in the format:
     * - assets_by_file_size_min_{bytes}
     * - assets_by_file_size_max_{bytes}
     *
     * The tightest bound wins when a bound is encoded more than once (largest
     * min, smallest max), so multiple fields' scopes never widen each other.
     *
     * @param  array<string, mixed>  $values
     * @return array{0: int|null, 1: int|null} [minBytes, maxBytes]
     */
    private function sizeBoundsFromScopeHandles(array $values): array
    {
        // Extract encoded handle suffixes from incoming query scopes.
        $suffixes = $this->encodedScopeSuffixes($values);

        // Initialize the resolved bounds as absent.
        $minBytes = null;
        $maxBytes = null;

        // Parse each suffix as a min or max byte bound.
        foreach ($suffixes as $suffix) {
            // Resolve a minimum bound encoded as "min_{bytes}".
            if (str_starts_with($suffix, 'min_')) {
                // Read the integer byte value after the "min_" marker.
                $bytes = $this->parseBytes(substr($suffix, 4));

                // Keep the largest minimum (the tightest lower bound).
                if ($bytes !== null && ($minBytes === null || $bytes > $minBytes)) {
                    $minBytes = $bytes;
                }

                // Move on to the next suffix.
                continue;
            }

            // Resolve a maximum bound encoded as "max_{bytes}".
            if (str_starts_with($suffix, 'max_')) {
                // Read the integer byte value after the "max_" marker.
                $bytes = $this->parseBytes(substr($suffix, 4));

                // Keep the smallest maximum (the tightest upper bound).
                if ($bytes !== null && ($maxBytes === null || $bytes < $maxBytes)) {
                    $maxBytes = $bytes;
                }
            }
        }

        // Clean up variables to free up memory.
        unset($suffixes);

        // Return the resolved [min, max] byte bounds.
        return [$minBytes, $maxBytes];
    }

    /**
     * Parse a non-negative integer byte count from an encoded handle fragment.
     */
    private function parseBytes(string $fragment): ?int
    {
        // Reject anything that is not a run of digits (no signs, dots, or units).
        if ($fragment === '' || ! ctype_digit($fragment)) {
            // Signal an unusable fragment.
            return null;
        }

        // Reject a value that would overflow PHP's int — a too-long digit run
        // silently saturates to PHP_INT_MAX on cast, which would apply a wrong
        // (clamped) bound rather than no bound. Compare as strings to avoid the
        // overflow we are guarding against.
        $maxInt = (string) PHP_INT_MAX;
        if (\strlen($fragment) > \strlen($maxInt)
            || (\strlen($fragment) === \strlen($maxInt) && $fragment > $maxInt)) {
            // Signal an out-of-range fragment.
            return null;
        }

        // Cast the validated digit string to an integer byte count.
        return (int) $fragment;
    }

    /**
     * Extract encoded suffixes from query scope handles.
     *
     * @param  array<string, mixed>  $values
     * @return array<int, string>
     */
    private function encodedScopeSuffixes(array $values): array
    {
        // Extract query scope handles from contextual values.
        $queryScopes = Arr::wrap($values['queryScopes'] ?? []);

        // Initialize the encoded-handle prefix.
        $prefix = static::$handle.'_';

        // Initialize extracted suffix collection.
        $suffixes = [];

        // Parse each query scope and collect matching encoded suffixes.
        foreach ($queryScopes as $queryScope) {
            // Check if the current value is not a string.
            if (! \is_string($queryScope)) {
                // Skip non-string values.
                continue;
            }

            // Check if the query scope starts with the encoded prefix.
            if (! str_starts_with($queryScope, $prefix)) {
                // Skip values that do not start with the encoded prefix.
                continue;
            }

            // Extract the suffix portion from the query scope.
            $suffix = substr($queryScope, \strlen($prefix));

            // Check if the suffix is empty.
            if ($suffix === '') {
                // Skip empty suffixes.
                continue;
            }

            // Add the lowercased suffix to the collection.
            $suffixes[] = strtolower($suffix);
        }

        // Dedupe suffixes for stable parsing.
        $suffixes = array_values(array_unique($suffixes));

        // Clean up variables to free up memory.
        unset(
            $queryScopes,
            $prefix
        );

        // Return extracted encoded suffixes.
        return $suffixes;
    }
}
