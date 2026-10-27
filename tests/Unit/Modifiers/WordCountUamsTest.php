<?php

declare(strict_types=1);

use App\Modifiers\WordCountUams;

/**
 * Behavior of the `word_count_uams` Antlers modifier (app/Modifiers/WordCountUams.php):
 * count words using whitespace-only tokenization (so punctuation like commas inside a
 * token does not split it, unlike core word_count), after the same tag-stripping
 * normalization as core word_count. The tokenization/counting lives in
 * WhitespaceDelimitedWordMetrics (covered by its own test); this covers the modifier's
 * wiring + the whitespace-only counting contract.
 *
 * Mutation: the wrapper has no mutable surface (pure delegation); the counting is
 * mutation-tested under WhitespaceDelimitedWordMetricsTest.
 *
 * @command  php artisan test --compact tests/Unit/Modifiers/WordCountUamsTest.php
 */
it('counts whitespace-delimited words', function (mixed $value, int $expected) {
    expect((new WordCountUams)->index($value, [], []))->toBe($expected);
})->with([
    'comma-separated digits count as one token' => ['one 3,485 two', 3],
    'does not split on commas without whitespace' => ['a,b,c', 1],
    'strips tags before counting' => ['<p>one two</p>', 2],
    'empty string is zero' => ['', 0],
    'whitespace-only is zero' => ["  \n\t  ", 0],
    'collapses runs of whitespace' => ['  one   two  ', 2],
    'a single word is one' => ['word', 1],
]);
