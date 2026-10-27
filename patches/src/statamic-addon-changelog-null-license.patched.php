<?php

declare(strict_types=1);

namespace App\Patches\Snippets;

/**
 * Canonical "after" body for `AddonChangelog::isLicensed()` (null-safe license).
 */
class StatamicAddonChangelogNullLicensePatched
{
    /**
     * Mirrors `AddonChangelog::$addon` for parseable snippet text only.
     *
     * @var mixed
     */
    protected $addon;

    protected function isLicensed($version)
    {
        return version_compare($version, $this->addon->license()?->versionLimit() ?? 999999, '<');
    }
}
