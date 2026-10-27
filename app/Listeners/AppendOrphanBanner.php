<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Fieldtypes\OrphanBanner;
use App\Services\OrphanedAncestors\OrphanedAncestorResolver;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry;
use Statamic\Events\EntryBlueprintFound;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Fields\Blueprint;

/**
 * Universal Control-Panel orphan banner (UAMS-Web/uams-statamic-archive#1429), the CP-signal half — appends a
 * read-only `orphan_banner` field to the blueprints of every handled (routable +
 * hierarchical, not opted-out) collection at resolve time, generalizing UAMS-Web/uams-statamic-archive#1077's
 * per-blueprint `kb_orphan_banner` field.
 *
 * The field is inert on the front end and shows a warning in the publish form
 * only when the specific entry is orphaned (computed server-side by
 * {@see OrphanBanner}). A new routable + hierarchical collection
 * is covered with zero additional wiring.
 */
class AppendOrphanBanner
{
    /**
     * The injected banner field handle.
     */
    private const string FIELD_HANDLE = 'orphan_banner';

    /**
     * The `collections.` blueprint namespace prefix.
     */
    private const string COLLECTION_NAMESPACE_PREFIX = 'collections.';

    public function __construct(
        private readonly OrphanedAncestorResolver $resolver
    ) {}

    /**
     * Handle the EntryBlueprintFound event.
     *
     * @param  EntryBlueprintFound  $event  The resolved blueprint (+ entry when editing)
     */
    public function handle(
        EntryBlueprintFound $event
    ): void {
        $blueprint = $event->blueprint;
        if (! $blueprint instanceof Blueprint || $blueprint->hasField(self::FIELD_HANDLE)) {
            return;
        }

        $collection = $this->collectionFor($event);
        if (! $collection instanceof Collection || ! $this->resolver->handles($collection)) {
            return;
        }

        $blueprint->ensureFieldPrepended(self::FIELD_HANDLE, [
            'type' => self::FIELD_HANDLE,
            'display' => 'Orphaned Redirect Status',
            'localizable' => false,
            'listable' => false,
        ]);
    }

    /**
     * Resolve the collection the blueprint belongs to — from the entry when the
     * event carries one (the publish form), else from the `collections.<handle>`
     * blueprint namespace (blueprint listing).
     */
    private function collectionFor(EntryBlueprintFound $event): ?Collection
    {
        $entry = $event->entry;
        if ($entry instanceof Entry) {
            $collection = $entry->collection();

            return $collection instanceof Collection ? $collection : null;
        }

        $namespace = $event->blueprint instanceof Blueprint ? $event->blueprint->namespace() : null;
        if (! \is_string($namespace) || ! str_starts_with($namespace, self::COLLECTION_NAMESPACE_PREFIX)) {
            return null;
        }

        $collection = CollectionFacade::findByHandle(substr($namespace, \strlen(self::COLLECTION_NAMESPACE_PREFIX)));

        return $collection instanceof Collection ? $collection : null;
    }
}
