<?php

declare(strict_types=1);

namespace App\Modifiers;

use Statamic\Modifiers\Modifier;

/**
 * Antlers modifier: true when the value is strictly a float (`is_float`).
 *
 * Usage: `{{ value | is_float }}`, `{{ value | is_decimal }}`
 */
class IsFloat extends Modifier
{
    /**
     * @return array<int, string>
     */
    #[\Override]
    public static function aliases(): array
    {
        return [
            'is_decimal',
        ];
    }

    /**
     * @param  array<int|string, mixed>  $params
     * @param  array<int|string, mixed>  $context
     */
    public function index(mixed $value, array $params, array $context): bool
    {
        return \is_float($value);
    }
}
