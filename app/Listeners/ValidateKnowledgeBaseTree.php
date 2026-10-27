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
 * A listener that validates the structure of the `knowledge_bases` collection
 * tree when saved in the control panel.
 *
 * This listener ensures that the hierarchical structure adheres to the following
 * rules:
 *   - `knowledge_base` entries must be at depth 1 (top-level).
 *   - `topic` entries must be at depth 2 or greater and have a `knowledge_base`
 *      or `topic` parent.
 *   - `article` entries must be at depth 3 or greater and have a `topic` parent.
 *
 * If the structure violates these rules, an exception is thrown, preventing the
 * save and displaying an error in the control panel.
 */
class ValidateKnowledgeBaseTree
{
    /**
     * Tracks validated entries across multiple tree save operations to prevent
     * duplicates. Uses a combination of tree handle and entry ID to create unique
     * keys.
     *
     * @var array<string, bool>
     */
    protected static $validatedEntries = [];

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

        // ---
        // Only validate the `knowledge_bases` collection tree.
        // ---

        // Check if the tree handle is not 'knowledge_bases'
        if ($tree->handle() !== 'knowledge_bases') {
            // Skip validation for other collections
            return;
        }

        // ---
        // Skip validation during WordPress import operations
        // ---

        // Check if we're running the WordPress import command
        if (app()->runningInConsole()) {
            // Initialize command variable to track if import command is found
            $command = null;

            // Check if command line arguments are available
            if (
                isset($_SERVER['argv'])
                && \is_array($_SERVER['argv'])
            ) {
                // Loop through command line arguments to find import command
                // Once an argument names the import, scanning the rest can only find it again.
                // @pest-mutate-ignore: BreakToContinue
                foreach ($_SERVER['argv'] as $arg) {
                    // Check if argument contains the WordPress import command
                    if (
                        \is_string($arg)
                        && \str_contains(
                            $arg,
                            'import:wordpress'
                        )
                    ) {
                        // Store the matching command
                        $command = $arg;

                        // Exit the loop once command is found
                        break;
                    }
                }
            }

            // Check if WordPress import command was found
            if ($command) {
                // Skip validation during WordPress import operations
                return;
            }
        }

        // Get the tree structure as an array.
        /** @var array<int, array<string, mixed>> $structure */
        $structure = $tree->tree();

        // Validate the entire tree starting at depth 1.
        $this->validateTree(
            $structure,
            1,
            $tree->handle()
        );
    }

    /**
     * Recursively validate the tree structure to enforce depth and parent rules.
     *
     * @param  array<int, array<string, mixed>>  $tree  The tree structure as an array of entries and their children
     * @param  int  $depth  The current depth in the tree (starting at 1 for top-level)
     * @param  string  $treeHandle  The handle of the tree being validated
     * @param  ?string  $parentBlueprint  The blueprint of the parent entry (null for top-level)
     *
     * @throws Exception If the tree structure violates the hierarchy rules
     */
    protected function validateTree(
        array $tree,
        int $depth,
        string $treeHandle,
        ?string $parentBlueprint = null
    ): void {
        // Loop through each node in the tree
        foreach ($tree as $node) {
            // ---
            // Ensure the node has an entry ID.
            // ---

            // Check if the node has an entry ID
            if (! isset($node['entry'])) {
                // Log warning about missing entry ID
                Log::warning(
                    'ValidateKnowledgeBaseTree::validateTree: Missing entry ID in tree node',
                    [
                        'node' => $node,
                    ]
                );

                // Skip to next node
                continue;
            }

            // Extract entry ID from node
            $entryId = \is_scalar($node['entry']) ? (string) $node['entry'] : '';

            // ---
            // Skip if this entry has already been validated in the current operation
            // ---

            // Check if this entry has already been validated
            if (isset(self::$validatedEntries[sprintf('%s:%s', $treeHandle, $entryId)])) {
                // Skip already validated entry
                continue;
            }

            // Mark this entry as validated
            // Only isset() reads the map, and isset() is true for a stored false as well.
            // @pest-mutate-ignore: TrueToFalse
            self::$validatedEntries[sprintf('%s:%s', $treeHandle, $entryId)] = true;

            // Fetch the entry to determine its blueprint.
            /** @var ?Entry $entry */
            $entry = EntryFacade::find($entryId);

            // Check if entry exists
            if (! $entry) {
                // Log warning about missing entry
                Log::warning(
                    'ValidateKnowledgeBaseTree::validateTree: Entry not found',
                    [
                        'entry_id' => $entryId,
                    ]
                );

                // Skip to next node
                continue;
            }

            // Get the blueprint handle from the entry
            /** @var Blueprint $entryBlueprint */
            $entryBlueprint = $entry->blueprint();
            $blueprint = $entryBlueprint->handle();

            // Retrieve the entry's title for inclusion in error messages.
            $titleValue = $entry->get('title') ?? $entryId; // Fallback to ID if title is not set.
            // $title is only formatted with %s, which renders a number as its string.
            // @pest-mutate-ignore: RemoveStringCast
            $title = \is_scalar($titleValue) ? (string) $titleValue : $entryId;

            // ---
            // Validate depth and parent rules based on blueprint.
            // ---

            // Check if blueprint is 'knowledge_base'
            if ($blueprint === 'knowledge_base') {
                // ---
                // `knowledge_base` entries must be at depth 1 (top-level).
                // ---

                // Check if depth is not 1
                if ($depth !== 1) {
                    // Throw exception for invalid depth
                    throw new Exception(sprintf("Knowledge Base entry '%s' must be a top-level entry.", $title));
                }
            } elseif ($blueprint === 'topic') { // Check if blueprint is 'topic'
                // ---
                // `topic` entries must have a `knowledge_base` or `topic` parent.
                // ---

                // Check if parent blueprint is not 'knowledge_base' or 'topic'
                // $parentBlueprint is a non-numeric blueprint handle or null, which loose and
                // strict in_array() compare alike.
                // @pest-mutate-ignore: TrueToFalse
                if (
                    ! \in_array(
                        $parentBlueprint,
                        [
                            'knowledge_base',
                            'topic',
                        ],
                        true
                    )
                ) {
                    // Throw exception for invalid parent
                    throw new Exception(sprintf("Topic entry '%s' must be the child of a Knowledge Base entry or a Topic entry.", $title));
                }

                // ---
                // `topic` entries must be at depth 2 or greater.
                // ---

                // Check if depth is less than 2
                if ($depth < 2) {
                    // Throw exception for invalid depth
                    throw new Exception(sprintf("Topic entry '%s' must be at depth 2 or greater.", $title));
                }
            } elseif ($blueprint === 'article') { // Check if blueprint is 'article'
                // ---
                // `article` entries must have a `topic` parent.
                // ---

                // Check if parent blueprint is not 'topic'
                if ($parentBlueprint !== 'topic') {
                    // Throw exception for invalid parent
                    throw new Exception(sprintf("Article entry '%s' must be the child of a Topic entry.", $title));
                }

                // ---
                // `article` entries must be at depth 3 or greater.
                // ---

                // Check if depth is less than 3
                if ($depth < 3) {
                    // Throw exception for invalid depth
                    throw new Exception(sprintf("Article entry '%s' must be at depth 3 or greater.", $title));
                }
            } else { // Otherwise
                // Log warning about unknown blueprint
                Log::warning(
                    'ValidateKnowledgeBaseTree::validateTree: Unknown blueprint',
                    [
                        'blueprint' => $blueprint,
                    ]
                );

                // Skip to next node
                continue;
            }

            // ---
            // Recursively validate children, incrementing the depth and passing the current entry's blueprint as the parent.
            // ---

            // Check if node has children
            // Mutation testing reports one BooleanAndToBooleanOr here as a survivor, and it is
            // equivalent: the plugin rewrites the inner `&&`, giving
            // `(isset(...) || ! empty(...)) && \is_array(...)`, where `\is_array()` still gates
            // the block and an empty list recurses into nothing. The other one, on the outer
            // `&&`, enters for a non-empty string and is killed, so it is left unmarked: a
            // marker would suppress both.
            // Depth only has to reach the minimums, which the parent rules already guarantee,
            // so starting a level deeper changes no outcome.
            // @pest-mutate-ignore: IncrementInteger
            if (
                isset($node['children'])
                && ! empty($node['children'])
                && \is_array($node['children'])
            ) {
                /** @var array<int, array<string, mixed>> $children */
                $children = $node['children'];

                // Recursively validate children with incremented depth
                $this->validateTree(
                    $children,
                    $depth + 1,
                    $treeHandle,
                    $blueprint
                );
            }
        }
    }
}
