<?php

declare(strict_types=1);

namespace App\Rules;

use App\Fieldtypes\Date;
use App\Support\DateBoundaryEngine;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Closure;
use DateTimeInterface;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Statamic\Fields\Validator;

/**
 * Server-side backstop for the custom Date fieldtype's "Boundaries & Limits"
 * config (UAMS-Web/uams-statamic-archive#1394, follow-up to UAMS-Web/uams-statamic-archive#193).
 *
 * `App\Fieldtypes\Date` + `resources/js/components/fieldtypes/DateFieldtype.vue`
 * enforce earliest/latest boundaries, allowed `days_of_week`, and
 * `date_exceptions` blackouts CLIENT-SIDE only. This rule re-derives the same
 * effective bounds server-side (via {@see DateBoundaryEngine}, the PHP mirror of
 * `resources/js/date-boundary-engine.js`) and rejects an out-of-range value, so a
 * scripted or malicious public-form POST — or any client that bypasses the reka
 * picker — cannot persist a value the picker would have blocked. It is wired
 * through {@see Date::rules()} (keyed to the field's OWN handle,
 * NOT `extraRules()` — see the `FieldtypeValidationRules` trait note on UAMS-Web/uams-statamic-archive#1301) and
 * only attached when a boundary is actually configured, so unconstrained date
 * fields are unaffected.
 *
 * The value reaches this rule already preprocessed by the Statamic fields
 * validator ({@see Validator::validator()} validates
 * `preProcessValidatables()`), so single-mode arrives as a {@see DateTimeInterface}
 * and range-mode as `['start' => …, 'end' => …]`. Everything is DAY granularity:
 * a time-bearing value is compared by its calendar day, matching the client.
 *
 * For a `field`-relative boundary, the referenced sibling field's SUBMITTED value
 * is read from the validation data bag ({@see DataAwareRule}) at the same nesting
 * level as the field under validation, so the boundary tracks the same-request
 * value the client saw.
 *
 * @see DateBoundaryEngine
 */
final class DateBoundary implements DataAwareRule, ValidationRule
{
    /**
     * The full validation data bag (preprocessed, handle-keyed), used to resolve a
     * `field`-relative boundary's referenced sibling value.
     *
     * @var array<string, mixed>
     */
    private array $data = [];

    /**
     * @param  array<string, mixed>  $config  The date field's blueprint config
     */
    public function __construct(
        private readonly array $config
    ) {}

    /**
     * Receive the full validation data bag for {@see DataAwareRule}.
     *
     * @param  array<string, mixed>  $data  Request data including sibling fields
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Reject a submitted date that violates any configured boundary.
     *
     * @param  string  $attribute  Dotted validation attribute path
     * @param  mixed  $value  Preprocessed field value (Carbon, range array, or null)
     * @param  Closure(string): mixed  $fail  Failure callback
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $timezone = $this->displayTimezone();
        $today = $this->today($timezone);

        // Resolve each side independently — earliest and latest may reference
        // different sibling fields.
        $earliest = DateBoundaryEngine::resolveEarliest(
            $this->config,
            $today,
            $this->siblingValue($attribute, $this->config['earliest_date_field'] ?? null)
        );
        $latest = DateBoundaryEngine::resolveLatest(
            $this->config,
            $today,
            $this->siblingValue($attribute, $this->config['latest_date_field'] ?? null)
        );

        $allowedDays = DateBoundaryEngine::normalizeAllowedDays($this->config['days_of_week'] ?? null);
        $exceptions = DateBoundaryEngine::normalizeExceptions($this->config['date_exceptions'] ?? null);

        foreach ($this->endpointDays($value, $timezone) as $day) {
            // An empty or unparseable endpoint is the format/required rule's job,
            // not ours — never reject a value we cannot read as a calendar day.
            if ($day === null) {
                continue;
            }

            if ($earliest instanceof CarbonImmutable && $day->lessThan($earliest)) {
                $fail(__('This date must be on or after :date.', ['date' => $earliest->format('Y-m-d')]));

                return;
            }

            if ($latest instanceof CarbonImmutable && $day->greaterThan($latest)) {
                $fail(__('This date must be on or before :date.', ['date' => $latest->format('Y-m-d')]));

                return;
            }

            if (DateBoundaryEngine::isDateUnavailable($day, $allowedDays, $exceptions)) {
                $onDisallowedWeekday = $allowedDays !== null
                    && ! \in_array((string) $day->dayOfWeek, $allowedDays, true);

                $fail($onDisallowedWeekday
                    ? __('This date falls on a day of the week that is not allowed.')
                    : __('This date falls within a period that is not available.'));

                return;
            }
        }
    }

    /**
     * The calendar-day endpoints of the submitted value: the single day, or a
     * range's start and end. Empty values yield an empty list (nothing to check).
     *
     * @param  mixed  $value  Preprocessed field value
     * @param  string  $timezone  The display timezone (for time-bearing values)
     * @return list<CarbonImmutable|null> Calendar days (null per unparseable endpoint)
     */
    private function endpointDays(mixed $value, string $timezone): array
    {
        if (in_array($value, [null, '', []], true)) {
            return [];
        }

        if (\is_array($value) && (\array_key_exists('start', $value) || \array_key_exists('end', $value))) {
            return [
                $this->dayOf($value['start'] ?? null, $timezone),
                $this->dayOf($value['end'] ?? null, $timezone),
            ];
        }

        return [$this->dayOf($value, $timezone)];
    }

    /**
     * The calendar day of one submitted endpoint.
     *
     * A `time_enabled` field's value reaches this rule as a UTC instant that
     * encodes the display-timezone wall clock the editor picked (Statamic converts
     * the picked wall clock to a Zulu string, then `Carbon::parse` yields a UTC
     * Carbon). Its intended calendar day is therefore the day in the DISPLAY
     * timezone — the same frame the boundaries and `today()` use — not UTC; read it
     * there so an evening pick isn't pushed onto the next UTC day (a bypass) or a
     * next-UTC-day pick rejected (a false-reject). A date-only value already carries
     * the correct calendar day in its own frame, so it is parsed as-is.
     *
     * @param  mixed  $value  One endpoint (Carbon/DateTimeInterface, string, or null)
     * @param  string  $timezone  The display timezone
     */
    private function dayOf(mixed $value, string $timezone): ?CarbonImmutable
    {
        if ($value instanceof DateTimeInterface && ($this->config['time_enabled'] ?? false) === true) {
            $value = CarbonImmutable::instance($value)->setTimezone($timezone);
        }

        return DateBoundaryEngine::parseDay($value);
    }

    /**
     * Resolve a `field`-relative boundary's referenced sibling value from the data
     * bag, at the same nesting level as the field under validation (so a sibling in
     * the same replicator/grid set resolves too). Null when no handle is set.
     *
     * @param  string  $attribute  The validated field's dotted attribute path
     * @param  mixed  $handle  The referenced sibling field handle, or null
     * @return mixed The sibling's submitted value, or null
     */
    private function siblingValue(string $attribute, mixed $handle): mixed
    {
        if (! \is_string($handle) || $handle === '') {
            return null;
        }

        $prefix = Str::contains($attribute, '.')
            ? Str::beforeLast($attribute, '.').'.'
            : '';

        return Arr::get($this->data, $prefix.$handle);
    }

    /**
     * The site/display timezone — the frame the CP picker and public form present,
     * used for both `today` and reading a time-bearing value's calendar day. Falls
     * back to the app timezone, then UTC.
     */
    private function displayTimezone(): string
    {
        $displayTimezone = config('statamic.system.display_timezone');
        $appTimezone = config('app.timezone');

        return match (true) {
            is_scalar($displayTimezone) && (string) $displayTimezone !== '' => (string) $displayTimezone,
            is_scalar($appTimezone) && (string) $appTimezone !== '' => (string) $appTimezone,
            default => 'UTC',
        };
    }

    /**
     * Today's calendar date in the display timezone. Client "today" is the
     * browser-local day, so at the midnight edge the two can differ by a calendar
     * day; that day-granularity gap is accepted. Honors {@see Carbon::setTestNow()}
     * for deterministic tests.
     *
     * @param  string  $timezone  The display timezone
     */
    private function today(string $timezone): CarbonImmutable
    {
        return DateBoundaryEngine::parseDay(CarbonImmutable::now($timezone))
            ?? CarbonImmutable::now($timezone)->startOfDay();
    }
}
