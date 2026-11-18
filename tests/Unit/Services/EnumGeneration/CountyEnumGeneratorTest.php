<?php

declare(strict_types=1);

use App\Services\EnumGeneration\County\CountyEnumGenerator;
use Symfony\Component\Process\Process;

covers(CountyEnumGenerator::class);

/**
 * Unit tests for the extracted {@see CountyEnumGenerator} (UAMS-Web/uams-statamic-archive#876).
 *
 * Exercises the deterministic merge, case-id generation, record normalization,
 * and PHP rendering directly on the service.
 *
 * @command  php artisan test --compact tests/Unit/Services/EnumGeneration/CountyEnumGeneratorTest.php
 */
it('appends only layer 55 counties missing from layer 1', function () {
    $layer1 = [
        ['GEOID' => '05119', 'NAME' => 'Pulaski County'],
        ['GEOID' => '05125', 'NAME' => 'Saline County'],
    ];
    $layer55 = [
        ['GEOID' => '05119', 'NAME' => 'Pulaski County (dup)'],
        ['GEOID' => '99999', 'NAME' => 'Phantom County'],
        ['NAME' => 'No GeoId County'],
    ];

    $merged = (new CountyEnumGenerator)->mergeSupplementalRecords($layer1, $layer55);

    // Only the new geoId (99999) is appended; dup + no-geoId are dropped
    expect($merged)->toHaveCount(3);
    expect(array_column($merged, 'GEOID'))->toBe(['05119', '05125', '99999']);
});

it('builds a STATE_UPPER_SNAKE case id, stripping apostrophes and punctuation', function () {
    $generator = new CountyEnumGenerator;

    expect($generator->generateCaseId('AR', 'Pulaski County'))->toBe('AR_PULASKI_COUNTY');
    expect($generator->generateCaseId('LA', 'St. John the Baptist Parish'))->toBe('LA_ST_JOHN_THE_BAPTIST_PARISH');
    expect($generator->generateCaseId('MD', "Prince George's County"))->toBe('MD_PRINCE_GEORGES_COUNTY');
});

it('normalizes records, maps parent USPS from FIPS, and sorts by state then name', function () {
    $records = [
        ['STATE' => '05', 'COUNTY' => '125', 'NAME' => 'Saline County', 'BASENAME' => 'Saline', 'GEOID' => '05125'],
        ['STATE' => '05', 'COUNTY' => '119', 'NAME' => 'Pulaski County', 'BASENAME' => 'Pulaski', 'GEOID' => '05119'],
        ['STATE' => '01', 'COUNTY' => '001', 'NAME' => 'Autauga County', 'BASENAME' => 'Autauga', 'GEOID' => '01001'],
    ];

    $counties = (new CountyEnumGenerator)->buildCountiesFromRecords($records);

    // Sorted by parent USPS (AL before AR) then county full name
    expect(array_column($counties, 'caseId'))->toBe([
        'AL_AUTAUGA_COUNTY',
        'AR_PULASKI_COUNTY',
        'AR_SALINE_COUNTY',
    ]);

    // FIPS 05 maps to USPS AR via the State enum reflection
    $arPulaski = mustExist(collect($counties)->firstWhere('caseId', 'AR_PULASKI_COUNTY'), 'ar pulaski');
    expect($arPulaski['parentUspsCode'])->toBe('AR');
    expect($arPulaski['parentFipsCode'])->toBe('05');
    expect($arPulaski['fipsCode'])->toBe('119');
});

it('renders the County enum PHP with attributes, cases, and header', function () {
    $counties = [[
        'fullName' => 'Pulaski County',
        'baseName' => 'Pulaski',
        'fipsCode' => '119',
        'parentUspsCode' => 'AR',
        'parentFipsCode' => '05',
        'geoId' => '05119',
        'nationalStandardCode' => '00069902',
        'fipsClassCode' => 'H1',
        'functionalStatusCode' => 'A',
        'mafTigerFeatureClassCode' => 'G4020',
        'mafTigerObjectIdentifier' => '1',
        'legalStatisticalAreaDescriptionCode' => '06',
        'areaLand' => '1',
        'areaWater' => '2',
        'arcGisObjectIdentifier' => '3',
        'centroidGeo' => ['34.7', '-92.3'],
        'internalPointGeo' => ['34.8', '-92.4'],
        'caseId' => 'AR_PULASKI_COUNTY',
    ]];

    $php = (new CountyEnumGenerator)->generatePhpContent($counties, ['Example Source Line']);

    expect($php)->toContain('enum County: string');
    expect($php)->toContain("case AR_PULASKI_COUNTY = 'AR_PULASKI_COUNTY';");
    expect($php)->toContain("fullName: 'Pulaski County'");
    expect($php)->toContain("parentUspsCode: 'AR'");
    expect($php)->toContain("centroidGeo: ['34.7', '-92.3']");
    expect($php)->toContain('* - Example Source Line');
    expect($php)->toContain('Open app/Enums/County.php');
});

it('escapes single quotes in rendered county names', function () {
    $counties = [[
        'fullName' => "Prince George's County",
        'baseName' => "Prince George's",
        'fipsCode' => '033',
        'parentUspsCode' => 'MD',
        'parentFipsCode' => '24',
        'geoId' => '24033',
        'nationalStandardCode' => '',
        'fipsClassCode' => '',
        'functionalStatusCode' => '',
        'mafTigerFeatureClassCode' => '',
        'mafTigerObjectIdentifier' => '',
        'legalStatisticalAreaDescriptionCode' => '',
        'areaLand' => '',
        'areaWater' => '',
        'arcGisObjectIdentifier' => '',
        'centroidGeo' => ['', ''],
        'internalPointGeo' => ['', ''],
        'caseId' => 'MD_PRINCE_GEORGES_COUNTY',
    ]];

    $php = (new CountyEnumGenerator)->generatePhpContent($counties);

    expect($php)->toContain("fullName: 'Prince George\\'s County'");
});

/**
 * One county row for the rendering tests, with every attribute set to `$name`'s
 * shape so a literal escaping defect shows up in the output.
 *
 * @return array{fullName: string, baseName: string, fipsCode: string, parentUspsCode: string, parentFipsCode: string, geoId: string, nationalStandardCode: string, fipsClassCode: string, functionalStatusCode: string, mafTigerFeatureClassCode: string, mafTigerObjectIdentifier: string, legalStatisticalAreaDescriptionCode: string, areaLand: string, areaWater: string, arcGisObjectIdentifier: string, centroidGeo: array{string, string}, internalPointGeo: array{string, string}, caseId: string}
 */
function countyRenderRow(string $name, string $caseId): array
{
    return [
        'fullName' => $name,
        'baseName' => $name,
        'fipsCode' => '119',
        'parentUspsCode' => 'AR',
        'parentFipsCode' => '05',
        'geoId' => '05119',
        'nationalStandardCode' => '00069902',
        'fipsClassCode' => 'H1',
        'functionalStatusCode' => 'A',
        'mafTigerFeatureClassCode' => 'G4020',
        'mafTigerObjectIdentifier' => '1',
        'legalStatisticalAreaDescriptionCode' => '06',
        'areaLand' => '1',
        'areaWater' => '2',
        'arcGisObjectIdentifier' => '3',
        'centroidGeo' => ['34.7', '-92.3'],
        'internalPointGeo' => ['34.8', '-92.4'],
        'caseId' => $caseId,
    ];
}

it('renders output that Pint reports clean with no separate pass (#716)', function () {
    $php = (new CountyEnumGenerator)->generatePhpContent([
        countyRenderRow('Pulaski County', 'AR_PULASKI_COUNTY'),
        countyRenderRow("Prince George's County", 'MD_PRINCE_GEORGES_COUNTY'),
        countyRenderRow('Trailing\\', 'AR_TRAILING'),
    ], ['Example Source Line']);

    $root = dirname(__DIR__, 4);
    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'county-enum-'.bin2hex(random_bytes(6)).'.php';
    file_put_contents($path, $php);

    try {
        $process = new Process([
            PHP_BINARY,
            $root.'/vendor/bin/pint',
            '--test',
            '--config',
            $root.'/pint.json',
            $path,
        ]);
        $process->run();
    } finally {
        @unlink($path);
    }

    expect($process->getExitCode())->toBe(0, $process->getOutput().$process->getErrorOutput());
});

it('writes a fullName literal that evaluates back to the value (#716)', function (string $value) {
    $php = (new CountyEnumGenerator)->generatePhpContent([countyRenderRow($value, 'AR_CASE')]);

    // The first string literal in the file is the fullName argument; the docblock is a comment token
    $literal = null;
    foreach (token_get_all($php) as $token) {
        if (is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING) {
            $literal = $token[1];
            break;
        }
    }

    expect($literal)->not->toBeNull()
        ->and(mustBeString(eval('return '.$literal.';'), 'evaluated fullName literal'))->toBe($value);
})->with([
    'plain' => ['Pulaski County'],
    'apostrophe' => ["Prince George's County"],
    'trailing backslash' => ['end\\'],
    'doubled backslash' => ['a\\\\b'],
    'backslash before apostrophe' => ['x\\\'y'],
    'lone backslash' => ['\\'],
]);

it('keeps appending layer 55 counties after one without a geoId (#887)', function () {
    $merged = (new CountyEnumGenerator)->mergeSupplementalRecords(
        [['GEOID' => '05119', 'NAME' => 'Pulaski County'], ['NAME' => 'No GeoId in layer 1']],
        [['NAME' => 'No GeoId County'], ['GEOID' => '05125', 'NAME' => 'Saline County']]
    );

    expect(array_column($merged, 'NAME'))->toBe(['Pulaski County', 'No GeoId in layer 1', 'Saline County']);
});

it('trims the underscores punctuation leaves at either end of a case id (#887)', function () {
    expect((new CountyEnumGenerator)->generateCaseId('AR', '(Pulaski County)'))->toBe('AR_PULASKI_COUNTY');
});

it('maps every TIGERweb field onto its row, casting numbers to strings (#887)', function () {
    // TIGERweb's JSON carries the codes as strings and the measures as numbers
    /** @var array<int, array<string, string>> $records */
    $records = json_decode(<<<'JSON'
        [{
            "STATE": "05", "COUNTY": "119", "COUNTYNS": "00069902", "NAME": "Pulaski County",
            "COUNTYCC": "H1", "FUNCSTAT": "A", "GEOID": "05119", "MTFCC": "G4020",
            "OID": 27590204313040, "BASENAME": "Pulaski", "LSADC": "06",
            "AREALAND": 1959383218, "AREAWATER": 71263449, "OBJECTID": 512,
            "CENTLAT": "+34.7699806", "CENTLON": "-092.3106429",
            "INTPTLAT": "+34.7709447", "INTPTLON": "-092.3112768"
        }]
        JSON, true, flags: JSON_THROW_ON_ERROR);

    expect((new CountyEnumGenerator)->buildCountiesFromRecords($records))->toBe([[
        'fullName' => 'Pulaski County',
        'baseName' => 'Pulaski',
        'fipsCode' => '119',
        'parentUspsCode' => 'AR',
        'parentFipsCode' => '05',
        'geoId' => '05119',
        'nationalStandardCode' => '00069902',
        'fipsClassCode' => 'H1',
        'functionalStatusCode' => 'A',
        'mafTigerFeatureClassCode' => 'G4020',
        'mafTigerObjectIdentifier' => '27590204313040',
        'legalStatisticalAreaDescriptionCode' => '06',
        'areaLand' => '1959383218',
        'areaWater' => '71263449',
        'arcGisObjectIdentifier' => '512',
        'centroidGeo' => ['+34.7699806', '-092.3106429'],
        'internalPointGeo' => ['+34.7709447', '-092.3112768'],
        'caseId' => 'AR_PULASKI_COUNTY',
    ]]);
});

it('casts every numeric field to a string, before building the case id (#887)', function () {
    /** @var array<int, array<string, string>> $records */
    $records = json_decode(<<<'JSON'
        [{
            "STATE": 99, "COUNTY": 119, "COUNTYNS": 69902, "NAME": 1234, "COUNTYCC": 11,
            "FUNCSTAT": 1, "GEOID": 5119, "MTFCC": 4020, "OID": 27, "BASENAME": 56,
            "LSADC": 6, "AREALAND": 7, "AREAWATER": 8, "OBJECTID": 9,
            "CENTLAT": 34.5, "CENTLON": -92.25, "INTPTLAT": 35.5, "INTPTLON": -93.25
        }]
        JSON, true, flags: JSON_THROW_ON_ERROR);

    expect((new CountyEnumGenerator)->buildCountiesFromRecords($records))->toBe([[
        'fullName' => '1234',
        'baseName' => '56',
        'fipsCode' => '119',
        'parentUspsCode' => '',
        'parentFipsCode' => '99',
        'geoId' => '5119',
        'nationalStandardCode' => '69902',
        'fipsClassCode' => '11',
        'functionalStatusCode' => '1',
        'mafTigerFeatureClassCode' => '4020',
        'mafTigerObjectIdentifier' => '27',
        'legalStatisticalAreaDescriptionCode' => '6',
        'areaLand' => '7',
        'areaWater' => '8',
        'arcGisObjectIdentifier' => '9',
        'centroidGeo' => ['34.5', '-92.25'],
        'internalPointGeo' => ['35.5', '-93.25'],
        'caseId' => '99_1234',
    ]]);
});

it('fills every missing field with an empty string (#887)', function () {
    expect((new CountyEnumGenerator)->buildCountiesFromRecords([[]]))->toBe([[
        'fullName' => '',
        'baseName' => '',
        'fipsCode' => '',
        'parentUspsCode' => '',
        'parentFipsCode' => '',
        'geoId' => '',
        'nationalStandardCode' => '',
        'fipsClassCode' => '',
        'functionalStatusCode' => '',
        'mafTigerFeatureClassCode' => '',
        'mafTigerObjectIdentifier' => '',
        'legalStatisticalAreaDescriptionCode' => '',
        'areaLand' => '',
        'areaWater' => '',
        'arcGisObjectIdentifier' => '',
        'centroidGeo' => ['', ''],
        'internalPointGeo' => ['', ''],
        'caseId' => '_',
    ]]);
});

it('keeps the first record when two produce the same case id (#887)', function () {
    $counties = (new CountyEnumGenerator)->buildCountiesFromRecords([
        ['STATE' => '05', 'NAME' => 'Pulaski County', 'GEOID' => 'first'],
        ['STATE' => '05', 'NAME' => 'Pulaski County', 'GEOID' => 'second'],
    ]);

    expect(array_column($counties, 'geoId'))->toBe(['first']);
});

it('renders the docblock header line for line, with each source line right-trimmed (#887)', function () {
    $php = (new CountyEnumGenerator)->generatePhpContent(
        [countyRenderRow('Pulaski County', 'AR_PULASKI'), countyRenderRow('Saline County', 'AR_SALINE')],
        ['First source line   ', '  Indented second line']
    );

    $lines = explode("\n", $php);

    expect(array_slice($lines, 0, 26))->toBe([
        '<?php',
        '',
        'declare(strict_types=1);',
        '',
        '/**',
        ' * Generated County and County Equivalent Entities enum cases',
        ' *',
        ' * INSTRUCTIONS:',
        ' * 1. Copy all the case statements below (from the first case to the',
        ' *    closing brace)',
        ' * 2. Open app/Enums/County.php',
        ' * 3. Replace the existing case list in the County enum with the',
        ' *    copied cases',
        ' * 4. Keep any other methods in County.php; the CountyInfo attribute',
        ' *    class is in CountyInfo.php',
        ' * 5. Save the file',
        ' *',
        ' * Data Source:',
        ' * - First source line',
        ' * -   Indented second line',
        ' *',
        $lines[21],
        ' * Total counties: 2',
        ' */',
        'enum County: string',
        '{',
    ])
        ->and($lines[21])->toMatch('/^ \* This list of cases was generated on \d{4}-\d{2}-\d{2} at \d{2}:\d{2}:\d{2} \S+$/');
});

it('renders each attribute in order on one line, then its case (#887)', function () {
    $php = (new CountyEnumGenerator)->generatePhpContent([[
        'fullName' => 'Full',
        'baseName' => 'Base',
        'fipsCode' => 'Fips',
        'parentUspsCode' => 'Usps',
        'parentFipsCode' => 'ParentFips',
        'geoId' => 'Geo',
        'nationalStandardCode' => 'Ns',
        'fipsClassCode' => 'Cc',
        'functionalStatusCode' => 'Fs',
        'mafTigerFeatureClassCode' => 'Mtfcc',
        'mafTigerObjectIdentifier' => 'Oid',
        'legalStatisticalAreaDescriptionCode' => 'Lsad',
        'areaLand' => 'Land',
        'areaWater' => 'Water',
        'arcGisObjectIdentifier' => 'ObjectId',
        'centroidGeo' => ['CentLat', 'CentLon'],
        'internalPointGeo' => ['PointLat', 'PointLon'],
        'caseId' => 'XX_FULL',
    ]]);

    $lines = explode("\n", $php);
    $brace = array_search('{', $lines, true);
    $body = array_slice($lines, (int) $brace + 1);

    expect($brace)->toBeInt()
        ->and($lines)->toContain(' * - TIGERweb Counties API')
        ->and($body)->toBe([
            "    #[CountyInfo(fullName: 'Full', baseName: 'Base', fipsCode: 'Fips', parentUspsCode: 'Usps', parentFipsCode: 'ParentFips', geoId: 'Geo', nationalStandardCode: 'Ns', fipsClassCode: 'Cc', functionalStatusCode: 'Fs', mafTigerFeatureClassCode: 'Mtfcc', mafTigerObjectIdentifier: 'Oid', legalStatisticalAreaDescriptionCode: 'Lsad', areaLand: 'Land', areaWater: 'Water', arcGisObjectIdentifier: 'ObjectId', centroidGeo: ['CentLat', 'CentLon'], internalPointGeo: ['PointLat', 'PointLon'])]",
            "    case XX_FULL = 'XX_FULL';",
            '',
            '}',
            '',
        ]);
});
