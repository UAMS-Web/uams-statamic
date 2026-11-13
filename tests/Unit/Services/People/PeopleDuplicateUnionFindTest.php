<?php

declare(strict_types=1);

use App\Services\People\PeopleDuplicateUnionFind;

covers(PeopleDuplicateUnionFind::class);

/**
 * Behavior of the disjoint set `PeopleDuplicateDetector` clusters duplicate people
 * with (UAMS-Web/uams-statamic#309): every element starts as its own set, `union()`
 * merges two sets, `find()` returns a set's root and flattens the path it walked,
 * and `groups()` lists the members of each set under its root, in the order the
 * elements were given.
 *
 * @command  php artisan test --compact tests/Unit/Services/People/PeopleDuplicateUnionFindTest.php
 */

/**
 * The parent pointers, read through reflection, so the tests can see what
 * path compression wrote.
 *
 * @return array<string, string>
 */
function unionFindParents(PeopleDuplicateUnionFind $sets): array
{
    /** @var array<string, string> */
    return new ReflectionProperty(PeopleDuplicateUnionFind::class, 'parent')->getValue($sets);
}

it('starts every element as its own singleton set', function (): void {
    $sets = new PeopleDuplicateUnionFind(['a', 'b', 'c']);

    expect($sets->find('a'))->toBe('a')
        ->and($sets->find('b'))->toBe('b')
        ->and($sets->groups())->toBe(['a' => ['a'], 'b' => ['b'], 'c' => ['c']]);
});

it('returns an unknown element as its own root without adding it', function (): void {
    $sets = new PeopleDuplicateUnionFind(['a']);

    expect($sets->find('z'))->toBe('z')
        ->and(unionFindParents($sets))->toBe(['a' => 'a']);
});

it('points the first root at the second on a union', function (): void {
    $sets = new PeopleDuplicateUnionFind(['a', 'b']);

    $sets->union('a', 'b');

    expect(unionFindParents($sets))->toBe(['a' => 'b', 'b' => 'b'])
        ->and($sets->find('a'))->toBe('b')
        ->and($sets->groups())->toBe(['b' => ['a', 'b']]);
});

it('leaves the parents alone when both elements already share a root', function (): void {
    $sets = new PeopleDuplicateUnionFind(['a', 'b']);
    $sets->union('a', 'b');

    $sets->union('b', 'a');
    $sets->union('a', 'a');

    expect(unionFindParents($sets))->toBe(['a' => 'b', 'b' => 'b']);
});

it('merges chains transitively into one cluster', function (): void {
    $sets = new PeopleDuplicateUnionFind(['a', 'b', 'c', 'd', 'e']);

    $sets->union('a', 'b');
    $sets->union('c', 'd');
    $sets->union('b', 'd');

    expect($sets->find('a'))->toBe('d')
        ->and($sets->find('c'))->toBe('d')
        ->and($sets->groups())->toBe(['d' => ['a', 'b', 'c', 'd'], 'e' => ['e']]);
});

it('flattens every element on the walked path onto the root', function (): void {
    $sets = new PeopleDuplicateUnionFind(['a', 'b', 'c', 'd']);

    // a -> b -> c -> d, one hop at a time.
    $sets->union('a', 'b');
    $sets->union('b', 'c');
    $sets->union('c', 'd');

    expect(unionFindParents($sets))->toBe(['a' => 'b', 'b' => 'c', 'c' => 'd', 'd' => 'd']);

    expect($sets->find('a'))->toBe('d')
        ->and(unionFindParents($sets))->toBe(['a' => 'd', 'b' => 'd', 'c' => 'd', 'd' => 'd']);
});

it('compresses only the path it walked', function (): void {
    $sets = new PeopleDuplicateUnionFind(['a', 'b', 'c', 'd']);

    $sets->union('a', 'b');
    $sets->union('b', 'c');
    $sets->union('c', 'd');

    expect($sets->find('b'))->toBe('d')
        ->and(unionFindParents($sets))->toBe(['a' => 'b', 'b' => 'd', 'c' => 'd', 'd' => 'd']);
});

it('groups an empty set of elements as no groups', function (): void {
    expect(new PeopleDuplicateUnionFind([])->groups())->toBe([]);
});
