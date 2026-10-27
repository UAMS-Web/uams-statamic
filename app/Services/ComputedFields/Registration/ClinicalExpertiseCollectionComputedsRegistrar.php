<?php

declare(strict_types=1);

namespace App\Services\ComputedFields\Registration;

use App\Services\ClinicalExpertise\ClinicalExpertiseComputedFieldsService;
use Statamic\Entries\Entry;
use Statamic\Facades\Collection;

/**
 * Registers `clinical_expertise` collection computeds for the AoE subsite chrome
 * (UAMS-Web/uams-statamic-archive#1057), resolved by {@see ClinicalExpertiseComputedFieldsService}. Applies to
 * every entry in the collection (AoE entries and their content-page descendants),
 * so a descendant inherits its nearest-expertise-ancestor's primary nav.
 */
final readonly class ClinicalExpertiseCollectionComputedsRegistrar
{
    /**
     * @param  ClinicalExpertiseComputedFieldsService  $clinicalExpertiseComputedFields  Resolves the nearest-expertise-ancestor and the AoE section nav.
     */
    public function __construct(
        private ClinicalExpertiseComputedFieldsService $clinicalExpertiseComputedFields
    ) {}

    /**
     * Register the AoE primary-nav section-link list computed.
     */
    public function register(): void
    {
        // Alias the injected service for a shorter computed callback.
        $expertise = $this->clinicalExpertiseComputedFields;

        // Whether the page is within an AoE subsite. The nav templates gate the
        // AoE-vs-global primary nav on this so an AoE page never runs the pages
        // subsection scoping (which fatals TreeBuilder on a non-pages URI).
        Collection::computed(
            'clinical_expertise',
            'is_within_aoe',
            fn (Entry $entry, mixed $_stored): bool => $expertise->isWithinExpertiseSubsite($entry)
        );

        // The AoE primary-nav links (Overview + one per populated section) for the
        // entry's nearest expertise ancestor; empty outside any AoE subsite.
        Collection::computed(
            'clinical_expertise',
            'aoe_section_nav',
            fn (Entry $entry, mixed $_stored): array => $expertise->resolveSectionNav($entry)
        );

        // The effective appointment-CTA (own, else inherited from the nearest
        // ancestor that defines one, unless opted out) — the `uamsweb_block_cta`
        // shape, rendered on the AoE overview / content pages (UAMS-Web/uams-statamic-archive#1059).
        Collection::computed(
            'clinical_expertise',
            'aoe_effective_cta',
            fn (Entry $entry, mixed $_stored): mixed => $expertise->resolveEffectiveCta($entry)
        );
    }
}
