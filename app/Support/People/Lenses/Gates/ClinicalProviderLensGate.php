<?php

declare(strict_types=1);

namespace App\Support\People\Lenses\Gates;

use App\Support\People\ClinicalProviderProfileEligibility;
use App\Support\People\Lenses\LensGate;
use Statamic\Entries\Entry;
use Statamic\Sites\Site;

/**
 * The clinical provider lens gate (UAMS-Web/uams-statamic-archive#571 / UAMS-Web/uams-statamic-archive#919): a person qualifies for the
 * `people/provider/show` lens when they are eligible for a UAMS Health clinical
 * provider profile.
 *
 * This is the clinical lens expressed as an ordinary {@see LensGate} — the same
 * UAMS-Web/uams-statamic-archive#919 `ClinicalProviderProfileEligibility` filter the Find-a-Doc directory and
 * Providers block use — so the clinical lens fits the registry with no
 * special-case branch. Whether a hidden / separated (non-`searchable`) but
 * eligible provider should still have a live profile page is a UAMS-Web/uams-statamic-archive#571 refinement
 * layered on this gate, not a substrate concern.
 */
final readonly class ClinicalProviderLensGate implements LensGate
{
    public function __construct(
        private ClinicalProviderProfileEligibility $eligibility,
    ) {}

    public function passes(Entry $person, Site $site): bool
    {
        return $this->eligibility->qualifies($person);
    }
}
