<?php

declare(strict_types=1);

namespace App\Services\SchemaTemplates;

/**
 * Shared JSON-LD @id deduplication for schema template output.
 *
 * Centralizes list-item keys, recursive node collapsing, unique ref lists, and
 * full-node @id registration so SchemaOutputService,
 * {@see SchemaJsonLdSchemaObjectEmissionCoordinator}, SchemaThingRefsResolver,
 * and SchemaThingFromRelationshipResolver stay aligned.
 *
 * Emission passes (same canonical `@id` may appear from graph slots, nested
 * `schema_template_node` / relationship tags, and Antlers JSON decode):
 *
 * - **Shared pass map** (`$emittedNodeIds` by reference): one map for the
 *   whole request graph build. Top-level graph slots and `thing_*` resolution
 *   call {@see recordFullSchemaObjectNodeEmitted}. Relationship resolvers use
 *   {@see compactToReferenceIfFullNodeAlreadyEmitted}. Nested Antlers decode
 *   must not add new ids here (see
 *   {@see dedupeWithSharedEmitMapAndDecodeFirstFullRetention}).
 * - **Decode-first-full retention**: when walking JSON decoded from Antlers or
 *   from a nested template build, use {@see dedupeWithSharedEmitMapAndDecodeFirstFullRetention}
 *   so one inline full node per `@id` can still appear when that `@id` was
 *   already marked in the shared map (see `dedupeNodeByAtId` third parameter).
 *   New full nodes seen only inside that walk use a scratch map so the shared
 *   map is not polluted.
 * - **Fresh pass map**: after all top-level graph slots are built, use
 *   {@see dedupeWithFreshEmitMap} so a new empty map applies strict
 *   “second full collapses to `{"@id":…}`” rules across branches without
 *   mutating the shared build map.
 */
class SchemaJsonLdAtIdDeduplication
{
    /**
     * Append a `{"@id":…}` object when its @id is new in the seen map.
     *
     * @param  array<int, array<string, string>>  $list  Output list (by reference)
     * @param  array<string, bool>  $seenAtIdKeys  Canonical @id strings already taken
     * @param  array<string, string>  $atIdObject  Map containing an `@id` key
     */
    public function appendUniqueAtIdObject(
        array &$list,
        array &$seenAtIdKeys,
        array $atIdObject
    ): void {
        // Read the canonical @id string from the reference object
        $key = $atIdObject['@id'] ?? '';

        // Skip objects without a non-empty string @id
        if ($key === '') {
            unset($key);

            return;
        }

        // Skip when this @id was already appended
        if (isset($seenAtIdKeys[$key])) {
            unset($key);

            return;
        }

        // Record the @id so later duplicates are ignored
        $seenAtIdKeys[$key] = true;

        // Append the reference object to the output list
        $list[] = $atIdObject;

        unset($key);
    }

    /**
     * Whether a node with this @id should be emitted (first non-empty @id wins).
     *
     * Empty @id always returns true and does not update the seen map, matching
     * relationship resolver behavior for nodes without a stable id.
     *
     * @param  array<string, bool>  $seenAtIds  Non-empty @id values already emitted
     */
    public function shouldEmitUniqueAtIdNode(
        string $atId,
        array &$seenAtIds
    ): bool {
        // Allow every node when no @id is available for deduplication
        if ($atId === '') {
            return true;
        }

        // Skip duplicate emissions for the same canonical @id
        if (isset($seenAtIds[$atId])) {
            return false;
        }

        // Mark this @id as emitted for subsequent targets
        $seenAtIds[$atId] = true;

        return true;
    }

    /**
     * Remove duplicate list items while preserving first-seen order.
     *
     * Used for multi-row schema properties (e.g. alternateName) so identical
     * Antlers output from different rows appears once in JSON-LD.
     *
     * @param  array<int, mixed>  $items  Resolved fragments from rows or merges
     * @return array<int, mixed> List with duplicates removed
     */
    public function dedupeResolvedPropertyList(
        array $items
    ): array {
        // Initialize output and seen-key map
        $out = [];
        $seen = [];

        // Walk items and append only first occurrences
        foreach ($items as $item) {
            // Build a stable key when the item supports deduplication
            $key = $this->resolvedPropertyListItemDedupeKey($item);

            // Check if a key was produced and was already seen
            if ($key !== null && isset($seen[$key])) {
                // Prefer full JSON-LD nodes over compact @id references
                $existingIndex = $seen[$key];
                $existing = $out[$existingIndex] ?? null;

                // Check if the existing value is a compact @id reference and the
                // new item is full. A full item only shares a key through its
                // @id, so an existing array without @type is that reference.
                if (
                    \is_array($existing)
                    && ! isset($existing['@type'])
                    && \is_array($item)
                    && isset($item['@type'])
                ) {
                    // Replace the compact reference with the full node
                    $out[$existingIndex] = $item;
                }

                unset(
                    $existing,
                    $existingIndex
                );

                continue;
            }

            // Record the key when present so later duplicates are skipped
            if ($key !== null) {
                $seen[$key] = \count($out);
            }

            // Append this item to the output list
            $out[] = $item;
        }

        unset(
            $item,
            $key,
            $seen
        );

        return $out;
    }

    /**
     * When a canonical @id already has a full node in this pass, return a compact
     * reference object; otherwise return null so the caller builds the full node.
     *
     * @param  array<string, bool>  $emittedNodeIds  Canonical @id keys already emitted as full objects
     * @param  string  $atId  Canonical JSON-LD @id to look up
     * @return array<string, string>|null `{"@id":…}` when already emitted, or null
     */
    public function compactToReferenceIfFullNodeAlreadyEmitted(
        array &$emittedNodeIds,
        string $atId
    ): ?array {
        // Reject empty ids without treating them as emitted
        if ($atId === '') {
            return null;
        }

        // Check whether a full object for this @id was already recorded
        if (! isset($emittedNodeIds[$atId])) {
            return null;
        }

        // Return the JSON-LD reference shape only
        return ['@id' => $atId];
    }

    /**
     * Record that a resolved full JSON-LD object (with @type) was emitted for its @id.
     *
     * @param  array<string, bool>  $emittedNodeIds  Map updated by reference
     * @param  array<string, mixed>|null  $node  Resolved node map, or null
     */
    public function recordFullSchemaObjectNodeEmitted(
        array &$emittedNodeIds,
        ?array $node
    ): void {
        // Skip a missing node and reference-only maps (no inline @type)
        if (! isset($node['@type'])) {
            return;
        }

        // Read the node @id when present
        $atId = $node['@id'] ?? null;

        // Record only non-empty string @id values
        if (! \is_string($atId) || $atId === '') {
            return;
        }

        // Mark this canonical @id as having a full object in this pass
        $emittedNodeIds[$atId] = true;

        unset($atId);
    }

    /**
     * Deduplicate a decoded JSON value using the shared emit map for reads
     * only (plus decode-first-full retention for pre-registered `@id` values).
     *
     * Use for Antlers JSON output and nested `schema_template_node` builds.
     * New full-node `@id` keys are not written to `$emittedNodeIds`; a scratch
     * map handles duplicates within this fragment only.
     *
     * @param  mixed  $value  Decoded array/object fragment or list
     * @param  array<string, bool>  $emittedNodeIds  Shared full-node map for the
     *                                               graph build (by reference)
     * @return mixed Value after recursive dedupe
     */
    public function dedupeWithSharedEmitMapAndDecodeFirstFullRetention(
        mixed $value,
        array &$emittedNodeIds
    ): mixed {
        // Allocate a fresh decode buffer so each top-level call tracks
        // first-full retention independently
        $decodePassFirstFullAtIds = [];

        // Track duplicate full nodes within this fragment only; do not register
        // new @id keys on the shared graph map (see class doc).
        $localFullNodeIdsScratch = [];

        return $this->dedupeNodeByAtId(
            $value,
            $emittedNodeIds,
            $decodePassFirstFullAtIds,
            false,
            $localFullNodeIdsScratch
        );
    }

    /**
     * Deduplicate a value using a new empty emit map (strict duplicate-full
     * collapse).
     *
     * Use for a final pass over the assembled `@graph` list so repeated full
     * nodes that share a canonical `@id` across branches become compact
     * references without touching the original shared build map.
     *
     * @param  mixed  $value  Typically the `@graph` list array
     * @return mixed Value after recursive dedupe
     */
    public function dedupeWithFreshEmitMap(mixed $value): mixed
    {
        // Initialize an isolated map so ordering follows this traversal only
        $emittedNodeIds = [];

        return $this->dedupeNodeByAtId(
            $value,
            $emittedNodeIds
        );
    }

    /**
     * Replace repeated full objects with @id references across one build pass.
     *
     * Only maps with both `@id` and `@type` register in `$emittedNodeIds` when
     * `$persistNewFullNodeIdsToSharedEmitMap` is true (or in the local scratch
     * when it is false). Bare `{"@id":…}` references do not mark the id, so a later
     * full node with the same id is not collapsed before it is emitted.
     *
     * @param  mixed  $value  Node value or list of values
     * @param  array<string, bool>  $emittedNodeIds  Seen full-node ids for pass
     * @param  array<string, bool>|null  $decodePassFirstFullAtIds  When non-null (JSON decode path), the first full node for an @id pre-registered
     *                                                              by an upstream tag is kept inline once per top-level decode
     * @param  bool  $persistNewFullNodeIdsToSharedEmitMap  When false, first-seen full nodes in this walk update
     *                                                      `$localFullNodeIdsScratch` only (not `$emittedNodeIds`)
     * @param  array<string, bool>|null  $localFullNodeIdsScratch  Map for within-fragment duplicate-full detection when persist flag is false
     */
    public function dedupeNodeByAtId(
        mixed $value,
        array &$emittedNodeIds,
        ?array &$decodePassFirstFullAtIds = null,
        bool $persistNewFullNodeIdsToSharedEmitMap = true,
        ?array &$localFullNodeIdsScratch = null
    ): mixed {
        // Check if the value is a non-list object map
        if (
            \is_array($value)
            && ! array_is_list($value)
        ) {
            // Read the object's @id value when present
            $nodeId = $value['@id'] ?? null;

            // Check if the object has a usable @id value
            if (
                \is_string($nodeId)
                && $nodeId !== ''
            ) {
                // Read whether this map is a full JSON-LD node (not a bare @id reference)
                $isFullNode = isset($value['@type']);

                // Check whether this @id counts as already emitted (shared map,
                // optional local scratch, or both)
                $alreadyEmitted = isset($emittedNodeIds[$nodeId])
                    || (
                        $localFullNodeIdsScratch !== null
                        && isset($localFullNodeIdsScratch[$nodeId])
                    );

                // Check if a pre-registered id should keep one full inline copy in this decode
                if (
                    $alreadyEmitted
                    && $isFullNode
                    && $decodePassFirstFullAtIds !== null
                    && ! isset($decodePassFirstFullAtIds[$nodeId])
                ) {
                    // Record that this decode pass retained the first full body for the id
                    $decodePassFirstFullAtIds[$nodeId] = true;
                } elseif (
                    $alreadyEmitted
                    && $isFullNode
                ) {
                    // Replace duplicate full object with a compact @id reference
                    return ['@id' => $nodeId];
                } elseif ($alreadyEmitted) {
                    // Normalize repeated bare references to a single compact shape
                    return ['@id' => $nodeId];
                }

                // Mark first-seen full node when this @id is not yet in the pass map
                if (
                    $isFullNode
                    && ! $alreadyEmitted
                ) {
                    // Mark this object id for subsequent duplicate checks in this walk
                    if ($persistNewFullNodeIdsToSharedEmitMap) {
                        $emittedNodeIds[$nodeId] = true;
                    } elseif ($localFullNodeIdsScratch !== null) {
                        $localFullNodeIdsScratch[$nodeId] = true;
                    }
                }
            }

            // Loop through associative properties for nested deduplication
            foreach ($value as $key => $itemValue) {
                // Preserve the @id key without recursing into it
                if ($key === '@id') {
                    continue;
                }

                // Deduplicate nested structures under this key
                $value[$key] = $this->dedupeNodeByAtId(
                    $itemValue,
                    $emittedNodeIds,
                    $decodePassFirstFullAtIds,
                    $persistNewFullNodeIdsToSharedEmitMap,
                    $localFullNodeIdsScratch
                );
            }

            unset(
                $alreadyEmitted,
                $isFullNode,
                $itemValue,
                $key,
                $nodeId
            );

            return $value;
        }

        // Deduplicate each item of a list recursively (maps returned above)
        if (\is_array($value)) {
            foreach ($value as $index => $itemValue) {
                $value[$index] = $this->dedupeNodeByAtId(
                    $itemValue,
                    $emittedNodeIds,
                    $decodePassFirstFullAtIds,
                    $persistNewFullNodeIdsToSharedEmitMap,
                    $localFullNodeIdsScratch
                );
            }

            unset(
                $index,
                $itemValue
            );
        }

        return $value;
    }

    /**
     * Build a comparison key for dedupeResolvedPropertyList, or null to keep.
     *
     * Scalars use serialize. Maps with a string @id dedupe by that id. Other
     * arrays use JSON encoding so identical structures collapse. The three
     * kinds cannot meet: a serialized value starts with a type letter, a JSON
     * array starts with `[` or `{`, and an @id key starts with `id:`.
     *
     * @param  mixed  $item  One resolved list item
     */
    private function resolvedPropertyListItemDedupeKey(
        mixed $item
    ): ?string {
        // Use serialize for scalar values (strict, type-safe)
        if (
            \is_string($item)
            || \is_int($item)
            || \is_float($item)
            || \is_bool($item)
        ) {
            return serialize($item);
        }

        // Skip dedupe key for non-array values (e.g. unexpected objects)
        if (! \is_array($item)) {
            return null;
        }

        // Deduplicate @id references and nodes by canonical @id string
        if (
            isset($item['@id'])
            && \is_string($item['@id'])
            && $item['@id'] !== ''
        ) {
            // The prefix only has to mark the key as an @id key, and as a
            // suffix it would still do that, so ConcatSwitchSides changes no
            // dedupe result.
            // @pest-mutate-ignore: ConcatSwitchSides
            return 'id:'.$item['@id'];
        }

        // Encode list-shaped or other arrays for structural equality
        $json = json_encode($item);

        // Fall back to serialize when JSON encoding fails (INF, NAN, bad UTF-8)
        if ($json === false) {
            return serialize($item);
        }

        return $json;
    }

    /**
     * Record every canonical `@id` that appears on a full JSON-LD object map (has
     * both `@id` and `@type`) anywhere under `$value`, after shared dedupe has
     * settled the fragment. Used so `schema_template_node` can compact only when
     * a matching full body is known to exist in merged output (type/property agnostic).
     *
     * @param  mixed  $value  Decoded JSON-LD fragment or list
     * @param  array<string, bool>  $materializedFullNodeAtIds  Canonical @id keys (by reference)
     */
    public function recordMaterializedFullNodeAtIdsFromFragment(
        mixed $value,
        array &$materializedFullNodeAtIds
    ): void {
        $this->materializeFullNodeIdsFromTreeInto(
            $value,
            $materializedFullNodeAtIds
        );
    }

    /**
     * Reconcile the compact fast-path map with full nodes that actually exist under
     * one or more tree roots (e.g. committed @graph slot nodes). Drops stale @id
     * keys that were recorded during speculative resolution but never survived into
     * committed output, then merges in every full-node @id from the roots.
     *
     * @param  array<int, mixed>  $roots  JSON-LD value trees (e.g. top-level @graph list)
     * @param  array<string, bool>  $materializedFullNodeAtIds  Map updated by reference
     */
    public function syncMaterializedFullNodeAtIdsToUnionOfTreeRoots(
        array $roots,
        array &$materializedFullNodeAtIds
    ): void {
        $present = [];
        foreach ($roots as $root) {
            $this->materializeFullNodeIdsFromTreeInto(
                $root,
                $present
            );
        }

        foreach (array_keys($materializedFullNodeAtIds) as $id) {
            if (! isset($present[$id])) {
                unset($materializedFullNodeAtIds[$id]);
            }
        }

        foreach (array_keys($present) as $id) {
            $materializedFullNodeAtIds[$id] = true;
        }

        unset(
            $present,
            $id,
            $root
        );
    }

    /**
     * @param  array<string, bool>  $targetIds  Canonical @id keys (by reference)
     */
    private function materializeFullNodeIdsFromTreeInto(
        mixed $value,
        array &$targetIds
    ): void {
        if (
            \is_array($value)
            && ! array_is_list($value)
        ) {
            $nodeId = $value['@id'] ?? null;

            if (
                \is_string($nodeId)
                && $nodeId !== ''
                && isset($value['@type'])
            ) {
                $targetIds[$nodeId] = true;
            }

            foreach ($value as $key => $child) {
                if ($key === '@id') {
                    continue;
                }

                $this->materializeFullNodeIdsFromTreeInto(
                    $child,
                    $targetIds
                );
            }

            unset(
                $child,
                $key,
                $nodeId
            );

            return;
        }

        if (
            \is_array($value)
            && array_is_list($value)
        ) {
            foreach ($value as $child) {
                $this->materializeFullNodeIdsFromTreeInto(
                    $child,
                    $targetIds
                );
            }

            unset($child);
        }
    }
}
