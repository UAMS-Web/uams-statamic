<?php

declare(strict_types=1);

use App\Fieldtypes\Trigger;
use Tests\TestCase;

/**
 * @covers \App\Fieldtypes\Trigger
 *
 * @command  php artisan test --compact tests/Unit/Fieldtypes/TriggerFieldtypeTest.php
 */
uses(TestCase::class);

it('stores no data — process and preProcess always return null', function (mixed $input): void {
    // The trigger is a control button, not a value: whatever it is handed, it
    // persists nothing, so a stray value can never leak into saved content.
    $trigger = new Trigger;

    expect($trigger->process($input))->toBeNull()
        ->and($trigger->preProcess($input))->toBeNull();
})->with([
    'string' => ['some value'],
    'array' => [['a', 'b']],
    'null' => [null],
    'int' => [42],
    'bool true' => [true],
]);

it('exposes the workflow icon', function (): void {
    expect((new Trigger)->icon())->toBe('workflow');
});

it('is a non-selectable control that is never localized, validated or defaulted', function (): void {
    $trigger = new Trigger;

    expect($trigger->component())->toBe('trigger')
        ->and($trigger->categories())->toBe(['controls', 'special'])
        ->and($trigger->selectable())->toBeFalse()
        ->and($trigger->selectableInForms())->toBeFalse()
        ->and($trigger->localizable())->toBeFalse()
        ->and($trigger->validatable())->toBeFalse()
        ->and($trigger->defaultable())->toBeFalse();
});

it('configures only the button label and the related field', function (): void {
    $items = (new ReflectionMethod(Trigger::class, 'configFieldItems'))->invoke(new Trigger);

    expect($items)->toBe([
        [
            'display' => 'Appearance',
            'fields' => [
                'input_label' => [
                    'display' => 'Input Label',
                    'instructions' => 'Set the label for the button.',
                    'type' => 'text',
                    'default' => 'Trigger Action',
                ],
                'related_field' => [
                    'display' => 'Related Field',
                    'instructions' => 'Enter the handle of the field that determines whether this trigger is enabled. The trigger will be enabled if this field has a value.',
                    'type' => 'text',
                    'default' => '',
                ],
            ],
        ],
    ]);
});
