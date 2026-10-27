<?php

declare(strict_types=1);

use App\Services\PageBuilder\SideBySideAlignmentResolver;

/**
 * Build a Side-by-Side replicator block array.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function sxsResolverBlock(string $id, string $position, array $overrides = []): array
{
    return array_merge([
        'id' => $id,
        'type' => SideBySideAlignmentResolver::SET_TYPE,
        'enabled' => true,
        'image_position' => $position,
    ], $overrides);
}

/**
 * Build a non-Side-by-Side replicator block array.
 *
 * @return array<string, mixed>
 */
function sxsResolverOtherBlock(string $id, string $type = 'uamsweb_block_content'): array
{
    return ['id' => $id, 'type' => $type, 'enabled' => true];
}

beforeEach(function () {
    $this->resolver = new SideBySideAlignmentResolver;
});

it('passes explicit alignments through unchanged', function () {
    /** @var SideBySideAlignmentResolver $resolver */
    $resolver = $this->resolver;
    $map = $resolver->resolve([
        sxsResolverBlock('a', 'left'),
        sxsResolverBlock('b', 'right'),
    ]);

    expect($map)->toBe(['a' => 'left', 'b' => 'right']);
});

it('renders a standalone auto block on its stated default side', function () {
    /** @var SideBySideAlignmentResolver $resolver */
    $resolver = $this->resolver;
    expect($resolver->resolve([sxsResolverBlock('a', 'auto_default_left')]))
        ->toBe(['a' => 'left']);

    expect($resolver->resolve([sxsResolverBlock('a', 'auto_default_right')]))
        ->toBe(['a' => 'right']);
});

it('alternates a run of consecutive auto blocks (default left → left, right, left)', function () {
    /** @var SideBySideAlignmentResolver $resolver */
    $resolver = $this->resolver;
    $map = $resolver->resolve([
        sxsResolverBlock('a', 'auto_default_left'),
        sxsResolverBlock('b', 'auto_default_left'),
        sxsResolverBlock('c', 'auto_default_left'),
    ]);

    expect($map)->toBe(['a' => 'left', 'b' => 'right', 'c' => 'left']);
});

it('alternates a run of consecutive auto blocks (default right → right, left, right)', function () {
    /** @var SideBySideAlignmentResolver $resolver */
    $resolver = $this->resolver;
    $map = $resolver->resolve([
        sxsResolverBlock('a', 'auto_default_right'),
        sxsResolverBlock('b', 'auto_default_right'),
        sxsResolverBlock('c', 'auto_default_right'),
    ]);

    expect($map)->toBe(['a' => 'right', 'b' => 'left', 'c' => 'right']);
});

it('flips an auto block relative to a preceding explicit block', function () {
    /** @var SideBySideAlignmentResolver $resolver */
    $resolver = $this->resolver;
    // The default (left) would have collided with the preceding explicit-left
    // block; the auto rule flips it to the opposite side instead.
    $map = $resolver->resolve([
        sxsResolverBlock('a', 'left'),
        sxsResolverBlock('b', 'auto_default_left'),
    ]);

    expect($map)->toBe(['a' => 'left', 'b' => 'right']);
});

it('resets the run when a non-Side-by-Side block interrupts it', function () {
    /** @var SideBySideAlignmentResolver $resolver */
    $resolver = $this->resolver;
    $map = $resolver->resolve([
        sxsResolverBlock('a', 'auto_default_left'),
        sxsResolverOtherBlock('spacer'),
        sxsResolverBlock('b', 'auto_default_left'),
    ]);

    // `b` is not immediately preceded by a Side-by-Side block, so it uses its
    // default side rather than flipping.
    expect($map)->toBe(['a' => 'left', 'b' => 'left']);
});

it('treats a disabled block as absent so the run stays adjacent', function () {
    /** @var SideBySideAlignmentResolver $resolver */
    $resolver = $this->resolver;
    $map = $resolver->resolve([
        sxsResolverBlock('a', 'auto_default_left'),
        sxsResolverBlock('disabled', 'left', ['enabled' => false]),
        sxsResolverBlock('b', 'auto_default_left'),
    ]);

    // The disabled block is not rendered; `a` and `b` are visually adjacent, so
    // `b` flips. The disabled block never enters the map.
    expect($map)->toBe(['a' => 'left', 'b' => 'right']);
});

it('flips an auto block relative to the preceding block regardless of its own default', function () {
    /** @var SideBySideAlignmentResolver $resolver */
    $resolver = $this->resolver;
    // Preceding side is right; an auto_default_right block still flips to left.
    $map = $resolver->resolve([
        sxsResolverBlock('a', 'right'),
        sxsResolverBlock('b', 'auto_default_right'),
    ]);

    expect($map)->toBe(['a' => 'right', 'b' => 'left']);
});

it('omits blocks without a usable id from the map but still tracks adjacency', function () {
    /** @var SideBySideAlignmentResolver $resolver */
    $resolver = $this->resolver;
    $map = $resolver->resolve([
        sxsResolverBlock('a', 'auto_default_left'),
        sxsResolverBlock('', 'auto_default_left'),
        sxsResolverBlock('c', 'auto_default_left'),
    ]);

    // The id-less middle block is not addressable, so it is omitted; but it still
    // occupies the run, so `a` = left, (middle) = right, `c` = left.
    expect($map)->toBe(['a' => 'left', 'c' => 'left']);
});

it('ignores malformed non-array entries and breaks the run', function () {
    /** @var SideBySideAlignmentResolver $resolver */
    $resolver = $this->resolver;
    $map = $resolver->resolve([
        sxsResolverBlock('a', 'auto_default_left'),
        'not-a-block',
        sxsResolverBlock('b', 'auto_default_left'),
    ]);

    expect($map)->toBe(['a' => 'left', 'b' => 'left']);
});

it('returns an empty map when there are no Side-by-Side blocks', function () {
    /** @var SideBySideAlignmentResolver $resolver */
    $resolver = $this->resolver;
    expect($resolver->resolve([sxsResolverOtherBlock('x'), sxsResolverOtherBlock('y')]))->toBe([]);
    expect($resolver->resolve([]))->toBe([]);
});

it('defaults a Side-by-Side block with a missing position to left', function () {
    /** @var SideBySideAlignmentResolver $resolver */
    $resolver = $this->resolver;
    $block = sxsResolverBlock('a', 'left');
    unset($block['image_position']);

    expect($resolver->resolve([$block]))->toBe(['a' => 'left']);
});

it('treats a Side-by-Side block with no enabled key as enabled', function () {
    /** @var SideBySideAlignmentResolver $resolver */
    $resolver = $this->resolver;
    // Blocks default to enabled when the key is absent, so it must still resolve.
    $block = sxsResolverBlock('a', 'auto_default_left');
    unset($block['enabled']);

    expect($resolver->resolve([$block]))->toBe(['a' => 'left']);
});
