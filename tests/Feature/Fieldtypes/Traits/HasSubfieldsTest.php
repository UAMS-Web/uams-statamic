<?php

declare(strict_types=1);

use App\Fieldtypes\Traits\HasSubfields;
use Statamic\Fields\Field;
use Statamic\Fields\Fieldtype;
use Tests\Support\Doubles\HasSubfieldsHarness;
use Tests\TestCase;

uses(TestCase::class);

covers(HasSubfields::class);

/**
 * The `HasSubfields` trait's storage mapping, the subfield list it hands the
 * Control Panel's Vue component, its preset lookups, its value normalization and
 * its caches, driven through a harness whose subfields and presets the test sets
 * (UAMS-Web/uams-statamic#312). The harness has a text subfield, two select
 * subfields sharing the `region` canonical handle (one per preset), and a
 * textarea excluded by default.
 *
 * @command  php artisan test --compact tests/Feature/Fieldtypes/Traits/HasSubfieldsTest.php
 */

/**
 * A harness fieldtype attached to a field with the given config.
 *
 * @param  array<array-key, mixed>  $config
 */
function hasSubfieldsHarness(array $config = []): HasSubfieldsHarness
{
    $harness = new HasSubfieldsHarness;
    $harness->definitions = [
        [
            'handle' => 'first_name',
            'display' => 'First Name',
            'type' => 'text',
            'inclusion_default' => true,
            'required_default' => true,
            'label_default' => 'First',
            'placeholder_default' => 'Enter first name',
            'default_value' => '',
            'autocomplete' => 'given-name',
        ],
        [
            'handle' => 'region_us',
            'canonical_handle' => 'region',
            'display' => 'State',
            'type' => 'select',
            'inclusion_default' => true,
            'required_default' => false,
            'default_choices' => ['AR' => 'Arkansas', 'TX' => 'Texas'],
        ],
        [
            'handle' => 'region_ca',
            'canonical_handle' => 'region',
            'display' => 'Province',
            'type' => 'select',
            'inclusion_default' => true,
            'required_default' => false,
            'default_choices' => ['ON' => 'Ontario', 'QC' => 'Quebec'],
        ],
        [
            'handle' => 'notes',
            'display' => 'Notes',
            'type' => 'textarea',
            'inclusion_default' => false,
        ],
    ];
    $harness->presets = ['us' => 'United States', 'ca' => 'Canada'];
    $harness->presetConditions = [
        'region_us' => ['preset' => 'us'],
        'region_ca' => ['preset' => 'ca'],
    ];
    $harness->setField(new Field('contact', ['type' => 'has_subfields_harness', ...$config]));

    return $harness;
}

/**
 * The handles of the subfields the harness hands its Vue component.
 *
 * @param  array<array-key, mixed>  $config
 * @return list<mixed>
 */
function hasSubfieldsVueHandles(array $config = []): array
{
    return array_column(hasSubfieldsHarness($config)->getSubfieldsForVue(), 'handle');
}

it('reads the subfield definitions once until a cache clear', function (): void {
    $harness = hasSubfieldsHarness();
    $harness->definitions();
    $harness->definitions();

    expect($harness->definitionReads)->toBe(1);

    $harness->clearSubfieldDefinitionsCache();
    $harness->definitions();

    expect($harness->definitionReads)->toBe(2);
});

it('clears the definitions and the config together with clearAllCaches', function (): void {
    $harness = hasSubfieldsHarness(['preset' => 'us']);
    $harness->definitions();
    $harness->cachedConfig();
    $harness->setField(new Field('contact', ['type' => 'has_subfields_harness', 'preset' => 'ca']));

    expect($harness->cachedConfig()['preset'] ?? null)->toBe('us');

    $harness->clearAllCaches();
    $harness->definitions();

    expect($harness->cachedConfig()['preset'] ?? null)->toBe('ca')
        ->and($harness->definitionReads)->toBe(2);
});

it('stores each canonical handle once, turning a select key into its label by default', function (): void {
    expect(hasSubfieldsHarness()->forStorage([
        'first_name' => 'James',
        'region_us' => 'TX',
        'region_ca' => 'ON',
        'unknown' => 'ignored',
    ]))->toBe([
        ['key' => 'first_name', 'value' => 'James'],
        ['key' => 'region', 'value' => 'Texas'],
    ]);
});

it('stores a later subfield under the canonical handle when the earlier one has no value', function (): void {
    expect(hasSubfieldsHarness()->forStorage(['region_ca' => 'QC']))->toBe([
        ['key' => 'region', 'value' => 'Quebec'],
    ]);
});

it('keeps a select value that is not one of its keys in label mode', function (mixed $value): void {
    expect(hasSubfieldsHarness()->forStorage(['region_us' => $value]))->toBe([
        ['key' => 'region', 'value' => $value],
    ]);
})->with([
    'an existing label' => ['Texas'],
    'an unknown string' => ['Utah'],
    'an integer' => [7],
    'an array' => [['TX']],
]);

it('turns a select label into its key when the subfield stores keys', function (): void {
    $harness = hasSubfieldsHarness(['region_us_select_value_type' => 'key']);

    expect($harness->forStorage(['region_us' => 'Arkansas']))->toBe([['key' => 'region', 'value' => 'AR']])
        ->and($harness->forStorage(['region_us' => 'Utah']))->toBe([['key' => 'region', 'value' => 'Utah']])
        ->and($harness->forStorage(['region_us' => 'TX']))->toBe([['key' => 'region', 'value' => 'TX']]);
});

it('stores a select value unchanged for any other value type setting', function (): void {
    expect(hasSubfieldsHarness(['region_us_select_value_type' => 'raw'])->forStorage(['region_us' => 'TX']))->toBe([
        ['key' => 'region', 'value' => 'TX'],
    ]);
});

it('maps stored rows back onto every subfield sharing the key, skipping malformed rows', function (): void {
    expect(hasSubfieldsHarness()->fromStorage([
        ['key' => 'first_name', 'value' => 'James'],
        ['key' => 'region', 'value' => 'Texas'],
        ['key' => 'notes'],
        ['value' => 'no key'],
        'not a row',
        ['key' => 'unknown', 'value' => 'ignored'],
    ]))->toBe([
        'first_name' => 'James',
        'region_us' => 'Texas',
        'region_ca' => 'Texas',
        'notes' => null,
    ]);
});

it('hands the Vue component the included subfields visible for the first preset by default', function (): void {
    expect(hasSubfieldsHarness()->getSubfieldsForVue())->toBe([
        [
            'handle' => 'first_name',
            'vue_handle' => 'first_name',
            'label' => 'First',
            'type' => 'text',
            'options' => null,
            'width' => null,
            'tailwind_width_class' => 'w-full @lg:w-1/2 @4xl:w-1/2 @7xl:w-1/3',
            'required' => true,
            'include' => true,
            'canonical_handle' => null,
            'autocomplete' => 'given-name',
            'default' => '',
            'placeholder' => 'Enter first name',
        ],
        [
            'handle' => 'region',
            'vue_handle' => 'region',
            'label' => 'State',
            'type' => 'select',
            'options' => ['AR' => 'Arkansas', 'TX' => 'Texas'],
            'width' => null,
            'tailwind_width_class' => 'w-full @lg:w-1/2 @4xl:w-1/2 @7xl:w-1/3',
            'required' => false,
            'include' => true,
            'canonical_handle' => 'region',
            'autocomplete' => null,
            'default' => null,
            'select_value_type' => 'label',
        ],
    ]);
});

it('shows the subfields for the configured preset', function (): void {
    expect(hasSubfieldsVueHandles(['preset' => 'ca']))->toBe(['first_name', 'region'])
        ->and(hasSubfieldsHarness(['preset' => 'ca'])->getSubfieldsForVue()[1]['label'] ?? null)->toBe('Province');
});

it('shows no preset-restricted subfield for a preset none of them match', function (): void {
    expect(hasSubfieldsVueHandles(['preset' => 'mx']))->toBe(['first_name']);
});

it('shows a subfield whose condition names another config key only when that key matches', function (): void {
    $config = ['notes_include' => true];
    $conditioned = static function (array $config): array {
        $harness = hasSubfieldsHarness($config);
        $harness->presetConditions['notes'] = ['audience' => 'staff'];

        return array_column($harness->getSubfieldsForVue(), 'handle');
    };

    expect($conditioned($config))->toBe(['first_name', 'region'])
        ->and($conditioned([...$config, 'audience' => 'staff']))->toBe(['first_name', 'region', 'notes'])
        ->and($conditioned([...$config, 'audience' => 'public']))->toBe(['first_name', 'region']);
});

it('applies the label, width, required, default and placeholder overrides from config', function (): void {
    $subfields = hasSubfieldsHarness([
        'first_name_label' => 'Given name',
        'first_name_width' => 100,
        'first_name_required' => false,
        'first_name_default' => 'Jim',
        'first_name_placeholder' => 'e.g. James',
        'notes_include' => true,
        'region_us_include' => false,
    ])->getSubfieldsForVue();

    expect(array_column($subfields, 'handle'))->toBe(['first_name', 'notes'])
        ->and($subfields[0])->toMatchArray([
            'label' => 'Given name',
            'width' => 100,
            'tailwind_width_class' => 'w-full',
            'required' => false,
            'default' => 'Jim',
            'placeholder' => 'e.g. James',
        ])
        ->and($subfields[1])->toMatchArray([
            'label' => 'Notes',
            'required' => false,
            'placeholder' => null,
        ]);
});

it('shows a select default as its label in label mode and as its key in key mode', function (): void {
    $default = static fn (array $config): mixed => hasSubfieldsHarness(['preset' => 'ca', ...$config])->getSubfieldsForVue()[1]['default'] ?? null;

    expect($default(['region_ca_default' => 'ON']))->toBe('Ontario')
        ->and($default(['region_ca_default' => 'Quebec']))->toBe('Quebec')
        ->and($default(['region_ca_default' => 'ON', 'region_ca_select_value_type' => 'key']))->toBe('ON');
});

it('preloads the Vue subfields and the fieldtype handle', function (): void {
    $harness = hasSubfieldsHarness();

    expect($harness->preload())->toBe([
        'subfields' => $harness->getSubfieldsForVue(),
        'fieldtype_handle' => 'has_subfields_harness',
    ]);
});

it('validates the parent value only as an array', function (): void {
    expect(hasSubfieldsHarness()->rules())->toBe(['array']);
});

it('passes an array through and turns anything else into an empty array on :dataset', function (string $method): void {
    $harness = hasSubfieldsHarness();

    expect($harness->{$method}(['first_name' => 'James']))->toBe(['first_name' => 'James'])
        ->and($harness->{$method}('James'))->toBe([])
        ->and($harness->{$method}(null))->toBe([]);
})->with(['preProcess', 'process']);

it('fills every subfield for validation once any of them has a value', function (): void {
    expect(hasSubfieldsHarness()->preProcessValidatable(['region' => 'TX', 'extra' => 'kept']))->toBe([
        'region' => 'TX',
        'extra' => 'kept',
        'first_name' => null,
        'region_us' => null,
        'region_ca' => null,
        'notes' => null,
    ])->and(hasSubfieldsHarness()->preProcessValidatable(['first_name' => 'James']))->toBe([
        'first_name' => 'James',
        'region_us' => null,
        'region_ca' => null,
        'notes' => null,
    ]);
});

it('validates nothing when the value is :dataset', function (mixed $value): void {
    expect(hasSubfieldsHarness()->preProcessValidatable($value))->toBe([]);
})->with([
    'null' => [null],
    'an empty string' => [''],
    'a scalar' => ['James'],
    'blank subfields' => [['first_name' => '', 'region' => null]],
]);

it('exposes the preset condition for a subfield', function (): void {
    $harness = hasSubfieldsHarness();

    expect($harness->subfieldPresetIfCondition('region_ca', 'ca'))->toBe(['preset' => 'ca'])
        ->and($harness->subfieldPresetIfCondition('first_name', 'ca'))->toBeNull();
});

it('resolves the subfield handle for a canonical handle and :dataset', function (array $config, string $canonical, ?string $preset, ?string $handle): void {
    expect(hasSubfieldsHarness($config)->getPresetSpecificSubfieldHandle($canonical, $preset))->toBe($handle);
})->with([
    'a matching preset' => [[], 'region', 'ca', 'region_ca'],
    'the configured preset' => [['preset' => 'ca'], 'region', null, 'region_ca'],
    'no preset, so the first match' => [[], 'region', null, 'region_us'],
    'an unmatched preset, so the first match' => [[], 'region', 'mx', 'region_us'],
    'a preset that ends a handle only without its underscore, so the first match' => [[], 'region', 'a', 'region_us'],
    'a plain handle' => [[], 'first_name', 'ca', 'first_name'],
    'an unknown canonical handle' => [[], 'country', 'ca', null],
]);

it('attaches a rule list an overriding fieldtype returns to its subfield path unchanged', function (): void {
    $harness = hasSubfieldsHarness();
    $harness->ruleOverrides = ['region' => ['required', 'string'], 'first_name' => 'nullable'];

    expect($harness->extraRules())->toBe([
        'contact.region' => ['required', 'string'],
        'contact.first_name' => ['nullable'],
    ]);
});

it('builds the preset select from extended preset options, with their display and instructions', function (): void {
    $harness = hasSubfieldsHarness();
    $harness->presets = [
        'display' => 'Address Format',
        'instructions' => 'Choose the address format.',
        'options' => ['us' => 'United States', 'ca' => 'Canada'],
    ];

    expect($harness->presetConfigField())->toBe([
        'preset' => [
            'display' => 'Address Format',
            'type' => 'select',
            'options' => ['us' => 'United States', 'ca' => 'Canada'],
            'default' => 'us',
            'instructions' => 'Choose the address format.',
        ],
    ]);
});

it('falls back to the Preset label and no instructions when :dataset', function (string $shape): void {
    $harness = hasSubfieldsHarness();
    $harness->presets = match ($shape) {
        'plain' => ['ca' => 'Canada', 'us' => 'United States'],
        default => ['display' => '', 'instructions' => '', 'options' => ['ca' => 'Canada', 'us' => 'United States']],
    };

    expect($harness->presetConfigField())->toBe([
        'preset' => [
            'display' => 'Preset',
            'type' => 'select',
            'options' => ['ca' => 'Canada', 'us' => 'United States'],
            'default' => 'ca',
        ],
    ]);
})->with([
    'the options are a plain map' => ['plain'],
    'the extended display and instructions are blank' => ['blank'],
]);

it('builds no preset select when there are no preset options', function (): void {
    $harness = hasSubfieldsHarness();
    $harness->presets = [];

    expect($harness->presetConfigField())->toBeNull();
});

it('offers no presets and no preset condition from the trait defaults', function (): void {
    $fieldtype = new class extends Fieldtype
    {
        use HasSubfields;

        /** @return list<array<string, mixed>> */
        protected function getSubfieldDefinitions(): array
        {
            return [];
        }

        /** @return ?array<string, mixed> */
        public function presetConfigField(): ?array
        {
            return $this->getPresetConfigField();
        }
    };

    expect($fieldtype->presetConfigField())->toBeNull()
        ->and($fieldtype->subfieldPresetIfCondition('region_us', 'us'))->toBeNull();
});

it('makes every subfield nullable when the parent field is not required', function (): void {
    expect(hasSubfieldsHarness(['first_name_required' => true])->extraRules())->toBe([
        'contact.first_name' => ['nullable'],
        'contact.region' => ['nullable'],
        'contact.notes' => ['nullable'],
    ]);
});

it('requires a subfield only when the parent is required and it is both included and required', function (): void {
    expect(hasSubfieldsHarness(['validate' => ['required']])->extraRules())->toBe([
        'contact.first_name' => ['required'],
        'contact.region' => ['nullable'],
        'contact.notes' => ['nullable'],
    ])->and(hasSubfieldsHarness(['validate' => ['required'], 'first_name_include' => false])->extraRules())->toBe([
        'contact.first_name' => ['nullable'],
        'contact.region' => ['nullable'],
        'contact.notes' => ['nullable'],
    ])->and(hasSubfieldsHarness(['validate' => 'required', 'first_name_required' => false, 'notes_include' => true, 'notes_required' => true])->extraRules())->toBe([
        'contact.first_name' => ['nullable'],
        'contact.region' => ['nullable'],
        'contact.notes' => ['required'],
    ]);
});

it('takes the region rule from the subfield the default preset shows, ignoring the hidden one', function (): void {
    expect(hasSubfieldsHarness(['validate' => ['required'], 'region_us_required' => true])->extraRules())
        ->toHaveKey('contact.region', ['required'])
        ->and(hasSubfieldsHarness(['validate' => ['required'], 'region_ca_required' => true])->extraRules())
        ->toHaveKey('contact.region', ['nullable'])
        ->and(hasSubfieldsHarness(['validate' => ['required'], 'preset' => 'ca', 'region_ca_required' => true])->extraRules())
        ->toHaveKey('contact.region', ['required']);
});

it('leaves out a subfield whose condition on another config key is not met', function (): void {
    $harness = hasSubfieldsHarness(['validate' => ['required'], 'notes_include' => true, 'notes_required' => true]);
    $harness->presetConditions['notes'] = ['detail' => 'full'];

    $shown = hasSubfieldsHarness(['validate' => ['required'], 'notes_include' => true, 'notes_required' => true, 'detail' => 'full']);
    $shown->presetConditions['notes'] = ['detail' => 'full'];

    expect($harness->extraRules())->not->toHaveKey('contact.notes')
        ->and($shown->extraRules())->toHaveKey('contact.notes', ['required']);
});

it('adds the value type, options, clearable and width config for a select default with a width', function (): void {
    $harness = hasSubfieldsHarness();
    $harness->definitions[1] = [...$harness->definitions[1], 'default_type' => 'select', 'clearable' => true, 'width' => 50];
    $if = ['region_us_include' => true, 'preset' => 'us'];

    $fields = $harness->subfieldConfigFields(['preset' => 'us']);

    expect($fields)->toHaveKey('region_us_include.if', ['preset' => 'us'])
        ->and($fields['first_name_include'])->toBe([
            'display' => 'Include First Name',
            'type' => 'toggle',
            'default' => true,
            'visibility' => 'visible',
            'if' => null,
        ])
        ->and($fields['region_us_select_value_type'])->toBe([
            'display' => 'State Value Type',
            'type' => 'radio',
            'options' => ['label' => 'Label', 'key' => 'Key'],
            'default' => 'label',
            'instructions' => "Choose whether to store the selected option's key or label.",
            'if' => $if,
            'visibility' => 'visible',
        ])
        ->and($fields['region_us_default'])->toBe([
            'type' => 'select',
            'default' => null,
            'display' => 'Default State',
            'if' => $if,
            'visibility' => 'visible',
            'options' => ['AR' => 'Arkansas', 'TX' => 'Texas'],
            'clearable' => true,
        ])
        ->and($fields['region_us_width'])->toBe([
            'display' => 'State Width',
            'type' => 'width',
            'default' => 100,
            'if' => $if,
            'visibility' => 'visible',
        ]);
});

it('adds no value type, options, clearable or width config where the definition sets none', function (): void {
    $harness = hasSubfieldsHarness();
    $harness->definitions[2] = [...$harness->definitions[2], 'default_type' => 'select', 'default_choices' => null];

    $fields = $harness->subfieldConfigFields();

    expect($fields)->toHaveKey('region_ca_select_value_type')
        ->and($fields['region_ca_default'])->not->toHaveKeys(['options', 'clearable'])
        ->and($fields)->not->toHaveKeys(['region_ca_width', 'region_us_select_value_type', 'first_name_select_value_type', 'first_name_width'])
        ->and($fields)->toHaveKey('region_us_default.type', 'text');
});

it('builds the include, required, label, placeholder and default config for every subfield', function (): void {
    $harness = hasSubfieldsHarness();
    $harness->definitions[] = ['handle' => 'suffix', 'display' => 'Suffix', 'type' => 'text', 'visibility' => 'hidden'];
    $us = ['region_us_include' => true, 'preset' => 'us'];
    $ca = ['region_ca_include' => true, 'preset' => 'ca'];

    expect($harness->subfieldConfigFields())->toBe([
        'first_name_include' => ['display' => 'Include First Name', 'type' => 'toggle', 'default' => true, 'visibility' => 'visible', 'if' => null],
        'first_name_required' => ['display' => 'First Name Required', 'type' => 'toggle', 'default' => true, 'if' => ['first_name_include' => true], 'visibility' => 'visible'],
        'first_name_label' => ['display' => 'First Name Label', 'type' => 'text', 'placeholder' => 'First', 'if' => ['first_name_include' => true], 'visibility' => 'visible'],
        'first_name_placeholder' => ['display' => 'First Name Placeholder', 'type' => 'text', 'placeholder' => 'Enter first name', 'if' => ['first_name_include' => true], 'visibility' => 'visible'],
        'first_name_default' => ['type' => 'text', 'default' => '', 'display' => 'Default First Name', 'if' => ['first_name_include' => true], 'visibility' => 'visible'],
        'region_us_include' => ['display' => 'Include State', 'type' => 'toggle', 'default' => true, 'visibility' => 'visible', 'if' => ['preset' => 'us']],
        'region_us_required' => ['display' => 'State Required', 'type' => 'toggle', 'default' => false, 'if' => $us, 'visibility' => 'visible'],
        'region_us_label' => ['display' => 'State Label', 'type' => 'text', 'placeholder' => 'State', 'if' => $us, 'visibility' => 'visible'],
        'region_us_default' => ['type' => 'text', 'default' => null, 'display' => 'Default State', 'if' => $us, 'visibility' => 'visible'],
        'region_ca_include' => ['display' => 'Include Province', 'type' => 'toggle', 'default' => true, 'visibility' => 'visible', 'if' => ['preset' => 'ca']],
        'region_ca_required' => ['display' => 'Province Required', 'type' => 'toggle', 'default' => false, 'if' => $ca, 'visibility' => 'visible'],
        'region_ca_label' => ['display' => 'Province Label', 'type' => 'text', 'placeholder' => 'Province', 'if' => $ca, 'visibility' => 'visible'],
        'region_ca_default' => ['type' => 'text', 'default' => null, 'display' => 'Default Province', 'if' => $ca, 'visibility' => 'visible'],
        'notes_include' => ['display' => 'Include Notes', 'type' => 'toggle', 'default' => false, 'visibility' => 'visible', 'if' => null],
        'notes_required' => ['display' => 'Notes Required', 'type' => 'toggle', 'default' => false, 'if' => ['notes_include' => true], 'visibility' => 'visible'],
        'notes_label' => ['display' => 'Notes Label', 'type' => 'text', 'placeholder' => 'Notes', 'if' => ['notes_include' => true], 'visibility' => 'visible'],
        'notes_placeholder' => ['display' => 'Notes Placeholder', 'type' => 'text', 'placeholder' => null, 'if' => ['notes_include' => true], 'visibility' => 'visible'],
        'notes_default' => ['type' => 'text', 'default' => null, 'display' => 'Default Notes', 'if' => ['notes_include' => true], 'visibility' => 'visible'],
        'suffix_include' => ['display' => 'Include Suffix', 'type' => 'toggle', 'default' => true, 'visibility' => 'hidden', 'if' => null],
        'suffix_required' => ['display' => 'Suffix Required', 'type' => 'toggle', 'default' => false, 'if' => ['suffix_include' => true], 'visibility' => 'hidden'],
        'suffix_label' => ['display' => 'Suffix Label', 'type' => 'text', 'placeholder' => 'Suffix', 'if' => ['suffix_include' => true], 'visibility' => 'hidden'],
        'suffix_placeholder' => ['display' => 'Suffix Placeholder', 'type' => 'text', 'placeholder' => null, 'if' => ['suffix_include' => true], 'visibility' => 'hidden'],
        'suffix_default' => ['type' => 'text', 'default' => null, 'display' => 'Default Suffix', 'if' => ['suffix_include' => true], 'visibility' => 'hidden'],
    ]);
});

it('asks for each subfield condition with the configured preset, else the first, else none', function (): void {
    $harness = hasSubfieldsHarness();
    $harness->subfieldConfigFields();
    $firstPreset = $harness->presetArguments;

    $harness->subfieldConfigFields(['preset' => 'ca']);
    $configured = $harness->presetArguments;

    $harness->presets = [];
    $harness->subfieldConfigFields(['preset' => 'ca']);

    expect($firstPreset)->toBe(['first_name' => 'us', 'region_us' => 'us', 'region_ca' => 'us', 'notes' => 'us'])
        ->and($configured)->toBe(['first_name' => 'ca', 'region_us' => 'ca', 'region_ca' => 'ca', 'notes' => 'ca'])
        ->and($harness->presetArguments)->toBe(['first_name' => null, 'region_us' => null, 'region_ca' => null, 'notes' => null]);
});

it('builds no preset instructions when the extended options leave them out', function (): void {
    $harness = hasSubfieldsHarness();
    $harness->presets = ['display' => 'Country', 'options' => ['us' => 'United States']];

    expect($harness->presetConfigField())->toBe([
        'preset' => ['display' => 'Country', 'type' => 'select', 'options' => ['us' => 'United States'], 'default' => 'us'],
    ]);
});

it('falls back to the definitions and the first preset when the config leaves them out', function (): void {
    $harness = hasSubfieldsHarness();
    $harness->definitions[0]['inclusion_default'] = false;
    $harness->definitions[1]['required_default'] = true;
    $harness->definitions[] = ['handle' => 'suffix', 'display' => 'Suffix', 'type' => 'text'];

    expect($harness->validationRules([], true))->toBe([
        'first_name' => 'nullable',
        'region' => 'required',
        'notes' => 'nullable',
        'suffix' => 'nullable',
    ])->and($harness->presetArguments)->toBe([
        'first_name' => 'us', 'region_us' => 'us', 'region_ca' => 'us', 'notes' => 'us', 'suffix' => 'us',
    ]);

    $harness->definitions[0]['inclusion_default'] = null;
    $harness->clearAllCaches();

    expect($harness->validationRules([], true))->toHaveKey('first_name', 'required')
        ->and($harness->validationRules(['suffix_required' => true], true))->toHaveKey('suffix', 'required');
});

it('takes the first preset from the extended options for validation and the Vue subfields', function (): void {
    $harness = hasSubfieldsHarness();
    $harness->presets = ['display' => 'Country', 'options' => ['ca' => 'Canada', 'us' => 'United States']];
    $harness->definitions[2]['required_default'] = true;
    $harness->useConfig([]);

    expect($harness->validationRules([], true))->toHaveKey('region', 'required')
        ->and(array_column($harness->getSubfieldsForVue(), 'label', 'vue_handle'))->toHaveKey('region', 'Province');
});

it('hands the Vue component the definition defaults when the config leaves them out', function (): void {
    $harness = hasSubfieldsHarness();
    $harness->definitions[] = ['handle' => 'zip', 'display' => 'ZIP', 'type' => 'zip_code', 'placeholder_default' => '72205', 'default_value' => '00000'];
    $harness->definitions[] = ['handle' => 'suffix', 'display' => 'Suffix', 'type' => 'text', 'required_default' => true];
    $harness->definitions[] = ['handle' => 'plain', 'display' => 'Plain', 'type' => 'text'];
    $harness->useConfig([]);

    $vue = collect($harness->getSubfieldsForVue())->keyBy('vue_handle');

    expect($vue->keys()->all())->toBe(['first_name', 'region', 'zip', 'suffix', 'plain'])
        ->and($vue['region'])->toMatchArray(['label' => 'State'])
        ->and($vue['zip'])->toMatchArray(['placeholder' => '72205', 'default' => '00000', 'required' => false, 'include' => true])
        ->and($vue['suffix'])->toMatchArray(['required' => true, 'default' => null])
        ->and($vue['plain'])->toMatchArray(['required' => false, 'include' => true, 'default' => null]);
});

it('keeps a non-select value and a select value with no choices as given, in either value type', function (string $valueType): void {
    $harness = hasSubfieldsHarness(['first_name_select_value_type' => $valueType, 'region_us_select_value_type' => $valueType]);
    $harness->definitions[0]['default_choices'] = ['Ann' => 'Annie'];
    unset($harness->definitions[1]['default_choices']);

    expect($harness->forStorage(['first_name' => 'Ann', 'region_us' => 'Annie']))->toBe([
        ['key' => 'first_name', 'value' => 'Ann'],
        ['key' => 'region', 'value' => 'Annie'],
    ]);
})->with(['label', 'key']);

it('turns a label into its key only on a strict match', function (): void {
    $harness = hasSubfieldsHarness(['region_us_select_value_type' => 'key']);

    expect($harness->forStorage(['region_us' => true]))->toBe([['key' => 'region', 'value' => true]]);
});

it('treats only a literal required rule as making the parent required', function (): void {
    expect(hasSubfieldsHarness(['validate' => [true]])->extraRules())->toHaveKey('contact.first_name', ['nullable']);
});
