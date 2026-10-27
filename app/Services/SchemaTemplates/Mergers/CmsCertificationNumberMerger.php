<?php

declare(strict_types=1);

namespace App\Services\SchemaTemplates\Mergers;

/**
 * Merges the CMS Certification Number (CCN) into a schema node.
 *
 * Adds identifier (PropertyValue) only. No `propertyID` is emitted: there is no
 * authoritative Wikidata/registry property URI for the CCN, so — as with the
 * AHAID and VA STA6A identifiers — the value carries `name`/`alternateName`
 * only rather than asserting a possibly-wrong `propertyID`.
 */
class CmsCertificationNumberMerger implements SchemaIdentifierMergerInterface
{
    /**
     * {@inheritDoc}
     */
    public function sourceKey(): string
    {
        return 'hospital_ccn_id';
    }

    /**
     * {@inheritDoc}
     */
    public function buildFragment(string $value): array
    {
        return [
            'identifier' => [
                '@type' => 'PropertyValue',
                'name' => 'CMS Certification Number',
                'alternateName' => [
                    'CCN',
                    'CCN number',
                    'CMS CCN',
                    'CMS Certification Number',
                    'CMS certification number',
                    'Centers for Medicare & Medicaid Services Certification Number',
                    'Centers for Medicare and Medicaid Services Certification Number',
                    'Medicare provider number',
                    'Medicare/Medicaid provider number',
                    'OSCAR number',
                    'Online Survey Certification and Reporting number',
                    'provider number',
                ],
                'value' => $value,
            ],
        ];
    }
}
