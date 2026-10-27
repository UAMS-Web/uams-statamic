<?php

declare(strict_types=1);

namespace App\Services\SchemaTemplates\Mergers;

/**
 * Merges RxNorm CUI (identifiers tab) into schema node.
 *
 * Adds rxcui (string) and identifier (PropertyValue).
 */
class RxcuiMerger implements SchemaIdentifierMergerInterface
{
    /**
     * {@inheritDoc}
     */
    public function sourceKey(): string
    {
        return 'rxcui';
    }

    /**
     * {@inheritDoc}
     */
    public function buildFragment(string $value): array
    {
        return [
            'rxcui' => $value,
            'identifier' => [
                '@type' => 'PropertyValue',
                'name' => 'RxNorm CUI',
                'alternateName' => [
                    'RXCUI code',
                    'RXCUI number',
                    'RXCUI',
                    'RxNorm CUI code number',
                    'RxNorm CUI code',
                    'RxNorm CUI identifier',
                    'RxNorm CUI number',
                    'RxNorm CUI',
                    'RxNorm ID code',
                    'RxNorm ID number',
                    'RxNorm ID',
                    'RxNorm concept unique identifier code',
                    'RxNorm concept unique identifier number',
                    'RxNorm concept unique identifier',
                    'RxNorm identifier code',
                    'RxNorm identifier number',
                    'RxNorm identifier',
                ],
                'propertyID' => 'https://www.wikidata.org/wiki/Property:P3345',
                'value' => $value,
            ],
        ];
    }
}
