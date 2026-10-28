<?php

declare(strict_types=1);

namespace Tests\Support\Doubles;

/**
 * The parent whose configFieldItems() the ConfigSectionWrapper trait reads
 * through parent::configFieldItems(). Its config is a flat field list unless
 * $sectioned is set, in which case it is two sections.
 */
class ConfigSectionWrapperParent
{
    public static bool $sectioned = false;

    /** @return array<array-key, mixed> */
    protected function configFieldItems(): array
    {
        if (self::$sectioned) {
            return [
                ['display' => 'Appearance', 'fields' => ['placeholder' => ['type' => 'text']]],
                ['display' => 'Rules', 'fields' => ['required' => ['type' => 'toggle']]],
            ];
        }

        return [
            'placeholder' => ['type' => 'text'],
            'default' => ['type' => 'text'],
        ];
    }
}
