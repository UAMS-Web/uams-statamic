<?php

declare(strict_types=1);

use Tests\TestCase;
use UAMSWeb\Aether\Fieldtypes\Fieldtypes\Name;

uses(TestCase::class);

covers(Name::class);

/**
 * How the `Name` fieldtype presents itself: the Vue component, the icon, and that
 * it can be added to a form blueprint (UAMS-Web/uams-statamic#312). Its subfields
 * belong to the `HasSubfields` trait's tests.
 *
 * @command  php artisan test --compact tests/Feature/Fieldtypes/NameFieldtypeTest.php
 */
it('presents as the name component with the users icon, selectable in forms', function (): void {
    $fieldtype = new Name;

    expect($fieldtype->component())->toBe('name')
        ->and($fieldtype->icon())->toBe('fieldtype-users')
        ->and($fieldtype->selectableInForms())->toBeTrue();
});

it('registers under the name handle', function (): void {
    expect(app('statamic.fieldtypes')->get('name'))->toBe(Name::class);
});

it('defines the prefix, first, middle, last and suffix subfields, requiring only the first and last names', function (): void {
    $subfield = static fn (string $handle, string $display, bool $core, string $placeholder, string $autocomplete): array => [
        'handle' => $handle,
        'display' => $display,
        'type' => 'text',
        'inclusion_default' => $core,
        'required_default' => $core,
        'label_default' => $display,
        'placeholder_default' => $placeholder,
        'default_type' => 'text',
        'default_value' => '',
        'width' => 50,
        'autocomplete' => $autocomplete,
    ];

    expect(new ReflectionMethod(Name::class, 'getSubfieldDefinitions')->invoke(new Name))->toBe([
        $subfield('prefix', 'Prefix', false, 'e.g. Dr., Mr., Ms.', 'honorific-prefix'),
        $subfield('first_name', 'First Name', true, 'Enter first name', 'given-name'),
        $subfield('middle_name', 'Middle Name', false, 'Enter middle name', 'additional-name'),
        $subfield('last_name', 'Last Name', true, 'Enter last name', 'family-name'),
        $subfield('suffix', 'Suffix', false, 'e.g. Jr., Sr., III', 'honorific-suffix'),
    ]);
});
