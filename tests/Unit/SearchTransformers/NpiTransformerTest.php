<?php

declare(strict_types=1);

use App\SearchTransformers\NpiTransformer;

covers(NpiTransformer::class);

/**
 * Behavior of the `npi` search transformer
 * (app/SearchTransformers/NpiTransformer.php): flatten the `item` handle from
 * each `clinical_locations` `npi` replicator row into one searchable string of
 * NPI values, or null when none are present. The row-walking engine is covered
 * by ReplicatorSetFlattenerTest.
 *
 * @command  php artisan test --compact tests/Unit/SearchTransformers/NpiTransformerTest.php
 */
it('flattens npi replicator item rows into searchable text, or null', function (mixed $value, ?string $expected) {
    expect((new NpiTransformer)->handle($value))->toBe($expected);
})->with([
    'flattens the item handle across rows' => [
        [
            ['type' => 'new_set', 'item' => '1234567890'],
            ['type' => 'new_set', 'item' => '9876543210'],
        ],
        '1234567890 9876543210',
    ],
    'ignores non-item handles on the row' => [
        [['type' => 'new_set', 'item' => '1234567890', 'label' => 'ignored']],
        '1234567890',
    ],
    'an empty list yields null' => [[], null],
    'rows without an item value yield null' => [[['type' => 'new_set', 'label' => 'x']], null],
    'a non-array value yields null' => ['1234567890', null],
]);
