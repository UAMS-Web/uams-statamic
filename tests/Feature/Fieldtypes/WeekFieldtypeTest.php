<?php

declare(strict_types=1);

use App\Fieldtypes\Week;
use Illuminate\Support\Facades\Validator;
use Statamic\Fields\Field;
use Statamic\Fields\Fields;
use Tests\TestCase;

/**
 * Tests for App\Fieldtypes\Week: its Control Panel identity, the config lists it hands the Input
 * trait, and its validation rules.
 *
 * @command  php artisan test --compact tests/Feature/Fieldtypes/WeekFieldtypeTest.php
 */
uses(TestCase::class);

covers(Week::class);

/**
 * Build a Week fieldtype bound to a Field with the given config.
 *
 * @param  array<string, mixed>  $config  Field config merged over the type handle
 */
function weekFieldtype(array $config = []): Week
{
    // Build the fieldtype under test
    $fieldtype = new Week;

    // Bind a Field with the given config over the type handle
    $fieldtype->setField(new Field('week', array_merge(['type' => 'week'], $config)));

    return $fieldtype;
}

/**
 * Call a protected Week method via reflection.
 *
 * @param  string  $method  Protected method name to invoke
 * @param  array<int, mixed>  $args  Arguments forwarded to the method
 * @return mixed Reflected method return value
 */
function weekCall(string $method, array $args = []): mixed
{
    // Reflect the protected method and invoke it on a fresh fieldtype
    return (new ReflectionMethod(Week::class, $method))->invoke(weekFieldtype(), ...$args);
}

/**
 * Validate one value against a Week fieldtype's rules and return the error messages.
 *
 * @param  array<string, mixed>  $config  Field config (min and max)
 * @param  mixed  $value  Value under validation
 * @return array<string>
 */
function weekErrors(array $config, mixed $value): array
{
    // Run the fieldtype's rules through Laravel's validator
    $validator = Validator::make(['week' => $value], ['week' => weekFieldtype($config)->rules()]);

    return $validator->errors()->all();
}

it('identifies itself in the Control Panel', function (): void {
    $fieldtype = weekFieldtype();

    expect(Week::title())->toBe('Week')
        ->and($fieldtype->component())->toBe('week')
        ->and($fieldtype->icon())->toBe('fieldtype-date')
        ->and($fieldtype->selectable())->toBeTrue()
        ->and($fieldtype->selectableInForms())->toBeFalse();
});

it('exposes its search keywords exactly', function (): void {
    expect(weekFieldtype()->keywords())->toBe([
        'calendar',
        'date',
        'week',
    ]);
});

it('belongs to its fieldtype categories exactly', function (): void {
    expect(weekFieldtype()->categories())->toBe([
        'special',
    ]);
});

it('allows its autocomplete keys exactly', function (): void {
    expect(weekCall('getAllowedAutocompleteKeys'))->toBe([]);
});

it('pins every config field override exactly', function (): void {
    expect(weekCall('getConfigFieldOverrides'))->toBe([
        'input_type' => [
            'default' => 'week',
        ],
        'min' => [
            'instructions' => 'Set the minimum allowed week. Format: YYYY-Www (e.g., 2020-W01 for week 1 of 2020).',
            'validate' => [
                'regex:/^(\\d{4})-W(0[1-9]|[1-4]\\d|5[0-3])$/',
            ],
        ],
        'max' => [
            'instructions' => 'Set the maximum allowed week. Format: YYYY-Www (e.g., 2030-W52 for week 52 of 2030).',
            'validate' => [
                'regex:/^(\\d{4})-W(0[1-9]|[1-4]\\d|5[0-3])$/',
            ],
        ],
        'calculation_type' => [
            'default' => 'text',
        ],
    ]);
});

it('unsets no config fields', function (): void {
    expect(weekCall('getUnsetConfigFields'))->toBe([]);
});

it('pins every hidden config field handle exactly', function (): void {
    expect(weekCall('getHiddenConfigFields'))->toBe([
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
        'autocomplete',
        'enterkeyhint',
        'debounce',
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

it('only checks the YYYY-Www format when no bound is configured', function (): void {
    expect(weekFieldtype()->rules())->toBe(['regex:/^(\d{4})-W(0[1-9]|[1-4]\d|5[0-3])$/'])
        ->and(weekErrors([], '2024-W05'))->toBe([])
        ->and(weekErrors([], '2024-W5'))->toBe(['The week field format is invalid.']);
});

it('treats an empty bound as unset', function (): void {
    // An empty min or max adds no comparison rule
    expect(weekFieldtype(['min' => '', 'max' => null])->rules())->toHaveCount(1);
});

it('rejects a week before the configured minimum, naming it as Week xx, yyyy', function (string $value, array $errors): void {
    expect(weekErrors(['min' => '2020-W10'], $value))->toBe($errors);
})->with([
    'an earlier year, later week' => ['2019-W52', ['The week must be after or equal to Week 10, 2020.']],
    'the same year, earlier week' => ['2020-W09', ['The week must be after or equal to Week 10, 2020.']],
    'the minimum itself' => ['2020-W10', []],
    'the same year, later week' => ['2020-W11', []],
    'a later year, earlier week' => ['2021-W01', []],
]);

it('rejects a week after the configured maximum, naming it as Week xx, yyyy', function (string $value, array $errors): void {
    expect(weekErrors(['max' => '2030-W26'], $value))->toBe($errors);
})->with([
    'a later year, earlier week' => ['2031-W01', ['The week must be before or equal to Week 26, 2030.']],
    'the same year, later week' => ['2030-W27', ['The week must be before or equal to Week 26, 2030.']],
    'the maximum itself' => ['2030-W26', []],
    'the same year, earlier week' => ['2030-W25', []],
    'an earlier year, later week' => ['2029-W52', []],
]);

it('applies both bounds together', function (): void {
    $config = ['min' => '2020-W01', 'max' => '2020-W52'];

    expect(weekFieldtype($config)->rules())->toHaveCount(3)
        ->and(weekErrors($config, '2020-W26'))->toBe([])
        ->and(weekErrors($config, '2019-W26'))->toBe(['The week must be after or equal to Week 01, 2020.'])
        ->and(weekErrors($config, '2021-W26'))->toBe(['The week must be before or equal to Week 52, 2020.']);
});

it('reports only the format error for a malformed value when a bound is configured', function (string $value): void {
    // A value with no week separator cannot be ordered against a bound; the format rule reports it
    expect(weekErrors(['min' => '2020-W10', 'max' => '2030-W26'], $value))
        ->toBe(['The week field format is invalid.']);
})->with([
    'no separator' => ['abc'],
    'a bare year' => ['2020'],
    'a month' => ['2024-05'],
]);

it('ignores a bound that is not YYYY-Www', function (): void {
    // A bound with no week separator cannot be ordered against, so it fails no value
    expect(weekErrors(['min' => '2020', 'max' => 'June'], '2024-W05'))->toBe([]);
});

it('casts a non-string scalar bound or value to a string before comparing', function (array $config, mixed $value): void {
    // An integer bound or value is cast to a string, which has no week separator,
    // so no bound fails and only the format rule reports the value
    /** @var array<string, mixed> $typedConfig */
    $typedConfig = $config;

    expect(weekErrors($typedConfig, $value))->toBe(['The week field format is invalid.']);
})->with([
    'an integer minimum' => [['min' => 2020], '2019'],
    'an integer maximum' => [['max' => 2030], '2031'],
    'an integer value against a minimum' => [['min' => '2020-W10'], 2019],
    'an integer value against a maximum' => [['max' => '2030-W26'], 2031],
]);

it('skips the comparison for a non-scalar value', function (): void {
    // An array value is neither a week nor comparable, so no bound fails on it
    expect(weekFieldtype(['min' => '2020-W10'])->rules()[1])->toBeInstanceOf(Closure::class);

    $failures = [];
    $rules = weekFieldtype(['min' => '2020-W10', 'max' => '2030-W26'])->rules();
    $minRule = mustBe(Closure::class, $rules[1], 'min rule');
    $maxRule = mustBe(Closure::class, $rules[2], 'max rule');
    $minRule('week', ['2019-W01'], function (string $message) use (&$failures): void {
        $failures[] = $message;
    });
    $maxRule('week', ['2031-W01'], function (string $message) use (&$failures): void {
        $failures[] = $message;
    });

    expect($failures)->toBe([]);
});

it('compares each year and week part as an integer', function (array $config, string $value, array $errors): void {
    // A part that is not a number counts as 0, as the integer casts make it
    /** @var array<string, mixed> $typedConfig */
    $typedConfig = $config;

    expect(weekErrors($typedConfig, $value))->toBe($errors);
})->with([
    'a letter year in the value, below the minimum' => [['min' => '2020-W01'], 'abc-W05', ['The week field format is invalid.', 'The week must be after or equal to Week 01, 2020.']],
    'a letter year in the minimum' => [['min' => 'abc-W01'], '2020-W05', []],
    'a letter week in the value, below the minimum' => [['min' => '2020-W01'], '2020-Wab', ['The week field format is invalid.', 'The week must be after or equal to Week 01, 2020.']],
    'a letter week in the maximum, equal to a zero value week' => [['max' => '2020-Wab'], '2020-W00', ['The week field format is invalid.']],
    'a letter year in the value, equal to a zero maximum year' => [['max' => '0-W01'], 'abc-W01', ['The week field format is invalid.']],
    'a letter year in the maximum, below a numeric value year in the same week' => [['max' => 'abc-W01'], '2020-W01', ['The week must be before or equal to abc-W01.']],
    'a letter week in the minimum' => [['min' => '2020-Wab'], '2020-W05', []],
    'a letter week in the value, equal to a zero maximum week' => [['max' => '2020-W00'], '2020-Wab', ['The week field format is invalid.']],
    'a letter week in the maximum, below a numeric value week' => [['max' => '2020-Wab'], '2020-W05', ['The week must be before or equal to 2020-Wab.']],
]);

it('accepts a config bound or submitted value only from week 01 to week 53', function (string $week, bool $valid): void {
    foreach (['min', 'max'] as $bound) {
        $fields = mustBe(Fields::class, (new Week)->configFields()->addValues([$bound => $week]), 'the config fields');

        expect(rescue(fn () => $fields->validate(), false, false) !== false)->toBe($valid);
    }

    expect(weekErrors([], $week))->toBe($valid ? [] : ['The week field format is invalid.']);
})->with([
    'a zero week' => ['2024-W00', false],
    'the first week' => ['2024-W01', true],
    'the ninth week' => ['2024-W09', true],
    'the tenth week' => ['2024-W10', true],
    'the nineteenth week' => ['2024-W19', true],
    'the forty-ninth week' => ['2024-W49', true],
    'the fiftieth week' => ['2024-W50', true],
    'the last week' => ['2024-W53', true],
    'the week after the last' => ['2024-W54', false],
    'a week far above 53' => ['2024-W99', false],
]);

it('formats a YYYY-Www week as Week xx, yyyy and leaves anything else unchanged', function (string $week, string $display): void {
    expect(weekCall('formatWeekForDisplay', [$week]))->toBe($display);
})->with([
    'a valid week' => ['2024-W09', 'Week 09, 2024'],
    'the last week' => ['2024-W53', 'Week 53, 2024'],
    'the week after the last' => ['2024-W54', '2024-W54'],
    'a zero week' => ['2024-W00', '2024-W00'],
    'a single-digit week' => ['2024-W9', '2024-W9'],
    'a trailing character' => ['2024-W09x', '2024-W09x'],
    'a leading character' => ['x2024-W09', 'x2024-W09'],
]);
