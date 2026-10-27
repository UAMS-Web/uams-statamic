<?php

declare(strict_types=1);

use App\Fieldtypes\Traits\BuildsPreloadConfig;
use Statamic\Fields\Field;
use Tests\Support\Doubles\BuildsPreloadConfigHarness;
use Tests\Support\Doubles\BuildsPreloadConfigPresetHarness;
use Tests\TestCase;

covers(BuildsPreloadConfig::class);

uses(TestCase::class);

/**
 * Behavior of the BuildsPreloadConfig trait (app/Fieldtypes/Traits/BuildsPreloadConfig.php):
 * gathering config defaults from overrides, additional config fields and the
 * default preset; resolving the values the Vue component reads (preset
 * config, 'null' strings, autocomplete, autocapitalize, contenteditable,
 * dirname, the input mask and calculation formula keys, focus); stripping
 * internal keys; and the preload() payload.
 *
 * @command  php artisan test --compact tests/Feature/Fieldtypes/Traits/BuildsPreloadConfigTest.php
 */

/**
 * A harness attached to a field with the given handle and config.
 *
 * @param  array<string, mixed>  $config
 */
function preloadHarness(array $config = [], bool $presets = false, string $handle = 'probe'): BuildsPreloadConfigHarness
{
    $harness = $presets ? new BuildsPreloadConfigPresetHarness : new BuildsPreloadConfigHarness;
    $harness->setField(new Field($handle, ['type' => 'preload_probe', ...$config]));

    return $harness;
}

/**
 * Processes $config through the harness and returns the result without the
 * keys every run adds, so each case compares only what it set.
 *
 * @param  array<array-key, mixed>  $config  A dataset row's config, string-keyed in practice
 * @return array<string, mixed>
 */
function processedPreload(array $config, ?BuildsPreloadConfigHarness $harness = null): array
{
    /** @var array<string, mixed> $typedConfig */
    $typedConfig = $config;

    $result = ($harness ?? preloadHarness())->processPreloadConfig($typedConfig);

    unset($result['handle'], $result['contenteditable']);

    return $result;
}

it('gathers defaults from overrides and additional config fields, and lets the config win', function (): void {
    $harness = preloadHarness();
    $harness->overrides = [
        'placeholder' => ['default' => 'Type here'],
        'width' => ['default' => 50],
        'no_default' => ['display' => 'x'],
        'not_an_array' => 'default',
    ];
    $harness->additional = [
        'mask' => ['default' => false],
        'width' => ['default' => 100],
        'skipped' => ['type' => 'text'],
        'scalar' => 'default',
    ];

    expect($harness->normalizeConfigWithDefaults(['placeholder' => 'Mine']))->toBe([
        'placeholder' => 'Mine',
        'width' => 100,
        'mask' => false,
    ]);
});

it('treats a null default as no default', function (): void {
    $harness = preloadHarness();
    $harness->overrides = ['a' => ['default' => null]];
    $harness->additional = ['b' => ['default' => null]];

    expect($harness->normalizeConfigWithDefaults([]))->toBe([]);
});

it('merges the default preset\'s config and names it, under the given config', function (): void {
    $harness = preloadHarness(presets: true);
    $harness->overrides = ['width' => ['default' => 25]];
    $harness->presets = [
        'default' => 'phone',
        'options' => ['phone' => ['config' => ['input_type' => 'tel', 'width' => 50]]],
    ];

    expect($harness->normalizeConfigWithDefaults(['input_type' => 'text']))->toBe([
        'width' => 50,
        'input_type' => 'text',
        'preset' => 'phone',
    ]);
});

it('accepts an integer default preset key', function (): void {
    $harness = preloadHarness(presets: true);
    $harness->presets = ['default' => 1, 'options' => [1 => ['config' => ['a' => 'b']]]];

    expect($harness->normalizeConfigWithDefaults([]))->toBe(['a' => 'b', 'preset' => 1]);
});

it('ignores a default preset that is missing, unusable or has no config', function (array $presets): void {
    $harness = preloadHarness(presets: true);
    $harness->presets = $presets;

    expect($harness->normalizeConfigWithDefaults(['x' => 1]))->toBe(['x' => 1]);
})->with([
    'no default' => [['options' => ['phone' => ['config' => ['a' => 'b']]]]],
    'empty default' => [['default' => '', 'options' => ['' => ['config' => ['a' => 'b']]]]],
    'non-scalar default' => [['default' => ['phone'], 'options' => ['phone' => ['config' => ['a' => 'b']]]]],
    'options not an array' => [['default' => 'phone', 'options' => 'phone']],
    'no options' => [['default' => 'phone']],
    'unknown preset' => [['default' => 'fax', 'options' => ['phone' => ['config' => ['a' => 'b']]]]],
    'preset not an array' => [['default' => 'phone', 'options' => ['phone' => 'config']]],
    'preset without config' => [['default' => 'phone', 'options' => ['phone' => ['label' => 'Phone']]]],
    'config not an array' => [['default' => 'phone', 'options' => ['phone' => ['config' => 'a']]]],
]);

it('does not read presets on a fieldtype that has none', function (): void {
    $harness = preloadHarness();
    $harness->presets = ['default' => 'phone', 'options' => ['phone' => ['config' => ['a' => 'b']]]];

    expect($harness->normalizeConfigWithDefaults([]))->toBe([]);
});

it('merges the selected preset\'s config over the given config', function (): void {
    $harness = preloadHarness(presets: true);
    $harness->presets = ['options' => [
        'phone' => ['config' => ['input_type' => 'tel']],
        2 => ['config' => ['input_type' => 'number']],
    ]];

    expect(processedPreload(['preset' => 'phone', 'input_type' => 'text'], $harness))->toBe(['preset' => 'phone', 'input_type' => 'tel'])
        ->and(processedPreload(['preset' => 2, 'input_type' => 'text'], $harness))->toBe(['preset' => 2, 'input_type' => 'number']);
});

it('leaves the config alone when the selected preset is unusable', function (array $presets, mixed $preset): void {
    $harness = preloadHarness(presets: true);
    $harness->presets = $presets;

    expect(processedPreload(['preset' => $preset, 'input_type' => 'text'], $harness))->toBe(['preset' => $preset, 'input_type' => 'text']);
})->with([
    'options not an array' => [['options' => 'phone'], 'phone'],
    'no options' => [[], 'phone'],
    'non-scalar preset' => [['options' => ['phone' => ['config' => ['input_type' => 'tel']]]], ['phone']],
    'unknown preset' => [['options' => ['phone' => ['config' => ['input_type' => 'tel']]]], 'fax'],
    'preset not an array' => [['options' => ['phone' => 'tel']], 'phone'],
    'preset without config' => [['options' => ['phone' => ['label' => 'Phone']]], 'phone'],
    'config not an array' => [['options' => ['phone' => ['config' => 'tel']]], 'phone'],
]);

it('does not apply a selected preset on a fieldtype that has no presets', function (): void {
    $harness = preloadHarness();
    $harness->presets = ['options' => ['phone' => ['config' => ['input_type' => 'tel']]]];

    expect(processedPreload(['preset' => 'phone', 'input_type' => 'text'], $harness))->toBe(['preset' => 'phone', 'input_type' => 'text']);
});

it('turns the string null into null and leaves other values alone', function (): void {
    expect(processedPreload(['a' => 'null', 'b' => null, 'c' => 'Null', 'd' => 0]))->toBe(['a' => null, 'b' => null, 'c' => 'Null', 'd' => 0]);
});

it('copies an enabled autocomplete query into an empty autocomplete', function (array $config, array $expected): void {
    expect(processedPreload($config))->toBe($expected);
})->with([
    'empty' => [['autocomplete_query' => 'on', 'autocomplete' => ''], ['autocomplete_query' => 'on', 'autocomplete' => 'on']],
    'absent' => [['autocomplete_query' => 'on'], ['autocomplete_query' => 'on', 'autocomplete' => 'on']],
    'already set' => [['autocomplete_query' => 'on', 'autocomplete' => 'email'], ['autocomplete_query' => 'on', 'autocomplete' => 'email']],
    'query off' => [['autocomplete_query' => 'off', 'autocomplete' => ''], ['autocomplete_query' => 'off', 'autocomplete' => '']],
    'no query' => [['autocomplete' => ''], ['autocomplete' => '']],
]);

it('replaces an enabled autocapitalize with its setting', function (array $config, array $expected): void {
    expect(processedPreload($config))->toBe($expected);
})->with([
    'with setting' => [['autocapitalize' => 'on', 'autocapitalize_setting' => 'words'], ['autocapitalize' => 'words', 'autocapitalize_setting' => 'words']],
    'empty setting' => [['autocapitalize' => 'on', 'autocapitalize_setting' => ''], ['autocapitalize' => 'on', 'autocapitalize_setting' => '']],
    'no setting' => [['autocapitalize' => 'on'], ['autocapitalize' => 'on']],
    'off' => [['autocapitalize' => 'off', 'autocapitalize_setting' => 'words'], ['autocapitalize' => 'off', 'autocapitalize_setting' => 'words']],
]);

it('marks only a visible, non-hidden typed field as editable', function (array $config, string $editable): void {
    /** @var array<string, mixed> $typedConfig */
    $typedConfig = $config;

    expect(preloadHarness()->processPreloadConfig($typedConfig)['contenteditable'])->toBe($editable);
})->with([
    'text, no visibility' => [['type' => 'text'], 'true'],
    'text, visible' => [['type' => 'text', 'visibility' => 'visible'], 'true'],
    'text, hidden visibility' => [['type' => 'text', 'visibility' => 'hidden'], 'false'],
    'hidden type' => [['type' => 'hidden'], 'false'],
    'no type' => [[], 'false'],
]);

it('sets dirname from the name when dir and name are both set', function (array $config, ?string $dirname): void {
    expect(processedPreload($config)['dirname'] ?? null)->toBe($dirname);
})->with([
    'both' => [['dir' => true, 'name' => 'comment'], 'comment.dir'],
    'numeric name' => [['dir' => 'auto', 'name' => 7], '7.dir'],
    'non-scalar name' => [['dir' => true, 'name' => ['comment']], '.dir'],
    'dir false' => [['dir' => false, 'name' => 'comment'], null],
    'no dir' => [['name' => 'comment'], null],
    'empty name' => [['dir' => true, 'name' => ''], null],
    'no name' => [['dir' => true], null],
]);

it('moves the mask for the chosen mask class into input_mask_mask and forces a text input', function (string $class): void {
    $config = [
        'input_mask' => true,
        'input_type' => 'tel',
        'input_mask_class' => $class,
        'input_mask_mask_MaskedDynamic' => 'dynamic',
        'input_mask_mask_MaskedFunction' => 'function',
        'input_mask_mask_MaskedPattern' => 'pattern',
        'input_mask_mask_MaskedRegExp' => 'regexp',
    ];
    $kept = array_diff_key($config, ["input_mask_mask_{$class}" => true]);

    expect(processedPreload($config))->toBe([...$kept, 'input_type' => 'text', 'input_mask_mask' => $config["input_mask_mask_{$class}"]]);
})->with(['MaskedDynamic', 'MaskedFunction', 'MaskedPattern', 'MaskedRegExp']);

it('keeps an empty or missing mask for the chosen class out of input_mask_mask', function (string $class): void {
    $empty = ['input_mask' => true, 'input_mask_class' => $class, "input_mask_mask_{$class}" => ''];
    $missing = ['input_mask' => true, 'input_mask_class' => $class];

    expect(processedPreload($empty))->toBe([...$empty, 'input_type' => 'text'])
        ->and(processedPreload($missing))->toBe([...$missing, 'input_type' => 'text']);
})->with(['MaskedDynamic', 'MaskedFunction', 'MaskedPattern', 'MaskedRegExp']);

it('leaves the mask keys alone without a mask class or with the mask off', function (array $config): void {
    expect(processedPreload($config))->toBe($config);
})->with([
    'no class' => [['input_mask' => true, 'input_type' => 'text', 'input_mask_mask_MaskedPattern' => '000']],
    'mask off' => [['input_mask' => false, 'input_type' => 'tel', 'input_mask_class' => 'MaskedPattern', 'input_mask_mask_MaskedPattern' => '000']],
    'mask truthy but not true' => [['input_mask' => 1, 'input_type' => 'tel', 'input_mask_class' => 'MaskedPattern', 'input_mask_mask_MaskedPattern' => '000']],
]);

it('moves the formula for the calculation type into calculation_formula', function (array $config, array $expected): void {
    expect(processedPreload($config))->toBe($expected);
})->with([
    'math' => [
        ['calculation_query' => true, 'calculation_type' => 'math', 'calculation_formula_math' => '{a}+1', 'calculation_formula_text' => '{a}'],
        ['calculation_query' => true, 'calculation_type' => 'math', 'calculation_formula_text' => '{a}', 'calculation_formula' => '{a}+1'],
    ],
    'text' => [
        ['calculation_query' => true, 'calculation_type' => 'text', 'calculation_formula_math' => '{a}+1', 'calculation_formula_text' => '{a}'],
        ['calculation_query' => true, 'calculation_type' => 'text', 'calculation_formula_math' => '{a}+1', 'calculation_formula' => '{a}'],
    ],
    'math, empty formula' => [
        ['calculation_query' => true, 'calculation_type' => 'math', 'calculation_formula_math' => '', 'calculation_formula_text' => '{a}'],
        ['calculation_query' => true, 'calculation_type' => 'math', 'calculation_formula_math' => '', 'calculation_formula_text' => '{a}'],
    ],
    'math, no formula' => [
        ['calculation_query' => true, 'calculation_type' => 'math'],
        ['calculation_query' => true, 'calculation_type' => 'math'],
    ],
    'text, empty formula' => [
        ['calculation_query' => true, 'calculation_type' => 'text', 'calculation_formula_text' => ''],
        ['calculation_query' => true, 'calculation_type' => 'text', 'calculation_formula_text' => ''],
    ],
    'text, no formula' => [
        ['calculation_query' => true, 'calculation_type' => 'text'],
        ['calculation_query' => true, 'calculation_type' => 'text'],
    ],
    'no type' => [
        ['calculation_query' => true, 'calculation_formula_math' => '{a}+1'],
        ['calculation_query' => true, 'calculation_formula_math' => '{a}+1'],
    ],
    'query off' => [
        ['calculation_query' => false, 'calculation_type' => 'math', 'calculation_formula_math' => '{a}+1'],
        ['calculation_query' => false, 'calculation_type' => 'math', 'calculation_formula_math' => '{a}+1'],
    ],
    'query truthy but not true' => [
        ['calculation_query' => 'true', 'calculation_type' => 'math', 'calculation_formula_math' => '{a}+1'],
        ['calculation_query' => 'true', 'calculation_type' => 'math', 'calculation_formula_math' => '{a}+1'],
    ],
]);

it('turns a focus string into the value the Vue component reads', function (mixed $focus, mixed $expected): void {
    expect(processedPreload(['focus' => $focus])['focus'])->toBe($expected);
})->with([
    'true' => ['true', true],
    'false' => ['false', false],
    'other string' => ['yes', 'yes'],
    'boolean' => [true, true],
]);

it('turns a focus of the string null into null before the focus mapping', function (): void {
    expect(processedPreload(['focus' => 'null']))->toBe(['focus' => null]);
});

it('strips keys a sectioned config field marks internal, after the fieldtype modifies the config', function (): void {
    $harness = preloadHarness(presets: true);
    $harness->fieldItems = [
        ['display' => 'Main', 'fields' => [
            'secret' => ['type' => 'text', 'internal' => true],
            'visible' => ['type' => 'text', 'internal' => false],
            'scalar' => 'text',
        ]],
        ['display' => 'Empty', 'fields' => []],
        ['display' => 'Broken', 'fields' => 'secret_two'],
        ['display' => 'More', 'fields' => ['added' => ['internal' => 1]]],
    ];
    $harness->preloadModifications = ['added' => 'by modify'];

    expect(processedPreload(['secret' => 's', 'visible' => 'v', 'scalar' => 'c', 'secret_two' => 't'], $harness))
        ->toBe(['visible' => 'v', 'scalar' => 'c', 'secret_two' => 't']);
});

it('sets the handle from the field and reads the cached config when none is given', function (): void {
    $harness = preloadHarness(['placeholder' => 'From the field'], handle: 'contact_email');

    $result = $harness->processPreloadConfig();

    expect($result['handle'])->toBe('contact_email')
        ->and($result['placeholder'])->toBe('From the field')
        ->and($result['type'])->toBe('preload_probe')
        ->and($result['contenteditable'])->toBe('true');
});

it('preloads the processed config and the fieldtype handle', function (): void {
    $harness = preloadHarness(['placeholder' => 'Hi'], handle: 'greeting');

    expect($harness->preload())->toBe([
        'config' => $harness->processPreloadConfig(),
        'fieldtype_handle' => 'builds_preload_config_harness',
    ]);
});
