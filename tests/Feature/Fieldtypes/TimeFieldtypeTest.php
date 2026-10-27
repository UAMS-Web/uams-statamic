<?php

declare(strict_types=1);

use App\Fieldtypes\Time;
use Illuminate\Support\Facades\Validator;
use Statamic\Fields\Field;
use Tests\TestCase;

/**
 * Tests for App\Fieldtypes\Time: its Control Panel identity, the config lists it hands the Input
 * trait, and its validation rules.
 *
 * @command  php artisan test --compact tests/Feature/Fieldtypes/TimeFieldtypeTest.php
 */
uses(TestCase::class);

covers(Time::class);

/**
 * Build a Time fieldtype bound to a Field with the given config.
 *
 * @param  array<string, mixed>  $config  Field config merged over the type handle
 */
function timeFieldtype(array $config = []): Time
{
    // Build the fieldtype under test
    $fieldtype = new Time;

    // Bind a Field with the given config over the type handle
    $fieldtype->setField(new Field('time', array_merge(['type' => 'time'], $config)));

    return $fieldtype;
}

/**
 * Call a protected Time method via reflection.
 *
 * @param  string  $method  Protected method name to invoke
 * @return mixed Reflected method return value
 */
function timeCall(string $method): mixed
{
    // Reflect the protected method and invoke it on a fresh fieldtype
    return (new ReflectionMethod(Time::class, $method))->invoke(timeFieldtype());
}

it('identifies itself in the Control Panel', function (): void {
    $fieldtype = timeFieldtype();

    expect(Time::title())->toBe('Time')
        ->and($fieldtype->component())->toBe('time')
        ->and($fieldtype->icon())->toBe('fieldtype-time')
        ->and($fieldtype->selectable())->toBeTrue()
        ->and($fieldtype->selectableInForms())->toBeTrue();
});

it('exposes its search keywords exactly', function (): void {
    expect(timeFieldtype()->keywords())->toBe([]);
});

it('belongs to its fieldtype categories exactly', function (): void {
    expect(timeFieldtype()->categories())->toBe([
        'special',
    ]);
});

it('allows its autocomplete keys exactly', function (): void {
    expect(timeCall('getAllowedAutocompleteKeys'))->toBe([]);
});

it('pins every config field override exactly', function (): void {
    expect(timeCall('getConfigFieldOverrides'))->toBe([
        'input_type' => [
            'default' => 'time',
        ],
        'default' => [
            'type' => 'time',
        ],
        'autocomplete_query' => [
            'default' => false,
        ],
        'inputmode' => [
            'default' => 'numeric',
        ],
    ]);
});

it('pins every hidden config field handle exactly', function (): void {
    expect(timeCall('getHiddenConfigFields'))->toBe([
        'accept',
        'accesskey',
        'alt',
        'anchor',
        'append',
        'autocapitalize',
        'autocapitalize_setting',
        'autocomplete',
        'autocomplete_query',
        'autocorrect',
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
        'max',
        'maxlength',
        'min',
        'minlength',
        'multiple',
        'name',
        'nonce',
        'part',
        'pattern',
        'placeholder',
        'popover',
        'popovertarget',
        'popovertargetaction',
        'prepend',
        'role',
        'size',
        'slot',
        'spellcheck',
        'src',
        'step',
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

it('validates its content with its rule', function (): void {
    $rules = timeFieldtype()->rules();

    expect($rules)->toBe(['date_format:H:i'])
        ->and(Validator::make(['value' => '13:45'], ['value' => $rules])->passes())->toBeTrue()
        ->and(Validator::make(['value' => '1:45 PM'], ['value' => $rules])->passes())->toBeFalse();
});
