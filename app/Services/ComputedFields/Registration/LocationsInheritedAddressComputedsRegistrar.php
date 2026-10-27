<?php

declare(strict_types=1);

namespace App\Services\ComputedFields\Registration;

use App\Services\Locations\LocationInheritedAddressService;
use Statamic\Entries\Entry;
use Statamic\Facades\Collection;

/**
 * Registers the `clinical_locations` computeds exposing each location's effective
 * (own-or-inherited) address components: the street address (UAMS-Web/uams-statamic-archive#1269), the city (UAMS-Web/uams-statamic-archive#1225), the
 * state and postal code (UAMS-Web/uams-statamic-archive#2389), and since UAMS-Web/uams-statamic-archive#2608 the containing facility, the facility level and
 * the suite, the entrance map pin and the region terms, each resolved per group.
 *
 * Separate from {@see FacilitiesInheritedAddressComputedsRegistrar} because the two resolve
 * different chains: a facility walks its structural ancestors, while a location may inherit
 * from its parent location, take the address of the facility it names, or hold its own. The
 * computed handle is deliberately the same on both collections, so a template reading
 * `resolved_street_address` does not need to know which collection it is looking at — the way
 * `resolved_parking_instructions` already works across the two.
 */
final readonly class LocationsInheritedAddressComputedsRegistrar
{
    /**
     * @param  LocationInheritedAddressService  $locationInheritedAddress  Resolves the own-or-inherited street address for a location.
     */
    public function __construct(
        private LocationInheritedAddressService $locationInheritedAddress
    ) {}

    /**
     * Register the effective address-component computeds on the `clinical_locations` collection.
     */
    public function register(): void
    {
        // Alias the injected service for the computed callbacks
        $service = $this->locationInheritedAddress;

        // Register the effective street address resolved from self, facility or parent location
        Collection::computed(
            'clinical_locations',
            'resolved_street_address',
            fn (Entry $entry, mixed $_stored): ?string => $service->resolveStreetAddress($entry)
        );

        // Register the effective city, resolved through the same three routes (UAMS-Web/uams-statamic-archive#1225)
        Collection::computed(
            'clinical_locations',
            'resolved_city',
            fn (Entry $entry, mixed $_stored): ?string => $service->resolveCity($entry)
        );

        // Register the effective state, whose storage handle depends on the stored preset (UAMS-Web/uams-statamic-archive#2389)
        Collection::computed(
            'clinical_locations',
            'resolved_state',
            fn (Entry $entry, mixed $_stored): ?string => $service->resolveState($entry)
        );

        // Register the effective postal code, stored as `zip` on a facility (UAMS-Web/uams-statamic-archive#2389)
        Collection::computed(
            'clinical_locations',
            'resolved_postal_code',
            fn (Entry $entry, mixed $_stored): ?string => $service->resolvePostalCode($entry)
        );

        // Register the second address line, which travels with the street (UAMS-Web/uams-statamic-archive#2608)
        Collection::computed(
            'clinical_locations',
            'resolved_address_line_2',
            fn (Entry $entry, mixed $_stored): ?string => $service->resolveAddressLine2($entry)
        );

        // Register the containing facility's id, from whichever location owns the facility group (UAMS-Web/uams-statamic-archive#2608)
        Collection::computed(
            'clinical_locations',
            'resolved_facility',
            fn (Entry $entry, mixed $_stored): ?string => $service->resolveFacilityId($entry)
        );

        // Register the containing facility's name, for a template to print (UAMS-Web/uams-statamic-archive#2608)
        Collection::computed(
            'clinical_locations',
            'resolved_facility_name',
            fn (Entry $entry, mixed $_stored): ?string => $service->resolveFacilityName($entry)
        );

        // Register the facility level's label, from whichever location owns the unit group (UAMS-Web/uams-statamic-archive#2608)
        Collection::computed(
            'clinical_locations',
            'resolved_facility_level',
            fn (Entry $entry, mixed $_stored): ?string => $service->resolveFacilityLevel($entry)
        );

        // Register the suite, from whichever location owns the unit group (UAMS-Web/uams-statamic-archive#2608)
        Collection::computed(
            'clinical_locations',
            'resolved_suite',
            fn (Entry $entry, mixed $_stored): ?string => $service->resolveSuite($entry)
        );

        // Register the entrance map pin, from whichever location owns the map group (UAMS-Web/uams-statamic-archive#2608)
        Collection::computed(
            'clinical_locations',
            'resolved_map',
            fn (Entry $entry, mixed $_stored): mixed => $service->resolveMap($entry)
        );

        // Register the region terms, which follow the street group (UAMS-Web/uams-statamic-archive#2608)
        Collection::computed(
            'clinical_locations',
            'resolved_regions',
            fn (Entry $entry, mixed $_stored): array => $service->resolveRegionSlugs($entry)
        );
    }
}
