<?php

declare(strict_types=1);

use App\Modifiers\UniqueValues;

/**
 * Behavior of the `unique_values` Antlers modifier (app/Modifiers/UniqueValues.php):
 * normalize list input with NormalizeList, then drop entries whose stable key was
 * already seen, preserving first-seen order. Keys are a prefixed serialize() for
 * scalars/null, spl_object_id for objects, and serialize() for arrays — so the int
 * 1 and string "1" dedupe separately, and distinct object instances are kept. The
 * normalization is covered by NormalizeListTest.
 *
 * Mutation: every killable mutant is dead (score ~55%); this is a thin dedup loop, so
 * the residual survivors are all equivalents — the dedup-key prefixes and concat order
 * ('s:'/'o:'/'x:') are cosmetic (serialize() and spl_object_id() are already
 * unambiguous, so any consistent unique key dedupes identically), and the
 * `$seen[$key] = true` marker value is irrelevant because the membership check is
 * `isset()` (key existence, not truthiness). Left clean rather than annotated with
 * `@pest-mutate-ignore`.
 *
 * @command  php artisan test --compact tests/Unit/Modifiers/UniqueValuesTest.php
 */
$objectA = new stdClass;
$objectB = new stdClass;

it('removes duplicate values, preserving first-seen order', function (mixed $value, array $expected) {
    expect((new UniqueValues)->index($value, [], []))->toBe($expected);
})->with([
    'deduplicates strings, preserving first occurrence' => [['a', 'b', 'a', 'c', 'b'], ['a', 'b', 'c']],
    'empty input returns an empty array' => [[], []],
    'scalar keys distinguish type (1 vs "1", true vs "true")' => [[1, '1', 1, true, 'true', true], [1, '1', true, 'true']],
    'null deduplicates and is distinct from 0 / "0"' => [[null, null, 0, '0'], [null, 0, '0']],
    'arrays deduplicate by serialized value' => [[[1, 2], [1, 2], [3]], [[1, 2], [3]]],
    'objects deduplicate by instance identity' => [[$objectA, $objectA, $objectB], [$objectA, $objectB]],
]);
