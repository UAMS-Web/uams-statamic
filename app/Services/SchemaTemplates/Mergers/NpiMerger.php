<?php

declare(strict_types=1);

namespace App\Services\SchemaTemplates\Mergers;

/**
 * Merges National Provider Identifier (identifiers tab) into schema node.
 *
 * Adds usNPI (string) and identifier (PropertyValue).
 */
class NpiMerger implements SchemaIdentifierMergerInterface
{
    /**
     * {@inheritDoc}
     */
    public function sourceKey(): string
    {
        return 'npi';
    }

    /**
     * {@inheritDoc}
     */
    public function buildFragment(string $value): array
    {
        return [
            'usNPI' => $value,
            'identifier' => [
                '@type' => 'PropertyValue',
                'name' => 'National Provider Identifier',
                'alternateName' => [
                    'NPI number',
                    'NPI',
                    'National Provider ID number',
                    'National Provider ID',
                    'National Provider Identifier number',
                ],
                'propertyID' => 'https://www.wikidata.org/wiki/Property:P9450',
                'value' => $value,
            ],
        ];
    }
}
