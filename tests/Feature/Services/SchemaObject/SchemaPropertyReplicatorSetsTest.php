<?php

declare(strict_types=1);

use App\Services\SchemaObject\SchemaPropertyReplicatorSets;
use Tests\TestCase;

uses(TestCase::class);

// cspell:ignore actionstatustype

/**
 * The Antlers set every range type list ends with.
 *
 * @return array<int|string, mixed>
 */
function replicatorAntlersSet(): array
{
    return [
        ['handle' => 'antlers'],
        'display' => 'Antlers',
        'icon' => 'fieldtype-code',
        'fields' => [
            [
                'handle' => 'value',
                'field' => [
                    'type' => 'code',
                    'display' => 'Antlers',
                    'mode' => 'html',
                    'mode_selectable' => false,
                ],
            ],
        ],
    ];
}

/**
 * The set handles setsForRangeTypes() builds for the given range types, in order.
 *
 * @param  list<string>  $types
 * @return list<int|string>
 */
function schemaReplicatorSetHandles(SchemaPropertyReplicatorSets $service, array $types): array
{
    $sets = data_get($service->setsForRangeTypes($types), 'main.sets');

    return \is_array($sets) ? array_keys($sets) : [];
}

/**
 * The replicator set config SchemaPropertyReplicatorSets builds from Schema.org range
 * types, the set meta and defaults it builds ahead for the Vue replicator, and the flattened
 * set list the Vue component reads.
 *
 * @command  php artisan test --compact tests/Feature/Services/SchemaObject/SchemaPropertyReplicatorSetsTest.php
 */
it('builds one set per data type, plus the Antlers set', function (string $type, array $sets): void {
    $config = (new SchemaPropertyReplicatorSets)->setsForRangeTypes([$type]);

    expect(data_get($config, 'main.display'))->toBe('Main')
        ->and(data_get($config, 'main.sets'))->toBe([...$sets, 'antlers' => replicatorAntlersSet()]);
})->with([
    'Number' => ['Number', [
        'number' => [
            0 => [
                'handle' => 'number',
            ],
            'display' => 'Number',
            'icon' => 'fieldtype-float',
            'fields' => [
                [
                    'handle' => 'value',
                    'field' => [
                        'type' => 'float',
                        'display' => 'Number',
                    ],
                ],
            ],
        ],
    ]],
    'Float' => ['Float', [
        'float' => [
            0 => [
                'handle' => 'float',
            ],
            'display' => 'Decimal Number',
            'icon' => 'fieldtype-float',
            'fields' => [
                [
                    'handle' => 'value',
                    'field' => [
                        'type' => 'float',
                        'display' => 'Decimal Number',
                    ],
                ],
            ],
        ],
    ]],
    'Integer' => ['Integer', [
        'integer' => [
            0 => [
                'handle' => 'integer',
            ],
            'display' => 'Integer',
            'icon' => 'fieldtype-integer',
            'fields' => [
                [
                    'handle' => 'value',
                    'field' => [
                        'type' => 'integer',
                        'display' => 'Integer',
                    ],
                ],
            ],
        ],
    ]],
    'Boolean' => ['Boolean', [
        'boolean' => [
            0 => [
                'handle' => 'boolean',
            ],
            'display' => 'True/False',
            'icon' => 'fieldtype-toggle',
            'fields' => [
                [
                    'handle' => 'value',
                    'field' => [
                        'type' => 'true_false',
                        'display' => 'True/False',
                    ],
                ],
            ],
        ],
    ]],
    'False' => ['False', [
        'false' => [
            0 => [
                'handle' => 'false',
            ],
            'display' => 'False',
            'icon' => 'fieldtype-toggle',
            'fields' => [
                [
                    'handle' => 'value',
                    'field' => [
                        'type' => 'toggle',
                        'display' => 'False',
                    ],
                ],
            ],
        ],
    ]],
    'True' => ['True', [
        'true' => [
            0 => [
                'handle' => 'true',
            ],
            'display' => 'True',
            'icon' => 'fieldtype-toggle',
            'fields' => [
                [
                    'handle' => 'value',
                    'field' => [
                        'type' => 'toggle',
                        'display' => 'True',
                    ],
                ],
            ],
        ],
    ]],
    'DateTime' => ['DateTime', [
        'datetime' => [
            0 => [
                'handle' => 'datetime',
            ],
            'display' => 'Date and Time',
            'icon' => 'fieldtype-date',
            'fields' => [
                [
                    'handle' => 'value',
                    'field' => [
                        'type' => 'date',
                        'display' => 'Date and Time',
                        'time_enabled' => true,
                        'format' => 'c',
                    ],
                ],
            ],
        ],
    ]],
    'Date' => ['Date', [
        'date' => [
            0 => [
                'handle' => 'date',
            ],
            'display' => 'Date',
            'icon' => 'fieldtype-date',
            'fields' => [
                [
                    'handle' => 'value',
                    'field' => [
                        'type' => 'date',
                        'display' => 'Date',
                        'time_enabled' => false,
                        'format' => 'c',
                    ],
                ],
            ],
        ],
    ]],
    'Text' => ['Text', [
        'text_short' => [
            0 => [
                'handle' => 'text_short',
            ],
            'display' => 'Text',
            'icon' => 'fieldtype-text',
            'fields' => [
                [
                    'handle' => 'value',
                    'field' => [
                        'type' => 'text',
                        'display' => 'Text',
                    ],
                ],
            ],
        ],
        'text_long' => [
            0 => [
                'handle' => 'text_long',
            ],
            'display' => 'Textarea',
            'icon' => 'fieldtype-textarea',
            'fields' => [
                [
                    'handle' => 'value',
                    'field' => [
                        'type' => 'textarea',
                        'display' => 'Textarea',
                    ],
                ],
            ],
        ],
    ]],
    'CssSelectorType' => ['CssSelectorType', [
        'css_selector' => [
            0 => [
                'handle' => 'css_selector',
            ],
            'display' => 'CSS Selector',
            'icon' => 'code-inline',
            'fields' => [
                [
                    'handle' => 'value',
                    'field' => [
                        'type' => 'css_selector',
                        'display' => 'CSS Selector',
                    ],
                ],
            ],
        ],
    ]],
    'PronounceableText' => ['PronounceableText', [
        'pronounceable_text' => [
            0 => [
                'handle' => 'pronounceable_text',
            ],
            'display' => 'Pronounceable Text',
            'icon' => 'fieldtype-text',
            'fields' => [
                [
                    'handle' => 'value',
                    'field' => [
                        'type' => 'text',
                        'display' => 'Pronounceable Text',
                    ],
                ],
            ],
        ],
    ]],
    'URL' => ['URL', [
        'url' => [
            0 => [
                'handle' => 'url',
            ],
            'display' => 'URL',
            'icon' => 'web',
            'fields' => [
                [
                    'handle' => 'value',
                    'field' => [
                        'type' => 'url',
                        'display' => 'URL',
                    ],
                ],
            ],
        ],
    ]],
    'XPathType' => ['XPathType', [
        'xpath' => [
            0 => [
                'handle' => 'xpath',
            ],
            'display' => 'XPath',
            'icon' => 'code-inline',
            'fields' => [
                [
                    'handle' => 'value',
                    'field' => [
                        'type' => 'xpath',
                        'display' => 'XPath',
                    ],
                ],
            ],
        ],
    ]],
    'Time' => ['Time', [
        'time' => [
            0 => [
                'handle' => 'time',
            ],
            'display' => 'Time',
            'icon' => 'fieldtype-time',
            'fields' => [
                [
                    'handle' => 'value',
                    'field' => [
                        'type' => 'time',
                        'display' => 'Time',
                    ],
                ],
            ],
        ],
    ]],
    'Distance' => ['Distance', [
        'text_distance' => [
            0 => [
                'handle' => 'text_distance',
            ],
            'display' => 'Text',
            'icon' => 'fieldtype-text',
            'fields' => [
                [
                    'handle' => 'value',
                    'field' => [
                        'type' => 'text',
                        'display' => 'Text',
                    ],
                ],
            ],
        ],
    ]],
]);

it('builds a select set for an enumeration, an entries set for a thing, and the Antlers set last', function (): void {
    expect((new SchemaPropertyReplicatorSets)->setsForRangeTypes(['ActionStatusType', 'Person']))->toBe([
        'main' => [
            'display' => 'Main',
            'sets' => [
                'enumeration_actionstatustype' => [
                    ['handle' => 'enumeration_actionstatustype'],
                    'display' => 'ActionStatusType',
                    'icon' => 'select',
                    'fields' => [
                        [
                            'handle' => 'value',
                            'field' => [
                                'type' => 'select',
                                'display' => 'ActionStatusType',
                                'options' => [
                                    'ActiveActionStatus' => 'Active Action Status',
                                    'CompletedActionStatus' => 'Completed Action Status',
                                    'FailedActionStatus' => 'Failed Action Status',
                                    'PotentialActionStatus' => 'Potential Action Status',
                                ],
                                'clearable' => true,
                                'multiple' => true,
                            ],
                        ],
                    ],
                ],
                'thing_person' => [
                    ['handle' => 'thing_person'],
                    'display' => 'Person',
                    'icon' => 'fieldtype-entries',
                    'fields' => [
                        [
                            'handle' => 'value',
                            'field' => [
                                'type' => 'entries',
                                'display' => 'Person',
                                'collections' => ['schema_objects'],
                                'query_scopes' => ['entries_by_schema_type_person'],
                                'validate' => ['entry_schema_type_in:Person'],
                            ],
                        ],
                    ],
                ],
                'antlers' => [
                    ['handle' => 'antlers'],
                    'display' => 'Antlers',
                    'icon' => 'fieldtype-code',
                    'fields' => [
                        [
                            'handle' => 'value',
                            'field' => [
                                'type' => 'code',
                                'display' => 'Antlers',
                                'mode' => 'html',
                                'mode_selectable' => false,
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ]);
});

it('reads an unknown type and an unlisted data type as text, with a slugged handle', function (): void {
    $service = new SchemaPropertyReplicatorSets;

    expect(schemaReplicatorSetHandles($service, ['Not A-Type', 'DataType', 'Number']))
        ->toBe(['text_not_a_type', 'text_datatype', 'number', 'antlers'])
        ->and(data_get($service->setsForRangeTypes(['Not A-Type']), 'main.sets.text_not_a_type.display'))->toBe('Text');
});

it('builds the antlers set alone for no range types', function (): void {
    expect(schemaReplicatorSetHandles(new SchemaPropertyReplicatorSets, []))->toBe(['antlers']);
});

it('points entries sets at the configured collection, memoized per collection', function (): void {
    $service = new SchemaPropertyReplicatorSets;
    $collections = static fn (): mixed => data_get($service->setsForRangeTypes(['Person']), 'main.sets.thing_person.fields.0.field.collections');

    config(['schema_object.entries_collection' => 'people']);
    $people = $collections();
    config(['schema_object.entries_collection' => 'schema_objects']);
    $schemaObjects = $collections();
    config(['schema_object.entries_collection' => 7]);
    $numeric = $collections();

    expect($people)->toBe(['people'])
        ->and($schemaObjects)->toBe(['schema_objects'])
        ->and($numeric)->toBe(['7']);
});

it('reads a non-text collection setting as the default collection, in the memo and the set alike', function (): void {
    $service = new SchemaPropertyReplicatorSets;
    $collections = static fn (): mixed => data_get($service->setsForRangeTypes(['Place']), 'main.sets.thing_place.fields.0.field.collections');

    config(['schema_object.entries_collection' => ['not', 'text']]);
    $nonText = $collections();
    config(['schema_object.entries_collection' => 'schema_objects']);
    $default = $collections();
    config(['schema_object.entries_collection' => 'people']);

    expect($nonText)->toBe(['schema_objects'])
        ->and($default)->toBe(['schema_objects'])
        ->and($collections())->toBe(['people']);
});

it('builds no meta for a config without a main set list', function (mixed $config): void {
    /** @var array<string, mixed> $typedConfig */
    $typedConfig = $config;

    expect((new SchemaPropertyReplicatorSets)->metaForSets($typedConfig))->toBe(['new' => [], 'defaults' => []])
        ->and((new SchemaPropertyReplicatorSets)->configSetsForVue($typedConfig))->toBe([]);
})->with([
    'no main' => [[]],
    'a main that is not a list' => [['main' => 'sets']],
    'a main with no sets' => [['main' => ['display' => 'Main']]],
    'sets that are not a list' => [['main' => ['sets' => 'number']]],
]);

it('builds meta and defaults for each set from its fields', function (): void {
    $service = new SchemaPropertyReplicatorSets;
    $meta = $service->metaForSets($service->setsForRangeTypes(['Number']));
    /** @var array<string, mixed> $number */
    $number = $meta['new']['number'];
    /** @var array{config: array<string, mixed>, fieldtype_handle: string} $value */
    $value = $number['value'];

    expect(array_keys($meta['new']))->toBe(['number', 'antlers'])
        ->and($meta['defaults'])->toBe([
            'number' => ['value' => null],
            'antlers' => ['value' => ['code' => null, 'mode' => 'html']],
        ])
        ->and(array_keys($number))->toBe(['value', '_'])
        ->and($number['_'])->toBe('_')
        ->and($value['fieldtype_handle'])->toBe('float')
        ->and([$value['config']['type'], $value['config']['display'], $value['config']['handle']])->toBe(['float', 'Number', 'value'])
        ->and($meta['new']['antlers'])->toBe(['value' => null, '_' => '_']);
});

it('keys meta by a set\'s own handle, and skips sets without usable fields', function (): void {
    $meta = (new SchemaPropertyReplicatorSets)->metaForSets(['main' => ['sets' => [
        'listed' => ['handle' => 'own', 'fields' => [['handle' => 'value', 'field' => ['type' => 'text']]]],
        'not a set' => 'text',
        'fields not a list' => ['fields' => 'text'],
        'no fields' => ['fields' => []],
        'an object handle' => ['handle' => ['x'], 'fields' => [['handle' => 'value', 'field' => ['type' => 'text']]]],
    ]]]);

    expect(array_keys($meta['new']))->toBe(['own', 'an object handle'])
        ->and($meta['defaults'])->toBe(['own' => ['value' => null], 'an object handle' => ['value' => null]]);
});

it('builds empty meta for a set whose fields cannot be built', function (): void {
    $meta = (new SchemaPropertyReplicatorSets)->metaForSets(['main' => ['sets' => [
        'broken' => ['fields' => [['handle' => 'value', 'field' => ['type' => 'no_such_fieldtype']]]],
        'fine' => ['fields' => [['handle' => 'value', 'field' => ['type' => 'toggle']]]],
    ]]]);

    expect($meta['defaults'])->toBe(['broken' => [], 'fine' => ['value' => false]])
        ->and($meta['new']['broken'])->toBe([])
        ->and(\is_array($meta['new']['fine']) ? array_keys($meta['new']['fine']) : null)->toBe(['value', '_']);
});

it('flattens each set\'s fields for the Vue replicator', function (): void {
    $service = new SchemaPropertyReplicatorSets;

    expect($service->configSetsForVue($service->setsForRangeTypes(['Number'])))->toBe([
        [
            'display' => 'Main',
            'sets' => [
                [
                    'handle' => 'number',
                    ['handle' => 'number'],
                    'display' => 'Number',
                    'icon' => 'fieldtype-float',
                    'fields' => [
                        ['handle' => 'value', 'type' => 'float', 'display' => 'Number'],
                    ],
                ],
                [
                    'handle' => 'antlers',
                    ['handle' => 'antlers'],
                    'display' => 'Antlers',
                    'icon' => 'fieldtype-code',
                    'fields' => [
                        ['handle' => 'value', 'type' => 'code', 'display' => 'Antlers', 'mode' => 'html', 'mode_selectable' => false],
                    ],
                ],
            ],
        ],
    ]);
});

it('reads malformed sets and fields for the Vue replicator as empty', function (): void {
    expect((new SchemaPropertyReplicatorSets)->configSetsForVue(['main' => [
        'display' => 'Values',
        'sets' => [
            'keyed' => ['handle' => 'own', 'fields' => ['not a field', ['handle' => 'bare'], ['field' => ['type' => 'text']], ['handle' => 'h', 'field' => 'text']]],
            'not a set' => 'text',
            'fields not a list' => ['handle' => ['x'], 'fields' => 'text'],
        ],
    ]]))->toBe([
        [
            'display' => 'Values',
            'sets' => [
                [
                    'handle' => 'own',
                    ['handle' => 'own'],
                    'fields' => [
                        ['handle' => ''],
                        ['handle' => 'bare'],
                        ['handle' => '', 'type' => 'text'],
                        ['handle' => 'h'],
                    ],
                ],
                [
                    'handle' => 'not a set',
                    ['handle' => 'not a set'],
                    'fields' => [],
                ],
                [
                    // A set's own handle that is not text gives way to the set's key
                    'handle' => 'fields not a list',
                    ['handle' => 'fields not a list'],
                    'fields' => [],
                ],
            ],
        ],
    ]);
});

it('replaces a set\'s own numbered handle entry with the resolved one, and keeps its other entries', function (): void {
    expect((new SchemaPropertyReplicatorSets)->configSetsForVue(['main' => ['display' => 'Main', 'sets' => [
        'listed' => [['handle' => 'stale'], ['handle' => 'kept', 'extra' => true], 'display' => 'Listed', 'note' => ['handle' => 'named']],
    ]]]))->toBe([
        [
            'display' => 'Main',
            'sets' => [
                [
                    'handle' => 'listed',
                    ['handle' => 'listed'],
                    ['handle' => 'kept', 'extra' => true],
                    'display' => 'Listed',
                    'note' => ['handle' => 'named'],
                    'fields' => [],
                ],
            ],
        ],
    ]);
});

it('labels the Vue group Main when the stored display is not text', function (): void {
    expect((new SchemaPropertyReplicatorSets)->configSetsForVue(['main' => ['display' => ['x'], 'sets' => []]]))
        ->toBe([['display' => 'Main', 'sets' => []]]);
});

it('keys a field with no handle under an empty handle', function (): void {
    expect((new SchemaPropertyReplicatorSets)->metaForSets(['main' => ['sets' => [
        'listed' => ['fields' => [['handle' => null, 'field' => ['type' => 'toggle']]]],
    ]]])['defaults'])->toBe(['listed' => ['' => false]]);
});

it('reads a decimal set or field handle as text', function (): void {
    $service = new SchemaPropertyReplicatorSets;

    expect($service->metaForSets(['main' => ['sets' => [
        'listed' => ['handle' => 1.5, 'fields' => [['handle' => 2.5, 'field' => ['type' => 'toggle']]]],
    ]]])['defaults'])->toBe(['1.5' => ['2.5' => false]])
        ->and($service->configSetsForVue(['main' => ['display' => 5, 'sets' => ['listed' => ['handle' => 1.5]]]]))->toBe([
            [
                'display' => '5',
                'sets' => [
                    ['handle' => '1.5', ['handle' => '1.5'], 'fields' => []],
                ],
            ],
        ]);
});

it('keeps each type and collection pair under its own memo key', function (): void {
    $service = new SchemaPropertyReplicatorSets;
    $handles = static function (string $type, string $collection) use ($service): array {
        config(['schema_object.entries_collection' => $collection]);

        return schemaReplicatorSetHandles($service, [$type]);
    };

    // Each pair joins to the same text as another pair when the separator is dropped or moved
    expect($handles('ab', 'c'))->toBe(['text_ab', 'antlers'])
        ->and($handles('a', 'bc'))->toBe(['text_a', 'antlers'])
        ->and($handles('b', 'ca'))->toBe(['text_b', 'antlers']);
});
