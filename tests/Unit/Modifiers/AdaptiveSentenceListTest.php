<?php

declare(strict_types=1);

use App\Modifiers\AdaptiveSentenceList;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Behavior of the `adaptive_sentence_list` Antlers modifier
 * (app/Modifiers/AdaptiveSentenceList.php): join a list into a human-readable
 * phrase, escalating to semicolons when any item contains a comma (credentials
 * in names), with glue/oxford/semicolon flags, tag-stripped comma detection,
 * Collection normalization, and scalar/empty input guards.
 *
 * Equivalent mutants from the #816 baseline:
 * - Disabling strict `in_array` comparison still compares only booleans.
 * - Changing the empty-list count to -1 or removing the empty-list return
 *   sends the empty list to `makeSentenceList`, which also returns `''`.
 * - Changing the two-item comma fallback cannot matter: each of the two
 *   reindexed items produces a flag, so flag zero always exists.
 * - Requiring both null and false, or removing their early return, sends
 *   null to the final `''` and false to a scalar cast that also yields `''`.
 * The two behavior-changing mutations are covered below; the closing
 * mutation pass is separate.
 *
 * @command  php artisan test --compact tests/Unit/Modifiers/AdaptiveSentenceListTest.php
 */
it('joins a list per the comma-aware rules', function (mixed $value, array $params, string $expected): void {
    expect((new AdaptiveSentenceList)->index($value, $params, []))->toBe($expected);
})->with([
    // ── Original cases (behavior-preserving port of the PHPUnit class) ──
    'two items without commas use plain glue' => [['John Doe', 'Jane Doe'], [], 'John Doe and Jane Doe'],
    'three items default to the oxford comma' => [['John Doe', 'Jane Doe', 'John Smith'], [], 'John Doe, Jane Doe, and John Smith'],
    'three items respect oxford off' => [['John Doe', 'Jane Doe', 'John Smith'], ['and', 0], 'John Doe, Jane Doe and John Smith'],
    'two items, comma in first, use ", glue "' => [['John Doe, M.D.', 'Jane Doe'], [], 'John Doe, M.D., and Jane Doe'],
    'two items, comma only in second, use plain glue' => [['John Doe', 'Jane Doe, M.D.'], [], 'John Doe and Jane Doe, M.D.'],
    'three items, comma in first, use semicolons' => [['John Doe, M.D.', 'Jane Doe', 'John Smith'], [], 'John Doe, M.D.; Jane Doe; and John Smith'],
    'three items, comma in middle, use semicolons' => [['John Doe', 'Jane Doe, M.D.', 'John Smith'], [], 'John Doe; Jane Doe, M.D.; and John Smith'],
    'three items, comma in last, use semicolons' => [['John Doe', 'Jane Doe', 'John Smith, M.D.'], [], 'John Doe; Jane Doe; and John Smith, M.D.'],
    'three items, all credentials, use semicolons' => [['John Doe, M.D.', 'Jane Doe, M.D.', 'John Smith, M.D.'], [], 'John Doe, M.D.; Jane Doe, M.D.; and John Smith, M.D.'],
    'comma detection ignores markup commas' => [['<span>John Doe</span>', '<span>Jane Doe</span>', '<span>John Smith</span>'], [], '<span>John Doe</span>, <span>Jane Doe</span>, and <span>John Smith</span>'],
    'comma inside stripped text triggers semicolon mode' => [['<span>John Doe, M.D.</span>', '<span>Jane Doe</span>', '<span>John Smith</span>'], [], '<span>John Doe, M.D.</span>; <span>Jane Doe</span>; and <span>John Smith</span>'],
    'semicolon mode off falls back to the sentence list' => [['John Doe, M.D.', 'Jane Doe', 'John Smith'], ['and', 1, 0], 'John Doe, M.D., Jane Doe, and John Smith'],
    'custom glue parameter' => [['a', 'b'], ['&'], 'a & b'],
    'numeric glue is rendered as text' => [['a', 'b'], [0], 'a 0 b'],
    'collection input is normalized' => [Collection::make(['x', 'y']), [], 'x and y'],
    'empty array returns an empty string' => [[], [], ''],
    'single item is returned unchanged' => [['Only'], [], 'Only'],
    'non-array value is coerced to a string' => ['plain', [], 'plain'],

    // ── boolParam: every off-trigger and the truthy fall-through ──
    'oxford off via int 0' => [['a', 'b', 'c'], ['and', 0], 'a, b and c'],
    'oxford off via string "0"' => [['a', 'b', 'c'], ['and', '0'], 'a, b and c'],
    'oxford off via false' => [['a', 'b', 'c'], ['and', false], 'a, b and c'],
    'oxford on via int 1' => [['a', 'b', 'c'], ['and', 1], 'a, b, and c'],
    'oxford on via any other truthy' => [['a', 'b', 'c'], ['and', 'yes'], 'a, b, and c'],
    'semicolon mode off via int 0' => [['a, x', 'b', 'c'], ['and', 1, 0], 'a, x, b, and c'],
    'semicolon mode off via string "0"' => [['a, x', 'b', 'c'], ['and', 1, '0'], 'a, x, b, and c'],
    'semicolon mode off via false' => [['a, x', 'b', 'c'], ['and', 1, false], 'a, x, b, and c'],

    // ── glue resolution: non-scalar glue falls back to "and" ──
    'non-scalar glue falls back to "and"' => [['a', 'b'], [['array']], 'a and b'],
    'null glue falls back to "and"' => [['a', 'b'], [null], 'a and b'],

    // ── scalarToString: per-item null/false/scalar/Stringable coercion ──
    'null and false items become empty strings' => [['a', null, false], [], 'a, , and '],
    'numeric items are stringified' => [[1, 2.5], [], '1 and 2.5'],
    'a Stringable item is cast via __toString' => [[Str::of('x'), 'y'], [], 'x and y'],
    'a non-stringable object item becomes empty' => [[new stdClass, 'y'], [], ' and y'],
    'a null scalar value passes through as empty' => [null, [], ''],
    'a false scalar value passes through as empty' => [false, [], ''],
    'a boolean true item stringifies to "1"' => [[true, 'y'], [], '1 and y'],

    // ── Mutation-driven adversarial rows ──
    // array_values() reindex: associative input must collapse to a positional list.
    'associative array keys are reindexed' => [['x' => 'a', 'y' => 'b'], [], 'a and b'],
    // single-item guard: a lone credentialed item must NOT escalate to semicolons.
    'single credentialed item is returned unchanged' => [['Jane Roe, M.D.'], [], 'Jane Roe, M.D.'],
    // strip_tags before comma check: a comma only inside markup must not trigger semicolons.
    'comma only inside markup is ignored' => [['<a href="/a,b">John</a>', 'Jane', 'Joe'], [], '<a href="/a,b">John</a>, Jane, and Joe'],
]);

it('uses the default flags without warning when omitted', function (): void {
    // Fail if a missing flag is read instead of defaulted.
    set_error_handler(static function (int $severity, string $message): never {
        throw new ErrorException($message, 0, $severity);
    }, E_WARNING);

    try {
        expect((new AdaptiveSentenceList)->index(['a, x', 'b', 'c'], [], []))
            ->toBe('a, x; b; and c');
    } finally {
        restore_error_handler();
    }
});
