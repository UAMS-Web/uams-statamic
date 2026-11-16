<?php

declare(strict_types=1);

use App\Support\Identifiers\ResolvesIdentifierValues;
use Statamic\Entries\Entry as ConcreteEntry;
use Statamic\Facades\Entry;
use Statamic\Stache\Query\EntryQueryBuilder;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Tests for the reading and comparison helpers the identifier-uniqueness rules share.
 *
 * @command  php artisan test --compact tests/Feature/Support/Identifiers/ResolvesIdentifierValuesTest.php
 */

/** Call one private helper of the trait on a fresh host. */
function identifierHostCall(string $method, mixed ...$arguments): mixed
{
    $host = new class
    {
        use ResolvesIdentifierValues;

        /**
         * Call one private helper of the trait.
         */
        public function call(string $method, mixed ...$arguments): mixed
        {
            return $this->{$method}(...$arguments);
        }
    };

    return $host->call($method, ...$arguments);
}

/**
 * A mocked entry carrying an id, a stored payload and a title.
 *
 * @param  mixed  $data  What `data()` returns
 */
function identifierEntry(mixed $id, mixed $data = [], mixed $title = null): ConcreteEntry
{
    $entry = Mockery::mock(ConcreteEntry::class);
    $entry->shouldReceive('id')->andReturn($id);
    $entry->shouldReceive('data')->andReturn(is_array($data) ? collect($data) : $data);
    $entry->shouldReceive('get')->with('title')->andReturn($title);

    return $entry;
}

it('reduces a value to its comparison and display forms', function (mixed $value, ?array $expected): void {
    expect(identifierHostCall('identifierForms', $value))->toBe($expected)
        ->and(identifierHostCall('normalizeIdentifier', $value))->toBe($expected['compare'] ?? null);
})->with([
    'plain' => ['ABC123', ['compare' => 'abc123', 'display' => 'ABC123']],
    'trimmed' => ["  Wd-9 \n", ['compare' => 'wd-9', 'display' => 'Wd-9']],
    'multibyte case folded' => ['ÉÑ-12', ['compare' => 'éñ-12', 'display' => 'ÉÑ-12']],
    'integer' => [42, ['compare' => '42', 'display' => '42']],
    'zero string' => ['0', ['compare' => '0', 'display' => '0']],
    'zero integer' => [0, ['compare' => '0', 'display' => '0']],
    'true' => [true, ['compare' => '1', 'display' => '1']],
    'false' => [false, null],
    'empty' => ['', null],
    'whitespace' => ["  \t ", null],
    'null' => [null, null],
    'array' => [['ABC'], null],
    'object' => [new stdClass, null],
]);

it('reads a single-value field, resolving a dotted path into a group', function (): void {
    $entry = identifierEntry('e1', [
        'npi' => ' 1234567890 ',
        'uams_workday_id' => ['workday_universal_id' => 'WD-42'],
        'blank' => '   ',
    ]);

    expect(identifierHostCall('identifierValuesOn', $entry, 'npi', null))->toBe(['1234567890' => '1234567890'])
        ->and(identifierHostCall('identifierValuesOn', $entry, 'uams_workday_id.workday_universal_id', null))->toBe(['wd-42' => 'WD-42'])
        ->and(identifierHostCall('identifierValuesOn', $entry, 'workday_universal_id', null))->toBe([])
        ->and(identifierHostCall('identifierValuesOn', $entry, 'blank', null))->toBe([])
        ->and(identifierHostCall('identifierValuesOn', $entry, 'missing', null))->toBe([]);
});

it('reads nothing when the entry data is not a collection', function (): void {
    $entry = identifierEntry('e1', 'not a collection');

    expect(identifierHostCall('identifierValuesOn', $entry, 'npi', null))->toBe([])
        ->and(identifierHostCall('identifierValuesOn', $entry, 'ids', 'value'))->toBe([]);
});

it('reads one pair per usable replicator row and keeps the first spelling', function (): void {
    $entry = identifierEntry('e1', [
        'ids' => [
            ['value' => ' Abc '],
            ['value' => ''],
            ['other' => 'ignored'],
            ['value' => 'ABC'],
            'Loose',
            ['value' => ['nested']],
            ['value' => 'def'],
            7,
        ],
    ]);

    expect(identifierHostCall('identifierValuesOn', $entry, 'ids', 'value'))->toBe([
        'abc' => 'Abc',
        'loose' => 'Loose',
        'def' => 'def',
        '7' => '7',
    ]);
});

it('reads no rows when a list field is not an array', function (mixed $raw): void {
    $entry = identifierEntry('e1', ['ids' => $raw]);

    expect(identifierHostCall('identifierValuesOn', $entry, 'ids', 'value'))->toBe([]);
})->with([
    'string' => ['abc'],
    'null' => [null],
    'integer' => [7],
]);

it('returns every entry in the collection except the one being saved', function (?string $exceptId, array $expectedIds): void {
    $first = identifierEntry('first');
    $second = identifierEntry('second');
    $numeric = identifierEntry(7);

    $query = Mockery::mock(EntryQueryBuilder::class);
    $query->shouldReceive('where')->once()->with('collection', 'people')->andReturnSelf();
    $query->shouldReceive('get')->once()->andReturn(collect([$first, 'not an entry', $second, $numeric]));
    Entry::shouldReceive('query')->once()->andReturn($query);

    $others = identifierHostCall('otherEntriesIn', 'people', $exceptId);

    $ids = [];

    foreach (is_array($others) ? $others : [] as $other) {
        $ids[] = $other instanceof ConcreteEntry ? $other->id() : $other;
    }

    expect($others)->toBeArray()->and($ids)->toBe($expectedIds);
})->with([
    'exclude nothing' => [null, ['first', 'second', 7]],
    'exclude one' => ['second', ['first', 7]],
    'exclude a numeric id' => ['7', ['first', 'second']],
    'unknown id' => ['third', ['first', 'second', 7]],
]);

it('normalizes the except parameter into an entry id', function (?string $except, ?string $expected): void {
    expect(identifierHostCall('exceptEntryId', $except))->toBe($expected);
})->with([
    'null' => [null, null],
    'empty' => ['', null],
    'whitespace' => ['   ', null],
    'placeholder' => ['NULL', null],
    'lowercase placeholder' => [' null ', null],
    'id' => ['abc-123', 'abc-123'],
    'trimmed id' => ['  abc-123 ', 'abc-123'],
    'id containing null' => ['nullable', 'nullable'],
]);

it('labels an entry by its trimmed title and falls back to its id', function (mixed $id, mixed $title, string $expected): void {
    expect(identifierHostCall('identifierEntryLabel', identifierEntry($id, [], $title)))->toBe($expected);
})->with([
    'title' => ['e1', ' Dr. Ann Smith ', 'Dr. Ann Smith'],
    'blank title' => ['e1', '  ', 'e1'],
    'no title' => ['e1', null, 'e1'],
    'array title' => ['e1', ['x'], 'e1'],
    'numeric id' => [12, null, '12'],
    'unreadable id' => [null, null, ''],
]);

it('reads an entry id as a string', function (mixed $id, string $expected): void {
    expect(identifierHostCall('entryIdAsString', identifierEntry($id)))->toBe($expected);
})->with([
    'string' => ['abc', 'abc'],
    'integer' => [5, '5'],
    'null' => [null, ''],
    'array' => [['abc'], ''],
]);
