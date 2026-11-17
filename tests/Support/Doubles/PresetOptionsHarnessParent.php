<?php

declare(strict_types=1);

namespace Tests\Support\Doubles;

use Statamic\Fieldtypes\Select;

/**
 * The parent a PresetOptions fieldtype extends: Statamic's Select, whose config
 * field items can be replaced through $parentItems so the trait's visibility
 * pass can be fed sections Select itself does not carry.
 */
class PresetOptionsHarnessParent extends Select
{
    /** @var ?array<array-key, mixed> */
    public ?array $parentItems = null;

    /** @return array<array-key, mixed> */
    protected function configFieldItems(): array
    {
        return $this->parentItems ?? parent::configFieldItems();
    }
}
