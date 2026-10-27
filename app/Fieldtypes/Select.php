<?php

declare(strict_types=1);

namespace App\Fieldtypes;

use App\Fieldtypes\Traits\ConfigSectionWrapper;
use App\Fieldtypes\Traits\DynamicDefaultValue;
use App\Fieldtypes\Traits\HasChoiceSubmissionLimits;
use App\Fieldtypes\Traits\PresetOptions;
use App\Fieldtypes\Traits\RandomizesOptions;
use Statamic\Fieldtypes\Select as BaseSelect;

/**
 * Custom Select fieldtype that extends Statamic's Select to support additional
 * config fields.
 *
 * It uses the ConfigSectionWrapper trait to inject the custom config field into
 * the existing Statamic Select fieldtype configuration.
 */
class Select extends BaseSelect
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
     * Trait adding per-choice submission limits (config + render-time full choices, UAMS-Web/uams-statamic-archive#292).
     */
    use HasChoiceSubmissionLimits;

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
     * The icon for this fieldtype.
     *
     * @return string The icon name
     */
    #[\Override]
    public function icon(): string
    {
        $icon = parent::icon();

        return is_scalar($icon) ? (string) $icon : '';
    }

    /**
     * Extend the config to add additional configuration fields available for this
     * fieldtype in the blueprint.
     *
     * @return array<string, mixed> The configuration field items
     */
    #[\Override]
    protected function configFieldItems(): array
    {
        // Get parent configuration with conditional visibility applied
        /** @var array<string, mixed> $parentConfig */
        $parentConfig = $this->getParentConfigWithConditionalVisibility();

        // Section keys must match Statamic v6 base Select: "Selection & Options",
        // "Appearance", "Boundaries & Limits", "Data & Format".
        // Define fields to add before the options field (preset)
        $fieldsToAddBefore = [
            __('Selection & Options') => [
                // Preset Options config
                ...$this->presetConfigField(),
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
        $parentConfig = $this->applyDynamicDefaultValueConfig($parentConfig);

        // Define modifications to the parent config
        $fieldModifications = [
            // Add display conditions to the max_items field
            'max_items' => [
                'if' => [
                    'multiple' => 'equals true',
                ],
            ],
        ];

        // Apply display conditions to the max_items field
        /** @var array<array-key, array<string, mixed>> $parentConfig */
        $parentConfig = $this->modifyExistingFields(
            $fieldModifications,
            $parentConfig
        );

        // ---
        // Inject the custom fields into the existing config sections using the trait
        // method. Add preset fields before the options field and randomize fields after.
        // ---
        /** @var array<string, mixed> $finalConfig */
        $finalConfig = $this->addFieldsToExistingSectionsWithMultiplePositions(
            $fieldsToAddBefore,
            $fieldsToAddAfter,
            $parentConfig
        );

        return $finalConfig;
    }

    /**
     * Expose the currently-full option values so the select template can disable them (UAMS-Web/uams-statamic-archive#292).
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
}
