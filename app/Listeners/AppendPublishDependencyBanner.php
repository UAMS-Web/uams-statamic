<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Fieldtypes\PublishDependencyBanner;
use App\Services\PublishDependencies\PublishDependencyResolver;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry;
use Statamic\Events\EntryBlueprintFound;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Fields\Blueprint;

/**
 * Control-Panel banner for the opt-in publish-dependency mechanism (UAMS-Web/uams-statamic-archive#1593), the
 * CP-signal half — appends a read-only `publish_dependency_banner` field to every
 * blueprint that declares the tether field ({@see PublishDependencyResolver::FIELD_HANDLE}),
 * mirroring {@see AppendOrphanBanner}. The blueprint declaring the tether *is* the
 * opt-in signal, so a collection is covered with zero additional wiring.
 *
 * The field is inert on the front end and shows a warning in the publish form only
 * when the specific entry is suppressed (computed server-side by
 * {@see PublishDependencyBanner}). The editor opts a dependent out by editing or
 * clearing its tether.
 */
class AppendPublishDependencyBanner
{
    /**
     * The injected banner field handle.
     */
    private const string FIELD_HANDLE = 'publish_dependency_banner';

    /**
     * The `collections.` blueprint namespace prefix.
     */
    private const string COLLECTION_NAMESPACE_PREFIX = 'collections.';

    public function __construct(
        private readonly PublishDependencyResolver $resolver
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

        // Opt-in signal: the blueprint declares the tether field. A blueprint
        // without it never shows the banner.
        if (! $blueprint->hasField(PublishDependencyResolver::FIELD_HANDLE)) {
            return;
        }

        $collection = $this->collectionFor($event);
        if (! $collection instanceof Collection || ! $this->resolver->isEnabledFor($collection)) {
            return;
        }

        $blueprint->ensureFieldPrepended(self::FIELD_HANDLE, [
            'type' => self::FIELD_HANDLE,
            'display' => 'Publish Dependency Status',
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
