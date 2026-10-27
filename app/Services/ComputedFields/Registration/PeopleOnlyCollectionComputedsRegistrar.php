<?php

declare(strict_types=1);

namespace App\Services\ComputedFields\Registration;

use App\Services\ComputedFields\ProviderPeople\ProviderPeopleListingComputedValues;
use App\Support\Collections\PeopleCollection;
use App\Support\People\CertificationActivityValidity;
use App\Support\People\PersonKnowsAboutComputer;
use Statamic\Entries\Entry;
use Statamic\Facades\Collection;

/**
 * Registers people-only collection computeds: certifications merge, clinical profile
 * eligibility, and `knows_about_*` fields.
 */
final readonly class PeopleOnlyCollectionComputedsRegistrar
{
    /**
     * @param  CertificationActivityValidity  $certificationActivityValidity  Merges virtual certification validity fields into replicator rows.
     * @param  ProviderPeopleListingComputedValues  $listingComputed  Computes listing-oriented people fields including clinical profile eligibility.
     * @param  PersonKnowsAboutComputer  $personKnowsAboutComputer  Derives knows_about terms, entries, and strings for people entries.
     */
    public function __construct(
        private CertificationActivityValidity $certificationActivityValidity,
        private ProviderPeopleListingComputedValues $listingComputed,
        private PersonKnowsAboutComputer $personKnowsAboutComputer
    ) {}

    /**
     * Register all people collection computeds that do not apply to providers entries.
     */
    public function register(): void
    {
        // Register certifications replicator merge for virtual activity computeds
        Collection::computed(
            PeopleCollection::handle(),
            'certifications',
            fn (Entry $_entry, mixed $stored): mixed => $this->certificationActivityValidity
                ->mergeIntoCertificationsRows($stored)
        );

        // Register clinical provider profile eligibility query flag
        Collection::computed(
            PeopleCollection::handle(),
            'clinical_provider_profile_eligible_query',
            $this->listingComputed->computeClinicalProviderProfileEligibleQuery(...)
        );

        // Register knows_about taxonomy term id list computed
        Collection::computed(
            PeopleCollection::handle(),
            'knows_about_terms',
            $this->personKnowsAboutComputer->computeTerms(...)
        );

        // Register knows_about related entry id list computed
        Collection::computed(
            PeopleCollection::handle(),
            'knows_about_entries',
            $this->personKnowsAboutComputer->computeEntries(...)
        );

        // Register knows_about free-text research string list computed
        Collection::computed(
            PeopleCollection::handle(),
            'knows_about_strings',
            $this->personKnowsAboutComputer->computeStrings(...)
        );
    }
}
