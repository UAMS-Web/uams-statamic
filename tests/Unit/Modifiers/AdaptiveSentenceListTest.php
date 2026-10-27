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
 * Mutation: every killable mutant is dead (score ~92%). The residual survivors leave
 * the modifier's returned string unchanged for every reachable input — `(string)` casts
 * the non-strict concat / return-type / param coercion already performs (lines 62, 189,
 * 193), the strict-comparison flag of an all-boolean `in_array` (line 74; the needle
 * `true` IS tested), the empty-list guard (`makeSentenceList([])` already returns `''`,
 * lines 80-81), the unreachable `?? false` default (`$commaFlags[0]` always exists after
 * `array_values`, line 119), and the redundant null/false guard in `scalarToString`
 * (null falls to the final `''`, false to `is_scalar`→`""`, lines 184-185). One survivor
 * is output-equivalent but not silent: removing the absent-key early return in `boolParam`
 * (line 158) returns the same boolean (both call sites pass default `true`) yet emits an
 * "undefined array key" warning — a defensive guard the suite's warning config does not
 * escalate to a failure, not a missing assertion. All are left clean rather than annotated
 * with `@pest-mutate-ignore`.
 *
 * @command  php artisan test --compact tests/Unit/Modifiers/AdaptiveSentenceListTest.php
 */
it('joins a list per the comma-aware rules', function (mixed $value, array $params, string $expected) {
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
