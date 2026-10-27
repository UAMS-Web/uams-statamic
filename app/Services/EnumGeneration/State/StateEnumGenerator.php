<?php

declare(strict_types=1);

namespace App\Services\EnumGeneration\State;

use App\Services\EnumGeneration\GeneratorUtils;
use App\Services\EnumGeneration\TigerWebUtils;
use Illuminate\Support\Facades\File as LaravelFile;
use RuntimeException;

/**
 * Generates the timestamped `State_generated.php` enum from TIGERweb.
 *
 * Retrieves U.S. states, territories, and the district from TIGERweb (MapServer
 * layer 0) and renders a `State_generated_YYYY-MM-DD_HH-MM-SS.php` enum file
 * with enriched `StateInfo` metadata.
 *
 * Data Source:
 *   - TIGERweb States API (MapServer layer 0):
 *     https://tigerweb.geo.census.gov/arcgis/rest/services/TIGERweb/State_County/MapServer/0/query
 *       - Requested fields: STUSAB, NAME, BASENAME, STATE, GEOID, STATENS,
 *         DIVISION, REGION, FUNCSTAT, MTFCC, OID, LSADC, AREALAND, AREAWATER,
 *         OBJECTID, CENTLAT, CENTLON, INTPTLAT, INTPTLON
 *
 * After generation, copy the case list from the generated file to
 * `app/Enums/State.php` to replace the existing case list.
 */
final class StateEnumGenerator
{
    /**
     * Fetches the TIGERweb state records for a layer; TigerWebUtils::fetchStateRecords() unless a test passes another.
     *
     * @var \Closure(int): ?array<int, array<string, string>>
     */
    private readonly \Closure $fetchStateRecords;

    /**
     * @param  ?\Closure(int): ?array<int, array<string, string>>  $fetchStateRecords  Replaces TigerWebUtils::fetchStateRecords() (tests only)
     */
    public function __construct(?\Closure $fetchStateRecords = null)
    {
        $this->fetchStateRecords = $fetchStateRecords ?? TigerWebUtils::fetchStateRecords(...);
    }

    /**
     * Fetch TIGERweb states, normalize, and write the timestamped enum file.
     *
     * @return string Absolute path to the generated enum file
     *
     * @throws RuntimeException When the TIGERweb fetch fails
     */
    public function generate(): string
    {
        // Determine output path for the timestamped generated enum file
        $outputPath = GeneratorUtils::makeTimestampedEnumPath('State');

        // Retrieve state features from TIGERweb
        $records = ($this->fetchStateRecords)(0);

        // Abort on fetch/parse failure
        if ($records === null) {
            throw new RuntimeException('Failed to fetch TIGERweb states');
        }

        // Normalize the records into sorted enum-case rows
        $states = $this->buildStatesFromRecords($records);

        // Render the generated PHP with cases and metadata header
        $phpContent = $this->generatePhpContent(
            $states,
            $this->dataSourceLines()
        );

        // Write the file to disk
        LaravelFile::put(
            $outputPath,
            $phpContent
        );

        // Return the destination path for the caller to report
        return $outputPath;
    }

    /**
     * Normalize TIGERweb state records into sorted enum-case rows.
     *
     * @param  array<int, array<string, string>>  $records  TIGERweb feature attributes
     * @return array<int, array{caseId:string, fullName:string, baseName:string, apAbbreviation:string, type:string, fipsCode:string, iso3166_2:string, geoId:string, nationalStandardCode:string, division:string, region:string, functionalStatusCode:string, mafTigerFeatureClassCode:string, mafTigerObjectIdentifier:string, legalStatisticalAreaDescriptionCode:string, areaLand:string, areaWater:string, arcGisObjectIdentifier:string, centroidGeo:array<int,string>, internalPointGeo:array<int,string>}>
     */
    public function buildStatesFromRecords(
        array $records
    ): array {
        // Build USPS -> AP style abbreviation mapping
        $uspsToAp = $this->buildUspsToApAbbreviationMap();

        // Build USPS -> type mapping
        $uspsToType = $this->buildUspsToTypeMap();

        // Collect unique states keyed by USPS case ID
        $statesByCaseId = [];

        // Normalize each TIGERweb record into our internal structure
        foreach ($records as $rec) {
            // USPS postal code (case ID/backing value)
            $usps = (string) ($rec['STUSAB'] ?? '');

            // Human‑readable names
            $fullName = (string) ($rec['NAME'] ?? '');

            // Base name without LSAD suffix
            $baseName = (string) ($rec['BASENAME'] ?? '');

            // Two‑digit FIPS code
            $fips = (string) ($rec['STATE'] ?? '');

            // GEOID string for the state
            $geoId = (string) ($rec['GEOID'] ?? '');

            // Skip incomplete rows that lack essential identifiers
            if (
                $usps === ''
                || $fullName === ''
                || $fips === ''
            ) {
                // Clean up variables to free up memory
                unset(
                    $rec,
                    $usps,
                    $fullName,
                    $baseName,
                    $fips,
                    $geoId
                );

                // Long‑running loop: hint GC to control memory footprint
                // Only frees memory, so the RemoveFunctionCall mutant is equivalent. It
                // is not annotated: that mutator removes this statement itself, a marker
                // above a statement reaches only its children, and the trailing marker
                // that would reach it is inert on Windows, where the plugin's line map
                // does not work.
                gc_collect_cycles();

                // Move on to the next record
                continue;
            }

            // Case ID and value are the USPS code
            $caseId = $usps;

            // Create the entry once per USPS code
            if (! isset($statesByCaseId[$caseId])) {
                $statesByCaseId[$caseId] = [
                    'caseId' => $caseId,
                    'fullName' => $fullName,
                    'baseName' => $baseName,
                    'apAbbreviation' => $uspsToAp[$usps] ?? $fullName,
                    'type' => $uspsToType[$usps] ?? 'state',
                    'fipsCode' => $fips,
                    'iso3166_2' => 'US-'.$usps,
                    'geoId' => $geoId,
                    'nationalStandardCode' => (string) ($rec['STATENS'] ?? ''),
                    'division' => (string) ($rec['DIVISION'] ?? ''),
                    'region' => (string) ($rec['REGION'] ?? ''),
                    'functionalStatusCode' => (string) ($rec['FUNCSTAT'] ?? ''),
                    'mafTigerFeatureClassCode' => (string) ($rec['MTFCC'] ?? ''),
                    'mafTigerObjectIdentifier' => (string) ($rec['OID'] ?? ''),
                    'legalStatisticalAreaDescriptionCode' => (string) ($rec['LSADC'] ?? ''),
                    'areaLand' => (string) ($rec['AREALAND'] ?? ''),
                    'areaWater' => (string) ($rec['AREAWATER'] ?? ''),
                    'arcGisObjectIdentifier' => (string) ($rec['OBJECTID'] ?? ''),
                    'centroidGeo' => [
                        (string) ($rec['CENTLAT'] ?? ''),
                        (string) ($rec['CENTLON'] ?? ''),
                    ],
                    'internalPointGeo' => [
                        (string) ($rec['INTPTLAT'] ?? ''),
                        (string) ($rec['INTPTLON'] ?? ''),
                    ],
                ];
            }

            // Clean up variables to free up memory
            unset(
                $rec,
                $usps,
                $fullName,
                $baseName,
                $fips,
                $geoId,
                $caseId
            );

            // Long‑running loop: hint GC to control memory footprint
            // Only frees memory, so the RemoveFunctionCall mutant is equivalent. It
            // is not annotated: that mutator removes this statement itself, a marker
            // above a statement reaches only its children, and the trailing marker
            // that would reach it is inert on Windows, where the plugin's line map
            // does not work.
            gc_collect_cycles();
        }

        // Flatten to a list for deterministic ordering
        // usort() below renumbers the list anyway, so array_values() changes no output.
        // @pest-mutate-ignore: UnwrapArrayValues
        $states = \array_values($statesByCaseId);

        // Sort cases by USPS code to ensure stable output
        usort(
            $states,
            fn (array $a, array $b): int => $a['caseId'] <=> $b['caseId']
        );

        // Return the normalized, sorted state rows
        return $states;
    }

    /**
     * Data-source header lines embedded in the generated file for traceability.
     *
     * @return array<int, string>
     */
    private function dataSourceLines(): array
    {
        return [
            'TIGERweb/State_County (MapServer), States (or statistically equivalent entities):',
            '  - Layer 0 (January 1, 2025 vintage):',
            '    https://tigerweb.geo.census.gov/arcgis/rest/services/TIGERweb/State_County/MapServer/0/query?where=1%3D1&outFields=STUSAB,NAME,BASENAME,STATE,GEOID,STATENS,DIVISION,REGION,FUNCSTAT,MTFCC,OID,LSADC,AREALAND,AREAWATER,OBJECTID,CENTLAT,CENTLON,INTPTLAT,INTPTLON&returnGeometry=false&f=json',
        ];
    }

    /**
     * Build a USPS -> AP style abbreviation map.
     *
     * @return array<string,string>
     */
    private function buildUspsToApAbbreviationMap(): array
    {
        return [
            'AL' => 'Ala.',
            'AK' => 'Alaska',
            'AZ' => 'Ariz.',
            'AR' => 'Ark.',
            'CA' => 'Calif.',
            'CO' => 'Colo.',
            'CT' => 'Conn.',
            'DE' => 'Del.',
            'FL' => 'Fla.',
            'GA' => 'Ga.',
            'HI' => 'Hawaii',
            'ID' => 'Idaho',
            'IL' => 'Ill.',
            'IN' => 'Ind.',
            'IA' => 'Iowa',
            'KS' => 'Kan.',
            'KY' => 'Ky.',
            'LA' => 'La.',
            'ME' => 'Maine',
            'MD' => 'Md.',
            'MA' => 'Mass.',
            'MI' => 'Mich.',
            'MN' => 'Minn.',
            'MS' => 'Miss.',
            'MO' => 'Mo.',
            'MT' => 'Mont.',
            'NE' => 'Neb.',
            'NV' => 'Nev.',
            'NH' => 'N.H.',
            'NJ' => 'N.J.',
            'NM' => 'N.M.',
            'NY' => 'N.Y.',
            'NC' => 'N.C.',
            'ND' => 'N.D.',
            'OH' => 'Ohio',
            'OK' => 'Okla.',
            'OR' => 'Ore.',
            'PA' => 'Pa.',
            'RI' => 'R.I.',
            'SC' => 'S.C.',
            'SD' => 'S.D.',
            'TN' => 'Tenn.',
            'TX' => 'Texas',
            'UT' => 'Utah',
            'VT' => 'Vt.',
            'VA' => 'Va.',
            'WA' => 'Wash.',
            'WV' => 'W.Va.',
            'WI' => 'Wis.',
            'WY' => 'Wyo.',
            'DC' => 'D.C.',
            'AS' => 'American Samoa',
            'GU' => 'Guam',
            'MP' => 'Northern Mariana Islands',
            'PR' => 'Puerto Rico',
            'VI' => 'U.S. Virgin Islands',
        ];
    }

    /**
     * Build a USPS -> type map for the codes that are not states.
     *
     * Every code missing here is a state: buildStatesFromRecords() defaults it
     * to 'state'.
     *
     * @return array<string,string>
     */
    private function buildUspsToTypeMap(): array
    {
        return [
            'DC' => 'district',
            'AS' => 'territory',
            'GU' => 'territory',
            'MP' => 'territory',
            'PR' => 'territory',
            'VI' => 'territory',
        ];
    }

    /**
     * Generate the PHP content for the State enum cases.
     *
     * @param  array<int, array{caseId:string, fullName:string, baseName:string, apAbbreviation:string, type:string, fipsCode:string, iso3166_2:string, geoId:string, nationalStandardCode:string, division:string, region:string, functionalStatusCode:string, mafTigerFeatureClassCode:string, mafTigerObjectIdentifier:string, legalStatisticalAreaDescriptionCode:string, areaLand:string, areaWater:string, arcGisObjectIdentifier:string, centroidGeo:array<int,string>, internalPointGeo:array<int,string>}>  $states
     * @param  array<int,string>  $dataSourceLines
     */
    public function generatePhpContent(
        array $states,
        array $dataSourceLines
    ): string {
        // Begin file and docblock header
        $content = "<?php\n\n";
        $content .= "/**\n";
        $content .= " * Generated States enum cases\n";
        $content .= " *\n";
        $content .= " * INSTRUCTIONS:\n";
        $content .= " * 1. Copy all the case statements below (from the first case to the\n";
        $content .= " *    closing brace)\n";
        $content .= " * 2. Open app/Enums/State.php\n";
        $content .= " * 3. Replace the existing case list in the State enum with the\n";
        $content .= " *    copied cases\n";
        $content .= " * 4. Keep the StateInfo attribute class and any other methods in\n";
        $content .= " *    State.php\n";
        $content .= " * 5. Save the file\n";
        $content .= " *\n";
        $content .= " * Data Source:\n";

        // Render data source lines for traceability
        foreach ($dataSourceLines as $line) {
            $content .= ' * - '.\rtrim($line)."\n";
        }

        // Footer of header block with generation timestamp
        $content .= " *\n";
        $content .= ' * This list of cases was generated on '.\date('Y-m-d').' at '.\date('H:i:s T')."\n";
        $content .= ' * Total states: '.\count($states)."\n";
        $content .= " */\n\n";

        // Open enum block
        $content .= "enum State: string\n";
        $content .= "{\n";

        // Emit cases with enriched StateInfo attributes
        foreach ($states as $st) {
            // Escape values for embedding
            $fullName = GeneratorUtils::escapeSingleQuotes($st['fullName']);
            $baseName = "'".GeneratorUtils::escapeSingleQuotes($st['baseName'])."'";
            $apAbbrev = GeneratorUtils::escapeSingleQuotes($st['apAbbreviation']);
            $type = GeneratorUtils::escapeSingleQuotes($st['type']);
            $fips = GeneratorUtils::escapeSingleQuotes($st['fipsCode']);
            $iso3166_2 = GeneratorUtils::escapeSingleQuotes($st['iso3166_2']);
            $geoId = GeneratorUtils::escapeSingleQuotes($st['geoId']);
            $ns = GeneratorUtils::escapeSingleQuotes($st['nationalStandardCode']);
            $division = GeneratorUtils::escapeSingleQuotes($st['division']);
            $region = GeneratorUtils::escapeSingleQuotes($st['region']);
            $func = GeneratorUtils::escapeSingleQuotes($st['functionalStatusCode']);
            $mtfcc = GeneratorUtils::escapeSingleQuotes($st['mafTigerFeatureClassCode']);
            $oid = GeneratorUtils::escapeSingleQuotes($st['mafTigerObjectIdentifier']);
            $lsadc = GeneratorUtils::escapeSingleQuotes($st['legalStatisticalAreaDescriptionCode']);
            $areaLand = GeneratorUtils::escapeSingleQuotes($st['areaLand']);
            $areaWater = GeneratorUtils::escapeSingleQuotes($st['areaWater']);
            $objectId = GeneratorUtils::escapeSingleQuotes($st['arcGisObjectIdentifier']);
            $centLat = isset($st['centroidGeo'][0]) ? GeneratorUtils::escapeSingleQuotes($st['centroidGeo'][0]) : '';
            $centLon = isset($st['centroidGeo'][1]) ? GeneratorUtils::escapeSingleQuotes($st['centroidGeo'][1]) : '';
            $intLat = isset($st['internalPointGeo'][0]) ? GeneratorUtils::escapeSingleQuotes($st['internalPointGeo'][0]) : '';
            $intLon = isset($st['internalPointGeo'][1]) ? GeneratorUtils::escapeSingleQuotes($st['internalPointGeo'][1]) : '';

            // Attribute line followed by the enum case
            $content .= "\t#[StateInfo(fullName: '{$fullName}', apAbbreviation: '{$apAbbrev}', type: '{$type}', fipsCode: '{$fips}', iso3166_2: '{$iso3166_2}', baseName: {$baseName}, geoId: '{$geoId}', nationalStandardCode: '{$ns}', division: '{$division}', region: '{$region}', functionalStatusCode: '{$func}', mafTigerFeatureClassCode: '{$mtfcc}', mafTigerObjectIdentifier: '{$oid}', legalStatisticalAreaDescriptionCode: '{$lsadc}', areaLand: '{$areaLand}', areaWater: '{$areaWater}', arcGisObjectIdentifier: '{$objectId}', centroidGeo: ['{$centLat}', '{$centLon}'], internalPointGeo: ['{$intLat}', '{$intLon}'])]\n";
            $content .= "\tcase {$st['caseId']} = '{$st['caseId']}';\n\n";
        }

        // Close enum block and return content
        $content .= "}\n";

        // Return completed PHP file content
        return $content;
    }
}
