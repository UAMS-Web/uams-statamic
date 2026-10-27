<?php

declare(strict_types=1);

use App\SearchTransformers\SameAsTransformer;

covers(SameAsTransformer::class);

/**
 * Behavior of the `same_as` search transformer
 * (app/SearchTransformers/SameAsTransformer.php): flatten the `url` handle from each
 * replicator row into one searchable string, or null when none are present. The
 * row-walking engine is covered by ReplicatorSetFlattenerTest.
 *
 * @command  php artisan test --compact tests/Unit/SearchTransformers/SameAsTransformerTest.php
 */
it('flattens url rows into searchable text, or null', function (mixed $value, ?string $expected) {
    expect((new SameAsTransformer)->handle($value))->toBe($expected);
})->with([
    'flattens the url handle, ignoring others' => [
        [
            ['type' => 'new_set', 'url' => 'https://en.wikipedia.org/wiki/Aspirin', 'uri' => 'ignored'],
            ['type' => 'new_set', 'url' => 'https://www.wikidata.org/wiki/Q1'],
        ],
        'https://en.wikipedia.org/wiki/Aspirin https://www.wikidata.org/wiki/Q1',
    ],
    'an empty list yields null' => [[], null],
    'rows without a url value yield null' => [[['type' => 'new_set', 'uri' => 'x']], null],
]);
