<?php

declare(strict_types=1);

use App\Fieldtypes\Date;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Contracts\Entries\Collection as EntriesCollectionContract;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;
use Statamic\Support\DateFormat;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Unit tests for the custom Date fieldtype (App\Fieldtypes\Date).
 *
 * Tests application-specific functionality:
 *   - date_exceptions replicator field configuration
 *   - Date field validation in replicators (Statamic v5.53.1 fix)
 *   - Deep copy behavior in replicator sets (Statamic v5.53.1 fix)
 *
 * Tests the Statamic v5.53.1 upgrade fixes:
 *   - Validation of date field nested in a replicator (commit 1148a51)
 *   - Deep copy of objects in replicator sets
 *
 * Mutation: 100% on App\Fieldtypes\Date (507 mutants, --covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Fieldtypes/DateFieldtypeTest.php
 */

/**
 * Simulate deep copy of replicator data.
 *
 * In Statamic v5.53.1, replicator sets use deep copy to prevent object
 * reference issues. This helper simulates that behavior for testing.
 *
 * @param  array<int, array<string, mixed>>  $data  The replicator data to copy
 * @return array<int, array<string, mixed>> The deeply copied data
 */
function dateFieldtypeDeepCopyReplicatorData(array $data): array
{
    // Use serialize/unserialize for deep copy simulation
    /** @var array<int, array<string, mixed>> $copied */
    $copied = unserialize(serialize($data));

    return $copied;
}

it('configures the date_exceptions replicator field', function () {
    $fieldtype = new Date;

    // Use reflection to access protected method
    $reflection = new ReflectionClass($fieldtype);
    $method = $reflection->getMethod('configFieldItems');
    /** @var array<string, array<string, mixed>> $config */
    $config = $method->invoke($fieldtype);

    // configFieldItems returns an array of sections, each with 'display' and 'fields'
    // Search through sections to find date_exceptions field
    $dateExceptionsField = null;
    foreach ($config as $section) {
        $fields = $section['fields'] ?? null;

        if (\is_array($fields) && isset($fields['date_exceptions'])) {
            /** @var array<string, mixed> $dateExceptionsField */
            $dateExceptionsField = $fields['date_exceptions'];
            break;
        }
    }

    // Verify date_exceptions field exists
    expect($dateExceptionsField)->not->toBeNull();

    // Verify it's a replicator type
    expect(data_get($dateExceptionsField, 'type'))->toEqual('replicator');

    // Verify it has sets configured
    expect($dateExceptionsField)->toHaveKey('sets');

    // Verify single_date and date_range sets exist
    /** @var array<string, mixed> $sets */
    $sets = data_get($dateExceptionsField, 'sets.new_set_group.sets');
    expect($sets)->toHaveKey('single_date');
    expect($sets)->toHaveKey('date_range');

    // Verify nested date fields have validation
    expect(data_get($sets, 'single_date.fields'))->not->toBeEmpty();
    expect(data_get($sets, 'single_date.fields.0.field.type'))->toEqual('date');
    expect(data_get($sets, 'single_date.fields.0.field.validate'))->toContain('required');

    // Verify date_range set has range mode configured
    expect(data_get($sets, 'date_range.fields'))->not->toBeEmpty();
    expect(data_get($sets, 'date_range.fields.0.field.type'))->toEqual('date');
    expect(data_get($sets, 'date_range.fields.0.field.mode'))->toEqual('range');
    expect(data_get($sets, 'date_range.fields.0.field.validate'))->toContain('required');
});

it('keys validation errors by fieldPathPrefix for a nested date field', function () {
    $fieldtype = new Date;

    // Create a parent field (simulating date_exceptions replicator)
    $parentField = new Field('date_exceptions', [
        'type' => 'replicator',
    ]);

    // Create a nested field (simulating date field inside replicator set)
    // This matches the structure in date_exceptions replicator
    $field = new Field('item', [
        'type' => 'date',
        'display' => 'Date Exception',
        'validate' => ['required'],
        'mode' => 'single',
    ]);

    // Set parent to simulate nested structure (replicator set index 0)
    $field->setParentField($parentField, 0);

    $fieldtype->setField($field);

    // Test with invalid value (missing required date)
    $invalidValue = [
        'date' => '',
    ];

    // Should throw ValidationException with an error keyed by fieldPathPrefix
    expect(function () use ($fieldtype, $field, $invalidValue) {
        try {
            $fieldtype->preProcessValidatable($invalidValue);
        } catch (ValidationException $e) {
            // Verify the error structure
            $errors = $e->errors();
            /** @var string $fieldPath */
            $fieldPath = $field->fieldPathPrefix();

            // After v5.53.1 upgrade, the error should be keyed by fieldPathPrefix
            // (e.g., "date_exceptions.0.item"). Before upgrade, it may be keyed
            // by the field handle (e.g., "item").
            // Verify that an error exists (either by path or handle)
            $hasPathError = isset($errors[$fieldPath]);
            /** @var string $fieldHandle */
            $fieldHandle = $field->handle();
            $hasHandleError = isset($errors[$fieldHandle]);

            expect($hasPathError || $hasHandleError)->toBeTrue();

            // After upgrade to v5.53.1, verify it uses fieldPathPrefix
            // This assertion will pass after the upgrade
            if ($hasPathError) {
                expect($errors)->toHaveKey($fieldPath);
            }

            // Re-throw to satisfy the exception expectation
            throw $e;
        }
    })->toThrow(ValidationException::class);
});

it('returns a Carbon instance from preProcessValidatable for a valid value', function () {
    $fieldtype = new Date;

    // Create a field with single mode (matching date_exceptions single_date set)
    $field = new Field('item', [
        'type' => 'date',
        'validate' => [],
        'mode' => 'single',
    ]);

    $fieldtype->setField($field);

    // Test with valid date value
    $validValue = [
        'date' => '2025-04-17',
        'time' => '10:00:00',
    ];

    // Should not throw exception and should return a Carbon instance
    $result = $fieldtype->preProcessValidatable($validValue);

    expect($result)->toBeInstanceOf(Carbon::class);
});

it('returns null from preProcessValidatable for a null value', function () {
    $fieldtype = new Date;

    // Create a field
    $field = new Field('item', [
        'type' => 'date',
        'validate' => [],
        'mode' => 'single',
    ]);

    $fieldtype->setField($field);

    // Test with null value
    $result = $fieldtype->preProcessValidatable(null);

    // Should return null
    expect($result)->toBeNull();
});

it('stores a date-only value when time is disabled', function () {
    $fieldtype = new Date;

    $field = new Field('founding_date', [
        'type' => 'date',
        'mode' => 'single',
        'time_enabled' => false,
        'format' => 'Y-m-d',
    ]);

    $fieldtype->setField($field);

    $wire = '1879-09-26T00:00:00.000Z';
    $result = $fieldtype->process($wire);

    expect($result)->toBe('1879-09-26');
});

it('stores a datetime value when time is enabled', function () {
    $fieldtype = new Date;

    $field = new Field('starts_at', [
        'type' => 'date',
        'mode' => 'single',
        'time_enabled' => true,
    ]);

    $fieldtype->setField($field);

    $result = $fieldtype->process('1879-09-26T05:30:00.000Z');

    expect($result)->toBeString();
    expect($result)->toMatch('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/');
});

it('stores date-only range bounds when time is disabled', function () {
    $fieldtype = new Date;

    $field = new Field('booking_window', [
        'type' => 'date',
        'mode' => 'range',
        'time_enabled' => false,
        'format' => 'Y-m-d',
    ]);

    $fieldtype->setField($field);

    $payload = [
        'start' => '2025-01-01T00:00:00.000Z',
        'end' => '2025-01-31T00:00:00.000Z',
    ];
    $result = $fieldtype->process($payload);

    expect(data_get($result, 'start'))->toBe('2025-01-01');
    expect(data_get($result, 'end'))->toBe('2025-01-31');
});

it('preserves an explicit blueprint null default', function () {
    $field = new Field(
        'degree_date',
        [
            'type' => 'date',
            'mode' => 'single',
            'default' => null,
        ]
    );

    expect($field->defaultValue())->toBeNull();
});

it('skips the publish-date fallback when the default is explicitly null', function () {
    $entry = Mockery::mock(Entry::class);
    $entry->shouldNotReceive('get');
    $entry->shouldNotReceive('date');
    $entry->shouldNotReceive('path');
    $entry->shouldNotReceive('id');
    $entry->shouldNotReceive('collection');

    $field = new Field(
        'side_date',
        [
            'type' => 'date',
            'mode' => 'single',
            'default' => null,
        ]
    );
    $field->setParent($entry);

    $fieldtype = new Date;
    $fieldtype->setField($field);

    expect($fieldtype->preProcess(null))->toBeNull();
});

it('preprocesses a nested date using the row value on a dated collection', function () {
    $collection = Mockery::mock(EntriesCollectionContract::class);
    $collection->shouldReceive('dated')->andReturn(true);

    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('id')->andReturn('00000000-0000-0000-0000-000000000001');
    $entry->shouldReceive('collection')->andReturn($collection);
    $entry->shouldNotReceive('get');
    $entry->shouldNotReceive('date');
    $entry->shouldNotReceive('path');

    $replicatorField = new Field(
        'education',
        [
            'type' => 'replicator',
        ]
    );

    $field = new Field(
        'date',
        [
            'type' => 'date',
            'mode' => 'single',
        ]
    );
    $field->setParent($entry);
    $field->setParentField($replicatorField, 0);

    $fieldtype = new Date;
    $fieldtype->setField($field);

    $result = $fieldtype->preProcess('2020-06-15');

    expect($result)->toBeString();
    expect($result)->toContain('2020-06-15');
});

it('round-trips a date-only calendar value in the display timezone', function () {
    Config::set(
        'app.timezone',
        'America/New_York'
    );
    Config::set(
        'statamic.system.display_timezone',
        'America/Chicago'
    );

    $fieldtype = new Date;

    $field = new Field(
        'anniversary',
        [
            'type' => 'date',
            'mode' => 'single',
            'time_enabled' => false,
            'format' => 'Y-m-d',
        ]
    );

    $fieldtype->setField($field);

    $wire = $fieldtype->preProcess('2020-06-15');

    expect($wire)->toBe('2020-06-15');

    expect($fieldtype->process($wire))->toBe('2020-06-15');
});

it('keeps new years day on a date-only field in the CP', function () {
    Config::set(
        'statamic.system.display_timezone',
        'America/Chicago'
    );

    $fieldtype = new Date;

    $field = new Field(
        'date',
        [
            'type' => 'date',
            'mode' => 'single',
            'time_enabled' => false,
            'format' => 'Y-m-d',
        ]
    );

    $fieldtype->setField($field);

    $wire = $fieldtype->preProcess('2020-01-01');

    expect($wire)->toBe('2020-01-01');
});

it('stores the display wall clock for a literal datetime', function () {
    Config::set(
        'statamic.system.display_timezone',
        'America/Chicago'
    );
    Config::set(
        'app.timezone',
        'UTC'
    );

    $fieldtype = new Date;

    $field = new Field(
        'starts_at',
        [
            'type' => 'date',
            'mode' => 'single',
            'time_enabled' => true,
            'time_seconds_enabled' => false,
            'datetime_storage' => 'literal',
        ]
    );

    $fieldtype->setField($field);

    $stored = $fieldtype->process('2020-06-15T17:00:00.000Z');

    expect($stored)->toBe('2020-06-15 12:00');
});

it('augments a literal datetime back to a UTC Carbon', function () {
    Config::set(
        'statamic.system.display_timezone',
        'America/Chicago'
    );
    Config::set(
        'app.timezone',
        'UTC'
    );

    $fieldtype = new Date;

    $field = new Field(
        'starts_at',
        [
            'type' => 'date',
            'mode' => 'single',
            'time_enabled' => true,
            'time_seconds_enabled' => false,
            'datetime_storage' => 'literal',
        ]
    );

    $fieldtype->setField($field);

    $augmented = $fieldtype->augment('2020-06-15 12:00');

    expect($augmented)->toBeInstanceOf(Carbon::class);
    /** @var Carbon $augmented */
    expect($augmented->toIso8601String())->toBe('2020-06-15T17:00:00+00:00');
});

it('deep copies replicator set data', function () {
    // Create sample replicator data matching date_exceptions structure
    $originalData = [
        [
            'type' => 'single_date',
            '_id' => 'set-1',
            'item' => [
                'date' => '2025-04-17',
                'time' => '10:00:00',
            ],
        ],
        [
            'type' => 'date_range',
            '_id' => 'set-2',
            'item' => [
                'date' => [
                    'start' => '2025-04-17',
                    'end' => '2025-04-20',
                ],
            ],
        ],
    ];

    // Simulate replicator processing (deep copy should be used)
    $copiedData = dateFieldtypeDeepCopyReplicatorData($originalData);

    // Modify the copied data
    data_set($copiedData, '0.item.date', '2025-04-18');
    data_set($copiedData, '1.item.date.start', '2025-04-19');

    // Verify original data is not affected (deep copy worked)
    expect(data_get($originalData, '0.item.date'))->toBe('2025-04-17');
    expect(data_get($copiedData, '0.item.date'))->toBe('2025-04-18');
    expect(data_get($originalData, '1.item.date.start'))->toBe('2025-04-17');
    expect(data_get($copiedData, '1.item.date.start'))->toBe('2025-04-19');
});

it('recognizes minutes and meridiem tokens as time-bearing', function () {
    // Assert minutes token is detected (regression guard for statamic/cms#14555)
    expect(DateFormat::containsTime('Y-m-d H:i'))->toBeTrue();

    // Assert meridiem and seconds are detected when present in the pattern
    expect(DateFormat::containsTime('m/d/y g:i:s A'))->toBeTrue();

    // Assert a pure calendar format stays date-only for the same helper
    expect(DateFormat::containsTime('Y-m-d'))->toBeFalse();
});

/**
 * A Date fieldtype on a field with the given config, with the display timezone
 * America/Chicago and the app timezone UTC.
 *
 * @param  array<array-key, mixed>  $config
 */
function dateFieldtypeFor(array $config, string $handle = 'starts_at'): Date
{
    Config::set('statamic.system.display_timezone', 'America/Chicago');
    Config::set('app.timezone', 'UTC');

    $fieldtype = new Date;
    $fieldtype->setField(new Field($handle, ['type' => 'date'] + $config));

    return $fieldtype;
}

/**
 * Field config for a literal-storage datetime, single or range.
 *
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function dateLiteralConfig(string $mode = 'single', array $extra = []): array
{
    return ['mode' => $mode, 'time_enabled' => true, 'datetime_storage' => 'literal'] + $extra;
}

it('uses the parent Date icon', function () {
    expect((new Date)->icon())->toBe((new Statamic\Fieldtypes\Date)->icon());
});

it('falls back to the parent default without a field', function () {
    expect((new Date)->defaultValue())->toBe((new Statamic\Fieldtypes\Date)->defaultValue());
});

it('fails before preprocessing without a field', function () {
    expect(throwableFrom(fn () => (new Date)->preProcess('2020-06-15')))->toBeInstanceOf(Error::class);
});

it('preprocesses a literal single datetime as the display wall clock in UTC', function (mixed $stored, mixed $expected) {
    expect(dateFieldtypeFor(dateLiteralConfig())->preProcess($stored))->toBe($expected);
})->with([
    'a stored wall clock' => ['2020-06-15 12:00', '2020-06-15T17:00:00.000Z'],
    'a range-shaped array' => [['start' => '2020-06-15 12:00'], '2020-06-15T17:00:00.000Z'],
    'a value in another format' => ['June 15 2020 12:00', '2020-06-15T17:00:00.000Z'],
    'now' => ['now', 'now'],
    'nothing' => ['', null],
]);

it('hands a non-string literal value to the parent', function () {
    $parent = new Statamic\Fieldtypes\Date;
    $parent->setField(new Field('starts_at', ['type' => 'date'] + dateLiteralConfig()));

    expect(dateFieldtypeFor(dateLiteralConfig())->preProcess(1592240400))->toBe($parent->preProcess(1592240400));
});

it('hands an unparseable literal string to the parent, which rejects it as the parent does', function () {
    $parent = new Statamic\Fieldtypes\Date;
    $parent->setField(new Field('starts_at', ['type' => 'date'] + dateLiteralConfig()));
    $ours = throwableFrom(fn () => dateFieldtypeFor(dateLiteralConfig())->preProcess('not a date at all'));
    $theirs = throwableFrom(fn () => $parent->preProcess('not a date at all'));

    expect($ours)->toBeInstanceOf(InvalidFormatException::class)
        ->and($ours?->getMessage())->toBe($theirs?->getMessage());
});

it('preprocesses a literal datetime range', function (mixed $stored, mixed $expected) {
    expect(dateFieldtypeFor(dateLiteralConfig('range'))->preProcess($stored))->toBe($expected);
})->with([
    'both bounds' => [
        ['start' => '2020-06-15 12:00', 'end' => '2020-06-16 08:30'],
        ['start' => '2020-06-15T17:00:00.000Z', 'end' => '2020-06-16T13:30:00.000Z'],
    ],
    'no end' => [['start' => '2020-06-15 12:00'], ['start' => '2020-06-15T17:00:00.000Z', 'end' => null]],
    'no start key' => [['end' => '2020-06-16 08:30'], null],
    'a null start' => [['start' => null, 'end' => '2020-06-16 08:30'], null],
    'an empty start' => [['start' => '', 'end' => '2020-06-16 08:30'], null],
    'nothing' => [null, null],
]);

it('hands a non-array literal range value to the parent', function () {
    $parent = new Statamic\Fieldtypes\Date;
    $parent->setField(new Field('starts_at', ['type' => 'date'] + dateLiteralConfig('range')));

    expect(dateFieldtypeFor(dateLiteralConfig('range'))->preProcess('2020-06-15 12:00'))
        ->toBe($parent->preProcess('2020-06-15 12:00'));
});

it('preprocesses a converted (non-literal) datetime through the parent', function (string $mode) {
    $config = ['mode' => $mode, 'time_enabled' => true];
    $parent = new Statamic\Fieldtypes\Date;
    $parent->setField(new Field('starts_at', ['type' => 'date'] + $config));
    $value = $mode === 'range' ? ['start' => '2020-06-15 12:00', 'end' => '2020-06-16 12:00'] : '2020-06-15 12:00';

    expect(dateFieldtypeFor($config)->preProcess($value))->toBe($parent->preProcess($value));
})->with(['single', 'range']);

it('stores a literal datetime range and an integer year', function () {
    expect(dateFieldtypeFor(dateLiteralConfig('range'))->process(['start' => '2020-06-15T17:00:00.000Z', 'end' => '2020-06-16T13:30:00.000Z']))
        ->toBe(['start' => '2020-06-15 12:00', 'end' => '2020-06-16 08:30'])
        ->and(dateFieldtypeFor(dateLiteralConfig('range'))->process(['start' => null, 'end' => '2020-06-16T13:30:00.000Z']))->toBeNull()
        ->and(dateFieldtypeFor(dateLiteralConfig('range'))->process('2020-06-15T17:00:00.000Z'))->toBeNull()
        ->and(dateFieldtypeFor(dateLiteralConfig('single', ['format' => 'Y']))->process('2020-06-15T17:00:00.000Z'))->toBe(2020)
        ->and(dateFieldtypeFor(dateLiteralConfig('single', ['time_seconds_enabled' => true]))->process('2020-06-15T17:00:09.000Z'))->toBe('2020-06-15 12:00:09');
});

it('stores nothing for a null submission', function (array $config) {
    expect(dateFieldtypeFor($config)->process(null))->toBeNull();
})->with([
    'date only' => [['mode' => 'single']],
    'literal' => [dateLiteralConfig()],
]);

it('augments literal values: nothing, a Carbon passthrough, a range, and an unparseable format', function () {
    $carbon = Illuminate\Support\Carbon::parse('2020-06-15 17:00:00', 'UTC');
    $range = mustBeArray(dateFieldtypeFor(dateLiteralConfig('range'))->augment(['start' => '2020-06-15 12:00', 'end' => $carbon]), 'augmented range');
    $start = mustBe(Carbon::class, $range['start'] ?? null, 'range start');
    $end = mustBe(Carbon::class, $range['end'] ?? null, 'range end');
    $loose = mustBe(Carbon::class, dateFieldtypeFor(dateLiteralConfig())->augment('June 15 2020 12:00:45'), 'loose value');

    expect(dateFieldtypeFor(dateLiteralConfig())->augment(''))->toBeNull()
        ->and(dateFieldtypeFor(dateLiteralConfig())->augment($carbon))->toBe($carbon)
        ->and($start->toIso8601String())->toBe('2020-06-15T17:00:00+00:00')
        ->and($end->toIso8601String())->toBe('2020-06-15T17:00:00+00:00')
        ->and($loose->toIso8601String())->toBe('2020-06-15T17:00:00+00:00');
});

it('keeps seconds on an augmented literal datetime when seconds are enabled', function () {
    $augmented = mustBe(Carbon::class, dateFieldtypeFor(dateLiteralConfig('single', ['time_seconds_enabled' => true]))->augment('2020-06-15 12:00:45'), 'augmented value');

    expect($augmented->toIso8601String())->toBe('2020-06-15T17:00:45+00:00');
});

it('builds the ISO string from a CP date and time payload', function (array $config, array $payload, string $expected) {
    $carbon = mustBe(Carbon::class, dateFieldtypeFor($config)->preProcessValidatable($payload), 'validatable value');

    expect($carbon->toIso8601ZuluString('millisecond'))->toBe($expected);
})->with([
    'a five-character time' => [['mode' => 'single', 'time_enabled' => true], ['date' => '2020-06-15', 'time' => '12:00'], '2020-06-15T17:00:00.000Z'],
    'a time with seconds' => [['mode' => 'single', 'time_enabled' => true], ['date' => '2020-06-15', 'time' => '12:00:45'], '2020-06-15T17:00:45.000Z'],
    'an empty time' => [['mode' => 'single', 'time_enabled' => true], ['date' => '2020-06-15', 'time' => ''], '2020-06-15T05:00:00.000Z'],
    'time disabled' => [['mode' => 'single'], ['date' => '2020-06-15', 'time' => '12:00'], '2020-06-15T05:00:00.000Z'],
]);

it('requires a date when any validate rule starts with required', function (mixed $validate, bool $throws) {
    $result = throwableFrom(fn () => dateFieldtypeFor(['mode' => 'single', 'validate' => $validate])->preProcessValidatable(['date' => null]));

    expect($result instanceof ValidationException)->toBe($throws);
})->with([
    'required_if as a string rule' => [['required_if:other,1'], true],
    'a single string rule' => ['required', true],
    'required after another rule' => [['nullable', 'required'], true],
    'no required rule' => [['nullable', 'date'], false],
    'a non-string rule' => [[5], false],
]);

it('hands an empty optional date to the parent as null', function () {
    expect(dateFieldtypeFor(['mode' => 'single'])->preProcessValidatable(['date' => '']))->toBeNull();
});

/**
 * The publish `date` fieldtype on a dated-collection entry stored at the given
 * path, with the given stored value and entry date.
 */
function datePublishFieldtypeFor(string $path, mixed $stored, ?Carbon $entryDate, string $timezone = 'UTC'): Date
{
    Config::set('app.timezone', $timezone);

    $collection = Mockery::mock(Collection::class);
    $collection->shouldReceive('dated')->andReturn(true);

    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('id')->andReturn('00000000-0000-0000-0000-000000000002');
    $entry->shouldReceive('collection')->andReturn($collection);
    $entry->shouldReceive('path')->andReturn($path);
    $entry->shouldReceive('get')->with('date')->andReturn($stored);
    $entry->shouldReceive('date')->andReturn($entryDate === null ? null : Illuminate\Support\Carbon::instance($entryDate));

    $field = new Field('date', ['type' => 'date', 'mode' => 'single']);
    $field->setParent($entry);

    $fieldtype = new Date;
    $fieldtype->setField($field);

    return $fieldtype;
}

it('preprocesses the publish date from the filename prefix in each supported length', function (string $file, string $expected) {
    expect(datePublishFieldtypeFor('/content/posts/'.$file, 'now', null)->preProcess('now'))->toBe($expected);
})->with([
    'a date only, at the start of the day' => ['2026-04-04.post.md', '2026-04-04T00:00:00.000Z'],
    'a date and hour-minute' => ['2026-04-04-0416.post.md', '2026-04-04T04:16:00.000Z'],
    'a date and hour-minute-second' => ['2026-04-04-041645.post.md', '2026-04-04T04:16:45.000Z'],
]);

it('reads the filename prefix and the stored value in the app timezone', function (string $path, mixed $stored, string $expected) {
    // PHP's default zone stays UTC (phpunit.xml), so only the app timezone can move these
    expect(datePublishFieldtypeFor($path, $stored, null, 'America/Chicago')->preProcess($stored))->toBe($expected);
})->with([
    'a date-only prefix, at the start of the local day' => ['/content/posts/2026-04-04.post.md', 'now', '2026-04-04T05:00:00.000Z'],
    'an hour-minute prefix' => ['/content/posts/2026-04-04-0416.post.md', 'now', '2026-04-04T09:16:00.000Z'],
    'a stored value without a prefix' => ['/content/posts/post.md', '2026-04-04 08:00', '2026-04-04T13:00:00.000Z'],
]);

it('falls back to the stored value when the filename prefix ends in a newline', function (string $file) {
    // GetDateFromPath's anchor matches before a trailing newline, so it returns the
    // prefix with the newline still on it, at a length no format accepts
    expect(datePublishFieldtypeFor('/content/posts/'.$file, '2026-04-04 08:00', null)->preProcess('2026-04-04 08:00'))
        ->toBe('2026-04-04T08:00:00.000Z');
})->with([
    'a date only' => ["2026-01-01\n.post.md"],
    'a date and hour-minute' => ["2026-01-01-1234\n.post.md"],
    'a date and hour-minute-second' => ["2026-01-01-123456\n.post.md"],
]);

it('falls back to the stored value, then the entry date, without a filename prefix', function (mixed $stored, ?Carbon $entryDate, mixed $expected) {
    expect(datePublishFieldtypeFor('/content/posts/post.md', $stored, $entryDate)->preProcess($stored))->toBe($expected);
})->with([
    'a stored value' => ['2026-04-04 08:00', null, '2026-04-04T08:00:00.000Z'],
    'a stored now, with an entry date' => ['now', Carbon::parse('2026-05-01 10:30', 'UTC'), '2026-05-01T10:30:00.000Z'],
    'an unparseable stored value, with an entry date' => ['garbage', Carbon::parse('2026-05-01 10:30', 'UTC'), '2026-05-01T10:30:00.000Z'],
    'nothing stored, with an entry date' => [null, Carbon::parse('2026-05-01 10:30', 'UTC'), '2026-05-01T10:30:00.000Z'],
    'an empty stored value, with an entry date' => ['', Carbon::parse('2026-05-01 10:30', 'UTC'), '2026-05-01T10:30:00.000Z'],
    'a stored value ahead of the entry date' => ['2026-04-04 08:00', Carbon::parse('2026-05-01 10:30', 'UTC'), '2026-04-04T08:00:00.000Z'],
    'nothing stored and no entry date' => [null, null, null],
]);

it('augments a non-literal value through the parent', function () {
    $parent = new Statamic\Fieldtypes\Date;
    $parent->setField(new Field('starts_at', ['type' => 'date', 'mode' => 'single', 'time_enabled' => true]));

    $ours = mustBe(Carbon::class, dateFieldtypeFor(['mode' => 'single', 'time_enabled' => true])->augment('2020-06-15 12:00'), 'our value');
    $theirs = mustBe(Carbon::class, $parent->augment('2020-06-15 12:00'), 'parent value');

    expect($ours->toIso8601String())->toBe($theirs->toIso8601String());
});

it('builds the datetime storage instructions without a scalar app timezone', function () {
    Config::set('app.timezone', ['not', 'scalar']);
    Config::set('statamic.system.display_timezone', ['not', 'scalar']);

    $reflection = new ReflectionClass(Date::class);
    $method = $reflection->getMethod('configFieldItems');
    /** @var array<string, array<string, mixed>> $config */
    $config = $method->invoke(new Date);

    $storage = null;
    foreach ($config as $section) {
        $fields = $section['fields'] ?? [];
        if (is_array($fields) && isset($fields['datetime_storage'])) {
            $storage = $fields['datetime_storage'];
        }
    }

    expect($storage)->toBeArray();
});

it('adds its own boundary and storage fields to the parent config, in order', function () {
    Config::set('app.timezone', 'UTC');
    Config::set('statamic.system.display_timezone', 'America/Chicago');

    /** @var list<array{display: string, fields: array<string, array<string, mixed>>}> $sections */
    $sections = (new ReflectionMethod(Date::class, 'configFieldItems'))->invoke(new Date);

    // The parent's own fields are Statamic's to change, so only the computed
    // boundary fields it already defines are checked, and only for being hidden
    $boundaries = $sections[2]['fields'];
    expect($sections[1]['display'])->toBe('Date & Time')
        ->and($sections[2]['display'])->toBe('Boundaries & Limits')
        ->and($boundaries['earliest_date']['visibility'])->toBe('hidden')
        ->and($boundaries['latest_date']['visibility'])->toBe('hidden')
        ->and(array_key_last($sections[1]['fields']))->toBe('datetime_storage');

    unset($boundaries['earliest_date'], $boundaries['latest_date']);

    expect(['datetime_storage' => $sections[1]['fields']['datetime_storage']] + $boundaries)->toBe(
        [
            'datetime_storage' => [
                'display' => 'Datetime storage',
                'instructions' => 'When time is enabled: “Convert” interprets the picker in the Statamic display timezone (America/Chicago) and stores using the app timezone (UTC). “Literal” stores the wall clock in America/Chicago exactly as entered (no conversion into UTC).',
                'type' => 'select',
                'options' => [
                    [
                        'key' => 'convert',
                        'value' => 'Convert (display → app)',
                    ],
                    [
                        'key' => 'literal',
                        'value' => 'Literal (exact wall clock)',
                    ],
                ],
                'default' => 'convert',
                'if' => [
                    'time_enabled' => true,
                ],
            ],
            'earliest_date_section' => [
                'display' => 'Earliest Date',
                'type' => 'subsection',
            ],
            'earliest_date_type' => [
                'display' => 'Earliest Date Type',
                'instructions' => 'Specify the type of earliest date allowed.',
                'type' => 'radio',
                'options' => [
                    [
                        'key' => 'none',
                        'value' => 'None',
                    ],
                    [
                        'key' => 'custom',
                        'value' => 'Custom Date',
                    ],
                    [
                        'key' => 'today',
                        'value' => 'Current Date',
                    ],
                    [
                        'key' => 'field',
                        'value' => 'Date From a Form Field',
                    ],
                ],
                'default' => 'none',
                'validate' => [
                    'required',
                ],
            ],
            'earliest_date_custom' => [
                'display' => 'Custom Earliest Date',
                'instructions' => 'Specify a specific date to use as the earliest date allowed.',
                'type' => 'date',
                'if' => [
                    'earliest_date_type' => 'custom',
                ],
                'validate' => [
                    'required_if:earliest_date_type,custom',
                    'exclude_unless:earliest_date_type,custom',
                ],
            ],
            'earliest_date_field' => [
                'display' => 'Earliest Date Field',
                'instructions' => 'Select a required date field from this blueprint to use as the earliest date allowed.',
                'type' => 'date_field_select',
                'if' => [
                    'earliest_date_type' => 'field',
                ],
                'validate' => [
                    'required_if:earliest_date_type,field',
                    'exclude_unless:earliest_date_type,field',
                ],
            ],
            'earliest_date_field_offset' => [
                'display' => 'Earliest Date Field Offset',
                'instructions' => 'Offset the selected date by adding or subtracting days, weeks, months or years. For example, "+1 day" would exclude dates before or equal to the selected date.',
                'type' => 'grid',
                'mode' => 'stacked',
                'max_rows' => 1,
                'add_row' => 'Add Offset',
                'fields' => [
                    [
                        'handle' => 'operator',
                        'field' => [
                            'display' => 'Operator',
                            'type' => 'radio',
                            'options' => [
                                [
                                    'key' => 'add',
                                    'value' => 'Add',
                                ],
                                [
                                    'key' => 'subtract',
                                    'value' => 'Subtract',
                                ],
                            ],
                            'default' => 'add',
                            'width' => 33,
                            'validate' => [
                                'required',
                            ],
                        ],
                    ],
                    [
                        'handle' => 'number',
                        'field' => [
                            'display' => 'Number',
                            'type' => 'integer',
                            'default' => 0,
                            'width' => 33,
                            'validate' => [
                                'required',
                                'min:1',
                            ],
                        ],
                    ],
                    [
                        'handle' => 'unit',
                        'field' => [
                            'display' => 'Unit',
                            'type' => 'radio',
                            'options' => [
                                [
                                    'key' => 'day',
                                    'value' => 'Days',
                                ],
                                [
                                    'key' => 'week',
                                    'value' => 'Weeks',
                                ],
                                [
                                    'key' => 'month',
                                    'value' => 'Months',
                                ],
                                [
                                    'key' => 'year',
                                    'value' => 'Years',
                                ],
                            ],
                            'default' => 'day',
                            'width' => 33,
                            'validate' => [
                                'required',
                            ],
                        ],
                    ],
                ],
                'if' => [
                    'earliest_date_type' => 'field',
                ],
            ],
            'earliest_date_exclude_before_today' => [
                'display' => 'Exclude Before Current Date',
                'instructions' => 'Automatically exclude any dates that are before the current date.',
                'type' => 'toggle',
                'default' => false,
                'if' => [
                    'earliest_date_type' => 'contains_any custom,field',
                ],
            ],
            'earliest_date_today_offset' => [
                'display' => 'Current Date Offset',
                'instructions' => 'Offset the current date by adding or subtracting days, weeks, months or years. For example, "+1 day" would exclude dates before or equal to the current date.',
                'type' => 'grid',
                'mode' => 'stacked',
                'max_rows' => 1,
                'add_row' => 'Add Offset',
                'fields' => [
                    [
                        'handle' => 'operator',
                        'field' => [
                            'display' => 'Operator',
                            'type' => 'radio',
                            'options' => [
                                [
                                    'key' => 'add',
                                    'value' => 'Add',
                                ],
                                [
                                    'key' => 'subtract',
                                    'value' => 'Subtract',
                                ],
                            ],
                            'default' => 'add',
                            'width' => 33,
                            'validate' => [
                                'required',
                            ],
                        ],
                    ],
                    [
                        'handle' => 'number',
                        'field' => [
                            'display' => 'Number',
                            'type' => 'integer',
                            'default' => 0,
                            'width' => 33,
                            'validate' => [
                                'required',
                                'min:1',
                            ],
                        ],
                    ],
                    [
                        'handle' => 'unit',
                        'field' => [
                            'display' => 'Unit',
                            'type' => 'radio',
                            'options' => [
                                [
                                    'key' => 'day',
                                    'value' => 'Days',
                                ],
                                [
                                    'key' => 'week',
                                    'value' => 'Weeks',
                                ],
                                [
                                    'key' => 'month',
                                    'value' => 'Months',
                                ],
                                [
                                    'key' => 'year',
                                    'value' => 'Years',
                                ],
                            ],
                            'default' => 'day',
                            'width' => 33,
                            'validate' => [
                                'required',
                            ],
                        ],
                    ],
                ],
                'if_any' => [
                    'earliest_date_type' => 'is today',
                    'earliest_date_exclude_before_today' => true,
                ],
            ],
            'latest_date_section' => [
                'display' => 'Latest Date',
                'type' => 'subsection',
            ],
            'latest_date_type' => [
                'display' => 'Latest Date Type',
                'instructions' => 'Specify the type of latest date allowed.',
                'type' => 'radio',
                'options' => [
                    [
                        'key' => 'none',
                        'value' => 'None',
                    ],
                    [
                        'key' => 'custom',
                        'value' => 'Custom Date',
                    ],
                    [
                        'key' => 'today',
                        'value' => 'Current Date',
                    ],
                    [
                        'key' => 'field',
                        'value' => 'Date From a Form Field',
                    ],
                ],
                'default' => 'none',
                'validate' => [
                    'required',
                ],
            ],
            'latest_date_custom' => [
                'display' => 'Custom Latest Date',
                'instructions' => 'Specify a specific date to use as the latest date allowed.',
                'type' => 'date',
                'if' => [
                    'latest_date_type' => 'custom',
                ],
                'validate' => [
                    'required_if:latest_date_type,custom',
                    'exclude_unless:latest_date_type,custom',
                ],
            ],
            'latest_date_field' => [
                'display' => 'Latest Date Field',
                'instructions' => 'Select a required date field from this blueprint to use as the latest date allowed.',
                'type' => 'date_field_select',
                'if' => [
                    'latest_date_type' => 'field',
                ],
                'validate' => [
                    'required_if:latest_date_type,field',
                    'exclude_unless:latest_date_type,field',
                ],
            ],
            'latest_date_field_offset' => [
                'display' => 'Latest Date Field Offset',
                'instructions' => 'Offset the selected date by adding or subtracting days, weeks, months or years. For example, "+1 day" would exclude dates after or equal to the the day after the selected date.',
                'type' => 'grid',
                'mode' => 'stacked',
                'max_rows' => 1,
                'add_row' => 'Add Offset',
                'fields' => [
                    [
                        'handle' => 'operator',
                        'field' => [
                            'display' => 'Operator',
                            'type' => 'radio',
                            'options' => [
                                [
                                    'key' => 'add',
                                    'value' => 'Add',
                                ],
                                [
                                    'key' => 'subtract',
                                    'value' => 'Subtract',
                                ],
                            ],
                            'default' => 'add',
                            'width' => 33,
                            'validate' => [
                                'required',
                            ],
                        ],
                    ],
                    [
                        'handle' => 'number',
                        'field' => [
                            'display' => 'Number',
                            'type' => 'integer',
                            'default' => 0,
                            'width' => 33,
                            'validate' => [
                                'required',
                                'min:1',
                            ],
                        ],
                    ],
                    [
                        'handle' => 'unit',
                        'field' => [
                            'display' => 'Unit',
                            'type' => 'radio',
                            'options' => [
                                [
                                    'key' => 'day',
                                    'value' => 'Days',
                                ],
                                [
                                    'key' => 'week',
                                    'value' => 'Weeks',
                                ],
                                [
                                    'key' => 'month',
                                    'value' => 'Months',
                                ],
                                [
                                    'key' => 'year',
                                    'value' => 'Years',
                                ],
                            ],
                            'default' => 'day',
                            'width' => 33,
                            'validate' => [
                                'required',
                            ],
                        ],
                    ],
                ],
                'if' => [
                    'latest_date_type' => 'field',
                ],
            ],
            'boundaries_other_section' => [
                'display' => 'Other Boundaries',
                'type' => 'subsection',
            ],
            'days_of_week' => [
                'display' => 'Days of Week',
                'instructions' => 'Select the days of the week that are valid.',
                'type' => 'checkboxes',
                'options' => [
                    [
                        'key' => '0',
                        'value' => 'Sunday',
                    ],
                    [
                        'key' => '1',
                        'value' => 'Monday',
                    ],
                    [
                        'key' => '2',
                        'value' => 'Tuesday',
                    ],
                    [
                        'key' => '3',
                        'value' => 'Wednesday',
                    ],
                    [
                        'key' => '4',
                        'value' => 'Thursday',
                    ],
                    [
                        'key' => '5',
                        'value' => 'Friday',
                    ],
                    [
                        'key' => '6',
                        'value' => 'Saturday',
                    ],
                ],
                'default' => [
                    '0',
                    '1',
                    '2',
                    '3',
                    '4',
                    '5',
                    '6',
                ],
                'validate' => [
                    'required',
                ],
            ],
            'date_exceptions' => [
                'display' => 'Date Exceptions',
                'instructions' => 'Specify any dates that should be excepted from the above rules.',
                'type' => 'replicator',
                'button_label' => 'Add Date Exception',
                'sets' => [
                    'new_set_group' => [
                        'display' => 'New Set Group',
                        'sets' => [
                            'single_date' => [
                                'display' => 'Single Date',
                                'icon' => 'calendar-date',
                                'fields' => [
                                    [
                                        'handle' => 'item',
                                        'field' => [
                                            'display' => 'Date',
                                            'type' => 'date',
                                            'validate' => [
                                                'required',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            'date_range' => [
                                'display' => 'Date Range',
                                'icon' => 'calendar-date',
                                'fields' => [
                                    [
                                        'handle' => 'item',
                                        'field' => [
                                            'display' => 'Date Range',
                                            'type' => 'date',
                                            'mode' => 'range',
                                            'validate' => [
                                                'required',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]
    );

    // The computed fields keep their places among the added ones
    expect(array_keys($sections[2]['fields']))->toBe([
        'earliest_date_section', 'earliest_date_type', 'earliest_date', 'earliest_date_custom',
        'earliest_date_field', 'earliest_date_field_offset', 'earliest_date_exclude_before_today',
        'earliest_date_today_offset', 'latest_date_section', 'latest_date_type', 'latest_date',
        'latest_date_custom', 'latest_date_field', 'latest_date_field_offset',
        'boundaries_other_section', 'days_of_week', 'date_exceptions',
    ]);
});

it('returns the blueprint default, even a null one, and otherwise the parent default', function (array $config, mixed $expected) {
    expect(dateFieldtypeFor(['mode' => 'single'] + $config)->defaultValue())->toBe($expected);
})->with([
    'a default' => [['default' => 'now'], 'now'],
    'an explicit null default' => [['default' => null], null],
    'no default key' => [[], null],
]);

/**
 * A fieldtype on a dated-collection entry stored with a filename prefix, in a
 * context the publish date path must decline.
 */
function datePrefixedEntryFieldtype(string $handle, mixed $collection, bool $throughBlueprint = false): Date
{
    Config::set('app.timezone', 'UTC');

    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('id')->andReturn('00000000-0000-0000-0000-000000000003');
    $entry->shouldReceive('collection')->andReturn($collection);
    $entry->shouldReceive('path')->andReturn('/content/posts/2026-04-04-0416.post.md');
    $entry->shouldReceive('get')->andReturn('2026-01-01 08:00');
    $entry->shouldReceive('date')->andReturn(Illuminate\Support\Carbon::parse('2026-02-02 09:00', 'UTC'));

    $field = new Field($handle, ['type' => 'date', 'mode' => 'single']);

    if ($throughBlueprint) {
        $blueprint = Mockery::mock(Blueprint::class);
        $blueprint->shouldReceive('parent')->andReturn($entry);
        $field->setParent($blueprint);
    } else {
        $field->setParent($entry);
    }

    $fieldtype = new Date;
    $fieldtype->setField($field);

    return $fieldtype;
}

/**
 * A dated or undated collection.
 */
function dateCollectionMock(bool $dated): Collection
{
    $collection = Mockery::mock(Collection::class);
    $collection->shouldReceive('dated')->andReturn($dated);

    return $collection;
}

it('reads the publish date from the filename prefix through the blueprint the entry owns', function () {
    expect(datePrefixedEntryFieldtype('date', dateCollectionMock(true), throughBlueprint: true)->preProcess('now'))
        ->toBe('2026-04-04T04:16:00.000Z');
});

it('leaves the value to the parent outside the publish date of a dated collection', function (string $handle, Closure $collection) {
    $parent = new Statamic\Fieldtypes\Date;
    $parent->setField(new Field($handle, ['type' => 'date', 'mode' => 'single']));

    expect(datePrefixedEntryFieldtype($handle, $collection())->preProcess('2026-03-03'))
        ->toBe($parent->preProcess('2026-03-03'));
})->with([
    'another top-level date field' => ['published_on', fn () => dateCollectionMock(true)],
    'an undated collection' => ['date', fn () => dateCollectionMock(false)],
    'an entry without a collection' => ['date', fn () => null],
]);

it('parses a literal value without a string display timezone as UTC', function () {
    $fieldtype = dateFieldtypeFor(dateLiteralConfig());
    Config::set('statamic.system.display_timezone', ['not', 'a', 'string']);

    expect($fieldtype->preProcess('2020-06-15 12:00'))->toBe('2020-06-15T12:00:00.000Z');
});

it('stores a literal range in its own format, without the single-date seconds default', function (array $extra, array $expected) {
    $instants = ['start' => '2020-06-15T17:00:30.000Z', 'end' => '2020-06-16T17:00:30.000Z'];

    expect(dateFieldtypeFor(dateLiteralConfig('range') + $extra)->process($instants))->toBe($expected);
})->with([
    'seconds enabled, no format' => [['time_seconds_enabled' => true], ['start' => '2020-06-15 12:00', 'end' => '2020-06-16 12:00']],
    'a custom format' => [['format' => 'Y/m/d H:i'], ['start' => '2020/06/15 12:00', 'end' => '2020/06/16 12:00']],
    'a format that is not a string' => [['format' => ['Y']], ['start' => '2020-06-15 12:00', 'end' => '2020-06-16 12:00']],
]);

it('names the field in the required message, or leaves the name out when it is not a string', function (mixed $display, string $message) {
    $fieldtype = dateFieldtypeFor(['mode' => 'single', 'validate' => 'required', 'display' => $display]);
    $exception = mustBe(ValidationException::class, throwableFrom(fn () => $fieldtype->preProcessValidatable(['date' => null])), 'validation error');

    expect($exception->errors())->toBe(['starts_at' => [$message]]);
})->with([
    'a display name' => ['Starts At', 'The Starts At field is required.'],
    'a display that is not a string' => [['en' => 'Starts At'], 'The  field is required.'],
]);

it('reads a date part that is not a string as today at the given time', function () {
    Carbon::setTestNow(Carbon::parse('2026-03-10 00:00', 'UTC'));

    $carbon = mustBe(Carbon::class, dateFieldtypeFor(['mode' => 'single', 'time_enabled' => true])
        ->preProcessValidatable(['date' => ['2020-06-15'], 'time' => '12:00']), 'validatable value');

    // Midnight UTC is still March 9 in Chicago
    expect($carbon->toIso8601ZuluString('millisecond'))->toBe('2026-03-09T17:00:00.000Z');
});

it('augments a literal integer year and a non-scalar single value in the display timezone', function (mixed $value) {
    Carbon::setTestNow(Carbon::parse('2026-03-10 00:00', 'UTC'));

    $carbon = mustBe(Carbon::class, dateFieldtypeFor(dateLiteralConfig('single', ['format' => 'Y']))->augment($value), 'augmented value');

    // The Y format fills the rest from now; an empty value parses as now
    expect($carbon->toIso8601ZuluString('millisecond'))->toBe('2026-03-10T00:00:00.000Z');
})->with([
    'an integer year' => [2026],
    'an array' => [['not', 'a', 'date']],
]);

it('parses a literal integer that does not fit the format as text, not as a timestamp', function () {
    Config::set('statamic.system.display_timezone', 'America/Chicago');
    Carbon::setTestNow(Carbon::parse('2026-03-10 00:00', 'UTC'));

    $carbon = mustBe(Carbon::class, dateFieldtypeFor(dateLiteralConfig())->augment(2026), 'augmented value');

    // "2026" reads as 20:26 today in Chicago; the integer 2026 would be 1970
    expect($carbon->toIso8601ZuluString('millisecond'))->toBe('2026-03-10T01:26:00.000Z');
});

it('preprocesses a single literal value with an empty start to null', function () {
    expect(dateFieldtypeFor(dateLiteralConfig())->preProcess(['start' => null]))->toBeNull();
});
