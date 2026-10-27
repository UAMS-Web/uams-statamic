<?php

declare(strict_types=1);

namespace App\Fieldtypes;

use Statamic\Fields\Fieldtype;

/**
 * Signature fieldtype
 */
class Signature extends Fieldtype
{
    /**
     * The display title of the fieldtype in the Control Panel.
     *
     * @var string The display title of the fieldtype in the Control Panel.
     */
    protected static $title = 'Signature';

    /**
     * The categories this fieldtype belongs to in the Statamic fieldtype selector.
     *
     * @var array<string> Categories this fieldtype belongs to in the Statamic fieldtype selector.
     */
    protected $categories = [
        // ''
    ];

    /**
     * Indicates whether the fieldtype is selectable in form blueprints.
     *
     * @var bool Indicates whether the fieldtype is selectable in form blueprints
     */
    protected $selectableInForms = true;

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
    protected $component = 'signature';

    /**
     * The icon for this fieldtype.
     *
     * @return string The icon name
     */
    #[\Override]
    public function icon(): string
    {
        return 'text-formatting-input-signature';
    }

    /**
     * Process the value before saving to storage.
     *
     * Ensures that empty strings are converted to null so they are properly
     * filtered out by Statamic, while preserving valid base64 data URL
     * strings.
     *
     * @param  mixed  $value  The value to process
     * @return mixed The processed value (null for empty, string for valid data)
     */
    #[\Override]
    public function process(mixed $value): mixed
    {
        // ---
        // Convert empty strings to null (Statamic filters out null values)
        // ---

        // Check if value is empty string or null
        if (
            $value === ''
            || $value === null
        ) {
            // Return null for empty values
            return null;
        }

        // Return the value as-is if it's a valid string
        return $value;
    }

    /**
     * Pre-process the value before it is displayed in the control panel.
     *
     * Ensures stored values are properly formatted for the Vue component.
     * The component can handle both string (base64 data URL) and object
     * formats.
     *
     * @param  mixed  $value  The value to pre-process
     * @return mixed The pre-processed value (string or null)
     */
    #[\Override]
    public function preProcess(mixed $value): mixed
    {
        // ---
        // Return null for empty values
        // ---

        // Check if value is empty string or null
        if (
            $value === ''
            || $value === null
        ) {
            // Return null for empty values
            return null;
        }

        // Return the value as-is (should be a base64 data URL string)
        return $value;
    }

    /**
     * Defines the configuration fields available for this fieldtype in the blueprint.
     *
     * @return list<array<string, mixed>> The configuration field items
     */
    #[\Override]
    protected function configFieldItems(): array
    {
        return [
            // [
            //     'display' => __('Signature Options'),
            //     'fields' => [
            //         'box_width' => [
            //             'display' => 'Signature Area Width',
            //             'instructions' => 'Enter the width for the signature area in pixels.',
            //             'type' => 'integer',
            //             'min' => 1,
            //             'step' => 1,
            //             'append' => 'px',
            //             'default' => 300,
            //         ],
            //         'box_height' => [
            //             'display' => 'Signature Area Height',
            //             'instructions' => 'Enter the height for the signature area in pixels.',
            //             'type' => 'integer',
            //             'min' => 1,
            //             'step' => 1,
            //             'append' => 'px',
            //             'default' => 180,
            //         ],
            //         'background_color' => [
            //             'display' => 'Background Color',
            //             'instructions' => 'Select the color to be used for the background of the signature area.',
            //             'type' => 'color',
            //             'default' => '#ffffff',
            //         ],
            //         'pen_color' => [
            //             'display' => 'Pen Color',
            //             'instructions' => 'Select the color of the pen to be used for the signature.',
            //             'type' => 'color',
            //             'default' => '#0000ff',
            //         ],
            //         'pen_size' => [
            //             'display' => 'Pen Size',
            //             'instructions' => 'Select the width of the pen to be used for the signature in pixels.',
            //             'type' => 'select',
            //             'options' => [
            //                 '1' => 'Small (1px)',
            //                 '2' => 'Medium (2px)',
            //                 '3' => 'Large (3px)',
            //             ],
            //             'default' => '2',
            //         ],
            //         'border_width' => [
            //             'display' => 'Border Width',
            //             'instructions' => 'Select the border width to be used around the signature area.',
            //             'type' => 'select',
            //             'options' => [
            //                 '0' => 'None (0)',
            //                 '1px' => 'Small (1px)',
            //                 '2px' => 'Medium (2px)',
            //                 '4px' => 'Large (4px)',
            //             ],
            //             'default' => '2px',
            //         ],
            //         'border_style' => [
            //             'display' => 'Border Style',
            //             'instructions' => 'Select the border style to be used around the signature area.',
            //             'type' => 'select',
            //             'options' => [
            //                 'solid' => 'Solid',
            //                 'dotted' => 'Dotted',
            //                 'dashed' => 'Dashed',
            //                 'groove' => 'Groove',
            //                 'ridge' => 'Ridge',
            //                 'inset' => 'Inset',
            //                 'outset' => 'Outset',
            //                 'double' => 'Double',
            //             ],
            //             'default' => 'dashed',
            //             'if' => [
            //                 'border_width' => 'not none',
            //             ],
            //         ],
            //         'border_color' => [
            //             'display' => 'Border Color',
            //             'instructions' => 'Select the color to be used for the border around the signature area.',
            //             'type' => 'color',
            //             'default' => '#dddddd',
            //             'if' => [
            //                 'border_width' => 'not 0',
            //             ],
            //         ],
            //     ],
            // ]
        ];
    }
}
