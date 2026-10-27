<?php

declare(strict_types=1);

namespace App\Fieldtypes;

use App\Fieldtypes\Traits\ConfigSectionWrapper;
use App\Rules\DateBoundary;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry;
use Statamic\Entries\GetDateFromPath;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;
use Statamic\Fields\Fieldtype;
use Statamic\Fieldtypes\Date as BaseDate;

/**
 * Extended Date fieldtype.
 *
 * ## Historical behavior (rollback reference)
 *
 * Until this class delegated date-only single/range modes to Statamic core, we
 * implemented custom round-tripping that differed from stock
 * `Statamic\Fieldtypes\Date` in two ways. Editors in negative-offset zones hit
 * CP day shifts that Statamic later fixed (statamic/cms#14552); our older stack
 * was an attempt to anchor calendar meaning in
 * `config('statamic.system.display_timezone')` while the CP still received UTC
 * Zulu strings from preprocess.
 *
 * **Preprocess (removed `preProcessSingleDateOnlyStable` /
 * `preProcessRangeDateOnlyStable`):** For `time_enabled` false, stored strings
 * were parsed in `config('statamic.system.display_timezone')` with
 * `Carbon::createFromFormat(...)->startOfDay()`, then emitted as
 * `toIso8601ZuluString('millisecond')` for the Vue field. Stock Statamic now
 * emits plain `Y-m-d` when the save format has no time tokens, matching
 * `DateFieldtype.vue` `parseDateOnly()`.
 *
 * **Process (removed `storageStringForCalendarDayInDisplayTimezone`):** For
 * `time_enabled` false, CP values were parsed as UTC instants, shifted into the
 * Statamic display timezone, and formatted to the blueprint `format` or
 * `Y-m-d`. That kept the wall calendar in the display zone even when
 * `config('app.timezone')` differed. Core instead uses `parent::process()`:
 * `Carbon::parse` in `app.timezone` for date-only save formats and UTC for
 * time-bearing save formats, then `formatAndCast()` in `app.timezone`. When
 * the default save format still includes time (`Y-m-d H:i`), persisted values
 * can be datetime strings unless the blueprint sets an explicit date-only
 * `format` (for example `Y-m-d`).
 *
 * To restore the old semantics, reintroduce the removed private methods, call
 * them from `preProcessWithTimezoneSemantics()` for date-only fields (which now
 * go straight to the parent), and replace the `return parent::process($data)` tail in `process()` with the
 * prior `storageStringForCalendarDayInDisplayTimezone` pipeline (re-read git
 * history before the delegation change).
 *
 * **Blueprint note:** For true calendar-only storage aligned with the CP Vue
 * picker, set an explicit date-only `format` (for example `Y-m-d`). Relying on
 * the default single-mode save format keeps a time component (`Y-m-d H:i`) per
 * Statamic core.
 *
 * ## Boundaries & Limits enforcement (CP + server)
 *
 * The `custom_date` Vue component ({@see self::component()} routes only fields
 * that configure a boundary to `resources/js/components/fieldtypes/DateFieldtype.vue`)
 * enforces the Boundaries & Limits config the base picker ignores. It computes
 * the effective earliest/latest min-max client-side (via
 * `resources/js/date-boundary-engine.js`) from `*_date_type` + `*_date_custom` /
 * `*_date_today_offset` / `*_date_field` + `*_date_field_offset` — including the
 * referenced sibling field's live value for the `field` type — and disables
 * `days_of_week` and `date_exceptions`. The hidden computed `earliest_date` /
 * `latest_date` config fields are therefore vestigial (kept for back-compat;
 * the component derives the bounds directly).
 *
 * The client constraints are a CONVENIENCE, not authority: {@see self::rules()}
 * attaches {@see DateBoundary} whenever a boundary is configured, re-deriving the
 * same bounds server-side (via `App\Support\DateBoundaryEngine`, the PHP mirror of
 * `date-boundary-engine.js`) so a value that bypasses the picker — a scripted or
 * malicious public-form POST — is rejected too. The server rule also covers
 * `time_enabled` boundary fields, which the custom picker excludes (UAMS-Web/uams-statamic-archive#1394).
 *
 * The `earliest_date_field` / `latest_date_field` boundary selects (`type: field`)
 * are populated in the CP from the blueprint's other `date` fields via the
 * {@see DateFieldSelect} config fieldtype + its Vue component (UAMS-Web/uams-statamic-archive#1393) — the
 * server-side `configFieldItems()` cannot reach the sibling fields (the config
 * schema is built in isolation), so that list is derived client-side.
 *
 * @see BaseDate
 */
class Date extends BaseDate
{
    /**
     * Store datetimes by converting the CP instant into the Statamic display
     * timezone wall clock, then formatting in the app timezone (Statamic stock).
     */
    private const string DATETIME_STORAGE_CONVERT = 'convert';

    /**
     * Store datetimes as the wall clock in the Statamic display timezone without
     * shifting into the app timezone on save.
     */
    private const string DATETIME_STORAGE_LITERAL = 'literal';

    /**
     * Trait for wrapping and extending Statamic fieldtype config sections.
     *
     * Provides helper methods to inject or modify configuration fields in the
     * parent fieldtype's config, allowing for clean extension and organization
     * of config options without duplicating parent logic.
     */
    use ConfigSectionWrapper;

    /**
     * Indicates whether the fieldtype is selectable in form blueprints.
     *
     * @var bool Indicates whether the fieldtype is selectable in form blueprints
     */
    protected $selectableInForms = true;

    /**
     * The icon for this fieldtype.
     *
     * @return string The icon name
     */
    #[\Override]
    public function icon(): string
    {
        /** @var string $icon */
        $icon = parent::icon();

        return $icon;
    }

    /**
     * The Vue component that renders this fieldtype in the Control Panel.
     *
     * Only DATE-ONLY fields that actually configure a Boundaries & Limits
     * constraint use the custom `custom_date` component (registered in
     * `resources/js/cp.js`), which honors earliest/latest min-max, allowed
     * `days_of_week`, and `date_exceptions` blackout dates that the base picker
     * ignores. Every other `date` field — entry publish dates, unconstrained
     * form fields, this fieldtype's own `date`-typed config sub-fields
     * (`earliest_date_custom`, `date_exceptions`), and `time_enabled` fields —
     * keeps the fully-tested base `date` component, so the override's blast
     * radius is limited to fields with something to enforce. `time_enabled`
     * fields are excluded so the custom picker's value is always a plain
     * `Y-m-d` string that round-trips losslessly through a reka `CalendarDate`
     * (boundary enforcement on time-bearing fields is a tracked follow-up); the
     * base value contract is otherwise preserved, so server-side
     * `process()`/`augment()` are unaffected.
     *
     * @return string The registered CP component name
     */
    #[\Override]
    public function component(): string
    {
        if ($this->boundariesConfigured() && ! $this->config('time_enabled')) {
            return 'custom_date';
        }

        return parent::component();
    }

    /**
     * Whether this field configures any Boundaries & Limits constraint the base
     * picker cannot enforce (an active earliest/latest boundary, a restricted
     * set of `days_of_week`, or one or more `date_exceptions`).
     *
     * @return bool True when the enhanced `custom_date` picker is warranted
     */
    private function boundariesConfigured(): bool
    {
        foreach (['earliest_date_type', 'latest_date_type'] as $typeKey) {
            $type = $this->config($typeKey, 'none');

            if ($type !== 'none' && $type !== null) {
                return true;
            }
        }

        $days = $this->config('days_of_week');

        if (\is_array($days) && $days !== [] && \count($days) < 7) {
            return true;
        }

        $exceptions = $this->config('date_exceptions');

        return \is_array($exceptions) && $exceptions !== [];
    }

    /**
     * The validation rules applied to this field's own value.
     *
     * When a Boundaries & Limits constraint is configured, attach the
     * {@see DateBoundary} backstop so the client-side picker constraints
     * (`resources/js/date-boundary-engine.js`) are enforced server-side too
     * (UAMS-Web/uams-statamic-archive#1394) — including on `time_enabled` fields, whose ONLY boundary
     * enforcement this is (the custom picker excludes them; see {@see component()}).
     *
     * The rule is returned from {@see Fieldtype::rules()}, which
     * Statamic keys to the field's OWN handle bucket — NOT `extraRules()`, whose
     * flat list mis-keys to numeric phantom fields that never validate (see the
     * `FieldtypeValidationRules` trait note on UAMS-Web/uams-statamic-archive#1301). Unconstrained date fields
     * get no extra rule, so their validation is unchanged.
     *
     * @return array<int, mixed> Laravel validation rules for this field
     */
    #[\Override]
    public function rules(): array
    {
        /** @var array<int, mixed> $baseRules */
        $baseRules = parent::rules();

        /** @var Field|null $field */
        $field = $this->field;

        if ($field === null || ! $this->boundariesConfigured()) {
            return $baseRules;
        }

        /** @var array<string, mixed> $config */
        $config = $field->config();

        return array_merge($baseRules, [new DateBoundary($config)]);
    }

    /**
     * Default used when the entry has no value for this field.
     *
     * `Statamic\Fields\Field::defaultValue()` resolves defaults with
     * `$config['default'] ?? $this->fieldtype()->defaultValue()`, so a
     * blueprint `default: null` incorrectly skips the left side and would
     * use the fieldtype fallback. When the `default` key exists, return its
     * value (including null) so optional date fields stay empty.
     *
     * @return mixed Blueprint default, fieldtype default, or null
     */
    #[\Override]
    public function defaultValue(): mixed
    {
        /** @var Field|null $field */
        $field = $this->field;

        if ($field === null) {
            return parent::defaultValue();
        }

        $config = $field->config();

        if (\array_key_exists('default', $config)) {
            return $field->get('default');
        }

        return parent::defaultValue();
    }

    /**
     * Whether the blueprint sets `default: null` for this field.
     *
     * Distinguishes “no default” from a missing `default` key so dated-entry
     * publish-date fallback does not fill optional fields.
     */
    private function blueprintDefaultIsExplicitlyNull(): bool
    {
        // preProcess() reads this field's parent next, so it is set here
        /** @var Field $field */
        $field = $this->field;

        $config = $field->config();

        return \array_key_exists('default', $config) && $field->get('default') === null;
    }

    /**
     * Whether this field is the collection entry publish date (not a nested `date`).
     *
     * Dated collections use a top-level `date` field that syncs with the filename
     * prefix. Replicator or grid sets may also use the handle `date`; those must
     * preprocess from the row value (`$data`), not `$entry->get('date')` or
     * `$entry->date()`.
     */
    private function isDatedCollectionPublishDateField(): bool
    {
        // preProcess() has already read this field's parent, so it is set here
        /** @var Field $field */
        $field = $this->field;

        return $field->parentField() === null
            && $field->handle() === 'date';
    }

    /**
     * Preprocess the value for the Control Panel.
     *
     * For an existing entry in a dated collection, prefer the filename date
     * prefix (same as the Stache index), then real stored data, then the entry
     * date. Blueprint default "now" must not override the file prefix or the CP
     * will round-trip the wrong instant and rename the file.
     *
     * @param  mixed  $data  Raw value or default (e.g., "now")
     * @return mixed Value for the CP, or parent result
     */
    #[\Override]
    public function preProcess(
        mixed $data
    ): mixed {
        // Skip publish-date fallback when the field is explicitly optional
        if ($this->blueprintDefaultIsExplicitlyNull()) {
            return $this->preProcessWithTimezoneSemantics($data);
        }

        // Get the field's parent (entry, blueprint, or other context)
        /** @var Field $field */
        $field = $this->field;
        $parent = $field->parent();

        // Resolve the entry; in the CP the blueprint's parent is set to the entry
        $entry = $parent instanceof Entry
            ? $parent
            : (($parent instanceof Blueprint) ? $parent->parent() : null);

        // ---
        // Use the entry's date when the field belongs to an existing entry in a dated
        // collection (single mode only)
        // ---

        // Check if mode is single and we have the publish `date` field on a dated entry
        if (
            $this->config('mode', 'single') === 'single'
            && $entry instanceof Entry
            && $entry->id()
            && ($entry->collection() instanceof Collection
                && $entry->collection()->dated())
            && $this->isDatedCollectionPublishDateField()
        ) {
            // Parse publish instant from the dated filename (matches Stache indexing)
            $dateFromPath = $this->getDateFromEntryPath($entry);

            // Check if the date from path was resolved
            if ($dateFromPath instanceof Carbon) {
                // Return path date as UTC Zulu string for the CP
                return $dateFromPath->copy()->utc()->toIso8601ZuluString('millisecond');
            }

            // Get the stored date value from the entry for this field
            /** @var Field $field */
            $field = $this->field;
            /** @var \DateTimeInterface|float|int|string|null $storedDate */
            $storedDate = $entry->get($field->handle());

            // Check if stored value is present and not the blueprint default sentinel
            if (
                ! in_array($storedDate, [null, '', 'now'], true)
            ) {
                // Attempt to parse the stored date in the app timezone
                try {
                    /** @var string|null $appTimezone */
                    $appTimezone = config('app.timezone');

                    // Parse stored value and convert to UTC ISO 8601 for the CP
                    $carbon = Carbon::parse(
                        $storedDate,
                        $appTimezone
                    );

                    // Return UTC Zulu string so the field does not overwrite with default
                    return $carbon->copy()->utc()->toIso8601ZuluString('millisecond');
                } catch (\Throwable) {
                    // Fall through to entry date
                }
            }

            // Get the entry date (e.g., last modified when path has no date prefix)
            /** @var Carbon|null $entryDate */
            $entryDate = $entry->date();

            // Check if the entry date is not null
            if ($entryDate !== null) {
                // Return entry date as UTC Zulu string for the CP
                return $entryDate->copy()->utc()->toIso8601ZuluString('millisecond');
            }
        }

        // Apply literal-datetime CP semantics, or the Statamic default
        return $this->preProcessWithTimezoneSemantics($data);
    }

    /**
     * Preprocess a literal datetime ourselves, and anything else with Statamic.
     *
     * @param  mixed  $data  Raw value or default (e.g., "now")
     * @return mixed Value for the CP
     */
    private function preProcessWithTimezoneSemantics(mixed $data): mixed
    {
        // Only literal datetimes (which require time_enabled) are ours; date-only
        // fields and datetimes stored with conversion go to the parent
        if ($this->usesLiteralDatetimeStorage()) {
            if ($this->config('mode', 'single') === 'single') {
                return $this->preProcessSingleLiteralDatetime($data);
            }

            if ($this->config('mode') === 'range') {
                return $this->preProcessRangeLiteralDatetime($data);
            }
        }

        return parent::preProcess($data);
    }

    /**
     * Whether this field stores datetimes as display-zone wall clocks (no app
     * timezone shift on save).
     */
    private function usesLiteralDatetimeStorage(): bool
    {
        return $this->config('time_enabled')
            && $this->config('datetime_storage', self::DATETIME_STORAGE_CONVERT) === self::DATETIME_STORAGE_LITERAL;
    }

    /**
     * Statamic CP display timezone (STATAMIC_DISPLAY_TIMEZONE).
     */
    private function statamicDisplayTimezone(): string
    {
        $displayTimezone = config('statamic.system.display_timezone');

        // Carbon rejects '' as a timezone just as it rejects any other non-zone
        // string: both throw InvalidTimeZoneException, and only the message differs
        // @pest-mutate-ignore: EmptyStringToNotEmpty
        return \is_string($displayTimezone) ? $displayTimezone : '';
    }

    /**
     * Resolve the save format string (matches Statamic Date saveFormat logic).
     */
    private function resolveSaveFormat(): string
    {
        if ($this->config('mode', 'single') === 'range') {
            $rangeFormat = $this->config('format', BaseDate::DEFAULT_DATETIME_FORMAT);

            return \is_string($rangeFormat) ? $rangeFormat : BaseDate::DEFAULT_DATETIME_FORMAT;
        }

        $singleFormat = $this->config(
            'format',
            $this->config('time_seconds_enabled')
                ? BaseDate::DEFAULT_DATETIME_WITH_SECONDS_FORMAT
                : BaseDate::DEFAULT_DATETIME_FORMAT
        );

        return \is_string($singleFormat) ? $singleFormat : BaseDate::DEFAULT_DATETIME_FORMAT;
    }

    /**
     * Preprocess a literal datetime string for the CP (display zone wall clock).
     *
     * @param  mixed  $data  Stored scalar
     * @return mixed ISO Zulu or null
     */
    private function preProcessSingleLiteralDatetime(mixed $data): mixed
    {
        if (! $data) {
            return null;
        }

        if ($data === 'now') {
            return 'now';
        }

        if (\is_array($data)) {
            $data = $data['start'];
        }

        if (! \is_string($data)) {
            return parent::preProcess($data);
        }

        $format = $this->resolveSaveFormat();
        $tz = $this->statamicDisplayTimezone();

        try {
            $local = Carbon::createFromFormat(
                $format,
                $data,
                $tz
            );
        } catch (\Throwable) {
            try {
                $local = Carbon::parse(
                    $data,
                    $tz
                );
            } catch (\Throwable) {
                return parent::preProcess($data);
            }
        }

        /** @var Carbon $local */
        return $local->copy()->utc()->toIso8601ZuluString('millisecond');
    }

    /**
     * Preprocess a literal datetime range for the CP.
     *
     * @param  mixed  $data  Stored range
     * @return mixed Range payload or null
     */
    private function preProcessRangeLiteralDatetime(mixed $data): mixed
    {
        // The parent preprocesses an empty value (null, '', 0, false) to null
        if (! \is_array($data)) {
            return parent::preProcess($data);
        }

        if (
            ! \array_key_exists('start', $data)
            || $data['start'] === null
            || $data['start'] === ''
        ) {
            return null;
        }

        return [
            'start' => $this->preProcessSingleLiteralDatetime($data['start']),
            'end' => $this->preProcessSingleLiteralDatetime($data['end'] ?? ''),
        ];
    }

    /**
     * Parse the date from an entry's path (filename date prefix) when present.
     *
     * Dated collection paths use a prefix like 2026-03-02-2215 (Y-m-d-Hi) or
     * 2026-03-02-221542 (Y-m-d-His). Returns null if the path has no valid prefix.
     *
     * Parsing uses config('app.timezone') like
     * Statamic\Stache\Stores\CollectionEntriesStore::getDateFromPath and matches
     * how Statamic\Entries\Entry::buildPath() formats the prefix (date shifted
     * to app timezone). Keep this in sync when upgrading statamic/cms.
     *
     * @param  Entry  $entry  The entry whose path to parse
     * @return Carbon|null Carbon instance or null
     */
    private function getDateFromEntryPath(
        Entry $entry
    ): ?Carbon {
        // Resolve the entry path (initial path when present)
        $path = $entry->path();

        // Extract the dated prefix segment from the filename
        /** @var string|null $date */
        $date = (new GetDateFromPath)($path);

        // Check if the path has no dated prefix
        if (! $date) {
            return null;
        }

        // Choose the format string from the prefix length
        $format = match (\strlen($date)) {
            10 => 'Y-m-d',
            15 => 'Y-m-d-Hi',
            17 => 'Y-m-d-His',
            default => null,
        };

        // GetDateFromPath's `$` also matches before a trailing newline, so the
        // prefix can carry one (11, 16 or 18 characters); no format fits it
        if ($format === null) {
            return null;
        }

        // ---
        // Parse using app timezone (same as
        // \Statamic\Stache\Stores\CollectionEntriesStore::getDateFromPath). An
        // all-digit prefix never fails to parse: out-of-range parts roll over.
        // ---
        /** @var string|null $appTimezone */
        $appTimezone = config('app.timezone');

        $carbon = Carbon::createFromFormat(
            $format,
            $date,
            $appTimezone
        );

        /** @var Carbon $carbon */
        // Normalize date-only entries to start of day
        if (\strlen($date) === 10) {
            $carbon->startOfDay();
        }

        // Return the instant in UTC to match Entry::date() storage
        return $carbon->utc();
    }

    /**
     * Normalize CP single-date array payloads before Statamic's DateFieldtype rule.
     *
     * The Control Panel submits single dates as `['date' => 'Y-m-d', 'time' => …]`.
     * Parent validation expects an ISO-8601 Zulu string; passing an array causes
     * TypeError in Statamic\Rules\DateFieldtype.
     *
     * @param  mixed  $value  Raw value from the request or tests
     * @return mixed Carbon, null, or passthrough for parent
     */
    #[\Override]
    public function preProcessValidatable(
        $value
    ): mixed {
        // Check whether single mode uses the CP date/time array shape
        if (
            $this->config('mode', 'single') === 'single'
            && \is_array($value)
            && \array_key_exists('date', $value)
        ) {
            // Extract the date portion from the payload
            $datePart = $value['date'];

            // Check if the date is missing or empty and the field is required
            if (
                $datePart === null
                || $datePart === ''
            ) {
                // ---
                // Resolve validate rules from the field config
                // ---

                // Get the validate rules from the field config
                /** @var Field $field */
                $field = $this->field;
                $rules = $field->get('validate', []);

                // Check if the rules is an array and if not, convert it to an array
                $rules = \is_array($rules) ? $rules : [$rules];

                // Any rule starting with "required" (required, required_if, ...) requires a value
                $requiresValue = array_any(
                    $rules,
                    static fn (mixed $rule): bool => \is_string($rule) && str_starts_with($rule, 'required')
                );

                // ---
                // Fail with fieldPathPrefix so nested replicator errors key correctly
                // ---

                // Check if requiresValue is true
                if ($requiresValue) {
                    // Throw a ValidationException with the fieldPathPrefix and the required message
                    // fieldPathPrefix() implodes the field's path keys, so it is a string
                    /** @var string $fieldPathPrefix */
                    $fieldPathPrefix = $field->fieldPathPrefix();
                    $fieldDisplay = $field->display();

                    throw ValidationException::withMessages([
                        $fieldPathPrefix => [
                            trans(
                                'validation.required',
                                ['attribute' => \is_string($fieldDisplay) ? $fieldDisplay : '']
                            ),
                        ],
                    ]);
                }

                // ---
                // Delegate to the parent fieldtype with null to allow parent DateFieldtype
                // validation to handle the empty value
                // ---
                return parent::preProcessValidatable(null);
            }

            // Build ISO Zulu string so parent DateFieldtype validation accepts the value
            /** @var array<string, mixed> $arrayValue */
            $arrayValue = $value;
            $value = $this->cpSingleDateArrayToIsoZuluString($arrayValue);
        }

        // Delegate to the parent fieldtype with the original value
        return parent::preProcessValidatable($value);
    }

    /**
     * Build Statamic's expected single-date ISO string from a CP date/time array.
     *
     * @param  array<string, mixed>  $value  Must include non-empty `date`
     * @return string The ISO Zulu string
     */
    private function cpSingleDateArrayToIsoZuluString(
        array $value
    ): string {
        // Get the date part from the value
        $rawDatePart = $value['date'];
        $datePart = \is_string($rawDatePart) ? $rawDatePart : '';

        // ---
        // Get the time part from the value (if time is enabled and a time segment was
        // provided)
        // ---
        $timePart = $value['time'] ?? null;

        // ---
        // Check if time is enabled and a time segment was provided. An empty time
        // goes to the lenient parser too: a clean Y-m-d date reads as the start of
        // that day, a date that carries its own time keeps that time, and a missing
        // or non-string date reads as now.
        // ---
        if (
            $this->config('time_enabled')
            && \is_string($timePart)
        ) {
            // Parse the wall clock (HH:MM or HH:MM:SS) in the Statamic display
            // timezone, then UTC for CP
            $dt = Carbon::parse(
                sprintf('%s %s', $datePart, $timePart),
                $this->statamicDisplayTimezone()
            );
        } else {
            // Anchor date-only picks to the Statamic display timezone calendar day
            $parsed = Carbon::createFromFormat(
                'Y-m-d',
                $datePart,
                $this->statamicDisplayTimezone()
            );

            /** @var Carbon $parsed */
            $dt = $parsed->startOfDay();
        }

        // Return the ISO Zulu string
        return $dt->utc()->format('Y-m-d\TH:i:s.v\Z');
    }

    /**
     * Normalize submitted values for storage.
     *
     * Date-only (`time_enabled` false) delegates to Statamic core so save
     * formats, wire payloads, and CP Vue stay aligned (see class docblock
     * "Historical behavior" for the prior display-timezone pipeline).
     *
     * @param  mixed  $data  Raw value from the submission pipeline
     * @return mixed Stored scalar, range array, or null
     */
    #[\Override]
    public function process(mixed $data): mixed
    {
        if ($data === null) {
            return null;
        }

        // usesLiteralDatetimeStorage() requires time_enabled
        if ($this->usesLiteralDatetimeStorage()) {
            return $this->processDatetimeLiteral($data);
        }

        return parent::process($data);
    }

    /**
     * Store submitted datetimes as display-timezone wall clocks (literal mode).
     *
     * @param  mixed  $data  CP payload (ISO strings or range array)
     * @return mixed Stored scalar, range array, int year, or null
     */
    private function processDatetimeLiteral(mixed $data): mixed
    {
        if ($this->config('mode') === 'range') {
            if (! \is_array($data) || $data['start'] === null) {
                return null;
            }

            return [
                'start' => $this->processSingleDatetimeLiteralScalar($data['start']),
                'end' => $this->processSingleDatetimeLiteralScalar($data['end']),
            ];
        }

        return $this->processSingleDatetimeLiteralScalar($data);
    }

    /**
     * Format one literal datetime instant for storage in the display timezone.
     *
     * @param  mixed  $data  ISO-8601 string from the CP
     * @return string|int Stored value
     */
    private function processSingleDatetimeLiteralScalar(mixed $data): string|int
    {
        $time = (is_string($data) || is_int($data) || is_float($data)) ? $data : null;
        $instant = Carbon::parse(
            $time,
            'UTC'
        );
        $format = $this->resolveSaveFormat();
        $formatted = $instant->copy()->setTimezone(
            $this->statamicDisplayTimezone()
        )->format($format);

        if (is_numeric($formatted)) {
            return (int) $formatted;
        }

        return $formatted;
    }

    /**
     * Augment stored values for templates and the runtime API.
     *
     * @param  mixed  $value  Raw stored value
     * @return mixed Augmented value
     */
    #[\Override]
    public function augment(mixed $value): mixed
    {
        if ($this->config('time_enabled') && $this->usesLiteralDatetimeStorage()) {
            return $this->augmentLiteralDatetime($value);
        }

        return parent::augment($value);
    }

    /**
     * Augment literal datetime storage using the display timezone wall clock.
     *
     * @param  mixed  $value  Stored scalar or range array
     * @return mixed Carbon instance(s) or null
     */
    private function augmentLiteralDatetime(mixed $value): mixed
    {
        if (! $value) {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value;
        }

        if ($this->config('mode') === 'range') {
            /** @var array<string, mixed> $rangeValue */
            $rangeValue = is_array($value) ? $value : [];

            return [
                'start' => $this->parseLiteralStoredDatetimeToCarbon($rangeValue['start']),
                'end' => $this->parseLiteralStoredDatetimeToCarbon($rangeValue['end']),
            ];
        }

        return $this->parseLiteralStoredDatetimeToCarbon($value);
    }

    /**
     * Parse a literal stored datetime string into a Carbon in UTC for comparisons.
     *
     * @param  mixed  $value  Stored fragment
     */
    private function parseLiteralStoredDatetimeToCarbon(mixed $value): Carbon
    {
        $format = $this->resolveSaveFormat();
        $tz = $this->statamicDisplayTimezone();

        if ($value instanceof Carbon) {
            return $value->copy()->utc();
        }

        $valueString = is_scalar($value) ? (string) $value : '';

        try {
            $local = Carbon::createFromFormat(
                $format,
                $valueString,
                $tz
            );
        } catch (\Throwable) {
            $local = Carbon::parse(
                $valueString,
                $tz
            );
        }

        /** @var Carbon $local */
        if (! $this->config('time_seconds_enabled')) {
            $local->startOfMinute();
        }

        return $local->copy()->utc();
    }

    /**
     * Extend the config to add additional configuration fields available for this
     * fieldtype in the blueprint.
     *
     * Adds configuration fields for earliest and latest date boundaries,
     * including support for custom dates, current date, date fields from
     * the form, offsets, day-of-week restrictions, and date exceptions.
     *
     * @return array<string, array<string, mixed>> The configuration field items
     */
    #[\Override]
    protected function configFieldItems(): array
    {
        // Start with the parent Date fieldtype's configuration
        $parentConfig = parent::configFieldItems();

        // Section key must match Statamic v6 base Date: "Boundaries & Limits".
        // Define fields to add before the options field
        $fieldsToAddBefore = [
            __('Boundaries & Limits') => [
                // Earliest Date section config
                'earliest_date_section' => [
                    'display' => __('Earliest Date'),
                    'type' => 'subsection',
                ],
                // Earliest Date Type config
                'earliest_date_type' => [
                    'display' => __('Earliest Date Type'),
                    'instructions' => __('Specify the type of earliest date allowed.'),
                    'type' => 'radio',
                    'options' => [
                        [
                            'key' => 'none',
                            'value' => __('None'),
                        ],
                        [
                            'key' => 'custom',
                            'value' => __('Custom Date'),
                        ],
                        [
                            'key' => 'today',
                            'value' => __('Current Date'),
                        ],
                        [
                            'key' => 'field',
                            'value' => __('Date From a Form Field'),
                        ],
                    ],
                    'default' => 'none',
                    'validate' => ['required'],
                ],
                // Computed Earliest Date config
                'earliest_date' => [],
                // Custom Earliest Date config
                'earliest_date_custom' => [
                    'display' => __('Custom Earliest Date'),
                    'instructions' => __('Specify a specific date to use as the earliest date allowed.'),
                    'type' => 'date',
                    'if' => [
                        'earliest_date_type' => 'custom',
                    ],
                    'validate' => [
                        'required_if:earliest_date_type,custom',
                        'exclude_unless:earliest_date_type,custom',
                    ],
                ],
                // Earliest Date Field config — a CP-only select of the blueprint's
                // other date fields (populated client-side; see DateFieldSelect).
                'earliest_date_field' => [
                    'display' => __('Earliest Date Field'),
                    'instructions' => __('Select a required date field from this blueprint to use as the earliest date allowed.'),
                    'type' => 'date_field_select',
                    'if' => [
                        'earliest_date_type' => 'field',
                    ],
                    'validate' => [
                        'required_if:earliest_date_type,field',
                        'exclude_unless:earliest_date_type,field',
                    ],
                ],
                // Earliest Date Modifier config
                'earliest_date_field_offset' => [
                    'display' => __('Earliest Date Field Offset'),
                    'instructions' => __('Offset the selected date by adding or subtracting days, weeks, months or years. For example, "+1 day" would exclude dates before or equal to the selected date.'),
                    'type' => 'grid',
                    'mode' => 'stacked',
                    'max_rows' => 1,
                    'add_row' => __('Add Offset'),
                    'fields' => [
                        [
                            'handle' => 'operator',
                            'field' => [
                                'display' => __('Operator'),
                                'type' => 'radio',
                                'options' => [
                                    [
                                        'key' => 'add',
                                        'value' => __('Add'),
                                    ],
                                    [
                                        'key' => 'subtract',
                                        'value' => __('Subtract'),
                                    ],
                                ],
                                'default' => 'add',
                                'width' => 33,
                                'validate' => [
                                    'required',
                                ],
                            ],
                        ],
                        [
                            'handle' => 'number',
                            'field' => [
                                'display' => __('Number'),
                                'type' => 'integer',
                                'default' => 0,
                                'width' => 33,
                                'validate' => [
                                    'required',
                                    'min:1',
                                ],
                            ],
                        ],
                        [
                            'handle' => 'unit',
                            'field' => [
                                'display' => __('Unit'),
                                'type' => 'radio',
                                'options' => [
                                    [
                                        'key' => 'day',
                                        'value' => __('Days'),
                                    ],
                                    [
                                        'key' => 'week',
                                        'value' => __('Weeks'),
                                    ],
                                    [
                                        'key' => 'month',
                                        'value' => __('Months'),
                                    ],
                                    [
                                        'key' => 'year',
                                        'value' => __('Years'),
                                    ],
                                ],
                                'default' => 'day',
                                'width' => 33,
                                'validate' => [
                                    'required',
                                ],
                            ],
                        ],
                    ],
                    'if' => [
                        'earliest_date_type' => 'field',
                    ],
                ],
                // Exclude Before Today config
                'earliest_date_exclude_before_today' => [
                    'display' => __('Exclude Before Current Date'),
                    'instructions' => __('Automatically exclude any dates that are before the current date.'),
                    'type' => 'toggle',
                    'default' => false,
                    'if' => [
                        'earliest_date_type' => 'contains_any custom,field',
                    ],
                ],
                // Current Date Offset config
                'earliest_date_today_offset' => [
                    'display' => __('Current Date Offset'),
                    'instructions' => __('Offset the current date by adding or subtracting days, weeks, months or years. For example, "+1 day" would exclude dates before or equal to the current date.'),
                    'type' => 'grid',
                    'mode' => 'stacked',
                    'max_rows' => 1,
                    'add_row' => __('Add Offset'),
                    'fields' => [
                        [
                            'handle' => 'operator',
                            'field' => [
                                'display' => __('Operator'),
                                'type' => 'radio',
                                'options' => [
                                    [
                                        'key' => 'add',
                                        'value' => __('Add'),
                                    ],
                                    [
                                        'key' => 'subtract',
                                        'value' => __('Subtract'),
                                    ],
                                ],
                                'default' => 'add',
                                'width' => 33,
                                'validate' => [
                                    'required',
                                ],
                            ],
                        ],
                        [
                            'handle' => 'number',
                            'field' => [
                                'display' => __('Number'),
                                'type' => 'integer',
                                'default' => 0,
                                'width' => 33,
                                'validate' => [
                                    'required',
                                    'min:1',
                                ],
                            ],
                        ],
                        [
                            'handle' => 'unit',
                            'field' => [
                                'display' => __('Unit'),
                                'type' => 'radio',
                                'options' => [
                                    [
                                        'key' => 'day',
                                        'value' => __('Days'),
                                    ],
                                    [
                                        'key' => 'week',
                                        'value' => __('Weeks'),
                                    ],
                                    [
                                        'key' => 'month',
                                        'value' => __('Months'),
                                    ],
                                    [
                                        'key' => 'year',
                                        'value' => __('Years'),
                                    ],
                                ],
                                'default' => 'day',
                                'width' => 33,
                                'validate' => [
                                    'required',
                                ],
                            ],
                        ],
                    ],
                    'if_any' => [
                        'earliest_date_type' => 'is today',
                        'earliest_date_exclude_before_today' => true,
                    ],
                ],
                // Latest Date section config
                'latest_date_section' => [
                    'display' => __('Latest Date'),
                    'type' => 'subsection',
                ],
                // Latest Date Type config
                'latest_date_type' => [
                    'display' => __('Latest Date Type'),
                    'instructions' => __('Specify the type of latest date allowed.'),
                    'type' => 'radio',
                    'options' => [
                        [
                            'key' => 'none',
                            'value' => __('None'),
                        ],
                        [
                            'key' => 'custom',
                            'value' => __('Custom Date'),
                        ],
                        [
                            'key' => 'today',
                            'value' => __('Current Date'),
                        ],
                        [
                            'key' => 'field',
                            'value' => __('Date From a Form Field'),
                        ],
                    ],
                    'default' => 'none',
                    'validate' => ['required'],
                ],
                // Computed Latest Date config
                'latest_date' => [],
                // Custom Latest Date config
                'latest_date_custom' => [
                    'display' => __('Custom Latest Date'),
                    'instructions' => __('Specify a specific date to use as the latest date allowed.'),
                    'type' => 'date',
                    'if' => [
                        'latest_date_type' => 'custom',
                    ],
                    'validate' => [
                        'required_if:latest_date_type,custom',
                        'exclude_unless:latest_date_type,custom',
                    ],
                ],
                // Latest Date Field config — a CP-only select of the blueprint's
                // other date fields (populated client-side; see DateFieldSelect).
                'latest_date_field' => [
                    'display' => __('Latest Date Field'),
                    'instructions' => __('Select a required date field from this blueprint to use as the latest date allowed.'),
                    'type' => 'date_field_select',
                    'if' => [
                        'latest_date_type' => 'field',
                    ],
                    'validate' => [
                        'required_if:latest_date_type,field',
                        'exclude_unless:latest_date_type,field',
                    ],
                ],
                // Latest Date Modifier config
                'latest_date_field_offset' => [
                    'display' => __('Latest Date Field Offset'),
                    'instructions' => __('Offset the selected date by adding or subtracting days, weeks, months or years. For example, "+1 day" would exclude dates after or equal to the the day after the selected date.'),
                    'type' => 'grid',
                    'mode' => 'stacked',
                    'max_rows' => 1,
                    'add_row' => __('Add Offset'),
                    'fields' => [
                        [
                            'handle' => 'operator',
                            'field' => [
                                'display' => __('Operator'),
                                'type' => 'radio',
                                'options' => [
                                    [
                                        'key' => 'add',
                                        'value' => __('Add'),
                                    ],
                                    [
                                        'key' => 'subtract',
                                        'value' => __('Subtract'),
                                    ],
                                ],
                                'default' => 'add',
                                'width' => 33,
                                'validate' => [
                                    'required',
                                ],
                            ],
                        ],
                        [
                            'handle' => 'number',
                            'field' => [
                                'display' => __('Number'),
                                'type' => 'integer',
                                'default' => 0,
                                'width' => 33,
                                'validate' => [
                                    'required',
                                    'min:1',
                                ],
                            ],
                        ],
                        [
                            'handle' => 'unit',
                            'field' => [
                                'display' => __('Unit'),
                                'type' => 'radio',
                                'options' => [
                                    [
                                        'key' => 'day',
                                        'value' => __('Days'),
                                    ],
                                    [
                                        'key' => 'week',
                                        'value' => __('Weeks'),
                                    ],
                                    [
                                        'key' => 'month',
                                        'value' => __('Months'),
                                    ],
                                    [
                                        'key' => 'year',
                                        'value' => __('Years'),
                                    ],
                                ],
                                'default' => 'day',
                                'width' => 33,
                                'validate' => [
                                    'required',
                                ],
                            ],
                        ],
                    ],
                    'if' => [
                        'latest_date_type' => 'field',
                    ],
                ],
            ],
        ];

        // Resolve display/app timezones for the instructions copy (scalars)
        $displayTimezoneConfig = config('statamic.system.display_timezone');
        $appTimezoneConfig = config('app.timezone');
        $displayTimezoneReplacement = is_scalar($displayTimezoneConfig)
            ? $displayTimezoneConfig
            : null;
        $appTimezoneReplacement = is_scalar($appTimezoneConfig)
            ? $appTimezoneConfig
            : null;

        // Define fields to add after the options field
        $fieldsToAddAfter = [
            __('Date & Time') => [
                'datetime_storage' => [
                    'display' => __('Datetime storage'),
                    'instructions' => __(
                        'When time is enabled: “Convert” interprets the picker in the Statamic display timezone (:display) and stores using the app timezone (:app). “Literal” stores the wall clock in :display exactly as entered (no conversion into :app).',
                        [
                            'display' => $displayTimezoneReplacement,
                            'app' => $appTimezoneReplacement,
                        ]
                    ),
                    'type' => 'select',
                    'options' => [
                        [
                            'key' => self::DATETIME_STORAGE_CONVERT,
                            'value' => __('Convert (display → app)'),
                        ],
                        [
                            'key' => self::DATETIME_STORAGE_LITERAL,
                            'value' => __('Literal (exact wall clock)'),
                        ],
                    ],
                    'default' => self::DATETIME_STORAGE_CONVERT,
                    'if' => [
                        'time_enabled' => true,
                    ],
                ],
            ],
            __('Boundaries & Limits') => [
                // Latest Date section config
                'boundaries_other_section' => [
                    'display' => __('Other Boundaries'),
                    'type' => 'subsection',
                ],
                // Days of Week config
                'days_of_week' => [
                    'display' => __('Days of Week'),
                    'instructions' => __('Select the days of the week that are valid.'),
                    'type' => 'checkboxes',
                    'options' => [
                        [
                            'key' => '0',
                            'value' => __('Sunday'),
                        ],
                        [
                            'key' => '1',
                            'value' => __('Monday'),
                        ],
                        [
                            'key' => '2',
                            'value' => __('Tuesday'),
                        ],
                        [
                            'key' => '3',
                            'value' => __('Wednesday'),
                        ],
                        [
                            'key' => '4',
                            'value' => __('Thursday'),
                        ],
                        [
                            'key' => '5',
                            'value' => __('Friday'),
                        ],
                        [
                            'key' => '6',
                            'value' => __('Saturday'),
                        ],
                    ],
                    'default' => [
                        '0',
                        '1',
                        '2',
                        '3',
                        '4',
                        '5',
                        '6',
                    ],
                    'validate' => ['required'],
                ],
                // Date Exceptions config
                'date_exceptions' => [
                    'display' => __('Date Exceptions'),
                    'instructions' => __('Specify any dates that should be excepted from the above rules.'),
                    'type' => 'replicator',
                    'button_label' => 'Add Date Exception',
                    'sets' => [
                        'new_set_group' => [
                            'display' => 'New Set Group',
                            'sets' => [
                                'single_date' => [
                                    'display' => 'Single Date',
                                    'icon' => 'calendar-date',
                                    'fields' => [
                                        [
                                            'handle' => 'item',
                                            'field' => [
                                                'display' => __('Date'),
                                                'type' => 'date',
                                                'validate' => [
                                                    'required',
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                                'date_range' => [
                                    'display' => 'Date Range',
                                    'icon' => 'calendar-date',
                                    'fields' => [
                                        [
                                            'handle' => 'item',
                                            'field' => [
                                                'display' => __('Date Range'),
                                                'type' => 'date',
                                                'mode' => 'range',
                                                'validate' => [
                                                    'required',
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        // Define modifications to the parent config
        $fieldModifications = [
            // Hide `earliest_date`
            'earliest_date' => [
                'visibility' => 'hidden', // TODO: Compute the value of this field based on a combination of: `earliest_date_type`, `earliest_date_field`, `earliest_date_custom`, `earliest_date_offset`, `earliest_date_exclude_before_today`, `earliest_date_exclude_before_today_offset`
            ],
            // Hide `latest_date`
            'latest_date' => [
                'visibility' => 'hidden', // TODO: Compute the value of this field based on a combination of: latest_date_type, latest_date_field, latest_date_custom, latest_date_offset
            ],
        ];

        // Apply field modifications to hide computed date fields
        /** @var array<array<string, mixed>> $parentConfig */
        $parentConfig = $this->modifyExistingFields(
            $fieldModifications,
            $parentConfig
        );

        // ---
        // Inject the custom fields into the existing config sections using the trait
        // method. Add boundary configuration fields before the options field, and
        // additional boundary options (days of week, date exceptions) after.
        // ---
        /** @var array<string, array<string, mixed>> $configFieldItems */
        $configFieldItems = $this->addFieldsToExistingSectionsWithMultiplePositions(
            $fieldsToAddBefore,
            $fieldsToAddAfter,
            $parentConfig
        );

        return $configFieldItems;
    }
}
