<?php

declare(strict_types=1);

namespace App\Services\SchemaTemplates\Mergers;

/**
 * Merges NAICS code (classifications tab) into schema node.
 *
 * Adds naics (string) and identifier (PropertyValue).
 */
class NaicsMerger implements SchemaIdentifierMergerInterface
{
    /**
     * {@inheritDoc}
     */
    public function sourceKey(): string
    {
        return 'naics';
    }

    /**
     * {@inheritDoc}
     */
    public function buildFragment(string $value): array
    {
        return [
            'naics' => $value,
            'identifier' => [
                '@type' => 'PropertyValue',
                'name' => 'NAICS code',
                'alternateName' => [
                    'NAICS industry classification code',
                    'NAICS industry classification',
                    'NAICS',
                    'North American Industry Classification System code',
                    'North American Industry Classification System',
                ],
                'propertyID' => [
                    'https://www.wikidata.org/wiki/Property:P3224',
                    'https://purl.org/ctdl/terms/naics',
                ],
                'value' => $value,
            ],
        ];
    }
}
