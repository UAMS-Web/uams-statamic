<?php

declare(strict_types=1);

namespace App\Modifiers;

use App\Support\Statamic\Modifiers\WhitespaceDelimitedWordMetrics;
use App\Support\Statamic\Modifiers\WordMetricsModifierInput;
use Statamic\Modifiers\Modifier;

/**
 * Antlers modifier: word count using whitespace-delimited tokens only.
 *
 * Same inputs as Statamic `word_count`; differs only in how tokens are split
 * (see `WhitespaceDelimitedWordMetrics`).
 */
class WordCountUams extends Modifier
{
    /**
     * Count words in the normalized value.
     *
     * @param  mixed  $value  Value to measure
     * @param  array<int|string, mixed>  $params  Unused
     * @param  array<int|string, mixed>  $context  Runtime context (unused)
     * @return int Non-negative word count
     */
    public function index(
        mixed $value,
        array $params,
        array $context
    ): int {
        // Normalize like core word_count / read_time array path
        $text = WordMetricsModifierInput::normalizeForWordCount($value);

        // Apply UAMS whitespace-only tokenization
        $count = WhitespaceDelimitedWordMetrics::countWords($text);

        // Unset local variables to free memory
        unset(
            $text
        );

        // Return the count
        return $count;
    }
}
