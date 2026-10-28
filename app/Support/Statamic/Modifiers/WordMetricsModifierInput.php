<?php

declare(strict_types=1);

namespace App\Support\Statamic\Modifiers;

use Statamic\Fields\Value;
use Statamic\Fields\Values;

/**
 * Normalizes Antlers modifier values the same way Statamic core does before
 * counting, so UAMS modifiers differ only in tokenization (whitespace-based).
 */
final class WordMetricsModifierInput
{
    /**
     * Build the string passed to word counting for `word_count_uams`.
     *
     * Mirrors `word_count` input handling, plus the Bard text-array branch
     * from `read_time` so structured content behaves consistently.
     *
     * @param  mixed  $value  Raw modifier value
     * @return string Text without `strip_tags` (same as core `word_count`)
     */
    public static function normalizeForWordCount(
        mixed $value
    ): string {
        // Unwrap Statamic field values
        $unwrapped = self::unwrapValue($value);

        // Collapse Bard-style text blocks like core `read_time`
        $collapsed = self::bardTextArrayToImplodedString($unwrapped);

        // Coerce to string for trimming and splitting downstream
        $text = \is_string($collapsed) ? $collapsed : ((\is_scalar($collapsed) || $collapsed === null || $collapsed instanceof \Stringable) ? (string) $collapsed : '');

        // Unset local variables to free memory
        unset(
            $collapsed,
            $unwrapped
        );

        // Return the text
        return $text;
    }

    /**
     * Build the string passed to word counting for `read_time_uams`.
     *
     * Same normalization as core `read_time`, then `strip_tags` before counts.
     *
     * @param  mixed  $value  Raw modifier value
     * @return string Plain text with tags removed
     */
    public static function normalizeForReadTime(
        mixed $value
    ): string {
        // Reuse word-count normalization (no strip_tags yet)
        $text = self::normalizeForWordCount($value);

        // Strip HTML/XML tags like core `read_time`
        $stripped = strip_tags($text);

        // Unset local variables to free memory
        unset(
            $text
        );

        // Return the stripped text
        return $stripped;
    }

    /**
     * Extract the inner value from a Statamic `Value` wrapper.
     *
     * @param  mixed  $value  Candidate wrapper or raw value
     * @return mixed Inner or original value
     */
    private static function unwrapValue(
        mixed $value
    ): mixed {
        // Check if the value is a single field Value instance
        if ($value instanceof Value) {
            // Unwrap the Value instance to the underlying stored value
            $inner = $value->value();

            // Unset local variables to free memory
            unset(
                $value
            );

            // Return the inner value
            return $inner;
        }

        // Return the original value
        return $value;
    }

    /**
     * If the value is a Bard-style list of `Values`, implode text nodes.
     *
     * @param  mixed  $value  Array of Values or other value
     * @return mixed Imploded string or the original value
     */
    private static function bardTextArrayToImplodedString(
        mixed $value
    ): mixed {
        // Check if the value is not an array
        if (! \is_array($value)) {
            // Return the original value
            return $value;
        }

        // Mirror Statamic\CoreModifiers::readTime array handling
        $imploded = collect($value)
            ->map(

                // Bard text nodes augment to Values; skip anything else. Values wraps a
                // Collection, so all() is already an array and the cast only types it.
                // @pest-mutate-ignore: RemoveArrayCast
                fn (mixed $values): array => $values instanceof Values ? (array) $values->all() : []
            )
            ->where(
                'type',
                'text'
            )
            ->map(
                function (mixed $item): mixed {
                    /** @var array<string, mixed> $item */
                    /** @var Value $text */
                    $text = $item['text'];

                    return $text->raw();
                }
            )
            ->implode(' ');

        // Unset local variables to free memory
        unset(
            $value
        );

        // Return the imploded string
        return $imploded;
    }
}
