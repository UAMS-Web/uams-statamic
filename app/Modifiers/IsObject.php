<?php

declare(strict_types=1);

namespace App\Modifiers;

use Statamic\Modifiers\Modifier;

/**
 * Antlers modifier: true when the value is strictly an object (`is_object`).
 *
 * Usage: `{{ value | is_object }}`, `{{ value | is_obj }}`
 */
class IsObject extends Modifier
{
    /**
     * @return array<int, string>
     */
    #[\Override]
    public static function aliases(): array
    {
        return [
            'is_obj',
        ];
    }

    /**
     * @param  array<int|string, mixed>  $params
     * @param  array<int|string, mixed>  $context
     */
    public function index(mixed $value, array $params, array $context): bool
    {
        return \is_object($value);
    }
}
