<?php

declare(strict_types=1);

namespace App\Services\SchemaTemplates\Mergers;

/**
 * Merges VA STA3N / station ID (identifiers tab) into schema node.
 *
 * Adds identifier (PropertyValue) only.
 */
class VaStationIdMerger implements SchemaIdentifierMergerInterface
{
    /**
     * {@inheritDoc}
     */
    public function sourceKey(): string
    {
        return 'va_station_id';
    }

    /**
     * {@inheritDoc}
     */
    public function buildFragment(string $value): array
    {
        return [
            'identifier' => [
                '@type' => 'PropertyValue',
                'name' => 'STA3N',
                'alternateName' => [
                    'Department of Veterans Affairs STA3N',
                    'Department of Veterans Affairs facility #',
                    'Department of Veterans Affairs facility ID',
                    'Department of Veterans Affairs facility identifier',
                    'Department of Veterans Affairs facility number',
                    'Department of Veterans Affairs facilityId',
                    'Department of Veterans Affairs institutionCode',
                    'Department of Veterans Affairs location ID',
                    'Department of Veterans Affairs location identifier',
                    'Department of Veterans Affairs location number',
                    'Department of Veterans Affairs locationid',
                    'Department of Veterans Affairs parent station',
                    'Department of Veterans Affairs sta3nid code',
                    'Department of Veterans Affairs sta3nid',
                    'Department of Veterans Affairs station ID',
                    'Department of Veterans Affairs station code',
                    'Department of Veterans Affairs station identifier',
                    'Department of Veterans Affairs station number',
                    'Department of Veterans Affairs stationID',
                    'STA3N',
                    'U.S. Department of Veterans Affairs STA3N',
                    'U.S. Department of Veterans Affairs facility #',
                    'U.S. Department of Veterans Affairs facility ID',
                    'U.S. Department of Veterans Affairs facility identifier',
                    'U.S. Department of Veterans Affairs facility number',
                    'U.S. Department of Veterans Affairs facilityId',
                    'U.S. Department of Veterans Affairs institutionCode',
                    'U.S. Department of Veterans Affairs location ID',
                    'U.S. Department of Veterans Affairs location identifier',
                    'U.S. Department of Veterans Affairs location number',
                    'U.S. Department of Veterans Affairs locationid',
                    'U.S. Department of Veterans Affairs parent station',
                    'U.S. Department of Veterans Affairs sta3nid code',
                    'U.S. Department of Veterans Affairs sta3nid',
                    'U.S. Department of Veterans Affairs station ID',
                    'U.S. Department of Veterans Affairs station code',
                    'U.S. Department of Veterans Affairs station identifier',
                    'U.S. Department of Veterans Affairs station number',
                    'U.S. Department of Veterans Affairs stationID',
                    'United States Department of Veterans Affairs STA3N',
                    'United States Department of Veterans Affairs facility #',
                    'United States Department of Veterans Affairs facility ID',
                    'United States Department of Veterans Affairs facility identifier',
                    'United States Department of Veterans Affairs facility number',
                    'United States Department of Veterans Affairs facilityId',
                    'United States Department of Veterans Affairs institutionCode',
                    'United States Department of Veterans Affairs location ID',
                    'United States Department of Veterans Affairs location identifier',
                    'United States Department of Veterans Affairs location number',
                    'United States Department of Veterans Affairs locationid',
                    'United States Department of Veterans Affairs parent station',
                    'United States Department of Veterans Affairs sta3nid code',
                    'United States Department of Veterans Affairs sta3nid',
                    'United States Department of Veterans Affairs station ID',
                    'United States Department of Veterans Affairs station code',
                    'United States Department of Veterans Affairs station identifier',
                    'United States Department of Veterans Affairs station number',
                    'United States Department of Veterans Affairs stationID',
                    'VA STA3N',
                    'VA facility #',
                    'VA facility ID',
                    'VA facility identifier',
                    'VA facility number',
                    'VA facilityId',
                    'VA institutionCode',
                    'VA location ID',
                    'VA location identifier',
                    'VA location number',
                    'VA locationid',
                    'VA parent station',
                    'VA sta3nid code',
                    'VA sta3nid',
                    'VA station ID',
                    'VA station code',
                    'VA station identifier',
                    'VA station number',
                    'VA stationID',
                    'Veterans Affairs STA3N',
                    'Veterans Affairs facility #',
                    'Veterans Affairs facility ID',
                    'Veterans Affairs facility identifier',
                    'Veterans Affairs facility number',
                    'Veterans Affairs facilityId',
                    'Veterans Affairs institutionCode',
                    'Veterans Affairs location ID',
                    'Veterans Affairs location identifier',
                    'Veterans Affairs location number',
                    'Veterans Affairs locationid',
                    'Veterans Affairs parent station',
                    'Veterans Affairs sta3nid code',
                    'Veterans Affairs sta3nid',
                    'Veterans Affairs station ID',
                    'Veterans Affairs station code',
                    'Veterans Affairs station identifier',
                    'Veterans Affairs station number',
                    'Veterans Affairs stationID',
                    'facility #',
                    'facility ID',
                    'facility identifier',
                    'facility number',
                    'facilityId',
                    'institutionCode',
                    'location ID',
                    'location identifier',
                    'location number',
                    'locationid',
                    'parent station',
                    'sta3nid code',
                    'sta3nid',
                    'station ID',
                    'station code',
                    'station identifier',
                    'station number',
                    'stationID',
                ],
                'value' => $value,
            ],
        ];
    }
}
