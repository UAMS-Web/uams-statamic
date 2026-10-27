<?php

declare(strict_types=1);

use App\Fieldtypes\MaskField;
use Statamic\Fields\Field;
use Tests\TestCase;

/**
 * @covers \App\Fieldtypes\MaskField
 *
 * @command  php artisan test --compact tests/Unit/Fieldtypes/MaskFieldFieldtypeTest.php
 */
uses(TestCase::class);

it('has an empty-array default value', function (): void {
    expect((new MaskField)->defaultValue())->toBe([]);
});

it('passes values through process and preProcess unchanged when no preset is defined', function (mixed $input): void {
    // The base fieldtype (no preset mask/case) is a transparent pass-through; the
    // masking happens client-side, so the stored value is exactly what came in.
    $mask = new MaskField;

    expect($mask->process($input))->toBe($input)
        ->and($mask->preProcess($input))->toBe($input);
})->with([
    'string' => ['abc-123'],
    'empty string' => [''],
    'null' => [null],
    'array' => [['x' => 'y']],
]);

it('merges a subclass preset mask and transform into its config on process', function (): void {
    // A subclass that locks the mask/case (the intended extension point) has those
    // presets folded into the fieldtype config by applyPresetConfig() during
    // process()/preProcess(), so the child never has to override them.
    $mask = new class extends MaskField
    {
        protected ?string $presetInputMask = '000-000-0000';

        protected ?string $presetTransformCase = 'uppercase';
    };

    $mask->process(null);

    // applyPresetConfig() writes to the fieldtype's own protected $config array.
    $config = (function (): array {
        return $this->config;
    })->call($mask);

    expect($config['input_mask'])->toBe('000-000-0000')
        ->and($config['transform_case'])->toBe('uppercase')
        ->and($config['_preset_fields'])->toBe([
            'input_mask' => '000-000-0000',
            'transform_case' => 'uppercase',
        ]);
});

it('leaves config untouched when no preset properties are declared', function (): void {
    $mask = new MaskField;
    $mask->process('anything');

    $config = (function (): array {
        /** @var MaskField $this */
        return $this->config;
    })->call($mask);

    // No preset → applyPresetConfig() is a no-op → the config array stays empty.
    expect($config)->toBe([]);
});

/**
 * Invoke a protected MaskField method via reflection.
 *
 * @param  array<int, mixed>  $args  Arguments forwarded to the method
 */
function maskFieldCall(MaskField $fieldtype, string $method, array $args = []): mixed
{
    // Reflect on the base class so the subclass fixtures reach the same method
    return (new ReflectionMethod(MaskField::class, $method))->invoke($fieldtype, ...$args);
}

/**
 * A MaskField whose config sections are fixed, for the section-walking helpers.
 *
 * @param  list<array<string, mixed>>  $sections  Config sections to return
 */
function maskFieldWithSections(array $sections): MaskField
{
    // Override configFieldItems() so the helpers walk these sections instead
    return new class($sections) extends MaskField
    {
        /**
         * @param  list<array<string, mixed>>  $sections
         */
        public function __construct(private array $sections) {}

        protected function configFieldItems(): array
        {
            return $this->sections;
        }
    };
}

/**
 * Read a MaskField's own protected config array.
 *
 * @return array<string, mixed>
 */
function maskFieldOwnConfig(MaskField $fieldtype): array
{
    /** @var array<string, mixed> $config */
    $config = (fn (): array => $this->config)->call($fieldtype);

    return $config;
}

it('identifies itself in the Control Panel', function (): void {
    $mask = new MaskField;

    expect($mask->component())->toBe('mask_field')
        ->and($mask->icon())->toBe('form-text-field')
        ->and($mask->selectableInForms())->toBeFalse()
        ->and($mask->categories())->toBe(['number', 'text', 'special'])
        ->and($mask->keywords())->toBe([
            'all caps',
            'all-caps',
            'capitalize',
            'case',
            'character',
            'characters',
            'format',
            'formatted',
            'formatting',
            'imask',
            'lowercase',
            'mask',
            'masking',
            'pattern',
            'patterns',
            'regex',
            'regexp',
            'regular expression',
            'regular expressions',
            'transform',
            'uppercase',
        ]);
});

it('configures a placeholder, an input mask and a transform case', function (): void {
    /** @var array<int, array<string, mixed>> $items */
    $items = maskFieldCall(new MaskField, 'configFieldItems');

    /** @var string $maskInstructions */
    $maskInstructions = data_get($items, '0.fields.input_mask.instructions');

    expect($maskInstructions)->toStartWith('Define an input mask (e.g., `000-000-0000`')
        ->toEndWith('See IMask documentation at https://imask.js.org/guide.html.')
        ->and($items)->toBe([
            [
                'display' => 'Input Behavior',
                'fields' => [
                    'placeholder' => [
                        'display' => 'Placeholder',
                        'instructions' => 'Set placeholder text.',
                        'type' => 'text',
                    ],
                    'input_mask' => [
                        'display' => 'Input Mask',
                        'type' => 'text',
                        'placeholder' => 'e.g., 000-000-0000',
                        'instructions' => $maskInstructions,
                    ],
                    'transform_case' => [
                        'display' => 'Transform Case',
                        'type' => 'radio',
                        'options' => [
                            'none' => 'None',
                            'uppercase' => 'Uppercase',
                            'lowercase' => 'Lowercase',
                        ],
                        'default' => 'none',
                        'clearable' => true,
                        'instructions' => 'Choose whether to transform input characters to uppercase, lowercase, or leave them unchanged. If not set, no transformation occurs.',
                    ],
                ],
            ],
        ]);
});

it('defaults the config fields to a subclass\'s non-null presets', function (MaskField $mask, mixed $maskDefault, string $caseDefault): void {
    $fields = data_get(maskFieldCall($mask, 'configFieldItems'), '0.fields');

    expect(data_get($fields, 'input_mask.default'))->toBe($maskDefault)
        ->and(data_get($fields, 'transform_case.default'))->toBe($caseDefault);
})->with([
    'a mask preset only' => [fn () => new class extends MaskField
    {
        protected ?string $presetInputMask = '00000';
    }, '00000', 'none'],
    'a case preset only' => [fn () => new class extends MaskField
    {
        protected ?string $presetTransformCase = 'lowercase';
    }, null, 'lowercase'],
    'both presets' => [fn () => new class extends MaskField
    {
        protected ?string $presetInputMask = '00000';

        protected ?string $presetTransformCase = 'uppercase';
    }, '00000', 'uppercase'],
    'null presets' => [fn () => new class extends MaskField
    {
        protected ?string $presetInputMask = null;

        protected ?string $presetTransformCase = null;
    }, null, 'none'],
]);

it('folds a single preset into its own config with the other one defaulted', function (MaskField $mask, array $expected): void {
    $mask->preProcess('value');

    expect(maskFieldOwnConfig($mask))->toBe($expected);
})->with([
    'a mask preset only' => [fn () => new class extends MaskField
    {
        protected ?string $presetInputMask = '00000';
    }, [
        'input_mask' => '00000',
        'transform_case' => 'none',
        '_preset_fields' => ['input_mask' => '00000', 'transform_case' => 'none'],
    ]],
    'a case preset only' => [fn () => new class extends MaskField
    {
        protected ?string $presetTransformCase = 'lowercase';
    }, [
        'input_mask' => '',
        'transform_case' => 'lowercase',
        '_preset_fields' => ['input_mask' => '', 'transform_case' => 'lowercase'],
    ]],
    'null presets' => [fn () => new class extends MaskField
    {
        protected ?string $presetInputMask = null;

        protected ?string $presetTransformCase = null;
    }, []],
]);

it('keeps existing config entries when it folds in the presets', function (): void {
    $mask = new class extends MaskField
    {
        protected array $config = ['existing' => 'kept'];

        protected ?string $presetInputMask = '00000';
    };

    $mask->process('value');

    expect(maskFieldOwnConfig($mask))->toHaveKey('existing', 'kept')
        ->toHaveKey('input_mask', '00000');
});

it('removes the named fields from every section that has fields', function (): void {
    $mask = maskFieldWithSections([
        ['display' => 'A', 'fields' => ['keep' => ['type' => 'text'], 'drop' => ['type' => 'text'], 'gone' => ['type' => 'text']]],
        ['display' => 'B'],
        ['display' => 'C', 'fields' => 'not a list'],
    ]);

    expect(maskFieldCall($mask, 'hideConfigFields', [['drop', 'gone', 'missing']]))->toBe([
        ['display' => 'A', 'fields' => ['keep' => ['type' => 'text']]],
        ['display' => 'B'],
        ['display' => 'C', 'fields' => 'not a list'],
    ]);
});

it('keeps only the named fields, in the order asked, and drops emptied sections', function (): void {
    $mask = maskFieldWithSections([
        ['display' => 'A', 'fields' => ['one' => ['type' => 'text'], 'two' => ['type' => 'toggle'], 'three' => ['type' => 'text']]],
        ['display' => 'B', 'fields' => ['other' => ['type' => 'text']]],
        ['display' => 'C', 'fields' => 'not a list'],
    ]);

    expect(maskFieldCall($mask, 'createSimplifiedConfig', [['three', 'one', 'missing']]))->toBe([
        ['display' => 'A', 'fields' => ['three' => ['type' => 'text'], 'one' => ['type' => 'text']]],
    ])->and(maskFieldCall($mask, 'createSimplifiedConfig', [['missing']]))->toBe([]);
});

it('reads the presets to hide from the bound field config', function (): void {
    $mask = new MaskField;
    $mask->setField(new Field('mask', ['type' => 'mask_field', '_preset_fields' => ['input_mask' => '000']]));

    expect(data_get(maskFieldCall($mask, 'hidePresetFields'), '0.fields.input_mask.visibility'))->toBe('hidden')
        ->and(data_get(maskFieldCall($mask, 'hidePresetFields'), '0.fields.transform_case.visibility'))->toBeNull();
});

it('hides the named preset fields in every section that has fields', function (): void {
    // The sections and the presets are fixed so the section walk can be checked alone
    $mask = new class extends MaskField
    {
        protected function configFieldItems(): array
        {
            return [
                ['display' => 'A', 'fields' => ['input_mask' => ['type' => 'text'], 'transform_case' => ['type' => 'radio'], 'flat' => 'not a field']],
                ['display' => 'B'],
                ['display' => 'C', 'fields' => 'not a list'],
            ];
        }

        public function config(?string $key = null, mixed $fallback = null): mixed
        {
            return $key === '_preset_fields' ? ['input_mask' => '000', 'flat' => 'x', 'missing' => 'y'] : $fallback;
        }
    };

    expect(maskFieldCall($mask, 'hidePresetFields'))->toBe([
        ['display' => 'A', 'fields' => [
            'input_mask' => ['type' => 'text', 'visibility' => 'hidden'],
            'transform_case' => ['type' => 'radio'],
            'flat' => 'not a field',
        ]],
        ['display' => 'B'],
        ['display' => 'C', 'fields' => 'not a list'],
    ]);
});

it('hides nothing when the field config names no presets', function (): void {
    $sections = [['display' => 'A', 'fields' => ['input_mask' => ['type' => 'text']]]];

    expect(maskFieldCall(maskFieldWithSections($sections), 'hidePresetFields'))->toBe($sections);
});

it('treats a non-list preset value as a list before walking it', function (): void {
    // A scalar preset value is cast to a one-item list, which names no field
    $mask = new class extends MaskField
    {
        public function config(?string $key = null, mixed $fallback = null): mixed
        {
            return $key === '_preset_fields' ? 'input_mask' : $fallback;
        }
    };

    expect(data_get(maskFieldCall($mask, 'hidePresetFields'), '0.fields.input_mask.visibility'))->toBeNull();
});
