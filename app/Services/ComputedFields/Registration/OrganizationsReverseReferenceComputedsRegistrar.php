<?php

declare(strict_types=1);

namespace App\Services\ComputedFields\Registration;

use App\Services\ComputedFields\Reverse\OrganizationsReverseReferenceComputedValues;
use Statamic\Facades\Collection;

/**
 * Registers the `organizations` reverse-reference computeds (UAMS-Web/uams-statamic-archive#1373):
 * `service_line_members`, `academic_department_residents`, and
 * `academic_department_fellows` — the entities in this service line, and the current
 * residents / fellows of this academic department — derived on read by
 * {@see OrganizationsReverseReferenceComputedValues} (no stored back-reference).
 */
final readonly class OrganizationsReverseReferenceComputedsRegistrar
{
    /**
     * The collection that receives the reverse-reference computeds.
     */
    private const string COLLECTION_HANDLE = 'organizations';

    /**
     * @param  OrganizationsReverseReferenceComputedValues  $reverse  Resolves each reverse set.
     */
    public function __construct(
        private OrganizationsReverseReferenceComputedValues $reverse
    ) {}

    /**
     * Register all three organizations reverse-reference computeds.
     */
    public function register(): void
    {
        // Cache the injected service for readable first-class-callable bindings.
        $reverse = $this->reverse;

        // Entities (locations / providers) that name this organization as their service line.
        Collection::computed(
            self::COLLECTION_HANDLE,
            'service_line_members',
            $reverse->resolveServiceLineMembers(...)
        );

        // People whose current residency academic department is this organization.
        Collection::computed(
            self::COLLECTION_HANDLE,
            'academic_department_residents',
            $reverse->resolveAcademicDepartmentResidents(...)
        );

        // People whose current fellowship academic department is this organization.
        Collection::computed(
            self::COLLECTION_HANDLE,
            'academic_department_fellows',
            $reverse->resolveAcademicDepartmentFellows(...)
        );
    }
}
