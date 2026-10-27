<?php

declare(strict_types=1);

namespace App\Services\SchemaTemplates\Mergers;

/**
 * Merges Google Scholar author ID (identifiers tab) into schema node.
 *
 * Adds identifier (PropertyValue) only.
 */
class GoogleScholarAuthorIdMerger implements SchemaIdentifierMergerInterface
{
    /**
     * {@inheritDoc}
     */
    public function sourceKey(): string
    {
        return 'google_scholar_author_id';
    }

    /**
     * {@inheritDoc}
     */
    public function buildFragment(string $value): array
    {
        return [
            'identifier' => [
                '@type' => 'PropertyValue',
                'name' => 'Google Scholar author ID',
                'alternateName' => [
                    'Scholar ID in Google Scholar',
                    'Google Scholar ID',
                    'Google Scholar person ID',
                    'GoogleScholar author ID',
                ],
                'propertyID' => 'https://www.wikidata.org/wiki/Property:P1960',
                'value' => $value,
            ],
        ];
    }
}
