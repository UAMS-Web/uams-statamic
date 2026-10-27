<?php

declare(strict_types=1);

namespace App\Listeners;

use Exception;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry;
use Statamic\Events\CollectionSaving;
use Statamic\Structures\CollectionStructure;
use Statamic\Structures\Tree;

/**
 * Rejects saving a collection whose `mount` resolves to the structural root of
 * its target's site tree (a site's home page).
 *
 * A collection mounted to a `pages` tree root collapses every entry into the
 * site-root namespace (`/health/{slug}`) instead of the dedicated landing the
 * subsite is built around — a silent misconfiguration. This is the mount-side
 * complement to the entry-hierarchy validators (`ValidateClinicalExpertiseTree`
 * et al.): those guard positions *inside* a collection; this guards where the
 * collection *mounts*.
 *
 * The guard is deliberately NOT keyed to the `clinical_*` family — no collection
 * should ever mount to a site root, so it applies to any collection whose mount
 * resolves to a tree root (issue UAMS-Web/uams-statamic-archive#1324, Option C). A collection with no mount, a
 * mount into an unstructured collection, or a mount into a structure that does
 * not expect a root is a no-op. The root is resolved against the mount entry's
 * OWN site tree, so it is correct across sites.
 *
 * `CollectionSaving` halts on the first throwing listener, so throwing here blocks
 * the save and surfaces the message in the Control Panel.
 */
class PreventMountingToTreeRoot
{
    /**
     * Handle the CollectionSaving event.
     *
     * @param  CollectionSaving  $event  The event carrying the collection being saved
     *
     * @throws Exception If the collection's mount resolves to its site tree's root
     */
    public function handle(
        CollectionSaving $event
    ): void {
        /** @var Collection $collection */
        $collection = $event->collection;

        // No (resolvable) mount configured — nothing to check.
        $mountEntry = $collection->mount();
        if (! $mountEntry instanceof Entry) {
            return;
        }

        // The mount target must live in a structured collection for a "root" to
        // exist; a mount into a flat collection has no tree root.
        $mountCollection = $mountEntry->collection();
        if (! $mountCollection instanceof Collection) {
            return;
        }

        $structure = $mountCollection->structure();
        if (! $structure instanceof CollectionStructure) {
            return;
        }

        // Resolve the root of the mount entry's OWN site tree (multi-site
        // correctness). `Tree::root()` returns the raw root node (`['entry' =>
        // <id>, ...]`), or null when the structure does not expect a root — in
        // which case there is nothing to collide with.
        $tree = $structure->in($mountEntry->locale());
        $rootNode = $tree instanceof Tree ? $tree->root() : null;
        if (! \is_array($rootNode)) {
            return;
        }

        // The mount collapses the site-root namespace only when it points AT the
        // root entry itself. Any non-root page (top-level or deeper) is allowed.
        if (($rootNode['entry'] ?? null) === $mountEntry->id()) {
            throw new Exception(sprintf(
                "The '%s' collection cannot be mounted to a site's home page (the root of the '%s' structure). Choose a non-root page as the mount target.",
                $this->stringify($collection->title()),
                $this->stringify($mountCollection->handle()),
            ));
        }
    }

    /**
     * Coerce an untyped Statamic getter value (title / handle) to a string for
     * safe interpolation into the Control Panel error message.
     */
    private function stringify(mixed $value): string
    {
        return \is_scalar($value) ? (string) $value : '';
    }
}
