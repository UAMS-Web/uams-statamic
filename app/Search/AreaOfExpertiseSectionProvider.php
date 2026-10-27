<?php

declare(strict_types=1);

namespace App\Search;

use App\Listeners\SyncAreaOfExpertiseSectionSearchIndex;
use App\Scopes\ExcludeUnsearchableEntries;
use App\Services\ClinicalExpertise\ClinicalExpertiseSectionResolver;
use Illuminate\Support\Collection;
use Statamic\Entries\Entry;
use Statamic\Entries\EntryCollection;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\Site;
use Statamic\Search\Searchables\Provider;
use Statamic\Stache\Query\EntryQueryBuilder;

/**
 * A custom {@see Provider} that surfaces each populated Area-of-Expertise routed
 * section subpage (`/{entry}/{section}`, UAMS-Web/uams-statamic-archive#1058) as its own interactive search
 * result (UAMS-Web/uams-statamic-archive#1527).
 *
 * The subpages are not entries — {@see ClinicalExpertiseSectionResolver} derives
 * them per AoE at request time — so the `local` driver never indexes them. This
 * provider synthesizes one {@see AreaOfExpertiseSectionDocument} per populated
 * section and is registered into the `default` (All results) index in
 * `config/statamic/search.php` via the `clinical_expertise_section:*` searchables
 * key.
 *
 * The per-entry `searchable` gate (UAMS-Web/uams-statamic-archive#1060/UAMS-Web/uams-statamic-archive#1438) is honored here so a subpage of a
 * `searchable: false` Area of Expertise never surfaces, while still resolving at its
 * URL — mirroring {@see ExcludeUnsearchableEntries}'s strict-`false`
 * rule (a missing/null toggle stays searchable).
 *
 * Incremental freshness (an AoE edit adding/removing a section, toggling
 * `searchable`, or being deleted) is driven by
 * {@see SyncAreaOfExpertiseSectionSearchIndex}, because the `local`
 * driver's own reindex only tracks the source Entry, not these derived documents.
 */
final class AreaOfExpertiseSectionProvider extends Provider
{
    /**
     * The `clinical_expertise` collection handle whose entries expose routed sections.
     */
    public const string COLLECTION = 'clinical_expertise';

    private ?ClinicalExpertiseSectionResolver $resolver = null;

    /**
     * Config `searchables` prefix — an index references this provider as
     * `clinical_expertise_section:*`.
     */
    #[\Override]
    public static function handle(): string
    {
        return 'clinical_expertise_section';
    }

    /**
     * Reference prefix (unique across providers); also the augmented `result_type` the
     * results template dispatches on.
     */
    #[\Override]
    public static function referencePrefix(): string
    {
        return AreaOfExpertiseSectionDocument::REFERENCE_PREFIX;
    }

    /**
     * The references for every populated, searchable AoE section — the full index
     * membership rebuilt on `search:update` or first query.
     *
     * @return Collection<int, string>
     */
    public function provide(): Collection
    {
        /** @var EntryQueryBuilder $query */
        $query = EntryFacade::query();
        $query->where('collection', self::COLLECTION);

        // Annotate the loosely-typed Stache result so the iteration type-checks (mirrors
        // ExcludeUnsearchableEntries).
        /** @var EntryCollection<int, Entry> $entries */
        $entries = $query->get();

        $references = [];
        foreach ($entries as $aoe) {
            foreach ($this->sectionReferencesFor($aoe) as $reference) {
                $references[] = $reference;
            }
        }

        return collect($references);
    }

    /**
     * Whether a searchable belongs to this provider. Scoped strictly to our synthetic
     * document type — never the source Entry — so the core `UpdateItemIndexes` listener
     * (which only ever emits the saved Entry) does not misroute an `entry::` document
     * into this provider's set; our own listener drives incremental updates instead.
     */
    public function contains(mixed $searchable): bool
    {
        return $searchable instanceof AreaOfExpertiseSectionDocument;
    }

    /**
     * Rehydrate documents from their keys (the reference with the `clinical_expertise_section::`
     * prefix already stripped, i.e. `<aoe-id>::<section>`). Called at both index-build and
     * query time; a key whose AoE was deleted, gated out, or whose section emptied resolves to
     * nothing, so a stale reference never renders.
     *
     * @param  list<string>  $keys
     * @return Collection<int, AreaOfExpertiseSectionDocument>
     */
    public function find(array $keys): Collection
    {
        $documents = [];
        foreach ($keys as $key) {
            $document = $this->documentForKey($key);
            if ($document instanceof AreaOfExpertiseSectionDocument) {
                $documents[] = $document;
            }
        }

        return collect($documents);
    }

    /**
     * The section references for one AoE entry: none unless it is a published, searchable
     * Area of Expertise, then one per populated section (in display order).
     *
     * @return list<string>
     */
    public function sectionReferencesFor(Entry $aoe): array
    {
        if (! $this->isSearchable($aoe)) {
            return [];
        }

        $id = $aoe->id();
        if (! \is_string($id)) {
            return [];
        }

        return array_map(
            static fn (string $section): string => AreaOfExpertiseSectionDocument::reference($id, $section),
            $this->resolver()->populatedSections($aoe),
        );
    }

    /**
     * Build the document for one AoE section, or null when it must not be indexed: the entry
     * is not a published, searchable Area of Expertise, or the section is not populated (the
     * resolver's own 404 gate).
     */
    public function documentFor(Entry $aoe, string $section): ?AreaOfExpertiseSectionDocument
    {
        if (! $this->isSearchable($aoe)) {
            return null;
        }

        $payload = $this->resolver()->resolve($aoe, $section);
        if ($payload === null) {
            return null;
        }

        $id = $aoe->id();
        if (! \is_string($id)) {
            return null;
        }

        return new AreaOfExpertiseSectionDocument(
            aoeId: $id,
            section: $section,
            title: $this->title($aoe, $payload['section_label']),
            url: $this->sectionUrl($aoe, $section),
            site: $this->siteHandle($aoe),
        );
    }

    /**
     * Resolve one `<aoe-id>::<section>` key back to its document (revalidating it).
     */
    private function documentForKey(string $key): ?AreaOfExpertiseSectionDocument
    {
        [$aoeId, $section] = array_pad(explode('::', $key, 2), 2, null);
        if (! \is_string($aoeId) || ! \is_string($section) || $aoeId === '' || $section === '') {
            return null;
        }

        $aoe = EntryFacade::find($aoeId);
        if (! $aoe instanceof Entry) {
            return null;
        }

        return $this->documentFor($aoe, $section);
    }

    /**
     * Whether the AoE participates in interactive search: published and not explicitly flagged
     * out via the `searchable` toggle. Mirrors {@see ExcludeUnsearchableEntries}'s
     * strict-`false` rule — a missing/null toggle stays searchable, so nothing predating the
     * toggle is silently dropped.
     */
    private function isSearchable(Entry $aoe): bool
    {
        return $aoe->status() === 'published' && $aoe->get('searchable') !== false;
    }

    /**
     * The reader-facing + searchable title: `{AoE title} — {section label}` (label alone when
     * the AoE somehow has no title).
     */
    private function title(Entry $aoe, string $sectionLabel): string
    {
        $aoeTitle = $aoe->get('title');
        $aoeTitle = \is_string($aoeTitle) ? trim($aoeTitle) : '';

        return $aoeTitle !== '' ? $aoeTitle.' — '.$sectionLabel : $sectionLabel;
    }

    /**
     * The subpage URL, `/{aoe-uri}/{section}`.
     */
    private function sectionUrl(Entry $aoe, string $section): string
    {
        $url = $aoe->url();
        $url = \is_string($url) ? $url : '';

        return rtrim($url, '/').'/'.$section;
    }

    /**
     * The AoE entry's site handle (only consulted by the tag on multi-site installs).
     */
    private function siteHandle(Entry $aoe): string
    {
        $handle = $aoe->locale();
        if (\is_string($handle) && $handle !== '') {
            return $handle;
        }

        // Fall back to the default site handle (the Site facade is loosely typed).
        /** @var \Statamic\Sites\Site $default */
        $default = Site::default();
        $defaultHandle = $default->handle();

        return \is_string($defaultHandle) ? $defaultHandle : 'default';
    }

    /**
     * The section resolver — the single source of truth for which sections are populated and
     * their labels. Resolved lazily so this provider can be constructed by the search manager
     * without constructor injection.
     */
    private function resolver(): ClinicalExpertiseSectionResolver
    {
        return $this->resolver ??= app(ClinicalExpertiseSectionResolver::class);
    }
}
