<?php

declare(strict_types=1);

namespace App\Services\ComputedFields\Reverse;

use Statamic\Entries\Entry;
use Statamic\Entries\EntryCollection;

/**
 * The reverse-reference computeds for the `facilities` collection (UAMS-Web/uams-statamic-archive#1372).
 *
 * Two directional `entries` pointers aim at a facility but expose no reverse, so
 * a facility cannot answer "who depends on me?":
 *
 * - **Parking** — `parking_facility` (max 1) on `clinical_locations` locations and
 *   on `facilities` buildings / building divisions names the facility that provides
 *   their parking. The reverse is the areas this facility serves for parking.
 * - **Containment** — `facility` (max 1) on a `clinical_locations` location names the
 *   building / wing it sits in. The reverse is the locations contained in this facility.
 *
 * Both are derived on read via {@see ReverseEntryReferenceResolver}; nothing is
 * stored back on the facility.
 *
 * ## Parking semantics: direct-only
 *
 * A location or building division can *inherit* its parking from a parent (the
 * `parking_facility_inherit_query` / `parking_query` toggles), but "areas served for
 * parking" counts only entries that name **this** facility **directly** in their
 * `parking_facility` field — the simpler, documented default (UAMS-Web/uams-statamic-archive#1372). An entry that
 * merely inherits parking from another facility is not counted here, because it does
 * not name this one. The direct field value is matched as-is; the inheritance walk in
 * `App\Services\Facilities\FacilityInheritedParkingService` is deliberately not used.
 */
class FacilitiesReverseReferenceComputedValues
{
    /**
     * Collections whose entries can name a facility as their parking facility.
     *
     * @var list<string>
     */
    private const array PARKING_SOURCE_COLLECTIONS = [
        'clinical_locations',
        'facilities',
    ];

    /**
     * The direct parking-facility pointer matched for the parking reverse.
     */
    private const string PARKING_FIELD = 'parking_facility';

    /**
     * The collection whose entries name their containing facility.
     */
    private const string CONTAINMENT_SOURCE_COLLECTION = 'clinical_locations';

    /**
     * The containment pointer matched for the contained-locations reverse.
     */
    private const string CONTAINMENT_FIELD = 'facility';

    /**
     * @param  ReverseEntryReferenceResolver  $resolver  Resolves each reverse pointer.
     */
    public function __construct(
        private ReverseEntryReferenceResolver $resolver
    ) {}

    /**
     * Published `clinical_locations` / `facilities` entries that directly name this
     * facility as their parking facility (direct-only; inherited parking excluded).
     *
     * @return EntryCollection<int, Entry>
     */
    public function resolveAreasServedForParking(
        Entry $entry
    ): EntryCollection {
        return $this->resolver->resolve(
            self::PARKING_SOURCE_COLLECTIONS,
            self::PARKING_FIELD,
            $entry->id()
        );
    }

    /**
     * Published `clinical_locations` entries contained within this facility (their
     * `facility` containment pointer names this entry).
     *
     * @return EntryCollection<int, Entry>
     */
    public function resolveContainedLocations(
        Entry $entry
    ): EntryCollection {
        return $this->resolver->resolve(
            self::CONTAINMENT_SOURCE_COLLECTION,
            self::CONTAINMENT_FIELD,
            $entry->id()
        );
    }
}
