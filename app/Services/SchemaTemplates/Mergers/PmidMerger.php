<?php

declare(strict_types=1);

namespace App\Services\SchemaTemplates\Mergers;

/**
 * Merges PubMed publication ID (identifiers tab) into schema node.
 *
 * Adds identifier (PropertyValue) only.
 */
class PmidMerger implements SchemaIdentifierMergerInterface
{
    /**
     * {@inheritDoc}
     */
    public function sourceKey(): string
    {
        return 'pmid';
    }

    /**
     * {@inheritDoc}
     */
    public function buildFragment(string $value): array
    {
        return [
            'identifier' => [
                '@type' => 'PropertyValue',
                'name' => 'PubMed publication ID',
                'alternateName' => [
                    'PMID',
                    'PubMed ID',
                    'PubMed reference number',
                ],
                'propertyID' => 'https://www.wikidata.org/wiki/Property:P698',
                'value' => $value,
            ],
        ];
    }
}
