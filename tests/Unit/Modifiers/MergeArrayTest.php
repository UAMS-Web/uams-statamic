<?php

declare(strict_types=1);

use App\Modifiers\MergeArray;
use Illuminate\Support\Collection;
use Statamic\Contracts\Query\Builder as StatamicQueryBuilder;
use Tests\TestCase;

covers(MergeArray::class);

uses(TestCase::class);

/**
 * Behavior of the `merge_array` Antlers modifier (app/Modifiers/MergeArray.php):
 * normalize the base value and each parameter with NormalizeList, then concatenate
 * them into one flat indexed array. null/false/'' params contribute nothing because
 * NormalizeList maps them to [].
 *
 * Mutation: tracked under App\Modifiers\MergeArray (--covered-only).
 *
 * @command  php artisan test --compact tests/Unit/Modifiers/MergeArrayTest.php
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

$paramBuilder = new class implements StatamicQueryBuilder
{
    /** @return Collection<int, string> */
    public function get(): Collection
    {
        return collect(['from-qb']);
    }
};

$valueBuilder = new class implements StatamicQueryBuilder
{
    /** @return Collection<int, string> */
    public function get(): Collection
    {
        return collect(['only']);
    }
};

it('concatenates normalized list-like values', function (mixed $value, array $params, array $expected) {
    expect((new MergeArray)->index($value, $params, []))->toBe($expected);
})->with([
    // ── Original cases ──
    'single string param into an empty array' => [[], ['resources/js/site.js'], ['resources/js/site.js']],
    'array param into an existing array' => [['resources/js/site.js'], [['resources/css/tailwind/site.css', 'resources/scss/app.scss']], ['resources/js/site.js', 'resources/css/tailwind/site.css', 'resources/scss/app.scss']],
    'multiple string params as elements' => [['first'], ['second', 'third'], ['first', 'second', 'third']],
    'non-array value becomes an empty array then merges' => [null, [['resources/js/site.js']], ['resources/js/site.js']],
    'multiple array params' => [[], [['a.js', 'b.js'], ['c.css']], ['a.js', 'b.js', 'c.css']],
    'a Collection param merges' => [['first'], [Collection::make(['second', 'third'])], ['first', 'second', 'third']],
    'an initial Collection value is unwrapped' => [Collection::make(['a']), [['b']], ['a', 'b']],
    'a Statamic query builder param merges' => [['first'], [$paramBuilder], ['first', 'from-qb']],
    'an initial query builder value unwraps' => [$valueBuilder, [['second']], ['only', 'second']],
    'null, false, and empty-string params contribute nothing' => [['a'], [null, false, '', 'b'], ['a', 'b']],

    // ── Boundaries (true / 0 / "0" are kept) ──
    'bool true param is kept' => [[], [true], [true]],
    'int 0 param is kept' => [[], [0], [0]],
    'string "0" param is kept' => [[], ['0'], ['0']],
]);
