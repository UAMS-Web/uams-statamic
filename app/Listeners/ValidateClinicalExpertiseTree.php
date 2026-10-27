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
 * A listener that validates the structure of the `clinical_expertise` collection
 * tree when saved in the Control Panel.
 *
 * The Areas of Expertise subsite is an unbounded structured collection (D1), so —
 * unlike the `knowledge_bases` clone this is modeled on — there are NO
 * absolute-depth rules. Only the parent-blueprint relationship at each node is
 * enforced:
 *   - `clinical_expertise` nodes must be top-level or the child of another `clinical_expertise`.
 *   - content pages (`basic_page` / `landing_page`) must be the child of an
 *     `clinical_expertise` node or another content page — never top-level.
 *
 * If the structure violates these rules, an exception is thrown, preventing the
 * save and displaying an error in the Control Panel. WordPress import runs bypass
 * validation (imported trees are reconciled separately).
 */
class ValidateClinicalExpertiseTree
{
    /**
     * Blueprints whose entries are content pages (never valid at the subsite root).
     *
     * @var list<string>
     */
    private const array CONTENT_PAGE_BLUEPRINTS = ['basic_page', 'landing_page'];

    /**
     * Blueprints allowed to parent a content page: an Area of Expertise or another
     * content page.
     *
     * @var list<string>
     */
    private const array CONTENT_PAGE_PARENT_BLUEPRINTS = ['clinical_expertise', 'basic_page', 'landing_page'];

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

        // Only validate the `clinical_expertise` collection tree.
        if ($tree->handle() !== 'clinical_expertise') {
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

        // Validate the entire tree from the root (no parent).
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
     * Recursively validate the tree structure to enforce the parent-blueprint
     * rules. Unlike the `knowledge_bases` original, there are no depth rules (D1
     * is unbounded); only the parent-blueprint relationship is checked.
     *
     * @param  array<int, array<string, mixed>>  $tree  The tree structure as an array of entries and their children
     * @param  ?string  $parentBlueprint  The blueprint of the parent entry (null for top-level)
     *
     * @throws Exception If the tree structure violates the hierarchy rules
     */
    protected function validateTree(
        array $tree,
        ?string $parentBlueprint = null
    ): void {
        // Loop through each node in the tree
        foreach ($tree as $node) {
            // Ensure the node has an entry ID.
            if (! isset($node['entry'])) {
                Log::warning(
                    'ValidateClinicalExpertiseTree::validateTree: Missing entry ID in tree node',
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
                    'ValidateClinicalExpertiseTree::validateTree: Entry not found',
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
            // Validate the parent-blueprint relationship (no depth rules).
            // ---
            if ($blueprint === 'clinical_expertise') {
                // An Area of Expertise is top-level, or nested under another Area of Expertise.
                if (
                    $parentBlueprint !== null
                    && $parentBlueprint !== 'clinical_expertise'
                ) {
                    throw new Exception(sprintf("Area of Expertise entry '%s' must be top-level or the child of another Area of Expertise entry.", $title));
                }
            } elseif (\in_array($blueprint, self::CONTENT_PAGE_BLUEPRINTS, true)) {
                // A content page must be the child of an Area of Expertise or another
                // content page — a null parent (top-level) is invalid.
                if (! \in_array($parentBlueprint, self::CONTENT_PAGE_PARENT_BLUEPRINTS, true)) {
                    throw new Exception(sprintf("Content page entry '%s' must be the child of an Area of Expertise entry or another content page (it cannot be top-level).", $title));
                }
            } else {
                // Unknown blueprint — log and skip (mirrors the knowledge_bases original).
                Log::warning(
                    'ValidateClinicalExpertiseTree::validateTree: Unknown blueprint',
                    [
                        'blueprint' => $blueprint,
                    ]
                );

                continue;
            }

            // Recursively validate children, passing the current entry's blueprint as the parent.
            if (
                isset($node['children'])
                && ! empty($node['children'])
                && \is_array($node['children'])
            ) {
                /** @var array<int, array<string, mixed>> $children */
                $children = $node['children'];

                $this->validateTree(
                    $children,
                    $blueprint
                );
            }
        }
    }
}
