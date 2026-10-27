<?php

declare(strict_types=1);

namespace App\Services\ComputedFields\Registration;

use App\Services\ComputedFields\Reverse\FacilitiesReverseReferenceComputedValues;
use Statamic\Facades\Collection;

/**
 * Registers the `facilities` reverse-reference computeds (UAMS-Web/uams-statamic-archive#1372):
 * `areas_served_for_parking` and `contained_locations` — the areas that name this
 * facility for parking, and the locations contained within it — derived on read by
 * {@see FacilitiesReverseReferenceComputedValues} (no stored back-reference).
 */
final readonly class FacilitiesReverseReferenceComputedsRegistrar
{
    /**
     * The collection that receives the reverse-reference computeds.
     */
    private const string COLLECTION_HANDLE = 'facilities';

    /**
     * @param  FacilitiesReverseReferenceComputedValues  $reverse  Resolves each reverse set.
     */
    public function __construct(
        private FacilitiesReverseReferenceComputedValues $reverse
    ) {}

    /**
     * Register both facilities reverse-reference computeds.
     */
    public function register(): void
    {
        // Cache the injected service for readable first-class-callable bindings.
        $reverse = $this->reverse;

        // Areas (locations / buildings) that name this facility for parking.
        Collection::computed(
            self::COLLECTION_HANDLE,
            'areas_served_for_parking',
            $reverse->resolveAreasServedForParking(...)
        );

        // Locations contained within this facility.
        Collection::computed(
            self::COLLECTION_HANDLE,
            'contained_locations',
            $reverse->resolveContainedLocations(...)
        );
    }
}
