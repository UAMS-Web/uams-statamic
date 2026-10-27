<?php

declare(strict_types=1);

namespace App\Fieldtypes;

use App\Support\QuantitativeValueConfig;
use App\Support\QuantitativeValueDurationTotalYears;
use Statamic\Fields\Field;
use Statamic\Fields\Fieldtype;
use Statamic\Support\Arr;

/**
 * Quantitative value: numeric range and/or duration-style units (schema.org QuantitativeValue-style).
 *
 * Number mode can store a scalar at the field root when one major component is selected and flat storage
 * is enabled; duration mode always nests time units under value/min/max objects.
 */
class QuantitativeValue extends Fieldtype
{
    /**
     * @var string
     */
    protected static $title = 'statamic::fieldtypes.quantitative_value.title';

    /**
     * @var string
     */
    protected $component = 'quantitative_value';

    /** @var array<int, string> */
    protected $categories = [
        'structured',
        'number',
    ];

    /** @var array<int, string> */
    protected $keywords = [
        'quantitative',
        'quantitative value',
        'schema.org',
        'range',
        'min',
        'max',
        'value',
        'duration',
        'integer',
        'float',
        'number',
    ];

    /**
     * Whether the value can be localized.
     *
     * @var bool
     */
    protected $localizable = true;

    #[\Override]
    public function icon(): string
    {
        return 'calculator';
    }

    /**
     * @return list<array{display: string, fields: array<string, mixed>}>
     */
    #[\Override]
    protected function configFieldItems(): array
    {
        return [
            [
                'display' => __('statamic::fieldtypes.quantitative_value.config.section_mode'),
                'fields' => [
                    'mode' => [
                        'display' => __('statamic::fieldtypes.quantitative_value.config.mode_display'),
                        'type' => 'radio',
                        'options' => [
                            QuantitativeValueConfig::MODE_NUMBER => __('statamic::fieldtypes.quantitative_value.config.mode_option_number'),
                            QuantitativeValueConfig::MODE_DURATION => __('statamic::fieldtypes.quantitative_value.config.mode_option_duration'),
                        ],
                        'default' => QuantitativeValueConfig::MODE_NUMBER,
                    ],
                    'number_mode' => [
                        'display' => __('statamic::fieldtypes.quantitative_value.config.number_mode_display'),
                        'type' => 'radio',
                        'options' => [
                            QuantitativeValueConfig::NUMBER_INTEGER => __('statamic::fieldtypes.quantitative_value.config.number_mode_option_integer'),
                            QuantitativeValueConfig::NUMBER_FLOAT => __('statamic::fieldtypes.quantitative_value.config.number_mode_option_float'),
                        ],
                        'default' => QuantitativeValueConfig::NUMBER_INTEGER,
                        'if' => [
                            'mode' => 'equals '.QuantitativeValueConfig::MODE_NUMBER,
                        ],
                    ],
                    'unit_code_enabled' => [
                        'display' => __('statamic::fieldtypes.quantitative_value.config.unit_code_enabled_display'),
                        'instructions' => __('statamic::fieldtypes.quantitative_value.config.unit_code_enabled_instructions'),
                        'type' => 'toggle',
                        'default' => false,
                        'if' => [
                            'mode' => 'equals '.QuantitativeValueConfig::MODE_NUMBER,
                        ],
                    ],
                    'unit_code_required' => [
                        'display' => __('statamic::fieldtypes.quantitative_value.config.unit_code_required_display'),
                        'type' => 'toggle',
                        'default' => false,
                        'if' => [
                            'mode' => 'equals '.QuantitativeValueConfig::MODE_NUMBER,
                            'unit_code_enabled' => true,
                        ],
                    ],
                    'unit_text_enabled' => [
                        'display' => __('statamic::fieldtypes.quantitative_value.config.unit_text_enabled_display'),
                        'instructions' => __('statamic::fieldtypes.quantitative_value.config.unit_text_enabled_instructions'),
                        'type' => 'toggle',
                        'default' => false,
                        'if' => [
                            'mode' => 'equals '.QuantitativeValueConfig::MODE_NUMBER,
                        ],
                    ],
                    'unit_text_required' => [
                        'display' => __('statamic::fieldtypes.quantitative_value.config.unit_text_required_display'),
                        'type' => 'toggle',
                        'default' => false,
                        'if' => [
                            'mode' => 'equals '.QuantitativeValueConfig::MODE_NUMBER,
                            'unit_text_enabled' => true,
                        ],
                    ],
                ],
            ],
            [
                'display' => __('statamic::fieldtypes.quantitative_value.config.section_components'),
                'fields' => [
                    'major_components' => [
                        'display' => __('statamic::fieldtypes.quantitative_value.config.major_components_display'),
                        'instructions' => __('statamic::fieldtypes.quantitative_value.config.major_components_instructions'),
                        'type' => 'checkboxes',
                        'options' => [
                            QuantitativeValueConfig::MAJOR_VALUE => __('statamic::fieldtypes.quantitative_value.config.major_option_value'),
                            QuantitativeValueConfig::MAJOR_MIN => __('statamic::fieldtypes.quantitative_value.config.major_option_min'),
                            QuantitativeValueConfig::MAJOR_MAX => __('statamic::fieldtypes.quantitative_value.config.major_option_max'),
                        ],
                        'default' => QuantitativeValueConfig::MAJOR_KEYS,
                        'validate' => [
                            'required',
                            'array',
                            'min:1',
                        ],
                    ],
                    'value_required' => [
                        'display' => __('statamic::fieldtypes.quantitative_value.config.value_required_display'),
                        'type' => 'toggle',
                        'default' => false,
                        'if' => [
                            'major_components' => 'contains_any '.QuantitativeValueConfig::MAJOR_VALUE,
                        ],
                    ],
                    'min_required' => [
                        'display' => __('statamic::fieldtypes.quantitative_value.config.min_required_display'),
                        'type' => 'toggle',
                        'default' => false,
                        'if' => [
                            'major_components' => 'contains_any '.QuantitativeValueConfig::MAJOR_MIN,
                        ],
                    ],
                    'max_required' => [
                        'display' => __('statamic::fieldtypes.quantitative_value.config.max_required_display'),
                        'type' => 'toggle',
                        'default' => false,
                        'if' => [
                            'major_components' => 'contains_any '.QuantitativeValueConfig::MAJOR_MAX,
                        ],
                    ],
                    'number_single_storage' => [
                        'display' => __('statamic::fieldtypes.quantitative_value.config.number_single_storage_display'),
                        'instructions' => __('statamic::fieldtypes.quantitative_value.config.number_single_storage_instructions'),
                        'type' => 'radio',
                        'options' => [
                            QuantitativeValueConfig::STORAGE_NESTED => __('statamic::fieldtypes.quantitative_value.config.number_single_storage_option_nested'),
                            QuantitativeValueConfig::STORAGE_FLAT => __('statamic::fieldtypes.quantitative_value.config.number_single_storage_option_flat'),
                        ],
                        'default' => QuantitativeValueConfig::STORAGE_NESTED,
                        'if' => [
                            'mode' => 'equals '.QuantitativeValueConfig::MODE_NUMBER,
                            'major_components' => 'custom selectionCount:equals,1',
                        ],
                    ],
                ],
            ],
            [
                'display' => __('statamic::fieldtypes.quantitative_value.config.section_duration_units'),
                'fields' => [
                    'duration_units' => [
                        'display' => __('statamic::fieldtypes.quantitative_value.config.duration_units_display'),
                        'instructions' => __('statamic::fieldtypes.quantitative_value.config.duration_units_instructions'),
                        'type' => 'checkboxes',
                        'options' => [
                            'years' => __('statamic::fieldtypes.quantitative_value.config.duration_unit_years'),
                            'months' => __('statamic::fieldtypes.quantitative_value.config.duration_unit_months'),
                            'weeks' => __('statamic::fieldtypes.quantitative_value.config.duration_unit_weeks'),
                            'days' => __('statamic::fieldtypes.quantitative_value.config.duration_unit_days'),
                            'hours' => __('statamic::fieldtypes.quantitative_value.config.duration_unit_hours'),
                            'minutes' => __('statamic::fieldtypes.quantitative_value.config.duration_unit_minutes'),
                            'seconds' => __('statamic::fieldtypes.quantitative_value.config.duration_unit_seconds'),
                        ],
                        'default' => [
                            'years',
                            'months',
                            'days',
                        ],
                        'validate' => [
                            'required',
                            'array',
                            'min:1',
                        ],
                        'if' => [
                            'mode' => 'equals '.QuantitativeValueConfig::MODE_DURATION,
                        ],
                    ],
                    'unit_years_required' => [
                        'display' => __('statamic::fieldtypes.quantitative_value.config.unit_years_required_display'),
                        'type' => 'toggle',
                        'default' => false,
                        'if' => [
                            'mode' => 'equals '.QuantitativeValueConfig::MODE_DURATION,
                            'duration_units' => 'custom selectionContains:years',
                        ],
                    ],
                    'unit_months_required' => [
                        'display' => __('statamic::fieldtypes.quantitative_value.config.unit_months_required_display'),
                        'type' => 'toggle',
                        'default' => false,
                        'if' => [
                            'mode' => 'equals '.QuantitativeValueConfig::MODE_DURATION,
                            'duration_units' => 'custom selectionContains:months',
                        ],
                    ],
                    'unit_weeks_required' => [
                        'display' => __('statamic::fieldtypes.quantitative_value.config.unit_weeks_required_display'),
                        'type' => 'toggle',
                        'default' => false,
                        'if' => [
                            'mode' => 'equals '.QuantitativeValueConfig::MODE_DURATION,
                            'duration_units' => 'custom selectionContains:weeks',
                        ],
                    ],
                    'unit_days_required' => [
                        'display' => __('statamic::fieldtypes.quantitative_value.config.unit_days_required_display'),
                        'type' => 'toggle',
                        'default' => false,
                        'if' => [
                            'mode' => 'equals '.QuantitativeValueConfig::MODE_DURATION,
                            'duration_units' => 'custom selectionContains:days',
                        ],
                    ],
                    'unit_hours_required' => [
                        'display' => __('statamic::fieldtypes.quantitative_value.config.unit_hours_required_display'),
                        'type' => 'toggle',
                        'default' => false,
                        'if' => [
                            'mode' => 'equals '.QuantitativeValueConfig::MODE_DURATION,
                            'duration_units' => 'custom selectionContains:hours',
                        ],
                    ],
                    'unit_minutes_required' => [
                        'display' => __('statamic::fieldtypes.quantitative_value.config.unit_minutes_required_display'),
                        'type' => 'toggle',
                        'default' => false,
                        'if' => [
                            'mode' => 'equals '.QuantitativeValueConfig::MODE_DURATION,
                            'duration_units' => 'custom selectionContains:minutes',
                        ],
                    ],
                    'unit_seconds_required' => [
                        'display' => __('statamic::fieldtypes.quantitative_value.config.unit_seconds_required_display'),
                        'type' => 'toggle',
                        'default' => false,
                        'if' => [
                            'mode' => 'equals '.QuantitativeValueConfig::MODE_DURATION,
                            'duration_units' => 'custom selectionContains:seconds',
                        ],
                    ],
                    'duration_single_unit_presentation' => [
                        'display' => __('statamic::fieldtypes.quantitative_value.config.duration_single_unit_presentation_display'),
                        'instructions' => __('statamic::fieldtypes.quantitative_value.config.duration_single_unit_presentation_instructions'),
                        'type' => 'radio',
                        'options' => [
                            QuantitativeValueConfig::STORAGE_NESTED => __('statamic::fieldtypes.quantitative_value.config.duration_single_unit_option_grouped'),
                            QuantitativeValueConfig::STORAGE_FLAT => __('statamic::fieldtypes.quantitative_value.config.duration_single_unit_option_inline'),
                        ],
                        'default' => QuantitativeValueConfig::STORAGE_NESTED,
                        'if' => [
                            'mode' => 'equals '.QuantitativeValueConfig::MODE_DURATION,
                            'duration_units' => 'custom selectionCount:equals,1',
                        ],
                    ],
                ],
            ],
        ];
    }

    #[\Override]
    public function defaultValue(): mixed
    {
        /** @var array<string, mixed> $config */
        $config = $this->field()?->config() ?? [];
        $c = QuantitativeValueConfig::from($config);

        if ($c->storesNumberAsFlatScalar()) {
            return null;
        }

        return [];
    }

    /**
     * @return array{config: array<string, mixed>}
     */
    #[\Override]
    public function preload(): array
    {
        /** @var array<string, mixed> $config */
        $config = $this->field()?->config() ?? [];
        $c = QuantitativeValueConfig::from($config);

        return [
            'config' => [
                'mode' => $c->mode,
                'number_mode' => $c->numberMode,
                'major_components' => $c->majors,
                'duration_units' => $c->units,
                'number_single_storage' => $c->numberSingleStorage,
                'duration_single_unit_presentation' => $c->durationSingleUnitPresentation,
                'value_required' => $c->valueRequired,
                'min_required' => $c->minRequired,
                'max_required' => $c->maxRequired,
                'unit_required' => $c->unitRequired,
                'stores_number_as_flat_scalar' => $c->storesNumberAsFlatScalar(),
                'duration_flat_presentation' => $c->durationUsesFlatPresentation(),
                'duration_grouped_sections' => $c->isDurationMode()
                    && (
                        \count($c->units) > 1
                        || $c->durationSingleUnitPresentation === QuantitativeValueConfig::STORAGE_NESTED
                    ),
                'unit_code_enabled' => $c->usesNumberUnitCode(),
                'unit_text_enabled' => $c->usesNumberUnitText(),
                'unit_code_instructions' => __('statamic::fieldtypes.quantitative_value.config.unit_code_enabled_instructions'),
                'unit_text_instructions' => __('statamic::fieldtypes.quantitative_value.config.unit_text_enabled_instructions'),
            ],
        ];
    }

    #[\Override]
    public function process(mixed $data): mixed
    {
        /** @var array<string, mixed> $config */
        $config = $this->field()?->config() ?? [];
        $c = QuantitativeValueConfig::from($config);

        if ($c->isNumberMode()) {
            return $this->processNumber($data, $c);
        }

        return $this->processDuration($data, $c);
    }

    /**
     * @param  mixed  $data  Raw submitted value (array<string, mixed>|float|int|string|null); non-arrays are discarded.
     */
    private function processNumber(mixed $data, QuantitativeValueConfig $c): mixed
    {
        if (! \is_array($data)) {
            $data = [];
        }

        $out = [];

        foreach ($c->majors as $major) {
            if (! \array_key_exists($major, $data)) {
                continue;
            }

            $v = $data[$major];
            if ($v === null) {
                continue;
            }

            if ($v === '') {
                continue;
            }

            if (\is_array($v)) {
                continue;
            }

            /** @var scalar $v */
            $out[$major] = $c->usesIntegerNumbers() ? (int) $v : (float) $v;
        }

        if ($c->usesNumberUnitCode()) {
            $uc = $data['unit_code'] ?? null;
            if (\is_string($uc)) {
                $trimmed = trim($uc);
                // Arr::removeNullValues() below also drops an empty string (EmptyStringToNotEmpty is equivalent)
                // @pest-mutate-ignore: EmptyStringToNotEmpty
                if ($trimmed !== '') {
                    $out['unit_code'] = $trimmed;
                }
            } elseif ($uc !== null) {
                /** @var scalar $uc */
                $out['unit_code'] = (string) $uc;
            }
        }

        if ($c->usesNumberUnitText()) {
            $ut = $data['unit_text'] ?? null;
            if (\is_string($ut)) {
                $trimmed = trim($ut);
                // Arr::removeNullValues() below also drops an empty string (EmptyStringToNotEmpty is equivalent)
                // @pest-mutate-ignore: EmptyStringToNotEmpty
                if ($trimmed !== '') {
                    $out['unit_text'] = $trimmed;
                }
            } elseif ($ut !== null) {
                /** @var scalar $ut */
                $out['unit_text'] = (string) $ut;
            }
        }

        $out = Arr::removeNullValues($out);

        if ($c->storesNumberAsFlatScalar() && \count($out) === 1) {
            return reset($out);
        }

        return $out;
    }

    /**
     * @param  mixed  $data  Raw submitted value (array<string, mixed>|null); non-arrays are discarded.
     * @return array<string, array<string, int>>
     */
    private function processDuration(mixed $data, QuantitativeValueConfig $c): array
    {
        if (! \is_array($data)) {
            $data = [];
        }

        $out = [];

        foreach (QuantitativeValueConfig::MAJOR_KEYS as $major) {
            if (! $c->majorEnabled($major)) {
                continue;
            }

            if (! isset($data[$major])) {
                continue;
            }

            if (! \is_array($data[$major])) {
                continue;
            }

            $slice = [];

            foreach ($c->units as $unit) {
                if (! \array_key_exists($unit, $data[$major])) {
                    continue;
                }

                $v = $data[$major][$unit];
                if ($v === null) {
                    continue;
                }

                if ($v === '') {
                    continue;
                }

                /** @var scalar $v */
                $slice[$unit] = (int) $v;
            }

            if ($slice !== []) {
                $out[$major] = $slice;
            }
        }

        /** @var array<string, array<string, int>> $cleaned */
        $cleaned = Arr::removeNullValues($out);

        return $cleaned;
    }

    #[\Override]
    public function preProcess(mixed $data): mixed
    {
        /** @var array<string, mixed> $config */
        $config = $this->field()?->config() ?? [];
        $c = QuantitativeValueConfig::from($config);

        if ($c->isNumberMode()) {
            if ($c->storesNumberAsFlatScalar() && ($data !== null && $data !== [] && ! \is_array($data))) {
                $only = $c->majors[0] ?? QuantitativeValueConfig::MAJOR_VALUE;

                return [$only => $data];
            }

            if (! \is_array($data)) {
                return [];
            }

            $pre = [];

            foreach ($c->majors as $major) {
                $pre[$major] = $data[$major] ?? null;
            }

            if ($c->usesNumberUnitCode()) {
                $pre['unit_code'] = $data['unit_code'] ?? null;
            }

            if ($c->usesNumberUnitText()) {
                $pre['unit_text'] = $data['unit_text'] ?? null;
            }

            return $pre;
        }

        if (! \is_array($data)) {
            $data = [];
        }

        $pre = [];

        foreach ($c->majors as $major) {
            $pre[$major] = [];

            $section = $data[$major] ?? null;
            $section = \is_array($section) ? $section : [];

            foreach ($c->units as $unit) {
                $pre[$major][$unit] = $section[$unit] ?? null;
            }
        }

        return $pre;
    }

    /**
     * @return list<string>
     */
    #[\Override]
    public function rules(): array
    {
        /** @var array<string, mixed> $config */
        $config = $this->field()?->config() ?? [];
        $c = QuantitativeValueConfig::from($config);

        if ($c->storesNumberAsFlatScalar()) {
            $parentRequired = $this->publishBlueprintRequiresValue();
            $major = $c->majors[0] ?? QuantitativeValueConfig::MAJOR_VALUE;
            $wantRequired = $parentRequired && $c->majorRequiresValidation($major);
            $base = $c->usesIntegerNumbers() ? 'integer' : 'numeric';

            return $wantRequired ? ['required', $base] : ['nullable', $base];
        }

        return ['array'];
    }

    /**
     * @return array<string, string|array<int, mixed>>
     */
    #[\Override]
    public function extraRules(): array
    {
        // The field is dereferenced below, so a missing field fails either way (RemoveNullSafeOperator is equivalent)
        // @pest-mutate-ignore: RemoveNullSafeOperator
        /** @var array<string, mixed> $config */
        $config = $this->field()?->config() ?? [];
        $c = QuantitativeValueConfig::from($config);
        /** @var Field $field */
        $field = $this->field();
        $handle = $field->handle();
        $prefix = \is_scalar($handle) ? (string) $handle : '';
        $parentRequired = $this->publishBlueprintRequiresValue();

        if ($c->storesNumberAsFlatScalar()) {
            // Root rules live in rules() only so Field::rules merge is not overwritten.
            return [];
        }

        if ($c->isNumberMode()) {
            return $this->numberNestedExtraRules($c, $prefix, $parentRequired);
        }

        return $this->durationExtraRules($c, $prefix, $parentRequired);
    }

    /**
     * Whether the publish form marks this field required (validate rules), without
     * calling Field::isRequired() (which would recurse into the fieldtype rules).
     */
    private function publishBlueprintRequiresValue(): bool
    {
        // Only rules() for a flat number and extraRules() call this, and both need a bound field (RemoveNullSafeOperator is equivalent)
        // @pest-mutate-ignore: RemoveNullSafeOperator
        $config = $this->field()?->config() ?? [];

        if (! empty($config['required'])) {
            return true;
        }

        $validate = $config['validate'] ?? [];

        if (\is_string($validate)) {
            $validate = [$validate];
        }

        if (! \is_array($validate)) {
            return false;
        }

        return \in_array('required', $validate, true);
    }

    /**
     * @return array<string, string|array<int, mixed>>
     */
    private function numberNestedExtraRules(
        QuantitativeValueConfig $c,
        string $prefix,
        bool $parentRequired
    ): array {
        $rules = [];

        foreach ($c->majors as $major) {
            $path = sprintf('%s.%s', $prefix, $major);
            $wantRequired = $parentRequired && $c->majorRequiresValidation($major);
            $base = $c->usesIntegerNumbers() ? 'integer' : 'numeric';
            $rules[$path] = ($wantRequired ? 'required|' : 'nullable|').$base;
        }

        if ($c->usesNumberUnitCode()) {
            $wantRequired = $parentRequired && $c->unitCodeRequired;
            $rules[$prefix.'.unit_code'] = ($wantRequired ? 'required|' : 'nullable|').'string';
        }

        if ($c->usesNumberUnitText()) {
            $wantRequired = $parentRequired && $c->unitTextRequired;
            $rules[$prefix.'.unit_text'] = ($wantRequired ? 'required|' : 'nullable|').'string';
        }

        return $rules;
    }

    /**
     * @return array<string, string|array<int, mixed>>
     */
    private function durationExtraRules(
        QuantitativeValueConfig $c,
        string $prefix,
        bool $parentRequired
    ): array {
        $rules = [];

        foreach ($c->majors as $major) {
            foreach ($c->units as $unit) {
                $path = sprintf('%s.%s.%s', $prefix, $major, $unit);
                $wantRequired = $parentRequired
                    && $c->majorRequiresValidation($major)
                    && $c->unitRequiresValidation($unit);
                $rules[$path] = ($wantRequired ? 'required|' : 'nullable|').'integer';
            }
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    #[\Override]
    public function extraValidationAttributes(): array
    {
        // The field is dereferenced below, so a missing field fails either way (RemoveNullSafeOperator is equivalent)
        // @pest-mutate-ignore: RemoveNullSafeOperator
        /** @var array<string, mixed> $config */
        $config = $this->field()?->config() ?? [];
        $c = QuantitativeValueConfig::from($config);
        /** @var Field $field */
        $field = $this->field();
        $handle = $field->handle();
        // The prefix is only interpolated or used as an array key, and PHP turns a
        // numeric handle into the same key either way (RemoveStringCast is equivalent)
        // @pest-mutate-ignore: RemoveStringCast
        $prefix = \is_scalar($handle) ? (string) $handle : '';
        $attrs = [];

        if ($c->storesNumberAsFlatScalar()) {
            $major = $c->majors[0] ?? QuantitativeValueConfig::MAJOR_VALUE;

            // Each label is a translated string, so the cast cannot change it (RemoveStringCast is equivalent)
            // @pest-mutate-ignore: RemoveStringCast
            return [
                $prefix => (string) match ($major) {
                    QuantitativeValueConfig::MAJOR_MIN => __('statamic::fieldtypes.quantitative_value.field.minimum'),
                    QuantitativeValueConfig::MAJOR_MAX => __('statamic::fieldtypes.quantitative_value.field.maximum'),
                    default => __('statamic::fieldtypes.quantitative_value.field.value'),
                },
            ];
        }

        foreach ($c->majors as $major) {
            $majorLabel = match ($major) {
                QuantitativeValueConfig::MAJOR_MIN => __('statamic::fieldtypes.quantitative_value.field.minimum'),
                QuantitativeValueConfig::MAJOR_MAX => __('statamic::fieldtypes.quantitative_value.field.maximum'),
                default => __('statamic::fieldtypes.quantitative_value.field.value'),
            };

            if ($c->isNumberMode()) {
                $attrs[sprintf('%s.%s', $prefix, $major)] = $majorLabel;

                continue;
            }

            foreach ($c->units as $unit) {
                $unitLabel = __('statamic::fieldtypes.quantitative_value.field.duration_unit.'.$unit);
                $attrs[sprintf('%s.%s.%s', $prefix, $major, $unit)] = __('statamic::fieldtypes.quantitative_value.validation.duration_subfield', [
                    'major' => $majorLabel,
                    'unit' => $unitLabel,
                ]);
            }
        }

        if ($c->isNumberMode()) {
            if ($c->usesNumberUnitCode()) {
                $attrs[$prefix.'.unit_code'] = __('statamic::fieldtypes.quantitative_value.field.unit_code');
            }

            if ($c->usesNumberUnitText()) {
                $attrs[$prefix.'.unit_text'] = __('statamic::fieldtypes.quantitative_value.field.unit_text');
            }
        }

        return $attrs;
    }

    #[\Override]
    public function preProcessValidatable(mixed $value): mixed
    {
        /** @var array<string, mixed> $config */
        $config = $this->field()?->config() ?? [];
        $c = QuantitativeValueConfig::from($config);

        if ($c->storesNumberAsFlatScalar()) {
            return $value;
        }

        if ($c->isNumberMode()) {
            $value = \is_array($value) ? $value : [];

            $merged = [];

            foreach ($c->majors as $major) {
                $merged[$major] = $value[$major] ?? null;
            }

            if ($c->usesNumberUnitCode()) {
                $merged['unit_code'] = $value['unit_code'] ?? null;
            }

            if ($c->usesNumberUnitText()) {
                $merged['unit_text'] = $value['unit_text'] ?? null;
            }

            return $merged;
        }

        $value = \is_array($value) ? $value : [];
        $merged = [];

        foreach ($c->majors as $major) {
            $merged[$major] = [];

            $section = $value[$major] ?? null;
            $section = \is_array($section) ? $section : [];

            foreach ($c->units as $unit) {
                $merged[$major][$unit] = $section[$unit] ?? null;
            }
        }

        return $merged;
    }

    #[\Override]
    public function augment(mixed $value): mixed
    {
        /** @var array<string, mixed> $config */
        $config = $this->field()?->config() ?? [];
        $c = QuantitativeValueConfig::from($config);

        if (! $c->isDurationMode()) {
            return $value;
        }

        /** @var array<string, mixed> $stored */
        $stored = \is_array($value) ? $value : [];

        return QuantitativeValueDurationTotalYears::mergeIntoDurationStored($stored, $c);
    }
}
