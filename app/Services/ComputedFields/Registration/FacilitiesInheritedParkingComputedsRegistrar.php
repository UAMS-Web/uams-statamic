<?php

declare(strict_types=1);

namespace App\Services\ComputedFields\Registration;

use App\Services\Facilities\FacilityInheritedParkingService;
use Statamic\Entries\Entry;
use Statamic\Facades\Collection;

/**
 * Registers the own-or-inherited parking computeds on the `facilities` and
 * `clinical_locations` collections, exposing each entry's effective parking
 * entity and its name, instructions, directions, and free/validated flags for
 * templates and structured data.
 */
final readonly class FacilitiesInheritedParkingComputedsRegistrar
{
    /**
     * Collections whose entries inherit-with-override parking values.
     *
     * @var list<string>
     */
    private const array COLLECTIONS = [
        'facilities',
        'clinical_locations',
    ];

    /**
     * @param  FacilityInheritedParkingService  $facilityInheritedParking  Resolves the own-or-inherited parking values.
     */
    public function __construct(
        private FacilityInheritedParkingService $facilityInheritedParking
    ) {}

    /**
     * Register each effective-parking computed on every consumer collection.
     */
    public function register(): void
    {
        // Alias the injected service for the computed callbacks
        $service = $this->facilityInheritedParking;

        // Map each computed handle to the callback that resolves its value
        $computeds = [
            'resolved_parking_facility' => fn (Entry $entry, mixed $_stored): mixed => $service->resolveParkingFacility($entry)?->id(),
            'resolved_parking_facility_name' => fn (Entry $entry, mixed $_stored): ?string => $service->resolveParkingFacilityName($entry),
            'resolved_parking_instructions' => fn (Entry $entry, mixed $_stored): mixed => $service->resolveParkingInstructions($entry),
            'resolved_directions_from_parking' => fn (Entry $entry, mixed $_stored): mixed => $service->resolveDirectionsFromParking($entry),
            'resolved_parking_free' => fn (Entry $entry, mixed $_stored): ?bool => $service->resolveParkingFree($entry),
            'resolved_parking_validated' => fn (Entry $entry, mixed $_stored): ?bool => $service->resolveParkingValidated($entry),
        ];

        // Register every computed on every consumer collection
        foreach (self::COLLECTIONS as $collection) {
            foreach ($computeds as $handle => $callback) {
                Collection::computed(
                    $collection,
                    $handle,
                    $callback
                );
            }
        }
    }
}
