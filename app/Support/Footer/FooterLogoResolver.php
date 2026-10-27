<?php

declare(strict_types=1);

namespace App\Support\Footer;

use Illuminate\Support\Collection;
use Statamic\Facades\GlobalSet;
use Statamic\Globals\Variables;

/**
 * Resolves which site owns the global footer logo for the current site (UAMS-Web/uams-statamic-archive#46).
 *
 * `global_footer_logo` is a localizable field on the `uams_multisite_settings`
 * global set, so Statamic inherits a child site's footer logo asset from the
 * closest ancestor (in the multisite origin chain) that sets its own — the
 * rendered `<img>` already belongs to that ancestor. This resolver finds the
 * same site so the footer link and accessible name can point at it too, instead
 * of being hard-pinned to the top-most ancestor as they were before.
 *
 * Priority (per the issue): the current site if it sets its own logo; else the
 * closest ancestor that does; else the top-most ancestor (today's behavior),
 * so nothing breaks for sites without a per-site logo.
 */
class FooterLogoResolver
{
    /**
     * Handle of the multisite-settings global set.
     */
    private const string GLOBAL_HANDLE = 'uams_multisite_settings';

    /**
     * Handle of the per-site footer logo asset-group field.
     */
    private const string LOGO_FIELD = 'global_footer_logo';

    /**
     * Resolve the handle of the site whose footer logo, link, and accessible
     * name the global footer should use.
     *
     * @param  string  $currentSiteHandle  The handle of the current site
     * @return string The resolved owner site's handle
     */
    public function resolveOwnerHandle(string $currentSiteHandle): string
    {
        $set = GlobalSet::find(self::GLOBAL_HANDLE);

        // Without the global set there is nothing to walk — keep the current site.
        if (! $set) {
            return $currentSiteHandle;
        }

        $variables = $set->in($currentSiteHandle);
        $topmostHandle = $currentSiteHandle;

        // Walk the origin chain current-site-first. The first site that sets its
        // own logo owns the (inherited) rendered image, so return it.
        while ($variables instanceof Variables) {
            if ($this->setsOwnLogo($variables)) {
                return $this->handleOf($variables);
            }

            // Remember the farthest site seen as the final fallback.
            $topmostHandle = $this->handleOf($variables);

            // Walk to the parent localization; the loop condition re-narrows it
            // to Variables (and ends the walk at the root, where origin is null).
            $variables = $variables->origin();
        }

        // No site in the chain sets its own logo — point at the top-most
        // ancestor, preserving the original behavior.
        return $topmostHandle;
    }

    /**
     * Read a localization's site handle (locale) as a string.
     */
    private function handleOf(Variables $variables): string
    {
        $locale = $variables->locale();

        return \is_string($locale) ? $locale : '';
    }

    /**
     * Determine whether a site sets its own (non-inherited) footer logo.
     *
     * Reads the localization's own data only — `data()` excludes values
     * inherited from the origin — so inheritance does not make every descendant
     * look like an owner.
     */
    private function setsOwnLogo(Variables $variables): bool
    {
        $data = $variables->data();

        if (! $data instanceof Collection || ! $data->has(self::LOGO_FIELD)) {
            return false;
        }

        // A present-but-empty value (cleared asset group) is not ownership.
        return ! empty($data->get(self::LOGO_FIELD));
    }
}
