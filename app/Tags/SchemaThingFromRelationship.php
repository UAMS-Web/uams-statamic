<?php

declare(strict_types=1);

namespace App\Tags;

use App\Services\SchemaTemplates\SchemaOutputService;
use App\Services\SchemaTemplates\SchemaTemplateEmitContext;
use App\Services\SchemaTemplates\SchemaTemplateEmitContextRuntimeStack;
use App\Services\SchemaTemplates\SchemaThingFromRelationshipResolver;
use App\Support\Statamic\Tags\TagParam;
use Illuminate\Support\Arr;
use Statamic\Tags\Context;
use Statamic\Tags\Parameters;
use Statamic\Tags\Tags;

/**
 * JSON-LD nodes from relationship fields in the current context.
 *
 * Usage (direct mode):
 *   {{ schema_thing_from_relationship mode="direct" current_field="gallery" schema_object="entry-uuid" }}
 *
 * Usage (secondary mode):
 *   {{ schema_thing_from_relationship mode="secondary" current_field="authors" secondary_context="entries" secondary_scope="people" secondary_field="featured_image|thumbnail" }}
 *
 * Usage (secondary mode, first hop from a global set field):
 *   {{ schema_thing_from_relationship mode="secondary" global_handle="uams_multisite_settings" global_field="media_contact" secondary_context="terms" secondary_scope="media_contact_groups" secondary_field="people" nested_scope="people" schema_object="..." }}
 *
 * Usage (global mode):
 *   {{ schema_thing_from_relationship mode="global" global_handle="uams_multisite_settings" global_field="accountable_person" }}
 *
 * Usage (tertiary mode):
 *   {{ schema_thing_from_relationship mode="tertiary" current_field="segments" current_field_replicator="true" current_field_replicator_relationship_field="file" secondary_context="assets" secondary_field="series" intermediate_scope="audio_series|video_series" tertiary_field="about" nested_scope="subject_matter" schema_object="..." }}
 *
 * Parameters:
 *   - mode: `direct` (default), `secondary`, `tertiary`, or `global`
 *   - secondary_context: `globals`, `entries`, `assets`, or `terms`
 *   - global_handle: global set handle (`global` mode, or `secondary` first hop)
 *   - global_field: relationship field on the global set (`global` mode, or
 *     `secondary` first hop when `global_handle` is set; `current_field` is
 *     ignored for the first hop in that case)
 *   - current_field: relationship field in current context; may use explicit roots
 *     `source_entry.{handle}` or `replicator_row.{handle}` (pipe-separated fallbacks
 *     supported) when the merged Antlers context includes those keys
 *   - secondary_field: relationship field on each secondary entity (secondary mode)
 *   - current_field_replicator: whether current_field is a replicator field
 *   - secondary_field_replicator: whether secondary_field is a replicator field
 *   - current_field_replicator_relationship_field: optional relationship subfield handle(s)
 *     inside current_field replicator rows (`|`-separated fallbacks); omit when each row is
 *     the schema source itself (e.g. external video URL fields on the row)
 *   - secondary_field_replicator_relationship_field: relationship subfield handle(s)
 *     inside secondary_field replicator rows (`|`-separated fallbacks)
 *   - secondary_scope: optional disambiguator for secondary_context:
 *     collection (entries), taxonomy (terms), global set handle (globals),
 *     or asset container (assets).
 *   - direct_scope: optional scope for normalizing `current_field` items in
 *     direct mode (e.g., taxonomy handle when values are bare term slugs).
 *   - nested_scope: optional scope for normalizing nested `secondary_field`
 *     items; when omitted, `secondary_scope` is used. Use this when
 *     `secondary_context` is `assets` (container scope) but nested values are
 *     taxonomy term slugs (set `nested_scope` to the taxonomy handle).
 *   - intermediate_scope: (`tertiary` mode) taxonomy scope for normalizing
 *     `secondary_field` values on the secondary entity when they are term refs;
 *     pipe-separated handles try each (e.g. `audio_series|video_series`).
 *   - tertiary_field: (`tertiary` mode) field handle(s) on the middle taxonomy term
 *   - tertiary_field_replicator: (`tertiary` mode) whether `tertiary_field` is a replicator
 *   - tertiary_field_replicator_relationship_field: (`tertiary` mode) subfield handles
 *   - current_limit: optional cap for current field entities
 *   - secondary_limit: optional cap for entities read from secondary_field
 *   - schema_object: optional schema_objects template id (cascade fallback when omitted)
 *   - direct_term_data_equals_field: (direct mode) term data handle or dotted
 *     path (for example `ctdl.ceterms_type`) to match
 *   - direct_term_data_equals_value: (direct mode) required string value for that field
 *     When the field is `ctdl.ceterms_credential_type` (or ends with
 *     `.ceterms_credential_type`), the term must also have
 *     `ctdl.ceterms_type` equal to `Credential` before the subtype is compared.
 *
 * Template context variables available to the schema template:
 *   - target context (varies by resolved entity):
 *     - assets: flattened asset metadata keys (`absolute_url`, `url`,
 *       `permalink`, `size`, `last_modified`, `width`, `height`, `mime_type`,
 *       `duration`, `bitrate`, plus asset data keys) and an `asset` object
 *       alias for
 *       object-scoped access.
 *     - entries: flattened entry data keys (for example, `title`, `excerpt`,
 *       custom field handles), computed `permalink` (absolute) and `url`
 *       (relative) when not present in stored data, and an `entry` object
 *       alias for object-scoped access.
 *     - terms: flattened term data keys and a `term` object alias for
 *       object-scoped access.
 *     - globals: flattened global values and a `global` object alias for
 *       object-scoped access.
 *   - source context (primary entry/term from current route/context):
 *     - `source_entry`: source entry object alias when the source is an entry.
 *     - `source_term`: source term object alias when the source is a term.
 *     - source values should be accessed object-scoped (for example,
 *       `source_entry:permalink`, `source_entry:title`, `source_term:url`).
 *     - In **direct** mode, if `current_field` resolves to no entities in the
 *       overlay context (e.g., per-asset `about` is an empty query), the
 *       resolver re-reads the same field(s) from `source_entry` so parent
 *       entry terms still emit (e.g., `AudioObject` `about` matching the article).
 *   - replicator row context (when `*_field_replicator` is enabled):
 *     - `replicator_row`: normalized row value payload map used for
 *       relationship extraction.
 *     - `replicator_row_id`: row identifier (`_id` when present; otherwise `id`).
 *     - `replicator_row_index`: zero-based row index.
 *     - `replicator_row_position`: one-based row position.
 *     - `replicator_row_type`: replicator set handle/type for the row.
 *     - row value keys should be accessed through `replicator_row:*` (for
 *       example, `replicator_row:title_override`,
 *       `replicator_row:excerpt_override`, `replicator_row:loop`).
 *
 * Output: JSON array string of objects suitable for JSON-LD.
 */
class SchemaThingFromRelationship extends Tags
{
    /**
     * Registered Antlers tag handle so templates use
     * `{{ schema_thing_from_relationship }}`
     *
     * @var string
     */
    protected static $handle = 'schema_thing_from_relationship';

    /**
     * Build a JSON array string of relationship-derived schema nodes from the
     * current tag context and parameters.
     *
     * @return string JSON list of objects, or `[]` when nothing resolves
     */
    public function index(): string
    {
        // Read tag params via {@see TagParam::all()} and {@see Arr::get()} so static
        // analysis accepts string keys (`Collection::get` types keys from items).
        /** @var Parameters $tagParams */
        $tagParams = $this->params;
        $paramBag = TagParam::all($tagParams);

        // Read the mode parameter with direct mode as the default
        $modeRaw = Arr::get(
            $paramBag,
            'mode',
            'direct'
        );

        // Normalize mode to a lowercase string for comparisons
        $mode = \is_string($modeRaw) ? strtolower(trim($modeRaw)) : 'direct';

        // Map tag parameters into a plain array for the resolver
        $params = [
            'mode' => $mode,
            'secondary_context' => Arr::get($paramBag, 'secondary_context'),
            'global_handle' => Arr::get($paramBag, 'global_handle'),
            'global_field' => Arr::get($paramBag, 'global_field'),
            'current_field' => Arr::get($paramBag, 'current_field'),
            'secondary_field' => Arr::get($paramBag, 'secondary_field'),
            'current_field_replicator' => Arr::get($paramBag, 'current_field_replicator'),
            'secondary_field_replicator' => Arr::get($paramBag, 'secondary_field_replicator'),
            'current_field_replicator_relationship_field' => Arr::get($paramBag, 'current_field_replicator_relationship_field'),
            'secondary_field_replicator_relationship_field' => Arr::get($paramBag, 'secondary_field_replicator_relationship_field'),
            'secondary_scope' => Arr::get($paramBag, 'secondary_scope'),
            'direct_scope' => Arr::get($paramBag, 'direct_scope'),
            'nested_scope' => Arr::get($paramBag, 'nested_scope'),
            'intermediate_scope' => Arr::get($paramBag, 'intermediate_scope'),
            'merge_source_context' => Arr::get($paramBag, 'merge_source_context'),
            'tertiary_field' => Arr::get($paramBag, 'tertiary_field'),
            'tertiary_field_replicator' => Arr::get($paramBag, 'tertiary_field_replicator'),
            'tertiary_field_replicator_relationship_field' => Arr::get(
                $paramBag,
                'tertiary_field_replicator_relationship_field'
            ),
            'current_limit' => Arr::get($paramBag, 'current_limit'),
            'secondary_limit' => Arr::get($paramBag, 'secondary_limit'),
            'schema_object' => Arr::get($paramBag, 'schema_object'),
            'direct_term_data_equals_field' => Arr::get(
                $paramBag,
                'direct_term_data_equals_field'
            ),
            'direct_term_data_equals_value' => Arr::get(
                $paramBag,
                'direct_term_data_equals_value'
            ),
            'emit_context' => $this->resolveSchemaTemplateEmitContextFromAntlersScope(),
        ];

        // Resolve the service that builds ImageObject nodes from assets
        $resolver = app(SchemaThingFromRelationshipResolver::class);

        // Snapshot Antlers context as an associative array for the resolver
        /** @var Context $tagContext */
        $tagContext = $this->context;
        /** @var array<string, mixed> $context */
        $context = $tagContext->all();

        // Resolve JSON-LD nodes from the context and parameters
        $out = $resolver->resolve(
            $context,
            $params
        );

        // Unset local variables to free memory
        unset(
            $context,
            $mode,
            $modeRaw,
            $paramBag,
            $params,
            $resolver
        );

        // Return the resolved JSON-LD nodes
        return $out;
    }

    /**
     * Read `SchemaTemplateEmitContext` from Antlers when nested in schema templates.
     *
     * @return SchemaTemplateEmitContext|null Shared emit maps for the active graph, or null
     */
    private function resolveSchemaTemplateEmitContextFromAntlersScope(): ?SchemaTemplateEmitContext
    {
        $fromStack = SchemaTemplateEmitContextRuntimeStack::peek();

        if ($fromStack instanceof SchemaTemplateEmitContext) {
            return $fromStack;
        }

        // Read the emit context object from the live Antlers context
        /** @var Context $tagContext */
        $tagContext = $this->context;
        $raw = $tagContext->get(
            SchemaOutputService::ANTLERS_SCHEMA_TEMPLATE_EMIT_CONTEXT
        );

        // Accept only the expected context type
        if ($raw instanceof SchemaTemplateEmitContext) {
            return $raw;
        }

        unset($raw);

        return null;
    }
}
