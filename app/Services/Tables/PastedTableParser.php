<?php

declare(strict_types=1);

namespace App\Services\Tables;

use App\Listeners\HandleTableDataImporterTrigger;
use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Parses pasted table data into the `table` fieldtype's value shape.
 *
 * Mirrors the spreadsheet-file importer ({@see HandleTableDataImporterTrigger})
 * but takes editor-pasted text instead of an uploaded file, so editors can paste
 * a copied table from Excel/Sheets, a web page, raw HTML, or CSV and replace the
 * stored `table_data`.
 *
 * Three input shapes are recognized:
 * - an HTML `<table>` (copied from a web page or pasted markup) — cell inner HTML
 *   is preserved and sanitized through {@see TableCellSanitizer} so links survive
 *   while scripts/handlers are stripped;
 * - tab-separated values (the clipboard format of Excel/Sheets); and
 * - comma-separated values.
 *
 * Only the HTML branch sanitizes, because only it extracts markup. Delimited
 * cells are stored as the plain text they are (like manually-typed cells and the
 * spreadsheet-file importer), and — like every cell source — are neutralized at
 * the single render-time chokepoint, the `sanitize_table_cell` modifier in
 * `resources/views/sets/_table.antlers.html`. Sanitizing delimited cells here
 * would wrongly entity-encode plain text in storage (e.g. `a & b`), which the
 * control-panel table editor would then show with literal entities.
 *
 * The result is the `table` fieldtype shape: a list of `['cells' => [...]]` rows,
 * normalized so every row has the same column count.
 */
class PastedTableParser
{
    /**
     * @param  TableCellSanitizer  $sanitizer  Sanitizes HTML-table cell markup
     */
    public function __construct(
        private readonly TableCellSanitizer $sanitizer
    ) {}

    /**
     * Parse pasted content into table-fieldtype rows.
     *
     * @param  string  $pasted  The raw pasted text
     * @return list<array{cells: list<string>}> The normalized table rows
     */
    public function parse(
        string $pasted
    ): array {
        // Trim surrounding whitespace; nothing to parse when empty
        $pasted = \trim($pasted);

        // Bail out early on empty input
        if ($pasted === '') {
            return [];
        }

        // Treat the paste as HTML only when it actually begins with markup and
        // contains a table element. A delimited paste whose cell text merely
        // mentions `<table>` (a glossary, a code snippet) does not start with
        // `<`, so it stays on the delimited path and is not lost.
        $isHtmlTable = \str_starts_with($pasted, '<')
            && \preg_match('/<table[\s>]/i', $pasted) === 1;

        $rows = $isHtmlTable
            ? $this->parseHtml($pasted)
            : $this->parseDelimited($pasted);

        // Normalize ragged rows to a uniform column count
        return $this->normalize($rows);
    }

    /**
     * Parse an HTML `<table>` into rows of sanitized cell markup.
     *
     * @param  string  $html  The pasted HTML
     * @return list<list<string>> Rows of sanitized cell HTML
     */
    private function parseHtml(
        string $html
    ): array {
        // Parse the markup without emitting libxml warnings for partial HTML
        $dom = new DOMDocument;
        $previous = \libxml_use_internal_errors(true);

        // Force UTF-8 interpretation of the fragment
        $dom->loadHTML(
            '<?xml encoding="UTF-8">'.$html,
            LIBXML_NOERROR | LIBXML_NOWARNING
        );

        // Restore the previous libxml error state
        \libxml_clear_errors();
        \libxml_use_internal_errors($previous);

        // Use the first table in the paste
        $table = $dom->getElementsByTagName('table')->item(0);

        // No table element resolved despite the marker — nothing to parse
        if (! $table instanceof DOMElement) {
            return [];
        }

        // Build a row for each top-level <tr>, preserving document order
        $rows = [];
        foreach ($this->tableRows($table) as $tr) {
            $cells = [];

            // Collect the direct th/td children of the row
            foreach ($tr->childNodes as $cell) {
                if (
                    $cell instanceof DOMElement
                    && \in_array(
                        \strtolower($cell->nodeName),
                        ['th', 'td'],
                        true
                    )
                ) {
                    // Preserve and sanitize the cell's inner markup
                    $cells[] = $this->sanitizer->sanitize($this->innerHtml($cell));
                }
            }

            // Keep only rows that actually contained cells
            if ($cells !== []) {
                $rows[] = $cells;
            }
        }

        return $rows;
    }

    /**
     * Collect the top-level rows of a table, skipping any nested-table rows.
     *
     * Only `<tr>` elements that sit directly under the table or one of its
     * section elements are returned, so rows belonging to a table nested inside
     * a cell are not flattened into the parent.
     *
     * @param  DOMElement  $table  The table element
     * @return list<DOMElement> The table's own rows in document order
     */
    private function tableRows(
        DOMElement $table
    ): array {
        $rows = [];

        // Walk the table's direct children
        foreach ($table->childNodes as $child) {
            // Skip text nodes and other non-elements
            if (! $child instanceof DOMElement) {
                continue;
            }

            // Resolve the lower-cased tag name once
            $name = \strtolower($child->nodeName);

            // Rows inside a section element (thead/tbody/tfoot)
            if (\in_array($name, ['thead', 'tbody', 'tfoot'], true)) {
                foreach ($child->childNodes as $tr) {
                    if (
                        $tr instanceof DOMElement
                        && \strtolower($tr->nodeName) === 'tr'
                    ) {
                        $rows[] = $tr;
                    }
                }
            }

            // Rows placed directly under the table element
            elseif ($name === 'tr') {
                $rows[] = $child;
            }
        }

        return $rows;
    }

    /**
     * Serialize the inner HTML of a node.
     *
     * @param  DOMNode  $node  The node whose children to serialize
     * @return string The concatenated inner HTML, trimmed
     */
    private function innerHtml(
        DOMNode $node
    ): string {
        $html = '';

        // Concatenate the serialized form of each child node
        foreach ($node->childNodes as $child) {
            $serialized = $node->ownerDocument?->saveHTML($child);

            // Append only when serialization succeeded
            if (\is_string($serialized)) {
                $html .= $serialized;
            }
        }

        // Trim incidental whitespace around the cell markup
        return \trim($html);
    }

    /**
     * Parse delimited (TSV or CSV) text into rows of plain-text cells.
     *
     * The delimiter is inferred from the content: a tab anywhere in the paste
     * selects TSV (the Excel/Sheets clipboard format), otherwise CSV. Parsing
     * goes through a stream so quoted multi-line cells are handled correctly.
     *
     * @param  string  $text  The pasted delimited text
     * @return list<list<string>> Rows of trimmed plain-text cells
     */
    private function parseDelimited(
        string $text
    ): array {
        // Tabs anywhere imply the Excel/Sheets TSV clipboard format
        $delimiter = \str_contains($text, "\t") ? "\t" : ',';

        // Stream the text so fgetcsv can honour quoted multi-line cells
        $stream = \fopen('php://temp', 'r+');

        // Guard against an unavailable temp stream
        if ($stream === false) {
            return [];
        }

        // Load the paste into the stream and rewind for reading
        \fwrite($stream, $text);
        \rewind($stream);

        $rows = [];

        // Read each record; pass an empty escape for RFC-4180 behavior
        while (($record = \fgetcsv($stream, null, $delimiter, '"', '')) !== false) {
            // Trim every cell, treating a missing value as empty
            $cells = \array_map(
                static fn ($cell): string => \trim($cell ?? ''),
                $record
            );

            // Skip rows that hold no content (e.g. blank lines)
            if (\array_filter($cells, static fn (string $cell): bool => $cell !== '') === []) {
                continue;
            }

            $rows[] = $cells;
        }

        // Release the temp stream
        \fclose($stream);

        return $rows;
    }

    /**
     * Normalize rows to a uniform column count.
     *
     * Pads short rows with empty cells so every row matches the widest row,
     * matching the spreadsheet importer's behavior.
     *
     * @param  list<list<string>>  $rows  The parsed rows
     * @return list<array{cells: list<string>}> The normalized table rows
     */
    private function normalize(
        array $rows
    ): array {
        // Nothing to normalize for an empty table
        if ($rows === []) {
            return [];
        }

        // Determine the widest row's column count
        $columns = \max(
            \array_map(
                \count(...),
                $rows
            )
        );

        // Pad each row to the column count and wrap in the fieldtype shape
        return \array_map(
            // Append empty cells until the row is full width
            static fn (array $row): array => ['cells' => \array_pad(
                $row,
                $columns,
                ''
            )],
            $rows
        );
    }
}
