<?php

declare(strict_types=1);

namespace App\Services\SchemaTemplates;

use App\Support\Collections\PeopleCollection;
use Statamic\Contracts\Globals\Variables as VariablesContract;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;
use Statamic\Facades\Term as TermFacade;
use Statamic\Globals\Variables;
use Statamic\Sites\Site as StatamicSite;
use Statamic\Stache\Query\TermQueryBuilder;
use Statamic\Taxonomies\LocalizedTerm;
use Statamic\Taxonomies\Term as TaxonomyTerm;

/**
 * Builds front-end URLs and audit ids for warming JSON-LD output cache across sites.
 *
 * Config uses dotted global references (`global_set.field`) grouped by entry collection,
 * plus taxonomy traversal rules from globals → terms → entry fields.
 */
final class SchemaJsonLdWarmUrlCollector
{
    /**
     * Resolved globals variables per global set handle for one collectForSite run.
     *
     * @var array<string, VariablesContract|null>
     */
    private array $variablesByGlobalSetHandle = [];

    /**
     * Site handle whose localized globals row was used per global set (after fallback).
     *
     * @var array<string, string|null>
     */
    private array $localizationSiteByGlobalSet = [];

    /**
     * @param  array<string, mixed>|null  $warmConfigSlice  Warm section of `schema_templates`; null uses Laravel config at collect time (tests override config()).
     */
    public function __construct(
        private readonly ?array $warmConfigSlice = null
    ) {}

    /**
     * Collect urls and schema audit ids for one Statamic site.
     *
     * @return array{
     *     urls: list<string>,
     *     people_entry_ids: list<string>,
     *     schema_object_entry_ids: list<string>,
     *     warnings: list<string>,
     *     globals_site_handle_used: string|null,
     *     homepage_uri: string|null,
     *     globals_localization_site_by_global_set: array<string, string|null>
     * }
     */
    public function collectForSite(
        StatamicSite $site
    ): array {
        $this->variablesByGlobalSetHandle = [];
        $this->localizationSiteByGlobalSet = [];

        /** @var string $siteHandle */
        $siteHandle = $site->handle();

        $warm = $this->warmSlice();
        $warnings = [];

        /** @var list<string> $urlsOrdered */
        $urlsOrdered = [];
        /** @var array<string, true> $urlSeen */
        $urlSeen = [];
        $peopleEntryIds = [];
        /** @var array<string, true> $peopleSeen */
        $peopleSeen = [];
        $schemaObjectIds = [];
        /** @var array<string, true> $schemaSeen */
        $schemaSeen = [];

        $homepageUri = null;

        $pagesHandle = isset($warm['homepage_collection_handle']) && \is_string($warm['homepage_collection_handle'])
            && $warm['homepage_collection_handle'] !== ''
            ? $warm['homepage_collection_handle']
            : 'pages';

        // homepageUriCandidatesList() turns an empty list into ['/'] as well, so this
        // default is one of two equivalent values
        // @pest-mutate-ignore: RemoveArrayItem
        $uriCandidates = isset($warm['homepage_uri_candidates']) && \is_array($warm['homepage_uri_candidates'])
            ? $warm['homepage_uri_candidates']
            : ['/'];

        foreach ($this->homepageUriCandidatesList($uriCandidates) as $uri) {
            $home = EntryFacade::findByUri(
                $uri,
                $siteHandle
            );

            if ($home instanceof StatamicEntry) {
                if ($home->published() !== true) {
                    $warnings[] = \sprintf(
                        'Home candidate uri %s resolves to an unpublished entry for site %s.',
                        \json_encode($uri, JSON_THROW_ON_ERROR),
                        $siteHandle
                    );

                    continue;
                }

                /** @var Collection|null $homeCollection */
                $homeCollection = $home->collection();
                $collectionHandle = $homeCollection?->handle();

                if (
                    \is_string($collectionHandle)
                    && $collectionHandle === $pagesHandle
                ) {
                    $homeUrl = $home->absoluteUrl();

                    if (
                        \is_string($homeUrl)
                        && $homeUrl !== ''
                    ) {
                        $this->pushUniqueUrl(
                            $homeUrl,
                            $urlsOrdered,
                            $urlSeen
                        );
                        $homepageUri = $uri;

                        break;
                    }

                    $warnings[] = \sprintf(
                        'Home entry for site %s (uri %s) has no absoluteUrl.',
                        $siteHandle,
                        \json_encode($uri, JSON_THROW_ON_ERROR)
                    );
                }
            }
        }

        if ($homepageUri === null) {
            $warnings[] = \sprintf(
                'No published homepage found for site %s using collection %s and uri candidates %s.',
                $siteHandle,
                $pagesHandle,
                \json_encode(
                    $this->homepageUriCandidatesList($uriCandidates),
                    JSON_THROW_ON_ERROR
                )
            );
        }

        $entryByCollection = $warm['global_entry_fields_by_collection'] ?? null;
        $taxonomyRules = $warm['global_taxonomy_entry_traversals'] ?? null;

        $hasEntryConfig = \is_array($entryByCollection) && $entryByCollection !== [];
        $hasTaxonomyConfig = \is_array($taxonomyRules) && $taxonomyRules !== [];

        // Without this return, the empty configs skip both loops and pack the same result
        // @pest-mutate-ignore: RemoveEarlyReturn
        if (! $hasEntryConfig && ! $hasTaxonomyConfig) {
            return $this->packResult(
                $urlsOrdered,
                $peopleEntryIds,
                $schemaObjectIds,
                $warnings,
                null,
                $homepageUri,
                []
            );
        }

        if ($hasEntryConfig) {
            foreach ($entryByCollection as $collectionHandle => $refs) {
                if (! \is_string($collectionHandle)) {
                    continue;
                }

                if ($collectionHandle === '') {
                    continue;
                }

                if (! \is_array($refs)) {
                    continue;
                }

                foreach ($this->sanitizeStringList($refs) as $dottedRef) {
                    $parsed = $this->parseGlobalFieldReference($dottedRef);

                    if ($parsed === null) {
                        $warnings[] = \sprintf(
                            'Invalid global field reference %s (expected global_set_handle.field_handle).',
                            \json_encode($dottedRef, JSON_THROW_ON_ERROR)
                        );

                        continue;
                    }

                    $variables = $this->resolveVariablesForGlobalSet(
                        $parsed['global_set'],
                        $site,
                        $warnings
                    );

                    if (! $variables instanceof VariablesContract) {
                        continue;
                    }

                    /** @var Variables $variables */
                    $raw = $variables->get($parsed['field']);

                    if ($collectionHandle === 'schema_objects') {
                        $id = $this->singleEntryId($raw);

                        if ($id !== null) {
                            $this->pushUniqueId(
                                $id,
                                $schemaObjectIds,
                                $schemaSeen
                            );
                        }
                    } else {
                        foreach ($this->entryIdsFromEntriesField($raw) as $entryId) {
                            if ($collectionHandle === PeopleCollection::handle()) {
                                $this->pushUniqueId(
                                    $entryId,
                                    $peopleEntryIds,
                                    $peopleSeen
                                );
                            }

                            $this->appendPublishedEntryWarmUrl(
                                $entryId,
                                $urlsOrdered,
                                $urlSeen,
                                $warnings,
                                \sprintf(
                                    'global %s',
                                    $dottedRef
                                )
                            );
                        }
                    }
                }
            }
        }

        if ($hasTaxonomyConfig) {
            foreach ($taxonomyRules as $rule) {
                if (! \is_array($rule)) {
                    continue;
                }

                /** @var array<string, mixed> $rule */
                $this->collectFromTaxonomyTraversalRule(
                    $rule,
                    $site,
                    $schemaObjectIds,
                    $schemaSeen,
                    $peopleEntryIds,
                    $peopleSeen,
                    $urlsOrdered,
                    $urlSeen,
                    $warnings
                );
            }
        }

        $globalsSiteUsed = $this->primaryGlobalsLocalizationSite();

        return $this->packResult(
            $urlsOrdered,
            $peopleEntryIds,
            $schemaObjectIds,
            $warnings,
            $globalsSiteUsed,
            $homepageUri,
            $this->localizationSiteByGlobalSet
        );
    }

    /**
     * @param  list<string>  $urlsOrdered
     * @param  list<string>  $peopleEntryIds
     * @param  list<string>  $schemaObjectIds
     * @param  list<string>  $warnings
     * @param  array<string, string|null>  $globalsLocalizationBySet
     * @return array{
     *     urls: list<string>,
     *     people_entry_ids: list<string>,
     *     schema_object_entry_ids: list<string>,
     *     warnings: list<string>,
     *     globals_site_handle_used: string|null,
     *     homepage_uri: string|null,
     *     globals_localization_site_by_global_set: array<string, string|null>
     * }
     */
    private function packResult(
        array $urlsOrdered,
        array $peopleEntryIds,
        array $schemaObjectIds,
        array $warnings,
        ?string $globalsSiteUsed,
        ?string $homepageUri,
        array $globalsLocalizationBySet
    ): array {
        return [
            'urls' => $urlsOrdered,
            'people_entry_ids' => $peopleEntryIds,
            'schema_object_entry_ids' => $schemaObjectIds,
            'warnings' => $warnings,
            'globals_site_handle_used' => $globalsSiteUsed,
            'homepage_uri' => $homepageUri,
            'globals_localization_site_by_global_set' => $globalsLocalizationBySet,
        ];
    }

    /**
     * First non-null localization site among resolved global sets (stable enough for CP summary).
     */
    private function primaryGlobalsLocalizationSite(): ?string
    {
        foreach ($this->localizationSiteByGlobalSet as $siteHandle) {
            if (
                \is_string($siteHandle)
                && $siteHandle !== ''
            ) {
                return $siteHandle;
            }
        }

        return null;
    }

    /**
     * @return array{global_set: string, field: string}|null
     */
    private function parseGlobalFieldReference(
        string $reference
    ): ?array {
        // Without a dot the field below is empty, which returns null too
        // @pest-mutate-ignore: RemoveEarlyReturn
        if (! str_contains($reference, '.')) {
            return null;
        }

        $parts = explode('.', $reference, 2);
        $globalSet = $parts[0];
        // A reference with a dot always explodes to two parts, so the default is never read
        // @pest-mutate-ignore: EmptyStringToNotEmpty
        $field = $parts[1] ?? '';

        if (
            $globalSet === ''
            || $field === ''
        ) {
            return null;
        }

        return [
            'global_set' => $globalSet,
            'field' => $field,
        ];
    }

    /**
     * @param  list<string>  $warnings
     */
    private function resolveVariablesForGlobalSet(
        string $globalSetHandle,
        StatamicSite $site,
        array &$warnings
    ): ?VariablesContract {
        if (\array_key_exists($globalSetHandle, $this->variablesByGlobalSetHandle)) {
            return $this->variablesByGlobalSetHandle[$globalSetHandle];
        }

        $globalSet = GlobalSet::findByHandle($globalSetHandle);

        if ($globalSet === null) {
            $warnings[] = \sprintf(
                'Global set %s was not found.',
                $globalSetHandle
            );
            $this->variablesByGlobalSetHandle[$globalSetHandle] = null;
            $this->localizationSiteByGlobalSet[$globalSetHandle] = null;

            return null;
        }

        $warm = $this->warmSlice();
        $fallbackHandle = $warm['global_fallback_site_handle'] ?? $warm['multisite_global_fallback_site_handle'] ?? null;
        $defaultSite = Site::default();
        /** @var string|null $defaultSiteHandle */
        // Site::default() always returns a site, so the guard always holds
        // @pest-mutate-ignore: InstanceOfToTrue
        $defaultSiteHandle = $defaultSite instanceof StatamicSite ? $defaultSite->handle() : null;
        $fallbackHandle = \is_string($fallbackHandle) && $fallbackHandle !== ''
            ? $fallbackHandle
            : $defaultSiteHandle;

        /** @var string $siteHandle */
        $siteHandle = $site->handle();

        $variables = $globalSet->in($siteHandle);
        $usedSite = $siteHandle;

        if ($variables instanceof VariablesContract) {
            $this->localizationSiteByGlobalSet[$globalSetHandle] = $usedSite;
        } elseif (\is_string($fallbackHandle)) {
            $fallbackVariables = $globalSet->in($fallbackHandle);

            if ($fallbackVariables instanceof VariablesContract) {
                $variables = $fallbackVariables;
                $usedSite = $fallbackHandle;
                $this->localizationSiteByGlobalSet[$globalSetHandle] = $usedSite;
                $warnings[] = \sprintf(
                    'Site %s has no localized row for global set %s; used fallback site %s.',
                    $siteHandle,
                    $globalSetHandle,
                    $fallbackHandle
                );
            }
        }

        // Without the return, the code below caches and returns the same null
        // @pest-mutate-ignore: RemoveEarlyReturn
        if (! $variables instanceof VariablesContract) {
            $warnings[] = \sprintf(
                'Global set %s: no localized variables for site %s (fallback %s also missing).',
                $globalSetHandle,
                $siteHandle,
                $fallbackHandle
            );
            $this->variablesByGlobalSetHandle[$globalSetHandle] = null;
            $this->localizationSiteByGlobalSet[$globalSetHandle] = null;

            return null;
        }

        $this->variablesByGlobalSetHandle[$globalSetHandle] = $variables;

        return $variables;
    }

    /**
     * @param  array<string, mixed>  $rule
     * @param  list<string>  $schemaObjectIds
     * @param  array<string, true>  $schemaSeen
     * @param  list<string>  $peopleEntryIds
     * @param  array<string, true>  $peopleSeen
     * @param  list<string>  $urlsOrdered
     * @param  array<string, true>  $urlSeen
     * @param  list<string>  $warnings
     */
    private function collectFromTaxonomyTraversalRule(
        array $rule,
        StatamicSite $site,
        array &$schemaObjectIds,
        array &$schemaSeen,
        array &$peopleEntryIds,
        array &$peopleSeen,
        array &$urlsOrdered,
        array &$urlSeen,
        array &$warnings
    ): void {
        $handles = $rule['global_set_field_handles'] ?? null;
        $taxonomyHandle = isset($rule['taxonomy_handle']) && \is_string($rule['taxonomy_handle'])
            ? $rule['taxonomy_handle']
            : '';
        $termEntriesField = isset($rule['term_entries_field']) && \is_string($rule['term_entries_field'])
            ? $rule['term_entries_field']
            : '';

        if (
            ! \is_array($handles)
            || $taxonomyHandle === ''
            || $termEntriesField === ''
        ) {
            $warnings[] = 'A global_taxonomy_entry_traversals rule is incomplete; expected global_set_field_handles, taxonomy_handle, and term_entries_field.';

            return;
        }

        $configuredCollection = $rule['term_entries_field_collection_handle'] ?? null;
        $configuredCollection = \is_string($configuredCollection) && $configuredCollection !== ''
            ? $configuredCollection
            : null;

        foreach ($this->sanitizeStringList($handles) as $dottedRef) {
            $parsed = $this->parseGlobalFieldReference($dottedRef);

            if ($parsed === null) {
                $warnings[] = \sprintf(
                    'Invalid global field reference %s in taxonomy traversal rule.',
                    \json_encode($dottedRef, JSON_THROW_ON_ERROR)
                );

                continue;
            }

            $variables = $this->resolveVariablesForGlobalSet(
                $parsed['global_set'],
                $site,
                $warnings
            );

            if (! $variables instanceof VariablesContract) {
                continue;
            }

            /** @var Variables $variables */
            $rawTerms = $variables->get($parsed['field']);
            $termRefs = [];

            if (\is_array($rawTerms)) {
                foreach ($rawTerms as $t) {
                    if (
                        \is_string($t)
                        && $t !== ''
                    ) {
                        $termRefs[] = $t;
                    }
                }
            } elseif (
                \is_string($rawTerms)
                && $rawTerms !== ''
            ) {
                $termRefs[] = $rawTerms;
            }

            foreach ($termRefs as $ref) {
                $term = $this->resolveTermInTaxonomy(
                    $ref,
                    $taxonomyHandle
                );

                if (
                    ! $term instanceof LocalizedTerm
                    && ! $term instanceof TaxonomyTerm
                ) {
                    $warnings[] = \sprintf(
                        'Could not resolve term %s for taxonomy %s (%s).',
                        \json_encode($ref, JSON_THROW_ON_ERROR),
                        $taxonomyHandle,
                        $dottedRef
                    );

                    continue;
                }

                $rawTermId = $term->id();
                // The id is only formatted into warnings with %s, which prints a number the
                // same with or without the cast
                // @pest-mutate-ignore: RemoveStringCast
                $termId = \is_scalar($rawTermId)
                    ? (string) $rawTermId
                    : '';

                // A resolved term exposes get() via its default-locale (LocalizedTerm) API
                /** @var LocalizedTerm $term */
                $termEntriesValue = $term->get($termEntriesField);

                foreach ($this->entryIdsFromEntriesField($termEntriesValue) as $entryId) {
                    $effectiveCollection = $configuredCollection ?? $this->inferEntryCollectionHandle($entryId);

                    if ($effectiveCollection === '') {
                        $warnings[] = \sprintf(
                            'Could not determine entry collection for id %s (term %s); set term_entries_field_collection_handle on the rule.',
                            $entryId,
                            $termId
                        );

                        continue;
                    }

                    if ($effectiveCollection === 'schema_objects') {
                        $this->pushUniqueId(
                            $entryId,
                            $schemaObjectIds,
                            $schemaSeen
                        );
                    } else {
                        if ($effectiveCollection === PeopleCollection::handle()) {
                            $this->pushUniqueId(
                                $entryId,
                                $peopleEntryIds,
                                $peopleSeen
                            );
                        }

                        $this->appendPublishedEntryWarmUrl(
                            $entryId,
                            $urlsOrdered,
                            $urlSeen,
                            $warnings,
                            \sprintf(
                                'taxonomy %s term %s field %s',
                                $taxonomyHandle,
                                $termId,
                                $termEntriesField
                            )
                        );
                    }
                }
            }
        }
    }

    private function inferEntryCollectionHandle(
        string $entryId
    ): string {
        $entry = EntryFacade::find($entryId);

        if (! $entry instanceof StatamicEntry) {
            return '';
        }

        /** @var Collection|null $collection */
        $collection = $entry->collection();
        $h = $collection?->handle();

        return \is_string($h) ? $h : '';
    }

    /**
     * Find a term by its `taxonomy::slug` id, or by slug within the taxonomy.
     *
     * Returns what the facade finds, unnarrowed: the caller admits only a term.
     */
    private function resolveTermInTaxonomy(
        string $raw,
        string $taxonomyHandle
    ): mixed {
        if (str_contains($raw, '::')) {
            return TermFacade::find($raw);
        }

        /** @var TermQueryBuilder $termQuery */
        $termQuery = TermFacade::query();
        $termQuery->where(
            'taxonomy',
            $taxonomyHandle
        );
        $termQuery->where(
            'slug',
            $raw
        );

        return $termQuery->first();
    }

    /**
     * @param  list<string>  $urlsOrdered
     * @param  array<string, true>  $urlSeen
     * @param  list<string>  $warnings
     */
    private function appendPublishedEntryWarmUrl(
        string $entryId,
        array &$urlsOrdered,
        array &$urlSeen,
        array &$warnings,
        string $contextLabel
    ): void {
        $entry = EntryFacade::find($entryId);

        if (! $entry instanceof StatamicEntry) {
            $warnings[] = \sprintf(
                'Entry %s (%s) was not found.',
                $entryId,
                $contextLabel
            );

            return;
        }

        if ($entry->published() !== true) {
            $warnings[] = \sprintf(
                'Entry %s (%s) is not published; skipping URL.',
                $entryId,
                $contextLabel
            );

            return;
        }

        $url = $entry->absoluteUrl();

        if (
            ! \is_string($url)
            || $url === ''
        ) {
            $warnings[] = \sprintf(
                'Entry %s (%s) has no absoluteUrl.',
                $entryId,
                $contextLabel
            );

            return;
        }

        $this->pushUniqueUrl(
            $url,
            $urlsOrdered,
            $urlSeen
        );
    }

    private function singleEntryId(
        mixed $value
    ): ?string {
        if (
            $value === null
            || $value === ''
        ) {
            return null;
        }

        if (\is_array($value)) {
            $first = reset($value);

            return ! in_array($first, [false, ''], true)
                && \is_scalar($first)
                ? (string) $first
                : null;
        }

        return \is_scalar($value)
            ? (string) $value
            : null;
    }

    /**
     * @return list<string>
     */
    private function entryIdsFromEntriesField(
        mixed $value
    ): array {
        $out = [];

        if (
            $value === null
            || $value === ''
        ) {
            return $out;
        }

        // Without this branch's return, an array falls through the string check below and
        // returns the same list
        // @pest-mutate-ignore: RemoveEarlyReturn
        if (\is_array($value)) {
            foreach ($value as $id) {
                if (
                    \is_string($id)
                    && $id !== ''
                ) {
                    $out[] = $id;
                }
            }

            return $out;
        }

        if (\is_string($value)) {
            $out[] = $value;
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function warmSlice(): array
    {
        $slice = $this->warmConfigSlice;

        if ($slice !== null) {
            return $slice;
        }

        $fromConfig = config(
            'schema_templates.json_ld_cache.warm',
            []
        );

        if (! \is_array($fromConfig)) {
            return [];
        }

        /** @var array<string, mixed> $fromConfig */
        return $fromConfig;
    }

    /**
     * Homepage URI candidates preserve an empty-string root URI when configured.
     *
     * @param  list<mixed>|array<int|string, mixed>  $candidates  Raw values from warm config (strings only preserved)
     * @return list<string>
     */
    private function homepageUriCandidatesList(
        array $candidates
    ): array {
        $out = [];

        foreach ($candidates as $candidate) {
            if (\is_string($candidate)) {
                $out[] = $candidate;
            }
        }

        return $out === []
            ? ['/']
            : $out;
    }

    /**
     * @param  array<mixed, mixed>  $source
     * @return list<string>
     */
    private function sanitizeStringList(
        array $source
    ): array {
        $out = [];

        foreach ($source as $item) {
            if (\is_string($item) && $item !== '') {
                $out[] = $item;
            }
        }

        return $out;
    }

    /**
     * @param  list<string>  $urlsOrdered
     * @param  array<string, true>  $urlSeen
     */
    private function pushUniqueUrl(
        string $url,
        array &$urlsOrdered,
        array &$urlSeen
    ): void {
        if (isset($urlSeen[$url])) {
            return;
        }

        // isset() reads false as set as well, so any value marks the URL seen
        // @pest-mutate-ignore: TrueToFalse
        $urlSeen[$url] = true;
        $urlsOrdered[] = $url;
    }

    /**
     * @param  list<string>  $idsOrdered
     * @param  array<string, true>  $seen
     */
    private function pushUniqueId(
        string $id,
        array &$idsOrdered,
        array &$seen
    ): void {
        if (isset($seen[$id])) {
            return;
        }

        // isset() reads false as set as well, so any value marks the id seen
        // @pest-mutate-ignore: TrueToFalse
        $seen[$id] = true;
        $idsOrdered[] = $id;
    }
}
