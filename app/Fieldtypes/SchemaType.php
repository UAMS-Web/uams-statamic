<?php

declare(strict_types=1);

namespace App\Fieldtypes;

use App\Enums\SchemaEnumerationMember;
use App\Enums\SchemaType as SchemaTypeEnum;
use App\Fieldtypes\Traits\FieldtypeValidationHelpers;
use App\Rules\ChainedSchemaTypeComplete;
use App\Services\SchemaTemplates\SchemaTypeOptions;
use Illuminate\Validation\Rule;
use Statamic\Fields\Field;
use Statamic\Fields\Fieldtype;

/**
 * Schema.org type selector: chained immediate-subtype drilldown from configured
 * starting type(s), or a single combobox of starting type(s) plus all descendants.
 *
 * CP copy: `lang/vendor/statamic/en/fieldtypes.php` keys `statamic::fieldtypes.schema_type.*`.
 *
 * Chained storage: `{ value: string, selections: list<string> }` plus optional
 * `chain_complete: true` when the editor chose the sentinel while further steps
 * still exist (omitted when false so YAML stays minimal). Each step after the
 * root is an immediate subtype and/or an enumeration member for the parent (see
 * SchemaEnumerationMember). Chained mode is always one linear path (one leaf
 * type or member); new entries have no pre-filled selections and the `default`
 * config applies only in single mode. Single storage: string when multiple is
 * off, list of strings when multiple is on (single mode only).
 */
final class SchemaType extends Fieldtype
{
    use FieldtypeValidationHelpers;

    /**
     * Human-readable fieldtype title in the blueprint picker.
     *
     * @var string Label shown for this fieldtype
     */
    protected static $title = 'statamic::fieldtypes.schema_type.title';

    /**
     * Search keywords for discovering this fieldtype in the CP.
     *
     * @var array<int, string> Lowercase tokens for fieldtype search
     */
    protected $keywords = [
        'Schema.org',
        'schema.org',
        'Schema',
        'type',
        'Thing',
        'subtype',
        'hierarchy',
    ];

    /**
     * Categories in the Statamic fieldtype selector.
     *
     * @var array<int, string> Category slugs (e.g., special, structured)
     */
    protected $categories = [
        'special',
        'structured',
    ];

    /**
     * Whether the fieldtype is selectable in collection blueprints.
     *
     * @var bool True when the fieldtype can be added to blueprints
     */
    protected $selectable = true;

    /**
     * Whether the value can be localized.
     *
     * @var bool False so the field is not per-locale
     */
    protected $localizable = false;

    /**
     * Vue component name for the CP.
     *
     * @var string Component handle registered in cp.js (schema_type-fieldtype)
     */
    protected $component = 'schema_type';

    /**
     * Icon name for the fieldtype.
     *
     * @return string Statamic icon handle
     */
    #[\Override]
    public function icon(): string
    {
        return 'array';
    }

    /**
     * Reserved stored value for the explicit "None applies" selection. Chosen so
     * it can never collide with a real Schema type or enumeration member name.
     */
    public const string NONE_VALUE = '__none__';

    /**
     * Blueprint config: mode, starting types, and mode-specific toggles.
     *
     * @return array<int, array<string, mixed>> Config sections and field defs
     */
    #[\Override]
    protected function configFieldItems(): array
    {
        // Get the schema object types
        $typeOptions = SchemaTypeOptions::schemaObjectTypes();

        // Build the excluded-types option source: types plus enumeration members,
        // so members are selectable in the CP (not only via hand-edited YAML)
        $excludedTypeOptions = $typeOptions;
        foreach (SchemaEnumerationMember::cases() as $enumerationMember) {
            if (! isset($excludedTypeOptions[$enumerationMember->value])) {
                $excludedTypeOptions[$enumerationMember->value] = $enumerationMember->value.' (enumeration member)';
            }
        }

        // Return the config field items
        return [
            [
                'display' => __('statamic::fieldtypes.schema_type.config.section_heading'),
                'fields' => [
                    'mode' => [
                        'display' => __('statamic::fieldtypes.schema_type.config.mode_display'),
                        'instructions' => __(
                            'statamic::fieldtypes.schema_type.config.mode_instructions'
                        ),
                        'type' => 'radio',
                        'options' => [
                            'chained' => __('statamic::fieldtypes.schema_type.config.mode_chained'),
                            'single' => __('statamic::fieldtypes.schema_type.config.mode_single'),
                        ],
                        'default' => 'chained',
                        'validate' => [
                            'required',
                        ],
                    ],
                    'starting_types' => [
                        'display' => __('statamic::fieldtypes.schema_type.config.starting_types_display'),
                        'instructions' => __(
                            'statamic::fieldtypes.schema_type.config.starting_types_instructions'
                        ),
                        'type' => 'select',
                        'options' => $typeOptions,
                        'multiple' => true,
                        'clearable' => false,
                        'default' => ['Thing'],
                        'validate' => [
                            'required',
                            'array',
                            'min:1',
                        ],
                    ],
                    'excluded_types' => [
                        'display' => 'Excluded Types',
                        'instructions' => 'Optionally remove specific types (and their descendants) or enumeration members from the offered options — e.g. keep the semantic root `MedicalOrganization` while omitting `Physician` and `VeterinaryCare`.',
                        'type' => 'select',
                        'options' => $excludedTypeOptions,
                        'multiple' => true,
                        'clearable' => true,
                        'default' => [],
                    ],
                    'clearable' => [
                        'display' => __('statamic::fieldtypes.schema_type.config.clearable_display'),
                        'instructions' => __(
                            'statamic::fieldtypes.schema_type.config.clearable_instructions'
                        ),
                        'type' => 'toggle',
                        'default' => true,
                    ],
                    'searchable' => [
                        'display' => __('statamic::fieldtypes.schema_type.config.searchable_display'),
                        'instructions' => __(
                            'statamic::fieldtypes.schema_type.config.searchable_instructions'
                        ),
                        'type' => 'toggle',
                        'default' => true,
                    ],
                    'multiple' => [
                        'display' => __('statamic::fieldtypes.schema_type.config.multiple_display'),
                        'instructions' => __(
                            'statamic::fieldtypes.schema_type.config.multiple_instructions'
                        ),
                        'type' => 'toggle',
                        'default' => true,
                        'if' => [
                            'mode' => 'single',
                        ],
                    ],
                    'default' => [
                        'display' => __('statamic::fieldtypes.schema_type.config.default_display'),
                        'instructions' => __(
                            'statamic::fieldtypes.schema_type.config.default_instructions'
                        ),
                        'type' => 'text',
                        'default' => null,
                        'if' => [
                            'mode' => 'single',
                        ],
                    ],
                    'none_option' => [
                        'display' => 'Add a "None applies" option',
                        'instructions' => 'Adds a labeled, requirement-satisfying "None applies" choice that records an explicit sentinel (distinct from an empty/cleared value) and is omitted from JSON-LD output. In multi-select it is mutually exclusive with real selections.',
                        'type' => 'toggle',
                        'default' => false,
                        'if' => [
                            'mode' => 'single',
                        ],
                    ],
                    'none_option_label' => [
                        'display' => 'Label for the "None applies" option',
                        'type' => 'text',
                        'default' => 'None — no available option is relevant',
                        'if' => [
                            'none_option' => true,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Preload subtree type names, children map, chained option rows, and single
     * combobox values for the Vue fieldtype.
     *
     * @return array<string, mixed> Keys: subtree_type_names, children_by_type, chained_next_options_by_parent,
     *                              single_select_values, type_descriptions
     */
    #[\Override]
    public function preload(): array
    {
        // Collect every Schema type name in the configured subtree
        $relevant = $this->relevantTypeNamesForSubtree();

        // ---
        // Build immediate children per type for legacy or debugging consumers
        // ---

        // Initialize children by type
        $childrenByType = [];

        // Loop through relevant type names
        foreach ($relevant as $typeName) {
            // Add children of the type to the children by type map
            $childrenByType[$typeName] = SchemaTypeEnum::childrenOf($typeName);
        }

        // Sort subtree names for stable combobox ordering
        sort($relevant);

        // Return the preloaded data
        return [
            'subtree_type_names' => $relevant,
            'children_by_type' => $childrenByType,
            'chained_next_options_by_parent' => $this->chainedNextOptionsByParent(
                $relevant
            ),
            'single_select_values' => $this->mergedSubtreeTypesAndEnumerationMembers(),
            'type_descriptions' => $this->typeAndMemberDescriptionsByName(),
            'none_option' => $this->noneOptionEnabled(),
            'none_option_label' => $this->noneOptionLabel(),
            'none_value' => self::NONE_VALUE,
        ];
    }

    /**
     * Schema.org descriptions for CP alerts: Schema types use enum PHPDoc;
     * enumeration members use SchemaEnumerationMember rdfs_comment HTML.
     *
     * @return array<string, string> Type or member name => HTML description
     */
    private function typeAndMemberDescriptionsByName(): array
    {
        $out = [];
        foreach ($this->mergedSubtreeTypesAndEnumerationMembers() as $name) {
            $desc = SchemaTypeEnum::typeDescriptionFor($name);
            if ($desc === null || $desc === '') {
                $desc = SchemaEnumerationMember::tryFrom($name)?->description();
            }

            if ($desc !== null && $desc !== '') {
                $out[$name] = $desc;
            }
        }

        return $out;
    }

    /**
     * Default stored value for new entries (normalized for the active mode).
     *
     * @return array<string, mixed>|list<string>|string|null Chained payload with
     *                                                       only `selections` when empty,
     *                                                       or single-mode normalized value.
     *                                                       When the field is required, mode is chained,
     *                                                       and exactly one starting type is configured,
     *                                                       that type is pre-selected for new entries.
     */
    #[\Override]
    public function defaultValue(): mixed
    {
        // ---
        // Resolve single-mode default from config when applicable
        // ---

        // Check if the field is in single mode
        if ($this->isSingleMode()) {
            // Get the default value from the config
            $default = $this->config('default');

            // Normalize the default value
            return $this->normalizeSingleStored($default);
        }

        // ---
        // Chained mode: never use the `default` config key; optional fields start empty.
        // Required fields with a single configured root type pre-select that type (matches CP).
        // ---

        $field = $this->field();

        /** @var array<string, mixed> $config */
        $config = $field instanceof Field ? $field->config() : [];

        if (
            $field instanceof Field
            && $this->blueprintConfigMarksFieldRequired($config)
        ) {
            $starts = $this->resolvedStartingTypes();

            if (\count($starts) === 1 && ! isset($this->resolvedExcludedNames()[$starts[0]])) {
                $only = $starts[0];

                return [
                    'selections' => [$only],
                    'value' => $only,
                ];
            }
        }

        return [
            'selections' => [],
        ];
    }

    /**
     * Normalize raw input before the publish form binds it.
     *
     * @param  mixed  $data  Raw value from the publish form
     * @return array<string, mixed>|list<string>|string|null Normalized value
     */
    #[\Override]
    public function preProcess(
        mixed $data
    ): mixed {
        // Check if the field is in single mode
        if ($this->isSingleMode()) {
            // Normalize the data
            return $this->normalizeSingleStored($data);
        }

        // Normalize the data for chained mode
        return $this->normalizeChainedStored($data);
    }

    /**
     * Normalize stored value when augmenting for display or API output.
     *
     * @param  mixed  $value  Stored field value
     * @return array<string, mixed>|list<string>|string|null Normalized value
     */
    #[\Override]
    public function augment(
        mixed $value
    ): mixed {
        // Check if the field is in single mode
        if ($this->isSingleMode()) {
            // Read path: omit the None sentinel BEFORE normalizing, so any real
            // selections survive (storage enforces None/real exclusivity; output
            // simply emits nothing for the sentinel itself).
            return $this->normalizeSingleStored($this->withoutNoneSentinel($value));
        }

        // Normalize the value for chained mode
        return $this->normalizeChainedStored($value);
    }

    /**
     * Normalize and persist the value after save.
     *
     * @param  mixed  $data  Raw value from the publish form
     * @return array<string, mixed>|list<string>|string|null Normalized value
     */
    #[\Override]
    public function process(
        mixed $data
    ): mixed {
        // Check if the field is in single mode
        if ($this->isSingleMode()) {
            // Normalize the data
            return $this->normalizeSingleStored($data);
        }

        // Normalize the data for chained mode; omit the key when nothing is stored
        $out = $this->normalizeChainedStored($data);

        return $this->isChainedStorageCanonicallyEmpty($out) ? null : $out;
    }

    /**
     * Normalize before Laravel validation runs on the publish payload.
     *
     * @param  mixed  $value  Raw value under validation
     * @return array<string, mixed>|list<string>|string|null Normalized value
     */
    #[\Override]
    public function preProcessValidatable(
        mixed $value
    ): mixed {
        return $this->preProcess($value);
    }

    /**
     * Base validation rules for the field root shape.
     *
     * @return array<int, string>
     */
    #[\Override]
    public function rules(): array
    {
        // Check if the field is in single mode
        if ($this->isSingleMode()) {
            // Return the validation rules for single mode
            return $this->isMultipleSingleMode()
                ? ['nullable', 'array']
                : ['nullable', 'string'];
        }

        // Return the validation rules for chained mode
        return ['array'];
    }

    /**
     * Blueprint `validate` rules merged onto the field handle before the
     * fieldtype `rules()` array.
     *
     * Chained drill-down completion is merged here (not in extraRules()) so
     * Statamic Field::rules() does not overwrite the handle’s rules: an extra rule
     * with the same key as the field handle would drop `required` and break
     * isRequired() / CP required indicators.
     *
     * @return array<int, string|object>|string|null
     */
    #[\Override]
    public function fieldRules(): array|string|null
    {
        $normalized = $this->normalizeBlueprintValidateForSometimes(
            $this->config('validate')
        );

        if (! $this->shouldAppendChainedDrilldownCompleteRule()) {
            return $normalized;
        }

        $list = match (true) {
            \is_array($normalized) => $normalized,
            $normalized === null || $normalized === '' => [],
            default => $this->explodeStatamicValidationRules($normalized),
        };

        $list[] = new ChainedSchemaTypeComplete($this);

        return $list;
    }

    /**
     * Whether to append {@see ChainedSchemaTypeComplete} onto the root handle rules.
     */
    private function shouldAppendChainedDrilldownCompleteRule(): bool
    {
        if ($this->isSingleMode()) {
            return false;
        }

        $field = $this->field();
        if (! $field instanceof Field) {
            return false;
        }

        /** @var array<string, mixed> $config */
        $config = $field->config();

        return $this->blueprintConfigMarksFieldRequired($config);
    }

    /**
     * Whether chained mode has no pending drill-down: terminal leaf, or stop sentinel
     * ({@code chain_complete}) when further options exist. Used for required validation.
     *
     * @param  mixed  $value  Raw or preprocessed chained payload
     */
    public function chainedDrilldownResolvedForRequired(mixed $value): bool
    {
        $normalized = $this->normalizeChainedStored($value);
        $selections = $normalized['selections'] ?? [];
        if (! \is_array($selections) || $selections === []) {
            return true;
        }

        $last = $selections[\count($selections) - 1];
        if (! \is_string($last) || $last === '') {
            return true;
        }

        $nextRows = $this->chainedNextOptionRowsForParent($last);

        if ($nextRows === []) {
            return true;
        }

        return ! empty($normalized['chain_complete']);
    }

    /**
     * Next-step option rows for a parent type, matching CP preload and Vue meta.
     *
     * @return list<array{value: string, label: string}>
     */
    private function chainedNextOptionRowsForParent(string $parent): array
    {
        $map = $this->chainedNextOptionsByParent(
            $this->relevantTypeNamesForSubtree()
        );

        $rows = $map[$parent] ?? null;

        return \is_array($rows) ? $rows : [];
    }

    /**
     * Additional rules when the field is required (mode-specific paths).
     *
     * @return array<string, string|array<int, string|object>>
     */
    #[\Override]
    public function extraRules(): array
    {
        // Get the field instance
        $field = $this->field();

        // Skip rule wiring when the field instance is unavailable
        if (! $field instanceof Field) {
            return [];
        }

        // ---
        // Read blueprint config and whether the field is required
        // ---

        // Get the field config
        /** @var array<string, mixed> $config */
        $config = $field->config();

        $explodedValidate = $this->explodedBlueprintValidateRulesFromConfig($config);

        if (! $this->blueprintConfigMarksFieldRequired($config)) {
            return [];
        }

        // ---
        // Build dotted paths for nested chained keys
        // ---

        // Get the field handle
        /** @var string $prefix */
        $prefix = $field->handle();

        // Create a closure to generate dotted paths
        $dot = static fn (string $path): string => sprintf('%s.%s', $prefix, $path);

        // Allow subtree types plus enumeration members for terminal values
        $allowedSubtree = $this->mergedSubtreeTypesAndEnumerationMembers();

        // Allow the explicit None sentinel when the option is enabled
        if ($this->noneOptionEnabled()) {
            $allowedSubtree[] = self::NONE_VALUE;
        }

        // Check if the field is in single mode
        if ($this->isSingleMode()) {
            // Check if the field is in multiple single mode
            if (! $this->isMultipleSingleMode()) {
                $extra = [
                    $prefix => [
                        'required',
                        'string',
                        Rule::in($allowedSubtree),
                    ],
                ];
            } else {
                $extra = [
                    $prefix => ['required', 'array', 'min:1'],
                    $prefix.'.*' => [
                        'string',
                        Rule::in($allowedSubtree),
                    ],
                ];
            }
        } else {
            $extra = [
                $dot('selections') => ['required', 'array', 'min:1'],
                $dot('value') => [
                    'required',
                    'string',
                    Rule::in($allowedSubtree),
                ],
            ];
        }

        return $this->conditionallyPrependSometimesToKeyedRuleSets(
            $extra,
            $explodedValidate
        );
    }

    /**
     * Whether this field instance uses single-combobox mode.
     *
     * @return bool True if the field is in single mode
     */
    private function isSingleMode(): bool
    {
        return ($this->config('mode') ?? 'chained') === 'single';
    }

    /**
     * Whether single mode allows multiple type selections (subtree combobox).
     *
     * @return bool True if the field is in multiple single mode
     */
    private function isMultipleSingleMode(): bool
    {
        // Chained mode always follows one path; `multiple` applies only in single mode
        if (! $this->isSingleMode()) {
            return false;
        }

        // Get the multiple config
        $multiple = $this->config('multiple');

        // Check if the multiple config is null
        if ($multiple === null) {
            // Return true to signal that the field is in multiple single mode
            return true;
        }

        // Return the multiple config as a boolean
        return (bool) $multiple;
    }

    /**
     * Resolved starting types from config (non-empty, valid enum names).
     *
     * @return list<string> Schema type names allowed as chain roots
     */
    private function resolvedStartingTypes(): array
    {
        // Get the starting types config
        $raw = $this->config('starting_types');

        // Fall back to Thing when config is missing or empty
        if (! \is_array($raw) || $raw === []) {
            return ['Thing'];
        }

        // Initialize the output array
        $out = [];

        // Loop through the raw starting types
        foreach ($raw as $t) {
            // Check if the type is a non-empty string and a valid Schema type enum case
            if (
                \is_string($t) && $t !== ''
                && SchemaTypeEnum::tryFrom($t) !== null
            ) {
                // Add the type to the output array
                $out[] = $t;
            }
        }

        // Return the output array or ['Thing'] if it is empty
        return $out !== [] ? array_values(array_unique($out)) : ['Thing'];
    }

    /**
     * Names excluded from the offered options: each configured `excluded_types`
     * entry plus, when it is a Schema type, all of its descendants. Enumeration
     * members are excluded by their own name.
     *
     * @return array<string, true> Set of excluded type/member names
     */
    private function resolvedExcludedNames(): array
    {
        // Get the excluded types config
        $raw = $this->config('excluded_types');

        // Return an empty set when nothing is excluded
        if (! \is_array($raw) || $raw === []) {
            return [];
        }

        // Initialize the excluded set
        $set = [];

        // Loop through the configured excluded entries
        foreach ($raw as $t) {
            // Skip non-string or empty entries
            if (! \is_string($t)) {
                continue;
            }

            if ($t === '') {
                continue;
            }

            // Exclude the entry itself
            $set[$t] = true;

            // When the entry is a Schema type, also exclude its descendants
            if (SchemaTypeEnum::tryFrom($t) !== null) {
                foreach (SchemaTypeEnum::descendantsOf($t) as $d) {
                    $set[$d] = true;
                }
            }
        }

        // Return the excluded set
        return $set;
    }

    /**
     * Whether the explicit "None applies" option is enabled for this field.
     */
    private function noneOptionEnabled(): bool
    {
        // "None applies" is a single-mode-only concept. Treat it as off in
        // chained mode even if a hand-edited blueprint sets the flag (the CP
        // toggle is already `if: mode=single`; this mirrors that for YAML and
        // keeps every consumer — extraRules, preload, augment — consistent).
        return $this->isSingleMode() && (bool) $this->config('none_option');
    }

    /**
     * Label for the explicit "None applies" option.
     */
    private function noneOptionLabel(): string
    {
        $label = $this->config('none_option_label');

        return \is_string($label) && $label !== ''
            ? $label
            : 'None — no available option is relevant';
    }

    /**
     * Remove the None sentinel from a raw single-mode value before augmentation.
     * Storage keeps the sentinel (distinct from empty) and enforces None/real
     * mutual exclusivity; this read path simply omits the sentinel so it emits no
     * schema member, while leaving any real selections intact.
     *
     * @param  mixed  $value  Raw stored single-mode value
     * @return mixed Value with the None sentinel removed
     */
    private function withoutNoneSentinel(mixed $value): mixed
    {
        if (! $this->noneOptionEnabled()) {
            return $value;
        }

        if ($value === self::NONE_VALUE) {
            return null;
        }

        if (\is_array($value)) {
            return array_values(array_filter($value, static fn (mixed $v): bool => $v !== self::NONE_VALUE));
        }

        return $value;
    }

    /**
     * Union of configured starting types and all descendants (subtree options).
     *
     * @return list<string> Distinct Schema type names in the subtree
     */
    private function relevantTypeNamesForSubtree(): array
    {
        // Initialize the set array
        $set = [];

        // Loop through the resolved starting types
        foreach ($this->resolvedStartingTypes() as $start) {
            // Add the starting type to the set array
            $set[$start] = true;

            // Loop through the descendants of the starting type
            foreach (SchemaTypeEnum::descendantsOf($start) as $d) {
                // Add the descendant to the set array
                $set[$d] = true;
            }
        }

        // Remove excluded types (and their descendants) from the subtree
        foreach (array_keys($this->resolvedExcludedNames()) as $excludedName) {
            unset($set[$excludedName]);
        }

        // Return the keys of the set array
        return array_keys($set);
    }

    /**
     * Types in the configured subtree plus enumeration members declared for any of
     * those types (single mode, chained terminal value, and rules).
     *
     * @return list<string> Sorted distinct type and member string values
     */
    private function mergedSubtreeTypesAndEnumerationMembers(): array
    {
        // Initialize the set array
        $set = [];

        // Loop through the relevant type names for the subtree
        foreach ($this->relevantTypeNamesForSubtree() as $t) {
            // Add the type to the set array
            $set[$t] = true;
        }

        // ---
        // Add enumeration members for each subtree type
        // ---

        // Loop through the relevant type names for the subtree
        foreach ($this->relevantTypeNamesForSubtree() as $t) {
            // Loop through the enumeration members for the type
            foreach (
                SchemaEnumerationMember::memberNamesForEnumerationType($t) as $m
            ) {
                // Add the enumeration member to the set array
                $set[$m] = true;
            }
        }

        // Remove excluded enumeration members (types already filtered upstream)
        foreach (array_keys($this->resolvedExcludedNames()) as $excludedName) {
            unset($set[$excludedName]);
        }

        // Get the keys of the set array
        $keys = array_keys($set);

        // Sort the keys
        sort($keys);

        // Return the keys
        return $keys;
    }

    /**
     * Next chained-step options per parent: immediate subtypes and enumeration
     * members, with disambiguating labels when both kinds exist.
     *
     * @param  list<string>  $relevantTypeNames  Parent types needing option rows
     * @return array<string, list<array{value: string, label: string}>> By parent type, rows of value and label
     */
    private function chainedNextOptionsByParent(
        array $relevantTypeNames
    ): array {
        // Initialize the by parent array
        $byParent = [];

        // Resolve the excluded names once for this build
        $excluded = $this->resolvedExcludedNames();

        // Loop through the relevant type names
        foreach ($relevantTypeNames as $parent) {
            // Get the children of the parent
            $children = SchemaTypeEnum::childrenOf($parent);

            // Get the enumeration members for the parent
            $members = SchemaEnumerationMember::memberNamesForEnumerationType(
                $parent
            );

            // Drop excluded types/members from this step's options
            $children = array_values(array_filter($children, static fn (string $c): bool => ! isset($excluded[$c])));
            $members = array_values(array_filter($members, static fn (string $m): bool => ! isset($excluded[$m])));

            // Check if the parent has both children and enumeration members
            $hasBoth = $children !== [] && $members !== [];

            // Initialize the rows array
            $rows = [];

            // Loop through the children
            foreach ($children as $c) {
                // Add the child to the rows array
                $rows[] = [
                    'value' => $c,
                    'label' => $hasBoth ? $c.' (subtype)' : $c,
                ];
            }

            // Loop through the enumeration members
            foreach ($members as $m) {
                // Prefer subtype when the same name appears as child and member
                if (
                    \in_array(
                        $m,
                        $children,
                        true
                    )
                ) {
                    continue;
                }

                // Add the enumeration member to the rows array
                $rows[] = [
                    'value' => $m,
                    'label' => $hasBoth ? $m.' (enumeration member)' : $m,
                ];
            }

            // Sort the rows array by label
            usort(
                $rows,
                static fn (array $a, array $b): int => strcasecmp(
                    $a['label'],
                    $b['label']
                )
            );

            // Add the rows to the by parent array
            $byParent[$parent] = $rows;
        }

        // Return the by parent array
        return $byParent;
    }

    /**
     * Normalize chained-mode storage: selections, optional terminal value, and
     * chain_complete flag.
     *
     * @param  mixed  $data  Associative payload or non-array input
     * @return array<string, mixed> Normalized chained shape
     */
    private function normalizeChainedStored(
        mixed $data
    ): array {
        // Check if the data is an array
        if (! \is_array($data)) {
            // Align with defaultValue(): only selections (never persist `value: null`)
            return [
                'selections' => [],
            ];
        }

        // Get the selections raw data
        $selectionsRaw = $data['selections'] ?? [];

        // Initialize the selections array
        $selections = [];

        // Check if the selections raw data is an array
        if (\is_array($selectionsRaw)) {
            // Loop through the selections raw data
            foreach ($selectionsRaw as $item) {
                // Check if the item is a non-empty string and a valid Schema type enum case
                if (! \is_string($item)) {
                    // Skip the item
                    continue;
                }

                if ($item === '') {
                    // Skip the item
                    continue;
                }

                // Check if the item is a valid Schema type name or enumeration member name
                if (
                    SchemaTypeEnum::tryFrom($item) !== null
                    || SchemaEnumerationMember::tryFrom($item) !== null
                ) {
                    // Add the item to the selections array
                    $selections[] = $item;
                }
            }
        }

        // Check if the chain is complete
        $chainComplete = ! empty($data['chain_complete']);

        // Get the resolved starting types
        $starts = $this->resolvedStartingTypes();

        // Initialize the start set array
        $startSet = array_fill_keys(
            $starts,
            true
        );

        // Initialize the normalized array
        $normalized = [];

        // Loop through the selections
        foreach ($selections as $i => $typeName) {
            // Check if the index is 0
            if ($i === 0) {
                // Check if the type name is not in the start set or is excluded
                if (! isset($startSet[$typeName]) || isset($this->resolvedExcludedNames()[$typeName])) {
                    // Break the loop
                    break;
                }

                // Add the type name to the normalized array
                $normalized[] = $typeName;

                // Continue the loop
                continue;
            }

            // Get the parent type name
            $parent = $normalized[$i - 1];

            // Check if the type name is a valid chained step after the parent
            if (! $this->isValidChainedStepAfterParent($parent, $typeName)) {
                // Break the loop
                break;
            }

            // Add the type name to the normalized array
            $normalized[] = $typeName;
        }

        // Get the last type name
        $value = $normalized !== [] ? $normalized[\count($normalized) - 1] : null;

        // Initialize the output array
        $out = [
            'selections' => $normalized,
        ];

        // Check if the normalized array is not empty
        if ($value !== null) {
            // Add the value to the output array
            $out['value'] = $value;
        }

        // Check if the chain is complete
        if ($chainComplete) {
            // Add the chain complete flag to the output array
            $out['chain_complete'] = true;
        }

        // Return the output array
        return $out;
    }

    /**
     * Whether chained storage is the canonical empty state (no selections, no terminal value).
     *
     * Used so `process()` returns null and Statamic drops the field from flat-file content.
     *
     * @param  array<string, mixed>  $out  Output of `normalizeChainedStored()`
     */
    private function isChainedStorageCanonicallyEmpty(array $out): bool
    {
        $selections = $out['selections'] ?? null;

        if (! \is_array($selections) || $selections !== []) {
            return false;
        }

        if (! empty($out['chain_complete'])) {
            return false;
        }

        $value = $out['value'] ?? null;

        if ($value !== null && $value !== '') {
            return false;
        }

        return array_all(array_keys($out), fn (string $key): bool => \in_array($key, ['selections', 'value', 'chain_complete'], true));
    }

    /**
     * Normalize single-mode storage: string or list of strings in the subtree.
     *
     * @param  mixed  $data  String, list of strings, or other input
     * @return list<string>|string|null Normalized single or multi selection
     */
    private function normalizeSingleStored(
        mixed $data
    ): string|array|null {
        // Initialize the allowed array
        $allowed = array_fill_keys(
            $this->mergedSubtreeTypesAndEnumerationMembers(),
            true
        );

        // Allow the explicit None sentinel when the option is enabled
        if ($this->noneOptionEnabled()) {
            $allowed[self::NONE_VALUE] = true;
        }

        // Check if the field is in multiple single mode
        if ($this->isMultipleSingleMode()) {
            // Check if the data is a non-empty string and a valid Schema type name
            if (
                \is_string($data) && $data !== ''
                && isset($allowed[$data])
            ) {
                // Return the data as an array
                return [$data];
            }

            // Check if the data is not an array
            if (! \is_array($data)) {
                // Return an empty array
                return [];
            }

            // Initialize the output array
            $out = [];

            // Loop through the data
            foreach ($data as $item) {
                // Check if the item is a non-empty string and a valid Schema type name
                if (
                    \is_string($item) && $item !== ''
                    && isset($allowed[$item])
                ) {
                    // Add the item to the output array
                    $out[] = $item;
                }
            }

            // None is mutually exclusive with real selections
            if ($this->noneOptionEnabled() && \in_array(self::NONE_VALUE, $out, true)) {
                return [self::NONE_VALUE];
            }

            // Return the output array
            return array_values(array_unique($out));
        }

        // Check if the data is an array
        if (\is_array($data)) {
            // Get the first item
            $first = $data[0] ?? null;

            // Check if the first item is a non-empty string and a valid Schema type name
            return \is_string($first) && $first !== '' && isset($allowed[$first])
                ? $first
                : null;
        }

        // Check if the data is a non-empty string and a valid Schema type name
        if (
            ! \is_string($data)
            || $data === ''
        ) {
            // Return null to signal that the data is not a valid Schema type name
            return null;
        }

        // Check if the data is a valid Schema type name
        if (isset($allowed[$data])) {
            // Return the data
            return $data;
        }

        // Return null to signal that the data is not a valid Schema type name
        return null;
    }

    /**
     * Whether $step is an immediate subtype of $parent or an enumeration member whose
     * metadata lists $parent as a valid enumeration type.
     *
     * @param  string  $parent  Selected parent type name
     * @param  string  $step  Candidate child type or member name
     * @return bool True when the step is allowed after the parent
     */
    private function isValidChainedStepAfterParent(
        string $parent,
        string $step
    ): bool {
        // Reject steps excluded by config
        if (isset($this->resolvedExcludedNames()[$step])) {
            return false;
        }

        // Loop through the children of the parent
        foreach (SchemaTypeEnum::childrenOf($parent) as $child) {
            // Check if the child is the step
            if ($child === $step) {
                // Return true to signal that the step is allowed after the parent
                return true;
            }
        }

        // Get the enumeration member for the step
        $member = SchemaEnumerationMember::tryFrom($step);

        // Check if the enumeration member is null
        if ($member === null) {
            // Return false to signal that the step is not allowed after the parent
            return false;
        }

        // Check if the parent is a valid enumeration type for the enumeration member
        // Return true to signal that the step is allowed after the parent
        // Return false to signal that the step is not allowed after the parent
        return \in_array($parent, $member->type(), true);
    }
}
