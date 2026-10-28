<?php

declare(strict_types=1);

use App\Fieldtypes\Traits\DynamicDefaultValue;
use Tests\TestCase;

covers(DynamicDefaultValue::class);

uses(TestCase::class);

/**
 * Behavior of the DynamicDefaultValue trait (app/Fieldtypes/Traits/DynamicDefaultValue.php):
 * the toggle and formula config fields for a dynamic default, and the hand-off that
 * hides the static `default` field while the dynamic default is on.
 *
 * @command  php artisan test --compact tests/Feature/Fieldtypes/Traits/DynamicDefaultValueTest.php
 */
it('builds the dynamic default toggle and the formula field shown only while it is on', function (): void {
    expect(DynamicDefaultValue::dynamicDefaultValueConfigField())->toBe([
        'calculation_query' => [
            'display' => 'Enable Dynamic Default Value',
            'instructions' => 'Pre-populate the field with contextual or dynamic data based on the current page, user, or other factors.',
            'type' => 'toggle',
            'default' => false,
        ],
        'calculation_formula' => [
            'display' => 'Dynamic Default Value Text',
            'instructions' => <<<'EOT'
                Enter the text in the manner you would like it to be displayed.

                **Field References:** Use the field handle wrapped in square brackets to reference other field values (e.g., `[order_number]`, `[item_count]`, `[first_name]`).

                **URL Parameters:** Use `[get_param:param_name]` to reference URL parameters from the current page (e.g., `[get_param:editor_email_address]`).
                EOT,
            'type' => 'code',
            'mode' => 'handlebars',
            'mode_selectable' => false,
            'if' => [
                'calculation_query' => 'equals true',
            ],
        ],
    ]);
});

it('hands the parent config to modifyExistingFields with the default field hidden while the dynamic default is on', function (): void {
    $harness = new class
    {
        use DynamicDefaultValue;

        /** @var list<array{0: array<string, mixed>, 1: array<array-key, mixed>}> */
        public array $calls = [];

        /**
         * @param  array<string, mixed>  $modifications
         * @param  array<array-key, mixed>  $config
         * @return array<array-key, mixed>
         */
        protected function modifyExistingFields(array $modifications, array $config): array
        {
            $this->calls[] = [$modifications, $config];

            return ['modified' => $config];
        }

        /**
         * @param  array<array-key, mixed>  $config
         * @return array<array-key, mixed>
         */
        public function apply(array $config): array
        {
            return $this->applyDynamicDefaultValueConfig($config);
        }
    };

    $parent = [['display' => 'Data & Format', 'fields' => ['default' => ['type' => 'text']]]];

    expect($harness->apply($parent))->toBe(['modified' => $parent])
        ->and($harness->calls)->toBe([
            [
                ['default' => ['if' => ['calculation_query' => 'not true']]],
                $parent,
            ],
        ]);
});
