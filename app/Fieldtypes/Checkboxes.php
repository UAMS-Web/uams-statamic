<?php

declare(strict_types=1);

namespace App\Fieldtypes;

use App\Fieldtypes\Traits\ConfigSectionWrapper;
use App\Fieldtypes\Traits\DynamicDefaultValue;
use App\Fieldtypes\Traits\FieldtypeValidationRules;
use App\Fieldtypes\Traits\HasChoiceSubmissionLimits;
use App\Fieldtypes\Traits\OtherOption;
use App\Fieldtypes\Traits\PresetOptions;
use App\Fieldtypes\Traits\RandomizesOptions;
use Statamic\Fields\Field;
use Statamic\Fieldtypes\Checkboxes as BaseCheckboxes;

/**
 * Custom Checkboxes fieldtype that extends Statamic's Checkboxes to support an
 * "Other" option.
 *
 * This fieldtype adds an "Enable Other Option" toggle to the config panel,
 * allowing users to select an "Other" checkbox and enter a custom value. It uses
 * the ConfigSectionWrapper trait to inject the custom config field into the
 * existing Statamic Checkboxes fieldtype configuration.
 *
 * The Vue component (CheckboxesFieldtype.vue) is a fork of Statamic 6
 * CheckboxesFieldtype: uses CheckboxGroup and Checkbox from @statamic/cms/ui,
 * FieldtypeMixin and HasInputOptionsMixin from @statamic/cms, plus
 * OtherOptionMixin and OtherOptionInput for the "Other" option.
 */
class Checkboxes extends BaseCheckboxes
{
    /**
     * Trait for wrapping and extending Statamic fieldtype config sections.
     *
     * Provides helper methods to inject or modify configuration fields in the
     * parent fieldtype's config, allowing for clean extension and organization
     * of config options without duplicating parent logic.
     */
    use ConfigSectionWrapper;

    /**
     * Trait for adding dynamic default value functionality.
     *
     * Provides methods to add dynamic default value configuration fields
     * to fieldtype configs, allowing users to pre-populate fields with
     * contextual or dynamic data.
     */
    use DynamicDefaultValue;

    /**
     * Trait to provide reusable validation rule logic for Statamic fieldtypes.
     *
     *   - Supplies getValidationRules and setField methods for dynamic validation.
     *   - Allows this fieldtype to define custom validation rules and merge with
     *     parent rules.
     *   - Ensures validation logic is DRY and consistent across fieldtypes.
     */
    use FieldtypeValidationRules;

    /**
     * Trait adding per-choice submission limits (config + render-time full choices, UAMS-Web/uams-statamic-archive#292).
     */
    use HasChoiceSubmissionLimits;

    /**
     * Trait for adding "Other" option configuration to fieldtypes.
     *
     * Provides methods to add "Other" option configuration fields to fieldtype
     * configs, allowing users to enable a custom "Other" option with a customizable
     * label.
     */
    use OtherOption;

    /**
     * Trait for using preset options.
     *
     * Provides a method to use predefined option lists (e.g., U.S. States) if a
     * preset is selected in the fieldtype's config.
     */
    use PresetOptions;

    /**
     * Trait for randomizing the order of options.
     *
     * Provides a method to randomize the order of options if the 'randomize_order'
     * toggle is enabled in the fieldtype's config.
     */
    use RandomizesOptions;

    /**
     * The name of the Vue component to use for this fieldtype.
     *
     * @var string The name of the Vue component to use for this fieldtype
     */
    protected $component = 'custom_checkboxes';

    /**
     * The icon for this fieldtype.
     *
     * @return string The icon name
     */
    #[\Override]
    public function icon(): string
    {
        // Narrow the parent's untyped return to a string for the declared type
        $icon = parent::icon();

        return \is_scalar($icon) ? (string) $icon : '';
    }

    /**
     * Extend the config to add additional configuration fields available for this
     * fieldtype in the blueprint.
     *
     * Section keys match Statamic v6 base Checkboxes: "Selection & Options",
     * "Appearance", "Data & Format". Adds the following to "Selection & Options":
     *   - Preset options, enable_other, selection_limit, min/max/exact_selections,
     *     randomize_order. Adds dynamic default value fields to "Data & Format".
     *
     * @return array<string, mixed> The configuration field items
     */
    #[\Override]
    protected function configFieldItems(): array
    {
        // Get parent configuration with conditional visibility applied
        /** @var array<array-key, array<string, mixed>> $parentConfig */
        $parentConfig = $this->getParentConfigWithConditionalVisibility();

        // ---
        // Define fields to add before the options field (preset, enable_other, and
        // selection limit fields). Section keys must match Statamic v6 parent
        // config: "Selection & Options", "Appearance", "Data & Format".
        // ---
        $fieldsToAddBefore = [
            __('Selection & Options') => [
                // Preset Options config
                ...$this->presetConfigField(),
                // Other Option config
                ...$this->otherOptionConfigField(),
                // Selection Limit config
                'selection_limit' => [
                    'display' => __('Selection Limit'),
                    'instructions' => __('Limit how many options can be selected.'),
                    'type' => 'select',
                    'options' => [
                        'unlimited' => __('Unlimited Number of Selections'),
                        'exact' => __('Exact Number of Selections'),
                        'range' => __('Range of Selections'),
                    ],
                    'default' => 'unlimited',
                    'validate' => ['required'],
                ],
                // Minimum Number of Selections (shown if Range)
                'min_selections' => [
                    'display' => __('Minimum Number of Selections'),
                    'instructions' => __('The minimum number of options a user must select.'),
                    'type' => 'integer',
                    'min' => 1,
                    'step' => 1,
                    'if' => [
                        'selection_limit' => 'range',
                    ],
                    'validate' => [
                        'required_if:selection_limit,range',
                        'exclude_unless:selection_limit,range',
                    ],
                ],
                // Maximum Number of Selections (shown if Range)
                'max_selections' => [
                    'display' => __('Maximum Number of Selections'),
                    'instructions' => __('The maximum number of options a user can select.'),
                    'type' => 'integer',
                    'min' => 1,
                    'step' => 1,
                    'if' => [
                        'selection_limit' => 'range',
                    ],
                    'validate' => [
                        'required_if:selection_limit,range',
                        'exclude_unless:selection_limit,range',
                    ],
                ],
                // Exact Number of Selections (shown if Exact)
                'exact_selections' => [
                    'display' => __('Exact Number of Selections'),
                    'instructions' => __('The exact number of options a user must select.'),
                    'type' => 'integer',
                    'min' => 1,
                    'step' => 1,
                    'if' => [
                        'selection_limit' => 'exact',
                    ],
                    'validate' => [
                        'required_if:selection_limit,exact',
                        'exclude_unless:selection_limit,exact',
                    ],
                ],
            ],
            __('Data & Format') => [
                ...DynamicDefaultValue::dynamicDefaultValueConfigField(),
            ],
        ];

        // Define fields to add after the options field (randomize + per-choice limits)
        $fieldsToAddAfter = [
            __('Selection & Options') => [
                // Randomize Order of Options config
                ...RandomizesOptions::randomizeOrderConfigField(),
                // Per-choice submission limits (UAMS-Web/uams-statamic-archive#292)
                ...$this->choiceSubmissionLimitConfigField(),
            ],
        ];

        // Apply dynamic default value configuration
        /** @var array<array-key, array<string, mixed>> $parentConfig */
        $parentConfig = $this->applyDynamicDefaultValueConfig($parentConfig);

        // ---
        // Inject the custom fields into the existing config sections using the trait
        // method. Add preset, enable_other, and selection limit fields before the
        // options field, randomize after.
        // ---
        /** @var array<string, mixed> $configFieldItems */
        $configFieldItems = $this->addFieldsToExistingSectionsWithMultiplePositions(
            $fieldsToAddBefore,
            $fieldsToAddAfter,
            $parentConfig
        );

        return $configFieldItems;
    }

    /**
     * Expose the currently-full option values so the checkboxes template can disable them (UAMS-Web/uams-statamic-archive#292).
     *
     * @return array<array-key, mixed> The renderable field data (options + full choices)
     */
    #[\Override]
    public function extraRenderableFieldData(): array
    {
        return [
            ...parent::extraRenderableFieldData(),
            'full_choices' => $this->fullChoicesForRender(),
        ];
    }

    /**
     * Field-level validation rules, which Statamic's Field::rules() merges into the
     * field's OWN handle bucket. The selection-count rules from
     * {@see getValidationRules()} (the min/max/exact Closures) belong here: returning
     * them from extraRules() instead keyed them under numeric phantom fields (so they
     * never fired) AND threw a TypeError in Field::addNullableRule() whenever a
     * selection_limit was configured (UAMS-Web/uams-statamic-archive#1301, mirroring the UAMS-Web/uams-statamic-archive#1299 Assets fix). Passed
     * through rules() the whole Closure list attaches to the handle and actually
     * enforces the selection count.
     *
     * @return array<int, mixed> The base rules plus any selection-count Closures.
     */
    #[\Override]
    public function rules(): array
    {
        /** @var Field $field */
        $field = $this->field();

        /** @var array<string, mixed> $config */
        $config = $field->config();

        return array_values(array_merge(parent::rules(), $this->getValidationRules(null, $config)));
    }

    /**
     * Provide validation rules for this fieldtype.
     *
     * @param  mixed  $value  The value to validate
     * @param  array<string, mixed>  $config  The field config
     * @return list<\Closure> Array of validation rules (Laravel format)
     */
    public function getValidationRules(
        $value,
        array $config = []
    ): array {
        // Initialize rules array
        $rules = [];

        // Determine the selection limit mode from config
        $selectionLimit = $config['selection_limit'] ?? null;

        // Check if selection_limit is 'range'
        if ($selectionLimit === 'range') {
            // Check if min_selections is configured
            if (
                isset($config['min_selections'])
                && \is_numeric($config['min_selections'])
            ) {
                // Cast min_selections to integer
                $min = (int) $config['min_selections'];

                // Closure to enforce minimum number of selections
                $rules[] = function (
                    $attribute,
                    $value,
                    \Closure $fail
                ) use ($min): void {
                    // A blank submission defers to the field's own required/nullable
                    // state — an empty selection is "unanswered", not "too few" (UAMS-Web/uams-statamic-archive#1301
                    // review). The minimum applies only once at least one is selected,
                    // so an optional field stays blank-able while a required one is
                    // still rejected empty by its own `required` rule.
                    if (
                        \is_array($value)
                        && $value !== []
                        && \count($value) < $min
                    ) {
                        // Trigger validation failure when minimum selections requirement is not met
                        $fail(
                            __(
                                'You must select at least :min options.',
                                ['min' => $min]
                            )
                        );
                    }
                };
            }

            // Check if max_selections is configured
            if (
                isset($config['max_selections'])
                && \is_numeric($config['max_selections'])
            ) {
                // Cast max_selections to integer
                $max = (int) $config['max_selections'];

                // Closure to enforce maximum number of selections
                $rules[] = function (
                    $attribute,
                    $value,
                    \Closure $fail
                ) use ($max): void {
                    // Check if value is an array and count is greater than max
                    if (
                        \is_array($value)
                        && \count($value) > $max
                    ) {
                        // Trigger validation failure when maximum selections limit is exceeded
                        $fail(
                            __(
                                'You may not select more than :max options.',
                                ['max' => $max]
                            )
                        );
                    }
                };
            }
        }

        // Check if selection_limit is 'exact'
        if (
            $selectionLimit === 'exact'
            && isset($config['exact_selections'])
            && \is_numeric($config['exact_selections'])
        ) {
            // Cast exact_selections to integer
            $exact = (int) $config['exact_selections'];

            // Closure to enforce exact number of selections
            $rules[] = function (
                $attribute,
                $value,
                \Closure $fail
            ) use ($exact): void {
                // A blank submission defers to the field's own required/nullable
                // state — an empty selection is "unanswered", not "wrong count" (UAMS-Web/uams-statamic-archive#1301
                // review); the exact count applies only once any option is selected.
                if (
                    \is_array($value)
                    && $value !== []
                    && \count($value) !== $exact
                ) {
                    // Trigger validation failure when exact selections requirement is not met
                    $fail(
                        __(
                            'You must select exactly :exact options.',
                            ['exact' => $exact]
                        )
                    );
                }
            };
        }

        // Return the compiled array of validation rules
        return $rules;
    }
}
