<?php

declare(strict_types=1);

namespace App\Services\ComputedFields\Registration;

use App\Services\KnowledgeBase\KnowledgeBaseComputedFieldsService;
use App\Services\OrphanedAncestors\OrphanedAncestorResolver;
use Statamic\Entries\Entry;
use Statamic\Facades\Collection;

/**
 * Registers `knowledge_bases` collection computeds: structural flags and ancestor-
 * inherited configuration fields resolved by KnowledgeBaseComputedFieldsService.
 */
final readonly class KnowledgeBaseCollectionComputedsRegistrar
{
    /**
     * @param  KnowledgeBaseComputedFieldsService  $knowledgeBaseComputedFields  Resolves ancestor values, topic flags, and ancestor field map registrations.
     * @param  OrphanedAncestorResolver  $orphanedAncestorResolver  Universal orphaned-ancestor resolver (UAMS-Web/uams-statamic-archive#1429/UAMS-Web/uams-statamic-archive#1455) — gates the sitemap computed.
     */
    public function __construct(
        private KnowledgeBaseComputedFieldsService $knowledgeBaseComputedFields,
        private OrphanedAncestorResolver $orphanedAncestorResolver
    ) {}

    /**
     * Register structural computeds then ancestor-mirrored configuration computeds.
     */
    public function register(): void
    {
        // Alias the injected services for shorter computed callbacks
        $kb = $this->knowledgeBaseComputedFields;
        $orphanedAncestors = $this->orphanedAncestorResolver;

        // Register whether ancestor-driven content restriction applies to this entry
        Collection::computed(
            'knowledge_bases',
            'ancestor_restriction_enabled',
            fn (Entry $entry, mixed $_stored): bool => $kb->resolveAncestorRestrictionEnabled($entry)
        );

        // Register whether a topic has descendant articles for navigation or display
        Collection::computed(
            'knowledge_bases',
            'has_descendant_articles',
            fn (Entry $entry, mixed $_stored): bool => $kb->resolveHasDescendantArticles($entry)
        );

        // Register the sitemap-inclusion computed (UAMS-Web/uams-statamic-archive#1403). KB blueprints carry no
        // status/password fields, so the gate is the orphaned-ancestor rule: an
        // entry redirected away by an unpublished ancestor must not advertise its
        // own URL. UAMS-Web/uams-statamic-archive#1455 folded KB onto the universal OrphanedAncestorResolver, so
        // the orphaned-redirect signal now comes from `resolve()` (non-null redirect
        // URL == orphaned) rather than KB's retired bespoke computed.
        Collection::computed(
            'knowledge_bases',
            SitemapInclusionComputedsRegistrar::INCLUDE_HANDLE,
            fn (Entry $entry, mixed $_stored): bool => SitemapInclusionComputedsRegistrar::shouldIncludeKnowledgeBaseEntry(
                $orphanedAncestors->resolve($entry)?->redirectUrl,
                $entry->value(SitemapInclusionComputedsRegistrar::EDITOR_TOGGLE_HANDLE),
            )
        );

        // Register whether child topics expose displayable subtopic navigation
        Collection::computed(
            'knowledge_bases',
            'has_displayable_subtopics',
            fn (Entry $entry, mixed $_stored): bool => $kb->entryHasDisplayableSubtopics($entry)
        );

        // Register precomputed direct child counts for the topic-card footer. These
        // replace the per-card collection queries in _topic_list.antlers.html so the
        // listing's query count stays bounded regardless of how many cards render.
        Collection::computed(
            'knowledge_bases',
            'direct_subtopic_count',
            fn (Entry $entry, mixed $_stored): int => $kb->resolveDirectSubtopicCount($entry)
        );

        Collection::computed(
            'knowledge_bases',
            'direct_article_count',
            fn (Entry $entry, mixed $_stored): int => $kb->resolveDirectArticleCount($entry)
        );

        // Register ancestor-inherited knowledge base configuration computeds
        $kb->registerKnowledgeBaseComputedFieldsFromAncestor();
    }
}
