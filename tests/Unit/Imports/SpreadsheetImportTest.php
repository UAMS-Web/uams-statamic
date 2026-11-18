<?php

declare(strict_types=1);

use App\Imports\SpreadsheetImport;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

/**
 * Pins `App\Imports\SpreadsheetImport`, the class `Isco08EnumGenerator` passes to
 * `Excel::toArray()` so that `maatwebsite/excel` reads a workbook as plain arrays.
 *
 * The class has no mutable code: the mutation plugin reports `0 Mutations for 0 Files
 * created` for it, so these tests pin what it is and what reading a file through it
 * returns, rather than killing mutants (#864).
 *
 * @command  php artisan test --compact tests/Unit/Imports/SpreadsheetImportTest.php
 */
uses(TestCase::class);

it('is a ToArray import, so maatwebsite/excel reads each sheet as plain arrays', function (): void {
    expect(class_implements(SpreadsheetImport::class))->toHaveKey(ToArray::class);
});

it('accepts the rows handed to array() and returns nothing', function (): void {
    $rows = [['code', 'title'], ['0110', 'Commissioned armed forces officers']];

    $method = new ReflectionMethod(SpreadsheetImport::class, 'array');

    expect((string) $method->getReturnType())->toBe('void');

    (new SpreadsheetImport)->array($rows);

    expect($rows)->toBe([['code', 'title'], ['0110', 'Commissioned armed forces officers']]);
});

it('reads every sheet of a file into rows of cell values through Excel::toArray()', function (): void {
    // The fixture's third line is an empty row (`,`). Reading through this import
    // leaves it out, and keeps the leading zero of `0110` as a string.
    $path = base_path('tests/Fixtures/Imports/spreadsheet-import.csv');

    expect(Excel::toArray(new SpreadsheetImport, $path))->toBe([
        [
            ['code', 'title'],
            ['0110', 'Commissioned armed forces officers'],
        ],
    ]);
});
