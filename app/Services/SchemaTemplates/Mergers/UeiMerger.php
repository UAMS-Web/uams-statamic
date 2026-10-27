<?php

declare(strict_types=1);

namespace App\Services\SchemaTemplates\Mergers;

/**
 * Merges Unique Entity ID (identifiers tab) into schema node.
 *
 * Adds identifier (PropertyValue) only.
 */
class UeiMerger implements SchemaIdentifierMergerInterface
{
    /**
     * {@inheritDoc}
     */
    public function sourceKey(): string
    {
        return 'uei';
    }

    /**
     * {@inheritDoc}
     */
    public function buildFragment(string $value): array
    {
        return [
            'identifier' => [
                '@type' => 'PropertyValue',
                'name' => 'Unique Entity ID',
                'alternateName' => [
                    'SAM identifier',
                    'System for Award Management ID',
                    'System for Award Management identifier',
                    'U.S. SAM ID',
                    'UEI',
                    'Unique Entity ID from SAM.gov',
                    'Unique Entity Identifier',
                ],
                'propertyID' => 'https://www.wikidata.org/wiki/Property:P9955',
                'value' => $value,
            ],
        ];
    }
}
