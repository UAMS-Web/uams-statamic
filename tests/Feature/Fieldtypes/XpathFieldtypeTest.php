<?php

declare(strict_types=1);

use App\Fieldtypes\Xpath;
use Illuminate\Support\Facades\Validator;
use Statamic\Fields\Field;
use Tests\TestCase;

/**
 * Tests for App\Fieldtypes\Xpath: its Control Panel identity, the config lists it hands the Input
 * trait, and its validation rules.
 *
 * @command  php artisan test --compact tests/Feature/Fieldtypes/XpathFieldtypeTest.php
 */
uses(TestCase::class);

covers(Xpath::class);

/**
 * Build a Xpath fieldtype bound to a Field with the given config.
 *
 * @param  array<string, mixed>  $config  Field config merged over the type handle
 */
function xpathFieldtype(array $config = []): Xpath
{
    // Build the fieldtype under test
    $fieldtype = new Xpath;

    // Bind a Field with the given config over the type handle
    $fieldtype->setField(new Field('xpath', array_merge(['type' => 'xpath'], $config)));

    return $fieldtype;
}

/**
 * Call a protected Xpath method via reflection.
 *
 * @param  string  $method  Protected method name to invoke
 * @return mixed Reflected method return value
 */
function xpathCall(string $method): mixed
{
    // Reflect the protected method and invoke it on a fresh fieldtype
    return (new ReflectionMethod(Xpath::class, $method))->invoke(xpathFieldtype());
}

it('identifies itself in the Control Panel', function (): void {
    $fieldtype = xpathFieldtype();

    expect(Xpath::title())->toBe('XPath')
        ->and($fieldtype->component())->toBe('xpath')
        ->and($fieldtype->icon())->toBe('code-inline')
        ->and($fieldtype->selectable())->toBeFalse()
        ->and($fieldtype->selectableInForms())->toBeFalse();
});

it('exposes its search keywords exactly', function (): void {
    expect(xpathFieldtype()->keywords())->toBe([
        'code',
        'data access',
        'data',
        'expression',
        'expressions',
        'language',
        'languages',
        'queries',
        'query language',
        'query languages',
        'query',
        'xml path language',
        'xml',
        'xpath',
    ]);
});

it('belongs to its fieldtype categories exactly', function (): void {
    expect(xpathFieldtype()->categories())->toBe([
        'special',
    ]);
});

it('allows its autocomplete keys exactly', function (): void {
    expect(xpathCall('getAllowedAutocompleteKeys'))->toBe([]);
});

it('pins every config field override exactly', function (): void {
    expect(xpathCall('getConfigFieldOverrides'))->toBe([
        'input_type' => [
            'default' => 'text',
        ],
        'autocapitalize' => [
            'default' => 'off',
        ],
        'autocorrect' => [
            'default' => 'off',
        ],
        'inputmode' => [
            'default' => 'text',
        ],
        'spellcheck' => [
            'default' => 'off',
        ],
        'writingsuggestions' => [
            'default' => 'off',
        ],
        'calculation_query' => [
            'default' => false,
        ],
    ]);
});

it('pins every hidden config field handle exactly', function (): void {
    expect(xpathCall('getHiddenConfigFields'))->toBe([
        'accesskey',
        'anchor',
        'antlers',
        'append',
        'autocapitalize',
        'autocomplete_query',
        'autocorrect',
        'calculation_formula',
        'calculation_formula_math',
        'calculation_formula_text',
        'calculation_query',
        'calculation_type',
        'class',
        'debounce',
        'dir',
        'draggable',
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

it('validates its content with its rule', function (): void {
    $rules = xpathFieldtype()->rules();

    expect($rules)->toBe(['xpath_syntax'])
        ->and(Validator::make(['value' => '//div[@class="card"]'], ['value' => $rules])->passes())->toBeTrue()
        ->and(Validator::make(['value' => '//div['], ['value' => $rules])->passes())->toBeFalse();
});
