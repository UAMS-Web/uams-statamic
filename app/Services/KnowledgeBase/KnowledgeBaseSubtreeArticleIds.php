<?php

declare(strict_types=1);

namespace App\Services\KnowledgeBase;

use App\Support\ContentDescendantIds;
use Illuminate\Support\Collection;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Stache\Query\EntryQueryBuilder;

/**
 * Resolves the published `article` entry IDs within a knowledge-base subtree
 * rooted at a given `knowledge_base` or `topic` entry.
 *
 * Knowledge-base search (UAMS-Web/uams-statamic-archive#506) is scoped to a subtree: searching from a KB root
 * spans the whole knowledge base, and searching from a topic spans that topic and
 * its descendants. Both are the same operation rooted at a different entry. The
 * result is the id-set the `knowledge_base_subtree_articles` search query scope
 * intersects against, so a subtree search only surfaces terminal article content —
 * never an empty topic/root that would 302-redirect on click (UAMS-Web/uams-statamic-archive#215/UAMS-Web/uams-statamic-archive#216).
 */
class KnowledgeBaseSubtreeArticleIds
{
    /**
     * Build the published `article` entry IDs within the subtree rooted at the
     * given entry (its descendants at any depth, never the root itself — a root is
     * a `knowledge_base` or `topic`, never an article).
     *
     * Returns an empty list when the root is missing, is not part of a structured
     * tree, or has no published descendant article.
     *
     * @param  string  $rootId  The `knowledge_bases` entry ID to root the subtree at
     * @return list<string> Published `article` entry IDs within the subtree
     */
    public function forRoot(
        string $rootId
    ): array {
        // Collect every descendant id from the root's structure subtree (the
        // reusable flatten used by the CP cycle-guard scopes); excludes the root.
        $descendantIds = ContentDescendantIds::forEntry($rootId);

        // Stop when the root has no descendants (an empty or leaf subtree)
        if ($descendantIds === []) {
            return [];
        }

        // One bulk query narrows the descendant id-set to published articles,
        // rather than loading and inspecting each descendant individually.
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

        // Restrict to article-blueprint descendants (terminal content only)
        $entryQuery->where(
            'blueprint',
            'article'
        );

        // Restrict to published articles: a draft or future-dated article is not a
        // reachable search result, mirroring the empty-suppression published gate.
        $entryQuery->whereStatus('published');

        // Reduce the matched article entries to their string ids
        /** @var Collection<int, Entry> $articles */
        $articles = $entryQuery->get();

        // Collect each article's string id (entry ids are strings; skip anything else)
        $articleIds = [];

        foreach ($articles as $article) {
            $id = $article->id();

            if (is_string($id) && $id !== '') {
                $articleIds[] = $id;
            }
        }

        // Return the published article ids within the subtree
        return $articleIds;
    }
}
