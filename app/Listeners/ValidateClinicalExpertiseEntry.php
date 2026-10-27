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
 * Listener for validating entries in the `clinical_expertise` collection before
 * saving.
 *
 * The Areas of Expertise subsite is an unbounded structured collection (D1), so —
 * unlike the `knowledge_bases` clone this is modeled on — there are NO
 * absolute-depth rules. Two things are enforced on save:
 *
 * 1. Parent-blueprint hierarchy:
 *   - `clinical_expertise` entries must be top-level (no parent) or nested under
 *     another `clinical_expertise` entry.
 *   - content pages (`basic_page` / `landing_page`) must live under a
 *     `clinical_expertise` entry or another content page — never at the subsite
 *     root.
 * 2. Controlled certifying-org anchor (UAMS-Web/uams-statamic-archive#1066): a *published* Area of Expertise
 *    (`clinical_expertise` blueprint) must map to at least one controlled term —
 *    a Schema.org `MedicalSpecialty` member (`schema_medical_specialty`) or a
 *    Health Care Provider Taxonomy classification (`clinical_specializations`) —
 *    so every specialty page provably maps to the certifying-org vocabulary and
 *    can emit its `MedicalSpecialty` JSON-LD. Drafts are exempt (work in progress
 *    can be saved); content pages carry no anchor concept.
 *
 * If any rule is violated, an exception is thrown to prevent the entry from being
 * saved (surfaced as a Control Panel error), and the violation is logged.
 *
 * For structured collections, the parent is stored in the structure tree, not as
 * entry data. When creating a new child entry from the Control Panel, the parent
 * is passed in the request. This listener resolves the parent from the entry's
 * data (e.g., an explicit `parent` field) or from the request for new entries.
 *
 * A WordPress import bypass (`import:wordpress`, mirroring
 * `ValidateClinicalExpertiseTree`) short-circuits all validation during a console
 * import, so legacy specialty pages without anchors — and content pages whose tree
 * parent the console cannot resolve from the request — are not blocked; they are
 * backfilled editorially afterward (see UAMS-Web/uams-statamic-archive#1053).
 */
class ValidateClinicalExpertiseEntry
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
     * The controlled certifying-org anchor fields on the `clinical_expertise`
     * blueprint. A published Area of Expertise must set at least one (UAMS-Web/uams-statamic-archive#1066): a
     * Schema.org `MedicalSpecialty` member (`schema_medical_specialty`, a
     * `schema_type` fieldtype) or a Health Care Provider Taxonomy classification
     * (`clinical_specializations`, an `entries` relation to that collection, UAMS-Web/uams-statamic-archive#2596).
     *
     * @var list<string>
     */
    private const array CONTROLLED_ANCHOR_FIELDS = ['schema_medical_specialty', 'clinical_specializations'];

    /**
     * Create the listener with the request and the published-state resolver.
     *
     * @param  Request  $request  The current request, read for a new entry's parent
     * @param  SyncEntryPublishedStatus  $publishedStatus  Decides whether the save
     *                                                     leaves the entry published
     */
    public function __construct(
        protected Request $request,
        protected SyncEntryPublishedStatus $publishedStatus = new SyncEntryPublishedStatus
    ) {}

    /**
     * Handle the EntrySaving event to validate the entry's parent relationship.
     *
     * Validates the entry's blueprint and parent relationship according to the
     * rules of the `clinical_expertise` collection. If the entry does not belong
     * to the `clinical_expertise` collection, validation is skipped. Throws an
     * exception if validation fails.
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

        // Skip validation if the entry does not belong to the 'clinical_expertise' collection.
        /** @var ?Collection $collection */
        $collection = $entry->collection();
        if (! $collection || $collection->handle() !== 'clinical_expertise') {
            return;
        }

        // Skip all validation during a WordPress console import: legacy specialty
        // pages carry no controlled anchor yet, and a console import cannot resolve
        // a content page's tree parent from the request. Both are reconciled
        // editorially after the import (UAMS-Web/uams-statamic-archive#1066 / UAMS-Web/uams-statamic-archive#1053).
        if ($this->isRunningWordPressImport()) {
            return;
        }

        // Get the blueprint handle of the entry (e.g., 'clinical_expertise', 'basic_page', 'landing_page').
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

        // Validate 'clinical_expertise' entries: top-level, or nested under another 'clinical_expertise'.
        if ($blueprint === 'clinical_expertise') {
            if ($parentBlueprint !== null && $parentBlueprint !== 'clinical_expertise') {
                Log::error(
                    'ValidateClinicalExpertiseEntry::handle: Throwing exception: Invalid parent for Area of Expertise entry',
                    [
                        'entry_id' => $entry->id(),
                        'parent_blueprint' => $parentBlueprint,
                    ]
                );
                throw new Exception('An Area of Expertise must be top-level or the child of another Area of Expertise.');
            }

            // UAMS-Web/uams-statamic-archive#1066: a published Area of Expertise must anchor to at least one
            // controlled certifying-org term (drafts may be saved incomplete).
            // Whether it will be published is read from its status, so the rule
            // holds whether or not SyncEntryPublishedStatus has run yet (UAMS-Web/uams-statamic-archive#3240).
            if ($this->publishedStatus->willBePublished($entry) && ! $this->hasControlledAnchor($entry)) {
                Log::error(
                    'ValidateClinicalExpertiseEntry::handle: Throwing exception: Area of Expertise has no controlled anchor',
                    [
                        'entry_id' => $entry->id(),
                    ]
                );
                throw new Exception('An Area of Expertise must be anchored to at least one controlled term: select a Schema.org Medical Specialty or a Health Care Provider Taxonomy classification.');
            }
        } elseif (
            // Blueprint handles are non-numeric strings or null, which loose and strict
            // in_array() compare alike.
            // @pest-mutate-ignore: TrueToFalse
            \in_array($blueprint, self::CONTENT_PAGE_BLUEPRINTS, true)
        ) { // Validate content pages: they must have a parent that is an expertise or content page.
            // Content pages must have a parent (they can never be at the subsite root).
            if ($parentBlueprint === null) {
                Log::error(
                    'ValidateClinicalExpertiseEntry::handle: Throwing exception: Content page must have a parent',
                    [
                        'entry_id' => $entry->id(),
                    ]
                );
                throw new Exception('A content page cannot be a top-level entry; it must live under an Area of Expertise or another content page.');
            }

            // The parent must be an Area of Expertise or another content page.
            if (
                // Blueprint handles are non-numeric strings or null, which loose and
                // strict in_array() compare alike.
                // @pest-mutate-ignore: TrueToFalse
                ! \in_array($parentBlueprint, self::CONTENT_PAGE_PARENT_BLUEPRINTS, true)
            ) {
                Log::error(
                    'ValidateClinicalExpertiseEntry::handle: Throwing exception: Invalid parent for content page',
                    [
                        'entry_id' => $entry->id(),
                        'parent_blueprint' => $parentBlueprint,
                    ]
                );
                throw new Exception('A content page must be the child of an Area of Expertise or another content page.');
            }
        }
    }

    /**
     * Whether the entry sets at least one controlled certifying-org anchor field.
     */
    protected function hasControlledAnchor(Entry $entry): bool
    {
        return \array_any(self::CONTROLLED_ANCHOR_FIELDS, fn (string $field): bool => $this->isAnchorSet($entry->get($field)));
    }

    /**
     * Whether a controlled-anchor field value counts as set.
     *
     * Both anchor fields store a list when set and null / `[]` when unset: the
     * `schema_medical_specialty` `schema_type` fieldtype runs in multiple-single
     * mode, so it stores a list of member names — the `None` opt-out is the
     * non-empty `['__none__']`, which correctly counts as set (an auditable
     * opt-out, not a silent blank) — and `clinical_specializations` stores a list
     * of collection entry ids. So "set" is simply a non-empty array.
     */
    protected function isAnchorSet(
        mixed $value
    ): bool {
        return \is_array($value) && $value !== [];
    }

    /**
     * Whether the current process is a WordPress console import (`import:wordpress`).
     *
     * Mirrors `ValidateClinicalExpertiseTree::isRunningWordPressImport()`: only a
     * console invocation whose argv carries the import command bypasses validation.
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
     * Retrieve the parent entry for the given entry.
     *
     * For new entries, the parent is resolved from (in order): the entry's
     * 'parent' field, the request's '_parent' input (used by the Control Panel
     * when creating a child), or the request's 'parent' / 'parent_id' inputs.
     * Structure collections do not store parent on the entry; the CP passes parent
     * as '_parent'. For existing entries, the parent is retrieved from the
     * structure tree. Logs warnings if the structure, tree, entry, or parent
     * cannot be found.
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
        // parent in the request when creating a child (e.g., "Add entry" under an
        // Area of Expertise). Check if the entry exists in the repository.
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
        // Fetch the structure for the 'clinical_expertise' collection.
        /** @var ?Structure $structure */
        $structure = StructureFacade::findByHandle('collection::clinical_expertise');
        if (! $structure) {
            Log::warning('ValidateClinicalExpertiseEntry::getParent: No structure found for collection::clinical_expertise');

            return null;
        }

        // Get the tree for the entry's site (locale)
        /** @var ?Tree $tree */
        $tree = $structure->in($entry->locale());
        if (! $tree) {
            Log::warning(
                'ValidateClinicalExpertiseEntry::getParent: No tree found for site',
                ['site' => $entry->locale()]
            );

            return null;
        }

        // Find the page in the tree corresponding to the entry.
        /** @var ?Page $page */
        $page = $tree->find($entry->id());
        if (! $page) {
            Log::warning(
                'ValidateClinicalExpertiseEntry::getParent: Entry not found in tree',
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
                'ValidateClinicalExpertiseEntry::getParent: Parent entry not found',
                ['parent_id' => $parentPage->id()]
            );

            return null;
        }

        return $parentEntry;
    }
}
