<?php

declare(strict_types=1);

namespace Tests\Support;

use Tests\TestCase;

/**
 * Base test case for a test that persists a **collection tree** while still
 * reading the shared, real content for everything else (UAMS-Web/uams-statamic-archive#1873).
 *
 * **What this base is for changed in UAMS-Web/uams-statamic-archive#1916, and it is worth being precise about.**
 * It was written because the collection-trees store pointed at the live `content/`
 * submodule, so a test that built a structure wrote
 * `content/trees/collections/<site>/<handle>.yaml` into tracked source and removed
 * it in an `afterEach` that only runs when the PHP process survives to it. A band
 * killed at its bound stranded the file, the submodule read dirty for every later
 * run, and the stray was boot-visible input: `statamic:stache:warm` indexes it and
 * memoizes a collection-structure entry against the real collection, the state
 * that produced UAMS-Web/uams-statamic-archive#1845.
 *
 * UAMS-Web/uams-statamic-archive#1916 made that half structural — under test the store roots at a disposable
 * mirror of `content/`, so no tree write can reach the submodule whether or not a
 * test uses this base. What remains is the other half, which this base is still
 * the only thing providing: the mirror is **shared by every paratest worker**, so
 * a test that authors a tree and asserts against it needs a root of its own, or a
 * sibling worker's fixture is in its results. That is why this base survives
 * rather than being retired with the ticket that motivated it.
 *
 * The write is not always authored by the test. `Entry::delete()` on an entry in a
 * *structured* collection removes it from the tree and saves the tree, so any test
 * that creates and tears down such an entry writes a tree too.
 *
 * ## The invariant this rests on
 *
 * A re-rooted store has exactly two safe states: **directory and cached view both
 * real** (no isolation), or **both throwaway** (isolated). Every mixed state is
 * destructive, and not merely misdirected:
 *
 * ```php
 * // Statamic\Data\ExistsAsFile::writeFile()
 * $path = $path ?? $this->buildPath();     // recomputed LIVE from the store directory
 * $initial = $this->initialPath();          // where the item was READ from
 * if ($initial && $path !== $initial) {
 *     File::delete($this->initialPath());   // the committed file is REMOVED
 * }
 * ```
 *
 * So a tree loaded from the real directory and then saved under a re-rooted store
 * is *moved*, not copied — `File::delete()` takes the committed YAML with it. This
 * is not hypothetical: an earlier iteration of this ticket, which re-rooted the
 * directory while leaving the shared path index real, deleted
 * `content/trees/collections/default/recognition_lists.yaml` and
 * `content/trees/collections/science_reports/post_categories.yaml` during a run.
 *
 * `Entry::path()` has an `initialPath ?? buildPath()` fallback that looks like it
 * prevents this. It does not: `writeFile()` calls `buildPath()` directly and never
 * consults `path()`. `CollectionTree`, `Term`, `GlobalSet`, `Variables`,
 * `AssetContainer` and `Nav` have no such fallback at all.
 *
 * {@see IsolatesCollectionTrees::isolateCollectionTrees()}, called from {@see setUp()},
 * therefore moves the directory **and** drops the cached view, so
 * nothing this test resolves can carry an `initialPath` inside the shared root.
 *
 * ## What that costs, and why it is affordable here
 *
 * With the path index dropped, the store sees an empty directory, so real
 * committed trees are invisible for the duration of the test. That is exactly why
 * this base is scoped to *collection trees* rather than to content generally: the
 * tests on it **author the tree they assert against**. `CollectionStructure::in()`
 * makes a fresh tree when the store has none (`! $tree && $this->existsIn($site)`),
 * so an absent tree is a blank one rather than a null, and the implicit save from
 * `Entry::delete()` lands in the throwaway directory.
 *
 * Everything else — collections, entries, taxonomies, terms, globals — is
 * untouched, so the real content these tests exist to exercise still resolves.
 *
 * The sibling {@see StacheIsolatedTestCase} isolates every store *and* the Stache
 * cache; use it when a test needs no real content at all. This base is the narrow
 * case: real content, isolated trees.
 */
abstract class CollectionTreeIsolatedTestCase extends TestCase
{
    use IsolatesCollectionTrees;

    protected function setUp(): void
    {
        parent::setUp();

        $this->isolateCollectionTrees();
    }

    protected function tearDown(): void
    {
        $this->restoreCollectionTrees();

        parent::tearDown();
    }
}
