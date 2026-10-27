<?php

declare(strict_types=1);

if (! function_exists('runningUnderMutate')) {
    /**
     * Whether this process was started with Pest's `--mutate` flag.
     *
     * Several mutation-hardened suites pad the plugin's per-mutant child timeout by
     * sleeping once in their first test when this is true (#255). Each copy read
     * `implode(' ', $_SERVER['argv'] ?? [])` directly, and `$_SERVER['argv']` is
     * `mixed` to PHPStan, so every copy failed the `phpstan` job. Reading it here
     * keeps the copies identical in behavior: any argument containing `--mutate`
     * counts, and a process with no readable argv does not.
     *
     * ```php
     * if (runningUnderMutate()) {
     *     usleep(20_000_000);
     * }
     * ```
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
