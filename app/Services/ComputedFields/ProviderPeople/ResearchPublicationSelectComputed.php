<?php

declare(strict_types=1);

namespace App\Services\ComputedFields\ProviderPeople;

use App\Services\PubMed\PubMedEsummaryService;
use Statamic\Entries\Entry;

/**
 * Invokable Statamic computed callback for `research_publication_select` on providers
 * and people entries; enriches each set with PubMed-derived `pubmed_information`.
 */
final readonly class ResearchPublicationSelectComputed
{
    /**
     * @param  PubMedEsummaryService  $pubMedEsummaryService  Resolves PubMed citations into publication select rows.
     */
    public function __construct(
        private PubMedEsummaryService $pubMedEsummaryService
    ) {}

    /**
     * Enrich the entry's stored publication select rows with PubMed metadata.
     *
     * @param  Entry  $entry  Provider or people entry exposing `research_publication_select`.
     * @return array<int|string, mixed> Augmented replicator sets (same keys as input).
     */
    public function __invoke(Entry $entry): array
    {
        // Read stored publication select sets from the entry
        /** @var array<int|string, mixed> $sets */
        $sets = $entry->get(
            'research_publication_select',
            []
        );

        // Delegate enrichment to the PubMed E-summary service
        return $this->pubMedEsummaryService->enrichResearchPublicationSelectSets($sets);
    }
}
