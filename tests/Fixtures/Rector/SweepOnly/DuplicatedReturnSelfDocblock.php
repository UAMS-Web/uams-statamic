<?php

declare(strict_types=1);

namespace Tests\Fixtures\Rector\SweepOnly;

/**
 * Fixture for the #1720 profile split — trips
 * `RemoveDuplicatedReturnSelfDocblockRector`.
 *
 * The `@return $this` tag restates the native `: static` return type, which
 * Rector reads as redundant and wants to delete. The `php-documentation` skill
 * wants the tag kept, so the gating profile must leave this file alone; the sweep
 * profile must offer the removal.
 *
 * Do not "fix" this file — the restated tag is the assertion.
 */
final class DuplicatedReturnSelfDocblock
{
    private string $note = '';

    /**
     * Attach a note and return the same instance for chaining.
     *
     * @return $this
     */
    public function withNote(string $note): static
    {
        $this->note = $note;

        return $this;
    }

    public function note(): string
    {
        return $this->note;
    }
}
