<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seo;

use Illuminate\Http\Response;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;
use Statamic\Globals\Variables;
use Statamic\Sites\Site as SiteObject;

/**
 * Serves the app-owned `/robots.txt`.
 *
 * Replaces studio1902/statamic-peak-seo's robots route (UAMS-Web/uams-statamic-archive#1326, unblocking
 * UAMS-Web/uams-statamic-archive#1117). It renders the crawler directives stored on the `seo` global's
 * `robots` field and appends a single `Sitemap:` line pointing at the app-owned
 * multisite sitemap index (`/sitemaps.xml`, served by {@see SitemapIndexController}),
 * so crawlers discover every site's sitemap from one root document.
 */
final class RobotsController
{
    /**
     * The handle of the global set holding the `robots` field.
     */
    private const string SEO_GLOBAL_HANDLE = 'seo';

    /**
     * Render the plain-text robots.txt for the whole domain.
     */
    public function __invoke(): Response
    {
        // Resolve the stored crawler rules from the seo global (default site);
        // blank when the content global is absent (e.g. a fresh checkout).
        $globalSet = GlobalSet::findByHandle(self::SEO_GLOBAL_HANDLE);
        $variables = $globalSet?->inDefaultSite();
        $robots = $variables instanceof Variables ? $variables->get('robots') : null;
        $rules = is_string($robots) ? $robots : '';

        // Point crawlers at the multisite sitemap index at the domain root.
        $defaultSite = Site::default();
        $baseUrl = '';

        if ($defaultSite instanceof SiteObject) {
            $url = $defaultSite->absoluteUrl();
            $baseUrl = is_string($url) ? $url : '';
        }

        $sitemapIndexUrl = rtrim($baseUrl, '/').'/sitemaps.xml';

        return response(
            self::buildBody($rules, $sitemapIndexUrl),
            200,
            ['Content-Type' => 'text/plain; charset=utf-8']
        );
    }

    /**
     * Compose the robots.txt body from the stored rules and the sitemap index URL.
     *
     * The rules (when non-blank) come first, then a single trailing `Sitemap:`
     * directive. Kept pure so both branches are testable without content.
     */
    public static function buildBody(string $rules, string $sitemapIndexUrl): string
    {
        $rules = trim($rules);

        return ($rules === '' ? '' : $rules."\n\n").'Sitemap: '.$sitemapIndexUrl."\n";
    }
}
