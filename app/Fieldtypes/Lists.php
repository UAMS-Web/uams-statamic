<?php

declare(strict_types=1);

namespace App\Fieldtypes;

use App\Fieldtypes\Traits\ConfigSectionWrapper;
use Statamic\Fieldtypes\Lists as BaseLists;

/**
 * Extended Lists fieldtype with enhanced configuration and validation.
 *
 * This fieldtype extends Statamic's base Lists fieldtype and uses the
 * ConfigSectionWrapper trait to provide organized configuration sections
 * with automatic validation based on item count constraints.
 *
 * Key Features:
 *   - Inherits all functionality from Statamic's Lists fieldtype
 *   - Provides organized configuration sections for better user experience
 *   - Includes automatic validation for minimum and maximum item counts
 *   - Uses Laravel's array validation rules with proper error messages
 *   - Maintains DRY principles through trait-based implementation
 *
 * Configuration Sections:
 *   - Input Value and State: Contains default value configuration
 *   - Constraints: Contains min/max item count configuration
 *
 * Validation:
 *   - Automatically validates array length based on min_items/max_items config
 *   - Uses Laravel's array validation rules for proper error messages
 *   - Error messages display "items" instead of "characters" for clarity
 *
 * @see  ConfigSectionWrapper
 * @see  BaseLists
 */
class Lists extends BaseLists
{
    /*
     * Use ConfigSectionWrapper trait to organize configuration fields into sections.
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
        // Narrow the untyped base return value to a string icon name
        $icon = parent::icon();

        return \is_scalar($icon) || $icon instanceof \Stringable ? (string) $icon : '';
    }

    /**
     * Get the configuration fields organized in sections.
     *
     * This method uses the ConfigSectionWrapper trait to organize the
     * configuration fields into logical sections for better user experience.
     *
     * @return list<array<string, mixed>> The configuration field items
     */
    #[\Override]
    protected function configFieldItems(): array
    {
        // Define the new constraint fields
        $constraintFields = [
            'min_items' => [
                'display' => 'Minimum Number of Items',
                'instructions' => 'Set the minimum allowed number of items for this field.',
                'type' => 'integer',
                'default' => null,
                'validate' => ['gte:0'],
            ],
            'max_items' => [
                'display' => 'Maximum Number of Items',
                'instructions' => 'Set the maximum allowed number of items for this field.',
                'type' => 'integer',
                'default' => null,
                'validate' => ['gt:0'],
            ],
        ];

        // Create the additional sections
        $additionalSections = [
            [
                'display' => 'Constraints',
                'instructions' => 'Rules to enforce data format, range, and length requirements.',
                'fields' => $constraintFields,
            ],
        ];

        // Use the trait to wrap existing fields in sections
        return $this->wrapConfigFieldsInSections(
            'Input Value and State', // First section display
            [], // No additional fields for first section
            'after', // Position of additional fields
            $additionalSections // Additional sections
        );
    }

    /**
     * Provide validation rules for the Lists fieldtype based on configuration.
     *
     * This method implements automatic validation based on the min_items and
     * max_items configuration fields. It uses Laravel's array validation rules to
     * enforce minimum and maximum item count constraints with proper error messages.
     *
     * The method always includes the 'array' rule to ensure Laravel treats the field
     * as an array and uses appropriate error messages (e.g., "items" instead of
     * "characters").
     *
     * @return array<string> Validation rules (Laravel format), including 'array', 'between:X,Y', 'min:X', and/or 'max:X'
     */
    #[\Override]
    public function rules(): array
    {
        // Initialize the rules array with array type to ensure correct error messages
        $rules = ['array'];

        // Get the minimum items configuration value
        $minItems = $this->config('min_items');

        // Convert to integer if numeric, otherwise set to null
        $minItems = isset($minItems) && \is_numeric($minItems) ? (int) $minItems : null;

        // Check if we have a valid minimum items value (positive integer)
        $hasMinItems = $minItems !== null && $minItems > 0;

        // Get the maximum items configuration value
        $maxItems = $this->config('max_items');

        // Convert to integer if numeric, otherwise set to null
        $maxItems = isset($maxItems) && \is_numeric($maxItems) ? (int) $maxItems : null;

        // Check if we have a valid maximum items value (positive integer)
        $hasMaxItems = $maxItems !== null && $maxItems > 0;

        // Check if both min_items and max_items are set
        if ($hasMinItems && $hasMaxItems) {
            // Use Laravel's between rule
            $rules[] = sprintf('between:%d,%d', $minItems, $maxItems);
        } elseif ($hasMinItems) { // Check if only min_items is set
            // Use Laravel's min rule
            $rules[] = 'min:'.$minItems;
        } elseif ($hasMaxItems) { // Check if only max_items is set
            // Use Laravel's max rule
            $rules[] = 'max:'.$maxItems;
        }

        // Clean up variables to free up memory
        unset(
            $minItems,
            $maxItems
        );

        // Return the rules
        return $rules;
    }
}
