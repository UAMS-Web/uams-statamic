<?php

declare(strict_types=1);

namespace Tests\Support;

use Statamic\Listeners\UpdateAssetReferences;

/**
 * Runs a test's asset clean-up with Statamic's asset reference sweep switched off (UAMS-Web/uams-statamic-archive#2500).
 *
 * `UpdateAssetReferences` listens for `AssetDeleted` and walks every entry, term and
 * global to null out references to the deleted path. A test deleting its throwaway
 * assets in `afterEach` pays that walk on every delete: UAMS-Web/uams-statamic-archive#2504 measured it at about
 * 6.4s of each ~8s test in the `serial` band. The asset tests assert nothing about
 * references, so the walk buys them nothing.
 *
 * Statamic's own `Subscriber::withoutListeners()` re-enables the listeners after the
 * callback, but not when it throws, which would leave the sweep off for every later
 * test in the process. This re-enables in a `finally`. `enable()` re-subscribes
 * through `subscribe()`, so it still honors `statamic.system.update_references`.
 *
 * The same pairing as `UAMSWeb\Aether\WordPressImporter\...\AssetReferenceUpdates`, kept
 * here so the tests do not depend on the importer package for it.
 */
final class AssetReferenceSweep
{
    /**
     * Run clean-up work with the asset reference sweep switched off.
     *
     * @param  callable(): void  $work  The deletes to run without the sweep.
     */
    public static function withoutSweep(callable $work): void
    {
        // Unbind the sweep's listeners for the duration of the work
        UpdateAssetReferences::disable();

        try {
            // Run the caller's deletes
            $work();
        } finally {
            // Bind them again even when a delete throws, so no later test runs without them
            UpdateAssetReferences::enable();
        }
    }
}
