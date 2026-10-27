<?php

declare(strict_types=1);

namespace App\Services\SchemaTemplates\Mergers;

/**
 * Merges ISCO-08 code (classifications tab) into schema node.
 *
 * Adds identifier (PropertyValue) and occupationalCategory (CategoryCode).
 */
class Isco08CodeMerger implements SchemaIdentifierMergerInterface
{
    /**
     * {@inheritDoc}
     */
    public function sourceKey(): string
    {
        return 'isco_08_code';
    }

    /**
     * {@inheritDoc}
     */
    public function buildFragment(string $value): array
    {
        return [
            'identifier' => [
                '@type' => 'PropertyValue',
                'name' => 'International Standard Classification of Occupations 2008',
                'alternateName' => [
                    'ISCO-08 code',
                    'ISCO-08',
                    'ISCO08 code',
                    'ISCO08',
                ],
                'propertyID' => 'https://www.wikidata.org/wiki/Property:P8283',
                'value' => $value,
            ],
            'occupationalCategory' => [
                '@type' => 'CategoryCode',
                'codeValue' => $value,
                'inCodeSet' => [
                    '@type' => 'CategoryCodeSet',
                    'name' => 'International Standard Classification of Occupations 2008',
                    'sameAs' => 'https://www.wikidata.org/wiki/Q113864458',
                ],
            ],
        ];
    }
}
