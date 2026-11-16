<?php

declare(strict_types=1);

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;

/**
 * Import class for reading spreadsheet data into an array.
 *
 * Callers pass it to `Excel::toArray()`, which returns each sheet's rows
 * itself; the class only tells `maatwebsite/excel` to read the file as plain
 * arrays. It is used by the `TableDataImporter` fieldtype and the ISCO-08
 * enum generator.
 */
class SpreadsheetImport implements ToArray
{
    /**
     * Receives the rows when the class is run through `Excel::import()`.
     *
     * Required by the `ToArray` concern, whose 4.x signature returns nothing.
     * `Excel::toArray()` does not call it, so the rows need no handling here.
     *
     * @param  array<array-key, mixed>  $array  The spreadsheet rows
     */
    public function array(array $array): void {}
}
