<?php

declare(strict_types=1);

namespace App\Services\Locations;

use App\Services\Facilities\FacilityInheritedAddressService;
use App\Services\Facilities\FacilityInheritedParkingService;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Fields\Blueprint;
use Statamic\Fields\LabeledValue;
use Statamic\Fields\Value;
use Statamic\Structures\Page;

/**
 * Resolves the effective street address for a `clinical_locations` entry (UAMS-Web/uams-statamic-archive#1269).
 *
 * The sibling {@see FacilityInheritedAddressService} answers the same question for the
 * `facilities` collection, and this exists because it could not be asked of a location: that
 * service walks a facility's structural ancestors, while a location reaches its address by one
 * of three different routes.
 *
 *   1. A CHILD location inherits its parent location's resolved address, unless the editor has
 *      overridden it. That override is UAMS-Web/uams-statamic-archive#1269, split per group by UAMS-Web/uams-statamic-archive#2608 (see below).
 *   2. A location CONTAINED within a larger facility takes the address of the facility it
 *      names, resolved by the facilities service — which may itself inherit from a building.
 *   3. A STANDALONE location uses the `street_address` subfield of its own `address` value.
 *
 * Since UAMS-Web/uams-statamic-archive#2608 a child overrides its parent's address one GROUP at a time, matching
 * `UAMS-Web/UAMSWP-Find-a-Doc#833`: a master toggle, `parent_address_override_query`, plus one
 * toggle per group (street: `address` and `regions`; facility: `facility_query` and
 * `facility`; unit: `facility_level` and `suite`; map: `map`). A location OWNS a group when
 * it has no parent location, or when its master and that group's toggle are both on; a group
 * it does not own comes from its parent location.
 *
 * Containment is decided by whoever owns the facility group, and it wins over the street and
 * map groups at the same node: a location that owns its facility and answers `facility_query`
 * yes takes its street address and its regions from that facility and has no entrance pin of
 * its own, exactly
 * as a top-level contained location always has. That keeps a top-level location, and a child
 * with every group overridden, resolving exactly as before UAMS-Web/uams-statamic-archive#2608.
 *
 * Until this class the address was the one component of the location inherit-with-override
 * model with no resolver: parking, its instructions and its directions all resolve through
 * {@see FacilityInheritedParkingService}, which registers computeds on
 * `clinical_locations` as well as `facilities`, while `resolved_street_address` was registered
 * on `facilities` only. A template rendering a location's address therefore had to reimplement
 * the ancestor walk that already existed for parking.
 *
 * The location profile reads these computeds since UAMS-Web/uams-statamic-archive#2389, and the location card and a
 * provider's primary location since UAMS-Web/uams-statamic-archive#2608, so no template reimplements the walk.
 */
final readonly class LocationInheritedAddressService
{
    /**
     * Blueprint handle of a clinical location, which is what a parent must be to inherit from.
     */
    private const string LOCATION = 'location';

    /**
     * Master toggle letting a child location override any of its parent's address groups (UAMS-Web/uams-statamic-archive#1269).
     */
    private const string ADDRESS_OVERRIDE = 'parent_address_override_query';

    /**
     * Address group holding the street address, city, state, postal code and region (UAMS-Web/uams-statamic-archive#2608).
     */
    public const string GROUP_STREET = 'street';

    /**
     * Address group holding the containment question and the containing facility (UAMS-Web/uams-statamic-archive#2608).
     */
    public const string GROUP_FACILITY = 'facility';

    /**
     * Address group holding the facility level and the suite (UAMS-Web/uams-statamic-archive#2608).
     */
    public const string GROUP_UNIT = 'unit';

    /**
     * Address group holding the entrance map pin (UAMS-Web/uams-statamic-archive#2608).
     */
    public const string GROUP_MAP = 'map';

    /**
     * The toggle a child turns on, with the master, to override each group (UAMS-Web/uams-statamic-archive#2608).
     *
     * @var array<string, string>
     */
    public const array GROUP_OVERRIDE = [
        self::GROUP_STREET => 'parent_address_override_street_query',
        self::GROUP_FACILITY => 'parent_address_override_facility_query',
        self::GROUP_UNIT => 'parent_address_override_unit_query',
        self::GROUP_MAP => 'parent_address_override_map_query',
    ];

    /**
     * Toggle handle marking a location as contained within a larger facility.
     */
    private const string FACILITY_QUERY = 'facility_query';

    /**
     * Relationship handle naming the containing facility.
     */
    private const string FACILITY = 'facility';

    /**
     * The location's own address field.
     */
    private const string ADDRESS = 'address';

    /**
     * Subfield handle holding the street address inside the `address` value.
     */
    private const string STREET_ADDRESS_SUBFIELD = 'street_address';

    /**
     * The Address fieldtype subfield holding the second address line.
     */
    private const string ADDRESS_LINE_2_SUBFIELD = 'address_line_2';

    /**
     * Subfield handle holding the city inside the `address` value (UAMS-Web/uams-statamic-archive#1225).
     */
    private const string CITY_SUBFIELD = 'city';

    /**
     * Subfield handle holding the postal code inside the `address` value (UAMS-Web/uams-statamic-archive#2389).
     */
    private const string POSTAL_CODE_SUBFIELD = 'postal_code';

    /**
     * The facility level field, a select keyed by level.
     */
    private const string FACILITY_LEVEL = 'facility_level';

    /**
     * The suite or other unit field.
     */
    private const string SUITE = 'suite';

    /**
     * The entrance map pin field.
     */
    private const string MAP = 'map';

    /**
     * The Arkansas region terms field.
     */
    private const string REGIONS = 'regions';

    /**
     * Subfield handle the Address fieldtype stores every preset's state under.
     */
    private const string CANONICAL_STATE_SUBFIELD = 'state_province_region';

    /**
     * Subfield handle naming which preset the stored address was entered under.
     */
    private const string PRESET_SUBFIELD = 'preset';

    /**
     * The state component's storage handle per preset (UAMS-Web/uams-statamic-archive#2389).
     *
     * One component, three handles: `App\Fieldtypes\Address` shows exactly one of them
     * and gates it on the stored `preset`, so reading a fixed handle returns null for any
     * address entered under either of the other two.
     *
     * @var array<string, string>
     */
    private const array STATE_SUBFIELD_BY_PRESET = [
        'american' => 'state_province_region_american',
        'canadian' => 'state_province_region_canadian',
        'international' => 'state_province_region_international',
    ];

    /**
     * @param  FacilityInheritedAddressService  $facilityInheritedAddress  Resolves a facility's own-or-inherited address.
     */
    public function __construct(
        private FacilityInheritedAddressService $facilityInheritedAddress
    ) {}

    /**
     * Resolve the effective street address for a location, or null when none resolves.
     */
    public function resolveStreetAddress(
        Entry $location
    ): ?string {
        // Begin the walk with an empty visited set, resolving the street address
        return $this->walkStreet(
            $location,
            [],
            fn (Entry $facility): ?string => $this->facilityInheritedAddress->resolveStreetAddress($facility),
            fn (Entry $owner): ?string => $this->ownAddressComponent($owner, self::STREET_ADDRESS_SUBFIELD)
        );
    }

    /**
     * Resolve the effective second address line for a location, or null when none (UAMS-Web/uams-statamic-archive#2608).
     *
     * Part of the street group, so it travels with the street: a child that inherits its
     * street inherits this line too. A location contained within a facility has none, as
     * the facilities resolver carries no second line.
     */
    public function resolveAddressLine2(
        Entry $location
    ): ?string {
        // Walk the street group, which a contained location answers with no second line
        return $this->walkStreet(
            $location,
            [],
            fn (Entry $_facility): ?string => null,
            fn (Entry $owner): ?string => $this->ownAddressComponent($owner, self::ADDRESS_LINE_2_SUBFIELD)
        );
    }

    /**
     * Resolve the effective city for a location, or null when none resolves (UAMS-Web/uams-statamic-archive#1225).
     *
     * The same three routes as the street address, governed by the same override toggle:
     * the city is an address component, so a child that has taken its address over has
     * taken its city with it.
     */
    public function resolveCity(
        Entry $location
    ): ?string {
        // Begin the walk with an empty visited set, resolving the city
        return $this->walkStreet(
            $location,
            [],
            fn (Entry $facility): ?string => $this->facilityInheritedAddress->resolveCity($facility),
            fn (Entry $owner): ?string => $this->ownAddressComponent($owner, self::CITY_SUBFIELD)
        );
    }

    /**
     * Resolve the effective postal code for a location, or null when none resolves (UAMS-Web/uams-statamic-archive#2389).
     *
     * Named `postal_code` in the location's address value and `zip` on a facility; the
     * facilities resolver owns that difference.
     */
    public function resolvePostalCode(
        Entry $location
    ): ?string {
        // Begin the walk with an empty visited set, resolving the postal code
        return $this->walkStreet(
            $location,
            [],
            fn (Entry $facility): ?string => $this->facilityInheritedAddress->resolvePostalCode($facility),
            fn (Entry $owner): ?string => $this->ownAddressComponent($owner, self::POSTAL_CODE_SUBFIELD)
        );
    }

    /**
     * Resolve the effective state for a location, or null when none resolves (UAMS-Web/uams-statamic-archive#2389).
     *
     * The only component whose storage handle is not fixed: the location side keeps three
     * preset-specific variants and shows one, so which handle holds the value is a property
     * of the stored address rather than of the field. A facility keeps a single flat
     * `state`, so the two sides are reconciled here rather than in the walk.
     */
    public function resolveState(
        Entry $location
    ): ?string {
        // Begin the walk, reading the canonical state handle and then the preset variant
        return $this->walkStreet(
            $location,
            [],
            fn (Entry $facility): ?string => $this->facilityInheritedAddress->resolveState($facility),
            fn (Entry $owner): ?string => $this->ownAddressComponent($owner, self::CANONICAL_STATE_SUBFIELD)
                ?? $this->ownAddressComponent($owner, $this->stateSubfield($owner))
        );
    }

    /**
     * The facility a location is contained within, or null when it is not (UAMS-Web/uams-statamic-archive#2608).
     *
     * Read from whichever location owns the facility group: the location itself when it
     * has no parent or has overridden that group, otherwise the nearest ancestor that does.
     */
    public function resolveFacility(
        Entry $location
    ): ?Entry {
        // Find the location the facility group comes from
        $owner = $this->groupOwner($location, self::GROUP_FACILITY, []);

        // Stop when no owner resolves or the owner is not contained within a facility
        if (! $owner instanceof Entry || ! $owner->get(self::FACILITY_QUERY)) {
            return null;
        }

        // Return the referenced facility when it loads
        return $this->loadSingleEntry($owner, self::FACILITY);
    }

    /**
     * The id of the facility a location is contained within, for a computed (UAMS-Web/uams-statamic-archive#2608).
     */
    public function resolveFacilityId(
        Entry $location
    ): ?string {
        // Return the facility's id, or null when the location is not contained
        $id = $this->resolveFacility($location)?->id();

        return is_string($id) ? $id : null;
    }

    /**
     * The name of the facility a location is contained within, for a template (UAMS-Web/uams-statamic-archive#2608).
     *
     * A string rather than the id, because no tag in this application loads an entry by id
     * inside a partial: `entries:find` renders nothing.
     */
    public function resolveFacilityName(
        Entry $location
    ): ?string {
        // Return the facility's title, or null when the location is not contained
        $title = $this->resolveFacility($location)?->get('title');

        return is_string($title) && trim($title) !== '' ? $title : null;
    }

    /**
     * The location whose `facility_level` and `suite` apply to this one, or null (UAMS-Web/uams-statamic-archive#2608).
     *
     * A location that owns its facility group and is not contained within a facility has no
     * level or suite, whatever is stored, as a top-level location never has.
     */
    public function resolveUnitLocation(
        Entry $location
    ): ?Entry {
        // Find the location the unit group comes from
        $owner = $this->groupOwner($location, self::GROUP_UNIT, []);

        // Stop when no owner resolves, or the owner answers its own containment with no
        if (
            ! $owner instanceof Entry
            || ($this->ownsGroup($owner, self::GROUP_FACILITY) && ! $owner->get(self::FACILITY_QUERY))
        ) {
            return null;
        }

        // Return the owner only when it stores a level or a suite
        return $this->hasValue($owner->get(self::FACILITY_LEVEL)) || $this->hasValue($owner->get(self::SUITE))
            ? $owner
            : null;
    }

    /**
     * The label of the facility level that applies to a location, or null (UAMS-Web/uams-statamic-archive#2608).
     *
     * The label rather than the stored key: `facility_level` is a select, and what a visitor
     * reads is "First Floor", not "1". The `false` option ("Single-Story Building / The
     * Location Is the Facility") resolves to null, since there is no level to name.
     */
    public function resolveFacilityLevel(
        Entry $location
    ): ?string {
        // Find the location the unit group comes from
        $owner = $this->resolveUnitLocation($location);

        // Stop when no unit applies or the owner stores no level
        $stored = $owner?->get(self::FACILITY_LEVEL);
        if (! $owner instanceof Entry || ! $this->hasValue($stored)) {
            return null;
        }

        // The `false` option means the location is the facility, which has no level to show
        if ($stored === false || $stored === 'false') {
            return null;
        }

        // Read the augmented value, which a select yields as a labeled value
        $augmented = $owner->augmentedValue(self::FACILITY_LEVEL);
        $value = $augmented instanceof Value ? $augmented->value() : $augmented;
        $label = $value instanceof LabeledValue ? $value->label() : $value;

        // Only a non-empty string counts as a level
        return is_string($label) && trim($label) !== '' ? $label : null;
    }

    /**
     * The suite or other unit that applies to a location, or null (UAMS-Web/uams-statamic-archive#2608).
     */
    public function resolveSuite(
        Entry $location
    ): ?string {
        // Find the location the unit group comes from
        $suite = $this->resolveUnitLocation($location)?->get(self::SUITE);

        // Only a non-empty string counts as a suite
        return is_string($suite) && trim($suite) !== '' ? $suite : null;
    }

    /**
     * The stored entrance map pin that applies to a location, or null (UAMS-Web/uams-statamic-archive#2608).
     *
     * A contained location has none of its own: its entrance is the facility's.
     */
    public function resolveMap(
        Entry $location
    ): mixed {
        // Walk the street-shaped chain for the map group
        return $this->walkGroup(
            $location,
            [],
            self::GROUP_MAP,
            fn (Entry $facility): mixed => null,
            fn (Entry $owner): mixed => $this->hasValue($owner->get(self::MAP)) ? $owner->get(self::MAP) : null
        );
    }

    /**
     * The stored region terms that apply to a location, or null (UAMS-Web/uams-statamic-archive#2608).
     *
     * Part of the street group: the region follows the street address, so a location
     * contained within a facility takes the facility's regions, as it takes its street.
     */
    public function resolveRegions(
        Entry $location
    ): mixed {
        // Walk the street-shaped chain for the regions value
        return $this->walkGroup(
            $location,
            [],
            self::GROUP_STREET,
            fn (Entry $facility): mixed => $this->hasValue($facility->get(self::REGIONS)) ? $facility->get(self::REGIONS) : null,
            fn (Entry $owner): mixed => $this->hasValue($owner->get(self::REGIONS)) ? $owner->get(self::REGIONS) : null
        );
    }

    /**
     * The region slugs that apply to a location, as a list, for a template or a facet (UAMS-Web/uams-statamic-archive#2608).
     *
     * The stored `regions` value has two shapes: `Terms::process()` saves a bare slug for a
     * CP save, while a programmatic write leaves a list. A slug carrying a `taxonomy::`
     * prefix is reduced to the slug.
     *
     * @return list<string>
     */
    public function resolveRegionSlugs(
        Entry $location
    ): array {
        // Read the regions value, and wrap a bare slug as a one-item list
        $regions = $this->resolveRegions($location);
        $values = is_array($regions) ? $regions : [$regions];

        // Keep each non-empty string, without any taxonomy prefix, once
        $slugs = [];
        foreach ($values as $value) {
            if (is_string($value) && trim($value) !== '') {
                $slugs[] = str_contains($value, '::') ? substr($value, strrpos($value, '::') + 2) : $value;
            }
        }

        return array_values(array_unique($slugs));
    }

    /**
     * The state subfield handle matching a location's stored preset.
     *
     * Defaults to the American variant when no preset is stored, which is what
     * `App\Fieldtypes\Address` itself falls back to: `process()` writes the first preset
     * option when the field config names none, and that option is `american`.
     */
    private function stateSubfield(
        Entry $location
    ): string {
        // Read the stored address value
        $address = $location->get(self::ADDRESS);

        // Read the preset the address was entered under, when the value is the expected map
        $preset = is_array($address) ? ($address[self::PRESET_SUBFIELD] ?? null) : null;

        // Return the variant for that preset, defaulting to the American one
        return is_string($preset) && isset(self::STATE_SUBFIELD_BY_PRESET[$preset])
            ? self::STATE_SUBFIELD_BY_PRESET[$preset]
            : self::STATE_SUBFIELD_BY_PRESET['american'];
    }

    /**
     * Walk the street group's chain for one component, typed for the string components.
     *
     * @param  array<string, true>  $seen  Ids already visited, guarding against a cycle in the tree
     * @param  callable(Entry): ?string  $fromFacility  Resolves the component from a containing facility
     * @param  callable(Entry): ?string  $fromOwner  Resolves the component from the location that owns the group
     */
    private function walkStreet(
        Entry $location,
        array $seen,
        callable $fromFacility,
        callable $fromOwner
    ): ?string {
        // Walk the street group and narrow the result to a string
        $value = $this->walkGroup($location, $seen, self::GROUP_STREET, $fromFacility, $fromOwner);

        return is_string($value) ? $value : null;
    }

    /**
     * Walk the inheritance chain for one group that a contained location takes from its facility.
     *
     * At each location: one that owns its facility group and is contained takes the value
     * from that facility; one that owns the group takes its own; any other inherits from its
     * parent location.
     *
     * @param  array<string, true>  $seen  Ids already visited, guarding against a cycle in the tree
     * @param  callable(Entry): mixed  $fromFacility  Resolves the value from a containing facility
     * @param  callable(Entry): mixed  $fromOwner  Resolves the value from the location that owns the group
     */
    private function walkGroup(
        Entry $location,
        array $seen,
        string $group,
        callable $fromFacility,
        callable $fromOwner
    ): mixed {
        // Stop on a structural cycle, which would otherwise recurse without end
        if (! $this->visit($location, $seen)) {
            return null;
        }

        // A location that owns its containment and is contained takes the facility's value
        if (
            $this->ownsGroup($location, self::GROUP_FACILITY)
            && $location->get(self::FACILITY_QUERY)
        ) {
            // Load the referenced facility
            $facility = $this->loadSingleEntry($location, self::FACILITY);

            // Delegate to the facility, which may itself inherit from a building
            return $facility instanceof Entry
                ? $fromFacility($facility)
                : null;
        }

        // A location that owns the group uses its own value
        if ($this->ownsGroup($location, $group)) {
            return $fromOwner($location);
        }

        // Otherwise the value comes from the parent location, which ownsGroup() found
        $parent = $this->parentLocation($location);

        return $parent instanceof Entry
            ? $this->walkGroup($parent, $seen, $group, $fromFacility, $fromOwner)
            : null;
    }

    /**
     * The location a group's values come from: the nearest of itself and its ancestors that owns it.
     *
     * @param  array<string, true>  $seen  Ids already visited, guarding against a cycle in the tree
     */
    private function groupOwner(
        Entry $location,
        string $group,
        array $seen
    ): ?Entry {
        // Stop on a structural cycle
        if (! $this->visit($location, $seen)) {
            return null;
        }

        // A location that owns the group answers for it
        if ($this->ownsGroup($location, $group)) {
            return $location;
        }

        // Otherwise ask the parent location
        $parent = $this->parentLocation($location);

        return $parent instanceof Entry
            ? $this->groupOwner($parent, $group, $seen)
            : null;
    }

    /**
     * Whether a location supplies a group itself rather than inheriting it (UAMS-Web/uams-statamic-archive#2608).
     *
     * True for a location with no parent location, and for a child whose master toggle and
     * the group's own toggle are both on.
     */
    private function ownsGroup(
        Entry $location,
        string $group
    ): bool {
        // A location with no parent location owns every group
        if (! $this->parentLocation($location) instanceof Entry) {
            return true;
        }

        // A child owns the group only with the master and the group toggle both on
        return (bool) $location->get(self::ADDRESS_OVERRIDE)
            && (bool) $location->get(self::GROUP_OVERRIDE[$group]);
    }

    /**
     * Record a visit, returning false when the location was already visited on this walk.
     *
     * @param  array<string, true>  $seen  Ids already visited, updated in place
     */
    private function visit(
        Entry $location,
        array &$seen
    ): bool {
        // Read the location id to record the visit
        $id = $location->id();

        // A location without a usable id cannot close a cycle by id
        if (! is_string($id)) {
            return true;
        }

        // Stop when this location has already been visited on this walk
        if (isset($seen[$id])) {
            return false;
        }

        // Record the visit before descending
        $seen[$id] = true;

        return true;
    }

    /**
     * The location's structural parent when that parent is itself a location, otherwise null.
     */
    private function parentLocation(
        Entry $location
    ): ?Entry {
        // Only a location inherits: a page on another blueprint in the same tree owns its own
        if ($this->blueprintHandle($location) !== self::LOCATION) {
            return null;
        }

        // Resolve the structural parent
        $parent = $this->structuralParentEntity($location);

        // Only a parent on the location blueprint is one to inherit from
        return $parent instanceof Entry && $this->blueprintHandle($parent) === self::LOCATION
            ? $parent
            : null;
    }

    /**
     * Whether a stored value counts as provided: not null, not blank, not an empty list.
     */
    private function hasValue(
        mixed $value
    ): bool {
        // Treat an empty list and a blank string as no value
        if (is_array($value)) {
            return $value !== [];
        }

        return is_string($value) ? trim($value) !== '' : $value !== null;
    }

    /**
     * One component of the location's own address, read out of the `address` subfield map.
     *
     * The city and the street address live in the same stored value, so they are read the
     * same way rather than through two near-identical accessors.
     */
    private function ownAddressComponent(
        Entry $location,
        string $subfield
    ): ?string {
        // Read the stored address value, which the Address fieldtype persists as a subfield map
        $address = $location->get(self::ADDRESS);

        // Stop when the value is not the expected map
        if (! is_array($address)) {
            return null;
        }

        // Read the requested subfield
        $value = $address[$subfield] ?? null;

        // Only a non-empty string counts as a provided component
        if (
            ! is_string($value)
            || trim($value) === ''
        ) {
            return null;
        }

        // Return the location's own component
        return $value;
    }

    /**
     * Load the entry a single-relationship field points at, or null when unusable.
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
     * Resolve the entry's structural parent as an entry, or null when it has none.
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

        // Resolve the parent page id
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
