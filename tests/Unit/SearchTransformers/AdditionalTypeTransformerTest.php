<?php

declare(strict_types=1);

use App\SearchTransformers\AdditionalTypeTransformer;

covers(AdditionalTypeTransformer::class);

/**
 * Behavior of the `additional_type` search transformer
 * (app/SearchTransformers/AdditionalTypeTransformer.php): flatten the `uri` handle
 * from each replicator row into one searchable string, or null when none are
 * present. The row-walking engine is covered by ReplicatorSetFlattenerTest.
 *
 * @command  php artisan test --compact tests/Unit/SearchTransformers/AdditionalTypeTransformerTest.php
 */
it('flattens uri rows into searchable text, or null', function (mixed $value, ?string $expected) {
    expect((new AdditionalTypeTransformer)->handle($value))->toBe($expected);
})->with([
    'flattens the uri handle, ignoring others' => [
        [
            ['type' => 'new_set', 'uri' => 'http://id.loc.gov/authorities/names/n1', 'url' => 'ignored'],
            ['type' => 'new_set', 'uri' => 'https://www.wikidata.org/wiki/Q1'],
        ],
        'http://id.loc.gov/authorities/names/n1 https://www.wikidata.org/wiki/Q1',
    ],
    'an empty list yields null' => [[], null],
    'rows without a uri value yield null' => [[['type' => 'new_set', 'url' => 'x']], null],
]);
