<?php

declare(strict_types=1);

namespace App\Fieldtypes;

use App\Support\Multisite\ChildSitesResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Statamic\Facades\Site;
use Statamic\Fieldtypes\Sites;
use Statamic\Sites\Site as SiteObject;

/**
 * A `sites` relationship fieldtype restricted to the editing site's immediate
 * children (UAMS-Web/uams-statamic-archive#219).
 *
 * Statamic's `sites` fieldtype lists every site the user can view. This narrows
 * the picker to the direct children of the site being edited (via
 * `App\Support\Multisite\ChildSitesResolver`), so an editor placing a
 * child-site link in the primary navigation can only choose a real child — no
 * cross-tree leakage. All other behavior (augmentation, indexing) is inherited.
 */
class ChildSites extends Sites
{
    /**
     * Restrict the selectable options to the editing site's direct children.
     *
     * @param  Request  $request
     * @return Collection<int, mixed>
     */
    #[\Override]
    public function getIndexItems($request)
    {
        $items = parent::getIndexItems($request);

        // The parent always returns a Collection, so the fallback is unreachable and InstanceOfToTrue is equivalent
        // @pest-mutate-ignore: InstanceOfToTrue
        return $this->filterToChildSites($items instanceof Collection ? $items : collect());
    }

    /**
     * Filter a set of site option items down to the editing site's direct
     * children. Extracted from `getIndexItems()` so it can be tested without the
     * Control Panel's authenticated relationship-index request.
     *
     * @param  Collection<int, mixed>  $items  Site options as `['id' => handle, 'title' => name]`
     * @return Collection<int, mixed>
     */
    public function filterToChildSites(Collection $items): Collection
    {
        $childHandles = app(ChildSitesResolver::class)->forSite($this->editingSiteHandle());

        return $items
            ->filter(function ($item) use ($childHandles): bool {
                $id = is_array($item) ? ($item['id'] ?? null) : null;

                return is_string($id) && $childHandles->contains($id);
            })
            ->values();
    }

    /**
     * The handle of the site currently selected in the Control Panel.
     */
    private function editingSiteHandle(): string
    {
        $site = Site::selected();

        if (! $site instanceof SiteObject) {
            return '';
        }

        $handle = $site->handle();

        return is_string($handle) ? $handle : '';
    }
}
