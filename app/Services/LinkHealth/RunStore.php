<?php

declare(strict_types=1);

namespace App\Services\LinkHealth;

use Illuminate\Support\Facades\DB;

/**
 * Persist link-health runs and read back what a scan can diff against (UAMS-Web/uams-statamic-archive#1766).
 *
 * The store exists to answer one question — "what broke since last time" — so its shape is driven
 * by {@see NewlyBrokenDiff} rather than by what a scan happens to produce.
 */
final class RunStore
{
    /**
     * How many runs are kept. Older ones are pruned as new ones land.
     *
     * The diff needs exactly one prior run, so this is a history for a human reading trends
     * rather than a working set. Unbounded, it would grow by every occurrence of every scan
     * forever, on a table nobody looks at until it is large.
     */
    public const int RETAINED_RUNS = 10;

    /**
     * Record one run and prune history to {@see RETAINED_RUNS}.
     *
     * @param  array<string, ProbeOutcome|ResolutionState>  $outcomes  Outcome by occurrence key.
     * @param  array<string, string>  $values  The stored value by occurrence key.
     * @param  array<string, string>  $reasons  Why a target was not reached, by occurrence key (UAMS-Web/uams-statamic-archive#1768).
     * @return int The new run's id.
     */
    public function record(
        array $outcomes,
        array $values = [],
        ?string $site = null,
        ?string $collection = null,
        array $reasons = [],
    ): int {
        // The cast is for drivers that return the id as a string. Every driver these tests run
        // against returns an int already, so removing it yields the same value and no fixture
        // can separate the two.
        // @pest-mutate-ignore: RemoveIntegerCast
        $runId = (int) DB::table('link_health_runs')->insertGetId([
            'started_at' => now(),
            'scope_site' => $site,
            'scope_collection' => $collection,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $rows = [];

        foreach ($outcomes as $key => $outcome) {
            $rows[] = [
                'run_id' => $runId,
                'occurrence_key' => $key,
                'value' => $values[$key] ?? '',
                'outcome' => $outcome->value,
                'reason' => $reasons[$key] ?? null,
            ];
        }

        // Chunked because a full scan is tens of thousands of occurrences and a single insert of
        // that width exceeds SQLite's bound-parameter limit.
        // Both mutations stay correct and only change cost: unwrapping the chunk inserts a row at
        // a time, and 501 is as valid a chunk width as 500. The bound exists to stay under
        // SQLite's parameter limit, which a test asserting rows written cannot observe.
        // @pest-mutate-ignore: UnwrapArrayChunk, IncrementInteger, DecrementInteger
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('link_health_occurrences')->insert($chunk);
        }

        $this->prune();

        return $runId;
    }

    /**
     * The outcomes of the most recent run BEFORE the given one, keyed by occurrence.
     *
     * Scoped to a matching scope, which is not a refinement but a correctness requirement: a
     * `--site` run diffed against a full one would show every occurrence outside that site as
     * absent, and a later full run would then read hundreds of links as newly broken. Comparing
     * unlike scopes produces a number that looks like news and is an artifact.
     *
     * @return array<string, string> Raw outcome values by occurrence key.
     */
    public function previousOutcomes(int $beforeRunId, ?string $site = null, ?string $collection = null): array
    {
        $previous = DB::table('link_health_runs')
            ->where('id', '<', $beforeRunId)
            ->where('scope_site', $site)
            ->where('scope_collection', $collection)
            ->orderByDesc('id')
            ->first();

        if ($previous === null) {
            return [];
        }

        $outcomes = [];

        // Built by iteration rather than `pluck()->all()` so the key and value types are what the
        // signature promises: the query builder types both as mixed, and the diff downstream
        // reads them as strings.
        foreach (DB::table('link_health_occurrences')->where('run_id', $previous->id)->get() as $row) {
            $key = $row->occurrence_key;
            $outcome = $row->outcome;

            // A narrowing guard the query builder's `mixed` return forces on us for larastan. The
            // driver returns both columns as strings for every row this table can hold, so `&&`
            // and `||` admit exactly the same rows and no fixture can separate them.
            // @pest-mutate-ignore: BooleanAndToBooleanOr
            if (\is_string($key) && \is_string($outcome)) {
                $outcomes[$key] = $outcome;
            }
        }

        return $outcomes;
    }

    /** How many runs are currently stored. */
    public function runCount(): int
    {
        return DB::table('link_health_runs')->count();
    }

    /**
     * Drop the oldest runs beyond the retention bound.
     *
     * Occurrences go with them through the cascade on the foreign key, so pruning cannot leave
     * orphaned rows behind — which is the failure that makes a "bounded" table grow anyway.
     */
    private function prune(): void
    {
        $keep = DB::table('link_health_runs')
            ->orderByDesc('id')
            ->limit(self::RETAINED_RUNS)
            ->pluck('id')
            ->all();

        if ($keep === []) {
            return;
        }

        DB::table('link_health_runs')->whereNotIn('id', $keep)->delete();
    }
}
