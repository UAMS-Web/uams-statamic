<?php

declare(strict_types=1);

namespace App\Modifiers;

use Illuminate\Support\Collection;
use Statamic\Modifiers\Modifier;
use Statamic\Support\Str;

/**
 * Antlers modifier: join an array into a human-readable phrase (like
 * {@see Str::makeSentenceList()} and Statamic's `sentence_list`, see
 * https://statamic.dev/modifiers/sentence_list) with extra rules when list
 * items contain commas (e.g. credentials in names).
 *
 * Two items use `, {glue} ` when the first item contains a comma (after
 * stripping tags for the check); otherwise they use ` {glue} ` like core
 * {@see Str::makeSentenceList()}.
 *
 * When the third flag is on and any item contains a comma, three or more
 * items use semicolons between entries and `{glue}` before the last. When
 * that flag is off, three or more items always use
 * {@see Str::makeSentenceList()} (comma list and optional oxford comma).
 */
class AdaptiveSentenceList extends Modifier
{
    /**
     * Build a delimited phrase from a list of strings (often HTML fragments).
     *
     * @param  mixed  $value  Array or {@see Collection} of items castable to string
     * @param  array<int|string, mixed>  $params  {@code [0]} glue (default {@code 'and'}), {@code [1]}
     *                                            oxford comma {@code 1|0} (default {@code 1}),
     *                                            {@code [2]} semicolon mode when any item has a comma
     *                                            {@code 1|0} (default {@code 1})
     * @param  array<string, mixed>  $context  Unused Statamic modifier context
     * @return string Joined phrase (HTML fragments preserved when passed in)
     */
    public function index(
        $value,
        array $params,
        array $context
    ): string {
        // Normalize Collection and other list shapes to a plain array
        if ($value instanceof Collection) {
            $value = $value->all();
        }

        // Pass through non-lists as a string for Antlers consistency
        if (! \is_array($value)) {
            return $this->scalarToString($value);
        }

        // Coerce each entry to string for joining and comma detection
        $items = array_values(
            array_map(
                $this->scalarToString(...),
                $value
            )
        );

        // Resolve glue word, oxford comma, and semicolon-escalation flags
        $glueParam = $params[0] ?? 'and';
        $glue = \is_scalar($glueParam) ? (string) $glueParam : 'and';
        $oxfordComma = $this->boolParam($params, 1, true);
        $semicolonWhenComma = $this->boolParam($params, 2, true);

        // Detect commas using tag-stripped text only
        $commaFlags = array_map(
            fn (string $item): bool => str_contains(
                $this->textForCommaCheck($item),
                ','
            ),
            $items
        );
        $anyComma = \in_array(true, $commaFlags, true);

        // Initialize count after normalization
        $count = \count($items);

        // Return nothing for an empty list
        if ($count === 0) {
            return '';
        }

        // Return the sole item unchanged
        if ($count === 1) {
            return $items[0];
        }

        // Join exactly two items (comma in first item uses ", glue " not " glue ")
        if ($count === 2) {
            return $this->joinTwoItems(
                $items,
                $commaFlags,
                $glue
            );
        }

        // Join three or more items (semicolon mode or standard sentence list)
        if ($semicolonWhenComma && $anyComma) {
            return $this->joinManyWithSemicolons($items, $glue);
        }

        return Str::makeSentenceList($items, $glue, $oxfordComma);
    }

    /**
     * Join two items per comma-aware rules.
     *
     * @param  array<int, string>  $items
     * @param  array<int, bool>  $commaFlags  Whether each stripped item contains ','
     * @param  string  $glue  Word before the final segment (e.g. {@code and})
     */
    private function joinTwoItems(
        array $items,
        array $commaFlags,
        string $glue
    ): string {
        // Use ", glue " when the first item contains a comma (credentials, etc.)
        if ($commaFlags[0] ?? false) {
            return $items[0].', '.$glue.' '.$items[1];
        }

        // Otherwise mirror Statamic two-item sentence list spacing
        return $items[0].' '.$glue.' '.$items[1];
    }

    /**
     * Join three or more items with semicolons and a final ", glue " segment.
     *
     * @param  array<int, string>  $items
     * @param  string  $glue  Word before the last item
     */
    private function joinManyWithSemicolons(
        array $items,
        string $glue
    ): string {
        // Peel off the last entry for the closing phrase
        $last = array_pop($items);

        // Build "a; b; glue last"
        return implode('; ', $items).'; '.$glue.' '.$last;
    }

    /**
     * Read a boolean Statamic modifier argument ({@code 0}, {@code 1}, etc.).
     *
     * @param  array<int|string, mixed>  $params  Modifier parameter list
     * @param  int  $key  Zero-based index
     * @param  bool  $default  Value when the key is absent
     */
    private function boolParam(
        array $params,
        int $key,
        bool $default
    ): bool {
        // Use the default when the parameter was not passed
        if (! \array_key_exists($key, $params)) {
            return $default;
        }

        // Treat false, 0, and "0" as off
        $value = $params[$key];

        return ! in_array($value, [false, 0, '0'], true);
    }

    /**
     * Strip tags so commas inside markup do not affect detection.
     */
    private function textForCommaCheck(string $html): string
    {
        return strip_tags($html);
    }

    /**
     * Cast a single modifier value to string.
     */
    private function scalarToString(mixed $value): string
    {
        if ($value === null || $value === false) {
            return '';
        }

        if (\is_scalar($value)) {
            return (string) $value;
        }

        if (\is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        return '';
    }
}
