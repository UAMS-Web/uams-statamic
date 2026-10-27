<?php

declare(strict_types=1);

namespace App\Services\SchemaTemplates\Mergers;

/**
 * Merges Global Location Number (identifiers tab) into schema node.
 *
 * Adds globalLocationNumber (string), iso6523Code (0088:value), and identifier
 * (PropertyValue).
 */
class GlnMerger implements SchemaIdentifierMergerInterface
{
    /**
     * {@inheritDoc}
     */
    public function sourceKey(): string
    {
        return 'gln';
    }

    /**
     * {@inheritDoc}
     */
    public function buildFragment(string $value): array
    {
        return [
            'globalLocationNumber' => $value,
            'iso6523Code' => '0088:'.$value,
            'identifier' => [
                '@type' => 'PropertyValue',
                'name' => 'Global Location Number',
                'alternateName' => [
                    'GLN',
                    'GS1 GLN',
                    'GS1 Global Location Number',
                ],
                'propertyID' => 'https://www.wikidata.org/wiki/Property:P9860',
                'value' => $value,
            ],
        ];
    }
}
