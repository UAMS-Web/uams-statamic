<?php

declare(strict_types=1);

namespace App\Services\SchemaTemplates\Mergers;

/**
 * Merges CAGE code (identifiers tab) into schema node.
 *
 * Adds iso6523Code (0141:value) and identifier (PropertyValue).
 */
class CageMerger implements SchemaIdentifierMergerInterface
{
    /**
     * {@inheritDoc}
     */
    public function sourceKey(): string
    {
        return 'cage';
    }

    /**
     * {@inheritDoc}
     */
    public function buildFragment(string $value): array
    {
        return [
            'iso6523Code' => '0141:'.$value,
            'identifier' => [
                '@type' => 'PropertyValue',
                'name' => 'Commercial and Government Entity code',
                'alternateName' => [
                    'CAGE code',
                    'CAGE number',
                    'CAGE',
                    'FSCM',
                    'Federal CAGE code',
                    'Federal CAGE number',
                    'Federal Commercial and Government Entity code',
                    'Federal Commercial and Government Entity number',
                    'NCAGE',
                    'NSCM',
                    'U.S. Federal CAGE code',
                    'U.S. Federal CAGE number',
                    'U.S. Federal Commercial and Government Entity code',
                    'U.S. Federal Commercial and Government Entity number',
                    'US Federal CAGE code',
                    'US Federal CAGE number',
                    'US Federal Commercial and Government Entity code',
                    'US Federal Commercial and Government Entity number',
                ],
                'propertyID' => 'https://www.wikidata.org/wiki/Property:P5574',
                'value' => $value,
            ],
        ];
    }
}
