<?php

declare(strict_types=1);

namespace App\Services\SchemaTemplates\Mergers;

/**
 * Merges PubMed Author ID (identifiers tab) into schema node.
 *
 * Adds identifier (PropertyValue) only.
 */
class PubmedAuthorIdMerger implements SchemaIdentifierMergerInterface
{
    /**
     * {@inheritDoc}
     */
    public function sourceKey(): string
    {
        return 'pubmed_author_id';
    }

    /**
     * {@inheritDoc}
     */
    public function buildFragment(string $value): array
    {
        return [
            'identifier' => [
                '@type' => 'PropertyValue',
                'name' => 'PubMed Author ID',
                'alternateName' => [
                    'PubMed author identifier',
                ],
                'value' => $value,
            ],
        ];
    }
}
