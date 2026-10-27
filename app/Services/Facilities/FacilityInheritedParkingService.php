<?php

declare(strict_types=1);

namespace App\Services\Facilities;

use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Fields\Blueprint;
use Statamic\Structures\Page;

/**
 * Resolves the effective (own-or-inherited) parking values for `facilities` and
 * `clinical_locations` entries at render time.
 *
 * Parking is modelled as a first-class parking entity (`parking_facility` /
 * `parking_division`). Consumers reference one and inherit-with-override:
 * a `building` links one behind `parking_query`; a `building_division` (wing)
 * inherits its parent building's via the structure tree; a contained `location`
 * inherits its referenced `facility` entry's via the `facility` reference; a
 * standalone `location` picks one directly. The effective parking facility is
 * resolved by walking that reference/structure chain (cycle-safe via a seen-set);
 * the leaf instructions/directions come from the consumer's own value when its
 * inherit toggle is off, otherwise from the resolved entity, and the free/paid
 * flags always come from the resolved entity.
 */
final class FacilityInheritedParkingService
{
    private const string BUILDING = 'building';

    private const string BUILDING_DIVISION = 'building_division';

    private const string PARKING_FACILITY = 'parking_facility';

    private const string PARKING_DIVISION = 'parking_division';

    private const string LOCATION = 'location';

    private const string FACILITY_QUERY = 'facility_query';

    private const string FACILITY = 'facility';

    private const string PARKING_QUERY = 'parking_query';

    private const string PARKING_FACILITY_FIELD = 'parking_facility';

    private const string PARKING_FACILITY_INHERIT = 'parking_facility_inherit_query';

    private const string PARKING_INSTRUCTIONS = 'parking_instructions';

    private const string PARKING_INSTRUCTIONS_INHERIT = 'parking_instructions_inherit_query';

    private const string DIRECTIONS_FROM_PARKING = 'directions_from_parking';

    private const string DIRECTIONS_FROM_PARKING_INHERIT = 'directions_from_parking_inherit_query';

    private const string PARKING_FREE = 'parking_free_query';

    private const string PARKING_VALIDATED = 'parking_validated_query';

    /**
     * Resolve the effective parking facility entity for a consumer entry, or null
     * when none is resolvable.
     */
    public function resolveParkingFacility(
        Entry $entry
    ): ?Entry {
        return $this->walkToParkingFacility($entry, []);
    }

    /**
     * Resolve the effective parking facility's title, or null when no facility
     * resolves or its title is blank (UAMS-Web/uams-statamic-archive#3172).
     */
    public function resolveParkingFacilityName(
        Entry $entry
    ): ?string {
        // Read the title of whichever parking facility the entry resolves to
        $title = $this->resolveParkingFacility($entry)?->get('title');

        return is_string($title) && trim($title) !== '' ? $title : null;
    }

    /**
     * Resolve the effective parking instructions: the entry's own value when its
     * inherit toggle is explicitly off, otherwise the resolved entity's value.
     */
    public function resolveParkingInstructions(
        Entry $entry
    ): mixed {
        return $this->resolveInheritedEntityField(
            $entry,
            self::PARKING_INSTRUCTIONS_INHERIT,
            self::PARKING_INSTRUCTIONS
        );
    }

    /**
     * Resolve the effective directions from parking: own value when its inherit
     * toggle is explicitly off, otherwise the resolved entity's value.
     */
    public function resolveDirectionsFromParking(
        Entry $entry
    ): mixed {
        return $this->resolveInheritedEntityField(
            $entry,
            self::DIRECTIONS_FROM_PARKING_INHERIT,
            self::DIRECTIONS_FROM_PARKING
        );
    }

    /**
     * Resolve whether the effective parking entity is free.
     */
    public function resolveParkingFree(
        Entry $entry
    ): ?bool {
        return $this->resolveEntityBool($entry, self::PARKING_FREE);
    }

    /**
     * Resolve whether the effective parking entity offers validated parking.
     */
    public function resolveParkingValidated(
        Entry $entry
    ): ?bool {
        return $this->resolveEntityBool($entry, self::PARKING_VALIDATED);
    }

    /**
     * Walk the reference/structure chain to the effective parking entity, guarding
     * against cycles with the set of already-visited entry ids.
     *
     * @param  array<string, true>  $seen  Entry ids already visited on this walk
     */
    private function walkToParkingFacility(
        Entry $entry,
        array $seen
    ): ?Entry {
        // Resolve a stable key, stopping on an unkeyable entry
        $key = $this->entryKey($entry);

        // Stop on an unkeyable entry or a cycle back to a visited entry
        if (
            $key === ''
            || isset($seen[$key])
        ) {
            return null;
        }

        // Record this entry as visited for the remainder of the walk
        $seen[$key] = true;

        // Dispatch by blueprint: parking entities are their own facility, building
        // links one behind its toggle, wings and locations inherit-with-override
        return match ($this->blueprintHandle($entry)) {
            self::PARKING_FACILITY, self::PARKING_DIVISION => $entry,
            self::BUILDING => $entry->get(self::PARKING_QUERY)
                ? $this->loadSingleEntry($entry, self::PARKING_FACILITY_FIELD)
                : null,
            self::BUILDING_DIVISION => $this->walkBuildingDivisionParking($entry, $seen),
            self::LOCATION => $this->walkLocationParking($entry, $seen),
            default => null,
        };
    }

    /**
     * Resolve a wing's parking: inherit the parent building's chain by default,
     * otherwise the wing's own override entity.
     *
     * @param  array<string, true>  $seen  Entry ids already visited on this walk
     */
    private function walkBuildingDivisionParking(
        Entry $entry,
        array $seen
    ): ?Entry {
        // Use the own override entity when inheritance is explicitly turned off
        if ($entry->get(self::PARKING_FACILITY_INHERIT) === false) {
            return $this->loadSingleEntry($entry, self::PARKING_FACILITY_FIELD);
        }

        // Otherwise inherit from the parent building's parking chain
        $parent = $this->structuralParentEntity($entry);

        // Stop when the wing has no structural parent to inherit from
        if (! $parent instanceof Entry) {
            return null;
        }

        // Continue the walk from the parent building
        return $this->walkToParkingFacility($parent, $seen);
    }

    /**
     * Resolve a location's parking: a contained location inherits its facility's
     * chain by default (own override when off); a standalone root location picks
     * one directly when parking is available; a nested child location (which stores
     * no parking of its own — those fields are gated to root locations) inherits the
     * whole chain from its parent location.
     *
     * @param  array<string, true>  $seen  Entry ids already visited on this walk
     */
    private function walkLocationParking(
        Entry $entry,
        array $seen
    ): ?Entry {
        // Handle a contained location that sits inside a facility entry
        if ($entry->get(self::FACILITY_QUERY)) {
            // Use the own override entity when inheritance is explicitly turned off
            if ($entry->get(self::PARKING_FACILITY_INHERIT) === false) {
                return $this->loadSingleEntry($entry, self::PARKING_FACILITY_FIELD);
            }

            // Otherwise inherit from the referenced facility entry's parking chain
            $facility = $this->loadSingleEntry($entry, self::FACILITY);

            // Stop when the referenced facility cannot be loaded
            if (! $facility instanceof Entry) {
                return null;
            }

            // Continue the walk from the referenced facility
            return $this->walkToParkingFacility($facility, $seen);
        }

        // A standalone root location uses its directly-picked entity when parking exists
        if ($entry->get(self::PARKING_QUERY)) {
            return $this->loadSingleEntry($entry, self::PARKING_FACILITY_FIELD);
        }

        // A nested child location inherits the entire chain from its parent location
        $parent = $this->structuralParentEntity($entry);

        // Continue the walk from a parent location when one exists
        if (
            $parent instanceof Entry
            && $this->blueprintHandle($parent) === self::LOCATION
        ) {
            return $this->walkToParkingFacility($parent, $seen);
        }

        // No parking is resolvable for this location
        return null;
    }

    /**
     * Resolve a leaf field that inherits from the parking entity: the consumer's
     * own value when its inherit toggle is explicitly off, otherwise the resolved
     * entity's value.
     *
     * Note: the override path returns the consumer's own value regardless of whether
     * any parking entity resolves, so a consumer that has overridden instructions or
     * directions but no resolvable parking still returns those own values. Templates
     * and structured-data consumers must therefore gate instructions/directions
     * output on a non-null `resolved_parking_facility`.
     */
    private function resolveInheritedEntityField(
        Entry $entry,
        string $inheritToggle,
        string $field
    ): mixed {
        // Return the consumer's own value when it overrides the inherited one
        if ($entry->get($inheritToggle) === false) {
            return $entry->get($field);
        }

        // Otherwise read the field from the resolved parking entity
        return $this->resolveParkingFacility($entry)?->get($field);
    }

    /**
     * Resolve a boolean flag from the effective parking entity, or null when no
     * entity resolves or the value is not boolean.
     */
    private function resolveEntityBool(
        Entry $entry,
        string $field
    ): ?bool {
        // Resolve the effective parking entity
        $facility = $this->resolveParkingFacility($entry);

        // Stop when no parking entity is resolvable
        if (! $facility instanceof Entry) {
            return null;
        }

        // Read the flag, narrowing to a boolean
        $value = $facility->get($field);

        // Return the boolean flag or null when the stored value is not boolean
        return is_bool($value) ? $value : null;
    }

    /**
     * Load the single referenced entry from a `max_items: 1` entries field, or
     * null when the reference is absent or unresolvable.
     */
    private function loadSingleEntry(
        Entry $entry,
        string $field
    ): ?Entry {
        // Read the stored relationship value
        $value = $entry->get($field);

        // Normalize an array-shaped entries value to its first id
        if (is_array($value)) {
            $value = $value[0] ?? null;
        }

        // Stop when the reference is not a usable id string
        if (
            ! is_string($value)
            || $value === ''
        ) {
            return null;
        }

        // Load the referenced entry, narrowing to the concrete entry type
        $found = EntryFacade::find($value);

        // Return the referenced entry or null when it cannot be loaded
        return $found instanceof Entry ? $found : null;
    }

    /**
     * Resolve the structural parent entry (the building a wing sits in), or null
     * when there is no structural parent.
     */
    private function structuralParentEntity(
        Entry $entry
    ): ?Entry {
        // Read the structural parent page from the collection tree (untyped)
        $parentPage = $entry->parent();

        // Stop when the entry has no usable structural parent page
        if (! $parentPage instanceof Page) {
            return null;
        }

        // Resolve the parent page id, stopping when it is not a usable id
        $parentId = $parentPage->id();

        // Stop when the parent id is not a usable id string
        if (
            ! is_string($parentId)
            || $parentId === ''
        ) {
            return null;
        }

        // Load the parent entry, narrowing to the concrete entry type
        $parent = EntryFacade::find($parentId);

        // Return the parent entry or null when it cannot be loaded
        return $parent instanceof Entry ? $parent : null;
    }

    /**
     * Resolve a stable string key for an entry, or an empty string when the entry
     * has no usable id.
     */
    private function entryKey(
        Entry $entry
    ): string {
        // Read the entry id
        $id = $entry->id();

        // Return the id as a string, or empty when it is not a scalar
        return is_scalar($id) ? (string) $id : '';
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
