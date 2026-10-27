<?php

declare(strict_types=1);

namespace App\Scopes;

use Illuminate\Support\Arr;
use Statamic\Query\Builder;
use Statamic\Query\Scopes\Scope;

/**
 * Entry query scope that filters `organizations` entries by organization role.
 *
 * Resolves the role from encoded query scope handles — e.g.
 * `entries_by_organization_role_brand`, `entries_by_organization_role_department_academic`,
 * `entries_by_organization_role_service_line` (one per value in ROLES).
 *
 * Role values are noun-first so the handle mirrors the CP label
 * ("Department, Academic" → `department_academic`).
 *
 * One scope class serves every role via aliases; the invoking handle's suffix
 * is matched against each entry's `organization_role` field. Used with the
 * entries fieldtype so a brand picker shows only brand organizations, a
 * department picker only departments, and so on, within the one collection.
 *
 * A handle may also encode a named role *group* (see ROLE_GROUPS), which
 * expands to several roles at once — e.g. `entries_by_organization_role_academic_unit`
 * offers a department plus everything nested within it. Listing multiple
 * handles (roles and/or groups) on one field unions their roles, because every
 * invocation reads the full `queryScopes` list and applies a single whereIn.
 */
class EntriesByOrganizationRole extends Scope
{
    /**
     * Scope handle prefix for registration and alias encoding.
     *
     * @var string
     */
    protected static $handle = 'entries_by_organization_role';

    /**
     * Organization-role values that can be encoded as scope-handle suffixes.
     *
     * @var array<int, string>
     */
    public const array ROLES = [
        'brand',
        'college',
        'institute',
        'department_academic',
        'division_academic',
        'program',
        'program_academic',
        'program_academic_degree',
        'department_clinical',
        'department_institutional',
        'service_line',
        'campus',
    ];

    /**
     * Named groups that expand to several roles, so one handle can target a
     * whole family of org units.
     *
     * `academic_unit` is the academic org tree at or below a department: the
     * department itself, its divisions/sections, and its academic programs
     * (degree and non-degree). It excludes generic `program` (non-academic
     * centers) and the college/institute tiers above a department.
     *
     * `clinical_unit` is the clinical side a provider can hold a role in: a
     * clinical department or a service line.
     *
     * `department_unit` is the set of org tiers a news post or Departmental News
     * Hub is associated with: a college, an institute, a department (academic,
     * clinical, or institutional), an academic division, or a clinical service
     * line. It anchors on the department tier but also spans the college and
     * institute tiers a department rolls up into — so college- and
     * institute-level news is storable, since the newsroom category tree keys
     * news at those tiers too (UAMS-Web/uams-statamic-archive#1600) — and the division/service-line sub-units
     * below. Shared by a site's `department_organization` (UAMS-Web/uams-statamic-archive#1556) and a post's
     * `related_organizations` (UAMS-Web/uams-statamic-archive#1598). It excludes only the top `brand` tier and
     * the generic `program` and `campus` tiers.
     *
     * @var array<string, array<int, string>>
     */
    public const array ROLE_GROUPS = [
        'academic_unit' => [
            'department_academic',
            'division_academic',
            'program_academic',
            'program_academic_degree',
        ],
        'clinical_unit' => [
            'department_clinical',
            'service_line',
        ],
        'department_unit' => [
            'college',
            'institute',
            'department_academic',
            'division_academic',
            'department_clinical',
            'department_institutional',
            'service_line',
        ],
    ];

    /**
     * Register one alias per role and per role group so a single class serves
     * them all.
     *
     * The lists are small and static, so they are built inline (no ScopeAliasCache).
     *
     * @return array<int, string>
     */
    public static function aliases(): array
    {
        $prefix = static::$handle.'_';

        return array_map(
            static fn (string $suffix): string => $prefix.$suffix,
            [...self::ROLES, ...array_keys(self::ROLE_GROUPS)]
        );
    }

    /**
     * Filter to entries whose `organization_role` is in the set encoded by the
     * invoking scope handle(s). With no recognized handle, no filter applies.
     *
     * @param  Builder  $query  Entry query builder
     * @param  array<string, mixed>  $values  Request values (e.g., queryScopes)
     */
    public function apply(
        $query,
        $values
    ): void {
        $roles = $this->rolesFromScopeHandles($values);

        // No recognized role handle: leave the query unfiltered.
        if ($roles === []) {
            return;
        }

        $query->whereIn(
            'organization_role',
            $roles
        );

        unset($roles);
    }

    /**
     * Extract recognized role suffixes from the query scope handles.
     *
     * @param  array<string, mixed>  $values
     * @return array<int, string>
     */
    private function rolesFromScopeHandles(array $values): array
    {
        $queryScopes = Arr::wrap($values['queryScopes'] ?? []);
        $prefix = static::$handle.'_';
        $roles = [];

        foreach ($queryScopes as $queryScope) {
            if (! \is_string($queryScope)) {
                continue;
            }

            if (! str_starts_with($queryScope, $prefix)) {
                continue;
            }

            $suffix = strtolower(substr($queryScope, \strlen($prefix)));

            // A group handle expands to its member roles; a bare role maps to itself.
            if (isset(self::ROLE_GROUPS[$suffix])) {
                array_push($roles, ...self::ROLE_GROUPS[$suffix]);
            } elseif (\in_array($suffix, self::ROLES, true)) {
                $roles[] = $suffix;
            }
        }

        unset($queryScope);

        return array_values(array_unique($roles));
    }
}
