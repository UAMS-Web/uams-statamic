<?php

declare(strict_types=1);

namespace App\Tags;

use App\Modifiers\ApStyleTime;
use App\Support\Locations\ResolvesSpecialHoursValues;
use Statamic\Fields\LabeledValue;
use Statamic\Fields\Value;
use Statamic\Tags\Parameters;
use Statamic\Tags\Tags;

/**
 * Antlers tag: render a location's recurring weekly hours from `regular_hours`
 * as visitor-facing copy (UAMS-Web/uams-statamic-archive#2390).
 *
 * The JSON-LD counterpart ({@see SchemaLocationHours}) already emits these same
 * rules as `openingHoursSpecification`, so a search engine could read a
 * location's hours while a person reading the page could not. This renders the
 * stored values for that reader and changes nothing about what the structured
 * data emits.
 *
 * Rule semantics, deliberately mirroring {@see SchemaLocationHours} so the two
 * surfaces cannot disagree about the same stored value:
 *   - `open`     → the rule's time spans, AP-style, joined by commas.
 *   - `open_24h` → "Open 24 hours".
 *   - `closed`   → "Closed". Note this differs from the JSON-LD, which emits no
 *     spec at all for a regular closed rule because absence implies closed.
 *     A reader needs the word; a parser does not.
 *   - `telemedicine` service-mode rules are rendered as their own labeled group
 *     rather than skipped (UAMS-Web/uams-statamic-archive#2405). The JSON-LD still skips them, because
 *     `openingHoursSpecification` describes the physical premises and a remote
 *     visit is not a premises opening; a reader, by contrast, is the one person
 *     the stored value was entered for.
 *
 * Only an explicit `telemedicine` counts as remote. An absent or unrecognized
 * `service_mode` describes the premises, matching the blueprint default and the
 * reading {@see SchemaLocationHours} already applies.
 *
 * The mode labels appear only when a location actually has telemedicine hours.
 * The blueprint asks for a telemedicine rule "only when telemedicine hours
 * differ from the in-person hours", so a location with premises hours alone has
 * no distinction to draw and labeling it would invent one -- its output is
 * unchanged by this work, byte for byte.
 *
 * Days are expanded per rule and then regrouped, rather than rendered one row
 * per rule. An editor may express the same week as one Weekday rule or as five
 * Custom rules, and a reader should not be able to tell which was used. Runs of
 * consecutive days sharing identical copy collapse to a single row
 * ("Monday - Friday"); a gap in the week breaks the run.
 *
 * Where two rules name the same day, the LATER rule wins. That matches the
 * editor affordance the blueprint describes -- add a Daily preset, then a
 * Custom rule for the day that differs -- and it keeps one row per day, which
 * merging spans across rules would not.
 *
 * Output is empty when no day resolves, so the section and its heading are
 * absent rather than rendering an empty shell.
 *
 * Example:
 *   {{ location_regular_hours :regular_hours="regular_hours" }}
 *
 * @property Parameters $params
 */
class LocationRegularHours extends Tags
{
    use ResolvesSpecialHoursValues;

    /**
     * Registered Antlers handle `{{ location_regular_hours }}`.
     *
     * @var string
     */
    protected static $handle = 'location_regular_hours';

    /**
     * Week order used for both expansion and grouping.
     *
     * Grouping walks this list in order, so "consecutive" means consecutive
     * here: a week that runs Monday to Sunday groups Mon-Fri, and Sunday never
     * joins a run that starts on Monday.
     *
     * @var list<string>
     */
    private const array WEEK = [
        'monday',
        'tuesday',
        'wednesday',
        'thursday',
        'friday',
        'saturday',
        'sunday',
    ];

    /**
     * Display labels for each day key.
     *
     * @var array<string, string>
     */
    private const array DAY_LABELS = [
        'monday' => 'Monday',
        'tuesday' => 'Tuesday',
        'wednesday' => 'Wednesday',
        'thursday' => 'Thursday',
        'friday' => 'Friday',
        'saturday' => 'Saturday',
        'sunday' => 'Sunday',
    ];

    /**
     * Render the hours section, or an empty string when nothing resolves.
     */
    public function index(): string
    {
        // Read the bound rules once, since both modes expand the same stored set
        $rules = $this->params->get('regular_hours');

        // Collapse the premises rules into consecutive-day runs
        $inPerson = $this->groupConsecutive($this->scheduleByDay($rules, self::MODE_IN_PERSON));

        // Collapse the telemedicine rules the same way
        $telemedicine = $this->groupConsecutive($this->scheduleByDay($rules, self::MODE_TELEMEDICINE));

        // Emit nothing when neither mode carries hours
        if ($inPerson === [] && $telemedicine === []) {
            // Return an empty string so the section is omitted entirely
            return '';
        }

        // Label the groups only when telemedicine hours exist to contrast with
        $labeled = $telemedicine !== [];

        // Compose the section body from whichever modes resolved
        $body = $this->modeGroup($inPerson, $labeled ? self::MODE_IN_PERSON : null)
            .$this->modeGroup($telemedicine, $labeled ? self::MODE_TELEMEDICINE : null);

        // Wrap the groups in the hours section
        return '<section class="location-hours" aria-label="Hours">'
            .'<h2>Hours</h2>'
            .$body
            .'</section>';
    }

    /**
     * Render one service mode's grouped rows, optionally under a mode label.
     *
     * @param  list<array{days: list<string>, text: string}>  $groups  Runs of
     *                                                                 consecutive days for this mode
     * @param  string|null  $mode  Service mode to label the group with, or null
     *                             to render the bare list
     * @return string The group markup, or an empty string when it has no rows
     */
    private function modeGroup(array $groups, ?string $mode): string
    {
        // A mode with no resolved days contributes nothing
        if ($groups === []) {
            // Return an empty string so no empty group is emitted
            return '';
        }

        // Accumulate the day-range rows for this mode
        $rows = '';

        // Walk each grouped run of days
        foreach ($groups as $group) {
            // Compose the day-range term and its hours description
            $rows .= '<dt class="location-hours-days">'.$this->rangeLabel($group['days']).'</dt>'
                .'<dd class="location-hours-times">'.e($group['text']).'</dd>';
        }

        // Wrap the rows in a definition list
        $list = '<dl class="location-hours-list">'.$rows.'</dl>';

        // An unlabeled group is the bare list, which is what a location with only
        // in-person hours has always rendered
        if ($mode === null) {
            // Return the list on its own
            return $list;
        }

        // Wrap a labeled group so a reader can tell the two modes apart
        return '<div class="location-hours-group location-hours-group-'.str_replace('_', '-', $mode).'">'
            .'<h3 class="location-hours-mode">'.e($this->serviceModeLabel($mode)).'</h3>'
            .$list
            .'</div>';
    }

    /**
     * Expand every rule for one service mode into a day-keyed map of display copy.
     *
     * Each mode is expanded independently, so a day carrying both in-person and
     * telemedicine hours appears in both groups rather than one overwriting the
     * other.
     *
     * @param  mixed  $rules  The bound `regular_hours` replicator value
     * @param  string  $mode  Service mode to expand, `in_person` or `telemedicine`
     * @return array<string, string> Day key => the copy for that day
     */
    private function scheduleByDay(mixed $rules, string $mode): array
    {
        // Accumulate the copy for each named day
        $schedule = [];

        // Walk each weekly rule in editor order, so a later rule overwrites
        foreach ($this->rows($rules) as $rule) {
            // Skip a rule belonging to the other service mode
            if ($this->ruleMode($rule) !== $mode) {
                // Move to the next rule
                continue;
            }

            // Resolve the copy this rule produces
            $text = $this->ruleText($rule);

            // A rule that resolves to nothing contributes nothing
            if ($text === null) {
                // Move to the next rule
                continue;
            }

            // Apply the copy to each day the rule names
            foreach ($this->dayKeys($this->rowField($rule, 'days')) as $day) {
                // Later rules win for a day an earlier rule already set
                $schedule[$day] = $text;
            }
        }

        return $schedule;
    }

    /**
     * The display copy for a single rule, or null when it resolves to nothing.
     */
    private function ruleText(mixed $rule): ?string
    {
        // Resolve the rule status (open / open_24h / closed)
        $status = $this->status($rule);

        // A closed day is stated rather than implied by absence
        if ($status === 'closed') {
            // Return the closed copy
            return 'Closed';
        }

        // Round-the-clock opening needs no times
        if ($status === 'open_24h') {
            // Return the 24-hour copy
            return 'Open 24 hours';
        }

        // Open during specific hours: format each span
        $spans = [];

        // Walk each time span row
        foreach ($this->rows($this->rowField($rule, 'time_span')) as $span) {
            // Resolve the opening and closing times
            $opens = $this->scalarValue($this->rowField($span, 'open'));
            $closes = $this->scalarValue($this->rowField($span, 'close'));

            // A span needs an opening time
            if ($opens === null) {
                // Move to the next span
                continue;
            }

            // A span needs a closing time
            if ($closes === null) {
                // Move to the next span
                continue;
            }

            // Format both ends in the house AP style
            $spans[] = $this->formatTime($opens).' to '.$this->formatTime($closes);
        }

        // An open rule with no usable span says nothing
        if ($spans === []) {
            // Contribute nothing
            return null;
        }

        // Join multiple spans (for example a midday closure) into one line
        return implode(', ', $spans);
    }

    /**
     * Format an `HH:MM` time using the site's AP-style time convention.
     */
    private function formatTime(string $time): string
    {
        // Reuse the shared modifier so this matches times rendered elsewhere
        return (new ApStyleTime)->index($time, [], []);
    }

    /**
     * Collapse the day-keyed schedule into runs of consecutive days.
     *
     * Consecutiveness is structural rather than checked: the week is walked in
     * order and a day with no hours flushes the run in progress, so a run can
     * only ever contain days that are adjacent in `self::WEEK`.
     *
     * @param  array<string, string>  $schedule
     * @return list<array{days: list<string>, text: string}>
     */
    private function groupConsecutive(array $schedule): array
    {
        // Accumulate the completed runs
        $groups = [];

        // The run currently being extended, if any
        $current = null;

        // Walk the week in order so a run is genuinely consecutive
        foreach (self::WEEK as $day) {
            // A day with no hours ends any run in progress
            if (! isset($schedule[$day])) {
                // Close the open run before the gap
                if ($current !== null) {
                    // Bank the completed run
                    $groups[] = $current;

                    // Clear the run in progress
                    $current = null;
                }

                // Move to the next day
                continue;
            }

            // Read the copy for this day
            $text = $schedule[$day];

            // Extend the run when this day continues it with identical copy
            if ($current !== null && $current['text'] === $text) {
                // Append the day to the run
                $current['days'][] = $day;

                // Move to the next day
                continue;
            }

            // Bank a run that differs from this day before starting a new one
            if ($current !== null) {
                // Close the previous run
                $groups[] = $current;
            }

            // Start a new run at this day
            $current = ['days' => [$day], 'text' => $text];
        }

        // Bank a run still open at the end of the week
        if ($current !== null) {
            // Close the final run
            $groups[] = $current;
        }

        return $groups;
    }

    /**
     * Render a run of days as an escaped label, collapsing three or more into a
     * range.
     *
     * @param  list<string>  $days
     */
    private function rangeLabel(array $days): string
    {
        // A single day needs no range
        if (\count($days) === 1) {
            // Return the one escaped label
            return e(self::DAY_LABELS[$days[0]]);
        }

        // Two days read better listed than hyphenated ("Saturday and Sunday")
        if (\count($days) === 2) {
            // Return both labels joined by a conjunction
            return e(self::DAY_LABELS[$days[0]]).' and '.e(self::DAY_LABELS[$days[1]]);
        }

        // Three or more collapse to first and last, joined by an en dash
        return e(self::DAY_LABELS[$days[0]]).' &ndash; '.e(self::DAY_LABELS[$days[\count($days) - 1]]);
    }

    /**
     * Resolve a rule's selected day keys.
     *
     * @return list<string> Known day keys, in selection order; a repeated day repeats
     */
    private function dayKeys(mixed $days): array
    {
        // Unwrap a Statamic field value
        if ($days instanceof Value) {
            // Read the inner value for coercion
            $days = $days->value();
        }

        // The checkboxes field augments to a list of option rows
        if (! \is_array($days)) {
            // Return no days when the shape is unexpected
            return [];
        }

        // Collect the recognized keys
        $keys = [];

        // Walk each selected option
        foreach ($days as $day) {
            // Resolve the option key (day name)
            $key = $this->optionKey($day);

            // Keep a known day. A repeated day is kept again: the caller writes the
            // same copy to it twice, which leaves the schedule unchanged.
            //
            // The `&&` cannot be killed, for a structural reason one function
            // along. Loosening it admits an unrecognized key or a null into $keys,
            // which reaches groupConsecutive() only as an extra $schedule key, and
            // that function walks self::WEEK rather than the schedule, so a key
            // outside the week is never read. No assertion on rendered output can
            // distinguish the mutant.
            // @pest-mutate-ignore: BooleanAndToBooleanOr
            if ($key !== null && isset(self::DAY_LABELS[$key])) {
                // Append the day key
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * Resolve the option key from an augmented checkbox option.
     *
     * Every shape funnels to one normalization rather than repeating it per
     * branch: unwrap the augmented wrappers, reduce an option row to its key,
     * then trim once and reject what is not a usable string.
     */
    private function optionKey(mixed $option): ?string
    {
        // Unwrap a Statamic field value
        if ($option instanceof Value) {
            // Read the inner value for coercion
            $option = $option->value();
        }

        // Unwrap an augmented labeled option (not a Value subclass)
        if ($option instanceof LabeledValue) {
            // Use the option value as the key
            $option = $option->value();
        }

        // An augmented option row exposes the key under `key`, then `value`
        if (\is_array($option)) {
            // Prefer the option key, then the stored value
            $option = $option['key'] ?? $option['value'] ?? null;
        }

        // Anything that is not a string has no key
        if (! \is_string($option)) {
            // Report no key
            return null;
        }

        // Normalize the key
        $key = trim($option);

        // Return the key when non-empty.
        //
        // Returning '' rather than null here cannot be observed: the only
        // caller drops any key that is not a DAY_LABELS key, and '' is not
        // one, so both values take the same branch there.
        // @pest-mutate-ignore: EmptyStringToNotEmpty
        return $key === '' ? null : $key;
    }
}
