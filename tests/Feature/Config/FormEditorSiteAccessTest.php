<?php

declare(strict_types=1);

use Statamic\Facades\YAML;
use Tests\TestCase;

uses(TestCase::class);

/**
 * UAMS-Web/uams-statamic-archive#3065: `uams-web/statamic-multisite-forms` lets a non-super user edit a form
 * only when they can access every site it is assigned to, so `form_editor`
 * holds `access {site} site` for every site in `resources/sites.yaml`. This
 * fails when a site is added without the matching grant.
 *
 * UAMS-Web/uams-statamic-archive#3081: those grants make anything else `form_editor` holds reach all of
 * those sites at once, and the role is safe only because of what it does not
 * hold (no `access cp`, no content permission). The permitted-set cases below
 * hold that absence in place, and pin each role named in
 * siteGrantGuardedRoles() to the one site grant it holds today.
 *
 * UAMS-Web/uams-statamic-archive#3095: the per-role cases cover only the roles this file names, so a further
 * case fails when `resources/users/roles.yaml` holds any other role. A new role
 * is covered by nothing here until it is added to siteGrantGuardedRoles() with
 * the site grants it is meant to hold.
 */

/**
 * The roles the per-role cases cover, other than `form_editor`, mapped to the
 * site handles each is meant to hold a grant for.
 *
 * @return array<string, list<string>>
 */
function siteGrantGuardedRoles(): array
{
    return [
        'contributor' => ['default'],
        'editor' => ['default'],
        'marketeer' => ['default'],
        'people_editor' => ['default'],
        'user_management' => ['default'],
    ];
}

/**
 * The site handles defined in `resources/sites.yaml`.
 *
 * @return list<string>
 */
function formEditorSiteHandles(): array
{
    /** @var array<string, mixed> $sites */
    $sites = YAML::file(resource_path('sites.yaml'))->parse();

    return array_map(strval(...), array_keys($sites));
}

/**
 * The permissions `resources/users/roles.yaml` grants a role.
 *
 * @return list<string>
 */
function roleYamlPermissions(string $role): array
{
    /** @var array<string, array{permissions?: list<string>}> $roles */
    $roles = YAML::file(resource_path('users/roles.yaml'))->parse();

    return $roles[$role]['permissions'] ?? [];
}

/**
 * The site handles a role's `access {site} site` permissions name.
 *
 * @param  list<string>  $permissions
 * @return list<string>
 */
function roleSiteGrants(array $permissions): array
{
    $handles = [];

    foreach ($permissions as $permission) {
        if (preg_match('/^access (.+) site$/', $permission, $m) === 1) {
            $handles[] = $m[1];
        }
    }

    return $handles;
}

it('reads every site handle from sites.yaml', function (): void {
    $handles = formEditorSiteHandles();

    expect($handles)->toContain('default', 'health')
        ->and(count($handles))->toBeGreaterThan(2);
});

it('grants form_editor access to every configured site', function (): void {
    $permissions = roleYamlPermissions('form_editor');

    $missing = array_values(array_filter(
        formEditorSiteHandles(),
        fn (string $handle): bool => ! in_array(sprintf('access %s site', $handle), $permissions, true),
    ));

    expect($missing)->toBe([]);
});

it('grants form_editor no access to a site that is not configured', function (): void {
    $handles = formEditorSiteHandles();

    $extra = array_values(array_filter(
        roleYamlPermissions('form_editor'),
        fn (string $permission): bool => preg_match('/^access (.+) site$/', $permission, $m) === 1
            && ! in_array($m[1], $handles, true),
    ));

    expect($extra)->toBe([]);
});

it('grants form_editor nothing beyond configuring forms and site access', function (): void {
    $permissions = roleYamlPermissions('form_editor');

    $other = array_values(array_filter(
        $permissions,
        fn (string $permission): bool => preg_match('/^access .+ site$/', $permission) !== 1,
    ));
    sort($other);

    expect($other)->toBe(['configure form fields', 'configure forms']);
});

it('keeps every other role to the one site grant it holds', function (string $role): void {
    expect(roleSiteGrants(roleYamlPermissions($role)))->toBe(siteGrantGuardedRoles()[$role]);
})->with(array_keys(siteGrantGuardedRoles()));

it('covers every role in roles.yaml', function (): void {
    /** @var array<string, mixed> $roles */
    $roles = YAML::file(resource_path('users/roles.yaml'))->parse();

    $present = array_map(strval(...), array_keys($roles));
    sort($present);

    $covered = ['form_editor', ...array_keys(siteGrantGuardedRoles())];
    sort($covered);

    expect($present)->toBe($covered);
});
