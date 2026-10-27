<?php

declare(strict_types=1);

namespace App\Services\Redirects;

use App\Support\Redirects\MigrationRedirect;
use App\Support\Redirects\RedirectSource;
use Closure;

/**
 * Dispositions a retired WordPress permalink — one with no surviving Statamic
 * entity — by rule (UAMS-Web/uams-statamic-archive#1641).
 *
 * When migrated content is dropped rather than moved, its old URL still receives
 * inbound traffic. Rather than 404, the migration resolves each retired permalink
 * to the **nearest surviving ancestor** in its own URL path (a `301`, so the
 * visitor lands on the closest still-published context), and only declares the
 * resource permanently **`410 Gone`** when no proper ancestor survives.
 *
 * The site root (`/`) is deliberately **not** treated as a surviving ancestor:
 * funnelling every retired deep link to the home page is a soft-404 that dilutes
 * relevance signals, so a page with no surviving intermediate ancestor is
 * honestly declared gone instead.
 *
 * Dedup collapses (N old permalinks → one canonical survivor) are emitted by the
 * dedup tickets (#798 / UAMS-Web/uams-statamic-archive#1639) directly and are out of this resolver's scope — it
 * only handles permalinks with *no* survivor.
 */
class NotMigratedRedirectResolver
{
    /**
     * Resolve one retired permalink to its migration redirect.
     *
     * Walks the retired path's proper ancestors nearest-first; the first ancestor
     * the `$survives` predicate accepts becomes a `301` target. When none survive,
     * the permalink is declared `410 Gone`.
     *
     * @param  string  $retiredPath  The retired WordPress path (canonicalized here)
     * @param  Closure(string): bool  $survives  Whether a candidate ancestor path still resolves
     * @return MigrationRedirect The 301-to-ancestor or 410 disposition
     */
    public function resolve(string $retiredPath, Closure $survives): MigrationRedirect
    {
        // Canonicalize once so ancestor derivation and the emitted source agree.
        $source = RedirectSource::normalize($retiredPath);

        // Try each proper ancestor from nearest to furthest; the first survivor wins.
        foreach ($this->ancestorsOf($source) as $ancestor) {
            if ($survives($ancestor)) {
                return MigrationRedirect::moved(
                    $source,
                    $ancestor,
                    'Retired in migration; 301 to nearest surviving ancestor.',
                );
            }
        }

        // No surviving ancestor: the resource is permanently gone.
        return MigrationRedirect::gone(
            $source,
            'Retired in migration; no surviving ancestor.',
        );
    }

    /**
     * Enumerate a path's proper ancestors, nearest first, excluding the site root.
     *
     * `/a/b/c` yields `['/a/b', '/a']` — the root (`/`) and the path itself are
     * omitted, so a lone top-level path (`/a`) has no ancestors and resolves to
     * `410` rather than a home-page soft-404.
     *
     * @param  string  $path  A canonical path (single leading slash, no trailing slash)
     * @return list<string> The proper ancestor paths, nearest first
     */
    public function ancestorsOf(string $path): array
    {
        // Split the canonical path into its non-empty segments.
        $segments = array_filter(explode('/', $path), static fn (string $s): bool => $s !== '');

        // Build each proper-prefix ancestor, stopping before the final segment — that
        // prefix is the path itself, not an ancestor. A path of one segment or fewer
        // slices to nothing, so it yields no ancestors (and resolves to a 410).
        $ancestors = [];
        $prefix = '';

        foreach (\array_slice($segments, 0, -1) as $segment) {
            $prefix .= '/'.$segment;
            $ancestors[] = $prefix;
        }

        // Reverse so the nearest ancestor is first.
        return array_reverse($ancestors);
    }
}
