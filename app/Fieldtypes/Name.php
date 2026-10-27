<?php

declare(strict_types=1);

namespace App\Fieldtypes;

use App\Fieldtypes\Traits\HasSubfields;
use Statamic\Fields\Fieldtype;

/**
 * Name Fieldtype
 *
 * Custom fieldtype; not a fork of a Statamic component. Provides a structured
 * name input using the HasSubfields trait.
 */
class Name extends Fieldtype
{
    /**
     * Use HasSubfields trait to provide structured name input fields.
     */
    use HasSubfields;

    /**
     * Indicates whether the fieldtype is selectable in form blueprints.
     *
     * @var bool Indicates whether the fieldtype is selectable in form blueprints
     */
    protected $selectableInForms = true;

    /**
     * The name of the Vue component to use for this fieldtype.
     *
     * @var string The name of the Vue component to use for this fieldtype
     */
    protected $component = 'name';

    /**
     * The icon for this fieldtype.
     *
     * @return string The icon name
     */
    #[\Override]
    public function icon(): string
    {
        return 'fieldtype-users';
    }

    /**
     * Define the subfields for the Name fieldtype.
     *
     * @return list<array{handle: string, display: string, type: string, inclusion_default: bool, required_default: bool, label_default: string, placeholder_default: string, default_type: string, default_value: string, width: int, autocomplete: string}> The subfield definitions
     */
    protected function getSubfieldDefinitions(): array
    {
        return [
            [
                'handle' => 'prefix',
                'display' => 'Prefix',
                'type' => 'text',
                'inclusion_default' => false,
                'required_default' => false,
                'label_default' => 'Prefix',
                'placeholder_default' => 'e.g. Dr., Mr., Ms.',
                'default_type' => 'text',
                'default_value' => '',
                'width' => 50,
                'autocomplete' => 'honorific-prefix',
            ],
            [
                'handle' => 'first_name',
                'display' => 'First Name',
                'type' => 'text',
                'inclusion_default' => true,
                'required_default' => true,
                'label_default' => 'First Name',
                'placeholder_default' => 'Enter first name',
                'default_type' => 'text',
                'default_value' => '',
                'width' => 50,
                'autocomplete' => 'given-name',
            ],
            [
                'handle' => 'middle_name',
                'display' => 'Middle Name',
                'type' => 'text',
                'inclusion_default' => false,
                'required_default' => false,
                'label_default' => 'Middle Name',
                'placeholder_default' => 'Enter middle name',
                'default_type' => 'text',
                'default_value' => '',
                'width' => 50,
                'autocomplete' => 'additional-name',
            ],
            [
                'handle' => 'last_name',
                'display' => 'Last Name',
                'type' => 'text',
                'inclusion_default' => true,
                'required_default' => true,
                'label_default' => 'Last Name',
                'placeholder_default' => 'Enter last name',
                'default_type' => 'text',
                'default_value' => '',
                'width' => 50,
                'autocomplete' => 'family-name',
            ],
            [
                'handle' => 'suffix',
                'display' => 'Suffix',
                'type' => 'text',
                'inclusion_default' => false,
                'required_default' => false,
                'label_default' => 'Suffix',
                'placeholder_default' => 'e.g. Jr., Sr., III',
                'default_type' => 'text',
                'default_value' => '',
                'width' => 50,
                'autocomplete' => 'honorific-suffix',
            ],
        ];
    }
}
