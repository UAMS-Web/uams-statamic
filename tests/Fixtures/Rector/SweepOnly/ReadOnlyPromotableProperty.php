<?php

declare(strict_types=1);

namespace Tests\Fixtures\Rector\SweepOnly;

/**
 * Fixture for the #1720 profile split — trips `ReadOnlyPropertyRector` on its own.
 *
 * The class is deliberately **not** `final`, so `ReadOnlyClassRector` cannot fire
 * and subsume the property-level rule the way it does on
 * {@see ReadOnlyPromotable}. The property is already promoted so the gating
 * profile's `ClassPropertyAssignToConstructorPromotionRector` has nothing to say
 * about it; it is written only at construction, so Rector wants to mark it
 * `readonly` — a semantic change the gating profile must not require.
 *
 * Do not "fix" this file — the writable promoted property is the assertion.
 */
class ReadOnlyPromotableProperty
{
    public function __construct(
        private string $container,
    ) {}

    public function container(): string
    {
        return $this->container;
    }
}
