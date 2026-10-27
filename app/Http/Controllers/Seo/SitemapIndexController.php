<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seo;

use Illuminate\Http\Response;
use Statamic\Facades\Site;
use Statamic\Sites\Site as SiteObject;
use XMLWriter;

/**
 * Serves the app-owned multisite sitemap index at `/sitemaps.xml`.
 *
 * mitydigital/sitemapamic registers a per-site `{site}/sitemap.xml` for each of
 * the configured sites but emits no cross-site index. This controller renders a
 * `<sitemapindex>` listing every site's sitemap so a single root-level document —
 * referenced from `/robots.txt` (see {@see RobotsController}) — points crawlers at
 * all of them, matching the prior Peak SEO `/sitemaps.xml` behavior.
 */
final class SitemapIndexController
{
    /**
     * Render the `<sitemapindex>` XML listing every site's sitemap.
     */
    public function __invoke(): Response
    {
        // Build the index with XMLWriter so every <loc> is XML-escaped.
        $writer = new XMLWriter;
        $writer->openMemory();
        $writer->startDocument('1.0', 'UTF-8');
        $writer->startElement('sitemapindex');
        $writer->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        // One <sitemap> entry per site, pointing at its Sitemapamic-served map.
        $sites = Site::all();

        if (is_iterable($sites)) {
            foreach ($sites as $site) {
                if (! $site instanceof SiteObject) {
                    continue;
                }

                $url = $site->absoluteUrl();
                $location = rtrim(is_string($url) ? $url : '', '/').'/sitemap.xml';

                $writer->startElement('sitemap');
                $writer->writeElement('loc', $location);
                $writer->endElement();
            }
        }

        // endDocument() closes every open element; a second endElement() would
        // make either RemoveMethodCall equivalent (the other still closes).
        $writer->endDocument();

        return response(
            $writer->outputMemory(),
            200,
            ['Content-Type' => 'application/xml']
        );
    }
}
