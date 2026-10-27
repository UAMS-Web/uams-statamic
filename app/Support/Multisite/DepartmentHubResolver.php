<?php

declare(strict_types=1);

namespace App\Support\Multisite;

use App\Services\SchemaTemplates\SchemaTemplatesCascadeService;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;
use Statamic\Globals\Variables;
use Statamic\Sites\Site as SiteObject;

/**
 * Resolves a site's Departmental News Hub organization (UAMS-Web/uams-statamic-archive#1556).
 *
 * A site opts in as a Departmental News Hub by naming its `organizations` entry
 * in the `department_organization` field of its `uams_multisite_settings` global
 * localization. That org is the hub's universal department key — the same entry
 * anchors its schema entity node (`schema_subject`), its derived people zone
 * (UAMS-Web/uams-statamic-archive#1557), and its department-filtered news feed / archive (UAMS-Web/uams-statamic-archive#1598 / UAMS-Web/uams-statamic-archive#1599). This
 * resolver is the single read path for "which organization is this site's hub
 * for", plus the data-integrity audit the hub relies on to never emit a broken
 * entity node.
 *
 * The bridge is intentionally one-directional and opt-in: a site with no
 * `department_organization` is simply not a hub (the {@see self::organization()}
 * missing-link fallback returns null), so non-hub sites are wholly unaffected.
 */
class DepartmentHubResolver
{
    /**
     * Handle of the multisite-settings global set (the per-site hub config).
     */
    private const string GLOBAL_HANDLE = 'uams_multisite_settings';

    /**
     * The collection every hub organization must belong to.
     */
    private const string ORGANIZATIONS_COLLECTION = 'organizations';

    /**
     * Resolve the `organizations` entry id designated for the given site's hub,
     * or null when the site is not an opt-in hub (the missing-link fallback).
     *
     * @param  string  $siteHandle  The site whose hub organization to resolve.
     */
    public function organizationId(string $siteHandle): ?string
    {
        $set = GlobalSet::find(self::GLOBAL_HANDLE);

        if ($set === null) {
            return null;
        }

        $variables = $set->in($siteHandle);

        if (! $variables instanceof Variables) {
            return null;
        }

        return $this->singleEntryId($variables->get('department_organization'));
    }

    /**
     * Resolve the `organizations` entry a site is the news hub for, or null when
     * the site is not a hub or the stored id no longer resolves to an
     * organizations entry (the missing-link fallback).
     *
     * @param  string  $siteHandle  The site whose hub organization to resolve.
     */
    public function organization(string $siteHandle): ?Entry
    {
        $id = $this->organizationId($siteHandle);

        if ($id === null) {
            return null;
        }

        $entry = EntryFacade::find($id);

        // Guard against a dangling reference or a picker that stored an entry
        // from another collection — only an organizations entry is a valid hub.
        if (! $entry instanceof Entry || $entry->collectionHandle() !== self::ORGANIZATIONS_COLLECTION) {
            return null;
        }

        return $entry;
    }

    /**
     * Whether the given site is an opt-in Departmental News Hub (it names a
     * `department_organization`).
     *
     * @param  string  $siteHandle  The site to test.
     */
    public function isHubSite(string $siteHandle): bool
    {
        return $this->organizationId($siteHandle) !== null;
    }

    /**
     * Audit every configured site and return the handles of opt-in hub sites
     * whose `schema_subject` is unpopulated — a hub that would emit a broken
     * (subject-less) schema entity node. An empty list means every hub site is
     * schema-complete.
     *
     * @return array<int, string> Hub site handles missing a `schema_subject`.
     */
    public function hubSitesMissingSchemaSubject(): array
    {
        $set = GlobalSet::find(self::GLOBAL_HANDLE);

        if ($set === null) {
            return [];
        }

        $missing = [];

        $sites = Site::all();

        if (! is_iterable($sites)) {
            return $missing;
        }

        foreach ($sites as $site) {
            if (! $site instanceof SiteObject) {
                continue;
            }

            $handle = $site->handle();
            if (! \is_string($handle)) {
                continue;
            }

            if ($handle === '') {
                continue;
            }

            $variables = $set->in($handle);

            if (! $variables instanceof Variables) {
                continue;
            }

            // Only hub sites are audited; a non-hub site has no entity node to break.
            if ($this->singleEntryId($variables->get('department_organization')) === null) {
                continue;
            }

            if ($this->singleEntryId($variables->get('schema_subject')) === null) {
                $missing[] = $handle;
            }
        }

        unset($site);

        return $missing;
    }

    /**
     * Unwrap a stored entries-field value (a `max_items: 1` field stores a
     * one-element array, but a bare scalar is tolerated) to its single entry id,
     * or null when empty. Mirrors {@see SchemaTemplatesCascadeService}'s
     * `singleEntryId`.
     */
    private function singleEntryId(mixed $value): ?string
    {
        if (\is_array($value)) {
            $first = reset($value);

            return \is_scalar($first) && $first !== false && $first !== '' ? (string) $first : null;
        }

        return \is_scalar($value) && $value !== false && $value !== '' ? (string) $value : null;
    }
}
