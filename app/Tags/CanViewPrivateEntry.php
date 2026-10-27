<?php

declare(strict_types=1);

namespace App\Tags;

use App\Http\Middleware\ProtectEntry;
use App\Support\Entries\PrivateEntryAccess;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Tags\Parameters;
use Statamic\Tags\Tags;

/**
 * The {{ can_view_private_entry }} tag: whether the current user may view the
 * `private` entry with the given id (UAMS-Web/uams-statamic-archive#1152).
 *
 * The primary-nav templates exclude `private` entries from the menu for the
 * public, but a super user or a user who can publish the entry's collection may
 * both open the page and see it in the nav. This tag exposes that decision — the
 * shared {@see PrivateEntryAccess} rule the {@see ProtectEntry}
 * front-end gate uses — so the nav's `{{ unless }}` guard keeps the two consistent.
 */
class CanViewPrivateEntry extends Tags
{
    /**
     * The {{ can_view_private_entry :id="..." }} tag.
     *
     * Resolves the entry named by the `id` parameter and returns whether the
     * current user may view it while it is `private`. An absent/unknown id yields
     * false (the safe default — a private entry stays hidden), so the guard only
     * reveals a private item for a genuinely privileged user.
     */
    public function index(): bool
    {
        /** @var Parameters $params */
        $params = $this->params;
        $id = $params->get('id');

        if (! \is_string($id) && ! \is_int($id)) {
            return false;
        }

        // The Stache returns concrete Entry instances; narrow the facade's
        // contract return so the shared rule can read the entry's collection.
        $entry = EntryFacade::find((string) $id);

        return app(PrivateEntryAccess::class)->canView($entry instanceof Entry ? $entry : null);
    }
}
