<?php

declare(strict_types=1);

namespace App\Stache;

use App\Entries\KnowledgeBaseEntry;
use App\Support\Collections\CollectionExtensions;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry as BaseEntry;
use Statamic\Facades\Blink;
use Statamic\Stache\Stores\CollectionEntriesStore as BaseStore;

/**
 * Stache child store that hydrates `knowledge_bases` entries as
 * `KnowledgeBaseEntry` instances on every read path.
 *
 * `App\Entries\EntryRepository` already re-instantiates the correct class for
 * `Entry::find()`, but the Stache index and query builder build entries
 * straight from the store via `makeItemFromFile()`, which always returns the
 * stock `Statamic\Entries\Entry`. That matters here because the `uri` index is
 * derived by calling `uri()` on the stored entry (`ResolveValue`), and
 * `Data::findByUri()` reads that index. Without this store, the custom URI
 * resolution in `KnowledgeBaseEntry::routeData()` would only apply to entries
 * fetched through `find()` and would be invisible to the index, the query
 * builder, breadcrumbs, and routing — leaving those paths on the un-mounted
 * URI and disagreeing with `Entry::uri()`.
 *
 * Rather than copy core's `makeItemFromFile()` (which leans on private helpers
 * such as `getDateFromPath()`), we let the parent build the entry normally and
 * then re-wrap `knowledge_bases` rows into `KnowledgeBaseEntry`, mirroring the
 * approach in `App\Entries\EntryRepository::reinstantiateEntryFromParentRow()`.
 */
class CollectionEntriesStore extends BaseStore
{
    /**
     * Build an entry from a content file, re-wrapping `knowledge_bases` rows.
     *
     * @param  string  $path  Absolute path to the entry's content file
     * @param  string  $contents  Raw file contents
     * @return BaseEntry The hydrated entry (`KnowledgeBaseEntry` for `knowledge_bases`, `PersonEntry` for `people`)
     */
    #[\Override]
    public function makeItemFromFile($path, $contents): BaseEntry
    {
        // Let core build the entry normally (correct slug, date, blink, disk writes).
        /** @var BaseEntry $entry */
        $entry = parent::makeItemFromFile($path, $contents);

        // ---
        // A collection that registers its own entry class through CollectionExtensions
        // (UAMS-Web/uams-statamic-archive#3180): re-wrap the row as that class. For `people` that is PersonEntry, so
        // profile-page suppression (`route()` → null when `profile_page_query` is
        // false) reaches the `uri` index, the query builder, and routing, not just
        // `Entry::find()`. The EntryContract binding only covers `find()`; like
        // KnowledgeBaseEntry, the index path builds straight from this store. A
        // registered collection is flat (unstructured), so there is no
        // `structure-entries` blink to overwrite (unlike `knowledge_bases` below).
        // `people` has been undated since UAMS-Web/uams-statamic-archive#2552, so the date guard below does nothing
        // for it today; it stays so the re-wrap keeps path() aligned for a dated one.
        // ---
        $handle = $entry->collectionHandle();
        $class = app(CollectionExtensions::class)->entryClassFor(\is_string($handle) ? $handle : null);

        if ($class !== null) {
            // Re-wrap the stock row into the registered class, copying its state.
            $registeredEntry = new $class;
            $registeredEntry->id($entry->id());
            $registeredEntry->collection($entry->collectionHandle());
            $registeredEntry->locale($entry->locale());
            $registeredEntry->published($entry->published());
            $registeredEntry->data($entry->data());
            $registeredEntry->slug($entry->slug());
            $registeredEntry->initialPath($entry->initialPath());

            // Preserve the publish instant only when the collection is dated,
            // so path() stays aligned with the stored file.
            $registeredCollection = $entry->collection();
            // The parent built the entry from this store's own collection, so the check
            // only narrows the type; it is never false.
            // @pest-mutate-ignore: InstanceOfToTrue
            if ($registeredCollection instanceof Collection && $registeredCollection->dated()) {
                // Copy the date across to the re-wrapped instance
                $registeredEntry->date($entry->date());
            }

            // Preserve the origin relationship for localizations.
            if ($entry->hasOrigin()) {
                // Copy the origin id across to the re-wrapped instance
                $registeredEntry->origin($entry->origin());
            }

            // Preserve the explicit blueprint so the class's behavior resolves.
            if ($blueprint = $entry->get('blueprint')) {
                // Copy the blueprint handle across to the re-wrapped instance
                $registeredEntry->blueprint($blueprint);
            }

            // Return the re-wrapped entry
            return $registeredEntry;
        }

        // Only knowledge_bases entries need the custom class; leave others untouched.
        if ($entry->collectionHandle() !== 'knowledge_bases') {
            // Return the stock entry unchanged
            return $entry;
        }

        // Re-wrap the stock row into a KnowledgeBaseEntry, copying its state.
        $kbEntry = new KnowledgeBaseEntry;
        $kbEntry->id($entry->id());
        $kbEntry->collection($entry->collectionHandle());
        $kbEntry->locale($entry->locale());
        $kbEntry->published($entry->published());
        $kbEntry->data($entry->data());
        $kbEntry->slug($entry->slug());
        $kbEntry->initialPath($entry->initialPath());

        // Preserve the publish instant for dated collections so path() stays aligned.
        $collection = $entry->collection();
        // As above, the check only narrows the type; it is never false.
        // @pest-mutate-ignore: InstanceOfToTrue
        if ($collection instanceof Collection && $collection->dated()) {
            // Copy the date across to the re-wrapped instance
            $kbEntry->date($entry->date());
        }

        // Preserve the origin relationship for localizations.
        if ($entry->hasOrigin()) {
            // Copy the origin id across to the re-wrapped instance
            $kbEntry->origin($entry->origin());
        }

        // Preserve the explicit blueprint so root/topic/article behavior resolves.
        if ($blueprint = $entry->get('blueprint')) {
            // Copy the blueprint handle across to the re-wrapped instance
            $kbEntry->blueprint($blueprint);
        }

        // ---
        // Core blinks the stock entry under `structure-entries` so URI building can
        // find it before it is persisted. Overwrite that with the re-wrapped entry so
        // every consumer sees the KnowledgeBaseEntry.
        // ---
        /** @var \Spatie\Blink\Blink $structureEntries */
        $structureEntries = Blink::store('structure-entries');
        $rawId = $kbEntry->id();
        // An id read from a content file is a string or an integer, and Blink keys an
        // integer and its digit string alike, so neither the cast nor the fallback for a
        // non-scalar id can change what is stored.
        // @pest-mutate-ignore: RemoveStringCast, EmptyStringToNotEmpty
        $entryId = is_scalar($rawId) ? (string) $rawId : '';
        $structureEntries->put($entryId, $kbEntry);

        // Return the re-wrapped knowledge base entry
        return $kbEntry;
    }
}
