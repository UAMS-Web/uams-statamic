<?php

declare(strict_types=1);

namespace App\Support\Multisite;

use App\Services\ComputedFields\DepartmentHub\DepartmentHubPeopleComputedValues;
use Statamic\Entries\Entry;
use Statamic\Entries\EntryCollection;
use UAMSWeb\Aether\People\Contracts\DepartmentHubRoster;

/**
 * The host's department hubs, as `uams-web/aether-people`'s org-derived lenses read them.
 *
 * Organizations and their hub sites stay in the site (#281), so the site binds the
 * add-on's `DepartmentHubRoster` contract to this class. It joins the two site classes
 * the org-role lens gate used before the move: `DepartmentHubResolver` maps a site to
 * its `organizations` entry, and `DepartmentHubPeopleComputedValues` derives that
 * organization's roster.
 */
final readonly class DepartmentHubPeopleRoster implements DepartmentHubRoster
{
    /**
     * Create the roster over the site's hub resolver and roster computeds.
     *
     * @param  DepartmentHubResolver  $hubs  Maps a hub site to its `organizations` entry.
     * @param  DepartmentHubPeopleComputedValues  $roster  Derives an organization's people roster.
     */
    public function __construct(
        private DepartmentHubResolver $hubs,
        private DepartmentHubPeopleComputedValues $roster,
    ) {}

    /**
     * The `organizations` entry a hub site maps to.
     *
     * @param  string  $siteHandle  The handle of the site being rendered.
     * @return Entry|null The organization, or null when the site is not an opt-in hub.
     */
    #[\Override]
    public function organization(string $siteHandle): ?Entry
    {
        return $this->hubs->organization($siteHandle);
    }

    /**
     * The people in an organization's derived roster (leadership and mapped members).
     *
     * @param  Entry  $organization  The organization returned by `organization()`.
     * @return EntryCollection<int, Entry> The roster's `people` entries.
     */
    #[\Override]
    public function resolvePeople(Entry $organization): EntryCollection
    {
        return $this->roster->resolvePeople($organization);
    }
}
