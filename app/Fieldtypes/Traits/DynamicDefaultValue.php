<?php

declare(strict_types=1);

namespace App\Fieldtypes\Traits;

/**
 * Trait for adding dynamic default value functionality to fieldtypes.
 *
 * This trait provides methods to add dynamic default value configuration fields
 * to fieldtype configs, allowing users to pre-populate fields with contextual
 * or dynamic data based on the current page, user, or other factors.
 */
trait DynamicDefaultValue
{
    /**
     * Get the dynamic default value configuration fields for injection.
     *
     * Returns an array containing the dynamic default value fields in a format
     * suitable for use with the spread operator in fieldtype config arrays.
     * Similar to RandomizesOptions::randomizeOrderConfigField().
     *
     * @return array<string, array<string, mixed>> Array with dynamic default value fields ready for injection
     */
    public static function dynamicDefaultValueConfigField(): array
    {
        return [
            // Enable Dynamic Default Value config
            'calculation_query' => [
                'display' => 'Enable Dynamic Default Value',
                'instructions' => 'Pre-populate the field with contextual or dynamic data based on the current page, user, or other factors.',
                'type' => 'toggle',
                'default' => false,
            ],
            // Dynamic Default Value Text config
            'calculation_formula' => [
                'display' => 'Dynamic Default Value Text',
                'instructions' => <<<'EOT'
                    Enter the text in the manner you would like it to be displayed.

                    **Field References:** Use the field handle wrapped in square brackets to reference other field values (e.g., `[order_number]`, `[item_count]`, `[first_name]`).

                    **URL Parameters:** Use `[get_param:param_name]` to reference URL parameters from the current page (e.g., `[get_param:editor_email_address]`).
                    EOT,
                'type' => 'code',
                'mode' => 'handlebars',
                'mode_selectable' => false,
                'if' => [
                    'calculation_query' => 'equals true',
                ],
            ],
        ];
    }

    /**
     * Get the field modifications for the default value field.
     *
     * Returns an array containing the modifications needed to add display
     * conditions to the default field, hiding it when dynamic default value
     * is enabled.
     *
     * @return array<string, array<string, mixed>> Array with field modifications for the default field
     */
    protected function getDefaultFieldModifications(): array
    {
        return [
            'default' => [
                'if' => [
                    'calculation_query' => 'not true',
                ],
            ],
        ];
    }

    /**
     * Apply dynamic default value configuration to a parent config.
     *
     * This method takes a parent configuration and applies the dynamic default
     * value fields and modifications. It uses the ConfigSectionWrapper trait
     * methods to add fields and modify existing ones.
     *
     * @param  array<array-key, mixed>  $parentConfig  The parent configuration to modify (flat or sectioned)
     * @return array<array-key, mixed> The modified configuration with dynamic default value fields
     */
    protected function applyDynamicDefaultValueConfig(
        array $parentConfig
    ): array {
        // Get the field modifications for the default field
        $fieldModifications = $this->getDefaultFieldModifications();

        // Modify the parent config to add display conditions to the default field
        /** @var array<array-key, mixed> $parentConfig */
        $parentConfig = $this->modifyExistingFields(
            $fieldModifications,
            $parentConfig
        );

        // Clean up variables to free up memory
        unset(
            $fieldModifications
        );

        // ---
        // Return the modified parent configuration with dynamic default value
        // modifications applied
        // ---
        return $parentConfig;
    }
}
