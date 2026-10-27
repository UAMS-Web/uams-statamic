<?php

declare(strict_types=1);

namespace App\Patches\Snippets;

/**
 * Canonical "before" body for `UpdatesOverview::checkForAddonUpdates()`.
 *
 * Must match `vendor/statamic/cms/src/Updater/UpdatesOverview.php` for the
 * installed Statamic version (generator verifies this).
 */
class StatamicUpdatesOverviewBadgeOriginal
{
    protected function checkForAddonUpdates()
    {
        $updatableAddons = Addon::all()->reject->isLatestVersion();

        foreach ($updatableAddons as $addon) {
            if ($addon->changelog()->hasSecurityUpdate()) {
                $this->security = true;
                break;
            }
        }

        $this->addons = $updatableAddons->map->id()->values()->all();
        $this->count += count($this->addons);

        return $this;
    }
}
