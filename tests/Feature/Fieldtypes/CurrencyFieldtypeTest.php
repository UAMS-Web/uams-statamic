<?php

declare(strict_types=1);

use App\Enums\Currency as CurrencyEnum;
use App\Fieldtypes\Currency;
use Statamic\Fields\Field;
use Tests\TestCase;

/**
 * Tests for App\Fieldtypes\Currency preload masks, CP metadata, and config maps.
 *
 * Mutation: 100% on App\Fieldtypes\Currency (--covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Fieldtypes/CurrencyFieldtypeTest.php
 */
uses(TestCase::class);

covers(Currency::class);

/**
 * Build a Currency fieldtype bound to a Field with the given config.
 *
 * @param  array<string, mixed>  $config  Field config merged over the type handle
 */
function currencyFieldtype(array $config = []): Currency
{
    // Build the fieldtype under test
    $fieldtype = new Currency;

    // Bind a Field with the given config over the type handle
    $fieldtype->setField(new Field(
        'price',
        array_merge(
            ['type' => 'currency'],
            $config
        )
    ));

    return $fieldtype;
}

/**
 * Call a protected Currency method via reflection.
 *
 * @param  Currency  $fieldtype  Bound currency fieldtype under test
 * @param  string  $method  Protected method name to invoke
 * @param  array<int, mixed>  $args  Arguments forwarded to the method
 * @return mixed Reflected method return value
 */
function currencyCall(Currency $fieldtype, string $method, array $args = []): mixed
{
    // Reflect the protected method on Currency
    $reflection = new ReflectionMethod(Currency::class, $method);

    // Invoke the method with the forwarded arguments
    return $reflection->invoke($fieldtype, ...$args);
}

/**
 * Resolve preload config for the given field config.
 *
 * @param  array<string, mixed>  $config
 * @return array<string, mixed>
 */
function currencyPreloadConfig(array $config = []): array
{
    /** @var array<string, mixed> $preloadConfig */
    $preloadConfig = currencyFieldtype($config)->preload()['config'];

    return $preloadConfig;
}

/**
 * Resolve preload config for a currency_type string (preload-mask suite helper).
 *
 * @param  string  $currencyType  Currency enum value or invalid code under test
 * @return array<string, mixed>
 */
function currencyPreloadConfigForType(string $currencyType): array
{
    return currencyPreloadConfig(['currency_type' => $currencyType]);
}

it('prepends the symbol with no separator for a before/no-separation currency (USD)', function (): void {
    $config = currencyPreloadConfigForType('USD');

    expect($config['prepend'])->toBe('USD')
        ->and($config['input_mask_prepend_text_enabled'])->toBeTrue()
        ->and($config['input_mask_append_text_enabled'])->toBeFalse()
        ->and($config['input_mask_prepend_text'])->toBe('$')
        ->and($config['input_mask_scale'])->toBe(2);
});

it('prepends the symbol with a separating space for a before/separation currency (EUR)', function (): void {
    $config = currencyPreloadConfigForType('EUR');

    expect($config['input_mask_prepend_text'])->toBe('€ ')
        ->and($config['input_mask_prepend_text_enabled'])->toBeTrue()
        ->and($config['input_mask_scale'])->toBe(2);
});

it('uses a zero scale for a zero-minor-unit currency (JPY)', function (): void {
    // A currency with no minor unit must not allow fractional digits.
    expect(currencyPreloadConfigForType('JPY')['input_mask_scale'])->toBe(0);
});

it('registers the currency Vue component handle', function (): void {
    expect(currencyFieldtype()->component())->toBe('currency');
});

it('uses the money-cash-bill icon', function (): void {
    expect(currencyFieldtype()->icon())->toBe('money-cash-bill');
});

it('exposes currency search keywords exactly', function (): void {
    expect(currencyFieldtype()->keywords())->toBe([
        'currency',
        'money',
        'price',
        'cost',
        'amount',
        'value',
        'total',
        'subtotal',
        'tax',
    ]);
});

it('belongs to the special fieldtype category', function (): void {
    expect(currencyFieldtype()->categories())->toBe([
        'special',
    ]);
});

it('allows no autocomplete keys', function (): void {
    expect(currencyCall(currencyFieldtype(), 'getAllowedAutocompleteKeys'))
        ->toBe([]);
});

it('pins every config field override exactly', function (): void {
    $overrides = currencyCall(currencyFieldtype(), 'getConfigFieldOverrides');

    expect($overrides)->toBe([
        'input_type' => [
            'default' => 'text',
        ],
        'default' => [
            'type' => 'float',
        ],
        'autocomplete_query' => [
            'default' => 'off',
        ],
        'inputmode' => [
            'default' => 'decimal',
        ],
        'spellcheck' => [
            'default' => 'false',
        ],
        'writingsuggestions' => [
            'default' => 'false',
        ],
        'input_mask' => [
            'default' => true,
        ],
        'input_mask_class' => [
            'default' => 'MaskedNumber',
        ],
        'input_mask_store_unmasked' => [
            'default' => true,
            'instructions' => <<<'EOT'
                Controls whether to store the masked (formatted) value or the unmasked (raw) value.

                **When enabled (true):**
                - Stores the raw, unmasked value (e.g., `1234.5`)
                - Preserves the original data without formatting
                - Useful for data processing, calculations, or when you need the raw value

                **When disabled (false):**
                - Stores the formatted, masked value (e.g., `1,234.50`)
                - Preserves the exact display format
                - Useful for consistent display or when formatting is part of the data
                EOT,
        ],
        'input_mask_eager' => [
            'default' => false,
        ],
        'input_mask_mapToRadix' => [
            'default' => null,
        ],
        'input_mask_max' => [
            'display' => 'Maximum Value',
            'default' => null,
            'instructions' => 'Set the maximum allowed value for this field.',
            'type' => 'float',
        ],
        'input_mask_min' => [
            'display' => 'Maximum Value',
            'default' => null,
            'instructions' => 'Set the minimum allowed value for this field.',
            'type' => 'float',
        ],
        'input_mask_normalizeZeros' => [
            'default' => true,
        ],
        'input_mask_overwrite' => [
            'default' => false,
        ],
        'input_mask_padFractionalZeros' => [
            'default' => true,
        ],
        'input_mask_parent' => [
            'default' => null,
        ],
        'input_mask_radix' => [
            'default' => '.',
        ],
        'input_mask_scale' => [
            'default' => null,
        ],
        'input_mask_skipInvalid' => [
            'default' => true,
        ],
        'input_mask_thousandsSeparator' => [
            'default' => ',',
        ],
        'input_mask_prepend_text_enabled' => [
            'default' => null,
        ],
        'input_mask_prepend_text' => [
            'default' => null,
        ],
        'input_mask_append_text_enabled' => [
            'default' => null,
        ],
        'input_mask_append_text' => [
            'default' => null,
        ],
        'calculation_type' => [
            'default' => 'math',
        ],
    ]);
});

it('unsets no config fields', function (): void {
    expect(currencyCall(currencyFieldtype(), 'getUnsetConfigFields'))
        ->toBe([]);
});

it('pins every hidden config field handle exactly', function (): void {
    expect(currencyCall(currencyFieldtype(), 'getHiddenConfigFields'))->toBe([
        'accesskey',
        'anchor',
        'antlers',
        'append',
        'autocapitalize',
        'autocomplete',
        'autocomplete_query',
        'autocorrect',
        'calculation_type',
        'calculation_decimals',
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
        'step',
        'style',
        'tabindex',
        'title',
        'transform_case',
        'translate',
        'virtualkeyboardpolicy',
        'writingsuggestions',
        'input_mask',
        'input_mask_class',
        'input_mask_mask',
        'input_mask_mask_MaskedDynamic',
        'input_mask_mask_MaskedFunction',
        'input_mask_mask_MaskedPattern',
        'input_mask_mask_MaskedRegExp',
        'input_mask_append_text',
        'input_mask_append_text_enabled',
        'input_mask_autofix',
        'input_mask_blocks',
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
        'input_mask_maxLength',
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
        'input_mask_thousandsSeparator',
        'input_mask_to',
    ]);
});

it('adds a currency_type select with every enum case and USD default', function (): void {
    // Resolve the additional config fields map
    $fields = mustBeArray(currencyCall(currencyFieldtype(), 'getAdditionalConfigFields'), 'the additional config fields');

    // Build the expected select options from every Currency enum case
    $expectedOptions = [];
    foreach (CurrencyEnum::cases() as $currencyEnumCase) {
        $expectedOptions[$currencyEnumCase->value] = $currencyEnumCase->name();
    }

    // Assert currency_type options, default, and validation exactly
    expect($fields)->toBe([
        'currency_type' => [
            'display' => __('Currency Type'),
            'type' => 'select',
            'instructions' => __('Select the type of currency to be used by the field.'),
            'options' => $expectedOptions,
            'default' => 'USD',
            'validate' => ['required'],
        ],
    ])
        ->and(mustBeArray($fields['currency_type'], 'the currency_type field')['options'])->toHaveCount(\count(CurrencyEnum::cases()))
        ->and(mustBeArray($fields['currency_type'], 'the currency_type field')['default'])->toBe('USD');
});

it('appends the symbol for an after-placement currency (ETB)', function (): void {
    $config = currencyPreloadConfig(['currency_type' => 'ETB']);

    expect($config['prepend'])->toBe('ETB')
        ->and($config['input_mask_append_text_enabled'])->toBeTrue()
        ->and($config['input_mask_prepend_text_enabled'])->toBeFalse()
        ->and($config['input_mask_append_text'])->toBe(' Br')
        ->and($config['input_mask_scale'])->toBe(2);
});

it('leaves mask overrides unset for an invalid currency_type', function (): void {
    $config = currencyPreloadConfig(['currency_type' => 'NOTREAL']);

    expect($config['prepend'])->toBe('NOTREAL')
        ->and($config['input_mask_scale'] ?? null)->toBeNull()
        ->and($config['input_mask_prepend_text'] ?? null)->toBeNull()
        ->and($config['input_mask_append_text'] ?? null)->toBeNull()
        ->and($config['calculation_decimals'] ?? null)->toBeNull();
});

it('sets calculation_decimals from minor units when calculation_formula is true', function (): void {
    $config = currencyPreloadConfig([
        'currency_type' => 'USD',
        'calculation_formula' => true,
    ]);

    expect($config['calculation_decimals'])->toBe(2)
        ->and($config['input_mask_scale'])->toBe(2)
        ->and($config['input_mask_prepend_text'])->toBe('$');
});

it('does not set calculation_decimals when calculation_formula is absent', function (): void {
    $config = currencyPreloadConfig(['currency_type' => 'USD']);

    expect($config['calculation_decimals'] ?? null)->toBeNull();
});

it('does not set calculation_decimals for a truthy non-boolean calculation_formula', function (): void {
    // `1` is non-empty but not `=== true`, so the AND guard must leave decimals unset
    // (BooleanAndToBooleanOr would set them).
    $config = currencyPreloadConfig([
        'currency_type' => 'USD',
        'calculation_formula' => 1,
    ]);

    expect($config['calculation_decimals'] ?? null)->toBeNull();
});

it('appends without a leading space for after/no-separation currency (AFN)', function (): void {
    $config = currencyPreloadConfig(['currency_type' => 'AFN']);

    expect($config['input_mask_append_text_enabled'])->toBeTrue()
        ->and($config['input_mask_prepend_text_enabled'])->toBeFalse()
        ->and($config['input_mask_append_text'])->toBe('؋');
});

it('treats a non-scalar currency_type as an empty alpha and leaves the mask unset', function (): void {
    $config = currencyPreloadConfig(['currency_type' => ['USD']]);

    expect($config['prepend'])->toBe('')
        ->and($config['input_mask_scale'] ?? null)->toBeNull()
        ->and($config['input_mask_prepend_text'] ?? null)->toBeNull();
});

it('casts a non-string scalar currency_type before enum lookup', function (): void {
    // Without `(string)`, `CurrencyEnum::from(123)` would TypeError outside the
    // ValueError catch; with the cast it becomes an invalid code and soft-fails.
    $config = currencyPreloadConfig(['currency_type' => 123]);

    expect($config['prepend'])->toBe('123')
        ->and($config['input_mask_scale'] ?? null)->toBeNull();
});

it('exposes fieldtype_handle alongside preload config', function (): void {
    $preload = currencyFieldtype(['currency_type' => 'USD'])->preload();

    expect($preload)->toHaveKeys(['config', 'fieldtype_handle'])
        ->and($preload['fieldtype_handle'])->toBe('currency')
        ->and(mustBeArray($preload['config'], 'the preload config')['handle'])->toBe('price');
});
