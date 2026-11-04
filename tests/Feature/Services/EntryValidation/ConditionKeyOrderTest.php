<?php

declare(strict_types=1);

use App\Services\EntryValidation\EntryValidator;
use Statamic\Fields\Field;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Which condition block the entry validator judges a field on (#527).
 *
 * The editor's field-conditions `Validator` takes the first key of a fixed order
 * (`if`, `if_any`, `show_when`, `show_when_any`, `unless`, `unless_any`,
 * `hide_when`, `hide_when_any`) whose value is truthy in JavaScript, and ignores
 * every other block the field carries. The validator has to agree, or it checks
 * the rules of a field the editor hides, or skips one the editor shows.
 *
 * @command  php artisan test --compact tests/Feature/Services/EntryValidation/ConditionKeyOrderTest.php
 */

/**
 * Whether the validator reads the field as visible for one value of `status`.
 *
 * @param  array<string, mixed>  $conditions  The field's condition blocks
 */
function conditionKeyVisible(array $conditions, string $status): bool
{
    $field = new Field('subject', ['type' => 'text', ...$conditions]);

    return app(EntryValidator::class)->fieldPassesConditions($field, ['status' => $status, 'role' => 'captain']);
}

it('judges a field on one key alone, for each of the eight keys', function (string $key, string $status, bool $visible): void {
    expect(conditionKeyVisible([$key => ['status' => 'equals active']], $status))->toBe($visible);
})->with([
    'if, passing' => ['if', 'active', true],
    'if, failing' => ['if', 'retired', false],
    'if_any, passing' => ['if_any', 'active', true],
    'if_any, failing' => ['if_any', 'retired', false],
    'show_when, passing' => ['show_when', 'active', true],
    'show_when, failing' => ['show_when', 'retired', false],
    'show_when_any, passing' => ['show_when_any', 'active', true],
    'show_when_any, failing' => ['show_when_any', 'retired', false],
    'unless, passing' => ['unless', 'active', false],
    'unless, failing' => ['unless', 'retired', true],
    'unless_any, passing' => ['unless_any', 'active', false],
    'unless_any, failing' => ['unless_any', 'retired', true],
    'hide_when, passing' => ['hide_when', 'active', false],
    'hide_when, failing' => ['hide_when', 'retired', true],
    'hide_when_any, passing' => ['hide_when_any', 'active', false],
    'hide_when_any, failing' => ['hide_when_any', 'retired', true],
]);

it('judges a field carrying if and if_any on if alone, wherever if_any sits in the config', function (): void {
    // if_any is written first, so config order and the editor's order disagree
    $conditions = [
        'if_any' => ['role' => 'equals ensign'],
        'if' => ['status' => 'equals active'],
    ];

    expect(conditionKeyVisible($conditions, 'active'))->toBeTrue()
        ->and(conditionKeyVisible($conditions, 'retired'))->toBeFalse();
});

it('judges a field carrying if_any and unless on if_any alone', function (): void {
    $conditions = [
        'unless' => ['role' => 'equals captain'],
        'if_any' => ['status' => 'equals active'],
    ];

    expect(conditionKeyVisible($conditions, 'active'))->toBeTrue()
        ->and(conditionKeyVisible($conditions, 'retired'))->toBeFalse();
});

it('passes over a key whose value JavaScript reads as false, and takes one it reads as true', function (mixed $ifValue, string $unlessRole, bool $visible): void {
    // The role (captain) is fixed, so unless hides the field when it names captain; each
    // row's unless gives the opposite answer to the one the if value would give if taken
    expect(conditionKeyVisible(['if' => $ifValue, 'unless' => ['role' => 'equals '.$unlessRole]], 'active'))->toBe($visible);
})->with([
    // Taken, a scalar if is malformed and skipped (visible), so unless must hide
    'null is passed over' => [null, 'captain', false],
    'false is passed over' => [false, 'captain', false],
    'zero is passed over' => [0, 'captain', false],
    'a zero float is passed over' => [0.0, 'captain', false],
    // Taken, an empty string is an unported custom condition (hidden), so unless must show
    'an empty string is passed over' => ['', 'ensign', true],
    // Taken, an empty array has no conditions and passes, so unless must hide to tell
    'an empty array is taken, and passes' => [[], 'captain', true],
]);

it('skips a first key whose value is neither a string nor an array, reading the field as visible', function (): void {
    // The malformed if is still the key the editor takes, so unless never decides
    expect(conditionKeyVisible(['if' => 5, 'unless' => ['role' => 'equals captain']], 'retired'))->toBeTrue();
});

it('reads a field with no condition key as visible', function (): void {
    expect(conditionKeyVisible([], 'retired'))->toBeTrue();
});
