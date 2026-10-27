<?php

declare(strict_types=1);

namespace App\Support\Navigation;

use App\Support\ContentAncestorIds;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Fields\Blueprint;

/**
 * Resolves the subsection root for a page (UAMS-Web/uams-statamic-archive#52).
 *
 * A "subsection" is a within-site content branch treated as a sub-brand, ported
 * from the WordPress `uamswp_nav_subsection()` behavior: a page that starts a
 * subsection is flagged with the `subsection` marker (the "This Entry Starts a
 * New Subsection" toggle on `uamsweb_entry_site_navigation`). The subsection a
 * page belongs to is the nearest page — itself first, then up the page tree —
 * that sets that marker.
 *
 * When a page is inside a subsection the header sub-brand shows the subsection
 * root and the primary navigation scopes to its branch; pages outside any
 * subsection are unaffected.
 */
class SubsectionResolver
{
    /**
     * Marker field flagging a page as the start of a subsection.
     */
    private const string MARKER_FIELD = 'subsection';

    /**
     * Blueprint whose entries are automatically subsection roots without the marker.
     *
     * An Area-of-Expertise (`clinical_expertise`) entry is its own subsite within
     * the `health` site (UAMS-Web/uams-statamic-archive#1057), so it starts a subsection by blueprint; its
     * content-page descendants inherit it through the ancestor walk. Every other
     * page still opts in explicitly via {@see self::MARKER_FIELD} (UAMS-Web/uams-statamic-archive#52).
     */
    private const string AUTO_SUBSECTION_BLUEPRINT = 'clinical_expertise';

    /**
     * Resolve the subsection root entry for a page, or null when the page is not
     * within a subsection.
     *
     * @param  int|string  $entryId  The current page's entry id
     */
    public function resolveRoot(int|string $entryId): ?Entry
    {
        $entry = EntryFacade::find($entryId);

        if (! $entry instanceof Entry) {
            return null;
        }

        // Self first: the current page may itself start a subsection.
        if ($this->startsSubsection($entry)) {
            return $entry;
        }

        // Then the nearest ancestor (immediate parent first) up the page tree.
        foreach ($this->ancestorIds($entryId) as $ancestorId) {
            $ancestor = EntryFacade::find($ancestorId);

            // Skip a stale ancestor id whose entry no longer resolves.
            if ($ancestor instanceof Entry && $this->startsSubsection($ancestor)) {
                return $ancestor;
            }
        }

        return null;
    }

    /**
     * Ancestor entry ids for the page, immediate parent first.
     *
     * Wraps the shared structure walk (with its cycle / self-parent / hop-cap
     * guards) in a seam the tests can override.
     *
     * @param  int|string  $entryId  The page's entry id
     * @return list<int|string>
     */
    protected function ancestorIds(int|string $entryId): array
    {
        return ContentAncestorIds::forEntry($entryId);
    }

    /**
     * Determine whether a page starts a subsection: automatically by blueprint
     * (an Area of Expertise), or explicitly via the editor-set marker.
     */
    private function startsSubsection(Entry $entry): bool
    {
        // An Area-of-Expertise entry is its own subsection root (UAMS-Web/uams-statamic-archive#1057) without
        // the marker; any other page opts in via the marker (UAMS-Web/uams-statamic-archive#52).
        if ($this->isAutoSubsectionBlueprint($entry)) {
            return true;
        }

        return $entry->value(self::MARKER_FIELD) === true;
    }

    /**
     * Whether the entry's blueprint auto-starts a subsection (no marker required).
     */
    private function isAutoSubsectionBlueprint(Entry $entry): bool
    {
        // Entry::blueprint() is typed mixed in the stubs; narrow it before handle().
        /** @var Blueprint $blueprint */
        $blueprint = $entry->blueprint();

        return $blueprint->handle() === self::AUTO_SUBSECTION_BLUEPRINT;
    }
}
