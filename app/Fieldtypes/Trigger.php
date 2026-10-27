<?php

declare(strict_types=1);

namespace App\Fieldtypes;

use Statamic\Fields\Fieldtype;

/**
 * A custom Statamic fieldtype that provides a trigger button in the control panel.
 *
 * The trigger button can be configured to enable/disable based on a related
 * field's value and does not store data itself. It is used to initiate actions,
 * such as importing data from an asset into another field (e.g., `table_data`).
 */
class Trigger extends Fieldtype
{
    /**
     * The categories this fieldtype belongs to in the Statamic fieldtype selector.
     *
     * @var array<string> Categories this fieldtype belongs to in the Statamic fieldtype selector.
     */
    protected $categories = [
        'controls',
        'special',
    ];

    /**
     * Indicates whether the fieldtype is selectable in collection blueprints.
     *
     * @var bool Indicates whether the fieldtype is selectable in collection blueprints
     */
    protected $selectable = false;

    /**
     * Indicates whether the fieldtype is selectable in form blueprints.
     *
     * @var bool Indicates whether the fieldtype is selectable in form blueprints
     */
    protected $selectableInForms = false;

    /**
     * Indicates whether the fieldtype's value can be localized.
     *
     * @var bool Indicates whether the fieldtype's value can be localized
     */
    protected $localizable = false;

    /**
     * Indicates whether the fieldtype's value can be validated.
     *
     * @var bool Indicates whether the fieldtype's value can be validated
     */
    protected $validatable = false;

    /**
     * Indicates whether the fieldtype can have a default value.
     *
     * @var bool Indicates whether the fieldtype can have a default value
     */
    protected $defaultable = false;

    /**
     * The name of the Vue component to use for this fieldtype.
     *
     * @var string The name of the Vue component to use for this fieldtype
     */
    protected $component = 'trigger';

    /**
     * The icon for this fieldtype.
     *
     * @return string The icon name
     */
    #[\Override]
    public function icon(): string
    {
        return 'workflow';
    }

    /**
     * Defines the configuration fields available for this fieldtype in the blueprint.
     *
     * @return array<int, array{display: string, fields: array<string, array<string, mixed>>}> The configuration field items
     */
    #[\Override]
    protected function configFieldItems(): array
    {
        return [
            [
                // Appearance settings for the trigger button
                'display' => __('Appearance'),
                'fields' => [
                    'input_label' => [
                        'display' => __('Input Label'),
                        'instructions' => __('Set the label for the button.'),
                        'type' => 'text',
                        'default' => 'Trigger Action',
                    ],
                    'related_field' => [
                        'display' => __('Related Field'),
                        'instructions' => __('Enter the handle of the field that determines whether this trigger is enabled. The trigger will be enabled if this field has a value.'),
                        'type' => 'text',
                        'default' => '',
                    ],
                ],
            ],
        ];
    }

    /**
     * Pre-processes the field's data before it is displayed in the control panel.
     *
     * @param  mixed  $data  The field's data
     * @return null The trigger fieldtype does not store data, so always return null
     */
    #[\Override]
    public function preProcess(
        mixed $data
    ): ?string {
        return null;
    }

    /**
     * Processes the field's data before it is saved.
     *
     * @param  mixed  $data  The field's data
     * @return null The trigger fieldtype does not store data, so always return null
     */
    #[\Override]
    public function process(
        mixed $data
    ): ?string {
        return null;
    }
}
