<?php

declare(strict_types=1);

namespace App\Services\SchemaTemplates\Mergers;

/**
 * Merges IPEDS ID (identifiers tab) into schema node.
 *
 * Adds identifier (PropertyValue) only.
 */
class IpedsMerger implements SchemaIdentifierMergerInterface
{
    /**
     * {@inheritDoc}
     */
    public function sourceKey(): string
    {
        return 'ipeds';
    }

    /**
     * {@inheritDoc}
     */
    public function buildFragment(string $value): array
    {
        return [
            'identifier' => [
                '@type' => 'PropertyValue',
                'name' => 'Integrated Postsecondary Education Data System ID',
                'alternateName' => [
                    'IPEDS ID number',
                    'IPEDS ID',
                    'IPEDS Number',
                    'Integrated Postsecondary Education Data System ID number',
                    'Integrated Postsecondary Education Data System ID',
                    'Integrated Postsecondary Education Data System Number',
                    'Integrated Postsecondary Education Data System identifier number',
                    'Integrated Postsecondary Education Data System identifier',
                    'Unit ID code',
                    'Unit ID number',
                    'Unit ID',
                    'Unit Identification Code',
                    'UnitID code',
                    'UnitID number',
                    'UnitID',
                ],
                'propertyID' => [
                    'https://www.wikidata.org/wiki/Property:P1771',
                    'https://purl.org/ctdl/terms/ipedsID',
                ],
                'value' => $value,
            ],
        ];
    }
}
