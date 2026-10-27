<?php

declare(strict_types=1);

use App\Modifiers\FirstNonEmptyList;
use Illuminate\Support\Collection;

covers(FirstNonEmptyList::class);

/**
 * Behavior of the `first_non_empty_list` Antlers modifier
 * (app/Modifiers/FirstNonEmptyList.php): normalize each parameter with the same
 * rules as NormalizeList and return the first that resolves to a non-empty list,
 * or [] when every candidate is empty. The left-hand pipe value is ignored. The
 * normalization rules themselves are covered by NormalizeListTest.
 *
 * @covers \App\Modifiers\FirstNonEmptyList
 *
 * @command  php artisan test --compact tests/Unit/Modifiers/FirstNonEmptyListTest.php
 */
it('returns the first candidate that normalizes to a non-empty list', function (mixed $value, array $params, array $expected) {
    expect((new FirstNonEmptyList)->index($value, $params, []))->toBe($expected);
})->with([
    'returns empty when all candidates are empty' => [[], [[], [], []], []],
    'skips empty arrays and returns the first non-empty' => [[], [[], ['term-a'], ['term-b']], ['term-a']],
    'returns the first non-empty mid-chain (Collection)' => [[], [[], [], Collection::make(['winner'])], ['winner']],
    'the first non-empty candidate wins (no skipping)' => [[], [['a'], ['b']], ['a']],
    'ignores the left-hand pipe value' => [['ignored'], [['use-this']], ['use-this']],
    'returns empty when there are no candidates' => [[], [], []],
]);
