<?php

declare(strict_types=1);

namespace App\Services\SchemaTemplates\Mergers;

/**
 * Merges Google Tag Manager Container ID (identifiers tab) into schema node.
 *
 * Adds identifier (PropertyValue) only.
 */
class GoogleTagManagerContainerIdMerger implements SchemaIdentifierMergerInterface
{
    /**
     * {@inheritDoc}
     */
    public function sourceKey(): string
    {
        return 'google_tag_manager_container_id';
    }

    /**
     * {@inheritDoc}
     */
    public function buildFragment(string $value): array
    {
        return [
            'identifier' => [
                '@type' => 'PropertyValue',
                'name' => 'Google Tag Manager Container ID',
                'alternateName' => [
                    'Container ID',
                    'GTM Container ID',
                    'GTM ID',
                    'Google Tag Manager ID',
                    'Container ID number',
                    'GTM Container ID number',
                    'GTM ID number',
                    'Google Tag Manager ID number',
                ],
                'propertyID' => 'https://tagmanager.google.com/',
                'value' => $value,
            ],
        ];
    }
}
