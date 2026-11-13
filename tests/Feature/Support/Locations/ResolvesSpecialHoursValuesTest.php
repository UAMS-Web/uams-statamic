<?php

declare(strict_types=1);

use App\Support\Locations\ResolvesSpecialHoursValues;
use Illuminate\Support\Carbon;
use Statamic\Fields\LabeledValue;
use Statamic\Fields\Value;
use Statamic\Fields\Values;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Tests for the value-coercion trait the special-hours consumers share.
 *
 * @command  php artisan test --compact tests/Feature/Support/Locations/ResolvesSpecialHoursValuesTest.php
 */

/** A host that uses the trait and forwards calls to its private helpers. */
function specialHoursHost(): object
{
    return new class
    {
        use ResolvesSpecialHoursValues;

        /**
         * Call one private helper of the trait.
         */
        public function call(string $method, mixed ...$arguments): mixed
        {
            return $this->{$method}(...$arguments);
        }
    };
}

/** Call one private helper of the trait on a fresh host. */
function specialHoursCall(string $method, mixed ...$arguments): mixed
{
    $host = specialHoursHost();

    // The anonymous host exposes `call()`; narrow it for static analysis
    if (! method_exists($host, 'call')) {
        throw new RuntimeException('The special-hours host lost its call() method');
    }

    return $host->call($method, ...$arguments);
}

afterEach(function (): void {
    Carbon::setTestNow();
});

it('normalizes a bound value to a list of rows', function (mixed $raw, array $expected): void {
    expect(specialHoursCall('rows', $raw))->toBe($expected);
})->with([
    'list' => [[['a' => 1], ['b' => 2]], [['a' => 1], ['b' => 2]]],
    'map is re-indexed' => [['x' => 'one', 'y' => 'two'], ['one', 'two']],
    'wrapped list' => [new Value([['a' => 1]]), [['a' => 1]]],
    'empty list' => [[], []],
    'string' => ['rows', []],
    'null' => [null, []],
    'wrapped string' => [new Value('rows'), []],
]);

it('reads a field from a Values set or a plain array row', function (): void {
    $set = new Values(['status' => 'closed']);

    expect(specialHoursCall('rowField', $set, 'status'))->toBe('closed')
        ->and(specialHoursCall('rowField', $set, 'missing'))->toBeNull()
        ->and(specialHoursCall('rowField', ['status' => 'open'], 'status'))->toBe('open')
        ->and(specialHoursCall('rowField', ['status' => 'open'], 'missing'))->toBeNull()
        ->and(specialHoursCall('rowField', 'status', 'status'))->toBeNull()
        ->and(specialHoursCall('rowField', null, 'status'))->toBeNull()
        ->and(specialHoursCall('rowField', new stdClass, 'status'))->toBeNull();
});

it('resolves the set type of a Values set or a plain array row', function (): void {
    expect(specialHoursCall('setType', new Values(['type' => ' holiday '])))->toBe('holiday')
        ->and(specialHoursCall('setType', new Values([])))->toBeNull()
        ->and(specialHoursCall('setType', ['type' => 'temporary_change']))->toBe('temporary_change')
        ->and(specialHoursCall('setType', ['type' => new LabeledValue('holiday', 'Holiday')]))->toBe('holiday')
        ->and(specialHoursCall('setType', []))->toBeNull()
        ->and(specialHoursCall('setType', 'holiday'))->toBeNull()
        ->and(specialHoursCall('setType', null))->toBeNull()
        ->and(specialHoursCall('setType', new stdClass))->toBeNull();
});

it('resolves a rule status from its button-group value', function (): void {
    expect(specialHoursCall('status', ['status' => new LabeledValue('open_24h', 'Open 24 hours')]))->toBe('open_24h')
        ->and(specialHoursCall('status', new Values(['status' => 'closed'])))->toBe('closed')
        ->and(specialHoursCall('status', ['status' => '']))->toBeNull()
        ->and(specialHoursCall('status', []))->toBeNull();
});

it('resolves a rule service mode from its button-group value', function (): void {
    expect(specialHoursCall('serviceMode', ['service_mode' => new LabeledValue('telemedicine', 'Telemedicine')]))->toBe('telemedicine')
        ->and(specialHoursCall('serviceMode', new Values(['service_mode' => 'in_person'])))->toBe('in_person')
        ->and(specialHoursCall('serviceMode', []))->toBeNull();
});

it('labels each known service mode and returns an empty string for an unknown one', function (string $mode, string $expected): void {
    expect(specialHoursCall('serviceModeLabel', $mode))->toBe($expected);
})->with([
    'in person' => ['in_person', 'In-Person'],
    'telemedicine' => ['telemedicine', 'Telemedicine'],
    'unknown' => ['drive_through', ''],
    'empty' => ['', ''],
]);

it('treats only an explicit telemedicine mode as remote', function (mixed $rule, string $expected): void {
    expect(specialHoursCall('ruleMode', $rule))->toBe($expected);
})->with([
    'telemedicine' => [['service_mode' => 'telemedicine'], 'telemedicine'],
    'labeled telemedicine' => [new Values(['service_mode' => new LabeledValue('telemedicine', 'Telemedicine')]), 'telemedicine'],
    'in person' => [['service_mode' => 'in_person'], 'in_person'],
    'absent' => [[], 'in_person'],
    'unknown' => [['service_mode' => 'drive_through'], 'in_person'],
    'not a row' => ['telemedicine', 'in_person'],
]);

it('normalizes a bound date value to Y-m-d', function (mixed $raw, ?string $expected): void {
    expect(specialHoursCall('dateValue', $raw))->toBe($expected);
})->with([
    'carbon' => [Carbon::create(2026, 12, 24, 15, 30, 0), '2026-12-24'],
    'native datetime' => [new DateTimeImmutable('2026-07-04 08:00:00'), '2026-07-04'],
    'wrapped carbon' => [new Value(Carbon::create(2026, 1, 2, 0, 0, 0)), '2026-01-02'],
    'string' => [' 2026-11-26 ', '2026-11-26'],
    'wrapped string' => [new Value('2026-11-27'), '2026-11-27'],
    'empty string' => ['', null],
    'null' => [null, null],
    'array' => [['2026-11-26'], null],
]);

it('coerces a bound value to a clean non-empty string', function (mixed $raw, ?string $expected): void {
    expect(specialHoursCall('scalarValue', $raw))->toBe($expected);
})->with([
    'plain' => ['open', 'open'],
    'trimmed' => ["  open \n", 'open'],
    'markup stripped' => ['<b>Closed</b> <script>x</script>', 'Closed x'],
    'markup only' => ['<br>', null],
    'zero string kept' => ['0', '0'],
    'integer' => [7, '7'],
    'zero integer kept' => [0, '0'],
    'float' => [1.5, '1.5'],
    'true' => [true, '1'],
    'false' => [false, null],
    'empty' => ['', null],
    'whitespace' => ['   ', null],
    'null' => [null, null],
    'array' => [['open'], null],
    'object' => [new stdClass, null],
    'wrapped' => [new Value(' closed '), 'closed'],
    'labeled' => [new LabeledValue('open_24h', 'Open 24 hours'), 'open_24h'],
    'wrapped labeled' => [new Value(new LabeledValue('closed', 'Closed')), 'closed'],
    'labeled null' => [new LabeledValue(null, null), null],
]);

it('treats a window that ended before today as entirely past', function (?string $date, bool $expected): void {
    Carbon::setTestNow(Carbon::create(2026, 11, 13, 9, 30, 0));

    expect(specialHoursCall('isEntirelyPast', $date))->toBe($expected);
})->with([
    'yesterday' => ['2026-11-12', true],
    'long ago' => ['2025-01-01', true],
    'today' => ['2026-11-13', false],
    'today with a time before now' => ['2026-11-13 00:01:00', false],
    'tomorrow' => ['2026-11-14', false],
    'null' => [null, false],
    'unparseable' => ['not a date', false],
]);
