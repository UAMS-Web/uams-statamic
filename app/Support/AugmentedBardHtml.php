<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Collection;
use Statamic\Fields\Value;
use Statamic\Fields\Values;

/**
 * Helpers for injecting strings into augmented Bard HTML (post-augmentation
 * fragments), including the first {@code type === 'text'} row when Bard uses sets.
 */
class AugmentedBardHtml
{
    /**
     * Walk augmented Bard output and rewrite the first text row's HTML, or a
     * whole string when the field has no sets.
     *
     * @param  mixed  $value  Augmented Bard value from Antlers
     * @param  string  $injected  Text to inject (skip when empty)
     * @param  mixed  $elementCandidate  Second modifier argument (tag name)
     * @param  callable  $mutateHtml  {@code (string $html, string $injected, string
     *                                $element): string}
     * @return mixed Same outer shape as {@code $value} where applicable
     */
    public static function transformFirstTextBlock(
        mixed $value,
        string $injected,
        mixed $elementCandidate,
        callable $mutateHtml
    ): mixed {
        // ---
        // Pass through when there is nothing to inject
        // ---

        // Check if the injected text is empty
        if ($injected === '') {
            // Return the value unchanged
            return $value;
        }

        // Resolve target element; invalid names fall back to p
        $element = self::normalizeElementName($elementCandidate);

        // ---
        // Handle Bard rendered as a single HTML string (no sets)
        // ---

        // Check if the value is a string
        if (\is_string($value)) {
            // Apply the HTML mutation to the full fragment
            return $mutateHtml($value, $injected, $element);
        }

        // ---
        // Normalize iterable Bard rows to a plain list
        // ---

        // Check if the value is a collection
        if ($value instanceof Collection) {
            // Set the rows to the value of the collection
            $rows = $value->all();
        } elseif (\is_array($value)) { // Check if the value is an array
            // Set the rows to the value
            $rows = $value;
        } else {
            // Return the value unchanged
            return $value;
        }

        // Track whether the first prose (text) row has already been updated
        $updated = false;

        // ---
        // Rebuild rows, mutating only the first matching text block
        // ---

        // Initialize the output array
        $out = [];

        // Iterate over the rows
        foreach ($rows as $key => $row) {
            // Check if this row is the first Bard "text" block we should modify
            if (! $updated && self::isTextRow($row)) {
                // Extract current HTML from the row
                $html = self::textHtmlFromRow($row);

                // Apply the mutation to this fragment only
                /** @var string $html */
                $html = $mutateHtml(
                    $html,
                    $injected,
                    $element
                );

                // Set the row with the updated HTML
                $out[$key] = self::rowWithUpdatedText(
                    $row,
                    $html
                );

                // Set the updated flag to true
                $updated = true;

                // Continue to the next row
                continue;
            }

            // Set the row with the original HTML
            $out[$key] = $row;
        }

        // Return the output array as the final result
        return $out;
    }

    /**
     * Insert text immediately after the first opening tag of {@code $element}
     * (optional attributes). Only the first match in the fragment is changed.
     *
     * @param  string  $html  The HTML fragment to inject into
     * @param  string  $injected  The text to inject
     * @param  string  $element  The element to inject the text after
     * @return string The modified HTML fragment
     */
    public static function injectAfterFirstOpeningTag(
        string $html,
        string $injected,
        string $element
    ): string {
        // ---
        // Build a case-insensitive pattern for the first opening tag of this type
        // ---

        // Quote the element name for the regex pattern
        $quoted = preg_quote(
            $element,
            '/'
        );

        // Build a case-insensitive pattern for the first opening tag of this type
        $pattern = sprintf('/^(\s*)<(%s)(\s[^>]*)?>/i', $quoted);

        // Replace once so only the first matching element is affected; a callback keeps `$n` and `\n` in the injected text literal
        $result = preg_replace_callback(
            $pattern,
            static fn (array $match): string => $match[0].$injected,
            $html,
            // The pattern is anchored at the start, so it can match at most once and any higher limit is equivalent (IncrementInteger)
            // @pest-mutate-ignore: IncrementInteger
            1
        );

        // ---
        // Pass through on PCRE failure
        // ---

        // Check if the result is not a string
        if (! \is_string($result)) {
            // Return the HTML unchanged
            return $html;
        }

        // Return the modified HTML
        return $result;
    }

    /**
     * Insert text immediately before the last closing tag for {@code $element}.
     * If no closing tag exists, the fragment is returned unchanged.
     *
     * @param  string  $html  The HTML fragment to inject into
     * @param  string  $injected  The text to inject
     * @param  string  $element  The element to inject the text before
     * @return string The modified HTML fragment
     */
    public static function injectBeforeLastClosingTag(
        string $html,
        string $injected,
        string $element
    ): string {
        // ---
        // Match closing tags with optional whitespace before {@code >}
        // ---

        // Quote the element name for the regex pattern
        $quoted = preg_quote($element, '/');

        // Build a case-insensitive pattern for the last closing tag of this type
        $pattern = sprintf('/<\/%s\s*>/i', $quoted);

        // Collect every match so we can edit only the final closing tag
        preg_match_all($pattern, $html, $matches, PREG_OFFSET_CAPTURE);

        // ---
        // Leave the fragment unchanged when the closing tag never appears
        // ---

        // Check if there are no matches (a PCRE failure leaves the list empty too)
        if (empty($matches[0])) {
            // Return the HTML unchanged
            return $html;
        }

        // ---
        // Take the byte offset of the last closing tag in the string
        // ---

        // Get the last closing tag in the matches
        $last = $matches[0][array_key_last($matches[0])];

        // Get the byte offset of the last closing tag in the string
        $position = $last[1];

        // Splice injected text before that closing tag
        return substr($html, 0, $position).$injected.substr($html, $position);
    }

    /**
     * Normalize the element parameter to a safe tag name or default {@code p}.
     *
     * @param  mixed  $candidate  The element parameter to normalize
     * @return string The normalized element name
     */
    public static function normalizeElementName(
        mixed $candidate
    ): string {
        // Check if the parameter is not a string
        if (! \is_string($candidate)) {
            // Return the default element name
            return 'p';
        }

        // Trim surrounding whitespace before validation
        $name = trim($candidate);

        // ---
        // Fall back when empty or not a simple HTML local name
        // ---

        // Check if the name is not a simple HTML local name (an empty name never matches)
        if (! preg_match('/^[a-zA-Z][a-zA-Z0-9]*$/', $name)) {
            // Return the default element name
            return 'p';
        }

        // Lowercase so regex {@code i} matching is consistent
        return strtolower($name);
    }

    /**
     * Determine whether a loop row is a Bard prose (text) block.
     *
     * @param  mixed  $row  The row to check
     * @return bool True if the row is a Bard prose (text) block
     *
     * @phpstan-assert-if-true Values|array<mixed> $row
     */
    private static function isTextRow(
        mixed $row
    ): bool {
        // ---
        // Handle Statamic Values instances (augmented Bard with sets)
        // ---

        // Check if the row is a Values instance
        if ($row instanceof Values) {
            // Check if the row type is text
            return $row->raw('type') === 'text';
        }

        // ---
        // Handle plain associative arrays
        // ---

        // Check if the row is an array
        if (\is_array($row)) {
            // Check if the row type is text
            return ($row['type'] ?? null) === 'text';
        }

        // Return false as the row is not a Bard prose (text) block
        return false;
    }

    /**
     * Read the HTML string from a text-type Bard row.
     *
     * @param  Values|array<mixed>  $row  The row to read the HTML string from
     * @return string The HTML string from the row
     */
    private static function textHtmlFromRow(
        Values|array $row
    ): string {
        // ---
        // Handle Statamic Values instances (augmented Bard with sets)
        // ---

        // Check if the row is a Values instance
        if ($row instanceof Values) {
            // Get the text from the row
            $text = $row->raw('text');

            // Return the text as a string
            return self::scalarToString($text);
        }

        // ---
        // Handle plain associative arrays
        // ---

        // Get the text from the row
        $text = $row['text'] ?? '';

        // Check if the text is a Value instance
        if ($text instanceof Value) {
            // Return the text as a string
            return self::scalarToString($text->raw());
        }

        // Return the text as a string
        return self::scalarToString($text);
    }

    /**
     * Build a replacement row with updated HTML for the text field.
     *
     * @param  Values|array<mixed>  $row  The row to build the replacement for
     * @param  string  $html  The HTML string to replace the text with
     * @return array<string, mixed> The replacement row
     */
    private static function rowWithUpdatedText(
        Values|array $row,
        string $html
    ): array {
        // ---
        // Handle Statamic Values instances (augmented Bard with sets)
        // ---

        // Check if the row is a Values instance
        if ($row instanceof Values) {
            /** @var array<string, mixed> $rawArray */
            $rawArray = $row->toRawArray();

            // Return the row with the updated HTML
            return [
                ...$rawArray,
                'text' => $html,
            ];
        }

        // ---
        // Handle plain associative arrays
        // ---

        /** @var array<string, mixed> $row */
        // Return the row with the updated HTML
        return [
            ...$row,
            'text' => $html,
        ];
    }

    /**
     * Coerce a scalar to string for HTML fragments.
     *
     * @param  mixed  $value  The value to coerce to a string
     * @return string The coerced string
     */
    private static function scalarToString(
        mixed $value
    ): string {
        // Return a scalar as a string, and an empty string for null or a non-scalar
        return \is_scalar($value) ? (string) $value : '';
    }
}
