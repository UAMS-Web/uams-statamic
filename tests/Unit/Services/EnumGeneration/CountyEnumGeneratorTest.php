<?php

declare(strict_types=1);

use App\Services\EnumGeneration\County\CountyEnumGenerator;
use Symfony\Component\Process\Process;

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
