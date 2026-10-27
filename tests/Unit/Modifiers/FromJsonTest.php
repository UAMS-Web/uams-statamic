<?php

declare(strict_types=1);

use App\Modifiers\FromJson;
use Statamic\Fields\Value;

/**
 * Behavior of the `from_json` Antlers modifier (app/Modifiers/FromJson.php):
 * decode a JSON string (the inverse of core `to_json`), unwrapping a Statamic
 * Value, trimming whitespace, returning null for empty/invalid/non-string input,
 * and choosing associative arrays (default) vs stdClass objects via the first flag.
 *
 * Mutation: every killable mutant is dead (score ~95%). The two residual survivors are
 * output-equivalent redundant guards — removing the empty-string `=== ''` early return
 * still yields null (an empty string is also invalid JSON, so the json_last_error guard
 * returns null), and removing the json-error early return still yields null (json_decode
 * already returned null on error, so `return $decoded` is null). Left clean rather than
 * annotated with `@pest-mutate-ignore`. (The conversion also dropped the method's dead
 * `unset()` cruft, which had masked the non-string-guard mutant.)
 *
 * @command  php artisan test --compact tests/Unit/Modifiers/FromJsonTest.php
 */
it('decodes JSON to an associative array, or null', function (mixed $value, array $params, mixed $expected) {
    expect((new FromJson)->index($value, $params, []))->toBe($expected);
})->with([
    // ── Original cases ──
    'object decodes to an associative array' => ['{"@type":"Language","name":"English"}', [], ['@type' => 'Language', 'name' => 'English']],
    'invalid JSON returns null' => ['{not json', [], null],
    'whitespace-only string returns null' => ["  \n  ", [], null],
    'a Value-wrapped string decodes' => [new Value('[1,2]'), [], [1, 2]],
    'a non-string value returns null' => [['already' => 'array'], [], null],

    // ── Input guards ──
    'an empty string returns null' => ['', [], null],
    'a null value returns null' => [null, [], null],

    // ── Associative flag stays on (default + non-object-mode tokens) ──
    'default flag stays associative' => ['{"a":1}', [], ['a' => 1]],
    'true flag stays associative' => ['{"a":1}', [true], ['a' => 1]],
    'int 1 flag stays associative' => ['{"a":1}', [1], ['a' => 1]],
    'unknown string flag stays associative' => ['{"a":1}', ['nope'], ['a' => 1]],
    'singular "object" stays associative' => ['{"a":1}', ['object'], ['a' => 1]],
    'a 2-element array flag stays associative' => ['{"a":1}', [[false, false]], ['a' => 1]],
]);

it('decodes objects to stdClass for object-mode flags', function (mixed $flag) {
    $out = (new FromJson)->index('{"a":1}', [$flag], []);

    // toBeInstanceOf gives the object-vs-array distinction; the JSON compare keeps
    // the value strict (int 1, not "1") without a mixed property access.
    expect($out)->toBeInstanceOf(stdClass::class);
    expect(json_encode($out))->toBe('{"a":1}');
})->with([
    'false (bool)' => [false],
    'zero (int)' => [0],
    'zero (string)' => ['0'],
    '"false" (string)' => ['false'],
    '"objects" token' => ['objects'],
    'uppercase "OBJECTS"' => ['OBJECTS'],
    'whitespace-padded "objects"' => ['  objects  '],
    'single-element [false] array unwraps' => [[false]],
]);
