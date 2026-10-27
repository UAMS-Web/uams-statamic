<?php

declare(strict_types=1);

namespace App\Services\EnumGeneration\MimeType;

use App\Console\Commands\GenerateMimeTypeEnum;
use App\Services\EnumGeneration\GeneratorUtils;
use Illuminate\Support\Facades\File as LaravelFile;

/**
 * Generates the timestamped `MimeType_generated.php` enum from mime-db, IANA
 * media types, and Apache MIME types.
 *
 * Retrieves MIME type mappings from multiple sources in priority order:
 *   - mime-db database (primary source - includes IANA, Apache, and nginx data)
 *   - IANA Media Types Registry (all categories) - as backup
 *   - Apache HTTP Server mime.types file - as backup
 *
 * It generates a `MimeType_generated_YYYY-MM-DD_HH-MM-SS.php` enum file with
 * cases from all sources, with mime-db types taking precedence for duplicates.
 *
 * Thin wiring around this service lives in {@see GenerateMimeTypeEnum}: the
 * generation logic lives here so it can be tested in isolation, matching the
 * other `app/Services/EnumGeneration/` generators.
 *
 * Data Sources:
 *   - mime-db database:
 *     https://github.com/jshttp/mime-db
 *   - IANA Media Types Registry:
 *     https://www.iana.org/assignments/media-types/media-types.xhtml
 *   - Apache HTTP Server MIME Types:
 *     https://svn.apache.org/repos/asf/httpd/httpd/trunk/docs/conf/mime.types
 *
 * After generation, copy the case list from the generated file to
 * `app/Enums/MimeType.php` to replace the existing case list. The generated file
 * includes instructions for this process.
 */
final class MimeTypeEnumGenerator
{
    /**
     * Fetch all sources, merge extensions, build, and write the timestamped enum file.
     *
     * @return string Absolute path to the generated enum file
     */
    public function generate(): string
    {
        // Compose timestamped output path without mutating global timezone
        $outputPath = GeneratorUtils::makeTimestampedEnumPath('MimeType');

        // Initialize records array
        $records = [];

        // Fetch from mime-db (primary source)
        $mimeDbRecords = $this->fetchMimeDbRecords();

        // Check if mime-db fetch failed
        if ($mimeDbRecords === null) {
            // Initialize empty array to prevent null errors
            $mimeDbRecords = [];
        }

        // Fetch from IANA media types (secondary source)
        $ianaRecords = $this->fetchIanaMediaTypeRecords();

        // Fetch from Apache MIME types (tertiary source)
        $apacheRecords = $this->fetchApacheMimeTypeRecords();

        // Check if the Apache fetch failed
        if ($apacheRecords === null) {
            // Initialize empty array to prevent null errors
            $apacheRecords = [];
        }

        // ---
        // Merge records from all sources with proper deduplication.
        // ---

        // Initialize an associative array to track MIME types and ensure proper priority
        $recordsByMimeType = [];

        // Loop through the Apache records (lowest priority)
        foreach ($apacheRecords as $record) {
            // Extract MIME type from the record
            $mimeType = $record['mimeType'];

            // Check if this MIME type is not already in the records
            if (! isset($recordsByMimeType[$mimeType])) {
                // Add record to the map
                $recordsByMimeType[$mimeType] = $record;
            }
        }

        // Loop through the IANA records (medium priority)
        foreach ($ianaRecords as $record) {
            // Extract MIME type from the record
            $mimeType = $record['mimeType'];

            // Check if this MIME type is not already in the records
            if (! isset($recordsByMimeType[$mimeType])) {
                // Add record to the map
                $recordsByMimeType[$mimeType] = $record;
            }
        }

        // Loop through the mime-db records (highest priority)
        foreach ($mimeDbRecords as $record) {
            // Extract MIME type from the record
            $mimeType = $record['mimeType'];

            // ---
            // Check if the MIME type is already in the records and the original MIME type is
            // set (mime-db records never carry an original MIME type of their own)
            // ---
            if (
                isset($recordsByMimeType[$mimeType])
                && isset($recordsByMimeType[$mimeType]['originalMimeType'])
            ) {
                // ---
                // Set the original MIME type to the original MIME type from the records by
                // overwriting the record.
                // ---
                $record['originalMimeType'] = $recordsByMimeType[$mimeType]['originalMimeType'];
            }

            // Always overwrite with mime-db data
            $recordsByMimeType[$mimeType] = $record;
        }

        // Convert back to indexed array
        /** @var array<int, array{mimeType: string, extensions: array<int, string>}> $records */
        $records = \array_values($recordsByMimeType);

        // Clean up variables to free up memory
        unset(
            $recordsByMimeType
        );

        // Fetch extensions for MIME types that don't have extensions
        $records = $this->fetchExtensionsForMimeTypes($records);

        // Assemble unique cases keyed by generated caseId
        $mimeTypesByCaseId = [];

        // Loop through the parsed records to normalize and collect MIME type data
        foreach ($records as $rec) {
            // MIME type string
            $mimeType = (string) $rec['mimeType'];

            // Extensions array
            $extensions = $rec['extensions'];

            // Check if the MIME type is empty
            if ($mimeType === '') {
                // Clean up variables to free up memory
                unset(
                    $rec,
                    $mimeType,
                    $extensions
                );

                // Long-running loop: hint GC to control memory footprint
                gc_collect_cycles();

                // Move on to the next record
                continue;
            }

            // Generate case ID from MIME type
            $caseId = $this->generateCaseId($mimeType);

            // Check if this case ID has not been processed yet
            if (! isset($mimeTypesByCaseId[$caseId])) {
                // Extract category from MIME type (part before the slash)
                $category = strtok(
                    $mimeType,
                    '/'
                );

                // Preserve original MIME type capitalization if available
                $originalMimeType = $mimeType;

                // Initialize MIME type data array for this case ID
                $mimeTypesByCaseId[$caseId] = [
                    'mimeType' => $mimeType,
                    'extensions' => $extensions,
                    'category' => $category,
                    'caseId' => $caseId,
                    'originalMimeType' => $originalMimeType,
                ];
            }

            // Clean up variables to free up memory
            unset(
                $mimeType,
                $extensions,
                $caseId,
                $originalMimeType,
                $rec
            );

            // Encourage GC during large loops
            gc_collect_cycles();
        }

        // Flatten associative map into list for sorting and output
        $mimeTypes = \array_values($mimeTypesByCaseId);

        // Sort by MIME type for stable output order
        usort(
            $mimeTypes,
            fn (array $a, array $b): int => $a['mimeType'] <=> $b['mimeType']
        );

        // Compose data source lines for inclusion in the generated file
        $dataSourceLines = [
            '  - mime-db database:',
            '    https://github.com/jshttp/mime-db',
            '  - IANA Media Types Registry:',
            '    https://www.iana.org/assignments/media-types/media-types.xhtml',
            '  - Apache HTTP Server MIME Types:',
            '    https://svn.apache.org/repos/asf/httpd/httpd/trunk/docs/conf/mime.types',
        ];

        // Render the generated enum content
        $phpContent = $this->generatePhpContent(
            $mimeTypes,
            $dataSourceLines
        );

        // Write the generated PHP file to disk
        LaravelFile::put(
            $outputPath,
            $phpContent
        );

        // Clean up variables to free up memory
        unset(
            $mimeTypesByCaseId,
            $mimeTypes,
            $phpContent,
            $records
        );

        // Trigger GC cycle after cleanup
        gc_collect_cycles();

        // Return the destination path for the caller to report
        return $outputPath;
    }

    /**
     * Fetch MIME type records from mime-db database.
     *
     * @return array<int, array{mimeType: string, extensions: ?array<int,string>}>
     */
    private function fetchMimeDbRecords(): ?array
    {
        // Try to read from node_modules/mime-db/db.json
        $mimeDbPath = base_path('node_modules/mime-db/db.json');

        // Check if the mime-db file does not exist
        if (! \file_exists($mimeDbPath)) {
            // Return null to indicate failure
            return null;
        }

        // Read the JSON content
        $content = file_get_contents($mimeDbPath);

        // Check if file read failed
        if ($content === false) {
            // Return null to indicate failure
            return null;
        }

        // Parse the JSON content
        $mimeDbData = \json_decode(
            $content,
            true
        );

        // Check if JSON parsing failed or result is not an array
        if (! \is_array($mimeDbData)) {
            // Return null to indicate failure
            return null;
        }

        /** @var array<string, array{source?: string, extensions?: array<int,string>, compressible?: bool, charset?: string}> $mimeDbData */

        // Parse the content
        return $this->parseMimeDbContent($mimeDbData);
    }

    /**
     * Parse mime-db content from JSON format.
     *
     * @param  array<string, array{source?: string, extensions?: array<int,string>, compressible?: bool, charset?: string}>  $mimeDbData  The parsed mime-db data
     * @return array<int, array{mimeType: string, extensions: array<int,string>}>
     */
    private function parseMimeDbContent(
        array $mimeDbData
    ): array {
        // Initialize records array
        $records = [];

        // Loop through the MIME type entries
        foreach ($mimeDbData as $mimeType => $data) {
            // Normalize MIME type to lowercase
            $mimeType = \strtolower($mimeType);

            // Check if the MIME type format is not valid
            if (
                ! \preg_match(
                    '/^[a-zA-Z0-9!#$&\-\^_\.\+]*\/[a-zA-Z0-9!#$&\-\^_\.\+]*$/',
                    $mimeType
                )
            ) {
                // Clean up variables to free up memory
                unset(
                    $mimeType,
                    $data
                );

                // Skip invalid MIME type formats
                continue;
            }

            // Get extensions from the data
            $extensions = $data['extensions'] ?? [];

            // Normalize extensions to lowercase and remove leading dots
            $normalizedExtensions = [];

            // Loop through extensions to normalize them
            foreach ($extensions as $extension) {
                // Normalize extension to lowercase and remove leading dot
                $extension = \strtolower(
                    \ltrim(
                        $extension,
                        '.'
                    )
                );

                // Check if extension is not empty after normalization
                if ($extension !== '') {
                    // Add normalized extension to array
                    $normalizedExtensions[] = $extension;
                }
            }

            // Remove duplicates
            $normalizedExtensions = array_unique($normalizedExtensions);

            // Add record with normalized MIME type and extensions
            $records[] = [
                'mimeType' => $mimeType,
                'extensions' => $normalizedExtensions,
            ];

            // Clean up variables to free up memory
            unset(
                $mimeType,
                $data,
                $extensions,
                $normalizedExtensions
            );
        }

        // Clean up variables to free up memory
        unset(
            $mimeDbData
        );

        // Return parsed records array
        return $records;
    }

    /**
     * Fetch MIME type records from Apache mime.types file.
     *
     * @return array<int, array{mimeType: string, extensions: ?array<int,string>}>
     */
    private function fetchApacheMimeTypeRecords(): ?array
    {
        // Define cache key for Apache MIME types
        $cacheKey = 'apache_mime_types';

        // Check cache first
        $cached = cache()->get($cacheKey);

        // Check if cached data exists
        if (\is_string($cached)) {
            // Return parsed cached content
            return $this->parseMimeTypesContent($cached);
        }

        // Apache MIME types URL
        $url = 'https://svn.apache.org/repos/asf/httpd/httpd/trunk/docs/conf/mime.types';

        // Fetch the content
        $content = GeneratorUtils::httpGet(
            $url,
            ['Accept' => 'text/plain'],
            60
        );

        // Check if HTTP fetch failed
        if ($content === null) {
            // Return null to indicate failure
            return null;
        }

        // Cache the content for 24 hours
        cache()->put(
            $cacheKey,
            $content,
            now()->addHours(24)
        );

        // Parse the content
        return $this->parseMimeTypesContent($content);
    }

    /**
     * Fetch media type records from IANA media types registry.
     *
     * @return array<int, array{mimeType: string, extensions: ?array<int,string>, originalMimeType?: string}>
     */
    private function fetchIanaMediaTypeRecords(): array
    {
        // IANA media type categories and their CSV URLs
        $ianaCategories = [
            'application' => 'https://www.iana.org/assignments/media-types/application.csv',
            'audio' => 'https://www.iana.org/assignments/media-types/audio.csv',
            'font' => 'https://www.iana.org/assignments/media-types/font.csv',
            'haptics' => 'https://www.iana.org/assignments/media-types/haptics.csv',
            'image' => 'https://www.iana.org/assignments/media-types/image.csv',
            'message' => 'https://www.iana.org/assignments/media-types/message.csv',
            'model' => 'https://www.iana.org/assignments/media-types/model.csv',
            'multipart' => 'https://www.iana.org/assignments/media-types/multipart.csv',
            'text' => 'https://www.iana.org/assignments/media-types/text.csv',
            'video' => 'https://www.iana.org/assignments/media-types/video.csv',
        ];

        // Initialize combined records array
        $allRecords = [];

        // Loop through the IANA categories and URLs
        foreach ($ianaCategories as $category => $url) {
            // Define cache key for this IANA category
            $cacheKey = 'iana_csv_'.$category;

            // Check cache first
            $cached = cache()->get($cacheKey);

            // Check if cached data exists
            if ($cached !== null) {
                // Use cached content
                /** @var string $cached */
                $content = $cached;
            } else { // Otherwise
                // Fetch the CSV content
                $content = GeneratorUtils::httpGet(
                    $url,
                    ['Accept' => 'text/csv'],
                    60
                );

                // Check if HTTP fetch failed
                if ($content === null) {
                    // Skip to next category
                    continue;
                }

                // Cache the content for 30 days
                cache()->put(
                    $cacheKey,
                    $content,
                    now()->addDays(30)
                );
            }

            // Parse the CSV content
            $categoryRecords = $this->parseIanaCsvContent(
                $content,
                $category
            );

            // Merge category records into combined records array
            $allRecords = \array_merge(
                $allRecords,
                $categoryRecords
            );

            // Clean up variables to free up memory
            unset(
                $content,
                $categoryRecords,
                $cacheKey,
                $cached
            );

            // Encourage GC during large loops
            gc_collect_cycles();
        }

        // Clean up variables to free up memory
        unset(
            $ianaCategories
        );

        // Return combined records from all IANA categories
        return $allRecords;
    }

    /**
     * Fetch extensions for MIME types from all available sources.
     * This method accumulates extensions from all sources, allowing multiple
     * sources to contribute extensions for the same MIME type.
     *
     * @param  array<int, array{mimeType: string, extensions: array<int,string>}>  $records  All MIME type records
     * @return array<int, array{mimeType: string, extensions: array<int,string>}>
     */
    private function fetchExtensionsForMimeTypes(
        array $records
    ): array {
        // Try to get extensions from IANA detail pages
        $records = $this->fetchExtensionsFromIanaPages($records);

        // Try to get extensions from Apache mime.types file
        $records = $this->fetchExtensionsFromApache($records);

        // Return records with extensions added
        return $records;
    }

    /**
     * Parse MIME types content from Apache mime.types format.
     *
     * @param  string  $content  The raw content from the mime.types file
     * @return array<int, array{mimeType: string, extensions: ?array<int,string>}>
     */
    private function parseMimeTypesContent(
        string $content
    ): array {
        // Split content into lines
        $lines = \explode(
            "\n",
            $content
        );

        // Initialize records array
        $records = [];

        // Flag to indicate we've found the start of the data
        $foundHeader = false;

        // Loop through the lines
        foreach ($lines as $line) {
            // Trim whitespace
            $line = \trim($line);

            // Check if the line is empty
            if ($line === '') {
                // Skip empty lines
                continue;
            }

            // Check if the line starts with a hash
            if (
                \str_starts_with(
                    $line,
                    '#'
                )
            ) {
                // Check if the line contains "MIME type (lowercased)" and "Extensions"
                if (
                    \str_contains(
                        $line,
                        'MIME type (lowercased)'
                    )
                    && \str_contains(
                        $line,
                        'Extensions'
                    )
                ) {
                    // Mark that header has been found
                    $foundHeader = true;
                }

                // Skip comment lines
                continue;
            }

            // Check if the header was not found
            if (! $foundHeader) {
                // Skip lines before header
                continue;
            }

            // Parse the MIME type line
            $parsed = $this->parseMimeTypeLine($line);

            // Check if parsing was successful
            if ($parsed !== null) {
                // Add parsed record to records array
                $records[] = $parsed;
            }

            // Clean up variables to free up memory
            unset(
                $line,
                $parsed
            );
        }

        // Clean up variables to free up memory
        unset(
            $lines,
            $foundHeader
        );

        // Return parsed records array
        return $records;
    }

    /**
     * Parse a single MIME type line from the Apache format.
     *
     * @param  string  $line  The line to parse (e.g., "application/pdf\t\t\tpdf")
     * @return array{mimeType: string, extensions: ?array<int, string>}
     */
    private function parseMimeTypeLine(
        string $line
    ): ?array {
        // Split by whitespace (tabs and spaces)
        $parts = preg_split(
            '/\s+/',
            $line,
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        // Check if line has less than 2 parts (MIME type and at least one extension)
        if ($parts === false || \count($parts) < 2) {
            // Return null to indicate invalid line format
            return null;
        }

        // First part is the MIME type - normalize to lowercase
        $mimeType = \strtolower(
            \trim($parts[0])
        );

        // Check if the MIME type is not valid (allow . and + characters)
        if (
            ! \preg_match(
                '/^[a-zA-Z0-9!#$&\-\^_\.\+]*\/[a-zA-Z0-9!#$&\-\^_\.\+]*$/',
                $mimeType
            )
        ) {
            // Return null to indicate invalid MIME type
            return null;
        }

        // ---
        // Remaining parts are extensions - normalize to lowercase
        // ---

        // Initialize extensions array
        $extensions = [];
        $counter = \count($parts);

        // Loop through remaining parts to extract extensions
        for ($i = 1; $i < $counter; $i++) {
            // Normalize extension to lowercase and trim whitespace
            $extension = \strtolower(
                \trim($parts[$i])
            );

            // Check if extension is not empty
            if ($extension !== '') {
                // Add extension to array
                $extensions[] = $extension;
            }
        }

        // Remove duplicates
        $extensions = array_unique($extensions);

        // Clean up variables to free up memory
        unset(
            $parts,
            $i,
            $extension
        );

        // Return parsed MIME type record
        return [
            'mimeType' => $mimeType,
            'extensions' => $extensions,
        ];
    }

    /**
     * Parse IANA CSV content from media types registry.
     *
     * @param  string  $content  The raw CSV content from IANA
     * @param  string  $category  The media type category (e.g., 'application', 'text')
     * @return array<int, array{mimeType: string, extensions: ?array<int,string>, originalMimeType?: string}>
     */
    private function parseIanaCsvContent(
        string $content,
        string $category
    ): array {
        // Split content into lines
        $lines = \explode(
            "\n",
            $content
        );

        // Initialize records array
        $records = [];

        // Skip header line (first line)
        $firstLine = true;

        // Loop through the lines
        foreach ($lines as $line) {
            // Check if this is the first line
            if ($firstLine) {
                // Mark that header line has been processed
                $firstLine = false;

                // Skip header line
                continue;
            }

            // Trim whitespace
            $line = \trim($line);

            // Check if the line is empty
            if ($line === '') {
                // Skip empty lines
                continue;
            }

            // Parse the CSV line
            $parsed = $this->parseIanaCsvLine(
                $line,
                $category
            );

            // Check if the parsed line is not null
            if ($parsed !== null) {
                // Add parsed record to records array
                $records[] = $parsed;
            }

            // Clean up variables to free up memory
            unset(
                $line,
                $parsed
            );
        }

        // Clean up variables to free up memory
        unset(
            $lines,
            $firstLine
        );

        // Return parsed records array
        return $records;
    }

    /**
     * Parse a single IANA CSV line.
     *
     * @param  string  $line  The CSV line to parse
     * @param  string  $category  The media type category
     * @return array{mimeType: string, extensions: ?array<int,string>, originalMimeType?: string}
     */
    private function parseIanaCsvLine(
        string $line,
        string $category
    ): ?array {
        // Parse CSV line, handling quoted fields
        $parts = str_getcsv($line, escape: '\\');

        // ---
        // IANA CSV format: Name,Template,Reference,Deprecated,Obsoleted
        // ---

        // Get the subtype from the Name field
        $subtype = \trim($parts[0] ?? '');

        // Check if the subtype is empty
        if ($subtype === '') {
            // Return null to indicate empty subtype
            return null;
        }

        // Construct full MIME type with original capitalization for IANA URLs
        $originalMimeType = $category.'/'.$subtype;

        // Also create lowercase version for deduplication
        $mimeType = \strtolower($originalMimeType);

        // Check if the MIME type is not valid (allow . and + characters)
        if (
            ! \preg_match(
                '/^[a-zA-Z0-9!#$&\-\^_\.\+]*\/[a-zA-Z0-9!#$&\-\^_\.\+]*$/',
                $mimeType
            )
        ) {
            // Return null to indicate invalid MIME type
            return null;
        }

        // IANA registry doesn't provide file extensions, so we'll use an empty array
        // Extensions can be added manually or from other sources later
        $extensions = [];

        // Clean up variables to free up memory
        unset(
            $parts,
            $subtype
        );

        // Return parsed IANA CSV record
        return [
            'mimeType' => $mimeType,
            'extensions' => $extensions,
            'originalMimeType' => $originalMimeType, // Store original capitalization for IANA URLs
        ];
    }

    /**
     * Fetch extensions from IANA detail pages.
     *
     * @param  array<int, array{mimeType: string, extensions: array<int,string>}>  $records  All MIME type records
     * @return array<int, array{mimeType: string, extensions: array<int,string>}>
     */
    private function fetchExtensionsFromIanaPages(
        array $records
    ): array {
        // ---
        // Update records with extensions from IANA detail pages
        // ---

        // Initialize counter for tracking updated records
        $updatedCount = 0;

        // Initialize counter for tracking processed records
        $processedCount = 0;

        // Loop through records to fetch extensions from IANA detail pages
        foreach ($records as $index => $record) {
            // Extract MIME type from the record
            $mimeType = $record['mimeType'];

            // Get existing extensions (if any)
            $existingExtensions = $record['extensions'];

            // Use original capitalization for IANA URLs if available, otherwise use lowercase
            $ianaMimeType = $mimeType;

            // Define cache key for this MIME type detail page
            $cacheKey = 'iana_mime_detail_'.$mimeType;

            // Check cache first
            $cached = cache()->get($cacheKey);

            // Check if cached content exists
            if ($cached !== null) {
                // Use cached content
                /** @var string $cached */
                $content = $cached;
            } else { // Otherwise
                // Construct IANA detail page URL using original capitalization
                $url = 'https://www.iana.org/assignments/media-types/'.$ianaMimeType;

                // Fetch the detail page content
                $content = GeneratorUtils::httpGet(
                    $url,
                    ['Accept' => 'text/html'],
                    30
                );

                // Check if HTTP fetch failed
                if ($content === null) {
                    // Clean up variables to free up memory
                    unset(
                        $mimeType,
                        $ianaMimeType,
                        $url,
                        $content,
                        $cacheKey,
                        $cached
                    );

                    // Skip to next record
                    continue;
                }

                // Cache the content for 30 days
                cache()->put(
                    $cacheKey,
                    $content,
                    now()->addDays(30)
                );
            }

            // Parse extensions from the page content
            $newExtensions = $this->parseExtensionsFromIanaPage($content);

            // Check if new extensions were found
            if ($newExtensions !== []) {
                // Merge with existing extensions and remove duplicates
                $allExtensions = array_unique(
                    \array_merge(
                        $existingExtensions,
                        $newExtensions
                    )
                );

                // Update record with merged extensions
                $records[$index]['extensions'] = $allExtensions;

                // Increment counter for updated records
                $updatedCount++;
            }

            // Increment counter for processed records
            $processedCount++;

            // Clean up variables to free up memory
            unset(
                $mimeType,
                $ianaMimeType,
                $url,
                $content,
                $newExtensions,
                $allExtensions,
                $existingExtensions,
                $cacheKey,
                $cached
            );

            // Encourage GC during large loops
            gc_collect_cycles();
        }

        // Clean up variables to free up memory
        unset(
            $updatedCount,
            $processedCount
        );

        // Return records with extensions added
        return $records;
    }

    /**
     * Fetch extensions from Apache mime.types file.
     *
     * @param  array<int, array{mimeType: string, extensions: array<int,string>}>  $records  All MIME type records
     * @return array<int, array{mimeType: string, extensions: array<int,string>}>
     */
    private function fetchExtensionsFromApache(
        array $records
    ): array {
        // Get Apache MIME types data
        $apacheRecords = $this->fetchApacheMimeTypeRecords();

        // Check if Apache fetch failed
        if ($apacheRecords === null) {
            // Return records unchanged
            return $records;
        }

        // Initialize map of Apache MIME types to extensions
        $apacheMimeTypeMap = [];

        // Loop through Apache records to build MIME type to extensions map
        foreach ($apacheRecords as $record) {
            // Extract MIME type from the record
            $mimeType = $record['mimeType'];

            // Extract extensions from the record
            $extensions = $record['extensions'];

            // Check if extensions array is not empty
            if (! empty($extensions)) {
                // Add MIME type and extensions to map
                $apacheMimeTypeMap[$mimeType] = $extensions;
            }
        }

        // Initialize counter for tracking updated records
        $updatedCount = 0;

        // Loop through records to add Apache extensions
        foreach ($records as $index => $record) {
            // Extract MIME type from the record
            $mimeType = $record['mimeType'];

            // Get existing extensions from the record
            $existingExtensions = $record['extensions'];

            // Check if the MIME type is in the Apache MIME type map
            if (isset($apacheMimeTypeMap[$mimeType])) {
                // Get Apache extensions for this MIME type
                $apacheExtensions = $apacheMimeTypeMap[$mimeType];

                // Merge with existing extensions and remove duplicates
                $allExtensions = array_unique(
                    \array_merge(
                        $existingExtensions,
                        $apacheExtensions
                    )
                );

                // Update record with merged extensions
                $records[$index]['extensions'] = $allExtensions;

                // Increment counter for updated records
                $updatedCount++;

                // Clean up variables to free up memory
                unset(
                    $apacheExtensions,
                    $allExtensions
                );
            }

            // Clean up variables to free up memory
            unset(
                $mimeType,
                $existingExtensions
            );
        }

        // Clean up variables to free up memory
        unset(
            $apacheRecords,
            $apacheMimeTypeMap,
            $updatedCount
        );

        // Return records with Apache extensions added
        return $records;
    }

    /**
     * Parse extensions from IANA detail page content.
     *
     * @param  string  $content  The HTML content from the IANA detail page
     * @return array<int,string> Array of extensions (without leading dots)
     */
    public function parseExtensionsFromIanaPage(
        string $content
    ): array {
        // Look for "File extension(s):" patterns in the content
        $patterns = [
            '/File [Ee]xtensions?(?:\(s\))?\s*:\s*([^\n\r]+)/i',
        ];

        // More specific patterns for clean extensions (skip if contains hash symbols)
        $cleanPatterns = [
            '/File [Ee]xtensions?(?:\(s\))?\s*:\s*(?!.*#)([a-zA-Z0-9.]+)/i',
        ];

        // Skip patterns that indicate no extensions
        $skipPatterns = [
            '/File [Ee]xtensions?(?:\(s\))?\s*:\s*[-<]?(not applicable|NOT APPLICABLE|Not applicable|Not Applicable|None|none|NONE|N\/A|n\/a|N\/A|Unknown|unknown|UNKNOWN|Undefined|undefined|UNDEFINED|Not Available|Not available|not available|NOT AVAILABLE)[->]?\.?/i',
            '/File [Ee]xtensions?(?:\(s\))?\s*:\s*-\s*$/i',
            '/File [Ee]xtensions?(?:\(s\))?\s*:\s*-\s*[\n\r]/i',
            '/File [Ee]xtensions?(?:\(s\))?\s*:\s*-\\\\n\s*$/i',
            '/File [Ee]xtensions?(?:\(s\))?\s*:\s*any/i',
        ];

        // Skip patterns for non-standard structures
        $skipNonStandardPatterns = [
            '/.*:.*File extension\(s\)/i',
            '/File [Ee]xtensions?(?:\(s\))?\s*:\s*"[^"]+"\s*\(e\.g\./i',
            '/File [Ee]xtensions?(?:\(s\))?\s*:\s*"[^"]+"\s+is/i',
            '/File [Ee]xtensions?(?:\(s\))?\s*:\s*.*\s+"[^"]+"\s+.*\s+(suggested|recommended|is)/i',
            '/File [Ee]xtensions?(?:\(s\))?\s*:\s*Files do not require/i',
            '/File [Ee]xtensions?(?:\(s\))?\s*:\s*Generally/i',
            '/File [Ee]xtensions?(?:\(s\))?\s*:\s*In the event.*/i',
            '/File [Ee]xtensions?(?:\(s\))?\s*:\s*No specific.*extensions.*recommended/i',
            '/File [Ee]xtensions?(?:\(s\))?\s*:\s*RFC\s+\d+.*does not require.*file extension/i',
            '/File [Ee]xtensions?(?:\(s\))?\s*:\s*See registration of/i',
            '/File [Ee]xtensions?(?:\(s\))?\s*:\s*The extension/i',
            '/File [Ee]xtensions?(?:\(s\))?\s*:\s*The file extension of/i',
            '/File [Ee]xtensions?(?:\(s\))?\s*:\s*This media type does not propose/i',
            '/File [Ee]xtensions?(?:\(s\))?\s*:\s*\n/i',
            '/File [Ee]xtensions?(?:\(s\))?\s*:\s*\w+\s*\([^)]*\)\s+\w+\s*\([^)]*\)/i',
            '/File [Ee]xtensions?(?:\(s\))?\s*:\s*not expected to be stored in files/i',
            '/File [Ee]xtensions?(?:\(s\))?\s*:\s*See Section.*RFC/i',
            '/File [Ee]xtensions?(?:\(s\))?\s*:\s*SOAP messages are not required/i',
            '/File [Ee]xtensions?(?:\(s\))?\s*:\s*Suggest.*for general purpose/i',
        ];

        // Also try a more specific pattern for extensions with leading dots
        $dotPatterns = [
            '/File [Ee]xtensions?(?:\(s\))?\s*:\s*([.a-zA-Z0-9,\s]+?)(?:\n|$)/i',
        ];

        // Also try a pattern that stops at newlines more precisely
        $precisePatterns = [
            '/File [Ee]xtensions?(?:\(s\))?\s*:\s*([^\n\r]+?)(?:\n|$)/i',
        ];

        // Pattern for extensions that end with comma and newline
        $trailingCommaPatterns = [
            '/File [Ee]xtensions?(?:\(s\))?\s*:\s*([^\n\r]+),?\s*\n/i',
        ];

        // Initialize extensions array
        $extensions = [];

        // Loop through the skip patterns
        foreach ($skipPatterns as $pattern) {
            // Check if the pattern matches the content
            if (
                \preg_match(
                    $pattern,
                    $content
                )
            ) {
                // Found a skip pattern, return empty array
                return $extensions;
            }
        }

        // Loop through the non-standard skip patterns
        foreach ($skipNonStandardPatterns as $pattern) {
            // Check if the pattern matches the content
            if (
                \preg_match(
                    $pattern,
                    $content
                )
            ) {
                // Found a non-standard skip pattern, return empty array
                return $extensions;
            }
        }

        // Loop through the trailing comma patterns (before other patterns)
        foreach ($trailingCommaPatterns as $pattern) {
            // Check if pattern matches content
            if (
                preg_match_all(
                    $pattern,
                    $content,
                    $matches,
                    PREG_SET_ORDER
                )
            ) {
                // Loop through pattern matches
                foreach ($matches as $match) {
                    $extensionText = \trim($match[1]);
                    // Check if extension text is empty or whitespace only
                    if ($extensionText === '') {
                        // Skip empty extension text
                        continue;
                    }

                    if (\preg_match(
                        '/^\s*$/',
                        $extensionText
                    )) {
                        // Skip empty extension text
                        continue;
                    }

                    // Parse the extensions from the text
                    $parsedExtensions = $this->parseExtensionText($extensionText);

                    // Check if parsed extensions were found
                    if ($parsedExtensions !== []) {
                        // Merge parsed extensions into extensions array
                        $extensions = \array_merge(
                            $extensions,
                            $parsedExtensions
                        );
                    }
                }
            }
        }

        // Loop through the main patterns
        foreach ($patterns as $pattern) {
            // Check if pattern matches content
            if (
                preg_match_all(
                    $pattern,
                    $content,
                    $matches,
                    PREG_SET_ORDER
                )
            ) {
                // Loop through pattern matches
                foreach ($matches as $match) {
                    // Extract and trim extension text from match
                    $extensionText = \trim($match[1]);
                    // Check if extension text is empty or whitespace only
                    if ($extensionText === '') {
                        // Skip empty extension text
                        continue;
                    }

                    if (\preg_match(
                        '/^\s*$/',
                        $extensionText
                    )) {
                        // Skip empty extension text
                        continue;
                    }

                    // Parse the extensions from the text
                    $parsedExtensions = $this->parseExtensionText($extensionText);

                    // Check if parsed extensions were found
                    if ($parsedExtensions !== []) {
                        // Merge parsed extensions into extensions array
                        $extensions = \array_merge(
                            $extensions,
                            $parsedExtensions
                        );
                    }
                }
            }
        }

        // Loop through the dot patterns
        foreach ($dotPatterns as $pattern) {
            // Check if pattern matches content
            if (
                preg_match_all(
                    $pattern,
                    $content,
                    $matches,
                    PREG_SET_ORDER
                )
            ) {
                // Loop through pattern matches
                foreach ($matches as $match) {
                    // Extract and trim extension text from match
                    $extensionText = \trim($match[1]);
                    // Check if extension text is empty or whitespace only
                    if ($extensionText === '') {
                        // Skip empty extension text
                        continue;
                    }

                    if (\preg_match(
                        '/^\s*$/',
                        $extensionText
                    )) {
                        // Skip empty extension text
                        continue;
                    }

                    // Parse the extensions from the text
                    $parsedExtensions = $this->parseExtensionText($extensionText);

                    // Check if parsed extensions were found
                    if ($parsedExtensions !== []) {
                        // Merge parsed extensions into extensions array
                        $extensions = \array_merge(
                            $extensions,
                            $parsedExtensions
                        );
                    }
                }
            }
        }

        // Loop through the precise patterns
        foreach ($precisePatterns as $pattern) {
            // Check if pattern matches content
            if (
                preg_match_all(
                    $pattern,
                    $content,
                    $matches,
                    PREG_SET_ORDER
                )
            ) {
                // Loop through pattern matches
                foreach ($matches as $match) {
                    // Extract and trim extension text from match
                    $extensionText = \trim($match[1]);
                    // Check if extension text is empty or whitespace only
                    if ($extensionText === '') {
                        // Skip empty extension text
                        continue;
                    }

                    if (\preg_match(
                        '/^\s*$/',
                        $extensionText
                    )) {
                        // Skip empty extension text
                        continue;
                    }

                    // Parse the extensions from the text
                    $parsedExtensions = $this->parseExtensionText($extensionText);

                    // Check if parsed extensions were found
                    if ($parsedExtensions !== []) {
                        // Merge parsed extensions into extensions array
                        $extensions = \array_merge(
                            $extensions,
                            $parsedExtensions
                        );
                    }
                }
            }
        }

        // Check if no extensions were found yet
        if ($extensions === []) {
            // Loop through clean patterns as fallback
            foreach ($cleanPatterns as $pattern) {
                // Check if pattern matches content
                if (
                    preg_match_all(
                        $pattern,
                        $content,
                        $matches,
                        PREG_SET_ORDER
                    )
                ) {
                    // Loop through pattern matches
                    foreach ($matches as $match) {
                        // Extract and trim extension text from match
                        $extensionText = \trim($match[1]);
                        // Check if extension text is empty or whitespace only
                        if ($extensionText === '') {
                            // Skip empty extension text
                            continue;
                        }

                        if (\preg_match(
                            '/^\s*$/',
                            $extensionText
                        )) {
                            // Skip empty extension text
                            continue;
                        }

                        // Parse the extensions from the text
                        $parsedExtensions = $this->parseExtensionText($extensionText);

                        // Check if parsed extensions were found
                        if ($parsedExtensions !== []) {
                            // Merge parsed extensions into extensions array
                            $extensions = \array_merge(
                                $extensions,
                                $parsedExtensions
                            );
                        }
                    }
                }
            }
        }

        // Remove duplicates and normalize
        $extensions = array_unique(
            \array_map(
                strtolower(...),
                $extensions
            )
        );

        // Return parsed extensions array
        return $extensions;
    }

    /**
     * Parse extension text from IANA detail page.
     *
     * @param  string  $extensionText  The text containing extensions (e.g., "exr", ".apng", "avif, heif, heifs or
     *                                 hif")
     * @return array<int,string> Array of extensions (without leading dots)
     */
    public function parseExtensionText(
        string $extensionText
    ): array {
        $extensions = [];

        // Check if extension text matches "not applicable" patterns
        if (
            \preg_match(
                '/^(not applicable|NOT APPLICABLE|Not applicable|Not Applicable|None|none|NONE|N\/A|n\/a|N\/A|Unknown|unknown|UNKNOWN|Undefined|undefined|UNDEFINED)\.?$/i',
                $extensionText
            )
        ) {
            // Return empty array for not applicable cases
            return $extensions;
        }

        // Check if extension text is just a dash
        if (
            \preg_match(
                '/^\s*-\s*$/s',
                $extensionText
            )
        ) {
            // Return empty array for dash-only text
            return $extensions;
        }

        // Check if extension text contains non-standard parenthetical structures
        if (
            \preg_match(
                '/\w+\s*\([^)]*\)\s+\w+\s*\([^)]*\)/',
                $extensionText
            )
        ) {
            // Return empty array for non-standard structures
            return $extensions;
        }

        // ---
        // Check for quoted non-standard structures with descriptive text
        // (e.g., '".dcm" is recommended')
        // ---
        if (
            \preg_match(
                '/^"[^"]+"\s+is\s+/i',
                $extensionText
            )
        ) {
            // Return empty array for quoted descriptive structures
            return $extensions;
        }

        // Remove any parenthetical content first before splitting
        $extensionTextWithoutParentheses = \preg_replace(
            '/\s*\([^)]*\)/',
            '',
            $extensionText
        ) ?? $extensionText;

        // Check if extension text contains spaces without valid delimiters
        // (check after removing parenthetical content to catch cases like
        // "def (for subtype def)" which should be skipped)
        if (
            \preg_match(
                '/\s/',
                $extensionTextWithoutParentheses
            )
            && ! \preg_match(
                '/[,;]|\s+or\s+|\s+and\s+/i',
                $extensionTextWithoutParentheses
            )
        ) {
            // Return empty array for invalid spacing
            return $extensions;
        }

        // Use the text without parentheses for further processing
        $extensionText = $extensionTextWithoutParentheses;

        // Split by common delimiters: comma, semicolon, "or", "and"
        $parts = preg_split(
            '/[,;]|\s+or\s+|\s+and\s+/i',
            $extensionText
        );

        // Loop through split parts to process each extension
        foreach (($parts === false ? [] : $parts) as $part) {
            // Trim whitespace from part
            $part = \trim($part);

            // Check if part is empty after trimming
            if ($part === '') {
                // Skip empty parts
                continue;
            }

            // Check if part is just a dash
            if (
                \preg_match(
                    '/^\s*-\s*$/',
                    $part
                )
            ) {
                // Skip dash-only parts
                continue;
            }

            // Check if part contains hash symbol
            if (
                str_contains(
                    $part,
                    '#'
                )
            ) {
                // Skip parts with hash symbols
                continue;
            }

            // Check if part matches "not applicable" patterns
            if (
                \preg_match(
                    '/^(not applicable|NOT APPLICABLE|Not applicable|Not Applicable|None|none|NONE|N\/A|n\/a|N\/A|Unknown|unknown|UNKNOWN|Undefined|undefined|UNDEFINED)\.?$/i',
                    $part
                )
            ) {
                // Skip not applicable parts
                continue;
            }

            // Check for wildcard extensions (e.g., "*.json" -> "json", "*.123.4" -> "123.4")
            if (
                \preg_match(
                    '/^\*\.([a-zA-Z0-9.<>]+)$/',
                    $part,
                    $matches
                )
            ) {
                // Extract extension from wildcard pattern and normalize to lowercase
                $extension = \strtolower($matches[1]);

                // ---
                // Check for numbered pattern expansion within wildcard
                // (e.g., "*.abc<n>" -> ["abc0", "abc1", ..., "abc9"])
                // ---
                if (
                    \preg_match(
                        '/^(.+)<n>$/',
                        $extension,
                        $numberMatches
                    )
                ) {
                    // Extract base extension for pattern expansion
                    $baseExtension = $numberMatches[1];

                    // Generate extensions for digits 0-9
                    for ($i = 0; $i <= 9; $i++) {
                        // Add numbered extension variant
                        $extensions[] = \strtolower($baseExtension.$i);
                    }
                } else { // Otherwise
                    // Add single extension from wildcard
                    $extensions[] = $extension;

                }

                // Skip to next part after processing wildcard
                continue;
            }

            // Check for certain wildcard patterns that are non-standard
            if (
                \preg_match(
                    '/^\*\.(finf|jpm|jpgm)$/',
                    $part
                )
            ) {
                // Skip non-standard wildcard patterns
                continue;
            }

            // Check for wildcard patterns that are non-standard (more comprehensive)
            if (
                \preg_match(
                    '/^\*\./',
                    $part
                )
                && ! \preg_match(
                    '/^\*\.([a-zA-Z0-9]+)$/',
                    $part
                )
            ) {
                // Skip non-standard wildcard patterns
                continue;
            }

            // Remove any trailing content after the extension
            $part = \preg_replace(
                '/\s+.*$/',
                '',
                $part
            ) ?? $part;

            // Remove trailing colons
            $part = \rtrim(
                $part,
                ':'
            );

            // ---
            // Check for quoted extensions
            // (e.g., '"vtt"' -> 'vtt', '".abc"' -> 'abc', '"*.abc"' -> 'abc', "'abc'" -> 'abc')
            // ---
            if (
                \preg_match(
                    '/^"([^"]+)"$/',
                    $part,
                    $matches
                )
            ) {
                // Extract extension from double-quoted string
                $extension = $matches[1];

                // Remove leading dot if present
                $extension = \ltrim(
                    $extension,
                    '.'
                );

                // Check for wildcards
                if (
                    \preg_match(
                        '/^\*\.(.+)$/',
                        $extension,
                        $wildcardMatches
                    )
                ) {
                    // Extract extension from wildcard pattern
                    $extension = $wildcardMatches[1];
                }
            } elseif (
                \preg_match(
                    '/^\'([^\']+)\'$/',
                    $part,
                    $matches
                )
            ) { // Check if part matches single-quoted string pattern
                // Extract extension from single-quoted string
                $extension = $matches[1];

                // Remove leading dot if present
                $extension = \ltrim(
                    $extension,
                    '.'
                );

                // Check for wildcards
                if (
                    \preg_match(
                        '/^\*\.(.+)$/',
                        $extension,
                        $wildcardMatches
                    )
                ) {
                    // Extract extension from wildcard pattern
                    $extension = $wildcardMatches[1];
                }
            } else { // Otherwise
                // Remove leading dot if present
                $extension = \ltrim(
                    $part,
                    '.'
                );
            }

            // Check if the extension is empty after removing dot
            if ($extension === '') {
                // Skip empty extensions
                continue;
            }

            // Trim again after processing
            $extension = \trim($extension);

            // Check if the extension is empty after processing
            if ($extension === '') {
                // Skip empty extensions after trimming
                continue;
            }

            // Check if the extension contains spaces (likely not a valid extension)
            if (
                str_contains(
                    $extension,
                    ' '
                )
            ) {
                // Skip extensions with spaces
                continue;
            }

            // Check if the original part contained spaces (for cases like "invalid extension")
            if (
                str_contains(
                    $part,
                    ' '
                )
            ) {
                // Skip parts with spaces
                continue;
            }

            // Check if the extension still contains quotes (likely part of a non-standard structure)
            if (
                str_contains(
                    $extension,
                    '"'
                )
            ) {
                // Skip extensions with quotes
                continue;
            }

            // Check if the extension is just a wildcard
            if ($extension === '*') {
                // Skip wildcard-only extensions
                continue;
            }

            // Check if the extension is "n/a" (not applicable)
            if (\strtolower($extension) === 'n/a') {
                // Skip not applicable extensions
                continue;
            }

            // Check if the extension contains hash symbols (likely not a valid extension)
            if (
                str_contains(
                    $extension,
                    '#'
                )
            ) {
                // Skip extensions with hash symbols
                continue;
            }

            // ---
            // Check for numbered pattern expansion
            // (e.g., "abc<n>" -> ["abc0", "abc1", ..., "abc9"])
            // ---
            if (
                \preg_match(
                    '/^(.+)<n>$/',
                    $extension,
                    $matches
                )
            ) {
                // Extract base extension for pattern expansion
                $baseExtension = $matches[1];

                // Generate extensions for digits 0-9
                for ($i = 0; $i <= 9; $i++) {
                    // Add numbered extension variant
                    $extensions[] = \strtolower($baseExtension.$i);
                }

                // Skip to next part after processing pattern expansion
                continue;
            }

            // Add normalized extension to array
            $extensions[] = \strtolower($extension);
        }

        // Return parsed extensions array
        return $extensions;
    }

    /**
     * Generate case ID from MIME type.
     *
     * @param  string  $mimeType  The MIME type string (e.g., 'application/pdf')
     * @return string Case identifier in the form UPPER_SNAKE_CASE
     */
    public function generateCaseId(
        string $mimeType
    ): string {
        // Replace special characters with underscores
        $normalized = \str_replace(
            [
                '/',
                '+',
                '-',
                '.',
            ],
            '_',
            $mimeType
        );

        // Convert to uppercase
        $upperCase = \strtoupper($normalized);

        // Trim any leading/trailing underscores
        $trimmed = \trim(
            $upperCase,
            '_'
        );

        // Clean up variables to free up memory
        unset(
            $normalized,
            $upperCase
        );

        // Return trimmed case ID
        return $trimmed;
    }

    /**
     * Generate the PHP content for the MimeType enum.
     *
     * @param  array<int, array{mimeType: string, extensions: array<int,string>, category: string, caseId: string, originalMimeType?: string}>  $mimeTypes
     * @param  array<int,string>  $dataSourceLines  Lines to embed in the Data Source section
     */
    public function generatePhpContent(
        array $mimeTypes,
        array $dataSourceLines = []
    ): string {
        // Header: open PHP tag and start generated documentation block
        $content = "<?php\n\n";
        $content .= "/**\n";
        $content .= " * Generated MIME Types enum cases\n";
        $content .= " *\n";
        $content .= " * INSTRUCTIONS:\n";
        $content .= " * 1. Copy all the case statements below (from the first case to the\n";
        $content .= " *    closing brace)\n";
        $content .= " * 2. Open app/Enums/MimeType.php\n";
        $content .= " * 3. Replace the existing case list in the MimeType enum with the\n";
        $content .= " *    copied cases\n";
        $content .= " * 4. Keep the MimeTypeInfo attribute class and any other methods in\n";
        $content .= " *    MimeType.php\n";
        $content .= " * 5. Save the file\n";
        $content .= " *\n";
        $content .= " * Data Source:\n";

        // Check if a source list was provided
        if ($dataSourceLines !== []) {
            // Loop through data source lines to add to documentation
            foreach ($dataSourceLines as $line) {
                // Prepend each line with a bullet for readability inside the block
                $content .= ' * '.\rtrim($line)."\n";
            }
        } else { // Otherwise, include a simple placeholder
            // Fallback note (should not occur; kept for safety)
            $content .= " * - Apache MIME Types\n";
        }

        // Footer spacing in the docblock
        $content .= " *\n";
        $content .= ' * This list of cases was generated on '.\date('Y-m-d').' at '.\date('H:i:s T')."\n";
        $content .= ' * Total MIME types: '.\count($mimeTypes)."\n";
        $content .= " */\n\n";
        $content .= "enum MimeType: string\n";
        $content .= "{\n";

        // Loop through MIME types to generate enum cases
        foreach ($mimeTypes as $mimeType) {
            // Escape single quotes in the mimeType for valid PHP string literal
            $escapedMimeType = \str_replace(
                "'",
                "\\'",
                $mimeType['mimeType']
            );

            // Check if the extensions array is empty
            if (empty($mimeType['extensions'])) {
                // Set the extensions array to an empty array
                $extensionsArray = '[]';
            } else { // Otherwise
                // Prepare extensions array as PHP array literal
                $extensionsArray = "['".\implode(
                    "', '",
                    \array_map(
                        fn ($ext): string => \str_replace(
                            "'",
                            "\\'",
                            $ext
                        ),
                        $mimeType['extensions']
                    )
                )."']";
            }

            // Escape single quotes in the category for valid PHP string literal
            $escapedCategory = \str_replace(
                "'",
                "\\'",
                $mimeType['category']
            );

            // Attribute + case line
            $content .= "\t#[MimeTypeInfo(mimeType: '{$escapedMimeType}', extensions: {$extensionsArray}, category: '{$escapedCategory}')]\n";
            $content .= "\tcase {$mimeType['caseId']} = '{$mimeType['caseId']}';\n\n";
        }

        // Close enum definition
        $content .= "}\n";

        // Return full file content for writing
        return $content;
    }
}
