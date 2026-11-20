<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Support\ContentMirrorBandLock;
use App\Support\ContentRoot;
use RuntimeException;

/**
 * Refuses a test application whose write roots resolve into the live tree (#1160).
 *
 * The harness writes through two roots before any test runs: the file cache store,
 * which a Stache seed empties and refills, and the content root, beside which
 * {@see ContentMirrorBandLock} creates its lock. Under `APP_ENV=testing`
 * both resolve under `storage/framework/testing`, through {@see ContentRoot}.
 * A `--mutate` run on that class hands the harness the live roots instead, and the
 * seed then deleted the tracked `storage/framework/cache/data/.gitignore` and the
 * lock landed as `content.band.lock` at the repository root. Checking the roots
 * against the testing directory here, before the first write, turns that mutant
 * into a boot failure and leaves the working copy untouched.
 */
final class TestingWriteRoots
{
    /**
     * Throw unless every root lies inside the testing storage directory.
     *
     * @param  string  $testingDirectory  `storage_path('framework/testing')`.
     * @param  array<string, string>  $roots  Each root's resolved path, keyed by what it is.
     *
     * @throws RuntimeException When a root resolves outside `$testingDirectory`.
     */
    public static function assertInside(string $testingDirectory, array $roots): void
    {
        foreach (self::outside($testingDirectory, $roots) as $name) {
            throw new RuntimeException(\sprintf(
                'The test %s resolves to %s, outside %s, so booting would write to the live tree. Run with APP_ENV=testing, and check App\Support\ContentRoot.',
                $name,
                $roots[$name],
                $testingDirectory,
            ));
        }
    }

    /**
     * The names of the roots that lie outside the testing storage directory.
     *
     * @param  string  $testingDirectory  `storage_path('framework/testing')`.
     * @param  array<string, string>  $roots  Each root's resolved path, keyed by what it is.
     * @return list<string> The offending roots' names, in the order given.
     */
    public static function outside(string $testingDirectory, array $roots): array
    {
        $prefix = self::normalize($testingDirectory).'/';

        return array_keys(array_filter(
            $roots,
            static fn (string $root): bool => ! str_starts_with(self::normalize($root).'/', $prefix),
        ));
    }

    /**
     * Use forward slashes and drop a trailing separator, so Windows paths compare.
     *
     * @param  string  $path  A filesystem path.
     */
    private static function normalize(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }
}
