<?php

declare(strict_types=1);

namespace App\Services\Locations;

use Illuminate\Support\Collection;
use Statamic\Contracts\Assets\Asset;
use Statamic\Entries\Entry;
use Statamic\Fields\Value;

/**
 * Resolves the photo gallery a `clinical_locations` profile shows (UAMS-Web/uams-statamic#1578).
 *
 * A standalone location shows its own `gallery_image`. A child location shows its parent's
 * gallery, walking up the tree, unless it overrides its parent's images
 * (`image_parent_override_query`) and its parent's gallery
 * (`gallery_image_parent_override_query`); an overriding child shows its own gallery, and
 * an empty one removes the parent's, as the field's instructions say. This mirrors
 * Find-a-Doc's `single-locations.php`, and the blueprint shows the gallery field only to a
 * location that owns it.
 */
final readonly class LocationInheritedGalleryService
{
    /**
     * The gallery field.
     */
    private const string GALLERY = 'gallery_image';

    /**
     * The toggle a child sets to override every parent image group.
     */
    private const string IMAGE_OVERRIDE = 'image_parent_override_query';

    /**
     * The toggle a child sets, within the image override, to override the parent gallery.
     */
    private const string GALLERY_OVERRIDE = 'gallery_image_parent_override_query';

    /**
     * @param  LocationInheritedAddressService  $addresses  Finds the parent location a child inherits from.
     */
    public function __construct(
        private LocationInheritedAddressService $addresses
    ) {}

    /**
     * The gallery assets a location shows, in their stored order.
     *
     * @param  Entry  $location  The location being shown.
     * @return list<Asset>
     */
    public function resolveGallery(
        Entry $location
    ): array {
        // Walk to the location that owns the gallery, guarding against a cycle
        $owner = $location;
        $seen = [];
        while (! isset($seen[$this->key($owner)])) {
            $seen[$this->key($owner)] = true;

            // A location with no parent location, or one overriding both toggles, owns its gallery
            $parent = $this->addresses->resolveParentLocation($owner);
            if (! $parent instanceof Entry || ($owner->get(self::IMAGE_OVERRIDE) && $owner->get(self::GALLERY_OVERRIDE))) {
                return $this->assets($owner);
            }

            // Otherwise the gallery comes from further up the tree
            $owner = $parent;
        }

        // A cycle in the tree has no owner
        return [];
    }

    /**
     * The owner's augmented gallery, as a list of assets.
     *
     * @param  Entry  $owner  The location that owns the gallery.
     * @return list<Asset>
     */
    private function assets(Entry $owner): array
    {
        // Augment through the field, so a one-file field and a many-file field both resolve
        $value = $owner->augmentedValue(self::GALLERY);
        $value = $value instanceof Value ? $value->value() : $value;

        // Normalize one asset, a collection or a list to a list of assets
        $items = match (true) {
            $value instanceof Asset => [$value],
            $value instanceof Collection => $value->all(),
            \is_array($value) => $value,
            default => [],
        };

        return \array_values(\array_filter($items, static fn (mixed $item): bool => $item instanceof Asset));
    }

    /**
     * A key identifying a location on the walk.
     *
     * @param  Entry  $location  The location visited.
     */
    private function key(Entry $location): string
    {
        // Use the id, falling back to the object's identity for an unsaved entry
        $id = $location->id();

        return \is_string($id) || \is_int($id) ? (string) $id : \spl_object_hash($location);
    }
}
