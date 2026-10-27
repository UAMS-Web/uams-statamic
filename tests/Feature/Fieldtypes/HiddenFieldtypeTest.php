<?php

declare(strict_types=1);

use App\Fieldtypes\Hidden;
use Statamic\Fields\Field;
use Statamic\Fieldtypes\Hidden as BaseHidden;
use Tests\TestCase;

uses(TestCase::class);

covers(Hidden::class);

/**
 * Behavioral and mutation coverage for the Hidden fieldtype contract, config
 * overrides, and hidden-config handle list.
 *
 * @command  php artisan test --compact tests/Feature/Fieldtypes/HiddenFieldtypeTest.php
 */

/**
 * Build a Hidden fieldtype bound to a Field with the given config.
 *
 * @param  array<string, mixed>  $config  Field config merged over the type handle
 */
function hiddenFieldtype(array $config = []): Hidden
{
    // Build a blueprint Field selecting the hidden fieldtype
    $field = new Field(
        'secret',
        array_merge(
            ['type' => 'hidden'],
            $config
        )
    );

    // Resolve the auto-discovered Hidden fieldtype from the Field
    /** @var Hidden $fieldtype */
    $fieldtype = $field->fieldtype();

    // Bind the Field onto the fieldtype instance
    $fieldtype->setField($field);

    return $fieldtype;
}

/**
 * Call a protected Hidden method via reflection.
 *
 * @param  Hidden  $fieldtype  Bound hidden fieldtype under test
 * @param  string  $method  Protected method name to invoke
 * @param  array<int, mixed>  $args  Arguments forwarded to the method
 * @return mixed Reflected method return value
 */
function hiddenCall(Hidden $fieldtype, string $method, array $args = []): mixed
{
    // Reflect the protected method on Hidden
    $reflection = new ReflectionMethod(Hidden::class, $method);

    // Invoke the method with the forwarded arguments
    return $reflection->invoke($fieldtype, ...$args);
}

it('exposes the hidden Vue component and form-selectable contract', function (): void {
    // Resolve a bound Hidden fieldtype
    $fieldtype = hiddenFieldtype();

    // Assert the CP/form contract surface
    expect($fieldtype->component())->toBe('hidden')
        ->and($fieldtype->selectableInForms())->toBeTrue()
        ->and(Hidden::handle())->toBe('hidden');
});

it('defers the icon to the parent Hidden fieldtype', function (): void {
    // Resolve icons from both the app fieldtype and Statamic's base
    $appIcon = hiddenFieldtype()->icon();
    $baseIcon = (new BaseHidden)->icon();

    // Assert the override returns the parent icon unchanged
    expect($appIcon)->toBe($baseIcon)
        ->and($appIcon)->not->toBe('');
});

it('allows no autocomplete keys', function (): void {
    expect(hiddenCall(hiddenFieldtype(), 'getAllowedAutocompleteKeys'))
        ->toBe([]);
});

it('pins every config field override exactly', function (): void {
    // Resolve the config field overrides map
    $overrides = hiddenCall(hiddenFieldtype(), 'getConfigFieldOverrides');

    // Assert input_type and default overrides exactly
    expect($overrides)->toBe([
        'input_type' => [
            'default' => 'hidden',
        ],
        'default' => [
            'type' => 'integer',
        ],
    ]);
});

it('pins every hidden config field handle exactly', function (): void {
    // Resolve the hidden config field handle list
    $hidden = hiddenCall(hiddenFieldtype(), 'getHiddenConfigFields');

    // Assert the full handle list exactly (order and membership)
    expect($hidden)->toBe([
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
        'dir',
        'dirname',
        'draggable',
        'enterkeyhint',
        'debounce',
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

it('returns no content validation rules', function (): void {
    expect(hiddenFieldtype()->rules())->toBe([]);
});
