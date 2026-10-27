<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Str;
use Statamic\Auth\File\User as FileStatamicUser;
use Statamic\Facades\Role;
use Statamic\Facades\User;
use Tests\TestCase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;
use function Pest\Laravel\withoutMiddleware;

/**
 * HTTP coverage for the Statamic `Trigger` fieldtype activate endpoint.
 *
 * Ensures `POST /!/trigger/activate` is gated by the resource-specific
 * `activate field triggers` permission (UAMS-Web/uams-statamic-archive#863), not bare `access cp`: a guest is
 * unauthorized, an `access cp`-only CP user (e.g. the `editor` role) is rejected
 * with 403, and a user holding the permission (a super user) may dispatch
 * `TriggerActivated`.
 *
 * Mutation: no mutable App surface targetable here — the activate logic is a
 * route closure in routes/web.php (outside the analyzed app/ path) and
 * App\Events\TriggerActivated is a thin event DTO (0 covered mutants); the
 * table-data-importer listener has its own test. This test verifies the
 * permission gating and JSON response shape of POST /!/trigger/activate.
 */
uses(TestCase::class);

/**
 * The email used for every temporary user this test persists. Cleanup in
 * afterEach deletes by this email rather than guessing the on-disk path
 * (Statamic file-backed users land at `users/{email}.yaml` at the project root,
 * a private git submodule — a manual unlink would silently miss).
 */
const TRIGGER_ACTIVATE_ROUTE_TEST_EMAIL = 'trigger-activate-route-test@example.test';

beforeEach(function (): void {
    // Disable CSRF to isolate auth assertions.
    withoutMiddleware(PreventRequestForgery::class);
});

const TRIGGER_ACTIVATE_ROUTE_TEST_ROLE = 'trigger_activate_route_test_role';

afterEach(function (): void {
    // Remove the temporary Statamic user via the facade if one was created.
    /** @var FileStatamicUser|null $user */
    $user = User::findByEmail(TRIGGER_ACTIVATE_ROUTE_TEST_EMAIL);
    if ($user !== null) {
        $user->delete();
    }

    // Remove the temporary role created for the non-super permission test.
    $role = Role::find(TRIGGER_ACTIVATE_ROUTE_TEST_ROLE);
    if ($role !== null) {
        $role->delete();
    }
});

/**
 * Persist a temporary file-backed Statamic user.
 *
 * @param  callable(FileStatamicUser): void  $configure  Applies role/super to the user before saving
 */
function triggerActivateRouteUser(callable $configure): FileStatamicUser
{
    $user = User::make();

    // Narrow the repository user to the file-backed implementation.
    expect($user)->toBeInstanceOf(FileStatamicUser::class);

    /** @var FileStatamicUser $user */
    $user->id((string) Str::uuid());
    $user->email(TRIGGER_ACTIVATE_ROUTE_TEST_EMAIL);
    $user->password('password');

    // Apply the caller's role/permission setup
    $configure($user);

    $user->save();

    return $user;
}

it('does not let a guest activate a trigger', function (): void {
    $response = postJson(
        '/!/trigger/activate',
        [
            'trigger_handle' => 'table_data_importer_trigger',
            'form_values' => [],
        ]
    );

    $response->assertUnauthorized();
});

it('rejects a cp user that lacks the activate field triggers permission', function (): void {
    // The `editor` role holds `access cp` but NOT `activate field triggers`, so
    // it used to reach this endpoint and must now be refused (UAMS-Web/uams-statamic-archive#863).
    $user = triggerActivateRouteUser(function (FileStatamicUser $user): void {
        $user->assignRole('editor');
    });

    // Guard the precondition: the gating must be the new permission, not a
    // coincidental lack of CP access.
    expect($user->can('access cp'))->toBeTrue();
    expect($user->can('activate field triggers'))->toBeFalse();

    actingAs($user);

    $response = postJson(
        '/!/trigger/activate',
        [
            'trigger_handle' => 'table_data_importer_trigger',
            'form_values' => [],
        ]
    );

    $response->assertForbidden();
});

it('lets a user holding the activate field triggers permission activate a trigger', function (): void {
    // A super user holds every permission, including `activate field triggers`.
    $user = triggerActivateRouteUser(function (FileStatamicUser $user): void {
        $user->makeSuper();
    });

    expect($user->can('activate field triggers'))->toBeTrue();

    actingAs($user);

    $response = postJson(
        '/!/trigger/activate',
        [
            'trigger_handle' => 'table_data_importer_trigger',
            'form_values' => [],
        ]
    );

    $response->assertOk();
    $response->assertJsonStructure(
        [
            'success',
            'updated_values',
        ]
    );
});

it('lets a non-super cp user granted the permission via a role activate a trigger', function (): void {
    // Prove the PERMISSION grants access, not a super-user bypass: a plain user in
    // a role carrying `access cp` + `activate field triggers` (and nothing more).
    $role = Role::make();
    /** @var Statamic\Auth\File\Role $role */
    $role->handle(TRIGGER_ACTIVATE_ROUTE_TEST_ROLE);
    $role->addPermission('access cp');
    $role->addPermission('activate field triggers');
    $role->save();

    $user = triggerActivateRouteUser(function (FileStatamicUser $user): void {
        $user->assignRole(TRIGGER_ACTIVATE_ROUTE_TEST_ROLE);
    });

    // Guard the precondition: a genuinely non-super user that holds the permission.
    expect($user->isSuper())->toBeFalse();
    expect($user->can('activate field triggers'))->toBeTrue();

    actingAs($user);

    $response = postJson(
        '/!/trigger/activate',
        [
            'trigger_handle' => 'table_data_importer_trigger',
            'form_values' => [],
        ]
    );

    $response->assertOk();
});
