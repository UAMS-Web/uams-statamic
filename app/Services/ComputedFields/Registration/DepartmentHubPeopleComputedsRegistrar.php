<?php

declare(strict_types=1);

namespace App\Services\ComputedFields\Registration;

use App\Services\ComputedFields\DepartmentHub\DepartmentHubPeopleComputedValues;
use Statamic\Facades\Collection;

/**
 * Registers the Departmental News Hub people-zone computeds on `organizations`
 * (UAMS-Web/uams-statamic-archive#1557): `department_leadership` and `department_people` — the published people
 * who hold a mapped leadership (and, for the latter, member/faculty) role for this
 * organization, derived on read from `job_title` by
 * {@see DepartmentHubPeopleComputedValues} (no stored back-reference).
 *
 * These sit beside the {@see OrganizationsReverseReferenceComputedsRegistrar}
 * reverse-reference computeds; the hub (UAMS-Web/uams-statamic-archive#1602) resolves its site's
 * `department_organization` (UAMS-Web/uams-statamic-archive#1556) and renders these as its leadership/people zone.
 */
final readonly class DepartmentHubPeopleComputedsRegistrar
{
    /**
     * The collection that receives the people-zone computeds.
     */
    private const string COLLECTION_HANDLE = 'organizations';

    /**
     * @param  DepartmentHubPeopleComputedValues  $people  Resolves the leadership / people sets.
     */
    public function __construct(
        private DepartmentHubPeopleComputedValues $people
    ) {}

    /**
     * Register the leadership and full people-zone computeds.
     */
    public function register(): void
    {
        // Cache the injected service for readable first-class-callable bindings.
        $people = $this->people;

        // The reliable leadership spine of the hub's people zone.
        Collection::computed(
            self::COLLECTION_HANDLE,
            'department_leadership',
            $people->resolveLeadership(...)
        );

        // The full people zone, leadership-first then optional member/faculty roles.
        Collection::computed(
            self::COLLECTION_HANDLE,
            'department_people',
            $people->resolvePeople(...)
        );
    }
}
