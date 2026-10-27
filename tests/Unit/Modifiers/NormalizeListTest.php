<?php

declare(strict_types=1);

use App\Modifiers\NormalizeList;
use Illuminate\Database\Eloquent\Builder as EloquentQueryBuilder;
use Illuminate\Database\Query\Builder as DatabaseQueryBuilder;
use Illuminate\Support\Collection;
use Statamic\Contracts\Query\Builder as StatamicQueryBuilder;

/**
 * Behavior of the `normalize_list` Antlers modifier
 * (app/Modifiers/NormalizeList.php): coerce a single list-like value into a flat,
 * zero-indexed array — empty sentinels become [], query builders (Statamic,
 * Laravel database, Laravel Eloquent) resolve via get(), Collections and arrays
 * flatten (expanding any nested builders), and any other scalar/object wraps in a
 * one-element array. Builder results carry deliberately non-sequential/string keys
 * so the reindexing is observable.
 *
 * Mutation: every killable mutant is dead (score ~94%). The two residual survivors
 * are equivalent UnwrapArrayValues on the Collection/array paths (lines 78, 88):
 * the array_values() there is redundant because flattenListItems() already reindexes
 * via `$out[]`, so removing it cannot change the output — left clean rather than
 * annotated with `@pest-mutate-ignore`.
 *
 * @command  php artisan test --compact tests/Unit/Modifiers/NormalizeListTest.php
 */
$statamicBuilder = new class implements StatamicQueryBuilder
{
    /** @return Collection<string, string> */
    public function get(): Collection
    {
        return collect(['a' => 'resolved-a', 'b' => 'resolved-b']);
    }
};

$nestedBuilder = new class implements StatamicQueryBuilder
{
    /** @return Collection<string, string> */
    public function get(): Collection
    {
        return collect(['k' => 'inner']);
    }
};

$databaseRow = (object) ['value' => 'db-result'];

$databaseBuilder = new class($databaseRow) extends DatabaseQueryBuilder
{
    public function __construct(private stdClass $row) {}

    /**
     * @param  array<int, string>|string  $columns
     * @return Collection<int, stdClass>
     */
    public function get($columns = ['*']): Collection
    {
        return collect([$this->row]);
    }
};

$eloquentBuilder = new class extends EloquentQueryBuilder
{
    public function __construct() {}

    /**
     * @param  array<int, string>|string  $columns
     * @return Collection<int, string>
     */
    public function get($columns = ['*']): Collection
    {
        return collect(['eloquent-result']);
    }
};

it('normalizes a list-like value into a flat indexed array', function (mixed $value, array $expected) {
    expect((new NormalizeList)->index($value, [], []))->toBe($expected);
})->with([
    'null is an empty list' => [null, []],
    'false is an empty list' => [false, []],
    'empty string is an empty list' => ['', []],
    'a Collection unwraps to a list' => [collect(['a', 'b']), ['a', 'b']],
    'an array is reindexed' => [[1 => 'x', 3 => 'y'], ['x', 'y']],
    'a scalar wraps in a single-element array' => ['only', ['only']],
    'a Statamic query builder resolves via get(), stripping keys' => [$statamicBuilder, ['resolved-a', 'resolved-b']],
    'a Laravel database query builder resolves via get()' => [$databaseBuilder, [$databaseRow]],
    'a Laravel Eloquent builder resolves via get()' => [$eloquentBuilder, ['eloquent-result']],
    'a nested query builder inside an array expands, stripping keys' => [['keep', $nestedBuilder], ['keep', 'inner']],
]);
