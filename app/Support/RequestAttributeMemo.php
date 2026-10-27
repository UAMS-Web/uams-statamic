<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Request-scoped memoization using a string-keyed array on `request()->attributes`.
 *
 * Callers choose a distinct `$bagAttribute` per memo domain (e.g. one attribute name
 * per feature) and string `$itemKey` entries inside that bag. Hits are detected with
 * `array_key_exists` so `null` and empty strings may be cached.
 */
final class RequestAttributeMemo
{
    /**
     * Return a cached value from the request bag or compute, store, and return it.
     *
     * When the application request is not available, invokes `$factory` every time
     * (no memoization).
     *
     * @param  string  $bagAttribute  Request attribute name holding the memo map
     * @param  string  $itemKey  Key within the memo map
     * @param  callable(): mixed  $factory  Invoked on cache miss to build the value
     * @return mixed Cached or freshly built value
     */
    public static function remember(
        string $bagAttribute,
        string $itemKey,
        callable $factory
    ): mixed {
        // Skip memoization when the request container is not bound
        if (! app()->bound('request')) {
            return $factory();
        }

        // Read the current request instance
        $request = request();

        // Read the existing memo bag when present
        $bag = $request->attributes->get($bagAttribute);

        // Return the cached entry when this key was stored (including null / '')
        if (
            is_array($bag)
            && array_key_exists($itemKey, $bag)
        ) {
            // Return the cached entry when this key was stored (including null / '')
            $cached = $bag[$itemKey];

            // Unset local variables to free up memory
            unset(
                $bag,
                $request
            );

            // Return the cached entry
            return $cached;
        }

        // Build the value on cache miss
        $built = $factory();

        // Initialize the bag when the attribute is missing or not an array
        if (! is_array($bag)) {
            $bag = [];
        }

        // Store the built value for later calls in this request
        $bag[$itemKey] = $built;

        // Persist the memo bag on the request
        $request->attributes->set(
            $bagAttribute,
            $bag
        );

        // Unset local variables to free up memory
        unset(
            $bag,
            $request
        );

        // Return the freshly built value
        return $built;
    }

    /**
     * Drop a memo bag from the current request so the next `remember` call misses.
     *
     * Use after mutations that invalidate cached reads for the same bag attribute.
     *
     * @param  string  $bagAttribute  Same bag name passed to `remember`
     */
    public static function forget(string $bagAttribute): void
    {
        // Skip when the request container is not bound
        if (! app()->bound('request')) {
            return;
        }

        // Read the current request instance
        $request = request();

        // Remove the memo bag when present
        $request->attributes->remove($bagAttribute);

        // Unset local variables to free up memory
        unset($request);
    }
}
