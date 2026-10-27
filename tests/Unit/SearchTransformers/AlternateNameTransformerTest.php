<?php

declare(strict_types=1);

use App\SearchTransformers\AlternateNameTransformer;

covers(AlternateNameTransformer::class);

/**
 * Behavior of the `alternate_name` search transformer
 * (app/SearchTransformers/AlternateNameTransformer.php): flatten the `text` handle
 * from each replicator row into one searchable string, or null when none are
 * present. The row-walking engine is covered by ReplicatorSetFlattenerTest.
 *
 * @command  php artisan test --compact tests/Unit/SearchTransformers/AlternateNameTransformerTest.php
 */
it('flattens text rows into searchable text, or null', function (mixed $value, ?string $expected) {
    expect((new AlternateNameTransformer)->handle($value))->toBe($expected);
})->with([
    'flattens the text handle, ignoring others' => [
        [
            ['type' => 'new_set', 'text' => 'Tylenol', 'url' => 'ignored'],
            ['type' => 'new_set', 'text' => 'Paracetamol'],
        ],
        'Tylenol Paracetamol',
    ],
    'an empty list yields null' => [[], null],
    'rows without a text value yield null' => [[['type' => 'new_set', 'url' => 'x']], null],
]);
