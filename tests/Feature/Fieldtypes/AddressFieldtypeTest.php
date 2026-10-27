<?php

declare(strict_types=1);

use App\Enums\CanadianProvince;
use App\Enums\Country;
use App\Enums\State;
use App\Fieldtypes\Address;
use Statamic\Fields\Field;
use Tests\TestCase;

uses(TestCase::class);

covers(Address::class);

/**
 * Unit tests for App\Fieldtypes\Address::process preset persistence and the
 * subfield validation rules contributed via the HasSubfields trait.
 *
 * Mutation: 100% on App\Fieldtypes\Address (185 mutants, --covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Fieldtypes/AddressFieldtypeTest.php
 */
it('stores preset from field config on process', function () {
    $field = new Field(
        'mailing',
        [
            'type' => 'address',
            'preset' => 'canadian',
        ]
    );

    /** @var Address $fieldtype */
    $fieldtype = $field->fieldtype();
    $fieldtype->setField($field);

    $out = $fieldtype->process(
        [
            'city' => 'Toronto',
        ]
    );

    expect(data_get($out, 'preset'))->toBe('canadian');
    expect(data_get($out, 'city'))->toBe('Toronto');
});

it('does not store preset when value is empty', function () {
    $field = new Field(
        'mailing',
        [
            'type' => 'address',
        ]
    );

    /** @var Address $fieldtype */
    $fieldtype = $field->fieldtype();
    $fieldtype->setField($field);

    $out = $fieldtype->process([]);

    expect($out)->toBe([]);
});

it('returns an empty array for non-array input without subfield content', function () {
    $field = new Field(
        'mailing',
        [
            'type' => 'address',
            'preset' => 'international',
        ]
    );

    /** @var Address $fieldtype */
    $fieldtype = $field->fieldtype();
    $fieldtype->setField($field);

    $out = $fieldtype->process(null);

    expect($out)->toBe([]);
});

it('does not store preset when subfields are blank', function () {
    $field = new Field(
        'mailing',
        [
            'type' => 'address',
            'preset' => 'american',
        ]
    );

    /** @var Address $fieldtype */
    $fieldtype = $field->fieldtype();
    $fieldtype->setField($field);

    $out = $fieldtype->process(
        [
            'street_address' => '',
            'city' => '   ',
        ]
    );

    expect($out)->toBe([]);
});

it('prepends sometimes to nested rules when the blueprint uses sometimes', function () {
    $field = new Field(
        'hometown_us',
        [
            'type' => 'address',
            'preset' => 'american',
            'validate' => [
                'sometimes',
                'required',
            ],
        ]
    );

    /** @var Address $fieldtype */
    $fieldtype = $field->fieldtype();
    $fieldtype->setField($field);

    $extra = $fieldtype->extraRules();

    expect($extra)->toHaveKey('hometown_us.city');
    // extraRules() keys contain literal dots, so index the key directly; data_get()
    // would treat the dot as nested traversal and miss the flat key.
    expect($extra['hometown_us.city'] ?? null)->toBe([
        'sometimes',
        'required',
    ]);
});

it('returns empty for pre process validatable when no subfield content', function () {
    $field = new Field(
        'hometown_us',
        [
            'type' => 'address',
            'preset' => 'american',
        ]
    );

    /** @var Address $fieldtype */
    $fieldtype = $field->fieldtype();
    $fieldtype->setField($field);

    expect($fieldtype->preProcessValidatable(
        [
            'city' => '',
            'state_province_region' => '',
        ]
    ))->toBe([]);
});

it('requires the shared state subfield for the american preset', function () {
    $field = new Field(
        'hometown_us',
        [
            'type' => 'address',
            'preset' => 'american',
            'validate' => [
                'sometimes',
                'required',
            ],
        ]
    );

    /** @var Address $fieldtype */
    $fieldtype = $field->fieldtype();
    $fieldtype->setField($field);

    $extra = $fieldtype->extraRules();

    expect($extra['hometown_us.state_province_region'] ?? null)->toBe([
        'sometimes',
        'required',
    ]);
});

it('requires the shared state subfield for the canadian preset', function () {
    $field = new Field(
        'hometown_canada',
        [
            'type' => 'address',
            'preset' => 'canadian',
            'validate' => [
                'sometimes',
                'required',
            ],
        ]
    );

    /** @var Address $fieldtype */
    $fieldtype = $field->fieldtype();
    $fieldtype->setField($field);

    $extra = $fieldtype->extraRules();

    expect($extra['hometown_canada.state_province_region'] ?? null)->toBe([
        'sometimes',
        'required',
    ]);
});

it('makes the international state subfield optional when its toggle is off', function () {
    $field = new Field(
        'hometown_international',
        [
            'type' => 'address',
            'preset' => 'international',
            'state_province_region_international_required' => false,
            'validate' => [
                'sometimes',
                'required',
            ],
        ]
    );

    /** @var Address $fieldtype */
    $fieldtype = $field->fieldtype();
    $fieldtype->setField($field);

    $extra = $fieldtype->extraRules();

    expect($extra['hometown_international.state_province_region'] ?? null)->toBe([
        'sometimes',
        'nullable',
    ]);
});

it('keeps the american state subfield required when preset is omitted', function () {
    $field = new Field(
        'hometown_us',
        [
            'type' => 'address',
            'validate' => [
                'sometimes',
                'required',
            ],
        ]
    );

    /** @var Address $fieldtype */
    $fieldtype = $field->fieldtype();
    $fieldtype->setField($field);

    $extra = $fieldtype->extraRules();

    expect($extra['hometown_us.state_province_region'] ?? null)->toBe([
        'sometimes',
        'required',
    ]);
});

/**
 * Build an Address fieldtype bound to a Field with the given config.
 *
 * @param  array<string, mixed>  $config  Field config merged over the type handle
 */
function addressFieldtype(array $config = []): Address
{
    // Build a blueprint Field selecting the address fieldtype
    $field = new Field(
        'mailing',
        array_merge(
            ['type' => 'address'],
            $config
        )
    );

    // Resolve the auto-discovered Address fieldtype from the Field
    /** @var Address $fieldtype */
    $fieldtype = $field->fieldtype();

    // Bind the Field onto the fieldtype instance
    $fieldtype->setField($field);

    return $fieldtype;
}

/**
 * Call a protected Address method via reflection.
 *
 * @param  Address  $fieldtype  Bound address fieldtype under test
 * @param  string  $method  Protected method name to invoke
 * @param  array<int, mixed>  $args  Arguments forwarded to the method
 * @return mixed Reflected method return value
 */
function addressCall(Address $fieldtype, string $method, array $args = []): mixed
{
    // Reflect the protected method on Address
    $reflection = new ReflectionMethod(Address::class, $method);

    // Invoke the method with the forwarded arguments
    return $reflection->invoke($fieldtype, ...$args);
}

it('exposes the form-address-field icon', function (): void {
    expect(addressFieldtype()->icon())->toBe('form-address-field');
});

it('lists american canadian and international preset options', function (): void {
    $presets = addressCall(addressFieldtype(), 'getPresetOptions');

    expect($presets)->toBe([
        'display' => 'Address Format',
        'options' => [
            'american' => 'American',
            'canadian' => 'Canadian',
            'international' => 'International',
        ],
    ]);
});

it('returns preset visibility conditions for country and state variants', function (): void {
    $ft = addressFieldtype();

    expect(addressCall($ft, 'getSubfieldPresetIfCondition', ['country', null]))
        ->toBe(['preset' => 'international'])
        ->and(addressCall($ft, 'getSubfieldPresetIfCondition', ['state_province_region_american', null]))
        ->toBe(['preset' => 'american'])
        ->and(addressCall($ft, 'getSubfieldPresetIfCondition', ['state_province_region_canadian', null]))
        ->toBe(['preset' => 'canadian'])
        ->and(addressCall($ft, 'getSubfieldPresetIfCondition', ['state_province_region_international', null]))
        ->toBe(['preset' => 'international'])
        ->and(addressCall($ft, 'getSubfieldPresetIfCondition', ['city', null]))
        ->toBeNull();
});

it('maps every Country State and CanadianProvince enum case', function (): void {
    $ft = addressFieldtype();

    $countries = mustBeArray(addressCall($ft, 'getCountryOptions'), 'the country options');
    $states = mustBeArray(addressCall($ft, 'getUsStatesAndTerritories'), 'the US state options');
    $provinces = mustBeArray(addressCall($ft, 'getCanadianProvincesAndTerritories'), 'the Canadian province options');

    expect($countries)->toHaveCount(\count(Country::cases()))
        ->and($countries['US'] ?? null)->toBe(Country::US->country_name())
        ->and($states)->toHaveCount(\count(State::cases()))
        ->and($states['AR'] ?? null)->toBe(State::AR->fullName())
        ->and($provinces)->toHaveCount(\count(CanadianProvince::cases()))
        ->and($provinces['ON'] ?? null)->toBe(CanadianProvince::ON->fullName());
});

it('defines every subfield scalar default exactly', function (): void {
    $definitions = mustBeArray(addressCall(addressFieldtype(), 'getSubfieldDefinitions'), 'the subfield definitions');
    // Second call must hit the static caches (IdenticalToNotIdentical / IfNegated).
    $again = addressCall(addressFieldtype(), 'getSubfieldDefinitions');

    expect($definitions)->toBe($again);

    $byHandle = collect($definitions)->keyBy('handle');

    expect($byHandle->keys()->all())->toBe([
        'street_address',
        'address_line_2',
        'city',
        'state_province_region_american',
        'state_province_region_canadian',
        'state_province_region_international',
        'postal_code',
        'country',
    ]);

    expect($byHandle['street_address'])->toMatchArray([
        'display' => 'Street Address',
        'type' => 'text',
        'inclusion_default' => true,
        'required_default' => true,
        'label_default' => 'Street Address',
        'placeholder_default' => '123 Main St.',
        'default_type' => 'text',
        'default_value' => '',
        'width' => 100,
        'autocomplete' => 'street-address',
    ]);

    expect($byHandle['address_line_2'])->toMatchArray([
        'display' => 'Address Line 2',
        'type' => 'text',
        'inclusion_default' => true,
        'required_default' => false,
        'label_default' => 'Address Line 2',
        'placeholder_default' => 'Apt, Suite, etc.',
        'default_type' => 'text',
        'default_value' => '',
        'width' => 100,
        'autocomplete' => 'address-line2',
    ]);

    expect($byHandle['city'])->toMatchArray([
        'display' => 'City',
        'type' => 'text',
        'inclusion_default' => true,
        'required_default' => true,
        'label_default' => 'City',
        'default_type' => 'text',
        'default_value' => '',
        'width' => 50,
        'autocomplete' => 'address-level2',
    ]);

    expect($byHandle['state_province_region_american'])->toMatchArray([
        'canonical_handle' => 'state_province_region',
        'display' => 'State, Territory or District',
        'type' => 'select',
        'inclusion_default' => true,
        'required_default' => true,
        'label_default' => 'State, Territory or District',
        'default_type' => 'select',
        'default_value' => '',
        'width' => 50,
        'autocomplete' => 'address-level1',
        'clearable' => true,
    ])->and(mustBeArray($byHandle['state_province_region_american'], 'the American state subfield')['default_choices'])->toHaveKey('AR');

    expect($byHandle['state_province_region_canadian'])->toMatchArray([
        'canonical_handle' => 'state_province_region',
        'display' => 'Province or Territory',
        'type' => 'select',
        'inclusion_default' => true,
        'required_default' => true,
        'label_default' => 'Province or Territory',
        'default_type' => 'select',
        'default_value' => '',
        'width' => 50,
        'autocomplete' => 'address-level1',
        'clearable' => true,
    ])->and(mustBeArray($byHandle['state_province_region_canadian'], 'the Canadian province subfield')['default_choices'])->toHaveKey('ON');

    expect($byHandle['state_province_region_international'])->toMatchArray([
        'canonical_handle' => 'state_province_region',
        'display' => 'State, Province or Region',
        'type' => 'text',
        'inclusion_default' => true,
        'required_default' => true,
        'label_default' => 'State, Province or Region',
        'default_type' => 'text',
        'default_value' => '',
        'width' => 50,
        'autocomplete' => 'address-level1',
    ]);

    expect($byHandle['postal_code'])->toMatchArray([
        'display' => 'Postal Code',
        'type' => 'zip_code',
        'inclusion_default' => true,
        'required_default' => true,
        'label_default' => 'Postal Code',
        'default_type' => 'text',
        'default_value' => '',
        'width' => 50,
        'autocomplete' => 'postal-code',
        'placeholder_default' => 'e.g., 12345 or 12345-6789',
    ]);

    expect($byHandle['country'])->toMatchArray([
        'display' => 'Country',
        'type' => 'select',
        'inclusion_default' => true,
        'required_default' => true,
        'label_default' => 'Country',
        'default_type' => 'select',
        'default_value' => '',
        'width' => 50,
        'clearable' => true,
    ])->and(mustBeArray($byHandle['country'], 'the country subfield')['default_choices'])->toHaveKey('US');
});

it('falls back to the first preset option when config omits preset', function (): void {
    $out = addressFieldtype()->process(['city' => 'Little Rock']);

    expect($out['preset'] ?? null)->toBe('american')
        ->and($out['city'] ?? null)->toBe('Little Rock');
});

it('prefers the configured preset over the first option', function (): void {
    $out = addressFieldtype(['preset' => 'international'])->process([
        'city' => 'Paris',
    ]);

    expect($out['preset'] ?? null)->toBe('international');
});

it('exposes select maps on the form-field template scope', function (): void {
    $extra = addressFieldtype()->extraRenderableFieldData();

    expect($extra)->toHaveKeys([
        'state_options_american',
        'state_options_canadian',
        'country_options',
    ])
        ->and($extra['state_options_american'])->toHaveKey('AR')
        ->and($extra['state_options_canadian'])->toHaveKey('ON')
        ->and($extra['country_options'])->toHaveKey('US');
});
