<?php

declare(strict_types=1);

namespace App\Support\People\Lenses\Gates;

use App\Support\People\Lenses\LensGate;
use App\Support\People\ResearcherProfileEligibility;
use Statamic\Entries\Entry;
use Statamic\Sites\Site;

/**
 * The researcher people lens gate (UAMS-Web/uams-statamic-archive#1667): a person qualifies for the
 * `people/researcher/show` lens when they have a public research identity — a
 * PERSON-LEVEL signal (see {@see ResearcherProfileEligibility}), independent of the
 * requesting site.
 *
 * Where the academic and institutional lenses gate on a site's organization roster
 * ({@see OrgRoleLensGate}), the researcher gate ignores the site: researchers are
 * scattered across many sites and the org-to-people bridge has no research role, so
 * the lens is registered on every site and this person-level signal is the filter
 * (the same shape as {@see ClinicalProviderLensGate}).
 */
final readonly class ResearcherLensGate implements LensGate
{
    public function __construct(
        private ResearcherProfileEligibility $eligibility,
    ) {}

    public function passes(Entry $person, Site $site): bool
    {
        return $this->eligibility->qualifies($person);
    }
}
