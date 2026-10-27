<?php

declare(strict_types=1);

use App\Fieldtypes\Floatval;
use Illuminate\Support\Facades\Validator;
use Statamic\Fields\Field;
use Tests\TestCase;

/**
 * Tests for App\Fieldtypes\Floatval: its Control Panel identity, the config lists it hands the Input
 * trait, and its validation rules.
 *
 * @command  php artisan test --compact tests/Feature/Fieldtypes/FloatvalFieldtypeTest.php
 */
uses(TestCase::class);

covers(Floatval::class);

/**
 * Build a Floatval fieldtype bound to a Field with the given config.
 *
 * @param  array<string, mixed>  $config  Field config merged over the type handle
 */
function floatvalFieldtype(array $config = []): Floatval
{
    // Build the fieldtype under test
    $fieldtype = new Floatval;

    // Bind a Field with the given config over the type handle
    $fieldtype->setField(new Field('floatval', array_merge(['type' => 'floatval'], $config)));

    return $fieldtype;
}

/**
 * Call a protected Floatval method via reflection.
 *
 * @param  string  $method  Protected method name to invoke
 * @return mixed Reflected method return value
 */
function floatvalCall(string $method): mixed
{
    // Reflect the protected method and invoke it on a fresh fieldtype
    return (new ReflectionMethod(Floatval::class, $method))->invoke(floatvalFieldtype());
}

it('identifies itself in the Control Panel', function (): void {
    $fieldtype = floatvalFieldtype();

    expect(Floatval::title())->toBe('Decimal Number')
        ->and($fieldtype->component())->toBe('custom_floatval')
        ->and($fieldtype->icon())->toBe('fieldtype-float')
        ->and($fieldtype->selectable())->toBeTrue()
        ->and($fieldtype->selectableInForms())->toBeTrue();
});

it('exposes its search keywords exactly', function (): void {
    expect(floatvalFieldtype()->keywords())->toBe([
        'float',
        'number',
        'numeric',
        'decimal',
        'floatval',
    ]);
});

it('belongs to its fieldtype categories exactly', function (): void {
    expect(floatvalFieldtype()->categories())->toBe([
        'number',
    ]);
});

it('allows its autocomplete keys exactly', function (): void {
    expect(floatvalCall('getAllowedAutocompleteKeys'))->toBe([
        'transaction-amount',
    ]);
});

it('pins every config field override exactly', function (): void {
    expect(floatvalCall('getConfigFieldOverrides'))->toBe([
        'input_type' => [
            'default' => 'number',
        ],
        'default' => [
            'type' => 'float',
        ],
        'max' => [
            'type' => 'float',
        ],
        'min' => [
            'type' => 'float',
        ],
        'step' => [
            'type' => 'float',
        ],
        'inputmode' => [
            'default' => 'numeric',
        ],
        'calculation_type' => [
            'default' => 'math',
        ],
    ]);
});

it('pins every hidden config field handle exactly', function (): void {
    expect(floatvalCall('getHiddenConfigFields'))->toBe([
        'accept',
        'accesskey',
        'alt',
        'anchor',
        'antlers',
        'autocapitalize',
        'autocapitalize_setting',
        'autocorrect',
        'calculation_type',
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

    expect(floatvalFieldtype($typedConfig)->rules())->toBe($rules);
})->with([
    'no bounds' => [[], ['numeric']],
    'empty bounds' => [['min' => '', 'max' => ''], ['numeric']],
    'a minimum only' => [['min' => 1.5], ['numeric', 'min:1.5']],
    'a zero minimum' => [['min' => 0], ['numeric', 'min:0']],
    'a maximum only' => [['max' => 9.25], ['numeric', 'max:9.25']],
    'a zero maximum' => [['max' => 0], ['numeric', 'max:0']],
    'both bounds' => [['min' => 1.5, 'max' => 9.25], ['numeric', 'between:1.5,9.25']],
    'a minimum and an empty maximum' => [['min' => 1.5, 'max' => ''], ['numeric', 'min:1.5']],
    'an empty minimum and a maximum' => [['min' => '', 'max' => 9.25], ['numeric', 'max:9.25']],
    'a non-scalar minimum' => [['min' => [1]], ['numeric', 'min:']],
    'a non-scalar maximum' => [['max' => [9]], ['numeric', 'max:']],
]);

it('enforces the range through the validator', function (): void {
    $rules = ['n' => floatvalFieldtype(['min' => 1.5, 'max' => 9.25])->rules()];

    expect(Validator::make(['n' => 4.75], $rules)->passes())->toBeTrue()
        ->and(Validator::make(['n' => 1.25], $rules)->passes())->toBeFalse()
        ->and(Validator::make(['n' => 'abc'], $rules)->passes())->toBeFalse();
});
