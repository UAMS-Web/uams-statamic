<?php

declare(strict_types=1);

namespace Tests\Fixtures\Rector\SweepOnly;

/**
 * Fixture for the #1720 profile split — trips `ReadOnlyClassRector` (and, via the
 * promoted property, `ReadOnlyPropertyRector`).
 *
 * Every property is `readonly` and the class is `final`, so Rector wants to hoist
 * `readonly` onto the class declaration. That promotion is a semantic change, so
 * the gating profile must leave this file alone; the sweep profile must offer it.
 *
 * Do not "fix" this file — the un-promoted shape is the assertion.
 */
final class ReadOnlyPromotable
{
    public function __construct(
        private readonly string $handle,
    ) {}

    public function handle(): string
    {
        return $this->handle;
    }
}
