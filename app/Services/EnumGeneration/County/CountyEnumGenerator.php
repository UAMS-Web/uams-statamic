<?php

declare(strict_types=1);

namespace App\Services\EnumGeneration\County;

use App\Enums\State;
use App\Enums\StateInfo;
use App\Services\EnumGeneration\GeneratorUtils;
use App\Services\EnumGeneration\TigerWebUtils;
use Illuminate\Support\Facades\File as LaravelFile;
use ReflectionEnum;
use RuntimeException;

/**
 * Generates the timestamped `County_generated.php` enum from TIGERweb.
 *
 * Retrieves "County and County Equivalent Entities" from TIGERweb (MapServer
 * layer 1, supplemented by layer 55 for any counties absent from layer 1) and
 * renders a `County_generated_YYYY-MM-DD_HH-MM-SS.php` enum file with
 * `CountyInfo` metadata.
 *
 * Data Sources:
 *   - TIGERweb Counties API (MapServer layers 1 and 55):
 *     https://tigerweb.geo.census.gov/arcgis/rest/services/TIGERweb/State_County/MapServer/1/query
 *
 * After generation, copy the case list from the generated file to
 * `app/Enums/County.php` to replace the existing case list.
 */
final class CountyEnumGenerator
{
    /**
     * Fetches the TIGERweb county records for a layer; TigerWebUtils::fetchCountyRecords() unless a test passes another.
     *
     * @var \Closure(int): ?array<int, array<string, string>>
     */
    private readonly \Closure $fetchCountyRecords;

    /**
     * @param  ?\Closure(int): ?array<int, array<string, string>>  $fetchCountyRecords  Replaces TigerWebUtils::fetchCountyRecords() (tests only)
     */
    public function __construct(?\Closure $fetchCountyRecords = null)
    {
        $this->fetchCountyRecords = $fetchCountyRecords ?? TigerWebUtils::fetchCountyRecords(...);
    }

    /**
     * Fetch TIGERweb counties, normalize, and write the timestamped enum file.
     *
     * @return string Absolute path to the generated enum file
     *
     * @throws RuntimeException When the primary (layer 1) TIGERweb fetch fails
     */
    public function generate(): string
    {
        // Compose timestamped output path without mutating global timezone
        $outputPath = GeneratorUtils::makeTimestampedEnumPath('County');

        // Download and parse TIGERweb layer 1 JSON features
        $records = ($this->fetchCountyRecords)(1);

        // Abort on fetch/parse failure
        if ($records === null) {
            throw new RuntimeException('Failed to fetch TIGERweb layer 1 counties');
        }

        // Download and parse TIGERweb layer 55 JSON features (supplemental)
        $layer55Records = ($this->fetchCountyRecords)(55);

        // Merge in layer 55 records only when that supplemental fetch succeeded
        if ($layer55Records !== null) {
            $records = $this->mergeSupplementalRecords(
                $records,
                $layer55Records
            );
        }

        // Normalize the merged records into sorted enum-case rows
        $counties = $this->buildCountiesFromRecords($records);

        // Render the generated enum content
        $phpContent = $this->generatePhpContent(
            $counties,
            $this->dataSourceLines()
        );

        // Write the generated PHP file to disk
        LaravelFile::put(
            $outputPath,
            $phpContent
        );

        // Return the destination path for the caller to report
        return $outputPath;
    }

    /**
     * Append layer 55 county records absent from the primary (layer 1) set.
     *
     * @param  array<int, array<string, string>>  $records  Primary layer 1 records
     * @param  array<int, array<string, string>>  $layer55Records  Supplemental layer 55 records
     * @return array<int, array<string, string>> Primary records plus any new layer 55 counties
     */
    public function mergeSupplementalRecords(
        array $records,
        array $layer55Records
    ): array {
        // Create a lookup map of existing records by geoId for efficient matching
        $existingGeoIds = [];

        // Loop through existing records to build geoId lookup map
        foreach ($records as $index => $record) {
            // Extract geoId from the record
            $geoId = $record['GEOID'] ?? '';

            // Check if geoId is present
            if ($geoId !== '') {
                // Add geoId to lookup map with its index
                $existingGeoIds[$geoId] = $index;
            }
        }

        // Loop through layer 55 records to find missing counties
        foreach ($layer55Records as $layer55Record) {
            // Extract geoId from the layer 55 record
            $geoId = $layer55Record['GEOID'] ?? '';

            // Check if geoId is missing
            if ($geoId === '') {
                // Skip records without geoId
                continue;
            }

            // Check if this geoId already exists in our records
            if (! isset($existingGeoIds[$geoId])) {
                // Add new record (counties that only exist in layer 55)
                $records[] = $layer55Record;
            }
        }

        // Return the merged record set
        return $records;
    }

    /**
     * Normalize TIGERweb county records into sorted enum-case rows.
     *
     * @param  array<int, array<string, string>>  $records  Merged TIGERweb county records
     * @return array<int, array{fullName: string, baseName: string, fipsCode: string, parentUspsCode: string, parentFipsCode: string, geoId: string, nationalStandardCode: string, fipsClassCode: string, functionalStatusCode: string, mafTigerFeatureClassCode: string, mafTigerObjectIdentifier: string, legalStatisticalAreaDescriptionCode: string, areaLand: string, areaWater: string, arcGisObjectIdentifier: string, centroidGeo: array{string, string}, internalPointGeo: array{string, string}, caseId: string}>
     */
    public function buildCountiesFromRecords(
        array $records
    ): array {
        // Assemble unique cases keyed by generated caseId
        $countiesByCaseId = [];

        // Build FIPS->USPS mapping for states/territories (for parentUspsCode)
        $fipsToUsps = $this->buildStateFipsToUspsMap();

        // Iterate over parsed records to normalize and collect county data
        foreach ($records as $rec) {
            // Parent FIPS code (two digits)
            $parentFips = (string) ($rec['STATE'] ?? '');

            // County FIPS code (three digits)
            $countyFips3 = (string) ($rec['COUNTY'] ?? '');

            // USPS code via mapping (may be null for unknowns)
            $usps = $fipsToUsps[$parentFips] ?? null;

            // Full name
            $fullName = (string) ($rec['NAME'] ?? '');

            // Base name
            $baseName = (string) ($rec['BASENAME'] ?? '');

            // Determine case ID prefix using USPS code if available, otherwise FIPS code
            $caseIdPrefix = $usps ?: $parentFips;

            // Generate unique case identifier from prefix and full name
            $caseId = $this->generateCaseId(
                $caseIdPrefix,
                $fullName
            );

            // Initialize county data array for this case ID
            $countiesByCaseId[$caseId] ??= [
                'fullName' => $fullName,
                'baseName' => $baseName,
                'fipsCode' => $countyFips3,
                'parentUspsCode' => $usps ?? '',
                'parentFipsCode' => $parentFips,
                'geoId' => (string) ($rec['GEOID'] ?? ''),
                'nationalStandardCode' => (string) ($rec['COUNTYNS'] ?? ''),
                'fipsClassCode' => (string) ($rec['COUNTYCC'] ?? ''),
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
                'caseId' => $caseId,
            ];

            // Clean up variables to free up memory
            unset(
                $parentFips,
                $countyFips3,
                $usps,
                $fullName,
                $baseName,
                $caseIdPrefix,
                $caseId,
                $rec
            );

            // Encourage GC during large loops
            gc_collect_cycles();
        }

        // Flatten associative map into list for sorting and output
        $counties = \array_values($countiesByCaseId);

        // Sort by state (parentUspsCode) then by county full name for stable output order
        usort(
            $counties,
            function (array $a, array $b): int {
                // Check if parent USPS codes differ
                if ($a['parentUspsCode'] !== $b['parentUspsCode']) {
                    // Return comparison result for parent USPS codes
                    return $a['parentUspsCode'] <=> $b['parentUspsCode'];
                }

                // Secondary sort: county name
                return $a['fullName'] <=> $b['fullName'];
            }
        );

        // Return the normalized, sorted county rows
        return $counties;
    }

    /**
     * Data-source header lines embedded in the generated file for traceability.
     *
     * @return array<int, string>
     */
    private function dataSourceLines(): array
    {
        return [
            'TIGERweb/State_County (MapServer) API, Counties (or statistically equivalent entities):',
            '  - Layer 1 (January 1, 2025 vintage):',
            '    https://tigerweb.geo.census.gov/arcgis/rest/services/TIGERweb/State_County/MapServer/1/query?where=1%3D1&outFields=STATE,COUNTY,COUNTYNS,NAME,COUNTYCC,FUNCSTAT,GEOID,MTFCC,OID,BASENAME,LSADC,AREALAND,AREAWATER,OBJECTID,CENTLAT,CENTLON,INTPTLAT,INTPTLON&returnGeometry=false&f=json',
            '  - Layer 55 (2020 Census - January 1, 2020 vintage):',
            '    https://tigerweb.geo.census.gov/arcgis/rest/services/TIGERweb/State_County/MapServer/55/query?where=1%3D1&outFields=STATE,COUNTY,COUNTYNS,NAME,COUNTYCC,FUNCSTAT,GEOID,MTFCC,OID,BASENAME,LSADC,AREALAND,AREAWATER,OBJECTID,CENTLAT,CENTLON,INTPTLAT,INTPTLON&returnGeometry=false&f=json',
        ];
    }

    /**
     * Build a mapping of two-digit state FIPS to USPS postal codes.
     *
     * @return array<string,string>
     */
    private function buildStateFipsToUspsMap(): array
    {
        // Initialize output mapping: FIPS (2-digit) -> USPS abbreviation
        $map = [];

        // Reflect over the State enum to read embedded StateInfo attributes
        $reflection = new ReflectionEnum(State::class);

        // Loop through all State enum cases
        foreach ($reflection->getCases() as $case) {

            // Fetch the StateInfo attribute from the enum case
            $attributes = $case->getAttributes(StateInfo::class);

            // Check if StateInfo attribute is missing
            if (empty($attributes)) {
                // Skip cases without StateInfo attribute
                continue;
            }

            // Materialize the attribute instance to access metadata
            /** @var StateInfo $info */
            $info = $attributes[0]->newInstance();

            // Check if FIPS code is present and non-empty
            if ($info->fipsCode !== '') {
                // Map FIPS code to USPS code (string backing value)
                $map[$info->fipsCode] = (string) $case->getBackingValue();
            }
        }

        // Return the completed FIPS -> USPS mapping
        return $map;
    }

    /**
     * Generate case ID from USPS state code and county name.
     *
     * @param  string  $stateCode  Two-letter USPS state/territory code (e.g., 'TX')
     * @param  string  $countyName  County or county-equivalent name (e.g., 'Pulaski County')
     * @return string Case identifier in the form STATE_UPPER_SNAKE_NAME
     */
    public function generateCaseId(
        string $stateCode,
        string $countyName
    ): string {
        // Remove apostrophes to normalize case ID generation
        $withoutApostrophes = \str_replace(
            "'",
            '',
            $countyName
        );

        // Convert to uppercase snake case to form the case ID suffix
        // preg_replace only returns null on regex error; the static pattern is valid
        /** @var string $normalized */
        $normalized = \preg_replace(
            '/[^a-zA-Z0-9]+/',
            '_',
            $withoutApostrophes
        );

        $snakeCase = \strtoupper($normalized);

        // Trim any leading/trailing underscores introduced by replacement
        $snakeCase = \trim(
            $snakeCase,
            '_'
        );

        // Prefix with USPS state code to complete the case ID
        return $stateCode.'_'.$snakeCase;
    }

    /**
     * Generate the PHP content for the County enum.
     *
     * @param  array<int, array{fullName: string, baseName: string, fipsCode: string, parentUspsCode: string, parentFipsCode: string, geoId: string, nationalStandardCode: string, fipsClassCode: string, functionalStatusCode: string, mafTigerFeatureClassCode: string, mafTigerObjectIdentifier: string, legalStatisticalAreaDescriptionCode: string, areaLand: string, areaWater: string, arcGisObjectIdentifier: string, centroidGeo: array{string, string}, internalPointGeo: array{string, string}, caseId: string}>  $counties
     * @param  array<int,string>  $dataSourceLines  Lines to embed in the Data Source section
     */
    public function generatePhpContent(
        array $counties,
        array $dataSourceLines = []
    ): string {
        // Header: open PHP tag and start generated documentation block
        $content = "<?php\n\ndeclare(strict_types=1);\n\n";
        $content .= "/**\n";
        $content .= " * Generated County and County Equivalent Entities enum cases\n";
        $content .= " *\n";
        $content .= " * INSTRUCTIONS:\n";
        $content .= " * 1. Copy all the case statements below (from the first case to the\n";
        $content .= " *    closing brace)\n";
        $content .= " * 2. Open app/Enums/County.php\n";
        $content .= " * 3. Replace the existing case list in the County enum with the\n";
        $content .= " *    copied cases\n";
        $content .= " * 4. Keep any other methods in County.php; the CountyInfo attribute\n";
        $content .= " *    class is in CountyInfo.php\n";
        $content .= " * 5. Save the file\n";
        $content .= " *\n";
        $content .= " * Data Source:\n";

        // Check if data source lines are provided
        if ($dataSourceLines !== []) {
            // Loop through data source lines to add to documentation
            foreach ($dataSourceLines as $line) {
                // Append trimmed data source line to content
                $content .= ' * - '.\rtrim($line)."\n";
            }
        } else { // Otherwise
            // Fallback note (should not occur; kept for safety)
            $content .= " * - TIGERweb Counties API\n";
        }

        // Footer spacing in the docblock
        $content .= " *\n";
        $content .= ' * This list of cases was generated on '.\date('Y-m-d').' at '.\date('H:i:s T')."\n";
        $content .= ' * Total counties: '.\count($counties)."\n";
        $content .= " */\n";
        $content .= "enum County: string\n";
        $content .= "{\n";

        // Render each attribute value as a single-quoted PHP literal
        $l = fn (string $value): string => $this->phpLiteral($value);

        // Loop through counties to generate enum cases
        foreach ($counties as $county) {
            // Attribute + case line, indented with four spaces to match Pint's `indentation_type`
            $content .= '    #[CountyInfo(fullName: '.$l($county['fullName'])
                .', baseName: '.$l($county['baseName'])
                .', fipsCode: '.$l($county['fipsCode'])
                .', parentUspsCode: '.$l($county['parentUspsCode'])
                .', parentFipsCode: '.$l($county['parentFipsCode'])
                .', geoId: '.$l($county['geoId'])
                .', nationalStandardCode: '.$l($county['nationalStandardCode'])
                .', fipsClassCode: '.$l($county['fipsClassCode'])
                .', functionalStatusCode: '.$l($county['functionalStatusCode'])
                .', mafTigerFeatureClassCode: '.$l($county['mafTigerFeatureClassCode'])
                .', mafTigerObjectIdentifier: '.$l($county['mafTigerObjectIdentifier'])
                .', legalStatisticalAreaDescriptionCode: '.$l($county['legalStatisticalAreaDescriptionCode'])
                .', areaLand: '.$l($county['areaLand'])
                .', areaWater: '.$l($county['areaWater'])
                .', arcGisObjectIdentifier: '.$l($county['arcGisObjectIdentifier'])
                .', centroidGeo: ['.$l($county['centroidGeo'][0]).', '.$l($county['centroidGeo'][1]).']'
                .', internalPointGeo: ['.$l($county['internalPointGeo'][0]).', '.$l($county['internalPointGeo'][1]).'])]'
                ."\n";
            $content .= "    case {$county['caseId']} = '{$county['caseId']}';\n\n";
        }

        // Close enum definition
        $content .= "}\n";

        // Return full file content for writing
        return $content;
    }

    /**
     * Render a value as a single-quoted PHP string literal that evaluates back to it.
     *
     * Both `\` and `'` are escaped, so a value holding a backslash, including a
     * trailing one, round-trips instead of losing a character or breaking the
     * literal (#716).
     */
    private function phpLiteral(string $value): string
    {
        return "'".\addcslashes($value, '\\\'')."'";
    }
}
