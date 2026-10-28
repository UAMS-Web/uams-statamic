<?php

declare(strict_types=1);

namespace Tests\Support\Doubles;

use App\Fieldtypes\Traits\InputMaskValueStorage;

/**
 * Exposes the InputMaskValueStorage trait's mask check and storage decision.
 */
final class InputMaskValueStorageHarness
{
    use InputMaskValueStorage;

    /** @param  array<string, mixed>  $config */
    public function enabled(array $config): bool
    {
        return $this->isInputMaskEnabled($config);
    }

    /** @param  array<string, mixed>  $config */
    public function store(mixed $value, array $config): mixed
    {
        return $this->processInputMaskValue($value, $config);
    }
}
