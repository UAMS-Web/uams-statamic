<?php

declare(strict_types=1);

namespace App\Support\UamsAlert;

use Illuminate\Contracts\Config\Repository as Config;

/**
 * Resolves a Statamic site handle to its UAMS Alert "site" bucket (UAMS-Web/uams-statamic-archive#299).
 *
 * The shared alert feed categorizes alerts by a small set of coarse buckets
 * (`uams`, `uamshealth`, …); there are ~255 Statamic sites, so the mapping is
 * declared explicitly in `config/uamsalert.php` (`buckets`) rather than derived
 * from the handle. Any unmapped handle resolves to the configured `default`.
 */
class AlertSiteKeyResolver
{
    public function __construct(private readonly Config $config) {}

    /**
     * Resolve the alert bucket for a Statamic site handle.
     *
     * Returns the first bucket whose handle list contains `$siteHandle`, else
     * the configured default bucket.
     *
     * @param  string  $siteHandle  The current Statamic site handle (e.g. `health`, `default`)
     * @return string The alert bucket key (e.g. `uamshealth`, `uams`)
     */
    public function resolve(string $siteHandle): string
    {
        // Read the bucket → [handles] map, tolerating a malformed/missing config.
        $buckets = $this->config->get('uamsalert.buckets', []);
        $buckets = \is_array($buckets) ? $buckets : [];

        // Return the first bucket whose handle list contains this site.
        foreach ($buckets as $bucket => $handles) {
            if (
                \is_string($bucket)
                && \is_array($handles)
                && \in_array($siteHandle, $handles, true)
            ) {
                return $bucket;
            }
        }

        // Fall back to the configured default bucket (the shared UAMS alert).
        $default = $this->config->get('uamsalert.default', 'uams');

        return \is_string($default) ? $default : 'uams';
    }
}
