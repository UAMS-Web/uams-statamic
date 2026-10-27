<?php

declare(strict_types=1);

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;

/**
 * Import class for reading spreadsheet data into an array.
 *
 * This class implements the `ToArray` concern from `maatwebsite/excel` to
 * convert a spreadsheet file into a PHP array. It is used by the
 * `TableDataImporter` fieldtype to process uploaded spreadsheets.
 */
class SpreadsheetImport implements ToArray
{
    /**
     * Converts the spreadsheet data into an array.
     *
     * This method is required by the `ToArray` concern. It receives the
     * spreadsheet data as an array and returns it unchanged. The `TableDataImporter`
     * fieldtype will further process this array to populate the `table_data` field.
     *
     * @param  list<list<scalar|null>>  $array  The spreadsheet data as a multidimensional array
     * @return list<list<scalar|null>> The same array, returned for further processing
     */
    public function array(
        array $array
    ): array {
        // Return the array as-is for processing by TableDataImporter
        return $array;
    }
}
