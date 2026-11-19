<?php

declare(strict_types=1);

use App\Support\Entries\EntryReferenceIds;
use Illuminate\Support\Collection as SupportCollection;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Contracts\Query\Builder;
use Statamic\Entries\Entry;

uses(MockeryPHPUnitIntegration::class);

covers(EntryReferenceIds::class);

/**
 * Each shape an `entries` selection arrives in, per App\Support\Entries\EntryReferenceIds.
 *
 * Entries and the query builder are doubles, so these never boot the app or touch
 * the Stache. The hermetic scope behavior is in PeopleGroupsHierarchyTest.
 *
 * @command  php artisan test --compact tests/Unit/Support/Entries/EntryReferenceIdsTest.php
 */

/**
 * Pad Pest's mutant-child timeout once per process under ``--mutate``.
 */
beforeEach(function (): void {
    static $padded = false;
    if ($padded) {
        return;
    }

    if (runningUnderMutate()) {
        usleep(20_000_000);
        $padded = true;
    }
});

/**
 * An entry double whose `id()` returns `$id`.
 */
function entryReferenceIdsEntry(mixed $id): Entry
{
    /** @var Entry&MockInterface $entry */
    $entry = Mockery::mock(Entry::class);
    $entry->shouldReceive('id')->andReturn($id);

    return $entry;
}

/**
 * A Stringable that stringifies to `$value`, the shape of a `LabeledValue`.
 */
function entryReferenceIdsStringable(string $value): Stringable
{
    return new class($value) implements Stringable
    {
        public function __construct(private string $value) {}

        public function __toString(): string
        {
            return $this->value;
        }
    };
}

it('returns no ids for a null selection', function (): void {
    expect(EntryReferenceIds::from(null))->toBe([]);
});

it('returns no ids for an empty list', function (): void {
    expect(EntryReferenceIds::from([]))->toBe([]);
});

it('keeps raw ids in order', function (): void {
    expect(EntryReferenceIds::from(['b', 'a', 'c']))->toBe(['b', 'a', 'c']);
});

it('drops repeated ids and renumbers the list', function (): void {
    // array_unique alone would leave the keys 0 and 2
    expect(EntryReferenceIds::from(['a', 'a', 'b']))->toBe(['a', 'b']);
});

it('flattens a support collection', function (): void {
    expect(EntryReferenceIds::from(new SupportCollection(['a', 'b'])))->toBe(['a', 'b']);
});

it('wraps a single value from a max_items 1 field', function (): void {
    expect(EntryReferenceIds::from('solo'))->toBe(['solo'])
        ->and(EntryReferenceIds::from(entryReferenceIdsEntry('one')))->toBe(['one'])
        ->and(EntryReferenceIds::from(entryReferenceIdsStringable('label')))->toBe(['label']);
});

it('reads the id of each entry object', function (): void {
    expect(EntryReferenceIds::from([entryReferenceIdsEntry('e-1'), 'raw', entryReferenceIdsEntry('e-2')]))
        ->toBe(['e-1', 'raw', 'e-2']);
});

it('skips an entry whose id is not a string', function (): void {
    expect(EntryReferenceIds::from([entryReferenceIdsEntry(null), 'kept', entryReferenceIdsEntry(42)]))
        ->toBe(['kept']);
});

it('stringifies a Stringable item to its key', function (): void {
    expect(EntryReferenceIds::from([entryReferenceIdsStringable('x'), entryReferenceIdsStringable('y')]))
        ->toBe(['x', 'y']);
});

it('skips items that denote no id', function (): void {
    // An int, an array and a plain object are none of the three item shapes
    expect(EntryReferenceIds::from([7, ['nested'], new stdClass, 'kept']))->toBe(['kept']);
});

it('skips an empty id but keeps the id "0"', function (): void {
    expect(EntryReferenceIds::from(['', '0', entryReferenceIdsStringable('')]))->toBe(['0']);
});

it('refuses an augmented entries field, naming the fix', function (): void {
    $builder = Mockery::mock(Builder::class);

    // Each half of the concatenated message is asserted, so dropping either fails
    expect(fn () => EntryReferenceIds::from($builder))
        ->toThrow(RuntimeException::class, 'An entries-reference parameter arrived augmented (a query builder) rather than as ids. Pass the raw value instead')
        ->toThrow(RuntimeException::class, '`{{ $ids = <field> | raw }}`')
        ->toThrow(RuntimeException::class, 'as the People and Logo List blocks do.');
});
