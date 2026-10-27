<?php

declare(strict_types=1);

use PHPUnit\Framework\SkippedWithMessageException;
use Tests\Support\FormsSubmodule;

/**
 * Regression guard for the forms-submodule skip (UAMS-Web/uams-statamic-archive#2818).
 *
 * Both branches are driven from throwaway fixtures rather than this checkout's
 * `resources/forms`, so the file passes the same way with or without the
 * private submodule. Fixtures live under a per-run `sys_get_temp_dir()` path, so
 * the file is parallel-safe.
 *
 * @command  php artisan test --compact tests/Unit/FormsSubmoduleGuardTest.php
 */

/**
 * Create a throwaway forms directory, optionally holding a form configuration.
 *
 * @param  bool  $populated  Whether to write a form configuration into it
 * @return string The fixture directory path
 */
function makeFormsFixture(bool $populated): string
{
    $dir = sys_get_temp_dir().'/forms-submodule-'.bin2hex(random_bytes(6));
    mkdir($dir, 0o777, true);

    // Write one form configuration when the fixture should look initialized
    if ($populated) {
        file_put_contents($dir.'/probe.yaml', "title: Probe\n");
    }

    return $dir;
}

/**
 * Remove a fixture made by `makeFormsFixture()`.
 *
 * @param  string  $dir  The fixture directory path
 */
function removeFormsFixture(string $dir): void
{
    // Remove the configuration, then the directory
    foreach (glob($dir.'/*') ?: [] as $file) {
        unlink($file);
    }

    rmdir($dir);
}

it('reports an empty submodule mount point as uninitialized', function (): void {
    $dir = makeFormsFixture(populated: false);

    expect(FormsSubmodule::isInitialized($dir))->toBeFalse();

    removeFormsFixture($dir);
});

it('reports an absent directory as uninitialized', function (): void {
    expect(FormsSubmodule::isInitialized(sys_get_temp_dir().'/forms-submodule-absent-'.bin2hex(random_bytes(6))))
        ->toBeFalse();
});

it('reports a directory holding a form configuration as initialized', function (): void {
    $dir = makeFormsFixture(populated: true);

    expect(FormsSubmodule::isInitialized($dir))->toBeTrue();

    removeFormsFixture($dir);
});

it('skips when the submodule is absent and returns when it is present', function (): void {
    $empty = makeFormsFixture(populated: false);
    $populated = makeFormsFixture(populated: true);

    expect(fn () => FormsSubmodule::skipUnlessInitialized($empty))
        ->toThrow(SkippedWithMessageException::class, 'Requires the resources/forms submodule');

    FormsSubmodule::skipUnlessInitialized($populated);

    removeFormsFixture($empty);
    removeFormsFixture($populated);
});
