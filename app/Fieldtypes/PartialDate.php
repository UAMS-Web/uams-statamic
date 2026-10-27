<?php

declare(strict_types=1);

namespace App\Fieldtypes;

use Carbon\Carbon;
use Closure;
use Illuminate\Support\Arr;
use Statamic\Fields\Field;
use Statamic\Fields\Fieldtype;

/**
 * Partial calendar date from blueprint `included_parts` (year and/or month and/or day).
 *
 * Editors choose parts to store (year only through month+day). When the field is
 * required, conditional **Parts required** selects define which saved segments must be
 * filled.
 *
 * The CP edits `{ year, month, day }` objects; persisted values use a canonical
 * string (`YYYY`, `YYYY-MM-DD`, `XXXX-MM`, etc.) using `XXXX`/`XX` for unknown
 * year/month segments. Legacy keyed arrays load until the entry is saved again.
 *
 * When the year is unknown, February allows days 1–29; see maxDayInMonth().
 */
class PartialDate extends Fieldtype
{
    /**
     * Blueprint `included_parts` value: year only.
     */
    public const string INCLUDED_YEAR_ONLY = 'year_only';

    /**
     * Blueprint `included_parts` value: year and month.
     */
    public const string INCLUDED_YEAR_MONTH = 'year_month';

    /**
     * Blueprint `included_parts` value: year, month, and day.
     */
    public const string INCLUDED_YEAR_MONTH_DAY = 'year_month_day';

    /**
     * Blueprint `included_parts` value: month only.
     */
    public const string INCLUDED_MONTH_ONLY = 'month_only';

    /**
     * Blueprint `included_parts` value: month and day.
     */
    public const string INCLUDED_MONTH_DAY = 'month_day';

    /**
     * Required-granularity token: year only (matches year_only inclusion mode).
     */
    public const string GRANULARITY_YEAR_ONLY = 'year_only';

    /**
     * Required-granularity token: year and month.
     */
    public const string GRANULARITY_YEAR_MONTH = 'year_month';

    /**
     * Required-granularity token: full date.
     */
    public const string GRANULARITY_YEAR_MONTH_DAY = 'year_month_day';

    /**
     * Required-granularity token: month only.
     */
    public const string GRANULARITY_MONTH_ONLY = 'month_only';

    /**
     * Required-granularity token: month and day.
     */
    public const string GRANULARITY_MONTH_DAY = 'month_day';

    /**
     * Persisted substring meaning the calendar year component is unknown.
     *
     * @var string Four uppercase ASCII X letters.
     */
    public const string STORED_YEAR_UNKNOWN = 'XXXX';

    /**
     * Persisted substring meaning the calendar month segment is unknown.
     *
     * @var string Two uppercase ASCII X letters.
     */
    public const string STORED_MONTH_UNKNOWN = 'XX';

    /**
     * Translation key for the fieldtype title in the CP.
     *
     * @var string Statamic translation key string.
     */
    protected static $title = 'statamic::fieldtypes.partial_date.title';

    /**
     * Vue CP component basename for this fieldtype.
     *
     * @var string Registered `*-fieldtype` handle without suffix.
     */
    protected $component = 'partial_date';

    /**
     * CP field picker categories for this fieldtype.
     *
     * @var array<int, string> Category handles.
     */
    protected $categories = [
        'special',
    ];

    /**
     * CP search keywords for this fieldtype.
     *
     * @var array<int, string> Lowercase keyword strings.
     */
    protected $keywords = [
        'date',
        'year',
        'month',
        'day',
        'partial',
        'calendar',
    ];

    /**
     * Whether this fieldtype may be selected in forms.
     *
     * @var bool True when usable in the forms builder.
     */
    protected $selectableInForms = true;

    /**
     * Icon name for the fieldtype in the Statamic CP.
     */
    #[\Override]
    public function icon(): string
    {
        return 'fieldtype-date';
    }

    /**
     * Blueprint editor field definitions: parts, requirements, defaults, layout.
     *
     * @return array<int, array<string, mixed>> Sectioned config field definitions.
     */
    #[\Override]
    protected function configFieldItems(): array
    {
        // Build condition helpers for blueprint `if` visibility rules
        $eq = static fn (string $value): string => 'equals '.$value;

        // Join handles into a Statamic `contains_any` rule string
        $includedPartsContainsAny = static function (string ...$handles): string {
            // Join handles into a comma-separated string
            $joined = implode(',', $handles);

            // Return the contains_any rule string
            return 'contains_any '.$joined;
        };

        // Show year-related defaults when the stored mode includes a year
        $ifIncludedPartsHasYear = [
            'included_parts' => $includedPartsContainsAny(
                self::INCLUDED_YEAR_ONLY,
                self::INCLUDED_YEAR_MONTH,
                self::INCLUDED_YEAR_MONTH_DAY,
            ),
        ];

        // Show month-related defaults when the stored mode includes a month
        $ifIncludedPartsHasMonth = [
            'included_parts' => $includedPartsContainsAny(
                self::INCLUDED_YEAR_MONTH,
                self::INCLUDED_YEAR_MONTH_DAY,
                self::INCLUDED_MONTH_ONLY,
                self::INCLUDED_MONTH_DAY,
            ),
        ];

        // Show day-related defaults when the stored mode includes a day
        $ifIncludedPartsHasDay = [
            'included_parts' => $includedPartsContainsAny(
                self::INCLUDED_YEAR_MONTH_DAY,
                self::INCLUDED_MONTH_DAY,
            ),
        ];

        // Return the config field items
        return [
            [
                'display' => __('statamic::fieldtypes.partial_date.config.section_parts'),
                'fields' => [
                    'included_parts' => [
                        'type' => 'select',
                        'display' => __('statamic::fieldtypes.partial_date.config.included_parts_display'),
                        'instructions' => __('statamic::fieldtypes.partial_date.config.included_parts_instructions'),
                        'default' => self::INCLUDED_YEAR_MONTH_DAY,
                        'options' => [
                            self::INCLUDED_YEAR_ONLY => __('statamic::fieldtypes.partial_date.config.included.year_only'),
                            self::INCLUDED_YEAR_MONTH => __('statamic::fieldtypes.partial_date.config.included.year_month'),
                            self::INCLUDED_YEAR_MONTH_DAY => __('statamic::fieldtypes.partial_date.config.included.year_month_day'),
                            self::INCLUDED_MONTH_ONLY => __('statamic::fieldtypes.partial_date.config.included.month_only'),
                            self::INCLUDED_MONTH_DAY => __('statamic::fieldtypes.partial_date.config.included.month_day'),
                        ],
                    ],
                    'required_parts_year_month_day' => [
                        'type' => 'select',
                        'display' => __('statamic::fieldtypes.partial_date.config.required_parts_display'),
                        'instructions' => __('statamic::fieldtypes.partial_date.config.required_parts_instructions_ymd'),
                        'default' => self::GRANULARITY_YEAR_ONLY,
                        'options' => [
                            self::GRANULARITY_YEAR_ONLY => __('statamic::fieldtypes.partial_date.config.require.year_only'),
                            self::GRANULARITY_YEAR_MONTH => __('statamic::fieldtypes.partial_date.config.require.year_month'),
                            self::GRANULARITY_YEAR_MONTH_DAY => __('statamic::fieldtypes.partial_date.config.require.year_month_day'),
                        ],
                        'if' => [
                            'included_parts' => $eq(self::INCLUDED_YEAR_MONTH_DAY),
                        ],
                    ],
                    'required_parts_year_month' => [
                        'type' => 'select',
                        'display' => __('statamic::fieldtypes.partial_date.config.required_parts_display'),
                        'instructions' => __('statamic::fieldtypes.partial_date.config.required_parts_instructions_ym'),
                        'default' => self::GRANULARITY_YEAR_ONLY,
                        'options' => [
                            self::GRANULARITY_YEAR_ONLY => __('statamic::fieldtypes.partial_date.config.require.year_only'),
                            self::GRANULARITY_YEAR_MONTH => __('statamic::fieldtypes.partial_date.config.require.year_month'),
                        ],
                        'if' => [
                            'included_parts' => $eq(self::INCLUDED_YEAR_MONTH),
                        ],
                    ],
                    'required_parts_month_day' => [
                        'type' => 'select',
                        'display' => __('statamic::fieldtypes.partial_date.config.required_parts_display'),
                        'instructions' => __('statamic::fieldtypes.partial_date.config.required_parts_instructions_md'),
                        'default' => self::GRANULARITY_MONTH_ONLY,
                        'options' => [
                            self::GRANULARITY_MONTH_ONLY => __('statamic::fieldtypes.partial_date.config.require.month_only'),
                            self::GRANULARITY_MONTH_DAY => __('statamic::fieldtypes.partial_date.config.require.month_day'),
                        ],
                        'if' => [
                            'included_parts' => $eq(self::INCLUDED_MONTH_DAY),
                        ],
                    ],
                    'year_default' => [
                        'type' => 'integer',
                        'display' => __('statamic::fieldtypes.partial_date.config.year_default_display'),
                        'instructions' => __('statamic::fieldtypes.partial_date.config.year_default_instructions'),
                        'if' => $ifIncludedPartsHasYear,
                    ],
                    'year_min' => [
                        'type' => 'integer',
                        'display' => __('statamic::fieldtypes.partial_date.config.year_min_display'),
                        'instructions' => __('statamic::fieldtypes.partial_date.config.year_min_instructions'),
                        'if' => $ifIncludedPartsHasYear,
                    ],
                    'year_max' => [
                        'type' => 'integer',
                        'display' => __('statamic::fieldtypes.partial_date.config.year_max_display'),
                        'instructions' => __('statamic::fieldtypes.partial_date.config.year_max_instructions'),
                        'if' => $ifIncludedPartsHasYear,
                    ],
                    'month_default' => [
                        'type' => 'select',
                        'display' => __('statamic::fieldtypes.partial_date.config.month_default_display'),
                        'instructions' => __('statamic::fieldtypes.partial_date.config.month_default_instructions'),
                        'clearable' => true,
                        'options' => $this->monthSelectOptionsForConfig(),
                        'if' => $ifIncludedPartsHasMonth,
                    ],
                    'day_default' => [
                        'type' => 'integer',
                        'display' => __('statamic::fieldtypes.partial_date.config.day_default_display'),
                        'instructions' => __('statamic::fieldtypes.partial_date.config.day_default_instructions'),
                        'if' => $ifIncludedPartsHasDay,
                    ],
                ],
            ],
            [
                'display' => __('statamic::fieldtypes.partial_date.config.section_display'),
                'fields' => [
                    'subfields_layout' => [
                        'type' => 'radio',
                        'display' => __('statamic::fieldtypes.partial_date.config.subfields_layout_display'),
                        'instructions' => __('statamic::fieldtypes.partial_date.config.subfields_layout_instructions'),
                        'default' => 'inline',
                        'options' => [
                            'inline' => __('statamic::fieldtypes.partial_date.config.subfields_layout_inline'),
                            'stack' => __('statamic::fieldtypes.partial_date.config.subfields_layout_stack'),
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Month labels for the blueprint default-month select (clearable for no default).
     *
     * @return array<string|int, string> Month number keyed to localized month name.
     */
    protected function monthSelectOptionsForConfig(): array
    {
        // Initialize an empty array to store the month options
        $out = [];

        // Loop through each month and build the month options
        for ($m = 1; $m <= 12; $m++) {
            // Add the month option to the array
            $out[$m] = $this->localizedMonthName($m);
        }

        // Return the month options
        return $out;
    }

    /**
     * Extra data merged into the form-field template scope when this fieldtype
     * is rendered via `{{ form:create }}`. Exposes the same localized month
     * map the CP uses, so `partial_date.antlers.html` can loop over it rather
     * than hardcode English labels.
     *
     * Statamic calls this from `RendersForms::getRenderableField` (vendor
     * `vendor/statamic/cms/src/Tags/Concerns/RendersForms.php:158`); the
     * returned keys become Antlers variables in the rendered partial.
     *
     * The shape `[ 1 => 'January', 2 => 'February', … ]` is identical to
     * `monthSelectOptionsForConfig()` so a single source of truth drives
     * both the CP config UI and the public form. Switching the app locale
     * changes both at once.
     *
     * @return array<string, mixed> Extra data keyed for template consumption.
     */
    #[\Override]
    public function extraRenderableFieldData(): array
    {
        return [
            'month_options' => $this->monthSelectOptionsForConfig(),
        ];
    }

    /**
     * Normalize fieldtype config to an array for downstream reads.
     *
     * @return array<string, mixed> Blueprint config key-value pairs.
     */
    protected function effectiveConfig(): array
    {
        // Return the effective config
        /** @var array<string, mixed>|null $config */
        $config = $this->config();

        return \is_array($config) ? $config : [];
    }

    /**
     * Resolve the configured inclusion mode from blueprint config.
     *
     * @param  array<string, mixed>  $config  Fieldtype blueprint config.
     * @return string Inclusion token (e.g., year_month_day).
     */
    public function includedPartsFromConfig(
        array $config
    ): string {
        /** @var scalar $includedParts */
        $includedParts = $config['included_parts'] ?? self::INCLUDED_YEAR_MONTH_DAY;

        // Return the included parts
        return (string) $includedParts;
    }

    /**
     * Whether the inclusion mode stores a year component.
     *
     * @param  string  $includedParts  Inclusion token from included_parts.
     * @return bool Whether the inclusion mode stores a year component.
     */
    public function includesYear(
        string $includedParts
    ): bool {
        // Return whether the inclusion mode stores a year component
        return \in_array(
            $includedParts,
            [
                self::INCLUDED_YEAR_ONLY,
                self::INCLUDED_YEAR_MONTH,
                self::INCLUDED_YEAR_MONTH_DAY,
            ],
            true
        );
    }

    /**
     * Whether the inclusion mode stores a month component.
     *
     * @param  string  $includedParts  Inclusion token from included_parts.
     * @return bool Whether the inclusion mode stores a month component.
     */
    public function includesMonth(
        string $includedParts
    ): bool {
        // Return whether the inclusion mode stores a month component
        return \in_array(
            $includedParts,
            [
                self::INCLUDED_YEAR_MONTH,
                self::INCLUDED_YEAR_MONTH_DAY,
                self::INCLUDED_MONTH_ONLY,
                self::INCLUDED_MONTH_DAY,
            ],
            true
        );
    }

    /**
     * Whether the inclusion mode stores a day component.
     *
     * @param  string  $includedParts  Inclusion token from included_parts.
     * @return bool Whether the inclusion mode stores a day component.
     */
    public function includesDay(
        string $includedParts
    ): bool {
        // Return whether the inclusion mode stores a day component
        return \in_array(
            $includedParts,
            [
                self::INCLUDED_YEAR_MONTH_DAY,
                self::INCLUDED_MONTH_DAY,
            ],
            true
        );
    }

    /**
     * Whether the blueprint marks this field required at the parent level.
     *
     * @param  array<string, mixed>  $config  Effective blueprint config.
     * @return bool Whether the blueprint marks this field required at the parent level.
     */
    protected function isBlueprintFieldRequired(
        array $config
    ): bool {
        // Check if the config has a validate key and if it contains the required string
        return isset($config['validate'])
            && \in_array(
                'required',
                (array) $config['validate'],
                true
            );
    }

    /**
     * Localized full month name for a Gregorian month number (1–12).
     *
     * @param  int  $month  Month number 1–12.
     * @return string Localized full month name.
     */
    protected function localizedMonthName(
        int $month
    ): string {
        // Return the localized full month name
        /** @var Carbon $created */
        $created = Carbon::create(
            2020,
            $month,
            1
        );

        // locale() with an argument returns the Carbon instance (the no-arg
        // getter overload is what widens the inferred type to Carbon|string)
        /** @var Carbon $date */
        $date = $created->locale(
            app()->getLocale()
        );

        return $date->translatedFormat(
            'F'
        );
    }

    /**
     * CP publish form: merged field config plus meta for the Vue fieldtype.
     *
     * @return array<string, mixed> Config plus preload keys for the CP.
     */
    #[\Override]
    public function preload(): array
    {
        // ---
        // Read blueprint config and derived inclusion mode
        // ---

        // Resolve the effective config
        $config = $this->effectiveConfig();

        // Resolve the included parts from the config
        $included = $this->includedPartsFromConfig($config);

        // Resolve the effective granularity
        $granularity = $this->resolveEffectiveGranularity($config);

        // Detect whether the blueprint marks this field required at the parent level
        $parentRequired = $this->isBlueprintFieldRequired($config);

        // ---
        // Resolve which parts appear in the editor for this inclusion mode
        // ---

        // Check if the included parts includes a year
        $includeYear = $this->includesYear($included);

        // Check if the included parts includes a month
        $includeMonth = $this->includesMonth($included);

        // Check if the included parts includes a day
        $includeDay = $this->includesDay($included);

        // Return the preload data
        return [
            ...$config,
            'month_options' => $this->monthOptionsForMeta(),
            'effective_granularity' => $granularity,
            'included_parts' => $included,
            'include_year' => $includeYear,
            'include_month' => $includeMonth,
            'include_day' => $includeDay,
            'subfield_required' => [
                'year' => $parentRequired && $includeYear && $this->granularityRequiresYear($granularity),
                'month' => $parentRequired && $includeMonth && $this->granularityRequiresMonth($granularity),
                'day' => $parentRequired && $includeDay && $this->granularityRequiresDay($granularity),
            ],
        ];
    }

    /**
     * Optional integer default from blueprint config when a part is included.
     *
     * @param  array<string, mixed>  $config  Effective blueprint config.
     * @param  bool  $partIncluded  Whether this part is stored for the inclusion mode.
     * @param  string  $configKey  Blueprint key (e.g., year_default).
     * @return ?int Parsed default or null when absent or not applicable.
     */
    protected function optionalConfiguredPartInt(
        array $config,
        bool $partIncluded,
        string $configKey
    ): ?int {
        // Check if the part is not included
        if (! $partIncluded) {
            // Return null to signal absence
            return null;
        }

        // Check if the config key is not set or is empty (isset already excludes null)
        if (
            ! isset($config[$configKey])
            || $config[$configKey] === ''
        ) {
            // Return null to signal absence
            return null;
        }

        /** @var scalar $configuredDefault */
        $configuredDefault = $config[$configKey];

        // Return the parsed default as an integer
        return (int) $configuredDefault;
    }

    /**
     * Default entry value when the blueprint does not set `default`.
     *
     * @return mixed Blueprint `default`, else a canonical stored string when part
     *               defaults fill at least one included segment.
     */
    #[\Override]
    public function defaultValue(): mixed
    {
        // Resolve the field instance
        $field = $this->field();

        // ---
        // Delegate when no field instance is bound (falls back to Statamic default)
        // ---

        // Check if the field is null
        if (! $field instanceof Field) {
            // Return the parent default value
            return parent::defaultValue();
        }

        // Resolve the effective config
        $config = $this->effectiveConfig();

        // Check if the config has a default key
        if (\array_key_exists('default', $config)) {
            // Return the field default value (string or legacy array from YAML)
            return $this->processDefaultConfigValue(
                $field->get('default'),
                $config
            );
        }

        // Resolve the included parts from the config
        $included = $this->includedPartsFromConfig($config);

        // ---
        // Apply configured defaults only for parts included in the selected mode
        // ---

        // Build part defaults and serialize to the stored string form
        $parts = [
            'year' => $this->optionalConfiguredPartInt(
                $config,
                $this->includesYear($included),
                'year_default'
            ),
            'month' => $this->optionalConfiguredPartInt(
                $config,
                $this->includesMonth($included),
                'month_default'
            ),
            'day' => $this->optionalConfiguredPartInt(
                $config,
                $this->includesDay($included),
                'day_default'
            ),
        ];

        // Return null when no default segment is set
        return $this->serializePartialDate(
            $parts,
            $included
        );
    }

    /**
     * Coerce blueprint `default` through the same pipeline as entry values.
     *
     * @param  mixed  $default  Raw default from the field definition.
     * @param  array<string, mixed>  $config  Effective blueprint config.
     * @return ?string Canonical stored partial date or null when empty.
     */
    protected function processDefaultConfigValue(
        mixed $default,
        array $config
    ): ?string {
        // Resolve inclusion mode for stripping non-stored segments
        $included = $this->includedPartsFromConfig($config);

        // Build normalized parts via the shared reader for arrays, strings, and null
        $parts = $this->resolvePartsFromProcessInput($default, $included);

        // Persist defaults with the same string encoding as entry values
        return $this->serializePartialDate(
            $parts,
            $included
        );
    }

    /**
     * Month select options for CP preload (`month_options`).
     *
     * @return array<int, array{value: int, label: string}> Option rows for Vue.
     */
    protected function monthOptionsForMeta(): array
    {
        // Initialize an empty array to store the month options
        $options = [];

        // Loop through each month and build the month options
        for ($m = 1; $m <= 12; $m++) {
            // Build a stable calendar anchor for translated month names
            $options[] = [
                'value' => $m,
                'label' => $this->localizedMonthName($m),
            ];
        }

        // Return the month options
        return $options;
    }

    /**
     * Base Laravel validation rules for the root field value.
     *
     * Root coercion + coherence closure must live here only: `extraRules()` keys that
     * reuse the field handle overwrite `Field::rules()` merge and strip `required`
     * (breaking the CP required asterisk).
     *
     * @return array<int, string|Closure>
     */
    #[\Override]
    public function rules(): array
    {
        return [
            'nullable',
            $this->makeRootPayloadRuleClosure(),
        ];
    }

    /**
     * Closure rule validating CP payloads for invalid markers and cross-part coherence.
     */
    private function makeRootPayloadRuleClosure(): Closure
    {
        return function (
            string $attribute,
            mixed $value,
            Closure $fail
        ): void {
            // Ignore null or omitted payloads (optional fields)
            if (
                $value === null
                || $value === []
            ) {
                return;
            }

            // Expect array-shaped values after preprocessing (object-like CP state)
            if (! \is_array($value)) {
                $fail(
                    __(
                        'statamic::fieldtypes.partial_date.validation.invalid_shape'
                    )
                );

                return;
            }

            // Fail early when a stored string failed to parse into slots
            if (($value['_partial_date_invalid_storage'] ?? false) === true) {
                $fail(
                    __(
                        'statamic::fieldtypes.partial_date.validation.invalid_storage_format'
                    )
                );

                return;
            }

            // Isolate year, month, and day keys for coherence validators
            /** @var array<string, mixed> $payload */
            $payload = Arr::only(
                $value,
                [
                    'year',
                    'month',
                    'day',
                ]
            );

            // Run coherence against the effective blueprint configuration
            $config = $this->effectiveConfig();
            $this->validatePartialDateCoherence(
                $payload,
                $config,
                $fail
            );
        };
    }

    /**
     * Human-readable nested attributes for validation messages.
     *
     * @return array<string, string> Dotted attribute keys to labels.
     */
    #[\Override]
    public function extraValidationAttributes(): array
    {
        // Resolve the field instance
        $field = $this->field();

        // Check if the field is null
        if (! $field instanceof Field) {
            // Return an empty array
            return [];
        }

        // Prefix nested keys with this field handle for translator lookups
        $handle = $field->handle();
        $prefix = \is_scalar($handle) ? (string) $handle : '';

        // Return the extra validation attributes
        return [
            $prefix.'.year' => __('statamic::fieldtypes.partial_date.field.year'),
            $prefix.'.month' => __('statamic::fieldtypes.partial_date.field.month'),
            $prefix.'.day' => __('statamic::fieldtypes.partial_date.field.day'),
        ];
    }

    /**
     * Nested validation rules for year, month, day, and cross-field coherence.
     *
     * @return array<string, mixed> Rule arrays keyed by dotted handle paths.
     */
    #[\Override]
    public function extraRules(): array
    {
        // Resolve the field instance
        $field = $this->field();

        // Check if the field is null
        if (! $field instanceof Field) {
            // Return an empty array
            return [];
        }

        // Prefix nested keys with this field handle for translator lookups
        $handle = $field->handle();
        $prefix = \is_scalar($handle) ? (string) $handle : '';

        // Resolve the effective config
        $config = $this->effectiveConfig();

        // Check if the blueprint marks this field required at the parent level
        $parentRequired = $this->isBlueprintFieldRequired($config);

        // Resolve the included parts from the config
        $included = $this->includedPartsFromConfig($config);

        // Check if the included parts includes a year
        $includeYear = $this->includesYear($included);

        // Check if the included parts includes a month
        $includeMonth = $this->includesMonth($included);

        // Check if the included parts includes a day
        $includeDay = $this->includesDay($included);

        // Resolve the effective granularity
        $granularity = $this->resolveEffectiveGranularity($config);

        // Resolve the year minimum
        $yearMin = $config['year_min'] ?? null;

        // Resolve the year maximum
        $yearMax = $config['year_max'] ?? null;

        // Initialize an empty array to store the rules
        $rules = [];

        // Add the year rule
        $rules[$prefix.'.year'] = $this->rulesForYear(
            $includeYear,
            $granularity,
            $parentRequired,
            $yearMin,
            $yearMax
        );

        // Add the month rule
        $rules[$prefix.'.month'] = $this->rulesForMonth(
            $includeMonth,
            $granularity,
            $parentRequired
        );

        // Add the day rule
        $rules[$prefix.'.day'] = $this->rulesForDay(
            $includeDay,
            $granularity,
            $parentRequired
        );

        // Return the rules (coherence closure is on the root via rules(), not here)
        return $rules;
    }

    /**
     * Cross-field checks for month range and day vs calendar length.
     *
     * @param  array<string, mixed>  $value  Posted year/month/day keys.
     * @param  array<string, mixed>  $config  Effective blueprint config.
     * @param  Closure  $fail  Laravel validation failure callback.
     */
    protected function validatePartialDateCoherence(
        array $value,
        array $config,
        Closure $fail
    ): void {
        // Resolve the included parts from the config
        $included = $this->includedPartsFromConfig($config);

        // Check if the included parts includes a month
        $includeMonth = $this->includesMonth($included);

        // Check if the included parts includes a day
        $includeDay = $this->includesDay($included);

        // ---
        // Normalize primitives for parts that participate in this inclusion mode
        // ---

        // Normalize the parts from the payload
        $parts = $this->normalizePartsFromPayload($value, $included);

        // Resolve the year from the parts
        $year = $parts['year'];

        // Resolve the month from the parts
        $month = $parts['month'];

        // Resolve the day from the parts
        $day = $parts['day'];

        // ---
        // Reject month values outside 1–12 when a month is stored
        // ---

        // Check if the month is included and not null and is outside 1–12
        if (
            $includeMonth
            && $month !== null
            && (
                $month < 1
                || $month > 12
            )
        ) {
            // Fail the validation
            $fail(__('statamic::fieldtypes.partial_date.validation.month_range'));

            // Return early
            return;
        }

        // ---
        // Enforce day range against month length, or cap at 31 when month is unknown
        // ---

        // Check if the day is included and not null
        if (
            $includeDay
            && $day !== null
        ) {
            // Pick calendar max day when month is known, else allow 1–31 (unknown month segment)
            // The month-range validation above returns early on any out-of-range
            // month, so reaching here with a non-null included month guarantees
            // it is within 1–12 — no need to re-check the bounds.
            if (
                $includeMonth
                && $month !== null
            ) {
                // Resolve max day via Gregorian rules (leap handling when February)
                $max = $this->maxDayInMonth(
                    $year,
                    $month
                );
            } else {
                // Cap ambiguous month-unknown cases at 31 to match CP clamps
                $max = 31;
            }

            // Check whether the stored day exceeds the computed maximum
            if (
                $day < 1
                || $day > $max
            ) {
                // Fail against the translator string that names the inclusive max
                $fail(__('statamic::fieldtypes.partial_date.validation.day_range', ['max' => $max]));
            }
        }
    }

    /**
     * Maximum calendar day count for a month; year may be null (February allows 29).
     *
     * @param  int|null  $year  Gregorian year or null when unknown.
     * @param  int  $month  Month number 1–12.
     */
    public function maxDayInMonth(
        ?int $year,
        int $month
    ): int {
        // Check if the month is less than 1 or greater than 12
        if (
            $month < 1
            || $month > 12
        ) {
            // Return 31 for any month outside 1–12
            return 31;
        }

        // Check if the month is not 2
        if ($month !== 2) {
            // Return the days in the month
            /** @var Carbon $genericMonth */
            $genericMonth = Carbon::create(
                2020,
                $month,
                1
            );

            return $genericMonth->daysInMonth;
        }

        // Check if the year is not null
        if ($year !== null) {
            // Return the days in the month
            /** @var Carbon $februaryInYear */
            $februaryInYear = Carbon::create(
                $year,
                2,
                1
            );

            return $februaryInYear->daysInMonth;
        }

        // Return 29 for February in any year
        return 29;
    }

    /**
     * Effective requirement level when the blueprint marks this field required.
     *
     * Combines `included_parts` with conditional `required_parts_*` selects.
     *
     * @param  array<string, mixed>  $config  Effective blueprint config.
     * @return string Granularity token describing required parts.
     */
    public function resolveEffectiveGranularity(
        array $config
    ): string {
        // Resolve the included parts from the config
        $included = $this->includedPartsFromConfig($config);

        // Resolve the required granularity for the inclusion
        $required = $this->resolveRequiredGranularityForInclusion(
            $config,
            $included
        );

        // Clamp the required granularity to the inclusion and return the result
        return $this->clampRequiredGranularityToInclusion(
            $included,
            $required
        );
    }

    /**
     * Read the blueprint `required_parts_*` select for the active inclusion mode.
     *
     * @param  array<string, mixed>  $config  Effective blueprint config.
     * @param  string  $includedParts  Inclusion token from included_parts.
     * @return string Selected granularity token before clamping to inclusion.
     */
    protected function resolveRequiredGranularityForInclusion(
        array $config,
        string $includedParts
    ): string {
        /** @var scalar $yearMonth */
        $yearMonth = $config['required_parts_year_month'] ?? self::GRANULARITY_YEAR_MONTH;

        /** @var scalar $yearMonthDay */
        $yearMonthDay = $config['required_parts_year_month_day'] ?? self::GRANULARITY_YEAR_MONTH_DAY;

        /** @var scalar $monthDay */
        $monthDay = $config['required_parts_month_day'] ?? self::GRANULARITY_MONTH_DAY;

        // Return the selected granularity token
        return match ($includedParts) {
            self::INCLUDED_YEAR_ONLY => self::GRANULARITY_YEAR_ONLY,
            self::INCLUDED_MONTH_ONLY => self::GRANULARITY_MONTH_ONLY,
            self::INCLUDED_YEAR_MONTH => (string) $yearMonth,
            self::INCLUDED_YEAR_MONTH_DAY => (string) $yearMonthDay,
            self::INCLUDED_MONTH_DAY => (string) $monthDay,
            default => self::GRANULARITY_YEAR_MONTH_DAY,
        };
    }

    /**
     * Coerce required granularity when YAML disagrees with inclusion mode.
     *
     * @param  string  $includedParts  Active inclusion token.
     * @param  string  $requiredGranularity  Required token from config or YAML.
     * @return string Granularity allowed for this inclusion mode.
     */
    protected function clampRequiredGranularityToInclusion(
        string $includedParts,
        string $requiredGranularity
    ): string {
        // Allowed granularity tokens for this inclusion mode.
        $allowed = match ($includedParts) {
            self::INCLUDED_YEAR_ONLY => [
                self::GRANULARITY_YEAR_ONLY,
            ],
            self::INCLUDED_MONTH_ONLY => [
                self::GRANULARITY_MONTH_ONLY,
            ],
            self::INCLUDED_YEAR_MONTH => [
                self::GRANULARITY_YEAR_ONLY,
                self::GRANULARITY_YEAR_MONTH,
            ],
            self::INCLUDED_YEAR_MONTH_DAY => [
                self::GRANULARITY_YEAR_ONLY,
                self::GRANULARITY_YEAR_MONTH,
                self::GRANULARITY_YEAR_MONTH_DAY,
            ],
            self::INCLUDED_MONTH_DAY => [
                self::GRANULARITY_MONTH_ONLY,
                self::GRANULARITY_MONTH_DAY,
            ],
            default => [
                self::GRANULARITY_YEAR_MONTH_DAY,
            ],
        };

        // Check if the required granularity is in the allowed array
        if (\in_array($requiredGranularity, $allowed, true)) {
            // Return the required granularity
            return $requiredGranularity;
        }

        // Fallback is the coarsest valid requirement for this inclusion mode (last listed token)
        /** @var string $fallback */
        $fallback = Arr::last($allowed);

        return $fallback;
    }

    /**
     * Laravel rule tokens for the year subfield.
     *
     * @param  bool  $includeYear  Whether this inclusion mode stores year.
     * @param  string  $granularity  Effective required granularity token.
     * @param  bool  $parentRequired  Blueprint-level required flag.
     * @param  mixed  $yearMin  Optional minimum year from config.
     * @param  mixed  $yearMax  Optional maximum year from config.
     * @return array<int, string> Rule list for the nested year attribute.
     */
    protected function rulesForYear(
        bool $includeYear,
        string $granularity,
        bool $parentRequired,
        mixed $yearMin,
        mixed $yearMax
    ): array {
        // Check if the year is not included
        if (! $includeYear) {
            // Return the nullable rule for the year attribute
            return ['nullable'];
        }

        // Check if the parent is required and the granularity requires a year
        $mustRequire = $parentRequired && $this->granularityRequiresYear($granularity);

        // Initialize the rules array
        $rules = $mustRequire
            ? ['required', 'integer']
            : ['nullable', 'integer'];

        // Check if the year minimum is not null and not an empty string
        if (
            $yearMin !== null
            && $yearMin !== ''
        ) {
            /** @var scalar $yearMinScalar */
            $yearMinScalar = $yearMin;

            // Add the minimum rule to the rules array
            $rules[] = 'min:'.(int) $yearMinScalar;
        }

        // Check if the year maximum is not null and not an empty string
        if (
            $yearMax !== null
            && $yearMax !== ''
        ) {
            /** @var scalar $yearMaxScalar */
            $yearMaxScalar = $yearMax;

            // Add the maximum rule to the rules array
            $rules[] = 'max:'.(int) $yearMaxScalar;
        }

        // Return the rules array
        return $rules;
    }

    /**
     * Laravel rule tokens for the month subfield.
     *
     * @param  bool  $includeMonth  Whether this inclusion mode stores month.
     * @param  string  $granularity  Effective required granularity token.
     * @param  bool  $parentRequired  Blueprint-level required flag.
     * @return array<int, string> Rule list for the nested month attribute.
     */
    protected function rulesForMonth(
        bool $includeMonth,
        string $granularity,
        bool $parentRequired
    ): array {
        // Check if the month is not included
        if (! $includeMonth) {
            // Return the nullable rule for the month attribute
            return ['nullable'];
        }

        // Check if the parent is required and the granularity requires a month
        $mustRequire = $parentRequired && $this->granularityRequiresMonth($granularity);

        // Return the rules array
        return $mustRequire
            ? ['required', 'integer', 'min:1', 'max:12']
            : ['nullable', 'integer', 'min:1', 'max:12'];
    }

    /**
     * Laravel rule tokens for the day subfield.
     *
     * @param  bool  $includeDay  Whether this inclusion mode stores day.
     * @param  string  $granularity  Effective required granularity token.
     * @param  bool  $parentRequired  Blueprint-level required flag.
     * @return array<int, string> Rule list for the nested day attribute.
     */
    protected function rulesForDay(
        bool $includeDay,
        string $granularity,
        bool $parentRequired
    ): array {
        // Check if the day is not included
        if (! $includeDay) {
            // Return the nullable rule for the day attribute
            return ['nullable'];
        }

        // Check if the parent is required and the granularity requires a day
        $mustRequire = $parentRequired && $this->granularityRequiresDay($granularity);

        // Return the rules array
        return $mustRequire
            ? ['required', 'integer', 'min:1']
            : ['nullable', 'integer', 'min:1'];
    }

    /**
     * Whether the granularity token requires a year when the parent field is required.
     *
     * @param  string  $granularity  Granularity token from config.
     * @return bool Whether the granularity token requires a year when the parent field is required.
     */
    protected function granularityRequiresYear(
        string $granularity
    ): bool {
        // Return whether the granularity token requires a year when the parent field is required
        return \in_array(
            $granularity,
            [
                self::GRANULARITY_YEAR_ONLY,
                self::GRANULARITY_YEAR_MONTH,
                self::GRANULARITY_YEAR_MONTH_DAY,
            ],
            true
        );
    }

    /**
     * Whether the granularity token requires a month when the parent field is required.
     *
     * @param  string  $granularity  Granularity token from config.
     * @return bool Whether the granularity token requires a month when the parent field is required.
     */
    protected function granularityRequiresMonth(
        string $granularity
    ): bool {
        // Return whether the granularity token requires a month when the parent field is required
        return \in_array(
            $granularity,
            [
                self::GRANULARITY_YEAR_MONTH,
                self::GRANULARITY_YEAR_MONTH_DAY,
                self::GRANULARITY_MONTH_ONLY,
                self::GRANULARITY_MONTH_DAY,
            ],
            true
        );
    }

    /**
     * Whether the granularity token requires a day when the parent field is required.
     *
     * @param  string  $granularity  Granularity token from config.
     * @return bool Whether the granularity token requires a day when the parent field is required.
     */
    protected function granularityRequiresDay(
        string $granularity
    ): bool {
        // Return whether the granularity token requires a day when the parent field is required
        return \in_array(
            $granularity,
            [
                self::GRANULARITY_YEAR_MONTH_DAY,
                self::GRANULARITY_MONTH_DAY,
            ],
            true
        );
    }

    /**
     * Parse a canonical stored partial-date string into nullable year, month, and day.
     *
     * @param  string  $s  Trimmed storage token (for example YYYY-MM-DD, XXXX-MM).
     * @return array{year: ?int, month: ?int, day: ?int} Parsed slots.
     *
     * @throws \InvalidArgumentException When the string does not match any pattern.
     */
    public function parsePartialDateString(
        string $s
    ): array {
        // Attempt a single strict pattern match for the storage token
        $parsed = $this->tryParseStoredPartialDateString($s);

        // Check whether parsing failed outright
        if ($parsed === null) {
            // Signal an invalid persisted token to callers expecting strict parsing
            throw new \InvalidArgumentException(
                'Invalid partial_date storage string.'
            );
        }

        // Return canonical slots for callers and tests
        return $parsed;
    }

    /**
     * Encode normalized parts plus inclusion mode into stored string form (or null when empty).
     *
     * @param  array{year: ?int, month: ?int, day: ?int}  $parts  Normalized slots.
     * @param  string  $included  Inclusion token from `included_parts`.
     * @return ?string Canonical persisted string, or null when every included slot is absent.
     */
    public function serializePartialDate(
        array $parts,
        string $included
    ): ?string {
        // Pull slots with safe defaults before branching on inclusion modes
        $year = $parts['year'];
        $month = $parts['month'];
        $day = $parts['day'];

        // Branch serialization based on blueprint inclusion granularity
        return match ($included) {
            self::INCLUDED_YEAR_ONLY => $this->serializeIncludedYearOnly(
                $year
            ),
            self::INCLUDED_MONTH_ONLY => $this->serializeIncludedMonthOnly(
                $month
            ),
            self::INCLUDED_YEAR_MONTH => $this->serializeIncludedYearMonth(
                $year,
                $month
            ),
            self::INCLUDED_MONTH_DAY => $this->serializeIncludedMonthDay(
                $month,
                $day
            ),
            self::INCLUDED_YEAR_MONTH_DAY => $this->serializeIncludedYearMonthDay(
                $year,
                $month,
                $day
            ),
            default => null,
        };
    }

    /**
     * Map posted or persisted inputs into editable `{year,month,day}` slots for inclusion.
     *
     * @param  mixed  $data  CP array, persisted string, or legacy payloads.
     * @param  string  $included  Inclusion token used to drop non-mode keys.
     * @return array{year: ?int, month: ?int, day: ?int}
     */
    protected function resolvePartsFromProcessInput(
        mixed $data,
        string $included
    ): array {
        // Treat falsy empties consistently as absent slots throughout the pipeline
        if (
            in_array($data, [null, '', []], true)
        ) {
            // Return normalized empty triple for downstream serializers
            return [
                'year' => null,
                'month' => null,
                'day' => null,
            ];
        }

        // Parse trimmed stored strings into a temporary associative payload
        if (\is_string($data)) {
            // Trim whitespace from inbound stored strings
            $trimmed = trim($data);

            // Collapse whitespace-only strings to empty triples
            if ($trimmed === '') {
                // Return an empty triple when only whitespace was provided
                return [
                    'year' => null,
                    'month' => null,
                    'day' => null,
                ];
            }

            // Match known stored patterns strictly
            $parsed = $this->tryParseStoredPartialDateString($trimmed);

            // Treat unknown patterns as absent values for defensive processing paths
            if ($parsed === null) {
                // Return triple nulls instead of guessing when malformed strings slip in
                return [
                    'year' => null,
                    'month' => null,
                    'day' => null,
                ];
            }

            // Constrain triples against the blueprint inclusion selections
            return $this->normalizePartsFromPayload(
                $parsed,
                $included
            );
        }

        // Preserve existing array payloads from the CP and legacy YAML
        if (\is_array($data)) {
            /** @var array<string, mixed> $payload */
            $payload = $data;

            // Normalize associative arrays through the typed integer helper
            return $this->normalizePartsFromPayload(
                $payload,
                $included
            );
        }

        // Fallback to empty triple for unexpected scalar payloads
        return [
            'year' => null,
            'month' => null,
            'day' => null,
        ];
    }

    /**
     * Try to parse canonical storage grammar into editable slots without throwing.
     *
     * @param  string  $trimmed  Non-empty trimmed string from storage or preprocessing.
     * @return array{year: ?int, month: ?int, day: ?int}|null Parsed triple or null when
     *                                                        no pattern matched.
     */
    protected function tryParseStoredPartialDateString(
        string $trimmed
    ): ?array {
        // Try month+day with unknown year first (longest XXXX- prefix forms)
        if (
            preg_match(
                '/^XXXX-(\d{2})-(\d{2})$/',
                $trimmed,
                $m
            ) === 1
        ) {
            // Return unknown year with concrete month and day segments
            return [
                'year' => null,
                'month' => (int) $m[1],
                'day' => (int) $m[2],
            ];
        }

        // Try month-only with unknown year
        if (
            preg_match(
                '/^XXXX-(\d{2})$/',
                $trimmed,
                $m
            ) === 1
        ) {
            // Return month-only partial with unknown year and day
            return [
                'year' => null,
                'month' => (int) $m[1],
                'day' => null,
            ];
        }

        // Try day-only with unknown year and month
        if (
            preg_match(
                '/^XXXX-XX-(\d{2})$/',
                $trimmed,
                $m
            ) === 1
        ) {
            // Return day-only partial with unknown year and month
            return [
                'year' => null,
                'month' => null,
                'day' => (int) $m[1],
            ];
        }

        // Try year+day while month remains unknown explicitly
        if (
            preg_match(
                '/^(\d{4})-XX-(\d{2})$/',
                $trimmed,
                $m
            ) === 1
        ) {
            // Return Gregorian year and day slots with absent month semantics
            return [
                'year' => (int) $m[1],
                'month' => null,
                'day' => (int) $m[2],
            ];
        }

        // Try fully specified Gregorian triple
        if (
            preg_match(
                '/^(\d{4})-(\d{2})-(\d{2})$/',
                $trimmed,
                $m
            ) === 1
        ) {
            // Populate all segments from digit groups
            return [
                'year' => (int) $m[1],
                'month' => (int) $m[2],
                'day' => (int) $m[3],
            ];
        }

        // Try year+month without a day segment
        if (
            preg_match(
                '/^(\d{4})-(\d{2})$/',
                $trimmed,
                $m
            ) === 1
        ) {
            // Capture year and month while leaving day absent
            return [
                'year' => (int) $m[1],
                'month' => (int) $m[2],
                'day' => null,
            ];
        }

        // Try bare four-digit Gregorian year
        if (
            preg_match(
                '/^(\d{4})$/',
                $trimmed,
                $m
            ) === 1
        ) {
            // Return year-only partial with absent month and day
            return [
                'year' => (int) $m[1],
                'month' => null,
                'day' => null,
            ];
        }

        // Signal that no grammar branch recognized the token
        return null;
    }

    /**
     * Serialize year-only inclusion mode.
     *
     * @param  ?int  $year  Gregorian year or null when absent.
     * @return ?string Four-digit year string or null when empty.
     */
    protected function serializeIncludedYearOnly(
        ?int $year
    ): ?string {
        // Return null when the year slot is not populated
        if ($year === null) {
            // Represent empty year-only selections as absent storage
            return null;
        }

        // Persist a normalized four-digit prefix without month or day segments
        return sprintf(
            '%04d',
            $year
        );
    }

    /**
     * Serialize month-only inclusion mode (unknown year prefix).
     *
     * @param  ?int  $month  Month 1–12 or null when absent.
     * @return ?string `XXXX-MM` string or null when empty.
     */
    protected function serializeIncludedMonthOnly(
        ?int $month
    ): ?string {
        // Return null when the month slot is absent
        if ($month === null) {
            // Avoid writing placeholder-only rows with no calendar information
            return null;
        }

        // Persist unknown year with concrete month digits
        return sprintf(
            '%s-%02d',
            self::STORED_YEAR_UNKNOWN,
            $month
        );
    }

    /**
     * Serialize year+month inclusion permutations.
     *
     * @param  ?int  $year  Gregorian year or null when unknown.
     * @param  ?int  $month  Month 1–12 or null when absent.
     * @return ?string Stored token for the populated combination or null when empty.
     */
    protected function serializeIncludedYearMonth(
        ?int $year,
        ?int $month
    ): ?string {
        // Check for the fully populated year+month combination
        if (
            $year !== null
            && $month !== null
        ) {
            // Persist both segments with Gregorian padding
            return sprintf(
                '%04d-%02d',
                $year,
                $month
            );
        }

        // Check for year-only storage within this inclusion mode (month is null here)
        if ($year !== null) {
            // Persist bare year semantics without invented month placeholders
            return sprintf(
                '%04d',
                $year
            );
        }

        // Check for month-unknown-year storage semantics (year is null here)
        if ($month !== null) {
            // Encode unknown calendar year explicitly with padded month digits
            return sprintf(
                '%s-%02d',
                self::STORED_YEAR_UNKNOWN,
                $month
            );
        }

        // Return null when neither slot carries data
        return null;
    }

    /**
     * Serialize month+day inclusion where year is never stored.
     *
     * @param  ?int  $month  Month 1–12 or null when absent.
     * @param  ?int  $day  Day of month or null when absent.
     * @return ?string Persisted triple using `XXXX` year placeholder rules.
     */
    protected function serializeIncludedMonthDay(
        ?int $month,
        ?int $day
    ): ?string {
        // Persist both month and day with unknown year prefix
        if (
            $month !== null
            && $day !== null
        ) {
            // Store month and day under unknown year placeholder grammar
            return sprintf(
                '%s-%02d-%02d',
                self::STORED_YEAR_UNKNOWN,
                $month,
                $day
            );
        }

        // Persist month-only under unknown year when day remains absent (day is null here)
        if ($month !== null) {
            // Encode month-only partials with explicit unknown year prefix
            return sprintf(
                '%s-%02d',
                self::STORED_YEAR_UNKNOWN,
                $month
            );
        }

        // Persist day-only under unknown year and month placeholders (month is null here)
        if ($day !== null) {
            // Encode day-only partials with both unknown placeholders
            return sprintf(
                '%s-%s-%02d',
                self::STORED_YEAR_UNKNOWN,
                self::STORED_MONTH_UNKNOWN,
                $day
            );
        }

        // Return null when neither slot is present
        return null;
    }

    /**
     * Serialize all permutations allowed for year+month+day inclusion.
     *
     * @param  ?int  $year  Gregorian year or null when unknown.
     * @param  ?int  $month  Month 1–12 or null when unknown.
     * @param  ?int  $day  Day of month or null when absent.
     * @return ?string Stored token or null when every slot is empty.
     */
    protected function serializeIncludedYearMonthDay(
        ?int $year,
        ?int $month,
        ?int $day
    ): ?string {
        // Persist the complete triple when all segments are known
        if (
            $year !== null
            && $month !== null
            && $day !== null
        ) {
            // Return the strict Gregorian `YYYY-MM-DD` representation
            return sprintf(
                '%04d-%02d-%02d',
                $year,
                $month,
                $day
            );
        }

        // Persist year+month without a concrete day
        if (
            $year !== null
            && $month !== null
            && $day === null
        ) {
            // Represent partial precision ending at month granularity
            return sprintf(
                '%04d-%02d',
                $year,
                $month
            );
        }

        // Persist year-only selections inside the broadest inclusion mode
        if (
            $year !== null
            && $month === null
            && $day === null
        ) {
            // Store only the four-digit year segment
            return sprintf(
                '%04d',
                $year
            );
        }

        // Persist year+day while month remains explicitly unknown (month is null, day is set here)
        if ($year !== null) {
            // Encode unknown month segment with literal `XX` between year and day
            return sprintf(
                '%04d-%s-%02d',
                $year,
                self::STORED_MONTH_UNKNOWN,
                $day
            );
        }

        // Persist month+day under unknown calendar year (year is null here)
        if (
            $month !== null
            && $day !== null
        ) {
            // Represent unknown year through the `XXXX` prefix with month and day digits
            return sprintf(
                '%s-%02d-%02d',
                self::STORED_YEAR_UNKNOWN,
                $month,
                $day
            );
        }

        // Persist month-only under unknown year and absent day (year and day are null here)
        if ($month !== null) {
            // Encode month-only precision with explicit unknown year prefix
            return sprintf(
                '%s-%02d',
                self::STORED_YEAR_UNKNOWN,
                $month
            );
        }

        // Persist day-only with both year and month unknown (year and month are null here)
        if ($day !== null) {
            // Store day-only partials with both placeholder segments present
            return sprintf(
                '%s-%s-%02d',
                self::STORED_YEAR_UNKNOWN,
                self::STORED_MONTH_UNKNOWN,
                $day
            );
        }

        // Return null when no segment was stored
        return null;
    }

    /**
     * Normalize year, month, and day integers from a payload for an inclusion mode.
     *
     * @param  array<string, mixed>  $payload  Raw associative partial-date keys.
     * @param  string  $included  Inclusion token from `included_parts`.
     * @return array{year: ?int, month: ?int, day: ?int} Normalized year, month, and day integers.
     */
    protected function normalizePartsFromPayload(
        array $payload,
        string $included
    ): array {
        // Return the normalized year, month, and day as an array
        return [
            'year' => $this->includesYear($included)
                ? $this->normalizeInt(Arr::get($payload, 'year'))
                : null,
            'month' => $this->includesMonth($included)
                ? $this->normalizeInt(Arr::get($payload, 'month'))
                : null,
            'day' => $this->includesDay($included)
                ? $this->normalizeInt(Arr::get($payload, 'day'))
                : null,
        ];
    }

    /**
     * Normalize raw posted data to the canonical stored partial-date string.
     *
     * @param  mixed  $data  Raw field payload from the CP, YAML, or importer.
     * @return ?string Persisted token, or null when no included part is set.
     */
    #[\Override]
    public function process(
        mixed $data
    ): ?string {
        // Resolve the effective config
        $config = $this->effectiveConfig();

        // Resolve the included parts from the config
        $included = $this->includedPartsFromConfig($config);

        // Build the normalized triple regardless of source shape
        $parts = $this->resolvePartsFromProcessInput(
            $data,
            $included
        );

        // Serialize nullable slots into the persisted grammar
        return $this->serializePartialDate(
            $parts,
            $included
        );
    }

    /**
     * Prepare stored values for editing in the CP `{year,month,day}` object shape.
     *
     * @param  mixed  $data  Raw value loaded for the CP field.
     * @return array{year: ?int, month: ?int, day: ?int} Normalized nullable integers.
     */
    #[\Override]
    public function preProcess(
        mixed $data
    ): array {
        // Resolve inclusion mode for stripping parts not included in the blueprint
        $included = $this->includedPartsFromConfig(
            $this->effectiveConfig()
        );

        // Map strings, arrays, or empties into editable triples
        return $this->resolvePartsFromProcessInput(
            $data,
            $included
        );
    }

    /**
     * Ensure nested keys exist for nested validator rules.
     *
     * @param  mixed  $value  Incoming value before nested rule evaluation.
     * @return array<string, mixed> Always includes year, month, and day keys.
     */
    #[\Override]
    public function preProcessValidatable(
        mixed $value
    ): array {
        // Seed validation slots with nullable defaults
        $base = [
            'year' => null,
            'month' => null,
            'day' => null,
        ];

        // Normalize null or empty strings to triple nulls before validation
        if (
            $value === null
            || $value === ''
        ) {
            // Return untouched defaults for optional empty submissions
            return $base;
        }

        // Preserve associative CP payloads by intersecting allowed keys only
        if (\is_array($value)) {
            // Merge known slots while dropping unknown keys from the payload
            return [
                ...$base,
                ...\array_intersect_key(
                    $value,
                    $base
                ),
            ];
        }

        // Decode persisted strings into validation slots when arrays are absent
        if (\is_string($value)) {
            // Trim UTF-8 safe whitespace from stored tokens
            $trimmed = trim($value);

            // Treat blank strings as empty triples for optional fields
            if ($trimmed === '') {
                // Return base null slots for whitespace-only strings
                return $base;
            }

            // Parse strict storage grammar or mark failure for root rule closure
            $parsed = $this->tryParseStoredPartialDateString($trimmed);

            // Inject the invalid marker when parsing failed to guide error messaging
            if ($parsed === null) {
                // Flag invalid storage tokens without throwing during preprocessing
                return [
                    ...$base,
                    '_partial_date_invalid_storage' => true,
                ];
            }

            // Merge parsed slots into the validation triple
            return [
                ...$base,
                ...\array_intersect_key(
                    $parsed,
                    $base
                ),
            ];
        }

        // Fall back to empty triple for unexpected scalar types
        return $base;
    }

    /**
     * Coerce a scalar to integer or null for partial-date parts.
     *
     * @param  mixed  $value  Raw subfield value from user input.
     * @return ?int Parsed integer, or null when empty or non-numeric.
     */
    protected function normalizeInt(
        mixed $value
    ): ?int {
        // ---
        // Treat empty strings and explicit null as absent
        // ---

        // Check if the value is null or an empty string
        if (
            $value === null
            || $value === ''
        ) {
            // Return null to signal absence
            return null;
        }

        // ---
        // Never interpret booleans as numeric parts
        // ---

        // Check if the value is a boolean
        if (\is_bool($value)) {
            // Return null to signal absence
            return null;
        }

        // ---
        // Accept numeric strings and numbers for integer fields
        // ---

        // Check if the value is numeric
        if (is_numeric($value)) {
            // Return the value as an integer
            return (int) $value;
        }

        // Return null to signal absence
        return null;
    }
}
