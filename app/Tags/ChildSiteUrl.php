<?php

declare(strict_types=1);

namespace App\Tags;

use Statamic\Facades\Site;
use Statamic\Fields\Value;
use Statamic\Sites\Site as SiteObject;
use Statamic\Tags\Context;
use Statamic\Tags\Parameters;
use Statamic\Tags\Tags;

/**
 * `{{ child_site_url }}` — the absolute URL of a `child_site_link` page's
 * selected child site (UAMS-Web/uams-statamic-archive#219).
 *
 * In primary-navigation generation a `child_site_link` page links to another
 * site of the multisite rather than to its own permalink. This tag resolves the
 * selected site (the `child_site` field) to its URL; it reads the handle from
 * the `:handle` param, falling back to `child_site` in the current context (the
 * nav-item scope). Returns an empty string for a normal page (no `child_site`),
 * so the caller can fall back to the page's own URL.
 */
class ChildSiteUrl extends Tags
{
    /**
     * Resolve the selected child site's absolute URL, or an empty string.
     */
    public function index(): string
    {
        /** @var Parameters $params */
        $params = $this->params;

        $value = $params->get('handle');

        if ($value === null) {
            /** @var Context $context */
            $context = $this->context;
            $value = $context->get('child_site');
        }

        $site = $this->resolveSite($value);

        if (! $site instanceof SiteObject) {
            return '';
        }

        $url = $site->absoluteUrl();

        return is_string($url) ? $url : '';
    }

    /**
     * Normalize a `child_site` value (augmented Site, handle string, or
     * collection of either) to a single Site.
     */
    private function resolveSite(mixed $value): ?SiteObject
    {
        if ($value instanceof Value) {
            $value = $value->value();
        }

        if ($value instanceof SiteObject) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $site = Site::get($value);

            return $site instanceof SiteObject ? $site : null;
        }

        if (is_iterable($value)) {
            foreach ($value as $item) {
                $resolved = $this->resolveSite($item);

                if ($resolved instanceof SiteObject) {
                    return $resolved;
                }
            }
        }

        return null;
    }
}
