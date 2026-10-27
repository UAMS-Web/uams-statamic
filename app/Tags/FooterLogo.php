<?php

declare(strict_types=1);

namespace App\Tags;

use App\Support\Footer\FooterLogoResolver;
use Statamic\Facades\Site;
use Statamic\Sites\Site as SiteObject;
use Statamic\Tags\Tags;

/**
 * `{{ footer_logo }}` — resolves the global footer logo's owner site (UAMS-Web/uams-statamic-archive#46).
 *
 * Returns the site whose footer logo is rendered (the closest site, current
 * first, up the multisite origin chain that sets its own `global_footer_logo`),
 * so the footer link and accessible name align with the inherited logo image
 * instead of always pointing at the top-most ancestor.
 *
 * @return array{handle: string, link: string, text: string}
 */
class FooterLogo extends Tags
{
    /**
     * Resolve the footer logo owner's handle, URL, and display name.
     *
     * @return array{handle: string, link: string, text: string}
     */
    public function index(): array
    {
        /** @var SiteObject $currentSite */
        $currentSite = Site::current();

        $currentHandle = $this->stringValue($currentSite->handle());
        $ownerHandle = app(FooterLogoResolver::class)->resolveOwnerHandle($currentHandle);

        // Resolve the owner site; fall back to the current site if the handle no
        // longer maps to a configured site.
        $ownerSite = Site::get($ownerHandle);
        if (! $ownerSite instanceof SiteObject) {
            $ownerSite = $currentSite;
            $ownerHandle = $currentHandle;
        }

        return [
            'handle' => $ownerHandle,
            'link' => $this->stringValue($ownerSite->absoluteUrl()),
            'text' => $this->stringValue($ownerSite->name()),
        ];
    }

    /**
     * Narrow an untyped Site accessor value to a string.
     */
    private function stringValue(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
