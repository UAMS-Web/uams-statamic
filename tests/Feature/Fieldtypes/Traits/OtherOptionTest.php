<?php

declare(strict_types=1);

use App\Fieldtypes\Traits\OtherOption;
use Tests\TestCase;

covers(OtherOption::class);

uses(TestCase::class);

/**
 * Behavior of the OtherOption trait (app/Fieldtypes/Traits/OtherOption.php): the
 * config fields that enable an "Other" choice, with its label and placeholder
 * shown only while the option is enabled.
 *
 * @command  php artisan test --compact tests/Feature/Fieldtypes/Traits/OtherOptionTest.php
 */
it('builds the enable toggle and the conditional label and placeholder fields', function (): void {
    $harness = new class
    {
        use OtherOption;

        /** @return array<string, array<string, mixed>> */
        public function fields(): array
        {
            return $this->otherOptionConfigField();
        }
    };

    expect($harness->fields())->toBe([
        'enable_other' => [
            'display' => __('Enable Other Option'),
            'instructions' => __('Allow users to select "Other" and enter a custom value.'),
            'type' => 'toggle',
            'default' => false,
        ],
        'other_label' => [
            'display' => __('Label for Other Option'),
            'instructions' => __('The label for the "Other" option.'),
            'type' => 'text',
            'default' => 'Other',
            'if' => [
                'enable_other' => 'equals true',
            ],
        ],
        'other_placeholder' => [
            'display' => __('Placeholder for Other Input'),
            'instructions' => __('Placeholder text for the "Other" custom value input.'),
            'type' => 'text',
            'default' => 'Enter your custom value',
            'if' => [
                'enable_other' => 'equals true',
            ],
        ],
    ]);
});
