<?php

declare(strict_types=1);

namespace App\Modifiers;

use Statamic\Modifiers\Modifier;

/**
 * Antlers modifier: true when the value is strictly a boolean (`is_bool`).
 *
 * Usage: `{{ value | is_boolean }}`, `{{ value | is_bool }}`
 */
class IsBoolean extends Modifier
{
    /**
     * @return array<int, string>
     */
    #[\Override]
    public static function aliases(): array
    {
        return [
            'is_bool',
        ];
    }

    /**
     * @param  array<int|string, mixed>  $params
     * @param  array<int|string, mixed>  $context
     */
    public function index(mixed $value, array $params, array $context): bool
    {
        return \is_bool($value);
    }
}
