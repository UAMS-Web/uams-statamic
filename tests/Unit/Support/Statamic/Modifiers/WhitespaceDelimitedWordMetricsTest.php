<?php

declare(strict_types=1);

use App\Support\Statamic\Modifiers\WhitespaceDelimitedWordMetrics;

/**
 * Behavior of WhitespaceDelimitedWordMetrics
 * (app/Support/Statamic/Modifiers/WhitespaceDelimitedWordMetrics.php): word counts
 * and reading-time math using whitespace-only tokens — countWords (split on Unicode
 * whitespace runs), readingTimeSecondsFromText (ceil(words / wpm * 60), wpm clamped
 * to >= 1), readingTimeMinutesFromText (ceil(seconds / 60)), and
 * readingTimeSecondsRoundedUpToWholeMinutesFromText (minutes * 60). Shared by
 * word_count_uams, read_time_uams, and PostsReadingMetricsService.
 *
 * @command  php artisan test --compact tests/Unit/Support/Statamic/Modifiers/WhitespaceDelimitedWordMetricsTest.php
 */
it('counts words by whitespace runs only', function (string $text, int $expected) {
    expect(WhitespaceDelimitedWordMetrics::countWords($text))->toBe($expected);
})->with([
    'comma-formatted number stays one token' => ['one 3,485 two', 3],
    'whitespace-only is zero' => ['   ', 0],
    'empty string is zero' => ['', 0],
    'single word is one' => ['hello', 1],
    'mixed whitespace (tabs/newlines) splits' => ["a\tb\nc  d", 4],
    'leading/trailing whitespace is trimmed' => ['  one two  ', 2],
]);

it('reads seconds as ceil(words / wpm * 60), clamping wpm to >= 1', function (string $text, int $wpm, int $expected) {
    expect(WhitespaceDelimitedWordMetrics::readingTimeSecondsFromText($text, $wpm))->toBe($expected);
})->with([
    'no words is zero' => ['', 200, 0],
    'four words at 200 wpm -> ceil(1.2) = 2' => ['one two three four', 200, 2],
    'four words at 2 wpm -> 120' => ['one two three four', 2, 120],
    'wpm 0 is clamped to 1 -> 240' => ['one two three four', 0, 240],
    'negative wpm is clamped to 1 -> 240' => ['one two three four', -5, 240],
    'very high wpm still ceils up to 1' => ['one two three four', 400, 1],
]);

it('reads minutes as ceil(seconds / 60)', function (string $text, int $expected) {
    expect(WhitespaceDelimitedWordMetrics::readingTimeMinutesFromText($text))->toBe($expected);
})->with([
    'no words is zero' => ['', 0],
    'four words -> 2 seconds -> 1 minute' => ['one two three four', 1],
    '201 words -> 61 seconds -> 2 minutes' => [implode(' ', array_fill(0, 201, 'w')), 2],
]);

it('derives minutes from the true second estimate', function (string $text) {
    expect(WhitespaceDelimitedWordMetrics::readingTimeMinutesFromText($text))
        ->toBe((int) ceil(WhitespaceDelimitedWordMetrics::readingTimeSecondsFromText($text) / 60));
})->with([
    'four words' => ['one two three four'],
    '201 words' => [implode(' ', array_fill(0, 201, 'w'))],
    'empty' => [''],
]);

it('reads seconds rounded up to whole-minute buckets', function (string $text, int $expected) {
    expect(WhitespaceDelimitedWordMetrics::readingTimeSecondsRoundedUpToWholeMinutesFromText($text))->toBe($expected);
})->with([
    'no words is zero' => ['', 0],
    'four words -> 1-minute bucket -> 60' => ['one two three four', 60],
    '401 words -> 121 seconds -> 3-minute bucket -> 180' => [implode(' ', array_fill(0, 401, 'w')), 180],
]);
