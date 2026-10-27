<?php

declare(strict_types=1);

use Statamic\Facades\YAML;
use Tests\Support\FormsSubmodule;
use Tests\TestCase;

uses(TestCase::class);

/**
 * UAMS-Web/uams-statamic-archive#3070: a role in `resources/users/roles.yaml` may only grant a per-form
 * permission (`view {form} form submissions`, `delete {form} form submissions`)
 * for a form that exists under `resources/forms`. A grant naming a missing form
 * gives nothing and reads as access the role does not have.
 *
 * @command  php artisan test --compact tests/Feature/Config/RoleFormPermissionsTest.php
 */

/**
 * Pattern matching Statamic's per-form permissions, capturing the form handle.
 */
const ROLE_FORM_PERMISSION_PATTERN = '/^(?:view|delete) (\S+) form submissions$/';

/**
 * The form handles defined as YAML files in the configured forms directory.
 *
 * @return list<string>
 */
function roleFormPermissionsFormHandles(): array
{
    // Read every form definition file from the configured forms directory
    $directory = config()->string('statamic.forms.forms');
    $files = glob($directory.'/*.yaml') ?: [];

    return array_map(static fn (string $file): string => basename($file, '.yaml'), $files);
}

/**
 * Every per-form permission granted by any role, mapped to the form it names.
 *
 * @param  array<string, array{permissions?: list<string>}>  $roles  Parsed roles keyed by handle.
 * @return list<array{role: string, permission: string, form: string}>
 */
function roleFormPermissionsGranted(array $roles): array
{
    $granted = [];

    // Collect each permission that names a specific form, with its role
    foreach ($roles as $role => $definition) {
        foreach ($definition['permissions'] ?? [] as $permission) {
            if (preg_match(ROLE_FORM_PERMISSION_PATTERN, $permission, $matches) === 1) {
                $granted[] = ['role' => $role, 'permission' => $permission, 'form' => $matches[1]];
            }
        }
    }

    return $granted;
}

/**
 * The per-form permissions in the given roles whose form does not exist.
 *
 * @param  array<string, array{permissions?: list<string>}>  $roles  Parsed roles keyed by handle.
 * @return list<string> `role: permission` for each orphaned grant.
 */
function roleFormPermissionsOrphaned(array $roles): array
{
    $forms = roleFormPermissionsFormHandles();

    return array_values(array_map(
        static fn (array $grant): string => $grant['role'].': '.$grant['permission'],
        array_filter(
            roleFormPermissionsGranted($roles),
            static fn (array $grant): bool => ! in_array($grant['form'], $forms, true),
        ),
    ));
}

it('reads the form handles from the forms directory', function (): void {
    FormsSubmodule::skipUnlessInitialized();

    expect(roleFormPermissionsFormHandles())->toContain('spread_kindness');
});

it('grants no form permission for a form that does not exist', function (): void {
    FormsSubmodule::skipUnlessInitialized();

    /** @var array<string, array{permissions?: list<string>}> $roles */
    $roles = YAML::file(resource_path('users/roles.yaml'))->parse();

    expect(roleFormPermissionsOrphaned($roles))->toBe([]);
});

it('reports a form permission for a missing form, and accepts one for an existing form', function (): void {
    FormsSubmodule::skipUnlessInitialized();

    $roles = [
        'editor' => ['permissions' => [
            'access cp',
            'view contact form submissions',
            'delete spread_kindness form submissions',
        ]],
    ];

    expect(roleFormPermissionsOrphaned($roles))->toBe(['editor: view contact form submissions']);
});
