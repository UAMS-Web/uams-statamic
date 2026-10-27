<?php

declare(strict_types=1);

namespace App\Listeners;

use Exception;
use Illuminate\Support\Facades\Log;
use Statamic\Entries\Entry;
use Statamic\Events\CollectionTreeSaving;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Fields\Blueprint;
use Statamic\Structures\CollectionTree;

/**
 * A listener that validates the structure of the `clinical_locations` collection
 * tree when saved in the Control Panel.
 *
 * Modeled on {@see ValidateClinicalExpertiseTree}, but with the D7 twist: the
 * `location` blueprint is depth-capped while the content pages beneath a location
 * are unbounded. The coarse collection-wide `structure.max_depth` is replaced by
 * these per-blueprint rules:
 *   - `location` nodes are valid only at depth 1 (a root location) or depth 2 (one
 *     child level beneath a root location) — never deeper — and must be top-level
 *     or the child of another `location`.
 *   - content pages (`basic_page` / `landing_page`) must be the child of a
 *     `location` node or another content page — never top-level — at any depth.
 *
 * If the structure violates these rules, an exception is thrown, preventing the
 * save and displaying an error in the Control Panel. WordPress import runs bypass
 * validation (imported trees are reconciled separately).
 */
class ValidateClinicalLocationsTree
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
     * Deepest level a `location` entry may occupy: a root location (depth 1) and
     * one child location level (depth 2).
     */
    private const int MAX_LOCATION_DEPTH = 2;

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

    /**
     * Handle the CollectionTreeSaving event.
     *
     * @param  CollectionTreeSaving  $event  The event containing the collection tree being saved
     *
     * @throws Exception If the tree structure does not adhere to the required hierarchy
     */
    public function handle(
        CollectionTreeSaving $event
    ): void {
        // Get the tree from the event
        /** @var CollectionTree $tree */
        $tree = $event->tree;

        // Only validate the `clinical_locations` collection tree.
        if ($tree->handle() !== self::COLLECTION) {
            return;
        }

        // Skip validation during WordPress import operations (imported trees are
        // reconciled separately).
        if ($this->isRunningWordPressImport()) {
            return;
        }

        // Get the tree structure as an array.
        /** @var array<int, array<string, mixed>> $structure */
        $structure = $tree->tree();

        // Validate the entire tree from the root (depth 1, no parent).
        $this->validateTree($structure);
    }

    /**
     * Whether the current console invocation is a WordPress import command.
     *
     * Imported trees are frequently non-conforming at import time and reconciled
     * afterward, so structural validation is bypassed for the importer.
     */
    protected function isRunningWordPressImport(): bool
    {
        // Only console invocations can be the importer.
        if (! app()->runningInConsole()) {
            return false;
        }

        // Scan the command-line arguments for the WordPress import command.
        if (
            isset($_SERVER['argv'])
            && \is_array($_SERVER['argv'])
        ) {
            foreach ($_SERVER['argv'] as $arg) {
                if (
                    \is_string($arg)
                    && \str_contains(
                        $arg,
                        'import:wordpress'
                    )
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Recursively validate the tree structure to enforce the D7 depth + parent
     * rules: a `location` is depth-capped at {@see self::MAX_LOCATION_DEPTH}, while
     * content pages are unbounded beneath a location.
     *
     * @param  array<int, array<string, mixed>>  $tree  The tree structure as an array of entries and their children
     * @param  int  $depth  The current depth in the tree (1 for top-level)
     * @param  ?string  $parentBlueprint  The blueprint of the parent entry (null for top-level)
     *
     * @throws Exception If the tree structure violates the hierarchy rules
     */
    protected function validateTree(
        array $tree,
        int $depth = 1,
        ?string $parentBlueprint = null
    ): void {
        // Loop through each node in the tree
        foreach ($tree as $node) {
            // Ensure the node has an entry ID.
            if (! isset($node['entry'])) {
                Log::warning(
                    'ValidateClinicalLocationsTree::validateTree: Missing entry ID in tree node',
                    [
                        'node' => $node,
                    ]
                );

                continue;
            }

            // Extract entry ID from node
            $entryId = \is_scalar($node['entry']) ? (string) $node['entry'] : '';

            // Fetch the entry to determine its blueprint.
            /** @var ?Entry $entry */
            $entry = EntryFacade::find($entryId);
            if (! $entry) {
                Log::warning(
                    'ValidateClinicalLocationsTree::validateTree: Entry not found',
                    [
                        'entry_id' => $entryId,
                    ]
                );

                continue;
            }

            // Get the blueprint handle from the entry
            /** @var Blueprint $entryBlueprint */
            $entryBlueprint = $entry->blueprint();
            $blueprint = $entryBlueprint->handle();

            // Retrieve the entry's title for inclusion in error messages.
            $titleValue = $entry->get('title') ?? $entryId; // Fallback to ID if title is not set.
            $title = \is_scalar($titleValue) ? (string) $titleValue : $entryId;

            // ---
            // Validate the D7 depth + parent-blueprint relationship.
            // ---
            if ($blueprint === self::LOCATION_BLUEPRINT) {
                // A location is top-level, or nested under another location.
                if (
                    $parentBlueprint !== null
                    && $parentBlueprint !== self::LOCATION_BLUEPRINT
                ) {
                    throw new Exception(sprintf("Location entry '%s' must be top-level or the child of another location entry.", $title));
                }

                // A location may be nested at most one level deep (D7): a root
                // location and one child location level, never deeper.
                if ($depth > self::MAX_LOCATION_DEPTH) {
                    throw new Exception(sprintf("Location entry '%s' may be nested at most one level deep (a root location and one child location level).", $title));
                }
            } elseif (\in_array($blueprint, self::CONTENT_PAGE_BLUEPRINTS, true)) {
                // A content page must be the child of a location or another content
                // page — a null parent (top-level) is invalid. Depth is unbounded.
                if (! \in_array($parentBlueprint, self::CONTENT_PAGE_PARENT_BLUEPRINTS, true)) {
                    throw new Exception(sprintf("Content page entry '%s' must be the child of a location entry or another content page (it cannot be top-level).", $title));
                }
            } else {
                // Unknown blueprint — log and skip (mirrors the knowledge_bases original).
                Log::warning(
                    'ValidateClinicalLocationsTree::validateTree: Unknown blueprint',
                    [
                        'blueprint' => $blueprint,
                    ]
                );

                continue;
            }

            // Recursively validate children, incrementing depth and passing the
            // current entry's blueprint as the parent.
            if (
                isset($node['children'])
                && ! empty($node['children'])
                && \is_array($node['children'])
            ) {
                /** @var array<int, array<string, mixed>> $children */
                $children = $node['children'];

                $this->validateTree(
                    $children,
                    $depth + 1,
                    $blueprint
                );
            }
        }
    }
}
