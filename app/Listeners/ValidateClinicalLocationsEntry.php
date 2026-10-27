<?php

declare(strict_types=1);

namespace App\Listeners;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry;
use Statamic\Events\EntrySaving;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\Structure as StructureFacade;
use Statamic\Fields\Blueprint;
use Statamic\Structures\Page;
use Statamic\Structures\Structure;
use Statamic\Structures\Tree;

/**
 * Listener for validating entries in the `clinical_locations` collection before
 * saving.
 *
 * Modeled on {@see ValidateClinicalExpertiseEntry}. Enforces the parent-blueprint
 * relationship (the D7 `location` depth-2 cap is enforced by
 * {@see ValidateClinicalLocationsTree}, which has the full depth context on a tree
 * save):
 *   - `location` entries must be top-level (no parent) or nested under another
 *     `location` entry.
 *   - content pages (`basic_page` / `landing_page`) must live under a `location`
 *     entry or another content page — never at the subsite root.
 *
 * If these rules are violated, an exception is thrown to prevent the entry from
 * being saved (surfaced as a Control Panel error), and the violation is logged.
 *
 * For structured collections, the parent is stored in the structure tree, not as
 * entry data. When creating a new child entry from the Control Panel, the parent
 * is passed in the request. This listener resolves the parent from the entry's
 * data (an explicit `parent` field) or from the request for new entries.
 *
 * Unlike {@see ValidateClinicalLocationsTree}, this listener has no
 * `import:wordpress` bypass: a console import resolves no request parent, so a
 * content-page save would throw here — add the same bypass when clinical_locations
 * content pages become an importer target (see UAMS-Web/uams-statamic-archive#1053).
 */
class ValidateClinicalLocationsEntry
{
    /**
     * Collection this listener validates.
     */
    private const string COLLECTION = 'clinical_locations';

    /**
     * The `location` blueprint — the subsite-root analog of AoE's `clinical_expertise`.
     */
    private const string LOCATION_BLUEPRINT = 'location';

    /**
     * Deepest level a `location` entry may occupy — a root location (depth 1) and
     * one child location level (depth 2). Mirrors
     * {@see ValidateClinicalLocationsTree::MAX_LOCATION_DEPTH}; enforced here
     * pre-persist so a Control Panel create cannot leave an orphaned entry.
     */
    private const int MAX_LOCATION_DEPTH = 2;

    /**
     * Upper bound on the parent-location chain walk — a cycle guard for a
     * malformed tree. Real location depth never approaches this.
     */
    private const int MAX_ANCESTOR_WALK = 10;

    /**
     * Blueprints whose entries are content pages (never valid at the subsite root).
     *
     * @var list<string>
     */
    private const array CONTENT_PAGE_BLUEPRINTS = ['basic_page', 'landing_page'];

    /**
     * Blueprints allowed to parent a content page: a location or another content page.
     *
     * @var list<string>
     */
    private const array CONTENT_PAGE_PARENT_BLUEPRINTS = ['location', 'basic_page', 'landing_page'];

    public function __construct(
        protected Request $request
    ) {}

    /**
     * Handle the EntrySaving event to validate the entry's parent relationship.
     *
     * @param  EntrySaving  $event  The EntrySaving event containing the entry to validate
     *
     * @throws Exception If the entry violates the parent-blueprint rules
     */
    public function handle(
        EntrySaving $event
    ): void {
        // Extract the entry from the event.
        /** @var Entry $entry */
        $entry = $event->entry;

        // Skip validation if the entry does not belong to the 'clinical_locations' collection.
        /** @var ?Collection $collection */
        $collection = $entry->collection();
        if (! $collection || $collection->handle() !== self::COLLECTION) {
            return;
        }

        // Get the blueprint handle of the entry (e.g., 'location', 'basic_page', 'landing_page').
        /** @var Blueprint $entryBlueprint */
        $entryBlueprint = $entry->blueprint();
        $blueprint = $entryBlueprint->handle();

        // Retrieve the parent entry (if any) and its blueprint to validate the hierarchy.
        $parent = $this->getParent($entry);
        $parentBlueprint = null;
        if ($parent instanceof Entry) {
            /** @var Blueprint $parentBlueprintObject */
            $parentBlueprintObject = $parent->blueprint();
            $parentBlueprint = $parentBlueprintObject->handle();
        }

        // Validate 'location' entries: top-level, or nested under another 'location'.
        if ($blueprint === self::LOCATION_BLUEPRINT) {
            if ($parentBlueprint !== null && $parentBlueprint !== self::LOCATION_BLUEPRINT) {
                Log::error(
                    'ValidateClinicalLocationsEntry::handle: Throwing exception: Invalid parent for location entry',
                    [
                        'entry_id' => $entry->id(),
                        'parent_blueprint' => $parentBlueprint,
                    ]
                );
                throw new Exception('A location must be top-level or the child of another location.');
            }

            // Depth cap (pre-persist mirror of the D7 rule that
            // ValidateClinicalLocationsTree enforces authoritatively on tree save):
            // reject a location that would nest deeper than MAX_LOCATION_DEPTH
            // *before* it is written. Without this, EntrySaving passes (the parent
            // blueprint is a location), the entry file is persisted, and only the
            // subsequent afterSave tree save throws — stranding the just-written
            // entry as an orphan with no tree position.
            if ($parent instanceof Entry && $this->wouldExceedMaxLocationDepth($parent)) {
                Log::error(
                    'ValidateClinicalLocationsEntry::handle: Throwing exception: location nested beyond the max depth',
                    [
                        'entry_id' => $entry->id(),
                    ]
                );
                throw new Exception('A location may be nested at most one level deep (a root location and one child location level).');
            }
        } elseif (
            // Blueprint handles are non-numeric strings or null, which loose and strict
            // in_array() compare alike.
            // @pest-mutate-ignore: TrueToFalse
            \in_array($blueprint, self::CONTENT_PAGE_BLUEPRINTS, true)
        ) { // Validate content pages.
            // Content pages must have a parent (they can never be at the subsite root).
            if ($parentBlueprint === null) {
                Log::error(
                    'ValidateClinicalLocationsEntry::handle: Throwing exception: Content page must have a parent',
                    [
                        'entry_id' => $entry->id(),
                    ]
                );
                throw new Exception('A content page cannot be a top-level entry; it must live under a location or another content page.');
            }

            // The parent must be a location or another content page.
            if (
                // Blueprint handles are non-numeric strings or null, which loose and
                // strict in_array() compare alike.
                // @pest-mutate-ignore: TrueToFalse
                ! \in_array($parentBlueprint, self::CONTENT_PAGE_PARENT_BLUEPRINTS, true)
            ) {
                Log::error(
                    'ValidateClinicalLocationsEntry::handle: Throwing exception: Invalid parent for content page',
                    [
                        'entry_id' => $entry->id(),
                        'parent_blueprint' => $parentBlueprint,
                    ]
                );
                throw new Exception('A content page must be the child of a location or another content page.');
            }
        }
    }

    /**
     * Whether nesting a new `location` beneath $parentLocation would exceed the D7
     * depth cap ({@see self::MAX_LOCATION_DEPTH}).
     *
     * Walks the parent-location chain upward from $parentLocation via
     * {@see getParent()}, counting how deep $parentLocation sits; the prospective
     * child lands one level below. Mirrors the cap in
     * {@see ValidateClinicalLocationsTree} so the violation is caught pre-persist
     * (no orphaned entry) rather than only on the post-persist tree save.
     *
     * @param  Entry  $parentLocation  The resolved parent `location` entry
     */
    protected function wouldExceedMaxLocationDepth(
        Entry $parentLocation
    ): bool {
        // $parentLocation itself sits at depth >= 1; count its location ancestors.
        $parentDepth = 1;
        $ancestor = $this->getParent($parentLocation);
        // The depth is decided within MAX_LOCATION_DEPTH steps; this count only stops a
        // cycle, and starting it one higher or lower still stops it.
        // @pest-mutate-ignore: IncrementInteger, DecrementInteger
        $walked = 0;

        // One step more of the cycle bound still stops a cycle, and the depth is decided
        // long before it.
        // @pest-mutate-ignore: SmallerToSmallerOrEqual
        while ($ancestor instanceof Entry && $walked < self::MAX_ANCESTOR_WALK) {
            /** @var Blueprint $ancestorBlueprint */
            $ancestorBlueprint = $ancestor->blueprint();
            if ($ancestorBlueprint->handle() !== self::LOCATION_BLUEPRINT) {
                break;
            }

            $parentDepth++;
            $ancestor = $this->getParent($ancestor);
            $walked++;
        }

        // The prospective child sits one level below its parent.
        return ($parentDepth + 1) > self::MAX_LOCATION_DEPTH;
    }

    /**
     * Retrieve the parent entry for the given entry.
     *
     * For new entries, the parent is resolved from (in order): the entry's `parent`
     * field, the request's `_parent` input (used by the Control Panel when creating
     * a child), or the request's `parent` / `parent_id` inputs. Structure
     * collections do not store parent on the entry; the CP passes parent as
     * `_parent`. For existing entries, the parent is retrieved from the structure
     * tree. Logs warnings if the structure, tree, entry, or parent cannot be found.
     *
     * @param  Entry  $entry  The entry to find the parent for
     * @return ?Entry The parent entry, or null if no parent exists or cannot be found
     */
    protected function getParent(
        Entry $entry
    ): ?Entry {
        // ---
        // For new entries, resolve parent from entry data or from the request.
        // Structure collections do not store parent on the entry; the CP passes
        // parent in the request when creating a child. Check if the entry exists.
        // ---

        // Find the entry in the repository
        /** @var ?Entry $existingEntry */
        $existingEntry = EntryFacade::find($entry->id());

        // Check if the entry exists in the repository
        if (! $existingEntry) {
            // Resolve parent ID from entry field, then request (_parent, parent, parent_id)
            $parentId = $entry->get('parent')
                ?? $this->request->input('_parent')
                ?? $this->request->input('parent')
                ?? $this->request->input('parent_id');

            // Return the parent entry if found, otherwise null
            /** @var ?Entry $parent */
            $parent = $parentId ? EntryFacade::find($parentId) : null;

            return $parent;
        }

        // For existing entries, retrieve the parent from the structure tree.
        // Fetch the structure for the 'clinical_locations' collection.
        /** @var ?Structure $structure */
        $structure = StructureFacade::findByHandle('collection::'.self::COLLECTION);
        if (! $structure) {
            Log::warning('ValidateClinicalLocationsEntry::getParent: No structure found for collection::'.self::COLLECTION);

            return null;
        }

        // Get the tree for the entry's site (locale)
        /** @var ?Tree $tree */
        $tree = $structure->in($entry->locale());
        if (! $tree) {
            Log::warning(
                'ValidateClinicalLocationsEntry::getParent: No tree found for site',
                ['site' => $entry->locale()]
            );

            return null;
        }

        // Find the page in the tree corresponding to the entry.
        /** @var ?Page $page */
        $page = $tree->find($entry->id());
        if (! $page) {
            Log::warning(
                'ValidateClinicalLocationsEntry::getParent: Entry not found in tree',
                ['entry_id' => $entry->id()]
            );

            return null;
        }

        // Get the parent page of the entry.
        /** @var ?Page $parentPage */
        $parentPage = $page->parent();
        if (! $parentPage || $parentPage->isRoot()) {
            return null;
        }

        // Retrieve the parent entry based on the parent page's ID.
        /** @var ?Entry $parentEntry */
        $parentEntry = EntryFacade::find($parentPage->id());
        // $parentEntry is null here, so falling through to `return $parentEntry` returns
        // the same null; only the warning depends on this branch.
        // @pest-mutate-ignore: RemoveEarlyReturn
        if (! $parentEntry) {
            Log::warning(
                'ValidateClinicalLocationsEntry::getParent: Parent entry not found',
                ['parent_id' => $parentPage->id()]
            );

            return null;
        }

        return $parentEntry;
    }
}
