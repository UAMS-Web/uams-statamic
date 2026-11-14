<?php

declare(strict_types=1);

use App\Services\EnumGeneration\Isced\IscedA2011EnumGenerator;
use App\Services\EnumGeneration\Isced\IscedAnnexLoader;
use App\Services\EnumGeneration\Isced\IscedEnumConfig;
use App\Services\EnumGeneration\Isced\IscedEnumsGenerator;
use App\Services\EnumGeneration\Isced\IscedEnumWriter;
use App\Services\EnumGeneration\Isced\IscedF2013EnumGenerator;
use App\Services\EnumGeneration\Isced\IscedFieldsLoader;
use App\Services\EnumGeneration\Isced\IscedP2011EnumGenerator;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

uses(TestCase::class);

covers(
    IscedEnumsGenerator::class,
    IscedA2011EnumGenerator::class,
    IscedP2011EnumGenerator::class,
    IscedF2013EnumGenerator::class,
    IscedAnnexLoader::class,
    IscedFieldsLoader::class,
    IscedEnumWriter::class,
);

/**
 * The ISCED enum generators behind `php artisan generate:isced-enums`
 * (UAMS-Web/uams-statamic#309): the two fixture loaders, the shared writer, the
 * three per-classification generators and the coordinator that runs them.
 *
 * The writer writes to `app_path('Enums/<class>.php')`, so each test points the
 * application path at its own directory under the system temp directory and
 * nothing reaches `app/Enums`. Regenerating from the bundled fixtures reproduces
 * the committed enums except for two lines: the `Generated:` date, and the
 * `declare(strict_types=1);` that Pint adds after generation.
 *
 * @command  php artisan test --compact tests/Unit/Services/EnumGeneration/Isced/IscedEnumGeneratorsTest.php
 */
beforeEach(function (): void {
    // Point app_path() at a throwaway root holding an Enums directory
    $this->iscedRoot = sys_get_temp_dir().'/isced-enums-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists($this->iscedRoot.'/Enums');
    $this->committedAppPath = app_path();
    app()->useAppPath($this->iscedRoot);
});

afterEach(function (): void {
    // Restore the real application path and remove the throwaway root
    app()->useAppPath($this->committedAppPath);
    File::deleteDirectory($this->iscedRoot);
});

/**
 * Write a fixture JSON file under the test's throwaway root and return its path.
 *
 * @param  string  $root  The throwaway root.
 * @param  string  $json  The file's contents.
 */
function iscedFixture(string $root, string $json): string
{
    $path = $root.'/fixture-'.bin2hex(random_bytes(4)).'.json';
    file_put_contents($path, $json);

    return $path;
}

/**
 * Encode a rows fixture.
 *
 * @param  array<int, mixed>  $rows
 */
function iscedRows(array $rows): string
{
    return json_encode(['rows' => $rows], JSON_THROW_ON_ERROR);
}

/**
 * The committed enum as the generator writes it on the given date: without the
 * `declare(strict_types=1);` Pint adds, and with that date on the `Generated:` line.
 *
 * @param  string  $enumClass  The enum's short class name.
 * @param  string  $date  The `Y-m-d` date the generator stamps.
 * @param  string  $committedAppPath  The real application path.
 */
function iscedCommittedAsGenerated(string $enumClass, string $date, string $committedAppPath): string
{
    $committed = (string) file_get_contents($committedAppPath.'/Enums/'.$enumClass.'.php');
    $withoutDeclare = str_replace("<?php\n\ndeclare(strict_types=1);\n\n", "<?php\n\n", $committed);

    return (string) preg_replace('/^ \* Generated: \d{4}-\d{2}-\d{2}$/m', ' * Generated: '.$date, $withoutDeclare);
}

/**
 * A complete record for the writer.
 *
 * @param  array<string, mixed>  $overrides
 * @return array{code: string, label: string, parent: ?string, ancestors: array<int, string>, children: array<int, string>, descendants: array<int, string>, tier: int}
 */
function iscedRecord(array $overrides = []): array
{
    /** @var array{code: string, label: string, parent: ?string, ancestors: array<int, string>, children: array<int, string>, descendants: array<int, string>, tier: int} $record */
    $record = array_merge([
        'code' => '34',
        'label' => 'General',
        'parent' => '3',
        'ancestors' => ['3'],
        'children' => ['344'],
        'descendants' => ['344'],
        'tier' => 2,
    ], $overrides);

    return $record;
}

/** A writer config for an enum class that only the throwaway root holds. */
function iscedConfig(): IscedEnumConfig
{
    return new IscedEnumConfig(
        enumClass: 'IscedTestEnum',
        casePrefix: 'TEST_CODE',
        classification: 'Test Classification',
        classificationShort: 'TC 1',
        sourceTitle: 'Test source',
        sourceUrl: 'https://example.test/source',
        emitNotes: 'Test notes.',
    );
}

it('regenerates each committed ISCED enum from its bundled fixture', function (IscedA2011EnumGenerator|IscedP2011EnumGenerator|IscedF2013EnumGenerator $generator, string $enumClass): void {
    $path = $generator->generate();

    expect($path)->toBe($this->iscedRoot.'/Enums/'.$enumClass.'.php')
        ->and(file_get_contents($path))->toBe(iscedCommittedAsGenerated($enumClass, date('Y-m-d'), $this->committedAppPath));
})->with([
    'ISCED-A 2011' => [new IscedA2011EnumGenerator, 'IscedA2011'],
    'ISCED-P 2011' => [new IscedP2011EnumGenerator, 'IscedP2011'],
    'ISCED-F 2013' => [new IscedF2013EnumGenerator, 'IscedF2013'],
]);

it('runs all three generators and returns the path each one wrote', function (): void {
    $paths = (new IscedEnumsGenerator)->generate();

    expect($paths)->toBe([
        'a' => $this->iscedRoot.'/Enums/IscedA2011.php',
        'p' => $this->iscedRoot.'/Enums/IscedP2011.php',
        'f' => $this->iscedRoot.'/Enums/IscedF2013.php',
    ]);

    foreach (['a' => 'IscedA2011', 'p' => 'IscedP2011', 'f' => 'IscedF2013'] as $key => $enumClass) {
        expect(file_get_contents($paths[$key]))->toBe(iscedCommittedAsGenerated($enumClass, date('Y-m-d'), $this->committedAppPath));
    }
});

it('passes a given ISCED-F fixture through the coordinator to the ISCED-F generator', function (): void {
    $fixture = iscedFixture($this->iscedRoot, iscedRows([
        ['code' => '01', 'label' => 'Education'],
        ['code' => '011', 'label' => 'Education science'],
    ]));

    $paths = (new IscedEnumsGenerator)->generate($fixture);
    $php = (string) file_get_contents($paths['f']);

    expect($php)->toContain('Total cases: 2')
        ->toContain("case ISCEDF_01 = '01';")
        ->toContain("case ISCEDF_011 = '011';")
        ->not->toContain('ISCEDF_02');
});

it('builds the ISCED-F hierarchy from two-digit broad fields', function (): void {
    $fixture = iscedFixture($this->iscedRoot, iscedRows([
        ['code' => '0111', 'label' => 'Education science'],
        ['code' => '01', 'label' => 'Education'],
        ['code' => '011', 'label' => 'Education, general'],
        ['code' => '02', 'label' => 'Arts'],
    ]));

    $php = (string) file_get_contents((new IscedF2013EnumGenerator)->generate($fixture));

    expect($php)->toContain(implode("\n", [
        '    #[IscedInfo(',
        "        code: '01',",
        "        label: 'Education',",
        '        parent: null,',
        '        ancestors: [],',
        "        children: ['011'],",
        "        descendants: ['011', '0111'],",
        '        tier: 1,',
        '    )]',
        "    case ISCEDF_01 = '01';",
    ]))->toContain(implode("\n", [
        "        code: '0111',",
        "        label: 'Education science',",
        "        parent: '011',",
        "        ancestors: ['01', '011'],",
        '        children: [],',
        '        descendants: [],',
        '        tier: 3,',
    ]));
});

it('names the bundled ISCED-F fixture beside the generator and reports it readable', function (): void {
    $expected = dirname(mustBeString((new ReflectionClass(IscedF2013EnumGenerator::class))->getFileName(), 'generator file')).DIRECTORY_SEPARATOR.'isced-f-2013.json';

    expect(IscedF2013EnumGenerator::defaultFixturePath())->toBe($expected)
        ->and(IscedF2013EnumGenerator::fixtureIsReadable())->toBeTrue();
});

it('loads, trims, deduplicates and sorts fixture rows', function (IscedAnnexLoader|IscedFieldsLoader $loader, array $rows, array $expected): void {
    /** @var array<int, mixed> $rows */
    $fixture = iscedFixture($this->iscedRoot, iscedRows($rows));

    expect($loader->load($fixture))->toBe($expected);
})->with([
    // Every skipped row comes before the rows kept, so a skip that stopped the loop
    // instead of moving past the row would leave nothing.
    'annex' => [new IscedAnnexLoader, [
        'not a row',
        ['code' => '', 'label' => 'No code'],
        ['code' => '35', 'label' => '  '],
        ['code' => ['3'], 'label' => 'Array code'],
        ['code' => '36', 'label' => ['Array label']],
        ['label' => 'Missing code'],
        ['code' => '37'],
        ['code' => ' 34 ', 'label' => ' General '],
        ['code' => '3', 'label' => 'Upper secondary'],
        ['code' => '344', 'label' => 'Sufficient'],
        ['code' => 34, 'label' => 'General, relabeled'],
        ['code' => '2', 'label' => 2011],
    ], [
        ['code' => '2', 'label' => '2011'],
        ['code' => '3', 'label' => 'Upper secondary'],
        ['code' => '34', 'label' => 'General, relabeled'],
        ['code' => '344', 'label' => 'Sufficient'],
    ]],
    'fields' => [new IscedFieldsLoader, [
        'not a row',
        ['code' => '', 'label' => 'No code'],
        ['code' => '02', 'label' => ''],
        ['code' => null, 'label' => 'Null code'],
        ['code' => ['03'], 'label' => 'Array code'],
        ['code' => '04', 'label' => ['Array label']],
        ['code' => '05'],
        ['code' => ' 0111 ', 'label' => ' Education science '],
        ['code' => '01', 'label' => 'Education'],
        ['code' => '011', 'label' => 'Education, general'],
        ['code' => '01', 'label' => 'Education, relabeled'],
        ['code' => 10, 'label' => 2013],
    ], [
        ['code' => '01', 'label' => 'Education, relabeled'],
        ['code' => '10', 'label' => '2013'],
        ['code' => '011', 'label' => 'Education, general'],
        ['code' => '0111', 'label' => 'Education science'],
    ]],
]);

it('accepts codes of the lengths its classification uses and refuses others', function (IscedAnnexLoader|IscedFieldsLoader $loader, string $code, bool $valid): void {
    $fixture = iscedFixture($this->iscedRoot, iscedRows([['code' => $code, 'label' => 'Label']]));

    $load = fn (): array => $loader->load($fixture);

    if ($valid) {
        expect($load())->toBe([['code' => $code, 'label' => 'Label']]);
    } else {
        expect($load)->toThrow(ValueError::class, ': '.$code);
    }
})->with([
    'annex, one digit' => [new IscedAnnexLoader, '3', true],
    'annex, three digits' => [new IscedAnnexLoader, '344', true],
    'annex, four digits' => [new IscedAnnexLoader, '3441', false],
    'annex, a letter' => [new IscedAnnexLoader, '3a', false],
    'annex, a trailing letter after digits' => [new IscedAnnexLoader, '34x', false],
    'fields, one digit' => [new IscedFieldsLoader, '1', false],
    'fields, two digits' => [new IscedFieldsLoader, '01', true],
    'fields, four digits' => [new IscedFieldsLoader, '0111', true],
    'fields, five digits' => [new IscedFieldsLoader, '01111', false],
    'fields, the Eurostat prefix' => [new IscedFieldsLoader, 'F01', false],
]);

it('names the fixture and the code in a refused code', function (IscedAnnexLoader|IscedFieldsLoader $loader, string $prefix): void {
    $fixture = iscedFixture($this->iscedRoot, iscedRows([['code' => '12345', 'label' => 'Label']]));

    expect(fn (): array => $loader->load($fixture))
        ->toThrow(ValueError::class, $prefix.' in '.$fixture.': 12345');
})->with([
    'annex' => [new IscedAnnexLoader, 'Invalid ISCED level code'],
    'fields' => [new IscedFieldsLoader, 'Invalid ISCED-F code'],
]);

it('refuses a fixture it cannot use, naming it', function (IscedAnnexLoader|IscedFieldsLoader $loader, ?string $json, string $message): void {
    $fixture = $json === null ? $this->iscedRoot.'/absent.json' : iscedFixture($this->iscedRoot, $json);

    expect(fn (): array => $loader->load($fixture))
        ->toThrow(ValueError::class, $message.$fixture);
})->with([
    'annex, absent' => [new IscedAnnexLoader, null, 'ISCED fixture not readable: '],
    'annex, no rows key' => [new IscedAnnexLoader, '{"source": "x"}', "ISCED fixture missing 'rows' array: "],
    'annex, rows not a list' => [new IscedAnnexLoader, '{"rows": "x"}', "ISCED fixture missing 'rows' array: "],
    'annex, a top-level list' => [new IscedAnnexLoader, '[1, 2]', "ISCED fixture missing 'rows' array: "],
    'annex, a scalar document' => [new IscedAnnexLoader, '"rows"', "ISCED fixture missing 'rows' array: "],
    'annex, no usable rows' => [new IscedAnnexLoader, '{"rows": [{"code": "", "label": "x"}]}', 'ISCED fixture produced no valid rows: '],
    'annex, empty rows' => [new IscedAnnexLoader, '{"rows": []}', 'ISCED fixture produced no valid rows: '],
    'fields, absent' => [new IscedFieldsLoader, null, 'ISCED-F fixture not readable: '],
    'fields, no rows key' => [new IscedFieldsLoader, '{"source": "x"}', "ISCED-F fixture missing 'rows' array: "],
    'fields, rows not a list' => [new IscedFieldsLoader, '{"rows": 3}', "ISCED-F fixture missing 'rows' array: "],
    'fields, a top-level list' => [new IscedFieldsLoader, '[1, 2]', "ISCED-F fixture missing 'rows' array: "],
    'fields, a scalar document' => [new IscedFieldsLoader, '7', "ISCED-F fixture missing 'rows' array: "],
    'fields, no usable rows' => [new IscedFieldsLoader, '{"rows": [{"code": "01", "label": ""}]}', 'ISCED-F fixture produced no valid rows: '],
    'fields, empty rows' => [new IscedFieldsLoader, '{"rows": []}', 'ISCED-F fixture produced no valid rows: '],
]);

it('lets malformed JSON surface as a JSON error', function (IscedAnnexLoader|IscedFieldsLoader $loader): void {
    $fixture = iscedFixture($this->iscedRoot, '{"rows": [');

    expect(fn (): array => $loader->load($fixture))->toThrow(JsonException::class);
})->with([
    'annex' => [new IscedAnnexLoader],
    'fields' => [new IscedFieldsLoader],
]);

it('decodes a fixture nested 512 levels deep and refuses one nested 513', function (IscedAnnexLoader|IscedFieldsLoader $loader, string $code): void {
    // json_decode() counts the depth of this document as its padding lists plus four
    // (measured: 508 lists decode at a depth limit of 512, and 509 do not).
    $nested = fn (int $depth): string => '{"rows": [{"code": "'.$code.'", "label": "Label", "pad": '
        .str_repeat('[', $depth - 4).str_repeat(']', $depth - 4).'}]}';

    expect($loader->load(iscedFixture($this->iscedRoot, $nested(512))))->toBe([['code' => $code, 'label' => 'Label']])
        ->and(fn (): array => $loader->load(iscedFixture($this->iscedRoot, $nested(513))))->toThrow(JsonException::class);
})->with([
    'annex' => [new IscedAnnexLoader, '3'],
    'fields' => [new IscedFieldsLoader, '01'],
]);

it('refuses to write an enum with no rows, naming it', function (): void {
    expect(fn (): string => (new IscedEnumWriter)->write([], iscedConfig()))
        ->toThrow(ValueError::class, 'No rows provided for enum IscedTestEnum');

    expect(File::exists($this->iscedRoot.'/Enums/IscedTestEnum.php'))->toBeFalse();
});

it('reports a write it could not make, naming the path', function (): void {
    File::deleteDirectory($this->iscedRoot.'/Enums');

    // Silence the warning file_put_contents raises, so its false return reaches the writer
    set_error_handler(static fn (): bool => true);

    try {
        expect(fn (): string => (new IscedEnumWriter)->write([iscedRecord()], iscedConfig()))
            ->toThrow(ValueError::class, 'Failed to write enum file: '.$this->iscedRoot.'/Enums/IscedTestEnum.php');
    } finally {
        restore_error_handler();
    }
});

it('writes the header, one attributed case per row, and the getters', function (): void {
    $path = (new IscedEnumWriter)->write([
        iscedRecord(['code' => '3', 'label' => 'Upper', 'parent' => null, 'ancestors' => [], 'children' => ['34'], 'descendants' => ['34'], 'tier' => 1]),
        iscedRecord(['children' => [], 'descendants' => []]),
    ], iscedConfig());

    $php = (string) file_get_contents($path);

    expect($path)->toBe($this->iscedRoot.'/Enums/IscedTestEnum.php')
        ->and($php)->toStartWith(implode("\n", [
            '<?php',
            '',
            'namespace App\Enums;',
            '',
            'use App\Enums\Attributes\IscedInfo;',
            'use App\Services\EnumGeneration\EnumDataUtils;',
            '',
            '/**',
            ' * Test Classification (TC 1) code list.',
            ' *',
            ' * Source: Test source',
            ' *   https://example.test/source',
            ' *',
            ' * Test notes.',
            ' *',
            ' * Generated: '.date('Y-m-d'),
            ' * Regenerate: php artisan generate:isced-enums',
            ' *',
            ' * Case names use the prefix TEST_CODE_ (backing value is the canonical code).',
            ' * Total cases: 2',
            ' */',
            'enum IscedTestEnum: string',
            '{',
            '    #[IscedInfo(',
            "        code: '3',",
            "        label: 'Upper',",
            '        parent: null,',
            '        ancestors: [],',
            "        children: ['34'],",
            "        descendants: ['34'],",
            '        tier: 1,',
            '    )]',
            "    case TEST_CODE_3 = '3';",
            '',
            '    #[IscedInfo(',
            "        code: '34',",
            "        label: 'General',",
            "        parent: '3',",
            "        ancestors: ['3'],",
            '        children: [],',
            '        descendants: [],',
            '        tier: 2,',
            '    )]',
            "    case TEST_CODE_34 = '34';",
            '',
            '    /**',
        ]))
        ->toContain("            'IscedTestEnum',\n")
        ->toEndWith("        return implode(\$separator, \$labels);\n    }\n}\n");
});

it('writes an empty parent as null, and escapes and straightens quotes in labels', function (): void {
    $php = (string) file_get_contents((new IscedEnumWriter)->write([
        iscedRecord(['parent' => '', 'label' => "\u{2018}It\u{2019}s\u{2019} \u{201C}a\u{201D} back\\slash"]),
    ], iscedConfig()));

    expect($php)->toContain('        parent: null,')
        ->toContain("        label: '\\'It\\'s\\' \"a\" back\\\\slash',");
});

it('writes a non-scalar parent or list item as an empty string, a scalar parent as a string, and casts the tier', function (): void {
    /** @var array{code: string, label: string, parent: ?string, ancestors: array<int, string>, children: array<int, string>, descendants: array<int, string>, tier: int} $record */
    $record = array_merge(iscedRecord(), [
        'parent' => ['3'],
        'ancestors' => [['3'], 34],
        'children' => 'not a list',
        'tier' => '02',
    ]);

    // A scalar parent that is not a string is written as its string form
    /** @var array{code: string, label: string, parent: ?string, ancestors: array<int, string>, children: array<int, string>, descendants: array<int, string>, tier: int} $intParent */
    $intParent = array_merge(iscedRecord(['code' => '34']), ['parent' => 3]);

    $php = (string) file_get_contents((new IscedEnumWriter)->write([$record, $intParent], iscedConfig()));

    expect($php)->toContain("        parent: '',")
        ->toContain("        ancestors: ['', '34'],")
        ->toContain('        children: [],')
        ->toContain('        tier: 2,')
        ->not->toContain('tier: 02,')
        ->toContain("        parent: '3',");
});
