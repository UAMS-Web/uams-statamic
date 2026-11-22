<?php

declare(strict_types=1);
use Tests\Support\MutatePadding;

if (! function_exists('runningUnderMutate')) {
    /**
     * Whether this process was started with Pest's `--mutate` flag.
     *
     * Several mutation-hardened suites pad the plugin's per-mutant child timeout by
     * sleeping once when this is true (#255), through `padMutateChildTimeout()`. Each copy read
     * `implode(' ', $_SERVER['argv'] ?? [])` directly, and `$_SERVER['argv']` is
     * `mixed` to PHPStan, so every copy failed the `phpstan` job. Reading it here
     * keeps the copies identical in behavior: any argument containing `--mutate`
     * counts, and a process with no readable argv does not.
     *
     * @return bool True when an argument of this process contains `--mutate`.
     */
    function runningUnderMutate(): bool
    {
        $argv = $_SERVER['argv'] ?? [];

        if (! \is_array($argv)) {
            return false;
        }

        $strings = array_filter($argv, \is_string(...));

        return str_contains(implode(' ', $strings), '--mutate');
    }
}

if (! function_exists('padMutateChildTimeout')) {
    /**
     * Sleep once per test file per process under `--mutate`, to pad the plugin's
     * per-mutant child timeout (#255, #1051).
     *
     * Call it from a `beforeEach`. A `static` guard inside that closure does not hold,
     * because Pest binds a fresh copy of the closure to every test, so the guard lives
     * in `Tests\Support\MutatePadding`, keyed by the file this is called from.
     *
     * ```php
     * beforeEach(function (): void {
     *     padMutateChildTimeout();
     * });
     * ```
     *
     * @param  int  $microseconds  How long to sleep, 20 seconds unless a suite needs otherwise.
     * @return bool True only when this call slept.
     */
    function padMutateChildTimeout(int $microseconds = 20_000_000): bool
    {
        $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]['file'] ?? '';

        return MutatePadding::shared()->pad($caller, $microseconds);
    }
}
