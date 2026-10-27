# Fieldtype Section Wrapping

This document explains how to add sections around existing configuration fields in Statamic fieldtypes using the `ConfigSectionWrapper` trait.

## Problem

The original `Floatval` fieldtype in Statamic has a simple configuration structure:

```php
protected function configFieldItems(): array
{
    return [
        'default' => [
            'display' => __('Default Value'),
            'instructions' => __('statamic::messages.fields_default_instructions'),
            'type' => 'text',
        ],
    ];
}
```

However, you want to organize these fields into sections for better user experience, similar to how the `Integer` fieldtype is structured:

```php
protected function configFieldItems(): array
{
    return [
        [
            'display' => __('Behavior'),
            'fields' => [
                'placeholder' => [...],
                'default' => [...],
            ],
        ],
        [
            'display' => __('Appearance'),
            'fields' => [
                'prepend' => [...],
                'append' => [...],
            ],
        ],
    ];
}
```

## Solution

The `ConfigSectionWrapper` trait provides a single method to wrap existing configuration fields in sections without having to override the entire `configFieldItems` method.

### Basic Usage

1. **Use the trait in your fieldtype:**

```php
<?php

namespace App\Fieldtypes;

use Statamic\Fieldtypes\Floatval as BaseFloatval;
use App\Fieldtypes\Traits\ConfigSectionWrapper;

class Floatval extends BaseFloatval
{
    use ConfigSectionWrapper;

    protected function configFieldItems(): array
    {
        // Wrap all parent fields in a "Behavior" section
        return $this->wrapConfigFieldsInSections('Behavior');
    }
}
```

2. **Available method:**

- `wrapConfigFieldsInSections(?string $firstSectionDisplay = null, ?array $additionalFieldsForFirstSection = null, ?array $sections = null, ?array $parentFields = null): array`
  - Wraps fields in single or multiple sections
  - Optional enhanced first section with additional fields before parent fields
  - Each section should have 'display' and 'fields' keys
  - `null` and `[]` are equivalent for `$additionalFieldsForFirstSection` and `$sections`
  - If `$parentFields` is null, uses `parent::configFieldItems()`

### Advanced Usage

**Simple single section:**

```php
protected function configFieldItems(): array
{
    // Wrap all parent fields in a "Behavior" section
    return $this->wrapConfigFieldsInSections('Behavior');
}
```

**Single section with additional fields:**

```php
protected function configFieldItems(): array
{
    // Define additional fields to add before parent fields
    $additionalFields = [
        'placeholder' => [
            'display' => __('Placeholder'),
            'instructions' => __('statamic::fieldtypes.text.config.placeholder'),
            'type' => 'text',
        ],
    ];
    
    // Wrap parent fields in a section with additional fields first
    return $this->wrapConfigFieldsInSections('Behavior', $additionalFields);
}
```

This would result in:
```php
protected function configFieldItems(): array
{
    return [
        [
            'display' => __('Behavior'),
            'fields' => [
                'placeholder' => [
                    'display' => __('Placeholder'),
                    'instructions' => __('statamic::fieldtypes.text.config.placeholder'),
                    'type' => 'text',
                ],
                'default' => [
                    'display' => __('Default Value'),
                    'instructions' => __('statamic::messages.fields_default_instructions'),
                    'type' => 'text',
                ],
            ],
        ],
    ];
}
```

**Multiple sections only:**

```php
protected function configFieldItems(): array
{
    // Define additional sections
    $sections = [
        [
            'display' => 'Appearance',
            'fields' => [
                'prepend' => [
                    'display' => __('Prepend'),
                    'instructions' => __('statamic::fieldtypes.text.config.prepend'),
                    'type' => 'text',
                ],
                'append' => [
                    'display' => __('Append'),
                    'instructions' => __('statamic::fieldtypes.text.config.append'),
                    'type' => 'text',
                ],
            ],
        ],
    ];
    
    // Wrap in multiple sections (null and [] are equivalent)
    return $this->wrapConfigFieldsInSections(null, null, $sections);
    // OR
    return $this->wrapConfigFieldsInSections(null, [], $sections);
}
```

**Multiple sections with enhanced first section:**

```php
protected function configFieldItems(): array
{
    // Define additional fields for the first section
    $additionalFieldsForFirstSection = [
        'placeholder' => [
            'display' => __('Placeholder'),
            'instructions' => __('statamic::fieldtypes.text.config.placeholder'),
            'type' => 'text',
        ],
    ];
    
    // Define additional sections
    $additionalSections = [
        [
            'display' => 'Appearance',
            'fields' => [
                'prepend' => [
                    'display' => __('Prepend'),
                    'instructions' => __('statamic::fieldtypes.text.config.prepend'),
                    'type' => 'text',
                ],
                'append' => [
                    'display' => __('Append'),
                    'instructions' => __('statamic::fieldtypes.text.config.append'),
                    'type' => 'text',
                ],
            ],
        ],
    ];
    
    // Wrap parent fields in multiple sections with additional fields in the first section
    return $this->wrapConfigFieldsInSections('Behavior', $additionalFieldsForFirstSection, $additionalSections);
}
```

This would result in:
```php
protected function configFieldItems(): array
{
    return [
        [
            'display' => __('Behavior'),
            'fields' => [
                'placeholder' => [
                    'display' => __('Placeholder'),
                    'instructions' => __('statamic::fieldtypes.text.config.placeholder'),
                    'type' => 'text',
                ],
                'default' => [
                    'display' => __('Default Value'),
                    'instructions' => __('statamic::messages.fields_default_instructions'),
                    'type' => 'text',
                ],
            ],
        ],
        [
            'display' => __('Appearance'),
            'fields' => [
                'prepend' => [
                    'display' => __('Prepend'),
                    'instructions' => __('statamic::fieldtypes.text.config.prepend'),
                    'type' => 'text',
                ],
                'append' => [
                    'display' => __('Append'),
                    'instructions' => __('statamic::fieldtypes.text.config.append'),
                    'type' => 'text',
                ],
            ],
        ],
    ];
}
```

**Selective field wrapping:**

```php
protected function configFieldItems(): array
{
    // Only wrap specific fields from parent
    $specificFields = [
        'default' => parent::configFieldItems()['default'] ?? [],
        'placeholder' => parent::configFieldItems()['placeholder'] ?? [],
    ];
    
    return $this->wrapConfigFieldsInSections('Basic Settings', null, null, $specificFields);
    // OR
    return $this->wrapConfigFieldsInSections('Basic Settings', [], [], $specificFields);
}
```

## Benefits

1. **Maintains inheritance:** Changes to parent fieldtype configuration are automatically inherited
2. **Clean code:** No need to duplicate parent field definitions
3. **Reusable:** The trait can be used across multiple fieldtypes
4. **Flexible:** Supports both single and multiple section wrapping
5. **Extensible:** Can add new fields before existing ones
6. **Organized:** Can create multiple sections with enhanced first section
7. **Ultra-simplified API:** Only one method to learn and use
8. **Intuitive parameter order:** Most commonly used parameters come first
9. **Flexible parameters:** `null` and `[]` are equivalent for optional array parameters
10. **Future-proof:** If Statamic modifies the parent fieldtype, your changes will still work

## Implementation Details

The trait provides two main methods:

### `wrapConfigFieldsInSections()`

- Takes optional first section display name, optional additional fields for first section, optional position parameter, optional sections array, and optional parent fields
- `null` and `[]` are treated equivalently for `$additionalFieldsForFirstSection` and `$sections` parameters
- If first section display name is provided, creates enhanced first section with additional fields before or after parent fields based on position
- Adds additional sections after the first section
- Automatically calls `parent::configFieldItems()` if no parent fields provided
- Returns properly formatted sections structure
- Handles all use cases: single section, single section with additional fields, multiple sections, and multiple sections with enhanced first section

### `addFieldsToExistingSections()`

- Takes an array of fields to add (keyed by section display name), optional parent configuration, and optional position parameter
- Preserves existing section structure from parent fieldtype
- Adds new fields to specified sections without overriding the entire structure
- Useful when parent already has sections defined
- Automatically calls `parent::configFieldItems()` if no parent configuration provided
- Supports three positioning options:
  - `'after'` (default): Adds new fields after existing fields
  - `'before'`: Adds new fields before existing fields
  - `'replace'`: Replaces existing fields with new fields
- Returns the modified configuration with new fields added to existing sections

### `addFieldsToExistingSectionsWithMultiplePositions()`

- Takes two arrays of fields to add (before and after existing fields), keyed by section display name
- Allows you to add different groups of fields to the same section with different positioning
- Useful when you want some fields to appear before existing fields and others to appear after
- Automatically calls `parent::configFieldItems()` if no parent configuration provided
- Returns the modified configuration with fields added before and after existing fields

### `modifyExistingFields()`

- Takes an array of field modifications keyed by field name and optional parent configuration
- Allows you to modify existing configuration fields by setting display conditions, changing field types, or modifying any other configuration values
- Works with both flat field structures (like Radio fieldtype) and sectioned field structures
- Automatically calls `parent::configFieldItems()` if no parent configuration provided
- Returns the modified configuration with existing fields updated
- Useful for adding display conditions, changing field types, or modifying any configuration values

**Example usage:**
```php
// Modify the 'default' field to add display conditions
$fieldModifications = [
    'default' => [
        'if' => [
            'calculation_query' => 'equals true',
        ],
    ],
];

$config = $this->modifyExistingFields($fieldModifications);
```

**Example with multiple field modifications:**
```php
// Modify multiple fields at once
$fieldModifications = [
    'default' => [
        'if' => [
            'calculation_query' => 'equals true',
        ],
        'instructions' => 'Updated instructions for the default field',
    ],
    'placeholder' => [
        'type' => 'textarea',
        'character_limit' => 100,
    ],
];

$config = $this->modifyExistingFields($fieldModifications);
```

## Combining Methods

You can combine multiple methods for complex scenarios:

### Adding Fields to Existing Sections + Creating New Sections

```php
protected function configFieldItems(): array
{
    // Define fields to add to existing sections
    $fieldsToAdd = [
        'Behavior' => [
            'min' => [
                'display' => __('Min'),
                'instructions' => 'The minimum value',
                'type' => 'integer',
            ],
            'max' => [
                'display' => __('Max'),
                'instructions' => 'The maximum value',
                'type' => 'integer',
            ],
        ],
    ];

    // Start with existing sections and add fields to Behavior
    $config = $this->addFieldsToExistingSections($fieldsToAdd);

    // Add a new section with additional fields
    $newSection = $this->createSection('Validation', [
        'custom_rule' => [
            'display' => __('Custom Rule'),
            'instructions' => 'A custom validation rule',
            'type' => 'text',
        ],
        'step' => [
            'display' => __('Step'),
            'instructions' => 'The increment step value',
            'type' => 'integer',
        ],
    ]);

    // Combine existing sections with the new section
    return array_merge($config, [$newSection]);
}
```

This approach allows you to:
1. Add fields to existing sections (like adding min/max to Behavior)
2. Create completely new sections (like adding a Validation section)
3. Maintain all existing functionality and structure
4. Keep the code clean and maintainable

**Result:** The fieldtype will have the original sections with new fields added, plus any new sections you create.

### Modifying Existing Fields + Adding New Fields

```php
protected function configFieldItems(): array
{
    // First, modify existing fields (e.g., add display conditions)
    $fieldModifications = [
        'default' => [
            'if' => [
                'calculation_query' => 'equals true',
            ],
        ],
    ];

    // Get parent config with modifications applied
    $config = $this->modifyExistingFields($fieldModifications);

    // Then, add new fields to existing sections
    $fieldsToAdd = [
        'Behavior' => [
            'min' => [
                'display' => __('Min'),
                'instructions' => 'The minimum value',
                'type' => 'integer',
            ],
            'max' => [
                'display' => __('Max'),
                'instructions' => 'The maximum value',
                'type' => 'integer',
            ],
        ],
    ];

    // Add fields to the modified config
    $config = $this->addFieldsToExistingSections($fieldsToAdd, $config);

    return $config;
}
```

**Result:** The fieldtype will have modified existing fields (with display conditions, updated types, etc.) plus new fields added to existing sections.

### Complete Example: Radio Fieldtype

The `app/Fieldtypes/Radio.php` file demonstrates combining multiple methods:

```php
protected function configFieldItems(): array
{
    // Get parent configuration with conditional visibility applied
    $parentConfig = $this->getParentConfigWithConditionalVisibility();

    // Modify existing fields (add display conditions to default field)
    $fieldModifications = [
        'default' => [
            'if' => [
                'calculation_query' => 'equals true',
            ],
        ],
    ];

    // Apply modifications to parent config
    $parentConfig = $this->modifyExistingFields($fieldModifications, $parentConfig);

    // Define fields to add before the options field
    $fieldsToAddBefore = [
        'Radio Options' => [
            // Preset Options config
            ...$this->presetConfigField(),
            // Enable Other Option config
            'enable_other' => [
                'display' => __('Enable Other Option'),
                'instructions' => __('Allow users to select "Other" and enter a custom value.'),
                'type' => 'toggle',
                'default' => false,
            ],
        ],
        'Appearance & Behavior' => [
            // Enable Dynamic Default Value config
            'calculation_query' => [
                'display' => 'Enable Dynamic Default Value',
                'instructions' => 'Pre-populate the field with contextual or dynamic data.',
                'type' => 'toggle',
                'default' => false,
            ],
            // Dynamic Default Value Text config
            'calculation_formula' => [
                'display' => 'Dynamic Default Value Text',
                'instructions' => 'Enter the text in the manner you would like it to be displayed.',
                'type' => 'code',
                'mode' => 'handlebars',
                'mode_selectable' => false,
                'if' => [
                    'calculation_query' => 'equals true',
                ],
            ],
        ],
    ];

    // Define fields to add after the options field (randomize)
    $fieldsToAddAfter = [
        'Radio Options' => [
            // Randomize Order of Options config
            ...RandomizesOptions::randomizeOrderConfigField(),
        ],
    ];

    // Combine all modifications and additions
    return $this->addFieldsToExistingSectionsWithMultiplePositions($fieldsToAddBefore, $fieldsToAddAfter, $parentConfig);
}
```

**Result:** The Radio fieldtype has:
1. Modified existing fields (default field with display conditions)
2. New fields added before existing fields (preset options, enable other, calculation fields)
3. New fields added after existing fields (randomize order)
4. All while maintaining the original section structure and inheritance

### Field Positioning Examples

**Adding fields after existing fields (default behavior):**
```php
$fieldsToAdd = [
    'Behavior' => [
        'custom_field' => [
            'display' => __('Custom Field'),
            'type' => 'text',
        ],
    ],
];

return $this->addFieldsToExistingSections($fieldsToAdd);
// OR explicitly
return $this->addFieldsToExistingSections($fieldsToAdd, null, 'after');
```

**Adding fields before existing fields:**
```php
$fieldsToAdd = [
    'Options' => [
        'preset' => [
            'display' => __('Preset Options'),
            'type' => 'select',
        ],
    ],
];

return $this->addFieldsToExistingSections($fieldsToAdd, null, 'before');
```

**Replacing existing fields:**
```php
$fieldsToAdd = [
    'Behavior' => [
        'new_field' => [
            'display' => __('New Field'),
            'type' => 'text',
        ],
    ],
];

return $this->addFieldsToExistingSections($fieldsToAdd, null, 'replace');
```

**Multiple positioning for the same section:**
```php
// Fields to add before existing fields
$fieldsToAddBefore = [
    'Options' => [
        'preset' => [
            'display' => __('Preset Options'),
            'type' => 'select',
        ],
        'enable_other' => [
            'display' => __('Enable Other Option'),
            'type' => 'toggle',
        ],
    ],
];

// Fields to add after existing fields
$fieldsToAddAfter = [
    'Options' => [
        'randomize_order' => [
            'display' => __('Randomize Order'),
            'type' => 'toggle',
        ],
    ],
];

return $this->addFieldsToExistingSectionsWithMultiplePositions($fieldsToAddBefore, $fieldsToAddAfter);
```

## Example: Floatval Fieldtype

The `app/Fieldtypes/Floatval.php` file demonstrates this approach:

```php
<?php

namespace App\Fieldtypes;

use Statamic\Fieldtypes\Floatval as BaseFloatval;
use App\Fieldtypes\Traits\ConfigSectionWrapper;

class Floatval extends BaseFloatval
{
    use ConfigSectionWrapper;

    protected static $title = 'Number';

    public function keywords(): array
    {
        $keywords = [
            'float', 'number', 'numeric', 'decimal', 'floatval',
        ];
        return array_merge(parent::keywords(), $keywords);
    }

    protected function configFieldItems(): array
    {
        // Define additional fields for the first section
        $additionalFieldsForFirstSection = [
            'placeholder' => [
                'display' => __('Placeholder'),
                'instructions' => __('statamic::fieldtypes.text.config.placeholder'),
                'type' => 'text',
            ],
        ];

        // Define additional sections
        $additionalSections = [
            [
                'display' => 'Appearance',
                'fields' => [
                    'prepend' => [
                        'display' => __('Prepend'),
                        'instructions' => __('statamic::fieldtypes.text.config.prepend'),
                        'type' => 'text',
                    ],
                    'append' => [
                        'display' => __('Append'),
                        'instructions' => __('statamic::fieldtypes.text.config.append'),
                        'type' => 'text',
                    ],
                ],
            ],
        ];

        // Wrap parent fields in multiple sections with additional fields in the first section
        return $this->wrapConfigFieldsInSections('Behavior', $additionalFieldsForFirstSection, $additionalSections);
    }
}
```

This results in the `default` field being wrapped in a "Behavior" section with an additional placeholder field, plus an "Appearance" section with prepend and append fields, while maintaining all the original functionality and inheritance from the parent fieldtype.

## Files Created

1. `app/Fieldtypes/Traits/ConfigSectionWrapper.php` - The trait providing section wrapping functionality
2. `app/Fieldtypes/Floatval.php` - Updated to use the trait
3. `app/Fieldtypes/Traits/ConfigSectionWrapper_docs.md` - This documentation

## Usage in Other Fieldtypes

To use this approach with other fieldtypes, simply:

1. Extend the parent fieldtype
2. Use the `ConfigSectionWrapper` trait from `App\Fieldtypes\Traits\ConfigSectionWrapper`
3. Override `configFieldItems()` to call `wrapConfigFieldsInSections()` with the appropriate parameters

This approach provides a clean, maintainable way to organize fieldtype configuration without losing the benefits of inheritance. 
