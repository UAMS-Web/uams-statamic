<?php

declare(strict_types=1);

namespace App\Entries;

use App\Services\KnowledgeBase\KnowledgeBaseMountUriRefresher;
use Closure;
use Exception;
use Facades\Statamic\Entries\InitiatorStack;
use Illuminate\Support\Facades\Log;
use Spatie\Blink\Blink;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry;
use Statamic\Events\EntryCreated;
use Statamic\Events\EntryCreating;
use Statamic\Events\EntrySaved;
use Statamic\Events\EntrySaving;
use Statamic\Facades\Blink as BlinkFacade;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Fields\Blueprint;
use Statamic\Stache\Query\EntryQueryBuilder;
use Statamic\Structures\CollectionStructure;
use Statamic\Structures\Page;
use Statamic\Structures\Pages;
use Statamic\Structures\Tree;

/**
 * Custom Entry class for the `knowledge_bases` collection to control localization
 * behavior.
 *
 * This class extends the default `Statamic\Entries\Entry` class to override the
 * `save` and `makeLocalization` methods, enforcing specific rules for entries in
 * the `knowledge_bases` collection:
 *   - Restricts direct localization to `knowledge_base` blueprint entries,
 *     preventing `topic` and `article` entries from being localized independently.
 *   - When a `knowledge_base` entry is localized, automatically localizes all its
 *     descendants in the structure to the same site, ensuring the entire
 *     hierarchy is translated together.
 *
 * The class uses a static array to track localized entries during recursive
 * localization to prevent duplicate structure modifications.
 */
class KnowledgeBaseEntry extends Entry
{
    /**
     * Tracks localized entries to prevent duplicates during recursive localization.
     *
     * This static array stores the IDs of entries that have been localized in the
     * current process, ensuring they are not processed multiple times, which could
     * lead to duplicate entries in the structure tree.
     *
     * @var array<string, bool>
     */
    protected static $localizedEntries = [];

    /**
     * Blueprint handle of the knowledge base root entry that owns the mount point.
     */
    public const string ROOT_BLUEPRINT = 'knowledge_base';

    /**
     * Field handle on the root blueprint holding the `pages` entry the KB mounts under.
     */
    public const string MOUNT_FIELD = 'mount_parent';

    /**
     * Provide the raw route data used to build this entry's URI, adding the
     * `mount_prefix` token that implements per-knowledge-base mounting.
     *
     * The `knowledge_bases` collection route is `{mount_prefix}{parent_uri}/{slug}`.
     * For a `knowledge_base` root that declares a mount parent, `mount_prefix`
     * resolves live to the mount-parent page's current URI, so the root renders
     * at `{mount_parent_uri}/{root_slug}`. Topics and articles return an empty
     * `mount_prefix` and inherit the mounted path through `{parent_uri}`, which
     * chains from the root. Roots without a mount parent (and every entry in an
     * un-mounted knowledge base) yield an empty prefix and keep their original
     * collection-relative URI.
     *
     * Route URIs are built from raw, un-augmented route data, so this cannot be a
     * computed field; resolving the prefix live here means a mount-parent rename
     * or move is reflected without re-stamping any stored URI.
     *
     * @return array<string, mixed> The route data including the `mount_prefix` token
     */
    #[\Override]
    public function routeData(): array
    {
        // Start from the stock route data (slug, parent_uri, mount, etc.).
        /** @var array<string, mixed> $data */
        $data = parent::routeData();

        // Add the live mount prefix token consumed by the collection route.
        $data['mount_prefix'] = $this->knowledgeBaseMountPrefix();

        // Return the augmented route data
        return $data;
    }

    /**
     * Resolve the mount prefix for this entry: the mount-parent page's URI for a
     * mounted `knowledge_base` root, or an empty string otherwise.
     *
     * Only the root carries a mount reference; topics and articles inherit the
     * mounted path via `{parent_uri}` and therefore always return an empty prefix.
     * The mount reference is read from raw data (`get()`), not augmented values,
     * because URIs are resolved before augmentation.
     *
     * @return string The mount-parent URI (e.g. `/medicine/.../your-education`) or an empty string
     */
    private function knowledgeBaseMountPrefix(): string
    {
        // Only the knowledge_base root owns a mount reference.
        /** @var Blueprint $blueprint */
        $blueprint = $this->blueprint();
        if ($blueprint->handle() !== self::ROOT_BLUEPRINT) {
            // Topics, articles, and non-root entries inherit the path via parent_uri
            return '';
        }

        // Read the raw mount reference (an entries field stores an array of ids).
        $mountId = $this->get(self::MOUNT_FIELD);

        // Normalize the entries-field value to a single id.
        $mountId = \is_array($mountId)
            ? ($mountId[0] ?? null)
            : $mountId;

        // Check whether a mount parent has been declared.
        if (! $mountId) {
            // No mount declared: keep the original collection-relative URI
            return '';
        }

        // Resolve the mount-parent page entry.
        /** @var ?Entry $mount */
        $mount = EntryFacade::find($mountId);

        // Compute the mount-parent's current URI live.
        /** @var ?string $uri */
        $uri = $mount?->uri();

        // Use the mount-parent URI as the prefix, ignoring an empty or root URI.
        return ($uri && $uri !== '/')
            ? $uri
            : '';
    }

    /**
     * Create a localized copy of the entry for the given site.
     *
     * Overrides the parent `makeLocalization` method to enforce localization rules
     * for the `knowledge_bases` collection. Direct localization is restricted to
     * `knowledge_base` entries unless the process is part of a descendant
     * localization initiated by a `knowledge_base` entry. The localized entry is
     * created but not automatically added to the structure tree for `knowledge_base`
     * entries, as this is handled in `localizeDescendantsFromStructure`.
     *
     * @param  string  $site  The handle of the site to localize the entry into (e.g., 'health')
     * @param  bool  $isDescendantLocalization  Whether this localization is part of a recursive descendant localization process
     * @return Entry The newly created localized entry instance
     *
     * @throws Exception If the entry is a `topic` or `article` and not part of a descendant localization
     */
    #[\Override]
    public function makeLocalization(
        $site,
        bool $isDescendantLocalization = false
    ): Entry {
        // Check if the entry is not part of a recursive localization
        if (! $isDescendantLocalization) {
            // ---
            // Enforce localization restriction: only 'knowledge_base' entries can be
            // localized directly
            // ---

            // Get the blueprint handle to check the entry type
            /** @var Blueprint $blueprintInstance */
            $blueprintInstance = $this->blueprint();
            $blueprint = $blueprintInstance->handle();

            // ---
            // Check if the blueprint is 'topic' or 'article', which are not allowed to be
            // localized directly
            // ---
            if (
                \in_array(
                    $blueprint,
                    [
                        'topic',
                        'article',
                    ]
                )
            ) {
                // Log an error before throwing the exception
                Log::error(
                    'KnowledgeBaseEntry::makeLocalization: Throwing exception due to invalid blueprint',
                    [
                        'entry_id' => $this->id(),
                        'blueprint' => $blueprint,
                    ]
                );

                // Throw an exception to prevent direct localization of topic and article entries
                throw new Exception(sprintf("Only Knowledge Base entries can be localized. '%s' entries are not allowed to be localized directly.", $blueprint));
            }
        }

        // ---
        // Create a new entry instance for the specified site, preserving the collection
        // type
        // ---
        /** @var Entry $newEntry */
        $newEntry = EntryFacade::make();

        // Set the collection, origin, locale, published, and slug on the new entry
        /** @var Entry $localization */
        $localization = $newEntry->collection($this->collectionHandle());
        /** @var Entry $localization */
        $localization = $localization->origin($this);
        /** @var Entry $localization */
        $localization = $localization->locale($site);
        /** @var Entry $localization */
        $localization = $localization->published($this->published);
        /** @var Entry $localization */
        $localization = $localization->slug($this->slug());

        // ---
        // For non-'knowledge_base' entries not part of a recursive localization, add to
        // the structure tree. 'knowledge_base' entries are handled in
        // localizeDescendantsFromStructure to manage the entire hierarchy.
        // ---
        /** @var Blueprint $localizationBlueprint */
        $localizationBlueprint = $this->blueprint();
        if (
            ! $isDescendantLocalization
            && $localizationBlueprint->handle() !== 'knowledge_base'
        ) {
            // Check if a structure callback was returned to add the entry to the tree
            /** @var Entry|Page|null $entryParent */
            $entryParent = $this->parent();
            if (
                ($callback = $this->addToStructure(
                    $site,
                    $entryParent
                )) instanceof Closure
            ) {
                // Register the callback to be executed after the entry is saved
                $localization->afterSave($callback);
            }
        }

        // Return the newly created localized entry instance
        return $localization;
    }

    /**
     * Add the entry to the structure tree for the specified site.
     *
     * Replicates the private `Statamic\Entries\Entry::addToStructure()` method to
     * append the entry to the structure tree, either as a top-level entry or under a
     * parent entry. Returns a closure that performs the structure modification when
     * executed.
     *
     * Note: Monitor `Statamic\Entries\Entry::addToStructure()` during Statamic
     * upgrades to ensure this logic remains aligned.
     *
     * @param  string  $site  The handle of the site to add the entry to
     * @param  Entry|Page|null  $parent  The parent entry or structure page, or null for top-level
     * @return ?Closure A closure to append the entry to the structure tree, or null if not applicable
     */
    private function addToStructure(
        string $site,
        Entry|Page|null $parent = null
    ): ?Closure {
        // Check if the collection is orderable (single-depth structure)
        /** @var Collection $collection */
        $collection = $this->collection();
        if ($collection->orderable()) {
            // Skip
            return null;
        }

        // Check if the collection has no structure defined
        /** @var ?CollectionStructure $structure */
        $structure = $collection->structure();
        if (! $structure) {
            // Skip
            return null;
        }

        // Get the structure tree for the specified site
        /** @var Tree $tree */
        $tree = $structure->in($site);

        // Resolve parent for the tree: localize Entry to site; Page is already tree context
        /** @var Entry|Page|null $parentForTree */
        $parentForTree = $parent instanceof Entry
            ? $parent->in($site)
            : $parent;

        // ---
        // Return a closure that appends the entry to the tree, either as a top-level
        // entry or under the parent
        // ---
        return function (Entry $entry) use ($parentForTree, $tree): void {
            // Check if the entry already exists in the tree
            $existingPage = $tree->find($entry->id());

            // If the entry already exists in the tree, skip adding it to prevent duplicates
            if ($existingPage) {
                // Return early without modifying the tree
                return;

            }

            // Check if there is no parent, meaning this should be a top-level entry
            if (! $parentForTree) {
                // Append the entry as a top-level entry in the structure tree
                $tree->append($entry);
            } else { // Otherwise
                // Append the entry under the specified parent in the structure tree
                $tree->appendTo(
                    $parentForTree->id(),
                    $entry
                );
            }

            // Save the modified structure tree to persist the changes
            $tree->save();
        };
    }

    /**
     * Update the URIs of child pages if the parent entry's slug has changed.
     *
     * Replicates the private `Statamic\Entries\Entry::updateChildPageUris()` method
     * to ensure that child entries in a structured collection have their URIs updated
     * when the parent's slug changes. This maintains correct routing in the
     * collection's hierarchy.
     *
     * Note: Monitor `Statamic\Entries\Entry::updateChildPageUris()` during Statamic
     * upgrades so this copy stays aligned with core. Use `isEmpty()` on plucked IDs
     * because `empty()` does not apply to Collections in PHP.
     */
    private function updateChildPageUris(): void
    {
        /** @var Collection $collection */
        $collection = $this->collection();

        // ---
        // Check if the collection is orderable (single-depth structure), has no route, or
        // no page exists
        // ---
        if (
            $collection->orderable()
            || ! $this->route()
            || ! ($page = $this->page())
        ) {
            // Skip
            return;
        }

        // Collect descendant page entry IDs from the flattened structure
        /** @var Page $page */
        $flattenedPages = $page->flattenedPages();
        /** @var \Illuminate\Support\Collection<int, Page> $flattenedPages */
        $ids = $flattenedPages->pluck('id');

        // Check if there are no child pages to update
        if ($ids->isEmpty()) {
            // Skip
            return;
        }

        // Update the URIs of all child entries in the collection
        $collection->updateEntryUris($ids);
    }

    /**
     * Save the entry, overriding the default localization behavior.
     *
     * Enforces localization rules for the `knowledge_bases` collection, restricting
     * direct localization to `knowledge_base` entries. When a `knowledge_base` entry
     * is localized, all its descendants in the structure are also localized to the
     * same site, preserving the hierarchy. Handles events, auto-generated titles,
     * caching, taxonomy, and URI updates.
     *
     * @return bool True on successful save, false if the save is canceled by an event
     *
     * @throws Exception If the entry is a `topic` or `article` being localized directly
     */
    #[\Override]
    public function save(): bool
    {
        // Check if the entry has an origin, indicating it is a localization
        if ($this->hasOrigin()) {
            /** @var Blueprint $saveBlueprint */
            $saveBlueprint = $this->blueprint();
            $blueprint = $saveBlueprint->handle();

            // ---
            // Check if the blueprint is 'topic' or 'article', which are not allowed to be
            // localized directly
            // ---
            if (
                \in_array(
                    $blueprint,
                    [
                        'topic',
                        'article',
                    ]
                )
            ) {
                // Log an error before throwing the exception
                Log::error(
                    'KnowledgeBaseEntry::save: Throwing exception due to invalid blueprint',
                    [
                        'entry_id' => $this->id(),
                        'blueprint' => $blueprint,
                    ]
                );

                // Throw an exception to prevent direct localization of topic and article entries
                throw new Exception(sprintf("Only Knowledge Base entries can be localized. '%s' entries are not allowed to be localized directly.", $blueprint));
            }
        }

        // Determine if the entry is new (not yet saved in the repository)
        $isNew = EntryFacade::find($this->id()) === null;

        // Store the event firing state
        $withEvents = $this->withEvents;

        // Reset the event firing state to ensure events are triggered
        $this->withEvents = true;

        // Store the after-save callbacks
        /** @var array<array-key, callable> $afterSaveCallbacks */
        $afterSaveCallbacks = $this->afterSaveCallbacks;

        // Reset the after-save callbacks to prevent duplicate execution
        $this->afterSaveCallbacks = [];

        // Check if events are enabled before dispatching entry events
        if ($withEvents) {
            // Check if the entry is new and the EntryCreating event returns false
            if (
                $isNew
                && EntryCreating::dispatch($this) === false
            ) {
                // Log a notice that the EntryCreating event canceled the save operation
                Log::notice(
                    'KnowledgeBaseEntry::save: EntryCreating event returned false',
                    [
                        'entry_id' => $this->id(),
                    ]
                );

                // Return false to cancel the save operation
                return false;
            }

            // Check if the EntrySaving event returns false
            if (EntrySaving::dispatch($this) === false) {
                // Log a notice that the EntrySaving event canceled the save operation
                Log::notice(
                    'KnowledgeBaseEntry::save: EntrySaving event returned false',
                    [
                        'entry_id' => $this->id(),
                    ]
                );

                // Return false to cancel the save operation
                return false;
            }
        }

        // Check if the collection auto-generates titles
        /** @var Collection $titleCollection */
        $titleCollection = $this->collection();
        if ($titleCollection->autoGeneratesTitles()) {
            // Get the auto-generated title for the current entry
            $autoGeneratedTitle = $this->autoGeneratedTitle();

            // Get the auto-generated title from the origin entry, if it exists
            /** @var ?Entry $titleOrigin */
            $titleOrigin = $this->origin();
            $originAutoGeneratedTitle = $titleOrigin?->autoGeneratedTitle();

            // Check if the auto-generated title differs from the origin's auto-generated title
            if ($autoGeneratedTitle !== $originAutoGeneratedTitle) {
                // Update the title field with the auto-generated title
                $this->set(
                    'title',
                    $autoGeneratedTitle
                );
            }
        }

        // Ensure the slug is set before saving to avoid issues with URI generation
        $this->slug($this->slug());

        // Persist the entry to the repository using Statamic's Entry facade
        EntryFacade::save($this);

        // ---
        // Determine parent for structure: use entry data when present (e.g. from CP
        // when blueprint had a parent field), otherwise use current structure parent
        // so position is preserved after Statamic 6 RemoveParentField blueprint change.
        // addToStructure expects a structure Page, not an Entry.
        // ---

        // Initialize the parent variable
        /** @var Entry|Page|null $parent */
        $parent = null;

        // Get the parent ID from the entry data (not from the tree structure)
        $parentId = $this->get('parent');

        // Check if a parent ID exists in the entry data
        if ($parentId) {
            // Find the parent entry using the Entry facade
            /** @var Entry|null $parentEntry */
            $parentEntry = EntryFacade::find($parentId);

            // Get the parent page from the parent entry
            /** @var Entry|Page|null $parent */
            $parent = $parentEntry?->page();
        }

        // Check if the parent is still null and the entry has an ID
        if (
            $parent === null
            && $this->id()
        ) {
            // Get the parent page from the current entry
            /** @var Entry|Page|null $parent */
            $parent = $this->parent();
        }

        // ---
        // Add entry to structure tree under the resolved parent
        // ---

        // Check if the parent can be added to the structure tree
        /** @var string $entryLocale */
        $entryLocale = $this->locale();
        if (
            ($callback = $this->addToStructure(
                $entryLocale,
                $parent
            )) instanceof Closure
        ) {
            // Execute the callback to add the entry to the structure tree
            $callback($this);
        }

        // Check if the entry has an ID
        if ($this->id()) {
            // ---
            // Clear caches related to URIs, structure entries, and collection mounts to
            // ensure fresh data
            // ---

            // Capture the entry ID as a string for cache key lookups
            $rawEntryId = $this->id();
            $entryId = is_scalar($rawEntryId) ? (string) $rawEntryId : '';

            // Clear the entry URIs cache for this entry
            /** @var Blink $entryUrisStore */
            $entryUrisStore = BlinkFacade::store('entry-uris');
            $entryUrisStore->forget($entryId);

            // Clear the structure URIs cache for this entry
            /** @var Blink $structureUrisStore */
            $structureUrisStore = BlinkFacade::store('structure-uris');
            $structureUrisStore->forget($entryId);

            // Clear the structure entries cache for this entry
            /** @var Blink $structureEntriesStore */
            $structureEntriesStore = BlinkFacade::store('structure-entries');
            $structureEntriesStore->forget($entryId);

            // Clear the origin blink cache key for this entry
            /** @var string $originBlinkKey */
            $originBlinkKey = $this->getOriginBlinkKey();
            BlinkFacade::forget($originBlinkKey);

            // Flush the collection mounts cache to ensure fresh data
            /** @var Blink $collectionMountsStore */
            $collectionMountsStore = BlinkFacade::store('collection-mounts');
            $collectionMountsStore->flush();
        }

        // Clear descendant caches for all ancestors to prevent stale data
        /** @var \Illuminate\Support\Collection<array-key, Entry> $ancestors */
        $ancestors = $this->ancestors();
        $ancestors->each(function (Entry $entry): void {
            $ancestorId = $entry->id();
            BlinkFacade::forget('entry-descendants-'.(is_scalar($ancestorId) ? (string) $ancestorId : ''));
        });

        // Track the initiator stack to prevent infinite loops during recursive saves
        /** @var \Statamic\Entries\InitiatorStack $initiatorStack */
        $initiatorStack = InitiatorStack::entry($this);
        /** @var \Statamic\Entries\InitiatorStack $stack */
        $stack = $initiatorStack->push();

        // Check if the entry was not just localized
        $localizedKey = $this->id();
        $localizedKey = is_scalar($localizedKey) ? (string) $localizedKey : '';
        if (! isset(self::$localizedEntries[$localizedKey])) {
            // Save direct descendants, respecting the event firing state
            /** @var \Illuminate\Support\Collection<array-key, Entry> $directDescendants */
            $directDescendants = $this->directDescendants();
            $directDescendants->each->{$withEvents ? 'save' : 'saveQuietly'}();
        }

        // Update taxonomy terms associated with the entry
        $this->taxonomize();

        // Check if the slug has changed
        if ($this->isDirty('slug')) {
            // Update the entry URIs for the collection mount
            CollectionFacade::findByMount($this)?->updateEntryUris();

            // Update the child page URIs
            $this->updateChildPageUris();
        }

        // ---
        // When a root's mount reference changes, its whole subtree's URIs move.
        // The descendants are not dirty themselves, so re-stamp the subtree's
        // `uri` index explicitly so `Data::findByUri()` matches `Entry::uri()`.
        // ---
        /** @var Blueprint $rootBlueprint */
        $rootBlueprint = $this->blueprint();
        if (
            $rootBlueprint->handle() === self::ROOT_BLUEPRINT
            && $this->isDirty(self::MOUNT_FIELD)
        ) {
            // Re-stamp the mounted knowledge base subtree's URIs
            app(KnowledgeBaseMountUriRefresher::class)->restampSubtree($this);
        }

        // ---
        // Execute any registered after-save callbacks
        // ---

        // Loop through the after-save callbacks
        foreach ($afterSaveCallbacks as $callback) {
            /** @var ?callable $callback */

            // Check if the callback is callable before executing it
            if (\is_callable($callback)) {
                // Execute the callback with the current entry instance
                $callback($this);
            }
        }

        // Check if events are enabled
        if ($withEvents) {
            // Check if the entry is new
            if ($isNew) {
                // Dispatch the EntryCreated event
                EntryCreated::dispatch($this);
            }

            // Dispatch the EntrySaved event
            EntrySaved::dispatch($this);
        }

        // Check if the entry is a 'knowledge_base' entry with an origin
        /** @var Blueprint $localizeBlueprint */
        $localizeBlueprint = $this->blueprint();
        if (
            $localizeBlueprint->handle() === 'knowledge_base'
            && $this->hasOrigin()
        ) {
            // Get the origin entry that this entry is a localization of
            /** @var Entry $origin */
            $origin = $this->origin();

            // Get the ID of the origin entry
            $originId = $origin->id();

            // Build a query for descendant entries of the origin
            /** @var EntryQueryBuilder $query */
            $query = EntryFacade::query();

            // Capture the entry locale for the descendant query
            /** @var string $queryLocale */
            $queryLocale = $this->locale();

            // Add the collection, origin, and locale filters to the query
            /** @var EntryQueryBuilder $query */
            $query = $query->where(
                'collection',
                $this->collectionHandle()
            );
            /** @var EntryQueryBuilder $query */
            $query = $query->where(
                'origin',
                $originId
            );
            /** @var EntryQueryBuilder $query */
            $query = $query->where(
                'locale',
                $queryLocale
            );

            // Check if the current entry has an ID to exclude it from the query
            if ($this->id()) {
                // Exclude the current entry from the query to check for other descendants
                $query->where(
                    'id',
                    '!=',
                    $this->id()
                );
            }

            // Check if any descendants of the origin already exist in the target site
            $descendantsInTargetSite = $query->count() > 0;

            // Check if no descendants exist in the target site
            if (! $descendantsInTargetSite) {
                // ---
                // Retrieve the structure tree from the origin's site
                // ---

                // Get the structure configuration for the collection
                /** @var Collection $originCollection */
                $originCollection = $this->collection();
                /** @var CollectionStructure $structure */
                $structure = $originCollection->structure();

                // Get the structure tree for the origin's locale
                /** @var string $originLocale */
                $originLocale = $origin->locale();
                /** @var Tree $originTree */
                $originTree = $structure->in($originLocale);

                // Find the page in the structure tree that corresponds to the origin entry
                /** @var ?Page $page */
                $page = $originTree->find($originId);

                // Check if the page was found in the structure tree
                if ($page) {
                    // Reset the localized entries tracking to start a new localization process
                    self::$localizedEntries = [];

                    // Capture the entry locale for structure operations
                    /** @var string $localizeLocale */
                    $localizeLocale = $this->locale();

                    // Check if the entry can be added to the structure tree
                    if (
                        ($callback = $this->addToStructure(
                            $localizeLocale
                        )) instanceof Closure
                    ) {
                        // ---
                        // Execute the callback to add the entry to the structure tree as the root of the
                        // localized hierarchy
                        // ---
                        $callback($this);
                    }

                    // Recursively localize all descendants in the structure tree
                    $this->localizeDescendantsFromStructure(
                        $page,
                        $localizeLocale,
                        $this
                    );
                }
            }
        }

        // Pop the initiator stack to complete the save operation
        $stack->pop();

        // Sync the original state to track changes for future dirty checks
        $this->syncOriginal();

        // Return true to indicate the save operation completed successfully
        return true;
    }

    /**
     * Recursively localize descendants based on the structure tree.
     *
     * Traverses the structure tree of the given page, localizing each child entry to
     * the target site if it does not already exist. Adds each localized entry to the
     * structure tree under its localized parent, ensuring the hierarchy is preserved.
     * Logs a warning if a child entry is not found.
     *
     * @param  Page  $page  The structure page representing the entry
     * @param  string  $targetSite  The handle of the site to localize to (e.g., 'health')
     * @param  Entry  $localizedParent  The localized parent entry under which to add child localizations
     */
    private function localizeDescendantsFromStructure(
        Page $page,
        string $targetSite,
        Entry $localizedParent
    ): void {
        // Get all child pages of the current page in the structure tree
        /** @var Pages $childPages */
        $childPages = $page->pages();
        /** @var \Illuminate\Support\Collection<array-key, Page> $children */
        $children = $childPages->all();

        // Loop through each child page to localize its corresponding entry
        foreach ($children as $childPage) {
            // Retrieve the child entry by its ID
            /** @var ?KnowledgeBaseEntry $childEntry */
            $childEntry = EntryFacade::find($childPage->id());

            // Check if the child entry was not found in the repository
            if (! $childEntry) {
                // Log a warning that the child entry could not be found
                Log::warning(
                    'KnowledgeBaseEntry::localizeDescendantsFromStructure: Child entry not found in structure',
                    [
                        'child_id' => $childPage->id(),
                        'parent_id' => $localizedParent->id(),
                    ]
                );

                // Skip to the next child page since this entry cannot be localized
                continue;
            }

            // Build a query for existing localizations of the child entry
            /** @var EntryQueryBuilder $entryQuery */
            $entryQuery = EntryFacade::query();

            // Add the collection, origin, and locale filters to the query
            /** @var EntryQueryBuilder $entryQuery */
            $entryQuery = $entryQuery->where(
                'collection',
                $this->collectionHandle()
            );
            /** @var EntryQueryBuilder $entryQuery */
            $entryQuery = $entryQuery->where(
                'origin',
                $childEntry->id()
            );
            /** @var EntryQueryBuilder $entryQuery */
            $entryQuery = $entryQuery->where(
                'locale',
                $targetSite
            );
            $existingLocalization = $entryQuery->first();

            // Check if no localization exists
            if (! $existingLocalization) {
                // Create a localized version of the child entry for the target site
                $childLocalization = $childEntry->makeLocalization(
                    $targetSite,
                    true
                );

                // Save the localized child entry quietly to avoid triggering events
                $childLocalization->saveQuietly();

                // Track the localized entry to prevent redundant processing
                $childLocalizationId = $childLocalization->id();
                self::$localizedEntries[is_scalar($childLocalizationId) ? (string) $childLocalizationId : ''] = true;

                // Check if the child can be added to the structure tree
                if (
                    ($callback = $this->addToStructure(
                        $targetSite,
                        $localizedParent
                    )) instanceof Closure
                ) {
                    // ---
                    // Execute the callback to add the entry to the structure tree as the child of the
                    // localized parent
                    // ---
                    $callback($childLocalization);
                }

                // Recursively localize the child's descendants
                $this->localizeDescendantsFromStructure(
                    $childPage,
                    $targetSite,
                    $childLocalization
                );
            }
        }
    }
}
