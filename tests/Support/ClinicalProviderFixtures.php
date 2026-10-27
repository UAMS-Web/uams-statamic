<?php

declare(strict_types=1);

namespace Tests\Support;

use Statamic\Entries\Entry;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Stache\Query\EntryQueryBuilder;

/**
 * Fixtures for people who pass the clinical provider profile base rules.
 *
 * Since UAMS-Web/uams-statamic-archive#3123 the base rules require at least one published entry in `clinical_locations`,
 * so an eligible test person needs a location that exists in the Stache. Call these from a
 * test running on an isolated Stache: they save entries.
 */
final class ClinicalProviderFixtures
{
    /**
     * Slug of the shared published practice location.
     */
    public const PUBLISHED_LOCATION_SLUG = 'eligibility-published-location';

    /**
     * ID of a published `clinical_locations` entry, creating it and its collection when absent.
     *
     * Repeat calls within one test return the same entry.
     */
    public static function publishedLocationId(): string
    {
        if (Collection::find('clinical_locations') === null) {
            /** @var \Statamic\Entries\Collection $collection */
            $collection = Collection::make('clinical_locations');
            $collection->title('Clinical Locations');
            $collection->save();
        }

        /** @var EntryQueryBuilder $query */
        $query = EntryFacade::query();
        $query->where('collection', 'clinical_locations');
        $query->where('slug', self::PUBLISHED_LOCATION_SLUG);
        $existing = $query->first();

        if ($existing instanceof Entry) {
            /** @var string $existingId */
            $existingId = $existing->id();

            return $existingId;
        }

        /** @var Entry $location */
        $location = EntryFacade::make();
        $location->collection('clinical_locations');
        $location->slug(self::PUBLISHED_LOCATION_SLUG);
        $location->published(true);
        $location->data(['title' => 'Eligibility Published Location']);
        $location->saveQuietly();

        /** @var string $id */
        $id = $location->id();

        return $id;
    }
}
