<?php

declare(strict_types=1);

namespace App\Services\SchemaTemplates;

use App\Models\Asset as AssetMetadataModel;
use App\Scopes\CredentialTypeCertification;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Statamic\Assets\Asset;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Contracts\Taxonomies\Term as TermContract;
use Statamic\Data\AugmentedCollection;
use Statamic\Entries\Entry as EntryModel;
use Statamic\Facades\Asset as AssetFacade;
use Statamic\Facades\Data;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Term;
use Statamic\Fields\Value;
use Statamic\Globals\Variables;
use Statamic\Query\OrderedQueryBuilder;
use Statamic\Query\StatusQueryBuilder;
use Statamic\Sites\Site;
use Statamic\Structures\Page;
use Statamic\Taxonomies\LocalizedTerm;
use Statamic\Taxonomies\Taxonomy;
use Statamic\Taxonomies\Term as TaxonomyTerm;

/**
 * Builds JSON-LD nodes from relationship fields in direct, secondary, or
 * global traversal mode.
 *
 * When `$params['emit_context']` is a `SchemaTemplateEmitContext`, standalone
 * template builds share `emitted_node_ids` with the graph. Before expanding a
 * template, the resolver checks the target canonical `@id`: when that id
 * already has a full node in the pass map (from top-level graph slots,
 * `thing_*` resolution, or other callers that call
 * `recordFullSchemaObjectNodeEmitted`), only `{"@id":…}` is produced (no
 * subtree build). Multiple targets in one tag call reuse a per-invocation map
 * so the same canonical id is not fully built twice in the same output
 * array. Resolved nodes are not written to the shared pass map so nested
 * fragments do not pre-mark unrelated later properties; see
 * `SchemaJsonLdAtIdDeduplication` class documentation.
 *
 * Direct mode optional filter: when both `direct_term_data_equals_field` and
 * `direct_term_data_equals_value` are non-empty strings, only taxonomy term
 * targets whose default-locale data for that field equals the value (after
 * trimming) are kept; non-term targets are dropped. Field handles may use
 * dot notation for nested group data (for example `ctdl.ceterms_type`).
 * When the field targets `ctdl.ceterms_credential_type` (or ends with
 * `.ceterms_credential_type`), terms must also have `ctdl.ceterms_type`
 * equal to `Credential` before the subtype comparison applies.
 */
class SchemaThingFromRelationshipResolver
{
    private const string PROFILE_BAG = JsonLdCompositionProfiler::REQUEST_JSON_LD_COMPOSITION_PROFILE;

    /**
     * Collection handle for subject matter, whose entries have no route (UAMS-Web/uams-statamic-archive#2822).
     */
    private const string SUBJECT_MATTER_COLLECTION = 'subject_matter';

    /**
     * Collection handle for the clinical specializations, whose entries have no route
     * either and share subject matter's pickers since UAMS-Web/uams-statamic-archive#2823.
     */
    private const string CLINICAL_SPECIALIZATIONS_COLLECTION = 'clinical_specializations';

    /**
     * Create a resolver for relationship-driven schema node generation.
     *
     * @param  SchemaOutputService  $schemaOutputService  Service used to build standalone schema nodes from schema_objects templates.
     * @param  SchemaTemplatesCascadeService  $cascadeService  Service used to resolve fallback schema template ids from cascade settings.
     */
    public function __construct(
        protected SchemaOutputService $schemaOutputService,
        protected SchemaTemplatesCascadeService $cascadeService,
        protected SchemaJsonLdAtIdDeduplication $jsonLdAtIdDeduplication,
        protected ?SchemaJsonLdFragmentCache $jsonLdFragmentCache = null
    ) {}

    /**
     * Lazily resolve the cross-request fragment cache (optional constructor arg so
     * existing manual constructions keep working).
     */
    protected function fragmentCache(): SchemaJsonLdFragmentCache
    {
        // Resolve from the container on first use when not injected
        return $this->jsonLdFragmentCache ??= app(SchemaJsonLdFragmentCache::class);
    }

    /**
     * Resolve JSON-LD nodes from relationship fields.
     *
     * @param  array<string, mixed>  $context  Antlers context.
     * @param  array<string, mixed>  $params  Tag parameters; optional `emit_context`
     *                                        `SchemaTemplateEmitContext` for shared maps
     * @return string JSON array of schema objects.
     */
    public function resolve(
        array $context,
        array $params
    ): string {
        $profileEnabled = (bool) config('schema_templates.json_ld_compose_profile.enabled', false);
        $resolveStart = $profileEnabled ? microtime(true) : 0.0;

        // Read the requested traversal mode
        $modeRaw = $params['mode'] ?? 'direct';

        // Normalize traversal mode to direct, secondary, or global
        $mode = \is_string($modeRaw) ? strtolower(trim($modeRaw)) : 'direct';

        // Check if the mode is not supported
        if (
            ! in_array($mode, ['secondary', 'global', 'tertiary'], true)
        ) {
            // Default to direct mode when unsupported
            $mode = 'direct';
        }

        // Read an optional explicit schema template id
        $templateIdRaw = $params['schema_object'] ?? null;

        // Normalize the explicit schema template id
        $templateId = \is_string($templateIdRaw) && $templateIdRaw !== ''
            ? $templateIdRaw
            : null;

        // Read optional shared emit maps from schema property Antlers (by reference)
        $emitContextRaw = $params['emit_context'] ?? null;
        $emitContext = $emitContextRaw instanceof SchemaTemplateEmitContext
            ? $emitContextRaw
            : null;
        unset($emitContextRaw);

        if (! $emitContext instanceof SchemaTemplateEmitContext) {
            $stackEmit = SchemaTemplateEmitContextRuntimeStack::peek();

            if ($stackEmit instanceof SchemaTemplateEmitContext) {
                $emitContext = $stackEmit;
            }
        }

        // ---
        // Primary route/context entity: required before direct-mode collection; for
        // secondary, tertiary, and global modes it is resolved only when targets
        // exist so empty traversals skip Data::find / route resolution work.
        // ---
        $contextEntity = null;

        // Resolve entities from the selected traversal mode
        if ($mode === 'tertiary') {
            // Collect targets via asset (or other secondary) then term hop then field
            $collectStart = $profileEnabled ? microtime(true) : 0.0;
            $targets = $this->collectTargetsForTertiaryMode(
                $context,
                $params
            );
            if ($profileEnabled) {
                $this->recordProfileCollectorSample('tertiary', $targets, $collectStart);
            }
        } elseif ($mode === 'secondary') {
            // Collect targets via one relationship hop from secondary entities
            $collectStart = $profileEnabled ? microtime(true) : 0.0;
            $targets = $this->collectTargetsForSecondaryMode(
                $context,
                $params
            );
            if ($profileEnabled) {
                $this->recordProfileCollectorSample('secondary', $targets, $collectStart);
            }
        } elseif ($mode === 'global') {
            // Collect targets from a global set relationship field
            $collectStart = $profileEnabled ? microtime(true) : 0.0;
            $targets = $this->collectTargetsForGlobalMode($params);
            if ($profileEnabled) {
                $this->recordProfileCollectorSample('global', $targets, $collectStart);
            }
        } else {
            // Collect targets from the current context field directly
            $contextEntity = $this->resolveContextEntity($context);
            $collectStart = $profileEnabled ? microtime(true) : 0.0;
            $targets = $this->collectTargetsForDirectMode(
                $context,
                $params,
                $contextEntity
            );
            if ($profileEnabled) {
                $this->recordProfileCollectorSample('direct', $targets, $collectStart);
            }
        }

        // Filter direct-mode targets to taxonomy terms matching optional data equals rule
        if ($mode === 'direct') {
            $targets = $this->applyDirectTermDataEqualsFilter(
                $targets,
                $params
            );
        }

        // Check whether any targets remain after collection (and direct-only filter)
        if ($targets === []) {
            unset(
                $contextEntity,
                $emitContext,
                $mode,
                $modeRaw,
                $templateId,
                $templateIdRaw
            );

            return $this->jsonEncodeNodes([]);
        }

        // Resolve context entity for template merge when collection did not need it
        if (
            in_array($mode, ['secondary', 'tertiary', 'global'], true)
        ) {
            $contextEntity = $this->resolveContextEntity($context);
        }

        // Initialize the JSON-LD nodes list
        $nodes = [];

        // Track emitted @id values for deduplication
        $seenNodeIds = [];

        // Track canonical @ids that received a full node earlier in this tag call
        $builtFullCanonicalIds = [];

        // Build one JSON-LD node per resolved target entity
        foreach ($targets as $target) {
            // Normalize target payload to entity + optional template context overrides
            $resolvedTarget = $this->normalizeResolvedTarget($target);

            // Extract entity from the normalized target payload
            $targetEntity = $resolvedTarget['entity'];

            // Extract optional template context overrides from the normalized target payload
            $targetTemplateContextOverrides = $resolvedTarget['template_context'];

            // Resolve a template id from parameter or cascade
            $resolvedTemplateId = $templateId ?? $this->resolveTemplateIdForTarget(
                $targetEntity
            );
            // Check whether no template id is available for this target
            if (! \is_string($resolvedTemplateId)) {
                // Skip to the next target
                continue;
            }

            if ($resolvedTemplateId === '') {
                // Skip to the next target
                continue;
            }

            // Read whether the tag should merge source_entry/source_term into the target context
            $mergeSourceContext = ! \array_key_exists('merge_source_context', $params)
                || $this->parseBooleanParam($params['merge_source_context']);

            // ---
            // Resolve a cross-request fragment cache key when this build is
            // page-invariant: targets sourced from a global set, no source context
            // merged in, and no row-level template context overrides (issue UAMS-Web/uams-statamic-archive#937).
            // ---
            $fragmentCacheKey = null;

            // Check the page-invariance conditions before consulting the cache. The
            // target must not be the route entity itself: on an entity's own page
            // its node also renders as a graph slot, and reusing a fragment built
            // elsewhere shifts template instance `@id` suffix allocation for its
            // nested credential nodes.
            if (
                ! $mergeSourceContext
                && $targetTemplateContextOverrides === []
                && (
                    $mode === 'global'
                    || (\is_string($params['global_handle'] ?? null) && trim($params['global_handle']) !== '')
                )
                && ! $this->targetEntityIsContextEntity($targetEntity, $contextEntity)
                && $this->fragmentCache()->enabled()
            ) {
                // Build the generation-scoped key for this template/target pair
                $fragmentCacheKey = $this->fragmentCache()->keyFor(
                    $resolvedTemplateId,
                    $targetEntity
                );
            }

            // ---
            // Target template context is built lazily: a fragment cache hit never
            // needs it, and `buildTemplateContextForEntity` pays a full blueprint
            // augmentation per target (issue UAMS-Web/uams-statamic-archive#937: ~0.4s per person even when every
            // build is served from cache).
            // ---
            $targetTemplateContext = null;
            $templateContextEntity = null;

            // Build the base JSON-LD node from the schema template
            if ($emitContext instanceof SchemaTemplateEmitContext) {
                // Share emitted @id maps with the active graph for nested dedupe
                $sharedEmittedNodeIds = &$emitContext->emittedNodeIds;
                $sharedMaterializedFullNodeAtIds = &$emitContext->materializedFullNodeAtIds;

                // Resolve canonical @id for the target before any template expansion
                $preMergeCanonicalId = $this->resolveCanonicalIdForEntity(
                    $targetEntity
                );

                // Prefer a compact reference when this @id is already a full node
                $compactRef = null;
                if (
                    \is_string($preMergeCanonicalId)
                    && $preMergeCanonicalId !== ''
                ) {
                    // Check the graph pass map for a prior full emission
                    $compactRef = $this->jsonLdAtIdDeduplication
                        ->compactToReferenceIfFullNodeAlreadyEmitted(
                            $sharedEmittedNodeIds,
                            $preMergeCanonicalId
                        );

                    // Compact only when a full body for this @id survived merged output
                    if (
                        $compactRef !== null
                        && ! isset($sharedMaterializedFullNodeAtIds[$preMergeCanonicalId])
                    ) {
                        $compactRef = null;
                    }

                    // Check whether this tag call already built a full node for the id
                    if (
                        $compactRef === null
                        && isset($builtFullCanonicalIds[$preMergeCanonicalId])
                    ) {
                        $compactRef = ['@id' => $preMergeCanonicalId];
                    }
                }

                // Skip expensive template expansion when only a reference is needed
                if ($compactRef !== null) {
                    if ($profileEnabled) {
                        $this->recordProfileIncrement('relationship_compact_ref_hits', 1);
                    }

                    $baseNode = $compactRef;
                } else {
                    // Reuse a cross-request fragment for page-invariant builds
                    $cachedFragmentNode = $fragmentCacheKey !== null
                        ? $this->fragmentCache()->get($fragmentCacheKey)
                        : null;

                    // Check whether a cached fragment can stand in for the build
                    if ($cachedFragmentNode !== null) {
                        // Replay emit-map effects so later compact fast paths keep firing
                        $this->schemaOutputService->replayEmitSideEffectsFromCachedNodeFragment(
                            $cachedFragmentNode,
                            $emitContext->emittedEntryIds,
                            $sharedEmittedNodeIds,
                            $sharedMaterializedFullNodeAtIds
                        );

                        if ($profileEnabled) {
                            $this->recordProfileIncrement('relationship_fragment_cache_hits', 1);
                        }

                        $baseNode = $cachedFragmentNode;
                    } else {
                        // Build the deferred target context now that a build is needed
                        $preparedBuildContext = $this->prepareTargetTemplateContextForBuild(
                            $targetEntity,
                            $targetTemplateContextOverrides,
                            $mergeSourceContext,
                            $contextEntity
                        );
                        $targetTemplateContext = $preparedBuildContext['context'];
                        $templateContextEntity = $preparedBuildContext['entity'];
                        unset($preparedBuildContext);

                        // Build the full template node when this @id is not yet emitted
                        $buildStart = $profileEnabled ? microtime(true) : 0.0;
                        $baseNode = $this->schemaOutputService
                            ->buildStandaloneSchemaObjectNodeSharingEmittedNodeIds(
                                $resolvedTemplateId,
                                $templateContextEntity,
                                $targetTemplateContext,
                                $sharedEmittedNodeIds,
                                $sharedMaterializedFullNodeAtIds,
                                $emitContext->graphEntryIds,
                                $emitContext->templateInstanceWatermarkBag
                            );
                        if ($profileEnabled) {
                            $this->recordProfileFullBuildSample(
                                $mode,
                                $resolvedTemplateId,
                                $targetEntity,
                                $context,
                                $buildStart
                            );
                        }

                        // Store page-invariant full nodes for reuse by later composes
                        if (
                            $fragmentCacheKey !== null
                            && \is_array($baseNode)
                            && isset($baseNode['@type'])
                        ) {
                            $this->fragmentCache()->put(
                                $fragmentCacheKey,
                                $baseNode
                            );
                        }
                    }

                    unset($cachedFragmentNode);
                }

                unset(
                    $compactRef,
                    $preMergeCanonicalId
                );
            } else {
                // Reuse a cross-request fragment for page-invariant builds
                $cachedFragmentNode = $fragmentCacheKey !== null
                    ? $this->fragmentCache()->get($fragmentCacheKey)
                    : null;

                // Check whether a cached fragment can stand in for the build
                if ($cachedFragmentNode !== null) {
                    if ($profileEnabled) {
                        $this->recordProfileIncrement('relationship_fragment_cache_hits', 1);
                    }

                    $baseNode = $cachedFragmentNode;
                } else {
                    // Build the deferred target context now that a build is needed
                    $preparedBuildContext = $this->prepareTargetTemplateContextForBuild(
                        $targetEntity,
                        $targetTemplateContextOverrides,
                        $mergeSourceContext,
                        $contextEntity
                    );
                    $targetTemplateContext = $preparedBuildContext['context'];
                    $templateContextEntity = $preparedBuildContext['entity'];
                    unset($preparedBuildContext);

                    // Use isolated emit maps when the tag runs outside graph property templates
                    $buildStart = $profileEnabled ? microtime(true) : 0.0;
                    $baseNode = $this->schemaOutputService->buildStandaloneSchemaObjectNode(
                        $resolvedTemplateId,
                        $templateContextEntity,
                        $targetTemplateContext
                    );
                    if ($profileEnabled) {
                        $this->recordProfileFullBuildSample(
                            $mode,
                            $resolvedTemplateId,
                            $targetEntity,
                            $context,
                            $buildStart
                        );
                    }

                    // Store page-invariant full nodes for reuse by later composes
                    if (
                        $fragmentCacheKey !== null
                        && \is_array($baseNode)
                        && isset($baseNode['@type'])
                    ) {
                        $this->fragmentCache()->put(
                            $fragmentCacheKey,
                            $baseNode
                        );
                    }
                }

                unset($cachedFragmentNode);
            }

            // Check whether the schema template produced no node
            if ($baseNode === null) {
                // Skip to the next target
                continue;
            }

            if ($baseNode === []) {
                // Skip to the next target
                continue;
            }

            // Merge canonical target identity into the schema node
            $node = $this->mergeTemplateNodeWithTarget(
                $baseNode,
                $targetEntity
            );

            // Check whether node merge failed
            if ($node === []) {
                // Skip to the next target
                continue;
            }

            // Read the node @id key used for deduplication
            $nodeIdKey = \is_string($node['@id'] ?? null) ? $node['@id'] : '';

            // Append the node when @id is empty or first occurrence
            if ($this->jsonLdAtIdDeduplication->shouldEmitUniqueAtIdNode(
                $nodeIdKey,
                $seenNodeIds
            )) {
                $nodes[] = $node;
            }

            // When this tag is nested in a graph build, record full nodes so later
            // relationship traversals can compact to {"@id":...} instead of rebuilding.
            if (
                $emitContext instanceof SchemaTemplateEmitContext
                && isset($node['@type'])
                && \is_string($node['@id'] ?? null)
                && $node['@id'] !== ''
            ) {
                $sharedEmittedNodeIds = &$emitContext->emittedNodeIds;
                $sharedMaterializedFullNodeAtIds = &$emitContext->materializedFullNodeAtIds;

                $this->jsonLdAtIdDeduplication->recordFullSchemaObjectNodeEmitted(
                    $sharedEmittedNodeIds,
                    $node
                );
                $sharedMaterializedFullNodeAtIds[$node['@id']] = true;

                unset(
                    $sharedEmittedNodeIds,
                    $sharedMaterializedFullNodeAtIds
                );
            }

            // Record a full inline node for subsequent targets in this invocation
            if (
                isset($node['@type'])
                && \is_string($node['@id'] ?? null)
                && $node['@id'] !== ''
            ) {
                // Track full nodes built in this tag invocation only (shared map
                // is not updated here so nested fragments do not pre-mark @id as
                // emitted for unrelated later properties; see
                // SchemaJsonLdAtIdDeduplication class doc).
                $builtFullCanonicalIds[$node['@id']] = true;
            }

            // Unset local variables to free memory
            unset(
                $baseNode,
                $node,
                $nodeIdKey,
                $resolvedTemplateId,
                $resolvedTarget,
                $target,
                $targetEntity,
                $targetTemplateContext,
                $targetTemplateContextOverrides,
                $templateContextEntity,
                $fragmentCacheKey,
                $mergeSourceContext
            );

            if ($emitContext instanceof SchemaTemplateEmitContext) {
                unset(
                    $sharedEmittedNodeIds,
                    $sharedMaterializedFullNodeAtIds
                );
            }
        }

        // Unset local variables to free memory
        unset(
            $contextEntity,
            $emitContext,
            $mode,
            $modeRaw,
            $seenNodeIds,
            $builtFullCanonicalIds,
            $targets,
            $templateId,
            $templateIdRaw
        );

        // Encode nodes as JSON output
        if ($profileEnabled) {
            $elapsedMs = (microtime(true) - $resolveStart) * 1000;
            $this->recordProfileIncrement('relationship_resolve_calls', 1);
            $this->recordProfileAddMs('relationship_resolve_ms', $elapsedMs);
        }

        return $this->jsonEncodeNodes($nodes);
    }

    /**
     * Record a profiling sample for a target-collection pass.
     *
     * @param  string  $mode  Traversal mode.
     * @param  array<int, mixed>  $targets  Collected target entities.
     * @param  float  $start  Collection start timestamp.
     */
    private function recordProfileCollectorSample(
        string $mode,
        array $targets,
        float $start
    ): void {
        if (! app()->bound('request')) {
            return;
        }

        $elapsedMs = (microtime(true) - $start) * 1000;

        $callsKey = 'relationship_collect_'.$mode.'_calls';
        $msKey = 'relationship_collect_'.$mode.'_ms';
        $countKey = 'relationship_targets_'.$mode;

        $this->recordProfileIncrement($callsKey, 1);
        $this->recordProfileAddMs($msKey, $elapsedMs);
        $this->recordProfileIncrement($countKey, \count($targets));
    }

    /**
     * Record a profiling sample for a full canonical build.
     *
     * @param  string  $mode  Traversal mode.
     * @param  string  $schemaObject  Schema object type.
     * @param  mixed  $targetEntity  Resolved relationship target.
     * @param  array<string, mixed>  $context  Antlers context.
     * @param  float  $start  Build start timestamp.
     */
    private function recordProfileFullBuildSample(
        string $mode,
        string $schemaObject,
        mixed $targetEntity,
        array $context,
        float $start
    ): void {
        if (! app()->bound('request')) {
            return;
        }

        $elapsedMs = (microtime(true) - $start) * 1000;

        $this->recordProfileIncrement('relationship_full_build_calls', 1);
        $this->recordProfileAddMs('relationship_full_build_ms', $elapsedMs);

        $topLimitConfig = config('schema_templates.json_ld_compose_profile.top_n', 10);
        $topLimit = max(
            1,
            (int) (\is_scalar($topLimitConfig) ? $topLimitConfig : 10)
        );

        $tplEntryIdRaw = $context[SchemaOutputService::ANTLERS_SCHEMA_TEMPLATE_ENTRY_ID] ?? null;
        $propKeyRaw = $context[SchemaOutputService::ANTLERS_SCHEMA_PROPERTY_KEY] ?? null;

        $sample = [
            'ms' => round($elapsedMs, 2),
            'mode' => $mode,
            'schema_object' => $schemaObject,
            'target_entity_type' => $this->profileEntityType($targetEntity),
            'target_id' => $this->profileEntityId($targetEntity),
            'caller_template_entry_id' => \is_string($tplEntryIdRaw) ? $tplEntryIdRaw : null,
            'caller_property_key' => \is_string($propKeyRaw) ? $propKeyRaw : null,
        ];

        $bag = request()->attributes->get(self::PROFILE_BAG);
        if (! \is_array($bag)) {
            $bag = [];
        }

        $top = $bag['relationship_full_build_top'] ?? [];
        if (! \is_array($top)) {
            $top = [];
        }

        $top[] = $sample;

        /** @var list<array<string, mixed>> $top */
        usort($top, static function (array $a, array $b): int {
            $aMsRaw = $a['ms'] ?? 0.0;
            $bMsRaw = $b['ms'] ?? 0.0;
            $aMs = \is_numeric($aMsRaw) ? (float) $aMsRaw : 0.0;
            $bMs = \is_numeric($bMsRaw) ? (float) $bMsRaw : 0.0;

            return $bMs <=> $aMs;
        });

        if (\count($top) > $topLimit) {
            $top = \array_slice($top, 0, $topLimit);
        }

        $bag['relationship_full_build_top'] = $top;

        request()->attributes->set(self::PROFILE_BAG, $bag);
    }

    private function recordProfileIncrement(string $key, int $delta): void
    {
        $bag = request()->attributes->get(self::PROFILE_BAG);
        if (! \is_array($bag)) {
            $bag = [];
        }

        $currentCount = $bag[$key] ?? 0;
        $bag[$key] = (int) (\is_scalar($currentCount) ? $currentCount : 0) + $delta;

        request()->attributes->set(self::PROFILE_BAG, $bag);
    }

    private function recordProfileAddMs(string $key, float $elapsedMs): void
    {
        $bag = request()->attributes->get(self::PROFILE_BAG);
        if (! \is_array($bag)) {
            $bag = [];
        }

        $currentMs = $bag[$key] ?? 0.0;
        $bag[$key] = (float) (\is_scalar($currentMs) ? $currentMs : 0.0) + $elapsedMs;

        request()->attributes->set(self::PROFILE_BAG, $bag);
    }

    private function profileEntityType(mixed $entity): string
    {
        if ($entity instanceof EntryContract) {
            return 'entry';
        }

        if ($entity instanceof TermContract) {
            return 'term';
        }

        if ($entity instanceof Asset) {
            return 'asset';
        }

        if ($entity instanceof Variables) {
            return 'global';
        }

        return 'unknown';
    }

    private function profileEntityId(mixed $entity): string
    {
        if (
            \is_object($entity)
            && \str_starts_with(
                $entity::class,
                'Mockery_'
            )
        ) {
            return '';
        }

        if ($entity instanceof EntryModel) {
            $entryId = $entity->id();

            return \is_scalar($entryId) ? (string) $entryId : '';
        }

        if ($entity instanceof TaxonomyTerm) {
            $termId = $entity->id();

            return \is_scalar($termId) ? (string) $termId : '';
        }

        if ($entity instanceof LocalizedTerm) {
            $localizedTerm = $entity->term();
            $localizedTermId = $localizedTerm instanceof TaxonomyTerm ? $localizedTerm->id() : null;

            return \is_scalar($localizedTermId) ? (string) $localizedTermId : '';
        }

        if ($entity instanceof Asset) {
            $assetPath = $entity->path();

            return \is_scalar($assetPath) ? (string) $assetPath : '';
        }

        if ($entity instanceof Variables) {
            $variablesHandle = $entity->handle();

            return \is_scalar($variablesHandle) ? (string) $variablesHandle : '';
        }

        return '';
    }

    /**
     * Collect target entities directly from a relationship field.
     *
     * @param  array<string, mixed>  $context  Antlers context.
     * @param  array<string, mixed>  $params  Tag parameters.
     * @return array<int, mixed> Resolved target entities.
     */
    private function collectTargetsForDirectMode(
        array $context,
        array $params,
        EntryContract|TermContract|null $contextEntity
    ): array {
        // Read the direct/current field handles with fallback aliases
        $directHandles = $this->resolveFieldHandles(
            $params,
            'current_field'
        );

        // Check whether no usable direct/current field handles were provided
        if ($directHandles === []) {
            // ---
            // Return an empty array to signal no usable direct/current field handles were
            // provided
            // ---
            return [];
        }

        // Read whether current_field should be treated as a replicator field
        $isCurrentFieldReplicator = $this->parseBooleanParam(
            $params['current_field_replicator'] ?? null
        );

        // Resolve replicator relationship field handles for current_field
        $currentReplicatorRelationshipHandles = $this->resolveFieldHandles(
            $params,
            'current_field_replicator_relationship_field'
        );

        // Read optional scope for normalizing direct/current field items (e.g., bare term slugs)
        $directNormalizationScope = \is_string($params['direct_scope'] ?? null)
            ? trim($params['direct_scope'])
            : null;

        // Check if the direct normalization scope is an empty string
        if ($directNormalizationScope === '') {
            // Treat empty string as no scope
            $directNormalizationScope = null;
        }

        // Check whether any handle uses Antlers-only `source_entry.*` or
        // `replicator_row.*` forms (not bare entry field names on `contextEntity`)
        $directHandlesUseAntlersContextFieldForms = false;
        foreach ($directHandles as $directHandleCandidate) {
            if (
                \str_starts_with($directHandleCandidate, 'source_entry.')
                || \str_starts_with($directHandleCandidate, 'replicator_row.')
            ) {
                $directHandlesUseAntlersContextFieldForms = true;
                break;
            }
        }

        // Read context `id` when present for anchoring authoritative entry reads
        $contextIdForAuthoritativeEntryRead = data_get($context, 'id');

        // Fall back to the scoped `entry` id when the flattened values bag omits `id`
        if (
            (! \is_string($contextIdForAuthoritativeEntryRead) || $contextIdForAuthoritativeEntryRead === '')
            && isset($context['entry'])
            && $context['entry'] instanceof EntryModel
            && ! \str_starts_with(
                $context['entry']::class,
                'Mockery_'
            )
        ) {
            $contextIdForAuthoritativeEntryRead = $context['entry']->id();
        }

        // Check whether context `id` matches the resolved entry (route entity alone
        // is not enough — unit tests and overlays may omit `id` and supply fields
        // only in the context array)
        $contextAnchorsAuthoritativeStatamicEntryFieldRead = false;
        if (
            $contextEntity instanceof EntryModel
            && ! \str_starts_with(
                $contextEntity::class,
                'Mockery_'
            )
            && \is_string($contextIdForAuthoritativeEntryRead)
            && $contextIdForAuthoritativeEntryRead !== ''
        ) {
            $contextAnchorsAuthoritativeStatamicEntryFieldRead = $contextEntity->id() === $contextIdForAuthoritativeEntryRead;
        }

        // Prefer reading direct/current field values from the stored entry when the
        // Antlers bag is scoped to that entry so empty fields do not pick up stale
        // scope from earlier renders
        $rawTargets = null;
        $usedAuthoritativeStatamicEntryFieldRead = false;
        if (
            ! $directHandlesUseAntlersContextFieldForms
            && $contextAnchorsAuthoritativeStatamicEntryFieldRead
        ) {
            $rawTargets = $this->readFirstNonEmptyFieldValueFromEntity(
                $contextEntity,
                $directHandles
            );
            $usedAuthoritativeStatamicEntryFieldRead = true;
        }

        // Fall back to the Antlers context bag only when we did not read from a
        // real Statamic entry (mocks use context). When an authoritative entry
        // read did run, its result — empty or not — is authoritative, so we do
        // not merge leaked ambient keys. Reaching here without an authoritative
        // read means $rawTargets is still null, so the fallback always applies.
        if (! $usedAuthoritativeStatamicEntryFieldRead) {
            $rawTargets = $this->readFirstNonEmptyFieldValueFromContext(
                $context,
                $directHandles
            );
        }

        // Expand direct/current field value into target candidates
        $items = $this->expandDirectModeRawTargetsToItems(
            $rawTargets,
            $isCurrentFieldReplicator,
            $currentReplicatorRelationshipHandles
        );

        // Parse optional direct/current item limit
        $directLimit = $this->parseOptionalPositiveInt(
            $params['current_limit'] ?? null
        );

        // ---
        // Apply direct/current item limit when configured
        // ---

        // Check if a direct/current item limit is configured
        if ($directLimit !== null) {
            // Truncate the items list to the direct/current item limit
            $items = \array_slice($items, 0, $directLimit);
        }

        // Resolve each expanded item to supported entities
        $targets = $this->collectDirectTargetsFromExpandedItems(
            $items,
            $directNormalizationScope
        );

        // Initialize optional source-entry handle list for memory cleanup
        $handlesForSourceEntryObjectRead = null;

        // ---
        // When asset (or overlay) context exposes an empty relationship query
        // for the same handle (e.g., `about` on augmented audio assets), fall back
        // to the parent entry field merged as `source_entry`.
        // ---

        // Check whether no targets resolved and a source entry is available
        if (
            $targets === []
            && isset($context['source_entry'])
            && $context['source_entry'] instanceof EntryContract
        ) {
            // Normalize handles so `source_entry.field` reads `field` on the entry
            $handlesForSourceEntryObjectRead = $this->stripSourceEntryPrefixFromRelationshipFieldHandles(
                $directHandles
            );

            // Read the same field(s) from the source entry when handles remain
            $rawTargets = $handlesForSourceEntryObjectRead === []
                ? null
                : $this->readFirstNonEmptyFieldValueFromEntity(
                    $context['source_entry'],
                    $handlesForSourceEntryObjectRead
                );

            // Check whether the source entry yielded a usable relationship value
            if (
                $rawTargets !== null
                && ! $this->isEmptyRelationshipValue($rawTargets)
            ) {
                // Re-expand items from the source entry field value
                $items = $this->expandDirectModeRawTargetsToItems(
                    $rawTargets,
                    $isCurrentFieldReplicator,
                    $currentReplicatorRelationshipHandles
                );

                // Check if a direct/current item limit is configured
                if ($directLimit !== null) {
                    // Truncate the items list to the direct/current item limit
                    $items = \array_slice($items, 0, $directLimit);
                }

                // Build targets from source-entry field items
                $targets = $this->collectDirectTargetsFromExpandedItems(
                    $items,
                    $directNormalizationScope
                );
            }
        }

        // Unset local variables to free memory
        unset(
            $contextAnchorsAuthoritativeStatamicEntryFieldRead,
            $contextIdForAuthoritativeEntryRead,
            $currentReplicatorRelationshipHandles,
            $directHandleCandidate,
            $directHandles,
            $directHandlesUseAntlersContextFieldForms,
            $directLimit,
            $directNormalizationScope,
            $handlesForSourceEntryObjectRead,
            $isCurrentFieldReplicator,
            $items,
            $usedAuthoritativeStatamicEntryFieldRead,
            $rawTargets
        );

        // Return direct/current mode targets
        return $targets;
    }

    /**
     * Strip `source_entry.` prefixes from relationship field handles for reads on a
     * bare entry object.
     *
     * @param  array<int, string>  $handles  Handles parsed from `current_field`.
     * @return array<int, string> Handles suitable for Statamic entry `value()` / `get()` reads on
     *                            `EntryContract`.
     */
    private function stripSourceEntryPrefixFromRelationshipFieldHandles(
        array $handles
    ): array {
        $stripped = [];

        foreach ($handles as $handle) {
            if ($handle === '') {
                continue;
            }

            if (\str_starts_with($handle, 'source_entry.')) {
                $suffix = \substr(
                    $handle,
                    \strlen('source_entry.')
                );

                if ($suffix !== '') {
                    $stripped[] = $suffix;
                }
            } else {
                $stripped[] = $handle;
            }

            unset($handle);
        }

        return $stripped;
    }

    /**
     * Expand raw direct-mode field data into a flat list of items or replicator payloads.
     *
     * @param  mixed  $rawTargets  Raw field value from context or entity.
     * @param  bool  $isCurrentFieldReplicator  Whether the field is a replicator.
     * @param  array<int, string>  $currentReplicatorRelationshipHandles  Subfield handles.
     * @return array<int, mixed> Items for normalization.
     */
    private function expandDirectModeRawTargetsToItems(
        mixed $rawTargets,
        bool $isCurrentFieldReplicator,
        array $currentReplicatorRelationshipHandles
    ): array {
        // Expand replicator rows into relationship targets when configured
        if ($isCurrentFieldReplicator) {
            // Extract per-row relationship entities and template context
            return $this->extractReplicatorTargets(
                $rawTargets,
                $currentReplicatorRelationshipHandles
            );
        }

        // Normalize a plain relationship value to a list
        return $this->normalizeRawToList($rawTargets);
    }

    /**
     * Normalize expanded direct-mode items to entity targets (with optional row context).
     *
     * @param  array<int, mixed>  $items  Expanded items from the direct field.
     * @param  string|null  $directNormalizationScope  Optional scope (e.g., taxonomy handle).
     * @return array<int, mixed> Resolved targets (entities or structured payloads).
     */
    private function collectDirectTargetsFromExpandedItems(
        array $items,
        ?string $directNormalizationScope
    ): array {
        // Initialize the targets array
        $targets = [];

        // Loop through each item
        foreach ($items as $item) {
            // Initialize optional template context overrides
            $targetTemplateContext = [];

            // Extract raw target entity from structured item when present
            $targetRaw = \is_array($item) && \array_key_exists('entity', $item)
                ? $item['entity']
                : $item;

            // ---
            // Extract template context overrides from structured item when present
            // ---

            // Check if the item is an array and has a template context key
            if (
                \is_array($item)
                && \is_array($item['template_context'] ?? null)
            ) {
                // Merge template context overrides into computed entity context
                $targetTemplateContext = $item['template_context'];
            }

            // Attempt to normalize this item to an entity
            $target = $this->normalizeToAnySupportedEntity(
                $targetRaw,
                $directNormalizationScope
            );

            // ---
            // Append normalized entities, or replicator rows that only supply template
            // context (e.g., external video URLs on the row with no relationship asset)
            // ---

            // Check if the target resolved to a supported entity
            if ($target !== null) {
                // Append target payload with optional template context overrides
                $targets[] = $targetTemplateContext !== []
                    ? [
                        'entity' => $target,
                        'template_context' => $targetTemplateContext,
                    ]
                    : $target;
            } elseif ($targetTemplateContext !== []) {
                // Append row-driven payload using optional URL (or null) for canonical @id
                $targets[] = [
                    'entity' => $targetRaw,
                    'template_context' => $targetTemplateContext,
                ];
            }

            // Unset local variables to free memory
            unset(
                $item,
                $target,
                $targetRaw,
                $targetTemplateContext
            );
        }

        // Unset local variables to free memory
        unset(
            $items
        );

        // Return normalized direct targets
        return $targets;
    }

    /**
     * Collect target entities from secondary traversal mode.
     *
     * Reads first-hop relationship values from `current_field` on the Antlers
     * context, or from `global_field` on `global_handle` when that global set
     * handle is set. Normalizes each first-hop item to `secondary_context`,
     * then reads `secondary_field` entities from those normalized entities.
     *
     * @param  array<string, mixed>  $context  Antlers context.
     * @param  array<string, mixed>  $params  Tag parameters.
     * @return array<int, mixed> Resolved nested target entities.
     */
    private function collectTargetsForSecondaryMode(
        array $context,
        array $params
    ): array {
        // Normalize the configured secondary context type
        $secondaryContext = $this->normalizeContextType(
            $params['secondary_context'] ?? null,
            ['globals', 'entries', 'assets', 'terms']
        );

        // Check whether secondary_context is missing or invalid
        if ($secondaryContext === null) {
            // Return an empty array to signal no usable secondary context was provided
            return [];
        }

        // Read whether current_field should be treated as a replicator field
        $isCurrentFieldReplicator = $this->parseBooleanParam(
            $params['current_field_replicator'] ?? null
        );

        // Read whether secondary_field should be treated as a replicator field
        $isSecondaryFieldReplicator = $this->parseBooleanParam(
            $params['secondary_field_replicator'] ?? null
        );

        // Resolve direct/current field handles for secondary traversal
        $directHandles = $this->resolveFieldHandles(
            $params,
            'current_field'
        );

        // Resolve secondary field handles on secondary entities
        $secondaryHandles = $this->resolveFieldHandles(
            $params,
            'secondary_field',
        );

        // Read optional global set handle so the first hop can read a global field
        $globalHandleForFirstHopRaw = $params['global_handle'] ?? null;

        // Normalize global first-hop handle to null when missing or blank
        $globalHandleForFirstHop = \is_string($globalHandleForFirstHopRaw)
            ? trim($globalHandleForFirstHopRaw)
            : '';

        // Check if the global first-hop handle is empty after trimming
        if ($globalHandleForFirstHop === '') {
            // Treat empty global handle as unset
            $globalHandleForFirstHop = null;
        }

        // Resolve global-field handles for the first hop when a global set is configured
        $globalFirstHopHandles = $globalHandleForFirstHop !== null
            ? $this->resolveFieldHandles(
                $params,
                'global_field'
            )
            : [];

        // Resolve replicator relationship field handles for current_field
        $currentReplicatorRelationshipHandles = $this->resolveFieldHandles(
            $params,
            'current_field_replicator_relationship_field'
        );

        // Resolve replicator relationship field handles for secondary_field
        $secondaryReplicatorRelationshipHandles = $this->resolveFieldHandles(
            $params,
            'secondary_field_replicator_relationship_field'
        );

        // Check whether the secondary field handle set is missing
        if ($secondaryHandles === []) {
            // Return an empty array to signal no usable secondary field handles were provided
            return [];
        }

        // Check whether the first-hop field source is missing or invalid
        if ($globalHandleForFirstHop !== null) {
            // Check whether global first-hop field handles are missing
            if ($globalFirstHopHandles === []) {
                // Return an empty array when global_field is required but absent
                return [];
            }
        } elseif ($directHandles === []) {
            // Return an empty array when context current_field handles are missing
            return [];
        }

        // Normalize optional secondary context scope disambiguator
        $secondaryScope = \is_string($params['secondary_scope'] ?? null)
            ? trim($params['secondary_scope'])
            : null;

        // Check if the secondary scope is an empty string
        if ($secondaryScope === '') {
            // Treat empty string as no scope
            $secondaryScope = null;
        }

        // Read optional scope for nested secondary_field items (falls back to secondary_scope)
        $nestedScopeRaw = \is_string($params['nested_scope'] ?? null)
            ? trim($params['nested_scope'])
            : null;

        // Check if the nested scope is an empty string
        if ($nestedScopeRaw === '') {
            // Treat empty string as no nested scope override
            $nestedScopeRaw = null;
        }

        // Resolve nested normalization scope (nested_scope overrides secondary_scope)
        $nestedNormalizationScope = $nestedScopeRaw ?? $secondaryScope;

        // Read direct/current mode limit for secondary entities
        $directLimit = $this->parseOptionalPositiveInt(
            $params['current_limit'] ?? null
        );

        // Read the optional secondary field limit
        $secondaryLimit = $this->parseOptionalPositiveInt($params['secondary_limit'] ?? null);

        // Read first-hop relationship value from a global set or from Antlers context
        if ($globalHandleForFirstHop !== null) {
            // Resolve the global set for the configured handle
            $globalSetForFirstHop = GlobalSet::findByHandle($globalHandleForFirstHop);

            // Check if the global set could not be resolved
            if ($globalSetForFirstHop === null) {
                // Unset locals before returning
                unset(
                    $currentReplicatorRelationshipHandles,
                    $directHandles,
                    $directLimit,
                    $globalFirstHopHandles,
                    $globalHandleForFirstHop,
                    $globalHandleForFirstHopRaw,
                    $isCurrentFieldReplicator,
                    $isSecondaryFieldReplicator,
                    $nestedNormalizationScope,
                    $nestedScopeRaw,
                    $secondaryContext,
                    $secondaryHandles,
                    $secondaryReplicatorRelationshipHandles,
                    $secondaryLimit,
                    $secondaryScope
                );

                // Return no targets when the global set is missing
                return [];
            }

            // Resolve current-site variables for the global set
            $globalVariablesForFirstHop = $globalSetForFirstHop->inCurrentSite();

            // Read the first non-empty global field value as secondary entity candidates
            $rawSecondaryEntities = $this->readFirstNonEmptyFieldValueFromEntity(
                $globalVariablesForFirstHop,
                $globalFirstHopHandles
            );

            // Unset global first-hop locals after reading the relationship value
            unset(
                $globalSetForFirstHop,
                $globalVariablesForFirstHop
            );
        } else {
            // Read secondary entities from current context direct/current field
            $rawSecondaryEntities = $this->readFirstNonEmptyFieldValueFromContext(
                $context,
                $directHandles
            );
        }

        // Expand direct/current field into secondary entities list
        $secondaryItems = $isCurrentFieldReplicator
            ? $this->extractReplicatorTargets(
                $rawSecondaryEntities,
                $currentReplicatorRelationshipHandles
            )
            : $this->normalizeRawToList($rawSecondaryEntities);

        // ---
        // Apply direct/current limit to secondary entities
        // ---

        // Check if a direct/current limit is configured
        if ($directLimit !== null) {
            // Truncate the secondary items list to the direct/current limit
            $secondaryItems = \array_slice($secondaryItems, 0, $directLimit);
        }

        // Initialize final target entities
        $targets = [];

        // ---
        // Resolve each secondary entity and collect its related targets
        // ---

        // Loop through each secondary item
        foreach ($secondaryItems as $secondaryItem) {
            // Extract the relationship value from structured replicator rows (same shape as
            // extractReplicatorTargets: entity + optional template_context)
            $secondaryItemRaw = \is_array($secondaryItem) && \array_key_exists('entity', $secondaryItem)
                ? $secondaryItem['entity']
                : $secondaryItem;

            // Normalize the current item into the configured secondary context type
            $secondaryEntity = $this->normalizeToEntityByContextType(
                $secondaryItemRaw,
                $secondaryContext,
                $secondaryScope
            );

            // Check if the secondary entity is not null (i.e., unresolved)
            if ($secondaryEntity === null) {
                // Unset local variables to free memory
                unset(
                    $secondaryItem,
                    $secondaryItemRaw
                );

                // Skip to the next secondary item
                continue;
            }

            // Read the first non-empty secondary field value for this entity
            $rawNestedTargets = $this->readFirstNonEmptyFieldValueFromEntity(
                $secondaryEntity,
                $secondaryHandles
            );

            // Expand secondary relationship value to a list of items
            $nestedItems = $isSecondaryFieldReplicator
                ? $this->extractReplicatorTargets(
                    $rawNestedTargets,
                    $secondaryReplicatorRelationshipHandles
                )
                : $this->normalizeRawToList($rawNestedTargets);

            // Check if a secondary limit is configured
            if ($secondaryLimit !== null) {
                // ---
                // Truncate the nested items list to the secondary limit (i.e., apply the optional
                // secondary limit)
                // ---
                $nestedItems = \array_slice($nestedItems, 0, $secondaryLimit);
            }

            // ---
            // Normalize nested items to supported entities
            // ---

            // Loop through each nested item
            foreach ($nestedItems as $nestedItem) {
                // Initialize optional template context overrides
                $targetTemplateContext = [];

                // Extract raw target entity from structured nested item when present
                $targetRaw = \is_array($nestedItem) && \array_key_exists('entity', $nestedItem)
                    ? $nestedItem['entity']
                    : $nestedItem;

                // ---
                // Extract template context overrides from structured nested item when present
                // ---

                // Check if the nested item is an array and has a template context key
                if (
                    \is_array($nestedItem)
                    && \is_array($nestedItem['template_context'] ?? null)
                ) {
                    // Merge template context overrides into computed entity context
                    $targetTemplateContext = $nestedItem['template_context'];
                }

                // Attempt to normalize nested relation item to an entity
                $target = $this->normalizeToAnySupportedEntity(
                    $targetRaw,
                    $nestedNormalizationScope
                );

                // Check if the target is not null (i.e., successfully normalized)
                if ($target !== null) {
                    // Append target payload with optional template context overrides
                    $targets[] = $targetTemplateContext !== []
                        ? [
                            'entity' => $target,
                            'template_context' => $targetTemplateContext,
                        ]
                        : $target;
                }

                // Unset local variables to free memory
                unset(
                    $nestedItem,
                    $targetRaw,
                    $target
                );
            }

            // Unset local variables to free memory
            unset(
                $nestedItems,
                $rawNestedTargets,
                $secondaryEntity,
                $secondaryItem,
                $secondaryItemRaw
            );
        }

        // Unset local variables to free memory
        unset(
            $currentReplicatorRelationshipHandles,
            $directHandles,
            $directLimit,
            $globalFirstHopHandles,
            $globalHandleForFirstHop,
            $globalHandleForFirstHopRaw,
            $isCurrentFieldReplicator,
            $isSecondaryFieldReplicator,
            $nestedNormalizationScope,
            $nestedScopeRaw,
            $rawSecondaryEntities,
            $secondaryContext,
            $secondaryHandles,
            $secondaryReplicatorRelationshipHandles,
            $secondaryItems,
            $secondaryLimit,
            $secondaryScope
        );

        // Return targets collected through secondary traversal
        return $targets;
    }

    /**
     * Collect targets through two hops after the secondary entity (e.g. asset
     * `series` term, then that term's `about` subject_matter terms).
     *
     * Parameters mirror secondary mode, plus:
     * - `tertiary_field`: field handle(s) on the middle term (`|`-separated)
     * - `intermediate_scope`: taxonomy handle(s) for normalizing `secondary_field`
     *   values on the secondary entity when they are term refs; pipe-separated
     *   values try each taxonomy (e.g. `audio_series|video_series`)
     * - `tertiary_field_replicator` / `tertiary_field_replicator_relationship_field`:
     *   same meaning as secondary_* for the innermost field
     *
     * @param  array<string, mixed>  $context  Antlers context.
     * @param  array<string, mixed>  $params  Tag parameters.
     * @return array<int, mixed> Resolved innermost target entities.
     */
    private function collectTargetsForTertiaryMode(
        array $context,
        array $params
    ): array {
        // Normalize the configured secondary context type
        $secondaryContext = $this->normalizeContextType(
            $params['secondary_context'] ?? null,
            [
                'globals',
                'entries',
                'assets',
                'terms',
            ]
        );

        // Return no targets when secondary_context is invalid
        if ($secondaryContext === null) {
            return [];
        }

        // Read whether current_field should be treated as a replicator field
        $isCurrentFieldReplicator = $this->parseBooleanParam(
            $params['current_field_replicator'] ?? null
        );

        // Read whether secondary_field should be treated as a replicator field
        $isSecondaryFieldReplicator = $this->parseBooleanParam(
            $params['secondary_field_replicator'] ?? null
        );

        // Read whether tertiary_field should be treated as a replicator field
        $isTertiaryFieldReplicator = $this->parseBooleanParam(
            $params['tertiary_field_replicator'] ?? null
        );

        // Resolve direct/current field handles for the first hop
        $directHandles = $this->resolveFieldHandles(
            $params,
            'current_field'
        );

        // Resolve field handles read from each secondary entity (e.g. asset `series`)
        $secondaryHandles = $this->resolveFieldHandles(
            $params,
            'secondary_field',
        );

        // Resolve field handles read from each middle taxonomy term (e.g. `about`)
        $tertiaryHandles = $this->resolveFieldHandles(
            $params,
            'tertiary_field'
        );

        // Resolve replicator relationship field handles for current_field
        $currentReplicatorRelationshipHandles = $this->resolveFieldHandles(
            $params,
            'current_field_replicator_relationship_field'
        );

        // Resolve replicator relationship field handles for secondary_field
        $secondaryReplicatorRelationshipHandles = $this->resolveFieldHandles(
            $params,
            'secondary_field_replicator_relationship_field'
        );

        // Resolve replicator relationship field handles for tertiary_field
        $tertiaryReplicatorRelationshipHandles = $this->resolveFieldHandles(
            $params,
            'tertiary_field_replicator_relationship_field'
        );

        // Return no targets when a required field handle set is missing
        if (
            $directHandles === []
            || $secondaryHandles === []
            || $tertiaryHandles === []
        ) {
            return [];
        }

        // Normalize optional secondary context scope (e.g. asset container)
        $secondaryScope = \is_string($params['secondary_scope'] ?? null)
            ? trim($params['secondary_scope'])
            : null;

        // Treat empty secondary scope as unset
        if ($secondaryScope === '') {
            $secondaryScope = null;
        }

        // Normalize optional scope for middle hop term resolution
        $intermediateScopeRaw = \is_string($params['intermediate_scope'] ?? null)
            ? trim($params['intermediate_scope'])
            : null;

        // Treat empty intermediate scope as unset
        if ($intermediateScopeRaw === '') {
            $intermediateScopeRaw = null;
        }

        // Read optional nested_scope for the innermost relationship items
        $nestedScopeRaw = \is_string($params['nested_scope'] ?? null)
            ? trim($params['nested_scope'])
            : null;

        // Treat empty nested scope as unset
        if ($nestedScopeRaw === '') {
            $nestedScopeRaw = null;
        }

        // Resolve final item normalization scope (same rule as secondary mode)
        $finalNormalizationScope = $nestedScopeRaw ?? $secondaryScope;

        // Read optional limit for secondary entities (first expanded hop)
        $directLimit = $this->parseOptionalPositiveInt(
            $params['current_limit'] ?? null
        );

        // Read optional limit for items read from secondary_field per entity
        $secondaryLimit = $this->parseOptionalPositiveInt(
            $params['secondary_limit'] ?? null
        );

        // Read secondary entities from the Antlers context
        $rawSecondaryEntities = $this->readFirstNonEmptyFieldValueFromContext(
            $context,
            $directHandles
        );

        // Expand current_field into secondary entity candidates
        $secondaryItems = $isCurrentFieldReplicator
            ? $this->extractReplicatorTargets(
                $rawSecondaryEntities,
                $currentReplicatorRelationshipHandles
            )
            : $this->normalizeRawToList($rawSecondaryEntities);

        // Truncate secondary entity list when a direct limit is configured
        if ($directLimit !== null) {
            $secondaryItems = \array_slice(
                $secondaryItems,
                0,
                $directLimit
            );
        }

        // Initialize collected innermost targets
        $targets = [];

        // Walk each secondary entity candidate
        foreach ($secondaryItems as $secondaryItem) {
            // Extract raw entity from structured replicator payloads
            $secondaryItemRaw = \is_array($secondaryItem) && \array_key_exists(
                'entity',
                $secondaryItem
            )
                ? $secondaryItem['entity']
                : $secondaryItem;

            // Normalize into the configured secondary context type
            $secondaryEntity = $this->normalizeToEntityByContextType(
                $secondaryItemRaw,
                $secondaryContext,
                $secondaryScope
            );

            // Skip unresolved secondary entities
            if ($secondaryEntity === null) {
                unset(
                    $secondaryItem,
                    $secondaryItemRaw
                );

                continue;
            }

            // Read middle-hop field from the secondary entity (e.g. series)
            $rawMiddleTargets = $this->readFirstNonEmptyFieldValueFromEntity(
                $secondaryEntity,
                $secondaryHandles
            );

            // Expand middle field values to a list
            $middleItems = $isSecondaryFieldReplicator
                ? $this->extractReplicatorTargets(
                    $rawMiddleTargets,
                    $secondaryReplicatorRelationshipHandles
                )
                : $this->normalizeRawToList($rawMiddleTargets);

            // Truncate middle items when a secondary limit is configured
            if ($secondaryLimit !== null) {
                $middleItems = \array_slice(
                    $middleItems,
                    0,
                    $secondaryLimit
                );
            }

            // Walk each middle item (expected to resolve to a taxonomy term)
            foreach ($middleItems as $middleItem) {
                // Extract raw middle entity from structured payloads
                $middleItemRaw = \is_array($middleItem) && \array_key_exists(
                    'entity',
                    $middleItem
                )
                    ? $middleItem['entity']
                    : $middleItem;

                // Normalize the middle item to a taxonomy term
                $middleTerm = $this->normalizeToTerm(
                    $middleItemRaw,
                    $intermediateScopeRaw
                );

                // Skip items that do not resolve to a term
                if (! $middleTerm instanceof TaxonomyTerm) {
                    unset(
                        $middleItem,
                        $middleItemRaw,
                        $middleTerm
                    );

                    continue;
                }

                // Read the innermost field from the middle term
                $rawInnermostTargets = $this->readFirstNonEmptyFieldValueFromEntity(
                    $middleTerm,
                    $tertiaryHandles
                );

                // Expand innermost field to a list
                $innermostItems = $isTertiaryFieldReplicator
                    ? $this->extractReplicatorTargets(
                        $rawInnermostTargets,
                        $tertiaryReplicatorRelationshipHandles
                    )
                    : $this->normalizeRawToList($rawInnermostTargets);

                // Normalize each innermost item to a supported entity
                foreach ($innermostItems as $innermostItem) {
                    // Initialize optional template context overrides
                    $targetTemplateContext = [];

                    // Extract raw target from structured replicator payloads
                    $targetRaw = \is_array($innermostItem) && \array_key_exists(
                        'entity',
                        $innermostItem
                    )
                        ? $innermostItem['entity']
                        : $innermostItem;

                    // Merge template context overrides when present
                    if (
                        \is_array($innermostItem)
                        && \is_array($innermostItem['template_context'] ?? null)
                    ) {
                        $targetTemplateContext = $innermostItem['template_context'];
                    }

                    // Normalize the innermost relationship item
                    $target = $this->normalizeToAnySupportedEntity(
                        $targetRaw,
                        $finalNormalizationScope
                    );

                    // Append resolved targets
                    if ($target !== null) {
                        $targets[] = $targetTemplateContext !== []
                            ? [
                                'entity' => $target,
                                'template_context' => $targetTemplateContext,
                            ]
                            : $target;
                    }

                    unset(
                        $innermostItem,
                        $target,
                        $targetRaw,
                        $targetTemplateContext
                    );
                }

                unset(
                    $middleItem,
                    $middleItemRaw,
                    $middleTerm,
                    $innermostItems,
                    $rawInnermostTargets
                );
            }

            unset(
                $middleItems,
                $rawMiddleTargets,
                $secondaryEntity,
                $secondaryItem,
                $secondaryItemRaw
            );
        }

        // Unset method-local variables to free memory
        unset(
            $currentReplicatorRelationshipHandles,
            $directHandles,
            $directLimit,
            $finalNormalizationScope,
            $intermediateScopeRaw,
            $isCurrentFieldReplicator,
            $isSecondaryFieldReplicator,
            $isTertiaryFieldReplicator,
            $nestedScopeRaw,
            $rawSecondaryEntities,
            $secondaryContext,
            $secondaryHandles,
            $secondaryItems,
            $secondaryLimit,
            $secondaryReplicatorRelationshipHandles,
            $secondaryScope,
            $tertiaryHandles,
            $tertiaryReplicatorRelationshipHandles
        );

        // Return targets collected through tertiary traversal
        return $targets;
    }

    /**
     * Collect target entities directly from a global set relationship field.
     *
     * @param  array<string, mixed>  $params  Tag parameters.
     * @return array<int, mixed> Resolved target entities.
     */
    private function collectTargetsForGlobalMode(array $params): array
    {
        // Read global set handle from parameters
        $globalHandleRaw = $params['global_handle'] ?? null;

        // Normalize global set handle to a non-empty string
        $globalHandle = \is_string($globalHandleRaw) ? trim($globalHandleRaw) : '';

        // Check if the global set handle is missing
        if ($globalHandle === '') {
            // Return an empty array to signal no global set handle was provided
            return [];
        }

        // Resolve relationship field handles from global_field parameter
        $globalHandles = $this->resolveFieldHandles(
            $params,
            'global_field'
        );

        // Check if no usable global field handles were provided
        if ($globalHandles === []) {
            // Return an empty array to signal no global field handles were provided
            return [];
        }

        // Resolve the global set by handle
        $globalSet = GlobalSet::findByHandle($globalHandle);

        // Check if the global set could not be resolved
        if ($globalSet === null) {
            // Return an empty array when the global set is not found
            return [];
        }

        // Resolve current-site variables for the global set
        $variables = $globalSet->inCurrentSite();

        // Read the first non-empty relationship value from global variables
        $rawTargets = $this->readFirstNonEmptyFieldValueFromEntity(
            $variables,
            $globalHandles
        );

        // Normalize the global relationship value into sequential items
        $items = $this->normalizeRawToList($rawTargets);

        // Parse optional item limit from current_limit parameter
        $globalLimit = $this->parseOptionalPositiveInt(
            $params['current_limit'] ?? null
        );

        // Check if a global item limit is configured
        if ($globalLimit !== null) {
            // Truncate the items list to the configured limit
            $items = \array_slice($items, 0, $globalLimit);
        }

        // Initialize the normalized targets list
        $targets = [];

        // Loop through each raw relationship item
        foreach ($items as $item) {
            // Normalize the item to any supported entity type
            $target = $this->normalizeToAnySupportedEntity(
                $item
            );

            // Check if normalization produced a supported entity
            if ($target !== null) {
                // Append normalized target to the results list
                $targets[] = $target;
            }

            // Unset loop-local variables to free memory
            unset(
                $item,
                $target
            );
        }

        // Unset method-local variables to free memory
        unset(
            $globalHandle,
            $globalHandleRaw,
            $globalHandles,
            $globalLimit,
            $globalSet,
            $items,
            $rawTargets,
            $variables
        );

        // Return normalized targets collected from the global set
        return $targets;
    }

    /**
     * Resolve field handles from one tag parameter key.
     *
     * @param  array<string, mixed>  $params  Tag parameters.
     * @param  string  $fieldKey  Parameter key that stores `|`-separated handles.
     * @return array<int, string> Ordered field handles.
     */
    private function resolveFieldHandles(
        array $params,
        string $fieldKey
    ): array {
        // Read field parameter value by key
        $fieldRaw = $params[$fieldKey] ?? null;

        // Parse and return ordered field handles
        return SchemaTagFieldHandles::listFromParam($fieldRaw);
    }

    /**
     * Read the first non-empty field value from Antlers context.
     *
     * @param  array<string, mixed>  $context  Antlers context.
     * @param  array<int, string>  $handles  Ordered field handles.
     * @return mixed First non-empty field value, or null.
     */
    private function readFirstNonEmptyFieldValueFromContext(
        array $context,
        array $handles
    ): mixed {
        // Attempt each handle in order and return first non-empty value
        foreach ($handles as $handle) {
            // Initialize the candidate field value
            $value = null;

            // Read from explicit `source_entry.{field}` (Statamic entry fields are not
            // visible to `data_get` on the entry object)
            if (str_starts_with($handle, 'source_entry.')) {
                $prefixedField = substr(
                    $handle,
                    \strlen('source_entry.')
                );

                // Check whether a non-empty field suffix and a source entry exist
                if (
                    $prefixedField !== ''
                    && ($context['source_entry'] ?? null) instanceof EntryContract
                ) {
                    // Read the field from the source entry
                    $value = $this->readFirstNonEmptyFieldValueFromEntity(
                        $context['source_entry'],
                        [
                            $prefixedField,
                        ]
                    );
                }

                // Unset loop-local variables to free memory
                unset($prefixedField);
            } elseif (str_starts_with($handle, 'replicator_row.')) {
                // Read from `replicator_row.{field}` on the current row payload map
                $prefixedField = substr(
                    $handle,
                    \strlen('replicator_row.')
                );
                $rowPayload = $context['replicator_row'] ?? null;

                // Check whether the row payload is an array and the field suffix is non-empty
                if (
                    $prefixedField !== ''
                    && \is_array($rowPayload)
                ) {
                    // Resolve nested field values using the same rules as top-level context
                    /** @var array<string, mixed> $rowPayload */
                    $value = $this->readFirstNonEmptyFieldValueFromContext(
                        $rowPayload,
                        [
                            $prefixedField,
                        ]
                    );
                }

                // Unset loop-local variables to free memory
                unset(
                    $prefixedField,
                    $rowPayload
                );
            } else {
                // Read this field value from context using dot notation when present
                $value = data_get(
                    $context,
                    $handle
                );
            }

            // Check if the value is an instance of Statamic Value
            if ($value instanceof Value) {
                // Unwrap the Statamic Value wrapper
                $value = $value->value();
            }

            // Check if the value is not empty for relationship purposes
            if (! $this->isEmptyRelationshipValue($value)) {
                // Return the value immediately
                return $value;
            }

            // Unset local variables to free memory
            unset(
                $handle,
                $value
            );
        }

        // Return null when no handle yields a value
        return null;
    }

    /**
     * Read the first non-empty field value from a supported entity.
     *
     * @param  mixed  $entity  Supported entity object.
     * @param  array<int, string>  $handles  Ordered field handles.
     * @return mixed First non-empty field value, or null.
     */
    private function readFirstNonEmptyFieldValueFromEntity(
        mixed $entity,
        array $handles
    ): mixed {
        // Attempt each handle in order
        foreach ($handles as $handle) {
            // Initialize field value as null
            $value = null;

            // ---
            // Attempt to read from entries
            // ---

            // Check if the entity is an instance of EntryModel
            if ($entity instanceof EntryModel) {
                // Prefer value() so mocks only need to stub one accessor
                $value = $entity->value($handle);
            } elseif (
                $entity instanceof EntryContract
                && \is_callable([$entity, 'value'])
            ) {
                // Read the field value from the entry (use is_callable so Mockery and
                // magic dispatch still resolve `value`)
                $value = $entity->value($handle);
            }

            // ---
            // Attempt to read from localized terms
            // ---

            // Check if the entity is an instance of LocalizedTerm
            if ($entity instanceof LocalizedTerm) {
                $localizedTerm = $entity;
                // Read the field value from the localized term
                $value = $localizedTerm->value($handle);
            }

            // ---
            // Attempt to read from taxonomy terms
            // ---

            // Check if the entity is an instance of TaxonomyTerm
            if ($entity instanceof TaxonomyTerm) {
                /** @var LocalizedTerm $defaultLocaleTerm */
                $defaultLocaleTerm = $entity->inDefaultLocale();
                // Read the field value from the taxonomy term
                $value = $defaultLocaleTerm->value($handle);
            }

            // ---
            // Attempt to read from globals variables
            // ---

            // Check if the entity is an instance of Variables
            if ($entity instanceof Variables) {
                $variables = $entity;
                // Read the field value from the globals variables
                $value = $variables->get($handle);
            }

            // ---
            // Attempt to read from assets data
            // ---

            // Check if the entity is an instance of Asset
            if ($entity instanceof Asset) {
                /** @var Collection<string, mixed> $assetData */
                $assetData = $entity->data();
                // Read the field value from the asset data
                $value = $assetData->get($handle);
            }

            // Check if the value is an instance of Statamic Value
            if ($value instanceof Value) {
                // Unwrap the Statamic Value wrapper
                $value = $value->value();
            }

            // Check if the value is not empty for relationship purposes
            if (! $this->isEmptyRelationshipValue($value)) {
                // Return the value immediately
                return $value;
            }

            // Unset local variables to free memory
            unset(
                $handle,
                $value
            );
        }

        // Return null when no handle yields value
        return null;
    }

    /**
     * Normalize raw relationship value into a list of items.
     *
     * @param  mixed  $raw  Raw relationship field value.
     * @return array<int, mixed> Sequential relationship items.
     */
    private function normalizeRawToList(
        mixed $raw
    ): array {
        // Check if the raw value is an instance of Statamic Value
        if ($raw instanceof Value) {
            // Unwrap the Statamic Value wrapper
            $raw = $raw->value();
        }

        // Check if the raw value is empty for relationship purposes
        if ($this->isEmptyRelationshipValue($raw)) {
            // Return an empty array (i.e., empty list)
            return [];
        }

        // ---
        // Materialize a query builder to a plain PHP array of items. An augmented
        // `entries` field is a StatusQueryBuilder (published entries only), which
        // a term's subject_matter field became in UAMS-Web/uams-statamic-archive#2822; wrapping it as one item
        // would resolve to nothing
        // ---
        if (
            $raw instanceof OrderedQueryBuilder
            || $raw instanceof StatusQueryBuilder
        ) {
            /** @var Collection<array-key, mixed> $orderedResults */
            $orderedResults = $raw->get();
            $raw = $orderedResults->all();
        }

        // Check if the raw value is an instance of Collection
        if ($raw instanceof Collection) {
            // Convert the Collection to a zero-indexed array of items (i.e., plain PHP array)
            $raw = $raw->values()->all();
        }

        // Check if the raw value is not an array (i.e., single scalar/object item)
        if (! \is_array($raw)) {
            // Wrap the single scalar/object item into a list
            $raw = [$raw];
        }

        // Return re-indexed list
        return array_values($raw);
    }

    /**
     * Check whether a relationship value should be treated as empty.
     *
     * @param  mixed  $value  Raw value.
     * @return bool True when value is empty for relationship purposes.
     */
    private function isEmptyRelationshipValue(
        mixed $value
    ): bool {
        // Check if the value is null or an empty string
        if (
            $value === null
            || $value === ''
        ) {
            // Return true (i.e., treat null and empty strings as empty)
            return true;
        }

        // Check if the value is an empty array
        if (
            \is_array($value)
            && $value === []
        ) {
            // Return true (i.e., treat empty arrays as empty)
            return true;
        }

        // Check if the value is an instance of Collection
        if ($value instanceof Collection) {
            // Check if the Collection is empty
            // Return false (i.e., treat non-empty collections as non-empty)
            // Return true (i.e., treat empty collections as empty)
            return $value->isEmpty();
        }

        // Treat all other values as non-empty
        return false;
    }

    /**
     * Normalize an item to an entity by an explicit context type.
     *
     * @param  mixed  $item  Raw relation item.
     * @param  string  $contextType  Entity context type.
     * @param  string|null  $scope  Optional context scope.
     * @return mixed Normalized entity object, or null.
     */
    private function normalizeToEntityByContextType(
        mixed $item,
        string $contextType,
        ?string $scope
    ): mixed {
        // Resolve entity based on explicit context type
        return match ($contextType) {
            'entries' => $this->normalizeToEntry($item, $scope),
            'terms' => $this->normalizeToTerm($item, $scope),
            'assets' => $this->normalizeToAsset($item, $scope),
            'globals' => $this->normalizeToGlobalVariables($item, $scope),
            default => null,
        };
    }

    /**
     * Normalize an item to any supported entity type.
     *
     * @param  mixed  $item  Raw relation item.
     * @param  string|null  $scope  Optional scope.
     * @return mixed Normalized entity object, or null.
     */
    private function normalizeToAnySupportedEntity(
        mixed $item,
        ?string $scope = null
    ): mixed {
        // Check if the item is an already supported entity object
        if (
            $item instanceof EntryContract
            || $item instanceof TaxonomyTerm
            || $item instanceof LocalizedTerm
            || $item instanceof Asset
            || $item instanceof Variables
        ) {
            // Return the item immediately
            return $item;
        }

        // ---
        // Attempt entry normalization
        // ---

        // Check if the item can be normalized to an entry
        $entry = $this->normalizeToEntry($item, $scope);

        // Check if the entry is not null
        if ($entry instanceof EntryContract) {
            // Return the entry immediately
            return $entry;
        }

        // ---
        // Attempt asset normalization
        // ---

        // Check if the item can be normalized to an asset
        $asset = $this->normalizeToAsset($item, $scope);

        // Check if the asset is not null
        if ($asset instanceof Asset) {
            // Return the asset immediately
            return $asset;
        }

        // ---
        // Attempt term normalization
        // ---

        // Check if the item can be normalized to a term
        $term = $this->normalizeToTerm($item, $scope);

        // Return the term immediately
        return $term;
    }

    /**
     * Normalize one target item into entity + optional template context.
     *
     * @param  mixed  $target  Raw target value or structured target payload.
     * @return array{entity: mixed, template_context: array<string, mixed>} Normalized target payload.
     */
    private function normalizeResolvedTarget(
        mixed $target
    ): array {
        // Check if target is not a structured payload
        if (
            ! \is_array($target)
            || ! \array_key_exists('entity', $target)
        ) {
            // Return plain entity payload with no template context overrides
            return [
                'entity' => $target,
                'template_context' => [],
            ];
        }

        // Extract template context from structured payload when present
        /** @var array<string, mixed> $templateContext */
        $templateContext = \is_array($target['template_context'] ?? null)
            ? $target['template_context']
            : [];

        // Return structured entity payload with optional template context overrides
        return [
            'entity' => $target['entity'],
            'template_context' => $templateContext,
        ];
    }

    /**
     * Keep direct-mode targets that are taxonomy terms with a matching data field value.
     *
     * @param  array<int, mixed>  $targets  Resolved targets from direct mode.
     * @param  array<string, mixed>  $params  Tag parameters.
     * @return array<int, mixed> Filtered targets.
     */
    private function applyDirectTermDataEqualsFilter(
        array $targets,
        array $params
    ): array {
        // Read optional field handle for term data comparison
        $fieldRaw = $params['direct_term_data_equals_field'] ?? null;

        // Read optional expected string value for term data comparison
        $valueRaw = $params['direct_term_data_equals_value'] ?? null;

        // Return targets unchanged when the field handle is missing or not a non-empty string
        if (! \is_string($fieldRaw) || trim($fieldRaw) === '') {
            unset(
                $fieldRaw,
                $valueRaw
            );

            return $targets;
        }

        // Return targets unchanged when the expected value is not a string
        if (! \is_string($valueRaw)) {
            unset(
                $fieldRaw,
                $valueRaw
            );

            return $targets;
        }

        // Normalize field handle and expected value for comparison
        $field = trim($fieldRaw);
        $expected = trim($valueRaw);
        unset(
            $fieldRaw,
            $valueRaw
        );

        // Initialize the filtered target list
        $filtered = [];

        // Walk each direct-mode target
        foreach ($targets as $target) {
            // Normalize the target payload to entity plus optional template context
            $resolved = $this->normalizeResolvedTarget($target);

            // Read the entity from the normalized payload
            $entity = $resolved['entity'];

            // Subtypes (`ceterms_credential_type`, etc.) only apply when CTDL root is Credential
            if (
                $this->directTermDataEqualsComparesCtdlCredentialSubtype($field)
                && ! $this->taxonomyTermDefaultLocaleDataEquals(
                    $entity,
                    CredentialTypeCertification::CTDL_CETERMS_TYPE_FIELD,
                    CredentialTypeCertification::CTDL_CETERMS_TYPE_VALUE_CREDENTIAL
                )
            ) {
                unset(
                    $entity,
                    $resolved
                );

                continue;
            }

            // Append the target when the entity is a term with a matching field value
            if ($this->taxonomyTermDefaultLocaleDataEquals(
                $entity,
                $field,
                $expected
            )) {
                $filtered[] = $target;
            }

            // Release per-iteration references
            unset(
                $entity,
                $resolved
            );
        }

        // Release filter parameter copies
        unset(
            $field,
            $expected
        );

        // Return the filtered targets list
        return $filtered;
    }

    /**
     * Whether the direct-mode filter compares a CTDL credential subtype field (`ceterms_credential_type`).
     */
    private function directTermDataEqualsComparesCtdlCredentialSubtype(string $field): bool
    {
        // Match full dotted path (`ctdl.ceterms_credential_type`) used by schema tags / Blueprints,
        // and allow nested paths that end with the subtype key.
        return $field === CredentialTypeCertification::CTDL_CREDENTIAL_TYPE_FIELD
            || str_ends_with(
                $field,
                '.ceterms_credential_type'
            );
    }

    /**
     * Check whether a taxonomy term's default-locale field equals the expected string.
     *
     * @param  mixed  $entity  Target entity (term or localized term).
     * @param  string  $field  Term data field handle or dotted path (for example
     *                         `ctdl.ceterms_type`).
     * @param  string  $expected  Expected value after trimming.
     */
    private function taxonomyTermDefaultLocaleDataEquals(
        mixed $entity,
        string $field,
        string $expected
    ): bool {
        // Use default-locale data for taxonomy term roots (accept contract for unit-test doubles)
        if (
            $entity instanceof TermContract
            && method_exists($entity, 'inDefaultLocale')
        ) {
            /** @var mixed $localized */
            $localized = $entity->inDefaultLocale();
        } elseif ($entity instanceof LocalizedTerm) {
            // Use localized term data directly when already localized
            $localized = $entity;
        } else {
            // Reject non-term entities for this comparison
            return false;
        }

        if (! \is_object($localized)) {
            unset($localized);

            return false;
        }

        // Read raw field value (supports dotted paths into nested group fields)
        $raw = $this->getLocalizedTermDataValueByFieldPath(
            $localized,
            $field
        );

        // Unwrap Statamic Value wrappers to the underlying scalar
        if ($raw instanceof Value) {
            $raw = $raw->value();
        }

        // Coerce scalar field values to trimmed string form
        $actual = \is_scalar($raw) ? trim((string) $raw) : '';
        unset(
            $localized,
            $raw
        );

        // Compare normalized strings
        return $actual === $expected;
    }

    /**
     * Read a value from default-locale term data by handle or dotted path.
     *
     * Unwraps Statamic Value and Collection wrappers while walking segments so
     * nested blueprint groups (for example `ctdl.ceterms_type`) resolve.
     *
     * @param  mixed  $localized  Localized term accessor (or a term-like accessor).
     * @param  string  $field  Single handle or dot-separated path.
     * @return mixed Raw leaf value, or null when the path is missing.
     */
    private function getLocalizedTermDataValueByFieldPath(
        mixed $localized,
        string $field
    ): mixed {
        if (! \is_object($localized) || ! method_exists($localized, 'data')) {
            return null;
        }

        // Initialize traversal at the root default-locale data map
        /** @var Collection<array-key, mixed> $localizedData */
        $localizedData = $localized->data();
        $cursor = $localizedData->all();

        // Split the field into dot-separated segments for nested access
        $segments = explode(
            '.',
            $field
        );

        // Walk each segment, unwrapping wrappers before descending
        foreach ($segments as $segment) {
            // Unwrap Statamic Value at the current depth so children are reachable
            if ($cursor instanceof Value) {
                $cursor = $cursor->value();
            }

            // Normalize collections to plain arrays for key-based descent
            if ($cursor instanceof Collection) {
                $cursor = $cursor->all();
            }

            // Stop when the path cannot continue (missing or non-container)
            if (
                $cursor === null
                || (! \is_array($cursor) && ! \is_object($cursor))
            ) {
                unset(
                    $cursor,
                    $localized,
                    $segments,
                    $segment
                );

                return null;
            }

            // Descend using array key access when the segment exists
            if (\is_array($cursor) && \array_key_exists($segment, $cursor)) {
                $cursor = $cursor[$segment];
            } elseif (
                \is_object($cursor)
                && \property_exists($cursor, $segment)
            ) {
                // Read public property when present (rare for term payloads)
                $cursor = $cursor->{$segment};
            } else {
                unset(
                    $cursor,
                    $localized,
                    $segments,
                    $segment
                );

                return null;
            }
        }

        // Unwrap a leaf Statamic Value when present
        if ($cursor instanceof Value) {
            $cursor = $cursor->value();
        }

        unset(
            $localized,
            $segments,
            $segment
        );

        return $cursor;
    }

    /**
     * Parse a flexible boolean parameter value.
     *
     * Accepts true-ish values: true, 1, "1", "true", "yes", "on".
     *
     * @param  mixed  $value  Raw parameter value.
     * @return bool Parsed boolean value.
     */
    private function parseBooleanParam(mixed $value): bool
    {
        // Check for native boolean input
        if (\is_bool($value)) {
            // Return native boolean value
            return $value;
        }

        // Check for native integer input
        if (\is_int($value)) {
            // Return true only for non-zero integers
            return $value !== 0;
        }

        // Check for string input
        if (\is_string($value)) {
            // Normalize string value for matching
            $normalized = strtolower(trim($value));

            // Check if normalized value is in truthy list
            if (\in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                // Return true for known truthy strings
                return true;
            }
        }

        // Return false for unsupported or false-ish inputs
        return false;
    }

    /**
     * Extract replicator relationship items with row-level template context.
     *
     * @param  mixed  $rawRows  Raw replicator value.
     * @param  array<int, string>  $relationshipHandles  Relationship field handles in each row value.
     * @return array<int, mixed> List of structured targets with entity + template context.
     */
    private function extractReplicatorTargets(
        mixed $rawRows,
        array $relationshipHandles
    ): array {
        // Normalize raw replicator value to a sequential rows list
        $rows = $this->normalizeRawToList($rawRows);

        // ---
        // When no relationship subfield is configured, emit one schema target per enabled
        // row using replicator_row* template context and the row `url` for canonical @id when
        // it is a valid absolute URL (e.g., embedded YouTube/Vimeo segments).
        // ---

        // Check if the relationship handles are empty
        if ($relationshipHandles === []) {
            // Initialize structured targets for row-only replicator fields
            $targets = [];

            // Loop through each replicator row with index
            foreach ($rows as $rowIndex => $row) {
                // Skip non-array row payloads
                if (! \is_array($row)) {
                    unset(
                        $row,
                        $rowIndex
                    );

                    continue;
                }

                // Skip disabled replicator rows
                /** @var array<string, mixed> $row */
                if (! $this->isReplicatorRowEnabled($row)) {
                    unset(
                        $row,
                        $rowIndex
                    );

                    continue;
                }

                // Extract replicator row value payload from nested or flat row shape
                /** @var array<string, mixed> $rowValue */
                $rowValue = \is_array($row['value'] ?? null)
                    ? $row['value']
                    : $this->extractFlatReplicatorRowValue($row);

                // Resolve optional canonical identity URL from the row payload
                $urlEntity = $this->resolveExternalUrlFromRowValuePayloadForCanonicalIdentity(
                    $rowValue
                );

                // Build row-level template context payload
                $rowTemplateContext = [
                    'replicator_row' => $rowValue,
                    'replicator_row_id' => $row['_id'] ?? ($row['id'] ?? null),
                    'replicator_row_index' => $rowIndex,
                    'replicator_row_position' => $rowIndex + 1,
                    'replicator_row_type' => $row['type'] ?? null,
                ];

                // Append structured target with optional URL entity for @id merging
                $targets[] = [
                    'entity' => $urlEntity,
                    'template_context' => $rowTemplateContext,
                ];

                // Unset loop-local variables to free memory
                unset(
                    $row,
                    $rowIndex,
                    $rowTemplateContext,
                    $rowValue,
                    $urlEntity
                );
            }

            // Unset method-local variables to free memory
            unset(
                $rawRows,
                $relationshipHandles,
                $rows
            );

            // Return structured replicator targets without relationship extraction
            return $targets;
        }

        // Initialize extracted targets list
        $targets = [];

        // Loop through each replicator row with index
        foreach ($rows as $rowIndex => $row) {
            // Check if row is not an array payload
            if (! \is_array($row)) {
                // Skip to the next row
                continue;
            }

            // Skip disabled replicator rows
            /** @var array<string, mixed> $row */
            if (! $this->isReplicatorRowEnabled($row)) {
                // Skip to the next row
                continue;
            }

            // Extract replicator row value payload from nested or flat row shape
            /** @var array<string, mixed> $rowValue */
            $rowValue = \is_array($row['value'] ?? null)
                ? $row['value']
                : $this->extractFlatReplicatorRowValue($row);

            // Read first non-empty relationship value in row payload
            $rowRelationshipValue = $this->readFirstNonEmptyFieldValueFromContext(
                $rowValue,
                $relationshipHandles
            );

            // Normalize row relationship value to a list of candidate entities
            $relationshipItems = $this->normalizeRawToList($rowRelationshipValue);

            // Build row-level template context payload
            $rowTemplateContext = [
                'replicator_row' => $rowValue,
                'replicator_row_id' => $row['_id'] ?? ($row['id'] ?? null),
                'replicator_row_index' => $rowIndex,
                'replicator_row_position' => $rowIndex + 1,
                'replicator_row_type' => $row['type'] ?? null,
            ];

            // Loop through each relationship item from this row
            foreach ($relationshipItems as $relationshipItem) {
                // Append structured target with entity and row template context
                $targets[] = [
                    'entity' => $relationshipItem,
                    'template_context' => $rowTemplateContext,
                ];

                // Unset loop-local variables to free memory
                unset($relationshipItem);
            }

            // Unset loop-local variables to free memory
            unset(
                $relationshipItems,
                $row,
                $rowIndex,
                $rowRelationshipValue,
                $rowTemplateContext,
                $rowValue
            );
        }

        // Unset method-local variables to free memory
        unset(
            $rawRows,
            $relationshipHandles,
            $rows
        );

        // Return structured replicator targets
        return $targets;
    }

    /**
     * Determine whether a replicator row should be processed.
     *
     * @param  array<string, mixed>  $row  Replicator row payload.
     * @return bool True when row is enabled or no enabled flag exists.
     */
    private function isReplicatorRowEnabled(array $row): bool
    {
        // Check whether the row has no enabled key
        if (! \array_key_exists('enabled', $row)) {
            // Return true when enabled flag is absent
            return true;
        }

        // Parse the row enabled flag with flexible boolean handling
        return $this->parseBooleanParam($row['enabled']);
    }

    /**
     * Extract a flat replicator row value payload.
     *
     * Some entries store row fields at the row root (`file`, `title_override`, etc.)
     * instead of under `value`.
     *
     * @param  array<string, mixed>  $row  Replicator row payload.
     * @return array<string, mixed> Row value payload.
     */
    private function extractFlatReplicatorRowValue(array $row): array
    {
        // Initialize flat row value payload
        $rowValue = $row;

        // Remove row metadata keys not considered value fields
        unset(
            $rowValue['type'],
            $rowValue['enabled'],
            $rowValue['_id']
        );

        // Return flat row value payload
        return $rowValue;
    }

    /**
     * Read a validated HTTP(S) URL from a replicator row value map for JSON-LD @id merging.
     *
     * @param  array<string, mixed>  $rowValue  Row field payload (e.g. `title`, `url`).
     * @return string|null Trimmed absolute URL, or null when missing or invalid.
     */
    private function resolveExternalUrlFromRowValuePayloadForCanonicalIdentity(
        array $rowValue
    ): ?string {
        // Check whether the row defines a `url` field
        if (! \array_key_exists('url', $rowValue)) {
            // Return null to signal no URL is available
            return null;
        }

        // Read the raw url value from the row payload
        $rawUrl = $rowValue['url'];

        // ---
        // Unwrap Statamic field values to their native payload
        // ---

        // Check if the raw URL is an instance of Statamic Value
        if ($rawUrl instanceof Value) {
            // Unwrap the Statamic Value wrapper
            $rawUrl = $rawUrl->value();
        }

        // ---
        // Normalize the candidate to a trimmed string when scalar
        // ---

        // Check if the raw URL is a string
        if (\is_string($rawUrl)) {
            // Trim the raw URL to a candidate string
            $candidate = trim($rawUrl);
        } elseif (\is_scalar($rawUrl)) {
            // Cast the raw URL to a string and trim it to a candidate string
            $candidate = trim((string) $rawUrl);
        } else {
            // Unset local variables to free memory
            unset(
                $rawUrl
            );

            // Return null to signal no URL is available
            return null;
        }

        // ---
        // Reject empty or non-URL strings
        // ---

        // Check if the candidate is an empty string or not a valid URL
        if (
            $candidate === ''
            || filter_var(
                $candidate,
                FILTER_VALIDATE_URL
            ) === false
        ) {
            // Unset local variables to free memory
            unset(
                $candidate,
                $rawUrl
            );

            // Return null to signal no valid URL is available
            return null;
        }

        // Unset local variables to free memory
        unset(
            $rawUrl
        );

        // Return the candidate URL
        return $candidate;
    }

    /**
     * Normalize a context type parameter.
     *
     * @param  mixed  $rawType  Raw context value.
     * @param  array<int, string>  $allowed  Allowed values.
     * @return string|null Normalized type or null.
     */
    private function normalizeContextType(
        mixed $rawType,
        array $allowed
    ): ?string {
        // Normalize to lowercase string for matching
        $type = \is_string($rawType) ? strtolower(trim($rawType)) : null;

        // Check if the type is null or an empty string (i.e., no type is provided)
        if (
            $type === null
            || $type === ''
        ) {
            // Return null to signal no type is provided
            return null;
        }

        // Check if the type is in the allowed list
        if (\in_array($type, $allowed, true)) {
            // Return the type
            return $type;
        }

        // Return null to signal the type is not allowed
        return null;
    }

    /**
     * Build template context values for a target entity.
     *
     * @param  mixed  $entity  Target entity.
     * @return array<string, mixed> Antlers context values.
     */
    private function buildTemplateContextForEntity(
        mixed $entity
    ): array {
        // ---
        // Reuse dedicated asset context builder for assets
        // ---

        // Check if the entity is an instance of Asset
        if ($entity instanceof Asset) {
            // Build the template context for the asset
            return $this->buildTemplateContextForAsset($entity);
        }

        // ---
        // Build context from entry values
        // ---

        // Check if the entity is an instance of EntryContract
        if ($entity instanceof EntryContract) {
            // Build the same flattened Antlers bag as main schema (values(),
            // terms hydration, permalink/url)
            $data = $this->schemaOutputService
                ->buildFlattenedSchemaAntlersContextFromDataObject($entity);

            // Add the entry object so templates can use `{{ entry }}` when needed
            $data['entry'] = $entity;

            // Return the template context
            return $data;
        }

        // ---
        // Build context from taxonomy term values
        // ---

        // Check if the entity is an instance of TaxonomyTerm
        if ($entity instanceof TaxonomyTerm) {
            // Build the template context for the taxonomy term
            /** @var LocalizedTerm|null $localized */
            $localized = $entity->inDefaultLocale();

            // Get the data from the localized term
            /** @var array<string, mixed> $data */
            $data = [];

            if ($localized !== null) {
                /** @var Collection<string, mixed> $localizedData */
                $localizedData = $localized->data();
                $data = $localizedData->all();
            }

            // Add the localized term to the context as 'term'
            $data['term'] = $localized;

            // Return the template context
            return $data;
        }

        // ---
        // Build context from localized taxonomy term values
        // ---

        // Check if the entity is an instance of LocalizedTerm
        if ($entity instanceof LocalizedTerm) {
            // Build the template context for the localized term
            /** @var Collection<string, mixed> $localizedData */
            $localizedData = $entity->data();
            $data = $localizedData->all();

            // Add the localized term to the context as 'term'
            $data['term'] = $entity;

            // Return the template context
            return $data;
        }

        // ---
        // Build context from globals variable values
        // ---

        // Check if the entity is an instance of Variables
        if ($entity instanceof Variables) {
            // Get the data from the globals variables
            $variablesData = method_exists($entity, 'all') ? $entity->all() : [];

            /** @var array<string, mixed> $data */
            $data = \is_array($variablesData) ? $variablesData : [];

            // Add the globals variables to the context as 'global'
            $data['global'] = $entity;

            // Return the template context
            return $data;
        }

        // Return empty context for unsupported entity types
        return [];
    }

    /**
     * Whether the relationship target is the primary route/context entity itself.
     *
     * @param  mixed  $targetEntity  Resolved relationship target
     * @param  EntryContract|TermContract|null  $contextEntity  Primary route/context entity
     */
    private function targetEntityIsContextEntity(
        mixed $targetEntity,
        EntryContract|TermContract|null $contextEntity
    ): bool {
        // A missing context entity can never match a target
        if ($contextEntity === null || ! \is_object($targetEntity)) {
            return false;
        }

        // Same instance is always the same entity
        if ($targetEntity === $contextEntity) {
            return true;
        }

        // Compare by id when both sides expose one
        if (
            method_exists($targetEntity, 'id')
            && method_exists($contextEntity, 'id')
        ) {
            $targetId = $targetEntity->id();
            $contextId = $contextEntity->id();

            return \is_string($targetId)
                && $targetId !== ''
                && $targetId === $contextId;
        }

        // Treat other shapes as distinct entities
        return false;
    }

    /**
     * Build the full target template context and context entity for a node build.
     *
     * Deferred out of the target loop preamble so fragment cache hits skip the
     * per-target blueprint augmentation entirely (issue UAMS-Web/uams-statamic-archive#937).
     *
     * @param  mixed  $targetEntity  Resolved relationship target
     * @param  array<string, mixed>  $targetTemplateContextOverrides  Row-level overrides
     * @param  bool  $mergeSourceContext  Whether to merge source_entry/source_term
     * @param  EntryContract|TermContract|null  $contextEntity  Primary route/context entity
     * @return array{context: array<string, mixed>, entity: EntryContract|TermContract|null}
     */
    private function prepareTargetTemplateContextForBuild(
        mixed $targetEntity,
        array $targetTemplateContextOverrides,
        bool $mergeSourceContext,
        EntryContract|TermContract|null $contextEntity
    ): array {
        // Build Antlers context values specific to the current target
        $targetTemplateContext = $this->buildTemplateContextForEntity($targetEntity);

        // Merge source context values so primary entry/term fields remain available
        if ($mergeSourceContext) {
            $targetTemplateContext = $this->mergeSourceContextIntoTemplateContext(
                $targetTemplateContext,
                $contextEntity
            );
        }

        // Check if target template context overrides are present
        if ($targetTemplateContextOverrides !== []) {
            // Merge target template context overrides into computed entity context
            $targetTemplateContext = [
                ...$targetTemplateContext,
                ...$targetTemplateContextOverrides,
            ];
        }

        // Resolve the context entity used for schema template evaluation
        return [
            'context' => $targetTemplateContext,
            'entity' => $this->resolveTemplateContextEntityForTarget(
                $targetEntity,
                $contextEntity
            ),
        ];
    }

    /**
     * Resolve template context entity for schema property evaluation.
     *
     * @param  mixed  $target  Target entity.
     * @param  EntryContract|TermContract|null  $defaultContextEntity  Fallback context.
     * @return EntryContract|TermContract|null Context entity for template evaluation.
     */
    private function resolveTemplateContextEntityForTarget(
        mixed $target,
        EntryContract|TermContract|null $defaultContextEntity
    ): EntryContract|TermContract|null {
        // Check if the target is an asset
        if ($target instanceof Asset) {
            // Return null so asset templates evaluate with asset-only base context
            return null;
        }

        // Check if the target is an instance of EntryContract
        if ($target instanceof EntryContract) {
            // Return the target immediately (i.e., use the target directly)
            return $target;
        }

        // Check if the target is an instance of TaxonomyTerm
        if ($target instanceof TaxonomyTerm) {
            // Return the target immediately (i.e., use the target directly)
            return $target;
        }

        // Check if the target is an instance of LocalizedTerm
        if ($target instanceof LocalizedTerm) {
            // Get the term from the localized term
            $term = $target->term();

            // ---
            // Return the term if it is an instance of TaxonomyTerm, otherwise return the
            // default context entity (i.e., no term can be resolved)
            // ---
            return $term instanceof TaxonomyTerm ? $term : $defaultContextEntity;
        }

        // Fall back to current context entity for non entry/term targets
        return $defaultContextEntity;
    }

    /**
     * Resolve a schema template id for a target entity using cascade settings.
     *
     * @param  mixed  $target  Target entity.
     * @return string|null Resolved template id, or null.
     */
    private function resolveTemplateIdForTarget(
        mixed $target
    ): ?string {
        // Resolve the target as entry or term when possible
        $entity = $this->resolveTemplateContextEntityForTarget(
            $target,
            null
        );

        // Check if the entity is null (i.e., no entry/term context is available)
        if ($entity === null) {
            // Return null to signal no entry/term context is available
            return null;
        }

        // Resolve cascade settings for this entity
        $resolved = $this->cascadeService->resolve($entity);

        // Select main content template first, then fall back through other slots
        $candidates = [
            $resolved['main_content_entry'],
            $resolved['subject_entry'],
            $resolved['webpage_entry'],
            $resolved['website_entry'],
        ];

        // ---
        // Return first non-empty candidate string
        // ---

        // Loop through each candidate
        foreach ($candidates as $candidate) {
            // Check if the candidate is a string and not empty
            if (
                \is_string($candidate)
                && $candidate !== ''
            ) {
                // Return the candidate immediately (i.e., the template id is found)
                return $candidate;
            }
        }

        // Unset local variables to free memory
        unset(
            $candidates,
            $candidate,
            $entity,
            $resolved
        );

        // Return null to signal no template id is resolved
        return null;
    }

    /**
     * Merge a template node with canonical identity from a target entity.
     *
     * @param  array<string, mixed>  $baseNode  Template node.
     * @param  mixed  $target  Target entity.
     * @return array<string, mixed> Merged node.
     */
    private function mergeTemplateNodeWithTarget(
        array $baseNode,
        mixed $target
    ): array {
        // Start from the base template node
        $node = $baseNode;

        // Resolve canonical identity for this target
        $canonicalId = $this->resolveCanonicalIdForEntity($target);

        // Check if the canonical id is a string and not empty
        if (
            \is_string($canonicalId)
            && $canonicalId !== ''
        ) {
            // Override the node @id with the canonical id
            $node['@id'] = $canonicalId;
        }

        // Return merged node
        return $node;
    }

    /**
     * Resolve canonical identifier string for an entity.
     *
     * @param  mixed  $entity  Target entity, absolute URL string, or null.
     * @return string|null Canonical id string, or null.
     */
    private function resolveCanonicalIdForEntity(
        mixed $entity
    ): ?string {
        // ---
        // Resolve canonical id for absolute URL strings (e.g. external embed URLs used as
        // row-level schema identity when no Asset/Entry target exists)
        // ---

        // Check if the entity is a non-empty string that validates as an absolute URL
        if (\is_string($entity)) {
            // Trim surrounding whitespace from the candidate URL string
            $trimmedUrl = trim($entity);

            // Check if the trimmed URL is non-empty and validates as a URL
            if (
                $trimmedUrl !== ''
                && filter_var(
                    $trimmedUrl,
                    FILTER_VALIDATE_URL
                ) !== false
            ) {
                // Return the validated URL as the canonical JSON-LD @id
                return $trimmedUrl;
            }

            // Unset local variables to free memory
            unset(
                $trimmedUrl
            );
        }

        // ---
        // Resolve canonical id for assets using absolute URL fallback
        // ---

        // Check if the entity is an instance of Asset
        if ($entity instanceof Asset) {
            // Resolve the absolute asset URL
            return $this->resolveAbsoluteAssetUrl($entity);
        }

        // ---
        // Resolve canonical id for entries
        // ---

        // Check if the entity is an instance of the concrete Entry model
        if ($entity instanceof EntryModel) {
            // Read the entry absolute URL for JSON-LD @id
            $url = $entity->absoluteUrl();

            // Check if the URL is a string and not empty
            if (\is_string($url) && $url !== '') {
                // Return the URL immediately (i.e., the canonical id is found)
                return $url;
            }

            // Resolve the entry path URL when absolute URL is unavailable
            $url = $entity->url();

            // Check if the URL is a string and not empty
            if (\is_string($url) && $url !== '') {
                // Return the URL immediately (i.e., the canonical id is found)
                return $url;
            }

            // ---
            // A subject_matter entry has no route, so it has no URL and every subject would
            // share the template's @id, letting the tag's dedupe drop all but the first.
            // Give it the @id its taxonomy term had, from the term route
            // /subject-matter/{slug}, so published ids are unchanged (UAMS-Web/uams-statamic-archive#2822)
            // ---
            $slug = $entity->slug();

            // Check if the entry is a subject with a usable slug
            if (
                $entity->collectionHandle() === self::SUBJECT_MATTER_COLLECTION
                && \is_string($slug)
                && $slug !== ''
            ) {
                return $this->canonicalIdForTaxonomyTermUrl(
                    '/subject-matter/'.$slug
                );
            }

            // ---
            // A clinical specialization has no route for the same reason, and since UAMS-Web/uams-statamic-archive#2823
            // one can sit beside subjects in the same `about` field, where a shared @id
            // would drop all but the first. Give it an @id from its slug in the same form.
            // ---

            // Check if the entry is a clinical specialization with a usable slug
            if (
                $entity->collectionHandle() === self::CLINICAL_SPECIALIZATIONS_COLLECTION
                && \is_string($slug)
                && $slug !== ''
            ) {
                return $this->canonicalIdForTaxonomyTermUrl(
                    '/clinical-specializations/'.$slug
                );
            }
        }

        // Check if the entity is an EntryContract (e.g. test doubles)
        if ($entity instanceof EntryContract) {
            // Resolve absolute URL when callable (Mockery proxies may omit method_exists)
            if (\is_callable([$entity, 'absoluteUrl'])) {
                // Invoke absoluteUrl via callable to avoid contract API assumptions
                $url = $entity->absoluteUrl();

                // Check if the URL is a string and not empty
                if (\is_string($url) && $url !== '') {
                    // Return the URL immediately (i.e., the canonical id is found)
                    return $url;
                }
            }

            // Resolve the entry path URL when absolute URL is unavailable
            if (\is_callable([$entity, 'url'])) {
                // Invoke url via callable to avoid contract API assumptions
                $url = $entity->url();

                // Check if the URL is a string and not empty
                if (\is_string($url) && $url !== '') {
                    // Return the URL immediately (i.e., the canonical id is found)
                    return $url;
                }
            }
        }

        // ---
        // Resolve canonical id for terms
        // ---

        // Check if the entity is an instance of TaxonomyTerm
        if ($entity instanceof TaxonomyTerm) {
            /** @var LocalizedTerm $localizedTerm */
            $localizedTerm = $entity->inDefaultLocale();
            // Resolve the localized URL
            $url = $localizedTerm->url();

            // Check if the URL is a string and not empty
            if (
                \is_string($url)
                && $url !== ''
            ) {
                // Build @id as base URL + fragment (URL-safe Base64 of path)
                return $this->canonicalIdForTaxonomyTermUrl($url);
            }

            // Return null to signal no canonical id is found
            return null;
        }

        // ---
        // Resolve canonical id for localized terms
        // ---

        // Check if the entity is an instance of LocalizedTerm
        if ($entity instanceof LocalizedTerm) {
            // Resolve the URL
            $url = $entity->url();

            // Check if the URL is a string and not empty
            if (
                \is_string($url)
                && $url !== ''
            ) {
                // Build @id as base URL + fragment (URL-safe Base64 of path)
                return $this->canonicalIdForTaxonomyTermUrl($url);
            }

            // Return null to signal no canonical id is found
            return null;
        }

        // Return null for entities without canonical URL identity
        return null;
    }

    /**
     * Build a taxonomy term JSON-LD @id: application base URL plus a fragment
     * whose value is URL-safe Base64 (no padding) of the term URL path.
     *
     * @param  string  $url  Term URL from Statamic (absolute, root-relative, etc.).
     * @return string|null Fragment URI or null when no path can be derived.
     */
    private function canonicalIdForTaxonomyTermUrl(
        string $url
    ): ?string {
        // Derive the leading-slash path segment used for encoding
        $path = $this->extractPathFromTermUrlForCanonicalId($url);

        // Check if the path is missing or not usable
        if (
            $path === null
            || $path === ''
        ) {
            // Return null to signal no canonical id can be built
            return null;
        }

        // Build the canonical @id string (shared helper matches other fragment ids)
        $canonicalId = SchemaJsonLdFragmentAtId::atIdFromRootRelativePath($path);

        // Unset local variables to free memory
        unset($path);

        // Return stem + fragment identifier
        return $canonicalId;
    }

    /**
     * Extract the path portion of a term URL for canonical @id encoding.
     *
     * @param  string  $url  Raw term URL string.
     * @return string|null Path starting with `/`, or null when empty.
     */
    private function extractPathFromTermUrlForCanonicalId(
        string $url
    ): ?string {
        // Trim whitespace from the URL
        $trimmed = trim($url);

        // Check if nothing remains after trimming
        if ($trimmed === '') {
            // Return null when the URL is empty
            return null;
        }

        // Check for an absolute URL with scheme
        if (
            preg_match(
                '#^[a-z][a-z0-9+\-.]*://#i',
                $trimmed
            ) === 1
        ) {
            // Parse the path from the absolute URL
            $path = parse_url(
                $trimmed,
                PHP_URL_PATH
            );

            // Check if a non-empty path was parsed
            if (
                \is_string($path)
                && $path !== ''
            ) {
                // Return the parsed path
                return $path;
            }

            // Treat missing path as site root
            return '/';
        }

        // Check for protocol-relative URL
        if (str_starts_with($trimmed, '//')) {
            // Parse path using a dummy scheme so parse_url can read the path
            $path = parse_url(
                'https:'.$trimmed,
                PHP_URL_PATH
            );

            // Check if a non-empty path was parsed
            if (
                \is_string($path)
                && $path !== ''
            ) {
                // Return the parsed path
                return $path;
            }

            // Treat missing path as site root
            return '/';
        }

        // Check for root-relative path
        if (str_starts_with($trimmed, '/')) {
            // Return the path as-is
            return $trimmed;
        }

        // Normalize other relative forms to a single leading slash
        return '/'.$trimmed;
    }

    /**
     * Build per-asset Antlers context for schema template evaluation.
     *
     * @param  Asset  $asset  Target asset.
     * @return array<string, mixed> Asset Antlers context.
     */
    private function buildTemplateContextForAsset(
        Asset $asset
    ): array {
        // Initialize context array
        $context = [];

        // Attempt to include custom asset data fields
        try {
            // Get the data from the asset
            /** @var Collection<string, mixed>|null $assetData */
            $assetData = $asset->data();

            // Read the augmented asset data when present
            if ($assetData !== null) {
                // Get the data from the asset data
                $context = $assetData->all();
            }
        } catch (\Throwable $throwable) {
            // Unset the exception to free memory
            unset(
                $throwable
            );
        }

        // Merge computed and blueprint-backed augmented fields into context
        $context = $this->mergeAssetAugmentedFieldsIntoContext(
            $asset,
            $context
        );

        // Define scalar metadata fields to project into context
        $scalarMetadata = [
            'size' => 'size',
            'last_modified' => 'lastModified',
            'width' => 'width',
            'height' => 'height',
            'mime_type' => 'mimeType',
            'duration' => 'duration',
            'bitrate' => 'bitrate',
        ];

        // ---
        // Read each scalar metadata value when available
        // ---

        // Loop through each scalar metadata
        foreach ($scalarMetadata as $contextKey => $methodName) {
            // Attempt to read the scalar metadata value
            try {
                // Read the scalar metadata value. `bitrate` resolves through the
                // asset metadata/augmentation seam, which PHPStan cannot see on
                // the declared Asset type.
                $value = $asset->{$methodName}(); // @phpstan-ignore method.notFound
            } catch (\Throwable $exception) {
                // Unset the exception to free memory
                unset(
                    $exception
                );

                // Continue to the next scalar metadata
                continue;
            }

            // Add the scalar metadata value to the context
            $context[$contextKey] = $value;

            // Unset the value to free memory
            unset(
                $value
            );
        }

        // Add direct reference to the current asset
        $context['asset'] = $asset;

        // Add absolute URL alias for template convenience
        $context['absolute_url'] = $asset->absoluteUrl();

        // Add relative URL alias for template convenience
        $context['url'] = null;

        // Add permalink alias so asset can be primary template context
        $context['permalink'] = \is_string($context['absolute_url']) && $context['absolute_url'] !== ''
            ? $context['absolute_url']
            : $context['url'];

        // Return context array
        return $context;
    }

    /**
     * Merge augmented asset keys into the Antlers template context.
     *
     * @param  Asset  $asset  Target asset.
     * @param  array<string, mixed>  $context  Current asset template context.
     * @return array<string, mixed> Context merged with augmented key values.
     */
    private function mergeAssetAugmentedFieldsIntoContext(
        Asset $asset,
        array $context
    ): array {
        // Attempt to resolve the augmented asset payload
        try {
            // Read the augmented asset instance
            $augmented = $asset->augmented();
        } catch (\Throwable $throwable) {
            // Unset the exception to free memory
            unset(
                $throwable
            );

            // Return context unchanged when augmented payload is unavailable
            return $context;
        }

        // Attempt to read all augmented values
        try {
            // Read full augmented payload for this asset
            $augmentedValues = $augmented->all();
        } catch (\Throwable $throwable) {
            // Unset local variables to free memory
            unset(
                $augmented,
                $throwable
            );

            // Return context unchanged when augmented values cannot be read
            return $context;
        }

        // Normalize augmented values into a plain array
        $normalizedValues = [];

        // Check if augmented values are already a plain array
        if (\is_array($augmentedValues)) {
            // Use augmented values as-is when they are already an array
            $normalizedValues = $augmentedValues;
        }

        // Check if augmented values expose an all() method (AugmentedCollection)
        if (
            \is_object($augmentedValues)
            && method_exists($augmentedValues, 'all')
        ) {
            // Read the underlying array from the augmented collection object
            /** @var AugmentedCollection $augmentedValues */
            $collectionValues = $augmentedValues->all();

            // AugmentedCollection::all() already returns a plain array
            $normalizedValues = $collectionValues;

            // Unset collection values to free memory
            unset(
                $collectionValues
            );
        }

        // Loop through each augmented key/value pair and merge into context
        foreach ($normalizedValues as $augmentedKey => $augmentedValue) {
            // Check if the current augmented key is a non-empty string
            if (! \is_string($augmentedKey)) {
                // Continue to the next augmented key
                continue;
            }

            if ($augmentedKey === '') {
                // Continue to the next augmented key
                continue;
            }

            // Unwrap Statamic Value instances to plain PHP values
            if ($augmentedValue instanceof Value) {
                // Resolve the raw value from the Statamic Value wrapper
                $augmentedValue = $augmentedValue->value();
            }

            // Merge the resolved augmented value into context under the same key
            $context[$augmentedKey] = $augmentedValue;

            // Unset per-iteration variables to free memory
            unset(
                $augmentedValue,
                $augmentedKey
            );
        }

        // Unset local variables to free memory
        unset(
            $augmented,
            $augmentedValues,
            $normalizedValues
        );

        // Return context merged with augmented values
        return $context;
    }

    /**
     * Merge source entry/term context into target template context with prefixes.
     *
     * @param  array<string, mixed>  $targetTemplateContext  Target-derived template context.
     * @param  EntryContract|TermContract|null  $sourceContextEntity  Primary source context entity.
     * @return array<string, mixed> Merged template context.
     */
    private function mergeSourceContextIntoTemplateContext(
        array $targetTemplateContext,
        EntryContract|TermContract|null $sourceContextEntity
    ): array {
        // Check if no source context entity is available
        if ($sourceContextEntity === null) {
            // Return target context unchanged
            return $targetTemplateContext;
        }

        // Build source context payload with explicit prefixes
        $sourceContext = [];

        // Add source entry object when available
        if ($sourceContextEntity instanceof EntryContract) {
            $sourceContext['source_entry'] = $sourceContextEntity;
        }

        // Add source term object when available
        if ($sourceContextEntity instanceof TermContract) {
            $sourceContext['source_term'] = $sourceContextEntity;
        }

        // Resolve source site handle for explicit site-scoped template lookups
        $sourceSite = $this->resolveSourceSiteHandle($sourceContextEntity);

        // Check if a source site handle is available
        if ($sourceSite !== null) {
            // Add source site handle to merged context
            $sourceContext['source_site'] = $sourceSite;
        }

        // Return merged context preserving target-first key semantics
        return [
            ...$targetTemplateContext,
            ...$sourceContext,
        ];
    }

    /**
     * Resolve source site handle from entry or term context entity.
     *
     * @param  EntryContract|TermContract  $sourceContextEntity  Source context entity.
     * @return string|null Source site handle, or null.
     */
    private function resolveSourceSiteHandle(
        EntryContract|TermContract $sourceContextEntity
    ): ?string {
        // Check if source context is an entry with a site method
        if (
            $sourceContextEntity instanceof EntryContract
            && method_exists($sourceContextEntity, 'site')
        ) {
            // Read the entry site object
            /** @var Site|null $site */
            $site = $sourceContextEntity->site();

            // Check if the site object has a handle method
            if ($site !== null) {
                // Read the site handle value
                $siteHandle = $site->handle();

                // Check if site handle is a non-empty string
                if (
                    \is_string($siteHandle)
                    && $siteHandle !== ''
                ) {
                    // Return the resolved entry site handle
                    return $siteHandle;
                }
            }
        }

        // Check if source context is a term with an inDefaultLocale method
        if (
            $sourceContextEntity instanceof TermContract
            && method_exists($sourceContextEntity, 'inDefaultLocale')
        ) {
            // Read the localized term instance
            /** @var LocalizedTerm|null $localizedTerm */
            $localizedTerm = $sourceContextEntity->inDefaultLocale();

            // Resolve the localized term site when present
            if ($localizedTerm !== null) {
                // Read the localized term site object
                /** @var Site|null $site */
                $site = $localizedTerm->site();

                // Check if the site object has a handle method
                if ($site !== null) {
                    // Read the site handle value
                    $siteHandle = $site->handle();

                    // Check if site handle is a non-empty string
                    if (
                        \is_string($siteHandle)
                        && $siteHandle !== ''
                    ) {
                        // Return the resolved term site handle
                        return $siteHandle;
                    }
                }
            }
        }

        // Return null when no source site handle can be resolved
        return null;
    }

    /**
     * Resolve an asset absolute URL with relative URL fallback.
     *
     * @param  Asset  $asset  Target asset.
     * @return string|null Absolute or relative URL when available.
     */
    private function resolveAbsoluteAssetUrl(
        Asset $asset
    ): ?string {
        // ---
        // Prefer absolute URL when available
        // ---

        // Resolve the absolute URL
        $url = $asset->absoluteUrl();

        // Check if the URL is a string and not empty
        if (\is_string($url) && $url !== '') {
            // Return the URL immediately (i.e., the absolute URL is found)
            return $url;
        }

        // Fall back to relative URL
        $fallback = $asset->url();

        // Check if the fallback URL is not empty
        if ($fallback !== '') {
            // Return the fallback URL immediately (i.e., the fallback URL is found)
            return $fallback;
        }

        // Return null to signal no absolute or relative URL is found
        return null;
    }

    /**
     * Resolve context `id` to an entry or term for schema evaluation.
     *
     * @param  array<string, mixed>  $context  Antlers context.
     * @return EntryContract|TermContract|null Resolved entry or term.
     */
    private function resolveContextEntity(
        array $context
    ): EntryContract|TermContract|null {
        // Read context id value when present
        $id = data_get($context, 'id');

        // Check if the id is not a string or is an empty string
        if (
            ! \is_string($id)
            || $id === ''
        ) {
            // Prefer explicit `entry` from merged schema Antlers context (flattened
            // `values()` does not always expose top-level `id`, but
            // buildTemplateContextForEntity sets `entry`) over route-bound defaults
            if (isset($context['entry']) && $context['entry'] instanceof EntryContract) {
                return $context['entry'];
            }

            // Return the context entity from the current request
            return $this->resolveContextEntityFromCurrentRequest();
        }

        // Resolve content record by id
        $data = Data::find($id);

        // Check if the data is an instance of EntryContract
        if ($data instanceof EntryContract) {
            // Return the data immediately (i.e., the data is an instance of EntryContract)
            return $data;
        }

        // Check if the data is an instance of LocalizedTerm
        if ($data instanceof LocalizedTerm) {
            // Get the term from the localized term
            $term = $data->term();

            // ---
            // Return the term if it is an instance of TaxonomyTerm, otherwise return null
            // (i.e., no term can be resolved)
            // ---
            return $term instanceof TaxonomyTerm ? $term : null;
        }

        // Check if the data is an instance of TaxonomyTerm
        if ($data instanceof TaxonomyTerm) {
            // Return the data immediately (i.e., the data is an instance of TaxonomyTerm)
            return $data;
        }

        // Fall back to current request entity
        return $this->resolveContextEntityFromCurrentRequest();
    }

    /**
     * Resolve current request context entity.
     *
     * @return EntryContract|TermContract|null Resolved entry/term, or null.
     */
    private function resolveContextEntityFromCurrentRequest(): EntryContract|TermContract|null
    {
        // Read route-bound data parameter
        $routeData = request()->route()?->parameter('data');

        // Check if the route data is an instance of EntryContract or TaxonomyTerm
        if (
            $routeData instanceof EntryContract
            || $routeData instanceof TaxonomyTerm
        ) {
            // ---
            // Return the route data immediately (i.e., the route data is an instance of
            // EntryContract or TaxonomyTerm)
            // ---
            return $routeData;
        }

        // Check if the route data is an instance of LocalizedTerm
        if ($routeData instanceof LocalizedTerm) {
            // Get the term from the localized term
            $term = $routeData->term();

            // ---
            // Return the term if it is an instance of TaxonomyTerm, otherwise return null
            // (i.e., no term can be resolved)
            // ---
            return $term instanceof TaxonomyTerm ? $term : null;
        }

        // Resolve content from current request URL
        $resolved = Data::findByRequestUrl(request()->url());

        // Check if the resolved data is an instance of Page
        if ($resolved instanceof Page) {
            // Find the entry by the page id
            $pageId = $resolved->id();
            $entry = Data::find(\is_scalar($pageId) ? (string) $pageId : '');

            // ---
            // Return the entry if it is an instance of EntryContract, otherwise return null
            // (i.e., no entry can be resolved)
            // ---
            return $entry instanceof EntryContract ? $entry : null;
        }

        // Check if the resolved data is an instance of EntryContract or TaxonomyTerm
        if (
            $resolved instanceof EntryContract
            || $resolved instanceof TaxonomyTerm
        ) {
            // ---
            // Return the resolved data immediately (i.e., the resolved data is an instance of
            // EntryContract or TaxonomyTerm)
            // ---
            return $resolved;
        }

        // Check if the resolved data is an instance of LocalizedTerm
        if ($resolved instanceof LocalizedTerm) {
            // Get the term from the localized term
            $term = $resolved->term();

            // ---
            // Return the term if it is an instance of TaxonomyTerm, otherwise return null
            // (i.e., no term can be resolved)
            // ---
            return $term instanceof TaxonomyTerm ? $term : null;
        }

        // Return null when request entity cannot be resolved
        return null;
    }

    /**
     * Normalize one relation item to an entry.
     *
     * @param  mixed  $item  Raw relation item.
     * @param  string|null  $collection  Optional collection scope.
     * @return EntryContract|null Normalized entry.
     */
    private function normalizeToEntry(
        mixed $item,
        ?string $collection
    ): ?EntryContract {
        // Check if the item is an instance of Value
        if ($item instanceof Value) {
            // Unwrap the Value instance to the underlying stored value
            $item = $item->value();
        }

        // Check if the item is an instance of EntryContract
        if ($item instanceof EntryContract) {
            // ---
            // Return the item if it matches the collection scope, otherwise return null
            // (i.e., no entry can be resolved)
            // ---
            return $this->entryMatchesCollection($item, $collection) ? $item : null;
        }

        // ---
        // Resolve string ids via entry facade
        // ---

        // Check if the item is a string and not empty
        if (
            \is_string($item)
            && $item !== ''
        ) {
            // Find the entry by the item id
            $entry = EntryFacade::find($item);

            // ---
            // Return the entry if it is an instance of EntryContract and matches the
            // collection scope, otherwise return null (i.e., no entry can be resolved)
            // ---
            return $entry instanceof EntryContract && $this->entryMatchesCollection($entry, $collection)
                ? $entry
                : null;
        }

        // ---
        // Resolve arrays with explicit id key
        // ---

        // Check if the item is an array and has an id key
        if (
            \is_array($item)
            && \is_string($item['id'] ?? null)
            && $item['id'] !== ''
        ) {
            // Find the entry by the item id
            $entry = EntryFacade::find($item['id']);

            // ---
            // Return the entry if it is an instance of EntryContract and matches the
            // collection scope, otherwise return null (i.e., no entry can be resolved)
            // ---
            return $entry instanceof EntryContract && $this->entryMatchesCollection($entry, $collection)
                ? $entry
                : null;
        }

        // Return null when entry cannot be resolved
        return null;
    }

    /**
     * Check if an entry matches optional collection scope.
     *
     * The scope may name several collections separated by `|`, as a template does where a
     * field offers both `subject_matter` and `clinical_specializations` (UAMS-Web/uams-statamic-archive#2823).
     *
     * @param  EntryContract  $entry  Entry to inspect.
     * @param  string|null  $collection  Optional collection handle, or several joined by `|`.
     * @return bool True when scope is not set or matches.
     */
    private function entryMatchesCollection(
        EntryContract $entry,
        ?string $collection
    ): bool {
        // Check if the collection is not a string or is an empty string
        if (
            ! \is_string($collection)
            || $collection === ''
        ) {
            // Return true (i.e., accept all entries when no collection filter is configured)
            return true;
        }

        // Check whether the entry supports collection access
        if (! method_exists($entry, 'collection')) {
            // ---
            // Return false (i.e., do not accept entries when collection access is not
            // supported)
            // ---
            return false;
        }

        // Resolve the entry collection value
        /** @var \Statamic\Entries\Collection|null $entryCollection */
        $entryCollection = $entry->collection();

        // Compare entry collection handle against each requested collection
        return \in_array(
            $entryCollection?->handle(),
            array_map(trim(...), explode('|', $collection)),
            true
        );
    }

    /**
     * Normalize one relation item to a taxonomy term.
     *
     * @param  mixed  $item  Raw relation item.
     * @param  string|null  $taxonomy  Optional taxonomy scope.
     * @return TaxonomyTerm|null Normalized term.
     */
    private function normalizeToTerm(
        mixed $item,
        ?string $taxonomy
    ): ?TaxonomyTerm {
        // Try each taxonomy when the scope lists several handles (e.g. audio_series|video_series)
        if (
            \is_string($taxonomy)
            && str_contains($taxonomy, '|')
        ) {
            // Split pipe-separated taxonomy handles
            $taxonomyPieces = explode(
                '|',
                $taxonomy
            );

            // Attempt normalization against each taxonomy handle in order
            foreach ($taxonomyPieces as $taxonomyPiece) {
                // Trim whitespace from the taxonomy piece
                $taxonomyTrimmed = trim($taxonomyPiece);

                // Skip empty pieces
                if ($taxonomyTrimmed === '') {
                    unset(
                        $taxonomyPiece,
                        $taxonomyTrimmed
                    );

                    continue;
                }

                // Recursively resolve using a single taxonomy handle
                $resolvedPiece = $this->normalizeToTerm(
                    $item,
                    $taxonomyTrimmed
                );

                // Return the first successful resolution
                if ($resolvedPiece instanceof TaxonomyTerm) {
                    unset(
                        $taxonomyPiece,
                        $taxonomyPieces,
                        $taxonomyTrimmed
                    );

                    return $resolvedPiece;
                }

                unset(
                    $resolvedPiece,
                    $taxonomyPiece,
                    $taxonomyTrimmed
                );
            }

            unset($taxonomyPieces);

            // Return null when no listed taxonomy could normalize the item
            return null;
        }

        // Check if the item is an instance of Value
        if ($item instanceof Value) {
            // Unwrap the Value instance to the underlying stored value
            $item = $item->value();
        }

        // Check if the item is an instance of LocalizedTerm
        if ($item instanceof LocalizedTerm) {
            // Get the term from the localized term
            $term = $item->term();

            // ---
            // Return the term if it is an instance of TaxonomyTerm and matches the taxonomy
            // scope, otherwise return null (i.e., no term can be resolved)
            // ---
            return $term instanceof TaxonomyTerm && $this->termMatchesTaxonomy($term, $taxonomy)
                ? $term
                : null;
        }

        // Check if the item is an instance of TaxonomyTerm
        if ($item instanceof TaxonomyTerm) {
            // ---
            // Return the item if it matches the taxonomy scope, otherwise return null
            // (i.e., no term can be resolved)
            // ---
            return $this->termMatchesTaxonomy($item, $taxonomy) ? $item : null;
        }

        // Check if the item is an array and has a slug key
        if (
            \is_array($item)
            && \is_string($item['slug'] ?? null)
            && $item['slug'] !== ''
            && \is_string($taxonomy)
            && $taxonomy !== ''
        ) {
            // Resolve the term by the taxonomy and slug
            return $this->findTaxonomyTermById(sprintf('%s::%s', $taxonomy, $item['slug']));
        }

        // ---
        // Resolve string ids/slugs
        // ---

        // Check if the item is a string and not empty
        if (\is_string($item) && $item !== '') {
            // Check if the item contains a double colon
            if (str_contains($item, '::')) {
                // Resolve the term by the item id
                return $this->findTaxonomyTermById($item);
            }

            // Check if the taxonomy is a string and not empty
            if (
                \is_string($taxonomy)
                && $taxonomy !== ''
            ) {
                // Resolve the term by the taxonomy and item
                return $this->findTaxonomyTermById(sprintf('%s::%s', $taxonomy, $item));
            }
        }

        // Return null when term cannot be resolved
        return null;
    }

    /**
     * Check if a term matches optional taxonomy scope.
     *
     * @param  TaxonomyTerm  $term  Term to inspect.
     * @param  string|null  $taxonomy  Optional taxonomy handle.
     * @return bool True when scope is not set or matches.
     */
    private function termMatchesTaxonomy(
        TaxonomyTerm $term,
        ?string $taxonomy
    ): bool {
        // Check if the taxonomy is not a string or is an empty string
        if (
            ! \is_string($taxonomy)
            || $taxonomy === ''
        ) {
            // Return true (i.e., accept all terms when no taxonomy filter is configured)
            return true;
        }

        // Compare term taxonomy handle with filter
        /** @var Taxonomy $termTaxonomy */
        $termTaxonomy = $term->taxonomy();

        return $termTaxonomy->handle() === $taxonomy;
    }

    /**
     * Resolve a taxonomy term by id and normalize localized wrappers.
     *
     * @param  string  $termId  taxonomy::slug id.
     * @return TaxonomyTerm|null Resolved term.
     */
    private function findTaxonomyTermById(
        string $termId
    ): ?TaxonomyTerm {
        // Resolve term id through Statamic facade
        $found = Term::find($termId);

        // Check if the found term is an instance of LocalizedTerm
        if ($found instanceof LocalizedTerm) {
            // Get the term from the localized term
            $term = $found->term();

            // ---
            // Return the term if it is an instance of TaxonomyTerm, otherwise return null
            // (i.e., no term can be resolved)
            // ---
            return $term instanceof TaxonomyTerm ? $term : null;
        }

        // Return concrete term model
        return $found instanceof TaxonomyTerm ? $found : null;
    }

    /**
     * Normalize one relation item to an asset.
     *
     * @param  mixed  $item  Raw relation item.
     * @param  string|null  $container  Optional asset container scope.
     * @return Asset|null Normalized asset.
     */
    private function normalizeToAsset(
        mixed $item,
        ?string $container
    ): ?Asset {
        // Check if the item is an instance of Value
        if ($item instanceof Value) {
            // Unwrap the Value instance to the underlying stored value
            $item = $item->value();
        }

        // Check if the item is an instance of Asset
        if ($item instanceof Asset) {
            // ---
            // Return the item if it matches the container scope, otherwise return null
            // (i.e., no asset can be resolved)
            // ---
            return $this->assetMatchesContainer($item, $container) ? $item : null;
        }

        // ---
        // Resolve string values to asset ids
        // ---

        // Check if the item is a string and not empty
        if (\is_string($item) && $item !== '') {
            // Find the asset by the item id
            $found = AssetFacade::find($item);

            // Check if the found asset is an instance of Asset
            if ($found instanceof Asset) {
                // ---
                // Return the found asset if it matches the container scope, otherwise return null
                // (i.e., no asset can be resolved)
                // ---
                return $this->assetMatchesContainer($found, $container) ? $found : null;
            }

            // Check if the item does not contain a double colon
            if (! str_contains($item, '::')) {
                // Resolve the qualified asset id from the path
                try {
                    $qualifiedId = $this->resolveQualifiedAssetIdFromPath($item);
                } catch (QueryException) {
                    // Treat missing metadata store as unresolved path so other normalizers can run
                    $qualifiedId = null;
                }

                // Check if the qualified id is not null
                if ($qualifiedId !== null) {
                    // Find the asset by the qualified id
                    $foundQualified = AssetFacade::find($qualifiedId);

                    // ---
                    // Check if the found qualified asset is an instance of Asset and matches the
                    // container scope
                    // ---
                    if (
                        $foundQualified instanceof Asset
                        && $this->assetMatchesContainer($foundQualified, $container)
                    ) {
                        // ---
                        // Return the found qualified asset if it matches the container scope, otherwise
                        // return null (i.e., no asset can be resolved)
                        // ---
                        return $foundQualified;
                    }
                }
            }
        }

        // Return null when asset cannot be resolved
        return null;
    }

    /**
     * Check if an asset matches optional container scope.
     *
     * @param  Asset  $asset  Asset to inspect.
     * @param  string|null  $container  Optional container handle.
     * @return bool True when scope is not set or matches.
     */
    private function assetMatchesContainer(
        Asset $asset,
        ?string $container
    ): bool {
        // Check if the container is not a string or is an empty string
        if (
            ! \is_string($container)
            || $container === ''
        ) {
            // Return true (i.e., accept all assets when no container filter is configured)
            return true;
        }

        // Compare asset container handle with filter
        return $asset->container()->handle() === $container;
    }

    /**
     * Normalize one relation item to globals variables.
     *
     * @param  mixed  $item  Raw relation item.
     * @param  string|null  $globalHandle  Optional global set handle.
     * @return Variables|null Normalized globals variables.
     */
    private function normalizeToGlobalVariables(
        mixed $item,
        ?string $globalHandle
    ): ?Variables {
        // Check if the item is an instance of Variables
        if ($item instanceof Variables) {
            // Return the item immediately
            return $item;
        }

        // ---
        // Resolve global handle from item or explicit scope
        // ---

        // Check if the global handle is a string and not empty
        $resolvedHandle = \is_string($globalHandle) && $globalHandle !== ''
            ? $globalHandle
            : (\is_string($item) ? $item : null);

        // Check if the resolved handle is not a string or is an empty string
        if (
            ! \is_string($resolvedHandle)
            || $resolvedHandle === ''
        ) {
            // Return null to signal that no global handle is available
            return null;
        }

        // ---
        // Resolve the global set by handle
        // ---

        // Resolve the global set by handle
        $set = GlobalSet::findByHandle($resolvedHandle);

        // Check if the global set is not found
        if ($set === null) {
            // Return null to signal that no global set can be resolved
            return null;
        }

        // Return current-site variables for the resolved global set
        $variables = $set->inCurrentSite();

        return $variables instanceof Variables ? $variables : null;
    }

    /**
     * Resolve a container-qualified asset id from a path-only reference.
     *
     * @param  string  $path  Asset path.
     * @return string|null Qualified asset id.
     */
    private function resolveQualifiedAssetIdFromPath(
        string $path
    ): ?string {
        // Find asset metadata by path
        $metadata = AssetMetadataModel::query()->where('path', $path)->first();

        // Check if the metadata is not found
        if ($metadata === null) {
            // Return null to signal that no qualified asset id can be resolved
            return null;
        }

        // ---
        // Read metadata container handle
        // ---

        // Read metadata container handle
        $container = $metadata->getAttribute('container');

        // Check if the container is not a string or is an empty string
        if (
            ! \is_string($container)
            || $container === ''
        ) {
            // Return null to signal that no qualified asset id can be resolved
            return null;
        }

        // Build qualified asset id
        return sprintf('%s::%s', $container, $path);
    }

    /**
     * Encode nodes as JSON output.
     *
     * @param  array<int, array<string, mixed>>  $nodes  Node list.
     * @return string JSON output.
     */
    private function jsonEncodeNodes(
        array $nodes
    ): string {
        // Encode with unescaped slashes and unicode
        $json = json_encode($nodes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // Return fallback empty array on encoding failure
        return $json !== false ? $json : '[]';
    }

    /**
     * Parse a positive integer parameter value.
     *
     * @param  mixed  $value  Raw value.
     * @return int|null Positive integer or null.
     */
    private function parseOptionalPositiveInt(
        mixed $value
    ): ?int {
        // Check if the value is null or an empty string
        if (
            $value === null
            || $value === ''
        ) {
            // Return null to signal that no positive integer can be parsed
            return null;
        }

        // Check if the value is an integer
        if (\is_int($value)) {
            // Return the value if it is a strictly positive integer
            return $value > 0 ? $value : null;
        }

        // Check if the value is a string and is a digit-only string
        if (\is_string($value) && ctype_digit($value)) {
            // Convert the value to an integer
            $parsed = (int) $value;

            // Return the integer if it is a strictly positive integer
            return $parsed > 0 ? $parsed : null;
        }

        // Return null for unsupported values
        return null;
    }

    /**
     * Resolve keyword strings from subject_matter entries (or equivalently
     * shaped terms) reached via the same direct/secondary parameters as
     * {@see self::resolve()}.
     *
     * Each entry or term contributes its `title`, each `alternate_name` row `text`, and
     * each `same_as` row `url`. Order is preserved; duplicates within the
     * combined traversal are omitted.
     *
     * @param  array<string, mixed>  $context  Antlers context.
     * @param  array<string, mixed>  $params  Tag parameters (`direct`, `secondary`, or `tertiary`, matching
     *                                        {@see self::resolve()}).
     * @return string JSON array of strings.
     */
    public function resolveSubjectMatterKeywords(
        array $context,
        array $params
    ): string {
        // Read traversal mode from parameters
        $modeRaw = $params['mode'] ?? 'direct';

        // Normalize mode to direct, secondary, or tertiary
        $mode = \is_string($modeRaw) ? strtolower(trim($modeRaw)) : 'direct';

        // Default unsupported modes to direct
        if (
            $mode !== 'secondary'
            && $mode !== 'tertiary'
        ) {
            $mode = 'direct';
        }

        // ---
        // Context entity is only required for direct-mode collection (same as
        // schema_thing_from_relationship); secondary/tertiary collection does not use it.
        // ---
        $contextEntity = null;

        // Collect target entities using the same paths as schema_thing_from_relationship
        if ($mode === 'tertiary') {
            // Collect via tertiary traversal (e.g. segment assets -> series -> about)
            $targets = $this->collectTargetsForTertiaryMode(
                $context,
                $params
            );
        } elseif ($mode === 'secondary') {
            // Collect via one secondary hop from context
            $targets = $this->collectTargetsForSecondaryMode(
                $context,
                $params
            );
        } else {
            // Collect from direct relationship fields on context
            $contextEntity = $this->resolveContextEntity($context);
            $targets = $this->collectTargetsForDirectMode(
                $context,
                $params,
                $contextEntity
            );
        }

        // Check whether any targets remain after collection
        if ($targets === []) {
            unset(
                $contextEntity,
                $mode,
                $modeRaw,
                $targets
            );

            return '[]';
        }

        // Initialize ordered keyword list and a deduplication map
        $strings = [];

        $seenKeys = [];

        // Walk each resolved target and expand taxonomy term fields into keywords
        foreach ($targets as $target) {
            // Normalize structured target payloads to a plain entity
            $normalized = $this->normalizeResolvedTarget($target);

            // Read the entity from the normalized payload
            $entity = $normalized['entity'];

            // Resolve a concrete taxonomy term when the target is term-like
            $term = $this->unwrapTaxonomyTermFromTargetEntity($entity);

            // Read keywords from a term, or from an entry: subject matter is a collection
            // since UAMS-Web/uams-statamic-archive#2822, so its targets are entries
            if ($term instanceof TaxonomyTerm) {
                $keywords = $this->keywordStringsFromTaxonomyTermDefaultLocale($term);
            } elseif ($entity instanceof EntryModel) {
                /** @var Collection<array-key, mixed> $entryData */
                $entryData = $entity->data();
                $keywords = $this->keywordStringsFromData($entryData->all());
            } else {
                // Skip targets that are neither taxonomy terms nor entries
                unset(
                    $entity,
                    $normalized,
                    $target,
                    $term
                );

                continue;
            }

            // Append each keyword string from the target's stored data
            foreach ($keywords as $keyword) {
                // Skip empty keyword strings
                if ($keyword === '') {
                    continue;
                }

                // Record first-seen keywords only
                if (! isset($seenKeys[$keyword])) {
                    $seenKeys[$keyword] = true;
                    $strings[] = $keyword;
                }
            }

            unset(
                $entity,
                $entryData,
                $keywords,
                $normalized,
                $target,
                $term
            );
        }

        // Encode keyword strings as JSON
        $json = json_encode(
            $strings,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        // Unset local variables to free memory
        unset(
            $contextEntity,
            $mode,
            $modeRaw,
            $seenKeys,
            $strings,
            $targets
        );

        // Return JSON or a safe empty array when encoding fails
        return $json !== false ? $json : '[]';
    }

    /**
     * Normalize a relationship target entity to a concrete taxonomy term.
     *
     * @param  mixed  $entity  Resolved target entity.
     * @return TaxonomyTerm|null Concrete term, or null when not term-like.
     */
    private function unwrapTaxonomyTermFromTargetEntity(
        mixed $entity
    ): ?TaxonomyTerm {
        // Accept concrete taxonomy terms immediately
        if ($entity instanceof TaxonomyTerm) {
            return $entity;
        }

        // Unwrap localized term facades to their shared term model
        if ($entity instanceof LocalizedTerm) {
            // Read the underlying taxonomy term from the localized wrapper
            $term = $entity->term();

            // Return the term when it is a concrete TaxonomyTerm instance
            return $term instanceof TaxonomyTerm ? $term : null;
        }

        // Return null when the entity cannot be treated as a taxonomy term
        return null;
    }

    /**
     * Collect keyword strings from a term's default locale stored data.
     *
     * @param  TaxonomyTerm  $term  Subject matter (or compatible) term.
     * @return array<int, string> Non-empty strings in stable order.
     */
    private function keywordStringsFromTaxonomyTermDefaultLocale(
        TaxonomyTerm $term
    ): array {
        // Resolve default locale representation for consistent field reads
        /** @var LocalizedTerm|null $localized */
        $localized = $term->inDefaultLocale();

        // Return no strings when the default locale is unavailable
        if ($localized === null) {
            unset($localized);

            return [];
        }

        // Read raw term field values from default locale data
        /** @var Collection<array-key, mixed> $localizedData */
        $localizedData = $localized->data();

        // Unset local variables to free memory
        unset($localized);

        return $this->keywordStringsFromData($localizedData->all());
    }

    /**
     * Collect keyword strings from stored subject data: the title, each
     * `alternate_name` row's `text`, and each `same_as` row's `url`.
     *
     * @param  array<array-key, mixed>  $data  Stored field values of a term or entry.
     * @return list<string> Keyword strings in field order.
     */
    private function keywordStringsFromData(
        array $data
    ): array {
        // Start with the primary title when present
        $out = [];

        // Read title as the first keyword when it is a non-empty string
        $title = $data['title'] ?? null;

        // Check if the title is a non-empty string
        if (\is_string($title) && $title !== '') {
            // Append the title keyword
            $out[] = $title;
        }

        // Merge alternate name texts from replicator rows
        $out = array_merge(
            $out,
            $this->extractNonEmptyStringsFromReplicatorRows(
                $data['alternate_name'] ?? null,
                'text'
            )
        );

        // Merge sameAs URLs from replicator rows
        $out = array_merge(
            $out,
            $this->extractNonEmptyStringsFromReplicatorRows(
                $data['same_as'] ?? null,
                'url'
            )
        );

        // Unset local variables to free memory
        unset(
            $data,
            $title
        );

        return $out;
    }

    /**
     * Read a string subkey from each replicator row when present.
     *
     * @param  mixed  $raw  Replicator field value.
     * @param  string  $subkey  Row key to read (`text`, `url`, ...).
     * @return array<int, string> Non-empty string values in row order.
     */
    private function extractNonEmptyStringsFromReplicatorRows(
        mixed $raw,
        string $subkey
    ): array {
        // Return no values when the raw payload is not a non-empty list
        if (! \is_array($raw) || $raw === []) {
            unset($raw);

            return [];
        }

        // Initialize collected strings
        $found = [];

        // Walk each replicator row
        foreach ($raw as $row) {
            // Skip rows that are not associative arrays
            if (! \is_array($row)) {
                unset($row);

                continue;
            }

            // Read the configured subkey from the row
            $value = $row[$subkey] ?? null;

            // Append non-empty string values
            if (\is_string($value) && $value !== '') {
                $found[] = $value;
            }

            unset(
                $row,
                $value
            );
        }

        unset($raw);

        return $found;
    }
}
