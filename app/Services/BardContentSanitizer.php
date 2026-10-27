<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Service for sanitizing Bard content to prevent YAML parsing issues.
 *
 * This service handles the sanitization of Bard field content to prevent
 * YAML document separators from causing parsing errors and to ensure
 * non-breaking space text (`\_`) is double-quoted. It addresses:
 * - '...' and '---' appearing as text nodes in Bard content
 * - Unquoted text values containing `\_` (e.g. `text: \_and\_`)
 *
 * ## This is NOT an XSS control
 *
 * Despite "Sanitizer" in the name, nothing here removes or neutralizes unsafe
 * markup. This class is a YAML-safety helper and only ever quotes text values;
 * it never inspects content for scripts, event handlers, or unsafe URL schemes,
 * and it is not on the path that would stop them.
 *
 * The component that actually discards unsafe markup is
 * `UAMSWeb\WordPressImporter\Services\WordPressImport\Utilities\BardParser`,
 * whose Tiptap schema is an allowlist: `<script>`, `<iframe>`, event-handler
 * attributes and `javascript:`/`data:` hrefs are dropped at parse time because
 * the schema never declares them. That invariant is asserted by
 * `tests/Unit/WordPressImport/BardParserScriptStrippingTest.php` and, end to
 * end, by `tests/Feature/WordPressImportScriptStrippingTest.php` (UAMS-Web/uams-statamic-archive#1742).
 *
 * ## Where that allowlist stops
 *
 * It covers node *types* and *marks* — `Tiptap\Core\Schema::apply()` filters
 * those and nothing else. It does **not** filter node *attributes*. The vanilla
 * nodes carry no HTML-bearing attributes, so for them the distinction is moot,
 * but the importer's custom `set` node copies `data-set-attr-*` values from the
 * source HTML into `attrs.values` verbatim. Those values are therefore a
 * separate trust boundary that this allowlist does not cover — tracked as a
 * known gap in UAMS-Web/wordpress-importer#494 and pinned by
 * `tests/Unit/WordPressImport/BardParserSetAttributePassthroughTest.php`.
 *
 * Do not add XSS handling here, and do not read this class's presence as
 * evidence that a Bard field has been made safe to render.
 *
 * ## Problem Context
 *
 * When a Bard field contains text nodes with YAML document separators
 * (`...` or `---`) as the last content, Statamic throws the error
 * "Invalid content, text values must be strings". This occurs because YAML
 * parsers interpret `...` and `---` as document terminators rather than literal
 * string values when they appear at the end of content.
 *
 * ## Technical Details
 *
 * The service recursively processes Bard content arrays and escapes any text
 * nodes that contain YAML document separators by wrapping them in single quotes.
 * This prevents the YAML parser from interpreting them as document terminators.
 *
 * ## Upstream Monitoring
 *
 * This is a workaround for a bug in the interaction between Statamic's YAML
 * parsing and Tiptap's Bard editor. Developers should monitor:
 *   - Statamic CMS updates for YAML parsing improvements
 *   - Tiptap editor updates for Bard field handling
 *   - Symfony YAML component updates for parser behavior changes
 *
 * This service may become unnecessary or require modification if upstream
 * libraries implement proper handling of YAML separators in content fields.
 */
class BardContentSanitizer
{
    /**
     * YAML document separators that need to be escaped.
     *
     * These are special YAML tokens that can cause parsing errors when they appear
     * as literal text content in Bard fields. They must be quoted to prevent the
     * YAML parser from interpreting them as document structure markers.
     */
    private const array YAML_SEPARATORS = ['...', '---'];

    /**
     * Sanitize Bard content to prevent YAML parsing issues.
     *
     * This method recursively processes Bard content arrays and escapes
     * any text nodes that contain YAML document separators by wrapping
     * them in single quotes.
     *
     * @param  ?list<mixed>  $content  The Bard content array to sanitize
     * @return ?list<mixed> The sanitized content array, or null if input is null/empty
     */
    public function sanitize(
        ?array $content
    ): ?array {
        // ---
        // Early return for null or empty content to avoid unnecessary processing
        // ---

        // Check if content is empty
        if ($content === null || $content === []) {
            // Return content unchanged
            return $content;
        }

        // Process the content recursively to handle nested structures
        return $this->processContent($content);
    }

    /**
     * Process content recursively to sanitize text nodes.
     *
     * Iterates through each item in the content array and processes them
     * individually. Non-array items are passed through unchanged, while array items
     * are processed by the processItem method.
     *
     * @param  array<mixed>  $content  The content array to process
     * @return list<mixed> The processed content array with sanitized text nodes
     */
    private function processContent(
        array $content
    ): array {
        // Initialize array to store processed items
        $processed = [];

        // Iterate through each content item
        foreach ($content as $item) {
            // ---
            // Skip processing for non-array items (shouldn't occur in valid Bard content)
            // ---

            // Check if item is not an array
            if (! \is_array($item)) {
                // Add item to processed array
                $processed[] = $item;

                // Skip to next iteration
                continue;
            }

            // Process the item
            /** @var array<string, mixed> $item */
            $processedItem = $this->processItem($item);

            // Add processed item to result array
            $processed[] = $processedItem;
        }

        // Return processed content array
        return $processed;
    }

    /**
     * Process a single content item.
     *
     * Handles text nodes by escaping YAML separators and recursively processes
     * nested content arrays. This method is called for each item in the Bard
     * content structure.
     *
     * @param  array<string, mixed>  $item  The item to process
     * @return array<string, mixed> The processed item with sanitized text and nested content
     */
    private function processItem(
        array $item
    ): array {
        // ---
        // Handle text nodes
        // ---

        // Check if this is a text type with a text value
        if (
            isset($item['type'])
            && $item['type'] === 'text'
            && \array_key_exists(
                'text',
                $item
            )
        ) {
            // Get text value from item
            $text = $item['text'];

            // ---
            // Convert null text to empty string to prevent type issues
            // ---

            // Check if text is null
            if ($text === null) {
                // Set text to empty string
                $text = '';
            }

            // Coerce a non-string text value to string for consistent processing
            if (! \is_string($text)) {
                $text = \is_scalar($text) ? (string) $text : '';
            }

            // Escape YAML separators in the text and update the item
            $item['text'] = $this->escapeYamlSeparators($text);
        }

        // ---
        // Recursively process nested content arrays (e.g., paragraphs with multiple text
        // nodes)
        // ---

        // Check if item has nested content
        if (
            isset($item['content'])
            && \is_array($item['content'])
        ) {
            // Recursively process nested content
            $item['content'] = $this->processContent($item['content']);
        }

        // Return processed item
        return $item;
    }

    /**
     * Escape YAML document separators in text content.
     *
     * Checks if the text contains YAML separators and escapes them by wrapping
     * the entire text in single quotes. This prevents the YAML parser from
     * interpreting the separators as document structure markers.
     *
     * @param  ?string  $text  The text to escape
     * @return string The escaped text, or original text if no escaping needed
     */
    private function escapeYamlSeparators(
        ?string $text
    ): string {
        // Convert null to empty string for consistent processing
        $text ??= '';

        // Check if the text is already quoted to avoid double-quoting
        if (
            \preg_match(
                '/^[\'\"].*[\'\"]$/',
                $text
            )
        ) {
            // Return text unchanged
            return $text;
        }

        // Check if the text is exactly a YAML separator (most common case)
        if (
            \in_array(
                $text,
                self::YAML_SEPARATORS,
                true
            )
        ) {
            // Return quoted text
            return sprintf("'%s'", $text);
        }

        // ---
        // Check if the text starts or ends with a YAML separator
        // ---

        // Loop through YAML separators
        foreach (self::YAML_SEPARATORS as $separator) {
            // ---
            // Use \trim() to handle whitespace around the separator
            // ---

            // Check if text starts or ends with separator
            if (
                \str_starts_with(
                    \trim($text),
                    $separator
                )
                || \str_ends_with(
                    \trim($text),
                    $separator
                )
            ) {
                // Return quoted text
                return sprintf("'%s'", $text);
            }
        }

        // Return original text if no escaping is needed
        return $text;
    }

    /**
     * Check if content contains YAML separators that need sanitization.
     *
     * Provides a way to check if content needs sanitization without actually
     * modifying it. Useful for validation or preview purposes.
     *
     * @param  ?list<mixed>  $content  The content to check
     * @return bool True if content contains YAML separators that need sanitization
     */
    public function needsSanitization(
        ?array $content
    ): bool {
        // ---
        // Early return for null or empty content
        // ---

        // Check if content is empty
        if ($content === null || $content === []) {
            // Return false since empty content doesn't need sanitization
            return false;
        }

        // Recursively check for YAML separators
        return $this->containsYamlSeparatorsInArray($content);
    }

    /**
     * Recursively check if parsed content array contains YAML separators.
     *
     * Traverses the parsed content structure to find any text nodes that contain
     * YAML document separators. This is used for detection without modification.
     * Works with parsed PHP arrays (after YAML has been successfully parsed).
     *
     * @param  array<mixed>  $content  The parsed content array to check
     * @return bool True if content contains YAML separators
     */
    private function containsYamlSeparatorsInArray(
        array $content
    ): bool {
        // Iterate through each content item
        foreach ($content as $item) {
            // ---
            // Skip non-array items
            // ---

            // Check if item is not an array
            if (! \is_array($item)) {
                // Skip to next iteration
                continue;
            }

            // ---
            // Check text nodes for YAML separators
            // ---
            // Check if item is a text node
            // Check if the text is exactly a YAML separator
            if (
                isset($item['type']) && $item['type'] === 'text' && isset($item['text']) && \in_array(
                    $item['text'],
                    self::YAML_SEPARATORS
                )
            ) {
                // Return true since YAML separator found
                return true;
            }

            // ---
            // Recursively check nested content arrays
            // ---
            // Check if item has nested content
            // Check if nested content contains YAML separators
            if (
                isset($item['content']) && \is_array($item['content']) && $this->containsYamlSeparatorsInArray($item['content'])
            ) {
                // Return true since YAML separator found in nested content
                return true;
            }
        }

        // No YAML separators found
        return false;
    }

    /**
     * Check if raw YAML file content contains YAML separators in Bard content.
     *
     * Uses regex patterns to detect unquoted YAML document separators in text
     * fields within the YAML file. This method identifies files that need
     * sanitization without modifying them.
     *
     * This method works with raw YAML file strings (before parsing), unlike
     * `containsYamlSeparatorsInArray()` which works with parsed PHP arrays. This
     * is necessary because files with YAML parsing errors cannot be parsed to use
     * the array-based method.
     *
     * @param  string  $content  The raw file content to analyze
     * @return bool True if the file contains YAML separators that need sanitization
     */
    public function containsYamlSeparatorsInRawFile(
        string $content
    ): bool {
        // ---
        // Define regex patterns to match unquoted YAML separators in text fields. These
        // patterns look for "text: ..." or "text: ---" without quotes.
        // ---
        $patterns = [
            '/text:\s*\.\.\./',  // Matches "text: ..." with optional whitespace
            '/text:\s*---/',     // Matches "text: ---" with optional whitespace
        ];

        return array_any($patterns, fn (string $pattern): bool => \preg_match(
            $pattern,
            $content
        ) === 1);
    }

    /**
     * Check if raw YAML file content contains unquoted or single-quoted text
     * values with \_.
     *
     * Non-breaking spaces are stored in Bard text nodes as `\_` (escaped
     * underscore). In YAML, such values must be double-quoted so the backslash
     * is preserved. This method detects unquoted or single-quoted
     * "text: ...\_..." patterns that need sanitization.
     *
     * @param  string  $content  The raw file content to analyze
     * @return bool True if the file contains nbsp text that needs sanitization
     */
    public function containsUnquotedNbspTextInRawFile(
        string $content
    ): bool {
        // Unquoted: "text: " followed by value that contains \_
        if (
            \preg_match(
                '/text:\s+([^\'"][^\n]*\\\\_[^\n]*)/m',
                $content
            ) === 1
        ) {
            return true;
        }

        // Single-quoted: text: '...\_...' (value may contain '' for one quote)
        return \preg_match(
            '/text:\s+\'((?:[^\']|\'\')*\\\\_(?:[^\']|\'\')*)\'/m',
            $content
        ) === 1;
    }

    /**
     * Sanitize raw YAML file content by escaping YAML separators and nbsp text.
     *
     * Applies regex replacements to quote YAML document separators in text
     * fields and to double-quote text values containing `\_` (non-breaking
     * space). This prevents the YAML parser from misinterpreting separators
     * and ensures backslash-underscore is preserved.
     *
     * This method works with raw YAML file strings (before parsing), unlike
     * `sanitize()` which works with parsed PHP arrays. This is necessary
     * because files with YAML parsing errors cannot be parsed to use the
     * array-based method.
     *
     * @param  string  $content  The raw file content to sanitize
     * @return string The sanitized file content with quoted values
     */
    public function sanitizeRawFileContent(
        string $content
    ): string {
        // Replace "text: ..." with "text: '...'" to quote the separator
        /** @var string $content */
        $content = \preg_replace(
            '/text:\s*\.\.\./',
            "text: '...'",
            $content
        );

        // Replace "text: ---" with "text: '---'" to quote the separator
        /** @var string $content */
        $content = \preg_replace(
            '/text:\s*---/',
            "text: '---'",
            $content
        );

        // Convert single-quoted text values containing \_ to double-quoted
        // (e.g. text: '\_and ' → text: "\_and ") so output is consistent
        /** @var string $content */
        $content = \preg_replace_callback(
            '/text:\s+\'((?:[^\']|\'\')*\\\\_(?:[^\']|\'\')*)\'/m',
            function (array $matches): string {
                $value = \str_replace("''", "'", $matches[1]);
                $escaped = \str_replace('"', '\\"', $value);

                return 'text: "'.$escaped.'"';
            },
            $content
        );

        // Wrap unquoted text values containing \_ in double quotes; only escape
        // double quotes in the value so output is "\_and\_" not "\\_and\\_"
        /** @var string $content */
        $content = \preg_replace_callback(
            '/text:\s+([^\'"][^\n]*\\\\_[^\n]*)/m',
            function (array $matches): string {
                $value = \trim($matches[1]);
                $escaped = \str_replace('"', '\\"', $value);

                return 'text: "'.$escaped.'"';
            },
            $content
        );

        // Return sanitized content
        /** @var string $content */
        return $content;
    }
}
