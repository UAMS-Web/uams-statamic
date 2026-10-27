<?php

declare(strict_types=1);

use App\Services\EnumGeneration\State\StateEnumGenerator;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Unit tests for the extracted {@see StateEnumGenerator} (UAMS-Web/uams-statamic-archive#876).
 *
 * Exercises the record normalization and PHP rendering directly on the
 * service, and generate() with the TIGERweb fetch replaced by a stand-in.
 *
 * @command  php artisan test --compact tests/Unit/Services/EnumGeneration/StateEnumGeneratorTest.php
 */
it('normalizes, maps, and sorts TIGERweb state records', function () {
    $records = [
        // Out of order to prove the USPS sort; territory + district to prove type map
        ['STUSAB' => 'AR', 'NAME' => 'Arkansas', 'BASENAME' => 'Arkansas', 'STATE' => '05', 'GEOID' => '05'],
        ['STUSAB' => 'AL', 'NAME' => 'Alabama', 'BASENAME' => 'Alabama', 'STATE' => '01', 'GEOID' => '01'],
        ['STUSAB' => 'DC', 'NAME' => 'District of Columbia', 'BASENAME' => 'District of Columbia', 'STATE' => '11', 'GEOID' => '11'],
        ['STUSAB' => 'PR', 'NAME' => 'Puerto Rico', 'BASENAME' => 'Puerto Rico', 'STATE' => '72', 'GEOID' => '72'],
        // Incomplete row (no USPS) must be skipped
        ['NAME' => 'Nowhere', 'STATE' => '99'],
    ];

    $states = (new StateEnumGenerator)->buildStatesFromRecords($records);

    // Incomplete row dropped, then sorted by USPS case id
    expect(array_column($states, 'caseId'))->toBe(['AL', 'AR', 'DC', 'PR']);

    // AP abbreviation, type, and ISO mappings resolve from the static maps
    $byId = collect($states)->keyBy('caseId');

    $ar = mustBeArray($byId['AR'], 'ar');
    expect($ar['apAbbreviation'])->toBe('Ark.');
    expect($ar['type'])->toBe('state');
    expect($ar['iso3166_2'])->toBe('US-AR');
    expect($ar['fipsCode'])->toBe('05');

    $dc = mustBeArray($byId['DC'], 'dc');
    expect($dc['type'])->toBe('district');

    $pr = mustBeArray($byId['PR'], 'pr');
    expect($pr['type'])->toBe('territory');
});

it('deduplicates repeated USPS codes', function () {
    $records = [
        ['STUSAB' => 'AR', 'NAME' => 'Arkansas', 'BASENAME' => 'Arkansas', 'STATE' => '05', 'GEOID' => '05'],
        ['STUSAB' => 'AR', 'NAME' => 'Arkansas (dup)', 'BASENAME' => 'Arkansas', 'STATE' => '05', 'GEOID' => '05'],
    ];

    $states = (new StateEnumGenerator)->buildStatesFromRecords($records);

    // Only the first occurrence of a USPS code is kept
    expect($states)->toHaveCount(1);
    expect($states[0]['fullName'])->toBe('Arkansas');
});

it('renders the State enum PHP with attributes, cases, and header', function () {
    $states = [[
        'caseId' => 'AR',
        'fullName' => 'Arkansas',
        'baseName' => 'Arkansas',
        'apAbbreviation' => 'Ark.',
        'type' => 'state',
        'fipsCode' => '05',
        'iso3166_2' => 'US-AR',
        'geoId' => '05',
        'nationalStandardCode' => '00068085',
        'division' => '7',
        'region' => '3',
        'functionalStatusCode' => 'A',
        'mafTigerFeatureClassCode' => 'G4000',
        'mafTigerObjectIdentifier' => '123',
        'legalStatisticalAreaDescriptionCode' => '00',
        'areaLand' => '1',
        'areaWater' => '2',
        'arcGisObjectIdentifier' => '9',
        'centroidGeo' => ['34.0', '-92.0'],
        'internalPointGeo' => ['34.1', '-92.1'],
    ]];

    $php = (new StateEnumGenerator)->generatePhpContent($states, ['Example Source Line']);

    expect($php)->toContain('enum State: string');
    expect($php)->toContain("case AR = 'AR';");
    expect($php)->toContain("apAbbreviation: 'Ark.'");
    expect($php)->toContain("fullName: 'Arkansas'");
    expect($php)->toContain("centroidGeo: ['34.0', '-92.0']");
    expect($php)->toContain('* - Example Source Line');
    expect($php)->toContain('Open app/Enums/State.php');
});

it('escapes single quotes in rendered values', function () {
    $states = [[
        'caseId' => 'XX',
        'fullName' => "O'Hare",
        'baseName' => "O'Hare",
        'apAbbreviation' => 'Test',
        'type' => 'state',
        'fipsCode' => '00',
        'iso3166_2' => 'US-XX',
        'geoId' => '00',
        'nationalStandardCode' => '',
        'division' => '',
        'region' => '',
        'functionalStatusCode' => '',
        'mafTigerFeatureClassCode' => '',
        'mafTigerObjectIdentifier' => '',
        'legalStatisticalAreaDescriptionCode' => '',
        'areaLand' => '',
        'areaWater' => '',
        'arcGisObjectIdentifier' => '',
        'centroidGeo' => ['', ''],
        'internalPointGeo' => ['', ''],
    ]];

    $php = (new StateEnumGenerator)->generatePhpContent($states, []);

    // GeneratorUtils::escapeSingleQuotes backslash-escapes the apostrophe
    expect($php)->toContain("fullName: 'O\\'Hare'");
});

it('names the TIGERweb source in the generated file header', function (): void {
    expect((new ReflectionMethod(StateEnumGenerator::class, 'dataSourceLines'))->invoke(new StateEnumGenerator))->toBe([
        'TIGERweb/State_County (MapServer), States (or statistically equivalent entities):',
        '  - Layer 0 (January 1, 2025 vintage):',
        '    https://tigerweb.geo.census.gov/arcgis/rest/services/TIGERweb/State_County/MapServer/0/query?where=1%3D1&outFields=STUSAB,NAME,BASENAME,STATE,GEOID,STATENS,DIVISION,REGION,FUNCSTAT,MTFCC,OID,LSADC,AREALAND,AREAWATER,OBJECTID,CENTLAT,CENTLON,INTPTLAT,INTPTLON&returnGeometry=false&f=json',
    ]);
});

it('writes the enum from the TIGERweb records for layer 0', function (): void {
    $layers = [];
    $generator = new StateEnumGenerator(static function (int $layer) use (&$layers): array {
        $layers[] = $layer;

        return [['STUSAB' => 'AR', 'NAME' => 'Arkansas', 'BASENAME' => 'Arkansas', 'STATE' => '05', 'GEOID' => '05']];
    });
    $written = (object) ['path' => null, 'php' => null];
    File::partialMock()->shouldReceive('put')->once()->andReturnUsing(static function (string $path, string $php) use ($written): int {
        $written->path = $path;
        $written->php = $php;

        return \strlen($php);
    });

    $path = $generator->generate();

    expect($layers)->toBe([0])
        ->and($path)->toBe($written->path)
        ->and(str_replace('\\', '/', $path))->toMatch('#/app/Enums/State_generated_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.php$#')
        ->and($written->php)->toContain("case AR = 'AR';")
        ->and($written->php)->toContain('TIGERweb/State_County (MapServer), States (or statistically equivalent entities):');
});

it('stops when the TIGERweb fetch fails, before writing anything', function (): void {
    File::partialMock()->shouldReceive('put')->never();

    expect(fn () => (new StateEnumGenerator(static fn (int $layer): ?array => null))->generate())
        ->toThrow(RuntimeException::class, 'Failed to fetch TIGERweb states');
});

it('stores every TIGERweb attribute as a string, whatever JSON type it arrives in', function (): void {
    // TIGERweb sends some attributes as JSON numbers; the record narrowing in
    // TigerWebUtils does not convert them, so the generator must.
    /** @var array<int, array<string, string>> $records */
    $records = \json_decode(
        '[{"STUSAB":12,"NAME":34,"BASENAME":56,"STATE":5,"GEOID":7,"STATENS":68085,"DIVISION":8,"REGION":3,"FUNCSTAT":9,"MTFCC":4000,"OID":274903,"LSADC":0,"AREALAND":134660767709,"AREAWATER":3121950081,"OBJECTID":21,"CENTLAT":34.8955,"CENTLON":-92.4447,"INTPTLAT":34.8957,"INTPTLON":-92.4446}]',
        true,
        flags: JSON_THROW_ON_ERROR
    );

    expect((new StateEnumGenerator)->buildStatesFromRecords($records))->toBe([[
        'caseId' => '12',
        'fullName' => '34',
        'baseName' => '56',
        'apAbbreviation' => '34',
        'type' => 'state',
        'fipsCode' => '5',
        'iso3166_2' => 'US-12',
        'geoId' => '7',
        'nationalStandardCode' => '68085',
        'division' => '8',
        'region' => '3',
        'functionalStatusCode' => '9',
        'mafTigerFeatureClassCode' => '4000',
        'mafTigerObjectIdentifier' => '274903',
        'legalStatisticalAreaDescriptionCode' => '0',
        'areaLand' => '134660767709',
        'areaWater' => '3121950081',
        'arcGisObjectIdentifier' => '21',
        'centroidGeo' => ['34.8955', '-92.4447'],
        'internalPointGeo' => ['34.8957', '-92.4446'],
    ]]);
});

it('leaves an attribute TIGERweb omits empty, and falls back to the name and the state type for an unmapped code', function (): void {
    $states = (new StateEnumGenerator)->buildStatesFromRecords([
        ['STUSAB' => 'ZZ', 'NAME' => 'Atlantis', 'STATE' => '98'],
    ]);

    expect($states)->toBe([[
        'caseId' => 'ZZ',
        'fullName' => 'Atlantis',
        'baseName' => '',
        'apAbbreviation' => 'Atlantis',
        'type' => 'state',
        'fipsCode' => '98',
        'iso3166_2' => 'US-ZZ',
        'geoId' => '',
        'nationalStandardCode' => '',
        'division' => '',
        'region' => '',
        'functionalStatusCode' => '',
        'mafTigerFeatureClassCode' => '',
        'mafTigerObjectIdentifier' => '',
        'legalStatisticalAreaDescriptionCode' => '',
        'areaLand' => '',
        'areaWater' => '',
        'arcGisObjectIdentifier' => '',
        'centroidGeo' => ['', ''],
        'internalPointGeo' => ['', ''],
    ]]);
});

it('carries every TIGERweb attribute it is given into the row', function (): void {
    $states = (new StateEnumGenerator)->buildStatesFromRecords([[
        'STUSAB' => 'AR', 'NAME' => 'Arkansas', 'BASENAME' => 'Arkansas Base', 'STATE' => '05', 'GEOID' => '0500',
        'STATENS' => '00068085', 'DIVISION' => '7', 'REGION' => '3', 'FUNCSTAT' => 'A', 'MTFCC' => 'G4000',
        'OID' => '27490330474416', 'LSADC' => '00', 'AREALAND' => '134660767709', 'AREAWATER' => '3121950081',
        'OBJECTID' => '12', 'CENTLAT' => '+34.8955', 'CENTLON' => '-092.4447', 'INTPTLAT' => '+34.8957', 'INTPTLON' => '-092.4446',
    ]]);

    expect($states)->toBe([[
        'caseId' => 'AR',
        'fullName' => 'Arkansas',
        'baseName' => 'Arkansas Base',
        'apAbbreviation' => 'Ark.',
        'type' => 'state',
        'fipsCode' => '05',
        'iso3166_2' => 'US-AR',
        'geoId' => '0500',
        'nationalStandardCode' => '00068085',
        'division' => '7',
        'region' => '3',
        'functionalStatusCode' => 'A',
        'mafTigerFeatureClassCode' => 'G4000',
        'mafTigerObjectIdentifier' => '27490330474416',
        'legalStatisticalAreaDescriptionCode' => '00',
        'areaLand' => '134660767709',
        'areaWater' => '3121950081',
        'arcGisObjectIdentifier' => '12',
        'centroidGeo' => ['+34.8955', '-092.4447'],
        'internalPointGeo' => ['+34.8957', '-092.4446'],
    ]]);
});

it('skips a row missing its USPS code, name or FIPS code, and keeps reading after it', function (): void {
    $states = (new StateEnumGenerator)->buildStatesFromRecords([
        ['NAME' => 'No Code', 'STATE' => '97'],
        ['STUSAB' => 'AL', 'STATE' => '01'],
        ['STUSAB' => 'AK', 'NAME' => 'Alaska'],
        ['STUSAB' => 'AZ', 'NAME' => 'Arizona', 'STATE' => '04'],
    ]);

    expect(array_column($states, 'caseId'))->toBe(['AZ']);
});

it('maps every USPS code to its AP style abbreviation and its type', function (): void {
    $codes = [
        'AL', 'AK', 'AZ', 'AR', 'CA', 'CO', 'CT', 'DE', 'FL', 'GA', 'HI', 'ID', 'IL', 'IN', 'IA', 'KS', 'KY', 'LA', 'ME',
        'MD', 'MA', 'MI', 'MN', 'MS', 'MO', 'MT', 'NE', 'NV', 'NH', 'NJ', 'NM', 'NY', 'NC', 'ND', 'OH', 'OK', 'OR', 'PA',
        'RI', 'SC', 'SD', 'TN', 'TX', 'UT', 'VT', 'VA', 'WA', 'WV', 'WI', 'WY', 'DC', 'AS', 'GU', 'MP', 'PR', 'VI',
    ];

    // A name that never equals the AP form, so a code missing from the map
    // shows up as its name rather than passing by coincidence
    $states = (new StateEnumGenerator)->buildStatesFromRecords(array_map(
        static fn (string $code): array => ['STUSAB' => $code, 'NAME' => 'Name of '.$code, 'STATE' => '00'],
        $codes
    ));

    $mapped = [];
    foreach ($states as $state) {
        $mapped[$state['caseId']] = $state['apAbbreviation'].' | '.$state['type'];
    }

    expect($mapped)->toBe([
        'AK' => 'Alaska | state', 'AL' => 'Ala. | state', 'AR' => 'Ark. | state', 'AS' => 'American Samoa | territory',
        'AZ' => 'Ariz. | state', 'CA' => 'Calif. | state', 'CO' => 'Colo. | state', 'CT' => 'Conn. | state',
        'DC' => 'D.C. | district', 'DE' => 'Del. | state', 'FL' => 'Fla. | state', 'GA' => 'Ga. | state',
        'GU' => 'Guam | territory', 'HI' => 'Hawaii | state', 'IA' => 'Iowa | state', 'ID' => 'Idaho | state',
        'IL' => 'Ill. | state', 'IN' => 'Ind. | state', 'KS' => 'Kan. | state', 'KY' => 'Ky. | state',
        'LA' => 'La. | state', 'MA' => 'Mass. | state', 'MD' => 'Md. | state', 'ME' => 'Maine | state',
        'MI' => 'Mich. | state', 'MN' => 'Minn. | state', 'MO' => 'Mo. | state', 'MP' => 'Northern Mariana Islands | territory',
        'MS' => 'Miss. | state', 'MT' => 'Mont. | state', 'NC' => 'N.C. | state', 'ND' => 'N.D. | state',
        'NE' => 'Neb. | state', 'NH' => 'N.H. | state', 'NJ' => 'N.J. | state', 'NM' => 'N.M. | state',
        'NV' => 'Nev. | state', 'NY' => 'N.Y. | state', 'OH' => 'Ohio | state', 'OK' => 'Okla. | state',
        'OR' => 'Ore. | state', 'PA' => 'Pa. | state', 'PR' => 'Puerto Rico | territory', 'RI' => 'R.I. | state',
        'SC' => 'S.C. | state', 'SD' => 'S.D. | state', 'TN' => 'Tenn. | state', 'TX' => 'Texas | state',
        'UT' => 'Utah | state', 'VA' => 'Va. | state', 'VI' => 'U.S. Virgin Islands | territory', 'VT' => 'Vt. | state',
        'WA' => 'Wash. | state', 'WI' => 'Wis. | state', 'WV' => 'W.Va. | state', 'WY' => 'Wyo. | state',
    ]);
});

it('renders the whole generated file, one attribute line and case per state', function (): void {
    $row = static fn (string $id, array $centroid, array $internal): array => [
        'caseId' => $id,
        'fullName' => 'Full '.$id,
        'baseName' => 'Base '.$id,
        'apAbbreviation' => 'Ap '.$id,
        'type' => 'type-'.$id,
        'fipsCode' => 'fips-'.$id,
        'iso3166_2' => 'US-'.$id,
        'geoId' => 'geo-'.$id,
        'nationalStandardCode' => 'ns-'.$id,
        'division' => 'div-'.$id,
        'region' => 'reg-'.$id,
        'functionalStatusCode' => 'fs-'.$id,
        'mafTigerFeatureClassCode' => 'mtfcc-'.$id,
        'mafTigerObjectIdentifier' => 'oid-'.$id,
        'legalStatisticalAreaDescriptionCode' => 'lsadc-'.$id,
        'areaLand' => 'land-'.$id,
        'areaWater' => 'water-'.$id,
        'arcGisObjectIdentifier' => 'obj-'.$id,
        'centroidGeo' => $centroid,
        'internalPointGeo' => $internal,
    ];

    /** @var array<int, array{caseId: string, fullName: string, baseName: string, apAbbreviation: string, type: string, fipsCode: string, iso3166_2: string, geoId: string, nationalStandardCode: string, division: string, region: string, functionalStatusCode: string, mafTigerFeatureClassCode: string, mafTigerObjectIdentifier: string, legalStatisticalAreaDescriptionCode: string, areaLand: string, areaWater: string, arcGisObjectIdentifier: string, centroidGeo: array<int, string>, internalPointGeo: array<int, string>}> $states */
    $states = [
        $row('AA', ['10', '20'], ['30', '40']),
        // Missing coordinates render as empty strings rather than failing
        $row('BB', ['50'], [1 => '60']),
        $row('CC', [1 => '70'], ['80']),
    ];

    $php = (new StateEnumGenerator)->generatePhpContent($states, ['First source   ', 'Second source']);

    // The timestamp is the one line that changes from run to run
    $php = (string) preg_replace(
        '/^ \* This list of cases was generated on \d{4}-\d{2}-\d{2} at \d{2}:\d{2}:\d{2} \S+$/m',
        ' * This list of cases was generated on <when>',
        $php
    );

    $attributes = static fn (string $id, string $centroid, string $internal): string => "\t#[StateInfo(fullName: 'Full {$id}', apAbbreviation: 'Ap {$id}', type: 'type-{$id}', fipsCode: 'fips-{$id}', iso3166_2: 'US-{$id}', baseName: 'Base {$id}', geoId: 'geo-{$id}', nationalStandardCode: 'ns-{$id}', division: 'div-{$id}', region: 'reg-{$id}', functionalStatusCode: 'fs-{$id}', mafTigerFeatureClassCode: 'mtfcc-{$id}', mafTigerObjectIdentifier: 'oid-{$id}', legalStatisticalAreaDescriptionCode: 'lsadc-{$id}', areaLand: 'land-{$id}', areaWater: 'water-{$id}', arcGisObjectIdentifier: 'obj-{$id}', centroidGeo: [{$centroid}], internalPointGeo: [{$internal}])]";

    expect($php)->toBe(implode("\n", [
        '<?php',
        '',
        '/**',
        ' * Generated States enum cases',
        ' *',
        ' * INSTRUCTIONS:',
        ' * 1. Copy all the case statements below (from the first case to the',
        ' *    closing brace)',
        ' * 2. Open app/Enums/State.php',
        ' * 3. Replace the existing case list in the State enum with the',
        ' *    copied cases',
        ' * 4. Keep the StateInfo attribute class and any other methods in',
        ' *    State.php',
        ' * 5. Save the file',
        ' *',
        ' * Data Source:',
        ' * - First source',
        ' * - Second source',
        ' *',
        ' * This list of cases was generated on <when>',
        ' * Total states: 3',
        ' */',
        '',
        'enum State: string',
        '{',
        $attributes('AA', "'10', '20'", "'30', '40'"),
        "\tcase AA = 'AA';",
        '',
        $attributes('BB', "'50', ''", "'', '60'"),
        "\tcase BB = 'BB';",
        '',
        $attributes('CC', "'', '70'", "'80', ''"),
        "\tcase CC = 'CC';",
        '',
        '}',
        '',
    ]));
});
