<?php

declare(strict_types=1);

namespace App\Patches\Snippets;

/**
 * Canonical "after" body for `UpdatesOverview::checkForAddonUpdates()`.
 *
 * Counts addons with changelog upgrades so the CP nav badge matches the
 * updater index (`availableUpdatesCount()`), not stale Outpost `latest_version`
 * metadata used by `isLatestVersion()`.
 */
class StatamicUpdatesOverviewBadgePatched
{
    protected function checkForAddonUpdates()
    {
        $updatableAddons = Addon::all()
            ->filter->existsOnMarketplace()
            ->filter(fn ($addon) => $addon->changelog()->availableUpdatesCount() > 0);

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
