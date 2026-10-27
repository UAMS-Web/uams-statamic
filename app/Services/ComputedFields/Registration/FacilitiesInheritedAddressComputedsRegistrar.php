<?php

declare(strict_types=1);

namespace App\Services\ComputedFields\Registration;

use App\Services\Facilities\FacilityInheritedAddressService;
use Statamic\Entries\Entry;
use Statamic\Facades\Collection;

/**
 * Registers the `facilities` collection computed that exposes each entry's
 * effective (own-or-inherited) street address for templates and structured data.
 */
final readonly class FacilitiesInheritedAddressComputedsRegistrar
{
    /**
     * @param  FacilityInheritedAddressService  $facilityInheritedAddress  Resolves the own-or-inherited street address.
     */
    public function __construct(
        private FacilityInheritedAddressService $facilityInheritedAddress
    ) {}

    /**
     * Register the `resolved_street_address` computed on the `facilities` collection.
     */
    public function register(): void
    {
        // Alias the injected service for the computed callback
        $service = $this->facilityInheritedAddress;

        // Register the effective street address resolved from self or ancestors
        Collection::computed(
            'facilities',
            'resolved_street_address',
            fn (Entry $entry, mixed $_stored): ?string => $service->resolveStreetAddress($entry)
        );
    }
}
