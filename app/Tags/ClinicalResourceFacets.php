<?php

declare(strict_types=1);

namespace App\Tags;

use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Stache\Query\EntryQueryBuilder;
use Statamic\Tags\Tags;

/**
 * The option lists for the `clinical_resources` archive's location and area of
 * expertise facets (UAMS-Web/uams-statamic#1583).
 *
 * ```
 * {{ clinical_resource_facets:locations }}{{ slug }} {{ title }}{{ /clinical_resource_facets:locations }}
 * {{ clinical_resource_facets:expertise }}{{ slug }} {{ title }}{{ /clinical_resource_facets:expertise }}
 * ```
 *
 * Each pair returns the published entries of its collection as `slug` and `title`,
 * in title order: every `clinical_locations` entry, and the `clinical_expertise`
 * entries on the `clinical_expertise` blueprint (content pages nested under an area
 * are not areas). The slug is the value the `clinical_resources_archive_filter`
 * scope reads.
 *
 * A tag rather than a `collection:` tag in the template because that tag throws
 * when its collection does not exist, which would take the whole archive down
 * with it; a query over a missing collection here returns no options.
 */
final class ClinicalResourceFacets extends Tags
{
    /**
     * The location facet's options.
     *
     * @return list<array{slug: string, title: string}>
     */
    public function locations(): array
    {
        return $this->options('clinical_locations', null);
    }

    /**
     * The area of expertise facet's options.
     *
     * @return list<array{slug: string, title: string}>
     */
    public function expertise(): array
    {
        return $this->options('clinical_expertise', 'clinical_expertise');
    }

    /**
     * The published entries of a collection as slug and title, in title order.
     *
     * @param  string  $collection  The collection handle.
     * @param  string|null  $blueprint  The blueprint to keep, or null for every blueprint.
     * @return list<array{slug: string, title: string}>
     */
    private function options(
        string $collection,
        ?string $blueprint
    ): array {
        // Published entries of the collection, optionally of one blueprint.
        /** @var EntryQueryBuilder $query */
        $query = EntryFacade::query();
        $query->where('collection', $collection);
        $query->where('published', true);
        if ($blueprint !== null) {
            $query->where('blueprint', $blueprint);
        }

        // Keep each entry with a slug and a title.
        $options = [];
        $entries = $query->get();
        foreach (\is_iterable($entries) ? $entries : [] as $entry) {
            if (! $entry instanceof Entry) {
                continue;
            }

            $slug = $entry->slug();
            $title = $entry->get('title');
            if (\is_string($slug) && $slug !== '' && \is_string($title) && $title !== '') {
                $options[] = ['slug' => $slug, 'title' => $title];
            }
        }

        // Order by title, ignoring case.
        \usort($options, static fn (array $a, array $b): int => \strcasecmp($a['title'], $b['title']));

        return $options;
    }
}
