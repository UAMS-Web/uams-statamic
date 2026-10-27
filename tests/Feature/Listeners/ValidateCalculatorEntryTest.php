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
 * Mutation: 100% on App\Listeners\ValidateCalculatorEntry (112 mutants, --covered-only).
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
