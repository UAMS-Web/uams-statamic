<?php

declare(strict_types=1);

use Illuminate\Testing\PendingCommand;

use function Pest\Laravel\artisan;

if (! function_exists('pendingArtisan')) {
    /**
     * Invoke an Artisan command and resolve `artisan()`'s union to a `PendingCommand`.
     *
     * `Pest\Laravel\artisan()` is typed `PendingCommand|int`: it hands back a
     * `PendingCommand` while console-output mocking is on (the default) and a bare
     * exit code once it is off. Every caller here needs the object, so the union
     * has to be narrowed somewhere.
     *
     * Five console suites each did that with `assert($pending instanceof
     * PendingCommand)`, which is the wrong instrument twice over (UAMS-Web/uams-statamic-archive#1733):
     *
     * 1. **It can be compiled out.** Under `zend.assertions=-1` the call vanishes,
     *    so the guarantee it encodes is configuration-dependent — and what follows
     *    is a `->expectsOutput()` on an `int`.
     * 2. **It says nothing when it trips.** A bare `assert()` failure names no
     *    cause, so the real problem (output mocking is off, and none of the fluent
     *    assertions below can run) has to be rediscovered from the stack.
     *
     * Throwing on the `int` branch narrows the type by control flow instead, so
     * PHPStan verifies the return type rather than taking an assertion's word for
     * it, and a regression reports what actually went wrong.
     *
     * The returned command MUST be chained on directly and never stored in a
     * caller-held variable: a `PendingCommand` runs the command in its destructor,
     * so keeping a reference alive defers the run past the assertions that follow.
     * Returning it unstored lets it destruct at the end of the caller's statement.
     *
     * @param  string  $command  The Artisan command signature to invoke.
     * @param  array<string, mixed>  $parameters  Arguments and options for the command.
     * @return PendingCommand The pending command, for fluent assertions.
     */
    function pendingArtisan(string $command, array $parameters = []): PendingCommand
    {
        $pending = artisan($command, $parameters);

        if (! $pending instanceof PendingCommand) {
            throw new RuntimeException(sprintf(
                'artisan("%s") returned exit code %d instead of a PendingCommand, so console-output mocking is off and the fluent assertions cannot run.',
                $command,
                $pending,
            ));
        }

        return $pending;
    }
}
