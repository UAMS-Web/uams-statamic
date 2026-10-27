<?php

declare(strict_types=1);

namespace App\Services\Locations;

use App\Support\Locations\ResolvesSpecialHoursValues;
use App\Tags\LocationModifiedHoursNotice;
use App\Tags\SchemaLocationHours;
use Illuminate\Support\Carbon;
use Statamic\Facades\GlobalSet;
use Statamic\Globals\GlobalSet as GlobalSetItem;
use Statamic\Globals\Variables;

/**
 * Broadcast org-wide holiday closures across `clinical_locations` (UAMS-Web/uams-statamic-archive#1327).
 *
 * The per-location modified/holiday-hours model (`special_hours`, UAMS-Web/uams-statamic-archive#967/UAMS-Web/uams-statamic-archive#1328) is
 * authoritative but can't express a one-action system-wide closure — "close every
 * UAMS clinic for Thanksgiving" means adding the same holiday row to every entry.
 * This service reads a central `location_closures` replicator on the Find-a-Doc
 * Settings global and fans it out to each location at render time, so both hours
 * consumers — {@see SchemaLocationHours} (JSON-LD) and
 * {@see LocationModifiedHoursNotice} (visitor notice) — merge it with
 * the location's own `special_hours` before emitting.
 *
 * Precedence is resolved **per calendar date, local-wins**: a date already covered
 * by the location's own `special_hours` suppresses the central closure for that
 * date (the clinic that sets its own Thanksgiving hours overrides the org default).
 * A partly-overlapped central range keeps its uncovered dates. Coverage counts
 * only in-person local rules — a telemedicine-only local rule describes no physical
 * premises, so it can't suppress a physical closure. The merge itself is otherwise
 * mode-agnostic: it preserves each rule's `service_mode` and leaves emission
 * filtering to the consumers ({@see SchemaLocationHours} skips telemedicine rules;
 * {@see LocationModifiedHoursNotice} renders every mode, exactly as it already does
 * for a location's own telemedicine `special_hours`). Auto-expiry (UAMS-Web/uams-statamic-archive#1328) is NOT
 * applied here: the merged rows flow through each consumer's existing past-window
 * filter, so a stale central closure is dropped there uniformly.
 *
 * The merged result is expressed as ordinary `holiday`-shaped rows so both
 * consumers process central closures through their existing per-rule code paths.
 */
class SystemLocationClosures
{
    use ResolvesSpecialHoursValues;

    /**
     * Handle of the Find-a-Doc Settings global that carries the central closures.
     */
    private const string GLOBAL_HANDLE = 'uams_fad_settings';

    /**
     * Field on the global holding the `holiday`-shaped closures replicator.
     */
    private const string CLOSURES_FIELD = 'location_closures';

    /**
     * Per-location opt-out value that suppresses the org-wide broadcast.
     */
    private const string OPT_OUT_SUPPRESS = 'suppress';

    /**
     * Upper bound on the days walked while subtracting local coverage from one
     * central range — a guard against absurd fat-fingered date ranges (real
     * closures span a day or a few), not a functional limit.
     */
    private const int RANGE_DAY_CAP = 1500;

    /**
     * Memoized central closure rows for this instance's lifetime.
     *
     * @var array<int, mixed>|null
     */
    private ?array $centralRows = null;

    /**
     * The effective special-hours rows for a location: its own `special_hours`,
     * followed by the in-scope org-wide closures with any locally-covered date
     * removed. Reads the central source from the global and the per-location
     * opt-out from the entry.
     *
     * @param  mixed  $localSpecialHours  The location's raw/augmented `special_hours` value
     * @param  mixed  $optOut  The location's `system_closures` opt-out value
     * @return array<int, mixed> Combined special-hours rows (local first)
     */
    public function effectiveSpecialHours(mixed $localSpecialHours, mixed $optOut): array
    {
        // Delegate to the pure merge, honoring the broadcast unless opted out.
        return $this->merge(
            $localSpecialHours,
            $this->centralClosureRows(),
            ! $this->suppresses($optOut)
        );
    }

    /**
     * Merge a location's own special-hours rows with central closure rows, dropping
     * any central date the location already covers (local wins). Pure over its
     * inputs — no global or entry reads — so it is unit-testable directly.
     *
     * @param  mixed  $localSpecialHours  The location's raw/augmented `special_hours` value
     * @param  array<int, mixed>  $centralClosures  Central `holiday`-shaped rows
     * @param  bool  $honors  Whether this location honors the org-wide broadcast
     * @return array<int, mixed> Combined special-hours rows (local first)
     */
    public function merge(mixed $localSpecialHours, array $centralClosures, bool $honors): array
    {
        // Normalize the location's own rows once.
        $localRows = $this->rows($localSpecialHours);

        // Opted out, or nothing to broadcast → the location's own rows, unchanged.
        if (! $honors || $centralClosures === []) {
            // Zero behavior change for the common no-closures / opted-out path
            return $localRows;
        }

        // Build the local coverage windows the central closures must yield to.
        $coverage = $this->localCoverage($localRows);

        // Reduce each central closure to the dates the location has not addressed.
        $survivors = [];

        // Walk each central closure entry
        foreach ($centralClosures as $entry) {
            // Keep only its uncovered date runs (null when fully covered)
            $row = $this->centralRowUncovered($entry, $coverage);

            // Collect the surviving central closure
            if ($row !== null) {
                // Append behind the location's own rows
                $survivors[] = $row;
            }
        }

        // Local rows first, then the surviving central closures.
        return array_merge($localRows, $survivors);
    }

    /**
     * Read the central `location_closures` rows from the Find-a-Doc Settings global.
     *
     * @return array<int, mixed> Raw `holiday`-shaped replicator rows (empty when unset)
     */
    public function centralClosureRows(): array
    {
        // Return the memoized rows once resolved
        if ($this->centralRows !== null) {
            // Reuse the cached read
            return $this->centralRows;
        }

        // Resolve the global set container
        /** @var GlobalSetItem|null $set */
        $set = GlobalSet::findByHandle(self::GLOBAL_HANDLE);

        // No global configured → no central closures
        if ($set === null) {
            // Cache and return the empty result
            return $this->centralRows = [];
        }

        // The closure fields are non-localizable and the global is rooted at the
        // `default` site, so `inDefaultSite()` resolves them from any site
        // context (UAMS-Web/uams-statamic-archive#1698).
        /** @var Variables|null $variables */
        $variables = $set->inDefaultSite();

        // Normalize the stored replicator value into rows
        return $this->centralRows = $this->rows($variables?->get(self::CLOSURES_FIELD));
    }

    /**
     * Whether the resolved per-location opt-out suppresses the broadcast. Any value
     * other than the explicit `suppress` (absent, null, or `honor`) honors it, so
     * existing locations with no value keep the safe default: the closure applies.
     */
    private function suppresses(mixed $optOut): bool
    {
        // Only the explicit suppress value opts out
        return $this->scalarValue($optOut) === self::OPT_OUT_SUPPRESS;
    }

    /**
     * Build the calendar windows a location has addressed with its own in-person
     * rules — a central closure yields to any date inside one of them.
     *
     * @param  array<int, mixed>  $localRows
     * @return array<int, array{from: Carbon, through: Carbon|null}>
     */
    private function localCoverage(array $localRows): array
    {
        // Accumulate one window per addressed local range
        $windows = [];

        // Walk each of the location's own special-hours entries
        foreach ($localRows as $entry) {
            // Branch on the entry's set type
            $setType = $this->setType($entry);

            if ($setType === 'holiday') {
                // Holiday: each nested rule carries its own [date, through] window
                foreach ($this->rows($this->rowField($entry, 'rules')) as $rule) {
                    // A telemedicine rule addresses no physical premises — it can't
                    // suppress a physical closure
                    if ($this->serviceMode($rule) === 'telemedicine') {
                        // Skip it for coverage
                        continue;
                    }

                    // Resolve the single-date window (through defaults to the start)
                    $from = $this->dateValue($this->rowField($rule, 'date'));

                    // A rule with no date covers nothing
                    if ($from === null) {
                        // Skip it
                        continue;
                    }

                    // Record the window (through defaults to the start date)
                    $this->addWindow($windows, $from, $this->dateValue($this->rowField($rule, 'through')) ?? $from);
                }

                // Move to the next entry
                continue;
            }

            if ($setType === 'temporary_change') {
                // A temporary change with no in-person rule leaves the physical
                // schedule untouched, so it can't suppress a physical closure
                if (! $this->hasInPersonRule($this->rowField($entry, 'rules'))) {
                    // Skip it for coverage
                    continue;
                }

                // The window sits on the entry itself
                $from = $this->dateValue($this->rowField($entry, 'valid_from'));

                // A change with no start date covers nothing
                if ($from === null) {
                    // Skip it
                    continue;
                }

                // Record the window; a blank end is an ongoing (open-ended) change
                $this->addWindow($windows, $from, $this->dateValue($this->rowField($entry, 'valid_through')));
            }
        }

        return $windows;
    }

    /**
     * Append a parsed coverage window, skipping an unparseable start date.
     *
     * @param  array<int, array{from: Carbon, through: Carbon|null}>  $windows
     */
    private function addWindow(array &$windows, string $from, ?string $through): void
    {
        // Parse the window bounds, guarding an unparseable date
        try {
            // Normalize both ends to the start of the day for date-only comparison
            $windows[] = [
                'from' => Carbon::parse($from)->startOfDay(),
                'through' => $through !== null ? Carbon::parse($through)->startOfDay() : null,
            ];
        } catch (\Exception) {
            // An unparseable window covers nothing rather than throwing
        }
    }

    /**
     * Whether a replicator carries at least one in-person (non-telemedicine) rule.
     */
    private function hasInPersonRule(mixed $rules): bool
    {
        // A non-telemedicine rule (in-person, or an unset default) counts
        return \array_any($this->rows($rules), fn ($rule): bool => $this->serviceMode($rule) !== 'telemedicine');
    }

    /**
     * Reduce a central closure entry to only the date runs the location has not
     * covered, or null when it is fully covered (or not a holiday-shaped entry).
     *
     * @param  array<int, array{from: Carbon, through: Carbon|null}>  $coverage
     * @return array<string, mixed>|null
     */
    private function centralRowUncovered(mixed $entry, array $coverage): ?array
    {
        // Only holiday-shaped central entries are broadcast (org-wide closures)
        if ($this->setType($entry) !== 'holiday') {
            // Nothing to contribute
            return null;
        }

        // Accumulate the surviving rules
        $rules = [];

        // Walk each central rule
        foreach ($this->rows($this->rowField($entry, 'rules')) as $rule) {
            // Resolve the rule's [date, through] window
            $from = $this->dateValue($this->rowField($rule, 'date'));

            // A rule with no date contributes nothing
            if ($from === null) {
                // Skip it
                continue;
            }

            // Through defaults to the start date (single day)
            $through = $this->dateValue($this->rowField($rule, 'through')) ?? $from;

            // Emit one synthetic rule per contiguous uncovered run
            foreach ($this->survivingRuns($from, $through, $coverage) as [$runStart, $runEnd]) {
                // Rebuild the rule scoped to the surviving run, preserving its shape
                $rules[] = [
                    'type' => 'rule',
                    'date' => $runStart,
                    'through' => $runStart === $runEnd ? null : $runEnd,
                    'service_mode' => $this->scalarValue($this->rowField($rule, 'service_mode')) ?? 'in_person',
                    'status' => $this->scalarValue($this->rowField($rule, 'status')) ?? 'closed',
                    'time_span' => $this->rowField($rule, 'time_span'),
                ];
            }
        }

        // Fully covered → contribute nothing
        if ($rules === []) {
            // Drop the central closure for this location
            return null;
        }

        // Rebuild the holiday entry with only its surviving rules
        return [
            'type' => 'holiday',
            'title' => $this->scalarValue($this->rowField($entry, 'title')),
            'description' => $this->rowField($entry, 'description'),
            'rules' => $rules,
        ];
    }

    /**
     * The contiguous `[start, end]` date runs within `[from, through]` that no
     * local coverage window intersects (both bounds inclusive, `YYYY-MM-DD`).
     *
     * @param  array<int, array{from: Carbon, through: Carbon|null}>  $coverage
     * @return array<int, array{0: string, 1: string}>
     */
    private function survivingRuns(string $from, string $through, array $coverage): array
    {
        // Parse the range bounds, guarding an unparseable date
        try {
            // Walk from the start of the first day
            $cursor = Carbon::parse($from)->startOfDay();
            $end = Carbon::parse($through)->startOfDay();
        } catch (\Exception) {
            // An unparseable range yields nothing
            return [];
        }

        // An inverted range (bad data) yields nothing
        if ($end->lt($cursor)) {
            // Nothing to walk
            return [];
        }

        // Accumulate contiguous uncovered runs as [start, end] date pairs
        $runs = [];

        // Days of the current open run (contiguous uncovered dates)
        $current = [];

        // Bound the walk against absurd ranges
        $guard = 0;

        // Walk each day in the range
        while ($cursor->lte($end) && $guard++ < self::RANGE_DAY_CAP) {
            if ($this->covered($cursor, $coverage)) {
                // A covered day closes any open run
                if ($current !== []) {
                    // Emit [first, last] and reset
                    $runs[] = [$current[0], $current[\count($current) - 1]];
                    $current = [];
                }
            } else {
                // An uncovered day extends the current run
                $current[] = $cursor->format('Y-m-d');
            }

            // Advance to the next day
            $cursor->addDay();
        }

        // Emit a trailing open run
        if ($current !== []) {
            // Close out the final run
            $runs[] = [$current[0], $current[\count($current) - 1]];
        }

        return $runs;
    }

    /**
     * Whether a calendar day falls inside any local coverage window.
     *
     * @param  array<int, array{from: Carbon, through: Carbon|null}>  $coverage
     */
    private function covered(Carbon $day, array $coverage): bool
    {
        // Inside a window when at/after its start and at/before its end (open-ended when no end)
        return \array_any(
            $coverage,
            fn (array $window): bool => $day->gte($window['from']) && ($window['through'] === null || $day->lte($window['through'])),
        );
    }
}
