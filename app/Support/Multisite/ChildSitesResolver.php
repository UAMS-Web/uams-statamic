<?php

declare(strict_types=1);

namespace App\Support\Multisite;

use Illuminate\Support\Collection;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;
use Statamic\Globals\Variables;
use Statamic\Sites\Site as SiteObject;

/**
 * Resolves a site's immediate child sites in the multisite tree (UAMS-Web/uams-statamic-archive#219).
 *
 * The parent/child relationships are encoded in the `uams_multisite_settings`
 * global set's localization origins (the same tree the footer-logo and
 * subsection resolvers walk): a site is a direct child of X when its
 * localization's origin is X. Used to scope the `child_sites` fieldtype to the
 * editing site's direct children.
 */
class ChildSitesResolver
{
    /**
     * Handle of the multisite-settings global set.
     */
    private const string GLOBAL_HANDLE = 'uams_multisite_settings';

    /**
     * Return the handles of the immediate child sites of the given site.
     *
     * @param  string  $parentHandle  The site whose direct children to resolve
     * @return Collection<int, string>
     */
    public function forSite(string $parentHandle): Collection
    {
        /** @var list<string> $children */
        $children = [];

        $set = GlobalSet::find(self::GLOBAL_HANDLE);
        $sites = Site::all();

        // Check each site's multisite-settings origin; the contract exposes
        // `in()` (not `localizations()`), so iterate the configured sites.
        if ($set && is_iterable($sites)) {
            foreach ($sites as $site) {
                if (! $site instanceof SiteObject) {
                    continue;
                }

                $handle = $site->handle();
                if (! is_string($handle)) {
                    continue;
                }

                if ($handle === '') {
                    continue;
                }

                $variables = $set->in($handle);
                $origin = $variables instanceof Variables ? $variables->origin() : null;

                if ($origin instanceof Variables && $origin->locale() === $parentHandle) {
                    $children[] = $handle;
                }
            }
        }

        return collect($children);
    }
}
