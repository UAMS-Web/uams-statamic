<?php

declare(strict_types=1);

use App\Rules\BaseRules\BaseValidationRule;
use App\Rules\Latitude;
use App\Rules\Longitude;
use Tests\TestCase;

uses(TestCase::class);

covers(Latitude::class, Longitude::class);

/**
 * Behavior of the decimal coordinate rules (`Latitude`, `Longitude`): numeric
 * input within the inclusive range passes, numeric input just outside it fails
 * with the range message, non-numeric input fails with the decimal message, and
 * empty input is left to `required`.
 *
 * @command  php artisan test --compact tests/Feature/Rules/LatitudeLongitudeTest.php
 */

/**
 * Run a coordinate rule and return every message it failed with.
 *
 * @return list<mixed>
 */
function coordinateRuleMessages(BaseValidationRule $rule, mixed $value): array
{
    $messages = [];

    $rule->validate('coordinate', $value, function (mixed $message) use (&$messages): void {
        $messages[] = $message;
    });

    return $messages;
}

it('accepts a latitude within -90 to 90 inclusive', function (mixed $value): void {
    expect(coordinateRuleMessages(new Latitude, $value))->toBe([]);
})->with([
    'upper bound' => ['90'],
    'upper bound with decimals' => ['90.000'],
    'lower bound' => ['-90'],
    'lower bound as float' => [-90.0],
    'negative zero' => ['-0'],
    'typical value' => ['37.7749'],
    'typical negative value' => ['-37.7749'],
    'integer' => [45],
    'float' => [34.7465],
    'exponent form inside the range' => ['1e1'],
    'leading whitespace' => [' 45'],
]);

it('rejects a latitude outside -90 to 90 with the range message', function (mixed $value): void {
    expect(coordinateRuleMessages(new Latitude, $value))->toBe([__('statamic::validation.latitude')]);
})->with([
    'just above the upper bound' => ['90.0001'],
    'just below the lower bound' => ['-90.0001'],
    'integer above' => [91],
    'a valid longitude' => ['120.5'],
    'exponent form outside the range' => ['1e3'],
]);

it('accepts a longitude within -180 to 180 inclusive', function (mixed $value): void {
    expect(coordinateRuleMessages(new Longitude, $value))->toBe([]);
})->with([
    'upper bound' => ['180'],
    'lower bound' => ['-180'],
    'lower bound as float' => [-180.0],
    'negative zero' => ['-0'],
    'typical value' => ['-122.4194'],
    'beyond the latitude range' => ['120.5'],
    'integer' => [-92],
]);

it('rejects a longitude outside -180 to 180 with the range message', function (mixed $value): void {
    expect(coordinateRuleMessages(new Longitude, $value))->toBe([__('statamic::validation.longitude')]);
})->with([
    'just above the upper bound' => ['180.0001'],
    'just below the lower bound' => ['-180.0001'],
    'integer above' => [181],
    'integer below' => [-181],
]);

it('rejects non-numeric input with the decimal message', function (BaseValidationRule $rule, mixed $value): void {
    expect(coordinateRuleMessages($rule, $value))->toBe([__('statamic::validation.decimal_valid')]);
})->with([
    'latitude, word' => [new Latitude, 'north'],
    'latitude, cardinal suffix' => [new Latitude, '45N'],
    'latitude, cardinal prefix' => [new Latitude, 'N45'],
    'latitude, comma decimal' => [new Latitude, '45,5'],
    'longitude, cardinal suffix' => [new Longitude, '122W'],
    'longitude, degree sign' => [new Longitude, '122°'],
    'longitude, infinity' => [new Longitude, 'INF'],
]);

it('leaves empty input to other rules', function (BaseValidationRule $rule, mixed $value): void {
    expect(coordinateRuleMessages($rule, $value))->toBe([]);
})->with([
    'latitude, null' => [new Latitude, null],
    'latitude, empty string' => [new Latitude, ''],
    'latitude, string zero' => [new Latitude, '0'],
    'longitude, null' => [new Longitude, null],
    'longitude, integer zero' => [new Longitude, 0],
]);

it('rejects an array with the shared array message', function (BaseValidationRule $rule): void {
    expect(coordinateRuleMessages($rule, ['45']))->toBe(['The value must be a string, not an array.']);
})->with([
    'latitude' => [new Latitude],
    'longitude' => [new Longitude],
]);

it('agrees with validate() through isValid()', function (): void {
    expect((new Latitude)->isValid('90'))->toBeTrue()
        ->and((new Latitude)->isValid('90.5'))->toBeFalse()
        ->and((new Longitude)->isValid('180'))->toBeTrue()
        ->and((new Longitude)->isValid('180.5'))->toBeFalse()
        ->and((new Longitude)->isValid('west'))->toBeFalse();
});
