<?php

declare(strict_types=1);

use App\Enums\SchemaType;
use App\Fieldtypes\SchemaObject;
use App\Services\SchemaTemplates\SchemaTypeOptions;
use App\Tags\Schema\SchemaPropertyValidator;
use Illuminate\Support\Facades\Config;
use Statamic\Entries\Entry as EntryModel;
use Statamic\Facades\Entry;
use Statamic\Fields\Field;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Unit tests for SchemaObject fieldtype (preload, blacklist).
 *
 * Mutation: 100% on App\Fieldtypes\SchemaObject (151 mutants, --covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Fieldtypes/SchemaObjectFieldtypeTest.php
 */
it('excludes blacklisted properties for a specific type in preload', function () {
    // Set blacklist: hide mainEntityOfPage on WebPage in the CP (specific only)
    Config::set(
        'schema_object.property_blacklist',
        [
            'general' => [],
            'specific' => [
                'WebPage' => ['mainEntityOfPage'],
            ],
        ]
    );

    $fieldtype = new SchemaObject;
    $preload = $fieldtype->preload();

    expect($preload)->toHaveKey('properties_by_type');
    expect(data_get($preload, 'properties_by_type'))->toHaveKey('WebPage');

    $webPageProps = data_get($preload, 'properties_by_type.WebPage');
    expect($webPageProps)->not->toContain('mainEntityOfPage');

    // Restore default so other tests are not affected
    Config::set(
        'schema_object.property_blacklist',
        ['general' => [], 'specific' => []]
    );
});

it('applies the general blacklist to all schema types in preload', function () {
    // General blacklist: hide mainEntityOfPage for every type
    Config::set(
        'schema_object.property_blacklist',
        [
            'general' => ['mainEntityOfPage'],
            'specific' => [],
        ]
    );

    $fieldtype = new SchemaObject;
    $preload = $fieldtype->preload();

    expect($preload)->toHaveKey('properties_by_type');

    $propertiesByType = data_get($preload, 'properties_by_type');
    foreach (collect(is_array($propertiesByType) ? $propertiesByType : []) as $properties) {
        expect($properties)->not->toContain('mainEntityOfPage');
    }

    Config::set(
        'schema_object.property_blacklist',
        ['general' => [], 'specific' => []]
    );
});

it('normalizes a string type to a single-element array', function () {
    expect(SchemaObject::normalizeTypeToArray('Thing'))->toBe(['Thing']);
    expect(SchemaObject::normalizeTypeToArray('HotelRoom'))->toBe(['HotelRoom']);
});

it('normalizes an empty string type to an empty array', function () {
    expect(SchemaObject::normalizeTypeToArray(''))->toBe([]);
});

it('passes through an array type and dedupes and filters it', function () {
    expect(SchemaObject::normalizeTypeToArray(['HotelRoom', 'Product']))->toBe(['HotelRoom', 'Product']);
    expect(SchemaObject::normalizeTypeToArray(['Thing', 'Thing', '']))->toBe(['Thing']);
    expect(SchemaObject::normalizeTypeToArray(['', 'Thing', 'Thing', 'Hotel']))->toBe(['Thing', 'Hotel']);
});

it('returns the default value type as an empty array for MTE support', function () {
    $fieldtype = new SchemaObject;
    $default = $fieldtype->defaultValue();

    expect($default['type'])->toBe([]);
});

it('normalizes the processed type to an array for single and multiple types', function () {
    $fieldtype = new SchemaObject;

    $out = $fieldtype->process(['type' => 'Thing', 'properties' => []]);
    expect($out['type'])->toBe(['Thing']);

    $out = $fieldtype->process(['type' => ['HotelRoom', 'Product'], 'properties' => []]);
    expect($out['type'])->toBe(['HotelRoom', 'Product']);
});

it('augments the type as an array for templates and JSON-LD', function () {
    $field = new Field('schema', ['type' => 'schema_object', 'antlers' => false]);
    $fieldtype = new SchemaObject;
    $fieldtype->setField($field);

    $value = [
        'type' => ['HotelRoom', 'Product'],
        'properties' => ['name' => 'Suite'],
    ];
    $augmented = $fieldtype->augment($value);

    expect(data_get($augmented, 'type'))->toBe(['HotelRoom', 'Product']);
});

/**
 * Build a SchemaObject fieldtype bound to a Field with the given config.
 *
 * @param  array<string, mixed>  $config  Field config merged over the type handle
 */
function schemaObjectFieldtype(array $config = []): SchemaObject
{
    // Bind a Field named schema so nested rule keys are prefixed with it
    $fieldtype = new SchemaObject;
    $fieldtype->setField(new Field('schema', array_merge(['type' => 'schema_object'], $config)));

    return $fieldtype;
}

/**
 * Read one key of a preload payload as an array.
 *
 * @param  array<string, mixed>  $preload
 * @return array<string, mixed>
 */
function schemaObjectPreloadPart(array $preload, string $key): array
{
    /** @var array<string, mixed> $part */
    $part = $preload[$key];

    return $part;
}

it('identifies itself in the Control Panel', function (): void {
    $fieldtype = new SchemaObject;

    expect($fieldtype->component())->toBe('schema_object')
        ->and($fieldtype->icon())->toBe('code')
        ->and($fieldtype->selectable())->toBeTrue()
        ->and($fieldtype->localizable())->toBeFalse()
        ->and($fieldtype->categories())->toBe(['special', 'structured'])
        ->and($fieldtype->rules())->toBe(['array']);
});

it('configures the type selector and Antlers parsing', function (): void {
    expect((new ReflectionMethod(SchemaObject::class, 'configFieldItems'))->invoke(new SchemaObject))->toBe([
        [
            'display' => 'Schema Object',
            'fields' => [
                'type_selector_clearable' => [
                    'display' => 'Clearable',
                    'instructions' => 'Allow clearing the type selector so the placeholder is shown again.',
                    'type' => 'toggle',
                    'default' => true,
                ],
                'type_selector_disabled' => [
                    'display' => 'Disable type selector',
                    'instructions' => 'Prevent adding or changing schema types in the CP.',
                    'type' => 'toggle',
                    'default' => false,
                ],
                'type_selector_searchable' => [
                    'display' => 'Searchable',
                    'instructions' => 'Allow searching the type list. Recommended when many types are available.',
                    'type' => 'toggle',
                    'default' => true,
                ],
                'type_selector_multiple' => [
                    'display' => 'Multiple selection',
                    'instructions' => 'Allow selecting more than one type (Multi Typed Entities / MTE). When off, only a single type can be chosen.',
                    'type' => 'toggle',
                    'default' => true,
                ],
                'antlers' => [
                    'display' => 'Allow Antlers',
                    'instructions' => 'Parse property values as Antlers (e.g., {{ title }}) when output in templates.',
                    'type' => 'toggle',
                    'default' => true,
                    'section' => 'antlers',
                ],
            ],
        ],
    ]);
});

it('preloads every type with its blacklisted properties removed, its description and property instructions', function (): void {
    Config::set('schema_object.property_blacklist', [
        'general' => ['name'],
        'specific' => ['WebPage' => ['about']],
    ]);

    $preload = (new SchemaObject)->preload();
    $properties = schemaObjectPreloadPart($preload, 'properties_by_type');
    $descriptions = schemaObjectPreloadPart($preload, 'type_descriptions');
    $instructions = schemaObjectPreloadPart($preload, 'property_instructions_by_type');

    $webPage = array_values(array_diff(SchemaPropertyValidator::validPropertiesForType('WebPage'), ['name', 'about']));
    $thing = array_values(array_diff(SchemaPropertyValidator::validPropertiesForType('Thing'), ['name']));
    $webPageInstructions = array_filter(array_combine($webPage, array_map(
        fn (string $property): ?string => SchemaType::propertyDescriptionFor('WebPage', $property),
        $webPage
    )), fn (?string $instruction): bool => $instruction !== null);

    expect(array_keys($preload))->toBe([
        'type_options',
        'properties_by_type',
        'type_descriptions',
        'property_instructions_by_type',
        'property_replicator_sets_by_type',
        'property_replicator_config_by_type',
        'property_replicator_meta_by_type',
    ])
        ->and($preload['type_options'])->toBe(SchemaTypeOptions::schemaObjectTypes())
        ->and(array_keys($properties))->toBe(array_keys(SchemaTypeOptions::schemaObjectTypes()))
        ->and($properties['WebPage'])->toBe($webPage)
        ->and($properties['Thing'])->toBe($thing)
        ->and($descriptions['WebPage'])->toBe(SchemaType::typeDescriptionFor('WebPage'))
        ->and($instructions['WebPage'])->toBe($webPageInstructions)
        ->and($instructions['WebPage'])->not->toHaveKey('name')
        ->and($preload['property_replicator_sets_by_type'])->toBe([])
        ->and($preload['property_replicator_config_by_type'])->toBe([])
        ->and($preload['property_replicator_meta_by_type'])->toBe([]);
});

it('ignores a blacklist whose parts are not lists', function (): void {
    Config::set('schema_object.property_blacklist', ['general' => 'name', 'specific' => 'WebPage']);

    $properties = schemaObjectPreloadPart((new SchemaObject)->preload(), 'properties_by_type');

    expect($properties['WebPage'])->toBe(SchemaPropertyValidator::validPropertiesForType('WebPage'));
});

it('describes only the types that have a description', function (): void {
    $descriptions = schemaObjectPreloadPart((new SchemaObject)->preload(), 'type_descriptions');
    $described = array_filter(
        array_keys(SchemaTypeOptions::schemaObjectTypes()),
        fn (string $type): bool => SchemaType::typeDescriptionFor($type) !== null
    );

    expect(array_keys($descriptions))->toBe(array_values($described));
});

it('augments a value that is not a typed property map unchanged', function (mixed $value): void {
    expect(schemaObjectFieldtype(['antlers' => false])->augment($value))->toBe($value);
})->with([
    'null' => [null],
    'a string' => ['Thing'],
    'no properties' => [['type' => 'Thing']],
    'non-array properties' => [['type' => 'Thing', 'properties' => 'name']],
]);

it('augments each property to its effective string without Antlers', function (): void {
    expect(schemaObjectFieldtype(['antlers' => false])->augment([
        'type' => 5,
        'properties' => [
            'name' => 'Suite {{ title }}',
            'description' => ['mode' => 'custom', 'value' => [['value' => 'Custom', 'enabled' => true]]],
            'alternateName' => ['mode' => 'inherit_custom', 'value' => [['value' => 'Inherited custom']]],
            'url' => ['mode' => 'inherit', 'value' => [['value' => 'Ignored']]],
            'sameAs' => [['value' => 'Rows']],
        ],
    ]))->toBe([
        'type' => [],
        'properties' => [
            'name' => 'Suite {{ title }}',
            'description' => 'Custom',
            'alternateName' => 'Inherited custom',
            'url' => '',
            'sameAs' => 'Rows',
        ],
    ]);
});

it('parses each property as Antlers against the parent entry, without the field itself', function (): void {
    $entry = mustBe(EntryModel::class, Entry::make(), 'entry');
    $entry->data(['title' => 'Grand Hotel', 'schema' => 'own value']);
    $field = new Field('schema', ['type' => 'schema_object', 'antlers' => true]);
    $field->setParent($entry);

    $fieldtype = new SchemaObject;
    $fieldtype->setField($field);

    expect($fieldtype->augment([
        'type' => 'Hotel',
        'properties' => ['name' => '{{ title }} suite', 'description' => '[{{ schema }}]'],
    ]))->toBe([
        'type' => ['Hotel'],
        'properties' => ['name' => 'Grand Hotel suite', 'description' => '[]'],
    ]);
});

it('parses Antlers with no context when the field has no parent', function (): void {
    expect(schemaObjectFieldtype(['antlers' => true])->augment([
        'type' => ['Hotel'],
        'properties' => ['name' => '{{ title }}Suite'],
    ]))->toBe(['type' => ['Hotel'], 'properties' => ['name' => 'Suite']]);
});

it('requires a type only when the field is required', function (array $config, array $rules): void {
    /** @var array<string, mixed> $typedConfig */
    $typedConfig = $config;

    expect(schemaObjectFieldtype($typedConfig)->extraRules())->toBe($rules);
})->with([
    'not required' => [[], []],
    'the field required' => [['required' => true], ['schema.type' => 'required|array|min:1']],
    'required in the validate list' => [['validate' => ['array', 'required']], ['schema.type' => 'required|array|min:1']],
    'a validate string, not a list' => [['validate' => 'required'], []],
    'a loose required match in the validate list' => [['validate' => [true]], []],
]);

it('adds no nested rules without a bound field', function (): void {
    expect((new SchemaObject)->extraRules())->toBe([]);
});

it('shapes a submitted value for validation', function (mixed $value, array $validatable): void {
    expect((new SchemaObject)->preProcessValidatable($value))->toBe($validatable);
})->with([
    'a string' => ['Thing', ['type' => [], 'properties' => []]],
    'a string type with properties' => [['type' => 'Hotel', 'properties' => ['name' => 'x']], ['type' => ['Hotel'], 'properties' => ['name' => 'x']]],
    'a non-string type and properties' => [['type' => 5, 'properties' => 'name'], ['type' => [], 'properties' => []]],
    'no type' => [['properties' => ['name' => 'x']], ['type' => [], 'properties' => ['name' => 'x']]],
]);

it('stores and edits each property in the mode and value group shape', function (mixed $data, array $expected): void {
    $fieldtype = new SchemaObject;

    expect($fieldtype->process($data))->toBe($expected)
        ->and($fieldtype->preProcess($data))->toBe($expected);
})->with([
    'a string' => ['Thing', ['type' => [], 'properties' => []]],
    'a non-string type and properties' => [['type' => 5, 'properties' => 'name'], ['type' => [], 'properties' => []]],
    'group, row list, empty string and other values' => [[
        'type' => ['Hotel', 'Hotel'],
        'properties' => [
            'group' => ['mode' => 'custom', 'value' => [['value' => 'x']]],
            'loose group' => ['mode' => 5, 'value' => 'x'],
            'bare group' => ['mode' => null],
            'rows' => [['value' => 'y']],
            'empty' => '',
            'number' => 5,
        ],
    ], [
        'type' => ['Hotel'],
        'properties' => [
            'group' => ['mode' => 'custom', 'value' => [['value' => 'x']]],
            'loose group' => ['mode' => 'inherit', 'value' => []],
            'bare group' => ['mode' => 'inherit', 'value' => []],
            'rows' => ['mode' => 'custom', 'value' => [['value' => 'y']]],
            'empty' => ['mode' => 'custom', 'value' => []],
            'number' => ['mode' => 'inherit', 'value' => []],
        ],
    ]],
]);

it('turns a legacy string property into one enabled short-text row', function (): void {
    $fieldtype = new SchemaObject;

    foreach ([$fieldtype->process(['properties' => ['name' => 'Suite']]), $fieldtype->preProcess(['properties' => ['name' => 'Suite']])] as $out) {
        /** @var array{mode: string, value: list<array<string, mixed>>} $name */
        $name = data_get($out, 'properties.name');

        expect($name['mode'])->toBe('custom')
            ->and($name['value'])->toHaveCount(1)
            ->and($name['value'][0])->toHaveKeys(['_id', 'type', 'value', 'enabled'])
            ->and(mustBeString($name['value'][0]['_id'], 'row id'))->toHaveLength(9)
            ->and(array_diff_key($name['value'][0], ['_id' => true]))->toBe(['type' => 'text_short', 'value' => 'Suite', 'enabled' => true]);
    }
});

it('recognizes a property group by its mode key', function (mixed $value, bool $isGroup): void {
    expect(SchemaObject::isPropertyGroupValue($value))->toBe($isGroup);
})->with([
    'a mode key' => [['mode' => null], true],
    'no mode key' => [['value' => 'x'], false],
    'a string' => ['mode', false],
]);

it('reads replicator rows from any stored property shape', function (mixed $value, array $rows): void {
    expect(SchemaObject::propertyValueToReplicatorRows($value))->toBe($rows);
})->with([
    'a group' => [['mode' => 'custom', 'value' => [['value' => 'x']]], [['value' => 'x']]],
    'a group with a non-list value' => [['mode' => 'custom', 'value' => 'x'], []],
    'a row list' => [[['value' => 'y']], [['value' => 'y']]],
    'an empty string' => ['', []],
    'a number' => [5, []],
]);

it('wraps a non-empty string as one enabled short-text row', function (): void {
    $rows = SchemaObject::propertyValueToReplicatorRows('Suite');

    expect($rows)->toHaveCount(1)
        ->and(mustBeString($rows[0]['_id'], 'row id'))->toHaveLength(9)
        ->and(array_diff_key($rows[0], ['_id' => true]))->toBe(['type' => 'text_short', 'value' => 'Suite', 'enabled' => true]);
});

it('takes the first enabled, non-empty row value as the effective string', function (array|string $value, string $effective): void {
    /** @var array<int, array<string, mixed>>|string $typedValue */
    $typedValue = $value;

    expect(SchemaObject::replicatorRowsToEffectiveString($typedValue))->toBe($effective);
})->with([
    'a string' => ['As is', 'As is'],
    'no rows' => [[], ''],
    'a disabled row first' => [[['value' => 'off', 'enabled' => false], ['value' => 'on']], 'on'],
    'null and empty values first' => [[['value' => null], ['value' => ''], ['value' => 'third']], 'third'],
    'a missing value' => [[['enabled' => true], ['value' => 'second']], 'second'],
    'a number' => [[['value' => 5]], '5'],
    'a zero' => [[['value' => 0]], '0'],
    'a list value' => [[['value' => ['a', 'b']]], '["a","b"]'],
    'only disabled rows' => [[['value' => 'off', 'enabled' => false]], ''],
]);

it('uses the rows only for a group whose mode is strictly custom or inherit_custom', function (): void {
    // true equals any non-empty string loosely, so the mode match must be strict
    expect(schemaObjectFieldtype(['antlers' => false])->augment([
        'type' => 'Hotel',
        'properties' => ['name' => ['mode' => true, 'value' => [['value' => 'Suite']]]],
    ]))->toBe(['type' => ['Hotel'], 'properties' => ['name' => '']]);
});

it('builds no Antlers context from parent data that is not a collection', function (): void {
    // A parent whose data() is a plain array gives no context rather than failing
    $parent = new class
    {
        /**
         * @return array<string, string>
         */
        public function data(): array
        {
            return ['title' => 'Grand Hotel'];
        }
    };
    $field = new Field('schema', ['type' => 'schema_object', 'antlers' => true]);
    $field->setParent($parent);

    $fieldtype = new SchemaObject;
    $fieldtype->setField($field);

    expect($fieldtype->augment(['type' => 'Hotel', 'properties' => ['name' => '{{ title }}Suite']]))
        ->toBe(['type' => ['Hotel'], 'properties' => ['name' => 'Suite']]);
});

it('does not run PHP inside an Antlers property value', function (): void {
    expect(schemaObjectFieldtype(['antlers' => true])->augment([
        'type' => 'Hotel',
        'properties' => ['name' => 'a{{? echo "X"; ?}}b'],
    ]))->toBe(['type' => ['Hotel'], 'properties' => ['name' => 'ab']]);
});

it('returns the default value for an object instead of reading it as an array', function (): void {
    $fieldtype = new SchemaObject;
    $default = ['type' => [], 'properties' => []];

    expect($fieldtype->process(new stdClass))->toBe($default)
        ->and($fieldtype->preProcess(new stdClass))->toBe($default)
        ->and($fieldtype->preProcessValidatable(new stdClass))->toBe($default);
});

it('falls back to empty text for a row value JSON cannot encode', function (): void {
    expect(SchemaObject::replicatorRowsToEffectiveString([['value' => ["\xB1"]]]))->toBe('');
});

it('keys the required type rule by the handle as text, or by nothing for a missing handle', function (mixed $handle, string $key): void {
    // Blueprint YAML can give a numeric handle; it is used as text in the rule key
    $fieldtype = new SchemaObject;
    $fieldtype->setField(new Field($handle, ['type' => 'schema_object', 'required' => true]));

    expect($fieldtype->extraRules())->toBe([$key => 'required|array|min:1']);
})->with([
    'a numeric handle' => [5, '5.type'],
    'no handle' => [null, '.type'],
]);
