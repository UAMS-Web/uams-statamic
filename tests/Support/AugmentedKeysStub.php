<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Minimal parent stub exposing `keys()` so augmented-asset concern traits can
 * be unit-tested without booting Statamic.
 */
class AugmentedKeysStub
{
    public function __construct(private readonly mixed $stubParentKeys = []) {}

    public function keys(): mixed
    {
        return $this->stubParentKeys;
    }
}
