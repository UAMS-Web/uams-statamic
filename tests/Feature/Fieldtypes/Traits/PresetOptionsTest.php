<?php

declare(strict_types=1);

use App\Enums\Country;
use App\Enums\Currency;
use App\Enums\Language;
use App\Enums\LanguageBCP47;
use App\Enums\MimeType;
use App\Enums\State;
use App\Fieldtypes\Traits\PresetOptions;
use Statamic\Fields\Field;
use Tests\Support\Doubles\PresetOptionsHarness;
use Tests\TestCase;

covers(PresetOptions::class);

uses(TestCase::class);

/**
 * Behavior of the PresetOptions trait (app/Fieldtypes/Traits/PresetOptions.php):
 * a selected preset replaces a choice fieldtype's manual options with a
 * predefined list (static, or built from an enum filtered by the preset's own
 * config fields), and the trait hides the parent's config fields a preset
 * makes irrelevant.
 *
 * @command  php artisan test --compact tests/Feature/Fieldtypes/Traits/PresetOptionsTest.php
 */

/**
 * A harness bound to a select field with one manual option, x => X, plus the
 * given config.
 *
 * @param  array<string, mixed>  $config
 */
function presetOptionsHarness(array $config = []): PresetOptionsHarness
{
    $fieldtype = new PresetOptionsHarness;
    $fieldtype->setField(new Field('choice', array_merge(['type' => 'select', 'options' => ['x' => 'X']], $config)));

    return $fieldtype;
}

/**
 * Options in Statamic's value/label shape, from a key => label map.
 *
 * @param  array<array-key, string>  $options
 * @return list<array{value: array-key, label: string}>
 */
function presetOptionsAsStatamic(array $options): array
{
    $converted = [];

    foreach ($options as $value => $label) {
        $converted[] = ['value' => $value, 'label' => $label];
    }

    return $converted;
}

/** The manual options the harness falls back to. */
const PRESET_OPTIONS_MANUAL = [['value' => 'x', 'label' => 'X']];

/**
 * The State cases of the given types, in enum order.
 *
 * @param  list<string>  $types
 * @return array<string, string>
 */
function presetOptionsStates(array $types): array
{
    $states = [];

    foreach (State::cases() as $state) {
        if (in_array($state->type(), $types, true)) {
            $states[$state->value] = $state->fullName();
        }
    }

    return $states;
}

/**
 * The MimeType cases in the given categories, labeled as the preset labels them.
 *
 * @param  list<string>  $categories
 * @return array<string, string>
 */
function presetOptionsMimeTypes(array $categories): array
{
    $types = [];

    foreach (MimeType::cases() as $mimeType) {
        if (! in_array($mimeType->category(), $categories, true)) {
            continue;
        }

        $extensions = $mimeType->extensions();
        $types[$mimeType->mimeType()] = $mimeType->mimeType().($extensions === [] ? '' : ' ('.implode(', ', $extensions).')');
    }

    return $types;
}

it('keeps the manual options when no usable preset is selected', function (array $config): void {
    /** @var array<string, mixed> $typedConfig */
    $typedConfig = $config;

    expect(presetOptionsHarness($typedConfig)->options())->toBe(PRESET_OPTIONS_MANUAL);
})->with([
    'no preset' => [[]],
    'null' => [['preset' => null]],
    'empty string' => [['preset' => '']],
    'none' => [['preset' => 'none']],
    'unknown preset' => [['preset' => 'planets']],
    'non-scalar preset' => [['preset' => ['months']]],
    'a preset whose filter leaves nothing' => [['preset' => 'us_states', 'state_types' => []]],
]);

it('replaces the manual options with a static preset', function (string $preset, array $expected): void {
    /** @var array<array-key, string> $typedExpected */
    $typedExpected = $expected;

    expect(presetOptionsHarness(['preset' => $preset])->options())->toBe(presetOptionsAsStatamic($typedExpected));
})->with([
    'canadian provinces' => ['canadian_provinces', [
        'AB' => 'Alberta',
        'BC' => 'British Columbia',
        'MB' => 'Manitoba',
        'NB' => 'New Brunswick',
        'NL' => 'Newfoundland and Labrador',
        'NS' => 'Nova Scotia',
        'NT' => 'Northwest Territories',
        'NU' => 'Nunavut',
        'ON' => 'Ontario',
        'PE' => 'Prince Edward Island',
        'QC' => 'Quebec',
        'SK' => 'Saskatchewan',
        'YT' => 'Yukon',
    ]],
    'months' => ['months', [
        '01' => 'January',
        '02' => 'February',
        '03' => 'March',
        '04' => 'April',
        '05' => 'May',
        '06' => 'June',
        '07' => 'July',
        '08' => 'August',
        '09' => 'September',
        '10' => 'October',
        '11' => 'November',
        '12' => 'December',
    ]],
    'days of the week' => ['days_of_week', [
        '1' => 'Monday',
        '2' => 'Tuesday',
        '3' => 'Wednesday',
        '4' => 'Thursday',
        '5' => 'Friday',
        '6' => 'Saturday',
        '7' => 'Sunday',
    ]],
    'yes or no' => ['yes_no', ['yes' => 'Yes', 'no' => 'No']],
    'true or false' => ['true_false', ['true' => 'True', 'false' => 'False']],
]);

it('lists every country and currency from its enum', function (): void {
    $countries = [];
    foreach (Country::cases() as $country) {
        $countries[$country->value] = $country->country_name();
    }

    $currencies = [];
    foreach (Currency::cases() as $currency) {
        $currencies[$currency->value] = $currency->name();
    }

    expect(presetOptionsHarness(['preset' => 'countries'])->options())->toBe(presetOptionsAsStatamic($countries))
        ->and(presetOptionsHarness(['preset' => 'currencies'])->options())->toBe(presetOptionsAsStatamic($currencies));
});

it('filters the U.S. states by the configured state types', function (array $config, array $types): void {
    /** @var array<string, mixed> $typedConfig */
    $typedConfig = $config;
    /** @var list<string> $typedTypes */
    $typedTypes = $types;

    expect(presetOptionsHarness(['preset' => 'us_states', ...$typedConfig])->options())
        ->toBe(presetOptionsAsStatamic(presetOptionsStates($typedTypes)));
})->with([
    'states by default' => [[], ['state']],
    'a single type as a string' => [['state_types' => 'territory'], ['territory']],
    'several types' => [['state_types' => ['district', 'state']], ['state', 'district']],
]);

it('labels the states by full name in enum order', function (): void {
    $options = presetOptionsHarness(['preset' => 'us_states', 'state_types' => ['district']])->options();

    expect($options)->toBe([['value' => 'DC', 'label' => 'District of Columbia']]);
});

it('filters the languages by the configured scope and standard', function (array $config, string $standard, array $scopes): void {
    /** @var array<string, mixed> $typedConfig */
    $typedConfig = $config;
    /** @var list<?string> $typedScopes */
    $typedScopes = $scopes;

    $expected = [];

    if ($standard === 'bcp47') {
        foreach (LanguageBCP47::cases() as $language) {
            if (in_array($language->scope(), $typedScopes, true)) {
                $expected[$language->value] = $language->description();
            }
        }
    } else {
        foreach (Language::cases() as $language) {
            if (in_array($language->scope(), $typedScopes, true)) {
                $expected[$language->value] = $language->name();
            }
        }
    }

    expect($expected)->not->toBe([])
        ->and(presetOptionsHarness(['preset' => 'languages', ...$typedConfig])->options())
        ->toBe(presetOptionsAsStatamic($expected));
})->with([
    'individual ISO 639 by default' => [[], 'iso639', ['Individual']],
    'a single scope as a string' => [['language_scope' => 'collective'], 'iso639', ['Collective']],
    'several ISO 639 scopes' => [['language_scope' => ['macrolanguages', 'collective']], 'iso639', ['Collective', 'Macrolanguage']],
    'individual BCP 47' => [['language_standard' => 'bcp47'], 'bcp47', [null]],
    'collective BCP 47' => [['language_standard' => 'bcp47', 'language_scope' => ['collective']], 'bcp47', ['collection']],
    'macrolanguage BCP 47' => [['language_standard' => 'bcp47', 'language_scope' => ['macrolanguages']], 'bcp47', ['macrolanguage']],
    'every BCP 47 scope maps to null' => [['language_standard' => 'bcp47', 'language_scope' => ['all']], 'bcp47', [null]],
]);

it('keeps the manual options when no ISO 639 language has a null scope', function (): void {
    expect(presetOptionsHarness(['preset' => 'languages', 'language_scope' => ['all']])->options())
        ->toBe(PRESET_OPTIONS_MANUAL);
});

it('maps each user-facing language scope to the enum scope', function (?string $scope, string $enumClass, ?string $expected): void {
    expect(presetOptionsHarness()->mapScope($scope, $enumClass))->toBe($expected);
})->with([
    'null' => [null, 'Language', null],
    'all' => ['all', 'Language', null],
    'ISO 639 individual' => ['individual', 'Language', 'Individual'],
    'ISO 639 collective' => ['collective', 'Language', 'Collective'],
    'ISO 639 macrolanguages' => ['macrolanguages', 'Language', 'Macrolanguage'],
    'ISO 639 unknown' => ['special', 'Language', null],
    'BCP 47 individual' => ['individual', 'LanguageBCP47', null],
    'BCP 47 collective' => ['collective', 'LanguageBCP47', 'collection'],
    'BCP 47 macrolanguages' => ['macrolanguages', 'LanguageBCP47', 'macrolanguage'],
    'BCP 47 unknown' => ['special', 'LanguageBCP47', null],
    'an unknown enum' => ['collective', 'Dialect', null],
]);

it('filters the MIME types by the configured categories', function (array $config, array $categories): void {
    /** @var array<string, mixed> $typedConfig */
    $typedConfig = $config;
    /** @var list<string> $typedCategories */
    $typedCategories = $categories;

    expect(presetOptionsHarness(['preset' => 'mime_types', ...$typedConfig])->options())
        ->toBe(presetOptionsAsStatamic(presetOptionsMimeTypes($typedCategories)));
})->with([
    'the eight standard categories by default' => [[], ['application', 'audio', 'font', 'image', 'message', 'model', 'text', 'video']],
    'a single category as a string' => [['mime_type_categories' => 'x-shader'], ['x-shader']],
    'several categories' => [['mime_type_categories' => ['haptics', 'chemical']], ['chemical', 'haptics']],
]);

it('labels a MIME type with its extensions, or alone when it has none', function (): void {
    $labels = array_column(presetOptionsHarness(['preset' => 'mime_types'])->options(), 'label', 'value');

    expect($labels['image/png'])->toBe('image/png (png)')
        ->and($labels['audio/ac3'])->toBe('audio/ac3');
});

it('lists the MIME categories capitalized after each hyphen', function (): void {
    $expected = [];
    foreach (MimeType::getCategories() as $category) {
        $expected[$category] = ucwords($category, '-');
    }

    expect(presetOptionsHarness(['preset' => 'mime_categories'])->options())->toBe(presetOptionsAsStatamic($expected))
        ->and($expected['x-conference'])->toBe('X-Conference');
});

it('builds no dynamic options for a preset without a generator', function (): void {
    expect(presetOptionsHarness()->dynamicOptions('months'))->toBe([]);
});

it('reads a preset definition as static unless it says it has config', function (): void {
    $fieldtype = presetOptionsHarness(['preset' => 'shapes']);
    $fieldtype->definitions = ['shapes' => ['has_config' => false, 'options' => ['sq' => 'Square']]];

    expect($fieldtype->options())->toBe([['value' => 'sq', 'label' => 'Square']]);

    $fieldtype->definitions = ['shapes' => ['has_config' => true, 'options' => ['sq' => 'Square']]];

    expect($fieldtype->options())->toBe(PRESET_OPTIONS_MANUAL);

    $fieldtype->definitions = ['shapes' => ['display' => 'Shapes']];

    expect($fieldtype->options())->toBe(PRESET_OPTIONS_MANUAL);
});

it('builds the preset select from the definitions, then the preset config fields', function (): void {
    $fieldtype = presetOptionsHarness();
    $fieldtype->definitions = ['shapes' => ['display' => 'Shapes'], 'blank' => []];

    $fields = $fieldtype->presetConfigField();

    expect(array_keys($fields))->toBe(['preset', 'language_scope', 'language_standard', 'state_types', 'mime_type_categories'])
        ->and($fields['preset'])->toBe([
            'display' => __('Preset Options'),
            'instructions' => __('Select a predefined list of options to use instead of manually entering options.'),
            'type' => 'select',
            'options' => [
                'none' => __('No Preset (Use Manual Options)'),
                'shapes' => 'Shapes',
                'blank' => null,
            ],
            'default' => 'none',
            'validate' => ['required'],
        ]);
});

it('hides the parent fields a preset makes irrelevant', function (): void {
    $fieldtype = presetOptionsHarness();
    $fieldtype->definitions = [
        'shapes' => ['config' => ['cast_booleans' => false, 'multiple' => false]],
        'colors' => ['config' => ['cast_booleans' => true]],
        'sizes' => ['config' => 'not an array'],
        'weights' => [],
    ];
    $fieldtype->parentItems = [
        'heading' => 'not a section',
        'empty' => ['display' => 'Empty'],
        'scalar fields' => ['fields' => 'not an array'],
        'choices' => ['fields' => [
            'note' => 'not a field',
            'options' => ['type' => 'array'],
            'push_tags' => ['type' => 'toggle'],
            'cast_booleans' => ['type' => 'toggle'],
            'multiple' => ['type' => 'toggle'],
            'placeholder' => ['type' => 'text'],
        ]],
    ];

    expect($fieldtype->parentConfig())->toBe([
        'heading' => 'not a section',
        'empty' => ['display' => 'Empty'],
        'scalar fields' => ['fields' => 'not an array'],
        'choices' => ['fields' => [
            'note' => 'not a field',
            'options' => ['type' => 'array', 'if' => ['preset' => 'custom isEmptyOrValue:none']],
            'push_tags' => ['type' => 'toggle', 'if' => ['__always_false__' => true]],
            'cast_booleans' => ['type' => 'toggle', 'unless' => ['preset' => 'contains_any shapes,colors']],
            'multiple' => ['type' => 'toggle', 'unless' => ['preset' => 'contains_any shapes']],
            'placeholder' => ['type' => 'text'],
        ]],
    ]);
});
