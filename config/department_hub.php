<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | People-zone org-role → job_title role-set mapping (UAMS-Web/uams-statamic-archive#1557)
    |--------------------------------------------------------------------------
    |
    | The Departmental News Hub derives its leadership/people zone from the
    | existing `job_title` role replicator on `people` — no new model. A site's
    | `department_organization` (UAMS-Web/uams-statamic-archive#1556) resolves to an `organizations` entry; that
    | entry's `organization_role` selects, via this map, which `job_title` role
    | SETS name the org and through which `entries`→organizations LINK sub-field.
    |
    | Each descriptor:
    |   - `set`        the job_title replicator set handle (its stored `type`).
    |   - `link`       the `entries`→organizations sub-field within that set.
    |   - `leadership` true for the required leadership sets — the reliable spine
    |                  rendered first; false for the optional (faculty / member)
    |                  sets whose link population the audit tracks.
    |
    | The map is keyed by org role so the derivation is not hardcoded to academic
    | departments; add a role's descriptors here to extend the people zone to it.
    | An org role absent from this map (or a non-entity / no-org site) yields no
    | people zone — the hub degrades to news-only.
    |
    */
    'people_role_map' => [

        // Academic department / division — leadership is required; faculty optional.
        'department_academic' => [
            ['set' => 'academic_leadership_role', 'link' => 'department', 'leadership' => true],
            ['set' => 'faculty_role_title', 'link' => 'department', 'leadership' => false],
        ],
        'division_academic' => [
            ['set' => 'academic_leadership_role', 'link' => 'department', 'leadership' => true],
            ['set' => 'faculty_role_title', 'link' => 'department', 'leadership' => false],
        ],

        // Clinical service line — leadership over the service line (required).
        'service_line' => [
            ['set' => 'clinical_leadership_role', 'link' => 'service_line', 'leadership' => true],
        ],

        // Institutional department — leadership required; the general role optional.
        'department_institutional' => [
            ['set' => 'institutional_leadership_role', 'link' => 'department', 'leadership' => true],
            ['set' => 'institutional_role', 'link' => 'department', 'leadership' => false],
        ],

    ],

];
