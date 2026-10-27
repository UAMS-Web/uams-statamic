<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Services\SchemaTemplates\SchemaJsonLdOutputCache;
use Statamic\Entries\Entry as EntryModel;
use Statamic\Events\EntrySaved;
use Statamic\Events\GlobalSetSaved;
use Statamic\Events\TermSaved;

/**
 * Listener that bumps the schema JSON-LD output cache when watched content
 * changes.
 *
 * Dispatches on Statamic save events and compares handles against config
 * allowlists before calling `SchemaJsonLdOutputCache::bump`.
 */
class InvalidateSchemaJsonLdCache
{
    /**
     * Route a Statamic save event to the matching invalidation helper.
     *
     * @param  EntrySaved|GlobalSetSaved|TermSaved  $event  Persisted entry, globals, or term event
     */
    public function handle(
        EntrySaved|GlobalSetSaved|TermSaved $event
    ): void {
        // Check if the payload is an entry save event
        if ($event instanceof EntrySaved) {
            // Delegate invalidation when the entry collection is watched
            $this->bumpWhenEntryCollectionMatches($event);

            return;
        }

        // Check if the payload is a global set save event
        if ($event instanceof GlobalSetSaved) {
            // Delegate invalidation when the global handle is watched
            $this->bumpWhenGlobalSetMatches($event);

            return;
        }

        // The remaining payload is a taxonomy term save event
        // Delegate invalidation when the taxonomy is watched
        $this->bumpWhenTaxonomyMatches($event);
    }

    /**
     * Bump the cache when the saved entry belongs to a watched collection handle.
     *
     * @param  EntrySaved  $event  Statamic event carrying the saved entry
     */
    private function bumpWhenEntryCollectionMatches(
        EntrySaved $event
    ): void {
        // Read the saved entry from the event
        $entry = $event->entry;

        // Check if the payload is a concrete Statamic entry model
        if (! $entry instanceof EntryModel) {
            return;
        }

        // Resolve the entry collection handle for allow-list comparison
        $handle = $entry->collectionHandle();

        // Load collection handles that should invalidate JSON-LD cache
        $watched = config(
            'schema_templates.json_ld_cache.invalidate_entry_collections',
            []
        );

        // Check if this collection handle appears in the configured allow list
        if (
            ! \is_array($watched)
            || ! \in_array($handle, $watched, true)
        ) {
            // Return early
            return;
        }

        // Bump the JSON-LD output cache generation
        app(SchemaJsonLdOutputCache::class)->bump();
    }

    /**
     * Bump the cache when the saved global set handle is listed in config.
     *
     * @param  GlobalSetSaved  $event  Statamic event carrying the saved globals container
     */
    private function bumpWhenGlobalSetMatches(
        GlobalSetSaved $event
    ): void {
        // Read the globals container from the event
        $globals = $event->globals;

        // Resolve the global set handle when the container exposes one
        $handle = \is_object($globals) && method_exists($globals, 'handle')
            ? $globals->handle()
            : null;

        // Check if a non-empty string handle was resolved
        if (
            ! \is_string($handle)
            || $handle === ''
        ) {
            // Return early
            return;
        }

        // Load global set handles that should invalidate JSON-LD cache
        $watched = config(
            'schema_templates.json_ld_cache.invalidate_global_set_handles',
            []
        );

        // Check if this global handle appears in the configured allow list
        if (
            ! \is_array($watched)
            || ! \in_array($handle, $watched, true)
        ) {
            // Return early
            return;
        }

        // Bump the JSON-LD output cache generation
        app(SchemaJsonLdOutputCache::class)->bump();
    }

    /**
     * Bump the cache when the saved term belongs to a watched taxonomy handle.
     *
     * @param  TermSaved  $event  Statamic event carrying the saved term
     */
    private function bumpWhenTaxonomyMatches(
        TermSaved $event
    ): void {
        // Read the saved term from the event
        $term = $event->term;

        // Resolve the taxonomy handle when the term exposes one
        $handle = \is_object($term) && method_exists($term, 'taxonomyHandle')
            ? $term->taxonomyHandle()
            : null;

        // Check if a non-empty string taxonomy handle was resolved
        if (
            ! \is_string($handle)
            || $handle === ''
        ) {
            // Return early
            return;
        }

        // Load taxonomy handles that should invalidate JSON-LD cache
        $watched = config(
            'schema_templates.json_ld_cache.invalidate_taxonomies',
            []
        );

        // Check if this taxonomy handle appears in the configured allow list
        if (
            ! \is_array($watched)
            || ! \in_array($handle, $watched, true)
        ) {
            // Return early
            return;
        }

        // Bump the JSON-LD output cache generation
        app(SchemaJsonLdOutputCache::class)->bump();
    }
}
