<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class);

/**
 * Guards `patches/statamic-updates-overview-badge.patch` on `statamic/cms`.
 *
 * Remove this file when the patch is removed; see **When removing this patch**
 * in `patches/README.md` (CP updates nav badge section).
 *
 * This asserts the contents of the patched `statamic/cms` vendor file; there is
 * no backing App\ PHP class, so there is no mutable PHP surface to mutation-test.
 */
it('includes the available updates count filter in the patched vendor file', function () {
    $path = base_path('vendor/statamic/cms/src/Updater/UpdatesOverview.php');

    expect($path)->toBeFile();

    $source = file_get_contents($path);

    expect($source)->toBeString();
    expect($source)->toContain('availableUpdatesCount() > 0');
});
