<?php

declare(strict_types=1);

namespace App\Services\SchemaTemplates;

use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Contracts\Taxonomies\Term;
use Statamic\Entries\Entry as EntryModel;

/**
 * Shared fast-path and bookkeeping for schema_objects JSON-LD emission by @id.
 *
 * Coordinates cheap canonical @id preview with `SchemaOutputService` and
 * reference-vs-full decisions using `SchemaJsonLdAtIdDeduplication` so tags
 * and graph builds share one policy without duplicating logic. Tree walks that
 * collapse duplicate full nodes use {@see SchemaJsonLdAtIdDeduplication}
 * named helpers (`dedupeWithSharedEmitMapAndDecodeFirstFullRetention`,
 * `dedupeWithFreshEmitMap`) alongside low-level `dedupeNodeByAtId`.
 */
final readonly class SchemaJsonLdSchemaObjectEmissionCoordinator
{
    /**
     * Create the coordinator with shared dedupe helpers.
     *
     * @param  SchemaJsonLdAtIdDeduplication  $jsonLdAtIdDeduplication  @id map helpers
     */
    public function __construct(
        private SchemaJsonLdAtIdDeduplication $jsonLdAtIdDeduplication
    ) {}

    /**
     * Build `{"@id":…}` when this schema_objects template was already emitted as a
     * full node in the same pass (skips expensive template expansion).
     *
     * @param  SchemaOutputService  $schemaOutput  Source of canonical @id preview
     * @param  string  $templateEntryId  schema_objects entry id
     * @param  array<string, bool>  $emittedNodeIds  Full-node @id keys for this pass (by reference)
     * @param  array<string, bool>  $materializedFullNodeAtIds  @id keys whose full object survived shared dedupe
     * @param  EntryContract|Term|null  $contextEntity  Antlers / url context for @id rules
     * @param  array<int, string>  $graphEntryIds  Slot ids for graph-style @id rules
     * @param  array<string, mixed>  $antlersContext  Optional instance index and overrides
     * @param  SchemaTemplateInstanceWatermarkBag|null  $templateInstanceWatermarkBag  Peek-only allocation for preview @id
     * @return array<string, string>|null Reference map, or null when a full build is needed
     */
    public function tryCompactReferenceIfSchemaObjectAlreadyFullyEmitted(
        SchemaOutputService $schemaOutput,
        string $templateEntryId,
        array &$emittedNodeIds,
        array &$materializedFullNodeAtIds,
        EntryContract|Term|null $contextEntity,
        array $graphEntryIds,
        array $antlersContext,
        ?SchemaTemplateInstanceWatermarkBag $templateInstanceWatermarkBag = null
    ): ?array {
        // Resolve the canonical @id without expanding the full template
        $atId = $schemaOutput->previewCanonicalAtIdForSchemaObjectTemplate(
            $templateEntryId,
            $contextEntity,
            $graphEntryIds,
            $antlersContext,
            $templateInstanceWatermarkBag
        );

        // Require a preview @id for lookup. An empty one needs no check here:
        // compactToReferenceIfFullNodeAlreadyEmitted() rejects it below.
        if ($atId === null) {
            unset($atId);

            return null;
        }

        // Compact only when a full body for this @id is known to exist in merged
        // output (not merely recorded during an in-flight build that was later dropped)
        if (! isset($materializedFullNodeAtIds[$atId])) {
            unset($atId);

            return null;
        }

        // Return a compact reference when this @id already has a full node
        return $this->jsonLdAtIdDeduplication->compactToReferenceIfFullNodeAlreadyEmitted(
            $emittedNodeIds,
            $atId
        );
    }

    /**
     * Mark a full inline node as emitted for its canonical @id when it has @type.
     *
     * @param  array<string, bool>  $emittedNodeIds  Map updated by reference
     * @param  array<string, mixed>|null  $node  Resolved node, or null
     */
    public function recordFullSchemaObjectNode(
        array &$emittedNodeIds,
        ?array $node
    ): void {
        // Delegate to shared dedupe helper
        $this->jsonLdAtIdDeduplication->recordFullSchemaObjectNodeEmitted(
            $emittedNodeIds,
            $node
        );
    }

    /**
     * Build one JSON-LD reference object for a schema_objects entry using canonical @id rules.
     *
     * @param  EntryModel  $entry  Entry in the schema_objects collection
     * @param  SchemaOutputService  $schemaOutput  Canonical @id computation
     * @return array<string, string>|null `{"@id":…}` or null when @id is unusable
     */
    public function atIdReferenceObjectForSchemaObjectEntry(
        EntryModel $entry,
        SchemaOutputService $schemaOutput
    ): ?array {
        // Compute the canonical JSON-LD @id for this schema_objects entry
        $atId = $schemaOutput->getCanonicalAtIdForSchemaEntry($entry);

        // Reject empty @id strings
        if ($atId === '') {
            unset($atId);

            return null;
        }

        // Return the JSON-LD reference shape expected in property lists
        return ['@id' => $atId];
    }
}
