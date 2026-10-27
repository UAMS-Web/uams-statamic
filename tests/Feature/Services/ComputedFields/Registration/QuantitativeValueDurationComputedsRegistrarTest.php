<?php

declare(strict_types=1);

use App\Services\ComputedFields\Registration\QuantitativeValueDurationComputedsRegistrar;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Facades\Collection;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * @covers \App\Services\ComputedFields\Registration\QuantitativeValueDurationComputedsRegistrar
 *
 * @command  php artisan test --compact tests/Feature/Services/ComputedFields/Registration/QuantitativeValueDurationComputedsRegistrarTest.php
 */
it('registers nothing as the container builds it, because no collection opts in yet', function (): void {
    Collection::shouldReceive('computed')->never();

    app(QuantitativeValueDurationComputedsRegistrar::class)->register();
});

/**
 * Register the duration computed for `durations.length` and return its callback.
 */
function quantitativeValueDurationCallback(): Closure
{
    $callbacks = [];
    Collection::shouldReceive('computed')->andReturnUsing(
        function (string $collection, string $handle, Closure $callback) use (&$callbacks): void {
            $callbacks[$collection.'.'.$handle] = $callback;
        }
    );

    (new QuantitativeValueDurationComputedsRegistrar([['durations', 'length']]))->register();

    return $callbacks['durations.length'];
}

/**
 * An entry whose blueprint resolves `length` to the given field, or to none.
 */
function quantitativeValueDurationEntry(?Field $field): Entry
{
    $blueprint = Mockery::mock(Blueprint::class);
    $blueprint->shouldReceive('field')->with('length')->andReturn($field);

    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('blueprint')->andReturn($blueprint);

    return $entry;
}

it('registers one computed per tuple, on the collection and field the tuple names', function (): void {
    $registered = [];
    Collection::shouldReceive('computed')->andReturnUsing(
        function (string $collection, string $handle) use (&$registered): void {
            $registered[] = [$collection, $handle];
        }
    );

    (new QuantitativeValueDurationComputedsRegistrar([
        ['durations', 'length'],
        ['programs', 'term'],
    ]))->register();

    expect($registered)->toBe([
        ['durations', 'length'],
        ['programs', 'term'],
    ]);
});

it('merges total years into each enabled major of a duration field', function (): void {
    $callback = quantitativeValueDurationCallback();
    $field = new Field('length', ['type' => 'quantitative_value', 'mode' => 'duration', 'major_components' => ['value']]);

    $computed = $callback(quantitativeValueDurationEntry($field), [
        'value' => ['years' => 2, 'months' => 6],
        'label' => 'kept',
    ]);

    expect($computed)->toBe([
        'value' => ['years' => 2, 'months' => 6, 'total_years' => 2.5],
        'label' => 'kept',
    ]);
});

it('treats a stored value that is not an array as empty in duration mode', function (): void {
    $callback = quantitativeValueDurationCallback();
    $field = new Field('length', ['type' => 'quantitative_value', 'mode' => 'duration', 'major_components' => ['value']]);

    expect($callback(quantitativeValueDurationEntry($field), 'not a payload'))
        ->toBe(['value' => ['total_years' => 0.0]]);
});

it('returns the stored value unchanged when the field is not in duration mode', function (): void {
    $callback = quantitativeValueDurationCallback();
    $field = new Field('length', ['type' => 'quantitative_value', 'mode' => 'number']);
    $stored = ['value' => ['years' => 2]];

    expect($callback(quantitativeValueDurationEntry($field), $stored))->toBe($stored);
});

it('returns the stored value unchanged when the blueprint has no such field', function (): void {
    $callback = quantitativeValueDurationCallback();
    $stored = ['value' => ['years' => 2]];

    expect($callback(quantitativeValueDurationEntry(null), $stored))->toBe($stored);
});
