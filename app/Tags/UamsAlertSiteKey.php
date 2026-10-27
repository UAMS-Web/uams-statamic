<?php

declare(strict_types=1);

namespace App\Tags;

use App\Support\UamsAlert\AlertSiteKeyResolver;
use Statamic\Facades\Site;
use Statamic\Sites\Site as SiteObject;
use Statamic\Tags\Tags;

/**
 * `{{ uams_alert_site_key }}` — the current site's UAMS Alert bucket (UAMS-Web/uams-statamic-archive#299).
 *
 * Emitted as `data-uams-alert-site` on `<body>` so `uamsalert.js` knows which
 * bucket's alerts (`alert-<key>` / `urgent-<key>` / `fyi-<key>`) to display.
 * The mapping lives in `config/uamsalert.php`; see `AlertSiteKeyResolver`.
 */
class UamsAlertSiteKey extends Tags
{
    /**
     * Resolve the alert bucket for the current Statamic site.
     *
     * @return string The alert bucket key (e.g. `uams`, `uamshealth`)
     */
    public function index(): string
    {
        // Site::current() and Site::handle() are untyped at the facade; narrow
        // both. An unresolved site yields an empty handle, which the resolver
        // maps to the default bucket.
        $site = Site::current();
        $handle = $site instanceof SiteObject ? $site->handle() : '';

        return app(AlertSiteKeyResolver::class)
            ->resolve(\is_string($handle) ? $handle : '');
    }
}
