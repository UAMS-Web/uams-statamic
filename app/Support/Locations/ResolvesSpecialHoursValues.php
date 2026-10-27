<?php

declare(strict_types=1);

namespace App\Support\Locations;

use App\Services\Locations\SystemLocationClosures;
use App\Tags\LocationModifiedHoursNotice;
use App\Tags\SchemaLocationHours;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Statamic\Fields\LabeledValue;
use Statamic\Fields\Value;
use Statamic\Fields\Values;

/**
 * Shared value-coercion plumbing for the location special-hours subsystem.
 *
 * Both hours consumers — {@see SchemaLocationHours} (JSON-LD) and
 * {@see LocationModifiedHoursNotice} (the visitor-facing notice) — and
 * the {@see SystemLocationClosures} merge service read the
 * same `special_hours` replicator shape, which augments differently depending on
 * the render path (raw stored arrays from a global vs. engine-augmented `Value` /
 * `Values` / `LabeledValue` / `Carbon` instances on an entry). These helpers
 * normalize every shape to plain scalars so the callers can share one code path.
 */
trait ResolvesSpecialHoursValues
{
    /**
     * Normalize a bound value into a list of replicator rows.
     *
     * @return array<int, mixed>
     */
    private function rows(mixed $raw): array
    {
        // Unwrap a Statamic field value
        if ($raw instanceof Value) {
            // Read the inner value for coercion
            $raw = $raw->value();
        }

        // A replicator augments to a list of rows
        if (! \is_array($raw)) {
            // Return no rows when the shape is unexpected
            return [];
        }

        // Pass the rows through as a list
        return array_values($raw);
    }

    /**
     * Read a single field from a replicator row (a `Values` set or a plain array).
     */
    private function rowField(mixed $row, string $key): mixed
    {
        // An augmented replicator set exposes fields via array access
        if ($row instanceof Values) {
            // Read the field value
            return $row[$key] ?? null;
        }

        // A plain array row carries fields by key
        if (\is_array($row)) {
            // Read the keyed value
            return $row[$key] ?? null;
        }

        // Anything else has no readable field
        return null;
    }

    /**
     * Resolve a special-hours entry's set type (holiday / temporary_change).
     */
    private function setType(mixed $entry): ?string
    {
        // A replicator set exposes its set name under `type`
        if ($entry instanceof Values) {
            // Read the set type
            return $this->scalarValue($entry['type'] ?? null);
        }

        // A plain array row carries the set name under `type`
        if (\is_array($entry)) {
            // Read the set type
            return $this->scalarValue($entry['type'] ?? null);
        }

        // Anything else has no set type
        return null;
    }

    /**
     * Resolve a rule's status (open / open_24h / closed).
     */
    private function status(mixed $rule): ?string
    {
        // Read the button-group value
        return $this->scalarValue($this->rowField($rule, 'status'));
    }

    /**
     * Service mode of a rule describing the physical premises.
     *
     * @var string
     */
    private const string MODE_IN_PERSON = 'in_person';

    /**
     * Service mode of a rule describing remote visits.
     *
     * @var string
     */
    private const string MODE_TELEMEDICINE = 'telemedicine';

    /**
     * Reader-facing label for each service mode.
     *
     * @var array<string, string>
     */
    private const array MODE_LABELS = [
        self::MODE_IN_PERSON => 'In-Person',
        self::MODE_TELEMEDICINE => 'Telemedicine',
    ];

    /**
     * Resolve a rule's service mode (in_person / telemedicine).
     */
    private function serviceMode(mixed $rule): ?string
    {
        // Read the button-group value
        return $this->scalarValue($this->rowField($rule, 'service_mode'));
    }

    /**
     * Reader-facing label for a normalized service mode.
     *
     * Exists so the label map has a single consumer inside this trait rather
     * than being inherited as an unused constant by the two classes that read
     * service modes without ever displaying one.
     *
     * @param  string  $mode  A mode returned by `ruleMode()`
     * @return string The label, or an empty string for an unknown mode
     */
    private function serviceModeLabel(string $mode): string
    {
        // Read the label for a known mode
        return self::MODE_LABELS[$mode] ?? '';
    }

    /**
     * Resolve which service mode a rule describes, normalized.
     *
     * Only an explicit `telemedicine` is remote. Anything else -- `in_person`,
     * an absent value, or an unrecognized one -- describes the physical
     * premises, which matches the blueprint default and the reading
     * `SchemaLocationHours` and `SystemLocationClosures` already apply when they
     * skip telemedicine rules. Bucketing an unknown mode with the premises keeps
     * the rule visible rather than silently dropping it.
     *
     * Lives here rather than on a consumer because two of them now need it
     * (UAMS-Web/uams-statamic-archive#2405, UAMS-Web/uams-statamic-archive#2466), and two copies of "what counts as telemedicine" is exactly
     * the drift that produces a rule and a notice disagreeing about one entry.
     */
    private function ruleMode(mixed $rule): string
    {
        // Treat only an explicit telemedicine mode as remote
        return $this->serviceMode($rule) === self::MODE_TELEMEDICINE
            ? self::MODE_TELEMEDICINE
            : self::MODE_IN_PERSON;
    }

    /**
     * Normalize a bound date value to `YYYY-MM-DD`, or null.
     */
    private function dateValue(mixed $raw): ?string
    {
        // Unwrap a Statamic field value
        if ($raw instanceof Value) {
            // Read the inner value for coercion
            $raw = $raw->value();
        }

        // A date fieldtype augments to a Carbon / DateTime instance (Carbon implements DateTimeInterface)
        if ($raw instanceof DateTimeInterface) {
            // Format the calendar date
            return $raw->format('Y-m-d');
        }

        // A plain string date is used as-is once cleaned
        return $this->scalarValue($raw);
    }

    /**
     * Coerce a bound value to a clean non-empty string, or null.
     */
    private function scalarValue(mixed $raw): ?string
    {
        // Unwrap a Statamic field value
        if ($raw instanceof Value) {
            // Read the inner value for coercion
            $raw = $raw->value();
        }

        // Unwrap an augmented labeled option
        if ($raw instanceof LabeledValue) {
            // Read the underlying value for coercion
            $raw = $raw->value();
        }

        // Reject non-scalar values
        if (! \is_scalar($raw)) {
            // Return null when there is nothing to coerce
            return null;
        }

        // Strip any markup and trim surrounding whitespace (defense in depth)
        $clean = trim(strip_tags((string) $raw));

        // Return the cleaned value when non-empty
        return $clean === '' ? null : $clean;
    }

    /**
     * Whether a `YYYY-MM-DD` window-end date is strictly before today, so its
     * special-hours entry has fully elapsed (UAMS-Web/uams-statamic-archive#1328). A null or unparseable date
     * is treated as not-past (never expires), leaving ongoing windows intact.
     */
    private function isEntirelyPast(?string $date): bool
    {
        // A missing window end never expires
        if ($date === null) {
            // Keep the entry
            return false;
        }

        // Compare the calendar date against today, guarding an unparseable value
        try {
            // Past when the window end falls before the start of today
            return Carbon::parse($date)->startOfDay()->lt(Carbon::today());
        } catch (\Exception) {
            // An unparseable date is left to emit rather than silently dropped
            return false;
        }
    }
}
