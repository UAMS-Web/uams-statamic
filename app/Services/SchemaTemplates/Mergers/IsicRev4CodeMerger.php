<?php

declare(strict_types=1);

namespace App\Services\SchemaTemplates\Mergers;

/**
 * Merges ISIC Rev. 4 code (classifications tab) into schema node.
 *
 * Adds isicV4 (string) and identifier (PropertyValue).
 */
class IsicRev4CodeMerger implements SchemaIdentifierMergerInterface
{
    /**
     * {@inheritDoc}
     */
    public function sourceKey(): string
    {
        return 'isic_rev_4_code';
    }

    /**
     * {@inheritDoc}
     */
    public function buildFragment(string $value): array
    {
        return [
            'isicV4' => $value,
            'identifier' => [
                '@type' => 'PropertyValue',
                'name' => 'International Standard Industrial Classification code Rev.4',
                'alternateName' => [
                    'ISIC Rev. 4',
                    'ISIC Rev. 4 code',
                    'ISIC Rev.4',
                    'ISIC Rev.4 code',
                ],
                'propertyID' => 'https://www.wikidata.org/wiki/Property:P1796',
                'value' => $value,
            ],
        ];
    }
}
