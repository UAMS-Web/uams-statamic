<?php

declare(strict_types=1);

use App\Fieldtypes\Integer;
use Illuminate\Support\Facades\Validator;
use Statamic\Fields\Field;
use Tests\TestCase;

/**
 * Tests for App\Fieldtypes\Integer: its Control Panel identity, the config lists it hands the Input
 * trait, and its validation rules.
 *
 * @command  php artisan test --compact tests/Feature/Fieldtypes/IntegerFieldtypeTest.php
 */
uses(TestCase::class);

covers(Integer::class);

/**
 * Build a Integer fieldtype bound to a Field with the given config.
 *
 * @param  array<string, mixed>  $config  Field config merged over the type handle
 */
function integerFieldtype(array $config = []): Integer
{
    // Build the fieldtype under test
    $fieldtype = new Integer;

    // Bind a Field with the given config over the type handle
    $fieldtype->setField(new Field('integer', array_merge(['type' => 'integer'], $config)));

    return $fieldtype;
}

/**
 * Call a protected Integer method via reflection.
 *
 * @param  string  $method  Protected method name to invoke
 * @return mixed Reflected method return value
 */
function integerCall(string $method): mixed
{
    // Reflect the protected method and invoke it on a fresh fieldtype
    return (new ReflectionMethod(Integer::class, $method))->invoke(integerFieldtype());
}

it('identifies itself in the Control Panel', function (): void {
    $fieldtype = integerFieldtype();

    expect(Integer::title())->toBe('Integer')
        ->and($fieldtype->component())->toBe('custom_integer')
        ->and($fieldtype->icon())->toBe('fieldtype-integer')
        ->and($fieldtype->selectable())->toBeTrue()
        ->and($fieldtype->selectableInForms())->toBeTrue();
});

it('exposes its search keywords exactly', function (): void {
    expect(integerFieldtype()->keywords())->toBe([]);
});

it('belongs to its fieldtype categories exactly', function (): void {
    expect(integerFieldtype()->categories())->toBe([
        'number',
    ]);
});

it('allows its autocomplete keys exactly', function (): void {
    expect(integerCall('getAllowedAutocompleteKeys'))->toBe([
        'bday-day',
        'bday-month',
        'bday-year',
        'tel',
        'tel-country-code',
        'tel-national',
        'tel-area-code',
        'tel-local',
        'tel-local-prefix',
        'tel-local-suffix',
        'tel-extension',
        'postal-code',
        'cc-number',
        'cc-exp-month',
        'cc-exp-year',
        'cc-csc',
        'one-time-code',
    ]);
});

it('pins every config field override exactly', function (): void {
    expect(integerCall('getConfigFieldOverrides'))->toBe([
        'input_type' => [
            'default' => 'number',
        ],
        'default' => [
            'type' => 'integer',
        ],
        'max' => [
            'type' => 'integer',
        ],
        'min' => [
            'type' => 'integer',
        ],
        'step' => [
            'type' => 'integer',
        ],
        'inputmode' => [
            'default' => 'numeric',
        ],
        'calculation_type' => [
            'default' => 'math',
        ],
        'calculation_decimals' => [
            'default' => 0,
        ],
    ]);
});

it('pins every hidden config field handle exactly', function (): void {
    expect(integerCall('getHiddenConfigFields'))->toBe([
        'accept',
        'accesskey',
        'alt',
        'anchor',
        'antlers',
        'autocapitalize',
        'autocapitalize_setting',
        'autocorrect',
        'calculation_type',
        'calculation_decimals',
        'capture',
        'checked',
        'class',
        'contenteditable',
        'debounce',
        'dir',
        'dirname',
        'draggable',
        'enterkeyhint',
        'exportparts',
        'focus',
        'form',
        'formaction',
        'formenctype',
        'formmethod',
        'formnovalidate',
        'formtarget',
        'height',
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
        'input_type',
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
        'multiple',
        'name',
        'nonce',
        'part',
        'pattern',
        'popover',
        'popovertarget',
        'popovertargetaction',
        'role',
        'size',
        'slot',
        'spellcheck',
        'src',
        'style',
        'tabindex',
        'title',
        'transform_case',
        'translate',
        'virtualkeyboardpolicy',
        'width',
        'writingsuggestions',
    ]);
});

it('builds its range rule from the configured bounds', function (array $config, array $rules): void {
    /** @var array<string, mixed> $typedConfig */
    $typedConfig = $config;

    expect(integerFieldtype($typedConfig)->rules())->toBe($rules);
})->with([
    'no bounds' => [[], ['integer']],
    'empty bounds' => [['min' => '', 'max' => ''], ['integer']],
    'a minimum only' => [['min' => 3], ['integer', 'min:3']],
    'a zero minimum' => [['min' => 0], ['integer', 'min:0']],
    'a maximum only' => [['max' => 9], ['integer', 'max:9']],
    'a zero maximum' => [['max' => 0], ['integer', 'max:0']],
    'both bounds' => [['min' => 3, 'max' => 9], ['integer', 'between:3,9']],
    'a minimum and an empty maximum' => [['min' => 3, 'max' => ''], ['integer', 'min:3']],
    'an empty minimum and a maximum' => [['min' => '', 'max' => 9], ['integer', 'max:9']],
]);

it('enforces the range through the validator', function (): void {
    $rules = ['n' => integerFieldtype(['min' => 3, 'max' => 9])->rules()];

    expect(Validator::make(['n' => 5], $rules)->passes())->toBeTrue()
        ->and(Validator::make(['n' => 10], $rules)->passes())->toBeFalse()
        ->and(Validator::make(['n' => 4.5], $rules)->passes())->toBeFalse();
});
