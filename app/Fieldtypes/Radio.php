<?php

declare(strict_types=1);

namespace App\Fieldtypes;

use App\Fieldtypes\Traits\ConfigSectionWrapper;
use App\Fieldtypes\Traits\DynamicDefaultValue;
use App\Fieldtypes\Traits\HasChoiceSubmissionLimits;
use App\Fieldtypes\Traits\OtherOption;
use App\Fieldtypes\Traits\PresetOptions;
use App\Fieldtypes\Traits\RandomizesOptions;
use Statamic\Fieldtypes\Radio as BaseRadio;

/**
 * Custom Radio fieldtype that extends Statamic's Radio to support an "Other"
 * option.
 *
 * This fieldtype adds an "Enable Other Option" toggle to the config panel,
 * allowing users to select an "Other" radio button and enter a custom value. It
 * uses the ConfigSectionWrapper trait to inject the custom config field into the
 * existing Statamic Radio fieldtype configuration.
 *
 * The Vue component (RadioFieldtype.vue) is a fork of Statamic 6 RadioFieldtype:
 * uses RadioGroup and Radio from @statamic/cms/ui, FieldtypeMixin and
 * HasInputOptionsMixin from @statamic/cms, plus OtherOptionMixin and
 * OtherOptionInput for the "Other" option.
 */
class Radio extends BaseRadio
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
    protected $component = 'custom_radio';

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
     * Extend the config to add additional configuration fields available for this
     * fieldtype in the blueprint.
     *
     * Section keys match Statamic v6 base Radio: "Selection & Options",
     * "Appearance", "Data & Format". Adds preset, enable_other, randomize_order
     * to "Selection & Options" and dynamic default value to "Data & Format".
     *
     * @return array<string, mixed> The configuration field items
     */
    #[\Override]
    protected function configFieldItems(): array
    {
        // Get parent configuration with conditional visibility applied
        /** @var array<string, mixed> $parentConfig */
        $parentConfig = $this->getParentConfigWithConditionalVisibility();

        // Section keys must match Statamic v6 base Radio: "Selection & Options",
        // "Appearance", "Data & Format".
        // Define fields to add before the options field
        $fieldsToAddBefore = [
            __('Selection & Options') => [
                // Preset Options config
                ...$this->presetConfigField(),
                // Other Option config
                ...$this->otherOptionConfigField(),
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
        // method. Add preset and enable_other fields before the options field, randomize
        // after.
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
     * Expose the currently-full option values so the radio template can disable them (UAMS-Web/uams-statamic-archive#292).
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
