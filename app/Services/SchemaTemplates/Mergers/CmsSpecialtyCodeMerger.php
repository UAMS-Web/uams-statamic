<?php

declare(strict_types=1);

namespace App\Services\SchemaTemplates\Mergers;

/**
 * Merges CMS Specialty Code (classifications tab) into schema node.
 *
 * Adds identifier (PropertyValue) only.
 */
class CmsSpecialtyCodeMerger implements SchemaIdentifierMergerInterface
{
    /**
     * {@inheritDoc}
     */
    public function sourceKey(): string
    {
        return 'cms_specialty_code';
    }

    /**
     * {@inheritDoc}
     */
    public function buildFragment(string $value): array
    {
        return [
            'identifier' => [
                '@type' => 'PropertyValue',
                'name' => 'CMS Specialty Code',
                'alternateName' => [
                    'Centers for Medicare & Medicaid Services Specialty Code',
                    'Centers for Medicare and Medicaid Services Specialty Code',
                    'HCFA Specialty Code',
                    'Health Care Financing Administration Specialty Code',
                    'Medicare Specialty Code',
                ],
                'value' => $value,
            ],
        ];
    }
}
