<?php

declare(strict_types=1);

namespace App\Services\SchemaTemplates;

use App\Enums\SchemaType;
use App\Support\RequestAttributeMemo;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Contracts\Taxonomies\Term;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry as EntryModel;
use Statamic\Facades\GlobalSet;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Value;
use Statamic\Globals\Variables;
use Statamic\Taxonomies\LocalizedTerm;
use Statamic\Taxonomies\Taxonomy;

/**
 * Computes resolved "enabled" and four schema entry ids (subject_entry,
 * website_entry, webpage_entry, main_content_entry) from site → section →
 * blueprint → entry cascade. Subject and website are site-only; webpage and
 * main_content each have their own inherit flag per level.
 *
 * When present, schema_subject and schema_website from the uams_multisite_settings
 * global set override the subject_entry and website_entry from Schema Templates.
 */
class SchemaTemplatesCascadeService
{
    /**
     * Request attribute bag for multisite global schema overrides.
     */
    private const string MULTISITE_OVERRIDES_MEMO_BAG = 'schema_templates_cascade_multisite_overrides';

    public function __construct(
        protected SchemaSiteDefaultsRepository $siteRepo,
        protected SchemaSectionDefaultsRepository $sectionRepo,
        protected SchemaBlueprintDefaultsRepository $blueprintRepo
    ) {}

    /**
     * Get resolved enabled and four schema entry ids for the current entry
     * or term. Subject and website come from site only; webpage and
     * main_content cascade site → section → blueprint → entry.
     *
     * @return array{enabled: bool, subject_entry: string|null, website_entry: string|null, webpage_entry: string|null, main_content_entry: string|null}
     */
    public function resolve(EntryContract|Term|null $entity): array
    {
        $empty = [
            'enabled' => false,
            'subject_entry' => null,
            'website_entry' => null,
            'webpage_entry' => null,
            'main_content_entry' => null,
        ];

        // Only an entry or a localized term carries the section and blueprint the
        // cascade needs; anything else, null included, resolves nothing.
        if ($entity instanceof EntryModel) {
            $entityLocale = $entity->locale();
            $siteHandle = \is_scalar($entityLocale) ? (string) $entityLocale : '';
        } elseif ($entity instanceof LocalizedTerm) {
            $termLocale = $entity->locale();
            $siteHandle = \is_scalar($termLocale) ? (string) $termLocale : '';
        } else {
            return $empty;
        }

        if ($entity instanceof EntryModel) {
            /** @var Collection $collection */
            $collection = $entity->collection();
            /** @var string $sectionHandle */
            $sectionHandle = $collection->handle();
            $sectionType = 'collection';
            /** @var Blueprint|null $entryBlueprint */
            $entryBlueprint = $entity->blueprint();
            $blueprintHandle = $entryBlueprint?->handle();
            $rawEntryData = $entity->get('schema_templates');
            /** @var array<string, mixed> $entryData */
            $entryData = \is_array($rawEntryData) ? $rawEntryData : [];
        } else {
            /** @var Taxonomy $taxonomy */
            $taxonomy = $entity->taxonomy();
            /** @var string $sectionHandle */
            $sectionHandle = $taxonomy->handle();
            $sectionType = 'taxonomy';
            /** @var Blueprint|null $termBlueprint */
            $termBlueprint = $entity->blueprint();
            $blueprintHandle = $termBlueprint?->handle();
            $rawEntryData = $entity->get('schema_templates');
            /** @var array<string, mixed> $entryData */
            $entryData = \is_array($rawEntryData) ? $rawEntryData : [];
        }

        if ($blueprintHandle === null) {
            return $empty;
        }

        $site = $this->siteRepo->get($siteHandle);
        $section = $this->sectionRepo->get($sectionHandle, $sectionType);
        $blueprint = $this->blueprintRepo->get($sectionHandle, $blueprintHandle, $sectionType);

        $enabled = $this->resolveEnabled($site, $section, $blueprint, $entryData);
        $subjectEntry = $this->resolveSubjectEntry($site);
        $websiteEntry = $this->resolveWebsiteEntry($site);

        // ---
        // Override subject and website with uams_multisite_settings when available
        // ---

        // Get the multisite overrides
        $multisiteOverrides = $this->getMultisiteSchemaOverrides($siteHandle);

        // Check if schema_subject is set in the global set
        if ($multisiteOverrides['schema_subject'] !== null) {
            // Apply schema_subject override when set in global
            $subjectEntry = $multisiteOverrides['schema_subject'];
        }

        // Check if schema_website is set in the global set
        if ($multisiteOverrides['schema_website'] !== null) {
            // Apply schema_website override when set in global
            $websiteEntry = $multisiteOverrides['schema_website'];
        }

        $webpageEntry = $this->resolveWebpageEntry($site, $section, $blueprint, $entryData);

        // Route the main content node by the entity's MedicalOrganization subtype
        // when one is selected (clinical locations); other entities pass null.
        $subtype = $entity instanceof EntryModel ? $this->resolveSubtype($entity) : null;
        $mainContentEntry = $this->resolveMainContentEntry($site, $section, $blueprint, $entryData, $subtype);

        return [
            'enabled' => $enabled,
            'subject_entry' => $subjectEntry,
            'website_entry' => $websiteEntry,
            'webpage_entry' => $webpageEntry,
            'main_content_entry' => $mainContentEntry,
        ];
    }

    /**
     * Resolve final enabled: site (bool) then section/blueprint/entry (true|false|inherit).
     *
     * @param  array<string, mixed>  $site
     * @param  array<string, mixed>  $section
     * @param  array<string, mixed>  $blueprint
     * @param  array<string, mixed>  $entryData
     */
    protected function resolveEnabled(
        array $site,
        array $section,
        array $blueprint,
        array $entryData
    ): bool {
        $siteEnabled = isset($site['enabled']) && $site['enabled'];

        $sectionEnabled = $this->resolveTriState($section['enabled'] ?? 'inherit', $siteEnabled);
        $blueprintEnabled = $this->resolveTriState($blueprint['enabled'] ?? 'inherit', $sectionEnabled);

        return $this->resolveTriState($entryData['enabled'] ?? 'inherit', $blueprintEnabled);
    }

    /**
     * @param  mixed  $value  Raw value from config (string or bool from YAML/form)
     */
    private function resolveTriState(mixed $value, bool $inherited): bool
    {
        if ($value === 'true' || $value === true) {
            return true;
        }

        if ($value === 'false' || $value === false) {
            return false;
        }

        return $inherited;
    }

    /**
     * Normalize entries field value to single entry id or null (max_items 1).
     *
     * @param  mixed  $value  Raw value (id string or array of one id)
     */
    protected function singleEntryId(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (\is_array($value)) {
            $first = reset($value);

            return \is_scalar($first) && $first !== false && $first !== '' ? (string) $first : null;
        }

        return \is_scalar($value) ? (string) $value : null;
    }

    /**
     * Get schema_subject and schema_website from uams_multisite_settings global set
     * for the given site when available.
     *
     * @param  string  $siteHandle  The site handle for which to get the schema overrides
     * @return array{schema_subject: string|null, schema_website: string|null} The schema_subject and schema_website from the uams_multisite_settings global
     *                                                                         set for the given site, or null if not found
     */
    protected function getMultisiteSchemaOverrides(
        string $siteHandle
    ): array {
        /** @var array{schema_subject: string|null, schema_website: string|null} $overrides */
        $overrides = RequestAttributeMemo::remember(
            self::MULTISITE_OVERRIDES_MEMO_BAG,
            $siteHandle,
            function () use ($siteHandle): array {
                // Get the global set by the handle
                $globalSet = GlobalSet::findByHandle('uams_multisite_settings');

                // Check if the global set is not found
                if ($globalSet === null) {
                    // Return null for both schema_subject and schema_website
                    return [
                        'schema_subject' => null,
                        'schema_website' => null,
                    ];
                }

                // Get the localized version of the global set for the given site
                /** @var Variables|null $localized */
                $localized = $globalSet->in($siteHandle);

                // Check if the localized version is not found
                if ($localized === null) {
                    // Return null for both schema_subject and schema_website
                    return [
                        'schema_subject' => null,
                        'schema_website' => null,
                    ];
                }

                // Get the schema_subject from the localized version
                $schemaSubject = $this->singleEntryId($localized->get('schema_subject'));

                // Get the schema_website from the localized version
                $schemaWebsite = $this->singleEntryId($localized->get('schema_website'));

                // Return the schema_subject and schema_website
                return [
                    'schema_subject' => $schemaSubject,
                    'schema_website' => $schemaWebsite,
                ];
            }
        );

        return $overrides;
    }

    /**
     * Subject entry (site only).
     *
     * @param  array<string, mixed>  $site
     */
    protected function resolveSubjectEntry(array $site): ?string
    {
        return $this->singleEntryId($site['subject_entry'] ?? null);
    }

    /**
     * Website entry (site only).
     *
     * @param  array<string, mixed>  $site
     */
    protected function resolveWebsiteEntry(array $site): ?string
    {
        return $this->singleEntryId($site['website_entry'] ?? null);
    }

    /**
     * Webpage entry cascade: site → section → blueprint → entry, where the
     * most-specific non-inheriting level wins (entry over blueprint over
     * section over the site default), keyed by inherit_webpage_entry at each
     * level. The checks run deepest-first so the first override encountered is
     * the most specific one.
     *
     * @param  array<string, mixed>  $site
     * @param  array<string, mixed>  $section
     * @param  array<string, mixed>  $blueprint
     * @param  array<string, mixed>  $entryData
     */
    protected function resolveWebpageEntry(
        array $site,
        array $section,
        array $blueprint,
        array $entryData
    ): ?string {
        if ($this->useCustomValue($entryData['inherit_webpage_entry'] ?? true)) {
            return $this->singleEntryId($entryData['webpage_entry'] ?? null);
        }

        if ($this->useCustomValue($blueprint['inherit_webpage_entry'] ?? true)) {
            return $this->singleEntryId($blueprint['webpage_entry'] ?? null);
        }

        if ($this->useCustomValue($section['inherit_webpage_entry'] ?? true)) {
            return $this->singleEntryId($section['webpage_entry'] ?? null);
        }

        return $this->singleEntryId($site['webpage_entry'] ?? null);
    }

    /**
     * Main content entry cascade: site → section → blueprint → entry, where the
     * most-specific non-inheriting level wins (entry over blueprint over
     * section over the site default), keyed by inherit_main_content_entry at
     * each level. The checks run deepest-first so the first override
     * encountered is the most specific one.
     *
     * When the winning level carries a `main_content_entry_by_subtype` map and
     * the entity has a matching subtype, the mapped node is routed instead of
     * the flat `main_content_entry` — so a `clinical_locations` entry emits from
     * its own per-subtype schema node (issue UAMS-Web/uams-statamic-archive#1444).
     *
     * @param  array<string, mixed>  $site
     * @param  array<string, mixed>  $section
     * @param  array<string, mixed>  $blueprint
     * @param  array<string, mixed>  $entryData
     * @param  ?string  $subtype  The entity's MedicalOrganization subtype, or null
     */
    protected function resolveMainContentEntry(
        array $site,
        array $section,
        array $blueprint,
        array $entryData,
        ?string $subtype = null
    ): ?string {
        if ($this->useCustomValue($entryData['inherit_main_content_entry'] ?? true)) {
            return $this->mainContentEntryForLevel($entryData, $subtype);
        }

        if ($this->useCustomValue($blueprint['inherit_main_content_entry'] ?? true)) {
            return $this->mainContentEntryForLevel($blueprint, $subtype);
        }

        if ($this->useCustomValue($section['inherit_main_content_entry'] ?? true)) {
            return $this->mainContentEntryForLevel($section, $subtype);
        }

        return $this->mainContentEntryForLevel($site, $subtype);
    }

    /**
     * Resolve a single level's main content node id, preferring a subtype-routed
     * node when the level carries a `main_content_entry_by_subtype` map and the
     * entity's subtype has a resolvable entry in it. A level with no map (every
     * collection but clinical locations today) behaves exactly as before, so the
     * routing is generic and opt-in.
     *
     * @param  array<string, mixed>  $level
     * @param  ?string  $subtype  The entity's MedicalOrganization subtype, or null
     */
    private function mainContentEntryForLevel(array $level, ?string $subtype): ?string
    {
        if ($subtype !== null) {
            $bySubtype = $level['main_content_entry_by_subtype'] ?? null;

            if (\is_array($bySubtype)) {
                $mapped = $this->subtypeNodeId($bySubtype, $subtype);

                if ($mapped !== null) {
                    return $mapped;
                }
            }
        }

        return $this->singleEntryId($level['main_content_entry'] ?? null);
    }

    /**
     * Resolve a subtype to its mapped node id: an exact match, else the node
     * mapped to the subtype's nearest mapped ancestor. A `schema_type` single
     * field also offers descendants of its `starting_types`, so a selectable
     * descendant (e.g. `CovidTestingFacility`, a `MedicalClinic`) still routes to
     * its family node rather than losing its subtype-specific properties on the
     * shared fallback. The mapped keys are disjoint MedicalOrganization subtypes,
     * so at most one ancestor is ever in the map.
     *
     * @param  array<array-key, mixed>  $bySubtype
     */
    private function subtypeNodeId(array $bySubtype, string $subtype): ?string
    {
        if (\array_key_exists($subtype, $bySubtype)) {
            $mapped = $this->singleEntryId($bySubtype[$subtype]);

            if ($mapped !== null) {
                return $mapped;
            }
        }

        foreach (SchemaType::ancestorsOf($subtype) as $ancestor) {
            if (\array_key_exists($ancestor, $bySubtype)) {
                return $this->singleEntryId($bySubtype[$ancestor]);
            }
        }

        return null;
    }

    /**
     * Read the entity's editor-selected `schema_medicalorganization_subtype`,
     * used to route the main content node to a per-subtype schema node. Returns
     * null for a non-entry entity, an unset field, or an empty selection.
     */
    private function resolveSubtype(EntryModel $entity): ?string
    {
        $raw = $entity->get('schema_medicalorganization_subtype');

        if ($raw instanceof Value) {
            $raw = $raw->value();
        }

        if (! \is_string($raw)) {
            return null;
        }

        $raw = trim($raw);

        return $raw === '' ? null : $raw;
    }

    /**
     * Whether to use custom value at this level (not inherit).
     *
     * @param  mixed  $inherit  Raw value from config (true = inherit)
     */
    private function useCustomValue(mixed $inherit): bool
    {
        return $inherit === false || $inherit === 'false';
    }
}
