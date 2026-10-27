<?php

declare(strict_types=1);

namespace Tests\Fixtures\Rector\SweepOnly;

/**
 * Fixture for the #1720 profile split — trips `RemoveUselessVarTagRector`.
 *
 * The inline `@var mixed` annotation restates what the assignment already gives
 * the variable, so Rector reads it as redundant and wants to delete it. This is
 * the shape the rule actually reaches in `app/`: an inline annotation on a *local
 * variable*, pinning the type of a value that arrives untyped. Pint's docblock
 * fixers only own *property* and *method* docblocks, so — unlike a superfluous
 * `@var` on a typed property, which Pint strips itself — nothing else in the
 * toolchain removes this, and the gating profile must leave it alone.
 *
 * Do not "fix" this file — the inline annotation is the assertion.
 */
final class UselessVarTag
{
    public function label(): string
    {
        /** @var mixed $configured */
        $configured = $this->raw();

        return is_string($configured) ? $configured : '';
    }

    private function raw(): mixed
    {
        return 'fixture';
    }
}
