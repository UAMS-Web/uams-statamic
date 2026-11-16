<?php

declare(strict_types=1);

use App\Listeners\ValidateCalculatorEntry;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry;
use Statamic\Events\EntrySaving;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Build an EntrySaving event whose entry belongs to the given collection
 * (defaults to `calculators`) and whose `parts` field returns the array.
 *
 * @param  array<int, array<string, mixed>>  $parts
 */
function validateCalculatorMakeEvent(array $parts, string $collectionHandle = 'calculators'): EntrySaving
{
    $collection = Mockery::mock(Collection::class);
    $collection->shouldReceive('handle')->andReturn($collectionHandle);

    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('collection')->andReturn($collection);
    $entry->shouldReceive('get')->with('parts')->andReturn($parts);

    return new EntrySaving($entry);
}

/**
 * Feature tests for ValidateCalculatorEntry listener.
 *
 * Covers the three cross-field rules the listener enforces at save time:
 *   1. Exactly one primary (explicit or implied)
 *   2. No handle collisions across composite handles and split sub-handles
 *   3. No formula references to split sub-handles
 *
 * Each test builds a synthetic `parts` array, wraps it in a mocked entry
 * stamped with the `calculators` collection handle, fires an EntrySaving
 * event, and asserts the listener throws (or doesn't).
 *
 * @covers \App\Listeners\ValidateCalculatorEntry
 *
 * Mutation-clean on App\Listeners\ValidateCalculatorEntry (UAMS-Web/uams-statamic#315).
 * Accepted equivalents, annotated in the listener: the string casts on array keys
 * and on handles that only reach sprintf('%s'), the `true` stored for a handle that
 * isset() alone reads, the empty-formula skip, and the `array_unique()` over
 * identifiers when the first match throws.
 *
 * @command  php artisan test --compact tests/Feature/Listeners/ValidateCalculatorEntryTest.php
 */
it('skips validation for non-calculator collections', function () {
    // Other collections never trigger any of the calculator rules even
    // when the data shape would otherwise be invalid.
    $listener = new ValidateCalculatorEntry;
    $event = validateCalculatorMakeEvent([
        ['handle' => 'foo', 'primary' => true],
        ['handle' => 'foo', 'primary' => true],
    ], collectionHandle: 'articles');

    expect(throwableFrom(fn () => $listener->handle($event)))->toBeNull();
});

it('passes with a single output and no explicit primary', function () {
    // One formula-having variable => implicit primary, no flag needed.
    $listener = new ValidateCalculatorEntry;
    $event = validateCalculatorMakeEvent([
        ['handle' => 'result', 'kind' => 'output', 'formula' => ['code' => 'a + b']],
        ['handle' => 'a', 'kind' => 'input'],
        ['handle' => 'b', 'kind' => 'input'],
    ]);

    expect(throwableFrom(fn () => $listener->handle($event)))->toBeNull();
});

it('passes with an explicit primary on one of two bidirectional variables', function () {
    $listener = new ValidateCalculatorEntry;
    $event = validateCalculatorMakeEvent([
        ['handle' => 'kg', 'kind' => 'bidirectional', 'primary' => true, 'formula' => ['code' => 'lb * 0.4535']],
        ['handle' => 'lb', 'kind' => 'bidirectional', 'formula' => ['code' => 'kg / 0.4535']],
    ]);

    expect(throwableFrom(fn () => $listener->handle($event)))->toBeNull();
});

it('throws when no formula exists anywhere', function () {
    $listener = new ValidateCalculatorEntry;
    $event = validateCalculatorMakeEvent([
        ['handle' => 'a', 'kind' => 'input'],
        ['handle' => 'b', 'kind' => 'input'],
    ]);

    expect(fn () => $listener->handle($event))
        ->toThrow(Exception::class, 'at least one variable with a formula');
});

it('throws when multiple formulas exist and no primary is set', function () {
    $listener = new ValidateCalculatorEntry;
    $event = validateCalculatorMakeEvent([
        ['handle' => 'kg', 'kind' => 'bidirectional', 'formula' => ['code' => 'lb * 0.4535']],
        ['handle' => 'lb', 'kind' => 'bidirectional', 'formula' => ['code' => 'kg / 0.4535']],
    ]);

    expect(fn () => $listener->handle($event))
        ->toThrow(Exception::class, 'mark exactly one as Primary');
});

it('throws when more than one primary is marked', function () {
    $listener = new ValidateCalculatorEntry;
    $event = validateCalculatorMakeEvent([
        ['handle' => 'a', 'primary' => true, 'formula' => ['code' => 'b']],
        ['handle' => 'b', 'primary' => true, 'formula' => ['code' => 'a']],
    ]);

    expect(fn () => $listener->handle($event))
        ->toThrow(Exception::class, 'Only one variable may be marked Primary');
});

it('throws on duplicate composite handles', function () {
    $listener = new ValidateCalculatorEntry;
    $event = validateCalculatorMakeEvent([
        ['handle' => 'x', 'kind' => 'output', 'formula' => ['code' => 'y']],
        ['handle' => 'x', 'kind' => 'input'],
    ]);

    expect(fn () => $listener->handle($event))
        ->toThrow(Exception::class, "Handle 'x' is used more than once");
});

it('throws on a sub-handle colliding with a composite handle', function () {
    $listener = new ValidateCalculatorEntry;
    $event = validateCalculatorMakeEvent([
        [
            'handle' => 'imperial',
            'kind' => 'bidirectional',
            'formula' => ['code' => 'kg / 0.4535'],
            'split' => [
                ['handle' => 'kg', 'strategy' => 'integer'],
            ],
        ],
        ['handle' => 'kg', 'kind' => 'bidirectional', 'primary' => true, 'formula' => ['code' => 'imperial * 0.4535']],
    ]);

    expect(fn () => $listener->handle($event))
        ->toThrow(Exception::class, "Handle 'kg' is used more than once");
});

it('throws when a formula references a split sub-handle', function () {
    $listener = new ValidateCalculatorEntry;
    $event = validateCalculatorMakeEvent([
        [
            'handle' => 'imperial',
            'kind' => 'bidirectional',
            'formula' => ['code' => 'kg / 0.4535'],
            'split' => [
                ['handle' => 'lb', 'strategy' => 'integer'],
                ['handle' => 'oz', 'strategy' => 'fractional_times', 'multiplier' => 16],
            ],
        ],
        // kg's formula references `lb` directly — should be flagged.
        ['handle' => 'kg', 'kind' => 'bidirectional', 'primary' => true, 'formula' => ['code' => 'lb * 0.4535 + oz * 0.0283']],
    ]);

    expect(fn () => $listener->handle($event))
        ->toThrow(Exception::class, "Formula on 'kg' references 'lb'");
});

it('passes when a formula references only composite handles', function () {
    // Mirrors the weight-conversion entry shape exactly. Should pass.
    $listener = new ValidateCalculatorEntry;
    $event = validateCalculatorMakeEvent([
        ['handle' => 'weight_kg', 'kind' => 'bidirectional', 'primary' => true, 'formula' => ['code' => 'weight_imperial * 0.453592']],
        [
            'handle' => 'weight_imperial',
            'kind' => 'bidirectional',
            'formula' => ['code' => 'weight_kg / 0.453592'],
            'split' => [
                ['handle' => 'weight_lb', 'strategy' => 'integer'],
                ['handle' => 'weight_oz', 'strategy' => 'fractional_times', 'multiplier' => 16],
            ],
        ],
    ]);

    expect(throwableFrom(fn () => $listener->handle($event)))->toBeNull();
});

it('passes with empty parts', function () {
    // Entries that haven't been authored yet shouldn't blow up on save.
    $listener = new ValidateCalculatorEntry;
    $event = validateCalculatorMakeEvent([]);

    expect(throwableFrom(fn () => $listener->handle($event)))->toBeNull();
});

it('names each marked primary in the refusal, a missing or non-text handle as ?', function () {
    $listener = new ValidateCalculatorEntry;
    $event = validateCalculatorMakeEvent([
        ['handle' => 7, 'primary' => true, 'formula' => 'a + 1'],
        ['primary' => true],
        ['handle' => ['not', 'text'], 'primary' => true],
    ]);

    expect(fn () => $listener->handle($event))
        ->toThrow(Exception::class, 'Only one variable may be marked Primary, but the following are: 7, ?, ?.');
});

it('names each formula variable in the refusal when none is marked primary', function () {
    $listener = new ValidateCalculatorEntry;
    $event = validateCalculatorMakeEvent([
        ['handle' => 7, 'formula' => 'a + 1'],
        ['formula' => 'b + 1'],
        ['handle' => ['not', 'text'], 'formula' => 'c + 1'],
    ]);

    expect(fn () => $listener->handle($event))
        ->toThrow(Exception::class, 'This calculator has 3 variables with formulas (7, ?, ?); mark exactly one as Primary so the displayed equation has an unambiguous left side.');
});

it('does not count an empty handle as a handle', function () {
    // Two variables left without a handle are not a duplicate.
    $listener = new ValidateCalculatorEntry;
    $event = validateCalculatorMakeEvent([
        ['handle' => '', 'formula' => 'a + 1'],
        ['handle' => ''],
    ]);

    expect(throwableFrom(fn () => $listener->handle($event)))->toBeNull();
});

it('goes on to the next sub-handle after one it skips', function (mixed $skipped) {
    $listener = new ValidateCalculatorEntry;
    $event = validateCalculatorMakeEvent([
        ['handle' => 'weight', 'formula' => 'height * 2', 'split' => [['handle' => $skipped], ['handle' => 'weight']]],
        ['handle' => 'height'],
    ]);

    expect(fn () => $listener->handle($event))->toThrow(Exception::class, "Handle 'weight' is used more than once");
})->with(['a non-text sub-handle' => [['not', 'text']], 'an empty sub-handle' => ['']]);

it('ignores a split whose sub-handle is not text when checking formulas', function () {
    $listener = new ValidateCalculatorEntry;
    $event = validateCalculatorMakeEvent([
        ['handle' => 'weight', 'formula' => 'height * 2', 'split' => [['handle' => ['not', 'text']]]],
        ['handle' => 'height'],
    ]);

    expect(throwableFrom(fn () => $listener->handle($event)))->toBeNull();
});

it('names the composite of a referenced sub-handle as ? when it has no text handle', function (string $case) {
    $composite = match ($case) {
        'no handle' => [],
        'non-text handle' => ['handle' => ['not', 'text']],
        default => throw new InvalidArgumentException("No such composite: {$case}"),
    };

    $listener = new ValidateCalculatorEntry;
    $event = validateCalculatorMakeEvent([
        [...$composite, 'split' => [['handle' => 'lb']]],
        ['handle' => 'total', 'primary' => true, 'formula' => 'lb * 2'],
    ]);

    expect(fn () => $listener->handle($event))
        ->toThrow(Exception::class, "Formula on 'total' references 'lb', which is a split sub-handle of '?'.");
})->with(['no handle' => ['no handle'], 'a non-text handle' => ['non-text handle']]);

it('names the composite of a referenced sub-handle in full', function () {
    $listener = new ValidateCalculatorEntry;
    $event = validateCalculatorMakeEvent([
        ['handle' => 'kg', 'split' => [['handle' => 'lb']]],
        ['handle' => 'total', 'primary' => true, 'formula' => 'lb * 2'],
    ]);

    expect(fn () => $listener->handle($event))
        ->toThrow(Exception::class, "Formula on 'total' references 'lb', which is a split sub-handle of 'kg'. Sub-handles are display-only; reference the composite handle 'kg' instead.");
});

it('checks the formulas after one that is empty', function () {
    $listener = new ValidateCalculatorEntry;
    $event = validateCalculatorMakeEvent([
        ['handle' => 'kg', 'split' => [['handle' => 'lb']]],
        ['handle' => 'blank', 'formula' => ''],
        ['handle' => 'total', 'primary' => true, 'formula' => 'lb * 2'],
    ]);

    expect(fn () => $listener->handle($event))->toThrow(Exception::class, "Formula on 'total' references 'lb'");
});

it('reads a blank or code-less formula as no formula', function (mixed $formula) {
    // The variable with the blank formula is not a second formula variable, so the
    // other one is the implied primary.
    $listener = new ValidateCalculatorEntry;
    $event = validateCalculatorMakeEvent([
        ['handle' => 'a', 'formula' => 'b + 1'],
        ['handle' => 'c', 'formula' => $formula],
    ]);

    expect(throwableFrom(fn () => $listener->handle($event)))->toBeNull();
})->with(['only spaces' => ['   '], 'a code field missing' => [['mode' => 'javascript']]]);
