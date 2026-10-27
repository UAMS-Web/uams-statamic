<?php

declare(strict_types=1);

namespace App\Services\SchemaTemplates\Mergers;

/**
 * Merges ORCID iD (identifiers tab) into schema node.
 *
 * Adds identifier (PropertyValue) only.
 */
class OrcidMerger implements SchemaIdentifierMergerInterface
{
    /**
     * {@inheritDoc}
     */
    public function sourceKey(): string
    {
        return 'orcid';
    }

    /**
     * {@inheritDoc}
     */
    public function buildFragment(string $value): array
    {
        return [
            'identifier' => [
                '@type' => 'PropertyValue',
                'name' => 'ORCID iD',
                'alternateName' => [
                    'ORC ID number',
                    'ORC ID',
                    'ORCID iD number',
                    'ORCID iD',
                    'ORCID identifier',
                    'ORCID number',
                    'ORCID',
                    'Open Research Contributor ID number',
                    'Open Research Contributor ID',
                    'Open Research Contributor identifier',
                    'Open Researcher and Contributor ID number',
                    'Open Researcher and Contributor ID',
                    'Open Researcher and Contributor identifier',
                ],
                'propertyID' => 'https://www.wikidata.org/wiki/Property:P496',
                'value' => $value,
            ],
        ];
    }
}
