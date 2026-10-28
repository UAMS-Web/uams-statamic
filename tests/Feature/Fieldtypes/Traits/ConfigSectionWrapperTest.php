<?php

declare(strict_types=1);

use App\Fieldtypes\Traits\ConfigSectionWrapper;
use Tests\Support\Doubles\ConfigSectionWrapperHarness;
use Tests\Support\Doubles\ConfigSectionWrapperParent;
use Tests\TestCase;

covers(ConfigSectionWrapper::class);

uses(TestCase::class);

/**
 * Behavior of the ConfigSectionWrapper trait (app/Fieldtypes/Traits/ConfigSectionWrapper.php):
 * wrapping a parent's flat config fields in a first section plus further
 * sections, adding fields to named sections before, after or in place of their
 * fields, and merging modifications into existing fields in a flat or a
 * sectioned config. The parent config comes from ConfigSectionWrapperParent,
 * flat unless its $sectioned flag is set.
 *
 * @command  php artisan test --compact tests/Feature/Fieldtypes/Traits/ConfigSectionWrapperTest.php
 */
beforeEach(function (): void {
    ConfigSectionWrapperParent::$sectioned = false;
});

afterEach(function (): void {
    ConfigSectionWrapperParent::$sectioned = false;
});

/**
 * Two sections, the second without a `fields` key.
 *
 * @return list<array<string, mixed>>
 */
function configSectionWrapperSections(): array
{
    return [
        ['display' => 'Appearance', 'fields' => ['placeholder' => ['type' => 'text']]],
        ['display' => 'Notes'],
    ];
}

it('returns no sections when there is no first section and none to add', function (): void {
    expect((new ConfigSectionWrapperHarness)->wrapConfigFieldsInSections())->toBe([]);
});

it('wraps the parent fields in a first section with extra fields after them by default', function (): void {
    expect((new ConfigSectionWrapperHarness)->wrapConfigFieldsInSections('Input', ['mask' => ['type' => 'toggle']]))->toBe([
        [
            'display' => 'Input',
            'fields' => [
                'placeholder' => ['type' => 'text'],
                'default' => ['type' => 'text'],
                'mask' => ['type' => 'toggle'],
            ],
        ],
    ]);
});

it('puts the extra fields first for any position other than after', function (string $position): void {
    $result = (new ConfigSectionWrapperHarness)->wrapConfigFieldsInSections('Input', ['mask' => ['type' => 'toggle']], $position);

    expect($result)->toBe([
        [
            'display' => 'Input',
            'fields' => [
                'mask' => ['type' => 'toggle'],
                'placeholder' => ['type' => 'text'],
                'default' => ['type' => 'text'],
            ],
        ],
    ]);
})->with(['before', 'replace']);

it('wraps the given parent fields instead of the parent config', function (): void {
    expect((new ConfigSectionWrapperHarness)->wrapConfigFieldsInSections('Input', null, null, null, ['only' => ['type' => 'text']]))->toBe([
        ['display' => 'Input', 'fields' => ['only' => ['type' => 'text']]],
    ]);
});

it('appends each further section that has a display and fields, skipping the rest', function (): void {
    $sections = [
        ['display' => 'Rules', 'fields' => ['required' => ['type' => 'toggle']]],
        ['display' => 'No fields'],
        ['fields' => ['orphan' => ['type' => 'text']]],
        ['display' => 'Advanced', 'fields' => []],
    ];

    expect((new ConfigSectionWrapperHarness)->wrapConfigFieldsInSections('Input', null, null, $sections))->toBe([
        ['display' => 'Input', 'fields' => ['placeholder' => ['type' => 'text'], 'default' => ['type' => 'text']]],
        ['display' => 'Rules', 'fields' => ['required' => ['type' => 'toggle']]],
        ['display' => 'Advanced', 'fields' => []],
    ]);
});

it('adds fields to a named section after, before or in place of its fields', function (string $position, array $fields): void {
    $result = (new ConfigSectionWrapperHarness)->addFieldsToExistingSections(
        ['Appearance' => ['label' => ['type' => 'text']]],
        configSectionWrapperSections(),
        $position
    );

    expect($result)->toBe([
        ['display' => 'Appearance', 'fields' => $fields],
        ['display' => 'Notes'],
    ]);
})->with([
    'after' => ['after', ['placeholder' => ['type' => 'text'], 'label' => ['type' => 'text']]],
    'before' => ['before', ['label' => ['type' => 'text'], 'placeholder' => ['type' => 'text']]],
    'replace' => ['replace', ['label' => ['type' => 'text']]],
    'unknown position falls back to after' => ['sideways', ['placeholder' => ['type' => 'text'], 'label' => ['type' => 'text']]],
]);

it('adds fields after by default and leaves sections without fields to add, or without fields, alone', function (): void {
    $result = (new ConfigSectionWrapperHarness)->addFieldsToExistingSections(
        ['Notes' => ['note' => ['type' => 'text']], 'Missing' => ['x' => []], 'Appearance' => ['label' => ['type' => 'text']]],
        configSectionWrapperSections()
    );

    expect($result)->toBe([
        ['display' => 'Appearance', 'fields' => ['placeholder' => ['type' => 'text'], 'label' => ['type' => 'text']]],
        ['display' => 'Notes'],
    ]);
});

it('adds fields to the parent config when none is given', function (): void {
    ConfigSectionWrapperParent::$sectioned = true;

    $result = (new ConfigSectionWrapperHarness)->addFieldsToExistingSections(['Rules' => ['min' => ['type' => 'integer']]]);

    expect($result)->toBe([
        ['display' => 'Appearance', 'fields' => ['placeholder' => ['type' => 'text']]],
        ['display' => 'Rules', 'fields' => ['required' => ['type' => 'toggle'], 'min' => ['type' => 'integer']]],
    ]);
});

it('adds fields before and after a section\'s fields in one pass', function (): void {
    $result = (new ConfigSectionWrapperHarness)->addFieldsToExistingSectionsWithMultiplePositions(
        ['Appearance' => ['prefix' => ['type' => 'text']], 'Notes' => ['x' => []]],
        ['Appearance' => ['suffix' => ['type' => 'text']]],
        configSectionWrapperSections()
    );

    expect($result)->toBe([
        [
            'display' => 'Appearance',
            'fields' => [
                'prefix' => ['type' => 'text'],
                'placeholder' => ['type' => 'text'],
                'suffix' => ['type' => 'text'],
            ],
        ],
        ['display' => 'Notes'],
    ]);
});

it('adds before-only or after-only fields and uses the parent config when none is given', function (): void {
    ConfigSectionWrapperParent::$sectioned = true;

    $result = (new ConfigSectionWrapperHarness)->addFieldsToExistingSectionsWithMultiplePositions(
        ['Rules' => ['first' => []]],
        ['Appearance' => ['last' => []]]
    );

    expect($result)->toBe([
        ['display' => 'Appearance', 'fields' => ['placeholder' => ['type' => 'text'], 'last' => []]],
        ['display' => 'Rules', 'fields' => ['first' => [], 'required' => ['type' => 'toggle']]],
    ]);
});

it('merges modifications into existing fields of a flat config and ignores unknown fields', function (): void {
    $result = (new ConfigSectionWrapperHarness)->modifyExistingFields([
        'default' => ['if' => ['x' => 'not true']],
        'missing' => ['type' => 'text'],
    ]);

    expect($result)->toBe([
        'placeholder' => ['type' => 'text'],
        'default' => ['type' => 'text', 'if' => ['x' => 'not true']],
    ]);
});

it('merges modifications into the fields of every section of a sectioned config', function (): void {
    $config = [
        ['display' => 'Appearance', 'fields' => ['placeholder' => ['type' => 'text']]],
        ['display' => 'Broken', 'fields' => 'not a list'],
        ['display' => 'Other', 'fields' => ['placeholder' => ['type' => 'textarea']]],
    ];

    expect((new ConfigSectionWrapperHarness)->modifyExistingFields(['placeholder' => ['width' => 50]], $config))->toBe([
        ['display' => 'Appearance', 'fields' => ['placeholder' => ['type' => 'text', 'width' => 50]]],
        ['display' => 'Broken', 'fields' => 'not a list'],
        ['display' => 'Other', 'fields' => ['placeholder' => ['type' => 'textarea', 'width' => 50]]],
    ]);
});

it('reads the parent config as sectioned only when its first item has a display and fields', function (): void {
    ConfigSectionWrapperParent::$sectioned = true;
    $harness = new ConfigSectionWrapperHarness;

    expect($harness->modifyExistingFields(['required' => ['default' => true]]))->toBe([
        ['display' => 'Appearance', 'fields' => ['placeholder' => ['type' => 'text']]],
        ['display' => 'Rules', 'fields' => ['required' => ['type' => 'toggle', 'default' => true]]],
    ])
        // A first item with a display but no fields, or fields but no display, is a field, not a section.
        ->and($harness->modifyExistingFields(['note' => ['type' => 'text']], ['note' => ['display' => 'x']]))->toBe([
            'note' => ['display' => 'x', 'type' => 'text'],
        ])
        ->and($harness->modifyExistingFields(['note' => ['type' => 'text']], ['note' => ['fields' => []]]))->toBe([
            'note' => ['fields' => [], 'type' => 'text'],
        ])
        ->and($harness->modifyExistingFields(['a' => ['b' => 1]], []))->toBe([])
        ->and($harness->modifyExistingFields(['a' => ['b' => 1]], ['a' => ['c' => 2]]))->toBe(['a' => ['c' => 2, 'b' => 1]]);
});
