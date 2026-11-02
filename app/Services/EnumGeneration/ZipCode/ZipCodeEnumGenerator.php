<?php

declare(strict_types=1);

namespace App\Services\EnumGeneration\ZipCode;

use App\Console\Commands\GenerateZipCodeEnum;
use App\Services\EnumGeneration\GeneratorUtils;
use Illuminate\Support\Facades\File as LaravelFile;

/**
 * Generates the timestamped `ZipCode_generated.php` enum from the HUD API.
 *
 * Retrieves ZIP code data from the HUD USPS Crosswalk API and renders a
 * `ZipCode_generated_YYYY-MM-DD_HH-MM-SS.php` enum file with `ZipCodeInfo`
 * metadata.
 *
 * Thin wiring around this service lives in
 * {@see GenerateZipCodeEnum}: the generation logic lives
 * here so it can be tested in isolation, matching the other
 * `app/Services/EnumGeneration/` generators.
 *
 * Data Source:
 *   - HUD USPS Crosswalk API:
 *     https://www.huduser.gov/portal/dataset/uspszip-api.html
 *
 * After generation, copy the case list from the generated file to
 * `app/Enums/ZipCode.php` to replace the existing case list. The generated file
 * includes instructions for this process.
 *
 * Note: The generated ZipCodeInfo attribute emits:
 *   - zip_code: string
 *   - city: string
 *   - state_usps: string (USPS code)
 *   - state_geoId: string (state GEOID)
 *   - county_geoId: array<string> (county GEOIDs)
 *   - countySubdivision_geoId: array<string> (county subdivision GEOIDs)
 *   - tract_geoId: array<string> (census tract GEOIDs)
 *   - congressionalDistrict_geoId: array<string> (congressional district GEOIDs)
 *   - coreBasedStatisticalArea_geoId: array<string>
 *     (core-based statistical area GEOIDs)
 *   - coreBasedStatisticalAreaDivision_geoId: array<string>
 *     (core-based statistical area division GEOIDs)
 */
final class ZipCodeEnumGenerator
{
    /**
     * Longest the search buffer grows before it is trimmed while finding the results.
     *
     * It only bounds memory: any length longer than `"results"` finds the same keys, so
     * changing it changes no output.
     */
    // @pest-mutate-ignore: DecrementInteger, IncrementInteger
    private const int SEARCH_BUFFER_LIMIT = 10000;

    /**
     * How many trailing characters a trimmed search buffer keeps.
     *
     * It only bounds memory: any length longer than `"results"` keeps a key that is
     * split across the trim, so changing it changes no output.
     */
    // @pest-mutate-ignore: DecrementInteger, IncrementInteger
    private const int SEARCH_BUFFER_KEEP = 5000;

    /**
     * Downloads one HUD API response to a file; GeneratorUtils::downloadToFile() unless a test passes another.
     *
     * @var \Closure(string, string, array<string, string>, int): bool
     */
    private readonly \Closure $downloadToFile;

    /**
     * Waits between HUD API requests; usleep() unless a test passes another.
     *
     * @var \Closure(int): void
     */
    private readonly \Closure $pause;

    /**
     * @param  ?\Closure(string, string, array<string, string>, int): bool  $downloadToFile  Replaces GeneratorUtils::downloadToFile() (tests only)
     * @param  ?\Closure(int): void  $pause  Replaces usleep() (tests only)
     */
    public function __construct(
        ?\Closure $downloadToFile = null,
        ?\Closure $pause = null
    ) {
        $this->downloadToFile = $downloadToFile ?? GeneratorUtils::downloadToFile(...);
        $this->pause = $pause ?? usleep(...);
    }

    /**
     * Fetch HUD ZIP codes, normalize, and write the timestamped enum file.
     *
     * @param  ?int  $limit  Optional max ZIP codes to process (null = no limit)
     * @return string Absolute path to the generated enum file
     */
    public function generate(
        ?int $limit = null
    ): string {
        // Fetch comprehensive ZIP code data from multiple crosswalk types
        $zipData = $this->getComprehensiveZipDataFromHud($limit);

        // Normalize the records into enum-case rows
        $enumCases = $this->buildZipRowsFromRecords($zipData);

        // Render PHP content using helper method
        $php = $this->generatePhpContent($enumCases);

        // Compose timestamped output path without mutating global timezone
        $outPath = GeneratorUtils::makeTimestampedEnumPath('ZipCode');

        // Persist generated enum to disk
        LaravelFile::put(
            $outPath,
            $php
        );

        // Return the destination path for the caller to report
        return $outPath;
    }

    /**
     * Normalize HUD ZIP records into prepared enum-case rows.
     *
     * @param  array<int, array<string, mixed>>  $zipData
     * @return array<int, array<string, string>>
     */
    public function buildZipRowsFromRecords(
        array $zipData
    ): array {
        // Accumulators for generated cases and stats
        $enumCases = [];

        // Loop through ZIP data records to process each one
        foreach ($zipData as $zipRecord) {
            // Build enum attribute + case for this record
            $case = $this->processZipRecordWithHudData(
                $zipRecord,
                $zipRecord
            );

            // Add the generated case to the enum cases array
            $enumCases[] = $case;

            // Clean up variables to free up memory
            unset(
                $case
            );

            // Advise GC in long-running loop. It only frees memory, so the
            // RemoveFunctionCall mutant is equivalent. It is not annotated: that mutator
            // removes this statement itself, a marker above a statement reaches only its
            // children, and the trailing marker that would reach it is inert on Windows,
            // where the plugin's line map does not work.
            gc_collect_cycles();
        }

        // Return the normalized enum-case rows
        return $enumCases;
    }

    /**
     * Generate the PHP content for the ZipCode enum cases file.
     *
     * @param  array<int, array<string, string>>  $enumCases
     */
    public function generatePhpContent(
        array $enumCases
    ): string {
        // Header: open PHP tag and start generated documentation block
        $content = "<?php\n\n";

        // Instructions block
        $content .= "/**\n";
        $content .= " * Generated US ZIP codes enum cases\n";
        $content .= " *\n";
        $content .= " * INSTRUCTIONS:\n";
        $content .= " * 1. Copy all the case statements below (from the first case to the\n";
        $content .= " *    closing brace)\n";
        $content .= " * 2. Open app/Enums/ZipCode.php\n";
        $content .= " * 3. Replace the existing case list in the ZipCode enum with the\n";
        $content .= " *    copied cases\n";
        $content .= " * 4. Keep the ZipCodeInfo attribute class and any other methods in\n";
        $content .= " *    ZipCode.php\n";
        $content .= " * 5. Save the file\n";
        $content .= " *\n";
        $content .= " * Data Source:\n";
        $content .= " * - HUD USPS Crosswalk API: https://www.huduser.gov/portal/dataset/uspszip-api.html\n";
        $content .= " *\n";
        $content .= ' * This list of cases was generated on '.\date('Y-m-d').' at '.
            \date('H:i:s T')."\n";
        $content .= ' * Total ZIP records: '.\count($enumCases)."\n";
        $content .= " */\n\n";

        // Open enum
        $content .= "enum ZipCode: string\n";
        $content .= "{\n";

        // Loop through enum cases to generate case statements
        foreach ($enumCases as $c) {
            // Emit each prepared attribute + case
            $content .= "\t".$c['attribute']."\n";
            $content .= "\t".$c['case']."\n\n";
        }

        // Close enum
        $content .= "}\n";

        // Return the generated PHP content
        return $content;
    }

    /**
     * HUD USPS Crosswalk API bearer token from configuration (`US_HUD_API_TOKEN` at
     * config build time).
     *
     * @return ?string API token from configuration or null if not configured
     */
    private function hudUspsApiToken(): ?string
    {
        // Get API token from configuration
        $token = config('services.hud.usps_api_token');

        // Return null if token is not a string or is empty
        if (
            ! \is_string($token)
            || $token === ''
        ) {
            return null;
        }

        // Return the API token
        return $token;
    }

    /**
     * Process ZIP record using comprehensive HUD API data.
     *
     * @param  array<string,mixed>  $zipData
     * @param  array<string,mixed>  $hudComprehensiveData
     * @return array<string,string>
     */
    public function processZipRecordWithHudData(
        array $zipData,
        array $hudComprehensiveData
    ): array {
        // ---
        // Extract core fields from HUD data (prefer HUD data over original)
        // ---

        // Extract raw ZIP code value from HUD data or fallback to original data
        $zipCodeRaw = $hudComprehensiveData['zip'] ?? $zipData['zip_code'] ?? '';

        // Coerce ZIP code to a string only when scalar. The cast changes no output:
        // the value only reaches sprintf('%s') and string concatenation.
        // @pest-mutate-ignore: RemoveStringCast
        $zipCode = \is_scalar($zipCodeRaw) ? (string) $zipCodeRaw : '';

        // Extract raw city name value from HUD data or fallback to original data
        $cityRaw = $hudComprehensiveData['city'] ?? $zipData['usps_city'] ?? '';

        // Extract and escape city name from HUD data or fallback to original data
        $city = \str_replace(
            "'",
            "\\'",
            \is_scalar($cityRaw) ? (string) $cityRaw : ''
        );

        // Extract raw state USPS code value from HUD data or fallback to original data
        $stateUspsRaw = $hudComprehensiveData['state'] ?? $zipData['stusps_code'] ?? '';

        // Extract state USPS code from HUD data or fallback to original data. The
        // cast changes no output: the value only reaches sprintf('%s').
        // @pest-mutate-ignore: RemoveStringCast
        $stateUsps = \is_scalar($stateUspsRaw) ? (string) $stateUspsRaw : '';

        // Extract raw state GEOID value from HUD data
        $stateGeoIdRaw = $hudComprehensiveData['state_geoId'] ?? '';

        // Extract state GEOID from HUD data. The cast changes no output: the value
        // only reaches sprintf('%s').
        // @pest-mutate-ignore: RemoveStringCast
        $stateGeoId = \is_scalar($stateGeoIdRaw) ? (string) $stateGeoIdRaw : '';

        // Get county GEOIDs directly from HUD data
        $countyGeoIdsRaw = $hudComprehensiveData['county_geoId'] ?? [];

        // Narrow county GEOIDs to an array for formatting
        $countyGeoIds = \is_array($countyGeoIdsRaw) ? $countyGeoIdsRaw : [];

        // ---
        // Helper function to format arrays correctly
        // (empty arrays become [] instead of [''])
        // ---
        $formatArray = fn (array $array): string => $array === [] ? '[]' : "['".\implode(
            "', '",
            \array_map(static fn (mixed $value): string => \is_scalar($value) ? (string) $value : '', $array)
        )."']";

        // Prepare PHP array-literals for attribute output
        $countyGeoIdArray = $formatArray($countyGeoIds);

        // Format the ZipCodeInfo attribute for this enum case with comprehensive data
        $attribute = \sprintf(
            "#[ZipCodeInfo(zip_code: '%s', city: '%s', state_usps: '%s', state_geoId: '%s', county_geoId: %s)]",
            $zipCode,
            $city,
            $stateUsps,
            $stateGeoId,
            $countyGeoIdArray
        );

        // Compute enum case identifier string
        $caseName = 'ZIP_'.$zipCode;

        // Return attribute + enum case line for the generator
        $result = [
            'attribute' => $attribute,
            'case' => sprintf("case %s = '%s';", $caseName, $caseName),
        ];

        // Clean up variables to free up memory
        unset(
            $zipCode,
            $zipCodeRaw,
            $city,
            $cityRaw,
            $stateUsps,
            $stateUspsRaw,
            $stateGeoId,
            $stateGeoIdRaw,
            $countyGeoIds,
            $countyGeoIdsRaw,
            $countyGeoIdArray,
            $attribute,
            $caseName
        );

        // Return processed ZIP record with attribute and case
        return $result;
    }

    /**
     * Get comprehensive ZIP code data from HUD USPS Crosswalk API using multiple
     * crosswalk types.
     *
     * This method queries the HUD API with multiple crosswalk types for each state to
     * get comprehensive ZIP code information including city, state, tract, county,
     * core-based statistical area, and other geographic data.
     *
     * @param  ?int  $limit  Optional limit on number of ZIP codes to process
     * @return array<int, array<string,mixed>>
     */
    private function getComprehensiveZipDataFromHud(
        ?int $limit = null
    ): array {
        // Define cache key for comprehensive ZIP data (include limit if specified)
        $cacheKey = 'hud_zip_comprehensive_data'.($limit ? '_limit_'.$limit : '');

        // Check cache first
        /** @var array<int, array<string, mixed>>|null $cached */
        $cached = cache()->get($cacheKey);

        // Check if cached data exists
        if ($cached !== null) {
            // Return cached comprehensive ZIP data
            return $cached;
        }

        // Get API token from configuration
        $token = $this->hudUspsApiToken();

        // Check if API token is missing
        if ($token === null) {
            // Return empty array to indicate failure
            return [];
        }

        // HUD USPS Crosswalk API endpoint
        $baseUrl = 'https://www.huduser.gov/hudapi/public/usps';

        // Define all US states and territories
        $states = [
            'AL',
            'AK',
            'AZ',
            'AR',
            'CA',
            'CO',
            'CT',
            'DE',
            'FL',
            'GA',
            'HI',
            'ID',
            'IL',
            'IN',
            'IA',
            'KS',
            'KY',
            'LA',
            'ME',
            'MD',
            'MA',
            'MI',
            'MN',
            'MS',
            'MO',
            'MT',
            'NE',
            'NV',
            'NH',
            'NJ',
            'NM',
            'NY',
            'NC',
            'ND',
            'OH',
            'OK',
            'OR',
            'PA',
            'RI',
            'SC',
            'SD',
            'TN',
            'TX',
            'UT',
            'VT',
            'VA',
            'WA',
            'WV',
            'WI',
            'WY',
            'DC',
            'AS',
            'GU',
            'MP',
            'PR',
            'VI',
        ];

        // Define crosswalk types to process
        $crosswalkTypes = [
            2 => 'zip-county',
        ];

        // Initialize array to accumulate ZIP data from all states
        $allZipData = [];

        // Loop through each state
        foreach ($states as $state) {
            // Initialize data structure for this state
            /** @var array<int, array<string, mixed>> $stateZipData */
            $stateZipData = [];

            // Process each crosswalk type for this state
            foreach (array_keys($crosswalkTypes) as $type) {
                // Wrap API call in try-catch to handle errors gracefully
                try {
                    // Build request parameters for this crosswalk type and state
                    $params = [
                        'type' => $type,
                        'query' => $state,
                    ];

                    // Create temporary file for the response
                    $tempFile = tempnam(
                        sys_get_temp_dir(),
                        'hud_api_'.$type.'_'.$state.'_'
                    );

                    // Define HTTP headers for API request
                    $headers = [
                        'Authorization' => 'Bearer '.$token,
                        'Accept' => 'application/json',
                    ];

                    // Download API response to temporary file
                    $success = ($this->downloadToFile)(
                        $baseUrl.'?'.http_build_query($params),
                        $tempFile,
                        $headers,
                        60
                    );

                    // Check if download was successful
                    if ($success) {
                        // Create a chunk processor function that will handle each batch of data
                        $chunkProcessor = function (array $chunk) use (
                            &$stateZipData,
                            $type
                        ): true {
                            // Loop through chunk results to process each one
                            foreach ($chunk as $result) {
                                // Check if result is valid
                                if (! \is_array($result)) {
                                    // Skip invalid results
                                    continue;
                                }

                                // Extract ZIP code from result
                                $zipCode = $result['zip'] ?? null;

                                // Check if ZIP code is present
                                if ($zipCode) {
                                    $zipRecordIndex = array_find_key($stateZipData, fn ($record): bool => $record['zip'] === $zipCode);
                                    // Narrow the GEOID to a string for length and substring operations
                                    $geoId = isset($result['geoid']) && \is_scalar($result['geoid'])
                                        ? (string) $result['geoid']
                                        : '';

                                    // Check if no existing record was found
                                    if ($zipRecordIndex === null) {
                                        // Create new ZIP record
                                        $newRecord = [
                                            'zip' => $zipCode,
                                            'city' => $result['city'] ?? '',
                                            'state' => $result['state'] ?? '',
                                            'state_geoId' => isset($result['geoid']) && \strlen($geoId) >= 2 ? \substr(
                                                $geoId,
                                                0,
                                                2
                                            ) : '',
                                            'county_geoId' => [],
                                        ];
                                        // Add new record to state ZIP data
                                        $stateZipData[] = $newRecord;

                                        // Set index to the newly added record
                                        $zipRecordIndex = \count($stateZipData) - 1;
                                    }

                                    // Check if the GEOID is set (type 2 = zip-county crosswalk)
                                    // @phpstan-ignore identical.alwaysTrue (the crosswalk-type map currently defines only type 2; the explicit check guards the branch as more types are added)
                                    if (isset($result['geoid']) && $type === 2) {
                                        // Read the existing county GEOID list for this record
                                        /** @var list<string> $countyGeoIds */
                                        $countyGeoIds = $stateZipData[$zipRecordIndex]['county_geoId'] ?? [];
                                        // Check if GEOID is not already in the array
                                        if (! \in_array($result['geoid'], $countyGeoIds)) {
                                            // Add county GEOID to record
                                            $countyGeoIds[] = $geoId;
                                            $stateZipData[$zipRecordIndex]['county_geoId'] = $countyGeoIds;
                                        }
                                    }
                                }
                            }

                            // Continue processing
                            return true;
                        };

                        // Process the JSON file in chunks. The chunk size only sets how
                        // many results are handed over at a time, so it changes no output.
                        $this->parseLargeJsonFileInChunks(
                            $tempFile,
                            $chunkProcessor,
                            // @pest-mutate-ignore: IncrementInteger, DecrementInteger
                            500,
                            $type
                        );

                        // Clean up temp file immediately
                        unlink($tempFile);
                    } else { // Otherwise
                        // ---
                        // Check if this is a 404 response (no data available for this crosswalk
                        // type/state combination). This is acceptable and should not be treated as an
                        // error.
                        // ---

                        // Check if temp file exists
                        if (\file_exists($tempFile)) {
                            // Clean up temp file
                            unlink($tempFile);
                        }
                    }
                } catch (\Exception) {
                    // Check if temp file exists
                    if (
                        isset($tempFile)
                        && \file_exists($tempFile)
                    ) {
                        // Clean up temp file
                        unlink($tempFile);
                    }
                }

                // Add a small delay between crosswalk type requests
                ($this->pause)(50000); // 0.05 second delay
            }

            // Merge state data into all data
            $allZipData = \array_merge(
                $allZipData,
                $stateZipData
            );

            // ---
            // Check if we've reached the limit after processing all crosswalk types for this
            // state
            // ---
            if (
                $limit !== null
                && \count($allZipData) >= $limit
            ) {
                // Exit state processing loop
                break;
            }

            // Force garbage collection after each state. It only frees memory, so the
            // RemoveFunctionCall mutant is equivalent. It is not annotated: that mutator
            // removes this statement itself, a marker above a statement reaches only its
            // children, and the trailing marker that would reach it is inert on Windows,
            // where the plugin's line map does not work.
            gc_collect_cycles();

            // Clean up variables to free up memory
            unset(
                $stateZipData,
                $chunkProcessor,
                $tempFile,
                $headers,
                $success,
                $params
            );

            // Add a small delay between state requests to be respectful to the API
            ($this->pause)(100000); // 0.1 second delay
        }

        // ---
        // Remove duplicates by ZIP code (keep first occurrence)
        // ---

        // Initialize array for unique ZIP data
        $uniqueZipData = [];

        // Initialize lookup map to track seen ZIP codes
        $seenZips = [];

        // Loop through all ZIP data to remove duplicates
        foreach ($allZipData as $data) {
            // Extract ZIP code from data entry and narrow it to a valid array key
            $zipCodeRaw = $data['zip'];
            // Every ZIP that is not scalar shares this fallback key. The chunk processor
            // drops empty ZIPs, so '' never collides with a real one, and no real ZIP
            // equals the mutator's replacement text either.
            // @pest-mutate-ignore: EmptyStringToNotEmpty
            $zipCode = \is_scalar($zipCodeRaw) ? (string) $zipCodeRaw : '';

            // Check if this ZIP code has not been seen yet
            if (! isset($seenZips[$zipCode])) {
                // Add data entry to unique ZIP data array
                $uniqueZipData[] = $data;

                // Mark ZIP code as seen; isset() above is true for false as well
                // @pest-mutate-ignore: TrueToFalse
                $seenZips[$zipCode] = true;
            }
        }

        // Sort by ZIP code
        usort(
            $uniqueZipData,
            fn (array $a, array $b): int => $a['zip'] <=> $b['zip']
        );

        // Check if limit is specified
        if ($limit !== null) {
            // Slice the unique ZIP data to the limit
            $uniqueZipData = \array_slice(
                $uniqueZipData,
                0,
                $limit
            );
        }

        // Cache the result for 24 hours
        cache()->put(
            $cacheKey,
            $uniqueZipData,
            now()->addHours(24)
        );

        // Return unique ZIP data array
        return $uniqueZipData;
    }

    /**
     * Parse a large JSON file in chunks and process each chunk immediately.
     *
     * This method reads a JSON file and processes the 'data.results' array
     * in chunks to avoid memory issues and provide immediate feedback.
     *
     * @param  string  $filePath  Path to the JSON file
     * @param  callable  $chunkProcessor  Function to process each chunk of data
     * @param  int  $chunkSize  Number of objects to process in each chunk
     * @param  int  $crosswalkType  The crosswalk type being processed
     * @return bool True if successful, false on failure
     */
    private function parseLargeJsonFileInChunks(
        string $filePath,
        callable $chunkProcessor,
        int $chunkSize = 1000,
        int $crosswalkType = 0
    ): bool {
        // Open file for reading
        $handle = fopen(
            $filePath,
            'r'
        );

        // Check if file open failed
        if ($handle === false) {
            // Return false to indicate failure
            return false;
        }

        // Initialize buffer used to find the "data" and "results" keys. Any start
        // without those keys in it reads the same, so the mutant is equivalent.
        // @pest-mutate-ignore: EmptyStringToNotEmpty
        $buffer = '';

        // Initialize flag to track if the "data" key has been seen
        $sawData = false;

        // Initialize flag to track if we're inside results array
        $inResults = false;

        // Initialize flags to track a JSON string and an escape inside it, so a
        // brace or bracket in a string value is read as text, not as structure
        $inString = false;
        $escaped = false;

        // Initialize array to collect current chunk of objects
        $currentChunk = [];

        // Initialize string to build current JSON object
        $currentObject = '';

        // Initialize depth counter for current object nesting
        $objectDepth = 0;

        // Loop through file character by character
        while (($char = fgetc($handle)) !== false) {
            // ---
            // Find the results array: "results" after "data"
            // ---

            // Check if we haven't entered results yet
            if (! $inResults) {
                // Append character to buffer
                $buffer .= $char;

                // Check if the buffer contains "data"
                if (! $sawData && str_contains($buffer, '"data"')) {
                    // Remember it, so trimming the buffer cannot lose it
                    $sawData = true;
                }

                // Check if "results" follows "data". Trimming the buffer only bounds
                // memory: a trimmed or untrimmed buffer finds the same keys, so the
                // mutants of the trim are equivalent.
                // @pest-mutate-ignore: ElseIfNegated, GreaterToGreaterOrEqual, GreaterToSmallerOrEqual, UnwrapSubstr
                if ($sawData && str_contains($buffer, '"results"')) {
                    // Mark that we've entered the results array
                    $inResults = true;
                } elseif (\strlen($buffer) > self::SEARCH_BUFFER_LIMIT) { // Check if buffer exceeds maximum size
                    // Trim buffer to its trailing characters to prevent memory issues
                    $buffer = \substr(
                        $buffer,
                        -self::SEARCH_BUFFER_KEEP
                    );
                }

                // Read the next character
                continue;
            }

            // ---
            // Read a JSON string inside the results
            // ---

            // Check if we're inside a string
            if ($inString) {
                // Check if the string belongs to a result object
                if ($objectDepth > 0) {
                    // Append character to current object string
                    $currentObject .= $char;
                }

                // Check if this character is escaped
                if ($escaped) {
                    // The escape covers only this character
                    $escaped = false;
                } elseif ($char === '\\') { // Check if the character starts an escape
                    // Mark the next character as escaped
                    $escaped = true;
                } elseif ($char === '"') { // Check if the character ends the string
                    // Mark that we've left the string
                    $inString = false;
                }

                // Read the next character
                continue;
            }

            // ---
            // Track result objects, including the arrays and objects inside them
            // ---

            // Check if the character starts a string
            if ($char === '"') {
                // Mark that we've entered a string
                $inString = true;
            } elseif ($char === '{') { // Check if the character is a '{'
                // Append '{' to the current object, which is empty between results
                $currentObject .= '{';

                // Increment the object depth
                $objectDepth++;

                // Read the next character
                continue;
            } elseif ($char === '}') { // Check if the character is a '}'
                // Check if this closes the object around the results, not a result.
                // Past that point every later object starts after the stray '}' the
                // DecrementInteger mutant appends, so none decodes and the mutant is
                // equivalent.
                // @pest-mutate-ignore: DecrementInteger
                if ($objectDepth === 0) {
                    // Exit character reading loop
                    break;
                }

                // Decrement the object depth
                $objectDepth--;

                // Append '}' to the current object
                $currentObject .= '}';

                // Check if we've completed an object
                if ($objectDepth === 0) {
                    // Parse the current object as JSON
                    $parsedObject = \json_decode(
                        $currentObject,
                        true
                    );

                    // Reset current object string for next object
                    $currentObject = '';

                    // Check if JSON parsing was successful
                    if ($parsedObject !== null) {
                        // Add the parsed object to the current chunk
                        $currentChunk[] = $parsedObject;

                        // Check if the current chunk reaches the specified size
                        if (\count($currentChunk) >= $chunkSize) {
                            // Process the current chunk
                            $continue = $chunkProcessor(
                                $currentChunk,
                                $crosswalkType
                            );

                            // Reset the current chunk
                            $currentChunk = [];

                            // Force garbage collection after each chunk. It only frees
                            // memory, so the RemoveFunctionCall mutant is equivalent. It is
                            // not annotated: that mutator removes this statement itself, a
                            // marker above a statement reaches only its children, and the
                            // trailing marker that would reach it is inert on Windows, where
                            // the plugin's line map does not work.
                            gc_collect_cycles();

                            // Check if we should stop processing
                            if ($continue === false) {
                                // Exit character reading loop
                                break;
                            }
                        }
                    }
                }

                // Read the next character
                continue;
            } elseif ($char === ']' && $objectDepth === 0) { // Check if the character closes the results array
                // Exit character reading loop
                break;
            }

            // Check if we're inside a result object
            if ($objectDepth > 0) {
                // Append character to current object string
                $currentObject .= $char;
            }
        }

        // Close file handle. PHP closes it anyway when the function returns, so the
        // RemoveFunctionCall mutant is equivalent. It is not annotated: that mutator
        // removes this statement itself, a marker above a statement reaches only its
        // children, and the trailing marker that would reach it is inert on Windows,
        // where the plugin's line map does not work.
        fclose($handle);

        // ---
        // Process any remaining objects in the final chunk
        // ---

        // Check if there are remaining objects in the final chunk
        if ($currentChunk !== []) {
            // Process final chunk of objects
            $chunkProcessor(
                $currentChunk,
                $crosswalkType
            );
        }

        // Return true to indicate successful parsing
        return true;
    }
}
