<?php

declare(strict_types=1);

namespace App\Support\People\Lenses\Gates;

use App\Services\ComputedFields\DepartmentHub\DepartmentHubPeopleComputedValues;
use App\Support\Multisite\DepartmentHubResolver;
use App\Support\People\Lenses\LensGate;
use Statamic\Entries\Entry;
use Statamic\Sites\Site;

/**
 * Base gate for the org-derived people lenses (UAMS-Web/uams-statamic-archive#1636): a person qualifies for the
 * lens on a site when that site is a Departmental hub whose organization has a role
 * this lens serves, AND the person is in the organization's derived people roster.
 *
 * It reuses the org-to-people bridge the Departmental News Hub built (UAMS-Web/uams-statamic-archive#1556 / UAMS-Web/uams-statamic-archive#1557):
 * {@see DepartmentHubResolver} maps the site to its `organizations` entry, and
 * {@see DepartmentHubPeopleComputedValues::resolvePeople()} derives the roster from
 * the `job_title` role links. A subclass only names the `organization_role` values it
 * serves, so the academic and institutional lenses stay disjoint on their own sites.
 */
abstract class OrgRoleLensGate implements LensGate
{
    public function __construct(
        private readonly DepartmentHubResolver $hubs,
        private readonly DepartmentHubPeopleComputedValues $roster,
    ) {}

    /**
     * The `organization_role` values a site's hub org must have for this lens to apply.
     *
     * @return list<string>
     */
    abstract protected function orgRoles(): array;

    public function passes(Entry $person, Site $site): bool
    {
        $handle = $site->handle();

        if (! \is_string($handle) || $handle === '') {
            return false;
        }

        $organization = $this->hubs->organization($handle);

        // Not an opt-in hub site (no `department_organization`) → no org lens here.
        if (! $organization instanceof Entry) {
            return false;
        }

        // The lens only renders on sites whose org has a role this lens serves, so
        // the academic and institutional lenses never both claim the same person/site.
        $role = $organization->value('organization_role');

        if (! \is_string($role) || ! \in_array($role, $this->orgRoles(), true)) {
            return false;
        }

        $personId = $person->id();

        if (! \is_string($personId) || $personId === '') {
            return false;
        }

        // Membership in the org's derived roster (leadership + mapped members).
        return $this->roster->resolvePeople($organization)
            ->contains(static fn (Entry $member): bool => $member->id() === $personId);
    }
}
