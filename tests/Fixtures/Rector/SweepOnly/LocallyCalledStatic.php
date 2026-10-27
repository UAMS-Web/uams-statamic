<?php

declare(strict_types=1);

namespace Tests\Fixtures\Rector\SweepOnly;

/**
 * Fixture for the #1720 profile split — trips
 * `LocallyCalledStaticMethodToNonStaticRector`.
 *
 * `normalize()` is static and every call Rector can see is a local `self::` call,
 * so it wants to demote the method to an instance method. That is unsound at this
 * config's `app/`-only path scope — a caller in `tests/`, an Antlers template, or
 * `vendor/uams-web/*` is invisible to it — so the gating profile must leave this
 * file alone; the sweep profile must offer the demotion.
 *
 * Do not "fix" this file — the static method is the assertion.
 */
final class LocallyCalledStatic
{
    public function present(string $value): string
    {
        return self::normalize($value);
    }

    private static function normalize(string $value): string
    {
        return trim($value);
    }
}
