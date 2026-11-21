<?php

declare(strict_types=1);

namespace Tests\Support;

use Closure;

/**
 * Pads Pest's per-mutant child timeout by sleeping once per test file per process
 * under `--mutate` (#1051).
 *
 * The plugin sizes each mutant child's timeout from the initial run's duration, so a
 * suite whose children boot cold pads that run with one sleep. Each file used to
 * guard its sleep with a `static` inside its `beforeEach` closure, but Pest binds a
 * fresh copy of that closure to every test and each copy starts with its own
 * statics, so the guard never held: a 59-test file slept 59 times. The guard here
 * lives on an object that outlives the tests, keyed by the calling file.
 */
final class MutatePadding
{
    /**
     * The instance `padMutateChildTimeout()` uses for the whole process.
     */
    private static ?self $shared = null;

    /**
     * The keys (test files) that have already slept in this process.
     *
     * @var array<string, true>
     */
    private array $padded = [];

    /**
     * @param  Closure(): bool  $underMutate  Whether this process runs under `--mutate`.
     * @param  Closure(int): void  $sleep  Sleeps for the given number of microseconds.
     */
    public function __construct(
        private readonly Closure $underMutate,
        private readonly Closure $sleep,
    ) {}

    /**
     * The process-wide instance, reading the real `--mutate` flag and sleeping for real.
     */
    public static function shared(): self
    {
        return self::$shared ??= new self(
            runningUnderMutate(...),
            static function (int $microseconds): void {
                usleep($microseconds);
            },
        );
    }

    /**
     * Sleep for the given time the first time a key asks under `--mutate`, and never again.
     *
     * @param  string  $key  What the padding is once per, normally the calling test file.
     * @param  int  $microseconds  How long to sleep.
     * @return bool True only when this call slept.
     */
    public function pad(string $key, int $microseconds): bool
    {
        if (isset($this->padded[$key]) || ! ($this->underMutate)()) {
            return false;
        }

        $this->padded[$key] = true;
        ($this->sleep)($microseconds);

        return true;
    }
}
