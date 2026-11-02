<?php

declare(strict_types=1);

namespace App\Services\KnowledgeBase;

use Statamic\Entries\Entry;
use Statamic\Facades\Blink;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Fields\Blueprint;
use Statamic\Stache\Query\EntryQueryBuilder;
use Statamic\Structures\Page;
use Statamic\Structures\Pages;
use UAMSWeb\Aether\WordPressImporter\Services\WordPressImport\Utilities\FieldDefaultHelper;

/**
 * Registers and resolves knowledge_bases collection computed fields from the root
 * knowledge_base ancestor and related topic/article display flags.
 */
class KnowledgeBaseComputedFieldsService
{
    /**
     * Finds the structural ancestor 'knowledge_base' entry for a given entry.
     * Traverses up the structure tree until a 'knowledge_base' entry is found.
     *
     * @param  Entry  $entry  The entry to find the ancestor for
     * @return ?Entry The ancestor 'knowledge_base' entry, or null if not found
     */
    public function findKnowledgeBaseAncestor(
        Entry $entry
    ): ?Entry {
        // If the entry does not have a structure, return null
        if (! $entry->hasStructure()) {
            return null;
        }

        // ---
        // Memoize the ancestor walk per entry id. Every ancestor-inherited
        // computed field resolves through here, so a single topic or article
        // render would otherwise re-walk the structure tree once per field.
        // ---

        // Capture the entry id to key the per-request ancestor cache
        $entryId = $entry->id();

        // Without a string id (e.g. a not-yet-saved entry) there is no stable
        // cache key, so resolve the ancestor directly without memoizing.
        if (! is_string($entryId)) {
            return $this->computeKnowledgeBaseAncestor($entry);
        }

        // Resolve the ancestor once per request and reuse it for every computed
        /** @var ?Entry $ancestor */
        $ancestor = Blink::once(
            'computed_kb_ancestor_'.$entryId,
            fn (): ?Entry => $this->computeKnowledgeBaseAncestor($entry)
        );

        return $ancestor;
    }

    /**
     * Walks the structure tree upward from the entry to its nearest
     * 'knowledge_base' ancestor, resolving each parent entry along the way.
     *
     * @param  Entry  $entry  The entry to find the ancestor for
     * @return ?Entry The ancestor 'knowledge_base' entry, or null if not found
     */
    private function computeKnowledgeBaseAncestor(
        Entry $entry
    ): ?Entry {
        // ---
        // Start with the current entry's page
        // ---

        // Get the current page of the entry
        /** @var ?Page $currentPage */
        $currentPage = $entry->page();

        // Check if the current page is null
        if (! $currentPage) {
            // Return null to signal that no ancestor was found
            return null;
        }

        // Traverse up the structure tree until a 'knowledge_base' entry is found
        while (! $currentPage->isRoot()) {
            // Get the parent page of the current page
            /** @var ?Page $parentPage */
            $parentPage = $currentPage->parent();

            // Check if the parent page exists
            if (! $parentPage) {
                // Break the loop if the parent page does not exist
                break;
            }

            // Find the parent entry by its ID
            /** @var ?Entry $parentEntry */
            $parentEntry = EntryFacade::find($parentPage->id());

            // Guard against a dangling structure reference resolving to no entry
            if ($parentEntry === null) {
                // Ascend past the unresolved page instead of dereferencing null
                $currentPage = $parentPage;

                // Continue traversing upward from the parent page
                continue;
            }

            // Check if the parent entry is a 'knowledge_base' entry
            /** @var Blueprint $parentBlueprint */
            $parentBlueprint = $parentEntry->blueprint();
            if ($parentBlueprint->handle() === 'knowledge_base') {
                // Return the parent entry if it is a 'knowledge_base' entry
                return $parentEntry;
            }

            // Set the current page to the parent page
            $currentPage = $parentPage;
        }

        // Return null if no 'knowledge_base' ancestor is found
        return null;
    }

    /**
     * Returns whether a topic entry has any published descendant article at any
     * depth. Cached per request per entry ID to avoid redundant work.
     *
     * @param  string  $entryId  The topic entry ID
     * @return bool True if the topic has at least one published descendant article
     */
    public function topicHasDescendantArticles(
        string $entryId
    ): bool {
        // Return the cached result if available, otherwise computes and caches the result.
        return (bool) Blink::once(
            'computed_kb_topic_has_descendant_articles_'.$entryId,
            fn (): bool => $this->computeTopicHasDescendantArticles($entryId)
        );
    }

    /**
     * Checks whether a topic has any published descendant article at any depth
     * using one bulk query over the topic's structure subtree, instead of one
     * child query per descendant node. The flattened structure supplies every
     * descendant id, and a single query asks whether any of them is a published
     * article.
     *
     * @param  string  $entryId  The topic entry ID
     * @return bool True if the topic has at least one published descendant article
     */
    private function computeTopicHasDescendantArticles(
        string $entryId
    ): bool {
        // ---
        // Resolve the topic's structure page so the whole descendant subtree can
        // be read from the already-loaded structure in a single pass.
        // ---

        // Resolve the topic entry to access its structure page
        /** @var ?Entry $entry */
        $entry = EntryFacade::find($entryId);

        // Get the topic's page within the collection structure
        /** @var ?Page $page */
        $page = $entry?->page();

        // Return false when the topic has no structure page (no descendants)
        if (! $page) {
            return false;
        }

        // ---
        // Collect every descendant entry id from the flattened structure subtree.
        // Walking the loaded structure avoids one child query per subtree node.
        //
        // The flattened subtree spans descendants under any intermediate
        // blueprint. In a well-formed knowledge base every intermediate is a
        // 'topic', so this set matches the previous topic-only recursion exactly;
        // a 'knowledge_base' entry nested beneath a topic — a shape the page-mount
        // architecture does not produce — is the only case where they would differ.
        // ---

        // Read the flattened descendant pages from the loaded structure subtree
        /** @var \Illuminate\Support\Collection<int, Page> $flattenedPages */
        $flattenedPages = $page->flattenedPages();

        // Initialize the list of descendant entry ids
        /** @var list<string> $descendantIds */
        $descendantIds = [];

        // Loop through each descendant page in the flattened subtree
        foreach ($flattenedPages as $descendantPage) {
            // Read the descendant page's entry id
            $descendantId = $descendantPage->id();

            // Keep only string ids (structure entry ids are strings)
            if (is_string($descendantId)) {
                $descendantIds[] = $descendantId;
            }
        }

        // Return false when the topic has no descendants to inspect
        if ($descendantIds === []) {
            return false;
        }

        // ---
        // A single bulk query answers the descendant-article question for the
        // whole subtree, replacing the previous per-descendant recursion.
        // ---

        // Build one query scoped to the descendant ids
        /** @var EntryQueryBuilder $entryQuery */
        $entryQuery = EntryFacade::query();

        // Restrict to the knowledge_bases collection
        $entryQuery->where(
            'collection',
            'knowledge_bases'
        );

        // Restrict to the descendant entry ids gathered from the structure
        $entryQuery->whereIn(
            'id',
            $descendantIds
        );

        // Restrict to article-blueprint descendants
        $entryQuery->where(
            'blueprint',
            'article'
        );

        // Restrict to published descendants: an empty topic is one with no
        // published descendant article, so drafts must not keep a topic alive.
        $entryQuery->whereStatus('published');

        // The topic has descendant articles when any published descendant is an article
        return $entryQuery->count() > 0;
    }

    /**
     * Returns whether the entry has at least one published direct child topic
     * that has_descendant_articles. Used to decide whether to render the
     * subtopics block. Only applies to 'knowledge_base' and 'topic' entries.
     *
     * @param  Entry  $entry  The knowledge_bases entry (knowledge_base or topic)
     * @return bool True if at least one published direct child topic has published descendant articles
     */
    public function entryHasDisplayableSubtopics(
        Entry $entry
    ): bool {
        // Get the blueprint of the entry
        /** @var Blueprint $entryBlueprint */
        $entryBlueprint = $entry->blueprint();
        $blueprint = $entryBlueprint->handle();

        // Check if the entry is a 'knowledge_base' or 'topic' entry
        if (
            $blueprint !== 'knowledge_base'
            && $blueprint !== 'topic'
        ) {
            // ---
            // Return false to indicate that the entry does not have any displayable child
            // topics
            // ---
            return false;
        }

        // Build a query for direct child topic entries
        /** @var EntryQueryBuilder $entryQuery */
        $entryQuery = EntryFacade::query();

        // Get the direct child topics of the entry
        $entryQuery->where(
            'collection',
            'knowledge_bases'
        );
        $entryQuery->where(
            'parent',
            $entry->id()
        );
        $entryQuery->where(
            'blueprint',
            'topic'
        );

        // Restrict to published child topics so unpublished topics never count
        $entryQuery->whereStatus('published');

        /** @var \Illuminate\Support\Collection<int, Entry> $childTopics */
        $childTopics = $entryQuery->get();

        // Loop through each child topic
        foreach ($childTopics as $topic) {
            // Read the child topic's id (structure entry ids are strings)
            $topicId = $topic->id();

            // Check if the child topic has published descendant articles
            if (is_string($topicId) && $this->topicHasDescendantArticles($topicId)) {
                // Return true to indicate that the entry has at least one displayable child topic
                return true;
            }
        }

        // ---
        // Return false to indicate that the entry does not have any displayable child
        // topics
        // ---
        return false;
    }

    /**
     * Whether content restriction is enabled on the structural `knowledge_base`
     * ancestor. Always false for `knowledge_base` blueprint entries; for topic and
     * article entries, reads `restriction_enabled` from the ancestor when present.
     *
     * @param  Entry  $entry  Knowledge_bases collection entry being augmented.
     * @return bool True when an ancestor exists and stores `restriction_enabled` as true.
     */
    public function resolveAncestorRestrictionEnabled(Entry $entry): bool
    {
        // Read the entry blueprint handle to branch structural versus descendant entries
        /** @var Blueprint $entryBlueprint */
        $entryBlueprint = $entry->blueprint();
        $blueprint = $entryBlueprint->handle();

        // Short-circuit knowledge_base roots because the computed models ancestor-only state
        if ($blueprint === 'knowledge_base') {
            return false;
        }

        // Find the structural knowledge_base ancestor for topic and article entries
        $ancestor = $this->findKnowledgeBaseAncestor($entry);

        // Return false when no ancestor exists to read restriction flags from
        if (! $ancestor instanceof Entry) {
            return false;
        }

        // Compare ancestor restriction toggle explicitly to boolean true
        return $ancestor->get('restriction_enabled') === true;
    }

    /**
     * Whether a structural knowledge_base root or a topic entry has at least one
     * published descendant article at any depth. Both reuse the same subtree walk
     * because the mechanic is blueprint-agnostic — flatten the entry's structure
     * page, then a single bulk published-article query over its descendant ids — so
     * an empty knowledge_base root and an empty topic are detected identically.
     * Article and any other blueprint return false.
     *
     * @param  Entry  $entry  Knowledge_bases collection entry being augmented.
     * @return bool True for a knowledge_base or topic entry with any published descendant article.
     */
    public function resolveHasDescendantArticles(Entry $entry): bool
    {
        // Restrict descendant-article checks to the knowledge_base root and topic
        // entries: an empty knowledge base or topic is one with no published
        // descendant article, the emptiness test that drives front-end suppression.
        /** @var Blueprint $entryBlueprint */
        $entryBlueprint = $entry->blueprint();
        $blueprint = $entryBlueprint->handle();
        if ($blueprint !== 'knowledge_base' && $blueprint !== 'topic') {
            return false;
        }

        // ---
        // A not-yet-saved entry has no string id and therefore no descendants.
        // The CP store action resolves all computeds during URI validation
        // before the entry is persisted, so this path is reached with a null id.
        // ---

        // Without a string id there are no descendants to inspect
        $entryId = $entry->id();
        if (! is_string($entryId)) {
            // Return false to indicate that the entry does not have any descendant articles
            return false;
        }

        // Delegate published descendant-article detection over this entry's
        // structure subtree; the walk is identical for a root and a topic.
        return $this->topicHasDescendantArticles($entryId);
    }

    /**
     * Number of published direct child topic entries under this entry. Powers the
     * subtopic count on each topic card in the listing without a per-card query.
     *
     * @param  Entry  $entry  Knowledge_bases collection entry being augmented.
     * @return int Count of published direct child topic entries (0 when none, or no structure page / id).
     */
    public function resolveDirectSubtopicCount(Entry $entry): int
    {
        return $this->countPublishedDirectChildren($entry, 'topic');
    }

    /**
     * Number of published direct child article entries under this entry. Powers the
     * article count on each topic card in the listing without a per-card query.
     *
     * @param  Entry  $entry  Knowledge_bases collection entry being augmented.
     * @return int Count of published direct child article entries (0 when none, or no structure page / id).
     */
    public function resolveDirectArticleCount(Entry $entry): int
    {
        return $this->countPublishedDirectChildren($entry, 'article');
    }

    /**
     * Counts published direct children of the entry filtered to a single blueprint,
     * memoized per request per entry id and blueprint.
     *
     * The count is read straight from the already-loaded structure (no per-card
     * query): when the topic list renders, the collection's structure and entries
     * are warm, so the direct children resolve in memory. This preserves the
     * published-only default of the `collection` tag it replaces while keeping the
     * page's query count bounded regardless of how many topic cards render.
     *
     * @param  Entry  $entry  The parent knowledge_bases entry
     * @param  string  $blueprint  The child blueprint handle to count ('topic' or 'article')
     * @return int Count of published direct children of that blueprint
     */
    private function countPublishedDirectChildren(Entry $entry, string $blueprint): int
    {
        // Without a string id there is no stable per-request cache key
        $entryId = $entry->id();
        if (! is_string($entryId)) {
            return 0;
        }

        // Resolve the count once per request per (entry, blueprint)
        $count = Blink::once(
            sprintf('computed_kb_direct_%s_count_%s', $blueprint, $entryId),
            fn (): int => $this->computePublishedDirectChildCount($entry, $blueprint)
        );

        // Blink erases the closure's int return to mixed; narrow it back. The `: 0`
        // fallback is unreachable at runtime (the closure is typed int) but required
        // for PHPStan, which rejects both the (int) cast and intval(mixed) — so its
        // integer-literal mutants are equivalent (no test can reach the branch).
        return is_int($count) ? $count : 0;
    }

    /**
     * Walks the entry's direct structure children in memory and counts those that
     * are published and match the given blueprint. A dangling structure reference
     * (no resolvable entry) is skipped rather than dereferenced.
     *
     * Bounded by design: `page()->pages()` reads the loaded structure (no query),
     * and `$childPage->entry()` resolves through `Tree::entry()`, which is backed
     * by the per-request `structure-entries` Blink cache — under `withEntries` the
     * first resolution bulk-loads the whole tree's entries in one query and every
     * subsequent call is a cache hit; otherwise each reference is a warm Stache
     * `Entry::find()` (the collection is already loaded by the topic list's own
     * `{{ collection }}` query). Neither path issues a per-card `Entry::query()`,
     * so the listing's query count stays bounded regardless of card count.
     *
     * @param  Entry  $entry  The parent knowledge_bases entry
     * @param  string  $blueprint  The child blueprint handle to count
     * @return int Count of published direct children of that blueprint
     */
    private function computePublishedDirectChildCount(Entry $entry, string $blueprint): int
    {
        // The entry's structure page exposes its direct children in memory
        /** @var ?Page $page */
        $page = $entry->page();

        // Without a structure page there are no direct children to count
        if (! $page) {
            return 0;
        }

        // Read the direct child pages from the loaded structure (no query)
        /** @var Pages $childPages */
        $childPages = $page->pages();

        /** @var \Illuminate\Support\Collection<int, Page> $directChildren */
        $directChildren = $childPages->all();

        // Tally published direct children whose blueprint matches the request
        $count = 0;

        foreach ($directChildren as $childPage) {
            // Resolve the child entry, skipping a dangling structure reference
            /** @var ?Entry $childEntry */
            $childEntry = $childPage->entry();
            if ($childEntry === null) {
                continue;
            }

            // Count only published children whose blueprint matches. Compare on
            // status() === 'published' (not the bare published() flag) so this in-
            // memory tally tracks the collection tag's whereStatus('published')
            // exactly under any date_behavior: knowledge_bases is a dated collection,
            // so a future-dated entry the query would treat as 'scheduled' must not
            // be counted as published.
            /** @var Blueprint $childBlueprint */
            $childBlueprint = $childEntry->blueprint();
            if ($childBlueprint->handle() === $blueprint && $childEntry->status() === 'published') {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Dot-path blueprint handle used when resolving default field values for ancestor computeds.
     *
     * @var string Statamic blueprint path passed to FieldDefaultHelper lookups.
     */
    private const string KNOWLEDGE_BASE_BLUEPRINT_HANDLE = 'collections.knowledge_bases.knowledge_base';

    /**
     * Registers computed fields for the 'knowledge_bases' collection that inherit
     * their value from the root-level 'knowledge_base' ancestor. Topic and article
     * entries show these values from the ancestor; knowledge_base entries use their
     * own stored values. Defaults are read from the knowledge_base blueprint.
     */
    public function registerKnowledgeBaseComputedFieldsFromAncestor(): void
    {
        // ---
        // Define computed fields: each item is [computed_handle, source_handle]. Use the
        // same handle for both when the computed field name matches the source field.
        // ---
        $computedFieldMap = [
            // Knowledge Base Details
            ['knowledge_base_title', 'title'],
            ['knowledge_base_restriction_enabled', 'restriction_enabled'],
            ['knowledge_base_restriction_global_query', 'restriction_global_query'],
            ['knowledge_base_restriction_message', 'restriction_message'],
            ['knowledge_base_restriction_category', 'restriction_category'],
            ['knowledge_base_restriction_users', 'restriction_users'],
            ['knowledge_base_restriction_user_roles', 'restriction_user_roles'],
            ['knowledge_base_restriction_user_groups', 'restriction_user_groups'],
            ['topic_label', 'topic_label'],
            ['topic_label_singular', 'topic_label_singular'],
            ['article_rating_query', 'article_rating_query'],

            // Topic Settings
            ['topic_search_query', 'topic_search_query'],
            ['topic_search_bar_title', 'topic_search_bar_title'],
            ['topic_search_placeholder', 'topic_search_placeholder'],
            ['subtopic_label', 'subtopic_label'],
            ['subtopic_label_singular', 'subtopic_label_singular'],
            ['subtopic_limit', 'subtopic_limit'],
            ['subtopic_description_query', 'subtopic_description_query'],
            ['subtopic_image_query', 'subtopic_image_query'],
            ['show_all_subtopics_label', 'show_all_subtopics_label'],
            ['topic_article_label', 'topic_article_label'],
            ['topic_article_label_singular', 'topic_article_label_singular'],
            ['topic_article_count', 'topic_article_count'],
            ['topic_article_show_all_label', 'topic_article_show_all_label'],
            ['topic_article_last_modified_query', 'topic_article_last_modified_query'],
            ['topic_article_description_query', 'topic_article_description_query'],
            ['topic_article_image_query', 'topic_article_image_query'],
            ['topic_article_view_count_query', 'topic_article_view_count_query'],
            ['topic_article_like_count_query', 'topic_article_like_count_query'],
            ['topic_article_dislike_count_query', 'topic_article_dislike_count_query'],

            // Article Settings
            ['article_last_modified_query', 'article_last_modified_query'],
            ['article_last_modified_label', 'article_last_modified_label'],
            ['article_creator_query', 'article_creator_query'],
            ['article_estimated_reading_time_query', 'article_estimated_reading_time_query'],
            ['toc_query', 'toc_query'],
            ['toc_heading_exclude', 'toc_heading_exclude'],
            ['toc_label', 'toc_label'],
            ['attachments_label', 'attachments_label'],
            ['related_articles_query', 'related_articles_query'],
            ['related_articles_label', 'related_articles_label'],
        ];

        // Loop through each computed field and register it
        foreach ($computedFieldMap as [$computedHandle, $sourceHandle]) {

            // Resolve default value from the knowledge_base blueprint using source handle
            $default = FieldDefaultHelper::getDefaultValueForBlueprintField(
                self::KNOWLEDGE_BASE_BLUEPRINT_HANDLE,
                $sourceHandle
            );

            // Register computed field that inherits value from root knowledge_base ancestor
            Collection::computed(
                'knowledge_bases',
                $computedHandle,
                fn (Entry $entry): mixed => $this->getKnowledgeBaseAncestorValue(
                    $entry,
                    $sourceHandle,
                    $default
                )
            );
        }
    }

    /**
     * Returns the value for a given source handle from the entry if it is a
     * 'knowledge_base' entry, or from the root-level 'knowledge_base' ancestor for
     * 'topic' and 'article' entries.
     *
     * @param  Entry  $entry  The knowledge_bases collection entry
     * @param  string  $sourceHandle  Source field handle on the knowledge_base (or ancestor)
     * @param  mixed  $default  Default value when no ancestor or value exists
     * @return mixed The resolved value or default
     */
    public function getKnowledgeBaseAncestorValue(
        Entry $entry,
        string $sourceHandle,
        mixed $default = null
    ): mixed {
        // ---
        // Use the entry's own value when it is a knowledge_base
        // ---

        // Get the entry's blueprint handle to determine entry type
        /** @var Blueprint $entryBlueprint */
        $entryBlueprint = $entry->blueprint();
        $blueprint = $entryBlueprint->handle();

        // Check if the entry is a knowledge_base
        if ($blueprint === 'knowledge_base') {
            // Get the stored value for the source handle from the entry
            $value = $entry->get($sourceHandle);

            // Return the value if set, otherwise the default
            return $value ?? $default;
        }

        // ---
        // Find the root-level knowledge_base ancestor for topic/article
        // ---

        // Find the structural ancestor knowledge_base entry
        $ancestor = $this->findKnowledgeBaseAncestor($entry);

        // Check if an ancestor was not found
        if (! $ancestor instanceof Entry) {
            // No ancestor, so return the default value
            return $default;
        }

        // Get the value from the ancestor using the source handle
        $value = $ancestor->get($sourceHandle);

        // Return the value if set, otherwise the default
        return $value ?? $default;
    }
}
