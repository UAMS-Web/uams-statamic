<?php

declare(strict_types=1);

namespace App\Fieldtypes\Traits;

/**
 * Trait for adding "Other" option configuration to fieldtypes.
 *
 * Provides methods to add "Other" option configuration fields to fieldtype
 * configs, allowing users to enable a custom "Other" option with a customizable
 * label.
 */
trait OtherOption
{
    /**
     * Returns the configuration fields for the "Other" option.
     *
     * @return array<string, array<string, mixed>> Configuration fields for the "Other" option
     */
    protected function otherOptionConfigField(): array
    {
        return [
            // Enable Other Option config
            'enable_other' => [
                'display' => __('Enable Other Option'),
                'instructions' => __('Allow users to select "Other" and enter a custom value.'),
                'type' => 'toggle',
                'default' => false,
            ],
            'other_label' => [
                'display' => __('Label for Other Option'),
                'instructions' => __('The label for the "Other" option.'),
                'type' => 'text',
                'default' => 'Other',
                'if' => [
                    'enable_other' => 'equals true',
                ],
            ],
            'other_placeholder' => [
                'display' => __('Placeholder for Other Input'),
                'instructions' => __('Placeholder text for the "Other" custom value input.'),
                'type' => 'text',
                'default' => 'Enter your custom value',
                'if' => [
                    'enable_other' => 'equals true',
                ],
            ],
        ];
    }
}
