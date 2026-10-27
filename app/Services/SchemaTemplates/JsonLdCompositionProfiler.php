<?php

declare(strict_types=1);

namespace App\Services\SchemaTemplates;

use Illuminate\Support\Facades\Log;

/**
 * Accumulates and logs per-request JSON-LD composition profiling for
 * {@see SchemaOutputService}.
 *
 * All work is a no-op unless
 * `config('schema_templates.json_ld_compose_profile.enabled')` is true. Counters
 * live on a request attribute ({@see self::REQUEST_JSON_LD_COMPOSITION_PROFILE}) so
 * collaborators such as {@see SchemaThingFromRelationshipResolver} can contribute to
 * the same per-request profile output before it is logged once per compose.
 */
class JsonLdCompositionProfiler
{
    /**
     * Request attribute: JSON-LD composition profiling counters when
     * `config('schema_templates.json_ld_compose_profile.enabled')` is true.
     *
     * Shared by {@see SchemaThingFromRelationshipResolver} so relationship traversal
     * can contribute to the same per-request profile output.
     */
    public const string REQUEST_JSON_LD_COMPOSITION_PROFILE = '_app_schema_output_json_ld_comp_profile';

    /**
     * Whether to accumulate JSON-LD composition profiling (log once per compose when
     * enabled).
     */
    public function enabled(): bool
    {
        return (bool) config('schema_templates.json_ld_compose_profile.enabled', false);
    }

    public function topNLimit(): int
    {
        $topN = config('schema_templates.json_ld_compose_profile.top_n', 10);

        return max(
            1,
            (\is_scalar($topN) || $topN === null) ? (int) $topN : 10
        );
    }

    /**
     * Reset profiling counters at the start of
     * {@see SchemaOutputService::composeJsonLdOutputScriptTagForResolvedSlots()}.
     */
    public function reset(): void
    {
        if (! $this->enabled()) {
            return;
        }

        if (! app()->bound('request')) {
            return;
        }

        request()->attributes->set(
            self::REQUEST_JSON_LD_COMPOSITION_PROFILE,
            [
                'augment_uncached_calls' => 0,
                'augment_uncached_ms' => 0.0,
                'schema_object_node_build_calls' => 0,
                'schema_object_node_build_ms' => 0.0,
                'antlers_parse_calls' => 0,
                'antlers_parse_ms' => 0.0,
                'dedupe_calls' => 0,
                'dedupe_ms' => 0.0,
                'inflate_calls' => 0,
                'inflate_ms' => 0.0,
                'json_encode_calls' => 0,
                'json_encode_ms' => 0.0,
                'relationship_resolve_calls' => 0,
                'relationship_resolve_ms' => 0.0,
                'relationship_collect_direct_calls' => 0,
                'relationship_collect_direct_ms' => 0.0,
                'relationship_collect_secondary_calls' => 0,
                'relationship_collect_secondary_ms' => 0.0,
                'relationship_collect_tertiary_calls' => 0,
                'relationship_collect_tertiary_ms' => 0.0,
                'relationship_collect_global_calls' => 0,
                'relationship_collect_global_ms' => 0.0,
                'relationship_targets_direct' => 0,
                'relationship_targets_secondary' => 0,
                'relationship_targets_tertiary' => 0,
                'relationship_targets_global' => 0,
                'relationship_compact_ref_hits' => 0,
                'relationship_full_build_calls' => 0,
                'relationship_full_build_ms' => 0.0,
                'relationship_full_build_top' => [],
                'antlers_parse_top' => [],
            ]
        );
    }

    /**
     * Record one profiling bucket sample on the request memo bag.
     */
    public function recordBucketSample(
        string $callsKey,
        string $msKey,
        float $elapsedMs
    ): void {
        if (! $this->enabled()) {
            return;
        }

        if (! app()->bound('request')) {
            return;
        }

        $request = request();

        $bagRaw = $request->attributes->get(self::REQUEST_JSON_LD_COMPOSITION_PROFILE);

        if (! \is_array($bagRaw)) {
            return;
        }

        /** @var array<string, mixed> $bag */
        $bag = $bagRaw;

        /** @var mixed $callsValue */
        $callsValue = $bag[$callsKey] ?? 0;
        /** @var mixed $msValue */
        $msValue = $bag[$msKey] ?? 0.0;

        $bag[$callsKey] = (\is_scalar($callsValue) ? (int) $callsValue : 0) + 1;
        $bag[$msKey] = (\is_scalar($msValue) ? (float) $msValue : 0.0) + $elapsedMs;

        $request->attributes->set(
            self::REQUEST_JSON_LD_COMPOSITION_PROFILE,
            $bag
        );

        unset(
            $bag,
            $bagRaw,
            $request
        );
    }

    /**
     * Record a slow `Parse::template` sample for later Top-N logging.
     */
    public function recordAntlersParseSample(
        float $elapsedMs,
        ?string $schemaTemplateEntryId,
        ?string $schemaPropertyKey
    ): void {
        if (! $this->enabled()) {
            return;
        }

        if (! app()->bound('request')) {
            return;
        }

        $request = request();

        $bagRaw = $request->attributes->get(self::REQUEST_JSON_LD_COMPOSITION_PROFILE);

        if (! \is_array($bagRaw)) {
            return;
        }

        /** @var array<string, mixed> $bag */
        $bag = $bagRaw;

        $top = $bag['antlers_parse_top'] ?? null;

        if (! \is_array($top)) {
            $top = [];
        }

        $top[] = [
            'ms' => round($elapsedMs, 2),
            'template_entry_id' => \is_string($schemaTemplateEntryId) ? $schemaTemplateEntryId : null,
            'property_key' => \is_string($schemaPropertyKey) ? $schemaPropertyKey : null,
        ];

        usort(
            $top,
            fn (mixed $a, mixed $b): int => ((\is_array($b) ? $b['ms'] ?? 0 : 0) <=> (\is_array($a) ? $a['ms'] ?? 0 : 0))
        );

        $limit = $this->topNLimit();

        if (\count($top) > $limit) {
            $top = \array_slice($top, 0, $limit);
        }

        $bag['antlers_parse_top'] = $top;

        $request->attributes->set(
            self::REQUEST_JSON_LD_COMPOSITION_PROFILE,
            $bag
        );

        unset(
            $bag,
            $bagRaw,
            $request,
            $top,
            $limit
        );
    }

    /**
     * Record one uncached blueprint augment sample when JSON-LD compose profiling is
     * enabled.
     */
    public function recordAugmentUncachedSample(float $elapsedMs): void
    {
        if (! $this->enabled()) {
            return;
        }

        if (! app()->bound('request')) {
            return;
        }

        $request = request();
        $bagRaw = $request->attributes->get(self::REQUEST_JSON_LD_COMPOSITION_PROFILE);

        /** @var array{augment_uncached_calls?: int, augment_uncached_ms?: float} $bag */
        $bag = \is_array($bagRaw)
            ? $bagRaw
            : [
                'augment_uncached_calls' => 0,
                'augment_uncached_ms' => 0.0,
            ];

        $bag['augment_uncached_calls'] = ($bag['augment_uncached_calls'] ?? 0) + 1;
        $bag['augment_uncached_ms'] = ($bag['augment_uncached_ms'] ?? 0.0) + $elapsedMs;

        $request->attributes->set(
            self::REQUEST_JSON_LD_COMPOSITION_PROFILE,
            $bag
        );

        unset(
            $request,
            $bag,
            $bagRaw
        );
    }

    /**
     * Log augment totals and compose wall time when JSON-LD compose profiling is
     * enabled.
     */
    public function finalize(float $composeWallMs): void
    {
        if (! $this->enabled()) {
            return;
        }

        if (! app()->bound('request')) {
            return;
        }

        $rawProfile = request()->attributes->get(self::REQUEST_JSON_LD_COMPOSITION_PROFILE);

        if (! \is_array($rawProfile)) {
            Log::info('schema_json_ld_composition_profile', [
                'compose_wall_ms' => round($composeWallMs, 2),
                'augment_uncached_calls' => 0,
                'augment_uncached_ms' => 0.0,
            ]);

            return;
        }

        /**
         * @var array{
         *     augment_uncached_calls?: mixed,
         *     augment_uncached_ms?: mixed,
         *     schema_object_node_build_calls?: mixed,
         *     schema_object_node_build_ms?: mixed,
         *     antlers_parse_calls?: mixed,
         *     antlers_parse_ms?: mixed,
         *     dedupe_calls?: mixed,
         *     dedupe_ms?: mixed,
         *     inflate_calls?: mixed,
         *     inflate_ms?: mixed,
         *     json_encode_calls?: mixed,
         *     json_encode_ms?: mixed,
         *     relationship_resolve_calls?: mixed,
         *     relationship_resolve_ms?: mixed,
         *     relationship_collect_direct_calls?: mixed,
         *     relationship_collect_direct_ms?: mixed,
         *     relationship_collect_secondary_calls?: mixed,
         *     relationship_collect_secondary_ms?: mixed,
         *     relationship_collect_tertiary_calls?: mixed,
         *     relationship_collect_tertiary_ms?: mixed,
         *     relationship_collect_global_calls?: mixed,
         *     relationship_collect_global_ms?: mixed,
         *     relationship_targets_direct?: mixed,
         *     relationship_targets_secondary?: mixed,
         *     relationship_targets_tertiary?: mixed,
         *     relationship_targets_global?: mixed,
         *     relationship_compact_ref_hits?: mixed,
         *     relationship_full_build_calls?: mixed,
         *     relationship_full_build_ms?: mixed,
         *     antlers_parse_top?: mixed,
         *     relationship_full_build_top?: mixed
         * } $profile
         */
        $profile = $rawProfile;

        Log::info('schema_json_ld_composition_profile', [
            'compose_wall_ms' => round($composeWallMs, 2),
            'augment_uncached_calls' => $this->scalarToInt($profile['augment_uncached_calls'] ?? 0),
            'augment_uncached_ms' => round($this->scalarToFloat($profile['augment_uncached_ms'] ?? 0.0), 2),
            'schema_object_node_build_calls' => $this->scalarToInt($profile['schema_object_node_build_calls'] ?? 0),
            'schema_object_node_build_ms' => round($this->scalarToFloat($profile['schema_object_node_build_ms'] ?? 0.0), 2),
            'antlers_parse_calls' => $this->scalarToInt($profile['antlers_parse_calls'] ?? 0),
            'antlers_parse_ms' => round($this->scalarToFloat($profile['antlers_parse_ms'] ?? 0.0), 2),
            'dedupe_calls' => $this->scalarToInt($profile['dedupe_calls'] ?? 0),
            'dedupe_ms' => round($this->scalarToFloat($profile['dedupe_ms'] ?? 0.0), 2),
            'inflate_calls' => $this->scalarToInt($profile['inflate_calls'] ?? 0),
            'inflate_ms' => round($this->scalarToFloat($profile['inflate_ms'] ?? 0.0), 2),
            'json_encode_calls' => $this->scalarToInt($profile['json_encode_calls'] ?? 0),
            'json_encode_ms' => round($this->scalarToFloat($profile['json_encode_ms'] ?? 0.0), 2),
            'relationship_resolve_calls' => $this->scalarToInt($profile['relationship_resolve_calls'] ?? 0),
            'relationship_resolve_ms' => round($this->scalarToFloat($profile['relationship_resolve_ms'] ?? 0.0), 2),
            'relationship_collect_direct_calls' => $this->scalarToInt($profile['relationship_collect_direct_calls'] ?? 0),
            'relationship_collect_direct_ms' => round($this->scalarToFloat($profile['relationship_collect_direct_ms'] ?? 0.0), 2),
            'relationship_collect_secondary_calls' => $this->scalarToInt($profile['relationship_collect_secondary_calls'] ?? 0),
            'relationship_collect_secondary_ms' => round($this->scalarToFloat($profile['relationship_collect_secondary_ms'] ?? 0.0), 2),
            'relationship_collect_tertiary_calls' => $this->scalarToInt($profile['relationship_collect_tertiary_calls'] ?? 0),
            'relationship_collect_tertiary_ms' => round($this->scalarToFloat($profile['relationship_collect_tertiary_ms'] ?? 0.0), 2),
            'relationship_collect_global_calls' => $this->scalarToInt($profile['relationship_collect_global_calls'] ?? 0),
            'relationship_collect_global_ms' => round($this->scalarToFloat($profile['relationship_collect_global_ms'] ?? 0.0), 2),
            'relationship_targets_direct' => $this->scalarToInt($profile['relationship_targets_direct'] ?? 0),
            'relationship_targets_secondary' => $this->scalarToInt($profile['relationship_targets_secondary'] ?? 0),
            'relationship_targets_tertiary' => $this->scalarToInt($profile['relationship_targets_tertiary'] ?? 0),
            'relationship_targets_global' => $this->scalarToInt($profile['relationship_targets_global'] ?? 0),
            'relationship_compact_ref_hits' => $this->scalarToInt($profile['relationship_compact_ref_hits'] ?? 0),
            'relationship_full_build_calls' => $this->scalarToInt($profile['relationship_full_build_calls'] ?? 0),
            'relationship_full_build_ms' => round($this->scalarToFloat($profile['relationship_full_build_ms'] ?? 0.0), 2),
        ]);

        $top = $profile['antlers_parse_top'] ?? null;

        if (\is_array($top) && $top !== []) {
            Log::info('schema_json_ld_antlers_parse_top', [
                'top' => $top,
            ]);
        }

        $relTop = $profile['relationship_full_build_top'] ?? null;

        if (\is_array($relTop) && $relTop !== []) {
            Log::info('schema_json_ld_relationship_full_build_top', [
                'top' => $relTop,
            ]);
        }
    }

    /**
     * Coerce a profiling counter value to int, mirroring PHP's implicit `(int)`
     * coercion for scalars while treating non-scalars as 0.
     */
    private function scalarToInt(mixed $value): int
    {
        return \is_scalar($value) ? (int) $value : 0;
    }

    /**
     * Coerce a profiling counter value to float, mirroring PHP's implicit `(float)`
     * coercion for scalars while treating non-scalars as 0.0.
     */
    private function scalarToFloat(mixed $value): float
    {
        return \is_scalar($value) ? (float) $value : 0.0;
    }
}
