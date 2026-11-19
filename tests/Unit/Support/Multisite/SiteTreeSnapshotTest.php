<?php

declare(strict_types=1);

use App\Support\Multisite\SiteTreeSnapshot;

/**
 * The search tiers a multisite tree yields (#480), on synthetic trees.
 *
 * The real tree's figures are in tests/Feature/Support/Multisite/SiteTreeCacheTest.php, which
 * needs the `content/` submodule. These cases run everywhere.
 *
 * @command  php artisan test --compact tests/Unit/Support/Multisite/SiteTreeSnapshotTest.php
 */

/**
 * A tree with a root, two nested tiers, an ordinary site, and a tier subject with nothing below it.
 *
 *     root (root)
 *     ├── school (major_entity)
 *     │   ├── dept (sub_entity)
 *     │   └── institute (major_entity)
 *     │       └── lab (sub_entity)
 *     │           └── bench (non_entity)
 *     ├── lonely (major_entity, no descendants)
 *     │   (nothing)
 *     ├── office (sub_entity)
 *     │   └── team (non_entity)
 *     └── network (root, inherited)
 *         └── blog (root, inherited)
 */
function siteTreeFixture(): SiteTreeSnapshot
{
    return new SiteTreeSnapshot(
        [
            'root' => null,
            'school' => 'root',
            'dept' => 'school',
            'institute' => 'school',
            'lab' => 'institute',
            'bench' => 'lab',
            'lonely' => 'root',
            'office' => 'root',
            'team' => 'office',
            'network' => 'root',
            'blog' => 'network',
        ],
        [
            'root' => 'root',
            'school' => 'major_entity',
            'dept' => 'sub_entity',
            'institute' => 'major_entity',
            'lab' => 'sub_entity',
            'bench' => 'non_entity',
            'lonely' => 'major_entity',
            'office' => 'sub_entity',
            'team' => 'non_entity',
            'network' => 'root',
            'blog' => 'root',
        ],
    );
}

it('finds every site below a site, at any depth, nearest first', function (): void {
    $tree = siteTreeFixture();

    expect($tree->descendants('school'))->toBe(['dept', 'institute', 'lab', 'bench'])
        ->and($tree->descendants('root'))->toBe(['school', 'lonely', 'office', 'network', 'dept', 'institute', 'team', 'blog', 'lab', 'bench'])
        ->and($tree->descendants('bench'))->toBe([])
        ->and($tree->descendants('nowhere'))->toBe([]);
});

it('calls a site a tier only for a root or major_entity subject with a site below it', function (string $handle, bool $expected): void {
    expect(siteTreeFixture()->isTier($handle))->toBe($expected);
})->with([
    'root with descendants' => ['root', true],
    'major_entity with descendants' => ['school', true],
    'nested major_entity' => ['institute', true],
    'inherited root with a child' => ['network', true],
    'major_entity with none below' => ['lonely', false],
    'sub_entity with a child' => ['office', false],
    'inherited root with no child' => ['blog', false],
    'leaf' => ['lab', false],
    'unknown site' => ['nowhere', false],
]);

it('resolves the nearest tier above a site, and none when that tier is a root', function (string $handle, ?string $expected): void {
    expect(siteTreeFixture()->nearestTier($handle))->toBe($expected);
})->with([
    'below a tier' => ['dept', 'school'],
    'below a nested tier, not the outer one' => ['lab', 'institute'],
    'below an ordinary site below a tier' => ['bench', 'institute'],
    'a tier below a tier' => ['institute', 'school'],
    'below an inherited-root tier' => ['blog', 'network'],
    'a tier below the root' => ['school', null],
    'an ordinary site below the root' => ['office', null],
    'below an ordinary site, under the root' => ['team', null],
    'tier subject with nothing below' => ['lonely', null],
    'the root' => ['root', null],
    'unknown site' => ['nowhere', null],
]);

it('covers a tier\'s whole subtree from "This site", and any other site alone', function (): void {
    $tree = siteTreeFixture();

    expect($tree->covered('school'))->toBe(['school', 'dept', 'institute', 'lab', 'bench'])
        ->and($tree->covered('institute'))->toBe(['institute', 'lab', 'bench'])
        ->and($tree->covered('lonely'))->toBe(['lonely'])
        ->and($tree->covered('dept'))->toBe(['dept'])
        ->and($tree->covered('root'))->toHaveCount(11);
});

it('walks a tree with a loop in it without hanging', function (): void {
    // a and b name each other as origin; c names itself
    $tree = new SiteTreeSnapshot(
        ['a' => 'b', 'b' => 'a', 'c' => 'c'],
        ['a' => 'major_entity', 'b' => 'major_entity', 'c' => 'major_entity'],
    );

    expect($tree->descendants('a'))->toBe(['b'])
        ->and($tree->nearestTier('a'))->toBe('b')
        ->and($tree->descendants('c'))->toBe([])
        ->and($tree->isTier('c'))->toBeFalse()
        ->and($tree->nearestTier('c'))->toBeNull();
});

it('walks on past a site that closes a loop to the rest of the tree', function (): void {
    // a and b name each other; c sits below b
    $tree = new SiteTreeSnapshot(['a' => 'b', 'b' => 'a', 'c' => 'b'], ['a' => 'sub_entity', 'b' => 'sub_entity', 'c' => 'sub_entity']);

    expect($tree->descendants('a'))->toBe(['b', 'c']);
});

it('stops at the site itself when its ancestors loop back to it', function (): void {
    // a is a tier whose only ancestor, b, names a as its own origin
    $tree = new SiteTreeSnapshot(['a' => 'b', 'b' => 'a'], ['a' => 'major_entity', 'b' => 'sub_entity']);

    expect($tree->nearestTier('a'))->toBeNull();
});

it('tells apart handles that only compare equal as numbers', function (): void {
    // `10.0` and `1e1` are the same number, and different sites; neither becomes an integer key
    $tree = new SiteTreeSnapshot(
        ['root' => null, '10.0' => 'root', '1e1' => '10.0', 'x' => '1e1'],
        ['root' => 'root', '10.0' => 'major_entity', '1e1' => 'major_entity', 'x' => 'sub_entity'],
    );

    expect($tree->descendants('10.0'))->toBe(['1e1', 'x'])
        ->and($tree->nearestTier('x'))->toBe('1e1')
        ->and($tree->nearestTier('1e1'))->toBe('10.0');
});
