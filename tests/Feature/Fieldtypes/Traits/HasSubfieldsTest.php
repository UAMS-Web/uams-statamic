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
