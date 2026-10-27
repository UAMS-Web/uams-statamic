<?php

declare(strict_types=1);

namespace App\Services\SchemaTemplates\Mergers;

/**
 * Merges Office of Postsecondary Education ID (identifiers tab) into schema node.
 *
 * Adds identifier (PropertyValue) only.
 */
class OpeIdMerger implements SchemaIdentifierMergerInterface
{
    /**
     * {@inheritDoc}
     */
    public function sourceKey(): string
    {
        return 'ope_id';
    }

    /**
     * {@inheritDoc}
     */
    public function buildFragment(string $value): array
    {
        return [
            'identifier' => [
                '@type' => 'PropertyValue',
                'name' => 'Office of Postsecondary Education ID',
                'alternateName' => [
                    'FICE code',
                    'FICE number',
                    'FICE',
                    'Federal Interagency Committee on Education code',
                    'Federal Interagency Committee on Education number',
                    'Federal Interagency Committee on Education',
                    'Federal School Code',
                    'OPE ID code',
                    'OPE ID number',
                    'OPE ID',
                    'OPE number',
                    'OPEID code',
                    'OPEID number',
                    'OPEID',
                    'Office of Postsecondary Education Identification code',
                    'Office of Postsecondary Education Identification number',
                    'Office of Postsecondary Education Identification',
                    'U.S. FICE code',
                    'U.S. FICE number',
                    'U.S. FICE',
                    'U.S. Federal Interagency Committee on Education code',
                    'U.S. Federal Interagency Committee on Education number',
                    'U.S. Federal Interagency Committee on Education',
                    'U.S. Federal School Code',
                    'U.S. OPE ID code',
                    'U.S. OPE ID number',
                    'U.S. OPE ID',
                    'U.S. OPE number',
                    'U.S. OPEID code',
                    'U.S. OPEID number',
                    'U.S. OPEID',
                    'U.S. Office of Postsecondary Education Identification code',
                    'U.S. Office of Postsecondary Education Identification number',
                    'U.S. Office of Postsecondary Education Identification',
                    'US FICE code',
                    'US FICE number',
                    'US FICE',
                    'US Federal Interagency Committee on Education code',
                    'US Federal Interagency Committee on Education number',
                    'US Federal Interagency Committee on Education',
                    'US Federal School Code',
                    'US OPE ID code',
                    'US OPE ID number',
                    'US OPE ID',
                    'US OPE number',
                    'US OPEID code',
                    'US OPEID number',
                    'US OPEID',
                    'US Office of Postsecondary Education Identification code',
                    'US Office of Postsecondary Education Identification number',
                    'US Office of Postsecondary Education Identification',
                ],
                'propertyID' => 'https://purl.org/ctdl/terms/opeID',
                'value' => $value,
            ],
        ];
    }
}
