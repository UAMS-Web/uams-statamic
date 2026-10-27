<?php

declare(strict_types=1);

namespace App\Services\EnumGeneration;

/**
 * Utility class for interacting with TIGERweb APIs.
 *
 * Provides methods to fetch geographic data from various TIGERweb MapServer
 * endpoints.
 */
class TigerWebUtils
{
    /**
     * Common TIGERweb attribute fields shared across geographic entities.
     *
     * @var array<string>
     */
    private static array $commonOutFields = [
        'NAME',
        'BASENAME',
        'STATE',
        'GEOID',
        'FUNCSTAT',
        'MTFCC',
        'OID',
        'LSADC',
        'AREALAND',
        'AREAWATER',
        'OBJECTID',
        'CENTLAT',
        'CENTLON',
        'INTPTLAT',
        'INTPTLON',
    ];

    /**
     * Fetch records from a TIGERweb MapServer endpoint.
     *
     * @param  string  $servicePath  The service path (e.g., 'State_County', 'ACS2025')
     * @param  int  $layerNumber  The layer number to query
     * @param  array<string>  $outFields  The fields to request from the API
     * @param  bool  $returnGeometry  Whether to return geometry data (default: false)
     * @return ?array<int, array<string, string>> Array of records or null on failure
     */
    public static function fetchMapServerRecords(
        string $servicePath,
        int $layerNumber,
        array $outFields,
        bool $returnGeometry = false
    ): ?array {
        // ---
        // Build a comma-separated list of outFields and URL-encode it for use in
        // the query string
        // ---
        $outFieldsParam = rawurlencode(
            \implode(
                ',',
                $outFields
            )
        );

        // Build the URL for the TIGERweb MapServer API request.
        $url = sprintf('https://tigerweb.geo.census.gov/arcgis/rest/services/TIGERweb/%s/MapServer/%d/query', $servicePath, $layerNumber)
            .'?where=1%3D1'
            .'&outFields='.$outFieldsParam
            .'&returnGeometry='.($returnGeometry ? 'true' : 'false')
            .'&f=json';

        // Make the HTTP GET request to the TIGERweb API.
        $json = GeneratorUtils::httpGet(
            $url,
            [
                'Accept' => 'application/json,*/*',
                'Accept-Language' => 'en-US,en;q=0.9',
                'Connection' => 'keep-alive',
            ],
            60,
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
        );

        // Check if the request fails
        if ($json === null) {
            // Return null to indicate HTTP request failure
            return null;
        }

        // Decode the JSON response into an associative array
        $data = \json_decode(
            $json,
            true
        );

        // ---
        // Validate the response shape and bail if the API returned an error
        // ---

        // Check if the decoded data is invalid or contains an error response
        if (
            ! \is_array($data)
            || isset($data['error'])
        ) {
            // Return null when response structure is invalid or contains errors
            return null;
        }

        // Extract the features array that contains records
        $features = $data['features'] ?? [];

        // Guard against a non-iterable features value before looping
        if (! \is_array($features)) {
            // Treat a malformed features value as an empty result set
            $features = [];
        }

        /**
         * Output accumulator for normalized attribute arrays.
         *
         * @var array<int, array<string, string>> $out
         */
        $out = [];

        // Iterate through features and collect attributes from each feature
        foreach ($features as $feat) {
            // Attributes hold the flat properties we requested via outFields
            $attrs = \is_array($feat) ? ($feat['attributes'] ?? null) : null;

            // ---
            // Skip any malformed entries
            // ---

            // Check if attributes are missing or not an array
            if (! \is_array($attrs)) {
                // Skip this feature and continue to the next iteration
                continue;
            }

            // Narrow the attribute map to the documented flat string shape
            /** @var array<string, string> $record */
            $record = $attrs;

            // Append to the output list
            $out[] = $record;
        }

        // Clean up variables to free up memory
        unset(
            $features,
            $data,
            $json,
        );

        // Hint the GC since this can process thousands of features
        gc_collect_cycles();

        // Return the list of attribute arrays for downstream mapping
        return $out;
    }

    /**
     * Fetch county records from TIGERweb State_County service.
     *
     * @param  int  $layerNumber  The layer number to query
     * @return ?array<int, array<string, string>> Array of county records or null on failure
     */
    public static function fetchCountyRecords(
        int $layerNumber
    ): ?array {
        // ---
        // Combine common fields with county-specific fields to create the list of fields
        // to request from the API
        // ---
        $outFields = \array_merge(
            [
                'COUNTY',
                'COUNTYNS',
                'COUNTYCC',
            ],
            self::$commonOutFields
        );

        // Fetch county records from the TIGERweb State_County service
        return self::fetchMapServerRecords(
            'State_County',
            $layerNumber,
            $outFields,
            false
        );
    }

    /**
     * Fetch state records from TIGERweb State_County service.
     *
     * @param  int  $layerNumber  The layer number to query (typically 0 for states)
     * @return ?array<int, array<string, string>> Array of state records or null on failure
     */
    public static function fetchStateRecords(
        int $layerNumber
    ): ?array {
        // Combine common fields with state-specific fields
        $outFields = \array_merge(
            [
                'STUSAB',
                'STATENS',
                'DIVISION',
                'REGION',
            ],
            self::$commonOutFields
        );

        // Fetch state records from the TIGERweb State_County service
        return self::fetchMapServerRecords(
            'State_County',
            $layerNumber,
            $outFields,
            false
        );
    }
}
