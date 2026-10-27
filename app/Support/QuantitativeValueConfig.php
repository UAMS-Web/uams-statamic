<?php

declare(strict_types=1);

namespace App\Support;

use App\Fieldtypes\QuantitativeValue;

/**
 * Normalizes blueprint configuration for the QuantitativeValue fieldtype.
 *
 * @see QuantitativeValue
 */
final readonly class QuantitativeValueConfig
{
    public const string MODE_NUMBER = 'number';

    public const string MODE_DURATION = 'duration';

    public const string NUMBER_INTEGER = 'integer';

    public const string NUMBER_FLOAT = 'float';

    public const string STORAGE_NESTED = 'nested';

    public const string STORAGE_FLAT = 'flat';

    public const string MAJOR_VALUE = 'value';

    public const string MAJOR_MIN = 'min';

    public const string MAJOR_MAX = 'max';

    /** @var list<string> */
    public const array MAJOR_KEYS = [
        self::MAJOR_VALUE,
        self::MAJOR_MIN,
        self::MAJOR_MAX,
    ];

    /** @var list<string> */
    public const array UNIT_KEYS = [
        'years',
        'months',
        'weeks',
        'days',
        'hours',
        'minutes',
        'seconds',
    ];

    /**
     * @param  array<string, mixed>  $config  Field config from the blueprint
     */
    public static function from(array $config): self
    {
        $mode = $config['mode'] ?? self::MODE_NUMBER;
        if ($mode !== self::MODE_NUMBER && $mode !== self::MODE_DURATION) {
            $mode = self::MODE_NUMBER;
        }

        $numberMode = $config['number_mode'] ?? self::NUMBER_INTEGER;
        if ($numberMode !== self::NUMBER_INTEGER && $numberMode !== self::NUMBER_FLOAT) {
            $numberMode = self::NUMBER_INTEGER;
        }

        /** @var list<string>|null $rawMajors */
        $rawMajors = $config['major_components'] ?? null;
        $majors = self::normalizeMajors($rawMajors);

        /** @var list<string>|null $rawUnits */
        $rawUnits = $config['duration_units'] ?? null;
        $units = self::normalizeUnits($rawUnits);

        $numberSingleStorage = $config['number_single_storage'] ?? self::STORAGE_NESTED;
        if ($numberSingleStorage !== self::STORAGE_NESTED && $numberSingleStorage !== self::STORAGE_FLAT) {
            $numberSingleStorage = self::STORAGE_NESTED;
        }

        $durationPresentation = $config['duration_single_unit_presentation'] ?? self::STORAGE_NESTED;
        if ($durationPresentation !== self::STORAGE_NESTED && $durationPresentation !== self::STORAGE_FLAT) {
            $durationPresentation = self::STORAGE_NESTED;
        }

        return new self(
            mode: $mode,
            numberMode: $numberMode,
            majors: $majors,
            units: $units,
            numberSingleStorage: $numberSingleStorage,
            durationSingleUnitPresentation: $durationPresentation,
            unitCodeEnabled: (bool) ($config['unit_code_enabled'] ?? false),
            unitTextEnabled: (bool) ($config['unit_text_enabled'] ?? false),
            unitCodeRequired: (bool) ($config['unit_code_required'] ?? false),
            unitTextRequired: (bool) ($config['unit_text_required'] ?? false),
            valueRequired: (bool) ($config['value_required'] ?? false),
            minRequired: (bool) ($config['min_required'] ?? false),
            maxRequired: (bool) ($config['max_required'] ?? false),
            unitRequired: [
                'years' => (bool) ($config['unit_years_required'] ?? false),
                'months' => (bool) ($config['unit_months_required'] ?? false),
                'weeks' => (bool) ($config['unit_weeks_required'] ?? false),
                'days' => (bool) ($config['unit_days_required'] ?? false),
                'hours' => (bool) ($config['unit_hours_required'] ?? false),
                'minutes' => (bool) ($config['unit_minutes_required'] ?? false),
                'seconds' => (bool) ($config['unit_seconds_required'] ?? false),
            ],
        );
    }

    /**
     * @param  list<string>|null  $raw
     * @return list<string>
     */
    public static function normalizeMajors(?array $raw): array
    {
        if ($raw === null || $raw === []) {
            return [self::MAJOR_VALUE, self::MAJOR_MIN, self::MAJOR_MAX];
        }

        $out = [];

        foreach (self::MAJOR_KEYS as $key) {
            if (\in_array($key, $raw, true)) {
                $out[] = $key;
            }
        }

        return $out !== [] ? $out : [self::MAJOR_VALUE];
    }

    /**
     * @param  list<string>|null  $raw
     * @return list<string>
     */
    public static function normalizeUnits(?array $raw): array
    {
        $defaults = ['years', 'months', 'days'];

        if ($raw === null || $raw === []) {
            return $defaults;
        }

        $out = [];

        foreach (self::UNIT_KEYS as $key) {
            if (\in_array($key, $raw, true)) {
                $out[] = $key;
            }
        }

        return $out !== [] ? $out : $defaults;
    }

    public function __construct(
        public string $mode,
        public string $numberMode,
        /** @var list<string> */
        public array $majors,
        /** @var list<string> */
        public array $units,
        public string $numberSingleStorage,
        public string $durationSingleUnitPresentation,
        public bool $unitCodeEnabled,
        public bool $unitTextEnabled,
        public bool $unitCodeRequired,
        public bool $unitTextRequired,
        public bool $valueRequired,
        public bool $minRequired,
        public bool $maxRequired,
        /** @var array<string, bool> */
        public array $unitRequired,
    ) {}

    public function isNumberMode(): bool
    {
        return $this->mode === self::MODE_NUMBER;
    }

    public function isDurationMode(): bool
    {
        return $this->mode === self::MODE_DURATION;
    }

    public function majorCount(): int
    {
        return \count($this->majors);
    }

    public function usesIntegerNumbers(): bool
    {
        return $this->numberMode === self::NUMBER_INTEGER;
    }

    /**
     * Root stored value is a scalar (number mode, single major, flat storage).
     */
    public function storesNumberAsFlatScalar(): bool
    {
        return $this->isNumberMode()
            && $this->majorCount() === 1
            && $this->numberSingleStorage === self::STORAGE_FLAT
            && ! $this->unitCodeEnabled
            && ! $this->unitTextEnabled;
    }

    /**
     * Number mode: optional schema.org-style unit code subfield (text).
     */
    public function usesNumberUnitCode(): bool
    {
        return $this->isNumberMode() && $this->unitCodeEnabled;
    }

    /**
     * Number mode: optional schema.org-style unit text subfield (text).
     */
    public function usesNumberUnitText(): bool
    {
        return $this->isNumberMode() && $this->unitTextEnabled;
    }

    /**
     * Flat presentation = inline labels (single duration unit).
     */
    public function durationUsesFlatPresentation(): bool
    {
        return $this->isDurationMode()
            && \count($this->units) === 1
            && $this->durationSingleUnitPresentation === self::STORAGE_FLAT;
    }

    public function majorEnabled(string $major): bool
    {
        return \in_array($major, $this->majors, true);
    }

    public function unitEnabled(string $unit): bool
    {
        return \in_array($unit, $this->units, true);
    }

    public function unitRequiresValidation(string $unit): bool
    {
        return ($this->unitRequired[$unit] ?? false) === true;
    }

    public function majorRequiresValidation(string $major): bool
    {
        return match ($major) {
            self::MAJOR_VALUE => $this->valueRequired,
            self::MAJOR_MIN => $this->minRequired,
            self::MAJOR_MAX => $this->maxRequired,
            default => false,
        };
    }
}
