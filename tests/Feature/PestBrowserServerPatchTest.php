<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class);

/**
 * Guards `patches/pest-plugin-browser-server-no-shell.patch`.
 *
 * Remove this file when the patch is removed; see **`pest-plugin-browser`
 * shell-free Playwright server start** in `patches/README.md` for the retirement
 * condition.
 *
 * Unlike the sibling `Client.php` and `Execution.php` guards, this one asserts
 * the patched vendor source rather than driving behavior. What the patch changes
 * is how a child process is spawned, and the defect it removes only manifests on
 * Linux with piped output — neither is reachable from a unit test, and spawning a
 * real Playwright server to assert its process tree would make the default gate
 * depend on Node and a browser binary. The behavioral check lives in the browser
 * suite instead: `vendor/bin/pest tests/Browser` must still pass, and no server
 * process may survive it.
 *
 * This asserts the contents of a patched vendor file; there is no backing App\
 * PHP class, so there is no mutable PHP surface to mutation-test.
 *
 * @command  php artisan test --compact tests/Feature/PestBrowserServerPatchTest.php
 */
it('starts the Playwright server from argv rather than through a shell', function (): void {
    $path = base_path('vendor/pestphp/pest-plugin-browser/src/Playwright/Servers/PlaywrightNpmServer.php');

    expect($path)->toBeFile();

    $source = file_get_contents($path);

    expect($source)->toBeString();

    // Symfony only prepends `exec` for array-form commands, so a shell string
    // leaves an `sh -c` layer that absorbs the stop signal and orphans `node`.
    expect($source)->not->toContain('SystemProcess::fromShellCommandline(sprintf(');

    // The argv form: proc_open execs the binary directly on Unix, so the server
    // is the direct child and the existing signal handling reaches it.
    expect($source)->toContain('$arguments = preg_split(');
    expect($source)->toContain('new SystemProcess($arguments === false ? [] : $arguments');
});

it('leaves the version probe on the shell path it does not need to change', function (): void {
    $path = base_path('vendor/pestphp/pest-plugin-browser/src/Playwright/Servers/PlaywrightNpmServer.php');

    $source = file_get_contents($path);

    expect($source)->toBeString();

    // The `--version` probe is a short-lived, awaited process that is never
    // signalled, so it cannot orphan anything. Keeping it on the original code
    // path bounds the patch to the one call site that actually carries the bug —
    // if this disappears, the patch has grown beyond its stated scope.
    expect($source)->toContain('SystemProcess::fromShellCommandline(');
});
