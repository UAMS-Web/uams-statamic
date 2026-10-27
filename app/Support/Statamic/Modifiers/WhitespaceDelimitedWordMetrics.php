<?php

declare(strict_types=1);

namespace App\Support\Statamic\Modifiers;

/**
 * Word counts and reading-time math using whitespace-delimited tokens only.
 *
 * Statamic’s `word_count` splits on punctuation as well as whitespace; this
 * keeps tokens like `3,485` or `www.example.com` as single units so counts
 * align with common paste-based tools and `PostsReadingMetricsService`.
 */
final class WhitespaceDelimitedWordMetrics
{
    /**
     * Default words per minute (matches Statamic `read_time` with no parameter).
     */
    public const int DEFAULT_WORDS_PER_MINUTE = 200;

    /**
     * Count words as runs separated by Unicode whitespace.
     *
     * @param  string  $text  Plain text (may be empty)
     * @return int Non-negative token count
     */
    public static function countWords(
        string $text
    ): int {
        // Trim outer whitespace before splitting
        $trimmed = trim($text);

        // Check if the trimmed text is empty
        if ($trimmed === '') {
            // Unset local variable to free memory
            unset(
                $trimmed
            );

            // Return zero when there is nothing to count
            return 0;
        }

        // Split on runs of Unicode whitespace
        $tokens = preg_split(
            '/\s+/u',
            $trimmed,
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        // Count the tokens
        $count = \is_array($tokens) ? \count($tokens) : 0;

        // Unset local variables to free memory
        unset(
            $trimmed,
            $tokens
        );

        // Return the count of tokens
        return $count;
    }

    /**
     * Reading time in whole minutes, derived from the true second estimate.
     *
     * Uses `readingTimeSecondsFromWordCount` (same formula as
     * `readingTimeSecondsFromText`), then `ceil(seconds / 60)` so minutes align
     * with the second-based rate (`words / wpm`), not `ceil(words / wpm)` alone
     * (which can differ for short passages).
     *
     * @param  string  $text  Plain text (tags should already be stripped if needed)
     * @param  int  $wordsPerMinute  Divisor for the estimate (below 1 is treated as 1)
     * @return int Minutes, at least zero
     */
    public static function readingTimeMinutesFromText(
        string $text,
        int $wordsPerMinute = self::DEFAULT_WORDS_PER_MINUTE
    ): int {
        // Count whitespace-delimited tokens
        $words = self::countWords($text);

        // Build the ceiled second estimate from the same word count
        $seconds = self::readingTimeSecondsFromWordCount(
            $words,
            $wordsPerMinute
        );

        // Derive whole minutes from seconds (`ceil(0/60)` is zero)
        $minutes = (int) ceil($seconds / 60);

        // Unset local variables to free memory
        unset(
            $seconds,
            $words
        );

        // Return the minutes
        return $minutes;
    }

    /**
     * Seconds aligned to whole-minute buckets from the same minute estimate.
     *
     * Returns `readingTimeMinutesFromText(...) * 60`, so sub-minute true
     * durations (e.g. a few seconds of reading time) still report as 60 seconds
     * when the minute bucket is 1.
     *
     * @param  string  $text  Plain text (tags should already be stripped if needed)
     * @param  int  $wordsPerMinute  Divisor for the estimate (below 1 is treated as 1)
     * @return int Seconds, multiple of 60 when positive
     */
    public static function readingTimeSecondsRoundedUpToWholeMinutesFromText(
        string $text,
        int $wordsPerMinute = self::DEFAULT_WORDS_PER_MINUTE
    ): int {
        // Reuse the same whole-minute bucket as `readingTimeMinutesFromText`
        $minutes = self::readingTimeMinutesFromText(
            $text,
            $wordsPerMinute
        );

        // Express that bucket as seconds
        $seconds = $minutes * 60;

        // Unset local variable to free memory
        unset(
            $minutes
        );

        // Return the seconds
        return $seconds;
    }

    /**
     * Reading time in whole seconds from the word count and WPM rate.
     *
     * Uses `ceil(words / wpm * 60)` with a minimum effective WPM of 1 to avoid
     * division by zero.
     *
     * @param  string  $text  Plain text (tags should already be stripped if needed)
     * @param  int  $wordsPerMinute  Divisor for the estimate (below 1 is treated as 1)
     * @return int Seconds, at least zero
     */
    public static function readingTimeSecondsFromText(
        string $text,
        int $wordsPerMinute = self::DEFAULT_WORDS_PER_MINUTE
    ): int {
        // Count whitespace-delimited tokens
        $words = self::countWords($text);

        // Convert word count and WPM into a ceiled second estimate
        $seconds = self::readingTimeSecondsFromWordCount(
            $words,
            $wordsPerMinute
        );

        // Unset local variable to free memory
        unset(
            $words
        );

        // Return the seconds
        return $seconds;
    }

    /**
     * Ceiled seconds for a word count and words-per-minute rate.
     *
     * @param  int  $words  Non-negative token count
     * @param  int  $wordsPerMinute  Reading rate (values below 1 use 1)
     * @return int Whole seconds, zero when `words` is zero
     */
    private static function readingTimeSecondsFromWordCount(
        int $words,
        int $wordsPerMinute
    ): int {
        // Return zero immediately when there is nothing to read
        if ($words === 0) {
            return 0;
        }

        // Avoid division by zero or negative rates
        $effectiveWpm = max(
            1,
            $wordsPerMinute
        );

        // True duration: fractional minutes scaled to seconds, rounded up
        $seconds = (int) ceil($words / $effectiveWpm * 60);

        // Unset local variable to free memory
        unset(
            $effectiveWpm
        );

        // Return the seconds
        return $seconds;
    }
}
