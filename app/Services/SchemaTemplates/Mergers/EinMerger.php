<?php

declare(strict_types=1);

namespace App\Services\SchemaTemplates\Mergers;

/**
 * Merges Employer Identification Number (identifiers tab) into schema node.
 *
 * Adds taxID (string) and identifier (PropertyValue).
 */
class EinMerger implements SchemaIdentifierMergerInterface
{
    /**
     * {@inheritDoc}
     */
    public function sourceKey(): string
    {
        return 'ein';
    }

    /**
     * {@inheritDoc}
     */
    public function buildFragment(string $value): array
    {
        return [
            'taxID' => $value,
            'identifier' => [
                '@type' => 'PropertyValue',
                'name' => 'Employer Identification Number',
                'alternateName' => [
                    'EIN',
                    'Federal Employer Identification Number',
                    'FEIN',
                    'Federal Tax Identification Number',
                    'FTIN',
                    'Taxpayer Identification Number',
                    'TIN',
                ],
                'propertyID' => [
                    'https://www.wikidata.org/wiki/Property:P1297',
                    'https://purl.org/ctdl/terms/fein',
                ],
                'value' => $value,
            ],
        ];
    }
}
