<?php

declare(strict_types=1);

use App\Fieldtypes\ZipCode;
use App\Rules\ZipCode as ZipCodeRule;
use Illuminate\Support\Facades\Validator;
use Statamic\Fields\Field;
use Tests\TestCase;

/**
 * Tests for App\Fieldtypes\ZipCode: its Control Panel identity, the config lists it hands the Input
 * trait, and its validation rules.
 *
 * @command  php artisan test --compact tests/Feature/Fieldtypes/ZipCodeFieldtypeTest.php
 */
uses(TestCase::class);

covers(ZipCode::class);

/**
 * Build a ZipCode fieldtype bound to a Field with the given config.
 *
 * @param  array<string, mixed>  $config  Field config merged over the type handle
 */
function zipCodeFieldtype(array $config = []): ZipCode
{
    // Build the fieldtype under test
    $fieldtype = new ZipCode;

    // Bind a Field with the given config over the type handle
    $fieldtype->setField(new Field('zip_code', array_merge(['type' => 'zip_code'], $config)));

    return $fieldtype;
}

/**
 * Call a protected ZipCode method via reflection.
 *
 * @param  string  $method  Protected method name to invoke
 * @return mixed Reflected method return value
 */
function zipCodeCall(string $method): mixed
{
    // Reflect the protected method and invoke it on a fresh fieldtype
    return (new ReflectionMethod(ZipCode::class, $method))->invoke(zipCodeFieldtype());
}

it('identifies itself in the Control Panel', function (): void {
    $fieldtype = zipCodeFieldtype();

    expect(ZipCode::title())->toBe('ZIP Code')
        ->and($fieldtype->component())->toBe('zip_code')
        ->and($fieldtype->icon())->toBe('form-address-field')
        ->and($fieldtype->selectable())->toBeTrue()
        ->and($fieldtype->selectableInForms())->toBeFalse();
});

it('exposes its search keywords exactly', function (): void {
    expect(zipCodeFieldtype()->keywords())->toBe([
        'address',
        'code',
        'codes',
        'location',
        'postal',
        'zip',
        'zipcode',
        'zipcodes',
    ]);
});

it('belongs to its fieldtype categories exactly', function (): void {
    expect(zipCodeFieldtype()->categories())->toBe([
        'text',
        'special',
    ]);
});

it('allows its autocomplete keys exactly', function (): void {
    expect(zipCodeCall('getAllowedAutocompleteKeys'))->toBe([
        'postal-code',
    ]);
});

it('pins every config field override exactly', function (): void {
    expect(zipCodeCall('getConfigFieldOverrides'))->toBe([
        'input_type' => [
            'default' => 'text',
        ],
        'inputmode' => [
            'default' => 'numeric',
        ],
        'spellcheck' => [
            'default' => 'false',
        ],
        'placeholder' => [
            'default' => 'e.g., 12345 or 12345-6789',
        ],
        'input_mask' => [
            'default' => true,
        ],
        'input_mask_mask_MaskedPattern' => [
            'default' => '00000[-0000]',
        ],
        'calculation_type' => [
            'default' => 'text',
        ],
    ]);
});

it('pins every hidden config field handle exactly', function (): void {
    expect(zipCodeCall('getHiddenConfigFields'))->toBe([
        'accesskey',
        'anchor',
        'antlers',
        'append',
        'autocapitalize',
        'autocomplete_query',
        'autocorrect',
        'calculation_type',
        'class',
        'dir',
        'draggable',
        'debounce',
        'enterkeyhint',
        'exportparts',
        'focus',
        'form',
        'id',
        'inert',
        'input_mask',
        'input_mask_append_text',
        'input_mask_append_text_enabled',
        'input_mask_autofix',
        'input_mask_blocks',
        'input_mask_class',
        'input_mask_compiledMasks',
        'input_mask_currentMask',
        'input_mask_definitions',
        'input_mask_displayChar',
        'input_mask_eager',
        'input_mask_enum',
        'input_mask_exposeMask',
        'input_mask_from',
        'input_mask_lazy',
        'input_mask_mapToRadix',
        'input_mask_mask',
        'input_mask_mask_MaskedDynamic',
        'input_mask_mask_MaskedFunction',
        'input_mask_mask_MaskedPattern',
        'input_mask_mask_MaskedRegExp',
        'input_mask_max',
        'input_mask_maxLength',
        'input_mask_min',
        'input_mask_normalizeZeros',
        'input_mask_overwrite',
        'input_mask_padFractionalZeros',
        'input_mask_parent',
        'input_mask_pattern',
        'input_mask_placeholderChar',
        'input_mask_prepare_case',
        'input_mask_prepend_text',
        'input_mask_prepend_text_enabled',
        'input_mask_radix',
        'input_mask_repeat',
        'input_mask_scale',
        'input_mask_skipInvalid',
        'input_mask_store_unmasked',
        'input_mask_thousandsSeparator',
        'input_mask_to',
        'inputmode',
        'is',
        'itemid',
        'itemprop',
        'itemref',
        'itemscope',
        'itemtype',
        'lang',
        'list',
        'maxlength',
        'minlength',
        'name',
        'nonce',
        'part',
        'pattern',
        'placeholder',
        'popover',
        'prepend',
        'role',
        'size',
        'slot',
        'spellcheck',
        'style',
        'tabindex',
        'title',
        'transform_case',
        'translate',
        'virtualkeyboardpolicy',
        'writingsuggestions',
    ]);
});

it('validates its content with the ZIP code rule', function (): void {
    $rules = zipCodeFieldtype()->rules();

    expect($rules)->toEqual([new ZipCodeRule])
        ->and(Validator::make(['zip' => '72205-7199'], ['zip' => $rules])->passes())->toBeTrue()
        ->and(Validator::make(['zip' => '7220'], ['zip' => $rules])->passes())->toBeFalse();
});

it('normalizes a value to its trimmed string form', function (mixed $value, string $normalized): void {
    expect(zipCodeFieldtype()->normalizeValue($value))->toBe($normalized);
})->with([
    'surrounding whitespace' => [" 72205 \n", '72205'],
    'an integer' => [72205, '72205'],
    'null' => [null, ''],
    'an array' => [['72205'], ''],
]);
