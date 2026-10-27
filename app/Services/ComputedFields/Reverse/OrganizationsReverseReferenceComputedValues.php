<?php

declare(strict_types=1);

namespace App\Services\ComputedFields\Reverse;

use App\Support\Collections\PeopleCollection;
use Statamic\Entries\Entry;
use Statamic\Entries\EntryCollection;

/**
 * The reverse-reference computeds for the `organizations` collection (UAMS-Web/uams-statamic-archive#1373).
 *
 * Directional `entries` pointers aim at an organization but expose no reverse, so
 * a service line or academic department cannot answer "who belongs to me?":
 *
 * - **Service line** — the `service_line` pointer names the UAMS Health service-line
 *   organization an entity belongs to. It is authored on `people` (a provider's
 *   clinical role, `max_items: 1`, stored scalar) and on `clinical_locations` (a
 *   location, sharing the `service_line_multiple` fieldset, stored as an array), both
 *   under the same `service_line` handle. The reverse is the members of this service line.
 * - **Academic department** — `resident_academic_department` and
 *   `fellow_academic_department` (both `max_items: 1`) on a `people` entry name the
 *   academic department / division a resident or fellow trains in. The reverse is the
 *   current residents, and the current fellows, of this department.
 *
 * All three are derived on read via {@see ReverseEntryReferenceResolver}; nothing is
 * stored back on the organization. A given organization carries one `organization_role`,
 * so at most one reverse is non-empty for it (service-line orgs answer members; academic
 * department/division orgs answer residents/fellows) — the others resolve to empty.
 */
class OrganizationsReverseReferenceComputedValues
{
    /**
     * Collections whose entries can name an organization as their service line
     * (the shared `service_line` handle: scalar on people, array on locations).
     *
     * @return list<string>
     */
    private static function serviceLineSourceCollections(): array
    {
        return [
            'clinical_locations',
            PeopleCollection::handle(),
        ];
    }

    /**
     * The service-line pointer matched for the members reverse.
     */
    private const string SERVICE_LINE_FIELD = 'service_line';

    /**
     * The collection whose entries name their academic department (residents/fellows).
     */
    private static function academicSourceCollection(): string
    {
        return PeopleCollection::handle();
    }

    /**
     * The residency academic-department pointer matched for the residents reverse.
     */
    private const string RESIDENT_FIELD = 'resident_academic_department';

    /**
     * The fellowship academic-department pointer matched for the fellows reverse.
     */
    private const string FELLOW_FIELD = 'fellow_academic_department';

    /**
     * @param  ReverseEntryReferenceResolver  $resolver  Resolves each reverse pointer.
     */
    public function __construct(
        private ReverseEntryReferenceResolver $resolver
    ) {}

    /**
     * Published `clinical_locations` / `people` entries that name this organization
     * as their service line (via the shared `service_line` pointer).
     *
     * @return EntryCollection<int, Entry>
     */
    public function resolveServiceLineMembers(
        Entry $entry
    ): EntryCollection {
        return $this->resolver->resolve(
            self::serviceLineSourceCollections(),
            self::SERVICE_LINE_FIELD,
            $entry->id()
        );
    }

    /**
     * Published `people` entries whose current residency academic department is this
     * organization (their `resident_academic_department` pointer names this entry).
     *
     * @return EntryCollection<int, Entry>
     */
    public function resolveAcademicDepartmentResidents(
        Entry $entry
    ): EntryCollection {
        return $this->resolver->resolve(
            self::academicSourceCollection(),
            self::RESIDENT_FIELD,
            $entry->id()
        );
    }

    /**
     * Published `people` entries whose current fellowship academic department is this
     * organization (their `fellow_academic_department` pointer names this entry).
     *
     * @return EntryCollection<int, Entry>
     */
    public function resolveAcademicDepartmentFellows(
        Entry $entry
    ): EntryCollection {
        return $this->resolver->resolve(
            self::academicSourceCollection(),
            self::FELLOW_FIELD,
            $entry->id()
        );
    }
}
