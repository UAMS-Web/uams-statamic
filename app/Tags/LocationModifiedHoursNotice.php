<?php

declare(strict_types=1);

namespace App\Tags;

use App\Services\Locations\SystemLocationClosures;
use App\Support\Locations\ResolvesSpecialHoursValues;
use Illuminate\Support\Carbon;
use Statamic\Tags\Context;
use Statamic\Tags\Parameters;
use Statamic\Tags\Tags;

/**
 * Antlers tag: render a visitor-facing "Modified Hours" notice from a location's
 * `special_hours` replicator (UAMS-Web/uams-statamic-archive#1328).
 *
 * The JSON-LD counterpart ({@see SchemaLocationHours}) emits
 * `specialOpeningHoursSpecification` for search engines; this renders the same
 * holiday / temporary-change entries as human-readable copy on the location
 * profile (for example "Thanksgiving — Closed Nov 26, 2026"). It shares the
 * auto-expiry semantics: an entry whose date window is entirely in the past is
 * skipped, and an ongoing `temporary_change` (blank `valid_through`) never
 * expires. Output is empty when no active or upcoming entry resolves.
 *
 * Precedence: this notice covers *modified hours* only. Whole-location permanent
 * closures (the `Closing` tab) and system-wide banners (the `Alert` tab) are
 * rendered by their own regions and are intentionally NOT duplicated here. The
 * location's own `special_hours` is first merged with the org-wide holiday
 * closures broadcast from the Find-a-Doc Settings global (UAMS-Web/uams-statamic-archive#1327,
 * {@see SystemLocationClosures}), so a system-wide closure surfaces in this notice
 * for every in-scope location while the location's own rules still win per date.
 *
 * Example:
 *   {{ location_modified_hours_notice :special_hours="special_hours" }}
 *
 * @property Parameters $params
 */
class LocationModifiedHoursNotice extends Tags
{
    use ResolvesSpecialHoursValues;

    /**
     * Registered Antlers handle `{{ location_modified_hours_notice }}`.
     *
     * @var string
     */
    protected static $handle = 'location_modified_hours_notice';

    /**
     * Render the notice HTML, or an empty string when nothing is active.
     */
    public function index(): string
    {
        // Resolve the active / upcoming entries (own hours merged with org-wide closures)
        $notices = $this->activeNotices($this->effectiveSpecialHours());

        // Emit nothing when no entry is active or upcoming
        if ($notices === []) {
            // Return an empty string so the section is omitted
            return '';
        }

        // Build one list item per active entry
        $items = '';

        // Walk each resolved notice
        foreach ($notices as $notice) {
            // Label a remote-service exception, and only that one. An in-person
            // row stays exactly as it rendered before, because labeling the
            // ordinary case invents a distinction the stored data does not make
            // -- the same reading UAMS-Web/uams-statamic-archive#2405 applied to the hours section.
            $mode = $notice['mode'] === self::MODE_TELEMEDICINE
                ? ' <span class="location-hours-notice-mode">'.e($this->serviceModeLabel(self::MODE_TELEMEDICINE)).'</span>'
                : '';

            // Compose the label, dates, and status summary
            $items .= '<li class="location-hours-notice-item">'
                .'<strong class="location-hours-notice-title">'.e($notice['title']).'</strong>'
                .$mode
                .' <span class="location-hours-notice-status">'.e($notice['status']).'</span>'
                .' <span class="location-hours-notice-dates">'.e($notice['dates']).'</span>'
                .'</li>';
        }

        // Wrap the items in the notice section
        return '<section class="location-hours-notice" aria-label="Modified hours">'
            .'<h2>Modified Hours</h2>'
            .'<ul class="location-hours-notice-list">'.$items.'</ul>'
            .'</section>';
    }

    /**
     * The location's `special_hours` merged with the in-scope org-wide holiday
     * closures (UAMS-Web/uams-statamic-archive#1327), so the notice surfaces both the location's own overrides
     * and the system-wide broadcast (local rules win per date).
     *
     * @return array<int, mixed> Combined special-hours rows
     */
    private function effectiveSpecialHours(): array
    {
        // Read the per-location opt-out from the render cascade
        /** @var Context $context */
        $context = $this->context;

        // Merge the location's own special hours with the broadcast closures
        return app(SystemLocationClosures::class)->effectiveSpecialHours(
            $this->params->get('special_hours'),
            $context->get('system_closures')
        );
    }

    /**
     * Resolve the active / upcoming special-hours entries as display rows.
     *
     * @return list<array{title: string, status: string, dates: string, mode: string}>
     */
    private function activeNotices(mixed $special): array
    {
        // Accumulate one display row per active entry
        $notices = [];

        // Walk each special-hours entry (holiday or temporary change)
        foreach ($this->rows($special) as $entry) {
            // Branch on the entry's set type
            $setType = $this->setType($entry);

            // Resolve the editor-supplied title (falls back to a generic label)
            $title = $this->scalarValue($this->rowField($entry, 'title')) ?? 'Modified hours';

            if ($setType === 'holiday') {
                // Holiday: one notice per active nested rule, mirroring the
                // per-rule JSON-LD. Disjoint dates (e.g. Jan 1 and Dec 25) stay
                // separate rather than folding into a single continuous window
                // that would over-claim months of modified hours.
                foreach ($this->rows($this->rowField($entry, 'rules')) as $rule) {
                    // Resolve the rule's single-date window (through defaults to date)
                    $from = $this->dateValue($this->rowField($rule, 'date'));
                    $through = $this->dateValue($this->rowField($rule, 'through')) ?? $from;
                    // Skip a rule with no date, or one whose window is entirely past
                    if ($from === null) {
                        // Move to the next rule
                        continue;
                    }

                    if ($this->isEntirelyPast($through)) {
                        // Move to the next rule
                        continue;
                    }

                    // Append this rule's notice row, carrying the mode it
                    // describes -- a telemedicine rule says nothing about
                    // whether the premises are open
                    $notices[] = [
                        'title' => $title,
                        'status' => $this->status($rule) === 'closed' ? 'Closed' : 'Modified hours',
                        'dates' => $this->formatWindow($from, $through),
                        'mode' => $this->ruleMode($rule),
                    ];
                }

                // Move to the next entry
                continue;
            }

            if ($setType === 'temporary_change') {
                // Temporary change: one window on the entry itself
                $from = $this->dateValue($this->rowField($entry, 'valid_from'));
                $through = $this->dateValue($this->rowField($entry, 'valid_through'));
                // A change needs a start date, and must not be fully elapsed
                if ($from === null) {
                    // Move to the next entry
                    continue;
                }

                if ($through !== null && $this->isEntirelyPast($through)) {
                    // Move to the next entry
                    continue;
                }

                // Read the entry's rules once; both the mode and the closure
                // verdict are derived from them
                $rules = $this->rowField($entry, 'rules');

                // An entry carrying only telemedicine rules describes the remote
                // service; anything with a premises rule describes the premises
                $mode = $this->entryMode($rules);

                // Append the temporary-change notice row
                $notices[] = [
                    'title' => $title,
                    'status' => $this->allRulesClosed($rules, $mode) ? 'Closed' : 'Modified hours',
                    'dates' => $this->formatWindow($from, $through),
                    'mode' => $mode,
                ];
            }
        }

        return $notices;
    }

    /**
     * Whether every rule describing the given mode carries a `closed` status.
     *
     * Scoped to one mode because the unscoped form was wrong in BOTH directions
     * (UAMS-Web/uams-statamic-archive#2466). An open telemedicine rule alongside closed premises rules made a
     * real closure read as "Modified hours"; and a closed telemedicine-only
     * entry made premises that were open all week read as "Closed", which is the
     * direction that sends someone away from a clinic that would have seen them.
     *
     * @param  mixed  $rules  The entry's rules replicator
     * @param  string  $mode  Service mode the notice describes
     */
    private function allRulesClosed(mixed $rules, string $mode): bool
    {
        // Keep only the rules describing the mode this notice is about
        $rows = array_values(array_filter(
            $this->rows($rules),
            fn ($rule): bool => $this->ruleMode($rule) === $mode
        ));

        // An empty rule set is not treated as a full closure
        if ($rows === []) {
            // Default to "modified hours"
            return false;
        }

        // A single non-closed rule means the change is not a full closure
        return \array_all($rows, fn ($rule): bool => $this->status($rule) === 'closed');
    }

    /**
     * Which service mode an entry as a whole describes.
     *
     * An entry is remote only when EVERY rule it carries is a telemedicine rule.
     * One premises rule makes the entry a premises exception, because that is
     * the part a reader standing outside the building needs to know.
     *
     * An entry with no rules at all is a premises exception: that is what it has
     * always rendered as, and a mode label on a rule-less entry would assert
     * something nothing in the data says.
     *
     * @param  mixed  $rules  The entry's rules replicator
     */
    private function entryMode(mixed $rules): string
    {
        // Collect the rows once
        $rows = $this->rows($rules);

        // A rule-less entry stays a premises exception
        if ($rows === []) {
            // Report the premises mode
            return self::MODE_IN_PERSON;
        }

        // Remote only when nothing in the entry describes the premises
        return \array_all($rows, fn ($rule): bool => $this->ruleMode($rule) === self::MODE_TELEMEDICINE)
            ? self::MODE_TELEMEDICINE
            : self::MODE_IN_PERSON;
    }

    /**
     * Format a window as human copy: a single date ("Nov 26, 2026"), a range
     * ("Dec 24 – Dec 26, 2026"), or open-ended ("from Jul 1, 2026") when the
     * window has no end (an ongoing temporary change).
     */
    private function formatWindow(string $from, ?string $through): string
    {
        // Parse both ends, guarding an unparseable value
        try {
            // Resolve Carbon instances for formatting
            $start = Carbon::parse($from);
            $end = $through !== null ? Carbon::parse($through) : null;
        } catch (\Exception) {
            // Fall back to the raw dates when parsing fails
            if ($through === null || $from === $through) {
                // A single or open-ended date
                return $from;
            }

            // The raw range
            return sprintf('%s – %s', $from, $through);
        }

        // An ongoing window (no end) is open-ended from the start date
        if (! $end instanceof Carbon) {
            // Return the open-ended copy
            return sprintf('from %s', $start->format('M j, Y'));
        }

        // A single-day window renders one date
        if ($start->isSameDay($end)) {
            // Return the single formatted date
            return $start->format('M j, Y');
        }

        // A same-year range collapses the repeated year
        if ($start->year === $end->year) {
            // Return the abbreviated range
            return sprintf('%s – %s', $start->format('M j'), $end->format('M j, Y'));
        }

        // A cross-year range spells out both years
        return sprintf('%s – %s', $start->format('M j, Y'), $end->format('M j, Y'));
    }
}
