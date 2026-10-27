<?php

declare(strict_types=1);

use App\Support\ReplicatorSetFlattener;

/**
 * Behavior of ReplicatorSetFlattener (app/Support/ReplicatorSetFlattener.php): the
 * shared engine that flattens replicator set rows into one searchable string by
 * collecting the configured field handles (in handle order, then row order),
 * trimming and skipping empty/non-array rows, with a configurable separator. Used
 * by the search transformers.
 *
 * Mutation: every killable mutant is dead (score ~88%). The residual survivors are
 * equivalents — the value-validity and empty-handles guards (lines 33/36/41) are
 * redundant with the final empty-parts guard (an invalid/empty input collects no
 * parts and returns null either way; a non-array value only adds a foreach warning).
 * Left clean rather than annotated with `@pest-mutate-ignore`.
 *
 * @command  php artisan test --compact tests/Unit/Support/ReplicatorSetFlattenerTest.php
 */
it('flattens configured handles from replicator rows, or null', function (mixed $value, array $handles, string $separator, ?string $expected) {
    // Coerce the dataset handles to the list<string> the helper documents.
    $fieldHandles = array_values(array_map(
        fn (mixed $handle): string => is_string($handle) ? $handle : '',
        $handles
    ));

    expect(ReplicatorSetFlattener::flatten($value, $fieldHandles, $separator))->toBe($expected);
})->with([
    'null value is null' => [null, ['url'], ' ', null],
    'empty array is null' => [[], ['url'], ' ', null],
    'non-array value is null' => ['https://example.com', ['url'], ' ', null],
    'empty handle list is null' => [[['url' => 'https://a.test/']], [], ' ', null],
    'joins each set, trimming values' => [
        [
            ['type' => 'new_set', 'url' => '  https://en.wikipedia.org/wiki/Foo  '],
            ['type' => 'new_set', 'url' => 'https://www.wikidata.org/wiki/Q1'],
        ],
        ['url'],
        ' ',
        'https://en.wikipedia.org/wiki/Foo https://www.wikidata.org/wiki/Q1',
    ],
    'collects multiple handles per row in handle order' => [
        [
            ['type' => 'new_set', 'text' => 'Alpha', 'url' => 'https://a.test/'],
            ['type' => 'new_set', 'text' => 'Beta', 'url' => 'https://b.test/'],
        ],
        ['text', 'url'],
        ' ',
        'Alpha https://a.test/ Beta https://b.test/',
    ],
    'respects a custom separator' => [
        [['a' => 'one', 'b' => 'two']],
        ['a', 'b'],
        '|',
        'one|two',
    ],
    'skips rows without usable values' => [
        [
            ['type' => 'new_set'],
            ['type' => 'new_set', 'url' => ''],
            ['type' => 'new_set', 'url' => '   '],
            'not-an-array',
            ['type' => 'new_set', 'url' => 'https://keep.me/'],
        ],
        ['url'],
        ' ',
        'https://keep.me/',
    ],
    // A missing earlier handle must not stop later handles on the same row (continue, not break).
    'a missing handle continues to the next handle' => [
        [['url' => 'https://x.test/']],
        ['text', 'url'],
        ' ',
        'https://x.test/',
    ],
    // An empty-after-trim earlier handle must not stop later handles on the same row.
    'an empty handle continues to the next handle' => [
        [['text' => '   ', 'url' => 'https://y.test/']],
        ['text', 'url'],
        ' ',
        'https://y.test/',
    ],
]);
