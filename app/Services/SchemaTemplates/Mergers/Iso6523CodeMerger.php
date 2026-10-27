<?php

declare(strict_types=1);

namespace App\Services\SchemaTemplates\Mergers;

/**
 * Merges ISO/IEC 6523 code (identifiers tab) into schema node.
 *
 * Adds iso6523Code (string) and identifier (PropertyValue).
 */
class Iso6523CodeMerger implements SchemaIdentifierMergerInterface
{
    /**
     * {@inheritDoc}
     */
    public function sourceKey(): string
    {
        return 'iso_6523_code';
    }

    /**
     * {@inheritDoc}
     */
    public function buildFragment(string $value): array
    {
        return [
            'iso6523Code' => $value,
            'identifier' => [
                '@type' => 'PropertyValue',
                'name' => 'ISO/IEC 6523',
                'alternateName' => [
                    'ISO 6523',
                    'ISO 6523 code',
                    'ISO/IEC 6523 code',
                ],
                'propertyID' => 'https://www.wikidata.org/wiki/Property:P3743',
                'value' => $value,
            ],
        ];
    }
}
