<?php

declare(strict_types=1);

namespace App\Services\SchemaTemplates\Mergers;

/**
 * Merges D-U-N-S Number (classifications/identifiers tab) into schema node.
 *
 * Adds duns (string), iso6523Code (0060:value), and identifier (PropertyValue).
 */
class DunsMerger implements SchemaIdentifierMergerInterface
{
    /**
     * {@inheritDoc}
     */
    public function sourceKey(): string
    {
        return 'duns';
    }

    /**
     * {@inheritDoc}
     */
    public function buildFragment(string $value): array
    {
        return [
            'duns' => $value,
            'iso6523Code' => '0060:'.$value,
            'identifier' => [
                '@type' => 'PropertyValue',
                'name' => 'D-U-N-S',
                'alternateName' => [
                    'D-U-N-S Number',
                    'DUNS Number',
                    'DUNS',
                    'Data Universal Numbering System Number',
                    'Data Universal Numbering System',
                    'Dun & Bradstreet D-U-N-S Number',
                    'Dun & Bradstreet DUNS Number',
                    'Dun & Bradstreet DUNS',
                    'Dun & Bradstreet Data Universal Numbering System Number',
                    'Dun & Bradstreet Data Universal Numbering System',
                ],
                'propertyID' => 'https://www.wikidata.org/wiki/Property:P2771',
                'value' => $value,
            ],
        ];
    }
}
