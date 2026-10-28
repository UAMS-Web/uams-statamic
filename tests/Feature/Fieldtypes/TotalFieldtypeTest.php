<?php

declare(strict_types=1);

use App\Fieldtypes\Total;
use Statamic\Fields\Field;
use Tests\TestCase;

uses(TestCase::class);

/**
 * The Total fieldtype (UAMS-Web/uams-statamic-archive#488) is a read-only order-total display. Its server side
 * only normalizes the client-computed (advisory) submitted total and exposes the
 * configured prefix to its component/template.
 *
 * @command php artisan test --compact tests/Feature/Fieldtypes/TotalFieldtypeTest.php
 */

/**
 * A Total fieldtype bound to a field with the given config.
 *
 * @param  array<string, mixed>  $config
 */
function makeTotal(array $config = []): Total
{
    $fieldtype = new Total;
    $fieldtype->setField(new Field('order_total', array_merge(['type' => 'total'], $config)));

    return $fieldtype;
}

it('normalizes a numeric submitted total to a 2-decimal string', function () {
    expect(makeTotal()->process('125.5'))->toBe('125.50');
    expect(makeTotal()->process(0))->toBe('0.00');
    expect(makeTotal()->process('42'))->toBe('42.00');
});

it('keeps a negative net total (credits are permitted)', function () {
    expect(makeTotal()->process('-25'))->toBe('-25.00');
});

it('stores null for an empty or non-numeric submitted total', function () {
    expect(makeTotal()->process(''))->toBeNull();
    expect(makeTotal()->process(null))->toBeNull();
    expect(makeTotal()->process('abc'))->toBeNull();
});

it('exposes the configured amount prefix, defaulting to Total', function () {
    expect(makeTotal(['prefix' => 'Grand Total'])->preload()['prefix'])->toBe('Grand Total');
    expect(makeTotal()->preload()['prefix'])->toBe('Total');
});

it('uses the money-bag-dollar icon', function () {
    expect(makeTotal()->icon())->toBe('money-bag-dollar');
});

it('exposes the configured amount prefix to the front-end template, defaulting to Total', function () {
    expect(makeTotal(['prefix' => 'Grand Total'])->extraRenderableFieldData())->toBe(['prefix' => 'Grand Total'])
        ->and(makeTotal()->extraRenderableFieldData())->toBe(['prefix' => 'Total']);
});

it('renders an empty prefix when the configured prefix is not scalar', function () {
    expect(makeTotal(['prefix' => ['Grand', 'Total']])->extraRenderableFieldData())->toBe(['prefix' => ''])
        ->and(makeTotal(['prefix' => ['Grand', 'Total']])->preload())->toBe(['prefix' => '']);
});

it('pins its prefix config field exactly', function () {
    expect((new ReflectionMethod(Total::class, 'configFieldItems'))->invoke(makeTotal()))->toBe([
        'prefix' => [
            'display' => 'Amount Prefix',
            'instructions' => 'Text shown before the computed total (e.g. "Order Total").',
            'type' => 'text',
            'default' => 'Total',
        ],
    ]);
});

it('casts a numeric prefix to a string', function () {
    expect(makeTotal(['prefix' => 5])->preload())->toBe(['prefix' => '5'])
        ->and(makeTotal(['prefix' => 5])->extraRenderableFieldData())->toBe(['prefix' => '5']);
});

it('stores a total of 1000 or more with no thousands separator', function () {
    expect(makeTotal()->process('1234.5'))->toBe('1234.50');
});
