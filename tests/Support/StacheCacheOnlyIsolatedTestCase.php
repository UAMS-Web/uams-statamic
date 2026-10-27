<?php

declare(strict_types=1);

namespace Tests\Support;

use Tests\Concerns\IsolatesStacheCache;
use Tests\TestCase;

/**
 * Base test case that isolates the Stache CACHE and nothing else, leaving every
 * store pointed at the real `content/` corpus.
 *
 * This is deliberately weaker than its sibling {@see StacheIsolatedTestCase},
 * which re-roots the store directories as well so a test can persist fixture
 * collections without touching production content. That re-rooting is exactly
 * what a test about the real corpus cannot have: a throwaway content directory
 * holds no entries, so any assertion about how many of them rebuild has nothing
 * to count.
 *
 * What is left is the half that matters for cache-state tests. Swapping only
 * `statamic.stache.cache_store` for a per-test array store means the Stache
 * starts COLD against real content, which is the condition a first-rebuild test
 * needs — and it reaches that condition without `Cache::flush()`, which would
 * empty the worker's shared store and take the seeded index with it (UAMS-Web/uams-statamic-archive#1994).
 *
 * Prefer {@see StacheIsolatedTestCase} whenever a test writes content. Reach for
 * this one only to observe how the real corpus behaves against an empty cache.
 *
 * The trait carries the rationale for the store's configuration, including why
 * it serializes; do not restate those options inline in a test.
 */
abstract class StacheCacheOnlyIsolatedTestCase extends TestCase
{
    use IsolatesStacheCache;

    protected function setUp(): void
    {
        parent::setUp();

        // After the boot, so the once-per-process seed still populates the real
        // shared store; the swap then leaves this test's Stache cold.
        $this->isolateStacheCache();
    }
}
