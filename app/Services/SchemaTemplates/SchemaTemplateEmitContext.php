<?php

declare(strict_types=1);

namespace App\Services\SchemaTemplates;

use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Contracts\Taxonomies\Term;

/**
 * Mutable bag injected into Antlers while resolving schema property templates.
 *
 * Lets `schema_template_node` share `emitted_entry_ids`, `emitted_node_ids`,
 * `materialized_full_node_at_ids`, and `template_instance_watermark_bag` with
 * the active graph build so repeated JSON-LD nodes collapse to `@id` references
 * only after a full object for that `@id` survived shared dedupe in merged
 * output, while explicit template instance indices still allocate distinct `@id`
 * suffixes under the same scope entity.
 */
final class SchemaTemplateEmitContext
{
    /**
     * schema_objects entry ids that already received a full inline node.
     *
     * @var array<int, string>
     */
    public array $emittedEntryIds;

    /**
     * Canonical JSON-LD node `@id` strings already emitted in this pass.
     *
     * @var array<string, bool>
     */
    public array $emittedNodeIds;

    /**
     * Canonical `@id` keys for which a full object (`@id` + `@type`) exists in
     * output after shared dedupe (Antlers JSON merge, graph slot nodes, thing_*).
     *
     * @var array<string, bool>
     */
    public array $materializedFullNodeAtIds;

    /**
     * @param  array<int, string>  $emittedEntryIds  By reference; same arrays as graph build
     * @param  array<string, bool>  $emittedNodeIds  By reference; same arrays as graph build
     * @param  array<string, bool>  $materializedFullNodeAtIds  By reference; compact fast-path gate
     * @param  array<int, string>  $graphEntryIds  Slot entry ids for canonical `@id` rules
     */
    public function __construct(
        array &$emittedEntryIds,
        array &$emittedNodeIds,
        array &$materializedFullNodeAtIds,
        public array $graphEntryIds,
        public EntryContract|Term|null $contextEntity,
        /**
         * Per-build template instance allocation shared with nested Antlers and
         * {@see SchemaOutputService::buildSchemaTemplateNodeJsonForTag()}.
         */
        public SchemaTemplateInstanceWatermarkBag $templateInstanceWatermarkBag
    ) {
        // Bind to the parent scope arrays so tag output mutates graph dedupe state
        $this->emittedEntryIds = &$emittedEntryIds;
        $this->emittedNodeIds = &$emittedNodeIds;
        $this->materializedFullNodeAtIds = &$materializedFullNodeAtIds;
    }
}
