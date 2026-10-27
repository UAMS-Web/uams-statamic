<?php

declare(strict_types=1);

use App\Services\SchemaTemplates\SchemaJsonLdAtIdDeduplication;

/**
 * Unit tests for SchemaJsonLdAtIdDeduplication (shared JSON-LD @id deduplication
 * for schema template output).
 *
 * Mutation: 100% on App\Services\SchemaTemplates\SchemaJsonLdAtIdDeduplication (117 mutants, --covered-only).
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
