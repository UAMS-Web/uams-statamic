<?php

declare(strict_types=1);

use App\Services\EnumGeneration\Isced\IscedEnumConfig;
use App\Services\EnumGeneration\Isced\IscedHierarchy;

covers(IscedHierarchy::class, IscedEnumConfig::class);

/**
 * The prefix rule the ISCED enum generators build their hierarchy with
 * (UAMS-Web/uams-statamic#309): a code's parent is the code less its last
 * character, until the top tier, whose length is 1 for ISCED-P and ISCED-A and 2
 * for ISCED-F broad fields. Codes sort shorter first, then lexically.
 *
 * @command  php artisan test --compact tests/Unit/Services/EnumGeneration/Isced/IscedHierarchyTest.php
 */
it('strips the last character for a parent, and has none at or above the top tier', function (string $code, int $minTier, ?string $expected): void {
    expect(IscedHierarchy::parentCode($code, $minTier))->toBe($expected);
})->with([
    'ISCED-P tier 3' => ['344', 1, '34'],
    'ISCED-P tier 2' => ['34', 1, '3'],
    'ISCED-P top tier' => ['3', 1, null],
    'ISCED-F narrow field' => ['031', 2, '03'],
    'ISCED-F broad field' => ['03', 2, null],
    'shorter than the top tier' => ['0', 2, null],
    'empty' => ['', 1, null],
]);

it('numbers tiers from 1 at the top tier', function (string $code, int $minTier, int $expected): void {
    expect(IscedHierarchy::tier($code, $minTier))->toBe($expected);
})->with([
    'ISCED-P top' => ['3', 1, 1],
    'ISCED-P second' => ['34', 1, 2],
    'ISCED-P third' => ['344', 1, 3],
    'ISCED-F broad' => ['03', 2, 1],
    'ISCED-F detailed' => ['0311', 2, 3],
]);

it('lists ancestors root first, keeping only codes that exist', function (): void {
    $existing = ['3' => true, '34' => true, '344' => true];
    $partial = ['3' => true, '344' => true];
    $fields = ['03' => true, '031' => true];

    expect(IscedHierarchy::ancestors('3441', 1, $existing))->toBe(['3', '34', '344'])
        ->and(IscedHierarchy::ancestors('3441', 1, $partial))->toBe(['3', '344'])
        ->and(IscedHierarchy::ancestors('3', 1, $existing))->toBe([])
        ->and(IscedHierarchy::ancestors('0311', 2, $fields))->toBe(['03', '031']);
});

it('maps each existing parent to its children, sorted shorter first then lexically', function (): void {
    $codes = ['3', '35', '34', '344', '342', '4', '41', '9'];

    expect(IscedHierarchy::buildChildrenMap($codes, 1))->toBe([
        '3' => ['34', '35'],
        '34' => ['342', '344'],
        '4' => ['41'],
    ]);
});

it('leaves out a child whose parent is not in the list', function (): void {
    expect(IscedHierarchy::buildChildrenMap(['3', '344', '41'], 1))->toBe([])
        ->and(IscedHierarchy::buildChildrenMap([], 1))->toBe([])
        // An orphan ahead of a placed child skips only itself.
        ->and(IscedHierarchy::buildChildrenMap(['3', '344', '35'], 1))->toBe(['3' => ['35']]);
});

it('collects every descendant once, sorted shorter first then lexically', function (): void {
    $children = [
        '3' => ['35', '34'],
        '34' => ['344', '342'],
        '342' => ['3421'],
    ];

    expect(IscedHierarchy::descendants('3', $children))->toBe(['34', '35', '342', '344', '3421'])
        ->and(IscedHierarchy::descendants('34', $children))->toBe(['342', '344', '3421'])
        ->and(IscedHierarchy::descendants('35', $children))->toBe([])
        ->and(IscedHierarchy::descendants('9', $children))->toBe([]);
});

it('keeps walking after skipping a code it has already visited', function (): void {
    // 344 is listed under both 3 and 34, and is popped a second time while 35
    // still waits on the stack.
    $children = ['3' => ['35', '344', '34'], '34' => ['344']];

    expect(IscedHierarchy::descendants('3', $children))->toBe(['34', '35', '344']);
});

it('visits a descendant reachable twice only once', function (): void {
    // A malformed map where 344 is listed under both 3 and 34.
    $children = ['3' => ['34', '344'], '34' => ['344']];

    expect(IscedHierarchy::descendants('3', $children))->toBe(['34', '344']);
});

it('finds integer-keyed and string-keyed codes alike through its own lookups (#685)', function (): void {
    // PHP stores the one-character ISCED-P code '3' as an integer key and keeps the
    // two-character ISCED-F code '09' as a string, so one map holds both.
    $existing = array_fill_keys(['3', '34', '09', '091'], true);
    $children = IscedHierarchy::buildChildrenMap(['3', '34', '341'], 1)
        + IscedHierarchy::buildChildrenMap(['09', '091', '0912'], 2);

    expect(array_map(get_debug_type(...), array_keys($existing)))->toBe(['int', 'int', 'string', 'string'])
        ->and(array_map(get_debug_type(...), array_keys($children)))->toBe(['int', 'int', 'string', 'string'])
        ->and(IscedHierarchy::ancestors('341', 1, $existing))->toBe(['3', '34'])
        ->and(IscedHierarchy::ancestors('0912', 2, $existing))->toBe(['09', '091'])
        ->and(IscedHierarchy::descendants('3', $children))->toBe(['34', '341'])
        ->and(IscedHierarchy::descendants('09', $children))->toBe(['091', '0912']);
});

it('compares by length first, then lexically', function (string $a, string $b, int $sign): void {
    expect(IscedHierarchy::compareCodes($a, $b) <=> 0)->toBe($sign);
})->with([
    'shorter first' => ['9', '10', -1],
    'longer after' => ['10', '9', 1],
    'same length, lexical' => ['34', '35', -1],
    'same length, reversed' => ['35', '34', 1],
    'equal' => ['34', '34', 0],
]);

it('keeps the enum configuration it is given', function (): void {
    $config = new IscedEnumConfig('IscedF2013', 'ISCEDF', 'International Standard Classification of Education: Fields of education and training 2013', 'ISCED-F 2013', 'ISCED-F 2013 publication', 'https://example.org/isced-f', 'notes');

    expect($config->enumClass)->toBe('IscedF2013')
        ->and($config->casePrefix)->toBe('ISCEDF')
        ->and($config->classification)->toBe('International Standard Classification of Education: Fields of education and training 2013')
        ->and($config->classificationShort)->toBe('ISCED-F 2013')
        ->and($config->sourceTitle)->toBe('ISCED-F 2013 publication')
        ->and($config->sourceUrl)->toBe('https://example.org/isced-f')
        ->and($config->emitNotes)->toBe('notes');
});
