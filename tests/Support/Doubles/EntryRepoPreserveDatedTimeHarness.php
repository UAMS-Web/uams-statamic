<?php

declare(strict_types=1);

namespace Tests\Support\Doubles;

use Statamic\Entries\Entry as StatamicEntry;

/**
 * Test-local contract exposing the protected parent-row re-wrap so analyzers
 * know the harness instance declares `reinstantiateForTest()`.
 */
interface EntryRepoPreserveDatedTimeHarness
{
    /**
     * Expose parent-row re-wrap for tests.
     *
     * @param  ?StatamicEntry  $entry  Synthetic row from `Entry::make()`
     * @return ?StatamicEntry Re-bound entry instance
     */
    public function reinstantiateForTest(?StatamicEntry $entry): ?StatamicEntry;
}
