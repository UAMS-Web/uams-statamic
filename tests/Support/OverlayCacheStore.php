<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Contracts\Cache\Store;

/**
 * A copy-on-write cache store: reads fall through to another store, and every write
 * stays in this process's memory (UAMS-Web/uams-statamic#556).
 *
 * {@see EntriesAndTermsIsolatedTestCase} points the Stache at one of these for the
 * length of a test. The warm per-worker Stache cache stays readable, so a test pays
 * nothing to rebuild the indexes it does not change, and what the test writes into
 * that cache (a rewritten path index, a saved entry's index rows, a forgotten item)
 * is discarded with this object rather than left for the next test in the process.
 *
 * Values are kept serialized, as the file store keeps them, so a caller that changes
 * an object it read cannot change the stored value behind the next reader's back.
 */
final class OverlayCacheStore implements Store
{
    /**
     * Values written during the test, serialized, keyed by cache key.
     *
     * @var array<string, string>
     */
    private array $written = [];

    /**
     * Keys forgotten during the test, which must read as absent even where the
     * fallback still holds them.
     *
     * @var array<string, true>
     */
    private array $forgotten = [];

    /**
     * Whether the test flushed the store, after which nothing falls through.
     */
    private bool $flushed = false;

    /**
     * @param  Store  $fallback  The store reads fall through to; never written.
     */
    public function __construct(private readonly Store $fallback) {}

    public function get($key): mixed
    {
        // Prefer what the test wrote, then honour a forget or flush, then fall through
        if (\array_key_exists($key, $this->written)) {
            return unserialize($this->written[$key]);
        }

        if ($this->flushed || isset($this->forgotten[$key])) {
            return null;
        }

        return $this->fallback->get($key);
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    public function many(array $keys): array
    {
        $values = [];

        // Resolve each key through the same precedence as a single read
        foreach ($keys as $key) {
            $values[$key] = $this->get($key);
        }

        return $values;
    }

    public function put($key, $value, $seconds): bool
    {
        // Expiry is not tracked: the overlay lives for one test
        $this->written[$key] = serialize($value);
        unset($this->forgotten[$key]);

        return true;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function putMany(array $values, $seconds): bool
    {
        // Write each value through the single-key path
        foreach ($values as $key => $value) {
            $this->put($key, $value, $seconds);
        }

        return true;
    }

    /**
     * @param  string  $key
     * @param  int  $value
     */
    public function increment($key, $value = 1): int
    {
        $current = $this->get($key);
        $next = (\is_int($current) ? $current : 0) + $value;

        $this->put($key, $next, 0);

        return $next;
    }

    /**
     * @param  string  $key
     * @param  int  $value
     */
    public function decrement($key, $value = 1): int
    {
        return $this->increment($key, -$value);
    }

    public function forever($key, $value): bool
    {
        return $this->put($key, $value, 0);
    }

    public function touch($key, $seconds): bool
    {
        // A key is present when a read through the overlay finds it
        return $this->get($key) !== null;
    }

    public function forget($key): bool
    {
        unset($this->written[$key]);
        $this->forgotten[$key] = true;

        return true;
    }

    public function flush(): bool
    {
        $this->written = [];
        $this->forgotten = [];
        $this->flushed = true;

        return true;
    }

    public function getPrefix(): string
    {
        return $this->fallback->getPrefix();
    }
}
