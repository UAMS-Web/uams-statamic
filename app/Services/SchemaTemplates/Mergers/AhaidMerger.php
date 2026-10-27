<?php

declare(strict_types=1);

namespace App\Services\SchemaTemplates\Mergers;

/**
 * Merges AHAID (identifiers tab) into schema node.
 *
 * Adds identifier (PropertyValue) only.
 */
class AhaidMerger implements SchemaIdentifierMergerInterface
{
    /**
     * {@inheritDoc}
     */
    public function sourceKey(): string
    {
        return 'ahaid';
    }

    /**
     * {@inheritDoc}
     */
    public function buildFragment(string $value): array
    {
        return [
            'identifier' => [
                '@type' => 'PropertyValue',
                'name' => 'American Hospital Association Hospital Identifier',
                'alternateName' => [
                    'AHA ID',
                    'AHA ID number',
                    'AHA IDNUMBER',
                    'AHA hospital ID',
                    'AHA hospital ID number',
                    'AHA hospital identifier',
                    'AHA identifier',
                    'AHAID',
                    'AHAID number',
                    'American Hospital Association ID',
                    'American Hospital Association ID number',
                    'American Hospital Association IDNUMBER',
                    'American Hospital Association hospital ID',
                    'American Hospital Association hospital ID number',
                    'American Hospital Association hospital identifier',
                    'American Hospital Association identifier',
                    'IDNUMBER',
                ],
                'value' => $value,
            ],
        ];
    }
}
