<?php

declare(strict_types=1);

namespace App\Tags;

use App\Services\SchemaTemplates\SchemaOutputService;
use App\Services\SchemaTemplates\SchemaTemplateEmitContext;
use App\Services\SchemaTemplates\SchemaTemplateEmitContextRuntimeStack;
use App\Services\SchemaTemplates\SchemaThingFromRelationshipResolver;
use Statamic\Tags\Context;
use Statamic\Tags\Parameters;
use Statamic\Tags\Tags;

/**
 * JSON array of keyword strings from subject_matter entry titles, alternate
 * names, and sameAs URLs for the same relationship paths as
 * {@see SchemaThingFromRelationship}. Taxonomy terms reached on those paths
 * contribute the same fields.
 *
 * Parameters match {@see SchemaThingFromRelationship} (same resolver `$params`
 * keys); see that tag for `mode`, field handles, limits, globals hop, and
 * related options.
 */
class SchemaSubjectMatterKeywords extends Tags
{
    /**
     * Antlers tag handle: {{ schema_subject_matter_keywords }}
     *
     * @var string
     */
    protected static $handle = 'schema_subject_matter_keywords';

    /**
     * Build a JSON array string of keyword strings from resolved taxonomy terms.
     *
     * @return string JSON list of strings, or `[]` when none resolve
     */
    public function index(): string
    {
        /** @var Parameters $tagParams */
        $tagParams = $this->params;

        // Read the mode parameter with direct mode as the default
        $modeRaw = $tagParams->get(
            'mode',
            'direct'
        );

        // Normalize mode to a lowercase string for the resolver
        $mode = \is_string($modeRaw) ? strtolower(trim($modeRaw)) : 'direct';

        // Map tag parameters into the resolver shape (aligned with schema_thing_from_relationship)
        $params = [
            'mode' => $mode,
            'secondary_context' => $tagParams->get('secondary_context'),
            'global_handle' => $tagParams->get('global_handle'),
            'global_field' => $tagParams->get('global_field'),
            'current_field' => $tagParams->get('current_field'),
            'secondary_field' => $tagParams->get('secondary_field'),
            'current_field_replicator' => $tagParams->get('current_field_replicator'),
            'secondary_field_replicator' => $tagParams->get('secondary_field_replicator'),
            'current_field_replicator_relationship_field' => $tagParams->get(
                'current_field_replicator_relationship_field'
            ),
            'secondary_field_replicator_relationship_field' => $tagParams->get(
                'secondary_field_replicator_relationship_field'
            ),
            'secondary_scope' => $tagParams->get('secondary_scope'),
            'direct_scope' => $tagParams->get('direct_scope'),
            'nested_scope' => $tagParams->get('nested_scope'),
            'intermediate_scope' => $tagParams->get('intermediate_scope'),
            'merge_source_context' => $tagParams->get('merge_source_context'),
            'tertiary_field' => $tagParams->get('tertiary_field'),
            'tertiary_field_replicator' => $tagParams->get('tertiary_field_replicator'),
            'tertiary_field_replicator_relationship_field' => $tagParams->get(
                'tertiary_field_replicator_relationship_field'
            ),
            'current_limit' => $tagParams->get('current_limit'),
            'secondary_limit' => $tagParams->get('secondary_limit'),
            'schema_object' => $tagParams->get('schema_object'),
            'direct_term_data_equals_field' => $tagParams->get(
                'direct_term_data_equals_field'
            ),
            'direct_term_data_equals_value' => $tagParams->get(
                'direct_term_data_equals_value'
            ),
            'emit_context' => $this->resolveSchemaTemplateEmitContextFromAntlersScope(),
        ];

        // Resolve the relationship resolver from the container
        $resolver = app(SchemaThingFromRelationshipResolver::class);

        /** @var Context $tagContext */
        $tagContext = $this->context;

        // Snapshot Antlers context for the resolver
        /** @var array<string, mixed> $context */
        $context = $tagContext->all();

        // Build JSON keyword list from direct or secondary traversal
        $out = $resolver->resolveSubjectMatterKeywords(
            $context,
            $params
        );

        // Unset local variables to free memory
        unset(
            $context,
            $mode,
            $modeRaw,
            $params,
            $resolver
        );

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

        /** @var Context $tagContext */
        $tagContext = $this->context;

        // Read the emit context object from the live Antlers context
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
