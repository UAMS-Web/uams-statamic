<?php

declare(strict_types=1);

namespace App\Services\KnowledgeBase;

use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Fields\Blueprint;

/**
 * Resolves and gates the knowledge_bases article an engagement request targets.
 *
 * The public view / react endpoints accept an arbitrary entry id, so this resolver
 * is the trust boundary: it confirms the id names a published `article` in the
 * `knowledge_bases` collection before any counter is touched (a draft, a
 * non-article, or a foreign-collection entry resolves to null and the request is
 * rejected), and reports whether the article's knowledge base has ratings enabled.
 */
final readonly class ArticleEngagementRequestResolver
{
    /**
     * @param  KnowledgeBaseComputedFieldsService  $knowledgeBaseComputedFields  Resolves the ancestor knowledge_base's `article_rating_query` toggle
     */
    public function __construct(
        private KnowledgeBaseComputedFieldsService $knowledgeBaseComputedFields
    ) {}

    /**
     * Resolve a request id to the published knowledge_bases article it names, or
     * null when the id is absent, unknown, not an article, in another collection,
     * or not published.
     *
     * @param  string|null  $id  The entry id from the request path
     * @return Entry|null The published article entry, or null when it fails any gate
     */
    public function resolvePublishedArticle(?string $id): ?Entry
    {
        // An absent or empty id names no entry. Or→And / EmptyStringToNotEmpty /
        // RemoveEarlyReturn are equivalent: find(null|'') yields null and the
        // InstanceOf gate returns null the same way.
        // @pest-mutate-ignore: BooleanOrToBooleanAnd, EmptyStringToNotEmpty, RemoveEarlyReturn
        if ($id === null || $id === '') {
            return null;
        }

        // Resolve the id to an entry, if one exists. Narrow to the concrete Entry
        // (knowledge_bases entries are the KnowledgeBaseEntry subclass) so the
        // ancestor-value read below type-checks and a foreign implementation is rejected.
        $entry = EntryFacade::find($id);
        if (! $entry instanceof Entry) {
            return null;
        }

        // Reject entries outside the knowledge_bases collection. Removing this
        // return still nulls out via the article-blueprint gate below.
        // @pest-mutate-ignore: RemoveEarlyReturn
        if ($entry->collectionHandle() !== 'knowledge_bases') {
            return null;
        }

        // Reject non-article blueprints (knowledge_base roots, topics)
        /** @var Blueprint $blueprint */
        $blueprint = $entry->blueprint();
        if ($blueprint->handle() !== 'article') {
            return null;
        }

        // Reject anything not currently published (draft / scheduled / expired):
        // only a live article accrues public engagement
        if ($entry->status() !== 'published') {
            return null;
        }

        // The id names a published article
        return $entry;
    }

    /**
     * Whether the article's knowledge base has article ratings enabled, resolved
     * from the structural knowledge_base ancestor's `article_rating_query` toggle
     * (default false). Gates the react endpoint so ratings can be cast only where
     * the editor turned the feature on.
     *
     * @param  Entry  $article  A resolved knowledge_bases article entry
     * @return bool True when the ancestor knowledge base enables ratings
     */
    public function ratingEnabled(Entry $article): bool
    {
        return $this->knowledgeBaseComputedFields->getKnowledgeBaseAncestorValue(
            $article,
            'article_rating_query',
            false
        ) === true;
    }
}
