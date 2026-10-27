<?php

declare(strict_types=1);

namespace App\Services\SchemaTemplates\Mergers;

/**
 * Merges Scopus author ID (identifiers tab) into schema node.
 *
 * Adds identifier (PropertyValue) only.
 */
class ScopusAuthorIdMerger implements SchemaIdentifierMergerInterface
{
    /**
     * {@inheritDoc}
     */
    public function sourceKey(): string
    {
        return 'scopus_author_id';
    }

    /**
     * {@inheritDoc}
     */
    public function buildFragment(string $value): array
    {
        return [
            'identifier' => [
                '@type' => 'PropertyValue',
                'name' => 'Scopus author ID',
                'alternateName' => [
                    'Mendeley author ID',
                    'Mendeley author identifier',
                    'ScienceDirect author ID',
                    'ScienceDirect author identifier',
                    'Scopus ID',
                    'Scopus author identifier',
                    'Scopus identifier',
                    'ScopusID',
                ],
                'propertyID' => 'https://www.wikidata.org/wiki/Property:P1153',
                'value' => $value,
            ],
        ];
    }
}
