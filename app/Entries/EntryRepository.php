<?php

declare(strict_types=1);

namespace App\Entries;

use Illuminate\Support\Collection as IlluminateCollection;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry;
use Statamic\Entries\Entry as BaseEntry;
use Statamic\Fields\Blueprint;
use Statamic\Stache\Repositories\EntryRepository as BaseEntryRepository;

/**
 * Custom Entry Repository to override the creation and retrieval of Entry
 * instances.
 *
 * Extends Statamic\Stache\Repositories\EntryRepository so the Entry contract
 * resolves with the right collectionHandle (e.g., KnowledgeBaseEntry vs
 * Statamic\Entries\Entry).
 *
 * The custom find() re-instantiates via the container for that binding. A fresh
 * instance does not reload from disk, so we still copy date(), initialPath(),
 * origin, and blueprint from parent::find()’s row. Statamic 6.7+ fixed dated
 * filename drift on save (statamic/cms#14322); these copies remain required
 * for this re-hydration path, not as a second copy of that core fix.
 */
class EntryRepository extends BaseEntryRepository
{
    /**
     * Create a new Entry instance.
     *
     * Used when creating a new entry programmatically (e.g., via `Entry::make()`).
     * The method resolves the `Entry` contract with the provided collection handle,
     * allowing the service container to instantiate the appropriate class
     * (`KnowledgeBaseEntry` or `BaseEntry`).
     *
     * @param  ?string  $collectionHandle  The handle of the collection for the entry, or null if not specified.
     * @return BaseEntry The instantiated Entry object (either `KnowledgeBaseEntry` or `BaseEntry`).
     */
    #[\Override]
    public function make(
        $collectionHandle = null
    ): BaseEntry {
        // ---
        // Resolve the Entry contract with the collection handle to instantiate the
        // correct class.
        // ---
        return app(
            EntryContract::class,
            [
                'collectionHandle' => $collectionHandle,
            ]
        );
    }

    /**
     * Find an entry by its ID, ensuring the correct class is instantiated.
     *
     * Uses the parent repository to find the entry by ID, then re-instantiates it
     * with the appropriate class based on the collection handle to ensure consistency
     * with the dynamic binding defined in the service provider.
     *
     * @param  mixed  $id  The ID of the entry to find
     * @return ?BaseEntry The found entry (as `KnowledgeBaseEntry` or `BaseEntry`), or null if not found
     */
    #[\Override]
    public function find(
        $id
    ): ?BaseEntry {
        // Use the parent repository to find the entry by ID.
        /** @var ?BaseEntry $entry */
        $entry = parent::find($id);

        // Re-wrap the stache row with the container-bound Entry implementation.
        return $this->reinstantiateEntryFromParentRow($entry);
    }

    /**
     * Re-wrap a stache row so the container uses the correct Entry class.
     *
     * Copies id, locale, data, slug, dated publish instant from
     * `BaseEntry::date()`, `BaseEntry::initialPath()`, origin, and blueprint
     * from the parent repository row onto a fresh instance. Used by `find()`
     * and exposed for tests that build synthetic rows without on-disk
     * fixtures.
     *
     * @param  ?BaseEntry  $entry  Row from the parent repository, or null if missing
     * @return ?BaseEntry Re-bound instance, or null when the source row was null
     */
    protected function reinstantiateEntryFromParentRow(
        ?BaseEntry $entry
    ): ?BaseEntry {
        // Check if the entry was not found.
        if (! $entry instanceof Entry) {
            // Return null if the entry was not found.
            return null;
        }

        // Extract the collection handle from the found entry.
        $collectionHandle = $entry->collectionHandle();

        // The re-instantiated entry with the correct class type.
        /** @var BaseEntry $newEntry */
        $newEntry = app(
            EntryContract::class,
            [
                'collectionHandle' => $collectionHandle,
            ]
        );

        // Copy essential properties from the original entry to the new instance.
        $newEntry->id($entry->id());
        $newEntry->collection($collectionHandle);
        $newEntry->locale($entry->locale());
        $newEntry->data($entry->data());
        $newEntry->published($entry->published());
        $newEntry->slug($entry->slug());

        // ---
        // Copy dated publish instant and path onto the new instance so CP and saves
        // see the same Entry state as parent::find() before class substitution.
        // Without this, the container-built entry can miss date/initialPath and
        // mis-resolve path-dependent behavior even though core Stache dates are
        // correct (see statamic/cms#14251 / #14322 for upstream filename fixes).
        // ---

        // Check if the collection is dated and copy the entry date onto the new instance
        /** @var ?Collection $entryCollection */
        $entryCollection = $entry->collection();
        if ($entryCollection?->dated()) {
            // Preserve the publish instant from the source entry
            $newEntry->date($entry->date());
        }

        // ---
        // Preserve initialPath so path() matches the on-disk file (dated prefix, site).
        // ---

        // Check if the entry has an initial path
        if ($entry->initialPath() !== null) {
            // Preserve the initial path if the entry has one
            $newEntry->initialPath($entry->initialPath());
        }

        // Check if the entry has an origin relationship
        if ($entry->hasOrigin()) {
            // Preserve the origin relationship if the entry has one
            $newEntry->origin($entry->origin());
        }

        // Check if the entry has a blueprint defined
        /** @var ?Blueprint $entryBlueprint */
        $entryBlueprint = $entry->blueprint();
        if ($entryBlueprint) {
            // Preserve the blueprint if the entry has one defined
            $newEntry->blueprint($entryBlueprint->handle());
        }

        // Return the re-instantiated entry with the correct class type
        return $newEntry;
    }

    /**
     * Apply substitutions to a collection of entries, filtering out null entries.
     *
     * Overrides the parent method to handle null entries that may occur when
     * paths cache is cleared and some entries can't be found immediately.
     * This prevents "Call to a member function id() on null" errors by
     * filtering out null entries before calling the parent method.
     *
     * @param  IlluminateCollection<array-key, ?BaseEntry>  $items  The collection of entries
     * @return IlluminateCollection<array-key, BaseEntry> The collection with substitutions applied and nulls filtered
     */
    #[\Override]
    public function applySubstitutions(
        $items
    ) {
        // ---
        // Filter out null entries before applying substitutions to prevent
        // "Call to a member function id() on null" errors
        // ---
        $filteredItems = $items->filter();

        // ---
        // Apply substitutions to the filtered items using the parent implementation,
        // which maps each entry to its substituted version if one exists in the
        // substitutionsById array
        // ---
        /** @var IlluminateCollection<array-key, BaseEntry> $substituted */
        $substituted = parent::applySubstitutions($filteredItems);

        return $substituted;
    }

    /**
     * Define the bindings for the repository.
     *
     * Specifies the concrete classes to use for the `Entry` and `QueryBuilder`
     * contracts. Relies on the parent implementation, as the actual class binding for
     * `Entry` is handled in the service provider.
     *
     * @return array<class-string, class-string> An array of bindings for the repository
     */
    #[\Override]
    public static function bindings(): array
    {
        /** @var array<class-string, class-string> $bindings */
        $bindings = parent::bindings();

        return $bindings;
    }
}
