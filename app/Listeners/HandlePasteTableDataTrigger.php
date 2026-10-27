<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\TriggerActivated;
use App\Services\Tables\PastedTableParser;

/**
 * Handles the `table_data_paste_trigger` trigger by parsing pasted table data.
 *
 * The companion of {@see HandleTableDataImporterTrigger} (which imports a
 * spreadsheet file): this reads the `table_data_paste` textarea from the form
 * values, parses it through {@see PastedTableParser} (HTML table, TSV, or CSV),
 * and returns the parsed value for the `table_data` field.
 *
 * The activate route always dispatches with a null entry and the control panel
 * applies the returned value to the open publish form client-side, so there is
 * no server-side entry save here.
 *
 * Triggers it does not own are ignored (an empty result), so it composes with
 * the other `TriggerActivated` listeners.
 */
class HandlePasteTableDataTrigger
{
    /**
     * @param  PastedTableParser  $parser  Parses the pasted text into table rows
     */
    public function __construct(
        private readonly PastedTableParser $parser
    ) {}

    /**
     * Handle the TriggerActivated event for the paste trigger.
     *
     * @param  TriggerActivated  $event  The trigger event carrying the form values
     * @return array<string, mixed> Updated field values (`table_data`), or empty for no change
     */
    public function handle(
        TriggerActivated $event
    ): array {
        // Ignore triggers this listener does not own
        if ($event->triggerHandle !== 'table_data_paste_trigger') {
            return [];
        }

        // Read the pasted text from the form values
        $pasted = $event->formValues['table_data_paste'] ?? null;

        // The parser requires a string; a non-string paste is a no-op
        if (! is_string($pasted)) {
            return [];
        }

        // Parse the paste into the table fieldtype shape (the parser trims and
        // short-circuits empty/whitespace input to no rows)
        $tableData = $this->parser->parse($pasted);

        // No usable rows parsed — make no change
        if ($tableData === []) {
            return [];
        }

        // Return the parsed value for the control panel to apply
        return [
            'table_data' => $tableData,
        ];
    }
}
