<?php

declare(strict_types=1);

namespace App\Services\ComputedFields\Registration;

use App\Services\IptcDigitalSourceValueResolver;
use App\Support\Collections\PeopleCollection;
use Statamic\Entries\Entry;
use Statamic\Facades\Collection;

/**
 * Registers IPTC digital source computed fields on supported collections: resolved
 * type key and schema.org DigitalSource URL per group handle.
 *
 * Nested field contexts use Antlers modifiers; entry-root resolution mirrors
 * IptcDigitalSourceValueResolver static helpers.
 */
final class IptcDigitalSourceCollectionComputedsRegistrar
{
    /**
     * Statamic collection handles whose entries may carry digital source group fields at root.
     *
     * @return list<string>
     */
    private static function collectionHandles(): array
    {
        return [
            'clinical_expertise',
            'clinical_locations',
            'clinical_providers',
            'clinical_resources',
            'knowledge_bases',
            'pages',
            PeopleCollection::handle(),
            'posts',
        ];
    }

    /**
     * Register value and schema computeds for every collection and digital-source group pair.
     */
    public function register(): void
    {
        // Resolve configured digital source group field handles from the resolver
        $groupHandles = IptcDigitalSourceValueResolver::digitalSourceTypeGroupHandles();

        // Walk each collection that may expose root-level digital source computeds
        foreach (self::collectionHandles() as $collectionHandle) {
            // Walk each group handle to register paired value and schema computeds
            foreach ($groupHandles as $groupHandle) {
                // Build Statamic computed field keys for this group handle
                $computedKeys = IptcDigitalSourceValueResolver::computedKeysForGroupHandle(
                    $groupHandle
                );

                // Extract value and schema computed handles for this group
                $valueKey = $computedKeys['value'];
                $schemaKey = $computedKeys['schema'];

                // Register the resolved IPTC type string computed for this collection
                Collection::computed(
                    $collectionHandle,
                    $valueKey,
                    fn (Entry $entry, mixed $_stored): string => IptcDigitalSourceValueResolver::resolveFromEntryGroupField(
                        $entry,
                        $groupHandle
                    )
                );

                // Register the schema.org DigitalSource URL computed for this collection
                Collection::computed(
                    $collectionHandle,
                    $schemaKey,
                    fn (Entry $entry, mixed $_stored): string => IptcDigitalSourceValueResolver::schemaUrlFromEntryGroupField(
                        $entry,
                        $groupHandle
                    )
                );
            }
        }
    }
}
