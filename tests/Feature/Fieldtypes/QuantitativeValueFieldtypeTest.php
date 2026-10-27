<?php

declare(strict_types=1);

use App\Fieldtypes\QuantitativeValue;
use App\Support\QuantitativeValueConfig;
use App\Support\QuantitativeValueDurationTotalYears;
use PHPUnit\Framework\Assert;
use Statamic\Fields\Field;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Mutation: 100% on App\Fieldtypes\QuantitativeValue (133 mutants, --covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Fieldtypes/QuantitativeValueFieldtypeTest.php
 */

/**
 * Build a configured QuantitativeValue fieldtype from sensible defaults plus overrides.
 *
 * @param  array<string, mixed>  $config
 */
function quantitativeValueFieldtype(array $config = []): QuantitativeValue
{
    $defaults = [
        'type' => 'quantitative_value',
        'mode' => QuantitativeValueConfig::MODE_NUMBER,
        'number_mode' => QuantitativeValueConfig::NUMBER_INTEGER,
        'major_components' => QuantitativeValueConfig::MAJOR_KEYS,
        'duration_units' => ['years', 'months', 'days'],
        'number_single_storage' => QuantitativeValueConfig::STORAGE_NESTED,
        'duration_single_unit_presentation' => QuantitativeValueConfig::STORAGE_NESTED,
    ];

    $field = new Field('qty', array_replace($defaults, $config));

    $ft = $field->fieldtype();

    Assert::assertInstanceOf(QuantitativeValue::class, $ft);

    return $ft;
}

it('processes integer number mode into a nested array', function () {
    $ft = quantitativeValueFieldtype([
        'major_components' => ['value', 'min'],
    ]);

    $out = $ft->process([
        'value' => '10',
        'min' => '5',
        'max' => '99',
    ]);

    expect($out)->toBe(['value' => 10, 'min' => 5]);
});

it('processes float number mode into a nested array', function () {
    $ft = quantitativeValueFieldtype([
        'number_mode' => QuantitativeValueConfig::NUMBER_FLOAT,
    ]);

    $out = $ft->process([
        'value' => '10.1',
        'min' => '5.2',
        'max' => '15.3',
    ]);

    expect($out)->toBe([
        'value' => 10.1,
        'min' => 5.2,
        'max' => 15.3,
    ]);
});

it('processes a flat single-major number into a scalar', function () {
    $ft = quantitativeValueFieldtype([
        'major_components' => [QuantitativeValueConfig::MAJOR_VALUE],
        'number_single_storage' => QuantitativeValueConfig::STORAGE_FLAT,
    ]);

    $out = $ft->process(['value' => '42']);

    expect($out)->toBe(42);
});

it('expands a flat scalar back into an array for the control panel', function () {
    $ft = quantitativeValueFieldtype([
        'major_components' => [QuantitativeValueConfig::MAJOR_VALUE],
        'number_single_storage' => QuantitativeValueConfig::STORAGE_FLAT,
    ]);

    $pre = $ft->preProcess(42);

    expect($pre)->toBe(['value' => 42]);
});

it('processes duration mode into nested unit objects', function () {
    $ft = quantitativeValueFieldtype([
        'mode' => QuantitativeValueConfig::MODE_DURATION,
        'major_components' => ['value', 'min'],
        'duration_units' => ['years', 'months'],
    ]);

    $out = $ft->process([
        'value' => ['years' => '8', 'months' => '9'],
        'min' => ['years' => '1', 'months' => '2'],
        'max' => ['years' => '15'],
    ]);

    expect($out)->toBe([
        'value' => ['years' => 8, 'months' => 9],
        'min' => ['years' => 1, 'months' => 2],
    ]);
});

it('builds nested number extra rules for each major path', function () {
    $field = new Field('my_qty', [
        'type' => 'quantitative_value',
        'mode' => QuantitativeValueConfig::MODE_NUMBER,
        'number_mode' => QuantitativeValueConfig::NUMBER_INTEGER,
        'major_components' => ['value', 'max'],
        'required' => true,
        'value_required' => true,
        'max_required' => false,
    ]);

    $ft = $field->fieldtype();
    Assert::assertInstanceOf(QuantitativeValue::class, $ft);

    $rules = $ft->extraRules();

    expect($rules['my_qty.value'])->toBe('required|integer');
    expect($rules['my_qty.max'])->toBe('nullable|integer');
});

it('builds root rules for a flat scalar integer and no extra rules', function () {
    $field = new Field('n', [
        'type' => 'quantitative_value',
        'mode' => QuantitativeValueConfig::MODE_NUMBER,
        'number_mode' => QuantitativeValueConfig::NUMBER_INTEGER,
        'major_components' => ['min'],
        'number_single_storage' => QuantitativeValueConfig::STORAGE_FLAT,
        'required' => true,
        'min_required' => true,
    ]);

    $ft = $field->fieldtype();
    Assert::assertInstanceOf(QuantitativeValue::class, $ft);

    expect($ft->rules())->toBe(['required', 'integer']);
    expect($ft->extraRules())->toBe([]);
});

it('rules a nested field as an array', function () {
    $ft = quantitativeValueFieldtype();

    expect($ft->rules())->toBe(['array']);
});

it('processes number mode including trimmed unit code and unit text', function () {
    $ft = quantitativeValueFieldtype([
        'unit_code_enabled' => true,
        'unit_text_enabled' => true,
    ]);

    $out = $ft->process([
        'value' => '5',
        'unit_code' => '  KGM  ',
        'unit_text' => ' kilogram ',
    ]);

    expect($out)->toBe([
        'value' => 5,
        'unit_code' => 'KGM',
        'unit_text' => 'kilogram',
    ]);
});

it('builds extra rules for the number unit metadata paths', function () {
    $field = new Field('q', [
        'type' => 'quantitative_value',
        'mode' => QuantitativeValueConfig::MODE_NUMBER,
        'number_mode' => QuantitativeValueConfig::NUMBER_INTEGER,
        'major_components' => ['value'],
        'required' => true,
        'value_required' => true,
        'unit_code_enabled' => true,
        'unit_text_enabled' => true,
        'unit_code_required' => true,
        'unit_text_required' => false,
    ]);

    $ft = $field->fieldtype();
    Assert::assertInstanceOf(QuantitativeValue::class, $ft);

    $rules = $ft->extraRules();

    expect($rules['q.value'])->toBe('required|integer');
    expect($rules['q.unit_code'])->toBe('required|string');
    expect($rules['q.unit_text'])->toBe('nullable|string');
});

it('disables flat scalar storage when unit metadata is enabled', function () {
    $c = QuantitativeValueConfig::from([
        'mode' => QuantitativeValueConfig::MODE_NUMBER,
        'major_components' => [QuantitativeValueConfig::MAJOR_VALUE],
        'number_single_storage' => QuantitativeValueConfig::STORAGE_FLAT,
        'unit_code_enabled' => true,
    ]);

    expect($c->storesNumberAsFlatScalar())->toBeFalse();
});

it('augments duration mode by merging total years into each major', function () {
    $ft = quantitativeValueFieldtype([
        'mode' => QuantitativeValueConfig::MODE_DURATION,
        'major_components' => QuantitativeValueConfig::MAJOR_KEYS,
        'duration_units' => QuantitativeValueConfig::UNIT_KEYS,
    ]);

    $stored = [
        'value' => [
            'years' => 1,
            'months' => 2,
            'weeks' => 3,
            'days' => 4,
            'hours' => 5,
            'minutes' => 6,
            'seconds' => 7,
        ],
        'min' => [
            'years' => 8,
            'months' => 9,
            'weeks' => 0,
            'days' => 1,
            'hours' => 2,
            'minutes' => 3,
            'seconds' => 4,
        ],
        'max' => [
            'years' => 5,
            'months' => 6,
            'weeks' => 7,
            'days' => 8,
            'hours' => 9,
            'minutes' => 0,
            'seconds' => 1,
        ],
    ];

    $out = $ft->augment($stored);

    expect($out)->toBeArray();
    expect(data_get($out, 'value.total_years'))->toEqualWithDelta(1.24, 0.001);
    expect(data_get($out, 'min.total_years'))->toEqualWithDelta(8.75, 0.001);
    expect(data_get($out, 'max.total_years'))->toEqualWithDelta(5.66, 0.001);
});

it('augments duration mode respecting the major components subset', function () {
    $ft = quantitativeValueFieldtype([
        'mode' => QuantitativeValueConfig::MODE_DURATION,
        'major_components' => ['value', 'max'],
        'duration_units' => QuantitativeValueConfig::UNIT_KEYS,
    ]);

    $stored = [
        'value' => [
            'years' => 1,
            'months' => 2,
            'weeks' => 3,
            'days' => 4,
            'hours' => 5,
            'minutes' => 6,
            'seconds' => 7,
        ],
        'max' => [
            'years' => 5,
            'months' => 6,
            'weeks' => 7,
            'days' => 8,
            'hours' => 9,
            'minutes' => 0,
            'seconds' => 1,
        ],
    ];

    $out = $ft->augment($stored);

    expect($out)->toHaveKey('value');
    expect($out)->toHaveKey('max');
    expect($out)->not->toHaveKey('min');
    expect(data_get($out, 'value.total_years'))->toEqualWithDelta(1.24, 0.001);
    expect(data_get($out, 'max.total_years'))->toEqualWithDelta(5.66, 0.001);
});

it('augments an empty duration major to a zero total years', function () {
    $ft = quantitativeValueFieldtype([
        'mode' => QuantitativeValueConfig::MODE_DURATION,
        'major_components' => ['min'],
        'duration_units' => ['years', 'months'],
    ]);

    $out = $ft->augment([]);

    expect(data_get($out, 'min'))->toBe(['total_years' => 0.0]);
});

it('augments number mode by returning the value unchanged', function () {
    $ft = quantitativeValueFieldtype([
        'mode' => QuantitativeValueConfig::MODE_NUMBER,
        'major_components' => ['value'],
    ]);

    $stored = ['value' => 42];

    expect($ft->augment($stored))->toBe($stored);
});

/**
 * The preload config for a field, as an array.
 *
 * @param  array<string, mixed>  $config
 * @return array<string, mixed>
 */
function quantitativeValuePreloadConfig(array $config = []): array
{
    /** @var array<string, mixed> $preload */
    $preload = quantitativeValueFieldtype($config)->preload()['config'];

    return $preload;
}

/**
 * The validation label for one duration subfield.
 */
function quantitativeValueDurationLabel(string $major, string $unit): string
{
    return __('statamic::fieldtypes.quantitative_value.validation.duration_subfield', ['major' => $major, 'unit' => $unit]);
}

it('identifies itself in the Control Panel', function (): void {
    $fieldtype = new QuantitativeValue;

    expect(QuantitativeValue::title())->toBe('Quantitative Value')
        ->and($fieldtype->component())->toBe('quantitative_value')
        ->and($fieldtype->icon())->toBe('calculator')
        ->and($fieldtype->localizable())->toBeTrue()
        ->and($fieldtype->categories())->toBe(['structured', 'number'])
        ->and($fieldtype->keywords())->toBe([
            'quantitative',
            'quantitative value',
            'schema.org',
            'range',
            'min',
            'max',
            'value',
            'duration',
            'integer',
            'float',
            'number',
        ]);
});

it('configures the mode, components and duration units', function (): void {
    expect((new ReflectionMethod(QuantitativeValue::class, 'configFieldItems'))->invoke(new QuantitativeValue))->toBe([
        [
            'display' => 'Mode',
            'fields' => [
                'mode' => [
                    'display' => 'Mode',
                    'type' => 'radio',
                    'options' => [
                        'number' => 'Number',
                        'duration' => 'Duration',
                    ],
                    'default' => 'number',
                ],
                'number_mode' => [
                    'display' => 'Number mode',
                    'type' => 'radio',
                    'options' => [
                        'integer' => 'Integer',
                        'float' => 'Float',
                    ],
                    'default' => 'integer',
                    'if' => [
                        'mode' => 'equals number',
                    ],
                ],
                'unit_code_enabled' => [
                    'display' => 'Unit Code',
                    'instructions' => 'The unit of measurement given using the UN/CEFACT Common Code (three characters) or a URL. Other codes than the UN/CEFACT Common Code may be used with a prefix followed by a colon.',
                    'type' => 'toggle',
                    'default' => false,
                    'if' => [
                        'mode' => 'equals number',
                    ],
                ],
                'unit_code_required' => [
                    'display' => 'Unit Code required',
                    'type' => 'toggle',
                    'default' => false,
                    'if' => [
                        'mode' => 'equals number',
                        'unit_code_enabled' => true,
                    ],
                ],
                'unit_text_enabled' => [
                    'display' => 'Unit Text',
                    'instructions' => 'A string or text indicating the unit of measurement. This is useful if you cannot provide a standard unit code.',
                    'type' => 'toggle',
                    'default' => false,
                    'if' => [
                        'mode' => 'equals number',
                    ],
                ],
                'unit_text_required' => [
                    'display' => 'Unit Text required',
                    'type' => 'toggle',
                    'default' => false,
                    'if' => [
                        'mode' => 'equals number',
                        'unit_text_enabled' => true,
                    ],
                ],
            ],
        ],
        [
            'display' => 'Components',
            'fields' => [
                'major_components' => [
                    'display' => 'Major components',
                    'instructions' => 'Select at least one: Value, Minimum, and/or Maximum.',
                    'type' => 'checkboxes',
                    'options' => [
                        'value' => 'Value',
                        'min' => 'Minimum',
                        'max' => 'Maximum',
                    ],
                    'default' => [
                        'value',
                        'min',
                        'max',
                    ],
                    'validate' => [
                        'required',
                        'array',
                        'min:1',
                    ],
                ],
                'value_required' => [
                    'display' => 'Value required',
                    'type' => 'toggle',
                    'default' => false,
                    'if' => [
                        'major_components' => 'contains_any value',
                    ],
                ],
                'min_required' => [
                    'display' => 'Minimum required',
                    'type' => 'toggle',
                    'default' => false,
                    'if' => [
                        'major_components' => 'contains_any min',
                    ],
                ],
                'max_required' => [
                    'display' => 'Maximum required',
                    'type' => 'toggle',
                    'default' => false,
                    'if' => [
                        'major_components' => 'contains_any max',
                    ],
                ],
                'number_single_storage' => [
                    'display' => 'Single-component storage (number)',
                    'instructions' => 'When only one major component is enabled: nested keeps YAML as a one-key object; flat stores a scalar at the field root.',
                    'type' => 'radio',
                    'options' => [
                        'nested' => 'Nested object',
                        'flat' => 'Scalar (flat)',
                    ],
                    'default' => 'nested',
                    'if' => [
                        'mode' => 'equals number',
                        'major_components' => 'custom selectionCount:equals,1',
                    ],
                ],
            ],
        ],
        [
            'display' => 'Duration units',
            'fields' => [
                'duration_units' => [
                    'display' => 'Time units',
                    'instructions' => 'Select at least one unit.',
                    'type' => 'checkboxes',
                    'options' => [
                        'years' => 'Years',
                        'months' => 'Months',
                        'weeks' => 'Weeks',
                        'days' => 'Days',
                        'hours' => 'Hours',
                        'minutes' => 'Minutes',
                        'seconds' => 'Seconds',
                    ],
                    'default' => [
                        'years',
                        'months',
                        'days',
                    ],
                    'validate' => [
                        'required',
                        'array',
                        'min:1',
                    ],
                    'if' => [
                        'mode' => 'equals duration',
                    ],
                ],
                'unit_years_required' => [
                    'display' => 'Years required',
                    'type' => 'toggle',
                    'default' => false,
                    'if' => [
                        'mode' => 'equals duration',
                        'duration_units' => 'custom selectionContains:years',
                    ],
                ],
                'unit_months_required' => [
                    'display' => 'Months required',
                    'type' => 'toggle',
                    'default' => false,
                    'if' => [
                        'mode' => 'equals duration',
                        'duration_units' => 'custom selectionContains:months',
                    ],
                ],
                'unit_weeks_required' => [
                    'display' => 'Weeks required',
                    'type' => 'toggle',
                    'default' => false,
                    'if' => [
                        'mode' => 'equals duration',
                        'duration_units' => 'custom selectionContains:weeks',
                    ],
                ],
                'unit_days_required' => [
                    'display' => 'Days required',
                    'type' => 'toggle',
                    'default' => false,
                    'if' => [
                        'mode' => 'equals duration',
                        'duration_units' => 'custom selectionContains:days',
                    ],
                ],
                'unit_hours_required' => [
                    'display' => 'Hours required',
                    'type' => 'toggle',
                    'default' => false,
                    'if' => [
                        'mode' => 'equals duration',
                        'duration_units' => 'custom selectionContains:hours',
                    ],
                ],
                'unit_minutes_required' => [
                    'display' => 'Minutes required',
                    'type' => 'toggle',
                    'default' => false,
                    'if' => [
                        'mode' => 'equals duration',
                        'duration_units' => 'custom selectionContains:minutes',
                    ],
                ],
                'unit_seconds_required' => [
                    'display' => 'Seconds required',
                    'type' => 'toggle',
                    'default' => false,
                    'if' => [
                        'mode' => 'equals duration',
                        'duration_units' => 'custom selectionContains:seconds',
                    ],
                ],
                'duration_single_unit_presentation' => [
                    'display' => 'Single-unit presentation (duration)',
                    'instructions' => 'When only one time unit is enabled: grouped shows Value / Min / Max sections; inline uses compact labels. Stored YAML shape is unchanged.',
                    'type' => 'radio',
                    'options' => [
                        'nested' => 'Grouped',
                        'flat' => 'Inline labels',
                    ],
                    'default' => 'nested',
                    'if' => [
                        'mode' => 'equals duration',
                        'duration_units' => 'custom selectionCount:equals,1',
                    ],
                ],
            ],
        ],
    ]);
});

it('preloads the resolved field config for the control panel', function (): void {
    expect(quantitativeValuePreloadConfig([
        'number_mode' => QuantitativeValueConfig::NUMBER_FLOAT,
        'major_components' => ['min', 'max'],
        'value_required' => true,
        'min_required' => true,
        'max_required' => true,
        'unit_code_enabled' => true,
        'unit_text_enabled' => true,
        'unit_days_required' => true,
    ]))->toBe([
        'mode' => 'number',
        'number_mode' => 'float',
        'major_components' => ['min', 'max'],
        'duration_units' => ['years', 'months', 'days'],
        'number_single_storage' => 'nested',
        'duration_single_unit_presentation' => 'nested',
        'value_required' => true,
        'min_required' => true,
        'max_required' => true,
        'unit_required' => ['years' => false, 'months' => false, 'weeks' => false, 'days' => true, 'hours' => false, 'minutes' => false, 'seconds' => false],
        'stores_number_as_flat_scalar' => false,
        'duration_flat_presentation' => false,
        'duration_grouped_sections' => false,
        'unit_code_enabled' => true,
        'unit_text_enabled' => true,
        'unit_code_instructions' => 'The unit of measurement given using the UN/CEFACT Common Code (three characters) or a URL. Other codes than the UN/CEFACT Common Code may be used with a prefix followed by a colon.',
        'unit_text_instructions' => 'A string or text indicating the unit of measurement. This is useful if you cannot provide a standard unit code.',
    ]);
});

it('groups duration sections unless a single unit is shown inline', function (array $config, bool $grouped, bool $flat, bool $flatScalar): void {
    /** @var array<string, mixed> $typedConfig */
    $typedConfig = $config;
    $preload = quantitativeValuePreloadConfig($typedConfig);

    expect($preload['duration_grouped_sections'])->toBe($grouped)
        ->and($preload['duration_flat_presentation'])->toBe($flat)
        ->and($preload['stores_number_as_flat_scalar'])->toBe($flatScalar);
})->with([
    'number mode' => [[], false, false, false],
    'a flat single number' => [['major_components' => ['value'], 'number_single_storage' => 'flat'], false, false, true],
    'several duration units' => [['mode' => 'duration', 'duration_units' => ['years', 'days']], true, false, false],
    'one grouped duration unit' => [['mode' => 'duration', 'duration_units' => ['years']], true, false, false],
    'two inline duration units' => [['mode' => 'duration', 'duration_units' => ['years', 'days'], 'duration_single_unit_presentation' => 'flat'], true, false, false],
    'one inline duration unit' => [['mode' => 'duration', 'duration_units' => ['years'], 'duration_single_unit_presentation' => 'flat'], false, true, false],
]);

it('defaults to null for a flat single number and to an empty array otherwise', function (): void {
    expect(quantitativeValueFieldtype(['major_components' => ['min'], 'number_single_storage' => 'flat'])->defaultValue())->toBeNull()
        ->and(quantitativeValueFieldtype()->defaultValue())->toBe([])
        ->and((new QuantitativeValue)->defaultValue())->toBe([]);
});

it('stores only the enabled number components that hold a scalar', function (array $config, mixed $data, mixed $stored): void {
    /** @var array<string, mixed> $typedConfig */
    $typedConfig = $config;

    expect(quantitativeValueFieldtype($typedConfig)->process($data))->toBe($stored);
})->with([
    'a non-array value' => [[], 'five', []],
    'null, empty and list components skipped' => [[], ['value' => null, 'min' => '', 'max' => [5]], []],
    'disabled components ignored' => [['major_components' => ['max']], ['value' => '1', 'max' => '9'], ['max' => 9]],
    'a missing component before a set one' => [[], ['min' => '5'], ['min' => 5]],
    'a null component before a set one' => [[], ['value' => null, 'min' => '5'], ['min' => 5]],
    'an empty component before a set one' => [[], ['value' => '', 'min' => '5'], ['min' => 5]],
    'a list component before a set one' => [[], ['value' => [1], 'min' => '5'], ['min' => 5]],
    'integers cast' => [[], ['value' => '7.9', 'min' => 0], ['value' => 7, 'min' => 0]],
    'floats cast' => [['number_mode' => 'float'], ['value' => '7.5'], ['value' => 7.5]],
    'a flat single component as a scalar' => [['major_components' => ['value'], 'number_single_storage' => 'flat'], ['value' => '3'], 3],
    'a flat single component left empty' => [['major_components' => ['value'], 'number_single_storage' => 'flat'], ['value' => null], []],
]);

it('stores trimmed unit metadata only when it is enabled', function (array $config, array $data, array $stored): void {
    /** @var array<string, mixed> $typedConfig */
    $typedConfig = $config;

    expect(quantitativeValueFieldtype($typedConfig)->process($data))->toBe($stored);
})->with([
    'disabled' => [[], ['value' => 1, 'unit_code' => 'ANN', 'unit_text' => 'years'], ['value' => 1]],
    'trimmed strings' => [['unit_code_enabled' => true, 'unit_text_enabled' => true], ['unit_code' => ' ANN ', 'unit_text' => ' years '], ['unit_code' => 'ANN', 'unit_text' => 'years']],
    'blank strings dropped' => [['unit_code_enabled' => true, 'unit_text_enabled' => true], ['unit_code' => '  ', 'unit_text' => ''], []],
    'non-string scalars cast' => [['unit_code_enabled' => true, 'unit_text_enabled' => true], ['unit_code' => 5, 'unit_text' => 6], ['unit_code' => '5', 'unit_text' => '6']],
    'missing values dropped' => [['unit_code_enabled' => true, 'unit_text_enabled' => true], ['value' => 2], ['value' => 2]],
]);

it('stores each enabled duration component as integer units', function (array $config, mixed $data, array $stored): void {
    /** @var array<string, mixed> $typedConfig */
    $typedConfig = ['mode' => 'duration', ...$config];

    expect(quantitativeValueFieldtype($typedConfig)->process($data))->toBe($stored);
})->with([
    'a non-array value' => [[], 'P2Y', []],
    'units cast, blank and disabled units dropped' => [[], ['value' => ['years' => '2', 'months' => null, 'days' => '', 'hours' => 5]], ['value' => ['years' => 2]]],
    'a disabled component ignored' => [['major_components' => ['min']], ['value' => ['years' => 1], 'min' => ['years' => 3]], ['min' => ['years' => 3]]],
    'a non-array component skipped' => [[], ['value' => 4, 'max' => ['days' => 9]], ['max' => ['days' => 9]]],
    'a component with no units dropped' => [[], ['value' => ['years' => null]], []],
    'a missing unit before a set one' => [[], ['value' => ['months' => '2']], ['value' => ['months' => 2]]],
    'a null unit before a set one' => [[], ['value' => ['years' => null, 'days' => '3']], ['value' => ['days' => 3]]],
    'an empty unit before a set one' => [[], ['value' => ['years' => '', 'days' => '3']], ['value' => ['days' => 3]]],
]);

it('expands a flat number for the control panel only when one was stored', function (mixed $data, array $edit): void {
    expect(quantitativeValueFieldtype(['major_components' => ['max'], 'number_single_storage' => 'flat'])->preProcess($data))->toBe($edit);
})->with([
    'a scalar' => [4, ['max' => 4]],
    'nothing stored' => [null, []],
    'an empty array' => [[], ['max' => null]],
]);

it('expands a nested number for the control panel', function (array $config, mixed $data, array $edit): void {
    /** @var array<string, mixed> $typedConfig */
    $typedConfig = $config;

    expect(quantitativeValueFieldtype($typedConfig)->preProcess($data))->toBe($edit);
})->with([
    'a non-array value' => [[], 'five', []],
    'missing components filled' => [[], ['min' => 1], ['value' => null, 'min' => 1, 'max' => null]],
    'unit metadata when enabled' => [['major_components' => ['value'], 'unit_code_enabled' => true, 'unit_text_enabled' => true], ['value' => 2, 'unit_code' => 'ANN'], ['value' => 2, 'unit_code' => 'ANN', 'unit_text' => null]],
    'unit text when enabled' => [['major_components' => ['value'], 'unit_code_enabled' => true, 'unit_text_enabled' => true], ['unit_text' => 'years'], ['value' => null, 'unit_code' => null, 'unit_text' => 'years']],
]);

it('expands a duration for the control panel with every enabled unit', function (mixed $data, array $edit): void {
    expect(quantitativeValueFieldtype(['mode' => 'duration', 'major_components' => ['value', 'max'], 'duration_units' => ['years', 'days']])->preProcess($data))->toBe($edit);
})->with([
    'a non-array value' => ['P2Y', ['value' => ['years' => null, 'days' => null], 'max' => ['years' => null, 'days' => null]]],
    'a partial value' => [['value' => ['years' => 2, 'hours' => 1], 'max' => 'x'], ['value' => ['years' => 2, 'days' => null], 'max' => ['years' => null, 'days' => null]]],
]);

it('validates a flat number at the root, requiring it only when both the field and the component are required', function (array $config, array $rules): void {
    /** @var array<string, mixed> $typedConfig */
    $typedConfig = ['major_components' => ['min'], 'number_single_storage' => 'flat', ...$config];
    $fieldtype = quantitativeValueFieldtype($typedConfig);

    expect($fieldtype->rules())->toBe($rules)
        ->and($fieldtype->extraRules())->toBe([]);
})->with([
    'optional' => [[], ['nullable', 'integer']],
    'a required field, optional component' => [['required' => true], ['nullable', 'integer']],
    'a required component, optional field' => [['min_required' => true], ['nullable', 'integer']],
    'both required' => [['required' => true, 'min_required' => true], ['required', 'integer']],
    'both required, floats' => [['required' => true, 'min_required' => true, 'number_mode' => 'float'], ['required', 'numeric']],
]);

it('reads the field as required from its required flag or validate rules', function (array $config, string $valueRule): void {
    /** @var array<string, mixed> $typedConfig */
    $typedConfig = ['major_components' => ['value'], 'value_required' => true, ...$config];

    expect(quantitativeValueFieldtype($typedConfig)->extraRules())->toBe(['qty.value' => $valueRule]);
})->with([
    'not required' => [[], 'nullable|integer'],
    'the required flag' => [['required' => true], 'required|integer'],
    'a required validate string' => [['validate' => 'required'], 'required|integer'],
    'an empty validate string' => [['validate' => ''], 'nullable|integer'],
    'a required validate list' => [['validate' => ['required']], 'required|integer'],
    'a loose required match in the validate list' => [['validate' => [true]], 'nullable|integer'],
    'a validate value that is neither' => [['validate' => 5], 'nullable|integer'],
]);

it('builds nested number rules for unit metadata', function (array $config, array $rules): void {
    /** @var array<string, mixed> $typedConfig */
    $typedConfig = ['major_components' => ['value'], 'number_mode' => 'float', 'unit_code_enabled' => true, 'unit_text_enabled' => true, ...$config];

    expect(quantitativeValueFieldtype($typedConfig)->extraRules())->toBe($rules);
})->with([
    'optional' => [[], ['qty.value' => 'nullable|numeric', 'qty.unit_code' => 'nullable|string', 'qty.unit_text' => 'nullable|string']],
    'required metadata on a required field' => [['required' => true, 'unit_code_required' => true, 'unit_text_required' => true], ['qty.value' => 'nullable|numeric', 'qty.unit_code' => 'required|string', 'qty.unit_text' => 'required|string']],
    'required metadata on an optional field' => [['unit_code_required' => true, 'unit_text_required' => true], ['qty.value' => 'nullable|numeric', 'qty.unit_code' => 'nullable|string', 'qty.unit_text' => 'nullable|string']],
]);

it('builds duration rules that require a unit only when the field, component and unit are all required', function (array $config, array $rules): void {
    /** @var array<string, mixed> $typedConfig */
    $typedConfig = ['mode' => 'duration', 'major_components' => ['value', 'min'], 'duration_units' => ['years', 'days'], ...$config];

    expect(quantitativeValueFieldtype($typedConfig)->extraRules())->toBe($rules);
})->with([
    'all required' => [['required' => true, 'value_required' => true, 'unit_years_required' => true], [
        'qty.value.years' => 'required|integer',
        'qty.value.days' => 'nullable|integer',
        'qty.min.years' => 'nullable|integer',
        'qty.min.days' => 'nullable|integer',
    ]],
    'an optional field' => [['value_required' => true, 'unit_years_required' => true], [
        'qty.value.years' => 'nullable|integer',
        'qty.value.days' => 'nullable|integer',
        'qty.min.years' => 'nullable|integer',
        'qty.min.days' => 'nullable|integer',
    ]],
]);

it('labels a flat number by its only component', function (string $major, string $label): void {
    expect(quantitativeValueFieldtype(['major_components' => [$major], 'number_single_storage' => 'flat'])->extraValidationAttributes())
        ->toBe(['qty' => $label]);
})->with([
    'value' => ['value', 'Value'],
    'min' => ['min', 'Minimum'],
    'max' => ['max', 'Maximum'],
]);

it('labels each nested number component and unit metadata field', function (): void {
    expect(quantitativeValueFieldtype(['unit_code_enabled' => true, 'unit_text_enabled' => true])->extraValidationAttributes())->toBe([
        'qty.value' => 'Value',
        'qty.min' => 'Minimum',
        'qty.max' => 'Maximum',
        'qty.unit_code' => 'Unit Code',
        'qty.unit_text' => 'Unit Text',
    ])->and(quantitativeValueFieldtype(['major_components' => ['min']])->extraValidationAttributes())->toBe(['qty.min' => 'Minimum']);
});

it('labels each duration subfield by component and unit', function (): void {
    expect(quantitativeValueFieldtype(['mode' => 'duration', 'major_components' => ['value', 'max'], 'duration_units' => ['years', 'days']])->extraValidationAttributes())->toBe([
        'qty.value.years' => quantitativeValueDurationLabel('Value', 'Years'),
        'qty.value.days' => quantitativeValueDurationLabel('Value', 'Days'),
        'qty.max.years' => quantitativeValueDurationLabel('Maximum', 'Years'),
        'qty.max.days' => quantitativeValueDurationLabel('Maximum', 'Days'),
    ]);
});

it('shapes a submitted value for validation', function (array $config, mixed $value, mixed $validatable): void {
    /** @var array<string, mixed> $typedConfig */
    $typedConfig = $config;

    expect(quantitativeValueFieldtype($typedConfig)->preProcessValidatable($value))->toBe($validatable);
})->with([
    'a flat number unchanged' => [['major_components' => ['value'], 'number_single_storage' => 'flat'], 'x', 'x'],
    'a nested number from a non-array' => [['major_components' => ['value', 'min']], 'x', ['value' => null, 'min' => null]],
    'a nested number with unit metadata' => [['major_components' => ['value'], 'unit_code_enabled' => true, 'unit_text_enabled' => true], ['value' => 1, 'unit_text' => 't', 'extra' => 'dropped'], ['value' => 1, 'unit_code' => null, 'unit_text' => 't']],
    'a nested number with a unit code' => [['major_components' => ['value'], 'unit_code_enabled' => true], ['unit_code' => 'ANN'], ['value' => null, 'unit_code' => 'ANN']],
    'a duration from a non-array' => [['mode' => 'duration', 'major_components' => ['min'], 'duration_units' => ['days']], 'x', ['min' => ['days' => null]]],
    'a duration with a non-array component' => [['mode' => 'duration', 'major_components' => ['min', 'max'], 'duration_units' => ['days']], ['min' => ['days' => 3, 'hours' => 1], 'max' => 'x'], ['min' => ['days' => 3], 'max' => ['days' => null]]],
]);

it('augments a non-array duration as an empty stored duration', function (): void {
    $fieldtype = quantitativeValueFieldtype(['mode' => 'duration', 'major_components' => ['value']]);

    expect($fieldtype->augment('P2Y'))->toBe(QuantitativeValueDurationTotalYears::mergeIntoDurationStored([], QuantitativeValueConfig::from(['mode' => 'duration', 'major_components' => ['value'], 'duration_units' => ['years', 'months', 'days']])));
});

it('falls back to the default number config without a bound field', function (): void {
    // Every all-component, nested, integer default applies when no field is bound
    $fieldtype = new QuantitativeValue;

    expect($fieldtype->process(['value' => '4', 'min' => '1']))->toBe(['value' => 4, 'min' => 1])
        ->and($fieldtype->preProcess(['max' => 9]))->toBe(['value' => null, 'min' => null, 'max' => 9])
        ->and($fieldtype->preProcessValidatable('x'))->toBe(['value' => null, 'min' => null, 'max' => null])
        ->and($fieldtype->rules())->toBe(['array'])
        ->and($fieldtype->augment('unchanged'))->toBe('unchanged')
        ->and($fieldtype->preload()['config']['mode'] ?? null)->toBe('number');
});

it('keys nested rules and labels by the handle as text, or by nothing for a missing handle', function (mixed $handle, string $prefix): void {
    // Blueprint YAML can give a numeric handle; it is used as text in the rule keys
    $field = new Field($handle, ['type' => 'quantitative_value', 'major_components' => ['value']]);
    $fieldtype = new QuantitativeValue;
    $fieldtype->setField($field);

    expect($fieldtype->extraRules())->toBe([$prefix.'.value' => 'nullable|integer'])
        ->and($fieldtype->extraValidationAttributes())->toBe([$prefix.'.value' => 'Value']);
})->with([
    'a numeric handle' => [5, '5'],
    'no handle' => [null, ''],
]);
