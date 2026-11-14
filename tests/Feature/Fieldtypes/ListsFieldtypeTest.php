<?php

declare(strict_types=1);

use App\Fieldtypes\Lists;
use Statamic\Fields\Field;
use Tests\TestCase;

uses(TestCase::class);

covers(Lists::class);

/**
 * The validation rules the `Lists` fieldtype derives from its `min_items` and
 * `max_items` config: always `array`, so Laravel words a count error in items
 * rather than characters, then `between`, `min` or `max` for whichever bounds are
 * positive integers (UAMS-Web/uams-statamic#312).
 *
 * @command  php artisan test --compact tests/Feature/Fieldtypes/ListsFieldtypeTest.php
 */

/**
 * The rules a list field with the given config produces.
 *
 * @param  array<array-key, mixed>  $config
 * @return array<string>
 */
function listsFieldtypeRules(array $config): array
{
    $fieldtype = new Lists;
    $fieldtype->setField(new Field('items', ['type' => 'list', ...$config]));

    return $fieldtype->rules();
}

it("registers the application class under the list handle, keeping Statamic's icon", function (): void {
    expect(app('statamic.fieldtypes')->get('list'))->toBe(Lists::class)
        ->and((new Lists)->icon())->toBe('fieldtype-list');
});

it('derives :dataset', function (array $config, array $rules): void {
    expect(listsFieldtypeRules($config))->toBe($rules);
})->with([
    'only the array rule with no bounds' => [[], ['array']],
    'between when both bounds are set' => [['min_items' => 2, 'max_items' => 5], ['array', 'between:2,5']],
    'min when only the minimum is set' => [['min_items' => 2], ['array', 'min:2']],
    'max when only the maximum is set' => [['max_items' => 5], ['array', 'max:5']],
    'integer bounds from numeric strings' => [['min_items' => '3', 'max_items' => '4'], ['array', 'between:3,4']],
    'min of one, the smallest positive bound' => [['min_items' => 1], ['array', 'min:1']],
    'max of one, the smallest positive bound' => [['max_items' => 1], ['array', 'max:1']],
    'no bound from a zero minimum' => [['min_items' => 0, 'max_items' => 5], ['array', 'max:5']],
    'no bound from a zero maximum' => [['min_items' => 2, 'max_items' => 0], ['array', 'min:2']],
    'no bound from a negative minimum' => [['min_items' => -1], ['array']],
    'no bound from a non-numeric minimum' => [['min_items' => 'two', 'max_items' => 5], ['array', 'max:5']],
    'no bound from a non-numeric maximum' => [['min_items' => 2, 'max_items' => 'five'], ['array', 'min:2']],
    'no bound from a null maximum' => [['min_items' => 2, 'max_items' => null], ['array', 'min:2']],
]);
