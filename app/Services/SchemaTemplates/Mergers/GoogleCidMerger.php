<?php

declare(strict_types=1);

namespace App\Services\SchemaTemplates\Mergers;

/**
 * Merges Google Maps Customer ID (identifiers tab) into schema node.
 *
 * Adds identifier (PropertyValue) only.
 */
class GoogleCidMerger implements SchemaIdentifierMergerInterface
{
    /**
     * {@inheritDoc}
     */
    public function sourceKey(): string
    {
        return 'google_business_profile_cid';
    }

    /**
     * {@inheritDoc}
     */
    public function buildFragment(string $value): array
    {
        return [
            'identifier' => [
                '@type' => 'PropertyValue',
                'name' => 'Google Maps Customer ID',
                'alternateName' => [
                    'CID number',
                    'CID',
                    'GM CID number',
                    'GM CID',
                    'GMCID number',
                    'GMCID',
                    'Google CID number',
                    'Google CID',
                    'Google Maps CID number',
                    'Google Maps CID',
                    'Google Maps Customer ID number',
                    'Google Maps Place CID number',
                    'Google Maps Place CID',
                    'Ludocid number',
                    'Ludocid',
                ],
                'propertyID' => 'https://www.wikidata.org/wiki/Property:P3749',
                'value' => $value,
            ],
        ];
    }
}
