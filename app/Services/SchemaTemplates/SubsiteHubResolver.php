<?php

declare(strict_types=1);

namespace App\Services\SchemaTemplates;

use App\Services\ClinicalExpertise\ClinicalExpertiseComputedFieldsService;
use App\Support\ContentAncestorIds;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Fields\Blueprint;

/**
 * Resolves the nearest virtual-subsite hub for a content page (issue UAMS-Web/uams-statamic-archive#1414).
 *
 * A `basic_page` / `landing_page` inside the `clinical_expertise` (Medical
 * Specialty) or `clinical_locations` (Location) subsite describes itself in
 * JSON-LD as a `WebPage` that `isPartOf` its subsite hub. The hub is the nearest
 * ancestor up the structure tree on a hub blueprint — the Area-of-Expertise entry
 * (`clinical_expertise`) or the Location entry (`location`) — mirroring the
 * nearest-ancestor walk in {@see ClinicalExpertiseComputedFieldsService}.
 *
 * The two subsites live in separate structure trees, so the nearest hub ancestor
 * is unambiguous and one shared resolver serves both.
 */
class SubsiteHubResolver
{
    /**
     * Blueprint handles that anchor a virtual subsite hub: the Area-of-Expertise
     * entry and the Location entry.
     *
     * @var list<string>
     */
    public const array HUB_BLUEPRINTS = ['clinical_expertise', 'location'];

    /**
     * The nearest ancestor entry that anchors the page's virtual subsite — the
     * closest ancestor on a hub blueprint up the structure tree — or null when the
     * page sits outside a subsite (or its id is not yet resolvable).
     */
    public function resolve(Entry $page): ?Entry
    {
        $pageId = $page->id();
        if (! \is_string($pageId)) {
            return null;
        }

        foreach ($this->ancestorIds($pageId) as $ancestorId) {
            $ancestor = EntryFacade::find($ancestorId);

            // Skip a stale ancestor id whose entry no longer resolves.
            if ($ancestor instanceof Entry && $this->isHub($ancestor)) {
                return $ancestor;
            }
        }

        return null;
    }

    /**
     * The nearest subsite hub entry id (as a single-element relationship list so
     * `schema_thing_from_relationship` reads it like any entries field), or null.
     *
     * @return list<string>|null
     */
    public function resolveIdList(Entry $page): ?array
    {
        $id = $this->resolve($page)?->id();

        return \is_string($id) ? [$id] : null;
    }

    /**
     * Ancestor entry ids for the page, immediate parent first. Wraps the shared
     * guarded structure walk (cycle / self-parent / hop-cap) behind a seam the
     * tests can override, mirroring the AoE / KB computed services.
     *
     * @param  int|string  $entryId  The page's entry id
     * @return list<int|string>
     */
    protected function ancestorIds(int|string $entryId): array
    {
        // ContentAncestorIds::forEntry() findOrFail's the entry; a not-yet-saved
        // entry (its computeds resolve during CP save validation before persist)
        // has no resolvable id, so treat it as having no ancestors.
        if (EntryFacade::find($entryId) === null) {
            return [];
        }

        return ContentAncestorIds::forEntry($entryId);
    }

    /**
     * Whether the entry anchors a virtual subsite (its blueprint is a hub blueprint).
     */
    private function isHub(Entry $entry): bool
    {
        // Entry::blueprint() is typed mixed in the stubs; narrow before handle().
        /** @var Blueprint $blueprint */
        $blueprint = $entry->blueprint();

        // Equivalent with loose comparison: blueprint handles are non-numeric strings,
        // so == and === agree on every one.
        // @pest-mutate-ignore: TrueToFalse
        return \in_array($blueprint->handle(), self::HUB_BLUEPRINTS, true);
    }
}
