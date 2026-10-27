<?php

declare(strict_types=1);

use App\Support\Statamic\AugmentedEntryIds;
use App\Tags\NewsSpringboard;
use App\Tags\UamsNews;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Entries\Entry;
use Statamic\Fields\Value;
use Statamic\Query\OrderedQueryBuilder;

uses(MockeryPHPUnitIntegration::class);

/**
 * Unit coverage for {@see AugmentedEntryIds}, the shared entries-field →
 * entry-ids normalizer used by {@see UamsNews} (UAMS-Web/uams-statamic-archive#76) and
 * {@see NewsSpringboard} (UAMS-Web/uams-statamic-archive#1158).
 *
 * @covers \App\Support\Statamic\AugmentedEntryIds
 *
 * @command  php artisan test --compact tests/Unit/Support/Statamic/AugmentedEntryIdsTest.php
 */
it('reads a raw id list, trimming and de-duplicating', function () {
    expect(AugmentedEntryIds::fromValue([' a ', 'b', 'a', '']))->toBe(['a', 'b']);
});

it('wraps a single raw id', function () {
    expect(AugmentedEntryIds::fromValue('a'))->toBe(['a']);
});

it('reindexes to a list after removing a mid-list duplicate', function () {
    // array_unique leaves a hole where the middle 'a' was; the result must be a
    // 0-based list, not [0 => a, 1 => b, 3 => c].
    expect(AugmentedEntryIds::fromValue(['a', 'b', 'a', 'c']))->toBe(['a', 'b', 'c']);
});

it('unwraps a Value item inside a list', function () {
    expect(AugmentedEntryIds::fromValue([new Value('x'), new Value('y')]))->toBe(['x', 'y']);
});

it('drops an entry whose id is an empty string', function () {
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('id')->andReturn('');

    expect(AugmentedEntryIds::fromValue([$entry]))->toBe([]);
});

it('returns an empty list for null or blank', function () {
    expect(AugmentedEntryIds::fromValue(null))->toBe([]);
    expect(AugmentedEntryIds::fromValue(''))->toBe([]);
    expect(AugmentedEntryIds::fromValue([]))->toBe([]);
});

it('extracts the id from an augmented Entry', function () {
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('id')->andReturn('the-id');

    expect(AugmentedEntryIds::fromValue($entry))->toBe(['the-id']);
});

it('extracts ids from a collection of entries', function () {
    $a = Mockery::mock(Entry::class);
    $a->shouldReceive('id')->andReturn('a');
    $b = Mockery::mock(Entry::class);
    $b->shouldReceive('id')->andReturn('b');

    expect(AugmentedEntryIds::fromValue(collect([$a, $b])))->toBe(['a', 'b']);
});

it('materializes an unexecuted query builder before reading ids', function () {
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('id')->andReturn('a');

    // A multi-item entries field augments to an OrderedQueryBuilder (the real
    // marker type; its interface alone declares no get(), so mock the concrete).
    $builder = Mockery::mock(OrderedQueryBuilder::class);
    $builder->shouldReceive('get')->once()->andReturn(collect([$entry]));

    expect(AugmentedEntryIds::fromValue($builder))->toBe(['a']);
});

it('unwraps a Value to its raw stored ids', function () {
    // A grid/replicator subfield arrives wrapped; a plain Value exposes its raw.
    expect(AugmentedEntryIds::fromValue(new Value(['a', 'b'])))->toBe(['a', 'b']);
});

it('falls back to a Value raw when augmentation yields null', function () {
    $value = Mockery::mock(Value::class);
    $value->shouldReceive('value')->andReturnNull();
    $value->shouldReceive('raw')->andReturn(['cat-a']);

    expect(AugmentedEntryIds::fromValue($value))->toBe(['cat-a']);
});

it('prefers a resolved Value value over its raw', function () {
    // When augmentation resolves, the resolved value wins over the raw ids.
    $value = Mockery::mock(Value::class);
    $value->shouldReceive('value')->andReturn(['resolved-id']);
    $value->shouldReceive('raw')->andReturn(['raw-id']);

    expect(AugmentedEntryIds::fromValue($value))->toBe(['resolved-id']);
});

it('firstId returns the first id or null', function () {
    expect(AugmentedEntryIds::firstId(['a', 'b']))->toBe('a');
    expect(AugmentedEntryIds::firstId('x'))->toBe('x');
    expect(AugmentedEntryIds::firstId([]))->toBeNull();
    expect(AugmentedEntryIds::firstId(null))->toBeNull();
});
