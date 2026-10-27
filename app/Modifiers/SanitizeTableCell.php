<?php

declare(strict_types=1);

namespace App\Modifiers;

use App\Services\Tables\TableCellSanitizer;
use Statamic\Modifiers\Modifier;

/**
 * Antlers modifier: sanitize a table-cell value to a safe inline HTML allowlist.
 *
 * Table cells store raw HTML (imported WordPress tables carry inline links and
 * emphasis). This modifier renders that value through {@see TableCellSanitizer},
 * which permits inline links and basic emphasis and escapes everything else —
 * enabling cell links while closing the stored-XSS hole left by emitting the raw
 * `{{ value }}`.
 *
 * Usage in the table render partial:
 *
 * - `{{ value | sanitize_table_cell }}`
 *
 * @see TableCellSanitizer
 */
class SanitizeTableCell extends Modifier
{
    /**
     * Sanitize the cell value to safe inline HTML.
     *
     * @param  mixed  $value  The raw cell value
     * @param  array<int|string, mixed>  $params  Unused modifier parameters
     * @param  array<int|string, mixed>  $context  Unused Statamic modifier context
     * @return string The sanitized HTML, safe to render unescaped
     */
    public function index(
        mixed $value,
        array $params,
        array $context
    ): string {
        // Resolve the shared sanitizer so its compiled definition is reused
        return app(TableCellSanitizer::class)->sanitize($value);
    }
}
