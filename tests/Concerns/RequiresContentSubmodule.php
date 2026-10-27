<?php

declare(strict_types=1);

namespace Tests\Concerns;

use Statamic\Contracts\Entries\Collection as CollectionContract;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Collection;
use Tests\Support\ContentSubmodule;
use Tests\TestCase;

/**
 * Lets content-dependent tests skip gracefully when the `content/` submodule is
 * absent. The canonical environments (`ci.yml` phpunit job, `local-ci.mjs`, a
 * normal local checkout) all initialize `content/`, so these guards never trip
 * there; a bare worktree or `local-ci --skip-submodule` run reports a clear
 * `SKIP` instead of the `FAIL`/`ERROR` that a missing collection would cause.
 *
 * A `null` lookup on its own is **not** the condition these guards name. Both
 * delegate to {@see ContentSubmodule}, which skips only when `content/` is
 * genuinely uninitialized and fails when it is present — so a starved Stache
 * worker or a renamed handle can no longer report as a benign skip (UAMS-Web/uams-statamic-archive#1738).
 */
trait RequiresContentSubmodule
{
    /**
     * Resolve a collection that lives in the `content/` submodule, skipping the
     * test when the submodule is absent and failing when it is present.
     *
     * @param  string  $handle  The collection handle (e.g. `people`, `posts`)
     * @return CollectionContract The resolved collection
     */
    protected function requireContentCollection(string $handle): CollectionContract
    {
        return ContentSubmodule::requireCollection(Collection::find($handle), $handle);
    }

    /**
     * Assert a real committed entry resolved, skipping the test when the
     * `content/` submodule is absent and failing when it is present.
     *
     * @param  EntryContract|null  $entry  The resolved entry, or `null`
     * @param  string  $description  What the missing entry provides
     * @return EntryContract The non-null entry
     */
    protected function requireContentEntry(?EntryContract $entry, string $description): EntryContract
    {
        return ContentSubmodule::requireEntry($entry, $description);
    }

    /**
     * Skip when no committed entry satisfies a *data* precondition the content
     * set is free not to meet — as opposed to a lookup that must resolve.
     *
     * Use this only where `null` is an editorial fact ("no published topic has a
     * published descendant article"), never where it would mean the content is
     * broken; {@see requireContentEntry()} is the guard for the latter.
     *
     * @param  EntryContract|null  $entry  The resolved entry, or `null`
     * @param  string  $description  What no committed entry satisfies
     * @return EntryContract The non-null entry
     */
    protected function skipWithoutQualifyingContentEntry(?EntryContract $entry, string $description): EntryContract
    {
        return ContentSubmodule::skipWithoutQualifyingEntry($entry, $description);
    }
}

/**
 * PHPStan analysis anchor for {@see RequiresContentSubmodule}.
 *
 * The trait is mixed into Pest test cases at runtime via
 * `uses(TestCase::class, RequiresContentSubmodule::class)`, but this project's
 * PHPStan setup does not load Pest's own extension, so PHPStan never sees that
 * `uses()` call as a trait `use` site. Without one it reports the trait as
 * `trait.unused` and skips analyzing its body at the same level 10 as `app/`
 * (UAMS-Web/uams-statamic-archive#1344). This never-instantiated, abstract class supplies the real `use` site
 * so the trait is analyzed alongside the tests that actually mix it in.
 *
 * It is abstract and lives in `tests/Concerns`, which is outside every PHPUnit
 * testsuite (`tests/Unit`, `tests/Feature` in `phpunit.xml`), so it is never
 * discovered or executed as a test.
 *
 * @see https://phpstan.org/blog/how-phpstan-analyses-traits
 *
 * @internal
 */
abstract class RequiresContentSubmoduleAnalysisAnchor extends TestCase
{
    use RequiresContentSubmodule;
}
