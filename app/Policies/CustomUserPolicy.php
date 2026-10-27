<?php

declare(strict_types=1);

namespace App\Policies;

use Statamic\Facades\User;
use Statamic\Policies\UserPolicy;

/**
 * Custom user policy to restrict acting on super users by non-super users.
 *
 * Guards every super-user-sensitive ability — edit, edit password, delete, and
 * impersonate — so a non-super user cannot edit, reset, delete, or take over a
 * super admin even when granted the corresponding `*-users` permission. (The
 * base policy's `before()` still lets supers act on anyone.)
 *
 * This policy is sourced from studio1902/statamic-peak:
 * - https://statamic.com/starter-kits/studio1902/peak
 * - https://peak.1902.studio/
 */
class CustomUserPolicy extends UserPolicy
{
    /**
     * Determine if a user can be edited.
     *
     * @param  mixed  $authed  The authenticated user
     * @param  mixed  $user  The user to be edited
     * @return bool
     */
    #[\Override]
    public function edit(
        $authed,
        $user
    ) {
        /** @var \Statamic\Contracts\Auth\User $user */
        $user = User::fromUser($user);
        /** @var \Statamic\Contracts\Auth\User $authed */
        $authed = User::fromUser($authed);

        if (! $authed->isSuper() && $user->isSuper()) {
            return false; // Non-super users may not edit super users.
        }

        return (bool) parent::edit(
            $authed,
            $user
        );
    }

    /**
     * Determine if a user's password can be edited.
     *
     * @param  mixed  $authed  The authenticated user
     * @param  mixed  $user  The user whose password would be edited
     * @return bool
     */
    #[\Override]
    public function editPassword(
        $authed,
        $user
    ) {
        /** @var \Statamic\Contracts\Auth\User $user */
        $user = User::fromUser($user);
        /** @var \Statamic\Contracts\Auth\User $authed */
        $authed = User::fromUser($authed);

        if (! $authed->isSuper() && $user->isSuper()) {
            return false; // Non-super users may not edit super users.
        }

        return (bool) parent::editPassword(
            $authed,
            $user
        );
    }

    /**
     * Determine if a user can be deleted.
     *
     * @param  mixed  $authed  The authenticated user
     * @param  mixed  $user  The user to be deleted
     * @return bool
     */
    #[\Override]
    public function delete(
        $authed,
        $user
    ) {
        /** @var \Statamic\Contracts\Auth\User $user */
        $user = User::fromUser($user);
        /** @var \Statamic\Contracts\Auth\User $authed */
        $authed = User::fromUser($authed);

        if (! $authed->isSuper() && $user->isSuper()) {
            return false; // Non-super users may not delete super users.
        }

        return (bool) parent::delete(
            $authed,
            $user
        );
    }

    /**
     * Determine if a user can be impersonated.
     *
     * @param  mixed  $authed  The authenticated user
     * @param  mixed  $user  The user to be impersonated
     * @return bool
     */
    #[\Override]
    public function impersonate(
        $authed,
        $user
    ) {
        /** @var \Statamic\Contracts\Auth\User $user */
        $user = User::fromUser($user);
        /** @var \Statamic\Contracts\Auth\User $authed */
        $authed = User::fromUser($authed);

        if (! $authed->isSuper() && $user->isSuper()) {
            return false; // Non-super users may not impersonate super users.
        }

        return (bool) parent::impersonate(
            $authed,
            $user
        );
    }
}
