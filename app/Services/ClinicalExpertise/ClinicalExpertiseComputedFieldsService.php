<?php

declare(strict_types=1);

namespace App\Services\ClinicalExpertise;

use App\Services\KnowledgeBase\KnowledgeBaseComputedFieldsService;
use App\Support\ContentAncestorIds;
use App\Support\Navigation\SubsectionResolver;
use Statamic\Entries\Entry;
use Statamic\Facades\Blink;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Fields\Blueprint;

/**
 * Resolves clinical_expertise collection computed fields for the AoE subsite
 * chrome (UAMS-Web/uams-statamic-archive#1057). Composes with the extended subsection system (UAMS-Web/uams-statamic-archive#52/UAMS-Web/uams-statamic-archive#53): every
 * Area-of-Expertise entry is its own subsection root, so the header subbrand and
 * the global-nav replacement already activate; this service adds the AoE-specific
 * primary-nav section links that the generic subsection nav does not provide.
 *
 * The load-bearing primitive is the nearest-expertise-ancestor walk (self first),
 * modelled on {@see KnowledgeBaseComputedFieldsService::findKnowledgeBaseAncestor()}
 * and memoized per request. UAMS-Web/uams-statamic-archive#1059's inherited-CTA computed reuses the same walk.
 *
 * @phpstan-type NavLink array{label: string, url: string}
 */
class ClinicalExpertiseComputedFieldsService
{
    /**
     * Blueprint handle of an Area-of-Expertise entry.
     */
    public const BLUEPRINT = 'clinical_expertise';

    /**
     * Section token → short primary-nav label. Deliberately terser than the
     * section-page headings ({@see ClinicalExpertiseSectionResolver}); the nav
     * shows compact labels, the page shows the full "Related …" heading.
     *
     * @var array<string, string>
     */
    private const array NAV_LABELS = [
        'providers' => 'Providers',
        'locations' => 'Locations',
        'specialties' => 'Sub-specialties',
        'related' => 'Related Areas of Expertise',
        'resources' => 'Clinical Resources',
    ];

    /**
     * @param  ClinicalExpertiseSectionResolver  $sections  Populated-section resolution shared with the routed section pages.
     */
    public function __construct(
        private ClinicalExpertiseSectionResolver $sections
    ) {}

    /**
     * The nearest Area-of-Expertise entry for a page — the entry itself when it is
     * an AoE, else the closest AoE ancestor up the structure tree, else null.
     *
     * This is the entry whose chrome (subbrand, primary nav) a page renders, so a
     * content-page descendant inherits its ancestor AoE. Memoized per request per
     * entry id; a not-yet-saved entry (no string id) resolves without memoizing.
     *
     * The memoization scaffolding yields equivalent mutants a test cannot kill
     * (the same limitation as {@see KnowledgeBaseComputedFieldsService}): flipping
     * the `! is_string($entryId)` guard swaps the memoized path for the direct one
     * with an identical result, and mutating the Blink cache-key string only moves
     * the cache slot — neither changes the returned ancestor within a call.
     */
    public function findNearestExpertiseAncestor(Entry $entry): ?Entry
    {
        // An AoE entry is its own nearest expertise ancestor (self first).
        if ($this->isAreaOfExpertise($entry)) {
            return $entry;
        }

        $entryId = $entry->id();
        if (! is_string($entryId)) {
            return $this->computeNearestExpertiseAncestor($entry);
        }

        /** @var ?Entry $ancestor */
        $ancestor = Blink::once(
            'computed_aoe_ancestor_'.$entryId,
            fn (): ?Entry => $this->computeNearestExpertiseAncestor($entry)
        );

        return $ancestor;
    }

    /**
     * Walk the structure ancestors (immediate parent first) to the nearest AoE
     * entry, using the shared guarded walk (cycle / self-parent / hop-cap).
     */
    private function computeNearestExpertiseAncestor(Entry $entry): ?Entry
    {
        $entryId = $entry->id();
        if (! is_string($entryId)) {
            return null;
        }

        foreach ($this->ancestorIds($entryId) as $ancestorId) {
            $ancestor = EntryFacade::find($ancestorId);

            // Skip a stale ancestor id whose entry no longer resolves.
            if ($ancestor instanceof Entry && $this->isAreaOfExpertise($ancestor)) {
                return $ancestor;
            }
        }

        return null;
    }

    /**
     * Ancestor entry ids for the page, immediate parent first. Wraps the shared
     * guarded structure walk (cycle / self-parent / hop-cap) behind a seam the
     * tests can override, mirroring {@see SubsectionResolver::ancestorIds()}.
     *
     * @param  int|string  $entryId  The page's entry id
     * @return list<int|string>
     */
    protected function ancestorIds(int|string $entryId): array
    {
        // ContentAncestorIds::forEntry() findOrFail's the entry. The CP store action
        // resolves computeds during URI validation BEFORE the entry is persisted, so
        // a brand-new entry's id is not yet resolvable — treat it as having no
        // ancestors rather than letting findOrFail throw (which would 500 the save).
        if (EntryFacade::find($entryId) === null) {
            return [];
        }

        return ContentAncestorIds::forEntry($entryId);
    }

    /**
     * The AoE primary-nav link list for a page: an "Overview" link to the nearest
     * expertise ancestor, then one link per populated relationship section pointing
     * at that ancestor's routed `/{section}` URL. Empty when the page is not within
     * an AoE subsite (so the global nav renders unchanged).
     *
     * @return list<NavLink>
     */
    public function resolveSectionNav(Entry $entry): array
    {
        $root = $this->findNearestExpertiseAncestor($entry);
        if (! $root instanceof Entry) {
            return [];
        }

        // The ancestor's own URL anchors the Overview link and each section URL.
        $rootUrl = $root->url();
        if (! is_string($rootUrl) || $rootUrl === '') {
            return [];
        }

        $nav = [['label' => 'Overview', 'url' => $rootUrl]];

        foreach ($this->sections->populatedSections($root) as $token) {
            $nav[] = [
                'label' => self::NAV_LABELS[$token] ?? $token,
                'url' => rtrim($rootUrl, '/').'/'.$token,
            ];
        }

        return $nav;
    }

    /**
     * Whether the page sits within an Area-of-Expertise subsite — i.e. it resolves
     * a nearest expertise ancestor (the entry itself or an AoE above it).
     *
     * The nav templates gate the AoE-vs-global primary nav on this rather than on
     * `aoe_section_nav` being non-empty: within an AoE the global nav's
     * `collection::pages` subsection scoping must never run (an AoE URI is not a
     * node in the pages structure tree — it fatals `TreeBuilder`), even in the
     * degenerate case where the section-nav list is empty.
     */
    public function isWithinExpertiseSubsite(Entry $entry): bool
    {
        return $this->findNearestExpertiseAncestor($entry) instanceof Entry;
    }

    /**
     * The effective appointment-CTA value for a page (UAMS-Web/uams-statamic-archive#1059): the entry's own
     * authored `cta` when it has bars, else — unless the entry opts out via
     * `cta_inherit_disabled` — the `cta` of its nearest structural ancestor that
     * defines one. Null when nothing applies.
     *
     * The returned value is the existing `uamsweb_common_fad.cta_bars` replicator
     * shape (rendered through the `uamsweb_block_cta` body); no new block is
     * introduced. The opt-out suppresses only the *inherited* CTA — an entry's own
     * authored CTA always renders.
     */
    public function resolveEffectiveCta(Entry $entry): mixed
    {
        // The entry's own CTA always wins and always renders.
        $own = $entry->value('cta');
        if ($this->ctaHasBars($own)) {
            return $own;
        }

        // Opt-out suppresses only the inherited CTA (the own CTA is handled above).
        if ($entry->value('cta_inherit_disabled') === true) {
            return null;
        }

        // Inherit the nearest structural ancestor that defines a CTA.
        $entryId = $entry->id();
        if (! is_string($entryId)) {
            return null;
        }

        foreach ($this->ancestorIds($entryId) as $ancestorId) {
            $ancestor = EntryFacade::find($ancestorId);
            if (! $ancestor instanceof Entry) {
                continue;
            }

            $ancestorCta = $ancestor->value('cta');
            if ($this->ctaHasBars($ancestorCta)) {
                return $ancestorCta;
            }
        }

        return null;
    }

    /**
     * Whether a `cta_bars` replicator value holds at least one authored bar.
     */
    private function ctaHasBars(mixed $cta): bool
    {
        return is_array($cta) && $cta !== [];
    }

    /**
     * Whether the entry is on the clinical_expertise (Area of Expertise) blueprint.
     */
    private function isAreaOfExpertise(Entry $entry): bool
    {
        // Entry::blueprint() is typed mixed in the stubs; narrow before handle().
        /** @var Blueprint $blueprint */
        $blueprint = $entry->blueprint();

        return $blueprint->handle() === self::BLUEPRINT;
    }
}
