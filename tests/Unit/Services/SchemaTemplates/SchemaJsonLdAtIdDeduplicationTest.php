<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaJsonLdAtIdDeduplication;

covers(SchemaJsonLdAtIdDeduplication::class);

/**
 * Unit tests for SchemaJsonLdAtIdDeduplication (shared JSON-LD @id deduplication
 * for schema template output).
 *
 * Mutation: every mutant of App\Services\SchemaTemplates\SchemaJsonLdAtIdDeduplication
 * is killed by this file alone, without --covered-only.
 *
 * @command  php artisan test --compact tests/Unit/Services/SchemaTemplates/SchemaJsonLdAtIdDeduplicationTest.php
 */
it('keeps the first ref per canonical @id in appendUniqueAtIdObject', function () {
    $deduper = new SchemaJsonLdAtIdDeduplication;
    $refs = [];
    $seen = [];

    $deduper->appendUniqueAtIdObject(
        $refs,
        $seen,
        ['@id' => 'https://example.test/#a']
    );
    $deduper->appendUniqueAtIdObject(
        $refs,
        $seen,
        ['@id' => 'https://example.test/#a']
    );
    $deduper->appendUniqueAtIdObject(
        $refs,
        $seen,
        ['@id' => 'https://example.test/#b']
    );

    expect($refs)->toHaveCount(2);
    expect($refs)->toBe([
        ['@id' => 'https://example.test/#a'],
        ['@id' => 'https://example.test/#b'],
    ]);
});

it('allows every empty @id and blocks duplicate ids in shouldEmitUniqueAtIdNode', function () {
    $deduper = new SchemaJsonLdAtIdDeduplication;
    $seen = [];

    expect($deduper->shouldEmitUniqueAtIdNode('', $seen))->toBeTrue();
    expect($deduper->shouldEmitUniqueAtIdNode('', $seen))->toBeTrue();
    expect($deduper->shouldEmitUniqueAtIdNode('https://example.test/#x', $seen))->toBeTrue();
    expect($deduper->shouldEmitUniqueAtIdNode('https://example.test/#x', $seen))->toBeFalse();
});

it('collapses maps that share the same @id string in dedupeResolvedPropertyList', function () {
    $deduper = new SchemaJsonLdAtIdDeduplication;
    $items = [
        ['@id' => 'https://example.test/#1', '@type' => 'Thing'],
        ['@id' => 'https://example.test/#1', '@type' => 'Thing', 'name' => 'Second'],
        'plain',
        'plain',
    ];

    $out = $deduper->dedupeResolvedPropertyList($items);

    expect($out)->toHaveCount(2);
    expect($out)->toBe([
        ['@id' => 'https://example.test/#1', '@type' => 'Thing'],
        'plain',
    ]);
});

it('prefers a full node over a compact @id reference in dedupeResolvedPropertyList', function () {
    $deduper = new SchemaJsonLdAtIdDeduplication;
    $items = [
        ['@id' => 'https://example.test/#cred'],
        ['@id' => 'https://example.test/#cred', '@type' => 'Credential', 'name' => 'MD'],
    ];

    $out = $deduper->dedupeResolvedPropertyList($items);

    expect($out)->toHaveCount(1);
    expect(data_get($out, '0'))->toBe([
        '@id' => 'https://example.test/#cred',
        '@type' => 'Credential',
        'name' => 'MD',
    ]);
});

it('does not register bare @id refs and collapses a second full node in dedupeNodeByAtId', function () {
    $deduper = new SchemaJsonLdAtIdDeduplication;
    $emittedNodeIds = [];

    $refOnly = ['@id' => 'https://example.test/#thing'];
    $fullNode = [
        '@id' => 'https://example.test/#thing',
        '@type' => 'Thing',
        'name' => 'Full',
    ];

    // A bare @id reference must not mark the id as emitted.
    $first = $deduper->dedupeNodeByAtId($refOnly, $emittedNodeIds);
    expect($first)->toBe($refOnly);
    expect($emittedNodeIds)->toBe([]);

    // The first full node keeps its body and registers the id.
    $second = $deduper->dedupeNodeByAtId($fullNode, $emittedNodeIds);
    expect(data_get($second, '@type'))->toBe('Thing');
    expect($emittedNodeIds)->toHaveKey('https://example.test/#thing');

    // A second full node with the same id collapses to a compact reference.
    $third = $deduper->dedupeNodeByAtId($fullNode, $emittedNodeIds);
    expect($third)->toBe(['@id' => 'https://example.test/#thing']);
});

it('retains the first pre-registered full node per decode pass in dedupeNodeByAtId', function () {
    $deduper = new SchemaJsonLdAtIdDeduplication;
    $emittedNodeIds = ['https://example.test/#cred' => true];
    $decodePassFirstFullAtIds = [];

    $fullNode = [
        '@id' => 'https://example.test/#cred',
        '@type' => 'EducationalOccupationalCredential',
        'name' => 'MD',
    ];

    $tree = [
        'hasCredential' => [
            $fullNode,
            [
                '@id' => 'https://example.test/#cred',
                '@type' => 'EducationalOccupationalCredential',
                'name' => 'Duplicate',
            ],
        ],
    ];

    $out = $deduper->dedupeNodeByAtId(
        $tree,
        $emittedNodeIds,
        $decodePassFirstFullAtIds
    );

    // The first full body is kept inline once for the pre-registered id.
    expect(data_get($out, 'hasCredential.0.name'))->toBe('MD');

    // The second full object in the same decoded value still collapses.
    expect(data_get($out, 'hasCredential.1'))->toBe(['@id' => 'https://example.test/#cred']);
});

it('does not persist new full-node @id keys to the shared emit map', function () {
    $deduper = new SchemaJsonLdAtIdDeduplication;
    $emittedNodeIds = [];
    $fragment = [
        '@id' => 'https://example.test/#cred',
        '@type' => 'Credential',
        'name' => 'MA',
    ];

    $out = $deduper->dedupeWithSharedEmitMapAndDecodeFirstFullRetention(
        $fragment,
        $emittedNodeIds
    );

    expect(data_get($out, 'name'))->toBe('MA');
    expect($emittedNodeIds)->toBe([]);
});

it('drops stale ids not present under roots in syncMaterializedFullNodeAtIdsToUnionOfTreeRoots', function () {
    $deduper = new SchemaJsonLdAtIdDeduplication;
    $materialized = [
        'https://example.test/#gone' => true,
        'https://example.test/#kept' => true,
    ];
    $roots = [
        [
            '@id' => 'https://example.test/#kept',
            '@type' => 'Thing',
            'name' => 'Kept',
        ],
    ];

    $deduper->syncMaterializedFullNodeAtIdsToUnionOfTreeRoots(
        $roots,
        $materialized
    );

    expect($materialized)->toBe(['https://example.test/#kept' => true]);
});

it('skips a reference object with a missing or empty @id in appendUniqueAtIdObject', function () {
    $deduper = new SchemaJsonLdAtIdDeduplication;
    $refs = [];
    $seen = [];

    $deduper->appendUniqueAtIdObject($refs, $seen, ['name' => 'No id']);
    $deduper->appendUniqueAtIdObject($refs, $seen, ['@id' => '']);
    $deduper->appendUniqueAtIdObject($refs, $seen, ['@id' => 'https://example.test/#a']);

    expect($refs)->toBe([['@id' => 'https://example.test/#a']])
        ->and($seen)->toBe(['https://example.test/#a' => true]);
});

it('marks an emitted @id as true in shouldEmitUniqueAtIdNode', function () {
    $deduper = new SchemaJsonLdAtIdDeduplication;
    $seen = [];

    $deduper->shouldEmitUniqueAtIdNode('', $seen);
    $deduper->shouldEmitUniqueAtIdNode('https://example.test/#x', $seen);

    expect($seen)->toBe(['https://example.test/#x' => true]);
});

it('replaces an existing item only when it is a compact ref and the new item is full', function (array $items, array $expected) {
    $deduper = new SchemaJsonLdAtIdDeduplication;

    /** @var array<int, mixed> $items */
    expect($deduper->dedupeResolvedPropertyList($items))->toBe($expected);
})->with([
    'compact then compact' => [
        [['@id' => 'https://example.test/#c', 'name' => 'A'], ['@id' => 'https://example.test/#c', 'name' => 'B']],
        [['@id' => 'https://example.test/#c', 'name' => 'A']],
    ],
    'full then compact' => [
        [['@id' => 'https://example.test/#c', '@type' => 'Thing'], ['@id' => 'https://example.test/#c']],
        [['@id' => 'https://example.test/#c', '@type' => 'Thing']],
    ],
    'compact then full' => [
        [['@id' => 'https://example.test/#c'], ['@id' => 'https://example.test/#c', '@type' => 'Thing']],
        [['@id' => 'https://example.test/#c', '@type' => 'Thing']],
    ],
    'scalar key then a map' => [
        ['x', ['@id' => 'https://example.test/#c', '@type' => 'Thing']],
        ['x', ['@id' => 'https://example.test/#c', '@type' => 'Thing']],
    ],
]);

it('keys list items so distinct items stay and identical ones collapse', function (array $items, array $expected) {
    $deduper = new SchemaJsonLdAtIdDeduplication;

    /** @var array<int, mixed> $items */
    expect($deduper->dedupeResolvedPropertyList($items))->toBe($expected);
})->with([
    'distinct scalars' => [['a', 'b', 1, true, 1.5], ['a', 'b', 1, true, 1.5]],
    'distinct @id values' => [
        [['@id' => 'https://example.test/#1'], ['@id' => 'https://example.test/#2']],
        [['@id' => 'https://example.test/#1'], ['@id' => 'https://example.test/#2']],
    ],
    'an @id spelling a scalar key' => [
        ['x', ['@id' => serialize('x')]],
        ['x', ['@id' => serialize('x')]],
    ],
    'a non-string @id is not an @id key' => [
        [['@id' => 5, 'name' => 'n'], ['@id' => '5']],
        [['@id' => 5, 'name' => 'n'], ['@id' => '5']],
    ],
    'an empty @id is not an @id key' => [
        [['@id' => '', 'name' => 'a'], ['@id' => '', 'name' => 'b']],
        [['@id' => '', 'name' => 'a'], ['@id' => '', 'name' => 'b']],
    ],
    'distinct arrays without @id' => [[['a'], ['b']], [['a'], ['b']]],
    'identical arrays without @id' => [[['a'], ['a'], ['b']], [['a'], ['b']]],
    'objects are never keyed' => [
        [$object = new stdClass, $object],
        [$object, $object],
    ],
]);

it('keys arrays JSON cannot encode by their serialized form', function () {
    $deduper = new SchemaJsonLdAtIdDeduplication;

    $out = $deduper->dedupeResolvedPropertyList([[NAN], [NAN], [INF]]);

    expect($out)->toHaveCount(2)
        ->and(data_get($out, '0.0'))->toBeNan()
        ->and($out[1])->toBe([INF]);
});

it('compacts only an @id already recorded as a full node', function () {
    $deduper = new SchemaJsonLdAtIdDeduplication;
    $emitted = ['' => true, 'https://example.test/#a' => true];

    expect($deduper->compactToReferenceIfFullNodeAlreadyEmitted($emitted, ''))->toBeNull()
        ->and($deduper->compactToReferenceIfFullNodeAlreadyEmitted($emitted, 'https://example.test/#b'))->toBeNull()
        ->and($deduper->compactToReferenceIfFullNodeAlreadyEmitted($emitted, 'https://example.test/#a'))
        ->toBe(['@id' => 'https://example.test/#a']);
});

it('records only full nodes with a non-empty string @id', function (?array $node, array $expected) {
    $deduper = new SchemaJsonLdAtIdDeduplication;
    $emitted = [];

    /** @var array<string, mixed>|null $node */
    $deduper->recordFullSchemaObjectNodeEmitted($emitted, $node);

    expect($emitted)->toBe($expected);
})->with([
    'null' => [null, []],
    'reference only' => [['@id' => 'https://example.test/#a'], []],
    'no @id' => [['@type' => 'Thing'], []],
    'integer @id' => [['@id' => 5, '@type' => 'Thing'], []],
    'empty @id' => [['@id' => '', '@type' => 'Thing'], []],
    'full node' => [['@id' => 'https://example.test/#a', '@type' => 'Thing'], ['https://example.test/#a' => true]],
]);

it('registers a first full node as true and ignores unusable @id values in dedupeNodeByAtId', function () {
    $deduper = new SchemaJsonLdAtIdDeduplication;
    $emitted = [];

    $deduper->dedupeNodeByAtId(['@id' => '', '@type' => 'Thing'], $emitted);
    $deduper->dedupeNodeByAtId(['@id' => 5, '@type' => 'Thing'], $emitted);
    $deduper->dedupeNodeByAtId(['@id' => 'https://example.test/#a', '@type' => 'Thing'], $emitted);

    expect($emitted)->toBe(['https://example.test/#a' => true]);
});

it('compacts a repeated bare reference to its @id alone in dedupeNodeByAtId', function () {
    $deduper = new SchemaJsonLdAtIdDeduplication;
    $emitted = ['https://example.test/#a' => true];
    $decodePass = [];

    $out = $deduper->dedupeNodeByAtId(
        ['@id' => 'https://example.test/#a', 'name' => 'Extra'],
        $emitted,
        $decodePass
    );

    expect($out)->toBe(['@id' => 'https://example.test/#a'])
        ->and($decodePass)->toBe([]);
});

it('records retention and leaves the decode map alone for a new full node', function () {
    $deduper = new SchemaJsonLdAtIdDeduplication;
    $emitted = ['https://example.test/#pre' => true];
    $decodePass = [];

    $deduper->dedupeNodeByAtId(
        [
            ['@id' => 'https://example.test/#new', '@type' => 'Thing'],
            ['@id' => 'https://example.test/#pre', '@type' => 'Thing'],
        ],
        $emitted,
        $decodePass
    );

    expect($decodePass)->toBe(['https://example.test/#pre' => true])
        ->and($emitted)->toBe([
            'https://example.test/#pre' => true,
            'https://example.test/#new' => true,
        ]);
});

it('tracks new full nodes in the local scratch map when not persisting', function () {
    $deduper = new SchemaJsonLdAtIdDeduplication;
    $emitted = [];
    $decodePass = null;
    $scratch = [];
    $node = ['@id' => 'https://example.test/#a', '@type' => 'Thing'];

    $first = $deduper->dedupeNodeByAtId($node, $emitted, $decodePass, false, $scratch);
    $second = $deduper->dedupeNodeByAtId($node, $emitted, $decodePass, false, $scratch);

    expect($first)->toBe($node)
        ->and($second)->toBe(['@id' => 'https://example.test/#a'])
        ->and($scratch)->toBe(['https://example.test/#a' => true])
        ->and($emitted)->toBe([]);
});

it('records nothing when not persisting and no scratch map is given', function () {
    $deduper = new SchemaJsonLdAtIdDeduplication;
    $emitted = [];
    $decodePass = null;
    $scratch = null;

    $deduper->dedupeNodeByAtId(['@id' => 'https://example.test/#a', '@type' => 'Thing'], $emitted, $decodePass, false, $scratch);

    expect($emitted)->toBe([])
        ->and($scratch)->toBeNull();
});

it('retains one full copy then compacts within one shared-map decode', function () {
    $deduper = new SchemaJsonLdAtIdDeduplication;
    $emitted = [];
    $node = ['@id' => 'https://example.test/#a', '@type' => 'Thing'];

    $out = $deduper->dedupeWithSharedEmitMapAndDecodeFirstFullRetention([$node, $node, $node], $emitted);

    expect($out)->toBe([$node, $node, ['@id' => 'https://example.test/#a']])
        ->and($emitted)->toBe([]);
});

it('dedupes nested nodes under every key after @id', function () {
    $deduper = new SchemaJsonLdAtIdDeduplication;
    $child = ['@id' => 'https://example.test/#b', '@type' => 'Thing'];

    $out = $deduper->dedupeWithFreshEmitMap([
        '@id' => 'https://example.test/#a',
        '@type' => 'Thing',
        'knows' => [$child, $child],
    ]);

    expect($out)->toBe([
        '@id' => 'https://example.test/#a',
        '@type' => 'Thing',
        'knows' => [$child, ['@id' => 'https://example.test/#b']],
    ]);
});

it('records full-node @id values at every depth of a fragment', function () {
    $deduper = new SchemaJsonLdAtIdDeduplication;
    $materialized = [];

    $deduper->recordMaterializedFullNodeAtIdsFromFragment([
        '@id' => 'https://example.test/#a',
        '@type' => 'Thing',
        'about' => ['@id' => 'https://example.test/#b', '@type' => 'Thing'],
        'knows' => [
            ['@id' => 'https://example.test/#c', '@type' => 'Thing'],
            ['@id' => 'https://example.test/#ref'],
            ['@id' => '', '@type' => 'Thing'],
            ['@id' => 5, '@type' => 'Thing'],
        ],
    ], $materialized);

    expect($materialized)->toBe([
        'https://example.test/#a' => true,
        'https://example.test/#b' => true,
        'https://example.test/#c' => true,
    ]);
});

it('adds new ids from the roots in syncMaterializedFullNodeAtIdsToUnionOfTreeRoots', function () {
    $deduper = new SchemaJsonLdAtIdDeduplication;
    $materialized = [];

    $deduper->syncMaterializedFullNodeAtIdsToUnionOfTreeRoots(
        [['@id' => 'https://example.test/#new', '@type' => 'Thing']],
        $materialized
    );

    expect($materialized)->toBe(['https://example.test/#new' => true]);
});
