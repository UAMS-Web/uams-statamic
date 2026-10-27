<?php

declare(strict_types=1);

use Tests\TestCase;

// cspell:ignore stty

/**
 * Guards `patches/symfony-console-terminal-input-windows-null-device.patch` (#230).
 *
 * The defect: `TerminalInputHelper` restores the terminal with
 * `stty <state> 2>/dev/null || stty sane`. `shell_exec()` runs that through `cmd.exe` on
 * Windows, which has no `/dev/null`, so every numbered prompt (a `ChoiceQuestion` answered
 * at a terminal) prints `The system cannot find the path specified.` after the answer. The
 * patch picks `NUL` on Windows and `/dev/null` elsewhere, the same choice `Terminal.php`
 * already makes for its own `stty` probe.
 *
 * This asserts the patched vendor source rather than driving the helper: the defect only
 * shows with a real terminal attached, which a test process does not have. The prompt itself
 * was checked by hand before and after the patch; the pull request records the run.
 * `composer install` does not re-patch an already-installed package, so a reused `vendor/`
 * keeps the unpatched code while the install reports success, and this test is what notices.
 *
 * Remove this file with the patch; see **`symfony/console` Windows null device for the stty
 * restore** in `patches/README.md` for the retirement condition (#232).
 *
 * @command  php artisan test --compact tests/Feature/Config/SymfonyConsoleWindowsNullDevicePatchTest.php
 */
uses(TestCase::class);

/**
 * The source of the vendored `TerminalInputHelper`.
 */
function symfonyConsoleNullDevicePatchSource(): string
{
    $path = base_path('vendor/symfony/console/Helper/TerminalInputHelper.php');

    expect($path)->toBeFile();

    $source = file_get_contents($path);

    expect($source)->toBeString();

    return (string) $source;
}

it('no longer sends the stty restore error output to /dev/null on every platform', function (): void {
    expect(substr_count(symfonyConsoleNullDevicePatchSource(), "' 2>/dev/null || stty sane'"))->toBe(0);
});

it('sends the stty restore error output to NUL on Windows at both restore sites', function (): void {
    // finish() and the signal handler each restore the terminal, so both carry the fix.
    $patched = "2>'.('\\\\' === \\DIRECTORY_SEPARATOR ? 'NUL' : '/dev/null').' || stty sane'";

    expect(substr_count(symfonyConsoleNullDevicePatchSource(), $patched))->toBe(2);
});
