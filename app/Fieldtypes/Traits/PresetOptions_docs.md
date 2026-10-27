# PresetOptions Trait Documentation

## Overview

The `PresetOptions` trait provides functionality to add a `preset` config field to fieldtypes like `radio`, `checkboxes`, and `select`. When a preset is selected, it overrides the manually entered options with predefined lists (e.g., U.S. States).

## Usage

### Basic Implementation

```php
<?php

namespace App\Fieldtypes;

use App\Fieldtypes\Traits\ConfigSectionWrapper;
use App\Fieldtypes\Traits\PresetOptions;
use Statamic\Fieldtypes\Select as BaseSelect;

class Select extends BaseSelect
{
    use ConfigSectionWrapper;
    use PresetOptions;

    protected function configFieldItems(): array
    {
        $fieldsToAdd = [
            'Options' => [
                ...PresetOptions::presetConfigField(),
            ],
        ];

        return $this->addFieldsToExistingSections($fieldsToAdd);
    }
}
```

### Working with RandomizesOptions Trait

The `PresetOptions` trait works seamlessly with the `RandomizesOptions` trait. When both traits are used together, preset options are applied first, then randomization is applied:

```php
<?php

namespace App\Fieldtypes;

use App\Fieldtypes\Traits\ConfigSectionWrapper;
use App\Fieldtypes\Traits\PresetOptions;
use App\Fieldtypes\Traits\RandomizesOptions;
use Statamic\Fieldtypes\Select as BaseSelect;

class Select extends BaseSelect
{
    use ConfigSectionWrapper;
    use PresetOptions;
    use RandomizesOptions;

    protected function configFieldItems(): array
    {
        $fieldsToAdd = [
            'Options' => [
                ...PresetOptions::presetConfigField(),
                ...RandomizesOptions::randomizeOrderConfigField(),
            ],
        ];

        return $this->addFieldsToExistingSections($fieldsToAdd);
    }
}
```

## Available Presets

The trait includes the following predefined option lists:

- **U.S. States** (`us_states`) - All 50 U.S. states with abbreviations
- **U.S. Territories** (`us_territories`) - U.S. territories and districts
- **Canadian Provinces** (`canadian_provinces`) - All Canadian provinces and territories
- **Months** (`months`) - 12 months with numeric keys (01-12)
- **Days of Week** (`days_of_week`) - 7 days with numeric keys (1-7)
- **Yes/No** (`yes_no`) - Simple yes/no options
- **True/False** (`true_false`) - Boolean options

## Customizing Presets

To add custom presets or modify existing ones, override the `getAvailablePresets()` method in your fieldtype:

```php
protected function getAvailablePresets(): array
{
    $presets = parent::getAvailablePresets();
    
    // Add custom presets
    $presets['custom_list'] = [
        'option1' => 'Option 1',
        'option2' => 'Option 2',
        'option3' => 'Option 3',
    ];
    
    return $presets;
}
```

## Config Field

The trait provides a static method `presetConfigField()` that returns the config array for the preset selection dropdown:

```php
[
    'preset' => [
        'display' => 'Preset Options',
        'instructions' => 'Select a predefined list of options to use instead of manually entering options.',
        'type' => 'select',
        'options' => [
            '' => 'No Preset (Use Manual Options)',
            'us_states' => 'U.S. States',
            'us_territories' => 'U.S. Territories',
            'canadian_provinces' => 'Canadian Provinces',
            'months' => 'Months',
            'days_of_week' => 'Days of Week',
            'yes_no' => 'Yes/No',
            'true_false' => 'True/False',
        ],
        'default' => '',
    ],
]
```

## How It Works

1. When a preset is selected in the config, the trait's `getOptionsWithPreset()` method is called
2. This method checks if a preset is configured and retrieves the predefined options
3. If no preset is selected, it returns the original options from the parent fieldtype
4. The options are then processed by other traits (like `RandomizesOptions`) if present

## Integration with Existing Fieldtypes

The trait has been integrated into the following fieldtypes:

- `app/Fieldtypes/Select.php`
- `app/Fieldtypes/Radio.php`
- `app/Fieldtypes/Checkboxes.php`

Each fieldtype now includes the preset config field in their respective options sections. 
