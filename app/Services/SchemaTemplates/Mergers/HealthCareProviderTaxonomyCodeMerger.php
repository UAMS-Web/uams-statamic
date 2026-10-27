<?php

declare(strict_types=1);

namespace App\Services\SchemaTemplates\Mergers;

/**
 * Merges Healthcare Provider Taxonomy Code (classifications tab) into schema node.
 *
 * Adds identifier (PropertyValue) only.
 */
class HealthCareProviderTaxonomyCodeMerger implements SchemaIdentifierMergerInterface
{
    /**
     * {@inheritDoc}
     */
    public function sourceKey(): string
    {
        return 'health_care_provider_taxonomy_code';
    }

    /**
     * {@inheritDoc}
     */
    public function buildFragment(string $value): array
    {
        return [
            'identifier' => [
                '@type' => 'PropertyValue',
                'name' => 'Healthcare Provider Taxonomy Code',
                'alternateName' => [
                    'HPTC code',
                    'HPTC taxonomy code',
                    'HPTC taxonomy',
                    'HPTC',
                    'Health Care Provider Taxonomy code',
                    'Health Care Provider Taxonomy',
                    'Healthcare Provider Taxonomy',
                    'NUCC code',
                    'NUCC taxonomy code',
                    'NUCC taxonomy',
                    'NUCC',
                    'National Uniform Claim Committee code',
                    'National Uniform Claim Committee taxonomy code',
                ],
                'value' => $value,
            ],
        ];
    }
}
