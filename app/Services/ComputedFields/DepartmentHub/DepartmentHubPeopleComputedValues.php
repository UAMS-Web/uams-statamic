<?php

declare(strict_types=1);

namespace App\Services\ComputedFields\DepartmentHub;

use App\Services\ComputedFields\Reverse\ReverseEntryReferenceResolver;
use App\Support\Collections\PeopleCollection;
use Statamic\Entries\Entry;
use Statamic\Entries\EntryCollection;
use Statamic\Facades\Blink;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Stache\Query\EntryQueryBuilder;

/**
 * Derives a Departmental News Hub's leadership/people zone from the existing
 * `job_title` role replicator on `people` (UAMS-Web/uams-statamic-archive#1557) — no new person→department model.
 *
 * `job_title` already links each of a person's role sets to an `organizations`
 * entry, scoped by org role, with leadership split into its own set types. Given a
 * hub's `organizations` entry (resolved from the site by UAMS-Web/uams-statamic-archive#1556), this reads that
 * org's `organization_role`, looks up which role sets name it — via the
 * configurable `config/department_hub.php` `people_role_map`, so the derivation is
 * not hardcoded to academic departments — and returns the published `people` who
 * hold one of those roles for the org.
 *
 * Two reads, both **leadership-first** (the required leadership links are
 * well-populated, so leadership is the reliable spine):
 *
 * - {@see resolveLeadership()} — only the leadership roles (the reliable set).
 * - {@see resolvePeople()} — leadership first, then the optional member/faculty
 *   roles, de-duplicated (a person who is both is listed once, under leadership).
 *
 * Membership is matched in PHP against each person's `job_title` rows rather than
 * with a Stache predicate: the org link is nested inside the replicator and stored
 * as either a scalar (CP, `max_items: 1`) or an array (import), which no single
 * Stache JSON/where predicate covers — the same mixed-storage reason
 * {@see ReverseEntryReferenceResolver} matches
 * in PHP. The result is {@see Blink}-memoized per (org, role, leadership-only) so an
 * org rendering both reads scans `people` once.
 */
class DepartmentHubPeopleComputedValues
{
    /**
     * The collection scanned for people holding a role in the organization.
     */
    private static function peopleCollection(): string
    {
        return PeopleCollection::handle();
    }

    /**
     * The role replicator on a person that links each role set to an organization.
     */
    private const string JOB_TITLE_FIELD = 'job_title';

    /**
     * The organization field whose value selects the role-set mapping.
     */
    private const string ORG_ROLE_FIELD = 'organization_role';

    /**
     * The config key holding the org-role → role-set descriptor map.
     */
    private const string ROLE_MAP_CONFIG = 'department_hub.people_role_map';

    /**
     * Published `people` who hold a mapped **leadership** role for this
     * organization — the reliable people-zone spine.
     *
     * @return EntryCollection<int, Entry>
     */
    public function resolveLeadership(Entry $organization): EntryCollection
    {
        return $this->resolve($organization, leadershipOnly: true);
    }

    /**
     * Published `people` who hold any mapped role for this organization, ordered
     * leadership-first then the optional member/faculty roles, de-duplicated.
     *
     * @return EntryCollection<int, Entry>
     */
    public function resolvePeople(Entry $organization): EntryCollection
    {
        return $this->resolve($organization, leadershipOnly: false);
    }

    /**
     * Audit how completely the org links the people zone derives from are
     * populated, across published `people` (UAMS-Web/uams-statamic-archive#1557). For each mapped role set,
     * report how many rows of that set exist and how many name an organization
     * through the set's link — the data behind the derived-vs-curated
     * recommendation for a fuller roster (the required leadership links are
     * well-populated; the optional `faculty_role_title.department` may not be).
     *
     * @return array<string, array{link: string, leadership: bool, rows: int, linked: int}> Keyed by set handle.
     */
    public function auditRoleLinkCompleteness(): array
    {
        $descriptors = $this->allDescriptors();

        // Seed a zero row for every mapped set so a set with no data still reports.
        $report = [];
        foreach ($descriptors as $set => $descriptor) {
            $report[$set] = [
                'link' => $descriptor['link'],
                'leadership' => $descriptor['leadership'],
                'rows' => 0,
                'linked' => 0,
            ];
        }

        unset($descriptor);

        if ($descriptors === [] || CollectionFacade::findByHandle(self::peopleCollection()) === null) {
            return $report;
        }

        /** @var EntryQueryBuilder $query */
        $query = EntryFacade::query();
        $query->where('collection', self::peopleCollection());
        $query->whereStatus('published');

        /** @var EntryCollection<int, Entry> $people */
        $people = $query->get();

        foreach ($people as $person) {
            $rows = $person->value(self::JOB_TITLE_FIELD);

            if (! \is_array($rows)) {
                continue;
            }

            foreach ($rows as $row) {
                if (! \is_array($row)) {
                    continue;
                }

                $type = $row['type'] ?? null;
                if (! \is_string($type)) {
                    continue;
                }

                if (! isset($report[$type])) {
                    continue;
                }

                $report[$type]['rows']++;

                if ($this->normalizeIds($row[$descriptors[$type]['link']] ?? null) !== []) {
                    $report[$type]['linked']++;
                }
            }

            unset($row);
        }

        unset($person);

        return $report;
    }

    /**
     * Resolve the people for an organization: read its role, select the mapped
     * descriptors (optionally leadership-only), and scan `people` — memoized.
     *
     * @return EntryCollection<int, Entry>
     */
    private function resolve(
        Entry $organization,
        bool $leadershipOnly
    ): EntryCollection {
        $orgId = $organization->id();

        // An unsaved or id-less org cannot be named by anyone's job_title yet.
        if (! \is_string($orgId) || $orgId === '') {
            return EntryCollection::make();
        }

        $role = $organization->value(self::ORG_ROLE_FIELD);
        $role = \is_string($role) ? $role : '';

        $descriptors = $this->descriptorsForRole($role, $leadershipOnly);

        // A non-entity / unmapped org (or leadership-only with no leadership set)
        // has no people zone — the hub degrades to news-only.
        if ($descriptors === []) {
            return EntryCollection::make();
        }

        $cacheKey = sprintf(
            'department_hub_people_%s_%s_%d',
            $orgId,
            $role,
            $leadershipOnly ? 1 : 0
        );

        /** @var EntryCollection<int, Entry> $result */
        $result = Blink::once(
            $cacheKey,
            fn (): EntryCollection => $this->scan($orgId, $descriptors)
        );

        return $result;
    }

    /**
     * Scan published `people` and return those holding a mapped role for the org,
     * leadership-first then the rest (a person matching a leadership role is listed
     * under leadership only).
     *
     * @param  list<array{set: string, link: string, leadership: bool}>  $descriptors
     * @return EntryCollection<int, Entry>
     */
    private function scan(
        string $orgId,
        array $descriptors
    ): EntryCollection {
        // No `people` collection (a minimal/isolated Stache) → no people zone;
        // also avoids the whereStatus() null-collection fatal on an absent handle.
        if (CollectionFacade::findByHandle(self::peopleCollection()) === null) {
            return EntryCollection::make();
        }

        /** @var EntryQueryBuilder $query */
        $query = EntryFacade::query();
        $query->where('collection', self::peopleCollection());
        $query->whereStatus('published');

        /** @var EntryCollection<int, Entry> $people */
        $people = $query->get();

        $leadership = [];
        $others = [];

        foreach ($people as $person) {
            $level = $this->matchLevel($person, $orgId, $descriptors);

            if ($level === 'leadership') {
                $leadership[] = $person;
            } elseif ($level === 'other') {
                $others[] = $person;
            }
        }

        unset($person);

        return EntryCollection::make([...$leadership, ...$others]);
    }

    /**
     * The strongest role level a person holds for the org: `leadership` if any
     * leadership descriptor's set/link names it, else `other` if a non-leadership
     * one does, else null.
     *
     * @param  list<array{set: string, link: string, leadership: bool}>  $descriptors
     */
    private function matchLevel(
        Entry $person,
        string $orgId,
        array $descriptors
    ): ?string {
        $rows = $person->value(self::JOB_TITLE_FIELD);

        if (! \is_array($rows)) {
            return null;
        }

        $matchedLeadership = false;
        $matchedOther = false;

        foreach ($rows as $row) {
            if (! \is_array($row)) {
                continue;
            }

            $type = $row['type'] ?? null;

            foreach ($descriptors as $descriptor) {
                if ($type !== $descriptor['set']) {
                    continue;
                }

                if (\in_array($orgId, $this->normalizeIds($row[$descriptor['link']] ?? null), true)) {
                    if ($descriptor['leadership']) {
                        $matchedLeadership = true;
                    } else {
                        $matchedOther = true;
                    }
                }
            }
        }

        unset($row);

        return $matchedLeadership ? 'leadership' : ($matchedOther ? 'other' : null);
    }

    /**
     * The role-set descriptors for an org role, optionally limited to leadership
     * sets. Reads the configurable map and drops malformed descriptors.
     *
     * @return list<array{set: string, link: string, leadership: bool}>
     */
    private function descriptorsForRole(
        string $role,
        bool $leadershipOnly
    ): array {
        if ($role === '') {
            return [];
        }

        /** @var mixed $map */
        $map = config(self::ROLE_MAP_CONFIG, []);
        $forRole = \is_array($map) && \is_array($map[$role] ?? null) ? $map[$role] : [];

        $descriptors = [];

        foreach ($forRole as $descriptor) {
            // Require the two structural keys; default `leadership` to false.
            if (! \is_array($descriptor)) {
                continue;
            }

            if (! isset($descriptor['set'], $descriptor['link'])) {
                continue;
            }

            if (! \is_string($descriptor['set'])) {
                continue;
            }

            if (! \is_string($descriptor['link'])) {
                continue;
            }

            $leadership = (bool) ($descriptor['leadership'] ?? false);

            if ($leadershipOnly && ! $leadership) {
                continue;
            }

            $descriptors[] = [
                'set' => $descriptor['set'],
                'link' => $descriptor['link'],
                'leadership' => $leadership,
            ];
        }

        unset($descriptor);

        return $descriptors;
    }

    /**
     * Every distinct role-set descriptor across the whole role map, keyed by set
     * handle (a set shared by several roles is de-duplicated; its link and
     * leadership flag are consistent across roles by construction).
     *
     * @return array<string, array{link: string, leadership: bool}>
     */
    private function allDescriptors(): array
    {
        /** @var mixed $map */
        $map = config(self::ROLE_MAP_CONFIG, []);

        if (! \is_array($map)) {
            return [];
        }

        $all = [];

        foreach ($map as $descriptors) {
            if (! \is_array($descriptors)) {
                continue;
            }

            foreach ($descriptors as $descriptor) {
                if (! \is_array($descriptor)) {
                    continue;
                }

                if (! isset($descriptor['set'], $descriptor['link'])) {
                    continue;
                }

                if (! \is_string($descriptor['set'])) {
                    continue;
                }

                if (! \is_string($descriptor['link'])) {
                    continue;
                }

                $all[$descriptor['set']] = [
                    'link' => $descriptor['link'],
                    'leadership' => (bool) ($descriptor['leadership'] ?? false),
                ];
            }
        }

        unset($descriptors, $descriptor);

        return $all;
    }

    /**
     * Normalize a job_title link value (an `entries` sub-field stored as a scalar
     * id or an array of ids) to a list of non-empty id strings.
     *
     * @return list<string>
     */
    private function normalizeIds(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        $ids = \is_array($value) ? $value : [$value];

        return array_values(array_filter(
            $ids,
            fn (mixed $id): bool => \is_string($id) && $id !== ''
        ));
    }
}
