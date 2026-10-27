<?php

declare(strict_types=1);

use App\Policies\CustomUserPolicy;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Statamic\Contracts\Auth\User;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * Regression coverage for UAMS-Web/uams-statamic-archive#926.
 *
 * `CustomUserPolicy` exists to stop non-super users from acting on super users,
 * but it only guarded `edit()`/`editPassword()`. `delete()` and `impersonate()`
 * fell through to the base policy's bare permission check, so a non-super with
 * `delete users` / `impersonate users` could delete or take over a super admin.
 * These tests pin the guards on all four abilities.
 *
 * @command  php artisan test --compact tests/Feature/Policies/CustomUserPolicyTest.php
 *
 * Mutation: 100% on App\Policies\CustomUserPolicy (28 mutants, --covered-only).
 */

/**
 * Build a Statamic user mock with fixed `isSuper()`/`hasPermission()`/`id()` returns.
 *
 * `id()` is what the base policy compares to let a user edit their own profile, so each
 * user gets its own unless the caller passes the same one twice.
 */
function customUserPolicyTestUser(bool $super, bool $hasPermission = false, ?string $id = null): User
{
    $user = Mockery::mock(User::class);
    $user->shouldReceive('isSuper')->andReturn($super);
    $user->shouldReceive('hasPermission')->andReturn($hasPermission);
    $user->shouldReceive('id')->andReturn($id ?? uniqid('user-', true));

    return $user;
}

/**
 * Fresh policy instance under test.
 */
function customUserPolicyTestPolicy(): CustomUserPolicy
{
    return new CustomUserPolicy;
}

it('does not let a non-super delete a super even with permission', function (): void {
    $authed = customUserPolicyTestUser(super: false, hasPermission: true);
    $target = customUserPolicyTestUser(super: true);

    expect(customUserPolicyTestPolicy()->delete($authed, $target))->toBeFalse();
});

it('does not let a non-super impersonate a super even with permission', function (): void {
    $authed = customUserPolicyTestUser(super: false, hasPermission: true);
    $target = customUserPolicyTestUser(super: true);

    expect(customUserPolicyTestPolicy()->impersonate($authed, $target))->toBeFalse();
});

it('lets a non-super with permission delete and impersonate non-supers', function (): void {
    $authed = customUserPolicyTestUser(super: false, hasPermission: true);
    $target = customUserPolicyTestUser(super: false);

    expect(customUserPolicyTestPolicy()->delete($authed, $target))->toBeTrue();
    expect(customUserPolicyTestPolicy()->impersonate($authed, $target))->toBeTrue();
});

it('still stops a non-super without permission from acting on non-supers', function (): void {
    $authed = customUserPolicyTestUser(super: false, hasPermission: false);
    $target = customUserPolicyTestUser(super: false);

    expect(customUserPolicyTestPolicy()->delete($authed, $target))->toBeFalse();
    expect(customUserPolicyTestPolicy()->impersonate($authed, $target))->toBeFalse();
});

it('lets a super delete and impersonate a super', function (): void {
    $authed = customUserPolicyTestUser(super: true, hasPermission: true);
    $target = customUserPolicyTestUser(super: true);

    expect(customUserPolicyTestPolicy()->delete($authed, $target))->toBeTrue();
    expect(customUserPolicyTestPolicy()->impersonate($authed, $target))->toBeTrue();
});

it('does not let a non-super edit a super, or change a super\'s password, even with permission', function (string $ability): void {
    $authed = customUserPolicyTestUser(super: false, hasPermission: true);
    $target = customUserPolicyTestUser(super: true);

    expect(customUserPolicyTestPolicy()->{$ability}($authed, $target))->toBeFalse();
})->with(['edit', 'editPassword']);

it('defers to the base policy when the target is not a super, or the actor is', function (string $ability, bool $authedSuper, bool $targetSuper, bool $hasPermission, bool $expected): void {
    $authed = customUserPolicyTestUser(super: $authedSuper, hasPermission: $hasPermission);
    $target = customUserPolicyTestUser(super: $targetSuper);

    expect(customUserPolicyTestPolicy()->{$ability}($authed, $target))->toBe($expected);
})->with([
    'edit: a non-super with permission, a non-super' => ['edit', false, false, true, true],
    'edit: a non-super without permission, a non-super' => ['edit', false, false, false, false],
    'edit: a super, a super' => ['edit', true, true, true, true],
    'edit: a super without permission, a non-super' => ['edit', true, false, false, false],
    'password: a non-super with permission, a non-super' => ['editPassword', false, false, true, true],
    'password: a non-super without permission, a non-super' => ['editPassword', false, false, false, false],
    'password: a super, a super' => ['editPassword', true, true, true, true],
]);

it('lets a non-super edit their own profile and password without permission', function (string $ability): void {
    $authed = customUserPolicyTestUser(super: false, hasPermission: false, id: 'same-user');
    $target = customUserPolicyTestUser(super: false, id: 'same-user');

    expect(customUserPolicyTestPolicy()->{$ability}($authed, $target))->toBeTrue();
})->with(['edit', 'editPassword']);

it('returns a boolean even when a permission check answers with a truthy non-boolean', function (string $ability): void {
    $authed = Mockery::mock(User::class);
    $authed->shouldReceive('isSuper')->andReturn(false);
    $authed->shouldReceive('hasPermission')->andReturn(1);
    $authed->shouldReceive('id')->andReturn('actor');
    $target = customUserPolicyTestUser(super: false, id: 'target');

    expect(customUserPolicyTestPolicy()->{$ability}($authed, $target))->toBeTrue();
})->with(['edit', 'editPassword', 'delete', 'impersonate']);
