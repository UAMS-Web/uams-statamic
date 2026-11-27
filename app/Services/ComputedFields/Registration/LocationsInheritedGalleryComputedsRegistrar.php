<?php

declare(strict_types=1);

namespace App\Services\ComputedFields\Registration;

use App\Services\Locations\LocationInheritedGalleryService;
use Statamic\Contracts\Assets\Asset;
use Statamic\Entries\Entry;
use Statamic\Facades\Collection;

/**
 * Registers the `clinical_locations` computed `resolved_gallery_image`, the photo gallery a
 * profile shows: the location's own, or its parent's when it does not override it
 * (UAMS-Web/uams-statamic#1578). See {@see LocationInheritedGalleryService}.
 */
final readonly class LocationsInheritedGalleryComputedsRegistrar
{
    /**
     * @param  LocationInheritedGalleryService  $galleries  Resolves the own-or-inherited gallery for a location.
     */
    public function __construct(
        private LocationInheritedGalleryService $galleries
    ) {}

    /**
     * Register the gallery computed on the `clinical_locations` collection.
     */
    public function register(): void
    {
        // Alias the injected service for the computed callback
        $service = $this->galleries;

        // Register the gallery assets, resolved from the location or the parent it inherits from
        Collection::computed(
            'clinical_locations',
            'resolved_gallery_image',
            /** @return list<Asset> */
            fn (Entry $entry, mixed $_stored): array => $service->resolveGallery($entry)
        );
    }
}
