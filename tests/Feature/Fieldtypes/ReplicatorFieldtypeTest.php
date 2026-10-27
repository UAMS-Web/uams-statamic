<?php

declare(strict_types=1);

use App\Fieldtypes\Replicator;
use Illuminate\Support\Facades\Validator;
use Statamic\Fields\Field;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Unit tests for the custom Replicator fieldtype (App\Fieldtypes\Replicator).
 *
 * Verifies min_sets config in Boundaries & Limits and validation that enforces
 * the minimum number of sets.
 *
 * Mutation: 100% on App\Fieldtypes\Replicator (17 mutants, --covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Fieldtypes/ReplicatorFieldtypeTest.php
 */
it('config includes min sets in boundaries and limits', function () {
    $fieldtype = new Replicator;

    // Invoke configFieldItems via reflection (protected method).
    $reflection = new ReflectionClass($fieldtype);
    $method = $reflection->getMethod('configFieldItems');
    $config = $method->invoke($fieldtype);

    // Find the Boundaries & Limits section in the config.
    $boundariesSection = null;
    foreach ((is_array($config) ? $config : []) as $section) {
        if (
            is_array($section)
            && isset($section['display'])
            && $section['display'] === __('Boundaries & Limits')
        ) {
            $boundariesSection = $section;
            break;
        }
    }

    expect($boundariesSection)->not->toBeNull('Boundaries & Limits section should exist');
    $boundariesFields = data_get($boundariesSection, 'fields');
    // toHaveKey()'s second argument is an expected value, not a message, so pass
    // only the key.
    expect($boundariesFields)->toHaveKey('min_sets');
    expect(data_get($boundariesSection, 'fields.min_sets.type'))->toBe('integer');
    expect($boundariesFields)->toHaveKey('max_sets');
});

it('rules fail when fewer sets than min sets', function () {
    // Build a replicator field with min_sets set to 2 and required.
    $field = new Field(
        'blocks',
        [
            'type' => 'replicator',
            'validate' => ['required'],
            'min_sets' => 2,
            'sets' => [
                'main' => [
                    'sets' => [
                        'text' => [
                            'display' => 'Text',
                            'fields' => [],
                        ],
                    ],
                ],
            ],
        ]
    );

    /** @var Replicator $fieldtype */
    $fieldtype = (new Replicator)->setField($field);
    $rules = $fieldtype->rules();

    expect($rules)->not->toBeEmpty('rules should not be empty');

    // Validate a value with only one set (below min_sets of 2).
    $validator = Validator::make(
        ['blocks' => [['type' => 'text', '_id' => '1', 'enabled' => true]]],
        ['blocks' => $rules]
    );

    expect($validator->fails())->toBeTrue('Validation should fail when fewer sets than min_sets');
    expect($validator->errors()->first('blocks'))->toContain('2');
});

it('rules pass when at least min sets', function () {
    // Build a replicator field with min_sets set to 2 and required.
    $field = new Field(
        'blocks',
        [
            'type' => 'replicator',
            'validate' => ['required'],
            'min_sets' => 2,
            'sets' => [
                'main' => [
                    'sets' => [
                        'text' => [
                            'display' => 'Text',
                            'fields' => [],
                        ],
                    ],
                ],
            ],
        ]
    );

    /** @var Replicator $fieldtype */
    $fieldtype = (new Replicator)->setField($field);
    $rules = $fieldtype->rules();

    // Validate a value with two sets (meets min_sets of 2).
    $validator = Validator::make(
        [
            'blocks' => [
                ['type' => 'text', '_id' => '1', 'enabled' => true],
                ['type' => 'text', '_id' => '2', 'enabled' => true],
            ],
        ],
        ['blocks' => $rules]
    );

    expect($validator->fails())->toBeFalse('Validation should pass when at least min_sets sets present');
});

it('rules do not include min sets when not configured', function () {
    // Build a replicator field without min_sets.
    $field = new Field(
        'blocks',
        [
            'type' => 'replicator',
            'sets' => [
                'main' => [
                    'sets' => [
                        'text' => [
                            'display' => 'Text',
                            'fields' => [],
                        ],
                    ],
                ],
            ],
        ]
    );

    /** @var Replicator $fieldtype */
    $fieldtype = (new Replicator)->setField($field);
    $rules = $fieldtype->rules();

    // Validate an empty value; no min_sets rule should apply.
    $validator = Validator::make(
        ['blocks' => []],
        ['blocks' => $rules]
    );

    expect($validator->fails())->toBeFalse('Validation with empty value should not fail on min_sets when unset');
});

it('does not enforce min sets when field not required', function () {
    // Build a replicator field with min_sets but no required rule.
    $field = new Field(
        'blocks',
        [
            'type' => 'replicator',
            'min_sets' => 2,
            'sets' => [
                'main' => [
                    'sets' => [
                        'text' => [
                            'display' => 'Text',
                            'fields' => [],
                        ],
                    ],
                ],
            ],
        ]
    );

    /** @var Replicator $fieldtype */
    $fieldtype = (new Replicator)->setField($field);
    $rules = $fieldtype->rules();

    // Validate with only one set; field is not required so min_sets should not apply.
    $validator = Validator::make(
        ['blocks' => [['type' => 'text', '_id' => '1', 'enabled' => true]]],
        ['blocks' => $rules]
    );

    expect($validator->fails())->toBeFalse('Validation should pass when field is not required even with fewer than min_sets');
});

/**
 * Build a configured Replicator fieldtype (one `contact` set type) with the given bound value.
 *
 * @param  array<int, array<string, mixed>>  $value
 */
function replicatorFieldtypeWithValue(array $value): Replicator
{
    $config = [
        'type' => 'replicator',
        'sets' => [
            'main' => [
                'sets' => [
                    'contact' => ['display' => 'Contact', 'fields' => [
                        ['handle' => 'name', 'field' => ['type' => 'text', 'display' => 'Name']],
                    ]],
                ],
            ],
        ],
    ];

    $field = new Field('referrals', $config);
    $field->setValue($value);

    /** @var Replicator $fieldtype */
    $fieldtype = (new Replicator)->setField($field);

    return $fieldtype;
}

/**
 * Render the field's renderable data with a minimal child-render callback.
 *
 * @return array<string, mixed>
 */
function replicatorRenderable(Replicator $fieldtype): array
{
    return $fieldtype->preProcessTagRenderable([], fn (Field $child): array => ['name' => $child->handle()]);
}

it('coerces each row enabled flag to a boolean when rebuilding rows (#675)', function (mixed $enabled, bool $expected) {
    $data = replicatorRenderable(replicatorFieldtypeWithValue([
        ['type' => 'contact', 'enabled' => $enabled, 'name' => 'Ada'],
    ]));

    /** @var array<int, array<string, mixed>> $rows */
    $rows = $data['replicator_rows'];
    expect($rows[0]['enabled'])->toBe($expected);
})->with([
    'string 0 (disabled) stays disabled' => ['0', false],
    'string 1 (enabled)' => ['1', true],
    'boolean false' => [false, false],
    'boolean true' => [true, true],
    'string false' => ['false', false],
]);

it('defaults a row with no enabled key to enabled', function () {
    $data = replicatorRenderable(replicatorFieldtypeWithValue([
        ['type' => 'contact', 'name' => 'Ada'],
    ]));

    /** @var array<int, array<string, mixed>> $rows */
    $rows = $data['replicator_rows'];
    expect($rows[0]['enabled'])->toBeTrue();
});

it('renders each configured set type as a template and drops unknown-type rows', function () {
    $data = replicatorRenderable(replicatorFieldtypeWithValue([
        ['type' => 'contact', 'enabled' => '1', 'name' => 'Ada'],
        ['type' => 'not_a_real_set', 'enabled' => '1'],
    ]));

    // The unknown-type row is skipped; the valid one survives at index 0.
    expect($data['replicator_rows'])->toHaveCount(1);
    /** @var array<int, array<string, mixed>> $rows */
    $rows = $data['replicator_rows'];
    expect($rows[0]['type'])->toBe('contact');

    // One template per configured set type, keyed on the placeholder index.
    expect($data['replicator_set_templates'])->toHaveCount(1);
    /** @var array<int, array<string, mixed>> $templates */
    $templates = $data['replicator_set_templates'];
    expect($templates[0]['type'])->toBe('contact');
    expect($data['replicator_index_token'])->toBe(Replicator::INDEX_TOKEN);
});

it('seeds a multi-valued choice subfield as an empty list in each set scope (#2687)', function () {
    $field = new Field('people', [
        'type' => 'replicator',
        'sets' => ['main' => ['sets' => ['person' => ['display' => 'Person', 'fields' => [
            ['handle' => 'name', 'field' => ['type' => 'text']],
            ['handle' => 'pets', 'field' => ['type' => 'checkboxes', 'options' => ['cat' => 'Cat']]],
            ['handle' => 'tags', 'field' => ['type' => 'select', 'multiple' => true, 'options' => ['a' => 'A']]],
            ['handle' => 'size', 'field' => ['type' => 'select', 'options' => ['s' => 'S']]],
        ]]]]],
    ]);
    $field->setValue([['type' => 'person', 'enabled' => '1', 'pets' => ['cat']]]);

    /** @var Replicator $fieldtype */
    $fieldtype = (new Replicator)->setField($field);
    $data = replicatorRenderable($fieldtype);

    /** @var array<int, array{set_values: string}> $templates */
    $templates = $data['replicator_set_templates'];
    /** @var array<int, array{set_values: string}> $rows */
    $rows = $data['replicator_rows'];

    // `x-model` treats a checkbox bound to a non-array as one boolean, so a null seed would make
    // one click check every box. A single select and a text field keep their null.
    expect(json_decode(html_entity_decode($templates[0]['set_values']), true))
        ->toBe(['name' => null, 'pets' => [], 'tags' => [], 'size' => null]);

    // A value the row already carries is kept, and only an absent one takes the empty list.
    expect(json_decode(html_entity_decode($rows[0]['set_values']), true))
        ->toBe(['name' => null, 'pets' => ['cat'], 'tags' => [], 'size' => null]);
});
