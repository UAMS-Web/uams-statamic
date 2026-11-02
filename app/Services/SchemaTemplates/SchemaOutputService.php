<?php

declare(strict_types=1);

namespace App\Services\SchemaTemplates;

use App\Enums\SchemaType;
use App\Support\AudioSeries;
use App\Support\RequestAttributeMemo;
use App\Tags\Schema\SchemaPropertyValidator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Fluent;
use Statamic\Assets\Asset as StatamicAsset;
use Statamic\Contracts\Data\Augmentable;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Contracts\Taxonomies\Term;
use Statamic\Entries\Entry;
use Statamic\Entries\Entry as EntryModel;
use Statamic\Facades\Compare;
use Statamic\Facades\Data;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\Parse;
use Statamic\Facades\Term as TermFacade;
use Statamic\Facades\URL;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;
use Statamic\Fields\Fields;
use Statamic\Fields\Value;
use Statamic\Fieldtypes\Grid as GridFieldtype;
use Statamic\Fieldtypes\Group as GroupFieldtype;
use Statamic\Fieldtypes\Link\ArrayableLink;
use Statamic\Fieldtypes\Replicator as ReplicatorFieldtype;
use Statamic\Fieldtypes\Terms as TermsFieldtype;
use Statamic\Query\Builder;
use Statamic\Statamic;
use Statamic\Structures\Page;
use Statamic\Support\Arr;
use Statamic\Taxonomies\LocalizedTerm;
use Statamic\Taxonomies\Term as TaxonomyTerm;
use Statamic\View\Antlers\AntlersString;

/**
 * Produces the final schema script tag (or empty string) for the current
 * request using the Schema Templates cascade.
 *
 * Four slots (subject, website, webpage, main content) are each filled by
 * a schema_objects entry id resolved from cascade or tag parameters. Each
 * selected entry's schema (type + properties) is output as a JSON-LD node.
 */
class SchemaOutputService
{
    /**
     * Antlers context key: non-negative int suffixes schema_objects @id fragments
     * as {entryId}-0, {entryId}-1 when the same template is emitted repeatedly.
     */
    public const string ANTLERS_SCHEMA_TEMPLATE_INSTANCE_INDEX = 'schema_template_instance_index';

    /**
     * Antlers context key: optional list of Schema.org type name strings for
     * {@see SchemaTemplateNode} `type_override` (validated in
     * {@see self::schemaObjectEntryToNode()}).
     *
     * @see self::ANTLERS_SCHEMA_TEMPLATE_INSTANCE_INDEX
     */
    public const string ANTLERS_SCHEMA_TEMPLATE_TYPE_OVERRIDE = 'schema_template_type_override';

    /**
     * Antlers context key: {@see SchemaTemplateEmitContext} for shared emit maps
     * during property template parsing (used by `schema_template_node`).
     */
    public const string ANTLERS_SCHEMA_TEMPLATE_EMIT_CONTEXT = 'schema_template_emit_context';

    /**
     * Antlers context key: schema_objects template entry id currently being built.
     *
     * Used for profiling and context-aware memo keys.
     */
    public const string ANTLERS_SCHEMA_TEMPLATE_ENTRY_ID = 'schema_template_entry_id';

    /**
     * Antlers context key: schema property key currently being resolved.
     *
     * Used for profiling and context-aware memo keys.
     */
    public const string ANTLERS_SCHEMA_PROPERTY_KEY = 'schema_template_property_key';

    /**
     * Antlers context key: effective committed instance index for the current schema
     * template.
     *
     * Used for memoizing repeated Antlers parses within one compose.
     */
    public const string ANTLERS_SCHEMA_TEMPLATE_EFFECTIVE_INSTANCE_INDEX = 'schema_template_effective_instance_index';

    /**
     * Antlers context key: primary taxonomy term resolved from the live overlay
     * (`schema_template_node` snapshot) for nested property templates.
     *
     * When the overlay introduces one or more hydrated terms-like values, the
     * first key in lexicographic order whose merged value is a non-empty term
     * collection or a single term wins; its first term is exposed as a
     * {@see Fluent} flattening of that term for dot access / loops (production), or a
     * {@see LocalizedTerm} in tests when Mockery supplies a double.
     */
    public const string ANTLERS_SCHEMA_PRIMARY_RELATED_TAXONOMY_TERM = 'schema_primary_related_taxonomy_term';

    /**
     * Antlers context key: whether an audio entry's selected `series`
     * (`audio_series`) term declares the `podcast` subtype, so the shared audio
     * schema templates gate podcast-only properties (`audio`, `episodeNumber`,
     * `associatedMedia`, `partOfSeason`, `partOfSeries`) on
     * `{{ if is_podcast_episode }}` (UAMS-Web/uams-statamic-archive#1611). Computed in PHP via `Term::find`, so
     * it holds under the schema subsystem's synthetic in-memory fixtures where
     * terms-field augmentation does not hydrate.
     */
    public const string ANTLERS_IS_PODCAST_EPISODE = 'is_podcast_episode';

    /**
     * Request attribute: memoized `buildAntlersContextFromEntity` bags keyed by
     * entity id.
     */
    private const string REQUEST_ANTLERS_ENTITY_CONTEXT_CACHE = '_app_schema_output_antlers_entity_context';

    /**
     * Request attribute: memoized
     * {@see collectTermsLikeBlueprintFieldsGroupedByHandle()} results keyed by
     * {@see antlersEntityContextCacheKey()} (blueprint walk repeats for every Antlers
     * replicator row on the same subject entry).
     */
    private const string REQUEST_TERMS_LIKE_BLUEPRINT_FIELDS_GROUPED_CACHE = '_app_schema_output_terms_like_blueprint_grouped';

    /**
     * Request bag: memoized
     * {@see hydrateMergedContextTermsLikeValueViaTermsBlueprintFieldAugment()}
     * outputs keyed by parent + field + raw payload signature.
     */
    private const string REQUEST_TERMS_LIKE_AUGMENT_MEMO = '_app_schema_output_terms_like_augment_memo';

    /**
     * Request memo: compiled regex pattern strings for
     * {@see stripTermsLikeFieldPairBodiesForFillMissingAnalysis}.
     */
    private const string REQUEST_TERMS_LIKE_PAIR_STRIP_PATTERN_MEMO = '_app_schema_terms_like_pair_strip_pattern';

    /**
     * Request memo: {@see hydrateRawTermSlugValueToAugmentedValue()} results keyed by
     * input identity (objects) or content (scalar slug payloads).
     *
     * The hydration is a pure function of its input, but resolves term models via
     * Stache queries (~tens of ms per call); overlay bags repeat the same values for
     * every property parse of one schema object build, so without this memo a single
     * compose re-hydrates identical payloads hundreds of times (issue UAMS-Web/uams-statamic-archive#937).
     */
    private const string REQUEST_RAW_TERM_SLUG_HYDRATION_MEMO = '_app_schema_output_raw_term_slug_hydration_memo';

    /**
     * Request memo: `entries` field handles on a context entity's blueprint, keyed by
     * {@see antlersEntityContextCacheKey()} (the walk repeats for every Antlers
     * replicator row on the same subject entry).
     */
    private const string REQUEST_ENTRIES_FIELD_HANDLES_CACHE = '_app_schema_output_entries_field_handles';

    /**
     * Request memo: the entries an `entries` field's query builder resolves to,
     * keyed by the builder's pinned identity, so every snippet that loops one field
     * on one entity runs its query once (UAMS-Web/uams-statamic-archive#2637).
     */
    private const string REQUEST_ENTRIES_FIELD_HYDRATION_MEMO = '_app_schema_output_entries_field_hydration_memo';

    /**
     * Request bag holding a live reference to every object an identity-keyed memo
     * is keyed on, so that id can never be recycled underneath it (UAMS-Web/uams-statamic-archive#1967).
     *
     * `spl_object_id()` and `spl_object_hash()` are unique only among **live**
     * objects: PHP hands a freed object's id straight to the next allocation. A
     * memo keyed on one, with nothing keeping the object alive, therefore serves
     * the first entity's row to an unrelated second entity. UAMS-Web/uams-statamic-archive#1909 hit exactly this
     * one layer over, in the persisted output cache.
     *
     * Pinning here makes the collision impossible by construction rather than
     * detectable after the fact: while the pin is held the object cannot be freed,
     * so its id cannot be reissued, so no second object can produce the same key.
     * The bag is request-scoped, which is precisely the lifetime of the memos it
     * protects — both die with the request together.
     */
    private const string REQUEST_OBJECT_IDENTITY_PIN = '_app_schema_output_object_identity_pin';

    /**
     * Fieldtypes whose augmented values must not be merged into schema Antlers
     * context (they recurse into schema rendering or are otherwise unsafe here).
     *
     * @var list<string>
     */
    private const array SCHEMA_ANTLERS_CONTEXT_SKIP_AUGMENTATION_FIELD_TYPES = [
        'schema_object',
    ];

    public function __construct(
        protected SchemaTemplatesCascadeService $cascadeService,
        protected SchemaClassificationsIdentifiersMerger $schemaClassificationsIdentifiersMerger,
        protected SchemaJsonLdAtIdDeduplication $jsonLdAtIdDeduplication,
        protected SchemaJsonLdSchemaObjectEmissionCoordinator $schemaObjectEmissionCoordinator,
        protected JsonLdCompositionProfiler $jsonLdCompositionProfiler,
        protected MedicalOrganizationSubtypeAtTypeResolver $medicalOrganizationSubtypeAtTypeResolver,
        protected PodcastEpisodeAtTypeResolver $podcastEpisodeAtTypeResolver
    ) {}

    /**
     * Resolve identifiers/classifications tab Antlers replicator rows to strings for
     * mergers.
     *
     * Delegates to {@see resolveSingleReplicatorRowToValue()} with empty graph scope.
     * Non-string resolved values become JSON strings so
     * {@see SchemaIdentifierMergerInterface} receives a scalar string for merger
     * {@see Mergers\SchemaIdentifierMergerInterface::buildFragment()}.
     *
     * @param  array<string, mixed>  $row  Replicator row (keys: type, value, enabled)
     */
    public function resolveIdentifiersTabAntlersRow(
        array $row,
        EntryContract|Term|null $contextEntity
    ): ?string {
        if (($row['type'] ?? null) !== 'antlers') {
            return null;
        }

        $emittedEntryIds = [];
        $emittedNodeIds = [];
        $materializedFullNodeAtIds = [];

        $resolved = $this->resolveSingleReplicatorRowToValue(
            $row,
            $contextEntity,
            [],
            [],
            $emittedEntryIds,
            $emittedNodeIds,
            $materializedFullNodeAtIds,
            []
        );

        if ($resolved === null) {
            return null;
        }

        if (\is_string($resolved)) {
            $trimmed = trim($resolved);

            return $trimmed === '' ? null : $trimmed;
        }

        if (\is_int($resolved) || \is_float($resolved) || \is_bool($resolved)) {
            return (string) $resolved;
        }

        try {
            $encoded = json_encode(
                $resolved,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
        } catch (\JsonException) {
            return null;
        }

        return $encoded;
    }

    /**
     * Output script tag with JSON-LD for the current entry/term, or empty string.
     *
     * Tag parameters (subject, website, webpage, main_content) override the
     * cascade for each slot with an entry id. When omitted, cascade values are used.
     *
     * @param  array{subject?: string, website?: string, webpage?: string, main_content?: string}  $tagParams  Entry id overrides per slot
     */
    public function outputForCurrentContext(array $tagParams = []): string
    {
        if (Statamic::isCpRoute()) {
            return '';
        }

        $entity = $this->resolveEntity();

        if ($entity === null) {
            return '';
        }

        $resolved = $this->cascadeService->resolve($entity);

        if (! $resolved['enabled']) {
            // Unset local variables to free up memory
            unset(
                $resolved
            );

            return '';
        }

        $subjectEntryId = $this->normalizeEntryId($tagParams['subject'] ?? $resolved['subject_entry']);
        $websiteEntryId = $this->normalizeEntryId($tagParams['website'] ?? $resolved['website_entry']);
        $webpageEntryId = $this->normalizeEntryId($tagParams['webpage'] ?? $resolved['webpage_entry']);
        $mainContentEntryId = $this->normalizeEntryId(
            $tagParams['main_content'] ?? $resolved['main_content_entry']
        );

        // Unset local variables to free up memory
        unset(
            $resolved
        );

        if (
            $subjectEntryId === null
            && $websiteEntryId === null
            && $webpageEntryId === null
            && $mainContentEntryId === null
        ) {
            // Unset local variables to free up memory
            unset(
                $subjectEntryId,
                $websiteEntryId,
                $webpageEntryId,
                $mainContentEntryId
            );

            return '';
        }

        // Read the cache enabled configuration: the master switch and the
        // output-cache switch split from it in UAMS-Web/uams-statamic-archive#1842 must both be on.
        $cacheEnabled = (bool) config(
            'schema_templates.json_ld_cache.enabled',
            true
        ) && (bool) config(
            'schema_templates.json_ld_cache.output_enabled',
            true
        );

        // Read the cache TTL configuration
        $cacheTtlSecondsConfig = config(
            'schema_templates.json_ld_cache.ttl_seconds',
            3600
        );
        $cacheTtlSeconds = max(
            0,
            is_numeric($cacheTtlSecondsConfig) ? (int) $cacheTtlSecondsConfig : 0
        );

        // Check if the cache is enabled and the TTL is greater than 0
        if (
            ! $cacheEnabled
            || $cacheTtlSeconds <= 0
        ) {
            // Build the JSON-LD script tag for resolved slots
            $uncached = $this->composeJsonLdOutputScriptTagForResolvedSlots(
                $entity,
                $subjectEntryId,
                $websiteEntryId,
                $webpageEntryId,
                $mainContentEntryId
            );

            // Unset local variables to free up memory
            unset(
                $cacheEnabled,
                $cacheTtlSeconds,
                $subjectEntryId,
                $websiteEntryId,
                $webpageEntryId,
                $mainContentEntryId,
                $entity
            );

            // Return the JSON-LD script tag
            return $uncached;
        }

        // Resolve the JSON-LD cache
        $jsonLdCache = app(SchemaJsonLdOutputCache::class);

        // Build the cache key. Null means the context entity carries no
        // persisted id, so no key can distinguish it from another such entity
        // (UAMS-Web/uams-statamic-archive#1909) — compose uncached rather than risk serving one entity's
        // JSON-LD as another's.
        $cacheKey = $jsonLdCache->cacheKey(
            $entity,
            $tagParams,
            $subjectEntryId,
            $websiteEntryId,
            $webpageEntryId,
            $mainContentEntryId
        );

        if ($cacheKey === null) {
            $composed = $this->composeJsonLdOutputScriptTagForResolvedSlots(
                $entity,
                $subjectEntryId,
                $websiteEntryId,
                $webpageEntryId,
                $mainContentEntryId
            );

            // Unset local variables to free up memory
            unset(
                $cacheEnabled,
                $cacheTtlSeconds,
                $jsonLdCache,
                $cacheKey,
                $subjectEntryId,
                $websiteEntryId,
                $webpageEntryId,
                $mainContentEntryId,
                $entity
            );

            return $composed;
        }

        // Cache the JSON-LD script tag
        $cached = Cache::remember(
            $cacheKey,
            $cacheTtlSeconds,
            fn (): string => $this->composeJsonLdOutputScriptTagForResolvedSlots(
                $entity,
                $subjectEntryId,
                $websiteEntryId,
                $webpageEntryId,
                $mainContentEntryId
            )
        );

        // Unset local variables to free up memory
        unset(
            $cacheEnabled,
            $cacheTtlSeconds,
            $jsonLdCache,
            $cacheKey,
            $subjectEntryId,
            $websiteEntryId,
            $webpageEntryId,
            $mainContentEntryId,
            $entity
        );

        // Return the cached JSON-LD script tag
        return $cached;
    }

    /**
     * Encode a JSON-LD graph or node for embedding in a `<script
     * type="application/ld+json">` element.
     *
     * `JSON_HEX_TAG` escapes `<` and `>` (to `<` / `>`) so an
     * editor-controlled property value containing a literal `</script>` cannot
     * terminate the script element and inject markup into the page — the result
     * is still valid JSON-LD that structured-data consumers parse identically.
     * `JSON_UNESCAPED_SLASHES`/`JSON_UNESCAPED_UNICODE` are preserved for output
     * size and readability; neither is a safety mechanism in a script context.
     *
     * @param  mixed  $data  The graph array or single node to serialize.
     * @return string|false The encoded JSON, or false on failure.
     */
    private function encodeJsonLdForScript(mixed $data): string|false
    {
        return json_encode(
            $data,
            JSON_HEX_TAG | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * Build the JSON-LD script tag for resolved graph slot entry ids (uncached body).
     *
     * @param  string|null  $subjectEntryId  Normalized subject schema_objects id
     * @param  string|null  $websiteEntryId  Normalized website schema_objects id
     * @param  string|null  $webpageEntryId  Normalized webpage schema_objects id
     * @param  string|null  $mainContentEntryId  Normalized main content schema_objects id
     */
    private function composeJsonLdOutputScriptTagForResolvedSlots(
        EntryContract|Term $entity,
        ?string $subjectEntryId,
        ?string $websiteEntryId,
        ?string $webpageEntryId,
        ?string $mainContentEntryId
    ): string {
        $composeWallStart = microtime(true);

        $this->jsonLdCompositionProfiler->reset();

        try {
            $graph = [];
            $graphEntryIds = array_values(
                array_filter(
                    [
                        $subjectEntryId,
                        $websiteEntryId,
                        $webpageEntryId,
                        $mainContentEntryId,
                    ],
                    fn (?string $id): bool => $id !== null && $id !== ''
                )
            );
            // Track entry ids for which a full node is already emitted (top-level or nested)
            $emittedEntryIds = $graphEntryIds;

            // ---
            // Track emitted node @id values so repeated full objects become @id references
            // ---

            // Initialize the emitted node ids array
            $emittedNodeIds = [];

            // Track @id keys whose full JSON-LD object survived shared dedupe (compact gate)
            $materializedFullNodeAtIds = [];

            // Distinct template instance @id suffixes when explicit instance_index repeats
            // under the same scope entity (e.g. multiple contacts under one org context).
            $templateInstanceWatermarkBag = new SchemaTemplateInstanceWatermarkBag;

            // Loop through the slot entry ids
            foreach (
                [
                    'subject' => $subjectEntryId,
                    'website' => $websiteEntryId,
                    'webpage' => $webpageEntryId,
                    'main_content' => $mainContentEntryId,
                ] as $entryId
            ) {
                if ($entryId === null) {
                    continue;
                }

                $nodeBuildStart = $this->jsonLdCompositionProfiler->enabled()
                    ? microtime(true)
                    : null;

                $node = $this->schemaObjectEntryToNode(
                    $entryId,
                    $entity,
                    $graphEntryIds,
                    [],
                    $emittedEntryIds,
                    $emittedNodeIds,
                    $materializedFullNodeAtIds,
                    [],
                    $templateInstanceWatermarkBag,
                    $mainContentEntryId
                );

                if (\is_float($nodeBuildStart)) {
                    $this->jsonLdCompositionProfiler->recordBucketSample(
                        'schema_object_node_build_calls',
                        'schema_object_node_build_ms',
                        (microtime(true) - $nodeBuildStart) * 1000
                    );

                    unset($nodeBuildStart);
                }

                if ($node !== null && $node !== []) {
                    // Register top-level graph @id so property tags can compact without expanding
                    $this->schemaObjectEmissionCoordinator->recordFullSchemaObjectNode(
                        $emittedNodeIds,
                        $node
                    );
                    $graph[] = $node;

                    // Drop speculative materialized keys so later slots / nested tags only
                    // compact when a full body exists under committed graph trees.
                    $this->jsonLdAtIdDeduplication->syncMaterializedFullNodeAtIdsToUnionOfTreeRoots(
                        $graph,
                        $materializedFullNodeAtIds
                    );
                }

                // Unset local variables to free up memory
                unset(
                    $node
                );
            }

            if ($graph === []) {
                // Unset local variables to free up memory
                unset(
                    $graph,
                    $graphEntryIds,
                    $emittedEntryIds,
                    $emittedNodeIds,
                    $materializedFullNodeAtIds,
                    $templateInstanceWatermarkBag
                );

                return '';
            }

            // Collapse repeated full nodes that share a canonical `@id` to compact
            // references after all graph slots are built (nested branches may inline
            // the same `Credential` or other entity more than once).
            $dedupeStart = $this->jsonLdCompositionProfiler->enabled()
                ? microtime(true)
                : null;

            /** @var array<int, mixed> $graph */
            $graph = $this->jsonLdAtIdDeduplication->dedupeWithFreshEmitMap(
                $graph
            );

            if (\is_float($dedupeStart)) {
                $this->jsonLdCompositionProfiler->recordBucketSample(
                    'dedupe_calls',
                    'dedupe_ms',
                    (microtime(true) - $dedupeStart) * 1000
                );

                unset($dedupeStart);
            }

            $this->jsonLdAtIdDeduplication->syncMaterializedFullNodeAtIdsToUnionOfTreeRoots(
                $graph,
                $materializedFullNodeAtIds
            );

            // Re-expand bare {"@id":…} leaves when no matching full node exists anywhere
            // in the graph (type/property agnostic repair after dedupe / compact paths).
            $inflateStart = $this->jsonLdCompositionProfiler->enabled()
                ? microtime(true)
                : null;

            $this->inflateBareAtIdReferencesMissingFullNodesInGraph(
                $graph,
                $entity,
                $graphEntryIds,
                $emittedEntryIds,
                $emittedNodeIds,
                $materializedFullNodeAtIds,
                $templateInstanceWatermarkBag
            );

            if (\is_float($inflateStart)) {
                $this->jsonLdCompositionProfiler->recordBucketSample(
                    'inflate_calls',
                    'inflate_ms',
                    (microtime(true) - $inflateStart) * 1000
                );

                unset($inflateStart);
            }

            $dedupeStart = $this->jsonLdCompositionProfiler->enabled()
                ? microtime(true)
                : null;

            /** @var array<int, mixed> $graph */
            $graph = $this->jsonLdAtIdDeduplication->dedupeWithFreshEmitMap(
                $graph
            );

            if (\is_float($dedupeStart)) {
                $this->jsonLdCompositionProfiler->recordBucketSample(
                    'dedupe_calls',
                    'dedupe_ms',
                    (microtime(true) - $dedupeStart) * 1000
                );

                unset($dedupeStart);
            }

            $this->jsonLdAtIdDeduplication->syncMaterializedFullNodeAtIdsToUnionOfTreeRoots(
                $graph,
                $materializedFullNodeAtIds
            );

            $output = [
                '@context' => 'https://schema.org',
                '@graph' => $graph,
            ];

            // Serialize to JSON for Antlers output
            $jsonEncodeStart = $this->jsonLdCompositionProfiler->enabled()
                ? microtime(true)
                : null;

            $json = $this->encodeJsonLdForScript($output);

            if (\is_float($jsonEncodeStart)) {
                $this->jsonLdCompositionProfiler->recordBucketSample(
                    'json_encode_calls',
                    'json_encode_ms',
                    (microtime(true) - $jsonEncodeStart) * 1000
                );

                unset($jsonEncodeStart);
            }

            // Build the script tag
            $script = '<script type="application/ld+json">'."\n".$json."\n".'</script>';

            // Unset local variables to free up memory
            unset(
                $graph,
                $graphEntryIds,
                $emittedEntryIds,
                $emittedNodeIds,
                $materializedFullNodeAtIds,
                $templateInstanceWatermarkBag,
                $output,
                $json
            );

            // Return the script tag
            return $script;
        } finally {
            $this->jsonLdCompositionProfiler->finalize(
                (microtime(true) - $composeWallStart) * 1000
            );
        }
    }

    /**
     * Inline bare `{"@id":…}` objects when no full JSON-LD node for that @id exists
     * anywhere in the graph, by resolving a schema_objects UUID fragment (with
     * optional `-{instance}` suffix) and calling
     * {@see self::schemaObjectEntryToNode()}.
     *
     * @param  array<int, mixed>  $graph  Top-level @graph list (by reference)
     * @param  array<int, string>  $graphEntryIds  Slot entry ids for canonical @id rules
     * @param  array<int, string>  $emittedEntryIds  Shared emit entry list (by reference)
     * @param  array<string, bool>  $emittedNodeIds  Shared emitted-node map (by reference)
     * @param  array<string, bool>  $materializedFullNodeAtIds  Compact gate map (by reference)
     */
    protected function inflateBareAtIdReferencesMissingFullNodesInGraph(
        array &$graph,
        EntryContract|Term $entity,
        array $graphEntryIds,
        array &$emittedEntryIds,
        array &$emittedNodeIds,
        array &$materializedFullNodeAtIds,
        SchemaTemplateInstanceWatermarkBag $templateInstanceWatermarkBag
    ): void {
        $fullIdsPresent = [];
        foreach ($graph as $root) {
            $this->jsonLdAtIdDeduplication->recordMaterializedFullNodeAtIdsFromFragment(
                $root,
                $fullIdsPresent
            );
        }

        foreach ($graph as &$root) {
            $this->inflateBareAtIdMissingFullRecursive(
                $root,
                $fullIdsPresent,
                $entity,
                $graphEntryIds,
                $emittedEntryIds,
                $emittedNodeIds,
                $materializedFullNodeAtIds,
                $templateInstanceWatermarkBag
            );
        }

        unset(
            $fullIdsPresent,
            $root
        );
    }

    /**
     * @param  array<string, bool>  $fullIdsPresent  @id keys that already have a full object in the tree
     * @param  array<int, string>  $graphEntryIds  Slot entry ids for canonical @id rules
     * @param  array<int, string>  $emittedEntryIds  Shared emit entry list (by reference)
     * @param  array<string, bool>  $emittedNodeIds  Shared emitted-node map (by reference)
     * @param  array<string, bool>  $materializedFullNodeAtIds  Compact gate map (by reference)
     */
    private function inflateBareAtIdMissingFullRecursive(
        mixed &$value,
        array &$fullIdsPresent,
        EntryContract|Term $entity,
        array $graphEntryIds,
        array &$emittedEntryIds,
        array &$emittedNodeIds,
        array &$materializedFullNodeAtIds,
        SchemaTemplateInstanceWatermarkBag $templateInstanceWatermarkBag
    ): void {
        if (! \is_array($value)) {
            return;
        }

        if (! array_is_list($value)) {
            /** @var array<string, mixed> $value */
            if ($this->isBareJsonLdAtIdReferenceArray($value)) {
                $atId = $value['@id'];
                if (
                    \is_string($atId)
                    && $atId !== ''
                    && ! isset($fullIdsPresent[$atId])
                ) {
                    $parsed = $this->parseSchemaObjectsEntryIdAndTemplateInstanceIndexFromAtId(
                        $atId
                    );
                    if ($parsed !== null) {
                        $entryModel = EntryFacade::find($parsed['entry_id']);
                        if (
                            $entryModel instanceof EntryModel
                            && $entryModel->collectionHandle() === 'schema_objects'
                        ) {
                            $antlersContext = [];
                            if ($parsed['instance_index'] !== null) {
                                $antlersContext[self::ANTLERS_SCHEMA_TEMPLATE_INSTANCE_INDEX] = $parsed['instance_index'];
                            }

                            $built = $this->schemaObjectEntryToNode(
                                $parsed['entry_id'],
                                $entity,
                                $graphEntryIds,
                                [],
                                $emittedEntryIds,
                                $emittedNodeIds,
                                $materializedFullNodeAtIds,
                                $antlersContext,
                                $templateInstanceWatermarkBag
                            );
                            if (
                                \is_array($built)
                                && isset($built['@type'])
                            ) {
                                $value = $built;
                                $this->jsonLdAtIdDeduplication->recordMaterializedFullNodeAtIdsFromFragment(
                                    $built,
                                    $fullIdsPresent
                                );
                            }
                        }
                    }
                }
            }

            foreach ($value as $k => &$child) {
                if ($k === '@id') {
                    continue;
                }

                $this->inflateBareAtIdMissingFullRecursive(
                    $child,
                    $fullIdsPresent,
                    $entity,
                    $graphEntryIds,
                    $emittedEntryIds,
                    $emittedNodeIds,
                    $materializedFullNodeAtIds,
                    $templateInstanceWatermarkBag
                );
            }

            unset(
                $child,
                $k
            );

            return;
        }

        foreach ($value as &$item) {
            $this->inflateBareAtIdMissingFullRecursive(
                $item,
                $fullIdsPresent,
                $entity,
                $graphEntryIds,
                $emittedEntryIds,
                $emittedNodeIds,
                $materializedFullNodeAtIds,
                $templateInstanceWatermarkBag
            );
        }

        unset($item);
    }

    /**
     * Parse a canonical @id whose fragment is a schema_objects entry UUID, optionally
     * suffixed with `-{instance_index}` for template instance disambiguation.
     *
     * @return array{entry_id: string, instance_index: int|null}|null
     */
    protected function parseSchemaObjectsEntryIdAndTemplateInstanceIndexFromAtId(
        string $atId
    ): ?array {
        $hashPos = strrpos(
            $atId,
            '#'
        );
        $fragment = $hashPos === false
            ? $atId
            : substr(
                $atId,
                $hashPos + 1
            );

        if ($fragment === '') {
            return null;
        }

        if (
            preg_match(
                '/^([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})-(\d+)$/i',
                $fragment,
                $matches
            ) === 1
        ) {
            return [
                'entry_id' => $matches[1],
                'instance_index' => (int) $matches[2],
            ];
        }

        if (
            preg_match(
                '/^([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})$/i',
                $fragment,
                $matches
            ) === 1
        ) {
            return [
                'entry_id' => $matches[1],
                'instance_index' => null,
            ];
        }

        return null;
    }

    /**
     * Whether the map is a bare JSON-LD node reference (`@id` only, no `@type`).
     *
     * @param  array<string, mixed>  $value  Candidate associative array
     */
    private function isBareJsonLdAtIdReferenceArray(array $value): bool
    {
        if (\count($value) !== 1) {
            return false;
        }

        return isset($value['@id'])
            && \is_string($value['@id'])
            && $value['@id'] !== ''
            && ! isset($value['@type']);
    }

    /**
     * Normalize tag or cascade value to single entry id or null.
     *
     * @param  mixed  $value  Entry id (string) or array of one id
     */
    protected function normalizeEntryId(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (\is_array($value)) {
            $first = reset($value);

            return ! in_array($first, [false, null, ''], true) && (\is_scalar($first) || $first instanceof \Stringable) ? (string) $first : null;
        }

        return \is_scalar($value) || $value instanceof \Stringable ? (string) $value : null;
    }

    /**
     * Resolve effective schema (type + properties) for an entry with parent
     * inheritance.
     *
     * When the entry has a parent in the collection's structure (tree), any type or
     * property not set on the entry is inherited from the parent (recursively).
     * Properties taken from a parent or from "inherit_from" are limited to names
     * valid for this entry's effective Schema.org type (union when type is an
     * array). Additional entries in "inherit_from" contribute properties only
     * (after the parent). Cycles are detected via $visitedIds and broken by not
     * merging.
     * No root entry is required; any entry may have zero or one structure parent.
     *
     * @param  array<int, string>  $visitedIds  Entry ids already visited (cycle detection)
     * @return array{type: mixed, properties: array<string, mixed>}|null Effective schema or null if invalid
     */
    protected function getEffectiveSchema(EntryModel $entry, array $visitedIds = []): ?array
    {
        $rawEntryId = $entry->id();
        $entryId = \is_scalar($rawEntryId) ? (string) $rawEntryId : '';
        $entrySchema = $entry->get('schema');
        $entryType = \is_array($entrySchema) && isset($entrySchema['type']) ? $entrySchema['type'] : null;
        /** @var array<string, mixed> $entryProperties */
        $entryProperties = \is_array($entrySchema) && isset($entrySchema['properties']) && \is_array($entrySchema['properties'])
            ? $entrySchema['properties']
            : [];

        // Break cycle: do not follow parent if this entry was already visited
        if (\in_array($entryId, $visitedIds, true)) {
            if (! $this->isTypeSet($entryType)) {
                return null;
            }

            return [
                'type' => $entryType,
                'properties' => $entryProperties,
            ];
        }

        $visited = array_merge($visitedIds, [$entryId]);
        $parentId = $this->getStructureParentId($entry);

        if ($parentId === null || $parentId === '') {
            $effectiveType = $entryType;
            $effectiveProperties = $entryProperties;
        } else {
            $parentEntry = EntryFacade::find($parentId);
            if (! $parentEntry instanceof EntryModel) {
                $effectiveType = $entryType;
                $effectiveProperties = $entryProperties;
            } else {
                $parentEffective = $this->getEffectiveSchema($parentEntry, $visited);
                $parentType = $parentEffective['type'] ?? null;
                $parentProperties = \is_array($parentEffective['properties'] ?? null)
                    ? $parentEffective['properties']
                    : [];

                $effectiveType = $this->isTypeSet($entryType) ? $entryType : $parentType;

                // Filter parent properties to allowed names for the entry's effective type
                $effectiveProperties = $this->filterInheritedPropertiesForRecipientType(
                    $parentProperties,
                    $effectiveType
                );

                foreach ($entryProperties as $key => $value) {
                    // ---
                    // Check if the value is an array and a group-like value and the mode is
                    // inherit_custom
                    // ---
                    if (
                        \is_array($value)
                        && $this->isPropertyGroupValue($value)
                        && (($value['mode'] ?? '') === 'inherit_custom')
                    ) {
                        // Set the value to the effective properties array
                        $effectiveProperties[$key] = [
                            'mode' => 'inherit_custom',
                            'value' => \is_array($value['value'] ?? null) ? $value['value'] : [],
                            '_inherit_base' => $effectiveProperties[$key] ?? null,
                        ];
                    } elseif ($this->isValueSetAndNonEmpty($value)) { // Check if the value is set and non-empty
                        // Set the value to the effective properties array
                        $effectiveProperties[$key] = $value;
                    }
                }

                // Unset local variables to free up memory
                unset(
                    $parentEffective,
                    $parentEntry,
                    $parentProperties,
                    $parentType
                );
            }

            // Unset local variables to free up memory
            unset(
                $parentId
            );
        }

        if (! $this->isTypeSet($effectiveType)) {
            return null;
        }

        // ---
        // Merge properties from additional "inherit_from" entries (order: earlier
        // overridden by later)
        // ---
        $inheritFromIds = $this->normalizeInheritFromIds($entry->get('inherit_from'));
        foreach ($inheritFromIds as $otherId) {
            if (\in_array($otherId, $visited, true)) {
                continue;
            }

            $visited[] = $otherId;
            $otherEntry = EntryFacade::find($otherId);
            if (! $otherEntry instanceof EntryModel) {
                // Unset local variables to free up memory
                unset(
                    $otherEntry
                );

                continue;
            }

            $otherEffective = $this->getEffectiveSchema($otherEntry, $visited);
            if (! \is_array($otherEffective['properties'] ?? null)) {
                // Unset local variables to free up memory
                unset(
                    $otherEffective,
                    $otherEntry
                );

                continue;
            }

            // Restrict inherit_from properties to names valid for this recipient type
            $otherPropertiesFiltered = $this->filterInheritedPropertiesForRecipientType(
                $otherEffective['properties'],
                $effectiveType
            );

            // Merge each filtered key when the recipient still has an empty value there
            foreach ($otherPropertiesFiltered as $key => $value) {
                if (! $this->isValueSetAndNonEmpty($effectiveProperties[$key] ?? null)) {
                    $effectiveProperties[$key] = $value;
                }
            }

            // Unset local variables to free up memory
            unset(
                $otherPropertiesFiltered,
                $otherEffective,
                $otherEntry
            );
        }

        // Unset local variables to free up memory
        unset(
            $inheritFromIds,
            $otherId,
            $visited
        );

        // Unset local variables to free up memory
        unset(
            $entryId,
            $entryProperties,
            $entrySchema,
            $entryType
        );

        return [
            'type' => $effectiveType,
            'properties' => $effectiveProperties,
        ];
    }

    /**
     * Normalize "inherit_from" field value to a list of entry ids.
     *
     * @param  mixed  $value  Entry id, array of ids, or empty
     * @return array<int, string>
     */
    protected function normalizeInheritFromIds(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (\is_array($value)) {
            $ids = [];
            foreach ($value as $id) {
                if (\is_string($id) && $id !== '') {
                    $ids[] = $id;
                }
            }

            return array_values(array_unique($ids));
        }

        if (\is_string($value)) {
            return [$value];
        }

        return [];
    }

    /**
     * Keep only property keys valid for the recipient's Schema.org type(s) when
     * merging inherited data. Unknown types (no Spatie class) leave the array
     * unchanged so legacy content is not stripped.
     *
     * @param  array<string, mixed>  $properties  Properties from parent or inherit_from
     * @param  mixed  $effectiveType  Resolved schema.type (string or list of strings)
     * @return array<string, mixed> Filtered properties
     */
    protected function filterInheritedPropertiesForRecipientType(
        array $properties,
        mixed $effectiveType
    ): array {
        // Normalize effective schema.type to a list of Schema.org type name strings
        $typeNames = $this->normalizeEffectiveSchemaTypeToStringList($effectiveType);

        // Check whether there is no type list so inherited keys cannot be validated
        if ($typeNames === []) {
            // Return the inherited map unchanged when the recipient type is missing
            return $properties;
        }

        // Initialize the allowlist of property names permitted for those types
        $allowedKeys = [];

        // Walk each Schema.org type name declared on the recipient
        foreach ($typeNames as $typeName) {
            // Walk each property exposed by the Spatie type class for that name
            foreach (SchemaPropertyValidator::validPropertiesForType($typeName) as $propertyName) {
                // Record the property as inheritable for this recipient
                $allowedKeys[$propertyName] = true;
            }
        }

        // Check whether every type was unknown to Spatie so the allowlist is empty
        if ($allowedKeys === []) {
            // Return the inherited map unchanged when no vocabulary was resolved
            return $properties;
        }

        // Build the output map with only keys that pass the allowlist
        $filtered = [];

        // Walk each key and value proposed from a parent or inherit_from source
        foreach ($properties as $key => $value) {
            // Check if this property name is valid for the recipient type(s)
            if (isset($allowedKeys[$key])) {
                // Copy the inherited value under an allowed key
                $filtered[$key] = $value;
            }
        }

        // Unset local variables to free up memory
        unset(
            $allowedKeys,
            $key,
            $propertyName,
            $typeName,
            $typeNames,
            $value
        );

        // Return only inherited keys that belong to the recipient vocabulary
        return $filtered;
    }

    /**
     * Normalize schema.type to a deduplicated list of non-empty strings.
     *
     * @param  mixed  $type  String, list of strings, or other (yields empty list)
     * @return list<string> Unique non-empty type name strings in first-seen order
     */
    protected function normalizeEffectiveSchemaTypeToStringList(
        mixed $type
    ): array {
        // Check if schema.type is a single non-empty string
        if (
            \is_string($type)
            && $type !== ''
        ) {
            // Return that value as the sole segment
            return [$type];
        }

        // Check if schema.type is not an array of type segments
        if (! \is_array($type)) {
            // Return empty when the stored type is not a string or list
            return [];
        }

        // Collect each non-empty string segment from the type list
        $names = [];

        // Walk each raw item stored in the schema.type array
        foreach ($type as $item) {
            // Check if the item is a usable non-empty type name string
            if (
                \is_string($item)
                && $item !== ''
            ) {
                // Append the segment for later deduplication
                $names[] = $item;
            }
        }

        // Deduplicate segments while keeping array_values order stable
        $unique = array_values(array_unique($names));

        // Unset local variables to free up memory
        unset(
            $item,
            $names
        );

        // Return the normalized list of Schema.org type names
        return $unique;
    }

    /**
     * Normalize `type_override` values supplied via Antlers context to type name
     * strings.
     *
     * @param  mixed  $raw  List-like array from the tag; other shapes yield []
     * @return list<string> Unique non-empty trimmed strings in first-seen order
     */
    protected function normalizeTypeOverrideContextList(mixed $raw): array
    {
        if (! \is_array($raw) || $raw === []) {
            return [];
        }

        $names = [];

        foreach ($raw as $item) {
            if ($item instanceof Value) {
                $item = $item->value();
            }

            if (
                \is_string($item)
                || \is_int($item)
                || \is_float($item)
            ) {
                $t = trim((string) $item);
                if ($t !== '') {
                    $names[] = $t;
                }
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * Whether at least one override type is compatible with at least one entry
     * effective type.
     *
     * Compatible when {@code $O === $E} or when {@code $O} resolves in
     * {@see SchemaType} and {@code $E} appears in {@see SchemaType::ancestorsOf($O)}
     * (i.e., {@code $O} is a subtype of {@code $E} in the shipped enum graph).
     * Unknown override types only match by exact string equality to an entry type.
     *
     * @param  list<string>  $overrideTypeNames  Normalized override list (non-empty)
     * @param  list<string>  $entryTypeNames  Normalized effective entry types (non-empty)
     */
    protected function typeOverrideListIsCompatibleWithEntryTypes(
        array $overrideTypeNames,
        array $entryTypeNames
    ): bool {
        foreach ($overrideTypeNames as $o) {
            foreach ($entryTypeNames as $e) {
                if ($o === $e) {
                    return true;
                }

                if (SchemaType::tryFrom($o) === null) {
                    continue;
                }

                if (\in_array($e, SchemaType::ancestorsOf($o), true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Resolve a `@type` override for the location's own node from the context
     * entity's editor-selected `schema_medicalorganization_subtype`.
     *
     * Returns the resolved multi-type list (e.g. `[DiagnosticLab, LocalBusiness]`)
     * only when the context entity carries a non-empty MedicalOrganization
     * subtype that refines the schema entry's declared type(s); otherwise null,
     * so the declared `@type` stands. The refinement check scopes the rule to
     * the location's MedicalOrganization node and leaves nested ContactPoint /
     * PostalAddress nodes (which share the same context entity) untouched.
     *
     * @param  list<string>  $entryTypeNames  The schema entry's declared types
     * @return list<string>|null Override `@type` list, or null to keep declared
     */
    public function resolveContextEntitySubtypeAtType(
        EntryContract|Term|null $contextEntity,
        array $entryTypeNames
    ): ?array {
        if (! $contextEntity instanceof EntryModel || $entryTypeNames === []) {
            return null;
        }

        $rawSubtype = $contextEntity->get('schema_medicalorganization_subtype');
        if ($rawSubtype instanceof Value) {
            $rawSubtype = $rawSubtype->value();
        }

        if (! \is_string($rawSubtype)) {
            return null;
        }

        $subtype = trim($rawSubtype);
        if ($subtype === '') {
            return null;
        }

        $atType = $this->medicalOrganizationSubtypeAtTypeResolver->resolve($subtype);
        if ($atType === []) {
            return null;
        }

        // Only override when the chosen subtype refines a declared entry type
        // (e.g. DiagnosticLab refines MedicalOrganization); this keeps the rule
        // scoped to the location's own business node.
        if (! $this->typeOverrideListIsCompatibleWithEntryTypes([$subtype], $entryTypeNames)) {
            return null;
        }

        // Unset local variables to free up memory
        unset($rawSubtype);

        return $atType;
    }

    /**
     * Resolve a `@type` co-type for an audio content node when its series is a
     * podcast (issue UAMS-Web/uams-statamic-archive#1487; podcast folded into `audio_series` by UAMS-Web/uams-statamic-archive#1611).
     *
     * Returns the declared types with `PodcastEpisode` appended only when the
     * context entity's selected `series` (`audio_series`) term declares the
     * `podcast` subtype and the declared type is a `CreativeWork` the co-type
     * refines; otherwise null, so a non-podcast recording keeps its declared
     * `Article` type. The `series` field is shared with the video collections
     * (→ `video_series`), so {@see AudioSeries::entrySeriesHasSubtype} scopes the
     * check to entries whose `series` targets `audio_series`; the caller also
     * gates it to the entity's own business node.
     *
     * @param  list<string>  $entryTypeNames  The node's declared types
     * @return list<string>|null Co-typed `@type` list, or null to keep declared
     */
    public function resolvePodcastEpisodeAtType(
        EntryContract|Term|null $contextEntity,
        array $entryTypeNames
    ): ?array {
        if (! $contextEntity instanceof EntryModel || $entryTypeNames === []) {
            return null;
        }

        if (! AudioSeries::entrySeriesHasSubtype($contextEntity, AudioSeries::SUBTYPE_PODCAST)) {
            return null;
        }

        $atType = $this->podcastEpisodeAtTypeResolver->resolve($entryTypeNames);
        if ($atType === []) {
            return null;
        }

        return $atType;
    }

    /**
     * Get the parent entry id from the collection structure (tree), or null.
     *
     * Used for schema property inheritance: the structure defines the hierarchy,
     * not a parent field on the entry.
     *
     * @return string|null Parent entry id, or null if no structure or no parent
     */
    protected function getStructureParentId(EntryModel $entry): ?string
    {
        if (! $entry->hasStructure()) {
            return null;
        }

        $page = $entry->page();
        if (! $page instanceof Page) {
            return null;
        }

        $parentPage = $page->parent();
        if (! $parentPage instanceof Page || $parentPage->isRoot()) {
            return null;
        }

        $parentId = $parentPage->id();

        // Unset local variables to free up memory
        unset(
            $page,
            $parentPage
        );

        return \is_string($parentId) && $parentId !== '' ? $parentId : null;
    }

    /**
     * Whether the schema type is set and non-empty (string or non-empty array).
     */
    protected function isTypeSet(mixed $type): bool
    {
        if ($type === null || $type === '') {
            return false;
        }

        if (\is_array($type)) {
            return array_values(array_filter($type, fn ($t): bool => $t !== null && $t !== '')) !== [];
        }

        return true;
    }

    /**
     * Whether a raw property value is set and non-empty for inheritance purposes.
     *
     * For group-like values (mode + value): "set" when mode is disable,
     * inherit_custom, or custom with non-empty value; plain inherit with no
     * custom value is not set.
     */
    protected function isValueSetAndNonEmpty(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        if (\is_array($value)) {
            if ($this->isPropertyGroupValue($value)) {
                $mode = $value['mode'] ?? 'inherit';

                // Check if the mode is disable
                if ($mode === 'disable') {
                    // Return true to signal the value is set and non-empty
                    return true;
                }

                // Check if the mode is inherit_custom
                if ($mode === 'inherit_custom') {
                    // Return true to signal the value is set and non-empty
                    return true;
                }

                if ($mode === 'custom') {
                    return $this->propertyValueRowsNonEmpty($value['value'] ?? null);
                }

                return false;
            }

            // @phpstan-ignore notIdentical.alwaysTrue (the earlier null/''/property-group guards leave $value narrowed to a non-empty array here; the explicit non-empty check keeps the "set and non-empty" intent readable)
            return $value !== [];
        }

        return true;
    }

    /**
     * Whether the value is group-like (has "mode" key for inherit/disable/custom).
     *
     * @param  mixed  $value  Raw property value
     *
     * @phpstan-assert-if-true array<string, mixed>  $value
     */
    protected function isPropertyGroupValue(mixed $value): bool
    {
        return \is_array($value)
            && \array_key_exists('mode', $value);
    }

    /**
     * Whether replicator rows or legacy string are non-empty (for group custom value).
     *
     * @param  mixed  $value  value subfield (array of rows or string)
     */
    protected function propertyValueRowsNonEmpty(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        if (\is_array($value)) {
            return $value !== [];
        }

        return true;
    }

    /**
     * Replay the emit-map side effects of a cached standalone node fragment.
     *
     * A live build records every nested full node on the shared emit maps as it
     * goes; a memo or fragment-cache hit must leave those maps in an equivalent
     * state so later compact fast paths keep firing. Every full object (`@id` +
     * `@type`) in the fragment is recorded as emitted and materialized, and
     * schema_objects entry uuids recovered from `@id` fragments are appended to
     * the emitted entry list.
     *
     * @param  mixed  $fragment  Cached node fragment (array tree)
     * @param  array<int, string>  $emittedEntryIds  Shared emitted entry ids (by reference)
     * @param  array<string, bool>  $emittedNodeIds  Shared emitted node map (by reference)
     * @param  array<string, bool>  $materializedFullNodeAtIds  Shared materialized map (by reference)
     */
    public function replayEmitSideEffectsFromCachedNodeFragment(
        mixed $fragment,
        array &$emittedEntryIds,
        array &$emittedNodeIds,
        array &$materializedFullNodeAtIds
    ): void {
        // Ignore scalar fragments
        if (! \is_array($fragment)) {
            return;
        }

        // Record this fragment when it is a full node with @id and @type
        if (
            isset($fragment['@id'], $fragment['@type'])
            && \is_string($fragment['@id'])
            && $fragment['@id'] !== ''
        ) {
            // Mark the canonical @id as emitted and materialized
            $emittedNodeIds[$fragment['@id']] = true;
            $materializedFullNodeAtIds[$fragment['@id']] = true;

            // Recover a schema_objects entry uuid from the @id fragment
            $parsed = $this->parseSchemaObjectsEntryIdAndTemplateInstanceIndexFromAtId(
                $fragment['@id']
            );

            // Append the uuid to the emitted entry list when newly seen
            if (
                $parsed !== null
                && ! \in_array($parsed['entry_id'], $emittedEntryIds, true)
            ) {
                $emittedEntryIds[] = $parsed['entry_id'];
            }

            unset($parsed);
        }

        // Recurse into children to record nested full nodes
        foreach ($fragment as $child) {
            if (\is_array($child)) {
                $this->replayEmitSideEffectsFromCachedNodeFragment(
                    $child,
                    $emittedEntryIds,
                    $emittedNodeIds,
                    $materializedFullNodeAtIds
                );
            }

            unset($child);
        }
    }

    /**
     * Build a JSON-LD node from a schema_objects entry's front matter.
     *
     * Uses raw schema (type + properties). @id is "#" + entry id. @type is
     * schema.type (string if one, array if multiple). Properties are resolved
     * from schema.properties; empty/null values are omitted. When a property
     * uses the Antlers replicator set, its template is parsed with the
     * context entity. When a property uses the Thing set (entries), referenced
     * entries are output as @id reference if already in the graph or inline node.
     *
     * @param  string  $entryId  Entry id (from schema_objects collection)
     * @param  EntryContract|Term|null  $contextEntity  Current entry/term for Antlers context
     * @param  array<int, string>  $graphEntryIds  Entry ids already in the graph (use @id for these)
     * @param  array<int, string>  $visitedIds  Entry ids being built (avoid cycles)
     * @param  array<int, string>  $emittedEntryIds  Entry ids for which a full node was already output (by reference)
     * @param  array<string, bool>  $emittedNodeIds  Node @id values already emitted (by reference)
     * @param  array<string, bool>  $materializedFullNodeAtIds  Full nodes known after shared dedupe (by reference)
     * @param  array<string, mixed>  $antlersContext  Optional Antlers context overrides
     * @param  SchemaTemplateInstanceWatermarkBag|null  $templateInstanceWatermarkBag  Per-build instance suffix allocator
     * @param  string|null  $contextOwnBusinessNodeEntryId  The context entity's own business node id (its resolved main_content slot); the subtype-derived `@type` applies only to this node, never to shared graph slots
     * @return array<string, mixed>|null Node with @id, @type and properties, or null if entry/schema missing
     */
    protected function schemaObjectEntryToNode(
        string $entryId,
        EntryContract|Term|null $contextEntity = null,
        array $graphEntryIds = [],
        array $visitedIds = [],
        array &$emittedEntryIds = [],
        array &$emittedNodeIds = [],
        array &$materializedFullNodeAtIds = [],
        array $antlersContext = [],
        ?SchemaTemplateInstanceWatermarkBag $templateInstanceWatermarkBag = null,
        ?string $contextOwnBusinessNodeEntryId = null
    ): ?array {
        if (\in_array($entryId, $visitedIds, true)) {
            return null;
        }

        $entry = EntryFacade::find($entryId);

        if (! $entry instanceof EntryModel) {
            return null;
        }

        $effectiveSchema = $this->getEffectiveSchema($entry, $visitedIds);

        if ($effectiveSchema === null) {
            return null;
        }

        $type = $effectiveSchema['type'];
        $properties = $effectiveSchema['properties'];

        $typeArray = \is_array($type)
            ? array_values(array_filter($type, fn ($t): bool => $t !== null && $t !== ''))
            : ($type !== null && $type !== '' ? [$type] : []);

        if ($typeArray === []) {
            return null;
        }

        $entryTypeNames = $this->normalizeEffectiveSchemaTypeToStringList($type);
        $typeOverrideList = $this->normalizeTypeOverrideContextList(
            $antlersContext[self::ANTLERS_SCHEMA_TEMPLATE_TYPE_OVERRIDE] ?? null
        );
        $typeForAtType = $typeArray;
        $subtypeAtType = null;
        $podcastAtType = null;
        if (
            $typeOverrideList !== []
            && $entryTypeNames !== []
            && $this->typeOverrideListIsCompatibleWithEntryTypes($typeOverrideList, $entryTypeNames)
        ) {
            $typeForAtType = $typeOverrideList;
        } elseif (
            $contextOwnBusinessNodeEntryId !== null
            && $entryId === $contextOwnBusinessNodeEntryId
        ) {
            // No explicit Antlers `type_override`: derive `@type` from the
            // context entity's MedicalOrganization subtype when it refines the
            // declared type. Drives the location's own business node to its
            // selected subtype and the DiagnosticLab multi-type (issue UAMS-Web/uams-statamic-archive#967).
            //
            // Scope strictly to the context entity's OWN business node — its
            // resolved main_content slot, passed in explicitly. This is NOT any
            // id in $graphEntryIds: a location page also carries the shared
            // site-default subject_entry (the UAMS MedicalOrganization) as a
            // graph slot built under this same context entity, and it — like a
            // nested thing_* ref (e.g. parentOrganization → that same UAMS node)
            // — must keep its own declared @type, never the location's subtype.
            $subtypeAtType = $this->resolveContextEntitySubtypeAtType($contextEntity, $entryTypeNames);
            if ($subtypeAtType !== null) {
                $typeForAtType = $subtypeAtType;
            } else {
                // Otherwise co-type the audio content node as a PodcastEpisode
                // when the context entity's series is a podcast subtype (UAMS-Web/uams-statamic-archive#1487,
                // UAMS-Web/uams-statamic-archive#1611). Same own-business-node scoping as the subtype rule: a
                // non-podcast recording keeps its declared Article type, and a
                // nested / shared node (built for a different context) is never
                // relabeled.
                $podcastAtType = $this->resolvePodcastEpisodeAtType($contextEntity, $entryTypeNames);
                if ($podcastAtType !== null) {
                    $typeForAtType = $podcastAtType;
                }
            }
        }

        unset(
            $entryTypeNames,
            $typeOverrideList,
            $subtypeAtType,
            $podcastAtType
        );

        $visited = array_merge($visitedIds, [$entryId]);

        // Optional instance index for unique @id when reusing a template entry;
        // when a watermark bag is present, bump past prior emissions for this
        // template id under the same scope entity (see resolveEffective…).
        $templateInstanceIndex = $this->resolveEffectiveTemplateInstanceIndexForWatermark(
            $entryId,
            $contextEntity,
            $graphEntryIds,
            $antlersContext,
            $templateInstanceWatermarkBag,
            true
        );

        // ---
        // Drop reserved key so nested schema and Antlers templates do not see it
        // ---

        // Initialize the antlers context for properties
        $antlersContextForProperties = $antlersContext;

        // Unset the reserved key so nested schema and Antlers templates do not see it
        unset(
            $antlersContextForProperties[self::ANTLERS_SCHEMA_TEMPLATE_INSTANCE_INDEX],
            $antlersContextForProperties[self::ANTLERS_SCHEMA_TEMPLATE_TYPE_OVERRIDE]
        );

        $antlersContextForProperties[self::ANTLERS_SCHEMA_TEMPLATE_ENTRY_ID] = $entryId;
        $antlersContextForProperties[self::ANTLERS_SCHEMA_TEMPLATE_EFFECTIVE_INSTANCE_INDEX] = $templateInstanceIndex;

        // Expose the graph subject entry so `source_entry.{handle}` resolves inside
        // nested property Antlers when the live scope only carries replicator row
        // keys (see SchemaThingRefsResolver::getFieldValueFromAntlersContext).
        if (
            $contextEntity instanceof EntryContract
            && ! array_key_exists('source_entry', $antlersContextForProperties)
        ) {
            $antlersContextForProperties['source_entry'] = $contextEntity;
        }

        // Compute the JSON-LD @id for the schema_objects entry
        $nodeId = $this->entryIdToNodeId(
            $entryId,
            $contextEntity,
            $graphEntryIds,
            $visited,
            $entry,
            $properties,
            $templateInstanceIndex
        );

        $node = [
            '@id' => $nodeId,
            '@type' => \count($typeForAtType) === 1 ? (\is_scalar($typeForAtType[0]) ? (string) $typeForAtType[0] : '') : $typeForAtType,
        ];

        // Register this build on the shared stack so nested `schema_template_node`
        // tags for any in-flight ancestor (slot or relationship initiated, not just
        // tag initiated) emit `{"@id":…}` instead of a full rebuild whose body the
        // final dedupe pass would collapse to the same reference (issue UAMS-Web/uams-statamic-archive#937).
        SchemaTemplateNodeBuildStack::push($entryId);

        try {
            foreach ($properties as $key => $value) {
                $propertyContext = $antlersContextForProperties;
                $propertyContext[self::ANTLERS_SCHEMA_PROPERTY_KEY] = $key;

                $resolved = $this->resolvePropertyValue(
                    $value,
                    $contextEntity,
                    $graphEntryIds,
                    $visited,
                    $emittedEntryIds,
                    $emittedNodeIds,
                    $materializedFullNodeAtIds,
                    $propertyContext,
                    $templateInstanceWatermarkBag
                );
                if ($resolved === null) {
                    continue;
                }

                if ($resolved === '') {
                    continue;
                }

                $node[$key] = $resolved;
            }
        } finally {
            SchemaTemplateNodeBuildStack::pop();
        }

        // Merge classifications and identifiers tab values into the node
        $node = $this->schemaClassificationsIdentifiersMerger->merge($entry, $node);

        $this->jsonLdAtIdDeduplication->recordMaterializedFullNodeAtIdsFromFragment(
            $node,
            $materializedFullNodeAtIds
        );

        return $node;
    }

    /**
     * Canonical JSON-LD @id for a schema_objects template entry without building
     * properties.
     *
     * Matches `schemaObjectEntryToNode()` root @id rules (including instance index).
     *
     * @param  string  $entryId  schema_objects entry id
     * @param  EntryContract|Term|null  $contextEntity  Context for @id resolution
     * @param  array<int, string>  $graphEntryIds  Graph slot entry ids
     * @param  array<string, mixed>  $antlersContext  Optional template instance index
     * @param  SchemaTemplateInstanceWatermarkBag|null  $templateInstanceWatermarkBag  Peek-only allocator (must match full build bag)
     * @return string|null Canonical @id, or null when the entry is missing or not schema_objects
     */
    public function previewCanonicalAtIdForSchemaObjectTemplate(
        string $entryId,
        EntryContract|Term|null $contextEntity,
        array $graphEntryIds,
        array $antlersContext = [],
        ?SchemaTemplateInstanceWatermarkBag $templateInstanceWatermarkBag = null
    ): ?string {
        // Load the entry when Statamic can resolve it by id
        $entry = EntryFacade::find($entryId);

        // Reject missing entries
        if (! $entry instanceof EntryModel) {
            // Unset local variables to free up memory
            unset(
                $entry
            );

            return null;
        }

        // Limit preview to schema_objects templates
        if ($entry->collectionHandle() !== 'schema_objects') {
            // Unset local variables to free up memory
            unset(
                $entry
            );

            return null;
        }

        // ---
        // Match {@see self::schemaObjectEntryToNode()} root @id (peek; do not commit
        // watermark)
        // ---
        $templateInstanceIndex = $this->resolveEffectiveTemplateInstanceIndexForWatermark(
            $entryId,
            $contextEntity,
            $graphEntryIds,
            $antlersContext,
            $templateInstanceWatermarkBag,
            false
        );

        // Compute the same @id string the full node builder would use at the root
        $atId = $this->entryIdToNodeId(
            $entryId,
            $contextEntity,
            $graphEntryIds,
            [],
            $entry,
            null,
            $templateInstanceIndex
        );

        // Unset local variables to free up memory
        unset(
            $entry,
            $templateInstanceIndex
        );

        return $atId;
    }

    /**
     * Build one JSON-LD node from a schema_objects template entry for Antlers tags
     * (e.g. per-asset ImageObject), without a surrounding @graph.
     *
     * @param  string  $templateEntryId  schema_objects entry id
     * @param  EntryContract|Term|null  $contextEntity  Entry or term for property Antlers context
     * @param  array<string, mixed>  $antlersContext  Optional Antlers context overrides
     * @return array<string, mixed>|null Node with @id, @type, and resolved properties, or null
     */
    public function buildStandaloneSchemaObjectNode(
        string $templateEntryId,
        EntryContract|Term|null $contextEntity = null,
        array $antlersContext = []
    ): ?array {
        // Isolate emitted-node tracking for standalone calls
        $emittedNodeIds = [];
        $materializedFullNodeAtIds = [];

        // Delegate to the shared implementation with a local map
        return $this->buildStandaloneSchemaObjectNodeSharingEmittedNodeIds(
            $templateEntryId,
            $contextEntity,
            $antlersContext,
            $emittedNodeIds,
            $materializedFullNodeAtIds
        );
    }

    /**
     * Build one standalone JSON-LD node while sharing emitted @id maps with a graph
     * build.
     *
     * Relationship resolution may skip calling this when the target canonical
     * `@id` is already present in `$emittedNodeIds` (compact reference only).
     *
     * @param  string  $templateEntryId  schema_objects entry id
     * @param  EntryContract|Term|null  $contextEntity  Entry or term for property Antlers context
     * @param  array<string, mixed>  $antlersContext  Optional Antlers context overrides
     * @param  array<string, bool>  $emittedNodeIds  Canonical @id keys for nested dedupe (by reference)
     * @param  array<string, bool>  $materializedFullNodeAtIds  Full nodes known after shared dedupe (by reference)
     * @param  array<int, string>|null  $graphEntryIdsOverride  When set (e.g., from {@see SchemaTemplateEmitContext}), use for @id / watermark
     *                                                          anchor instead of resolving from `$contextEntity`
     * @param  SchemaTemplateInstanceWatermarkBag|null  $templateInstanceWatermarkBagOverride  When set, share this bag with nested emission
     * @return array<string, mixed>|null Node with @id, @type, and resolved properties, or null
     */
    public function buildStandaloneSchemaObjectNodeSharingEmittedNodeIds(
        string $templateEntryId,
        EntryContract|Term|null $contextEntity,
        array $antlersContext,
        array &$emittedNodeIds,
        array &$materializedFullNodeAtIds,
        ?array $graphEntryIdsOverride = null,
        ?SchemaTemplateInstanceWatermarkBag $templateInstanceWatermarkBagOverride = null
    ): ?array {
        // Resolve the context slot @id set (so thing_* properties can emit @id refs)
        // and the entity's own business node id (its main_content slot) for subtype
        // @type scoping. A nested/override build carrying $graphEntryIdsOverride is a
        // thing_* ref to another entity — never this entity's own business node — so
        // it must not derive a subtype @type, and skipping the resolve there avoids a
        // per-nested-node cascade resolve on a hot path (UAMS-Web/uams-statamic-archive#967). A true standalone
        // build resolves the cascade once and takes both from it.
        if ($graphEntryIdsOverride !== null) {
            $graphEntryIds = $graphEntryIdsOverride;
            $ownBusinessNodeEntryId = null;
        } elseif ($contextEntity === null) {
            $graphEntryIds = [];
            $ownBusinessNodeEntryId = null;
        } else {
            $resolvedCascade = $this->cascadeService->resolve($contextEntity);
            $graphEntryIds = $this->graphSlotEntryIdsFromResolvedCascade($resolvedCascade);
            $ownBusinessNodeEntryId = $this->normalizeEntryId($resolvedCascade['main_content_entry']);

            // Unset local variables to free memory
            unset(
                $resolvedCascade
            );
        }

        // Initialize the emitted entry ids array
        $emittedEntryIds = [];

        $templateInstanceWatermarkBag = $templateInstanceWatermarkBagOverride ?? new SchemaTemplateInstanceWatermarkBag;

        // Build the node
        $node = $this->schemaObjectEntryToNode(
            $templateEntryId,
            $contextEntity,
            $graphEntryIds,
            [],
            $emittedEntryIds,
            $emittedNodeIds,
            $materializedFullNodeAtIds,
            $antlersContext,
            $templateInstanceWatermarkBag,
            $ownBusinessNodeEntryId
        );

        // Unset local variables to free memory
        unset(
            $emittedEntryIds,
            $graphEntryIds
        );

        // Return the built node
        return $node;
    }

    /**
     * Build JSON for one or more schema_objects template ids for the
     * `schema_template_node` Antlers tag.
     *
     * When `$emitContext` is from property template parsing, later full nodes
     * with the same JSON-LD `@id` collapse to compact `{"@id":…}` objects.
     * When a template embeds the tag for its own schema_objects id while that
     * node is still building, output is only `{"@id":…}` (see
     * SchemaTemplateNodeBuildStack) so property values like `affiliation` can
     * reference the same entry without infinite recursion.
     * When `$emitContext` is null, uses isolated emit maps and the current
     * route entity so the tag works outside embedded schema templates.
     *
     * @param  array<int, string>  $templateEntryIds  schema_objects entry ids
     * @param  SchemaTemplateEmitContext|null  $emitContext  Shared emit maps from the active graph build, or null for standalone
     * @param  array<string, mixed>  $antlersContext  Optional keys merged into property template context (e.g. template instance index)
     * @return string JSON object, JSON array of objects, or empty string
     */
    public function buildSchemaTemplateNodeJsonForTag(
        array $templateEntryIds,
        ?SchemaTemplateEmitContext $emitContext,
        array $antlersContext = []
    ): string {
        // Normalize template ids to unique non-empty strings
        $templateEntryIds = array_values(
            array_unique(
                array_filter(
                    $templateEntryIds,
                    static fn (string $id): bool => $id !== ''
                )
            )
        );

        // Return empty output when no ids remain
        if ($templateEntryIds === []) {
            // Unset local variables to free up memory
            unset(
                $templateEntryIds
            );

            return '';
        }

        // Use shared emit maps when the tag runs inside schema property Antlers
        if ($emitContext instanceof SchemaTemplateEmitContext) {
            $contextEntity = $emitContext->contextEntity;
            $graphEntryIds = $emitContext->graphEntryIds;
            $emittedEntryIds = &$emitContext->emittedEntryIds;
            $emittedNodeIds = &$emitContext->emittedNodeIds;
            $materializedFullNodeAtIds = &$emitContext->materializedFullNodeAtIds;
            $templateInstanceWatermarkBag = $emitContext->templateInstanceWatermarkBag;
        } else {
            // Initialize fresh emit lists for standalone resolution
            $emittedEntryIds = [];
            $emittedNodeIds = [];
            $materializedFullNodeAtIds = [];
            $contextEntity = $this->resolveEntity();
            $graphEntryIds = $this->resolveGraphEntryIdsForEntity($contextEntity);
            $templateInstanceWatermarkBag = new SchemaTemplateInstanceWatermarkBag;
        }

        // Collect encoded nodes in request order
        $built = [];

        // Build each template id as a node with shared dedupe
        foreach ($templateEntryIds as $templateEntryId) {
            // Emit only an @id reference when this id is already being built
            // (e.g. template embeds schema_template_node for its own entry id)
            if (SchemaTemplateNodeBuildStack::contains($templateEntryId)) {
                $atId = $this->canonicalAtIdForReentrantSchemaTemplateNode(
                    $templateEntryId
                );
                $built[] = ['@id' => $atId];
                // Unset local variables to free up memory
                unset(
                    $atId
                );

                continue;
            }

            SchemaTemplateNodeBuildStack::push($templateEntryId);

            try {
                // Skip full template expansion when this @id was already emitted in the graph
                if ($emitContext instanceof SchemaTemplateEmitContext) {
                    // A validated type_override changes only the node @type, never its @id.
                    // The coordinator compacts only when a full body for the @id is already
                    // materialized in merged output, and final dedupe collapses any later
                    // full body for the same @id (overridden @type included) to a bare
                    // reference — so a full build here is pure wasted compose time even
                    // with type_override (issue UAMS-Web/uams-statamic-archive#937: ~11s per bypassed org rebuild).
                    // When no full body exists yet the coordinator returns null and the
                    // full build below still lets the overridden @type survive.
                    $compactRef = $this->schemaObjectEmissionCoordinator
                        ->tryCompactReferenceIfSchemaObjectAlreadyFullyEmitted(
                            $this,
                            $templateEntryId,
                            $emittedNodeIds,
                            $materializedFullNodeAtIds,
                            $contextEntity,
                            $graphEntryIds,
                            $antlersContext,
                            $templateInstanceWatermarkBag
                        );

                    // Append the reference and continue when the fast path applies
                    if ($compactRef !== null) {
                        $built[] = $compactRef;

                        // Unset local variables to free up memory
                        unset(
                            $compactRef
                        );

                        continue;
                    }
                }

                // Resolve a full node from the schema_objects entry
                $node = $this->schemaObjectEntryToNode(
                    $templateEntryId,
                    $contextEntity,
                    $graphEntryIds,
                    [],
                    $emittedEntryIds,
                    $emittedNodeIds,
                    $materializedFullNodeAtIds,
                    $antlersContext,
                    $templateInstanceWatermarkBag
                );

                // Skip ids that do not resolve to a node
                if ($node === null) {
                    continue;
                }

                // Collapse duplicate @id full objects to references
                $node = $this->jsonLdAtIdDeduplication->dedupeWithSharedEmitMapAndDecodeFirstFullRetention(
                    $node,
                    $emittedNodeIds
                );

                // Register canonical @id on the shared map when this tag emitted a
                // full node (nested dedupe no longer persists new ids; see
                // SchemaJsonLdAtIdDeduplication).
                if (
                    \is_array($node)
                    && isset($node['@type'])
                ) {
                    /** @var array<string, mixed> $node */
                    $this->schemaObjectEmissionCoordinator->recordFullSchemaObjectNode(
                        $emittedNodeIds,
                        $node
                    );

                    // Append entry id when not already tracked for thing_* refs
                    if (! \in_array($templateEntryId, $emittedEntryIds, true)) {
                        $emittedEntryIds[] = $templateEntryId;
                    }
                }

                // Append the resolved fragment
                $built[] = $node;

                // Unset local variables to free up memory
                unset(
                    $node
                );
            } finally {
                SchemaTemplateNodeBuildStack::pop();
            }
        }

        // Return empty string when every id failed to resolve
        if ($built === []) {
            // Unset local variables to free up memory
            unset(
                $built,
                $emitContext,
                $templateEntryIds
            );

            return '';
        }

        // Encode a single object or a list when multiple ids were requested
        $payload = \count($built) === 1 ? $built[0] : $built;

        // Serialize to JSON for Antlers output
        $json = $this->encodeJsonLdForScript($payload);

        // Unset local variables to free up memory
        unset(
            $built,
            $payload,
            $templateEntryIds
        );

        return $json !== false ? $json : '';
    }

    /**
     * Resolve current context slot entry ids for graph-style @id references.
     *
     * @param  EntryContract|Term|null  $entity  Current route entity
     * @return array<int, string> Non-empty schema_object entry ids from slots
     */
    protected function resolveGraphEntryIdsForEntity(
        EntryContract|Term|null $entity
    ): array {
        // Check if no entity is available for cascade resolution.
        if ($entity === null) {
            // Return an empty array to signal no entity is available for cascade resolution
            return [];
        }

        // Build slot entry ids from the resolved schema-template cascade.
        return $this->graphSlotEntryIdsFromResolvedCascade(
            $this->cascadeService->resolve($entity)
        );
    }

    /**
     * Build the graph slot @id set from an already-resolved schema-template cascade.
     *
     * Shared by {@see self::resolveGraphEntryIdsForEntity()} and the standalone
     * build path so a single `cascadeService->resolve()` yields both the slot set
     * and the context entity's own business node id (its `main_content` slot).
     *
     * @param  array<string, mixed>  $resolved  Result of {@see SchemaTemplatesCascadeService::resolve()}
     * @return array<int, string> Non-empty schema_object entry ids from the four slots
     */
    private function graphSlotEntryIdsFromResolvedCascade(array $resolved): array
    {
        return array_values(
            array_unique(
                array_filter(
                    [
                        $this->normalizeEntryId($resolved['subject_entry'] ?? null),
                        $this->normalizeEntryId($resolved['website_entry'] ?? null),
                        $this->normalizeEntryId($resolved['webpage_entry'] ?? null),
                        $this->normalizeEntryId($resolved['main_content_entry'] ?? null),
                    ],
                    fn (?string $entryId): bool => \is_string($entryId) && $entryId !== ''
                )
            )
        );
    }

    /**
     * Return the canonical JSON-LD @id for a schema_objects entry (single source of
     * truth).
     *
     * Uses the effective schema (after structure inheritance and mode:
     * inherit/custom/disable) so that url respects inherit (use parent url), custom
     * (use value), or disable. Base URL Fallback is the entry's site root or '/'.
     *
     * @param  EntryModel  $entry  A schema_objects collection entry
     * @return string Full @id (e.g. https://uams.edu/#uuid) or fragment-only #uuid
     */
    public function getCanonicalAtIdForSchemaEntry(EntryModel $entry): string
    {
        $effectiveSchema = $this->getEffectiveSchema($entry, []);
        $rawEntryId = $entry->id();
        $entryId = \is_scalar($rawEntryId) ? (string) $rawEntryId : '';

        if ($effectiveSchema === null) {
            $baseUrl = $this->siteRootForEntry($entry);

            // Unset local variables to free up memory
            unset(
                $effectiveSchema
            );

            return $baseUrl !== '' ? $baseUrl.'#'.$entryId : '#'.$entryId;
        }

        $properties = $effectiveSchema['properties'];
        $baseUrl = null;

        if (isset($properties['url'])) {
            $emittedEntryIdsForUrl = [];
            $emittedNodeIdsForUrl = [];
            $materializedForUrl = [];
            $resolvedUrl = $this->resolvePropertyValue(
                $properties['url'],
                $entry,
                [],
                [$entryId],
                $emittedEntryIdsForUrl,
                $emittedNodeIdsForUrl,
                $materializedForUrl,
                []
            );
            if (\is_string($resolvedUrl) && $resolvedUrl !== '') {
                $baseUrl = $resolvedUrl;
            }
        }

        if ($baseUrl !== null) {
            $baseUrl = str_contains($baseUrl, '#')
                ? substr($baseUrl, 0, (int) strpos($baseUrl, '#'))
                : $baseUrl;
        }

        if ($baseUrl === null || $baseUrl === '') {
            $baseUrl = $this->siteRootForEntry($entry);
        }

        $nodeId = $baseUrl !== '' ? $baseUrl.'#'.$entryId : '#'.$entryId;

        // Unset local variables to free up memory
        unset(
            $baseUrl,
            $effectiveSchema,
            $properties
        );

        return $nodeId;
    }

    /**
     * Return the site absolute URL for an entry (@id fallback base URL).
     *
     * @param  EntryModel  $entry  Entry that may have a site
     * @return string Site absolute URL, or '/' if none
     */
    protected function siteRootForEntry(
        EntryModel $entry
    ): string {
        // Get the entry's site
        $site = $entry->site();

        // Check if the site is not found
        if ($site === null) {
            // Return '/' to signal no site root is available
            return '/';
        }

        // Initialize the site URL value as null
        $url = null;

        // Check if the site supports absolute URL retrieval
        if (\is_object($site) && method_exists($site, 'absoluteUrl')) {
            // Get the site's absolute URL
            $url = $site->absoluteUrl();
        } elseif (\is_object($site) && method_exists($site, 'url')) {
            // Fallback to the site's configured URL
            $url = $site->url();
        }

        // Check if the URL is not a string or is empty
        if (
            ! \is_string($url)
            || $url === ''
        ) {
            // Return '/' to signal no site root is available
            return '/';
        }

        // Return the site URL as the fallback @id base
        return $url;
    }

    /**
     * Canonical JSON-LD @id for a re-entrant `schema_template_node` emit.
     *
     * Skips getCanonicalAtIdForSchemaEntry and property `url` resolution so
     * Antlers inside those paths cannot recurse when a template references
     * itself. Matches the site-root + `#entryId` fallback used when no usable
     * `url` is available.
     *
     * @param  string  $entryId  schema_objects (or other) entry id
     */
    protected function canonicalAtIdForReentrantSchemaTemplateNode(
        string $entryId
    ): string {
        // Load the entry when Statamic can resolve it by id
        $entry = EntryFacade::find($entryId);

        // Fall back to fragment-only id when the entry is missing
        if (! $entry instanceof EntryModel) {
            return '#'.$entryId;
        }

        // Delegate non-schema_objects entries to the shared @id rules
        if ($entry->collectionHandle() !== 'schema_objects') {
            $out = $this->entryIdToNodeId(
                $entryId,
                null,
                [],
                [],
                $entry
            );

            // Unset local variables to free up memory
            unset(
                $entry
            );

            return $out;
        }

        // Build the same base + fragment shape as getCanonicalAtIdForSchemaEntry
        // without resolving schema.properties.url
        $baseUrl = $this->siteRootForEntry($entry);

        // Unset local variables to free up memory
        unset(
            $entry
        );

        return $baseUrl !== '' ? $baseUrl.'#'.$entryId : '#'.$entryId;
    }

    /**
     * Compute the JSON-LD @id for a schema_objects entry (base URL + fragment).
     *
     * For schema_objects entries uses getCanonicalAtIdForSchemaEntry; otherwise
     * uses the entry's schema.properties.url if present, else context entity url.
     * Fragment is stripped from the base; result is baseUrl.'#'.entryId or
     * '#'.entryId.
     *
     * @param  string  $entryId  Entry id (from schema_objects collection)
     * @param  EntryContract|Term|null  $contextEntity  Current entry/term for Antlers/url fallback
     * @param  array<int, string>  $graphEntryIds  Entry ids already in the graph
     * @param  array<int, string>  $visitedIds  Entry ids being built (avoid cycles)
     * @param  EntryModel|null  $entry  Optional pre-loaded entry to avoid duplicate load
     * @param  array<string, mixed>|null  $properties  Optional schema.properties when $entry given
     * @param  ?int  $templateInstanceIndex  When set (>= 0), append -{index} to the fragment id
     * @return string Full @id (e.g. https://uams.edu/#uuid) or fragment-only #uuid
     */
    protected function entryIdToNodeId(
        string $entryId,
        EntryContract|Term|null $contextEntity,
        array $graphEntryIds,
        array $visitedIds,
        ?EntryModel $entry = null,
        ?array $properties = null,
        ?int $templateInstanceIndex = null
    ): string {
        if (! $entry instanceof Entry) {
            $entry = EntryFacade::find($entryId);
            if (! $entry instanceof EntryModel) {
                return '#'.$entryId;
            }
        }

        if ($entry->collectionHandle() === 'schema_objects') {
            // Get the canonical @id for the schema_objects entry
            $canonical = $this->getCanonicalAtIdForSchemaEntry($entry);

            // Check if template instance index should suffix the canonical @id
            if (
                $templateInstanceIndex !== null
                && $templateInstanceIndex >= 0
            ) {
                // Append instance index for repeated use of the same template entry
                return $this->appendTemplateInstanceIndexToCanonicalAtId(
                    $canonical,
                    $templateInstanceIndex
                );
            }

            // Return the canonical @id
            return $canonical;
        }

        if ($properties === null) {
            $schema = $entry->get('schema');
            if (! \is_array($schema) || ! isset($schema['properties']) || ! \is_array($schema['properties'])) {
                return '#'.$entryId;
            }

            $properties = $schema['properties'];
        }

        $visited = array_merge($visitedIds, [$entryId]);
        $baseUrl = null;
        if (isset($properties['url'])) {
            $emittedEntryIdsForUrl = [];
            $emittedNodeIdsForUrl = [];
            $materializedForUrl = [];
            $resolvedUrl = $this->resolvePropertyValue(
                $properties['url'],
                $contextEntity,
                $graphEntryIds,
                $visited,
                $emittedEntryIdsForUrl,
                $emittedNodeIdsForUrl,
                $materializedForUrl,
                []
            );
            if (\is_string($resolvedUrl) && $resolvedUrl !== '') {
                $baseUrl = $resolvedUrl;
            }
        }

        if ($baseUrl === null && $contextEntity !== null && method_exists($contextEntity, 'url')) {
            $contextUrl = $contextEntity->url();
            if (\is_string($contextUrl) && $contextUrl !== '') {
                $baseUrl = $contextUrl;
            }
        }

        if ($baseUrl !== null) {
            $baseUrl = str_contains($baseUrl, '#') ? substr($baseUrl, 0, (int) strpos($baseUrl, '#')) : $baseUrl;
        }

        $rawResolvedEntryId = $entry->id();
        $resolvedEntryId = \is_scalar($rawResolvedEntryId) ? (string) $rawResolvedEntryId : '';
        $nodeId = $baseUrl !== null && $baseUrl !== ''
            ? $baseUrl.'#'.$resolvedEntryId
            : '#'.$resolvedEntryId;

        // Unset local variables to free up memory
        unset(
            $baseUrl,
            $entry,
            $properties,
            $visited
        );

        return $nodeId;
    }

    /**
     * Append -{index} to the URL fragment of a canonical @id (or to the whole id
     * when no fragment is present).
     *
     * @param  string  $canonicalAtId  Full @id such as https://example/#uuid
     * @param  int  $index  Non-negative instance index
     * @return string @id with `-{index}` applied to the fragment after the last `#`, or appended to
     *                the whole string when no `#` is present
     */
    protected function appendTemplateInstanceIndexToCanonicalAtId(
        string $canonicalAtId,
        int $index
    ): string {
        // Find the last fragment delimiter in the canonical @id
        $hashPos = strrpos(
            $canonicalAtId,
            '#'
        );

        // Check if no fragment delimiter exists
        if ($hashPos === false) {
            // Append index suffix directly to the canonical @id
            return sprintf('%s-%d', $canonicalAtId, $index);
        }

        // ---
        // Split base and fragment at the last #
        // ---

        // Extract the substring from the start through and including the last `#`
        $beforeHash = substr(
            $canonicalAtId,
            0,
            $hashPos + 1
        );

        // Extract the fragment after that `#`
        $fragment = substr(
            $canonicalAtId,
            $hashPos + 1
        );

        // Check if fragment is empty
        if ($fragment === '') {
            // Append numeric index after the lone hash
            return sprintf('%s%d', $beforeHash, $index);
        }

        // Suffix fragment with instance index
        return sprintf('%s%s-%d', $beforeHash, $fragment, $index);
    }

    /**
     * Parse a non-negative template instance index from Antlers context overrides.
     *
     * @param  array<string, mixed>  $antlersContext  Optional Antlers context overrides
     * @return ?int Non-negative index when the reserved key holds a valid int or integer-like
     *              string; null when absent, negative, or not numeric
     */
    protected function parseTemplateInstanceIndexFromAntlersContext(
        array $antlersContext
    ): ?int {
        // Check if the reserved key is missing from context
        if (! \array_key_exists(
            self::ANTLERS_SCHEMA_TEMPLATE_INSTANCE_INDEX,
            $antlersContext
        )) {
            // Return null to signal no instance index override
            return null;
        }

        // Read the raw index value from context
        $raw = $antlersContext[self::ANTLERS_SCHEMA_TEMPLATE_INSTANCE_INDEX];

        // ---
        // Normalize numeric strings from Antlers to integers
        // ---

        // Check if the raw value is a string and is not empty
        if (
            \is_string($raw)
            && $raw !== ''
        ) {
            // Filter the string as an integer when possible
            $filtered = filter_var(
                $raw,
                FILTER_VALIDATE_INT
            );

            // Check if filtering produced a usable integer
            if ($filtered !== false) {
                // Replace raw with the filtered integer
                $raw = $filtered;
            }
        }

        // Check if the value is not a non-negative integer
        if (
            ! \is_int($raw)
            || $raw < 0
        ) {
            // Return null for invalid index values
            return null;
        }

        // Return the validated instance index
        return $raw;
    }

    /**
     * Stable scope key for template instance allocation (shared context entity).
     */
    protected function scopeEntityIdForTemplateInstanceWatermark(
        EntryContract|Term|null $contextEntity
    ): string {
        if (
            $contextEntity === null
            || ! method_exists($contextEntity, 'id')
        ) {
            return '';
        }

        $id = $contextEntity->id();

        return \is_scalar($id) ? (string) $id : '';
    }

    /**
     * First non-empty graph slot id (typically the subject) used to namespace template
     * instance suffixes across nested context entities whose @id fragments omit those
     * ids.
     *
     * @param  array<int, string>  $graphEntryIds  Graph slot entry ids (anchor = first non-empty)
     */
    protected function templateInstanceWatermarkAllocationAnchor(
        array $graphEntryIds,
        EntryContract|Term|null $contextEntity
    ): string {
        foreach ($graphEntryIds as $slotEntryId) {
            if ($slotEntryId !== '') {
                return $slotEntryId;
            }
        }

        return $this->scopeEntityIdForTemplateInstanceWatermark($contextEntity);
    }

    /**
     * Bag key for template instance watermarking: template id plus allocation anchor.
     *
     * @param  array<int, string>  $graphEntryIds  Graph slot entry ids (anchor = first non-empty)
     */
    protected function templateInstanceWatermarkAllocationKey(
        string $schemaObjectsEntryId,
        array $graphEntryIds,
        EntryContract|Term|null $contextEntity
    ): string {
        return $schemaObjectsEntryId."\0".$this->templateInstanceWatermarkAllocationAnchor(
            $graphEntryIds,
            $contextEntity
        );
    }

    /**
     * Effective template instance index for @id suffixing: when a watermark bag is
     * present and `schema_template_instance_index` is set, uses
     * max(requested, lastCommitted + 1) so repeated requested `0` under the same
     * allocation key still produce distinct fragments; optionally commits the
     * watermark.
     *
     * @param  array<int, string>  $graphEntryIds  Graph slot entry ids (anchor = first non-empty)
     * @param  array<string, mixed>  $antlersContext  Antlers context overrides (template instance index)
     */
    protected function resolveEffectiveTemplateInstanceIndexForWatermark(
        string $schemaObjectsEntryId,
        EntryContract|Term|null $contextEntity,
        array $graphEntryIds,
        array $antlersContext,
        ?SchemaTemplateInstanceWatermarkBag $watermarkBag,
        bool $commit
    ): ?int {
        if (! \array_key_exists(
            self::ANTLERS_SCHEMA_TEMPLATE_INSTANCE_INDEX,
            $antlersContext
        )) {
            return null;
        }

        $requested = $this->parseTemplateInstanceIndexFromAntlersContext(
            $antlersContext
        );

        if ($requested === null) {
            return null;
        }

        if (! $watermarkBag instanceof SchemaTemplateInstanceWatermarkBag) {
            return $requested;
        }

        $key = $this->templateInstanceWatermarkAllocationKey(
            $schemaObjectsEntryId,
            $graphEntryIds,
            $contextEntity
        );
        // Reuse the index already issued to this replicator row, so a row
        // emitted again keeps one @id and later emits compact to a reference
        $rowIdentity = $this->templateInstanceRowIdentity(
            $contextEntity,
            $requested,
            $antlersContext
        );

        if (
            $rowIdentity !== null
            && isset($watermarkBag->issuedByRowIdentity[$key][$rowIdentity])
        ) {
            return $watermarkBag->issuedByRowIdentity[$key][$rowIdentity];
        }

        // Bump past every index already issued under this allocation key
        $lastCommitted = $watermarkBag->lastIssuedByKey[$key] ?? -1;
        $effective = max($requested, $lastCommitted + 1);

        // Commit the watermark, and remember the index for this row
        if ($commit) {
            $watermarkBag->lastIssuedByKey[$key] = $effective;

            if ($rowIdentity !== null) {
                $watermarkBag->issuedByRowIdentity[$key][$rowIdentity] = $effective;
            }
        }

        return $effective;
    }

    /**
     * Identity of the replicator row requesting a template instance: the
     * context entity, the requested index, and the row's own `id`.
     *
     * Returns null when the row carries no `id` of its own (a missing or
     * non-string `id`, or one equal to the context entity's id, which Antlers
     * cascades from the outer scope), so such emits keep a fresh suffix each.
     *
     * @param  int  $requested  Requested `schema_template_instance_index`
     * @param  array<string, mixed>  $antlersContext  Antlers context overrides (the row scope)
     * @return string|null Row identity, or null when the row has no own `id`
     */
    protected function templateInstanceRowIdentity(
        EntryContract|Term|null $contextEntity,
        int $requested,
        array $antlersContext
    ): ?string {
        $rowId = $antlersContext['id'] ?? null;
        $contextEntityId = $this->scopeEntityIdForTemplateInstanceWatermark($contextEntity);

        // Require a row id that is the row's own rather than the outer scope's
        if (
            ! \is_string($rowId)
            || $rowId === ''
            || $rowId === $contextEntityId
        ) {
            return null;
        }

        return $contextEntityId."\0".$requested."\0".$rowId;
    }

    /**
     * Resolve a schema property value from front matter to a JSON-LD value.
     *
     * Replicator rows are resolved per row (Antlers, Thing, boolean, or effective
     * string). All enabled rows are collected; one value is returned as-is, multiple
     * as array.
     *
     * @param  mixed  $value  Raw value (string, array of rows, or empty)
     * @param  EntryContract|Term|null  $contextEntity  Current entry/term for Antlers context
     * @param  array<int, string>  $graphEntryIds  Entry ids in the graph (use @id for these)
     * @param  array<int, string>  $visitedIds  Entry ids being built (avoid cycles)
     * @param  array<int, string>  $emittedEntryIds  Entry ids for which a full node was already output (by reference)
     * @param  array<string, bool>  $emittedNodeIds  Node @id values already emitted (by reference)
     * @param  array<string, bool>  $materializedFullNodeAtIds  Full nodes known after shared dedupe (by reference)
     * @param  array<string, mixed>  $antlersContext  Optional Antlers context overrides
     * @param  SchemaTemplateInstanceWatermarkBag|null  $templateInstanceWatermarkBag  Shared template instance allocator
     * @return string|int|float|bool|array<array-key, mixed>|null
     */
    protected function resolvePropertyValue(
        mixed $value,
        EntryContract|Term|null $contextEntity = null,
        array $graphEntryIds = [],
        array $visitedIds = [],
        array &$emittedEntryIds = [],
        array &$emittedNodeIds = [],
        array &$materializedFullNodeAtIds = [],
        array $antlersContext = [],
        ?SchemaTemplateInstanceWatermarkBag $templateInstanceWatermarkBag = null
    ): string|int|float|bool|array|null {
        if ($value === null || $value === '') {
            return null;
        }

        // ---
        // Unwrap group-like property (mode + value): disable → omit; inherit_custom
        // merges resolved parent base with child rows; else resolve inner value.
        // ---
        if ($this->isPropertyGroupValue($value)) {
            if (($value['mode'] ?? '') === 'disable') {
                return null;
            }

            // Check if the mode is inherit_custom
            if (($value['mode'] ?? '') === 'inherit_custom') {
                // Get the base raw value
                $baseRaw = $value['_inherit_base'] ?? null;

                // Get the rows value
                $rows = $value['value'] ?? [];

                // Normalize the rows value to an array
                $rows = \is_array($rows) ? $rows : [];

                // Resolve the base raw value
                $baseResolved = $baseRaw !== null && $baseRaw !== ''
                    ? $this->resolvePropertyValue(
                        $baseRaw,
                        $contextEntity,
                        $graphEntryIds,
                        $visitedIds,
                        $emittedEntryIds,
                        $emittedNodeIds,
                        $materializedFullNodeAtIds,
                        $antlersContext,
                        $templateInstanceWatermarkBag
                    )
                    : null;

                // Resolve the rows value
                $addonResolved = $rows !== []
                    ? $this->resolvePropertyValue(
                        $rows,
                        $contextEntity,
                        $graphEntryIds,
                        $visitedIds,
                        $emittedEntryIds,
                        $emittedNodeIds,
                        $materializedFullNodeAtIds,
                        $antlersContext,
                        $templateInstanceWatermarkBag
                    )
                    : null;

                // Merge the base resolved value and the addon resolved value
                $merged = $this->mergeAdditiveResolvedPropertyValues(
                    $baseResolved,
                    $addonResolved
                );

                return $merged;
            }

            $value = $value['value'] ?? [];
        }

        if (\is_array($value)) {
            if ($value === []) {
                return null;
            }

            $collected = [];
            foreach ($value as $row) {
                /** @var array<string, mixed> $row */
                $enabled = $row['enabled'] ?? true;
                if (! $enabled) {
                    continue;
                }

                $resolved = $this->resolveSingleReplicatorRowToValue(
                    $row,
                    $contextEntity,
                    $graphEntryIds,
                    $visitedIds,
                    $emittedEntryIds,
                    $emittedNodeIds,
                    $materializedFullNodeAtIds,
                    $antlersContext,
                    $templateInstanceWatermarkBag
                );
                if ($resolved === null) {
                    continue;
                }

                if ($resolved === '') {
                    continue;
                }

                if (\is_array($resolved) && array_is_list($resolved)) {
                    foreach ($resolved as $item) {
                        if ($item !== null && $item !== '') {
                            $collected[] = $item;
                        }
                    }
                } else {
                    $collected[] = $resolved;
                }
            }

            if ($collected === []) {
                // Unset local variables to free up memory
                unset(
                    $collected,
                    $resolved,
                    $row
                );

                return null;
            }

            // Remove duplicate fragments (same string, same @id ref, etc.) in order
            $collected = $this->jsonLdAtIdDeduplication->dedupeResolvedPropertyList($collected);

            if (\count($collected) === 1) {
                /** @var string|int|float|bool|array<mixed>|null $out */
                $out = $collected[0];

                // Unset local variables to free up memory
                unset(
                    $collected,
                    $resolved,
                    $row
                );

                return $out;
            }

            // Unset local variables to free up memory
            unset(
                $resolved,
                $row
            );

            return $collected;
        }

        if (\is_scalar($value)) {
            return $value;
        }

        return null;
    }

    /**
     * Merge two resolved property values (inherited base plus locally added rows).
     *
     * @param  mixed  $a  First resolved fragment (scalar, list, or null)
     * @param  mixed  $b  Second resolved fragment (scalar, list, or null)
     * @return string|int|float|bool|array<string, mixed>|array<int, mixed>|null Null when both sides are empty; a single scalar when the merge yields one item;
     *                                                                           otherwise a list (inherited values first, then additive rows)
     */
    protected function mergeAdditiveResolvedPropertyValues(
        mixed $a,
        mixed $b
    ): string|int|float|bool|array|null {
        // Check if both values are null or empty
        if (
            (
                $a === null
                || $a === ''
            )
            && (
                $b === null
                || $b === ''
            )
        ) {
            // Return null to signal no values are set and non-empty
            return null;
        }

        // Check if the first value is null or empty
        if (
            $a === null
            || $a === ''
        ) {
            // Return the second value
            /** @var string|int|float|bool|array<mixed>|null $b */
            return $b;
        }

        // Check if the second value is null or empty
        if (
            $b === null
            || $b === ''
        ) {
            // Return the first value
            /** @var string|int|float|bool|array<mixed>|null $a */
            return $a;
        }

        // Normalize the first fragment to a list for ordered concatenation
        $listA = $this->resolvedPropertyValueToList($a);

        // Normalize the second fragment to a list for ordered concatenation
        $listB = $this->resolvedPropertyValueToList($b);

        // Concatenate lists so inherited values precede locally added rows
        $merged = [
            ...$listA,
            ...$listB,
        ];

        // Drop duplicate values when base and rows resolve to the same output
        $merged = $this->jsonLdAtIdDeduplication->dedupeResolvedPropertyList($merged);

        // Check if the merged value is an empty array
        if ($merged === []) {
            // Return null to signal no values are set and non-empty
            return null;
        }

        // Check if the merged value is a single value
        if (\count($merged) === 1) {
            // Return the first value
            /** @var string|int|float|bool|array<mixed>|null $singleMerged */
            $singleMerged = $merged[0];

            return $singleMerged;
        }

        // Return the merged value
        return $merged;
    }

    /**
     * Normalize a resolved property value to a flat list for additive merging.
     *
     * @param  mixed  $value  Scalar or list-shaped array
     * @return array<int, mixed> Flat list of values
     */
    protected function resolvedPropertyValueToList(
        mixed $value
    ): array {
        // Check if the value is an array and is a list
        if (
            \is_array($value)
            && array_is_list($value)
        ) {
            // Return the value as-is
            return $value;
        }

        // Return the value as a one-element array
        return [$value];
    }

    /**
     * Resolve a single replicator row to a value or list of values.
     *
     * Antlers row: parsed string. Thing row: array of node(s) or @id ref(s).
     * Boolean row: bool. Other rows: scalar or stringified value.
     *
     * @param  array<string, mixed>  $row  One replicator row (type, value, enabled)
     * @param  EntryContract|Term|null  $contextEntity  Current entry/term for Antlers context
     * @param  array<int, string>  $graphEntryIds  Entry ids already in the graph
     * @param  array<int, string>  $visitedIds  Entry ids being built (avoid cycles)
     * @param  array<int, string>  $emittedEntryIds  Entry ids for which a full node was already output (by reference)
     * @param  array<string, bool>  $emittedNodeIds  Node @id values already emitted (by reference)
     * @param  array<string, bool>  $materializedFullNodeAtIds  Full nodes known after shared dedupe (by reference)
     * @param  array<string, mixed>  $antlersContext  Optional Antlers context overrides
     * @param  SchemaTemplateInstanceWatermarkBag|null  $templateInstanceWatermarkBag  Shared template instance allocator
     * @return string|int|float|bool|array<string, mixed>|array<int, array<string, mixed>>|null
     */
    protected function resolveSingleReplicatorRowToValue(
        array $row,
        EntryContract|Term|null $contextEntity,
        array $graphEntryIds,
        array $visitedIds,
        array &$emittedEntryIds = [],
        array &$emittedNodeIds = [],
        array &$materializedFullNodeAtIds = [],
        array $antlersContext = [],
        ?SchemaTemplateInstanceWatermarkBag $templateInstanceWatermarkBag = null
    ): string|int|float|bool|array|null {
        $rowType = $row['type'] ?? null;
        $val = $row['value'] ?? null;

        if ($rowType === 'antlers' && \is_array($val) && isset($val['code']) && \is_string($val['code'])) {
            $template = trim($val['code']);
            if ($template === '') {
                // Unset local variables to free up memory
                unset(
                    $rowType,
                    $val
                );

                return null;
            }

            // Build context from merged values (includes computed collection fields)
            $baseContext = $this->buildAntlersContextFromEntity($contextEntity);

            // Shallow-copy so fillMissing placeholders do not mutate the memoized bag
            $context = $antlersContext !== []
                ? array_merge(
                    $baseContext,
                    $antlersContext
                )
                : array_merge($baseContext);

            // Nested replicator / grid / group `terms` and `terms_search_index` values are
            // not covered by {@see hydrateTermsLikeFieldValuesForSchemaAntlersContext} (it
            // only walks top-level blueprint field handles). Load term models for every
            // blueprint terms-like handle present in the merged Antlers context so
            // `{{ field }}` in nested `schema_template_node` is loopable and property output
            // does not trim to empty in {@see resolveSingleReplicatorRowToValue}.
            $termsLikeBlueprintFieldsGrouped = $this->collectTermsLikeBlueprintFieldsGroupedByHandle(
                $contextEntity
            );

            $termsLikeBlueprintFieldsGrouped = $this->filterGroupedTermsLikeBlueprintFieldsByAntlersTemplateReferences(
                $termsLikeBlueprintFieldsGrouped,
                $template
            );
            $termsLikePairStripHandles = array_keys(
                $termsLikeBlueprintFieldsGrouped
            );

            $context = $this->hydrateMergedContextTermsLikeFieldsIfRawSlugs(
                $contextEntity,
                $context,
                $termsLikeBlueprintFieldsGrouped
            );

            // Overlay keys such as replicator `activity` may hold bare term slugs;
            // when the Antlers snippet only references
            // `schema_primary_related_taxonomy_term`, blueprint-driven hydration
            // skips that handle, so resolve slug storage here before primary-term
            // injection (scoped by template references to avoid scanning every
            // overlay key on large snapshots).
            $context = $this->hydrateAntlersOverlayTermSlugValuesIfRaw(
                $context,
                $antlersContext,
                $template
            );

            unset(
                $termsLikeBlueprintFieldsGrouped
            );

            // Load the entries behind each `entries` field this snippet references, so
            // an `{{ field }} … {{ /field }}` pair iterates them instead of iterating
            // zero times over the query builder augmentation leaves (UAMS-Web/uams-statamic-archive#2637)
            $context = $this->hydrateReferencedEntriesFieldsForSchemaAntlersContext(
                $contextEntity,
                $context,
                $template
            );

            // Expose one taxonomy term from overlay keys so nested templates stay
            // handle-agnostic (no hardcoded replicator or taxonomy field names).
            $context = $this->injectSchemaPrimaryRelatedTaxonomyTermFromAntlersOverlay(
                $context,
                $antlersContext
            );

            // Expose shared emit maps so schema_template_node aligns with graph build
            $emitWatermarkBag = $templateInstanceWatermarkBag ?? new SchemaTemplateInstanceWatermarkBag;

            $emitContext = new SchemaTemplateEmitContext(
                $emittedEntryIds,
                $emittedNodeIds,
                $materializedFullNodeAtIds,
                $graphEntryIds,
                $contextEntity,
                $emitWatermarkBag
            );
            $context[self::ANTLERS_SCHEMA_TEMPLATE_EMIT_CONTEXT] = $emitContext;

            // Unset local variables to free up memory
            unset(
                $baseContext
            );

            SchemaTemplateEmitContextRuntimeStack::push($emitContext);

            try {
                // Add null placeholders for missing simple variables referenced in template
                $context = $this->fillMissingSimpleTemplateVariablesWithNull(
                    $template,
                    $context,
                    $termsLikePairStripHandles
                );

                $parseStart = $this->jsonLdCompositionProfiler->enabled()
                    ? microtime(true)
                    : null;

                $tplEntryId = null;
                $propKey = null;

                $tplEntryIdRaw = $context[self::ANTLERS_SCHEMA_TEMPLATE_ENTRY_ID] ?? null;
                $propKeyRaw = $context[self::ANTLERS_SCHEMA_PROPERTY_KEY] ?? null;

                if (\is_string($tplEntryIdRaw) && $tplEntryIdRaw !== '') {
                    $tplEntryId = $tplEntryIdRaw;
                }

                if (\is_string($propKeyRaw) && $propKeyRaw !== '') {
                    $propKey = $propKeyRaw;
                }

                // Parsed directly, with no memoization (UAMS-Web/uams-statamic-archive#1800). A request-scoped memo
                // wrapped this call until it was measured across four content types and
                // found to hit 3 times in 22,261 calls — 0.013%. Each parse site is
                // visited once per (template, property, instance index, entity) tuple,
                // so the key could not repeat; the context-entity term that made it
                // unique is load-bearing and cannot be relaxed without serving one
                // entity's rendered property as another's.
                $parsed = Parse::template(
                    $template,
                    $context,
                    [],
                    false
                );

                if (\is_float($parseStart)) {
                    $this->jsonLdCompositionProfiler->recordBucketSample(
                        'antlers_parse_calls',
                        'antlers_parse_ms',
                        (microtime(true) - $parseStart) * 1000
                    );

                    $this->jsonLdCompositionProfiler->recordAntlersParseSample(
                        (microtime(true) - $parseStart) * 1000,
                        $tplEntryId,
                        $propKey
                    );

                    unset($parseStart);
                }

                unset(
                    $propKey,
                    $propKeyRaw,
                    $tplEntryId,
                    $tplEntryIdRaw
                );
            } finally {
                SchemaTemplateEmitContextRuntimeStack::pop();
            }

            // `Parse::template()` always returns an AntlersString, so this is a plain
            // cast. It was an instanceof/is_string ladder while a memo wrapped the call
            // and could hand back `mixed` (UAMS-Web/uams-statamic-archive#1800).
            $out = (string) $parsed;

            // Unset local variables to free memory
            unset(
                $context,
                $parsed,
                $rowType,
                $template,
                $termsLikePairStripHandles,
                $val
            );

            // Trim surrounding whitespace from Antlers output
            $out = trim($out);

            // Omit output that was only whitespace/newlines
            if ($out === '') {
                // Unset local variables to free memory
                unset($out);

                // Return null to omit empty Antlers output
                return null;
            }

            // ---
            // Decode JSON object/array strings so JSON-LD gets structured values
            // ---

            // $out is non-empty and already trimmed, so the first byte is safe to read
            // and the old `is_string()` / re-trim guards are gone (UAMS-Web/uams-statamic-archive#1800).
            if (
                $out[0] === '['
                || $out[0] === '{'
            ) {
                // Decode the out as JSON
                $decoded = json_decode(
                    $out,
                    true
                );

                // Check if the decoded value is an array
                if (\is_array($decoded)) {
                    // Deduplicate decoded nodes by @id when applicable
                    /** @var string|int|float|bool|array<mixed>|null $decoded */
                    $decoded = $this->jsonLdAtIdDeduplication->dedupeWithSharedEmitMapAndDecodeFirstFullRetention(
                        $decoded,
                        $emittedNodeIds
                    );

                    return $decoded;
                }
            } elseif ($out[0] === '"') {
                // ---
                // Decode JSON Text documents (valid JSON whose top-level value is a string).
                // Tags such as `schema_pronounceable_text` intentionally `json_encode` plain
                // Text so Antlers output is valid JSON; only `{` / `[` were handled above, so
                // without this pass the literal quotes survive and the graph `json_encode`
                // double-escapes (`"givenName": "\"Kaitlin\""`).
                // ---
                try {
                    $decodedScalar = json_decode(
                        $out,
                        true,
                        512,
                        JSON_THROW_ON_ERROR
                    );

                    if (\is_string($decodedScalar)) {
                        return $decodedScalar;
                    }
                } catch (\JsonException) {
                    // Leave Antlers output unchanged when not valid JSON Text
                }
            }

            // ---
            // Wrap @id reference strings (e.g. from get_schema_slot) as {"@id": "..."} for
            // JSON-LD
            // ---
            if ($out[0] === '#') {
                $entryId = substr($out, 1);
                if ($entryId !== '') {
                    $fullId = $this->entryIdToNodeId(
                        $entryId,
                        $contextEntity,
                        $graphEntryIds,
                        $visitedIds
                    );

                    return ['@id' => $fullId];
                }
            }

            return $out;
        }

        if (\is_string($rowType) && str_starts_with($rowType, 'thing_') && $val !== null && $val !== '') {
            $entryIds = \is_string($val)
                ? [$val]
                : array_values(
                    array_filter(
                        \is_array($val) ? $val : [],
                        fn ($id): bool => \is_string($id) && $id !== ''
                    )
                );
            $resolved = [];
            foreach ($entryIds as $refId) {
                if (
                    \in_array($refId, $graphEntryIds, true)
                    || \in_array($refId, $visitedIds, true)
                    || \in_array($refId, $emittedEntryIds, true)
                ) {
                    $resolved[] = [
                        '@id' => $this->entryIdToNodeId(
                            $refId,
                            $contextEntity,
                            $graphEntryIds,
                            $visitedIds
                        ),
                    ];

                    continue;
                }

                $childNode = $this->schemaObjectEntryToNode(
                    $refId,
                    $contextEntity,
                    $graphEntryIds,
                    $visitedIds,
                    $emittedEntryIds,
                    $emittedNodeIds,
                    $materializedFullNodeAtIds,
                    $antlersContext,
                    $templateInstanceWatermarkBag
                );
                if ($childNode !== null) {
                    // Register inline thing_* child @id for later compact-to-@id fast paths
                    $this->schemaObjectEmissionCoordinator->recordFullSchemaObjectNode(
                        $emittedNodeIds,
                        $childNode
                    );
                    // Track entry ids only when a full inline node was emitted so
                    // `{"@id":…}` placeholders do not block a later full build.
                    if (
                        isset($childNode['@type'])
                        && ! \in_array($refId, $emittedEntryIds, true)
                    ) {
                        $emittedEntryIds[] = $refId;
                    }

                    $resolved[] = $childNode;
                }
            }

            // Unset local variables to free up memory
            unset(
                $childNode,
                $entryIds,
                $refId,
                $rowType,
                $val
            );

            return $resolved !== [] ? $resolved : null;
        }

        if ($val === null || $val === '') {
            // Unset local variables to free up memory
            unset(
                $rowType,
                $val
            );

            return null;
        }

        // ---
        // Return array as-is (e.g., enumeration multi-select) so JSON-LD gets a real array
        // ---

        // Check if the value is an array and is a list
        if (
            \is_array($val)
            && array_is_list($val)
        ) {
            // Unset variables that are no longer needed
            unset(
                $rowType,
            );

            // Return the value as-is
            return $val;
        }

        $out = \is_scalar($val) ? $val : (string) json_encode($val);

        // Unset local variables to free up memory
        unset(
            $rowType,
            $val
        );

        return $out;
    }

    /**
     * Remove `schema_primary_related_taxonomy_term` and
     * `schema_primary_related_taxonomy_term.<field>` tag-pair bodies so
     * {@see fillMissingSimpleTemplateVariablesWithNull} does not treat inner
     * `{{ handle }}` tokens as parent-scope variables.
     */
    private function stripSchemaPrimaryRelatedTaxonomyTermPairBodiesForFillMissingAnalysis(
        string $template
    ): string {
        $handle = preg_quote(self::ANTLERS_SCHEMA_PRIMARY_RELATED_TAXONOMY_TERM, '/');

        // Strip `schema_primary_related_taxonomy_term.<field>` pairs so inner
        // `{{ title }}` tokens are not treated as parent-scope variables.
        $stripped = (string) preg_replace(
            '/\{\{\s*'.$handle.'\.([a-z0-9_]+)\s*\}\}.*?\{\{\s*\/\s*'.$handle.'\.\1\s*\}\}/s',
            '',
            $template
        );

        return (string) preg_replace(
            '/\{\{\s*'.$handle.'\s*\}\}.*?\{\{\s*\/\s*'.$handle.'\s*\}\}/s',
            '',
            $stripped
        );
    }

    /**
     * Request-memoized regex for stripping one terms-like Antlers pair body (same
     * pattern repeated for many blueprint handles on large templates).
     */
    private function pairStripRegexPatternForTermsLikeHandle(string $termsHandle): string
    {
        if (
            ! preg_match(
                '/^[a-zA-Z_]\w*$/',
                $termsHandle
            )
        ) {
            return '';
        }

        /** @var string $pattern */
        $pattern = RequestAttributeMemo::remember(
            self::REQUEST_TERMS_LIKE_PAIR_STRIP_PATTERN_MEMO,
            $termsHandle,
            function () use ($termsHandle): string {
                $quotedHandle = preg_quote(
                    $termsHandle,
                    '/'
                );

                return '/\{\{\s*'.$quotedHandle.'\s*\}\}.*?\{\{\s*\/\s*'.$quotedHandle.'\s*\}\}/s';
            }
        );

        return $pattern;
    }

    /**
     * Remove bodies of `{{ terms_field }} … {{ /terms_field }}` pairs for terms-like
     * blueprint handles so inner variables (for example `schema_object` on taxonomy
     * terms) are not pre-filled on the parent context by
     * {@see fillMissingSimpleTemplateVariablesWithNull}.
     *
     * @param  list<string>  $termsLikePairStripHandles  Terms / terms_search_index field handles
     */
    private function stripTermsLikeFieldPairBodiesForFillMissingAnalysis(
        string $template,
        array $termsLikePairStripHandles
    ): string {
        $stripped = $template;

        foreach ($termsLikePairStripHandles as $termsHandle) {
            if ($termsHandle === '') {
                unset($termsHandle);

                continue;
            }

            $pattern = $this->pairStripRegexPatternForTermsLikeHandle($termsHandle);

            if ($pattern === '') {
                unset($termsHandle);

                continue;
            }

            $stripped = (string) preg_replace(
                $pattern,
                '',
                $stripped
            );

            unset(
                $pattern,
                $termsHandle
            );
        }

        return $stripped;
    }

    /**
     * Fill missing simple Antlers variables referenced by a template with null.
     *
     * This prevents unresolved simple variables (for example, {{ caption }}) from
     * falling back to ambient parse scope outside the explicitly provided context.
     * Branch tests (for example {{ if email }}) register the condition variable so
     * the parent scope is not used for that key.
     *
     * @param  string  $template  Antlers template string.
     * @param  array<string, mixed>  $context  Parse context.
     * @param  list<string>  $termsLikePairStripHandles  Nested terms-field tag handles to strip
     * @return array<string, mixed> Context with null placeholders for missing keys.
     */
    private function fillMissingSimpleTemplateVariablesWithNull(
        string $template,
        array $context,
        array $termsLikePairStripHandles = [],
    ): array {
        // Strip bodies of `schema_primary_related_taxonomy_term` tag pairs before
        // scanning for simple {{ handle }} tokens. Otherwise handles like `audience`
        // (present on the related term but absent on the parent entry) receive
        // `null` placeholders and shadow the term scope during Parse::template.
        $templateForVariableScan = $this->stripSchemaPrimaryRelatedTaxonomyTermPairBodiesForFillMissingAnalysis(
            $template
        );

        $templateForVariableScan = $this->stripTermsLikeFieldPairBodiesForFillMissingAnalysis(
            $templateForVariableScan,
            $termsLikePairStripHandles,
        );

        // Match piped simple variable names (for example: {{ caption | sanitize }}).
        preg_match_all(
            '/\{\{\s*([a-zA-Z_]\w*)\s*\|/',
            $templateForVariableScan,
            $pipeMatches
        );

        // Match direct simple variable names (for example: {{ caption }}).
        preg_match_all(
            '/\{\{\s*([a-zA-Z_]\w*)\s*\}\}/',
            $templateForVariableScan,
            $directMatches
        );

        // Match dot-path variable names and capture the root segment (for example:
        // first_name.text => first_name).
        preg_match_all(
            '/([a-zA-Z_]\w*)\.\w+/',
            $templateForVariableScan,
            $dotPathMatches
        );

        // Match schema relationship tag field-handle params (for example:
        // current_field="credentials|other" => credentials, other).
        preg_match_all(
            '/\b(?:current_field|secondary_field|tertiary_field|global_field)\s*=\s*["\']([^"\']+)["\']/',
            $templateForVariableScan,
            $fieldHandleParamMatches
        );

        // Match simple branch variables (for example: {{ if email }}, {{ unless fax }}).
        preg_match_all(
            '/\{\{\s*(?:if|elseif|unless)\s+(?:!\s*)?([a-zA-Z_]\w*)/',
            $templateForVariableScan,
            $conditionalMatches
        );

        // Track dot-path root variables so we can block nested fallback explicitly.
        $dotPathRoots = array_values(
            array_unique(
                $dotPathMatches[1]
            )
        );

        // Expand pipe-delimited field-handle params into explicit placeholder keys.
        $fieldHandleParamVariables = [];

        // Walk each matched field-handle param value.
        foreach ($fieldHandleParamMatches[1] as $handlesRaw) {
            // Split on pipe so tag syntax like `foo|bar` yields two handles.
            $handles = array_map(
                trim(...),
                explode(
                    '|',
                    $handlesRaw
                )
            );

            // Merge each non-empty handle into the placeholder list.
            foreach ($handles as $handle) {
                if ($handle !== '') {
                    $fieldHandleParamVariables[] = $handle;
                }
            }

            // Unset loop locals to free memory.
            unset(
                $handle,
                $handles,
                $handlesRaw
            );
        }

        // Initialize reserved Antlers keywords that should not be treated as variables.
        $reservedKeywords = [
            'if',
            'elseif',
            'else',
            'unless',
            'switch',
            'case',
            'default',
            'true',
            'false',
            'null',
            'and',
            'or',
            'not',
        ];

        // Normalize matched variable names to a unique list.
        $variableNames = array_values(
            array_unique(
                array_merge(
                    $pipeMatches[1],
                    $directMatches[1],
                    $dotPathRoots,
                    $conditionalMatches[1],
                    $fieldHandleParamVariables
                )
            )
        );

        // Resolve the current context entity once for field-handle safety checks.
        $contextEntity = null;
        $contextEntityId = data_get($context, 'id');
        if (\is_string($contextEntityId) && $contextEntityId !== '') {
            $contextEntity = Data::find($contextEntityId);
        }

        // Add placeholders only for variables missing from explicit context.
        foreach ($variableNames as $variableName) {
            // Check whether the matched token is a reserved keyword.
            if (\in_array($variableName, $reservedKeywords, true)) {
                // Skip reserved tokens.
                continue;
            }

            // Prevent leaked relationship-field values from surviving into tag evaluation.
            // Terms-like blueprint handles (`organization`, `specialty`, etc.) frequently
            // exist only on merged replicator / tag overlay scope—not as top-level entry
            // fields—so do not coerce them to false when schema relationship param scans
            // mention the same handle name.
            if (
                \in_array($variableName, $fieldHandleParamVariables, true)
                && \array_key_exists($variableName, $context)
                && $contextEntity instanceof EntryModel
                && ! \in_array($variableName, $termsLikePairStripHandles, true)
            ) {
                // Read the entity's stored value for the field handle.
                $entityValue = $contextEntity->get($variableName);

                // Clear the context value when the entity does not define it.
                if (
                    in_array($entityValue, [null, [], ''], true)
                ) {
                    $context[$variableName] = false;
                }

                // Unset local variables to free up memory
                unset(
                    $entityValue
                );
            }

            // Check whether the variable name is missing from context.
            if (! \array_key_exists($variableName, $context)) {
                // Set missing dot-path roots to an empty string to block nested fallback
                // while staying safe for string modifiers like strip_tags.
                if (\in_array($variableName, $dotPathRoots, true)) {
                    $context[$variableName] = '';
                } elseif (\in_array($variableName, $fieldHandleParamVariables, true)) {
                    // Set missing relationship-field handles to an empty list to block
                    // ambient fallback while remaining "no targets" for relationship tags.
                    $context[$variableName] = false;
                } else {
                    // Set missing simple variables to null to block ambient scope fallback.
                    $context[$variableName] = null;
                }
            }
        }

        // Unset local variables to free memory.
        unset(
            $conditionalMatches,
            $contextEntity,
            $contextEntityId,
            $directMatches,
            $fieldHandleParamMatches,
            $fieldHandleParamVariables,
            $dotPathMatches,
            $dotPathRoots,
            $pipeMatches,
            $reservedKeywords,
            $variableName,
            $variableNames
        );

        // Return updated context.
        return $context;
    }

    /**
     * If the property value is the Antlers replicator set, parse template and return
     * string.
     *
     * @param  array<int, array<string, mixed>>  $rows  Replicator rows for the property
     * @param  EntryContract|Term|null  $contextEntity  Current entry/term for Antlers context
     * @return string|null Parsed string, or null if not an Antlers set or no template
     */
    protected function resolveAntlersPropertyValue(
        array $rows,
        EntryContract|Term|null $contextEntity
    ): ?string {
        foreach ($rows as $row) {
            $enabled = $row['enabled'] ?? true;
            if (! $enabled) {
                continue;
            }

            $rowType = $row['type'] ?? null;
            if ($rowType !== 'antlers') {
                return null;
            }

            $val = $row['value'] ?? null;
            if (! \is_array($val) || ! isset($val['code']) || ! \is_string($val['code'])) {
                return null;
            }

            $template = trim($val['code']);
            if ($template === '') {
                return null;
            }

            // Build context from merged values (includes computed collection fields)
            $context = array_merge(
                $this->buildAntlersContextFromEntity($contextEntity)
            );

            $parsed = Parse::template(
                $template,
                $context,
                [],
                false
            );

            $out = (string) $parsed;

            // Unset local variables to free up memory
            unset(
                $context,
                $parsed,
                $row,
                $rowType,
                $template,
                $val
            );

            return $out;
        }

        // Unset local variables to free up memory
        unset(
            $row
        );

        return null;
    }

    /**
     * Build the Antlers variable bag for an entry or term.
     *
     * Build context from merged `values()` / `data()` (includes computed fields)
     * plus targeted augmentation for Link and similar fieldtypes. Full
     * `toEvaluatedAugmentedArray()` is avoided so heavy fieldtypes (for example
     * `schema_object`) are not evaluated while assembling schema Antlers scope.
     *
     * @param  EntryContract|Term|null  $contextEntity  Source entry or term
     * @return array<string, mixed> Variables for Antlers parsing
     */
    private function buildAntlersContextFromEntity(
        EntryContract|Term|null $contextEntity
    ): array {
        // Return an empty bag when there is no entity
        if ($contextEntity === null) {
            return [];
        }

        // ---
        // Reuse or materialize the flattened values/terms/url bag once per entity per
        // request
        // ---
        $memoKey = $this->antlersEntityContextCacheKey($contextEntity);

        $resolved = RequestAttributeMemo::remember(
            self::REQUEST_ANTLERS_ENTITY_CONTEXT_CACHE,
            $memoKey,
            fn (): array => $this->materializeAntlersContextFromEntity($contextEntity)
        );

        if (! \is_array($resolved)) {
            // Unset local variables to free up memory
            unset(
                $memoKey,
                $resolved
            );

            return [];
        }

        // Unset local variables to free up memory
        unset(
            $memoKey
        );

        /** @var array<string, mixed> $resolved */
        return $resolved;
    }

    /**
     * Build the Antlers variable bag for an entry or term without request memoization.
     *
     * Merges `values()` / `data()`, hydrates terms-like fields, then overlays
     * Statamic augmented values for every blueprint field except blocked types
     * (e.g., `schema_object`) so nested computed subfields match normal Antlers.
     *
     * @param  EntryContract|Term  $contextEntity  Source entry or term
     * @return array<string, mixed> Variables for Antlers parsing
     */
    private function materializeAntlersContextFromEntity(
        EntryContract|Term $contextEntity
    ): array {
        // Read merged raw values (includes computed collection fields)
        $context = $this->extractRawMergedValuesBagFromEntity($contextEntity);

        // Return an empty array when no variable bag is available
        if ($context === null) {
            return [];
        }

        // Overlay augmented field values (fieldtype-agnostic; skip recursion-prone types)
        if ($contextEntity instanceof Augmentable) {
            $context = $this->overlayAugmentedFieldTypeValuesForSchemaAntlersContext(
                $contextEntity,
                $context
            );
        }

        // ---
        // Replace raw terms field storage (or query builders from augmentation) with Term
        // collections
        // ---
        $context = $this->hydrateTermsLikeFieldsIfSupportedForSchemaAntlersContext(
            $contextEntity,
            $context
        );

        // Merge canonical URL keys for schema Antlers (e.g. `{{ permalink }}`)
        $context = $this->applyScopedEntryAliasToSchemaAntlersContext(
            $this->mergeCanonicalUrlKeysIntoSchemaAntlersContext(
                $contextEntity,
                $context
            ),
            $contextEntity
        );

        // Expose the podcast-episode gate for audio entries (UAMS-Web/uams-statamic-archive#1611)
        return $this->injectAudioSeriesPodcastSignal(
            $context,
            $contextEntity
        );
    }

    /**
     * Set the `is_podcast_episode` Antlers gate from an audio entry's `series`.
     *
     * A podcast episode is an audio entry whose selected `series` (`audio_series`)
     * term declares the `podcast` subtype (UAMS-Web/uams-statamic-archive#1611). Computing it here — once per
     * entity, memoized by {@see buildAntlersContextFromEntity} — lets the shared
     * audio schema templates gate podcast-only properties on
     * `{{ if is_podcast_episode }}` without depending on terms-field augmentation
     * (which does not hydrate under synthetic in-memory fixtures). A term context
     * carries no `series` field, so the gate is only injected for entries.
     *
     * @param  array<string, mixed>  $context  The built Antlers variable bag
     * @param  EntryContract|Term  $contextEntity  Source entry or term
     * @return array<string, mixed> Context with the gate set for entry contexts
     */
    private function injectAudioSeriesPodcastSignal(
        array $context,
        EntryContract|Term $contextEntity
    ): array {
        // Only an entry context can be an audio series; a term never gates, and
        // {@see AudioSeries::entrySeriesHasSubtype} itself returns false unless the
        // entry's `series` field targets `audio_series` (the field is shared with
        // the video collections), so this is a cheap no-op for non-audio entries.
        if ($contextEntity instanceof EntryModel) {
            $context[self::ANTLERS_IS_PODCAST_EPISODE] = AudioSeries::entrySeriesHasSubtype(
                $contextEntity,
                AudioSeries::SUBTYPE_PODCAST
            );
        }

        return $context;
    }

    /**
     * Read merged field values from the entity without evaluating every fieldtype.
     *
     * @param  EntryContract|Term  $contextEntity  Source entry or term
     * @return array<string, mixed>|null Flattened values, or null when unavailable
     */
    private function extractRawMergedValuesBagFromEntity(
        EntryContract|Term $contextEntity
    ): ?array {
        // Prefer Statamic merged values() when present (origin + computed data)
        if (method_exists($contextEntity, 'values')) {
            // Read the merged values collection or bag
            $values = $contextEntity->values();

            // Check if values expose an all() list
            if (
                $values !== null
                && \is_object($values)
                && method_exists($values, 'all')
            ) {
                // Flatten to a plain array for Antlers variables
                /** @var array<string, mixed> $out */
                $out = $values->all();

                // Unset local variables to free memory
                unset(
                    $values
                );

                return $out;
            }

            // Unset local variables to free memory
            unset(
                $values
            );
        }

        // Fall back to raw data() when values() is unavailable
        if (method_exists($contextEntity, 'data')) {
            // Read the raw data collection
            $data = $contextEntity->data();

            // Check if data expose an all() list
            if (
                $data !== null
                && \is_object($data)
                && method_exists($data, 'all')
            ) {
                // Flatten to a plain array for Antlers variables
                /** @var array<string, mixed> $out */
                $out = $data->all();

                // Unset local variables to free memory
                unset(
                    $data
                );

                return $out;
            }

            // Unset local variables to free memory
            unset(
                $data
            );
        }

        // Signal that no variable source was found
        return null;
    }

    /**
     * Replace raw storage with Statamic augmented values for blueprint fields.
     *
     * @param  Augmentable  $contextEntity  Entry or localized term with augmentation
     * @param  array<string, mixed>  $context  Variables from values() / data()
     * @return array<string, mixed> Context with overlay handles replaced when present
     */
    private function overlayAugmentedFieldTypeValuesForSchemaAntlersContext(
        Augmentable $contextEntity,
        array $context
    ): array {
        // Skip when the entity has no blueprint (partial test doubles)
        if (! method_exists($contextEntity, 'blueprint')) {
            return $context;
        }

        // Resolve the blueprint for field type inspection
        $blueprint = $contextEntity->blueprint();

        // Return unchanged when blueprint is missing
        if ($blueprint === null) {
            return $context;
        }

        /** @var Blueprint $blueprint */
        $blueprintFields = $blueprint->fields()->all();

        // Walk blueprint fields and overlay augmented values (skip recursion-prone types)
        foreach ($blueprintFields as $field) {
            /** @var Field $field */
            // Read the field type handle
            $type = $field->type();

            // Skip fieldtypes that must keep raw storage in schema Antlers context
            if (\in_array($type, self::SCHEMA_ANTLERS_CONTEXT_SKIP_AUGMENTATION_FIELD_TYPES, true)) {
                unset(
                    $field,
                    $type
                );

                continue;
            }

            // Read the field handle
            $rawHandle = $field->handle();
            $handle = \is_scalar($rawHandle) ? (string) $rawHandle : '';

            // Initialize overlay value placeholder for safe unset after try/catch
            $valueObj = null;

            // Attempt to augment this handle and replace the raw context entry
            try {
                // Resolve the augmented field Value from Statamic
                $valueObj = $contextEntity->augmentedValue($handle);

                // Replace raw storage when augmentation returns a Value instance
                if ($valueObj instanceof Value) {
                    // Read the evaluated augmented PHP value (URL, models, etc.)
                    $resolved = $valueObj->value();

                    // Normalize Link fields to absolute URLs for JSON-LD (ArrayableLink
                    // string-casts to relative paths for same-site entries by default)
                    if ($type === 'link') {
                        $resolved = $this->normalizeLinkAugmentedValueForSchemaJsonLd(
                            $resolved
                        );
                    }

                    // Write the overlay value into the Antlers context bag
                    $context[$handle] = $resolved;

                    // Unset local variables to free memory
                    unset(
                        $resolved
                    );
                }
            } catch (\Throwable) {
                // Keep the raw context value when augmentation fails
            }

            // Unset local variables to free memory
            unset(
                $field,
                $rawHandle,
                $handle,
                $type,
                $valueObj
            );
        }

        // Unset local variables to free memory
        unset(
            $blueprint
        );

        return $context;
    }

    /**
     * Convert augmented Link field values to absolute URL strings for schema output.
     *
     * Statamic's ArrayableLink casts to the target entry's relative `url()` by
     * default. JSON-LD and canonical `@id` usage expect absolute URLs when the
     * link resolves to an entry, asset, or site-relative path.
     *
     * @param  mixed  $resolved  Value from `Value::value()` for a link field
     * @return mixed Absolute URL string when applicable, or the original value
     */
    private function normalizeLinkAugmentedValueForSchemaJsonLd(mixed $resolved): mixed
    {
        // Pass through non-Link augmented shapes unchanged
        if (! $resolved instanceof ArrayableLink) {
            return $resolved;
        }

        // Read the resolved redirect target (entry, asset, or scalar URL)
        $inner = $resolved->value();

        // Prefer the entry's absolute URL when the link targets a routable entry
        // (concrete Entry/Page: Statamic's Entry contract omits url/absoluteUrl)
        if ($inner instanceof EntryModel || $inner instanceof Page) {
            // Resolve the canonical absolute front URL for the linked entry or page
            $absolute = $inner->absoluteUrl();

            if (\is_string($absolute) && $absolute !== '') {
                unset(
                    $inner,
                    $resolved
                );

                return $absolute;
            }

            // Fall back to the relative URL string when absolute is unavailable
            $url = $resolved->url();
            $fallback = \is_scalar($url) ? (string) $url : '';

            unset(
                $inner,
                $resolved,
                $url
            );

            return $fallback;
        }

        // Prefer the asset's absolute URL when the link targets an asset
        if ($inner instanceof StatamicAsset) {
            // Resolve the asset's public absolute URL when exposed
            $absolute = $inner->absoluteUrl();

            if (\is_string($absolute) && $absolute !== '') {
                unset(
                    $inner,
                    $resolved
                );

                return $absolute;
            }

            // Fall back to the container-relative URL when absolute is unavailable
            $url = $resolved->url();
            $fallback = \is_scalar($url) ? (string) $url : '';

            unset(
                $inner,
                $resolved,
                $url
            );

            return $fallback;
        }

        unset(
            $inner
        );

        // Read the string URL from the ArrayableLink (plain URL or relative path)
        $rawUrl = $resolved->url();
        $url = \is_scalar($rawUrl) ? (string) $rawUrl : '';

        unset(
            $rawUrl,
            $resolved
        );

        // Return empty strings unchanged
        if ($url === '') {
            return $url;
        }

        // Keep already-absolute HTTP(S) URLs unchanged
        if (
            str_starts_with($url, 'http://')
            || str_starts_with($url, 'https://')
        ) {
            return $url;
        }

        // Resolve root-relative paths against the current site (same as permalink)
        if (str_starts_with($url, '/')) {
            // Ask Statamic to prepend the active site URL
            $madeAbsolute = URL::makeAbsolute($url);

            if ($madeAbsolute !== '') {
                // Unset local variables to free up memory
                unset(
                    $url
                );

                return $madeAbsolute;
            }
        }

        return $url;
    }

    /**
     * Stable cache key for memoizing Antlers context bags per entity.
     *
     * @param  EntryContract|Term  $contextEntity  Source entry or term
     * @return string Cache key (entry id when available)
     */
    private function antlersEntityContextCacheKey(
        EntryContract|Term $contextEntity
    ): string {
        // Prefer Statamic id strings for stable cross-request semantics
        if (method_exists($contextEntity, 'id')) {
            try {
                $id = $contextEntity->id();

                if (\is_string($id) && $id !== '') {
                    return $id;
                }
            } catch (\Throwable) {
                // Partial test doubles may omit id(); fall back to object identity
            }
        }

        // Fall back to object identity when no id is exposed, PINNED so the id
        // cannot be recycled underneath this memo (UAMS-Web/uams-statamic-archive#1967). Skipping the memo here
        // was the wrong fix — it exists to avoid a recompose on a path measured at
        // 12.5s versus 1.6s (UAMS-Web/uams-statamic-archive#1842) — and UAMS-Web/uams-statamic-archive#1909's decline-to-cache answer suited a
        // persisted cache, not this one. See {@see REQUEST_OBJECT_IDENTITY_PIN} and
        // the reference-holding memo at
        // {@see memoizedHydrateRawTermSlugValueToAugmentedValue()}.
        return $this->pinnedObjectIdentityKey($contextEntity);
    }

    /**
     * Identity key for an object that cannot alias a recycled id (UAMS-Web/uams-statamic-archive#1967).
     *
     * `spl_object_id()` is unique only among **live** objects, so a memo keyed on
     * a bare id serves one entity's row to another the moment the first is freed
     * and its id reissued. Pinning the object in a request-scoped bag keeps it
     * alive for exactly as long as any memo keyed on it can be read, which makes
     * the collision impossible rather than merely unlikely.
     *
     * When no request is bound, {@see RequestAttributeMemo::remember()} stores
     * nothing — but it also stores nothing for the memos this keys, so there is no
     * memo left to alias and the two stay consistent.
     *
     * @param  object  $subject  Object whose identity keys a request-scoped memo
     * @return string Memo key that is stable and collision-free for this request
     */
    private function pinnedObjectIdentityKey(object $subject): string
    {
        $identity = spl_object_id($subject);

        // Hold the reference. The return value is deliberately unused: the point
        // is the row this leaves behind in the request bag, not what it hands back.
        RequestAttributeMemo::remember(
            self::REQUEST_OBJECT_IDENTITY_PIN,
            (string) $identity,
            static fn (): object => $subject
        );

        return 'o'.$identity;
    }

    /**
     * Add `entry` so schema relationship resolution can anchor field reads when
     * Statamic `values()->all()` omits top-level `id`.
     *
     * @param  array<string, mixed>  $context  Flattened field bag.
     * @param  EntryContract|Term|null  $contextEntity  Source entity.
     * @return array<string, mixed> Context including `entry` when applicable.
     */
    private function applyScopedEntryAliasToSchemaAntlersContext(
        array $context,
        EntryContract|Term|null $contextEntity
    ): array {
        if ($contextEntity instanceof EntryContract) {
            $context['entry'] = $contextEntity;
        }

        return $context;
    }

    /**
     * Restrict merged hydration to terms-like handles referenced by this Antlers
     * snippet, avoiding blueprint-wide augment work when the merged context carries
     * many keys.
     *
     * Falls back to the full grouped map when extraction finds nothing (conservative).
     *
     * @param  array<string, list<Field>>  $groupedTermsLikeBlueprintFieldsByHandle
     * @return array<string, list<Field>>
     */
    private function filterGroupedTermsLikeBlueprintFieldsByAntlersTemplateReferences(
        array $groupedTermsLikeBlueprintFieldsByHandle,
        string $template
    ): array {
        if ($groupedTermsLikeBlueprintFieldsByHandle === []) {
            return [];
        }

        $referenced = $this->antlersTemplateReferencedIdentifierSet($template);

        if ($referenced === []) {
            return $groupedTermsLikeBlueprintFieldsByHandle;
        }

        /** @var array<string, list<Field>> $filtered */
        $filtered = [];

        foreach ($groupedTermsLikeBlueprintFieldsByHandle as $handle => $candidates) {
            if (
                $handle !== ''
                && isset($referenced[$handle])
            ) {
                $filtered[$handle] = $candidates;
            }

            unset(
                $candidates,
                $handle
            );
        }

        if ($filtered === []) {
            return $groupedTermsLikeBlueprintFieldsByHandle;
        }

        return $filtered;
    }

    /**
     * Replace the query builder of each referenced `entries` field with its entries.
     *
     * Augmentation leaves an `entries` field without `max_items: 1` as a query
     * builder, and an Antlers pair over a builder in this pipeline iterates zero
     * times. The `terms` equivalent is
     * {@see hydrateTermsLikeFieldValuesForSchemaAntlersContext()}.
     *
     * Only fields the snippet names are loaded, and there is no fallback to every
     * field when it names none: an `entries` field can hold many entries, and
     * loading ones nothing reads is the cost UAMS-Web/uams-statamic-archive#937 measured. A value that is not a
     * builder is left alone, so a `max_items: 1` field keeps its single entry for
     * `{{ field:title }}`.
     *
     * @param  EntryContract|Term|null  $contextEntity  Entity whose blueprint names the fields
     * @param  array<string, mixed>  $context  Antlers context for this snippet
     * @param  string  $template  The Antlers snippet about to be parsed
     * @return array<string, mixed> Context with referenced entries fields loaded
     */
    private function hydrateReferencedEntriesFieldsForSchemaAntlersContext(
        EntryContract|Term|null $contextEntity,
        array $context,
        string $template
    ): array {
        // Skip a context without an entity, whose blueprint names no fields
        if ($contextEntity === null) {
            return $context;
        }

        $entriesHandles = $this->collectEntriesFieldHandles($contextEntity);

        // Skip an entity whose blueprint has no entries field
        if ($entriesHandles === []) {
            return $context;
        }

        $referenced = $this->antlersTemplateReferencedIdentifierSet($template);

        foreach ($entriesHandles as $handle) {
            $present = $context[$handle] ?? null;

            // Skip a field the snippet does not name, or one that is not a builder
            if (
                ! isset($referenced[$handle])
                || ! Compare::isQueryBuilder($present)
            ) {
                unset(
                    $handle,
                    $present
                );

                continue;
            }

            /** @var Builder $present */
            // Run the field's query once per builder for the request
            $entries = RequestAttributeMemo::remember(
                self::REQUEST_ENTRIES_FIELD_HYDRATION_MEMO,
                $this->pinnedObjectIdentityKey($present),
                static fn (): mixed => $present->get()
            );

            // Keep the builder when its query did not return a collection
            if (! $entries instanceof Collection) {
                unset(
                    $entries,
                    $handle,
                    $present
                );

                continue;
            }

            $context[$handle] = $entries;

            unset(
                $entries,
                $handle,
                $present
            );
        }

        unset(
            $entriesHandles,
            $referenced
        );

        return $context;
    }

    /**
     * Handles of the top-level `entries` fields on an entity's blueprint.
     *
     * @param  EntryContract|Term  $contextEntity  Entity whose blueprint is read
     * @return list<string> The handles, empty when the entity has no blueprint
     */
    private function collectEntriesFieldHandles(
        EntryContract|Term $contextEntity
    ): array {
        /** @var list<string> $handles */
        $handles = RequestAttributeMemo::remember(
            self::REQUEST_ENTRIES_FIELD_HANDLES_CACHE,
            $this->antlersEntityContextCacheKey($contextEntity),
            function () use ($contextEntity): array {
                // Skip an entity that exposes no blueprint (partial test doubles)
                if (! method_exists($contextEntity, 'blueprint')) {
                    return [];
                }

                $blueprint = $contextEntity->blueprint();

                // Skip an entity whose blueprint is missing
                if (! $blueprint instanceof Blueprint) {
                    return [];
                }

                /** @var list<string> $entriesHandles */
                $entriesHandles = [];

                foreach ($blueprint->fields()->all() as $field) {
                    /** @var Field $field */
                    // Keep the handle of every top-level entries field
                    if ($field->type() === 'entries') {
                        $entriesHandles[] = $field->handle();
                    }

                    unset($field);
                }

                return $entriesHandles;
            }
        );

        return $handles;
    }

    /**
     * Collect probable Antlers identifiers (`{{ handle }}`, `{{ if handle }}`, …).
     *
     * @return array<string, true>
     */
    private function antlersTemplateReferencedIdentifierSet(string $template): array
    {
        /** @var array<string, true> $identifiers */
        $identifiers = [];

        /** @var list<string> $patterns */
        $patterns = [
            '/\{\{\s*([a-zA-Z_]\w*)\s*\}\}/',
            '/\{\{\s*([a-zA-Z_]\w*)\s*\|/',
            '/\{\{\s*\/\s*([a-zA-Z_]\w*)\s*\}\}/',
            '/\{\{\s*(?:if|unless)\s+([a-zA-Z_]\w*)\s*\}\}/',
            '/\{\{\s*(?:elseif|else_if)\s+([a-zA-Z_]\w*)\s*\}\}/',
        ];

        foreach ($patterns as $pattern) {
            if (
                preg_match_all(
                    $pattern,
                    $template,
                    $matches
                )
            ) {
                foreach ($matches[1] as $identifier) {
                    if ($identifier !== '') {
                        $identifiers[$identifier] = true;
                    }

                    unset($identifier);
                }
            }

            unset(
                $matches,
                $pattern
            );
        }

        /** @var array<string, true> $reserved */
        $reserved = [
            'true' => true,
            'false' => true,
            'null' => true,
            'if' => true,
            'elseif' => true,
            'else_if' => true,
            'else' => true,
            'unless' => true,
            'endif' => true,
            'endunless' => true,
        ];

        foreach (array_keys($identifiers) as $identifier) {
            if (isset($reserved[$identifier])) {
                unset($identifiers[$identifier]);
            }

            unset($identifier);
        }

        return $identifiers;
    }

    /**
     * For every Statamic "terms" fieldtype (including {@see TermsFieldtype} subclasses
     * such as `terms_search_index`) in the context entity's blueprint, plus nested
     * replicator / grid / group children, if that field handle is present in the
     * merged Antlers context, ensure raw slug storage is turned into a loopable model
     * collection.
     *
     * Top-level only: {@see hydrateTermsLikeFieldValuesForSchemaAntlersContext}.
     * Plain slugs: {@see termCollectionForPlainOrQualifiedTermSlugs}.
     *
     * @param  array<string, list<Field>>  $groupedTermsLikeBlueprintFieldsByHandle
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function hydrateMergedContextTermsLikeFieldsIfRawSlugs(
        EntryContract|Term|null $contextEntity,
        array $context,
        array $groupedTermsLikeBlueprintFieldsByHandle
    ): array {
        if ($groupedTermsLikeBlueprintFieldsByHandle === []) {
            return $context;
        }

        foreach ($groupedTermsLikeBlueprintFieldsByHandle as $handle => $candidates) {
            if ($handle === '') {
                unset($handle);

                continue;
            }

            if (! \array_key_exists($handle, $context)) {
                unset($handle);

                continue;
            }

            $selectedBlueprintField = $this->selectTermsLikeBlueprintFieldForMergedValue(
                $candidates,
                $context[$handle]
            );

            $context[$handle] = $this->hydrateMergedContextTermsLikeFieldHandleValue(
                $selectedBlueprintField,
                $contextEntity,
                $context[$handle]
            );

            unset($handle);
        }

        return $context;
    }

    /**
     * Turn raw term slug values on Antlers overlay keys into loopable term
     * collections when they were not handled by
     * `hydrateMergedContextTermsLikeFieldsIfRawSlugs` (for example the template
     * references only `schema_primary_related_taxonomy_term`, so the grouped
     * blueprint map does not include `activity`).
     *
     * When the snippet does not reference
     * `schema_primary_related_taxonomy_term`, only keys that
     * `antlersTemplateReferencedIdentifierSet()` finds in `$template` are
     * considered so large overlay bags stay cheap.
     *
     * @param  array<string, mixed>  $context  Merged base + overlay context
     * @param  array<string, mixed>  $antlersContext  Caller overlay keys and values
     * @param  string  $template  Antlers source for this nested row
     * @return array<string, mixed>
     */
    private function hydrateAntlersOverlayTermSlugValuesIfRaw(
        array $context,
        array $antlersContext,
        string $template
    ): array {
        if ($antlersContext === []) {
            return $context;
        }

        // Check whether primary-term injection can run for this snippet
        $templateReferencesPrimaryRelatedTaxonomyTerm = str_contains(
            $template,
            self::ANTLERS_SCHEMA_PRIMARY_RELATED_TAXONOMY_TERM
        );

        /** @var array<string, true>|null $referencedIdentifiers */
        $referencedIdentifiers = null;

        $reservedOverlayKeys = [
            self::ANTLERS_SCHEMA_TEMPLATE_INSTANCE_INDEX => true,
            self::ANTLERS_SCHEMA_TEMPLATE_TYPE_OVERRIDE => true,
            self::ANTLERS_SCHEMA_TEMPLATE_EMIT_CONTEXT => true,
            self::ANTLERS_SCHEMA_TEMPLATE_ENTRY_ID => true,
            self::ANTLERS_SCHEMA_PROPERTY_KEY => true,
            self::ANTLERS_SCHEMA_PRIMARY_RELATED_TAXONOMY_TERM => true,
        ];

        foreach (array_keys($antlersContext) as $overlayKey) {
            if ($overlayKey === '') {
                unset($overlayKey);

                continue;
            }

            if (isset($reservedOverlayKeys[$overlayKey])) {
                unset($overlayKey);

                continue;
            }

            if (! \array_key_exists($overlayKey, $context)) {
                unset($overlayKey);

                continue;
            }

            // Skip overlay keys this template cannot read (avoids term queries for
            // unrelated snapshot fields unless primary-term wiring needs them).
            if (! $templateReferencesPrimaryRelatedTaxonomyTerm) {
                if ($referencedIdentifiers === null) {
                    $referencedIdentifiers = $this->antlersTemplateReferencedIdentifierSet(
                        $template
                    );
                }

                if (! isset($referencedIdentifiers[$overlayKey])) {
                    unset($overlayKey);

                    continue;
                }
            }

            $overlayPresent = $antlersContext[$overlayKey];

            if (! $this->mergedContextTermsLikeValueNeedsBlueprintAwareAugment($overlayPresent)) {
                unset(
                    $overlayKey,
                    $overlayPresent
                );

                continue;
            }

            $context[$overlayKey] = $this->memoizedHydrateRawTermSlugValueToAugmentedValue(
                $overlayPresent
            );

            unset(
                $overlayKey,
                $overlayPresent
            );
        }

        unset(
            $referencedIdentifiers,
            $reservedOverlayKeys,
            $templateReferencesPrimaryRelatedTaxonomyTerm
        );

        return $context;
    }

    /**
     * Resolve duplicate blueprint term fields (same handle, different replicator
     * sets) using {@see Field::get('max_items')} plus the merged storage shape.
     *
     * @param  list<Field>  $candidates
     */
    private function selectTermsLikeBlueprintFieldForMergedValue(
        array $candidates,
        mixed $present
    ): ?Field {
        $normalizedCandidates = [];

        foreach ($candidates as $candidate) {
            $normalizedCandidates[] = $candidate;

            unset($candidate);
        }

        unset($candidates);

        if ($normalizedCandidates === []) {
            return null;
        }

        if (\count($normalizedCandidates) === 1) {
            return $normalizedCandidates[0];
        }

        $inspected = $present instanceof Value
            ? $present->value()
            : $present;

        $maxItemsOneFields = [];
        $multiSelectFields = [];

        foreach ($normalizedCandidates as $candidateField) {
            if ($this->blueprintTermsFieldUsesMaxItemsOne($candidateField)) {
                $maxItemsOneFields[] = $candidateField;

                unset($candidateField);

                continue;
            }

            $multiSelectFields[] = $candidateField;

            unset($candidateField);
        }

        if (
            \is_string($inspected)
            && trim($inspected) !== ''
        ) {
            return $maxItemsOneFields[0] ?? $normalizedCandidates[0];
        }

        if (
            \is_array($inspected)
            && $inspected !== []
            && array_is_list($inspected)
        ) {
            return $multiSelectFields[0] ?? $maxItemsOneFields[0] ?? $normalizedCandidates[0];
        }

        if ($inspected instanceof Term) {
            return $maxItemsOneFields[0] ?? $normalizedCandidates[0];
        }

        if (
            $inspected instanceof Collection
            && $inspected->isNotEmpty()
        ) {
            return $multiSelectFields[0] ?? $maxItemsOneFields[0] ?? $normalizedCandidates[0];
        }

        return $normalizedCandidates[0];
    }

    private function blueprintTermsFieldUsesMaxItemsOne(
        Field $field
    ): bool {
        $maxItems = $field->get('max_items');

        return $maxItems === 1
            || $maxItems === '1';
    }

    private function mergedContextTermsLikeValueNeedsBlueprintAwareAugment(
        mixed $present
    ): bool {
        if ($present instanceof Value) {
            return $this->mergedContextTermsLikeValueNeedsBlueprintAwareAugment(
                $present->raw()
            );
        }

        if ($present instanceof Collection) {
            if ($present->isEmpty()) {
                unset($present);

                return false;
            }

            foreach ($present as $item) {
                if (
                    ! $item instanceof TaxonomyTerm
                    && ! $item instanceof LocalizedTerm
                ) {
                    unset(
                        $item,
                        $present
                    );

                    return true;
                }

                unset($item);
            }

            unset($present);

            return false;
        }

        if (
            \is_string($present)
            && trim($present) !== ''
        ) {
            unset($present);

            return true;
        }

        if (
            \is_array($present)
            && $present !== []
            && array_is_list($present)
        ) {
            foreach ($present as $slugCandidate) {
                if (! \is_string($slugCandidate) || $slugCandidate === '') {
                    unset(
                        $present,
                        $slugCandidate
                    );

                    return false;
                }

                unset($slugCandidate);
            }

            unset($present);

            return true;
        }

        unset($present);

        return false;
    }

    private function hydrateMergedContextTermsLikeFieldHandleValue(
        ?Field $blueprintField,
        EntryContract|Term|null $contextEntity,
        mixed $present
    ): mixed {
        if (
            $blueprintField instanceof Field
            && (
                $contextEntity instanceof EntryModel
                || $contextEntity instanceof LocalizedTerm
            )
            && $this->mergedContextTermsLikeValueNeedsBlueprintAwareAugment($present)
        ) {
            $resolvedAugment = $this->hydrateMergedContextTermsLikeValueViaTermsBlueprintFieldAugment(
                $blueprintField,
                $contextEntity,
                $present
            );

            return $this->normalizeTermsLikeAugmentedResolutionToAntlersLoopableCollection(
                $resolvedAugment
            );
        }

        return $this->memoizedHydrateRawTermSlugValueToAugmentedValue($present);
    }

    private function hydrateMergedContextTermsLikeValueViaTermsBlueprintFieldAugment(
        Field $blueprintField,
        EntryModel|LocalizedTerm $parentAugmentable,
        mixed $present
    ): mixed {
        $memoKey = $this->termsLikeAugmentMemoItemKey(
            $blueprintField,
            $parentAugmentable,
            $present
        );

        return RequestAttributeMemo::remember(
            self::REQUEST_TERMS_LIKE_AUGMENT_MEMO,
            $memoKey,
            function () use (
                $blueprintField,
                $parentAugmentable,
                $present
            ): mixed {
                $uncachedStart = microtime(true);
                $built = $this->hydrateMergedContextTermsLikeValueViaTermsBlueprintFieldAugmentUncached(
                    $blueprintField,
                    $parentAugmentable,
                    $present
                );

                $this->jsonLdCompositionProfiler->recordAugmentUncachedSample(
                    (microtime(true) - $uncachedStart) * 1000
                );

                return $built;
            }
        );
    }

    /**
     * Uncached blueprint-field augment (see memo wrapper).
     */
    private function hydrateMergedContextTermsLikeValueViaTermsBlueprintFieldAugmentUncached(
        Field $blueprintField,
        EntryModel|LocalizedTerm $parentAugmentable,
        mixed $present
    ): mixed {
        $rawForField = $present instanceof Value
            ? $present->raw()
            : $present;

        $workingField = new Field($blueprintField->handle(), $blueprintField->config());
        $workingField->setParent($parentAugmentable);
        $workingField->setParentField(
            $blueprintField->parentField(),
            $blueprintField->parentIndex()
        );
        $workingField->setValue($rawForField);

        /** @var Field $augmentedField */
        $augmentedField = $workingField->augment();
        $wrappedAugmentValue = $augmentedField->value();

        if (! $wrappedAugmentValue instanceof Value) {
            return $wrappedAugmentValue;
        }

        return $wrappedAugmentValue->value();
    }

    /**
     * Stable memo key for request-scoped terms augment cache.
     */
    private function termsLikeAugmentMemoItemKey(
        Field $blueprintField,
        EntryModel|LocalizedTerm $parentAugmentable,
        mixed $present
    ): string {
        $parentKey = $this->memoKeyForTermsAugmentParent($parentAugmentable);
        $rawFieldHandle = $blueprintField->handle();
        $fieldHandle = \is_scalar($rawFieldHandle) ? (string) $rawFieldHandle : '';
        $fieldSig = $fieldHandle."\0".hash(
            'xxh128',
            serialize($blueprintField->config())
        );
        $rawForPayload = $present instanceof Value
            ? $present->raw()
            : $present;
        $payloadSig = $this->stableSignatureForTermsAugmentPresentPayload($rawForPayload);

        return hash(
            'xxh128',
            $parentKey."\0".$fieldSig."\0".$payloadSig
        );
    }

    private function memoKeyForTermsAugmentParent(
        EntryModel|LocalizedTerm $parentAugmentable
    ): string {
        try {
            $id = $parentAugmentable->id();

            if (\is_string($id) && $id !== '') {
                return $parentAugmentable::class.'::'.$id;
            }
        } catch (\Throwable) {
            // Partial test doubles may omit id(); fall back to object identity.
        }

        // Same hazard and same mitigation as antlersEntityContextCacheKey() above:
        // pin the object so its id cannot be reissued to a later entity while this
        // memo can still be read (UAMS-Web/uams-statamic-archive#1967). Namespaced by class, matching the id
        // branch, so an entry and a term cannot collide on one pinned identity.
        return $parentAugmentable::class.'::'.$this->pinnedObjectIdentityKey($parentAugmentable);
    }

    private function stableSignatureForTermsAugmentPresentPayload(mixed $raw): string
    {
        if ($raw === null) {
            return 'null';
        }

        if (\is_string($raw)) {
            return 's:'.$raw;
        }

        if (
            \is_array($raw)
            && $raw !== []
            && array_is_list($raw)
        ) {
            $strings = [];

            foreach ($raw as $slugCandidate) {
                if (\is_string($slugCandidate) && $slugCandidate !== '') {
                    $strings[] = $slugCandidate;
                }
            }

            sort($strings);

            return 'l:'.implode("\0", $strings);
        }

        return 'x:'.hash(
            'xxh128',
            serialize($raw)
        );
    }

    private function normalizeTermsLikeAugmentedResolutionToAntlersLoopableCollection(
        mixed $resolvedAugment
    ): mixed {
        if (Compare::isQueryBuilder($resolvedAugment)) {
            /** @var Builder $resolvedAugment */
            return $resolvedAugment->get();
        }

        if ($resolvedAugment === null) {
            return collect();
        }

        // Keep {@see LocalizedTerm} instances so localized blueprint keys (for example
        // `schema_object` on taxonomy terms) resolve inside Antlers loops; peeled
        // {@see TaxonomyTerm} models omit localized-only storage for `Term::get()`.
        if ($resolvedAugment instanceof LocalizedTerm) {
            return collect([$resolvedAugment]);
        }

        if ($resolvedAugment instanceof Term) {
            return collect([$resolvedAugment]);
        }

        return $resolvedAugment;
    }

    /**
     * When nested schema property Antlers runs with a non-empty overlay (for
     * example `schema_template_node` snapshot keys), expose the first taxonomy
     * term as a {@see Fluent} flattening (or a Mockery {@see LocalizedTerm} double
     * unchanged) under {@see self::ANTLERS_SCHEMA_PRIMARY_RELATED_TAXONOMY_TERM}.
     *
     * @param  array<string, mixed>  $context  Merged Antlers context after slug hydration
     * @param  array<string, mixed>  $antlersContext  Keys supplied by the caller overlay
     * @return array<string, mixed> Context possibly including
     *                              {@see self::ANTLERS_SCHEMA_PRIMARY_RELATED_TAXONOMY_TERM}
     */
    private function injectSchemaPrimaryRelatedTaxonomyTermFromAntlersOverlay(
        array $context,
        array $antlersContext
    ): array {
        if ($antlersContext === []) {
            return $context;
        }

        // Resolve the first taxonomy term implied by overlay keys and merged values
        $taxonomyTerm = $this->resolveSchemaPrimaryRelatedTaxonomyTermFromAntlersOverlay(
            $context,
            $antlersContext
        );

        // Return the context unchanged when no overlay-driven term exists
        if (! $taxonomyTerm instanceof TaxonomyTerm) {
            unset($taxonomyTerm);

            return $context;
        }

        // Prefer default-locale term presentation for Antlers field reads
        $localized = $taxonomyTerm->inDefaultLocale();

        // Expose the primary term as a {@see Fluent} built from the same flattened,
        // terms-hydrated bag as {@see buildFlattenedSchemaAntlersContextFromDataObject}
        // so `schema_primary_related_taxonomy_term.audience` (and other dot paths)
        // loop and stringify like normal entry Antlers. Mockery {@see LocalizedTerm}
        // doubles keep the raw instance so unit tests do not require a full Stache term.
        if (
            $localized instanceof LocalizedTerm
            && $localized::class === LocalizedTerm::class
        ) {
            $context[self::ANTLERS_SCHEMA_PRIMARY_RELATED_TAXONOMY_TERM] = new Fluent(
                $this->buildFlattenedSchemaAntlersContextFromDataObject($localized)
            );
        } else {
            $context[self::ANTLERS_SCHEMA_PRIMARY_RELATED_TAXONOMY_TERM] = $localized;
        }

        unset(
            $localized,
            $taxonomyTerm
        );

        return $context;
    }

    /**
     * Pick the first {@see TaxonomyTerm} from merged context using overlay key order.
     *
     * Overlay keys are sorted lexicographically; the first key whose merged value
     * resolves to a taxonomy term (or a non-empty collection of terms) wins. This
     * avoids hardcoding any one replicator or taxonomy field handle in templates.
     *
     * @param  array<string, mixed>  $context  Merged Antlers context after slug hydration
     * @param  array<string, mixed>  $antlersContext  Keys supplied by the caller overlay
     */
    private function resolveSchemaPrimaryRelatedTaxonomyTermFromAntlersOverlay(
        array $context,
        array $antlersContext
    ): ?TaxonomyTerm {
        if ($antlersContext === []) {
            return null;
        }

        // Collect overlay keys in deterministic lexicographic order
        $overlayKeys = array_keys($antlersContext);
        sort($overlayKeys, SORT_STRING);

        // Skip reserved Antlers keys that are never taxonomy selections
        $reservedOverlayKeys = [
            self::ANTLERS_SCHEMA_TEMPLATE_INSTANCE_INDEX => true,
            self::ANTLERS_SCHEMA_TEMPLATE_TYPE_OVERRIDE => true,
            self::ANTLERS_SCHEMA_TEMPLATE_EMIT_CONTEXT => true,
            self::ANTLERS_SCHEMA_TEMPLATE_ENTRY_ID => true,
            self::ANTLERS_SCHEMA_PROPERTY_KEY => true,
            self::ANTLERS_SCHEMA_PRIMARY_RELATED_TAXONOMY_TERM => true,
        ];

        foreach ($overlayKeys as $overlayKey) {
            if ($overlayKey === '') {
                unset($overlayKey);

                continue;
            }

            // Skip reserved keys so emit maps and instance index never compete
            if (isset($reservedOverlayKeys[$overlayKey])) {
                unset($overlayKey);

                continue;
            }

            // Require the merged bag to carry a value for this overlay handle
            if (! \array_key_exists($overlayKey, $context)) {
                unset($overlayKey);

                continue;
            }

            // Unwrap a single taxonomy term from the merged value at this handle
            $taxonomyTerm = $this->unwrapFirstTaxonomyTermFromSchemaContextValue(
                $context[$overlayKey]
            );

            if ($taxonomyTerm instanceof TaxonomyTerm) {
                $resolvedTerm = $taxonomyTerm;
                unset(
                    $overlayKeys,
                    $overlayKey,
                    $reservedOverlayKeys,
                    $taxonomyTerm
                );

                return $resolvedTerm;
            }

            unset(
                $overlayKey,
                $taxonomyTerm
            );
        }

        unset(
            $overlayKeys,
            $reservedOverlayKeys
        );

        return null;
    }

    /**
     * Extract the first {@see TaxonomyTerm} from a hydrated terms value.
     *
     * @param  mixed  $value  Augmented terms collection, {@see LocalizedTerm}, or term
     */
    private function unwrapFirstTaxonomyTermFromSchemaContextValue(
        mixed $value
    ): ?TaxonomyTerm {
        if ($value instanceof Value) {
            $value = $value->value();
        }

        if ($value instanceof TaxonomyTerm) {
            return $value;
        }

        if ($value instanceof LocalizedTerm) {
            $term = $value->term();

            return $term instanceof TaxonomyTerm
                ? $term
                : null;
        }

        if (
            $value instanceof Collection
            && $value->isNotEmpty()
        ) {
            $first = $value->first();

            if ($first instanceof Value) {
                $first = $first->value();
            }

            if ($first instanceof TaxonomyTerm) {
                return $first;
            }

            if ($first instanceof LocalizedTerm) {
                $term = $first->term();

                return $term instanceof TaxonomyTerm
                    ? $term
                    : null;
            }
        }

        return null;
    }

    /**
     * Collect nested terms-like blueprint fields grouped by handle.
     *
     * Duplicate handles (for example identical `organization` fields in distinct
     * replicator sets) keep every candidate Field so callers can resolve `max_items`
     * ambiguity before augmenting overlay values for schema Antlers.
     *
     * @return array<string, list<Field>>
     */
    private function collectTermsLikeBlueprintFieldsGroupedByHandle(
        EntryContract|Term|null $contextEntity
    ): array {
        if (! $contextEntity instanceof Augmentable) {
            return [];
        }

        $memoKey = $this->antlersEntityContextCacheKey($contextEntity);

        /** @var array<string, list<Field>> $grouped */
        $grouped = RequestAttributeMemo::remember(
            self::REQUEST_TERMS_LIKE_BLUEPRINT_FIELDS_GROUPED_CACHE,
            $memoKey,
            fn (): array => $this->collectTermsLikeBlueprintFieldsGroupedByHandleUncached(
                $contextEntity
            )
        );

        return $grouped;
    }

    /**
     * @return array<string, list<Field>>
     */
    private function collectTermsLikeBlueprintFieldsGroupedByHandleUncached(
        Augmentable $contextEntity
    ): array {
        // Check whether the augmentable declares a blueprint for field discovery.
        if (! method_exists($contextEntity, 'blueprint')) {
            return [];
        }

        $blueprint = $contextEntity->blueprint();

        if ($blueprint === null) {
            return [];
        }

        /** @var Blueprint $blueprint */
        $blueprintFields = $blueprint->fields()->all();

        /** @var array<string, list<Field>> $groupedByHandle */
        $groupedByHandle = [];

        foreach ($blueprintFields as $field) {
            /** @var Field $field */
            $this->collectTermsLikeFieldHandlesFromField(
                $field,
                $groupedByHandle
            );

            unset($field);
        }

        unset($blueprint);

        return $groupedByHandle;
    }

    /**
     * @param  array<string, list<Field>>  $groupedByHandle
     */
    private function collectTermsLikeFieldHandlesFromField(
        Field $field,
        array &$groupedByHandle
    ): void {
        $ft = $field->fieldtype();

        if ($ft instanceof TermsFieldtype) {
            $rawHandle = $field->handle();
            $termsHandle = \is_scalar($rawHandle) ? (string) $rawHandle : '';
            $groupedByHandle[$termsHandle] ??= [];
            $groupedByHandle[$termsHandle][] = $field;

            unset($ft, $rawHandle, $termsHandle);

            return;
        }

        if ($ft instanceof ReplicatorFieldtype) {
            /** @var iterable<array-key, array<string, mixed>> $flattenedSetsConfig */
            $flattenedSetsConfig = $ft->flattenedSetsConfig();
            foreach ($flattenedSetsConfig as $setConfig) {
                $nested = new Fields(
                    Arr::get(
                        $setConfig,
                        'fields',
                        []
                    ),
                    $field->parent(),
                    $field,
                    -1
                );
                foreach ($nested->all() as $child) {
                    /** @var Field $child */
                    $this->collectTermsLikeFieldHandlesFromField(
                        $child,
                        $groupedByHandle
                    );
                }
            }

            unset($ft);

            return;
        }

        if ($ft instanceof GridFieldtype) {
            /** @var Fields $gridFields */
            $gridFields = $ft->fields(-1);
            foreach ($gridFields->all() as $child) {
                /** @var Field $child */
                $this->collectTermsLikeFieldHandlesFromField(
                    $child,
                    $groupedByHandle
                );
            }

            unset($child, $ft);

            return;
        }

        if ($ft instanceof GroupFieldtype) {
            /** @var Fields $groupFields */
            $groupFields = $ft->fields();
            foreach ($groupFields->all() as $child) {
                /** @var Field $child */
                $this->collectTermsLikeFieldHandlesFromField(
                    $child,
                    $groupedByHandle
                );
            }

            unset($child, $ft);

            return;
        }

        unset($ft);
    }

    /**
     * Memoized front for {@see hydrateRawTermSlugValueToAugmentedValue()}.
     *
     * Object inputs key on `spl_object_id` and keep a reference to the original
     * object inside the memo row so a recycled object id can never alias a different
     * instance; the row is only reused when the stored input is identical (`===`).
     * String and list-of-slug inputs key on content. Unkeyable shapes hydrate
     * directly (no memo).
     *
     * This site holds its reference in the memo row because the row already exists
     * to carry `in`/`out` for the content-keyed shapes. The two entity-keyed memos
     * have no such row, so they pin through {@see pinnedObjectIdentityKey()}
     * instead — same guarantee, different place to put it (UAMS-Web/uams-statamic-archive#1967).
     *
     * @param  mixed  $present  Raw overlay value (Value, query builder, collection, slug string, or slug list)
     * @return mixed Hydrated loopable term collection, or the input when not hydratable
     */
    private function memoizedHydrateRawTermSlugValueToAugmentedValue(
        mixed $present
    ): mixed {
        // Build a memo key from object identity or scalar slug content
        $memoKey = null;

        // Key objects by identity (Value / query builder / collection instances are
        // shared across the property parses of one build via the memoized bags)
        if (\is_object($present)) {
            $memoKey = 'o'.spl_object_id($present);
        } elseif (\is_string($present) && $present !== '') { // Key bare slug strings by content
            $memoKey = 's'.$present;
        } elseif (\is_array($present) && $present !== [] && array_is_list($present)) {
            // Key lists by content when every item is a non-empty string slug
            $slugItems = [];

            // Walk each list item to confirm the all-string slug shape
            foreach ($present as $item) {
                if (! \is_string($item) || $item === '') {
                    $slugItems = null;

                    break;
                }

                $slugItems[] = $item;
            }

            // Use a content hash when the list is all string slugs
            if (\is_array($slugItems)) {
                $memoKey = 'l'.hash('xxh128', implode("\0", $slugItems));
            }

            unset($slugItems);
        }

        // Hydrate directly when no stable memo key shape applies
        if ($memoKey === null) {
            return $this->hydrateRawTermSlugValueToAugmentedValue($present);
        }

        $row = RequestAttributeMemo::remember(
            self::REQUEST_RAW_TERM_SLUG_HYDRATION_MEMO,
            $memoKey,
            fn (): array => [
                'in' => $present,
                'out' => $this->hydrateRawTermSlugValueToAugmentedValue($present),
            ]
        );

        // Reuse the memoized result only when the stored input is the same value
        if (
            \is_array($row)
            && \array_key_exists('in', $row)
            && \array_key_exists('out', $row)
            && (\is_object($present) ? $row['in'] === $present : $row['in'] == $present)
        ) {
            return $row['out'];
        }

        // Fall back to a direct hydration when the memo row does not match
        return $this->hydrateRawTermSlugValueToAugmentedValue($present);
    }

    private function hydrateRawTermSlugValueToAugmentedValue(
        mixed $present
    ): mixed {
        if ($present instanceof Value) {
            $present = $present->value();
        }

        if (Compare::isQueryBuilder($present)) {
            /** @var Builder $present */
            return $present->get();
        }

        if ($present instanceof LocalizedTerm) {
            return collect([$present]);
        }

        if ($present instanceof Term) {
            return collect([$present]);
        }

        if ($present instanceof Collection) {
            if ($present->isEmpty()) {
                return $present;
            }

            $first = $present->first();
            if ($first instanceof Value) {
                $first = $first->value();
            }

            if (
                $first instanceof LocalizedTerm
                || $first instanceof Term
            ) {
                return $present;
            }

            $slugList = $present
                ->map(
                    function (mixed $item): ?string {
                        if ($item instanceof Value) {
                            $item = $item->value();
                        }

                        return \is_string($item) && $item !== '' ? $item : null;
                    }
                )
                ->filter()
                ->values()
                ->all();

            if ($slugList === []) {
                return $present;
            }

            /** @var list<string> $slugList */
            $hydrated = $this->termCollectionForPlainOrQualifiedTermSlugs(
                $slugList
            );

            return $hydrated instanceof Collection
                ? $hydrated
                : $present;
        }

        if (! \is_array($present) || $present === [] || ! array_is_list($present)) {
            if (\is_string($present) && $present !== '') {
                $hydrated = $this->termCollectionForPlainOrQualifiedTermSlugs(
                    [
                        $present,
                    ]
                );

                return $hydrated instanceof Collection
                    ? $hydrated
                    : $present;
            }

            return $present;
        }

        if ($present[0] === null) {
            return $present;
        }

        $first = $present[0];

        if ($first instanceof Value) {
            $first = $first->value();
        }

        if (
            $first instanceof LocalizedTerm
            || $first instanceof Term
        ) {
            return $this->termCollectionForAugmentedList($present);
        }

        if (! \is_string($first)) {
            return $present;
        }

        $stringSlugs = [];

        foreach ($present as $item) {
            if ($item instanceof Value) {
                $item = $item->value();
            }

            if (! \is_string($item) || $item === '') {
                return $present;
            }

            $stringSlugs[] = $item;
        }

        $hydrated = $this->termCollectionForPlainOrQualifiedTermSlugs(
            $stringSlugs
        );

        return $hydrated instanceof Collection
            ? $hydrated
            : $present;
    }

    /**
     * @param  array<int, mixed>  $list
     * @return Collection<int, LocalizedTerm|Term>
     */
    private function termCollectionForAugmentedList(array $list): Collection
    {
        /** @var Collection<int, LocalizedTerm|Term> $out */
        $out = collect();

        foreach ($list as $item) {
            if ($item instanceof Value) {
                $item = $item->value();
            }

            if ($item instanceof LocalizedTerm) {
                $out->push($item);

                continue;
            }

            if ($item instanceof Term) {
                $out->push($item);

                continue;
            }
        }

        return $out;
    }

    /**
     * Resolve a list of stored term references to concrete taxonomy term models.
     *
     * - Values containing `::` are treated as qualified Statamic term ids
     *   (`{taxonomy}::{slug}`) and passed to {@see TermFacade::find()}.
     * - Bare slugs (no `::`) are resolved with {@see TermFacade::query()} on `slug`
     *   across all taxonomies. If more than one term shares that slug, the first
     *   after sorting by `id` is used so the choice is stable (prefer qualified
     *   storage when slugs collide across taxonomies).
     *
     * @param  list<string>  $slugs
     * @return Collection<int, LocalizedTerm|TaxonomyTerm>|null
     */
    private function termCollectionForPlainOrQualifiedTermSlugs(array $slugs): ?Collection
    {
        /** @var Collection<int, LocalizedTerm|TaxonomyTerm> $out */
        $out = collect();

        foreach ($slugs as $raw) {
            if ($raw === '') {
                continue;
            }

            if ($raw === '0') {
                continue;
            }

            $term = $this->resolveTaxonomyTermForPlainOrQualifiedReference($raw);

            if ($term !== null) {
                $out->push($term);
            }
        }

        return $out->isNotEmpty() ? $out : null;
    }

    /**
     * Resolve one stored reference to a term model usable in schema Antlers loops.
     *
     * Preserves {@see LocalizedTerm} from {@see TermFacade::find()} so localized
     * fields remain readable inside `{{ taxonomy_field }} … {{ /taxonomy_field }}`
     * scopes.
     */
    private function resolveTaxonomyTermForPlainOrQualifiedReference(
        string $raw
    ): LocalizedTerm|TaxonomyTerm|null {
        if (str_contains($raw, '::')) {
            $found = TermFacade::find($raw);

            return $this->unwrapStatamicTermFindToTaxonomyTerm($found);
        }

        /** @var Builder $termQuery */
        $termQuery = TermFacade::query()
            ->where(
                'slug',
                $raw
            );
        /** @var Collection<int, mixed> $matches */
        $matches = $termQuery->get();

        if ($matches->isEmpty()) {
            return null;
        }

        if ($matches->count() === 1) {
            return $this->unwrapStatamicTermFindToTaxonomyTerm(
                $matches->first()
            );
        }

        $sorted = $matches->sortBy(
            function (mixed $t): string {
                if (is_object($t) && method_exists($t, 'id')) {
                    $id = $t->id();

                    return \is_string($id) ? $id : '';
                }

                return '';
            }
        );

        return $this->unwrapStatamicTermFindToTaxonomyTerm(
            $sorted->first()
        );
    }

    private function unwrapStatamicTermFindToTaxonomyTerm(
        mixed $found
    ): LocalizedTerm|TaxonomyTerm|null {
        if ($found === null) {
            return null;
        }

        if ($found instanceof LocalizedTerm) {
            return $found;
        }

        if ($found instanceof TaxonomyTerm) {
            return $found;
        }

        return null;
    }

    /**
     * Flatten entry or term fields for schema relationship Antlers (merged
     * values, terms hydration, selective Link-like augmentation, permalink/url).
     *
     * Relationship targets use this so loops such as `{{ pronouns }}` match
     * main schema property rendering. Includes an `entry` object alias for
     * entry-backed context.
     *
     * @param  EntryContract|Term  $contextEntity  Source entry or term
     * @return array<string, mixed> Variables for Antlers parsing
     */
    public function buildFlattenedSchemaAntlersContextFromDataObject(
        EntryContract|Term $contextEntity
    ): array {
        return $this->buildAntlersContextFromEntity($contextEntity);
    }

    /**
     * Swap list-of-slug or single-slug term field values for loaded Term models in
     * schema Antlers context.
     *
     * Flat context may still store terms fields as string lists. Antlers wraps each
     * scalar as `value`/`name` only, so `{{ title }}` inside the loop resolves to the
     * parent entry title. Hydrating from the field's Value + query builder fixes that.
     *
     * `max_items: 1` fields may persist as a single slug string (for example
     * `pronouns: she-her`); those are hydrated the same way as a one-element slug
     * list.
     *
     * @param  EntryModel|LocalizedTerm  $contextEntity  Entry or localized term with `blueprint()` and `augmentedValue()`
     * @param  array<string, mixed>  $context  Variables from augmentation, values(), or data()
     * @return array<string, mixed> Context with terms / terms_search_index fields hydrated
     */
    private function hydrateTermsLikeFieldValuesForSchemaAntlersContext(
        EntryModel|LocalizedTerm $contextEntity,
        array $context
    ): array {
        // Resolve the blueprint for field type inspection
        $blueprint = $contextEntity->blueprint();

        // Check if the blueprint is missing
        if ($blueprint === null) {
            return $context;
        }

        /** @var Blueprint $blueprint */
        $blueprintFields = $blueprint->fields()->all();

        // Walk blueprint fields for terms-like fieldtypes
        foreach ($blueprintFields as $field) {
            /** @var Field $field */
            // Read the field handle and fieldtype handle
            $rawHandle = $field->handle();
            $handle = \is_scalar($rawHandle) ? (string) $rawHandle : '';
            $type = $field->type();

            // Check if the field is not a terms or terms_search_index field
            if (! \in_array($type, ['terms', 'terms_search_index'], true)) {
                continue;
            }

            // Check if the context omits this handle
            if (! \array_key_exists($handle, $context)) {
                continue;
            }

            // Read the current context value for this field
            $present = $context[$handle];

            // Full-field augmentation may leave a query builder or a lone term instance
            if (Compare::isQueryBuilder($present)) {
                /** @var Builder $present */
                $context[$handle] = $present->get();

                continue;
            }

            if ($present instanceof Term) {
                $context[$handle] = collect([$present]);

                continue;
            }

            // Decide whether to hydrate from a slug list
            $hydrateFromSlugList = false;

            // Check for a non-empty list of raw slugs (not yet Term models)
            if (
                \is_array($present)
                && $present !== []
                && array_is_list($present)
            ) {
                // Read the first list element to detect prior hydration
                $first = $present[0];

                // Skip when terms are already augmentable objects
                if (
                    $first instanceof Augmentable
                ) {
                    continue;
                }

                // Mark slug list for hydration via augmented query
                $hydrateFromSlugList = true;
            }

            // Check for a single stored slug string (common when max_items is 1)
            $hydrateFromSingleSlug = \is_string($present) && trim($present) !== '';

            // Skip when neither list nor single-slug shape applies
            if (
                ! $hydrateFromSlugList
                && ! $hydrateFromSingleSlug
            ) {
                continue;
            }

            // Replace raw value with the augmented Term collection for Antlers loops
            $this->replaceTermsLikeFieldContextWithAugmentedTerms(
                $contextEntity,
                $handle,
                $context
            );
        }

        return $context;
    }

    /**
     * Run terms-field hydration only for concrete types that expose augmented values.
     *
     * `Statamic\Contracts\Taxonomies\Term` and `Entry` contracts do not declare
     * `augmentedValue()`; `Statamic\Taxonomies\Term` (non-localized) has no
     * augmentation API. Skip hydration in those cases.
     *
     * @param  EntryContract|Term  $contextEntity  Source entry or term from the route
     * @param  array<string, mixed>  $context  Variables from augmentation, values(), or data()
     * @return array<string, mixed> Context with terms fields hydrated when supported
     */
    private function hydrateTermsLikeFieldsIfSupportedForSchemaAntlersContext(
        EntryContract|Term $contextEntity,
        array $context
    ): array {
        // Check for a concrete entry or localized term before calling augmentation APIs
        if (
            $contextEntity instanceof EntryModel
            || $contextEntity instanceof LocalizedTerm
        ) {
            // Delegate to hydration that uses blueprint and augmentedValue
            return $this->hydrateTermsLikeFieldValuesForSchemaAntlersContext(
                $contextEntity,
                $context
            );
        }

        // Return context unchanged when the entity type cannot be augmented this way
        return $context;
    }

    /**
     * Assign context[field] to the Term collection from augmentedValue query get().
     *
     * @param  EntryModel|LocalizedTerm  $contextEntity  Entry or localized term
     * @param  string  $handle  Terms field handle
     * @param  array<string, mixed>  $context  Antlers context (modified in place)
     */
    private function replaceTermsLikeFieldContextWithAugmentedTerms(
        EntryModel|LocalizedTerm $contextEntity,
        string $handle,
        array &$context
    ): void {
        // Read the augmented Value wrapper (raw storage resolves to a term query)
        $valueObj = $contextEntity->augmentedValue($handle);

        // Check if the augmented value is not a Statamic Value
        if (! $valueObj instanceof Value) {
            return;
        }

        // Resolve the augmented field value (query, single term, or collection)
        $resolved = $valueObj->value();

        // Replace slugs with the ordered Term collection when augmentation is a query
        if (Compare::isQueryBuilder($resolved)) {
            /** @var Builder $resolved */
            $context[$handle] = $resolved->get();

            return;
        }

        // ---
        // max_items: 1 terms fields augment to one Term via query->first(), not a builder
        // ---

        // Check if augmentation resolved to a single taxonomy term instance
        if ($resolved instanceof Term) {
            // Wrap the lone term so `{{ handle }}` loops match multi-select shape
            $context[$handle] = collect([$resolved]);

            return;
        }

        // Check if augmentation is explicitly empty for a single-select field
        if ($resolved === null) {
            // Use an empty list so Antlers loops are valid (zero iterations)
            $context[$handle] = collect();

            return;
        }

        // Keep augmented Collection instances as loopable context when present
        if ($resolved instanceof Collection) {
            $context[$handle] = $resolved;
        }
    }

    /**
     * Add `permalink` and `url` to schema Antlers context when absent.
     *
     * Statamic augmented entry fields (e.g. permalink) are not always present
     * in merged `values()->all()` / `data()->all()`, but schema templates expect
     * the same flattened keys as front-end Antlers.
     *
     * @param  EntryContract|Term  $contextEntity  Source entry or term
     * @param  array<string, mixed>  $context  Variables built from values or data
     * @return array<string, mixed> Context with optional URL aliases merged in
     */
    private function mergeCanonicalUrlKeysIntoSchemaAntlersContext(
        EntryContract|Term $contextEntity,
        array $context
    ): array {
        // Check if permalink is missing and absolute URL can be resolved
        if (
            ! \array_key_exists('permalink', $context)
            && method_exists($contextEntity, 'absoluteUrl')
        ) {
            // Resolve absolute URL for flattened `{{ permalink }}`
            $absoluteUrl = $contextEntity->absoluteUrl();

            // Check if the absolute URL is a non-empty string
            if (\is_string($absoluteUrl) && $absoluteUrl !== '') {
                // Expose permalink for schema property Antlers
                $context['permalink'] = $absoluteUrl;
            }

            // Unset local variables to free up memory
            unset(
                $absoluteUrl
            );
        }

        // Check if relative url is missing without clobbering a content field
        if (
            ! \array_key_exists('url', $context)
            && method_exists($contextEntity, 'url')
        ) {
            // Resolve relative URL for flattened `{{ url }}`
            $relativeUrl = $contextEntity->url();

            // Check if the relative URL is a non-empty string
            if (\is_string($relativeUrl) && $relativeUrl !== '') {
                // Expose url for schema property Antlers
                $context['url'] = $relativeUrl;
            }

            // Unset local variables to free up memory
            unset(
                $relativeUrl
            );
        }

        // Return context with merged URL keys
        return $context;
    }

    /**
     * Get the schema_objects entry for a slot for the current webpage context.
     *
     * Resolves the current entry/term from the route, runs the cascade, and
     * returns the Entry for the requested slot (subject, website, webpage,
     * main_content). For use in Antlers (e.g. in the schema_object fieldtype's
     * Antlers set) so you can reference the slot entry's data.
     *
     * @param  string  $slot  Slot type: subject, website, webpage, or main_content
     * @return EntryContract|null The schema_objects entry for that slot, or null
     */
    public function getSlotEntryForCurrentContext(string $slot): ?EntryContract
    {
        if (Statamic::isCpRoute()) {
            return null;
        }

        $entity = $this->resolveEntity();
        if ($entity === null) {
            return null;
        }

        $resolved = $this->cascadeService->resolve($entity);
        $key = match ($slot) {
            'subject' => 'subject_entry',
            'website' => 'website_entry',
            'webpage' => 'webpage_entry',
            'main_content' => 'main_content_entry',
            default => null,
        };

        if ($key === null) {
            return null;
        }

        $entryId = $this->normalizeEntryId($resolved[$key]);
        if ($entryId === null || $entryId === '') {
            return null;
        }

        $entry = EntryFacade::find($entryId);

        return $entry instanceof EntryModel ? $entry : null;
    }

    /**
     * Resolve current entry or term from the route or request URL.
     *
     * Custom Statamic routes (e.g., entries/{entry}) pass the entity as the
     * 'data' route parameter. Content-routed URLs (e.g., /collection/slug) are
     * resolved by Statamic via the request URL; for structure collections
     * Statamic returns a Page, which we resolve to the underlying Entry.
     */
    protected function resolveEntity(): EntryContract|Term|null
    {
        $data = request()->route()?->parameter('data');

        if ($data instanceof EntryContract || $data instanceof Term) {
            return $data;
        }

        // Content-routed pages: resolve entity from current request URL
        $data = Data::findByRequestUrl(request()->url());

        // ---
        // Structure collections: Statamic returns a Page (which implements Entry);
        // resolve to the actual Entry so the cascade and providers get
        // collection/blueprint
        // ---
        if ($data instanceof Page) {
            return EntryFacade::find($data->id());
        }

        if ($data instanceof EntryContract || $data instanceof Term) {
            return $data;
        }

        return null;
    }
}
