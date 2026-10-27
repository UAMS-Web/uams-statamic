<?php

declare(strict_types=1);

namespace App\Services\Facilities;

use App\Support\ContentAncestorIds;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Fields\Blueprint;

/**
 * Resolves the effective address components for a `facilities` collection entry.
 *
 * Building / building-complex / parking-facility entries carry their own address.
 * A wing (`building_division`) carries none and a parking division
 * (`parking_division`) carries one only when its `streetaddress_query` toggle is
 * on; both otherwise inherit the address of the nearest structural ancestor that
 * provides one. The ancestor walk reuses {@see ContentAncestorIds::forEntry},
 * which is cycle-safe by construction.
 *
 * The CITY follows the same walk with one difference, and the difference is a
 * property of the blueprints rather than a choice here: no facility blueprint
 * gates a city behind a toggle. `building`, `building_complex` and
 * `parking_facility` carry a flat `city`; `building_division` and
 * `parking_division` carry none at all, so both always inherit. A parking
 * division can therefore hold its own street address and no city of its own,
 * which is asymmetric but is what the blueprints say (UAMS-Web/uams-statamic-archive#1225).
 */
final class FacilityInheritedAddressService
{
    /**
     * Blueprint handle whose own street address is gated behind a toggle.
     */
    private const string TOGGLED_ADDRESS_BLUEPRINT = 'parking_division';

    /**
     * Toggle field handle enabling a parking division's own street address.
     */
    private const string ADDRESS_TOGGLE_HANDLE = 'streetaddress_query';

    /**
     * Street address field handle.
     */
    private const string STREET_ADDRESS_HANDLE = 'streetaddress';

    /**
     * City field handle. No facility blueprint gates it behind a toggle.
     */
    private const string CITY_HANDLE = 'city';

    /**
     * State field handle. Flat on a facility; the location side stores three
     * preset-specific variants of the same component (UAMS-Web/uams-statamic-archive#2389).
     */
    private const string STATE_HANDLE = 'state';

    /**
     * Postal code field handle. Named `zip` on a facility and `postal_code` on a
     * location's address value -- the same component under two names (UAMS-Web/uams-statamic-archive#2389).
     */
    private const string POSTAL_CODE_HANDLE = 'zip';

    /**
     * Resolve the effective street address: the entry's own when it provides one,
     * otherwise the nearest structural ancestor that provides one. Null when none.
     */
    public function resolveStreetAddress(
        Entry $entry
    ): ?string {
        // Walk for the street address, which a parking division gates behind a toggle
        return $this->resolveInherited($entry, fn (Entry $candidate): ?string => $this->providedStreetAddress($candidate));
    }

    /**
     * Resolve the effective city: the entry's own when it provides one, otherwise the
     * nearest structural ancestor that provides one. Null when none (UAMS-Web/uams-statamic-archive#1225).
     */
    public function resolveCity(
        Entry $entry
    ): ?string {
        // Walk for the city, which no blueprint gates behind a toggle
        return $this->resolveInherited($entry, fn (Entry $candidate): ?string => $this->providedCity($candidate));
    }

    /**
     * Resolve the effective state, or null when none resolves (UAMS-Web/uams-statamic-archive#2389).
     */
    public function resolveState(
        Entry $entry
    ): ?string {
        // Walk for the state, ungated like the city
        return $this->resolveInherited($entry, fn (Entry $candidate): ?string => $this->providedValue($candidate, self::STATE_HANDLE));
    }

    /**
     * Resolve the effective postal code, or null when none resolves (UAMS-Web/uams-statamic-archive#2389).
     */
    public function resolvePostalCode(
        Entry $entry
    ): ?string {
        // Walk for the postal code, stored as `zip` on a facility
        return $this->resolveInherited($entry, fn (Entry $candidate): ?string => $this->providedValue($candidate, self::POSTAL_CODE_HANDLE));
    }

    /**
     * Walk self-then-ancestors for the first entry providing a component.
     *
     * The two public resolvers differ only in what counts as "provided", so the walk is
     * shared rather than written twice: a second copy would be a second place for the
     * ancestor ordering, the missing-ancestor skip and the id narrowing to drift.
     *
     * @param  callable(Entry): ?string  $provided  Returns the candidate's own value, or null when it inherits
     */
    private function resolveInherited(
        Entry $entry,
        callable $provided
    ): ?string {
        // Return the entry's own value when it provides one
        $own = $provided($entry);

        // Stop when the entry supplies its own
        if ($own !== null) {
            return $own;
        }

        // Narrow the entry id to a scalar usable by the ancestor walk
        $entryId = $entry->id();

        // Stop when the entry has no usable id (cannot resolve ancestors)
        if (
            ! is_int($entryId)
            && ! is_string($entryId)
        ) {
            return null;
        }

        // Walk structural ancestors (immediate parent first), returning the first
        // ancestor that provides a usable value.
        foreach (ContentAncestorIds::forEntry($entryId) as $ancestorId) {
            // Load the ancestor entry (a missing ancestor is skipped)
            $ancestor = EntryFacade::find($ancestorId);

            // Skip ancestors that cannot be loaded as an entry
            if (! $ancestor instanceof Entry) {
                continue;
            }

            // Resolve whether this ancestor provides its own value
            $ancestorValue = $provided($ancestor);

            // Return the nearest ancestor's provided value
            if ($ancestorValue !== null) {
                return $ancestorValue;
            }
        }

        // No ancestor provides a value
        return null;
    }

    /**
     * The entry's own usable city, or null when it stores nothing usable.
     *
     * No toggle case, unlike the street address: a facility blueprint either carries a
     * `city` field or does not.
     */
    private function providedCity(
        Entry $entry
    ): ?string {
        // The city has no toggle, so it is an ordinary field read
        return $this->providedValue($entry, self::CITY_HANDLE);
    }

    /**
     * The entry's own usable value for an ungated address field, or null when it stores
     * nothing usable.
     *
     * Every address component except the street address is ungated on a facility, so one
     * reader serves them all rather than a near-identical method per handle.
     */
    private function providedValue(
        Entry $entry,
        string $handle
    ): ?string {
        // Read the stored value
        $value = $entry->get($handle);

        // Only a non-empty string counts as provided
        if (
            ! is_string($value)
            || trim($value) === ''
        ) {
            return null;
        }

        // Return the entry's own value
        return $value;
    }

    /**
     * The entry's own usable street address, or null when it inherits (a wing, or
     * a parking division with its address toggle off) or stores nothing usable.
     */
    private function providedStreetAddress(
        Entry $entry
    ): ?string {
        // A parking division provides its own address only when the toggle is on
        if (
            $this->blueprintHandle($entry) === self::TOGGLED_ADDRESS_BLUEPRINT
            && ! $entry->get(self::ADDRESS_TOGGLE_HANDLE)
        ) {
            return null;
        }

        // Read the stored street address
        $address = $entry->get(self::STREET_ADDRESS_HANDLE);

        // Only a non-empty string counts as a provided address
        if (
            ! is_string($address)
            || trim($address) === ''
        ) {
            return null;
        }

        // Return the entry's own street address
        return $address;
    }

    /**
     * Resolve the entry's blueprint handle, or an empty string when unavailable.
     */
    private function blueprintHandle(
        Entry $entry
    ): string {
        // Read the entry's blueprint (untyped on the entry contract)
        $blueprint = $entry->blueprint();

        // Treat a missing or unexpected blueprint as no handle
        if (! $blueprint instanceof Blueprint) {
            return '';
        }

        // Return the blueprint handle or an empty string when unavailable
        return $blueprint->handle() ?? '';
    }
}
