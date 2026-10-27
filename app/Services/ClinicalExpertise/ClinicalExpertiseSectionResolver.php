<?php

declare(strict_types=1);

namespace App\Services\ClinicalExpertise;

use App\Http\Controllers\ClinicalExpertiseSectionController;
use App\Support\Navigation\SubsectionResolver;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Fields\Blueprint;
use Statamic\Stache\Query\EntryQueryBuilder;

/**
 * Resolves the per-Area-of-Expertise "virtual subpage" sections (UAMS-Web/uams-statamic-archive#1058).
 *
 * Each `clinical_expertise` entry exposes five relationship-derived sections at
 * their own routed `/{entry}/{section}` URL (served by
 * {@see ClinicalExpertiseSectionController}). This service
 * is the single source of truth for:
 *
 * - the section token enum (also the route's `{section}` constraint),
 * - which source field / structure axis each token renders from, and
 * - the 404 gate: a section with no source items does not render.
 *
 * The section → source map is derived from the live `clinical_expertise`
 * blueprint (post-#1380 renormalization): the relationship handles are
 * `clinical_providers`, `clinical_locations`, `clinical_expertise_related`, and
 * `clinical_resources`; the `specialties` section is the entry's structure
 * children on the same `clinical_expertise` blueprint. The per-section override
 * fields follow the `<prefix>_fpage_{intro,excerpt_query,excerpt,featured_image}`
 * convention, with `specialties` using the `clinical_expertise_descendant`
 * prefix.
 *
 * @phpstan-type SectionConfig array{source: string, override_prefix: string, label: string}
 * @phpstan-type SectionPayload array{
 *     section: string,
 *     section_label: string,
 *     source: string,
 *     override_prefix: string,
 *     is_children: bool,
 *     load: string,
 *     section_intro: mixed,
 *     section_excerpt: mixed,
 *     section_excerpt_query: mixed,
 *     section_featured_image: mixed
 * }
 */
class ClinicalExpertiseSectionResolver
{
    /**
     * Blueprint handle an entry must carry to expose routed sections.
     */
    public const BLUEPRINT = 'clinical_expertise';

    /**
     * Sentinel `source` marking the structure-children section (`specialties`),
     * which reads descendant entries rather than a relationship field.
     */
    private const string CHILDREN_SOURCE = '@children';

    /**
     * Section token → source + override-field prefix, from the live blueprint.
     *
     * `source` is a relationship field handle, or {@see self::CHILDREN_SOURCE}
     * for the structure-children section. `override_prefix` names the
     * `<prefix>_fpage_*` override fields for that section.
     *
     * @var array<string, SectionConfig>
     */
    private const array SECTIONS = [
        'providers' => ['source' => 'clinical_providers', 'override_prefix' => 'clinical_providers', 'label' => 'Related Clinical Providers'],
        'locations' => ['source' => 'clinical_locations', 'override_prefix' => 'clinical_locations', 'label' => 'Related Clinical Locations'],
        'specialties' => ['source' => self::CHILDREN_SOURCE, 'override_prefix' => 'clinical_expertise_descendant', 'label' => 'Sub-specialties'],
        'related' => ['source' => 'clinical_expertise_related', 'override_prefix' => 'clinical_expertise_related', 'label' => 'Related Areas of Expertise'],
        'resources' => ['source' => 'clinical_resources', 'override_prefix' => 'clinical_resources', 'label' => 'Related Clinical Resources'],
    ];

    /**
     * The routed section tokens, in display order.
     *
     * @return list<string>
     */
    public static function tokens(): array
    {
        return array_keys(self::SECTIONS);
    }

    /**
     * Regex alternation for the route's `{section}` constraint, single-sourced
     * from {@see self::SECTIONS} so the route and the resolver never drift.
     */
    public static function routeConstraint(): string
    {
        return implode('|', self::tokens());
    }

    /**
     * Whether a token is one of the known routed sections.
     */
    public function isKnownSection(string $section): bool
    {
        return array_key_exists($section, self::SECTIONS);
    }

    /**
     * The section tokens that are populated on the entry, in display order.
     *
     * Empty when the entry is not an Area of Expertise. Drives the AoE primary-nav
     * section links (UAMS-Web/uams-statamic-archive#1057): one link per populated section, so an empty section
     * gets no nav entry (mirroring the route's 404 gate).
     *
     * @return list<string>
     */
    public function populatedSections(Entry $entry): array
    {
        if (! $this->isAreaOfExpertise($entry)) {
            return [];
        }

        $populated = [];
        foreach (self::SECTIONS as $token => $config) {
            if ($this->isPopulated($entry, $config)) {
                $populated[] = $token;
            }
        }

        return $populated;
    }

    /**
     * Resolve the render payload for an entry's section, or null when the section
     * must 404: the entry is not a `clinical_expertise` entry, the token is not a
     * known section, or the section has no source items to show.
     *
     * The returned `load` is the entry id so the front-end cascade augments the
     * AoE entry; the `section_*` values are the raw per-section overrides (the
     * template applies the Find-a-Doc Settings fallback when they are blank).
     *
     * @return SectionPayload|null
     */
    public function resolve(Entry $entry, string $section): ?array
    {
        // Only clinical_expertise entries expose routed sections.
        if (! $this->isAreaOfExpertise($entry)) {
            return null;
        }

        // Unknown tokens 404 (the route constraint already blocks most, but a
        // direct call must not fabricate a section).
        $config = self::SECTIONS[$section] ?? null;
        if ($config === null) {
            return null;
        }

        // An empty section 404s rather than rendering a blank page.
        if (! $this->isPopulated($entry, $config)) {
            return null;
        }

        $prefix = $config['override_prefix'];
        $id = $entry->id();

        return [
            'section' => $section,
            'section_label' => $config['label'],
            'source' => $config['source'],
            'override_prefix' => $prefix,
            'is_children' => $config['source'] === self::CHILDREN_SOURCE,
            'load' => is_string($id) ? $id : '',
            'section_intro' => $entry->value($prefix.'_fpage_intro'),
            'section_excerpt' => $entry->value($prefix.'_fpage_excerpt'),
            'section_excerpt_query' => $entry->value($prefix.'_fpage_excerpt_query'),
            'section_featured_image' => $entry->value($prefix.'_fpage_featured_image'),
        ];
    }

    /**
     * Whether the entry is on the `clinical_expertise` blueprint.
     */
    private function isAreaOfExpertise(Entry $entry): bool
    {
        // Entry::blueprint() is typed mixed in the stubs; narrow it as KB does.
        /** @var Blueprint $blueprint */
        $blueprint = $entry->blueprint();

        return $blueprint->handle() === self::BLUEPRINT;
    }

    /**
     * Whether the section has at least one source item, so it should render.
     *
     * Relationship sections are populated when their field holds any value;
     * the structure-children section is populated when the entry has at least
     * one published `clinical_expertise` child.
     *
     * @param  SectionConfig  $config
     */
    private function isPopulated(Entry $entry, array $config): bool
    {
        if ($config['source'] === self::CHILDREN_SOURCE) {
            return $this->hasPublishedExpertiseChildren($entry);
        }

        return $this->relationshipHasItems($entry->value($config['source']));
    }

    /**
     * Whether a relationship field's raw value holds at least one related item.
     */
    private function relationshipHasItems(mixed $value): bool
    {
        if (is_array($value)) {
            return $value !== [];
        }

        // A single-item relationship (or scalar id) counts as populated.
        return $value !== null && $value !== '';
    }

    /**
     * Whether the entry has at least one published `clinical_expertise` child in
     * the collection structure. Isolated behind a protected seam so unit tests
     * can drive the `specialties` branch without a live structure tree (mirrors
     * {@see SubsectionResolver::ancestorIds()}).
     */
    protected function hasPublishedExpertiseChildren(Entry $entry): bool
    {
        $id = $entry->id();
        if (! is_string($id)) {
            return false;
        }

        // Mirror KnowledgeBaseComputedFieldsService: the Entry facade query()
        // returns the Stache EntryQueryBuilder, annotated so the fluent chain
        // type-checks.
        /** @var EntryQueryBuilder $query */
        $query = EntryFacade::query();
        $query->where('collection', 'clinical_expertise');
        $query->where('parent', $id);
        $query->where('blueprint', self::BLUEPRINT);
        $query->whereStatus('published');

        return $query->count() > 0;
    }
}
