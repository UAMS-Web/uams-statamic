<?php

declare(strict_types=1);

use App\Services\EnumGeneration\ZipCode\ZipCodeEnumGenerator;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    // The generator caches HUD records; keep that in memory, not in the shared file cache
    config(['cache.default' => 'array']);
});

/**
 * Unit tests for the extracted {@see ZipCodeEnumGenerator} (UAMS-Web/uams-statamic-archive#876).
 *
 * Exercises the record normalization, row building and PHP rendering, the
 * cached and token-less paths of `generate()`, and the chunked HUD response
 * parser on local files.
 *
 * @command  php artisan test --compact tests/Unit/Services/EnumGeneration/ZipCodeEnumGeneratorTest.php
 */
it('processes a HUD record into an attribute + case row', function () {
    $record = [
        'zip' => '61744',
        'city' => 'Gridley',
        'state' => 'IL',
        'state_geoId' => '17',
        'county_geoId' => ['17113', '17105'],
    ];

    $row = (new ZipCodeEnumGenerator)->processZipRecordWithHudData($record, $record);

    expect($row['attribute'])->toBe(
        "#[ZipCodeInfo(zip_code: '61744', city: 'Gridley', state_usps: 'IL', state_geoId: '17', county_geoId: ['17113', '17105'])]"
    );
    expect($row['case'])->toBe("case ZIP_61744 = 'ZIP_61744';");
});

it('formats an empty county_geoId list as [] not [\'\']', function () {
    $record = [
        'zip' => '72205',
        'city' => 'Little Rock',
        'state' => 'AR',
        'state_geoId' => '05',
        'county_geoId' => [],
    ];

    $row = (new ZipCodeEnumGenerator)->processZipRecordWithHudData($record, $record);

    expect($row['attribute'])->toContain('county_geoId: [])');
});

it('escapes single quotes in the rendered city name', function () {
    $record = [
        'zip' => '12345',
        'city' => "O'Fallon",
        'state' => 'MO',
        'state_geoId' => '29',
        'county_geoId' => [],
    ];

    $row = (new ZipCodeEnumGenerator)->processZipRecordWithHudData($record, $record);

    expect($row['attribute'])->toContain("city: 'O\\'Fallon'");
});

it('builds one prepared row per record', function () {
    $records = [
        ['zip' => '61744', 'city' => 'Gridley', 'state' => 'IL', 'state_geoId' => '17', 'county_geoId' => []],
        ['zip' => '72205', 'city' => 'Little Rock', 'state' => 'AR', 'state_geoId' => '05', 'county_geoId' => []],
    ];

    $rows = (new ZipCodeEnumGenerator)->buildZipRowsFromRecords($records);

    expect($rows)->toHaveCount(2);
    expect($rows[0]['case'])->toBe("case ZIP_61744 = 'ZIP_61744';");
    expect($rows[1]['case'])->toBe("case ZIP_72205 = 'ZIP_72205';");
});

it('renders the ZipCode enum PHP with attributes, cases, and header', function () {
    $rows = [[
        'attribute' => "#[ZipCodeInfo(zip_code: '61744', city: 'Gridley', state_usps: 'IL', state_geoId: '17', county_geoId: ['17113'])]",
        'case' => "case ZIP_61744 = 'ZIP_61744';",
    ]];

    $php = (new ZipCodeEnumGenerator)->generatePhpContent($rows);

    expect($php)->toContain('enum ZipCode: string');
    expect($php)->toContain("case ZIP_61744 = 'ZIP_61744';");
    expect($php)->toContain("#[ZipCodeInfo(zip_code: '61744', city: 'Gridley', state_usps: 'IL', state_geoId: '17', county_geoId: ['17113'])]");
    expect($php)->toContain('Open app/Enums/ZipCode.php');
    expect($php)->toContain('HUD USPS Crosswalk API');
    expect($php)->toContain('Total ZIP records: 1');
});

it('escapes single quotes end to end through render', function () {
    $record = [
        'zip' => '12345',
        'city' => "O'Fallon",
        'state' => 'MO',
        'state_geoId' => '29',
        'county_geoId' => [],
    ];

    $generator = new ZipCodeEnumGenerator;
    $rows = $generator->buildZipRowsFromRecords([$record]);
    $php = $generator->generatePhpContent($rows);

    expect($php)->toContain("city: 'O\\'Fallon'");
});

/**
 * Run the private chunked HUD response parser over the given JSON text.
 *
 * @return array{0: bool, 1: list<array{0: int, 1: array<mixed>}>} The parser's result and each chunk it handed over, with its crosswalk type
 */
function zipCodeParsedChunks(string $json, int $chunkSize, bool $stopAfterFirst = false): array
{
    $file = (string) tempnam(sys_get_temp_dir(), 'zip-code-test');
    file_put_contents($file, $json);
    $chunks = [];

    try {
        $result = (new ReflectionMethod(ZipCodeEnumGenerator::class, 'parseLargeJsonFileInChunks'))->invoke(
            new ZipCodeEnumGenerator,
            $file,
            static function (array $chunk, int $type) use (&$chunks, $stopAfterFirst): bool {
                $chunks[] = [$type, $chunk];

                return ! $stopAfterFirst;
            },
            $chunkSize,
            2
        );
    } finally {
        unlink($file);
    }

    return [(bool) $result, $chunks];
}

/**
 * Capture what generate() writes, instead of writing into app/Enums.
 *
 * @return object{path: ?string, php: ?string}
 */
function zipCodeCapturedWrite(): object
{
    $written = (object) ['path' => null, 'php' => null];
    File::partialMock()->shouldReceive('put')->once()->andReturnUsing(static function (string $path, string $php) use ($written): int {
        $written->path = $path;
        $written->php = $php;

        return \strlen($php);
    });

    return $written;
}

it('writes the enum from cached HUD records without reaching the network', function (?int $limit, string $cacheKey): void {
    cache()->put($cacheKey, [
        ['zip' => '61744', 'city' => 'Gridley', 'state' => 'IL', 'state_geoId' => '17', 'county_geoId' => ['17113']],
    ]);
    $written = zipCodeCapturedWrite();

    $path = (new ZipCodeEnumGenerator)->generate($limit);

    expect($path)->toBe($written->path)
        ->and(str_replace('\\', '/', $path))->toMatch('#/app/Enums/ZipCode_generated_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.php$#')
        ->and($written->php)->toContain("case ZIP_61744 = 'ZIP_61744';")
        ->and($written->php)->toContain('Total ZIP records: 1');
})->with([
    'no limit' => [null, 'hud_zip_comprehensive_data'],
    'a limit' => [2, 'hud_zip_comprehensive_data_limit_2'],
]);

it('writes an empty enum when no HUD token is set, and caches nothing', function (mixed $token): void {
    config(['services.hud.usps_api_token' => $token]);
    $written = zipCodeCapturedWrite();

    (new ZipCodeEnumGenerator)->generate(3);

    expect($written->php)->toContain('Total ZIP records: 0')
        ->and($written->php)->not->toContain('case ZIP_')
        ->and(cache()->has('hud_zip_comprehensive_data_limit_3'))->toBeFalse();
})->with([
    'unset' => [null],
    'empty' => [''],
    'not text' => [123],
]);

it('hands the HUD results over in chunks of the given size', function (): void {
    $json = '{"data":{"year":"2024","results":[{"zip":"61744","geoid":"17113"},{"zip":"61744","geoid":"17105"},{"zip":"72205","geoid":"05119"}]}}';
    $first = [['zip' => '61744', 'geoid' => '17113'], ['zip' => '61744', 'geoid' => '17105']];

    expect(zipCodeParsedChunks($json, 2))->toBe([true, [[2, $first], [2, [['zip' => '72205', 'geoid' => '05119']]]]])
        ->and(zipCodeParsedChunks($json, 10))->toBe([true, [[2, [...$first, ['zip' => '72205', 'geoid' => '05119']]]]])
        ->and(zipCodeParsedChunks($json, 2, stopAfterFirst: true))->toBe([true, [[2, $first]]]);
});

it('hands every result over, whatever it contains', function (string $json, array $chunks): void {
    expect(zipCodeParsedChunks($json, 10))->toBe([true, $chunks]);
})->with([
    'no results' => ['{"data":{"error":"none"}}', []],
    'an object that is not JSON is skipped' => ['{"data":{"results":[{zip:1},{"zip":"2"}]}}', [[2, [['zip' => '2']]]]],
    'an array inside a result' => ['{"data":{"results":[{"zip":"1","tags":[1]},{"zip":"2"}]}}', [[2, [['zip' => '1', 'tags' => [1]], ['zip' => '2']]]]],
    'a nested object inside a result' => ['{"data":{"results":[{"zip":"1","geo":{"a":"b"},"c":"d"},{"zip":"2"}]}}', [[2, [['zip' => '1', 'geo' => ['a' => 'b'], 'c' => 'd'], ['zip' => '2']]]]],
    'an array of objects inside a result' => ['{"data":{"results":[{"zip":"1","rows":[{"a":1},{"b":[2,{"c":3}]}]},{"zip":"2"}]}}', [[2, [['zip' => '1', 'rows' => [['a' => 1], ['b' => [2, ['c' => 3]]]]], ['zip' => '2']]]]],
    'braces, brackets and escaped quotes inside strings' => ['{"data":{"results":[{"zip":"1","n":"a}b]c{"},{"zip":"2","q":"x\\"]}y\\\\"}]}}', [[2, [['zip' => '1', 'n' => 'a}b]c{'], ['zip' => '2', 'q' => 'x"]}y\\']]]]],
    'an empty string first in a result' => ['{"data":{"results":[{"":"}","zip":"1"}]}}', [[2, [['' => '}', 'zip' => '1']]]]],
    'text between results' => ['{"data":{"results":["a",1,{"zip":"1"},true,{"zip":"2"}]}}', [[2, [['zip' => '1'], ['zip' => '2']]]]],
    'a results object closed by the data object' => ['{"data":{"results":{"zip":"1"}},"x":{"y":{"zip":"2"}}}', [[2, [['zip' => '1']]]]],
    'objects after the results array' => ['{"data":{"results":[{"zip":"1"}],"next":{"zip":"x"}},"more":[{"zip":"y"}]}', [[2, [['zip' => '1']]]]],
    'an array before the results' => ['{"meta":["a"],"data":{"results":[{"zip":"1"}]}}', [[2, [['zip' => '1']]]]],
    'a results value that is not an array' => ['{"data":{"results":"none"}}', []],
    'a long prefix before the results' => ['{"pad":"'.str_repeat('x', 12000).'","data":{"results":[{"zip":"9"}]}}', [[2, [['zip' => '9']]]]],
]);

it('reports failure when the response file cannot be opened', function (): void {
    $method = new ReflectionMethod(ZipCodeEnumGenerator::class, 'parseLargeJsonFileInChunks');
    // Let fopen() fail quietly, as it does outside the test error handler
    set_error_handler(static fn (): bool => true, E_WARNING);

    try {
        $result = $method->invoke(new ZipCodeEnumGenerator, sys_get_temp_dir().'/zip-code-test-missing.json', static fn (): bool => true);
    } finally {
        restore_error_handler();
    }

    expect($result)->toBeFalse();
});

/**
 * A stand-in for GeneratorUtils::downloadToFile() that writes a HUD response per state.
 *
 * A state maps to its result rows, to false for a failed download, or to an exception to throw.
 * Unlisted states answer with no results.
 *
 * @param  array<string, list<mixed>|false|Throwable>  $byState
 * @param  list<array{url: string, path: string, headers: array<string, string>, timeout: int}>  $calls
 */
function zipCodeHudDownloader(array $byState, array &$calls): Closure
{
    return static function (string $url, string $path, array $headers, int $timeout) use ($byState, &$calls): bool {
        $calls[] = ['url' => $url, 'path' => $path, 'headers' => $headers, 'timeout' => $timeout];
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $state = $query['query'] ?? '';
        $answer = \is_string($state) ? ($byState[$state] ?? []) : [];

        if ($answer instanceof Throwable) {
            throw $answer;
        }

        if ($answer === false) {
            return false;
        }

        file_put_contents($path, json_encode(['data' => ['year' => '2024', 'results' => $answer]]));

        return true;
    };
}

it('fetches every state from HUD, then dedupes, sorts and caches the ZIP records', function (): void {
    config(['services.hud.usps_api_token' => 'secret-token']);
    $calls = [];
    $pauses = [];
    $generator = new ZipCodeEnumGenerator(
        zipCodeHudDownloader([
            'AL' => [
                ['zip' => '35004', 'city' => 'Moody', 'state' => 'AL', 'geoid' => '01115'],
                ['zip' => '35004', 'city' => 'Moody', 'state' => 'AL', 'geoid' => '01073'],
                ['zip' => '35004', 'city' => 'Moody', 'state' => 'AL', 'geoid' => '01115'],
                ['zip' => '30000', 'city' => 'Border', 'state' => 'AL', 'geoid' => 1234],
            ],
            'AK' => [['zip' => '30000', 'city' => 'Elsewhere', 'state' => 'AK', 'geoid' => '02020']],
            'AZ' => false,
            'AR' => new RuntimeException('connection reset'),
            'CO' => [
                ['zip' => '80001', 'geoid' => '7'],
                ['zip' => '0', 'geoid' => '08001'],
                ['zip' => '', 'geoid' => '08001'],
                ['city' => 'No zip', 'geoid' => '08001'],
                ['zip' => '80002'],
            ],
        ], $calls),
        static function (int $microseconds) use (&$pauses): void {
            $pauses[] = $microseconds;
        }
    );
    $written = zipCodeCapturedWrite();

    $generator->generate();

    expect(cache()->get('hud_zip_comprehensive_data'))->toBe([
        ['zip' => '30000', 'city' => 'Border', 'state' => 'AL', 'state_geoId' => '12', 'county_geoId' => ['1234']],
        ['zip' => '35004', 'city' => 'Moody', 'state' => 'AL', 'state_geoId' => '01', 'county_geoId' => ['01115', '01073']],
        ['zip' => '80001', 'city' => '', 'state' => '', 'state_geoId' => '', 'county_geoId' => ['7']],
        ['zip' => '80002', 'city' => '', 'state' => '', 'state_geoId' => '', 'county_geoId' => []],
    ])
        ->and(\count($calls))->toBe(56)
        ->and($calls[0]['url'])->toBe('https://www.huduser.gov/hudapi/public/usps?type=2&query=AL')
        ->and($calls[55]['url'])->toBe('https://www.huduser.gov/hudapi/public/usps?type=2&query=VI')
        ->and($calls[0]['headers'])->toBe(['Authorization' => 'Bearer secret-token', 'Accept' => 'application/json'])
        ->and($calls[0]['timeout'])->toBe(60)
        // Windows tempnam() keeps only the first three characters of a prefix.
        ->and(basename($calls[2]['path']))->toStartWith(PHP_OS_FAMILY === 'Windows' ? 'hud' : 'hud_api_2_AZ_')
        ->and(dirname($calls[2]['path']))->toBe(dirname((string) tempnam(sys_get_temp_dir(), 'probe')))
        ->and(array_filter(array_column($calls, 'path'), 'file_exists'))->toBe([])
        ->and($pauses)->toBe(array_merge(...array_fill(0, 56, [50000, 100000])))
        ->and($written->php)->toContain('Total ZIP records: 4');
});

it('stops after the state that reaches the limit, and keeps only that many records', function (int $limit, string $cacheKey, int $requests, array $zips): void {
    config(['services.hud.usps_api_token' => 'secret-token']);
    $calls = [];
    $generator = new ZipCodeEnumGenerator(
        zipCodeHudDownloader([
            'AL' => [['zip' => '35004', 'geoid' => '01115']],
            'AK' => [['zip' => '99501', 'geoid' => '02020'], ['zip' => '99502', 'geoid' => '02020']],
        ], $calls),
        static function (int $microseconds): void {}
    );
    zipCodeCapturedWrite();

    $generator->generate($limit);
    /** @var list<array{zip: string}> $cached */
    $cached = cache()->get($cacheKey);

    expect(\count($calls))->toBe($requests)
        ->and(array_column($cached, 'zip'))->toBe($zips);
})->with([
    'reached by the first state' => [1, 'hud_zip_comprehensive_data_limit_1', 1, ['35004']],
    'reached part way through the second state' => [2, 'hud_zip_comprehensive_data_limit_2', 2, ['35004', '99501']],
    // A limit of 0 is falsy, so it shares the no-limit cache key, yet still stops after one state
    'zero' => [0, 'hud_zip_comprehensive_data', 1, []],
]);

it('renders the whole generated file, one attribute line and case per row', function (): void {
    $php = (new ZipCodeEnumGenerator)->generatePhpContent([
        ['attribute' => '#[First]', 'case' => "case ZIP_1 = 'ZIP_1';"],
        ['attribute' => '#[Second]', 'case' => "case ZIP_2 = 'ZIP_2';"],
    ]);

    // The timestamp is the one line that changes from run to run
    $php = (string) preg_replace(
        '/^ \* This list of cases was generated on \d{4}-\d{2}-\d{2} at \d{2}:\d{2}:\d{2} \S+$/m',
        ' * This list of cases was generated on <when>',
        $php
    );

    expect($php)->toBe(implode("\n", [
        '<?php',
        '',
        '/**',
        ' * Generated US ZIP codes enum cases',
        ' *',
        ' * INSTRUCTIONS:',
        ' * 1. Copy all the case statements below (from the first case to the',
        ' *    closing brace)',
        ' * 2. Open app/Enums/ZipCode.php',
        ' * 3. Replace the existing case list in the ZipCode enum with the',
        ' *    copied cases',
        ' * 4. Keep the ZipCodeInfo attribute class and any other methods in',
        ' *    ZipCode.php',
        ' * 5. Save the file',
        ' *',
        ' * Data Source:',
        ' * - HUD USPS Crosswalk API: https://www.huduser.gov/portal/dataset/uspszip-api.html',
        ' *',
        ' * This list of cases was generated on <when>',
        ' * Total ZIP records: 2',
        ' */',
        '',
        'enum ZipCode: string',
        '{',
        "\t#[First]",
        "\tcase ZIP_1 = 'ZIP_1';",
        '',
        "\t#[Second]",
        "\tcase ZIP_2 = 'ZIP_2';",
        '',
        '}',
        '',
    ]));
});

it('prefers the HUD values, falls back to the original record, then to empty', function (): void {
    $generator = new ZipCodeEnumGenerator;

    // HUD values win over the original record
    expect($generator->processZipRecordWithHudData(
        ['zip_code' => '00000', 'usps_city' => 'Old', 'stusps_code' => 'XX'],
        ['zip' => '72205', 'city' => 'Little Rock', 'state' => 'AR', 'state_geoId' => '05', 'county_geoId' => ['05119', '05125']],
    ))->toBe([
        'attribute' => "#[ZipCodeInfo(zip_code: '72205', city: 'Little Rock', state_usps: 'AR', state_geoId: '05', county_geoId: ['05119', '05125'])]",
        'case' => "case ZIP_72205 = 'ZIP_72205';",
    ]);

    // The original record fills in what HUD leaves out
    expect($generator->processZipRecordWithHudData(
        ['zip_code' => '63366', 'usps_city' => "O'Fallon", 'stusps_code' => 'MO'],
        [],
    ))->toBe([
        'attribute' => "#[ZipCodeInfo(zip_code: '63366', city: 'O\\'Fallon', state_usps: 'MO', state_geoId: '', county_geoId: [])]",
        'case' => "case ZIP_63366 = 'ZIP_63366';",
    ]);

    // Nothing given
    expect($generator->processZipRecordWithHudData([], []))->toBe([
        'attribute' => "#[ZipCodeInfo(zip_code: '', city: '', state_usps: '', state_geoId: '', county_geoId: [])]",
        'case' => "case ZIP_ = 'ZIP_';",
    ]);

    // Values that are not text
    expect($generator->processZipRecordWithHudData(
        [],
        ['zip' => ['72205'], 'city' => ['Little Rock'], 'state' => ['AR'], 'state_geoId' => ['05'], 'county_geoId' => '05119'],
    ))->toBe([
        'attribute' => "#[ZipCodeInfo(zip_code: '', city: '', state_usps: '', state_geoId: '', county_geoId: [])]",
        'case' => "case ZIP_ = 'ZIP_';",
    ]);

    // JSON numbers arrive as numbers; a county entry that is not scalar renders empty
    expect($generator->processZipRecordWithHudData(
        [],
        ['zip' => 72205, 'city' => 123, 'state' => 4, 'state_geoId' => 5, 'county_geoId' => [5119, null, ['x']]],
    ))->toBe([
        'attribute' => "#[ZipCodeInfo(zip_code: '72205', city: '123', state_usps: '4', state_geoId: '5', county_geoId: ['5119', '', ''])]",
        'case' => "case ZIP_72205 = 'ZIP_72205';",
    ]);
});

it('reads the state and county GEOIDs from each HUD result the way it does today', function (): void {
    config(['services.hud.usps_api_token' => 'secret-token']);
    $calls = [];
    $generator = new ZipCodeEnumGenerator(
        zipCodeHudDownloader([
            'AL' => [
                ['zip' => '35004', 'city' => 'Moody', 'state' => 'AL', 'geoid' => '01'],
                ['zip' => '35005', 'geoid' => '1'],
                ['zip' => '35007'],
                // Two numeric ZIPs stay apart when deduped, though they share a whole part
                ['zip' => 1.5, 'geoid' => '01001'],
                ['zip' => 1.7, 'geoid' => '01002'],
                // Last, since an array inside a result ends the read
                ['zip' => '35006', 'geoid' => ['x']],
            ],
        ], $calls),
        static function (int $microseconds): void {}
    );
    zipCodeCapturedWrite();

    $generator->generate();

    expect(cache()->get('hud_zip_comprehensive_data'))->toBe([
        ['zip' => 1.5, 'city' => '', 'state' => '', 'state_geoId' => '01', 'county_geoId' => ['01001']],
        ['zip' => 1.7, 'city' => '', 'state' => '', 'state_geoId' => '01', 'county_geoId' => ['01002']],
        // A two-character GEOID is the state code itself
        ['zip' => '35004', 'city' => 'Moody', 'state' => 'AL', 'state_geoId' => '01', 'county_geoId' => ['01']],
        // A one-character GEOID is too short for a state code
        ['zip' => '35005', 'city' => '', 'state' => '', 'state_geoId' => '', 'county_geoId' => ['1']],
        // A GEOID that is not scalar adds an empty county
        ['zip' => '35006', 'city' => '', 'state' => '', 'state_geoId' => '', 'county_geoId' => ['']],
        // No GEOID adds no county
        ['zip' => '35007', 'city' => '', 'state' => '', 'state_geoId' => '', 'county_geoId' => []],
    ]);
});

it('carries on when a failed download has already removed its temporary file', function (): void {
    config(['services.hud.usps_api_token' => 'secret-token']);
    $calls = 0;
    $generator = new ZipCodeEnumGenerator(
        static function (string $url, string $path, array $headers, int $timeout) use (&$calls): bool {
            $calls++;

            if (str_ends_with($url, 'query=AL')) {
                unlink($path);

                throw new RuntimeException('connection reset');
            }

            file_put_contents($path, '{"data":{"results":[]}}');

            return true;
        },
        static function (int $microseconds): void {}
    );
    $written = zipCodeCapturedWrite();

    $generator->generate();

    expect($calls)->toBe(56)
        ->and($written->php)->toContain('Total ZIP records: 0');
});

it('keeps the fetched HUD records for 24 hours', function (): void {
    config(['services.hud.usps_api_token' => 'secret-token']);
    $calls = [];
    $generator = new ZipCodeEnumGenerator(
        zipCodeHudDownloader(['AL' => [['zip' => '35004', 'geoid' => '01115']]], $calls),
        static function (int $microseconds): void {}
    );
    zipCodeCapturedWrite();
    $this->freezeSecond();
    $fetchedAt = now();

    $generator->generate(1);

    $this->travelTo($fetchedAt->copy()->addHours(24)->subSecond());
    expect(cache()->has('hud_zip_comprehensive_data_limit_1'))->toBeTrue();

    $this->travelTo($fetchedAt->copy()->addHours(24));
    expect(cache()->has('hud_zip_comprehensive_data_limit_1'))->toBeFalse();
});

it('only reads results inside the data object', function (): void {
    expect(zipCodeParsedChunks('{"results":[{"zip":"1"}]}', 10))->toBe([true, []])
        ->and(zipCodeParsedChunks('{"zip":"1"}', 10))->toBe([true, []]);
});

it('finds the results however far they sit from the data key', function (int $gap): void {
    // The search buffer is cut to its last 5,000 characters once it passes 10,000
    $json = '{"data":{"p":"'.str_repeat('b', $gap).'","results":[{"zip":"1"}]}}';

    expect(zipCodeParsedChunks($json, 10))->toBe([true, [[2, [['zip' => '1']]]]]);
})->with([
    'short' => [10],
    'past the buffer' => [12000],
]);
