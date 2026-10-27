<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\TriggerActivated;
use App\Imports\SpreadsheetImport;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Statamic\Assets\Asset as StatamicAsset;
use Statamic\Entries\Entry;
use Statamic\Facades\Asset;
use Throwable;

/**
 * Listener for the TriggerActivated event to import table data from an asset.
 *
 * This listener processes the `table_data_importer_trigger` field, reading data
 * from a selected asset (CSV, XLS, or XLSX) in the `table_data_importer` field
 * and updating the `table_data` field with the processed data. It supports both
 * new and existing entries.
 */
class HandleTableDataImporterTrigger
{
    /**
     * The only asset container this importer is permitted to read from.
     *
     * A caller-supplied id containing `::` carries its own container prefix and
     * would otherwise resolve outside this container; the lookup is pinned here
     * to prevent cross-container data exfiltration (UAMS-Web/uams-statamic-archive#863).
     */
    private const string INTENDED_CONTAINER = 'table_spreadsheets';

    /**
     * Cache of processed request keys to prevent duplicate event handling.
     *
     * @var array<string, bool>
     */
    protected static $processedRequests = [];

    /**
     * Handle the TriggerActivated event for the table_data_importer_trigger field.
     *
     * @param  TriggerActivated  $event  The triggered event containing entry, trigger handle, and form values
     * @return array<string, mixed> Updated field values, typically containing `table_data`
     */
    public function handle(
        TriggerActivated $event
    ): array {
        // Prevent duplicate processing by checking a request-specific key
        // The key only has to repeat for a repeated event; the serialized string itself
        // is as good a key as its hash.
        // @pest-mutate-ignore: UnwrapMd5
        $requestKey = \md5(\serialize($event));

        // Check if this request has already been processed
        if (isset(static::$processedRequests[$requestKey])) {
            // Log that we're skipping duplicate processing
            Log::notice(
                'HandleTableDataImporterTrigger::handle: Skipping duplicate TriggerActivated event processing',
                [
                    'trigger_handle' => $event->triggerHandle,
                ]
            );

            // Return empty array to indicate no changes
            return [];
        }

        // Mark this request as processed to prevent duplicate handling
        // Only isset() reads the map, and isset() is true for a stored false as well.
        // @pest-mutate-ignore: TrueToFalse
        static::$processedRequests[$requestKey] = true;

        // ---
        // Ensure the event is for the correct trigger handle
        // ---

        // Check if the trigger handle matches the expected value
        if ($event->triggerHandle !== 'table_data_importer_trigger') {
            // Log warning about mismatched trigger handle
            Log::warning('HandleTableDataImporterTrigger::handle: Trigger handle does not match, skipping listener');

            // Return empty array to indicate no changes
            return [];
        }

        // Extract the asset ID from form values
        $formValues = $event->formValues;

        // Get the asset ID from the table_data_importer field, or null if not set
        $assetId = $formValues['table_data_importer'] ?? null;

        // ---
        // Validate that an asset is selected
        // ---

        // Check if no asset ID was provided
        if (! $assetId) {
            // Log error about missing asset selection
            Log::error(
                'HandleTableDataImporterTrigger::handle: No asset selected in table_data_importer from form values',
                ['form_values' => $formValues]
            );

            // Return empty array to indicate no changes
            return [];
        }

        // ---
        // Handle array input (e.g., from Statamic's assets field)
        // ---

        // Check if asset ID is provided as an array
        if (\is_array($assetId)) {
            // Extract the first element from the array
            $assetId = $assetId[0] ?? null;
        }

        // ---
        // Validate that the asset ID is a string
        // ---

        // Check if asset ID is not a string
        if (! \is_string($assetId)) {
            // Log error about invalid asset ID format
            Log::error(
                'HandleTableDataImporterTrigger::handle: Invalid asset ID format in form values',
                ['asset_id' => $assetId]
            );

            // Return empty array to indicate no changes
            return [];
        }

        // ---
        // Constrain the lookup to the table_spreadsheets container (UAMS-Web/uams-statamic-archive#863)
        // ---

        // A caller-supplied id carries its own `container::` prefix, so an id
        // like `secret_container::file.csv` would resolve OUTSIDE the intended
        // container and exfiltrate parsed contents. Refuse any id that smuggles
        // a foreign container prefix, then always resolve within
        // `table_spreadsheets`.
        if (\str_contains($assetId, '::')) {
            // Split off the leading container handle (left of the first `::`)
            // Only the first part is read, and it is the same for a limit of 2 or 3.
            // @pest-mutate-ignore: IncrementInteger
            [$containerHandle] = \explode(
                '::',
                $assetId,
                2
            );

            // Reject ids that target any container other than table_spreadsheets
            if ($containerHandle !== self::INTENDED_CONTAINER) {
                // Log the rejected cross-container lookup attempt
                Log::warning(
                    'HandleTableDataImporterTrigger::handle: Refusing asset id outside the intended container',
                    [
                        'intended_container' => self::INTENDED_CONTAINER,
                        'requested_container' => $containerHandle,
                    ]
                );

                // Return empty array to indicate no changes
                return [];
            }
        } else {
            // Prepend the intended container handle to the bare asset path
            $assetId = self::INTENDED_CONTAINER.'::'.$assetId;
        }

        // Begin try block to handle any errors during asset resolution and
        // spreadsheet processing. Resolution lives inside the try so a malformed
        // id (e.g. a `table_spreadsheets::../traversal` path that Flysystem
        // rejects with PathTraversalDetected) becomes a logged no-op rather than
        // an uncaught 500.
        try {
            // ---
            // Retrieve and validate the asset
            // ---

            // Retrieve the asset from Statamic using the asset ID
            /** @var StatamicAsset|null $asset */
            $asset = Asset::find($assetId);

            // Check if asset doesn't exist or has an unsupported file extension
            // strtolower() returns a string, and a loose in_array() over non-numeric strings
            // matches exactly what a strict one does.
            // @pest-mutate-ignore: TrueToFalse
            if (
                $asset === null
                || ! \in_array(
                    \strtolower($asset->extension()),
                    [
                        'csv',
                        'xls',
                        'xlsx',
                    ],
                    true
                )
            ) {
                // Log error about asset not found or invalid format
                Log::error(
                    'HandleTableDataImporterTrigger::handle: Asset not found or not a supported format',
                    ['asset_id' => $assetId]
                );

                // Return empty array to indicate no changes
                return [];
            }

            // ---
            // Read the spreadsheet data
            // ---

            // Get the resolved file path from the asset
            $path = $asset->resolvedPath();

            // Read the spreadsheet data into an array using Excel facade
            /** @var list<array<int, mixed>> $rows */
            $rows = Excel::toArray(
                new SpreadsheetImport,
                $path
            )[0];

            // ---
            // Check if the spreadsheet is effectively empty
            // ---

            // Initialize flag to track if spreadsheet has any meaningful data
            $isEffectivelyEmpty = true;

            // Loop through each row to check for non-empty values
            foreach ($rows as $row) {
                // Filter row values to remove null and empty string values
                $rowValues = \array_filter(
                    $row,
                    fn ($value): bool => $value !== null && \trim(\is_scalar($value) ? (string) $value : '') !== ''
                );

                // Check if this row has any non-empty values
                // The loop only sets the flag; going on after it is set changes nothing but time.
                // @pest-mutate-ignore: BreakToContinue
                if ($rowValues !== []) {
                    // Mark spreadsheet as not empty since we found data
                    $isEffectivelyEmpty = false;

                    // Exit loop early since we've confirmed data exists
                    break;
                }
            }

            // Check if spreadsheet is effectively empty
            if ($isEffectivelyEmpty) {
                // Return empty array to indicate no changes
                return [];
            }

            // ---
            // Normalize rows to ensure consistent column count
            // ---

            // Get the column count from the first row to use as the standard
            $columnCount = \count($rows[0]);

            // Normalize all rows to have the same column count as the first row
            /** @var list<array<int, string>> $normalizedRows */
            $normalizedRows = collect($rows)->map(
                function ($row) use ($columnCount) {
                    // Convert all row values to strings for consistency
                    /** @var array<int, mixed> $row */
                    $row = \array_map(
                        fn ($value): string => \strval(\is_scalar($value) || $value === null ? $value : ''),
                        $row
                    );

                    // Get the current length of this row
                    $rowLength = \count($row);

                    // Check if this row has fewer columns than the standard
                    // At equal lengths array_fill() is asked for zero cells, so padding a full row
                    // changes nothing.
                    // @pest-mutate-ignore: SmallerToSmallerOrEqual
                    if ($rowLength < $columnCount) {
                        // Pad the row with empty strings to match the column count
                        return \array_merge(
                            $row,
                            \array_fill(
                                $rowLength,
                                $columnCount - $rowLength,
                                ''
                            )
                        );
                    }

                    // Return the normalized row
                    return $row;
                }
            )->toArray();

            // Format data for the table fieldtype
            $tableData = collect($normalizedRows)->map(
                fn ($row): array => ['cells' => \array_values(
                    \array_map(
                        trim(...),
                        $row
                    )
                )]
            )->filter()->toArray();

            // ---
            // Validate that valid data was extracted
            // ---

            // Check if no valid table data was extracted
            if (empty($tableData)) {
                // Return empty array to indicate no changes
                return [];
            }

            // ---
            // Update the entry if it exists (for existing entries)
            // ---

            // Check if an entry exists in the event (for existing entries)
            if ($event->entry !== null) {
                // Get the entry from the event
                /** @var Entry $entry */
                $entry = $event->entry;

                // Set the table_data field on the entry with the processed data
                $entry->set(
                    'table_data',
                    $tableData
                );

                // Save the entry quietly without triggering additional events
                $entry->saveQuietly();
            }

            // Return updated field values for the frontend
            return [
                'table_data' => $tableData,
            ];
        }

        // Catch any error during asset resolution or spreadsheet processing
        // (e.g. Flysystem's PathTraversalDetected on a `../` smuggled path) so a
        // malformed id is a logged no-op rather than an uncaught 500
        catch (Throwable $throwable) {
            // Log any errors during processing
            Log::error('HandleTableDataImporterTrigger::handle: Error replacing table data: '.$throwable->getMessage());

            // Return empty array to indicate no changes due to error
            return [];
        }
    }
}
