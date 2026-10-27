<?php

declare(strict_types=1);

namespace App\Services\SchemaTemplates\Mergers;

/**
 * Merges Legal Entity Identifier (identifiers tab) into schema node.
 *
 * Adds leiCode (string) and identifier (PropertyValue).
 */
class LegalEntityIdentifierMerger implements SchemaIdentifierMergerInterface
{
    /**
     * {@inheritDoc}
     */
    public function sourceKey(): string
    {
        return 'legal_entity_identifier';
    }

    /**
     * {@inheritDoc}
     */
    public function buildFragment(string $value): array
    {
        return [
            'leiCode' => $value,
            'identifier' => [
                '@type' => 'PropertyValue',
                'name' => 'Legal Entity Identifier',
                'alternateName' => [
                    'GLEI code',
                    'GLEI',
                    'GLEIF code',
                    'GLEIF',
                    'Global Legal Entity Identifier code',
                    'Global Legal Entity Identifier',
                    'ISO 17442 ID',
                    'ISO 17442 code',
                    'LEI code',
                    'LEI',
                    'Legal Entity ID',
                    'Legal Entity Identifier code',
                ],
                'propertyID' => 'https://www.wikidata.org/wiki/Property:P1278',
                'value' => $value,
            ],
        ];
    }
}
