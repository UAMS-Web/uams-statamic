<?php

declare(strict_types=1);

namespace App\Tags;

use App\Services\Locations\SystemLocationClosures;
use App\Support\Locations\ResolvesSpecialHoursValues;
use Statamic\Fields\LabeledValue;
use Statamic\Fields\Value;
use Statamic\Tags\Context;
use Statamic\Tags\Parameters;
use Statamic\Tags\Tags;

/**
 * Antlers tag: emit a location's hours rules as JSON-LD
 * `OpeningHoursSpecification` nodes.
 *
 * Two outputs off the one tag (`output` parameter):
 *
 *   - `regular` (default): the weekly `regular_hours` rules → the
 *     `openingHoursSpecification` property. Each rule carries a `dayOfWeek`
 *     list and no validity window.
 *   - `special`: the `special_hours` overrides → the
 *     `specialOpeningHoursSpecification` property. A `holiday` set's per-rule
 *     `date`/`through` become the `validFrom`/`validThrough` of a date-specific
 *     spec (no `dayOfWeek`); a `temporary_change` set's `valid_from`/`valid_through`
 *     window applies to each of its weekly rules (with `dayOfWeek`).
 *
 * Rule semantics (shared by both):
 *   - `open`   → one spec per time span, `opens`/`closes` from the span.
 *   - `open_24h` → a single `00:00`–`23:59` spec.
 *   - `closed` → for regular hours, no spec at all (a closed weekday is implied
 *     by its absence — "no open span"); for special hours, an explicit
 *     `00:00`–`00:00` spec so the closure overrides the regular schedule.
 *   - `telemedicine` service-mode rules are skipped: `openingHoursSpecification`
 *     describes the physical premises' hours only.
 *
 * Times come from `time` fields (`HH:MM`); dates from `date` fields, normalized
 * to `YYYY-MM-DD`. Output is empty when nothing resolves; a single spec is
 * flattened by the engine to the bare object.
 *
 * For `special` output the location's own `special_hours` is first merged with the
 * org-wide holiday closures broadcast from the Find-a-Doc Settings global (UAMS-Web/uams-statamic-archive#1327,
 * {@see SystemLocationClosures}), so a system-wide closure fans out to every
 * in-scope location while the location's own rules still win per date.
 *
 * Example:
 *   {{ schema_location_hours :regular_hours="regular_hours" }}
 *   {{ schema_location_hours output="special" :special_hours="special_hours" }}
 *
 * @property Parameters $params
 */
class SchemaLocationHours extends Tags
{
    use ResolvesSpecialHoursValues;

    /**
     * Registered Antlers handle `{{ schema_location_hours }}`.
     *
     * @var string
     */
    protected static $handle = 'schema_location_hours';

    /**
     * `@type` of every emitted node. A fixed URI: `App\Services\SchemaTemplates\SchemaObjectFragmentBuilder::
     * normalizedType('OpeningHoursSpecification')` returns exactly this and is null
     * only for a blank name.
     */
    private const string SPEC_TYPE = 'https://schema.org/OpeningHoursSpecification';

    /**
     * Map of day-of-week keys to their schema.org DayOfWeek enumeration URIs.
     *
     * @var array<string, string>
     */
    private const array DAY_TERMS = [
        'monday' => 'https://schema.org/Monday',
        'tuesday' => 'https://schema.org/Tuesday',
        'wednesday' => 'https://schema.org/Wednesday',
        'thursday' => 'https://schema.org/Thursday',
        'friday' => 'https://schema.org/Friday',
        'saturday' => 'https://schema.org/Saturday',
        'sunday' => 'https://schema.org/Sunday',
    ];

    /**
     * Build the JSON array of OpeningHoursSpecification nodes.
     *
     * @return string JSON array, or empty string when nothing resolves
     */
    public function index(): string
    {
        // The OpeningHoursSpecification type URI every node carries
        $specType = self::SPEC_TYPE;

        // Dispatch on the requested output; special hours merge in the org-wide closures
        $specs = $this->params->get('output') === 'special'
            ? $this->specialSpecs($this->effectiveSpecialHours(), $specType)
            : $this->regularSpecs($this->params->get('regular_hours'), $specType);

        // Emit nothing when no spec resolved
        if ($specs === []) {
            // Return an empty string to omit the property
            return '';
        }

        // Serialize the spec array for JSON-LD embedding
        $json = json_encode($specs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // Return the JSON array, or an empty string when encoding fails
        return $json === false ? '' : $json;
    }

    /**
     * The location's `special_hours` merged with the in-scope org-wide holiday
     * closures (UAMS-Web/uams-statamic-archive#1327), so the JSON-LD emits both the location's own overrides and
     * the system-wide broadcast (local rules win per date).
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
     * Build the weekly openingHoursSpecification specs from `regular_hours`.
     *
     * @return array<int, array<string, mixed>>
     */
    private function regularSpecs(mixed $rules, string $specType): array
    {
        // Accumulate one or more specs per rule
        $specs = [];

        // Walk each weekly rule
        foreach ($this->rows($rules) as $rule) {
            // Skip telemedicine rules — physical premises hours only
            if ($this->serviceMode($rule) === 'telemedicine') {
                // Move to the next rule
                continue;
            }

            // Resolve the rule's day-of-week terms
            $days = $this->dayTerms($this->rowField($rule, 'days'));

            // A weekly rule with no days contributes nothing
            if ($days === []) {
                // Move to the next rule
                continue;
            }

            // Append this rule's specs (regular closed rules emit nothing)
            foreach ($this->ruleSpecs($rule, $specType, $days, null, null, false) as $spec) {
                // Collect the built spec
                $specs[] = $spec;
            }
        }

        return $specs;
    }

    /**
     * Build the specialOpeningHoursSpecification specs from `special_hours`.
     *
     * @return array<int, array<string, mixed>>
     */
    private function specialSpecs(mixed $special, string $specType): array
    {
        // Accumulate specs across every special-hours entry
        $specs = [];

        // Walk each special-hours entry (holiday or temporary change)
        foreach ($this->rows($special) as $entry) {
            // Branch on the entry's set type
            $setType = $this->setType($entry);

            if ($setType === 'holiday') {
                // Holiday: each nested rule carries its own date / through window
                foreach ($this->rows($this->rowField($entry, 'rules')) as $rule) {
                    // Skip telemedicine rules
                    if ($this->serviceMode($rule) === 'telemedicine') {
                        // Move to the next rule
                        continue;
                    }

                    // Resolve the single-date window (through defaults to the start date)
                    $from = $this->dateValue($this->rowField($rule, 'date'));
                    $through = $this->dateValue($this->rowField($rule, 'through')) ?? $from;

                    // A holiday rule with no date contributes nothing
                    if ($from === null) {
                        // Move to the next rule
                        continue;
                    }

                    // Auto-expiry (UAMS-Web/uams-statamic-archive#1328): a holiday rule whose window is entirely
                    // past — its latest date (`through`, defaulting to `date`) is
                    // before today — emits no stale spec. A rule active today or in
                    // the future still emits.
                    if ($this->isEntirelyPast($through)) {
                        // Move to the next rule
                        continue;
                    }

                    // Append this rule's specs (holiday closed emits an explicit closure)
                    foreach ($this->ruleSpecs($rule, $specType, [], $from, $through, true) as $spec) {
                        // Collect the built spec
                        $specs[] = $spec;
                    }
                }

                // Move to the next special-hours entry
                continue;
            }

            if ($setType === 'temporary_change') {
                // Temporary change: one window applies to every weekly rule within it
                $from = $this->dateValue($this->rowField($entry, 'valid_from'));
                $through = $this->dateValue($this->rowField($entry, 'valid_through'));

                // A temporary change with no start date contributes nothing
                if ($from === null) {
                    // Move to the next special-hours entry
                    continue;
                }

                // Auto-expiry (UAMS-Web/uams-statamic-archive#1328): a temporary change whose `valid_through` is
                // before today has fully elapsed and emits nothing. A blank
                // `valid_through` is an ongoing change that never expires.
                if ($through !== null && $this->isEntirelyPast($through)) {
                    // Move to the next special-hours entry
                    continue;
                }

                // Walk each weekly rule inside the change window
                foreach ($this->rows($this->rowField($entry, 'rules')) as $rule) {
                    // Skip telemedicine rules
                    if ($this->serviceMode($rule) === 'telemedicine') {
                        // Move to the next rule
                        continue;
                    }

                    // Resolve the rule's day-of-week terms
                    $days = $this->dayTerms($this->rowField($rule, 'days'));

                    // A weekly rule with no days contributes nothing
                    if ($days === []) {
                        // Move to the next rule
                        continue;
                    }

                    // Append this rule's specs (temporary closed emits an explicit closure)
                    foreach ($this->ruleSpecs($rule, $specType, $days, $from, $through, true) as $spec) {
                        // Collect the built spec
                        $specs[] = $spec;
                    }
                }
            }
        }

        return $specs;
    }

    /**
     * Build the specs for a single hours rule.
     *
     * @param  array<int, string>  $days  Day-of-week terms (empty for date-specific specs)
     * @param  string|null  $validFrom  Window start (YYYY-MM-DD) or null
     * @param  string|null  $validThrough  Window end (YYYY-MM-DD) or null
     * @param  bool  $emitClosed  Whether a `closed` status emits an explicit closure spec
     * @return array<int, array<string, mixed>>
     */
    private function ruleSpecs(
        mixed $rule,
        string $specType,
        array $days,
        ?string $validFrom,
        ?string $validThrough,
        bool $emitClosed
    ): array {
        // Resolve the rule status (open / open_24h / closed)
        $status = $this->status($rule);

        // A closed rule emits an explicit closure only for special hours
        if ($status === 'closed') {
            // Regular closed rules produce no spec (absence implies closed)
            if (! $emitClosed) {
                // Contribute nothing
                return [];
            }

            // Special closed: an explicit all-day closure overrides the regular schedule
            return [$this->spec($specType, $days, '00:00', '00:00', $validFrom, $validThrough)];
        }

        // Open 24 hours: a single full-day span
        if ($status === 'open_24h') {
            // Contribute the round-the-clock spec
            return [$this->spec($specType, $days, '00:00', '23:59', $validFrom, $validThrough)];
        }

        // Open during specific hours: one spec per time span
        $specs = [];

        // Walk each time span row
        foreach ($this->rows($this->rowField($rule, 'time_span')) as $span) {
            // Resolve the opening and closing times
            $opens = $this->scalarValue($this->rowField($span, 'open'));
            $closes = $this->scalarValue($this->rowField($span, 'close'));
            // A span needs both an open and a close time
            if ($opens === null) {
                // Move to the next span
                continue;
            }

            if ($closes === null) {
                // Move to the next span
                continue;
            }

            // Contribute the time-span spec
            $specs[] = $this->spec($specType, $days, $opens, $closes, $validFrom, $validThrough);
        }

        return $specs;
    }

    /**
     * Assemble one OpeningHoursSpecification node with a stable key order.
     *
     * @param  array<int, string>  $days
     * @return array<string, mixed>
     */
    private function spec(
        string $specType,
        array $days,
        string $opens,
        string $closes,
        ?string $validFrom,
        ?string $validThrough
    ): array {
        // Start with the type
        $spec = ['@type' => $specType];

        // Add the day-of-week list when present (single day stays a scalar)
        if ($days !== []) {
            // Carry one term or the full list
            $spec['dayOfWeek'] = \count($days) === 1 ? $days[0] : $days;
        }

        // Add the opening and closing times
        $spec['opens'] = $opens;
        $spec['closes'] = $closes;

        // Add the validity window when present
        if ($validFrom !== null) {
            // Carry the window start
            $spec['validFrom'] = $validFrom;
        }

        if ($validThrough !== null) {
            // Carry the window end
            $spec['validThrough'] = $validThrough;
        }

        return $spec;
    }

    /**
     * Resolve a rule's day-of-week terms as bare schema.org DayOfWeek names.
     *
     * @return array<int, string>
     */
    private function dayTerms(mixed $days): array
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

        // Collect each recognized day term in first-seen order
        $terms = [];

        // Walk each selected option
        foreach ($days as $day) {
            // Resolve the option key (day name)
            $key = $this->optionKey($day);

            // Map the key to its schema.org term, skipping unknown or duplicate days
            // The terms are all strings, so a loose in_array() matches exactly as the
            // strict one does.
            // @pest-mutate-ignore: TrueToFalse
            if ($key !== null && isset(self::DAY_TERMS[$key]) && ! \in_array(self::DAY_TERMS[$key], $terms, true)) {
                // Append the mapped day term
                $terms[] = self::DAY_TERMS[$key];
            }
        }

        return $terms;
    }

    /**
     * Resolve the option key from an augmented checkbox option.
     */
    private function optionKey(mixed $option): ?string
    {
        // Unwrap a Statamic field value
        if ($option instanceof Value) {
            // Read the inner value for coercion
            $option = $option->value();
        }

        // Read the key from an augmented labeled option
        if ($option instanceof LabeledValue) {
            // Use the option value as the key
            $option = $option->value();
        }

        // A plain string is the key itself
        if (\is_string($option)) {
            // Normalize the key; a blank one matches no day term, so it needs no check
            return trim($option);
        }

        // An augmented option row exposes the key under `key`, then `value`
        if (\is_array($option)) {
            // Prefer the option key, then the stored value
            $key = $option['key'] ?? $option['value'] ?? null;

            // Return the trimmed key when it is a string; a blank one matches no day
            return \is_string($key) ? trim($key) : null;
        }

        // Anything else has no key
        return null;
    }
}
