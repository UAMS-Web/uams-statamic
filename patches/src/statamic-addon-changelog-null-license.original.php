<?php

declare(strict_types=1);

namespace App\Patches\Snippets;

/**
 * Canonical "before" body for `AddonChangelog::isLicensed()`.
 *
 * Must match `vendor/statamic/cms/src/Updater/AddonChangelog.php` for the
 * installed Statamic version (generator verifies this).
 */
class StatamicAddonChangelogNullLicenseOriginal
{
    /**
     * Mirrors `AddonChangelog::$addon` for parseable snippet text only.
     *
     * @var mixed
     */
    protected $addon;

    protected function isLicensed($version)
    {
        return version_compare($version, $this->addon->license()->versionLimit() ?? 999999, '<');
    }
}
