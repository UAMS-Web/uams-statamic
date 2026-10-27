<?php

declare(strict_types=1);

namespace App\Services\SchemaTemplates\Mergers;

/**
 * Merges ResearcherID (identifiers tab) into schema node.
 *
 * Adds identifier (PropertyValue) only.
 */
class ResearcherIdMerger implements SchemaIdentifierMergerInterface
{
    /**
     * {@inheritDoc}
     */
    public function sourceKey(): string
    {
        return 'researcherid';
    }

    /**
     * {@inheritDoc}
     */
    public function buildFragment(string $value): array
    {
        return [
            'identifier' => [
                '@type' => 'PropertyValue',
                'name' => 'ResearcherID',
                'alternateName' => [
                    'RID',
                    'Researcher ID',
                    'Web of Science ID',
                    'Web of Science RID',
                    'Web of Science Researcher ID',
                    'Web of Science ResearcherID',
                    'WoS ID',
                    'WoS RID',
                    'WoS Researcher ID',
                    'WoS ResearcherID',
                    'webofscience.com ID',
                    'webofscience.com RID',
                    'webofscience.com Researcher ID',
                    'webofscience.com ResearcherID',
                ],
                'propertyID' => 'https://www.wikidata.org/wiki/Property:P1053',
                'value' => $value,
            ],
        ];
    }
}
