<?php

declare(strict_types=1);

use App\Enums\Isco08;
use App\Imports\SpreadsheetImport;
use App\Services\EnumGeneration\Isco08\Isco08EnumGenerator;
use Illuminate\Support\Facades\File;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class);

it('writes a PHP string literal that evaluates to the value it was given', function (string $value): void {
    $literal = mustBeString((new ReflectionMethod(Isco08EnumGenerator::class, 'phpString'))->invoke(new Isco08EnumGenerator, $value), 'phpString literal');

    expect(eval('return '.$literal.';'))->toBe($value);
})->with([
    'plain' => ['Armed Forces Occupations'],
    'forward slash' => ['and/or'],
    'single quote' => ["Prince George's"],
    'double quote' => ['a "quoted" word'],
    'dollar sign' => ['costs $5 or $total'],
    'backslash' => ['C:\\path\\to'],
    'trailing backslash' => ['ends with \\'],
    'newline and tab' => ["line one\n\tline two\r\n"],
    'quote and newline' => ["it's\nhere"],
    'control character' => ["bell\x07 and \x1B escape"],
    'non-ASCII' => ['Café – 1–4 digit'],
    'empty' => [''],
]);

it('single-quotes a value Pint would single-quote, and double-quotes one it would not', function (string $value, string $expected): void {
    $literal = mustBeString((new ReflectionMethod(Isco08EnumGenerator::class, 'phpString'))->invoke(new Isco08EnumGenerator, $value), 'phpString literal');

    expect($literal)->toBe($expected);
})->with([
    'plain' => ['and/or', "'and/or'"],
    'double quote stays single-quoted' => ['say "hi"', "'say \"hi\"'"],
    'single quote needs double quotes' => ["it's", '"it\'s"'],
    'newline needs double quotes' => ["a\nb", '"a\\nb"'],
]);

/**
 * Calls one of the generator's private helpers on a fresh instance.
 */
function isco08Call(string $method, mixed ...$arguments): mixed
{
    return (new ReflectionMethod(Isco08EnumGenerator::class, $method))->invoke(new Isco08EnumGenerator, ...$arguments);
}

/**
 * Calls one of the generator's private helpers that edits its array argument in place,
 * and returns the edited array.
 *
 * @param  array<array-key, mixed>  $value
 * @return array<array-key, mixed>
 */
function isco08CallInPlace(string $method, array $value): array
{
    (new ReflectionMethod(Isco08EnumGenerator::class, $method))->invokeArgs(new Isco08EnumGenerator, [&$value]);

    return $value;
}

/**
 * Answers `Excel::toArray()` with the sheets of the first entry whose key is part of
 * the workbook path, so a test sets each workbook's rows without a file on disk.
 *
 * @param  array<string, array<array-key, mixed>>  $sheetsByPathPart
 */
function isco08FakeWorkbooks(array $sheetsByPathPart): void
{
    Excel::shouldReceive('toArray')->andReturnUsing(static function (object $import, string $path) use ($sheetsByPathPart): array {
        expect($import)->toBeInstanceOf(SpreadsheetImport::class);

        foreach ($sheetsByPathPart as $part => $sheets) {
            if (str_contains($path, $part)) {
                return $sheets;
            }
        }

        throw new RuntimeException('No fake workbook for '.$path);
    });
}

/**
 * One parsed definitions row, as `parseDefinitionsWorkbook()` returns it.
 *
 * @return array{title_en: string, definition: string, tasks_include: string, excluded_occupations: string, notes: string}
 */
function isco08Definition(string $title, string $definition = '', string $tasks = '', string $excluded = '', string $notes = ''): array
{
    return [
        'title_en' => $title,
        'definition' => $definition,
        'tasks_include' => $tasks,
        'excluded_occupations' => $excluded,
        'notes' => $notes,
    ];
}

/**
 * The definitions sheet's header row, in the column order the ILO workbook uses.
 *
 * @return list<string>
 */
function isco08DefinitionsHeader(): array
{
    return ['ISCO 08 Code', 'Title EN', 'Definition', 'Tasks include', 'Excluded occupations', 'Notes'];
}

/**
 * Points the application path at a new scratch directory for the callback, then
 * restores it and deletes the directory.
 */
function isco08WithScratchAppPath(Closure $callback): mixed
{
    $originalAppPath = app_path();
    $scratch = sys_get_temp_dir().DIRECTORY_SEPARATOR.'isco08-app-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists($scratch);

    try {
        app()->useAppPath($scratch);

        return $callback($scratch);
    } finally {
        app()->useAppPath($originalAppPath);
        File::deleteDirectory($scratch);
    }
}

it('names the two workbooks it ships beside itself', function (): void {
    isco08WithScratchAppPath(function (string $scratch): void {
        $dir = $scratch.DIRECTORY_SEPARATOR.'Services/EnumGeneration/Isco08';

        expect(Isco08EnumGenerator::defaultLocalFixturePaths())->toBe([
            'definitions' => $dir.DIRECTORY_SEPARATOR.'ISCO-08 EN Structure and definitions.xlsx',
            'index' => $dir.DIRECTORY_SEPARATOR.'ISCO-08 -88 EN Index.xlsx',
        ]);
    });
});

it('reports the shipped workbooks readable only when both are there', function (): void {
    isco08WithScratchAppPath(function (): void {
        $paths = Isco08EnumGenerator::defaultLocalFixturePaths();
        File::ensureDirectoryExists(dirname($paths['definitions']));

        expect(Isco08EnumGenerator::localFixturesAreReadable())->toBeFalse();

        file_put_contents($paths['definitions'], '');
        expect(Isco08EnumGenerator::localFixturesAreReadable())->toBeFalse();

        unlink($paths['definitions']);
        file_put_contents($paths['index'], '');
        expect(Isco08EnumGenerator::localFixturesAreReadable())->toBeFalse();

        file_put_contents($paths['definitions'], '');
        expect(Isco08EnumGenerator::localFixturesAreReadable())->toBeTrue();
    });
});

it('generates every structure level from small workbooks, filling titles from the index', function (): void {
    isco08FakeWorkbooks([
        'defs.xlsx' => [[
            isco08DefinitionsHeader(),
            ['1111', 'Legislators', 'Def 1111', 'Tasks 1111', 'Excl 1111', 'Note 1111'],
            ['2', 'Professionals', 'Def 2', '', '', ''],
        ]],
        'index.xlsx' => [[
            ['ISCO-08', 'English title'],
            ['1111', 'Senator'],
            ['1111', 'Mayor'],
            ['21', 'Engineer, civil'],
        ]],
    ]);

    $php = mustBeString(isco08WithScratchAppPath(function (string $scratch): string {
        File::ensureDirectoryExists($scratch.'/Enums');

        $path = (new Isco08EnumGenerator)->generate('defs.xlsx', 'index.xlsx');

        expect($path)->toBe($scratch.DIRECTORY_SEPARATOR.'Enums/Isco08.php');

        return (string) file_get_contents($path);
    }), 'generated enum');

    preg_match_all("/^    case (ISCO08_\\d+) = '(\\d+)';$/m", $php, $cases);

    expect($cases[2])->toBe(['1', '2', '11', '21', '111', '1111'])
        ->and($php)->toContain(' * Generated: '.date('Y-m-d')."\n")
        ->and($php)->toContain(" * Total cases: 6\n")
        ->and($php)->toContain(<<<'CASE'
                #[Isco08Info(
                    level: 2,
                    code: '21',
                    major_group: '2',
                    sub_major_group: '21',
                    minor_group: null,
                    unit_group: null,
                    title: 'Engineer, civil',
                    definition: '',
                    tasks_include: '',
                    excluded_occupations: null,
                    notes: null,
                    occupational_titles: ['Engineer, civil'],
                    parent: '2',
                    ancestors: ['2'],
                    children: [],
                    descendants: [],
                )]
                case ISCO08_21 = '21';
            CASE)
        ->and($php)->toContain(<<<'CASE'
                #[Isco08Info(
                    level: 4,
                    code: '1111',
                    major_group: '1',
                    sub_major_group: '11',
                    minor_group: '111',
                    unit_group: '1111',
                    title: 'Legislators',
                    definition: 'Def 1111',
                    tasks_include: 'Tasks 1111',
                    excluded_occupations: 'Excl 1111',
                    notes: 'Note 1111',
                    occupational_titles: ['Mayor', 'Senator'],
                    parent: '111',
                    ancestors: ['1', '11', '111'],
                    children: [],
                    descendants: [],
                )]
                case ISCO08_1111 = '1111';
            CASE)
        ->and($php)->toContain(<<<'CASE'
                #[Isco08Info(
                    level: 1,
                    code: '1',
                    major_group: '1',
                    sub_major_group: null,
                    minor_group: null,
                    unit_group: null,
                    title: '',
                    definition: '',
                    tasks_include: '',
                    excluded_occupations: null,
                    notes: null,
                    occupational_titles: [],
                    parent: null,
                    ancestors: [],
                    children: ['11'],
                    descendants: ['11', '111', '1111'],
                )]
                case ISCO08_1 = '1';
            CASE);
});

it('fails by path when the enum file cannot be written', function (): void {
    isco08FakeWorkbooks([
        'defs.xlsx' => [[isco08DefinitionsHeader(), ['1', 'Managers']]],
        'index.xlsx' => [[['ISCO-08', 'English title'], ['1', 'Manager']]],
    ]);

    isco08WithScratchAppPath(function (string $scratch): void {
        // No Enums directory, so the write fails; let file_put_contents() warn quietly.
        set_error_handler(static fn (): bool => true, E_WARNING);

        try {
            expect(fn (): string => (new Isco08EnumGenerator)->generate('defs.xlsx', 'index.xlsx'))
                ->toThrow(ValueError::class, 'Failed to write enum file: '.$scratch.DIRECTORY_SEPARATOR.'Enums/Isco08.php');
        } finally {
            restore_error_handler();
        }
    });
});

it('reads each definitions row below its header, skipping rows with no usable code', function (): void {
    isco08FakeWorkbooks(['defs.xlsx' => [
        [['Cover sheet'], ['Nothing to read here']],
        [
            ['International Standard Classification of Occupations'],
            ['9', 'A numbered note above the header', 'x', 'x', 'x', 'x'],
            isco08DefinitionsHeader(),
            ['1', ' Managers ', " Plan.\r\nDirect.\rReview. ", " (a) one\r\n(b) two ", ' Excl ', ' Note '],
            [11.0, 'Chief executives', 'D11', '', '', ''],
            ['111.00', 'Legislators'],
            ['', 'Blank code', 'x', 'x', 'x', 'x'],
            [null, 'Null code', 'x', 'x', 'x', 'x'],
            ['X1', 'Letters', 'x', 'x', 'x', 'x'],
            ['12345', 'Five digits', 'x', 'x', 'x', 'x'],
            ['2', ['not scalar'], ['nope'], null, 3, true],
            ['3'],
        ],
    ]]);

    expect(isco08Call('parseDefinitionsWorkbook', 'defs.xlsx'))->toBe([
        '1' => isco08Definition('Managers', "Plan.\nDirect.\nReview.", "(a) one\n(b) two", 'Excl', 'Note'),
        '11' => isco08Definition('Chief executives', 'D11'),
        '111' => isco08Definition('Legislators'),
        '2' => isco08Definition('', '', '', '3', '1'),
        '3' => isco08Definition(''),
    ]);
});

it('reads the definitions code from whichever code column the sheet names', function (array $header, array $row, string $code): void {
    isco08FakeWorkbooks(['defs.xlsx' => [[$header, $row]]]);

    expect(array_map(strval(...), array_keys(mustBeArray(isco08Call('parseDefinitionsWorkbook', 'defs.xlsx'), 'definitions'))))->toBe([$code]);
})->with([
    'ISCO 08 Code' => [['ISCO 08 Code', 'Title EN'], ['1', 'T'], '1'],
    'ISCO-08 Code' => [['ISCO-08 Code', 'Title EN'], ['2', 'T'], '2'],
    'ISCO08 Code' => [['ISCO08 Code', 'Title EN'], ['3', 'T'], '3'],
    'ISCO code, found beside a heading that ends in ISCO-08 code' => [['Unit ISCO-08 code', 'ISCO code', 'Title EN'], ['x', '6', 'T'], '6'],
    'ISCO code, found beside another ISCO-08 heading' => [['ISCO-08 label', 'ISCO code', 'Title EN'], ['x', '4', 'T'], '4'],
    'the first of two code columns in the order it looks for them' => [['ISCO code', 'ISCO 08 Code', 'Title EN'], ['9', '5', 'T'], '5'],
]);

it('takes the definitions title from Title EN, then Title, else leaves it empty', function (array $header, array $row, array $expected): void {
    isco08FakeWorkbooks(['defs.xlsx' => [[$header, $row]]]);

    expect(isco08Call('parseDefinitionsWorkbook', 'defs.xlsx'))->toBe(['1' => $expected]);
})->with([
    'Title EN before Title' => [['ISCO 08 Code', 'Title', 'Title EN'], ['1', 'Plain', 'English'], isco08Definition('English')],
    'Title alone' => [['ISCO-08 Code', 'Title'], ['1', 'Plain'], isco08Definition('Plain')],
    'no title column' => [['ISCO 08 Code', 'Definition'], ['1', 'D'], isco08Definition('', 'D')],
]);

it('rejects a definitions workbook it cannot read', function (array $sheet, string $message): void {
    isco08FakeWorkbooks(['defs.xlsx' => [$sheet]]);

    expect(fn (): mixed => isco08Call('parseDefinitionsWorkbook', 'defs.xlsx'))->toThrow(ValueError::class, $message);
})->with([
    'no header row' => [[['Code', 'Name'], ['1', 'Managers']], 'Could not locate definitions sheet in defs.xlsx'],
    'a header with no code column it knows' => [[['ISCO-08', 'Title EN'], ['1', 'Managers']], 'Definitions workbook missing ISCO code column: defs.xlsx'],
]);

it('reads each index title below the header once per code, sorted naturally, skipping unusable rows', function (): void {
    isco08FakeWorkbooks(['index.xlsx' => [[
        ['ISCO-08 index'],
        ['9', 'A numbered note above the header'],
        ['ISCO-08', 'English title', 'ISCO-88'],
        ['1111', 'senator'],
        ['1111', ' Member of parliament '],
        ['1111', 'senator'],
        [1111, 'a10'],
        ['1111.0', 'a9'],
        ['1111', 'x'],
        ['1111', 'é'],
        ['1111', 'ok'],
        ['', 'blank code'],
        [null, 'null code'],
        ['X', 'letters'],
        ['21', '   '],
        ['22'],
        ['3', 'Third'],
    ]]]);

    expect(isco08Call('parseIndexWorkbook', 'index.xlsx'))->toBe([
        '1111' => ['a9', 'a10', 'Member of parliament', 'ok', 'senator'],
        '3' => ['Third'],
    ]);
});

it('finds the index code column under each spelling it knows', function (string $heading): void {
    isco08FakeWorkbooks(['index.xlsx' => [[[$heading, 'English title'], ['5', 'Title five']]]]);

    expect(isco08Call('parseIndexWorkbook', 'index.xlsx'))->toBe(['5' => ['Title five']]);
})->with(['ISCO-08', 'ISCO 08', 'ISCO08']);

it('rejects an index workbook it cannot read', function (array $sheet, string $message): void {
    isco08FakeWorkbooks(['index.xlsx' => [$sheet]]);

    expect(fn (): mixed => isco08Call('parseIndexWorkbook', 'index.xlsx'))->toThrow(ValueError::class, $message);
})->with([
    'no header row' => [[['Code', 'Name']], 'Could not locate index sheet in index.xlsx'],
    'no exact code column' => [[['ISCO-08 code', 'English title']], 'Index workbook missing ISCO-08 or English title column: index.xlsx'],
    'no exact title column' => [[['ISCO-08', 'English titles']], 'Index workbook missing ISCO-08 or English title column: index.xlsx'],
]);

it('finds the header row by its code heading and one more expected heading', function (array $rows, ?int $expected): void {
    expect(isco08Call('findHeaderRowIndex', $rows, ['isco-08'], ['title en']))->toBe($expected);
})->with([
    'exact heading' => [[['ISCO-08', 'Title EN']], 0],
    'padded and upper case' => [[['  ISCO-08 ', ' TITLE EN ']], 0],
    'heading that starts with it' => [[['ISCO-08 code', 'Title EN']], 0],
    'last heading that ends with it' => [[['Title EN', 'Old ISCO-08']], 0],
    'expected heading inside a longer one' => [[['ISCO-08', 'English title en text']], 0],
    'a code row without the other heading is passed over' => [[['ISCO-08', 'Name'], ['ISCO-08', 'Title EN']], 1],
    'the other heading without the code' => [[['Code', 'Title EN']], null],
    'the code only inside a longer word' => [[['preISCO-08post', 'Title EN']], null],
    'fewer rows than it would search, none a header' => [[['a'], ['b']], null],
    'header on the last row it searches' => [[...array_fill(0, 59, ['filler']), ['ISCO-08', 'Title EN']], 59],
    'header past the rows it searches' => [[...array_fill(0, 60, ['filler']), ['ISCO-08', 'Title EN']], null],
]);

it('reads the first sheet that has a header row', function (): void {
    $cover = [['Cover']];
    $first = [['ISCO-08', 'Title EN'], ['1', 'A']];
    $second = [['Notes'], ['ISCO-08', 'Title EN']];

    expect(isco08Call('parseSheetWithHeader', [$cover, $first, $second], ['isco-08'], ['title en']))->toBe([$first, 0])
        ->and(isco08Call('parseSheetWithHeader', [$cover, $second], ['isco-08'], ['title en']))->toBe([$second, 1])
        ->and(isco08Call('parseSheetWithHeader', [$cover], ['isco-08'], ['title en']))->toBeNull();
});

it('indexes header names lowercased with spaces collapsed, first column winning', function (): void {
    expect(isco08Call('buildColumnIndex', ['ISCO 08 Code', "Title\n  EN", '', 'Title EN', null, 7, '  Notes  ']))->toBe([
        'isco 08 code' => 0,
        'title en' => 1,
        '7' => 5,
        'notes' => 6,
    ]);
});

it('finds the first column whose normalized name is one it wants', function (array $names, ?int $expected): void {
    expect(isco08Call('findColumnIndexExact', ['ISCO-08 code', "English\t Title", '', 'ISCO-08'], $names))->toBe($expected);
})->with([
    'the earliest matching column' => [['isco-08', 'english title'], 1],
    'an exact name only' => [['isco-08'], 3],
    'none' => [['isco 08'], null],
]);

it('builds one structure row per numeric code in either workbook', function (): void {
    expect(isco08Call('buildStructureRowsFromSourceTables', [
        '1' => isco08Definition('Managers'),
        '2' => isco08Definition(''),
        'X1' => isco08Definition('Letters'),
    ], [
        '3' => ['Three'],
        '1' => ['Other'],
    ]))->toBe([
        '1' => ['code' => '1', 'title' => 'Managers'],
        '2' => ['code' => '2', 'title' => ''],
        '3' => ['code' => '3', 'title' => ''],
    ]);
});

it('rejects workbooks that produce no numeric code', function (array $definitions, array $titles, string $message): void {
    expect(fn (): mixed => isco08Call('buildStructureRowsFromSourceTables', $definitions, $titles))->toThrow(ValueError::class, $message);
})->with([
    'no codes at all' => [[], [], 'No ISCO-08 codes found: the definitions and index workbooks produced no valid code rows.'],
    'only codes that are not numeric' => [['X1' => isco08Definition('Letters')], ['Y' => ['Why']], 'No valid numeric ISCO-08 codes after filtering the definitions and index workbooks.'],
]);

it('adds an untitled row for each missing level above a listed code', function (): void {
    $rows = isco08CallInPlace('ensureHierarchyUnitsFromPrefixes', [
        'x9' => ['code' => 'x9', 'title' => 'Not numeric'],
        '3x' => ['code' => '3x', 'title' => 'Numeric prefix'],
        '1234' => ['code' => '1234', 'title' => 'Leaf'],
        '12' => ['code' => '12', 'title' => 'Kept'],
        '21' => ['code' => '21', 'title' => 'Two one'],
    ]);
    ksort($rows, SORT_STRING);

    expect($rows)->toBe([
        '1' => ['code' => '1', 'title' => ''],
        '12' => ['code' => '12', 'title' => 'Kept'],
        '123' => ['code' => '123', 'title' => ''],
        '1234' => ['code' => '1234', 'title' => 'Leaf'],
        '2' => ['code' => '2', 'title' => ''],
        '21' => ['code' => '21', 'title' => 'Two one'],
        '3' => ['code' => '3', 'title' => ''],
        '3x' => ['code' => '3x', 'title' => 'Numeric prefix'],
        'x9' => ['code' => 'x9', 'title' => 'Not numeric'],
    ]);
});

it('merges each code\'s structure, definition and index titles, shortest codes first', function (): void {
    $merged = isco08Call('mergeRecords', [
        '11' => ['code' => '11', 'title' => ''],
        '1' => ['code' => '1', 'title' => 'Row title'],
        '3' => ['code' => '3', 'title' => 'Row three'],
        '2' => ['code' => '2', 'title' => ''],
        '111' => ['code' => '111', 'title' => ''],
    ], [
        '1' => isco08Definition('Definition title', 'D1', 'T1', '', 'N1'),
        '2' => isco08Definition('', 'D2', 'T2', 'E2', ''),
    ], [
        '2' => ['Index first', 'Index second'],
        '3' => ['Index three'],
        '11' => ['Eleven'],
    ]);

    $record = static fn (string $code, string $title, string $definition, string $tasks, ?string $excluded, ?string $notes, array $titles): array => [
        'level' => strlen($code),
        'code' => $code,
        'major_group' => substr($code, 0, 1),
        'sub_major_group' => strlen($code) >= 2 ? substr($code, 0, 2) : null,
        'minor_group' => strlen($code) >= 3 ? substr($code, 0, 3) : null,
        'unit_group' => strlen($code) >= 4 ? $code : null,
        'title' => $title,
        'definition' => $definition,
        'tasks_include' => $tasks,
        'excluded_occupations' => $excluded,
        'notes' => $notes,
        'occupational_titles' => $titles,
    ];

    expect($merged)->toBe([
        '1' => $record('1', 'Definition title', 'D1', 'T1', null, 'N1', []),
        '2' => $record('2', 'Index first', 'D2', 'T2', 'E2', null, ['Index first', 'Index second']),
        '3' => $record('3', 'Row three', '', '', null, null, ['Index three']),
        '11' => $record('11', 'Eleven', '', '', null, null, ['Eleven']),
        '111' => $record('111', '', '', '', null, null, []),
    ]);
});

it('derives the level and group codes from the code itself', function (string $code, array $expected): void {
    expect(isco08Call('hierarchyFromCode', $code))->toBe($expected);
})->with([
    'major group' => ['1', ['level' => 1, 'major_group' => '1', 'sub_major_group' => null, 'minor_group' => null, 'unit_group' => null]],
    'sub-major group' => ['12', ['level' => 2, 'major_group' => '1', 'sub_major_group' => '12', 'minor_group' => null, 'unit_group' => null]],
    'minor group' => ['123', ['level' => 3, 'major_group' => '1', 'sub_major_group' => '12', 'minor_group' => '123', 'unit_group' => null]],
    'unit group' => ['1234', ['level' => 4, 'major_group' => '1', 'sub_major_group' => '12', 'minor_group' => '123', 'unit_group' => '1234']],
]);

it('links each code to the parent, ancestors, children and descendants it has among the codes', function (): void {
    $merged = isco08CallInPlace('attachHierarchy', array_fill_keys(['1112', '12', '1111', '111', '11', '1', '213', '2', '312', '31'], ['title' => 'x']));

    $links = array_map(
        static fn (array $record): array => [$record['parent'], $record['ancestors'], $record['children'], $record['descendants']],
        array_map(static fn (mixed $record): array => mustBeArray($record, 'record'), $merged),
    );

    expect($links)->toBe([
        '1112' => ['111', ['1', '11', '111'], [], []],
        '12' => ['1', ['1'], [], []],
        '1111' => ['111', ['1', '11', '111'], [], []],
        '111' => ['11', ['1', '11'], ['1111', '1112'], ['1111', '1112']],
        '11' => ['1', ['1'], ['111'], ['111', '1111', '1112']],
        '1' => [null, [], ['11', '12'], ['11', '12', '111', '1111', '1112']],
        '213' => [null, ['2'], [], []],
        '2' => [null, [], [], []],
        '312' => ['31', ['31'], [], []],
        '31' => [null, [], ['312'], ['312']],
    ])->and(mustBeArray($merged['1'], 'record 1')['title'])->toBe('x');
});

it('lists the codes above one, top first', function (string $code, array $expected): void {
    expect(isco08Call('ancestorsForUnit', $code))->toBe($expected);
})->with([
    'major group' => ['1', []],
    'sub-major group' => ['12', ['1']],
    'unit group' => ['1234', ['1', '12', '123']],
]);

it('names the parent as the code one digit shorter', function (string $code, ?string $expected): void {
    expect(isco08Call('parentCode', $code))->toBe($expected);
})->with([
    'none for a major group' => ['1', null],
    'none for an empty code' => ['', null],
    'sub-major group' => ['12', '1'],
    'unit group' => ['1234', '123'],
]);

it('orders codes shorter first, then by digit', function (): void {
    $codes = ['111', '2', '12', '1', '11', '1111'];
    usort($codes, static function (string $a, string $b): int {
        $comparison = isco08Call('compareIscoCodes', $a, $b);

        return is_int($comparison) ? $comparison : throw new RuntimeException('compareIscoCodes returned '.get_debug_type($comparison));
    });

    expect($codes)->toBe(['1', '2', '11', '12', '111', '1111'])
        ->and(isco08Call('compareIscoCodes', '11', '11'))->toBe(0)
        ->and(isco08Call('compareIscoCodes', '2', '11'))->toBeLessThan(0)
        ->and(isco08Call('compareIscoCodes', '11', '2'))->toBeGreaterThan(0)
        ->and(isco08Call('compareIscoCodes', '11', '12'))->toBeLessThan(0)
        ->and(isco08Call('compareIscoCodes', '12', '11'))->toBeGreaterThan(0);
});

it('reads code keys back as strings', function (): void {
    expect(isco08Call('codeKeys', ['1' => 'a', '12' => 'b', 'x' => 'c']))->toBe(['1', '12', 'x']);
});

it('normalizes a spreadsheet code cell to its digits', function (mixed $cell, string $expected): void {
    expect(isco08Call('normalizeIscoCode', $cell))->toBe($expected);
})->with([
    'null' => [null, ''],
    'int' => [11, '11'],
    'whole float' => [11.0, '11'],
    'fractional float' => [1.5, '1.5'],
    'decimal zeros' => ['0111.00', '0111'],
    'padded' => [' 21 ', '21'],
    'decimal digits' => ['21.5', '21.5'],
    'bare decimal point' => ['7.', '7.'],
    'true' => [true, '1'],
    'array' => [['1'], ''],
]);

it('accepts only codes of one to four digits', function (string $code, bool $expected): void {
    expect(isco08Call('isNumericIscoCode', $code))->toBe($expected);
})->with([
    'one digit' => ['1', true],
    'four digits' => ['0110', true],
    'empty' => ['', false],
    'five digits' => ['12345', false],
    'trailing letter' => ['1a', false],
    'leading letter' => ['a1', false],
    'decimal' => ['1.5', false],
]);

it('turns every line break into a newline and trims the text', function (): void {
    expect(isco08Call('normalizeMultiline', "  a\r\nb\rc\n  "))->toBe("a\nb\nc");
});

it('casts a cell to a string or an int the way PHP would, and anything else to empty', function (mixed $cell, string $string, int $int): void {
    expect(isco08Call('toStringValue', $cell))->toBe($string)
        ->and(isco08Call('toIntValue', $cell))->toBe($int);
})->with([
    'string' => ['3x', '3x', 3],
    'int' => [3, '3', 3],
    'float' => [2.9, '2.9', 2],
    'true' => [true, '1', 1],
    'false' => [false, '', 0],
    'null' => [null, '', 0],
    'array' => [['3'], '', 0],
    'object' => [new stdClass, '', 0],
]);

it('writes the exact escape for each character a double-quoted literal needs', function (string $value, string $expected): void {
    expect(isco08Call('phpString', $value))->toBe($expected);
})->with([
    'backslash, single-quoted' => ['C:\\path', "'C:\\\\path'"],
    'double quote beside a single quote' => ["it's \"q\"", '"it\'s \\"q\\""'],
    'dollar beside a single quote' => ["it's \$5", '"it\'s \\$5"'],
    'backslash beside a single quote' => ["it's C:\\x", '"it\'s C:\\\\x"'],
    'carriage return' => ["a\rb", '"a\\rb"'],
    'tab' => ["a\tb", '"a\\tb"'],
    'bell' => ["a\x07b", '"a\\x07b"'],
    'null byte' => ["\x00", '"\\x00"'],
    'start of heading' => ["\x01", '"\\x01"'],
    'backspace' => ["\x08", '"\\x08"'],
    'vertical tab and form feed' => ["\x0B\x0C", '"\\x0B\\x0C"'],
    'shift out' => ["\x0E", '"\\x0E"'],
    'unit separator' => ["\x1F", '"\\x1F"'],
    'delete' => ["\x7F", '"\\x7F"'],
    'invalid UTF-8' => ["caf\xC3", "'caf?'"],
]);

it('writes null for an empty or missing value, and a literal otherwise', function (mixed $value, string $expected): void {
    expect(isco08Call('phpNullableString', $value))->toBe($expected);
})->with([
    'null' => [null, 'null'],
    'empty' => ['', 'null'],
    'text' => ['x', "'x'"],
    'zero' => [0, "'0'"],
    'single quote' => ["it's", '"it\'s"'],
]);

it('writes a list of string literals, or an empty list', function (mixed $items, string $expected): void {
    expect(isco08Call('phpStringArray', $items))->toBe($expected);
})->with([
    'not a list' => ['x', '[]'],
    'empty' => [[], '[]'],
    'mixed values' => [['a', "b'c", 3], "['a', \"b'c\", '3']"],
]);

/**
 * A stand-in for GeneratorUtils::downloadToFile() that writes an empty file and records
 * each call, failing instead for any URL listed in `$failing`.
 *
 * @param  list<array{url: string, path: string, headers: array<string, string>, timeout: int}>  $calls
 * @param  list<string>  $failing
 */
function isco08Downloader(array &$calls, array $failing = []): Closure
{
    return static function (string $url, string $path, array $headers, int $timeout) use (&$calls, $failing): bool {
        $calls[] = ['url' => $url, 'path' => $path, 'headers' => $headers, 'timeout' => $timeout];

        if (in_array($url, $failing, true)) {
            return false;
        }

        file_put_contents($path, '');

        return true;
    };
}

it('downloads whichever workbook it is not given to a new temporary file', function (?string $definitions, ?string $index, array $expectedUrls): void {
    isco08FakeWorkbooks([
        'isco08_definitions_' => [[isco08DefinitionsHeader(), ['1', 'Managers']]],
        'defs.xlsx' => [[isco08DefinitionsHeader(), ['1', 'Managers']]],
        'isco08_index_' => [[['ISCO-08', 'English title'], ['1', 'Manager']]],
        'index.xlsx' => [[['ISCO-08', 'English title'], ['1', 'Manager']]],
    ]);
    /** @var list<array{url: string, path: string, headers: array<string, string>, timeout: int}> $calls */
    $calls = [];

    try {
        isco08WithScratchAppPath(function (string $scratch) use ($definitions, $index, &$calls): void {
            File::ensureDirectoryExists($scratch.'/Enums');

            (new Isco08EnumGenerator(isco08Downloader($calls)))->generate($definitions, $index);
        });

        expect(array_column($calls, 'url'))->toBe($expectedUrls);

        foreach ($calls as $call) {
            $prefix = $call['url'] === Isco08EnumGenerator::URL_DEFINITIONS_XLSX ? 'isco08_definitions_' : 'isco08_index_';

            expect($call['path'])->toMatch('/^'.preg_quote(sys_get_temp_dir().DIRECTORY_SEPARATOR.$prefix, '/').'[0-9a-f]{14}\.\d{8}\.xlsx$/')
                ->and($call['headers'])->toBe(['Accept' => '*/*'])
                ->and($call['timeout'])->toBe(180);
        }
    } finally {
        foreach ($calls as $call) {
            @unlink($call['path']);
        }
    }
})->with([
    'both' => [null, null, [Isco08EnumGenerator::URL_DEFINITIONS_XLSX, Isco08EnumGenerator::URL_INDEX_XLSX]],
    'the index only' => ['defs.xlsx', null, [Isco08EnumGenerator::URL_INDEX_XLSX]],
    'the definitions only' => [null, 'index.xlsx', [Isco08EnumGenerator::URL_DEFINITIONS_XLSX]],
    'neither' => ['defs.xlsx', 'index.xlsx', []],
]);

it('fails by URL when a workbook does not download', function (string $failing): void {
    /** @var list<array{url: string, path: string, headers: array<string, string>, timeout: int}> $calls */
    $calls = [];

    try {
        expect(fn (): string => (new Isco08EnumGenerator(isco08Downloader($calls, [$failing])))->generate())
            ->toThrow(ValueError::class, 'Failed to download: '.$failing);
    } finally {
        foreach ($calls as $call) {
            @unlink($call['path']);
        }
    }
})->with([
    'definitions' => [Isco08EnumGenerator::URL_DEFINITIONS_XLSX],
    'index' => [Isco08EnumGenerator::URL_INDEX_XLSX],
]);

/**
 * Runs {@see Isco08EnumGenerator::generate()} against the ILO workbooks committed
 * beside it, the inputs `php artisan generate:isco-08-enum --local` reads.
 *
 * The generator keys its records by ISCO code, and PHP stores a decimal-integer
 * string key such as `'1111'` as the int `1111`, so every code read back through
 * `array_keys()` used to reach the strictly typed string helpers as an int and
 * throw a `TypeError` before anything was written (#713). Its output also needed a
 * separate Pint pass before it matched the committed enum (#715).
 *
 * The application path is pointed at a scratch directory for the run, so the
 * generator writes its enum there and never over the committed `app/Enums/Isco08.php`.
 *
 * @command  php artisan test --compact tests/Unit/Services/EnumGeneration/Isco08EnumGeneratorTest.php
 */
it('regenerates the committed ISCO-08 enum from the committed workbooks, Pint-clean', function (): void {
    $fixtures = Isco08EnumGenerator::defaultLocalFixturePaths();
    expect(Isco08EnumGenerator::localFixturesAreReadable())->toBeTrue();

    $committed = (string) file_get_contents(app_path('Enums/Isco08.php'));
    $originalAppPath = app_path();
    $scratch = sys_get_temp_dir().DIRECTORY_SEPARATOR.'isco08-enum-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists($scratch.DIRECTORY_SEPARATOR.'Enums');

    try {
        app()->useAppPath($scratch);

        $path = (new Isco08EnumGenerator)->generate($fixtures['definitions'], $fixtures['index']);

        expect($path)->toBe(app_path('Enums/Isco08.php'));
        $php = (string) file_get_contents($path);

        $pint = new Process(
            [PHP_BINARY, base_path('vendor/bin/pint'), '--test', '--config', base_path('pint.json'), $path],
            base_path(),
        );
        $pint->run();
    } finally {
        app()->useAppPath($originalAppPath);
        File::deleteDirectory($scratch);
    }

    // Pint has nothing to change in the file exactly as the generator wrote it.
    expect($pint->getExitCode())->toBe(0, $pint->getOutput().$pint->getErrorOutput());

    // Byte for byte the committed enum, apart from the date it was generated on.
    $undated = static fn (string $source): string => (string) preg_replace('/^ \* Generated: \d{4}-\d{2}-\d{2}$/m', ' * Generated: <date>', $source);
    expect($undated($php))->toBe($undated($committed));

    preg_match_all("/^    case (ISCO08_\\d{1,4}) = '(\\d{1,4})';$/m", $php, $matches);

    $expected = array_map(
        static fn (Isco08 $case): string => $case->name.'='.$case->value,
        Isco08::cases(),
    );
    $generated = array_map(
        static fn (string $name, string $value): string => $name.'='.$value,
        $matches[1],
        $matches[2],
    );

    // Same cases, in the same order, as the committed enum.
    expect($generated)->toBe($expected)
        ->and(count($generated))->toBeGreaterThan(600);
});
