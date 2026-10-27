<?php

declare(strict_types=1);

namespace App\Fieldtypes\Traits;

/**
 * Trait for wrapping existing configuration fields in sections.
 *
 * This trait provides methods to wrap existing configuration fields from
 * parent fieldtypes in sections, allowing for better organization of
 * configuration options without having to override the entire configFieldItems
 * method. This is particularly useful when you want to add sections around
 * existing fields without losing the benefits of inheritance.
 */
trait ConfigSectionWrapper
{
    /**
     * Get parent configuration fields.
     *
     * @param  ?array<string, mixed>  $parentFields  Optional parent fields. If null, calls parent::configFieldItems()
     * @return array<string, mixed> The parent configuration fields array
     */
    private function getParentFields(
        ?array $parentFields = null
    ): array {
        // Narrow the parent fieldtype's config items to the documented shape
        /** @var array<string, mixed> $parentConfigFieldItems */
        $parentConfigFieldItems = parent::configFieldItems();

        return $parentFields ?? $parentConfigFieldItems;
    }

    /**
     * Create a section with the given display name and fields.
     *
     * @param  string  $display  The display name for the section
     * @param  array<string, mixed>  $fields  The fields for the section
     * @return array<string, mixed> The section array with 'display' and 'fields' keys
     */
    private function createSection(
        string $display,
        array $fields
    ): array {
        return [
            'display' => __($display),
            'fields' => $fields,
        ];
    }

    /**
     * Wrap existing configuration fields in sections.
     *
     * This method allows you to create single or multiple sections with enhanced
     * control over the first section and additional sections. The first section can
     * include additional fields before or after parent fields, while additional
     * sections are fully customizable.
     *
     * @param  ?string  $firstSectionDisplay  Optional display name for the first section
     * @param  ?array<string, mixed>  $additionalFieldsForFirstSection  Additional fields to add to first section (null or [] for none)
     * @param  ?string  $additionalFieldsPosition  Position of additional fields relative to parent fields ('before', 'after', or
     *                                             null for default 'before')
     * @param  ?array<array-key, array<string, mixed>>  $sections  Array of sections, each with 'display' and 'fields' keys (null or [] for none)
     * @param  ?array<string, mixed>  $parentFields  Optional parent fields to wrap. If null, will call parent::configFieldItems()
     * @return list<array<string, mixed>> Array of sections, each with 'display' and 'fields' keys
     */
    protected function wrapConfigFieldsInSections(
        ?string $firstSectionDisplay = null,
        ?array $additionalFieldsForFirstSection = null,
        ?string $additionalFieldsPosition = null,
        ?array $sections = null,
        ?array $parentFields = null
    ): array {
        // Normalize additional fields to empty array if null
        $additionalFieldsForFirstSection ??= [];

        // Normalize sections to empty array if null
        $sections ??= [];

        // Set default position to 'after' if null
        $additionalFieldsPosition ??= 'after';

        // Initialize result array
        $result = [];

        // ---
        // If firstSectionDisplay is provided, create enhanced first section
        // ---

        // Check if first section display is provided
        if ($firstSectionDisplay !== null) {
            // Get parent fields (either provided or from parent method)
            $parentFields = $this->getParentFields($parentFields);

            // ---
            // Merge additional fields with parent fields for first section based on position
            // ---

            // Merge fields based on position (after or before)
            $allFields = $additionalFieldsPosition === 'after'
                ? \array_merge(
                    $parentFields,
                    $additionalFieldsForFirstSection
                )
                : \array_merge(
                    $additionalFieldsForFirstSection,
                    $parentFields
                );

            // Create first section with combined fields
            $result[] = $this->createSection(
                $firstSectionDisplay,
                $allFields
            );
        }

        // ---
        // Add any additional sections
        // ---

        // Loop through additional sections
        foreach ($sections as $section) {
            // ---
            // Validate section has required keys before adding
            // ---

            // Check if section has required keys
            if (
                isset($section['display'])
                && isset($section['fields'])
            ) {
                // Narrow the section display to a string for the section factory
                /** @var string $sectionDisplay */
                $sectionDisplay = $section['display'];

                // Narrow the section fields to a fields array for the section factory
                /** @var array<string, mixed> $sectionFields */
                $sectionFields = $section['fields'];

                // Add validated section to result
                $result[] = $this->createSection(
                    $sectionDisplay,
                    $sectionFields
                );
            }
        }

        // Return all sections
        return $result;
    }

    /**
     * Add fields to existing sections in parent configuration.
     *
     * This method preserves the existing section structure from the parent
     * fieldtype and adds new fields to specified sections. This is useful
     * when the parent already has sections defined and you want to add
     * fields without overriding the entire structure.
     *
     * @param  array<string, array<string, mixed>>  $fieldsToAdd  Array of fields to add, keyed by section display name
     *                                                            Example: ['Behavior' => ['min' => [...], 'max' => [...]]]
     * @param  ?array<array-key, array<string, mixed>>  $parentConfig  Optional parent configuration. If null, will call parent::configFieldItems()
     * @param  string  $position  Position of new fields relative to existing fields ('before', 'after', or
     *                            'replace'). Defaults to 'after'.
     * @return array<array-key, array<string, mixed>> The updated parent configuration with new fields added
     */
    protected function addFieldsToExistingSections(
        array $fieldsToAdd,
        ?array $parentConfig = null,
        string $position = 'after'
    ): array {
        // Get parent configuration if not provided
        $parentConfig ??= parent::configFieldItems();

        // Narrow the resolved parent config to the section shape
        /** @var array<array-key, array<string, mixed>> $parentConfig */

        // ---
        // Add fields to existing sections
        // ---

        // Loop through parent configuration sections
        foreach ($parentConfig as &$section) {
            // Get the section display name
            $sectionDisplay = $section['display'];

            // Narrow the section display name to a valid array key for lookups
            /** @var array-key $sectionDisplayKey */
            $sectionDisplayKey = $sectionDisplay;

            // Check if we have fields to add to this section
            if (isset($fieldsToAdd[$sectionDisplayKey]) && isset($section['fields'])) {
                // Add the new fields to the existing section based on position
                switch ($position) {
                    // Add fields before existing fields
                    case 'before':
                        // Narrow the existing section fields to an array for the merge
                        /** @var array<string, mixed> $existingSectionFields */
                        $existingSectionFields = $section['fields'];

                        // Merge the new fields with the existing fields
                        $section['fields'] = \array_merge(
                            $fieldsToAdd[$sectionDisplayKey],
                            $existingSectionFields
                        );

                        // Update the section fields with the new fields
                        break;

                        // Replace existing fields
                    case 'replace':
                        // Replace the existing fields with the new fields
                        $section['fields'] = $fieldsToAdd[$sectionDisplayKey];

                        // Update the section fields with the new fields
                        break;

                        // Add fields after existing fields
                    case 'after':
                    default:
                        // Narrow the existing section fields to an array for the merge
                        /** @var array<string, mixed> $existingSectionFields */
                        $existingSectionFields = $section['fields'];

                        // Merge the new fields with the existing fields
                        $section['fields'] = \array_merge(
                            $existingSectionFields,
                            $fieldsToAdd[$sectionDisplayKey]
                        );

                        // Update the section fields with the new fields
                        break;
                }
            }
        }

        // Return the updated parent configuration
        return $parentConfig;
    }

    /**
     * Add fields to existing sections with multiple positioning options.
     *
     * This method allows you to add different groups of fields to the same section
     * with different positioning options. This is useful when you want some fields
     * to appear before existing fields and others to appear after.
     *
     * @param  array<string, array<string, mixed>>  $fieldsToAddBefore  Array of fields to add before existing fields, keyed by section display name
     * @param  array<string, array<string, mixed>>  $fieldsToAddAfter  Array of fields to add after existing fields, keyed by section display name
     * @param  ?array<array-key, array<string, mixed>>  $parentConfig  Optional parent configuration. If null, will call parent::configFieldItems()
     * @return array<array-key, array<string, mixed>> The updated parent configuration with new fields added
     */
    protected function addFieldsToExistingSectionsWithMultiplePositions(
        array $fieldsToAddBefore,
        array $fieldsToAddAfter,
        ?array $parentConfig = null
    ): array {
        // Get parent configuration if not provided
        $parentConfig ??= parent::configFieldItems();

        // Narrow the resolved parent config to the section shape
        /** @var array<array-key, array<string, mixed>> $parentConfig */

        // ---
        // Add fields to existing sections
        // ---

        // Loop through parent configuration sections
        foreach ($parentConfig as &$section) {
            // Get the section display name
            $sectionDisplay = $section['display'];

            // Narrow the section display name to a valid array key for lookups
            /** @var array-key $sectionDisplayKey */
            $sectionDisplayKey = $sectionDisplay;

            // Check if we have fields to add to this section
            if (isset($section['fields'])) {
                // Narrow the current section fields to an array for the merges
                /** @var array<string, mixed> $sectionFields */
                $sectionFields = $section['fields'];

                // Check if there are fields to add before the existing fields
                if (isset($fieldsToAddBefore[$sectionDisplayKey])) {
                    // Merge fields before existing fields
                    $sectionFields = \array_merge(
                        $fieldsToAddBefore[$sectionDisplayKey],
                        $sectionFields
                    );
                }

                // Check if there are fields to add after the existing fields
                if (isset($fieldsToAddAfter[$sectionDisplayKey])) {
                    // Merge fields after the existing fields
                    $sectionFields = \array_merge(
                        $sectionFields,
                        $fieldsToAddAfter[$sectionDisplayKey]
                    );
                }

                // Update the section fields with the new fields
                $section['fields'] = $sectionFields;
            }
        }

        // Return the updated parent configuration
        return $parentConfig;
    }

    /**
     * Modify existing configuration fields from base Statamic fieldtypes.
     *
     * This method allows you to modify existing configuration fields by setting
     * display conditions, changing field types, or modifying any other configuration
     * values. It works with both flat field structures (like Radio fieldtype) and
     * sectioned field structures.
     *
     * @param  array<string, array<string, mixed>>  $fieldModifications  Array of field modifications, keyed by field name.
     *                                                                   Example: ['default' => ['if' => ['calculation_query' => 'equals true']]]
     * @param  ?array<array-key, mixed>  $parentConfig  Optional parent configuration. If null, will call parent::configFieldItems()
     * @return array<array-key, mixed> The modified configuration with updated fields
     */
    protected function modifyExistingFields(
        array $fieldModifications,
        ?array $parentConfig = null
    ): array {
        // Get parent configuration if not provided
        $parentConfig ??= parent::configFieldItems();

        // Narrow the resolved parent config to a keyed array
        /** @var array<array-key, mixed> $parentConfig */

        // Check if parent config is sectioned or flat
        $isSectioned = $this->isSectionedConfig($parentConfig);

        // Check if configuration is sectioned
        if ($isSectioned) {
            // Narrow the sectioned parent config to the section shape for modification
            /** @var array<array-key, array<string, mixed>> $sectionedParentConfig */
            $sectionedParentConfig = $parentConfig;

            // Handle sectioned configuration
            return $this->modifyFieldsInSections(
                $fieldModifications,
                $sectionedParentConfig
            );
        }

        // Otherwise
        // Narrow the flat parent config to a flat fields array for modification
        /** @var array<string, mixed> $flatParentConfig */
        $flatParentConfig = $parentConfig;

        // Handle flat configuration
        return $this->modifyFieldsInFlatConfig(
            $fieldModifications,
            $flatParentConfig
        );
    }

    /**
     * Check if the configuration is sectioned or flat.
     *
     * @param  array<array-key, mixed>  $config  The configuration array to check
     * @return bool True if sectioned, false if flat
     */
    private function isSectionedConfig(
        array $config
    ): bool {
        // ---
        // If the first element has 'display' and 'fields' keys, it's sectioned
        // ---

        // Get first element of configuration
        $firstElement = reset($config);

        // Return whether first element is a sectioned structure
        return \is_array($firstElement) && isset($firstElement['display']) && isset($firstElement['fields']);
    }

    /**
     * Modify fields in a sectioned configuration.
     *
     * @param  array<string, array<string, mixed>>  $fieldModifications  Array of field modifications
     * @param  array<array-key, array<string, mixed>>  $parentConfig  The parent configuration
     * @return array<array-key, array<string, mixed>> The modified sectioned configuration
     */
    private function modifyFieldsInSections(
        array $fieldModifications,
        array $parentConfig
    ): array {
        // Iterate through each section
        foreach ($parentConfig as &$section) {
            // Check if section has valid fields array
            if (
                isset($section['fields'])
                && \is_array($section['fields'])
            ) {
                // Narrow the section fields to a flat fields array
                /** @var array<string, mixed> $sectionFields */
                $sectionFields = $section['fields'];

                // Modify fields in this section
                $section['fields'] = $this->modifyFieldsInFlatConfig(
                    $fieldModifications,
                    $sectionFields
                );
            }
        }

        // Return the modified configuration
        return $parentConfig;
    }

    /**
     * Modify fields in a flat configuration.
     *
     * @param  array<string, array<string, mixed>>  $fieldModifications  Array of field modifications
     * @param  array<string, mixed>  $parentConfig  The parent configuration
     * @return array<string, mixed> The modified flat configuration
     */
    private function modifyFieldsInFlatConfig(
        array $fieldModifications,
        array $parentConfig
    ): array {
        // Iterate through each field modification
        foreach ($fieldModifications as $fieldName => $modifications) {
            // Check if the field exists in the parent config
            if (isset($parentConfig[$fieldName])) {
                // Narrow the existing field to an array for the merge
                /** @var array<string, mixed> $existingField */
                $existingField = $parentConfig[$fieldName];

                // Apply modifications to the existing field
                $parentConfig[$fieldName] = \array_merge(
                    $existingField,
                    $modifications
                );
            }
        }

        // Return the modified configuration
        return $parentConfig;
    }
}
