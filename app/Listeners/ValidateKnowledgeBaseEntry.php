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
 * Listener for validating entries in the `knowledge_bases` collection before
 * saving.
 *
 * This listener ensures that entries in the `knowledge_bases` collection adhere
 * to specific structural rules:
 *   - `knowledge_base` entries must be top-level (no parent).
 *   - `topic` entries must have a `knowledge_base` or `topic` parent.
 *   - `article` entries must have a `topic` parent.
 *
 * If these rules are violated, an exception is thrown to prevent the entry from
 * being saved. The listener logs errors for rule violations.
 *
 * For structured collections, the parent is stored in the structure tree, not
 * as entry data. When creating a new child entry from the Control Panel, the
 * parent is passed in the request. This listener resolves the parent from the
 * entry's data (e.g., explicit `parent` field) or from the request for new
 * entries.
 */
class ValidateKnowledgeBaseEntry
{
    /**
     * ???
     */
    public function __construct(
        protected Request $request
    ) {}

    /**
     * Handle the EntrySaving event to validate the entry's structure.
     *
     * Validates the entry's blueprint and parent relationship according to the rules
     * of the `knowledge_bases` collection. If the entry does not belong to the
     * `knowledge_bases` collection, the validation is skipped. Throws an exception if
     * validation fails.
     *
     * @param  EntrySaving  $event  The EntrySaving event containing the entry to validate
     *
     * @throws Exception If the entry violates the structural rules
     */
    public function handle(
        EntrySaving $event
    ): void {
        // Extract the entry from the event.
        /** @var Entry $entry */
        $entry = $event->entry;

        // Skip validation if the entry does not belong to the 'knowledge_bases' collection.
        /** @var ?Collection $collection */
        $collection = $entry->collection();
        if (! $collection || $collection->handle() !== 'knowledge_bases') {
            return;
        }

        // Get the blueprint handle of the entry (e.g., 'knowledge_base', 'topic', 'article').
        /** @var Blueprint $entryBlueprint */
        $entryBlueprint = $entry->blueprint();
        $blueprint = $entryBlueprint->handle();

        // Retrieve the parent entry, if any, to validate the hierarchy.
        $parent = $this->getParent($entry);

        // Validate 'knowledge_base' entries: they must be top-level (no parent).
        if ($blueprint === 'knowledge_base') {
            if ($parent instanceof Entry) {
                Log::error(
                    'ValidateKnowledgeBaseEntry::handle: Throwing exception: Knowledge Base entries must be top-level',
                    [
                        'entry_id' => $entry->id(),
                    ]
                );
                throw new Exception('Knowledge Base entries must be top-level (no parent).');
            }
        } elseif ($blueprint === 'topic') { // Validate 'topic' entries: they must have a parent, and the parent must be a 'knowledge_base' or 'topic'.
            // Check if a parent exists.
            if (! $parent instanceof Entry) {
                Log::error(
                    'ValidateKnowledgeBaseEntry::handle: Throwing exception: Topic entries must have a parent',
                    [
                        'entry_id' => $entry->id(),
                    ]
                );
                throw new Exception('Topic entries must have a Knowledge Base or Topic parent.');
            }

            // Verify that the parent's blueprint is either 'knowledge_base' or 'topic'.
            /** @var Blueprint $parentBlueprintObject */
            $parentBlueprintObject = $parent->blueprint();
            $parentBlueprint = $parentBlueprintObject->handle();
            if (
                ! \in_array(
                    $parentBlueprint,
                    [
                        'knowledge_base',
                        'topic',
                    ]
                )
            ) {
                Log::error(
                    'ValidateKnowledgeBaseEntry::handle: Throwing exception: Invalid parent for Topic entry',
                    [
                        'entry_id' => $entry->id(),
                        'parent_blueprint' => $parentBlueprint,
                    ]
                );
                throw new Exception('The parent of Topic entries must be a Knowledge Base or Topic.');
            }
        } elseif ($blueprint === 'article') { // Validate 'article' entries: they must have a parent, and the parent must be a 'topic'.
            // Check if a parent exists.
            if (! $parent instanceof Entry) {
                Log::error(
                    'ValidateKnowledgeBaseEntry::handle: Throwing exception: Article entries must have a Topic parent',
                    [
                        'entry_id' => $entry->id(),
                    ]
                );
                throw new Exception('Article entries must have a Topic parent.');
            }

            // Verify that the parent's blueprint is 'topic'.
            /** @var Blueprint $parentBlueprintObject */
            $parentBlueprintObject = $parent->blueprint();
            $parentBlueprint = $parentBlueprintObject->handle();
            if ($parentBlueprint !== 'topic') {
                Log::error(
                    'ValidateKnowledgeBaseEntry::handle: Throwing exception: Invalid parent for Article entry',
                    [
                        'entry_id' => $entry->id(),
                        'parent_blueprint' => $parentBlueprint,
                    ]
                );
                throw new Exception('Article entries must have a Topic parent.');
            }
        }
    }

    /**
     * Retrieve the parent entry for the given entry.
     *
     * For new entries, the parent is resolved from (in order): the entry's
     * 'parent' field, the request's '_parent' input (used by the Control
     * Panel when creating a child), or the request's 'parent' / 'parent_id'
     * inputs. Structure collections do not store parent on the entry; the CP
     * passes parent as '_parent'. For existing entries, the parent is
     * retrieved from the structure tree. Logs warnings if the structure,
     * tree, entry, or parent cannot be found.
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
        // parent in the request when creating a child (e.g., "Add entry" under a
        // knowledge_base or topic). Check if the entry exists in the repository.
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
        // Fetch the structure for the 'knowledge_bases' collection.
        /** @var ?Structure $structure */
        $structure = StructureFacade::findByHandle('collection::knowledge_bases');
        if (! $structure) {
            Log::warning('ValidateKnowledgeBaseEntry::getParent: No structure found for collection::knowledge_bases');

            return null;
        }

        // Get the tree for the entry's site (locale)
        /** @var ?Tree $tree */
        $tree = $structure->in($entry->locale());
        if (! $tree) {
            Log::warning(
                'ValidateKnowledgeBaseEntry::getParent: No tree found for site',
                ['site' => $entry->locale()]
            );

            return null;
        }

        // Find the page in the tree corresponding to the entry.
        /** @var ?Page $page */
        $page = $tree->find($entry->id());
        if (! $page) {
            Log::warning(
                'ValidateKnowledgeBaseEntry::getParent: Entry not found in tree',
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
                'ValidateKnowledgeBaseEntry::getParent: Parent entry not found',
                ['parent_id' => $parentPage->id()]
            );

            return null;
        }

        return $parentEntry;
    }
}
