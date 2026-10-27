<?php

declare(strict_types=1);

namespace App\Tags;

use App\Services\SchemaTemplates\SchemaOutputService;
use App\Services\SchemaTemplates\SchemaTemplateEmitContext;
use App\Services\SchemaTemplates\SchemaTemplateEmitContextRuntimeStack;
use Illuminate\Database\Eloquent\Builder as EloquentQueryBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as DatabaseQueryBuilder;
use Illuminate\Support\Collection;
use Statamic\Contracts\Query\Builder as StatamicQueryBuilder;
use Statamic\Entries\Entry as EntryModel;
use Statamic\Fields\Value;
use Statamic\Tags\Context;
use Statamic\Tags\Parameters;
use Statamic\Tags\Tags;

/**
 * Emit JSON-LD for one or more `schema_objects` template entry ids from Antlers.
 *
 * Usage:
 *   {{ schema_template_node id="58185f50-22a4-48e7-9575-b86a1fdffef9" }}
 *   {{ schema_template_node id="uuid-one|uuid-two" }}
 *   {{ if uams_worker_query }}{{ schema_template_node id="…" }}{{ /if }}
 *
 * Optional `enabled` (default: emit): when false or empty after normalization,
 * outputs nothing.
 *
 * When this tag runs inside a schema property Antlers template, it shares
 * emit state with `SchemaOutputService` via
 * `SchemaOutputService::ANTLERS_SCHEMA_TEMPLATE_EMIT_CONTEXT`: if the node's
 * canonical `@id` was already emitted as a full object in the same graph build,
 * `SchemaJsonLdSchemaObjectEmissionCoordinator` returns `{"@id":"…"}` without
 * re-expanding the template.
 *
 * If a template embeds this tag for its own schema_objects entry id while that
 * node is still being built, the service emits only `{"@id":"…"}` (same site
 * root + fragment as the eventual full node when `url` falls back to site).
 *
 * Parameters:
 *   - id: one or more schema_objects entry ids: a single uuid, pipe-separated
 *     uuids, an array, a {@see Collection}, a Statamic/Laravel query builder, or
 *     {@see EntryModel} instance(s); field-bound (`:id="…"`) values may be wrapped
 *     in {@see Value}
 *   - instance_index: optional int >= 0 for repeated template instances
 *   - enabled: optional gate for conditional output
 *   - type_override: optional Schema.org type name(s) for JSON-LD `@type` instead of
 *     the template entry's `schema.type` when at least one override type is compatible
 *     (equals an entry type or is a subtype per {@see SchemaType::ancestorsOf});
 *     the full normalized override list is then used for `@type`. Pipe-separated string,
 *     a single string, or a list of strings. When none match, entry types are used. When
 *     the shared graph already emitted a full node for the same canonical `@id`,
 *     {@see SchemaJsonLdAtIdDeduplication} may still collapse
 *     a later full body to `{"@id":…}` regardless of `type_override`.
 *
 * When this tag runs inside another Antlers scope (for example a `job_title`
 * replicator row), the current scope is merged into nested template property
 * resolution after the entity bag, so row fields such as `title` override the
 * entry-level `title` while resolving the nested `schema_objects` entry.
 * `SchemaOutputService::ANTLERS_SCHEMA_PRIMARY_RELATED_TAXONOMY_TERM` is then
 * derived from that merged overlay so nested property Antlers can read the
 * primary taxonomy term without hardcoding a replicator field handle.
 *
 * Output: JSON object string, JSON array string, or empty string
 */
class SchemaTemplateNode extends Tags
{
    /**
     * Replicator / taxonomy row handles that may exist in the live Antlers scope
     * (for example inside `{{ training }}`…`{{ /training }}`) but are omitted from
     * {@see $this->context->all()} flattening — pull them explicitly with {@see get()}.
     *
     * @var list<string>
     */
    private const array SCHEMA_TEMPLATE_NODE_ROW_SCOPE_PULL_HANDLES = [
        'activity',
        'chief_resident',
        'organization',
        'participating_sites',
        'specialty',
        'type',
    ];

    /**
     * Registered Antlers handle `{{ schema_template_node }}`
     *
     * @var string Statamic tag handle resolved to this class
     */
    protected static $handle = 'schema_template_node';

    /**
     * Build JSON-LD fragment text for the requested template entry ids.
     *
     * @return string JSON object, JSON array, or empty string
     */
    public function index(): string
    {
        // Check whether the tag should emit output when `enabled` is present
        if (! $this->shouldEmitFragment()) {
            return '';
        }

        // Collect template entry ids from the `id` parameter
        $templateEntryIds = $this->collectTemplateEntryIds();

        // Return empty output when no ids were provided
        if ($templateEntryIds === []) {
            unset($templateEntryIds);

            return '';
        }

        // Merge live Antlers scope (replicator rows, loop vars) then tag overrides
        $antlersContext = array_merge(
            $this->snapshotLiveAntlersScopeForNestedSchemaTemplateProperties(),
            $this->buildAntlersContextOverrides()
        );

        $antlersContext = $this->fillMissingRowScopeHandlesIntoSchemaTemplateNodeAntlersContext(
            $antlersContext
        );

        // Resolve shared emit context when the tag runs inside schema templates
        $emitContext = $this->resolveEmitContextFromAntlersScope();

        // Build JSON via the schema output service
        $service = app(SchemaOutputService::class);

        // Encode nodes with graph-aware deduplication
        $out = $service->buildSchemaTemplateNodeJsonForTag(
            $templateEntryIds,
            $emitContext,
            $antlersContext
        );

        unset(
            $antlersContext,
            $emitContext,
            $service,
            $templateEntryIds
        );

        return $out;
    }

    /**
     * Normalize the `id` tag parameter into a unique ordered list of entry ids.
     *
     * `id` may be field-bound (`:id="…"`), in which case Statamic passes a
     * {@see Value} wrapper; that is unwrapped in {@see normalizeTemplateIdListPieces()}.
     * Supported shapes include a single uuid, pipe-separated uuids, arrays,
     * {@see Collection} lists, Statamic/Laravel query builders (resolved via
     * {@code ->get()}), and {@see EntryModel} instances (nested {@see Value} list
     * items are unwrapped in {@see extractTemplateEntryIdString()}).
     *
     * @return array<int, string> Unique schema_objects entry ids in first-seen order
     */
    private function collectTemplateEntryIds(): array
    {
        // Initialize the output list
        $ids = [];

        /** @var Parameters $params */
        $params = $this->params;

        // Collect every id piece derived from the sole `id` parameter
        foreach ($this->normalizeTemplateIdListPieces($params->get('id')) as $part) {
            // Resolve each piece to a non-empty id string when possible
            $extracted = $this->extractTemplateEntryIdString($part);

            // Append non-empty ids in first-seen order
            if ($extracted !== null) {
                $ids[] = $extracted;
            }
        }

        // Deduplicate while preserving first-seen order
        $unique = array_values(array_unique($ids));

        unset($ids);

        return $unique;
    }

    /**
     * Turn a tag parameter value into a flat list of pieces to extract ids from.
     *
     * @param  mixed  $raw  null, scalar, array, {@see Collection}, query builder, or {@see Value}-wrapped same
     * @return array<int, mixed> List of parts (may contain {@see EntryModel}, scalars, etc.)
     */
    private function normalizeTemplateIdListPieces(mixed $raw): array
    {
        // Treat absent or empty sentinels as an empty list
        if (
            in_array($raw, [null, false, ''], true)
        ) {
            return [];
        }

        // Unwrap Statamic Value wrappers from field-bound parameters
        if ($raw instanceof Value) {
            $raw = $raw->value();
        }

        // Treat inner null / false / empty string as empty after unwrap
        if (
            in_array($raw, [null, false, ''], true)
        ) {
            return [];
        }

        // Resolve lazy relationship queries (e.g. `entries` without `max_items`)
        if ($this->isQueryBuilderValue($raw)) {
            /** @var StatamicQueryBuilder|DatabaseQueryBuilder|EloquentQueryBuilder<Model> $builder */
            $builder = $raw;

            /** @var Collection<int, mixed> $results */
            $results = $builder->get(); // @phpstan-ignore method.notFound

            return array_values($results->all());
        }

        // Split pipe-separated id strings into separate pieces
        if (\is_string($raw)) {
            // Trim surrounding whitespace from the full string
            $trimmed = trim($raw);

            // Treat whitespace-only strings as empty
            if ($trimmed === '') {
                return [];
            }

            // Split on `|` when the string carries multiple explicit ids
            if (str_contains($trimmed, '|')) {
                // Initialize the segment list
                $segments = [];

                // Walk each pipe-delimited segment
                foreach (explode('|', $trimmed) as $segment) {
                    // Trim whitespace from the segment
                    $t = trim($segment);

                    // Skip empty segments
                    if ($t === '') {
                        continue;
                    }

                    // Keep the non-empty segment
                    $segments[] = $t;
                }

                // Return the ordered non-empty segments
                return $segments;
            }

            // Single id string without pipes is one piece
            return [$trimmed];
        }

        // Expand Laravel / Statamic collections to a plain list
        if ($raw instanceof Collection) {
            return array_values($raw->all());
        }

        // Keep arrays as ordered lists of pieces
        if (\is_array($raw)) {
            return array_values($raw);
        }

        // Wrap any other single value (e.g. one {@see EntryModel}) as one piece
        return [$raw];
    }

    /**
     * True when the value is a Statamic or Laravel query builder.
     *
     * Matches {@see NormalizeList::isQueryBuilder()} so Antlers
     * relationship fields behave consistently.
     *
     * @param  mixed  $value  Value to inspect
     * @return bool True when {@code ->get()} should be used to expand entries
     */
    private function isQueryBuilderValue(mixed $value): bool
    {
        return $value instanceof StatamicQueryBuilder
            || $value instanceof DatabaseQueryBuilder
            || $value instanceof EloquentQueryBuilder;
    }

    /**
     * Coerce one list item to a schema_objects entry id string.
     *
     * @param  mixed  $part  Scalar id, {@see EntryModel}, or {@see Value}
     * @return string|null Non-empty trimmed id, or null when not extractable
     */
    private function extractTemplateEntryIdString(mixed $part): ?string
    {
        // Unwrap nested Value instances from list items
        if ($part instanceof Value) {
            $part = $part->value();
        }

        // Accept string and numeric scalar forms as direct ids
        if (
            \is_string($part)
            || \is_int($part)
            || \is_float($part)
        ) {
            // Trim whitespace from scalar forms
            $t = trim((string) $part);

            // Return null when the trimmed id is empty
            if ($t === '') {
                return null;
            }

            return $t;
        }

        // Resolve Statamic entries field augmentation to the entry's primary key
        if ($part instanceof EntryModel) {
            // Read the entry id from the model
            $id = $part->id();

            // Require a non-empty string id
            if (! \is_string($id)) {
                return null;
            }

            // Trim whitespace from the id string
            $t = trim($id);

            // Return null when the trimmed id is empty
            if ($t === '') {
                return null;
            }

            return $t;
        }

        // Unsupported piece shapes do not contribute an id
        return null;
    }

    /**
     * Optional Antlers context overrides for template instance indexing.
     *
     * @return array<string, mixed> Map of keys passed into schema property resolution (empty when instance_index
     *                              is absent or invalid)
     */
    private function buildAntlersContextOverrides(): array
    {
        $out = [];

        /** @var Parameters $params */
        $params = $this->params;

        // Read the optional instance index parameter
        $raw = $params->get('instance_index');

        // Apply instance index when the parameter is present and valid
        if (
            $raw !== null
            && $raw !== ''
        ) {
            // Coerce numeric strings to integers for the schema service
            if (\is_string($raw)) {
                // Trim whitespace from string forms
                $raw = trim($raw);
            }

            // Require a non-negative integer index
            if (
                is_numeric($raw)
                && (int) $raw >= 0
            ) {
                $out[SchemaOutputService::ANTLERS_SCHEMA_TEMPLATE_INSTANCE_INDEX] = (int) $raw;
            }
        }

        unset($raw);

        // Optional `type_override` → reserved list for SchemaOutputService
        $typeOverride = $this->collectTypeOverrideTypeNames($params->get('type_override'));

        if ($typeOverride !== []) {
            $out[SchemaOutputService::ANTLERS_SCHEMA_TEMPLATE_TYPE_OVERRIDE] = $typeOverride;
        }

        return $out;
    }

    /**
     * Normalize `type_override` into unique non-empty Schema.org type name strings.
     *
     * Accepts a scalar (single type or pipe-separated types), arrays, {@see Collection},
     * and {@see Value}-wrapped same. Does not resolve query builders.
     *
     * @return list<string>
     */
    private function collectTypeOverrideTypeNames(mixed $raw): array
    {
        if (
            in_array($raw, [null, false, ''], true)
        ) {
            return [];
        }

        if ($raw instanceof Value) {
            $raw = $raw->value();
        }

        if (
            in_array($raw, [null, false, ''], true)
        ) {
            return [];
        }

        $pieces = $this->normalizeTypeOverrideListPieces($raw);

        $names = [];

        foreach ($pieces as $part) {
            if ($part instanceof Value) {
                $part = $part->value();
            }

            if (
                \is_string($part)
                || \is_int($part)
                || \is_float($part)
            ) {
                $t = trim((string) $part);
                if ($t !== '') {
                    $names[] = $t;
                }
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * Expand `type_override` parameter shapes into list pieces (strings).
     *
     * @param  mixed  $raw  Scalar, array, or {@see Collection}
     * @return array<int, mixed>
     */
    private function normalizeTypeOverrideListPieces(mixed $raw): array
    {
        if ($raw instanceof Collection) {
            return array_values($raw->all());
        }

        if (\is_array($raw)) {
            return array_values($raw);
        }

        if (\is_string($raw)) {
            $trimmed = trim($raw);

            if ($trimmed === '') {
                return [];
            }

            if (str_contains($trimmed, '|')) {
                $segments = [];

                foreach (explode('|', $trimmed) as $segment) {
                    $t = trim($segment);

                    if ($t !== '') {
                        $segments[] = $t;
                    }
                }

                return $segments;
            }

            return [$trimmed];
        }

        if (
            \is_int($raw)
            || \is_float($raw)
        ) {
            return [(string) $raw];
        }

        return [];
    }

    /**
     * Read SchemaTemplateEmitContext from Antlers scope when the tag runs inside
     * schema property templates.
     *
     * @return SchemaTemplateEmitContext|null Shared emitted-entry and node-@id maps for the active graph when nested in
     *                                        property templates, or null when the tag runs outside that scope
     */
    private function resolveEmitContextFromAntlersScope(): ?SchemaTemplateEmitContext
    {
        $fromStack = SchemaTemplateEmitContextRuntimeStack::peek();

        if ($fromStack instanceof SchemaTemplateEmitContext) {
            return $fromStack;
        }

        /** @var Context $context */
        $context = $this->context;

        // Read the emit context object from the live Antlers context
        $raw = $context->get(
            SchemaOutputService::ANTLERS_SCHEMA_TEMPLATE_EMIT_CONTEXT
        );

        // Accept only the expected context type
        if ($raw instanceof SchemaTemplateEmitContext) {
            return $raw;
        }

        unset($raw);

        return null;
    }

    /**
     * Snapshot outer Antlers variables so nested `schema_objects` property templates
     * see the same handles as the calling template (for example `title` on a
     * `job_title` row instead of only the subject entry root).
     *
     * Reserved keys are removed so the service can attach emit context and so
     * `instance_index` from tag parameters wins over any stray scope value.
     *
     * @return array<string, mixed>
     */
    private function snapshotLiveAntlersScopeForNestedSchemaTemplateProperties(): array
    {
        /** @var Context $context */
        $context = $this->context;

        /** @var array<string, mixed> $scope */
        $scope = $context->all();

        unset(
            $scope[SchemaOutputService::ANTLERS_SCHEMA_TEMPLATE_EMIT_CONTEXT],
            $scope[SchemaOutputService::ANTLERS_SCHEMA_TEMPLATE_INSTANCE_INDEX],
            $scope[SchemaOutputService::ANTLERS_SCHEMA_TEMPLATE_TYPE_OVERRIDE]
        );

        return $scope;
    }

    /**
     * {@see Parameters::make()} resolves colon bindings with {@code $context->all()} only.
     * Nested replicator loop variables (such as `organization` under `{{ training }}`) are
     * often visible to {@see Context::get()} / {@see Context::has()} while absent from
     * {@see Context::all()}, so pull those handles after the merge.
     *
     * @param  array<string, mixed>  $antlersContext
     * @return array<string, mixed>
     */
    private function fillMissingRowScopeHandlesIntoSchemaTemplateNodeAntlersContext(
        array $antlersContext
    ): array {
        foreach (self::SCHEMA_TEMPLATE_NODE_ROW_SCOPE_PULL_HANDLES as $rowScopeHandle) {
            $present = $antlersContext[$rowScopeHandle] ?? null;

            if (
                ! in_array($present, [null, '', []], true)
            ) {
                unset(
                    $present,
                    $rowScopeHandle
                );

                continue;
            }

            unset($present);

            if (! $this->context->has($rowScopeHandle)) {
                unset($rowScopeHandle);

                continue;
            }

            $resolved = $this->context->get($rowScopeHandle);

            if (
                ! in_array($resolved, [null, '', []], true)
            ) {
                $antlersContext[$rowScopeHandle] = $resolved;
            }

            unset(
                $resolved,
                $rowScopeHandle
            );
        }

        return $antlersContext;
    }

    /**
     * Whether to render JSON-LD for this tag invocation (mirrors SchemaObject).
     *
     * @return bool True when `enabled` is omitted or passes normalization
     */
    private function shouldEmitFragment(): bool
    {
        /** @var Parameters $params */
        $params = $this->params;

        // Treat omitted `enabled` as allowing output
        if (! $params->has('enabled')) {
            return true;
        }

        // Read the raw `enabled` flag from tag parameters
        $enabled = $params->get('enabled');

        // Unwrap Statamic Value wrappers when present
        if ($enabled instanceof Value) {
            $enabled = $enabled->value();
        }

        // Block explicit false
        if ($enabled === false) {
            return false;
        }

        // Block null
        if ($enabled === null) {
            return false;
        }

        // Block empty strings after trim
        if (
            \is_string($enabled)
            && trim($enabled) === ''
        ) {
            return false;
        }

        // Block integer and float zero
        if (
            $enabled === 0
            || $enabled === 0.0
        ) {
            return false;
        }

        // Block string zero
        return $enabled !== '0';
    }
}
