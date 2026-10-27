<?php

declare(strict_types=1);

use App\Fieldtypes\Month;
use Illuminate\Support\Facades\Validator;
use Statamic\Fields\Field;
use Tests\TestCase;

/**
 * Tests for App\Fieldtypes\Month: its Control Panel identity, the config lists it hands the Input
 * trait, and its validation rules.
 *
 * @command  php artisan test --compact tests/Feature/Fieldtypes/MonthFieldtypeTest.php
 */
uses(TestCase::class);

covers(Month::class);

/**
 * Build a Month fieldtype bound to a Field with the given config.
 *
 * @param  array<string, mixed>  $config  Field config merged over the type handle
 */
function monthFieldtype(array $config = []): Month
{
    // Build the fieldtype under test
    $fieldtype = new Month;

    // Bind a Field with the given config over the type handle
    $fieldtype->setField(new Field('month', array_merge(['type' => 'month'], $config)));

    return $fieldtype;
}

/**
 * Call a protected Month method via reflection.
 *
 * @param  string  $method  Protected method name to invoke
 * @return mixed Reflected method return value
 */
function monthCall(string $method): mixed
{
    // Reflect the protected method and invoke it on a fresh fieldtype
    return (new ReflectionMethod(Month::class, $method))->invoke(monthFieldtype());
}

it('identifies itself in the Control Panel', function (): void {
    $fieldtype = monthFieldtype();

    expect(Month::title())->toBe('Month')
        ->and($fieldtype->component())->toBe('month')
        ->and($fieldtype->icon())->toBe('fieldtype-date')
        ->and($fieldtype->selectable())->toBeTrue()
        ->and($fieldtype->selectableInForms())->toBeFalse();
});

it('exposes its search keywords exactly', function (): void {
    expect(monthFieldtype()->keywords())->toBe([
        'calendar',
        'date',
        'month',
    ]);
});

it('belongs to its fieldtype categories exactly', function (): void {
    expect(monthFieldtype()->categories())->toBe([
        'special',
    ]);
});

it('allows its autocomplete keys exactly', function (): void {
    expect(monthCall('getAllowedAutocompleteKeys'))->toBe([]);
});

it('pins every config field override exactly', function (): void {
    expect(monthCall('getConfigFieldOverrides'))->toBe([
        'input_type' => [
            'default' => 'month',
        ],
        'min' => [
            'instructions' => 'Set the minimum allowed month. Format: YYYY-MM (e.g., 2020-01 for January 2020).',
            'validate' => [
                'regex:/^\\d{4}-\\d{2}$/',
            ],
        ],
        'max' => [
            'instructions' => 'Set the maximum allowed month. Format: YYYY-MM (e.g., 2030-12 for December 2030).',
            'validate' => [
                'regex:/^\\d{4}-\\d{2}$/',
            ],
        ],
        'calculation_type' => [
            'default' => 'text',
        ],
    ]);
});

it('unsets no config fields', function (): void {
    expect(monthCall('getUnsetConfigFields'))->toBe([]);
});

it('pins every hidden config field handle exactly', function (): void {
    expect(monthCall('getHiddenConfigFields'))->toBe([
        'accesskey',
        'anchor',
        'antlers',
        'append',
        'autocapitalize',
        'autocomplete',
        'autocomplete_query',
        'autocorrect',
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
        'inputmode',
        'is',
        'itemid',
        'itemprop',
        'itemref',
        'itemscope',
        'itemtype',
        'lang',
        'list',
        'name',
        'nonce',
        'part',
        'popover',
        'prepend',
        'role',
        'slot',
        'spellcheck',
        'step',
        'style',
        'tabindex',
        'title',
        'translate',
        'virtualkeyboardpolicy',
        'writingsuggestions',
    ]);
});

/**
 * Validate one value against a Month fieldtype's rules and return the error messages.
 *
 * @param  array<string, mixed>  $config  Field config (min and max)
 * @param  mixed  $value  Value under validation
 * @return array<string>
 */
function monthErrors(array $config, mixed $value): array
{
    // Run the fieldtype's rules through Laravel's validator
    $validator = Validator::make(['month' => $value], ['month' => monthFieldtype($config)->rules()]);

    return $validator->errors()->all();
}

it('only checks the YYYY-MM format when no bound is configured', function (): void {
    expect(monthFieldtype()->rules())->toBe(['regex:/^\d{4}-\d{2}$/'])
        ->and(monthErrors([], '2024-05'))->toBe([])
        ->and(monthErrors([], '2024-5'))->toBe(['The month field format is invalid.']);
});

it('treats an empty bound as unset', function (): void {
    // An empty min or max adds no comparison rule
    expect(monthFieldtype(['min' => '', 'max' => null])->rules())->toHaveCount(1);
});

it('rejects a month before the configured minimum, naming it as Month yyyy', function (string $value, array $errors): void {
    expect(monthErrors(['min' => '2020-03'], $value))->toBe($errors);
})->with([
    'an earlier year, later month' => ['2019-12', ['The month must be after or equal to March 2020.']],
    'the same year, earlier month' => ['2020-02', ['The month must be after or equal to March 2020.']],
    'the minimum itself' => ['2020-03', []],
    'the same year, later month' => ['2020-04', []],
    'a later year, earlier month' => ['2021-01', []],
]);

it('rejects a month after the configured maximum, naming it as Month yyyy', function (string $value, array $errors): void {
    expect(monthErrors(['max' => '2030-06'], $value))->toBe($errors);
})->with([
    'a later year, earlier month' => ['2031-01', ['The month must be before or equal to June 2030.']],
    'the same year, later month' => ['2030-07', ['The month must be before or equal to June 2030.']],
    'the maximum itself' => ['2030-06', []],
    'the same year, earlier month' => ['2030-05', []],
    'an earlier year, later month' => ['2029-12', []],
]);

it('applies both bounds together', function (): void {
    $config = ['min' => '2020-01', 'max' => '2020-12'];

    expect(monthFieldtype($config)->rules())->toHaveCount(3)
        ->and(monthErrors($config, '2020-06'))->toBe([])
        ->and(monthErrors($config, '2019-06'))->toBe(['The month must be after or equal to January 2020.'])
        ->and(monthErrors($config, '2021-06'))->toBe(['The month must be before or equal to December 2020.']);
});

it('reports only the format error for a malformed value when a bound is configured', function (string $value): void {
    // A value with no separator cannot be ordered against a bound; the format rule reports it
    expect(monthErrors(['min' => '2020-03', 'max' => '2030-06'], $value))
        ->toBe(['The month field format is invalid.']);
})->with([
    'no separator' => ['abc'],
    'a bare year' => ['2020'],
]);

it('formats a YYYY-MM month as Month yyyy and leaves anything else unchanged', function (string $month, string $display): void {
    $format = new ReflectionMethod(Month::class, 'formatMonthForDisplay');

    expect($format->invoke(monthFieldtype(), $month))->toBe($display);
})->with([
    'a valid month' => ['2024-09', 'September 2024'],
    'a single-digit month' => ['2024-9', '2024-9'],
    'a trailing character' => ['2024-09x', '2024-09x'],
    'a leading character' => ['x2024-09', 'x2024-09'],
]);

it('ignores a bound that is not YYYY-MM', function (): void {
    // A bound with no separator cannot be ordered against, so it fails no value
    expect(monthErrors(['min' => '2020', 'max' => 'June'], '2024-05'))->toBe([]);
});

it('casts a non-string scalar bound or value to a string before comparing', function (array $config, mixed $value): void {
    // An integer bound or value is cast to a string, which is not YYYY-MM, so
    // no bound fails and only the format rule reports the value
    /** @var array<string, mixed> $typedConfig */
    $typedConfig = $config;

    expect(monthErrors($typedConfig, $value))->toBe(['The month field format is invalid.']);
})->with([
    'an integer minimum' => [['min' => 2020], '2019'],
    'an integer maximum' => [['max' => 2030], '2031'],
    'an integer value against a minimum' => [['min' => '2020-03'], 2019],
    'an integer value against a maximum' => [['max' => '2030-06'], 2031],
]);

it('still orders a bound or value that has a separator but is not YYYY-MM', function (): void {
    // A loose bound such as 2024-5 is compared part by part, as before, and
    // shown unformatted in the message
    expect(monthErrors(['min' => '2024-5'], '2023-01'))
        ->toBe(['The month must be after or equal to 2024-5.'])
        ->and(monthErrors(['min' => '2024-06'], '2024-5'))
        ->toBe(['The month field format is invalid.', 'The month must be after or equal to June 2024.']);
});

it('compares each year and month part as an integer', function (array $config, string $value, array $errors): void {
    // A part that is not a number counts as 0, as the integer casts make it,
    // rather than being compared as a string against the other part
    /** @var array<string, mixed> $typedConfig */
    $typedConfig = $config;

    expect(monthErrors($typedConfig, $value))->toBe($errors);
})->with([
    'a letter year in the value, below the minimum' => [['min' => '2020-01'], 'abc-05', ['The month field format is invalid.', 'The month must be after or equal to January 2020.']],
    'a letter year in the minimum' => [['min' => 'abc-01'], '2020-05', []],
    'a letter year in the value, equal to a zero maximum year' => [['max' => '0-01'], 'abc-01', ['The month field format is invalid.']],
    'a symbol year in the maximum, equal to a zero value year' => [['max' => '!x-01'], '0-01', ['The month field format is invalid.']],
    'a letter month in the value, below the minimum' => [['min' => '2020-01'], '2020-abc', ['The month field format is invalid.', 'The month must be after or equal to January 2020.']],
    'a letter month in the minimum' => [['min' => '2020-abc'], '2020-05', []],
    'a letter month in the value, equal to a zero maximum month' => [['max' => '2020-00'], '2020-abc', ['The month field format is invalid.']],
    'a symbol month in the maximum, equal to a zero value month' => [['max' => '2020-!x'], '2020-00', []],
]);
