<?php

declare(strict_types=1);

namespace App\Support\Redirects;

use Statamic\Contracts\Globals\GlobalSet as GlobalSetContract;
use Statamic\Entries\Entry;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;
use Statamic\Globals\Variables;

/**
 * Records a redirect for an entry that has just been deleted, so the URL it occupied does
 * not begin returning 404 with nothing pointing anywhere.
 *
 * The captured row is written **disabled**. `RedirectRuleMatcher` skips a row whose
 * `enabled` is false, so a capture with no destination yet cannot serve a redirect — the
 * editor supplies the target and enables it as one deliberate act. That ordering is the
 * whole design: the alternative, a row that is live the moment it is written, would send
 * visitors to an empty destination.
 *
 * A structured (page-tree) entry keeps its full URI here, parent segments and all, because
 * Statamic resolves and caches the entry's URI while the delete is still in progress. That
 * is an implementation detail of the delete rather than a documented guarantee, so
 * `DeletedEntryRedirectCaptureTest` pins it: a page-tree entry must capture
 * `/services/cardiology`, never the parentless `/cardiology` a post-delete re-derivation
 * would produce.
 *
 * **Multi-site: this writes one row, into the deleted entry's own site, and that is
 * sufficient rather than a shortfall (UAMS-Web/uams-statamic-archive#2365).** The reason is Statamic's delete semantics,
 * not anything this class does. `Entry::delete()` throws outright while descendants exist,
 * so the only route to deleting a localized entry is `deleteDescendants()` first, and that
 * calls `delete()` on each descendant in turn. Every localization therefore raises its own
 * `EntryDeleted` carrying its own `locale()`, and each reaches this class separately to be
 * written into its own site. `DeleteMultisiteEntry`'s other behavior, detach, leaves the
 * localizations alive as independent entries, so their URLs never go dark and there is
 * nothing to capture for them.
 *
 * Two facts that decide whether that holds, both measured rather than assumed:
 * `GlobalSet::in()` returns null only for a site the set does not list and otherwise creates
 * the localization on demand, and the `redirects` global lists every site in
 * `resources/sites.yaml` — so no site's capture is silently dropped for want of a
 * localization. `MultisiteDeletedEntryRedirectCaptureTest` pins all of it, so a change to
 * either side fails there rather than quietly dropping sibling URLs.
 */
class DeletedEntryRedirectCapture
{
    /**
     * The global set handle the redirect rules live in.
     */
    private const string GLOBAL_HANDLE = 'redirects';

    /**
     * Record a disabled redirect for the deleted entry, in the site that entry belongs to.
     *
     * One call writes at most one row. A localized entry reaches this class once per
     * localization, each with its own site, which is why that is enough — see the class
     * docblock.
     *
     * @param  Entry  $entry  The entry Statamic has just deleted.
     * @return int Number of redirect rows written.
     */
    public function captureFor(Entry $entry): int
    {
        // Resolve the redirects global; nothing can be recorded without it
        $global = GlobalSet::findByHandle(self::GLOBAL_HANDLE);

        if (! $global instanceof GlobalSetContract) {
            return 0;
        }

        // An entry with no URI was never addressable, so its deletion leaves no 404 behind
        $uri = $this->uriFor($entry);

        if ($uri === null) {
            return 0;
        }

        // Write into the entry's own site, so the row sits beside that site's other rules.
        // Both `locale()` and the default handle are `mixed` to the analyser, so neither can
        // be handed on without narrowing.
        $locale = $entry->locale();
        $defaultSite = Site::default();
        $default = $defaultSite instanceof \Statamic\Sites\Site ? $defaultSite->handle() : null;
        $siteHandle = is_string($locale) && $locale !== ''
            ? $locale
            : (is_string($default) ? $default : 'default');

        return $this->appendDisabledRedirect($global, $siteHandle, $uri, $entry) ? 1 : 0;
    }

    /**
     * The deleted entry's public URI, or null when it had none.
     *
     * @param  Entry  $entry  The deleted entry.
     * @return string|null Leading-slash URI, or null when the entry was not addressable.
     */
    private function uriFor(Entry $entry): ?string
    {
        $uri = $entry->uri();

        // A draft, a dated-collection entry with no route, or a structure-less entry has none
        if (! is_string($uri) || $uri === '') {
            return null;
        }

        return $uri;
    }

    /**
     * Append one disabled redirect row to a site's localization of the redirects global.
     *
     * @param  GlobalSetContract  $global  The redirects global set.
     * @param  string  $siteHandle  Site whose localization receives the row.
     * @param  string  $uri  The deleted entry's URI, used as the redirect source.
     * @param  Entry  $entry  The deleted entry, recorded in the row's notes.
     * @return bool Whether a row was written.
     */
    private function appendDisabledRedirect(GlobalSetContract $global, string $siteHandle, string $uri, Entry $entry): bool
    {
        $variables = $global->in($siteHandle);

        // A site with no localization has nowhere to put the row
        if (! $variables instanceof Variables) {
            return false;
        }

        // Read the existing rows the same way the matcher does, so a write round-trips
        $redirects = $variables->get('redirects', []);

        if (! is_array($redirects)) {
            $redirects = [];
        }

        // Do not record a second capture for a URI that already has a rule
        foreach ($redirects as $existing) {
            if (is_array($existing) && ($existing['source'] ?? null) === $uri) {
                return false;
            }
        }

        $redirects[] = $this->rowFor($uri, $entry);

        $variables->set('redirects', $redirects);
        $variables->save();

        return true;
    }

    /**
     * Build the replicator row for a captured redirect.
     *
     * @param  string  $uri  The deleted entry's URI.
     * @param  Entry  $entry  The deleted entry, for the note.
     * @return array<string, mixed> A `redirect` set row, disabled and awaiting a destination.
     */
    private function rowFor(string $uri, Entry $entry): array
    {
        return [
            'type' => 'redirect',
            'enabled' => false,
            'source' => $uri,
            'use_regex' => false,
            'target_type' => 'url',
            'target_url' => '',
            'notes' => sprintf(
                'Captured automatically when entry %s was deleted. Set a target and enable this redirect.',
                is_scalar($id = $entry->id()) ? (string) $id : 'unknown'
            ),
        ];
    }
}
