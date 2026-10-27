<?php

declare(strict_types=1);

namespace App\Modifiers;

use Statamic\Modifiers\Modifier;

/**
 * Antlers modifier: true when the value is null (`is_null`).
 *
 * Usage: `{{ value | is_null }}`
 */
class IsNull extends Modifier
{
    /**
     * @param  array<int|string, mixed>  $params
     * @param  array<int|string, mixed>  $context
     */
    public function index(mixed $value, array $params, array $context): bool
    {
        return \is_null($value);
    }
}
