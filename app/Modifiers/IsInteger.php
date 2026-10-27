<?php

declare(strict_types=1);

namespace App\Modifiers;

use Statamic\Modifiers\Modifier;

/**
 * Antlers modifier: true when the value is strictly an integer (`is_int`).
 *
 * Usage: `{{ value | is_integer }}`, `{{ value | is_int }}`
 */
class IsInteger extends Modifier
{
    /**
     * @return array<int, string>
     */
    #[\Override]
    public static function aliases(): array
    {
        return [
            'is_int',
        ];
    }

    /**
     * @param  array<int|string, mixed>  $params
     * @param  array<int|string, mixed>  $context
     */
    public function index(mixed $value, array $params, array $context): bool
    {
        return \is_int($value);
    }
}
