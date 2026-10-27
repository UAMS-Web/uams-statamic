<?php

declare(strict_types=1);

namespace App\Support\Entries;

use App\Http\Middleware\ProtectEntry;
use App\Tags\CanViewPrivateEntry;
use Statamic\Contracts\Auth\User;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry;
use Statamic\Facades\User as UserFacade;

/**
 * The single source of truth for who may view a `private` entry (UAMS-Web/uams-statamic-archive#223, UAMS-Web/uams-statamic-archive#1152).
 *
 * A `private` entry is live (`published`) but restricted: it must never leak to
 * the public, yet it stays reachable — and visible in the primary nav — for the
 * privileged users who can open it. {@see ProtectEntry}
 * enforces the 404 for the front-end request; {@see CanViewPrivateEntry}
 * drives the nav's per-item visibility. Both defer here so the nav can never show
 * a private entry the middleware would then 404 (or hide one it would serve).
 */
class PrivateEntryAccess
{
    /**
     * Whether the current user may view a `private` entry.
     *
     * A super user always may; otherwise the user must be able to *publish* the
     * entry's collection — the capability that separates an editor (who may see
     * private content) from a lesser role such as `contributor`, which holds
     * `view`/`edit` to author drafts but must not read others' private entries
     * (WordPress's `read_private_posts`). Guests, and a missing entry/collection,
     * never may.
     *
     * @param  Entry|null  $entry  The private entry in question
     * @return bool Whether the current user is permitted to view it
     */
    public function canView(?Entry $entry): bool
    {
        if (! $entry instanceof Entry) {
            return false;
        }

        $user = UserFacade::current();

        if (! $user instanceof User) {
            return false;
        }

        if ($user->isSuper()) {
            return true;
        }

        $collection = $entry->collection();

        if (! $collection instanceof Collection) {
            return false;
        }

        $handle = $collection->handle();

        return \is_string($handle)
            && $user->hasPermission('publish '.$handle.' entries');
    }
}
