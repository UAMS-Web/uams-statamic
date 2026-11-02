<?php

declare(strict_types=1);

namespace Tests\Support;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A rule that fails with whatever message the test set.
 */
final class FailMessageProbeRule implements ValidationRule
{
    public static mixed $message = null;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        /** @var Closure(mixed): void $failWithAnything */
        $failWithAnything = $fail;

        $failWithAnything(self::$message);
    }
}
